<?php
/**
 * Plugin Name: HMQZ Env Headers (MU)
 * Description: Add environment-specific headers (NOINDEX on staging, cache on prod).
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;
add_action('send_headers', function () {
  $env = getenv('HMQZ_ENV') ?: 'local';
  if ($env === 'staging') {
    header('X-Robots-Tag: noindex, nofollow', true);
    header('Cache-Control: no-store', true);
  } elseif ($env === 'production') {
    $max = getenv('CACHE_PUBLIC_MAXAGE') ?: '604800';
    header('Cache-Control: public, max-age=' . intval($max), true);
  } else {
    header('Cache-Control: private, no-store', true);
  }
  header('X-HMQZ-Env: ' . $env, true);
}, 0);
