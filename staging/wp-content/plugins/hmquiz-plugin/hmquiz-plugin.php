<?php
/**
 * Plugin Name: HMQUIZ Plugin
 * Description: HMQUIZ core (CPT + shortcodes + JSON banks + admin + GA4 events).
 * Version: 0.3.9
 * Author: HMQUIZ
 */
if (!defined('ABSPATH')) exit;

define('HMQZ_PLUGIN_VER', '0.3.9');
define('HMQZ_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HMQZ_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/includes/db.php';

require_once __DIR__ . '/includes/shortcode_play.php'; // Play shortcode/page
require_once HMQZ_PLUGIN_DIR . 'includes/banks.php';
require_once __DIR__ . '/includes/shortcode_hub.php';

require_once HMQZ_PLUGIN_DIR . 'includes/cpt.php';
require_once HMQZ_PLUGIN_DIR . 'includes/shortcodes.php'; // core [hmquiz]
require_once HMQZ_PLUGIN_DIR . 'includes/admin.php';
require_once HMQZ_PLUGIN_DIR . 'includes/shortcodes_multipicker.php'; // [hmquiz_multipicker], [hmquiz_hub]
require_once __DIR__ . '/includes/shortcode_multipicker_rest.php'; // [hmqz_multipicker_rest]
require_once __DIR__ . '/includes/shortcode_leaderboard.php';
if (is_admin()) {
  require_once HMQZ_PLUGIN_DIR . 'includes/admin/metabox_mcq.php';
}
if (defined('WP_CLI') && WP_CLI) {
  require_once HMQZ_PLUGIN_DIR . 'includes/cli.php';
}

add_action('wp_enqueue_scripts', function () {
  $css_path = HMQZ_PLUGIN_DIR . 'assets/css/app.css';
  $css_url  = HMQZ_PLUGIN_URL . 'assets/css/app.css';
  $js_path  = HMQZ_PLUGIN_DIR . 'assets/js/app.js';
  $js_url   = HMQZ_PLUGIN_URL . 'assets/js/app.js';
  $css_ver  = file_exists($css_path) ? (string)filemtime($css_path) : HMQZ_PLUGIN_VER;
  $js_ver   = file_exists($js_path) ? (string)filemtime($js_path) : HMQZ_PLUGIN_VER;

  wp_register_style('hmqz-style', HMQZ_PLUGIN_URL . 'assets/css/hmqz.css', [], $css_ver);
  wp_register_script('hmqz-script', HMQZ_PLUGIN_URL . 'assets/js/hmqz.js', [], $js_ver, true);

  wp_enqueue_style('hmqz-app', $css_url, [], $css_ver);
  wp_enqueue_script('hmqz-app', $js_url, [], $js_ver, true);

  $uploads = wp_get_upload_dir();
  $bank_base = trailingslashit($uploads['baseurl']) . 'hmquiz/banks/';

  $topics_page_id = get_option('hmqz_topics_page');
  $topics_url = $topics_page_id ? get_permalink($topics_page_id) : home_url('/quiz/general-knowledge/');

  $cfg = [
    'env'       => defined('HMQZ_ENV') ? HMQZ_ENV : 'unknown',
    'gaEnabled' => (defined('HMQZ_GA_ENABLE') && HMQZ_GA_ENABLE && defined('HMQZ_ENV') && HMQZ_ENV === 'production' && defined('HMQZ_GA4_ID') && HMQZ_GA4_ID),
    'gaId'      => defined('HMQZ_GA4_ID') ? HMQZ_GA4_ID : '',
    'debug'     => defined('HMQZ_GA_DEBUG') ? (bool) HMQZ_GA_DEBUG : false,
    'version'   => $js_ver,
    'bankBase'  => $bank_base,
    'topicsUrl' => $topics_url,
    'logo'      => HMQZ_PLUGIN_URL . 'assets/img/hmquiz-logo.png',
  ];
  wp_localize_script('hmqz-app', 'HMQZCFG', $cfg);
});

// v0.3.3: Simple "Play" shortcode that reads ?bank=... and renders [hmquiz]
add_shortcode('hmqz_play', function($atts = []) {
  $bank = isset($_GET['bank']) ? sanitize_text_field(wp_unslash($_GET['bank'])) : '';
  if (!$bank) return '<p>No quiz selected.</p>';
  $title = isset($atts['title']) ? sanitize_text_field($atts['title']) : $bank;
  return do_shortcode('[hmquiz bank="'.esc_attr($bank).'" title="'.esc_attr($title).'"]');
});

add_action('rest_api_init', function () {
  register_rest_route('hmqz/v1','/lead',[
    'methods' => 'POST',
    'permission_callback' => function (\WP_REST_Request $r) {
        return wp_verify_nonce($r->get_header('X-WP-Nonce'), 'wp_rest');
    },
    'callback' => function (\WP_REST_Request $r) {
      global $wpdb;
      $table_name = $wpdb->prefix . 'hmqz_leads';

      $email = sanitize_email($r->get_param('email'));
      if (!$email || !is_email($email)) {
        return new \WP_Error('bad_email', 'Invalid email', ['status' => 400]);
      }

      $data = [
        'timestamp' => current_time('mysql'),
        'email' => $email,
        'name' => sanitize_text_field($r->get_param('name') ?? ''),
        'score' => intval($r->get_param('score')),
        'total' => intval($r->get_param('total')),
        'percent' => floatval($r->get_param('percent')),
        'level' => intval($r->get_param('level')),
        'levels' => intval($r->get_param('levels')),
        'status' => sanitize_text_field($r->get_param('status') ?? ''),
        'badge' => sanitize_text_field($r->get_param('badge') ?? ''),
        'elapsed_ms' => intval($r->get_param('elapsed')),
        'title' => sanitize_text_field($r->get_param('title') ?? ''),
        'url' => esc_url_raw($r->get_param('url') ?? ''),
      ];

      $catsRaw = $r->get_param('categories');
      if (is_array($catsRaw)) {
        $data['categories'] = implode(' | ', array_map('sanitize_text_field', $catsRaw));
      } else {
        $data['categories'] = sanitize_text_field($catsRaw ?? '');
      }

      $topicsRaw = $r->get_param('topics');
      if (is_array($topicsRaw)) {
        $data['topics'] = implode(' | ', array_map('sanitize_text_field', $topicsRaw));
      } else {
        $data['topics'] = sanitize_text_field($topicsRaw ?? '');
      }

      $wpdb->insert($table_name, $data);

      return ['ok' => true];
    }
  ]);
});

add_action('wp_enqueue_scripts', function () {
  if (!wp_script_is('hmqz-script', 'enqueued')) {
    wp_enqueue_script('hmqz-script');
  }
  wp_localize_script('hmqz-script', 'hmqzApi', [
    'endpoint' => rest_url('hmqz/v1/lead'),
    'shareEndpoint' => rest_url('hmqz/v1/share'),
    'nonce'    => wp_create_nonce('wp_rest'),
  ]);
}, 20);
