<?php
// Minimal CSP report endpoint (no WP)
// Writes one line per report to wp-content/uploads/hmqz-csp.log
http_response_code(204);
header('Content-Type: text/plain; charset=utf-8');
$body = file_get_contents('php://input');
$line = date('c').' '.($body ?: '{"note":"empty-body"}').PHP_EOL;
$logf = __DIR__ . '/wp-content/uploads/hmqz-csp.log';
@file_put_contents($logf, $line, FILE_APPEND | LOCK_EX);
exit;
