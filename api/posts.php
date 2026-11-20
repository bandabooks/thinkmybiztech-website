<?php
/* Think Mybiz Tech — same-origin JSON list of the newest Insights posts for the home page (hand-edited). */
declare(strict_types=1);
require __DIR__ . '/cms.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Robots-Tag: noindex');
$posts = tmbt_cms_list((int)($_GET['n'] ?? 3));
if ($posts === false) { http_response_code(503); echo json_encode(['ok' => false]); exit; }
echo json_encode(['ok' => true, 'posts' => $posts], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
