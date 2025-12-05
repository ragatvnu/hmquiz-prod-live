<?php
/*
Plugin Name: HMQUIZ Staging Noindex
Description: Prevent search engines from indexing staging.hmquiz.com
*/

if (!defined('ABSPATH')) exit;

function hmqz_staging_send_noindex() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (stripos($host, 'staging.hmquiz.com') !== 0) {
        return;
    }

    // HTTP header
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, nofollow, noarchive', false);
    }

    // Meta tag
    add_action('wp_head', function () {
        echo "<meta name='robots' content='noindex,nofollow,noarchive,max-image-preview:none' />\n";
    }, 0);
}
add_action('send_headers', 'hmqz_staging_send_noindex', 0);
