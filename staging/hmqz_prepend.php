<?php
/* HMQZ auto_prepend: strip UA + add nonce + CSP-RO */
if (PHP_SAPI === 'cli') { return; }  // don't affect wp-cli

$hmqz_nonce = base64_encode(random_bytes(16));

/* CSP-Report-Only — allow GA4 but require nonce */
$pol = [
  "default-src 'self'",
  "script-src 'self' https://www.googletagmanager.com https://www.google-analytics.com 'nonce-{$hmqz_nonce}' 'report-sample'",
  "style-src 'self' https://fonts.googleapis.com 'unsafe-inline' 'report-sample'",
  "img-src 'self' data: https://www.google-analytics.com https://stats.g.doubleclick.net",
  "font-src 'self' https://fonts.gstatic.com data:",
  "connect-src 'self' https://www.google-analytics.com https://region1.google-analytics.com https://stats.g.doubleclick.net",
  "frame-src 'self' https://www.youtube.com https://www.youtube-nocookie.com",
  "object-src 'none'",
  "base-uri 'self'",
  "upgrade-insecure-requests"
];
header_remove('Content-Security-Policy');
header_remove('Content-Security-Policy-Report-Only');
header('Content-Security-Policy-Report-Only: '.implode('; ', $pol));

ob_start(function ($html) use ($hmqz_nonce) {
  // 1) Strip any UA analytics.js (http/https/protocol-relative, with/without query)
  $html = preg_replace(
    [
      '#<script\b[^>]*\bsrc=["\'](?:https?:)?//www\.google-analytics\.com/analytics\.js(?:\?[^"\']*)?["\'][^>]*>\s*</script>#i',
      '#<script\b[^>]*\bsrc=["\'][^"\']*google-analytics\.com/analytics\.js[^"\']*["\'][^>]*>\s*</script>#i',
      // also kill the classic inline loader that references analytics.js explicitly
      '#<script\b[^>]*>\s*\(function\s*\(i,s,o,g,r,a,m\).*?analytics\.js.*?</script>#is'
    ],
    '',
    $html
  );

  // 2) Add nonce to any <script> missing it (safe textual inject)
  $html = preg_replace(
    '#<script(?![^>]*\bnonce=)(\s)#i',
    '<script nonce="'.$hmqz_nonce.'"$1',
    $html
  );

  // 3) Small HTML marker (handy for grepping)
  if (stripos($html, '</head>') !== false) {
    $html = str_ireplace('</head>', "\n<!-- HMQZ-PREPEND-NONCE: $hmqz_nonce -->\n</head>", $html);
  }
  return $html;
});
