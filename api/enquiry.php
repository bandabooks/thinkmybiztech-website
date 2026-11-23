<?php
/*
 * Think Mybiz Tech — website enquiry endpoint (hand-edited, not generated).
 * POST from /contact/ form. Every valid enquiry is
 *   1. appended to a private log OUTSIDE public_html (survives a failed email), and
 *   2. emailed to info@thinkmybiztech.com with Reply-To set to the visitor.
 * Spam controls: honeypot field, minimum fill time, per-IP rate limit, field length limits.
 * POPIA: consent required; only the fields the visitor types are kept, plus time and a hashed IP for abuse control.
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

const TO_EMAIL = 'info@thinkmybiztech.com';
const RATE_MAX = 5;          // enquiries per IP …
const RATE_WINDOW = 3600;    // … per hour
const MIN_FILL_SECONDS = 3;

function out(int $code, array $body): void { http_response_code($code); echo json_encode($body); exit; }
function field(string $k, int $max): string {
    $v = isset($_POST[$k]) && is_string($_POST[$k]) ? trim($_POST[$k]) : '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr($v, 0, $max);
}
function oneline(string $v): string { return trim(preg_replace('/[\r\n]+/', ' ', $v) ?? ''); }

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') out(405, ['ok' => false, 'error' => 'Method not allowed']);

// Same-site check (blocks other sites posting into this endpoint)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && !getenv('TMBT_DEV') && !preg_match('#^https://(www\.)?thinkmybiztech\.com$#', $origin)) out(403, ['ok' => false, 'error' => 'Forbidden']);

$private = getenv('TMBT_PRIVATE_DIR') ?: dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..')) . '/tmbt_private';
if (!is_dir($private)) @mkdir($private, 0700, true);

// Honeypot + timing: silently accept and drop bots
$hp = field('website', 200);
$started = (int) field('ts', 20);
if ($hp !== '' || ($started > 0 && (time() - intdiv($started, 1000)) < MIN_FILL_SECONDS)) out(200, ['ok' => true, 'ref' => 'TMT-0']);

// Rate limit per hashed IP
$ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
$ip = trim(explode(',', $ip)[0]);
$ipHash = substr(hash('sha256', $ip . '|tmbt'), 0, 16);
$rateFile = $private . '/rate.json';
$rates = is_file($rateFile) ? (json_decode((string)@file_get_contents($rateFile), true) ?: []) : [];
$now = time();
foreach ($rates as $k => $times) { $rates[$k] = array_values(array_filter((array)$times, fn($t) => $t > $now - RATE_WINDOW)); if (!$rates[$k]) unset($rates[$k]); }
if (count($rates[$ipHash] ?? []) >= RATE_MAX) out(429, ['ok' => false, 'error' => 'Too many enquiries from this connection. Please call or WhatsApp 063 621 5642.']);

$d = [
    'name'    => oneline(field('name', 120)),
    'company' => oneline(field('company', 160)),
    'email'   => oneline(field('email', 160)),
    'phone'   => oneline(field('phone', 40)),
    'service' => oneline(field('service', 120)),
    'timeline'=> oneline(field('budget', 60)),
    'message' => field('message', 5000),
    'page'    => oneline(field('page', 200)),
];
$errors = [];
if ($d['name'] === '') $errors[] = 'name';
if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'email';
if (mb_strlen($d['message']) < 5) $errors[] = 'message';
if (field('consent', 10) === '') $errors[] = 'consent';
if ($errors) out(422, ['ok' => false, 'error' => 'Please complete: ' . implode(', ', $errors), 'fields' => $errors]);

$ref = 'TMT-' . gmdate('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
$record = ['ref' => $ref, 'received_at' => gmdate('c'), 'ip_hash' => $ipHash, 'consent' => true] + $d;

// 1. Durable log (JSON Lines), private, locked append
$logOk = @file_put_contents($private . '/enquiries.jsonl', json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX) !== false;
if ($logOk) @chmod($private . '/enquiries.jsonl', 0600);
// 1b. Client Hub lead pipeline (when installed). The log and email above still work if this fails.
$dbOk = false;
try {
    require_once __DIR__ . '/../portal/lib/bootstrap.php';
    if (hub_installed()) {
        create_request(['ref' => $ref, 'name' => $d['name'], 'email' => $d['email'], 'phone' => $d['phone'], 'company' => $d['company'],
            'service' => html_entity_decode($d['service']), 'timeline' => $d['timeline'], 'description' => $d['message'], 'page' => $d['page'],
            'utm_source' => oneline(field('utm_source', 80)) ?: null, 'utm_medium' => oneline(field('utm_medium', 80)) ?: null,
            'utm_campaign' => oneline(field('utm_campaign', 120)) ?: null], 'website_form', null);
        $dbOk = true;
    }
} catch (Throwable $t) { error_log('TMBT enquiry DB insert failed: ' . $t->getMessage()); }
$rates[$ipHash][] = $now;
@file_put_contents($rateFile, json_encode($rates), LOCK_EX);

// 2. Email to the practice mailbox
$subject = 'Website enquiry ' . $ref . ' — ' . ($d['company'] !== '' ? $d['company'] : $d['name']);
$body = "New enquiry from thinkmybiztech.com\n\nReference: $ref\nReceived: " . date('Y-m-d H:i', $now) . " (server time)\n\n"
      . "Name: {$d['name']}\nCompany: {$d['company']}\nEmail: {$d['email']}\nPhone: {$d['phone']}\n"
      . "Service: {$d['service']}\nTimeline: {$d['timeline']}\nPage: {$d['page']}\n\nMessage:\n{$d['message']}\n\n"
      . "— Reply to this email to answer the client directly. POPIA consent was given on the form.\n";
$headers = [
    'From: Think Mybiz Tech Website <' . TO_EMAIL . '>',
    'Reply-To: ' . (preg_replace('/[^\p{L}\p{N} .\'-]/u', '', $d['name']) ?: 'Client') . ' <' . $d['email'] . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-TMBT-Ref: ' . $ref,
];
$mailOk = @mail(TO_EMAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers), '-f' . TO_EMAIL);

if (!$logOk && !$mailOk && !$dbOk) out(500, ['ok' => false, 'error' => 'We could not record your enquiry. Please email ' . TO_EMAIL . ' or WhatsApp 063 621 5642.']);
out(200, ['ok' => true, 'ref' => $ref]);
