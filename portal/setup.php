<?php
/*
 * One-time setup for the Think Mybiz Tech Client Hub.
 * Connects only to the local MySQL server, creates the tables, creates the first admin (who sets a password
 * from an emailed link) and writes tmbt_private/portal-config.php outside public_html.
 * Once that file exists this page returns 404.
 */
declare(strict_types=1);
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/layout.php';

if (hub_installed()) { http_response_code(404); exit('Not found.'); }
hub_session();
$error = null; $link = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    csrf_check();
    $dbName = trim((string)($_POST['db_name'] ?? '')); $dbUser = trim((string)($_POST['db_user'] ?? '')); $dbPass = (string)($_POST['db_pass'] ?? '');
    $name = oneline(post('admin_name', 120)); $email = strtolower(oneline(post('admin_email', 190)));
    try {
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName) || !preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbUser)) throw new RuntimeException('Enter the database name and user exactly as hPanel shows them.');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter the administrator\'s name and email.');
        $host = getenv('TMBT_DEV') ? (getenv('TMBT_DB_HOST') ?: 'localhost') : 'localhost';
        $pdo = new PDO("mysql:host=$host;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $sql = preg_replace('/^--.*$/m', '', (string)file_get_contents(__DIR__ . '/lib/schema.sql'));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) $pdo->exec($stmt);
        $now = date('Y-m-d H:i:s');
        if ((int)$pdo->query('SELECT COUNT(*) FROM staff')->fetchColumn() === 0) {
            $pdo->prepare("INSERT INTO staff (email, full_name, role, active, created_at) VALUES (?,?,'admin',1,?)")->execute([$email, $name, $now]);
        }
        $defaults = [
            'quote_terms' => "1. Prices are in South African rand and are valid until the date shown.\n2. A 50% deposit is payable to start; the balance is payable at go-live, before the site or system is transferred.\n3. Scope is limited to the items listed. Additional work is quoted separately or billed at the hourly rate after your approval.\n4. You own your content and data. Third-party costs (domains, licences, gateway fees, advertising) are payable by the client.",
            'invoice_terms' => "Payment by EFT using the invoice number as reference. Please send proof of payment to info@thinkmybiztech.com.",
            'payment_terms_days' => '7', 'quote_valid_days' => '30', 'quote_prefix' => 'QUO', 'invoice_prefix' => 'INV',
            // Contracting entity: Think Mybiz is a VAT vendor (confirmed by MF Banda 23/11/2026). Details as published on thinkmybiz.co.za.
            // Banking is NOT pre-filled: MF Banda enters it in Admin -> Settings, where all of these can be corrected.
            'co_legal_name' => 'Think Mybiz Accountants and Tax Practioners (Pty) Ltd', 'co_reg_no' => '2021/819201/07',
            'co_vat_number' => '4010320630', 'co_vat_registered' => '1',
        ];
        $st = $pdo->prepare('INSERT IGNORE INTO settings (k, v, updated_at) VALUES (?,?,?)');
        foreach ($defaults as $k => $v) $st->execute([$k, $v, $now]);
        if (!is_dir(TMBT_PRIVATE)) @mkdir(TMBT_PRIVATE, 0700, true);
        $cfg = "<?php\n// Think Mybiz Tech Client Hub settings. Written by portal/setup.php. Private: never commit or share.\nreturn " . var_export(['db_host' => $host, 'db_name' => $dbName, 'db_user' => $dbUser, 'db_pass' => $dbPass, 'installed_at' => date('c')], true) . ";\n";
        if (@file_put_contents(HUB_CONFIG, $cfg, LOCK_EX) === false) throw new RuntimeException('Could not save the settings file in tmbt_private. Check folder permissions.');
        @chmod(HUB_CONFIG, 0600);
        hub_config(true);
        $sid = (int)db()->query("SELECT id FROM staff WHERE role = 'admin' ORDER BY id LIMIT 1")->fetchColumn();
        $t = make_token('staff_reset', $sid, 24);
        $link = HUB_SITE . '/portal/admin/?p=reset&t=' . $t;
        send_mail($email, 'Set your Client Hub admin password', "Hello $name,\n\nThe Think Mybiz Tech Client Hub is installed. Choose your admin password (valid 24 hours):\n$link");
        audit('system', null, 'installed', null, null, ['admin' => $email]);
    } catch (PDOException $x) { $error = 'Could not connect to the database with those details. Check the name, user and password in hPanel.'; }
    catch (RuntimeException $x) { $error = $x->getMessage(); }
}
page_top('Set up the Client Hub', 'admin');
echo '<div class="hub-card narrow"><h1>Set up the Client Hub</h1>';
if ($link) {
    echo '<p class="notice ok">Setup is complete and this page is now switched off. A password link was also emailed to the administrator.</p><p><a class="btn btn-primary" href="' . e($link) . '">Choose the admin password now</a></p>';
} else {
    echo '<p>Create a MySQL database in hPanel (Websites → Manage → Databases), then enter its details. This page works only once.</p>' . ($error ? '<p class="notice err">' . e($error) . '</p>' : '')
       . '<form method="post" class="form" autocomplete="off">' . csrf_field() . '<h2>Database</h2><div><label>Database name</label><input name="db_name" required value="' . e($_POST['db_name'] ?? '') . '"></div>'
       . '<div><label>Database user</label><input name="db_user" required value="' . e($_POST['db_user'] ?? '') . '"></div><div><label>Database password</label><input name="db_pass" type="password" required></div>'
       . '<h2>First administrator</h2><div><label>Full name</label><input name="admin_name" required value="' . e($_POST['admin_name'] ?? 'MF Banda') . '"></div><div><label>Email</label><input name="admin_email" type="email" required value="' . e($_POST['admin_email'] ?? 'info@thinkmybiztech.com') . '"></div>'
       . '<button class="btn btn-primary">Install</button></form>';
}
echo '</div>';
page_end();
