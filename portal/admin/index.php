<?php
/* Think Mybiz Tech Client Hub — admin. Router: /portal/admin/?p=<page> */
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/docs.php';
require __DIR__ . '/../lib/layout.php';
require __DIR__ . '/../lib/social.php';

if (!hub_installed()) redirect('/portal/setup.php');
$p = preg_replace('/[^a-z]/', '', (string)($_GET['p'] ?? ''));
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($isPost) csrf_check();
function go(string $msg, string $to, string $type = 'ok'): never { flash($msg, $type); redirect($to); }
function A(string $p, string $qs = ''): string { return '/portal/admin/?p=' . $p . ($qs !== '' ? '&' . $qs : ''); }

/* ---------- Staff log-in / password ---------- */
if ($p === 'logout') { hub_session(); unset($_SESSION['staff_id']); session_regenerate_id(true); go('Logged out.', A('login')); }
if ($p === 'login' || ($p === '' && !current_staff())) {
    if (current_staff()) redirect(A('home'));
    $err = null;
    if ($isPost) {
        $email = strtolower(oneline(post('email', 190))); $key = 'staff:' . $email . '|' . ip_hash();
        if (too_many($key, 5, 15) || too_many('ip:' . ip_hash(), 30, 15)) $err = 'Too many attempts. Wait 15 minutes.';
        else {
            $s = row('SELECT * FROM staff WHERE email = ? AND active = 1', [$email]);
            if ($s && $s['password_hash'] && password_verify((string)($_POST['password'] ?? ''), $s['password_hash'])) {
                clear_hits($key); login_as('staff', (int)$s['id']);
                q('UPDATE staff SET last_login_at = NOW() WHERE id = ?', [$s['id']]);
                audit('staff', (int)$s['id'], 'login', 'staff', (int)$s['id']);
                redirect(A('home'));
            }
            hit($key); hit('ip:' . ip_hash()); $err = 'Email or password not correct.';
            audit('public', null, 'staff_login_failed', null, null, ['email' => $email]);
        }
    }
    page_top('Admin log-in', 'admin');
    echo '<div class="hub-card narrow"><h1>Admin log-in</h1>' . ($err ? '<p class="notice err">' . e($err) . '</p>' : '')
       . '<form method="post" class="form" action="' . A('login') . '">' . csrf_field() . '<div><label for="e">Email</label><input id="e" name="email" type="email" required autocomplete="username"></div>'
       . '<div><label for="pw">Password</label><input id="pw" name="password" type="password" required autocomplete="current-password"></div><button class="btn btn-primary">Log in</button></form>'
       . '<p class="small"><a href="' . A('forgot') . '">Forgot password / first-time set-up</a></p></div>';
    page_end(); exit;
}
if ($p === 'forgot') {
    if ($isPost) {
        if (!too_many('sforgot:' . ip_hash(), 5, 60)) {
            hit('sforgot:' . ip_hash());
            $s = row('SELECT * FROM staff WHERE email = ? AND active = 1', [strtolower(oneline(post('email', 190)))]);
            if ($s) { $t = make_token('staff_reset', (int)$s['id'], 2); send_mail($s['email'], 'Admin password — Think Mybiz Tech', "Set your admin password (valid 2 hours):\n" . HUB_SITE . "/portal/admin/?p=reset&t=$t"); }
        }
        go('If that address belongs to a staff member, a link has been sent.', A('login'));
    }
    page_top('Admin password', 'admin');
    echo '<div class="hub-card narrow"><h1>Set or reset admin password</h1><form method="post" class="form">' . csrf_field() . '<div><label for="e">Staff email</label><input id="e" name="email" type="email" required></div><button class="btn btn-primary">Send link</button></form></div>';
    page_end(); exit;
}
if ($p === 'reset') {
    $t = (string)($_GET['t'] ?? $_POST['t'] ?? ''); $err = null;
    if ($isPost) {
        $pw = (string)($_POST['password'] ?? '');
        if ($m = valid_password($pw)) $err = $m;
        elseif (!hash_equals($pw, (string)($_POST['password2'] ?? ''))) $err = 'Passwords do not match.';
        else {
            $sid = use_token('staff_reset', $t);
            if (!$sid) go('Link invalid or expired.', A('forgot'), 'err');
            q('UPDATE staff SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $sid]);
            audit('staff', $sid, 'password_set', 'staff', $sid);
            login_as('staff', $sid); go('Password saved.', A('home'));
        }
    }
    page_top('Choose password', 'admin');
    echo '<div class="hub-card narrow"><h1>Choose your admin password</h1>' . ($err ? '<p class="notice err">' . e($err) . '</p>' : '') . '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="t" value="' . e($t) . '">'
       . '<div><label>New password (10+ characters)</label><input name="password" type="password" minlength="10" required autocomplete="new-password"></div><div><label>Repeat</label><input name="password2" type="password" minlength="10" required autocomplete="new-password"></div><button class="btn btn-primary">Save</button></form></div>';
    page_end(); exit;
}

$S = require_staff();
$sid = (int)$S['id'];
$isAdmin = $S['role'] === 'admin';
if ($p === '') $p = 'home';
publish_due_social(2);   // fallback when the cron job is not set up

function clients_opts(?int $sel): string {
    $h = '<option value="">Choose client…</option>';
    foreach (q('SELECT id, full_name, company, email FROM clients ORDER BY COALESCE(company, full_name)')->fetchAll() as $c)
        $h .= '<option value="' . $c['id'] . '"' . ($sel === (int)$c['id'] ? ' selected' : '') . '>' . e(($c['company'] ? $c['company'] . ' — ' : '') . $c['full_name'] . ' (' . $c['email'] . ')') . '</option>';
    return $h;
}
function doc_rows(array $rows): string {
    if (!$rows) return empty_state('Nothing here yet.');
    $h = '<table class="tbl"><thead><tr><th>Number</th><th>Client</th><th>Date</th><th class="r">Total</th><th class="r">Balance</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $d) $h .= '<tr><td><a href="' . A('doc', 'id=' . $d['id']) . '">' . e($d['number'] ?: 'Draft #' . $d['id']) . '</a></td><td>' . e($d['company'] ?: $d['full_name']) . '</td><td>' . e(fdate($d['issue_date'] ?: $d['created_at'])) . '</td><td class="r">' . e(money((int)$d['total_cents'])) . '</td><td class="r">' . ($d['kind'] === 'invoice' && $d['status'] !== 'void' && $d['status'] !== 'draft' ? e(money((int)$d['total_cents'] - (int)$d['paid_cents'])) : '') . '</td><td>' . status_pill(doc_status_label($d)) . '</td></tr>';
    return $h . '</tbody></table>';
}
function req_rows(array $rows): string {
    if (!$rows) return empty_state('No requests.');
    $h = '<table class="tbl"><thead><tr><th>Ref</th><th>Contact</th><th>Service</th><th>Source</th><th>Received</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $r) $h .= '<tr><td><a href="' . A('request', 'id=' . $r['id']) . '">' . e($r['ref']) . '</a></td><td>' . e($r['company'] ?: $r['contact_name']) . '<div class="small muted">' . e($r['contact_email']) . '</div></td><td>' . e($r['service']) . '</td><td>' . e($r['source'] === 'website_form' ? 'Contact form' : ($r['source'] === 'portal' ? 'Start a project' : 'Admin')) . ($r['utm_source'] ? '<div class="small muted">' . e($r['utm_source'] . ($r['utm_campaign'] ? ' / ' . $r['utm_campaign'] : '')) . '</div>' : '') . '</td><td>' . e(date('j M H:i', strtotime($r['created_at']))) . '</td><td>' . status_pill(REQUEST_STATUSES[$r['status']]) . '</td></tr>';
    return $h . '</tbody></table>';
}

/* ---------- Dashboard ---------- */
if ($p === 'home') {
    $k = row("SELECT
      (SELECT COUNT(*) FROM requests WHERE status = 'new') new_req,
      (SELECT COUNT(*) FROM requests WHERE created_at >= (NOW() - INTERVAL 30 DAY)) req30,
      (SELECT COUNT(*) FROM requests WHERE status IN ('new','contacted','quoted')) open_req,
      (SELECT COALESCE(SUM(total_cents),0) FROM documents WHERE kind='quote' AND status='issued' AND due_date >= CURDATE()) quotes_open,
      (SELECT COALESCE(SUM(total_cents - paid_cents),0) FROM documents WHERE kind='invoice' AND status IN ('issued','part_paid')) receivable,
      (SELECT COALESCE(SUM(total_cents - paid_cents),0) FROM documents WHERE kind='invoice' AND status IN ('issued','part_paid') AND due_date < CURDATE()) overdue,
      (SELECT COALESCE(SUM(amount_cents),0) FROM payments WHERE reversed_at IS NULL AND paid_on >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) paid_month,
      (SELECT COUNT(*) FROM messages WHERE sender='client' AND read_at IS NULL) unread,
      (SELECT COUNT(*) FROM clients) clients");
    page_top('Dashboard', 'admin', $S, 'home');
    $warn = [];
    if (!banking_ready()) $warn[] = 'Add banking details in <a href="' . A('settings') . '">Settings</a> before issuing invoices.';
    if (setting('co_legal_name') === null) $warn[] = 'Add the legal entity name and registration number in <a href="' . A('settings') . '">Settings</a> so quotations identify the contracting entity.';
    foreach ($warn as $w) echo '<div class="notice">' . $w . '</div>';
    echo '<div class="hub-bar"><h1 class="hub-h1">Dashboard</h1><div class="btnrow"><a class="btn btn-primary btn-sm" href="' . A('edit', 'kind=quote') . '">New quotation</a><a class="btn btn-outline btn-sm" href="' . A('edit', 'kind=invoice') . '">New invoice</a></div></div>'
       . '<div class="kpis">' . kpi('New requests', (string)$k['new_req'], $k['req30'] . ' in last 30 days', A('requests', 'status=new')) . kpi('Open pipeline', (string)$k['open_req'], 'new, in discussion, quoted', A('requests'))
       . kpi('Quotes awaiting reply', money((int)$k['quotes_open']), '', A('quotes')) . kpi('Owed to us', money((int)$k['receivable']), 'overdue ' . money((int)$k['overdue']), A('invoices'))
       . kpi('Received this month', money((int)$k['paid_month'])) . kpi('Unread messages', (string)$k['unread'], $k['clients'] . ' clients', A('messages')) . '</div>';
    echo '<div class="hub-grid2"><section class="hub-card"><h2>Latest requests</h2>' . req_rows(q('SELECT * FROM requests ORDER BY created_at DESC LIMIT 8')->fetchAll()) . '</section>'
       . '<section class="hub-card"><h2>Recent documents</h2>' . doc_rows(q('SELECT d.*, c.full_name, c.company FROM documents d JOIN clients c ON c.id = d.client_id ORDER BY d.updated_at DESC LIMIT 8')->fetchAll()) . '</section></div>';
    page_end(); exit;
}

/* ---------- Requests (leads) ---------- */
if ($p === 'requests') {
    $st = (string)($_GET['status'] ?? '');
    $rows = array_key_exists($st, REQUEST_STATUSES) ? q('SELECT * FROM requests WHERE status = ? ORDER BY created_at DESC LIMIT 300', [$st])->fetchAll() : q('SELECT * FROM requests ORDER BY created_at DESC LIMIT 300')->fetchAll();
    page_top('Requests', 'admin', $S, 'requests');
    echo '<div class="hub-bar"><h1 class="hub-h1">Requests &amp; leads</h1><a class="btn btn-outline btn-sm" href="' . A('export', 'what=requests') . '">Export CSV</a></div><div class="tabs"><a href="' . A('requests') . '"' . ($st === '' ? ' class="on"' : '') . '>All</a>';
    foreach (REQUEST_STATUSES as $k => $t) echo '<a href="' . A('requests', 'status=' . $k) . '"' . ($st === $k ? ' class="on"' : '') . '>' . e($t) . '</a>';
    echo '</div><section class="hub-card">' . req_rows($rows) . '</section>';
    page_end(); exit;
}
if ($p === 'request') {
    $r = row('SELECT * FROM requests WHERE id = ?', [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$r) go('Request not found.', A('requests'), 'err');
    $rid = (int)$r['id'];
    if ($isPost) {
        $a = post('action', 20);
        if ($a === 'status' && array_key_exists(post('status', 20), REQUEST_STATUSES)) {
            q('UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?', [post('status', 20), $rid]);
            audit('staff', $sid, 'request_status', 'request', $rid, ['status' => post('status', 20)]);
            go('Status updated.', A('request', 'id=' . $rid));
        }
        if ($a === 'link') {
            $c = row('SELECT id FROM clients WHERE email = ?', [$r['contact_email']]);
            if (!$c || $r['client_id']) go('No matching client account.', A('request', 'id=' . $rid), 'err');
            q('UPDATE requests SET client_id = ?, updated_at = NOW() WHERE id = ?', [$c['id'], $rid]);
            audit('staff', $sid, 'request_linked', 'request', $rid, ['client' => (int)$c['id']]);
            go('Linked to the client account.', A('request', 'id=' . $rid));
        }
        if ($a === 'invite') {
            if ($r['client_id']) go('Already linked to a client.', A('request', 'id=' . $rid), 'err');
            if (row('SELECT id FROM clients WHERE email = ?', [$r['contact_email']])) go('A client with this email exists — use "Link".', A('request', 'id=' . $rid), 'err');
            q("INSERT INTO clients (email, full_name, company, phone, source, created_at, updated_at) VALUES (?,?,?,?,'invited',NOW(),NOW())", [$r['contact_email'], $r['contact_name'], $r['company'], $r['contact_phone']]);
            $cid = (int)db()->lastInsertId();
            q('UPDATE requests SET client_id = ?, updated_at = NOW() WHERE id = ?', [$cid, $rid]);
            $t = make_token('reset', $cid, 72);
            send_mail($r['contact_email'], 'Your Think Mybiz Tech client account', "Hello {$r['contact_name']},\n\nWe have opened a client account for your request {$r['ref']}. Your quotation, invoices and messages will be there.\n\nChoose your password here (valid 72 hours):\n" . HUB_SITE . "/portal/?p=reset&t=$t\n\nAfter that, log in at " . HUB_SITE . "/portal/");
            audit('staff', $sid, 'client_invited', 'client', $cid, ['request' => $rid]);
            go('Client account created and invitation emailed.', A('request', 'id=' . $rid));
        }
    }
    $match = !$r['client_id'] ? row('SELECT id, full_name FROM clients WHERE email = ?', [$r['contact_email']]) : null;
    page_top('Request ' . $r['ref'], 'admin', $S, 'requests');
    echo '<p><a href="' . A('requests') . '">← Requests</a></p><div class="hub-grid2"><section class="hub-card"><div class="hub-bar"><h1 class="hub-h1">' . e($r['ref']) . '</h1>' . status_pill(REQUEST_STATUSES[$r['status']]) . '</div>'
       . '<dl class="dl"><dt>Contact</dt><dd>' . e($r['contact_name']) . ($r['company'] ? ' — ' . e($r['company']) : '') . '</dd><dt>Email</dt><dd><a href="mailto:' . e($r['contact_email']) . '">' . e($r['contact_email']) . '</a></dd>'
       . '<dt>Phone</dt><dd>' . ($r['contact_phone'] ? e($r['contact_phone']) . ' · <a target="_blank" rel="noopener" href="https://wa.me/' . e(preg_replace('/^0/', '27', preg_replace('/\D/', '', $r['contact_phone']))) . '">WhatsApp</a>' : '—') . '</dd>'
       . '<dt>Service</dt><dd>' . e($r['service']) . ($r['package_id'] ? ' <span class="muted">(' . e($r['package_id']) . ')</span>' : '') . '</dd><dt>Budget / timeline</dt><dd>' . e(($r['budget'] ?: '—') . ' · ' . ($r['timeline'] ?: '—')) . '</dd>'
       . '<dt>Source</dt><dd>' . e($r['source']) . ($r['utm_source'] ? ' · ' . e($r['utm_source'] . ' / ' . $r['utm_medium'] . ' / ' . $r['utm_campaign']) : '') . ($r['page'] ? '<div class="small muted">' . e($r['page']) . '</div>' : '') . '</dd>'
       . '<dt>Received</dt><dd>' . e(date('j M Y H:i', strtotime($r['created_at']))) . '</dd></dl><div class="prewrap">' . e($r['description']) . '</div></section><section class="hub-card"><h2>Actions</h2>'
       . '<form method="post" class="form inline-form">' . csrf_field() . '<input type="hidden" name="action" value="status"><select name="status">';
    foreach (REQUEST_STATUSES as $k => $t) echo '<option value="' . $k . '"' . ($r['status'] === $k ? ' selected' : '') . '>' . e($t) . '</option>';
    echo '</select><button class="btn btn-outline btn-sm">Update status</button></form>';
    if ($r['client_id']) {
        $c = row('SELECT * FROM clients WHERE id = ?', [$r['client_id']]);
        echo '<p>Client account: <a href="' . A('client', 'id=' . $c['id']) . '">' . e($c['company'] ?: $c['full_name']) . '</a></p><div class="btnrow"><a class="btn btn-primary btn-sm" href="' . A('edit', 'kind=quote&client=' . $c['id'] . '&request=' . $rid) . '">Create quotation</a><a class="btn btn-outline btn-sm" href="' . A('thread', 'id=' . $c['id'] . '&request=' . $rid) . '">Message client</a></div>';
    } elseif ($match) {
        echo '<p>A client account exists for this email (' . e($match['full_name']) . ').</p><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="link"><button class="btn btn-primary btn-sm">Link request to client</button></form>';
    } else {
        echo '<p>To send a quotation, open a client account for this lead. They receive an email to choose a password.</p><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="invite"><button class="btn btn-primary btn-sm">Create client account &amp; invite</button></form>';
    }
    $docs = q('SELECT d.*, c.full_name, c.company FROM documents d JOIN clients c ON c.id = d.client_id WHERE d.request_id = ? ORDER BY d.created_at', [$rid])->fetchAll();
    if ($docs) echo '<h3 style="margin-top:20px">Documents</h3>' . doc_rows($docs);
    echo '</section></div>';
    page_end(); exit;
}

/* ---------- Clients ---------- */
if ($p === 'clients') {
    if ($isPost) {
        $email = strtolower(oneline(post('email', 190))); $name = oneline(post('name', 120));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '') go('Enter a name and valid email.', A('clients'), 'err');
        if (row('SELECT id FROM clients WHERE email = ?', [$email])) go('A client with that email already exists.', A('clients'), 'err');
        q("INSERT INTO clients (email, full_name, company, phone, source, created_at, updated_at) VALUES (?,?,?,?,'admin',NOW(),NOW())", [$email, $name, oneline(post('company', 160)) ?: null, oneline(post('phone', 40)) ?: null]);
        $cid = (int)db()->lastInsertId();
        audit('staff', $sid, 'client_created', 'client', $cid);
        if (post('invite', 5) !== '') { $t = make_token('reset', $cid, 72); send_mail($email, 'Your Think Mybiz Tech client account', "Hello $name,\n\nWe have opened a client account for you. Choose your password (valid 72 hours):\n" . HUB_SITE . "/portal/?p=reset&t=$t"); }
        go('Client added.', A('client', 'id=' . $cid));
    }
    $s = '%' . oneline((string)($_GET['q'] ?? '')) . '%';
    $rows = q("SELECT c.*, (SELECT COALESCE(SUM(total_cents - paid_cents),0) FROM documents d WHERE d.client_id = c.id AND d.kind='invoice' AND d.status IN ('issued','part_paid')) bal
               FROM clients c WHERE c.email LIKE ? OR c.full_name LIKE ? OR c.company LIKE ? ORDER BY c.created_at DESC LIMIT 300", [$s, $s, $s])->fetchAll();
    page_top('Clients', 'admin', $S, 'clients');
    echo '<div class="hub-bar"><h1 class="hub-h1">Clients</h1><form class="inline-form"><input type="hidden" name="p" value="clients"><input name="q" placeholder="Search" value="' . e($_GET['q'] ?? '') . '"><button class="btn btn-outline btn-sm">Search</button></form></div>'
       . '<section class="hub-card"><table class="tbl"><thead><tr><th>Client</th><th>Email</th><th>Phone</th><th>Account</th><th class="r">Balance</th></tr></thead><tbody>';
    foreach ($rows as $c) echo '<tr><td><a href="' . A('client', 'id=' . $c['id']) . '">' . e($c['company'] ?: $c['full_name']) . '</a>' . ($c['company'] ? '<div class="small muted">' . e($c['full_name']) . '</div>' : '') . '</td><td>' . e($c['email']) . '</td><td>' . e($c['phone']) . '</td><td>' . ($c['password_hash'] ? ($c['email_verified_at'] ? 'Active' : 'Unconfirmed email') : 'Invited') . ($c['status'] === 'disabled' ? ' · disabled' : '') . '</td><td class="r">' . e(money((int)$c['bal'])) . '</td></tr>';
    echo '</tbody></table>' . ($rows ? '' : empty_state('No clients yet.')) . '</section><section class="hub-card"><h2>Add a client</h2><form method="post" class="form">' . csrf_field()
       . '<div class="row"><div><label>Full name</label><input name="name" required></div><div><label>Company</label><input name="company"></div></div><div class="row"><div><label>Email</label><input name="email" type="email" required></div><div><label>Phone</label><input name="phone"></div></div>'
       . '<label class="consent"><input type="checkbox" name="invite" value="1" checked> <span>Email an invitation to choose a password</span></label><button class="btn btn-primary">Add client</button></form></section>';
    page_end(); exit;
}
if ($p === 'client') {
    $c = row('SELECT * FROM clients WHERE id = ?', [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$c) go('Client not found.', A('clients'), 'err');
    $cid = (int)$c['id'];
    if ($isPost) {
        $a = post('action', 20);
        if ($a === 'save') {
            $vat = oneline(post('vat_number', 20));
            if ($vat !== '' && !preg_match('/^4\d{9}$/', $vat)) go('A South African VAT number has 10 digits and starts with 4.', A('client', 'id=' . $cid), 'err');
            q('UPDATE clients SET full_name = ?, company = ?, phone = ?, vat_number = ?, address = ?, updated_at = NOW() WHERE id = ?', [oneline(post('name', 120)) ?: $c['full_name'], oneline(post('company', 160)) ?: null, oneline(post('phone', 40)) ?: null, $vat ?: null, oneline(post('address', 400)) ?: null, $cid]);
            audit('staff', $sid, 'client_updated', 'client', $cid); go('Saved.', A('client', 'id=' . $cid));
        }
        if ($a === 'invite') { $t = make_token('reset', $cid, 72); send_mail($c['email'], 'Your Think Mybiz Tech client account', "Hello {$c['full_name']},\n\nChoose your Client Hub password here (valid 72 hours):\n" . HUB_SITE . "/portal/?p=reset&t=$t"); audit('staff', $sid, 'client_invited', 'client', $cid); go('Password link emailed.', A('client', 'id=' . $cid)); }
        if ($a === 'toggle' && $isAdmin) { q("UPDATE clients SET status = IF(status='active','disabled','active') WHERE id = ?", [$cid]); audit('staff', $sid, 'client_status_toggled', 'client', $cid); go('Account status changed.', A('client', 'id=' . $cid)); }
    }
    page_top($c['company'] ?: $c['full_name'], 'admin', $S, 'clients');
    echo '<p><a href="' . A('clients') . '">← Clients</a></p><div class="hub-bar"><h1 class="hub-h1">' . e($c['company'] ?: $c['full_name']) . '</h1><div class="btnrow"><a class="btn btn-primary btn-sm" href="' . A('edit', 'kind=quote&client=' . $cid) . '">New quotation</a><a class="btn btn-outline btn-sm" href="' . A('edit', 'kind=invoice&client=' . $cid) . '">New invoice</a><a class="btn btn-outline btn-sm" href="' . A('thread', 'id=' . $cid) . '">Messages</a></div></div>'
       . '<div class="hub-grid2"><section class="hub-card"><h2>Details</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="save">'
       . '<div class="row"><div><label>Full name</label><input name="name" value="' . e($c['full_name']) . '"></div><div><label>Company</label><input name="company" value="' . e($c['company']) . '"></div></div>'
       . '<div class="row"><div><label>Email</label><input value="' . e($c['email']) . '" disabled></div><div><label>Phone</label><input name="phone" value="' . e($c['phone']) . '"></div></div>'
       . '<div class="row"><div><label>VAT number</label><input name="vat_number" value="' . e($c['vat_number']) . '"></div><div><label>Address</label><input name="address" value="' . e($c['address']) . '"></div></div><button class="btn btn-outline btn-sm">Save</button></form>'
       . '<p class="small" style="margin-top:14px">Account: ' . ($c['password_hash'] ? 'password set' : 'no password yet') . ' · email ' . ($c['email_verified_at'] ? 'confirmed' : 'not confirmed') . ' · marketing ' . ($c['marketing_consent'] ? 'yes' : 'no') . ' · since ' . e(fdate($c['created_at'])) . '</p>'
       . '<div class="btnrow"><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="invite"><button class="linkbtn">Email password link</button></form>'
       . ($isAdmin ? '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="toggle"><button class="linkbtn" onclick="return confirm(\'Change account status?\')">' . ($c['status'] === 'active' ? 'Disable log-in' : 'Enable log-in') . '</button></form>' : '') . '</div></section>'
       . '<section class="hub-card"><h2>Requests</h2>' . req_rows(q('SELECT * FROM requests WHERE client_id = ? ORDER BY created_at DESC', [$cid])->fetchAll()) . '</section></div>'
       . '<section class="hub-card"><h2>Quotes &amp; invoices</h2>' . doc_rows(q('SELECT d.*, c.full_name, c.company FROM documents d JOIN clients c ON c.id = d.client_id WHERE d.client_id = ? ORDER BY d.created_at DESC', [$cid])->fetchAll()) . '</section>';
    page_end(); exit;
}

/* ---------- Documents ---------- */
if ($p === 'quotes' || $p === 'invoices') {
    $kind = $p === 'quotes' ? 'quote' : 'invoice';
    page_top($p === 'quotes' ? 'Quotations' : 'Invoices', 'admin', $S, $p);
    echo '<div class="hub-bar"><h1 class="hub-h1">' . ($kind === 'quote' ? 'Quotations' : 'Invoices') . '</h1><div class="btnrow"><a class="btn btn-primary btn-sm" href="' . A('edit', 'kind=' . $kind) . '">New ' . ($kind === 'quote' ? 'quotation' : 'invoice') . '</a>'
       . ($kind === 'invoice' ? '<a class="btn btn-outline btn-sm" href="' . A('export', 'what=invoices') . '">Export invoices</a><a class="btn btn-outline btn-sm" href="' . A('export', 'what=payments') . '">Export payments</a>' : '') . '</div></div>'
       . '<section class="hub-card">' . doc_rows(q('SELECT d.*, c.full_name, c.company FROM documents d JOIN clients c ON c.id = d.client_id WHERE d.kind = ? ORDER BY COALESCE(d.issued_at, d.created_at) DESC LIMIT 500', [$kind])->fetchAll()) . '</section>';
    page_end(); exit;
}

if ($p === 'edit') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    $d = $id ? get_doc($id) : null;
    if ($id && (!$d || $d['status'] !== 'draft')) go('Only drafts can be edited.', A('doc', 'id=' . $id), 'err');
    $kind = $d['kind'] ?? ((string)($_GET['kind'] ?? $_POST['kind'] ?? '') === 'invoice' ? 'invoice' : 'quote');
    $err = null;
    if ($isPost) {
        [$lines, $err] = parse_lines((array)($_POST['desc'] ?? []), (array)($_POST['qty'] ?? []), (array)($_POST['price'] ?? []));
        $cl = (int)($_POST['client_id'] ?? 0);
        if (!$err && !$cl) $err = 'Choose a client.';
        if (!$err) {
            try {
                $id = save_draft($id ?: null, $kind, $cl, (int)($_POST['request_id'] ?? 0) ?: null, ['title' => oneline(post('title', 200)), 'notes' => post('notes', 3000), 'terms' => post('terms', 4000)], $lines, $sid);
                if (post('then', 10) === 'issue') { $doc = issue_doc($id, $sid); go(doc_label($doc) . ' ' . $doc['number'] . ' issued and emailed to the client.', A('doc', 'id=' . $id)); }
                go('Draft saved.', A('doc', 'id=' . $id));
            } catch (RuntimeException $x) { if ($id) go($x->getMessage(), A('doc', 'id=' . $id), 'err'); $err = $x->getMessage(); }
        }
    }
    $lines = $d ? doc_lines((int)$d['id']) : [];
    if ($isPost && $err) $lines = array_map(fn($i) => ['description' => $_POST['desc'][$i] ?? '', 'qty' => $_POST['qty'][$i] ?? '', 'price' => $_POST['price'][$i] ?? ''], array_keys((array)($_POST['desc'] ?? [])));
    $selClient = (int)($_POST['client_id'] ?? $d['client_id'] ?? $_GET['client'] ?? 0);
    $reqId = (int)($_POST['request_id'] ?? $d['request_id'] ?? $_GET['request'] ?? 0);
    if (!$lines && !$d && ($pk = package_by_id((string)($_GET['package'] ?? (string)val('SELECT package_id FROM requests WHERE id = ?', [$reqId]))))) $lines = [['description' => html_entity_decode($pk['name']) . ' — ' . implode('; ', array_map('html_entity_decode', $pk['inc'])), 'qty' => '1', 'price' => (string)$pk['price']]];
    page_top(($d ? 'Edit draft' : 'New ') . ($kind === 'quote' ? 'quotation' : 'invoice'), 'admin', $S, $kind === 'quote' ? 'quotes' : 'invoices');
    echo '<h1 class="hub-h1">' . ($d ? 'Edit draft ' : 'New ') . ($kind === 'quote' ? 'quotation' : 'invoice') . '</h1>' . ($err ? '<p class="notice err">' . e($err) . '</p>' : '')
       . '<div class="notice small">VAT: ' . (vat_on() ? 'charged at 15% (VAT registered).' : 'not charged — VAT registration is off in Settings.') . ' Totals are calculated by the server. Numbers are assigned when you issue.</div>'
       . '<form method="post" class="form hub-card" id="docform">' . csrf_field() . '<input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="kind" value="' . e($kind) . '"><input type="hidden" name="request_id" value="' . $reqId . '">'
       . '<div class="row"><div><label>Client</label><select name="client_id" required>' . clients_opts($selClient ?: null) . '</select></div><div><label>Subject (optional)</label><input name="title" maxlength="200" value="' . e($_POST['title'] ?? $d['title'] ?? '') . '" placeholder="e.g. Business Website — 8 pages"></div></div>'
       . '<table class="tbl lines"><thead><tr><th>Description</th><th style="width:90px">Qty</th><th style="width:150px">Unit price (R' . (vat_on() ? ', excl. VAT' : '') . ')</th><th style="width:130px" class="r">Amount</th><th style="width:36px"></th></tr></thead><tbody id="lines">';
    $lines = $lines ?: [['description' => '', 'qty' => '1', 'price' => '']];
    foreach ($lines as $l) {
        $q = $l['qty'] ?? fmt_qty((int)$l['qty_milli']); $pr = $l['price'] ?? number_format((int)$l['unit_cents'] / 100, 2, '.', '');
        echo '<tr><td><input name="desc[]" maxlength="500" value="' . e($l['description']) . '"></td><td><input name="qty[]" inputmode="decimal" value="' . e($q) . '"></td><td><input name="price[]" inputmode="decimal" value="' . e($pr) . '"></td><td class="r amt"></td><td><button type="button" class="linkbtn rm" aria-label="Remove line">✕</button></td></tr>';
    }
    echo '</tbody></table><p><button type="button" class="btn btn-outline btn-sm" id="addline">+ Add line</button> <span class="small muted">Estimated total: <strong id="esttotal"></strong>' . (vat_on() ? ' excl. VAT' : '') . '</span></p>'
       . '<div><label>Notes to client (optional)</label><textarea name="notes" rows="3">' . e($_POST['notes'] ?? $d['notes'] ?? '') . '</textarea></div>'
       . '<div><label>Terms</label><textarea name="terms" rows="4">' . e($_POST['terms'] ?? $d['terms'] ?? setting($kind === 'quote' ? 'quote_terms' : 'invoice_terms', '')) . '</textarea></div>'
       . '<div class="btnrow"><button class="btn btn-outline" name="then" value="save">Save draft</button><button class="btn btn-primary" name="then" value="issue" onclick="return confirm(\'Issue now? It gets a number, is locked and is emailed to the client.\')">Save &amp; issue</button></div></form>';
    page_end(); exit;
}

if ($p === 'doc') {
    $d = get_doc((int)($_GET['id'] ?? $_POST['id'] ?? 0));
    if (!$d) go('Document not found.', A('home'), 'err');
    $did = (int)$d['id'];
    if ($isPost) {
        $a = post('action', 20);
        try {
            if ($a === 'issue') { $x = issue_doc($did, $sid); go(doc_label($x) . ' ' . $x['number'] . ' issued and emailed.', A('doc', 'id=' . $did)); }
            if ($a === 'void') { if (!$isAdmin && $d['status'] !== 'draft') throw new RuntimeException('Only an administrator can void issued documents.'); void_doc($did, oneline(post('reason', 300)), $sid); go($d['status'] === 'draft' ? 'Draft deleted.' : 'Voided.', $d['status'] === 'draft' ? A($d['kind'] === 'quote' ? 'quotes' : 'invoices') : A('doc', 'id=' . $did)); }
            if ($a === 'pay') {
                $c = parse_money(post('amount', 20));
                $m = post('method', 40); if (!in_array($m, ['EFT', 'Cash', 'Card', 'Other'], true)) $m = 'Other';
                record_payment($did, $c ?? 0, post('paid_on', 10), $m, oneline(post('reference', 120)), $sid);
                go('Payment recorded.', A('doc', 'id=' . $did));
            }
            if ($a === 'reverse' && $isAdmin) { reverse_payment((int)($_POST['payment'] ?? 0), oneline(post('reason', 300)), $sid); go('Payment reversed.', A('doc', 'id=' . $did)); }
            if ($a === 'toinvoice') { $nid = invoice_from_quote($did, (int)post('percent', 3), $sid); go('Draft invoice created from the quotation. Check it, then issue.', A('edit', 'id=' . $nid)); }
            if ($a === 'resend' && $d['status'] !== 'draft') {
                $c = row('SELECT email, full_name FROM clients WHERE id = ?', [$d['client_id']]);
                send_mail($c['email'], doc_label($d) . ' ' . $d['number'] . ' — Think Mybiz Tech', "Hello {$c['full_name']},\n\nA reminder about " . strtolower(doc_label($d)) . " {$d['number']} (" . money((int)$d['total_cents']) . ").\n\nView it here: " . HUB_SITE . "/portal/?p=doc&id=$did");
                audit('staff', $sid, 'document_resent', 'document', $did); go('Email sent to the client.', A('doc', 'id=' . $did));
            }
        } catch (RuntimeException $x) { go($x->getMessage(), A('doc', 'id=' . $did), 'err'); }
    }
    page_top(doc_label($d) . ' ' . ($d['number'] ?: 'draft'), 'admin', $S, $d['kind'] === 'quote' ? 'quotes' : 'invoices');
    echo '<div class="hub-bar noprint"><a href="' . A($d['kind'] === 'quote' ? 'quotes' : 'invoices') . '">← ' . ($d['kind'] === 'quote' ? 'Quotations' : 'Invoices') . '</a><div class="btnrow">';
    if ($d['status'] === 'draft') echo '<a class="btn btn-outline btn-sm" href="' . A('edit', 'id=' . $did) . '">Edit</a><form method="post">' . csrf_field() . '<input type="hidden" name="action" value="issue"><button class="btn btn-primary btn-sm" onclick="return confirm(\'Issue now? It is numbered, locked and emailed to the client.\')">Issue &amp; email</button></form>';
    else echo '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="resend"><button class="btn btn-outline btn-sm">Email reminder</button></form>';
    echo '<button class="btn btn-outline btn-sm" onclick="window.print()">Print / PDF</button></div></div>' . render_doc($d) . '<div class="noprint">';
    if ($d['kind'] === 'quote' && $d['status'] === 'accepted') {
        echo '<section class="hub-card"><h2>Invoice this quotation</h2><form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="action" value="toinvoice"><label>Percentage</label><select name="percent">'
           . '<option value="50">50% deposit</option><option value="100">100% (full)</option><option value="30">30% deposit</option><option value="40">40%</option><option value="60">60%</option><option value="70">70%</option></select><button class="btn btn-primary btn-sm">Create draft invoice</button></form>';
        $inv = q("SELECT d.*, c.full_name, c.company FROM documents d JOIN clients c ON c.id = d.client_id WHERE d.source_quote_id = ? ORDER BY d.created_at", [$did])->fetchAll();
        if ($inv) echo doc_rows($inv);
        echo '</section>';
    }
    if ($d['kind'] === 'invoice' && $d['status'] !== 'draft') {
        $pays = q('SELECT p.*, s.full_name FROM payments p LEFT JOIN staff s ON s.id = p.recorded_by WHERE p.document_id = ? ORDER BY p.paid_on, p.id', [$did])->fetchAll();
        echo '<section class="hub-card"><h2>Payments</h2>';
        if ($pays) {
            echo '<table class="tbl"><thead><tr><th>Date</th><th>Method</th><th>Reference</th><th class="r">Amount</th><th>Recorded by</th><th></th></tr></thead><tbody>';
            foreach ($pays as $py) echo '<tr' . ($py['reversed_at'] ? ' class="struck"' : '') . '><td>' . e(fdate($py['paid_on'])) . '</td><td>' . e($py['method']) . '</td><td>' . e($py['reference']) . '</td><td class="r">' . e(money((int)$py['amount_cents'])) . '</td><td>' . e($py['full_name']) . ($py['reversed_at'] ? '<div class="small">Reversed: ' . e($py['reverse_reason']) . '</div>' : '') . '</td><td>'
               . (!$py['reversed_at'] && $isAdmin ? '<form method="post" class="inline-form" onsubmit="var r=prompt(\'Reason for reversing this payment?\');if(!r)return false;this.reason.value=r;">' . csrf_field() . '<input type="hidden" name="action" value="reverse"><input type="hidden" name="payment" value="' . $py['id'] . '"><input type="hidden" name="reason"><button class="linkbtn">Reverse</button></form>' : '') . '</td></tr>';
            echo '</tbody></table>';
        }
        if (in_array($d['status'], ['issued', 'part_paid'], true)) {
            $bal = (int)$d['total_cents'] - (int)$d['paid_cents'];
            echo '<form method="post" class="form" style="margin-top:14px">' . csrf_field() . '<input type="hidden" name="action" value="pay"><div class="row4"><div><label>Amount received</label><input name="amount" inputmode="decimal" value="' . e(number_format($bal / 100, 2, '.', '')) . '" required></div>'
               . '<div><label>Date received</label><input name="paid_on" type="date" max="' . date('Y-m-d') . '" value="' . date('Y-m-d') . '" required></div><div><label>Method</label><select name="method"><option>EFT</option><option>Cash</option><option>Card</option><option>Other</option></select></div>'
               . '<div><label>Bank reference</label><input name="reference" maxlength="120"></div></div><button class="btn btn-primary btn-sm">Record payment</button><p class="small muted">Record only money that has cleared in the bank. Then capture it in Taxnify (or use Export).</p></form>';
        }
        echo '</section>';
    }
    if ($d['status'] !== 'void' && ($d['status'] === 'draft' || $isAdmin) && !($d['kind'] === 'invoice' && (int)$d['paid_cents'] > 0)) {
        echo '<details class="hub-card"><summary>' . ($d['status'] === 'draft' ? 'Delete draft' : 'Void this document') . '</summary><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="void"><div><label>Reason</label><input name="reason" required minlength="4" maxlength="300"></div><button class="btn btn-outline btn-sm" onclick="return confirm(\'Are you sure?\')">' . ($d['status'] === 'draft' ? 'Delete draft' : 'Void') . '</button></form>'
           . ($d['status'] !== 'draft' ? '<p class="small muted">The number stays used and the document stays visible as void, so the sequence has no gaps.</p>' : '') . '</details>';
    }
    $log = q("SELECT a.*, s.full_name FROM audit_log a LEFT JOIN staff s ON a.actor_type='staff' AND s.id = a.actor_id WHERE a.entity = 'document' AND a.entity_id = ? ORDER BY a.id", [$did])->fetchAll();
    echo '<details class="hub-card"><summary>History (' . count($log) . ')</summary><ul class="log">';
    foreach ($log as $l) echo '<li>' . e(date('j M Y H:i', strtotime($l['created_at']))) . ' — ' . e(str_replace('_', ' ', $l['action'])) . ' — ' . e($l['full_name'] ?: $l['actor_type']) . '</li>';
    echo '</ul></details></div>';
    page_end(); exit;
}

/* ---------- Messages ---------- */
if ($p === 'messages') {
    $rows = q("SELECT c.id, c.full_name, c.company, MAX(m.created_at) last_at, SUM(m.sender='client' AND m.read_at IS NULL) unread
               FROM messages m JOIN clients c ON c.id = m.client_id GROUP BY c.id, c.full_name, c.company ORDER BY last_at DESC LIMIT 200")->fetchAll();
    page_top('Messages', 'admin', $S, 'messages');
    echo '<h1 class="hub-h1">Messages</h1><section class="hub-card">';
    if (!$rows) echo empty_state('No client messages yet.');
    else { echo '<table class="tbl"><thead><tr><th>Client</th><th>Last message</th><th>Unread</th></tr></thead><tbody>'; foreach ($rows as $r) echo '<tr><td><a href="' . A('thread', 'id=' . $r['id']) . '">' . e($r['company'] ?: $r['full_name']) . '</a></td><td>' . e(date('j M Y H:i', strtotime($r['last_at']))) . '</td><td>' . ((int)$r['unread'] ? status_pill((int)$r['unread'] . ' new') : '') . '</td></tr>'; echo '</tbody></table>'; }
    echo '</section>';
    page_end(); exit;
}
if ($p === 'thread') {
    $c = row('SELECT * FROM clients WHERE id = ?', [(int)($_GET['id'] ?? $_POST['id'] ?? 0)]);
    if (!$c) go('Client not found.', A('messages'), 'err');
    $cid = (int)$c['id']; $reqId = (int)($_GET['request'] ?? $_POST['request'] ?? 0);
    if ($reqId && !row('SELECT id FROM requests WHERE id = ? AND client_id = ?', [$reqId, $cid])) $reqId = 0;
    if ($isPost) {
        $body = post('body', 4000);
        if (mb_strlen($body) < 2) go('Type a message.', A('thread', 'id=' . $cid), 'err');
        q("INSERT INTO messages (client_id, request_id, sender, staff_id, body, created_at) VALUES (?,?,'staff',?,?,NOW())", [$cid, $reqId ?: null, $sid, $body]);
        audit('staff', $sid, 'message_sent', 'client', $cid);
        send_mail($c['email'], 'New message from Think Mybiz Tech', "Hello {$c['full_name']},\n\n$body\n\nReply in your Client Hub: " . HUB_SITE . "/portal/?p=messages");
        go('Message sent and emailed to the client.', A('thread', 'id=' . $cid));
    }
    q("UPDATE messages SET read_at = NOW() WHERE client_id = ? AND sender = 'client' AND read_at IS NULL", [$cid]);
    $msgs = q('SELECT m.*, r.ref, s.full_name staff_name FROM messages m LEFT JOIN requests r ON r.id = m.request_id LEFT JOIN staff s ON s.id = m.staff_id WHERE m.client_id = ? ORDER BY m.created_at', [$cid])->fetchAll();
    page_top('Messages — ' . ($c['company'] ?: $c['full_name']), 'admin', $S, 'messages');
    echo '<p><a href="' . A('messages') . '">← Messages</a> · <a href="' . A('client', 'id=' . $cid) . '">Client record</a></p><h1 class="hub-h1">' . e($c['company'] ?: $c['full_name']) . '</h1><section class="hub-card"><div class="thread">';
    if (!$msgs) echo empty_state('No messages yet.');
    foreach ($msgs as $m) echo '<div class="msg ' . ($m['sender'] === 'staff' ? 'mine' : 'theirs') . '"><div class="meta">' . e($m['sender'] === 'staff' ? ($m['staff_name'] ?: 'Staff') : $c['full_name']) . ' · ' . e(date('j M Y H:i', strtotime($m['created_at']))) . ($m['ref'] ? ' · ' . e($m['ref']) : '') . '</div><div class="prewrap">' . e($m['body']) . '</div></div>';
    echo '</div><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="request" value="' . $reqId . '"><div><label>Reply (also emailed to the client)</label><textarea name="body" rows="4" required></textarea></div><button class="btn btn-primary">Send</button></form></section>';
    page_end(); exit;
}

/* ---------- Social & marketing ---------- */
if ($p === 'social') {
    if ($isPost) {
        $a = post('action', 20);
        if ($a === 'post') {
            $body = post('body', 3000); $link = oneline(post('link', 500)); $when = post('when', 20);
            if (mb_strlen($body) < 10) go('Write the post text (at least a sentence).', A('social'), 'err');
            if ($link !== '' && !preg_match('#^https://#', $link)) go('Links must start with https://', A('social'), 'err');
            $dt = $when !== '' ? DateTime::createFromFormat('Y-m-d\TH:i', $when) : new DateTime();
            if (!$dt) go('Invalid date/time.', A('social'), 'err');
            $fb = fb_ready() && post('to_fb', 5) !== '';
            q('INSERT INTO social_posts (body, link_url, publish_at, fb_status, created_by, created_at) VALUES (?,?,?,?,?,NOW())', [$body, $link ?: null, $dt->format('Y-m-d H:i:s'), $fb ? 'pending' : 'off', $sid]);
            audit('staff', $sid, 'social_post_created', 'social', (int)db()->lastInsertId(), ['facebook' => $fb]);
            if ($fb) publish_due_social(1);
            go($fb ? 'Saved. Facebook status is shown below.' : 'Saved. Use the share buttons to post it on other platforms.', A('social'));
        }
        if ($a === 'retry') { q("UPDATE social_posts SET fb_status = 'pending' WHERE id = ? AND fb_status = 'failed'", [(int)($_POST['id'] ?? 0)]); publish_due_social(1); go('Tried again.', A('social')); }
    }
    $posts = q('SELECT * FROM social_posts ORDER BY publish_at DESC LIMIT 30')->fetchAll();
    page_top('Social & marketing', 'admin', $S, 'social');
    echo '<h1 class="hub-h1">Social &amp; marketing</h1><div class="hub-grid2"><section class="hub-card"><h2>New post</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="post">'
       . '<div><label>Post text</label><textarea name="body" rows="6" required placeholder="e.g. We have just launched a new website for…"></textarea></div><div><label>Link (optional)</label><input name="link" placeholder="https://thinkmybiztech.com/portfolio/"></div>'
       . '<div><label>Publish at (leave empty for now)</label><input name="when" type="datetime-local"></div>'
       . '<label class="consent"><input type="checkbox" name="to_fb" value="1"' . (fb_ready() ? ' checked' : ' disabled') . '> <span>Publish to the Facebook Page' . (fb_ready() ? '' : ' — connect it in Settings first') . '</span></label><button class="btn btn-primary">Save post</button></form></section>'
       . '<section class="hub-card"><h2>Channels</h2><ul class="checks"><li><strong>Facebook Page</strong> — ' . (fb_ready() ? 'connected, posts publish automatically' : 'not connected (Settings → Facebook)') . '</li>'
       . '<li><strong>Meta Pixel</strong> — ' . (setting('meta_pixel_id') ? 'ID ' . e(setting('meta_pixel_id')) . ', loads after visitor consent' : 'not set (Settings → Tracking)') . '</li>'
       . '<li><strong>LinkedIn, X, WhatsApp</strong> — use the share buttons on each post (opens the platform with the text and link ready).</li><li><strong>TikTok &amp; Instagram</strong> — copy the text and post from the app (their APIs require approved business apps).</li></ul>'
       . '<p class="small muted">Website leads from ads are tracked with UTM links, e.g. <code>https://thinkmybiztech.com/portal/?p=start&amp;utm_source=facebook&amp;utm_medium=paid&amp;utm_campaign=websites</code> — the source shows on each request.</p></section></div>'
       . '<section class="hub-card"><h2>Posts</h2>';
    if (!$posts) echo empty_state('No posts yet.');
    foreach ($posts as $sp) {
        $text = $sp['body'] . ($sp['link_url'] ? "\n" . $sp['link_url'] : '');
        $u = rawurlencode($sp['link_url'] ?: HUB_SITE . '/');
        echo '<div class="post-row"><div class="meta">' . e(date('j M Y H:i', strtotime($sp['publish_at']))) . ' · Facebook: ' . e($sp['fb_status'] === 'off' ? 'not sent' : $sp['fb_status']) . ($sp['fb_error'] ? ' — ' . e($sp['fb_error']) : '') . '</div><div class="prewrap">' . e($text) . '</div><div class="share">'
           . '<a target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url=' . $u . '">LinkedIn</a><a target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=' . $u . '">Facebook share</a>'
           . '<a target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=' . rawurlencode(mb_substr($text, 0, 250)) . '">X</a><a target="_blank" rel="noopener" href="https://wa.me/?text=' . rawurlencode($text) . '">WhatsApp</a>'
           . '<button type="button" class="linkbtn" data-copy="' . e($text) . '">Copy text (TikTok / Instagram)</button>'
           . ($sp['fb_status'] === 'failed' ? '<form method="post" class="inline-form">' . csrf_field() . '<input type="hidden" name="action" value="retry"><input type="hidden" name="id" value="' . $sp['id'] . '"><button class="linkbtn">Retry Facebook</button></form>' : '') . '</div></div>';
    }
    echo '</section>';
    page_end(); exit;
}

/* ---------- Settings (admin only) ---------- */
if ($p === 'settings') {
    require_staff(true);
    if ($isPost) {
        $a = post('action', 20);
        if ($a === 'company') {
            foreach (['company_name', 'legal_name', 'reg_no', 'address', 'email', 'phone', 'bank_name', 'bank_account_name', 'bank_account_no', 'bank_branch_code', 'bank_account_type'] as $k) set_setting('co_' . $k, oneline(post($k, 300)));
            $vatNo = oneline(post('vat_number', 20));
            if ($vatNo !== '' && !preg_match('/^4\d{9}$/', $vatNo)) go('A South African VAT number has 10 digits and starts with 4.', A('settings'), 'err');
            if (post('vat_registered', 5) !== '' && $vatNo === '') go('Enter the VAT number to switch VAT on.', A('settings'), 'err');
            set_setting('co_vat_number', $vatNo); set_setting('co_vat_registered', post('vat_registered', 5) !== '' ? '1' : '0');
            audit('staff', $sid, 'settings_company', 'settings', null, ['vat' => post('vat_registered', 5) !== '']);
            go('Company and banking details saved. They apply to documents issued from now on.', A('settings'));
        }
        if ($a === 'docs') {
            foreach (['quote_terms' => 4000, 'invoice_terms' => 4000] as $k => $m) set_setting($k, post($k, $m));
            set_setting('payment_terms_days', (string)max(0, min(90, (int)post('payment_terms_days', 3))));
            set_setting('quote_valid_days', (string)max(1, min(180, (int)post('quote_valid_days', 3))));
            $ne = strtolower(oneline(post('notify_email', 190))); if ($ne !== '' && filter_var($ne, FILTER_VALIDATE_EMAIL)) set_setting('notify_email', $ne);
            audit('staff', $sid, 'settings_docs', 'settings'); go('Saved.', A('settings'));
        }
        if ($a === 'tracking') {
            $px = preg_replace('/\D/', '', post('meta_pixel_id', 30)); $li = preg_replace('/\D/', '', post('linkedin_partner_id', 20)); $tt = preg_replace('/[^A-Z0-9]/', '', strtoupper(post('tiktok_pixel_id', 40)));
            set_setting('meta_pixel_id', $px); set_setting('linkedin_partner_id', $li); set_setting('tiktok_pixel_id', $tt);
            @file_put_contents(TMBT_PRIVATE . '/site-config.json', json_encode(['meta' => $px, 'linkedin' => $li, 'tiktok' => $tt]), LOCK_EX);
            audit('staff', $sid, 'settings_tracking', 'settings', null, ['meta' => $px !== '', 'linkedin' => $li !== '', 'tiktok' => $tt !== '']);
            go('Tracking saved. Tags load only for visitors who accept marketing cookies.', A('settings'));
        }
        if ($a === 'facebook') {
            $pid = preg_replace('/\D/', '', post('fb_page_id', 30)); $tok = trim((string)($_POST['fb_page_token'] ?? ''));
            if ($pid === '' || strlen($tok) < 50) go('Enter the Page ID and a Page access token.', A('settings'), 'err');
            [$ok, $msg] = fb_check($pid, $tok);
            if ($ok) { set_setting('fb_page_id', $pid); set_setting('fb_page_token', $tok); set_setting('fb_page_name', $msg); }
            audit('staff', $sid, 'facebook_connect', 'settings', null, ['ok' => $ok]);
            go($ok ? 'Connected to the Facebook Page "' . $msg . '".' : 'Facebook rejected the details: ' . $msg, A('settings'), $ok ? 'ok' : 'err');
        }
        if ($a === 'fboff') { set_setting('fb_page_token', null); audit('staff', $sid, 'facebook_disconnect', 'settings'); go('Facebook disconnected.', A('settings')); }
        if ($a === 'staff') {
            $em = strtolower(oneline(post('email', 190))); $nm = oneline(post('name', 120)); $role = post('role', 10) === 'admin' ? 'admin' : 'staff';
            if (!filter_var($em, FILTER_VALIDATE_EMAIL) || $nm === '') go('Enter name and email.', A('settings'), 'err');
            if (row('SELECT id FROM staff WHERE email = ?', [$em])) go('That staff email exists.', A('settings'), 'err');
            q('INSERT INTO staff (email, full_name, role, active, created_at) VALUES (?,?,?,1,NOW())', [$em, $nm, $role]);
            $nid = (int)db()->lastInsertId(); $t = make_token('staff_reset', $nid, 72);
            send_mail($em, 'Your Think Mybiz Tech admin access', "Hello $nm,\n\nChoose your admin password (valid 72 hours):\n" . HUB_SITE . "/portal/admin/?p=reset&t=$t");
            audit('staff', $sid, 'staff_added', 'staff', $nid, ['role' => $role]); go('Staff member added and invited.', A('settings'));
        }
        if ($a === 'staffoff') {
            $tid = (int)($_POST['id'] ?? 0);
            if ($tid === $sid) go('You cannot disable yourself.', A('settings'), 'err');
            q('UPDATE staff SET active = IF(active=1,0,1) WHERE id = ?', [$tid]); audit('staff', $sid, 'staff_toggled', 'staff', $tid); go('Updated.', A('settings'));
        }
    }
    $co = company_profile();
    $in = fn($k, $label, $v, $extra = '') => '<div><label>' . $label . '</label><input name="' . $k . '" value="' . e($v) . '" ' . $extra . '></div>';
    page_top('Settings', 'admin', $S, 'settings');
    echo '<h1 class="hub-h1">Settings</h1><section class="hub-card"><h2>Company &amp; banking (printed on quotations and invoices)</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="company">'
       . '<div class="row">' . $in('company_name', 'Trading name', $co['company_name']) . $in('legal_name', 'Legal entity name (as registered with CIPC)', $co['legal_name']) . '</div>'
       . '<div class="row">' . $in('reg_no', 'Company registration number', $co['reg_no']) . $in('address', 'Address', $co['address']) . '</div>'
       . '<div class="row">' . $in('email', 'Email', $co['email']) . $in('phone', 'Phone', $co['phone']) . '</div>'
       . '<div class="row">' . $in('bank_name', 'Bank', $co['bank_name']) . $in('bank_account_name', 'Account name', $co['bank_account_name']) . '</div>'
       . '<div class="row">' . $in('bank_account_no', 'Account number', $co['bank_account_no'], 'inputmode="numeric"') . $in('bank_branch_code', 'Branch code', $co['bank_branch_code'], 'inputmode="numeric"') . '</div>'
       . '<div class="row">' . $in('bank_account_type', 'Account type', $co['bank_account_type']) . $in('vat_number', 'VAT number (only if registered)', (string)setting('co_vat_number', ''), 'inputmode="numeric"') . '</div>'
       . '<label class="consent"><input type="checkbox" name="vat_registered" value="1"' . (setting('co_vat_registered') === '1' ? ' checked' : '') . '> <span><strong>Registered for VAT</strong> — charge 15% VAT and issue "Tax invoice" documents. Leave off unless the business has a SARS VAT number.</span></label>'
       . '<button class="btn btn-primary">Save company details</button></form></section>';
    echo '<div class="hub-grid2"><section class="hub-card"><h2>Documents &amp; notifications</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="docs">'
       . '<div class="row"><div><label>Invoice due after (days)</label><input name="payment_terms_days" type="number" min="0" max="90" value="' . e(setting('payment_terms_days', '7')) . '"></div><div><label>Quotation valid for (days)</label><input name="quote_valid_days" type="number" min="1" max="180" value="' . e(setting('quote_valid_days', '30')) . '"></div></div>'
       . '<div><label>Default quotation terms</label><textarea name="quote_terms" rows="4">' . e(setting('quote_terms', '')) . '</textarea></div><div><label>Default invoice terms</label><textarea name="invoice_terms" rows="3">' . e(setting('invoice_terms', '')) . '</textarea></div>'
       . '<div><label>Send new-lead alerts to</label><input name="notify_email" type="email" value="' . e(setting('notify_email', HUB_FROM)) . '"></div><button class="btn btn-primary">Save</button></form></section>';
    echo '<section class="hub-card"><h2>Advertising &amp; tracking</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="tracking">'
       . '<div><label>Meta (Facebook / Instagram) Pixel ID</label><input name="meta_pixel_id" inputmode="numeric" value="' . e(setting('meta_pixel_id', '')) . '" placeholder="15–16 digits from Meta Events Manager"></div>'
       . '<div><label>LinkedIn Insight Tag partner ID</label><input name="linkedin_partner_id" inputmode="numeric" value="' . e(setting('linkedin_partner_id', '')) . '"></div>'
       . '<div><label>TikTok Pixel ID</label><input name="tiktok_pixel_id" value="' . e(setting('tiktok_pixel_id', '')) . '"></div>'
       . '<p class="small muted">Tags load only after a visitor accepts marketing cookies (POPIA). Leads fire a "Lead" event; new accounts fire "CompleteRegistration".</p><button class="btn btn-primary">Save tracking</button></form>'
       . '<h2 style="margin-top:26px">Facebook Page publishing</h2><p class="small">Status: ' . (fb_ready() ? 'connected to <strong>' . e(setting('fb_page_name', '')) . '</strong>' : 'not connected') . '</p>'
       . '<details><summary>How to get a Page access token</summary><ol class="small"><li>At developers.facebook.com create a Business app and add the Facebook Login for Business product.</li><li>In Graph API Explorer choose the app, add permissions <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_posts</code>, and generate a User token.</li><li>Exchange it for a long-lived token, then call <code>/me/accounts</code>: copy the Page <code>id</code> and its <code>access_token</code> (a long-lived Page token does not expire).</li></ol></details>'
       . '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="facebook"><div><label>Page ID</label><input name="fb_page_id" inputmode="numeric" value="' . e(setting('fb_page_id', '')) . '"></div><div><label>Page access token</label><input name="fb_page_token" type="password" autocomplete="off"></div><button class="btn btn-outline btn-sm">Connect &amp; test</button></form>'
       . (fb_ready() ? '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="fboff"><button class="linkbtn">Disconnect Facebook</button></form>' : '') . '</section></div>';
    $staff = q('SELECT * FROM staff ORDER BY created_at')->fetchAll();
    echo '<section class="hub-card"><h2>Staff access</h2><table class="tbl"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Last log-in</th><th></th></tr></thead><tbody>';
    foreach ($staff as $s) echo '<tr><td>' . e($s['full_name']) . '</td><td>' . e($s['email']) . '</td><td>' . e($s['role']) . ($s['active'] ? '' : ' (disabled)') . '</td><td>' . e($s['last_login_at'] ? date('j M Y H:i', strtotime($s['last_login_at'])) : '—') . '</td><td>' . ((int)$s['id'] !== $sid ? '<form method="post">' . csrf_field() . '<input type="hidden" name="action" value="staffoff"><input type="hidden" name="id" value="' . $s['id'] . '"><button class="linkbtn">' . ($s['active'] ? 'Disable' : 'Enable') . '</button></form>' : '') . '</td></tr>';
    echo '</tbody></table><form method="post" class="form inline-form" style="margin-top:14px">' . csrf_field() . '<input type="hidden" name="action" value="staff"><input name="name" placeholder="Full name" required><input name="email" type="email" placeholder="Email" required><select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select><button class="btn btn-outline btn-sm">Add staff</button></form>'
       . '<p class="small muted">Staff can manage requests, clients, quotations, invoices and payments. Only admins can void documents, reverse payments and change settings. <a href="' . A('audit') . '">View the audit log</a>.</p></section>';
    page_end(); exit;
}

if ($p === 'audit') {
    require_staff(true);
    $rows = q('SELECT a.*, s.full_name FROM audit_log a LEFT JOIN staff s ON a.actor_type = \'staff\' AND s.id = a.actor_id ORDER BY a.id DESC LIMIT 300')->fetchAll();
    page_top('Audit log', 'admin', $S, 'settings');
    echo '<h1 class="hub-h1">Audit log</h1><section class="hub-card"><table class="tbl"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>';
    foreach ($rows as $r) echo '<tr><td>' . e(date('j M Y H:i', strtotime($r['created_at']))) . '</td><td>' . e($r['full_name'] ?: $r['actor_type'] . ($r['actor_id'] ? ' #' . $r['actor_id'] : '')) . '</td><td>' . e(str_replace('_', ' ', $r['action'])) . '</td><td>' . e($r['entity'] ? $r['entity'] . ' #' . $r['entity_id'] : '') . '</td><td class="small">' . e((string)$r['details']) . '</td></tr>';
    echo '</tbody></table></section>';
    page_end(); exit;
}

/* ---------- CSV export (for capture in Taxnify / review) ---------- */
if ($p === 'export') {
    $what = (string)($_GET['what'] ?? '');
    $map = [
        'invoices' => ["SELECT d.number, d.issue_date, d.due_date, COALESCE(c.company, c.full_name) client, c.email, c.vat_number, d.status, d.subtotal_cents, d.vat_cents, d.total_cents, d.paid_cents, (d.total_cents - d.paid_cents) balance_cents, d.void_reason FROM documents d JOIN clients c ON c.id = d.client_id WHERE d.kind = 'invoice' AND d.status <> 'draft' ORDER BY d.number", ['subtotal_cents', 'vat_cents', 'total_cents', 'paid_cents', 'balance_cents']],
        'payments' => ["SELECT p.paid_on, d.number invoice, COALESCE(c.company, c.full_name) client, p.method, p.reference, p.amount_cents, p.reversed_at, p.reverse_reason FROM payments p JOIN documents d ON d.id = p.document_id JOIN clients c ON c.id = d.client_id ORDER BY p.paid_on, p.id", ['amount_cents']],
        'requests' => ["SELECT ref, created_at, status, source, contact_name, company, contact_email, contact_phone, service, package_id, budget, timeline, utm_source, utm_medium, utm_campaign FROM requests ORDER BY created_at", []],
    ];
    if (!isset($map[$what])) go('Unknown export.', A('home'), 'err');
    audit('staff', $sid, 'export', null, null, ['what' => $what]);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tmbt-' . $what . '-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    $first = true;
    foreach (q($map[$what][0])->fetchAll() as $r) {
        foreach ($map[$what][1] as $mc) { $r[str_replace('_cents', '', $mc)] = number_format((int)$r[$mc] / 100, 2, '.', ''); unset($r[$mc]); }
        foreach ($r as $k => $v) if (is_string($v) && preg_match('/^[=+\-@]/', $v)) $r[$k] = "'" . $v;   // spreadsheet formula injection guard
        if ($first) { fputcsv($out, array_keys($r)); $first = false; }
        fputcsv($out, $r);
    }
    fclose($out); exit;
}

redirect(A('home'));
