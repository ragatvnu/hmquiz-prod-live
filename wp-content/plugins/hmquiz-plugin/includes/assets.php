<?php
if (!defined('ABSPATH')) exit;

function hmqz_enqueue_assets() {
  if (is_admin()) return;

  $load = is_page('play');                           // /play page
  if (!$load) {
    global $post;
    if ($post instanceof WP_Post && has_shortcode($post->post_content, 'hmquiz')) {
      $load = true;                                  // any page that uses [hmquiz]
    }
  }
  if (!$load) return;

  $base = plugins_url('', dirname(__FILE__));
  $ver  = '1763451990'; // bump this after edits

  wp_enqueue_style('hmquiz-css', $base . '/assets/css/app.css', [], $ver);
  wp_enqueue_script('hmquiz-app', $base . '/assets/js/app.js', [], $ver, true);

  // Pass REST base + site url to JS for canonical + fallback fetches
  wp_add_inline_script('hmquiz-app', 'window.HMQZ_CONF=' . wp_json_encode([
    'rest_base' => get_rest_url(null, 'hmqz/v1/'),
    'site_url'  => site_url('/'),
  ]) . ';', 'before');
}
add_action('wp_enqueue_scripts', 'hmqz_enqueue_assets');
add_action('wp_enqueue_scripts', function(){
  wp_enqueue_script(
    'jspdf',
    'https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js',
    [],
    null,
    true
  );
});

