<?php
/* Facebook Page publishing (same approach as the Gesond members system). Tokens live in the database, never in Git. */
declare(strict_types=1);

function fb_ready(): bool { return (bool)setting('fb_page_id') && (bool)setting('fb_page_token') && function_exists('curl_init'); }

function fb_call(string $method, string $path, array $fields): array {
    if (!function_exists('curl_init')) return [0, ['error' => ['message' => 'cURL is not available on this server']]];
    $url = HUB_GRAPH . $path;
    $ch = curl_init($method === 'GET' ? $url . '?' . http_build_query($fields) : $url);
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20];
    if ($method === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = http_build_query($fields); }
    curl_setopt_array($ch, $opt);
    $raw = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    return [$code, json_decode((string)$raw, true) ?: []];
}

/* Returns [ok, page name or error message]. */
function fb_check(string $pageId, string $token): array {
    [$code, $res] = fb_call('GET', '/' . rawurlencode($pageId), ['fields' => 'name', 'access_token' => $token]);
    if ($code === 200 && !empty($res['name'])) return [true, (string)$res['name']];
    return [false, substr((string)($res['error']['message'] ?? 'HTTP ' . $code), 0, 200)];
}

/* Publish due posts. Each post is claimed with a conditional UPDATE so it can never be sent twice. */
function publish_due_social(int $limit = 3): int {
    if (!fb_ready()) return 0;
    $n = 0;
    foreach (q("SELECT * FROM social_posts WHERE fb_status = 'pending' AND publish_at <= NOW() ORDER BY publish_at LIMIT " . (int)$limit)->fetchAll() as $p) {
        $claim = q("UPDATE social_posts SET fb_status = 'sending' WHERE id = ? AND fb_status = 'pending'", [$p['id']]);
        if ($claim->rowCount() !== 1) continue;
        $f = ['message' => $p['body'], 'access_token' => setting('fb_page_token')];
        if ($p['link_url']) $f['link'] = $p['link_url'];
        [$code, $res] = fb_call('POST', '/' . rawurlencode((string)setting('fb_page_id')) . '/feed', $f);
        if ($code === 200 && !empty($res['id'])) {
            q("UPDATE social_posts SET fb_status = 'posted', fb_post_id = ?, fb_error = NULL WHERE id = ?", [$res['id'], $p['id']]);
            audit('system', null, 'facebook_posted', 'social', (int)$p['id'], ['fb_id' => $res['id']]);
            $n++;
        } else {
            q("UPDATE social_posts SET fb_status = 'failed', fb_error = ? WHERE id = ?", [substr((string)($res['error']['message'] ?? 'HTTP ' . $code), 0, 290), $p['id']]);
        }
    }
    return $n;
}
