<?php
/**
 * Plugin Name: HMQZ CSP Nonce
 * Description: Generate a per-request CSP nonce and add it to enqueued script tags.
 */
if (!defined('ABSPATH')) exit;

function hmqz_csp_nonce() {
  static $nonce = null;
  if ($nonce === null) $nonce = bin2hex(random_bytes(16));
  return $nonce;
}

add_filter('script_loader_tag', function($tag){
  if (is_admin()) return $tag;
  $n = hmqz_csp_nonce();
  // Inject nonce attribute into enqueued scripts
  if (preg_match('/^<script\b/i', $tag)) {
    $tag = preg_replace('/^<script\b/', '<script nonce="'.esc_attr($n).'"', $tag, 1);
  }
  return $tag;
}, 9999);
