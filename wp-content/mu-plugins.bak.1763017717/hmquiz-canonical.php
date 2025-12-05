<?php
/**
 * Plugin Name: HMQUIZ Canonical (MU)
 * Description: Ensure a single canonical on home/front by removing WP core canonical and letting Rank Math output it.
 */
if (!defined('ABSPATH')) exit;

/** Remove WP core canonical at multiple lifecycle points (avoid duplicates) */
remove_action('wp_head', 'rel_canonical');
add_action('muplugins_loaded',  function(){ remove_action('wp_head','rel_canonical'); }, 0);
add_action('plugins_loaded',    function(){ remove_action('wp_head','rel_canonical'); }, 0);
add_action('after_setup_theme', function(){ remove_action('wp_head','rel_canonical'); }, 0);
add_action('template_redirect', function(){ remove_action('wp_head','rel_canonical'); }, 0);
add_action('wp',                function(){ remove_action('wp_head','rel_canonical'); }, 0);

/** If Rank Math is active, force homepage canonical URL via its filter */
add_filter('rank_math/frontend/canonical', function($canonical){
  if (is_front_page() || is_home()) return home_url('/');
  return $canonical;
}, 10, 1);

/** No fallback output. If Rank Math is disabled, you’ll simply have no canonical (which is OK until you re-enable it). */
