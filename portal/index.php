<?php
/* Think Mybiz Tech Client Hub — client-facing pages. Router: /portal/?p=<page> */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/docs.php';
require __DIR__ . '/lib/layout.php';

if (!hub_installed()) {
    http_response_code(503);
    page_top('Coming soon');
    echo '<div class="hub-card narrow"><h1>Client Hub is being set up</h1><p>Please <a href="/contact/">send us an enquiry</a> or WhatsApp <a href="https://wa.me/27636215642">063 621 5642</a> in the meantime.</p></div>';
    page_end(); exit;
}

$p = preg_replace('/[^a-z]/', '', (string)($_GET['p'] ?? ''));
$me = current_client();
if ($p === '') $p = $me ? 'home' : 'login';
$isPost = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($isPost) csrf_check();

function back_with(string $msg, string $to, string $type = 'err'): never { flash($msg, $type); redirect($to); }

/* ============ Public pages ============ */
if ($p === 'logout') { hub_session(); unset($_SESSION['client_id']); session_regenerate_id(true); flash('You are logged out.'); redirect('/portal/?p=login'); }

if ($p === 'login') {
    if ($me) redirect('/portal/?p=home');
    $next = safe_next((string)($_GET['next'] ?? $_POST['next'] ?? ''), '/portal/?p=home');
    $err = null;
    if ($isPost) {
        $email = strtolower(oneline(post('email', 190))); $pass = (string)($_POST['password'] ?? '');
        $key = 'client:' . $email . '|' . ip_hash();
        if (too_many($key, 5, 15) || too_many('ip:' . ip_hash(), 30, 15)) $err = 'Too many attempts. Wait 15 minutes, or use "Forgot password".';
        else {
            $c = row("SELECT * FROM clients WHERE email = ? AND status = 'active'", [$email]);
            if ($c && $c['password_hash'] && password_verify($pass, $c['password_hash'])) {
                clear_hits($key); login_as('client', (int)$c['id']);
                if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) q('UPDATE clients SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $c['id']]);
                audit('client', (int)$c['id'], 'login', 'client', (int)$c['id']);
                redirect($next);
            }
            hit($key); hit('ip:' . ip_hash());
            $err = 'That email and password do not match. If we set up your account, use "Forgot password" to choose a password.';
        }
    }
    page_top('Log in');
    echo '<div class="hub-split"><div class="hub-card narrow"><h1>Client log-in</h1><p class="muted">Track your requests, accept quotations, view invoices and message our team.</p>';
    if ($err) echo '<p class="notice err" role="alert">' . e($err) . '</p>';
    echo '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="next" value="' . e($next) . '">'
       . '<div><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" value="' . e($_POST['email'] ?? '') . '"></div>'
       . '<div><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>'
       . '<button class="btn btn-primary" type="submit">Log in</button></form>'
       . '<p class="small"><a href="/portal/?p=forgot">Forgot password?</a> · New here? <a href="/portal/?p=register">Create a client account</a></p></div>'
       . '<div class="hub-aside"><h2>New project?</h2><p>You do not need an account to ask for a quotation.</p><a class="btn btn-outline" href="/portal/?p=start">Start a project</a>'
       . '<ul class="checks" style="margin-top:20px"><li>Written quotation you can accept online</li><li>Invoices with EFT details and payment status</li><li>One message thread with our team</li></ul></div></div>';
    page_end(true); exit;
}

if ($p === 'register') {
    if ($me) redirect('/portal/?p=home');
    $err = null;
    if ($isPost) {
        $d = ['name' => oneline(post('name', 120)), 'company' => oneline(post('company', 160)), 'email' => strtolower(oneline(post('email', 190))), 'phone' => oneline(post('phone', 40))];
        $pass = (string)($_POST['password'] ?? '');
        if (post('website', 100) !== '') redirect('/portal/?p=login');                                  // honeypot
        if (too_many('reg:' . ip_hash(), 5, 60)) $err = 'Too many sign-ups from this connection. Please try again later.';
        elseif ($d['name'] === '' || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err = 'Enter your name and a valid email address.';
        elseif ($m = valid_password($pass)) $err = $m;
        elseif (post('consent', 5) === '') $err = 'Please accept the privacy notice to create an account.';
        elseif (row('SELECT id FROM clients WHERE email = ?', [$d['email']])) $err = 'An account already exists for that email. Log in, or use "Forgot password".';
        else {
            hit('reg:' . ip_hash());
            q('INSERT INTO clients (email, full_name, company, phone, password_hash, marketing_consent, source, created_at, updated_at) VALUES (?,?,?,?,?,?,\'portal\',NOW(),NOW())',
              [$d['email'], $d['name'], $d['company'] ?: null, $d['phone'] ?: null, password_hash($pass, PASSWORD_DEFAULT), post('marketing', 5) !== '' ? 1 : 0]);
            $cid = (int)db()->lastInsertId();
            audit('client', $cid, 'registered', 'client', $cid);
            $t = make_token('verify', $cid, 72);
            send_mail($d['email'], 'Confirm your email — Think Mybiz Tech Client Hub', "Hello {$d['name']},\n\nWelcome to the Think Mybiz Tech Client Hub. Please confirm your email address:\n" . HUB_SITE . "/portal/?p=verify&t=$t\n\nThe link is valid for 72 hours.");
            notify_office('New client account: ' . ($d['company'] ?: $d['name']), "{$d['name']} ({$d['email']}, {$d['phone']}) created a client account.", $d['email']);
            login_as('client', $cid);
            flash('Your account is ready. We sent a confirmation link to ' . $d['email'] . '.');
            $_SESSION['ev'] = 'CompleteRegistration';
            redirect('/portal/?p=home');
        }
    }
    page_top('Create account');
    echo '<div class="hub-card narrow"><h1>Create your client account</h1>';
    if ($err) echo '<p class="notice err" role="alert">' . e($err) . '</p>';
    echo '<form method="post" class="form">' . csrf_field() . '<div class="hp" aria-hidden="true"><label>Leave empty<input name="website" tabindex="-1" autocomplete="off"></label></div>'
       . '<div class="row"><div><label for="name">Full name</label><input id="name" name="name" required autocomplete="name" value="' . e($_POST['name'] ?? '') . '"></div>'
       . '<div><label for="company">Company (optional)</label><input id="company" name="company" autocomplete="organization" value="' . e($_POST['company'] ?? '') . '"></div></div>'
       . '<div class="row"><div><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" value="' . e($_POST['email'] ?? '') . '"></div>'
       . '<div><label for="phone">Cellphone</label><input id="phone" name="phone" type="tel" autocomplete="tel" value="' . e($_POST['phone'] ?? '') . '"></div></div>'
       . '<div><label for="password">Password (10+ characters)</label><input id="password" name="password" type="password" minlength="10" required autocomplete="new-password"></div>'
       . '<label class="consent"><input type="checkbox" name="consent" value="1" required> <span>I agree that Think Mybiz Tech may keep these details to manage my account and projects, as described in the <a href="/privacy/">privacy notice</a>.</span></label>'
       . '<label class="consent"><input type="checkbox" name="marketing" value="1"> <span>Send me occasional updates and offers (optional, unsubscribe any time).</span></label>'
       . '<button class="btn btn-primary" type="submit">Create account</button></form><p class="small">Already registered? <a href="/portal/?p=login">Log in</a></p></div>';
    page_end(true); exit;
}

if ($p === 'verify') {
    $cid = use_token('verify', (string)($_GET['t'] ?? ''));
    if (!$cid) back_with('That confirmation link is invalid or has expired. Log in and ask for a new one from your profile.', '/portal/?p=login');
    $c = row('SELECT * FROM clients WHERE id = ?', [$cid]);
    q('UPDATE clients SET email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = ?', [$cid]);
    link_requests_to_client($cid, $c['email']);
    audit('client', $cid, 'email_verified', 'client', $cid);
    back_with('Thank you — your email address is confirmed.', $me ? '/portal/?p=home' : '/portal/?p=login', 'ok');
}

if ($p === 'forgot') {
    $sent = false;
    if ($isPost) {
        $email = strtolower(oneline(post('email', 190)));
        if (!too_many('forgot:' . ip_hash(), 5, 60)) {
            hit('forgot:' . ip_hash());
            $c = row("SELECT * FROM clients WHERE email = ? AND status = 'active'", [$email]);
            if ($c) {
                $t = make_token('reset', (int)$c['id'], 2);
                send_mail($c['email'], 'Choose a password — Think Mybiz Tech Client Hub', "Hello {$c['full_name']},\n\nUse this link to choose a new password (valid for 2 hours):\n" . HUB_SITE . "/portal/?p=reset&t=$t\n\nIf you did not ask for this, ignore this email.");
                audit('client', (int)$c['id'], 'password_reset_requested', 'client', (int)$c['id']);
            }
        }
        $sent = true;   // same answer whether or not the address exists
    }
    page_top('Forgot password');
    echo '<div class="hub-card narrow"><h1>Choose a new password</h1>';
    if ($sent) echo '<p class="notice ok">If that email has a client account, we have sent a link to choose a password. Check your inbox and spam folder.</p>';
    echo '<form method="post" class="form">' . csrf_field() . '<div><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email"></div>'
       . '<button class="btn btn-primary" type="submit">Send link</button></form><p class="small"><a href="/portal/?p=login">Back to log-in</a></p></div>';
    page_end(); exit;
}

if ($p === 'reset') {
    $t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
    $err = null;
    if ($isPost) {
        $pass = (string)($_POST['password'] ?? '');
        if ($m = valid_password($pass)) $err = $m;
        elseif (!hash_equals($pass, (string)($_POST['password2'] ?? ''))) $err = 'The two passwords do not match.';
        else {
            $cid = use_token('reset', $t);
            if (!$cid) back_with('That link is invalid or has expired. Ask for a new one.', '/portal/?p=forgot');
            $c = row('SELECT * FROM clients WHERE id = ?', [$cid]);
            // The link was delivered to this address, so the address is confirmed.
            q('UPDATE clients SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, NOW()), updated_at = NOW() WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $cid]);
            link_requests_to_client($cid, $c['email']);
            audit('client', $cid, 'password_set', 'client', $cid);
            login_as('client', $cid);
            back_with('Your password is saved. Welcome to your Client Hub.', '/portal/?p=home', 'ok');
        }
    }
    page_top('New password');
    echo '<div class="hub-card narrow"><h1>Choose your password</h1>';
    if ($err) echo '<p class="notice err">' . e($err) . '</p>';
    echo '<form method="post" class="form">' . csrf_field() . '<input type="hidden" name="t" value="' . e($t) . '">'
       . '<div><label for="pw">New password (10+ characters)</label><input id="pw" name="password" type="password" minlength="10" required autocomplete="new-password"></div>'
       . '<div><label for="pw2">Repeat password</label><input id="pw2" name="password2" type="password" minlength="10" required autocomplete="new-password"></div>'
       . '<button class="btn btn-primary" type="submit">Save password</button></form></div>';
    page_end(); exit;
}

/* ---------- Start a project (public or logged in) ---------- */
if ($p === 'start') {
    $err = null;
    $pkg = package_by_id((string)($_GET['package'] ?? $_POST['package_id'] ?? ''));
    if ($isPost) {
        $d = ['name' => oneline(post('name', 120)), 'company' => oneline(post('company', 160)), 'email' => strtolower(oneline(post('email', 190))),
              'phone' => oneline(post('phone', 40)), 'service' => oneline(post('service', 160)), 'package_id' => $pkg['id'] ?? null,
              'budget' => oneline(post('budget', 60)), 'timeline' => oneline(post('timeline', 60)), 'description' => post('description', 6000),
              'utm_source' => oneline(post('utm_source', 80)) ?: null, 'utm_medium' => oneline(post('utm_medium', 80)) ?: null,
              'utm_campaign' => oneline(post('utm_campaign', 120)) ?: null, 'page' => oneline(post('page', 200)) ?: '/portal/?p=start'];
        if ($me) { $d['name'] = $me['full_name']; $d['email'] = $me['email']; $d['company'] = $d['company'] ?: (string)$me['company']; $d['phone'] = $d['phone'] ?: (string)$me['phone']; }
        $make = !$me && post('create', 5) !== '';
        $pass = (string)($_POST['password'] ?? '');
        $started = (int)post('ts', 20);
        if (post('website', 100) !== '' || ($started > 0 && time() - intdiv($started, 1000) < 3)) redirect('/portal/?p=thanks&ref=TMT-0');
        if (too_many('req:' . ip_hash(), 6, 60)) $err = 'Too many requests from this connection. Please WhatsApp 063 621 5642.';
        elseif ($d['name'] === '' || !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err = 'Enter your name and a valid email address.';
        elseif ($d['service'] === '') $err = 'Choose the service you need.';
        elseif (mb_strlen($d['description']) < 15) $err = 'Tell us a little more about the project (a few sentences is perfect).';
        elseif (!$me && post('consent', 5) === '') $err = 'Please accept the privacy notice so we can respond.';
        elseif ($make && ($m = valid_password($pass))) $err = $m;
        elseif ($make && row('SELECT id FROM clients WHERE email = ?', [$d['email']])) $err = 'You already have an account with that email. Log in first, then start the request.';
        else {
            hit('req:' . ip_hash());
            $cid = $me ? (int)$me['id'] : null;
            if ($make) {
                q("INSERT INTO clients (email, full_name, company, phone, password_hash, source, created_at, updated_at) VALUES (?,?,?,?,?,'request',NOW(),NOW())",
                  [$d['email'], $d['name'], $d['company'] ?: null, $d['phone'] ?: null, password_hash($pass, PASSWORD_DEFAULT)]);
                $cid = (int)db()->lastInsertId();
                audit('client', $cid, 'registered', 'client', $cid);
                $t = make_token('verify', $cid, 72);
                send_mail($d['email'], 'Confirm your email — Think Mybiz Tech Client Hub', "Hello {$d['name']},\n\nPlease confirm your email address:\n" . HUB_SITE . "/portal/?p=verify&t=$t");
                login_as('client', $cid);
            }
            $r = create_request($d, 'portal', $cid);
            notify_office("New project request {$r['ref']} — " . ($d['company'] ?: $d['name']),
                "Reference: {$r['ref']}\nName: {$d['name']}\nCompany: {$d['company']}\nEmail: {$d['email']}\nPhone: {$d['phone']}\nService: {$d['service']}\n"
                . "Package: " . ($pkg['name'] ?? '—') . "\nBudget: {$d['budget']}\nTimeline: {$d['timeline']}\nSource: " . ($d['utm_source'] ?: 'direct') . ($d['utm_campaign'] ? " / {$d['utm_campaign']}" : '') . "\n\n{$d['description']}", $d['email']);
            send_mail($d['email'], "We received your request {$r['ref']}", "Hello {$d['name']},\n\nThank you for your project request ({$d['service']}). Your reference is {$r['ref']}.\n\nWe will contact you, normally within one business day, to discuss the scope. Your quotation will be sent to your client account"
                . ($cid ? '' : ' (we will invite you to set one up)') . ".\n\nUrgent? WhatsApp 063 621 5642.");
            hub_session(); $_SESSION['ev'] = 'Lead';
            redirect('/portal/?p=thanks&ref=' . rawurlencode($r['ref']));
        }
    }
    $opts = '';
    $groups = [];
    foreach (catalogue() as $c) $groups[$c['group']][] = $c;
    $sel = (string)($_POST['service'] ?? ($pkg ? $pkg['name'] : ''));
    foreach ($groups as $g => $items) {
        $opts .= '<optgroup label="' . e(html_entity_decode($g)) . '">';
        foreach ($items as $c) { $n = html_entity_decode($c['name']); $opts .= '<option' . ($sel === $n ? ' selected' : '') . '>' . e($n) . '</option>'; }
        $opts .= '</optgroup>';
    }
    foreach (['Something else / not sure yet', 'Taxnify configuration and integration'] as $o) $opts .= '<option' . ($sel === $o ? ' selected' : '') . '>' . e($o) . '</option>';
    $budgets = ['Under R5 000', 'R5 000 – R15 000', 'R15 000 – R50 000', 'R50 000 – R150 000', 'Over R150 000', 'Not sure yet'];
    $times = ['As soon as possible', 'Within 1 month', '1–3 months', '3+ months', 'Exploring options'];
    $sopt = fn(array $a, string $cur) => implode('', array_map(fn($x) => '<option' . ($cur === $x ? ' selected' : '') . '>' . e($x) . '</option>', $a));
    page_top('Start a project', 'client', $me, '');
    echo '<div class="hub-split wide"><div class="hub-card"><span class="eyebrow">Start a project</span><h1>Tell us what you need</h1><p class="muted">We reply within one business day. Your written quotation arrives in your client account.</p>';
    if ($pkg) echo '<div class="notice ok">Package: <strong>' . e(html_entity_decode($pkg['name'])) . '</strong> — guide price ' . e(($pkg['unit'] === 'from' ? 'from ' : '') . money(intdiv($pkg['price'] * 100 * (10000 + VAT_RATE_BP) + 5000, 10000)) . ($pkg['unit'] !== 'from' ? ' ' . $pkg['unit'] : '') . ' incl. VAT') . '</div>';
    if ($err) echo '<p class="notice err" role="alert">' . e($err) . '</p>';
    echo '<form method="post" class="form" data-utm>' . csrf_field() . '<div class="hp" aria-hidden="true"><label>Leave empty<input name="website" tabindex="-1" autocomplete="off"></label></div>'
       . '<input type="hidden" name="ts" data-ts><input type="hidden" name="page" data-page><input type="hidden" name="package_id" value="' . e($pkg['id'] ?? '') . '">'
       . '<input type="hidden" name="utm_source"><input type="hidden" name="utm_medium"><input type="hidden" name="utm_campaign">';
    if (!$me) {
        echo '<div class="row"><div><label for="name">Full name</label><input id="name" name="name" required autocomplete="name" value="' . e($_POST['name'] ?? '') . '"></div>'
           . '<div><label for="company">Company / organisation</label><input id="company" name="company" autocomplete="organization" value="' . e($_POST['company'] ?? '') . '"></div></div>'
           . '<div class="row"><div><label for="email">Email</label><input id="email" name="email" type="email" required autocomplete="email" value="' . e($_POST['email'] ?? '') . '"></div>'
           . '<div><label for="phone">Cellphone / WhatsApp</label><input id="phone" name="phone" type="tel" autocomplete="tel" value="' . e($_POST['phone'] ?? '') . '"></div></div>';
    } else {
        echo '<p class="small">Requesting as <strong>' . e($me['full_name']) . '</strong> (' . e($me['email']) . ').</p>';
    }
    echo '<div><label for="service">Service</label><select id="service" name="service" required><option value="">Choose…</option>' . $opts . '</select></div>'
       . '<div class="row"><div><label for="budget">Budget</label><select id="budget" name="budget">' . $sopt($budgets, (string)($_POST['budget'] ?? 'Not sure yet')) . '</select></div>'
       . '<div><label for="timeline">Timeline</label><select id="timeline" name="timeline">' . $sopt($times, (string)($_POST['timeline'] ?? 'Within 1 month')) . '</select></div></div>'
       . '<div><label for="description">About your project</label><textarea id="description" name="description" rows="6" required placeholder="What should the website or system do? Who will use it? Any examples you like?">' . e($_POST['description'] ?? '') . '</textarea></div>';
    if (!$me) {
        echo '<label class="consent"><input type="checkbox" name="create" value="1" data-toggle="#pwbox"' . (!empty($_POST['create']) ? ' checked' : '') . '> <span><strong>Create my client account</strong> so I can track this request, accept the quotation and see invoices.</span></label>'
           . '<div id="pwbox"' . (empty($_POST['create']) ? ' hidden' : '') . '><label for="password">Choose a password (10+ characters)</label><input id="password" name="password" type="password" minlength="10" autocomplete="new-password"></div>'
           . '<label class="consent"><input type="checkbox" name="consent" value="1" required> <span>I agree that Think Mybiz Tech may use these details to respond and prepare a quotation, as described in the <a href="/privacy/">privacy notice</a>.</span></label>';
    }
    echo '<button class="btn btn-primary" type="submit">Send request</button></form></div>'
       . '<aside class="hub-aside"><h2>What happens next</h2><ol class="steps-mini"><li><strong>We call or WhatsApp you</strong> to understand the scope.</li><li><strong>You receive a written quotation</strong> in your client account.</li><li><strong>You accept online</strong> and we start after the deposit.</li><li><strong>Private test site</strong>, then go-live with your approval.</li></ol>'
       . '<p class="small">Prefer to talk? <a href="https://wa.me/27636215642" target="_blank" rel="noopener">WhatsApp 063 621 5642</a> · <a href="/pricing/">See guide prices</a> · <a href="/portfolio/">See our work</a></p></aside></div>';
    page_end(true); exit;
}

if ($p === 'thanks') {
    hub_session(); $ev = $_SESSION['ev'] ?? null; unset($_SESSION['ev']);
    $ref = preg_replace('/[^A-Z0-9-]/', '', (string)($_GET['ref'] ?? ''));
    page_top('Request received', 'client', $me);
    echo '<div class="hub-card narrow"><h1>Thank you — request received</h1><p>Your reference is <strong>' . e($ref) . '</strong>. We will contact you, normally within one business day. A confirmation email is on its way.</p>';
    echo $me ? '<p><a class="btn btn-primary" href="/portal/?p=requests">View my requests</a></p>'
             : '<p>Want to track progress, accept your quotation online and see invoices? <a href="/portal/?p=register">Create a client account</a> with the same email address — your request will be linked once you confirm your email.</p><p><a class="btn btn-outline" href="/portfolio/">See our portfolio</a></p>';
    echo '</div>';
    page_end(true, $ev ? [$ev] : []); exit;
}

/* ============ Logged-in pages ============ */
$me = require_client();
$cid = (int)$me['id'];
hub_session(); $ev = $_SESSION['ev'] ?? null; unset($_SESSION['ev']);
$verifyBanner = $me['email_verified_at'] ? '' : '<div class="notice">Please confirm your email address — we sent a link to ' . e($me['email']) . '. <form method="post" action="/portal/?p=resend" class="inline">' . csrf_field() . '<button class="linkbtn">Send it again</button></form></div>';

if ($p === 'resend' && $isPost) {
    if (!$me['email_verified_at'] && !too_many('resend:' . $cid, 3, 60)) {
        hit('resend:' . $cid);
        $t = make_token('verify', $cid, 72);
        send_mail($me['email'], 'Confirm your email — Think Mybiz Tech Client Hub', "Hello {$me['full_name']},\n\nPlease confirm your email address:\n" . HUB_SITE . "/portal/?p=verify&t=$t");
    }
    back_with('Confirmation link sent.', '/portal/?p=home', 'ok');
}

if ($p === 'home') {
    $open = (int)val("SELECT COUNT(*) FROM requests WHERE client_id = ? AND status IN ('new','contacted','quoted')", [$cid]);
    $quotes = (int)val("SELECT COUNT(*) FROM documents WHERE client_id = ? AND kind = 'quote' AND status = 'issued' AND due_date >= CURDATE()", [$cid]);
    $due = (int)val("SELECT COALESCE(SUM(total_cents - paid_cents),0) FROM documents WHERE client_id = ? AND kind = 'invoice' AND status IN ('issued','part_paid')", [$cid]);
    $unread = (int)val("SELECT COUNT(*) FROM messages WHERE client_id = ? AND sender = 'staff' AND read_at IS NULL", [$cid]);
    page_top('Overview', 'client', $me, 'home');
    echo $verifyBanner . '<h1 class="hub-h1">Hello, ' . e(explode(' ', $me['full_name'])[0]) . '</h1>'
       . '<div class="kpis">' . kpi('Open requests', (string)$open, '', '/portal/?p=requests') . kpi('Quotations to review', (string)$quotes, '', '/portal/?p=docs')
       . kpi('Balance due', money($due), '', '/portal/?p=docs') . kpi('New messages', (string)$unread, '', '/portal/?p=messages') . '</div>';
    $docs = q("SELECT * FROM documents WHERE client_id = ? AND status <> 'draft' ORDER BY issued_at DESC LIMIT 5", [$cid])->fetchAll();
    $reqs = q('SELECT * FROM requests WHERE client_id = ? ORDER BY created_at DESC LIMIT 5', [$cid])->fetchAll();
    echo '<div class="hub-grid2"><section class="hub-card"><h2>Recent requests</h2>' . ($reqs ? requests_table($reqs) : empty_state('No requests yet. <a href="/portal/?p=start">Start a project</a>.')) . '</section>'
       . '<section class="hub-card"><h2>Quotes &amp; invoices</h2>' . ($docs ? docs_table($docs) : empty_state('Your quotations and invoices will appear here.')) . '</section></div>';
    page_end(true, $ev ? [$ev] : []); exit;
}

function requests_table(array $rows): string {
    $h = '<table class="tbl"><thead><tr><th>Reference</th><th>Service</th><th>Date</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $r) $h .= '<tr><td><a href="/portal/?p=request&amp;id=' . (int)$r['id'] . '">' . e($r['ref']) . '</a></td><td>' . e($r['service']) . '</td><td>' . e(fdate($r['created_at'])) . '</td><td>' . status_pill(REQUEST_STATUSES[$r['status']]) . '</td></tr>';
    return $h . '</tbody></table>';
}
function docs_table(array $rows): string {
    $h = '<table class="tbl"><thead><tr><th>Document</th><th>Date</th><th class="r">Total</th><th class="r">Balance</th><th>Status</th></tr></thead><tbody>';
    foreach ($rows as $d) {
        $bal = $d['kind'] === 'invoice' && $d['status'] !== 'void' ? money((int)$d['total_cents'] - (int)$d['paid_cents']) : '';
        $h .= '<tr><td><a href="/portal/?p=doc&amp;id=' . (int)$d['id'] . '">' . e(doc_label($d) . ' ' . $d['number']) . '</a></td><td>' . e(fdate($d['issue_date'])) . '</td><td class="r">' . e(money((int)$d['total_cents'])) . '</td><td class="r">' . e($bal) . '</td><td>' . status_pill(doc_status_label($d)) . '</td></tr>';
    }
    return $h . '</tbody></table>';
}

if ($p === 'requests') {
    page_top('My requests', 'client', $me, 'requests');
    $rows = q('SELECT * FROM requests WHERE client_id = ? ORDER BY created_at DESC', [$cid])->fetchAll();
    echo $verifyBanner . '<div class="hub-bar"><h1 class="hub-h1">My requests</h1><a class="btn btn-primary btn-sm" href="/portal/?p=start">New request</a></div><section class="hub-card">' . ($rows ? requests_table($rows) : empty_state('No requests yet.')) . '</section>';
    page_end(true); exit;
}

if ($p === 'request') {
    $r = row('SELECT * FROM requests WHERE id = ? AND client_id = ?', [(int)($_GET['id'] ?? 0), $cid]);
    if (!$r) back_with('Request not found.', '/portal/?p=requests');
    page_top('Request ' . $r['ref'], 'client', $me, 'requests');
    echo '<p><a href="/portal/?p=requests">← My requests</a></p><section class="hub-card"><div class="hub-bar"><h1 class="hub-h1">' . e($r['service']) . '</h1>' . status_pill(REQUEST_STATUSES[$r['status']]) . '</div>'
       . '<dl class="dl"><dt>Reference</dt><dd>' . e($r['ref']) . '</dd><dt>Submitted</dt><dd>' . e(date('j M Y H:i', strtotime($r['created_at']))) . '</dd><dt>Budget</dt><dd>' . e($r['budget'] ?: '—') . '</dd><dt>Timeline</dt><dd>' . e($r['timeline'] ?: '—') . '</dd></dl>'
       . '<div class="prewrap">' . e($r['description']) . '</div></section>';
    $docs = q("SELECT * FROM documents WHERE client_id = ? AND request_id = ? AND status <> 'draft' ORDER BY issued_at", [$cid, $r['id']])->fetchAll();
    if ($docs) echo '<section class="hub-card"><h2>Quotes &amp; invoices</h2>' . docs_table($docs) . '</section>';
    echo '<p><a class="btn btn-outline" href="/portal/?p=messages&amp;request=' . (int)$r['id'] . '">Message us about this request</a></p>';
    page_end(true); exit;
}

if ($p === 'docs') {
    page_top('Quotes & invoices', 'client', $me, 'docs');
    $rows = q("SELECT * FROM documents WHERE client_id = ? AND status <> 'draft' ORDER BY issued_at DESC", [$cid])->fetchAll();
    echo '<h1 class="hub-h1">Quotes &amp; invoices</h1><section class="hub-card">' . ($rows ? docs_table($rows) : empty_state('Your quotations and invoices will appear here.')) . '</section>';
    page_end(true); exit;
}

if ($p === 'doc') {
    $d = get_doc((int)($_GET['id'] ?? $_POST['id'] ?? 0), $cid);
    if (!$d) back_with('Document not found.', '/portal/?p=docs');
    if ($isPost && $d['kind'] === 'quote') {
        try { respond_quote((int)$d['id'], $cid, post('action', 10) === 'accept', oneline(post('name', 120))); flash(post('action', 10) === 'accept' ? 'Thank you — quotation accepted. We will send the deposit invoice and confirm the start date.' : 'Quotation declined. Tell us what to change in Messages.'); }
        catch (RuntimeException $x) { flash($x->getMessage(), 'err'); }
        redirect('/portal/?p=doc&id=' . (int)$d['id']);
    }
    page_top(doc_label($d) . ' ' . $d['number'], 'client', $me, 'docs');
    echo '<div class="hub-bar noprint"><a href="/portal/?p=docs">← Quotes &amp; invoices</a><button class="btn btn-outline btn-sm" onclick="window.print()">Print / save as PDF</button></div>' . render_doc($d);
    if ($d['kind'] === 'quote' && $d['status'] === 'issued' && $d['due_date'] >= date('Y-m-d')) {
        echo '<section class="hub-card noprint"><h2>Your response</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="id" value="' . (int)$d['id'] . '">'
           . '<div><label for="nm">Type your full name to accept</label><input id="nm" name="name" autocomplete="name" value="' . e($me['full_name']) . '"></div>'
           . '<p class="small">By accepting you confirm the scope and price in this quotation' . ($d['terms'] ? ' and its terms' : '') . '. Work starts after the deposit invoice is paid.</p>'
           . '<div class="btnrow"><button class="btn btn-primary" name="action" value="accept">Accept quotation</button><button class="btn btn-outline" name="action" value="decline" onclick="return confirm(\'Decline this quotation?\')">Decline</button></div></form></section>';
    }
    page_end(true); exit;
}

if ($p === 'messages') {
    $reqId = (int)($_GET['request'] ?? $_POST['request'] ?? 0);
    if ($reqId && !row('SELECT id FROM requests WHERE id = ? AND client_id = ?', [$reqId, $cid])) $reqId = 0;
    if ($isPost) {
        $body = post('body', 4000);
        if (mb_strlen($body) < 2) back_with('Type a message first.', '/portal/?p=messages');
        if (too_many('msg:' . $cid, 20, 60)) back_with('You have sent many messages in the last hour. Please WhatsApp us if it is urgent.', '/portal/?p=messages');
        hit('msg:' . $cid);
        q("INSERT INTO messages (client_id, request_id, sender, body, created_at) VALUES (?,?,'client',?,NOW())", [$cid, $reqId ?: null, $body]);
        audit('client', $cid, 'message_sent', 'client', $cid);
        notify_office('Client message — ' . ($me['company'] ?: $me['full_name']), $body, $me['email']);
        back_with('Message sent. We will reply here and by email.', '/portal/?p=messages', 'ok');
    }
    q("UPDATE messages SET read_at = NOW() WHERE client_id = ? AND sender = 'staff' AND read_at IS NULL", [$cid]);
    $msgs = q('SELECT m.*, r.ref FROM messages m LEFT JOIN requests r ON r.id = m.request_id WHERE m.client_id = ? ORDER BY m.created_at', [$cid])->fetchAll();
    page_top('Messages', 'client', $me, 'messages');
    echo '<h1 class="hub-h1">Messages</h1><section class="hub-card"><div class="thread">';
    if (!$msgs) echo empty_state('No messages yet. Ask us anything about your project below.');
    foreach ($msgs as $m) echo '<div class="msg ' . ($m['sender'] === 'client' ? 'mine' : 'theirs') . '"><div class="meta">' . ($m['sender'] === 'client' ? 'You' : 'Think Mybiz Tech') . ' · ' . e(date('j M Y H:i', strtotime($m['created_at']))) . ($m['ref'] ? ' · ' . e($m['ref']) : '') . '</div><div class="prewrap">' . e($m['body']) . '</div></div>';
    echo '</div><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="request" value="' . $reqId . '"><div><label for="body">New message</label><textarea id="body" name="body" rows="4" required></textarea></div><button class="btn btn-primary" type="submit">Send</button></form></section>';
    page_end(true); exit;
}

if ($p === 'profile') {
    if ($isPost) {
        if (post('action', 10) === 'password') {
            if (!password_verify((string)($_POST['current'] ?? ''), (string)$me['password_hash'])) back_with('Your current password is not correct.', '/portal/?p=profile');
            if ($m = valid_password((string)($_POST['password'] ?? ''))) back_with($m, '/portal/?p=profile');
            q('UPDATE clients SET password_hash = ?, updated_at = NOW() WHERE id = ?', [password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $cid]);
            audit('client', $cid, 'password_changed', 'client', $cid);
            back_with('Password changed.', '/portal/?p=profile', 'ok');
        }
        $name = oneline(post('name', 120));
        if ($name === '') back_with('Enter your name.', '/portal/?p=profile');
        $vat = oneline(post('vat_number', 20));
        if ($vat !== '' && !preg_match('/^4\d{9}$/', $vat)) back_with('A South African VAT number has 10 digits and starts with 4.', '/portal/?p=profile');
        q('UPDATE clients SET full_name = ?, company = ?, phone = ?, vat_number = ?, address = ?, marketing_consent = ?, updated_at = NOW() WHERE id = ?',
          [$name, oneline(post('company', 160)) ?: null, oneline(post('phone', 40)) ?: null, $vat ?: null, oneline(post('address', 400)) ?: null, post('marketing', 5) !== '' ? 1 : 0, $cid]);
        audit('client', $cid, 'profile_updated', 'client', $cid);
        back_with('Profile saved. New documents will use these details.', '/portal/?p=profile', 'ok');
    }
    page_top('Profile', 'client', $me, 'profile');
    echo $verifyBanner . '<h1 class="hub-h1">Profile</h1><div class="hub-grid2"><section class="hub-card"><h2>Billing details</h2><form method="post" class="form">' . csrf_field()
       . '<div><label>Email (contact us to change)</label><input value="' . e($me['email']) . '" disabled></div>'
       . '<div class="row"><div><label for="name">Full name</label><input id="name" name="name" required value="' . e($me['full_name']) . '"></div><div><label for="company">Company</label><input id="company" name="company" value="' . e($me['company']) . '"></div></div>'
       . '<div class="row"><div><label for="phone">Phone</label><input id="phone" name="phone" value="' . e($me['phone']) . '"></div><div><label for="vat">VAT number (if registered)</label><input id="vat" name="vat_number" inputmode="numeric" value="' . e($me['vat_number']) . '"></div></div>'
       . '<div><label for="address">Billing address</label><input id="address" name="address" value="' . e($me['address']) . '"></div>'
       . '<label class="consent"><input type="checkbox" name="marketing" value="1"' . ($me['marketing_consent'] ? ' checked' : '') . '> <span>Send me occasional updates and offers.</span></label>'
       . '<button class="btn btn-primary" type="submit">Save</button></form></section>'
       . '<section class="hub-card"><h2>Change password</h2><form method="post" class="form">' . csrf_field() . '<input type="hidden" name="action" value="password">'
       . '<div><label for="cur">Current password</label><input id="cur" name="current" type="password" required autocomplete="current-password"></div>'
       . '<div><label for="np">New password (10+ characters)</label><input id="np" name="password" type="password" minlength="10" required autocomplete="new-password"></div>'
       . '<button class="btn btn-outline" type="submit">Change password</button></form><p class="small" style="margin-top:18px">To close your account or get a copy of your information, send us a message.</p></section></div>';
    page_end(true); exit;
}

redirect('/portal/?p=home');
