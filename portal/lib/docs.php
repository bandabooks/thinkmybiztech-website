<?php
/*
 * Quotations and invoices. Rules that protect the records:
 *  - Amounts are integer cents; totals are always recalculated on the server from the lines.
 *  - Numbers are assigned only when a document is issued, from a locked counter: no gaps, no reuse.
 *  - Issued documents cannot be edited. A wrong invoice is voided (only while nothing is paid) and re-issued.
 *  - VAT is charged only when the business is set up as VAT registered with a VAT number; the rate is frozen on the document.
 *  - Payments cannot exceed the balance; a payment is reversed (with a reason), never deleted.
 *  - These are billing documents. The books are kept in Taxnify: export issued invoices and payments from Admin → Export.
 */
declare(strict_types=1);

const VAT_RATE_BP = 1500; // 15.00% South African standard rate

function company_profile(): array {
    $k = ['company_name' => 'Think Mybiz Tech', 'legal_name' => '', 'reg_no' => '', 'vat_number' => '', 'address' => '48 Rabe Street, Mokopane, Limpopo',
          'email' => 'info@thinkmybiztech.com', 'phone' => '063 621 5642', 'bank_name' => '', 'bank_account_name' => '', 'bank_account_no' => '',
          'bank_branch_code' => '', 'bank_account_type' => ''];
    foreach ($k as $key => $def) $k[$key] = (string)setting('co_' . $key, $def);
    return $k;
}
function vat_on(): bool { return setting('co_vat_registered') === '1' && trim((string)setting('co_vat_number', '')) !== ''; }
function banking_ready(): bool { $c = company_profile(); return $c['bank_name'] !== '' && $c['bank_account_no'] !== ''; }

function doc_label(array $d): string {
    if ($d['kind'] === 'quote') return 'Quotation';
    return (int)$d['vat_registered'] === 1 ? 'Tax invoice' : 'Invoice';
}
const DOC_STATUSES = ['draft' => 'Draft', 'issued' => 'Issued', 'accepted' => 'Accepted', 'declined' => 'Declined', 'paid' => 'Paid', 'part_paid' => 'Part-paid', 'void' => 'Void'];
function doc_status_label(array $d): string {
    if ($d['kind'] === 'invoice' && in_array($d['status'], ['issued', 'part_paid'], true) && $d['due_date'] && $d['due_date'] < date('Y-m-d')) return 'Overdue';
    if ($d['kind'] === 'quote' && $d['status'] === 'issued' && $d['due_date'] && $d['due_date'] < date('Y-m-d')) return 'Expired';
    return DOC_STATUSES[$d['status']] ?? $d['status'];
}
function doc_lines(int $docId): array { return q('SELECT * FROM document_lines WHERE document_id = ? ORDER BY position', [$docId])->fetchAll(); }
function get_doc(int $id, ?int $clientId = null): ?array {
    // Client access: only that client's documents, and never drafts.
    if ($clientId !== null) return row("SELECT * FROM documents WHERE id = ? AND client_id = ? AND status <> 'draft'", [$id, $clientId]);
    return row('SELECT * FROM documents WHERE id = ?', [$id]);
}

/* Parse posted line arrays into validated lines. Returns [lines, error]. */
function parse_lines(array $desc, array $qty, array $price): array {
    $lines = [];
    foreach ($desc as $i => $dRaw) {
        $d = oneline(mb_substr(trim((string)$dRaw), 0, 500));
        $qs = trim((string)($qty[$i] ?? '')); $ps = trim((string)($price[$i] ?? ''));
        if ($d === '' && $qs === '' && $ps === '') continue;
        if ($d === '') return [[], 'Line ' . ($i + 1) . ': enter a description.'];
        $qm = parse_qty($qs === '' ? '1' : $qs);
        if ($qm === null || $qm <= 0) return [[], 'Line ' . ($i + 1) . ': quantity must be a positive number.'];
        $uc = parse_money($ps);
        if ($uc === null || $uc < 0) return [[], 'Line ' . ($i + 1) . ': enter a valid unit price (e.g. 7500 or 7 500.00).'];
        $lines[] = ['description' => $d, 'qty_milli' => $qm, 'unit_cents' => $uc];
    }
    if (!$lines) return [[], 'Add at least one line.'];
    if (count($lines) > 60) return [[], 'A document can have at most 60 lines.'];
    return [$lines, null];
}

/* Write lines and recalculate totals for a draft. Must run inside a transaction. */
function write_lines(int $docId, array $lines, bool $vat): void {
    q('DELETE FROM document_lines WHERE document_id = ?', [$docId]);
    $sub = 0; $vatTot = 0;
    foreach (array_values($lines) as $i => $l) {
        $net = intdiv($l['qty_milli'] * $l['unit_cents'] + 500, 1000);           // round half up to the cent
        $v = $vat ? intdiv($net * VAT_RATE_BP + 5000, 10000) : 0;
        q('INSERT INTO document_lines (document_id, position, description, qty_milli, unit_cents, net_cents, vat_cents) VALUES (?,?,?,?,?,?,?)',
          [$docId, $i + 1, $l['description'], $l['qty_milli'], $l['unit_cents'], $net, $v]);
        $sub += $net; $vatTot += $v;
    }
    q('UPDATE documents SET vat_registered = ?, vat_rate_bp = ?, subtotal_cents = ?, vat_cents = ?, total_cents = ?, updated_at = NOW() WHERE id = ?',
      [$vat ? 1 : 0, $vat ? VAT_RATE_BP : 0, $sub, $vatTot, $sub + $vatTot, $docId]);
}

function save_draft(?int $docId, string $kind, int $clientId, ?int $requestId, array $f, array $lines, int $staffId): int {
    if (!in_array($kind, ['quote', 'invoice'], true)) throw new RuntimeException('Unknown document type.');
    if (!row("SELECT id FROM clients WHERE id = ?", [$clientId])) throw new RuntimeException('Choose a client.');
    if ($requestId !== null && !row('SELECT id FROM requests WHERE id = ? AND (client_id = ? OR client_id IS NULL)', [$requestId, $clientId])) $requestId = null;
    $pdo = db(); $pdo->beginTransaction();
    try {
        if ($docId === null) {
            q("INSERT INTO documents (kind, client_id, request_id, status, title, notes, terms, created_by, created_at, updated_at) VALUES (?,?,?,'draft',?,?,?,?,NOW(),NOW())",
              [$kind, $clientId, $requestId, $f['title'] ?: null, $f['notes'] ?: null, $f['terms'] ?: null, $staffId]);
            $docId = (int)$pdo->lastInsertId();
            audit('staff', $staffId, $kind . '_draft_created', 'document', $docId);
        } else {
            $d = row('SELECT * FROM documents WHERE id = ? FOR UPDATE', [$docId]);
            if (!$d || $d['status'] !== 'draft') throw new RuntimeException('Only drafts can be edited. Issued documents are locked.');
            q('UPDATE documents SET client_id = ?, request_id = ?, title = ?, notes = ?, terms = ?, updated_at = NOW() WHERE id = ?',
              [$clientId, $requestId, $f['title'] ?: null, $f['notes'] ?: null, $f['terms'] ?: null, $docId]);
            audit('staff', $staffId, $kind . '_draft_saved', 'document', $docId);
        }
        write_lines($docId, $lines, vat_on());
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    return $docId;
}

function next_number(string $kind): string {
    $name = $kind === 'quote' ? 'quote' : 'invoice';
    q('INSERT IGNORE INTO counters (name, next_value) VALUES (?, 1)', [$name]);
    $n = (int)val('SELECT next_value FROM counters WHERE name = ? FOR UPDATE', [$name]);
    q('UPDATE counters SET next_value = next_value + 1 WHERE name = ?', [$name]);
    $prefix = $kind === 'quote' ? setting('quote_prefix', 'QUO') : setting('invoice_prefix', 'INV');
    return $prefix . '-' . str_pad((string)$n, 5, '0', STR_PAD_LEFT);
}

function issue_doc(int $docId, int $staffId): array {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $d = row('SELECT * FROM documents WHERE id = ? FOR UPDATE', [$docId]);
        if (!$d || $d['status'] !== 'draft') throw new RuntimeException('Only a draft can be issued.');
        $lines = doc_lines($docId);
        if (!$lines) throw new RuntimeException('Add at least one line before issuing.');
        $vat = vat_on();
        // Re-price VAT with the current registration status so the issued document is consistent.
        write_lines($docId, array_map(fn($l) => ['description' => $l['description'], 'qty_milli' => (int)$l['qty_milli'], 'unit_cents' => (int)$l['unit_cents']], $lines), $vat);
        $d = row('SELECT * FROM documents WHERE id = ?', [$docId]);
        if ((int)$d['total_cents'] <= 0) throw new RuntimeException('The total must be more than R0.00.');
        if ($d['kind'] === 'invoice' && !banking_ready()) throw new RuntimeException('Add your banking details in Settings before issuing invoices, so clients can pay by EFT.');
        $c = row('SELECT full_name, company, email, phone, vat_number, address FROM clients WHERE id = ?', [$d['client_id']]);
        $days = $d['kind'] === 'quote' ? (int)setting('quote_valid_days', '30') : (int)setting('payment_terms_days', '7');
        $number = next_number($d['kind']);
        q("UPDATE documents SET number = ?, status = 'issued', issue_date = CURDATE(), due_date = (CURDATE() + INTERVAL ? DAY),
           client_snapshot = ?, company_snapshot = ?, issued_at = NOW(), updated_at = NOW() WHERE id = ?",
          [$number, max(0, $days), json_encode($c, JSON_UNESCAPED_UNICODE), json_encode(company_profile() + ['vat_on' => $vat], JSON_UNESCAPED_UNICODE), $docId]);
        if ($d['request_id'] && $d['kind'] === 'quote') q("UPDATE requests SET status = 'quoted', updated_at = NOW() WHERE id = ? AND status IN ('new','contacted')", [$d['request_id']]);
        audit('staff', $staffId, $d['kind'] . '_issued', 'document', $docId, ['number' => $number, 'total' => (int)$d['total_cents']]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    $d = row('SELECT * FROM documents WHERE id = ?', [$docId]);
    $c = row('SELECT email, full_name FROM clients WHERE id = ?', [$d['client_id']]);
    $label = doc_label($d);
    send_mail($c['email'], "$label {$d['number']} from Think Mybiz Tech",
        "Hello {$c['full_name']},\n\nYour " . strtolower($label) . " {$d['number']} for " . money((int)$d['total_cents']) . " is ready in your client account.\n\n"
        . ($d['kind'] === 'quote' ? "You can review and accept it online. It is valid until " . fdate($d['due_date']) . ".\n\n" : "Payment is due by " . fdate($d['due_date']) . ". Please use {$d['number']} as your EFT payment reference.\n\n")
        . "View it here: " . HUB_SITE . "/portal/?p=doc&id={$d['id']}\n\nIf you have not set a password yet, use \"Forgot password\" on the log-in page with this email address.");
    return $d;
}

function respond_quote(int $docId, int $clientId, bool $accept, string $name): void {
    $pdo = db(); $pdo->beginTransaction();
    try {
        $d = row("SELECT * FROM documents WHERE id = ? AND client_id = ? AND kind = 'quote' FOR UPDATE", [$docId, $clientId]);
        if (!$d || $d['status'] !== 'issued') throw new RuntimeException('This quotation can no longer be accepted or declined.');
        if ($d['due_date'] && $d['due_date'] < date('Y-m-d')) throw new RuntimeException('This quotation has expired. Send us a message and we will re-issue it.');
        if ($accept && mb_strlen($name) < 3) throw new RuntimeException('Type your full name to accept the quotation.');
        q('UPDATE documents SET status = ?, responded_at = NOW(), responded_name = ?, updated_at = NOW() WHERE id = ?', [$accept ? 'accepted' : 'declined', $name ?: null, $docId]);
        if ($accept && $d['request_id']) q("UPDATE requests SET status = 'won', updated_at = NOW() WHERE id = ?", [$d['request_id']]);
        audit('client', $clientId, $accept ? 'quote_accepted' : 'quote_declined', 'document', $docId, ['number' => $d['number'], 'name' => $name]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    $c = row('SELECT full_name, company, email FROM clients WHERE id = ?', [$clientId]);
    notify_office(($accept ? 'Quotation accepted: ' : 'Quotation declined: ') . $d['number'],
        ($c['company'] ?: $c['full_name']) . ($accept ? " accepted quotation {$d['number']} (" . money((int)$d['total_cents']) . ") as \"$name\"." : " declined quotation {$d['number']}."), $c['email']);
}

/* New draft invoice from an accepted quotation: 100% copies the lines; less creates one deposit line on the quoted amount excluding VAT. */
function invoice_from_quote(int $quoteId, int $percent, int $staffId): int {
    $qd = row("SELECT * FROM documents WHERE id = ? AND kind = 'quote'", [$quoteId]);
    if (!$qd || $qd['status'] !== 'accepted') throw new RuntimeException('Only an accepted quotation can be invoiced.');
    if ($percent < 1 || $percent > 100) throw new RuntimeException('Choose a percentage from 1 to 100.');
    $already = (int)val("SELECT COALESCE(SUM(subtotal_cents),0) FROM documents WHERE source_quote_id = ? AND kind = 'invoice' AND status <> 'void'", [$quoteId]);
    $lines = $percent === 100
        ? array_map(fn($l) => ['description' => $l['description'], 'qty_milli' => (int)$l['qty_milli'], 'unit_cents' => (int)$l['unit_cents']], doc_lines($quoteId))
        : [['description' => "Deposit ({$percent}%) on quotation {$qd['number']}" . ($qd['title'] ? ' — ' . $qd['title'] : ''), 'qty_milli' => 1000,
            'unit_cents' => intdiv((int)$qd['subtotal_cents'] * $percent + 50, 100)]];
    $new = array_sum(array_map(fn($l) => intdiv($l['qty_milli'] * $l['unit_cents'] + 500, 1000), $lines));
    if ($already + $new > (int)$qd['subtotal_cents']) throw new RuntimeException('Invoices for this quotation would exceed the quoted amount (' . money((int)$qd['subtotal_cents']) . ' excl. VAT; already invoiced ' . money($already) . '). Edit the draft lines instead.');
    $id = save_draft(null, 'invoice', (int)$qd['client_id'], $qd['request_id'] ? (int)$qd['request_id'] : null,
        ['title' => $qd['title'], 'notes' => null, 'terms' => setting('invoice_terms')], $lines, $staffId);
    q('UPDATE documents SET source_quote_id = ? WHERE id = ?', [$quoteId, $id]);
    return $id;
}

function refresh_paid(int $docId): void {
    $paid = (int)val('SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE document_id = ? AND reversed_at IS NULL', [$docId]);
    $d = row('SELECT total_cents, status FROM documents WHERE id = ?', [$docId]);
    $status = $paid <= 0 ? 'issued' : ($paid >= (int)$d['total_cents'] ? 'paid' : 'part_paid');
    q('UPDATE documents SET paid_cents = ?, status = ?, updated_at = NOW() WHERE id = ?', [$paid, $status, $docId]);
}

function record_payment(int $docId, int $cents, string $date, string $method, string $ref, int $staffId): void {
    if ($cents <= 0) throw new RuntimeException('Enter the amount received.');
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    if (!$dt || $dt->format('Y-m-d') !== $date || $date > date('Y-m-d')) throw new RuntimeException('Enter the date the money was received (not in the future).');
    $pdo = db(); $pdo->beginTransaction();
    try {
        $d = row("SELECT * FROM documents WHERE id = ? AND kind = 'invoice' FOR UPDATE", [$docId]);
        if (!$d || !in_array($d['status'], ['issued', 'part_paid'], true)) throw new RuntimeException('Payments can only be recorded on an issued, unpaid invoice.');
        $balance = (int)$d['total_cents'] - (int)$d['paid_cents'];
        if ($cents > $balance) throw new RuntimeException('That is more than the balance of ' . money($balance) . '. Record only what applies to this invoice.');
        q('INSERT INTO payments (document_id, amount_cents, paid_on, method, reference, recorded_by, created_at) VALUES (?,?,?,?,?,?,NOW())', [$docId, $cents, $date, $method, $ref ?: null, $staffId]);
        refresh_paid($docId);
        audit('staff', $staffId, 'payment_recorded', 'document', $docId, ['amount' => $cents, 'date' => $date, 'method' => $method, 'ref' => $ref]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
}

function reverse_payment(int $paymentId, string $reason, int $staffId): int {
    if (mb_strlen($reason) < 4) throw new RuntimeException('Give a reason for reversing the payment.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        $p = row('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$p || $p['reversed_at']) throw new RuntimeException('Payment not found or already reversed.');
        row('SELECT id FROM documents WHERE id = ? FOR UPDATE', [$p['document_id']]);
        q('UPDATE payments SET reversed_at = NOW(), reverse_reason = ? WHERE id = ?', [$reason, $paymentId]);
        refresh_paid((int)$p['document_id']);
        audit('staff', $staffId, 'payment_reversed', 'document', (int)$p['document_id'], ['payment' => $paymentId, 'amount' => (int)$p['amount_cents'], 'reason' => $reason]);
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
    return (int)$p['document_id'];
}

function void_doc(int $docId, string $reason, int $staffId): void {
    if (mb_strlen($reason) < 4) throw new RuntimeException('Give a reason for voiding.');
    $pdo = db(); $pdo->beginTransaction();
    try {
        $d = row('SELECT * FROM documents WHERE id = ? FOR UPDATE', [$docId]);
        if (!$d) throw new RuntimeException('Document not found.');
        if ($d['status'] === 'draft') {   // never numbered: remove it completely
            q('DELETE FROM document_lines WHERE document_id = ?', [$docId]);
            q('DELETE FROM documents WHERE id = ?', [$docId]);
            audit('staff', $staffId, $d['kind'] . '_draft_deleted', 'document', $docId, ['reason' => $reason]);
        } else {
            if ($d['status'] === 'void') throw new RuntimeException('Already void.');
            if ($d['kind'] === 'invoice' && (int)$d['paid_cents'] !== 0) throw new RuntimeException('Reverse the payments on this invoice before voiding it.');
            q("UPDATE documents SET status = 'void', void_reason = ?, updated_at = NOW() WHERE id = ?", [$reason, $docId]);
            audit('staff', $staffId, $d['kind'] . '_voided', 'document', $docId, ['number' => $d['number'], 'reason' => $reason]);
        }
        $pdo->commit();
    } catch (Throwable $t) { $pdo->rollBack(); throw $t; }
}

/* Printable document body (shared by client and admin views). */
function render_doc(array $d): string {
    $co = $d['company_snapshot'] ? json_decode($d['company_snapshot'], true) : company_profile();
    $cl = $d['client_snapshot'] ? json_decode($d['client_snapshot'], true) : row('SELECT full_name, company, email, phone, vat_number, address FROM clients WHERE id = ?', [$d['client_id']]);
    $vat = (int)$d['vat_registered'] === 1;
    $label = doc_label($d);
    $h = '<div class="doc"><div class="doc-head"><div><div class="doc-brand">' . e($co['legal_name'] ?: $co['company_name']) . '</div>';
    if ($co['legal_name'] && $co['legal_name'] !== $co['company_name']) $h .= '<div>Trading as ' . e($co['company_name']) . '</div>';
    if ($co['reg_no']) $h .= '<div>Reg. no. ' . e($co['reg_no']) . '</div>';
    if ($vat && $co['vat_number']) $h .= '<div>VAT no. ' . e($co['vat_number']) . '</div>';
    $h .= '<div>' . e($co['address']) . '</div><div>' . e($co['email']) . ' · ' . e($co['phone']) . '</div></div>';
    $h .= '<div class="doc-title"><h2>' . e($label) . '</h2><div class="doc-no">' . e($d['number'] ?: 'DRAFT — not issued') . '</div>';
    $h .= '<div>Date: ' . e(fdate($d['issue_date'])) . '</div>';
    if ($d['due_date']) $h .= '<div>' . ($d['kind'] === 'quote' ? 'Valid until' : 'Due') . ': ' . e(fdate($d['due_date'])) . '</div>';
    $h .= '<div class="pill s-' . e(strtolower(str_replace(' ', '', doc_status_label($d)))) . '">' . e(doc_status_label($d)) . '</div></div></div>';
    $h .= '<div class="doc-to"><small>' . ($d['kind'] === 'quote' ? 'Prepared for' : 'Bill to') . '</small><strong>' . e($cl['company'] ?: $cl['full_name']) . '</strong>';
    if ($cl['company']) $h .= '<div>Attn: ' . e($cl['full_name']) . '</div>';
    if (!empty($cl['address'])) $h .= '<div>' . e($cl['address']) . '</div>';
    if (!empty($cl['vat_number'])) $h .= '<div>VAT no. ' . e($cl['vat_number']) . '</div>';
    $h .= '<div>' . e($cl['email']) . '</div></div>';
    if ($d['title']) $h .= '<h3 class="doc-subject">' . e($d['title']) . '</h3>';
    $h .= '<table class="doc-lines"><thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Unit price</th>' . ($vat ? '<th class="r">VAT</th>' : '') . '<th class="r">Amount</th></tr></thead><tbody>';
    foreach (doc_lines((int)$d['id']) as $l) {
        $h .= '<tr><td>' . e($l['description']) . '</td><td class="r">' . e(fmt_qty((int)$l['qty_milli'])) . '</td><td class="r">' . e(money((int)$l['unit_cents'])) . '</td>'
            . ($vat ? '<td class="r">' . e(money((int)$l['vat_cents'])) . '</td>' : '') . '<td class="r">' . e(money((int)$l['net_cents'])) . '</td></tr>';
    }
    $h .= '</tbody></table><table class="doc-tot">';
    if ($vat) $h .= '<tr><td>Subtotal (excl. VAT)</td><td>' . e(money((int)$d['subtotal_cents'])) . '</td></tr><tr><td>VAT at ' . e(number_format($d['vat_rate_bp'] / 100, 0)) . '%</td><td>' . e(money((int)$d['vat_cents'])) . '</td></tr>';
    $h .= '<tr class="grand"><td>Total' . ($vat ? ' (incl. VAT)' : '') . '</td><td>' . e(money((int)$d['total_cents'])) . '</td></tr>';
    if ($d['kind'] === 'invoice' && (int)$d['paid_cents'] > 0) $h .= '<tr><td>Paid</td><td>' . e(money((int)$d['paid_cents'])) . '</td></tr><tr class="grand"><td>Balance due</td><td>' . e(money((int)$d['total_cents'] - (int)$d['paid_cents'])) . '</td></tr>';
    $h .= '</table>';
    if ($d['notes']) $h .= '<div class="doc-notes"><small>Notes</small>' . nl2br(e($d['notes'])) . '</div>';
    if ($d['kind'] === 'invoice' && $co['bank_account_no']) {
        $h .= '<div class="doc-bank"><small>Pay by EFT</small><div>Bank: <strong>' . e($co['bank_name']) . '</strong></div><div>Account name: <strong>' . e($co['bank_account_name']) . '</strong></div>'
            . '<div>Account no.: <strong>' . e($co['bank_account_no']) . '</strong>' . ($co['bank_account_type'] ? ' (' . e($co['bank_account_type']) . ')' : '') . '</div>'
            . ($co['bank_branch_code'] ? '<div>Branch code: <strong>' . e($co['bank_branch_code']) . '</strong></div>' : '') . '<div>Reference: <strong>' . e($d['number'] ?: '—') . '</strong></div></div>';
    }
    if ($d['terms']) $h .= '<div class="doc-notes"><small>Terms</small>' . nl2br(e($d['terms'])) . '</div>';
    if ($d['status'] === 'void') $h .= '<p class="notice err">This document was voided: ' . e($d['void_reason']) . '</p>';
    if ($d['kind'] === 'quote' && $d['responded_at']) $h .= '<p class="notice ' . ($d['status'] === 'accepted' ? 'ok' : '') . '">' . ($d['status'] === 'accepted' ? 'Accepted' : 'Declined') . ' online on ' . e(date('j M Y H:i', strtotime($d['responded_at']))) . ($d['responded_name'] ? ' by ' . e($d['responded_name']) : '') . '.</p>';
    return $h . '</div>';
}
