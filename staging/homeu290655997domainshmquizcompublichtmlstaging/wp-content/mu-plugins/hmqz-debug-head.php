<?php
/**
 * Plugin Name: HMQZ Debug Head (MU)
 * Description: Temporary debug helpers in <head>. Remove after verification.
 */
if (!defined('ABSPATH')) exit;
add_action('wp_head', function () {
  $env = getenv('HMQZ_ENV') ?: 'unset';
  $ga  = getenv('GA_MEASUREMENT_ID') ?: 'unset';
  echo "<meta name='hmqz-env' content='".esc_attr($env)."' />\n";
  echo "<!-- HMQZ_DEBUG env=$env ga=$ga -->\n";
}, 0);
