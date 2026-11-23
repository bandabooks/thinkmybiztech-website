<?php
/*
 * Think Mybiz Tech Client Hub — shared bootstrap (hand-edited, not generated).
 * Database credentials live OUTSIDE public_html in tmbt_private/portal-config.php,
 * written once by portal/setup.php. Never commit credentials: this repository is public.
 */
declare(strict_types=1);

date_default_timezone_set('Africa/Johannesburg');

define('TMBT_PRIVATE', getenv('TMBT_PRIVATE_DIR') ?: dirname(__DIR__, 3) . '/tmbt_private');
const HUB_CONFIG = TMBT_PRIVATE . '/portal-config.php';
const HUB_SITE = 'https://thinkmybiztech.com';
const HUB_FROM = 'info@thinkmybiztech.com';
const HUB_GRAPH = 'https://graph.facebook.com/v21.0';

function hub_config(bool $reload = false): array {
    static $cfg = null;
    if ($cfg === null || $reload) $cfg = is_file(HUB_CONFIG) ? (require HUB_CONFIG) : [];
    return is_array($cfg) ? $cfg : [];
}
function hub_installed(): bool { return hub_config() !== []; }

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = hub_config();
        $pdo = new PDO('mysql:host=' . ($c['db_host'] ?? 'localhost') . ';dbname=' . $c['db_name'] . ';charset=utf8mb4', $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+02:00'");
    }
    return $pdo;
}
function q(string $sql, array $args = []): PDOStatement { $st = db()->prepare($sql); $st->execute($args); return $st; }
function row(string $sql, array $args = []): ?array { $r = q($sql, $args)->fetch(); return $r ?: null; }
function val(string $sql, array $args = []) { return q($sql, $args)->fetchColumn(); }

/* ---------- Session, CSRF, output ---------- */
function hub_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('tmbt_hub');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/portal/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    if (isset($_SESSION['seen']) && time() - $_SESSION['seen'] > 7200) { $_SESSION = []; session_regenerate_id(true); }
    $_SESSION['seen'] = time();
}
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf_token(): string { hub_session(); if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">'; }
function csrf_check(): void {
    hub_session();
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) { http_response_code(400); exit('Your session expired. Go back, refresh the page and try again.'); }
}
function redirect(string $to): never { header('Location: ' . $to); exit; }
function flash(?string $msg = null, string $type = 'ok'): ?array {
    hub_session();
    if ($msg !== null) { $_SESSION['flash'] = [$type, $msg]; return null; }
    $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f;
}
function post(string $k, int $max = 500): string {
    $v = isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}
if (!function_exists('oneline')) {   // /api/enquiry.php declares its own equivalent before including this file
    function oneline(string $v): string { return trim(preg_replace('/[\r\n\t]+/', ' ', $v) ?? ''); }
}
function ip_hash(): string {
    $ip = trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''))[0]);
    return substr(hash('sha256', $ip . '|tmbt'), 0, 16);
}

/* ---------- Rate limiting ---------- */
function too_many(string $key, int $max, int $minutes): bool {
    return (int)val('SELECT COUNT(*) FROM login_attempts WHERE key_hash = ? AND created_at > (NOW() - INTERVAL ? MINUTE)', [hash('sha256', $key), $minutes]) >= $max;
}
function hit(string $key): void { q('INSERT INTO login_attempts (key_hash, created_at) VALUES (?, NOW())', [hash('sha256', $key)]); }
function clear_hits(string $key): void { q('DELETE FROM login_attempts WHERE key_hash = ?', [hash('sha256', $key)]); }

/* ---------- Audit ---------- */
function audit(string $actorType, ?int $actorId, string $action, ?string $entity = null, ?int $entityId = null, array $details = []): void {
    q('INSERT INTO audit_log (actor_type, actor_id, action, entity, entity_id, details, ip_hash, created_at) VALUES (?,?,?,?,?,?,?,NOW())',
      [$actorType, $actorId, $action, $entity, $entityId, $details ? json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null, ip_hash()]);
}

/* ---------- Settings ---------- */
function setting(string $k, ?string $default = null): ?string {
    static $cache = null;
    if ($cache === null) { $cache = []; foreach (q('SELECT k, v FROM settings')->fetchAll() as $r) $cache[$r['k']] = $r['v']; }
    return array_key_exists($k, $cache) && $cache[$k] !== null && $cache[$k] !== '' ? $cache[$k] : $default;
}
function set_setting(string $k, ?string $v): void {
    q('INSERT INTO settings (k, v, updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v), updated_at = NOW()', [$k, $v]);
}

/* ---------- Auth ---------- */
function current_client(): ?array {
    hub_session();
    if (empty($_SESSION['client_id'])) return null;
    return row("SELECT * FROM clients WHERE id = ? AND status = 'active'", [$_SESSION['client_id']]);
}
function require_client(): array { $c = current_client(); if (!$c) redirect('/portal/?p=login&next=' . rawurlencode($_SERVER['REQUEST_URI'] ?? '/portal/')); return $c; }
function current_staff(): ?array {
    hub_session();
    if (empty($_SESSION['staff_id'])) return null;
    return row('SELECT * FROM staff WHERE id = ? AND active = 1', [$_SESSION['staff_id']]);
}
function require_staff(bool $adminOnly = false): array {
    $s = current_staff();
    if (!$s) redirect('/portal/admin/?p=login');
    if ($adminOnly && $s['role'] !== 'admin') { http_response_code(403); exit('Only an administrator can do this.'); }
    return $s;
}
function login_as(string $kind, int $id): void {
    hub_session(); session_regenerate_id(true);
    $_SESSION[$kind . '_id'] = $id; $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
function valid_password(string $p): ?string { return strlen($p) < 10 ? 'Use at least 10 characters for your password.' : null; }
function safe_next(string $n, string $fallback): string { return preg_match('#^/portal/[A-Za-z0-9/?=&._%-]*$#', $n) ? $n : $fallback; }

/* ---------- One-time tokens (verify email, reset password) ---------- */
function make_token(string $kind, int $subjectId, int $hours): string {
    $t = bin2hex(random_bytes(24));
    q('UPDATE tokens SET used_at = NOW() WHERE kind = ? AND subject_id = ? AND used_at IS NULL', [$kind, $subjectId]);
    q('INSERT INTO tokens (kind, subject_id, token_hash, expires_at, created_at) VALUES (?,?,?,(NOW() + INTERVAL ? HOUR),NOW())', [$kind, $subjectId, hash('sha256', $t), $hours]);
    return $t;
}
function use_token(string $kind, string $t): ?int {
    if (!preg_match('/^[a-f0-9]{48}$/', $t)) return null;
    $r = row('SELECT id, subject_id FROM tokens WHERE kind = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()', [$kind, hash('sha256', $t)]);
    if (!$r) return null;
    $st = q('UPDATE tokens SET used_at = NOW() WHERE id = ? AND used_at IS NULL', [$r['id']]);
    return $st->rowCount() === 1 ? (int)$r['subject_id'] : null;
}

/* ---------- Email ---------- */
function send_mail(string $to, string $subject, string $body, ?string $replyTo = null): bool {
    if (getenv('TMBT_DEV')) { @file_put_contents(TMBT_PRIVATE . '/dev-mail.log', "TO: $to\nSUBJECT: $subject\n$body\n-----\n", FILE_APPEND); return true; }
    $h = ['From: Think Mybiz Tech <' . HUB_FROM . '>', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'];
    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $h[] = 'Reply-To: ' . $replyTo;
    $body .= "\n\n—\nThink Mybiz Tech · " . HUB_SITE . "\n063 621 5642 · info@thinkmybiztech.com\n";
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $h), '-f' . HUB_FROM);
}
function notify_office(string $subject, string $body, ?string $replyTo = null): void {
    send_mail(setting('notify_email', HUB_FROM), $subject, $body . "\n\nOpen the admin: " . HUB_SITE . '/portal/admin/', $replyTo);
}

/* ---------- Money ---------- */
function money(int $cents): string { return ($cents < 0 ? '-R' : 'R') . number_format(abs($cents) / 100, 2, '.', ' '); }
/* "1 250.50" / "1250,5" -> 125050 cents; null when invalid. */
function parse_money(string $s): ?int {
    $s = str_replace([' ', "\u{00A0}", 'R', 'r'], '', trim($s));
    if (preg_match('/^\d+,\d{1,2}$/', $s)) $s = str_replace(',', '.', $s);
    $s = str_replace(',', '', $s);
    if (!preg_match('/^-?\d{1,9}(\.\d{1,2})?$/', $s)) return null;
    [$w, $f] = array_pad(explode('.', ltrim($s, '-')), 2, '0');
    $c = (int)$w * 100 + (int)str_pad(substr($f, 0, 2), 2, '0');
    return str_starts_with($s, '-') ? -$c : $c;
}
function parse_qty(string $s): ?int {
    $s = str_replace([' ', ','], ['', '.'], trim($s));
    if (!preg_match('/^\d{1,6}(\.\d{1,3})?$/', $s)) return null;
    [$w, $f] = array_pad(explode('.', $s), 2, '0');
    return (int)$w * 1000 + (int)str_pad(substr($f, 0, 3), 3, '0');
}
function fmt_qty(int $m): string { return rtrim(rtrim(number_format($m / 1000, 3, '.', ''), '0'), '.'); }
function fdate(?string $d): string { return $d ? date('j M Y', strtotime($d)) : '—'; }

/* ---------- Service catalogue (generated from _src/build.py) ---------- */
function catalogue(): array { static $c = null; if ($c === null) $c = require __DIR__ . '/catalogue.php'; return $c; }
function package_by_id(?string $id): ?array { foreach (catalogue() as $p) if ($p['id'] === $id) return $p; return null; }

/* ---------- Service requests (shared by portal, admin and /api/enquiry.php) ---------- */
function new_ref(): string { return 'TMT-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5)); }
function create_request(array $d, string $source, ?int $clientId): array {
    $ref = $d['ref'] ?? new_ref();
    q('INSERT INTO requests (ref, client_id, contact_name, contact_email, contact_phone, company, service, package_id, budget, timeline, description, source, status,
        utm_source, utm_medium, utm_campaign, page, ip_hash, consent_at, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'new\',?,?,?,?,?,NOW(),NOW(),NOW())', [
        $ref, $clientId, $d['name'], strtolower($d['email']), $d['phone'] ?: null, $d['company'] ?: null, $d['service'] ?: null, $d['package_id'] ?? null,
        $d['budget'] ?? null, $d['timeline'] ?? null, $d['description'], $source,
        $d['utm_source'] ?? null, $d['utm_medium'] ?? null, $d['utm_campaign'] ?? null, $d['page'] ?? null, ip_hash()]);
    $id = (int)db()->lastInsertId();
    audit($clientId ? 'client' : 'public', $clientId, 'request_created', 'request', $id, ['ref' => $ref, 'source' => $source]);
    return ['id' => $id, 'ref' => $ref];
}
/* Website enquiries are linked to a client account only once that client has verified the same email address. */
function link_requests_to_client(int $clientId, string $email): void {
    q('UPDATE requests SET client_id = ?, updated_at = NOW() WHERE client_id IS NULL AND contact_email = ?', [$clientId, strtolower($email)]);
}
const REQUEST_STATUSES = ['new' => 'New', 'contacted' => 'In discussion', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost', 'closed' => 'Closed'];
