<?php
declare(strict_types=1);

function css_ver(string $rel): string { $f = dirname(__DIR__, 2) . $rel; return is_file($f) ? substr(sha1_file($f), 0, 10) : '1'; }

function page_top(string $title, string $area = 'client', ?array $who = null, string $active = ''): void {
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $logo = '<svg viewBox="0 0 40 40" aria-hidden="true"><rect width="40" height="40" rx="9" fill="#fff"/><path d="M11 12.5h18v4.2h-6.8V29h-4.4V16.7H11z" fill="#0a1d3f"/><circle cx="12.5" cy="25" r="2.2" fill="#2f6df6"/><circle cx="27.5" cy="25" r="2.2" fill="#19c3a5"/></svg>';
    if ($area === 'admin') {
        $nav = [['home', 'Dashboard'], ['requests', 'Requests'], ['clients', 'Clients'], ['quotes', 'Quotations'], ['invoices', 'Invoices'], ['messages', 'Messages'], ['social', 'Social'], ['settings', 'Settings']];
        $base = '/portal/admin/?p=';
    } else {
        $nav = $who ? [['home', 'Overview'], ['requests', 'My requests'], ['docs', 'Quotes &amp; invoices'], ['messages', 'Messages'], ['profile', 'Profile']] : [];
        $base = '/portal/?p=';
    }
    $links = '';
    if ($who) {
        foreach ($nav as [$k, $t]) $links .= '<a href="' . $base . $k . '"' . ($k === $active ? ' class="on"' : '') . '>' . $t . '</a>';
        if ($area === 'client') $links .= '<a class="hub-new" href="/portal/?p=start">+ New request</a>';
        $links .= '<a href="' . $base . 'logout" class="hub-out">Log out</a>';
    } else {
        $links = '<a href="/">Website</a><a href="/portfolio/">Portfolio</a><a href="/pricing/">Pricing</a>';
    }
    echo '<!DOCTYPE html><html lang="en-ZA"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">'
       . '<title>' . e($title) . ' | Think Mybiz Tech ' . ($area === 'admin' ? 'Admin' : 'Client Hub') . '</title><meta name="theme-color" content="#06132b">'
       . '<link rel="icon" href="/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/assets/css/style.css?v=' . css_ver('/assets/css/style.css') . '">'
       . '<link rel="stylesheet" href="/portal/assets/hub.css?v=' . css_ver('/portal/assets/hub.css') . '"></head>'
       . '<body class="hub hub-' . e($area) . '"><header class="hub-top"><div class="hub-wrap"><a class="brand" href="' . ($area === 'admin' ? '/portal/admin/' : '/portal/') . '">' . $logo
       . '<span class="brand-text"><strong>THINK MYBIZ</strong><span>' . ($area === 'admin' ? 'ADMIN' : 'CLIENT HUB') . '</span></span></a>'
       . '<button class="hub-burger" aria-label="Menu" onclick="document.querySelector(\'.hub-nav\').classList.toggle(\'open\')">☰</button>'
       . '<nav class="hub-nav">' . $links . '</nav></div></header><main class="hub-main"><div class="hub-wrap">';
    if ($f = flash()) echo '<div class="notice ' . ($f[0] === 'err' ? 'err' : 'ok') . '" role="' . ($f[0] === 'err' ? 'alert' : 'status') . '">' . e($f[1]) . '</div>';
}

function page_end(bool $track = false, array $events = []): void {
    echo '</div></main><footer class="hub-foot"><div class="hub-wrap">© ' . date('Y') . ' Think Mybiz Tech · <a href="/privacy/">Privacy &amp; POPIA</a> · <a href="mailto:info@thinkmybiztech.com">info@thinkmybiztech.com</a> · 063 621 5642</div></footer>';
    if ($track) {
        if ($events) echo '<script>window.TMBT_EVENTS=' . json_encode(array_values($events), JSON_HEX_TAG) . ';</script>';
        echo '<script src="/assets/js/track.js?v=' . css_ver('/assets/js/track.js') . '" defer></script>';
    }
    echo '<script src="/portal/assets/hub.js?v=' . css_ver('/portal/assets/hub.js') . '" defer></script></body></html>';
}

function status_pill(string $label): string { return '<span class="pill s-' . e(strtolower(str_replace([' ', '-'], '', $label))) . '">' . e($label) . '</span>'; }
function kpi(string $label, string $value, string $sub = '', string $href = ''): string {
    $in = '<small>' . e($label) . '</small><strong>' . e($value) . '</strong>' . ($sub !== '' ? '<span>' . e($sub) . '</span>' : '');
    return $href ? '<a class="kpi" href="' . e($href) . '">' . $in . '</a>' : '<div class="kpi">' . $in . '</div>';
}
function empty_state(string $msg): string { return '<p class="hub-empty">' . $msg . '</p>'; }
