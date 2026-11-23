<?php
/* Public, non-secret site configuration for /assets/js/track.js: advertising tag IDs set in Admin → Settings → Tracking.
   Read from tmbt_private/site-config.json (written by the admin) so the website does not need a database call per page. */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=600');
header('X-Robots-Tag: noindex');
$dir = getenv('TMBT_PRIVATE_DIR') ?: dirname(__DIR__, 2) . '/tmbt_private';
$c = is_file($dir . '/site-config.json') ? (json_decode((string)@file_get_contents($dir . '/site-config.json'), true) ?: []) : [];
echo json_encode([
    'meta' => preg_replace('/\D/', '', (string)($c['meta'] ?? '')),
    'linkedin' => preg_replace('/\D/', '', (string)($c['linkedin'] ?? '')),
    'tiktok' => preg_replace('/[^A-Z0-9]/', '', (string)($c['tiktok'] ?? '')),
]);
