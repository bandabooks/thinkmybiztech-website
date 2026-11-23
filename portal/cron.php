<?php
/* Run by a Hostinger cron job every 15 minutes (CLI only): publishes scheduled Facebook posts. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/social.php';
if (!hub_installed()) exit(0);
q('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
echo date('c') . ' facebook posts published: ' . publish_due_social(10) . "\n";
