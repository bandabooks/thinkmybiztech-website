<?php
/* Think Mybiz Tech — XML sitemap of published Insights articles, served at /sitemap-insights.xml (hand-edited). */
declare(strict_types=1);
require __DIR__ . '/cms.php';
$posts = tmbt_cms_all_posts();
if ($posts === false) { http_response_code(503); header('Retry-After: 300'); exit; }
header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($posts as $p) {
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($p['slug'] ?? '')));
    if ($slug === '') continue;
    $mod = substr((string)($p['modified_gmt'] ?? ''), 0, 10);
    echo '<url><loc>https://thinkmybiztech.com/insights/' . $slug . '/</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . "</url>\n";
}
echo "</urlset>\n";
