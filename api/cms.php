<?php
/*
 * Think Mybiz Tech — read-only client for the headless WordPress CMS (hand-edited, not generated).
 * Used by /insights/article.php and /api/sitemap-insights.php to render posts server-side for SEO.
 * Responses are cached for 10 minutes in a private folder outside public_html; a stale copy is used if the CMS is down.
 */
declare(strict_types=1);

const TMBT_CMS_API = 'https://cms.thinkmybiztech.com/wp-json/wp/v2';
const TMBT_CMS_TTL = 600;

function tmbt_private_dir(): string {
    $d = getenv('TMBT_PRIVATE_DIR') ?: dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/..')) . '/tmbt_private';
    if (!is_dir($d . '/cache')) @mkdir($d . '/cache', 0700, true);
    return $d;
}

/** @return array|false decoded JSON list, or false if the CMS is unreachable and nothing is cached */
function tmbt_cms_get(string $path) {
    $file = tmbt_private_dir() . '/cache/' . sha1($path) . '.json';
    if (is_file($file) && (time() - filemtime($file)) < TMBT_CMS_TTL) {
        $c = json_decode((string)file_get_contents($file), true);
        if (is_array($c)) return $c;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 6, 'ignore_errors' => true, 'header' => "Accept: application/json\r\nUser-Agent: thinkmybiztech-site\r\n"]]);
    // The CMS sits behind Hostinger's CDN; a time-bucket parameter stops a stale edge copy being served for more than the TTL.
    $url = TMBT_CMS_API . $path . (strpos($path, '?') === false ? '?' : '&') . '_t=' . intdiv(time(), TMBT_CMS_TTL);
    $raw = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $h) if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) $status = (int)$m[1];
    $data = $raw !== false ? json_decode($raw, true) : null;
    if ($status === 200 && is_array($data)) {
        @file_put_contents($file, $raw, LOCK_EX);
        return $data;
    }
    if (is_file($file)) { $c = json_decode((string)file_get_contents($file), true); if (is_array($c)) return $c; }
    return false;
}

/** @return array|null|false post, null if it does not exist, false if the CMS is unavailable */
function tmbt_cms_post(string $slug) {
    $r = tmbt_cms_get('/posts?status=publish&_embed=wp:featuredmedia&slug=' . rawurlencode($slug));
    if ($r === false) return false;
    return $r[0] ?? null;
}

/** @return array|false list of ['slug','modified_gmt'] for every published post */
function tmbt_cms_all_posts() {
    $all = [];
    for ($page = 1; $page <= 10; $page++) {
        $r = tmbt_cms_get('/posts?status=publish&per_page=100&_fields=slug,modified_gmt&page=' . $page);
        if ($r === false) return $page === 1 ? false : $all;
        $all = array_merge($all, $r);
        if (count($r) < 100) break;
    }
    return $all;
}

/** Cover image for a post: WordPress featured image, else a generated cover in /assets/img/insights/<slug>.png, else ''. */
function tmbt_post_image(array $p): string {
    $img = $p['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '';
    if ($img !== '') return (string)$img;
    $slug = preg_replace('/[^a-z0-9-]/', '', strtolower((string)($p['slug'] ?? '')));
    $root = (string)($_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__));
    return ($slug !== '' && is_file($root . '/assets/img/insights/' . $slug . '.png')) ? '/assets/img/insights/' . $slug . '.png' : '';
}

/** @return array|false compact list of the newest published posts for listings */
function tmbt_cms_list(int $n) {
    $n = max(1, min(50, $n));
    $r = tmbt_cms_get('/posts?status=publish&_embed=wp:featuredmedia&per_page=' . $n);
    if ($r === false) return false;
    $out = [];
    foreach ($r as $p) {
        if (!is_array($p) || empty($p['slug'])) continue;
        $out[] = [
            'slug' => (string)$p['slug'],
            'title' => (string)($p['title']['rendered'] ?? ''),
            'date' => substr((string)($p['date'] ?? ''), 0, 10),
            'excerpt' => mb_substr(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string)($p['excerpt']['rendered'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))), 0, 180),
            'image' => tmbt_post_image($p),
        ];
    }
    return $out;
}
