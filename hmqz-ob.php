<?php
// HMQUIZ output buffer for staging

// Do not interfere with wp-cli commands
if (defined('WP_CLI') && WP_CLI) {
    return;
}

// Disable page/object/db cache for staging
if (!defined('DONOTCACHEPAGE'))   define('DONOTCACHEPAGE',   true);
if (!defined('DONOTCACHEOBJECT')) define('DONOTCACHEOBJECT', true);
if (!defined('DONOTCACHEDB'))     define('DONOTCACHEDB',     true);

// Marker header so we know this file ran
header('x-hmqz-ob: yes');

if (!defined('HMQZ_OB_LOADED')) {
    define('HMQZ_OB_LOADED', 1);

    ob_start(function ($html) {
        // Only touch real HTML responses
        if (!is_string($html) || stripos($html, '<html') === false) {
            return $html;
        }

        // 1) Strip legacy UA analytics.js (keep GA4 from googletagmanager)
        $html = preg_replace(
            '#<script[^>]+src=["\']https?://(www\.)?google-analytics\.com/analytics\.js[^>]*></script>\s*#i',
            '',
            $html
        );

        // 2) Generate nonce
        try {
            $nonce = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $nonce = substr(sha1(mt_rand() . microtime(true)), 0, 32);
        }

        // 3) Add nonce to <script> tags that don't already have one
        $html = preg_replace(
            '#<script(?![^>]*\bnonce=)([^>]*)>#i',
            '<script nonce="' . $nonce . '"$1>',
            $html
        );

        // 4) Insert HMQZ-OB-NONCE marker near </head>
        $marker = "\n<!-- HMQZ-OB-NONCE " . $nonce . " -->\n";
        if (preg_match('#</head>#i', $html)) {
            $html = preg_replace('#</head>#i', $marker . '</head>', $html, 1);
        } else {
            $html .= $marker;
        }

        // 5) Set CSP-Report-Only
        //    Allow:
        //      - self
        //      - nonced inline scripts
        //      - GA4 from googletagmanager.com
        if (!headers_sent()) {
            header(
                "Content-Security-Policy-Report-Only: " .
                "default-src 'self'; " .
                "script-src 'self' 'nonce-" . $nonce . "' https://www.googletagmanager.com; " .
                "object-src 'none'; " .
                "base-uri 'self'; " .
                "frame-ancestors 'self';"
            );
        }

        return $html;
    });
}
