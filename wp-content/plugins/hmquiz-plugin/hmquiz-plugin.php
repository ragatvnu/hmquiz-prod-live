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
require_once __DIR__ . '/includes/shortcode_hub.php';
require_once HMQZ_PLUGIN_DIR . 'includes/banks.php';
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
wp_localize_script('hmqz-app', 'hmqzApi', [
  'endpoint' => esc_url_raw(rest_url('hmqz/v1/email-score')),
  'nonce'    => wp_create_nonce('wp_rest'),
]);
});
// v0.3.3: Simple "Play" shortcode that reads ?bank=... and renders [hmquiz]
// v0.3.3: Simple "Play" shortcode that reads ?bank=... and renders [hmquiz]


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

  register_rest_route('hmqz/v1','/email-score',[
    'methods' => 'POST',
    'permission_callback' => function (\WP_REST_Request $r) {
      return wp_verify_nonce($r->get_header('X-WP-Nonce'), 'wp_rest');
    },
    'callback' => function (\WP_REST_Request $r) {
      $params = $r->get_json_params();
      $player_name  = sanitize_text_field($params['name'] ?? '');
      $player_email = sanitize_email($params['email'] ?? '');
      $score_block  = isset($params['score']) && is_array($params['score']) ? $params['score'] : [];
      $score_correct = isset($score_block['correct']) ? intval($score_block['correct']) : intval($params['score'] ?? 0);
      $score_total   = isset($score_block['total']) ? intval($score_block['total']) : intval($params['total'] ?? 0);
      $score_percent = isset($score_block['percent']) ? floatval($score_block['percent']) : ($score_total ? round(($score_correct / max(1,$score_total)) * 100) : 0);
      $quiz_title    = sanitize_text_field($params['title'] ?? ($params['meta']['title'] ?? 'HMQUIZ'));
      $source_url    = esc_url_raw($params['meta']['url'] ?? home_url());

      $lines = [];
      $lines[] = 'Player: ' . ($player_name ?: '—');
      $lines[] = 'Player email: ' . ($player_email ?: '—');
      $lines[] = 'Quiz: ' . $quiz_title;
      $lines[] = sprintf('Score: %d/%d (%d%%)', $score_correct, $score_total, $score_percent);
      $lines[] = 'Quiz ID: ' . sanitize_text_field($params['quiz_id'] ?? '—');
      $lines[] = 'Source: ' . $source_url;

      $history = $params['history'] ?? [];
      if (is_array($history) && !empty($history)) {
        $lines[] = '';
        $lines[] = 'History:';
        foreach ($history as $entry) {
          $lvl = isset($entry['level']) ? intval($entry['level']) : '?';
          $corr = isset($entry['correct']) ? intval($entry['correct']) : 0;
          $tot = isset($entry['total']) ? intval($entry['total']) : 0;
          $lines[] = sprintf('  Level %s: %d/%d', $lvl, $corr, $tot);
        }
      }

      $subject = $player_name
        ? sprintf('HMQUIZ score from %s', $player_name)
        : 'HMQUIZ score submission';
      $headers = ['Content-Type: text/plain; charset=UTF-8'];
      if ($player_email) {
        $headers[] = 'Reply-To: ' . $player_email;
      }

      $sent = wp_mail('hello@hmquiz.com', $subject, implode("\n", $lines), $headers);
      if (!$sent) {
        return new \WP_Error('mail_failed', 'Unable to send email', ['status' => 500]);
      }

      return ['ok' => true];
    }
  ]);
});

/**
 * Strip specific inline styles that CSP would block and replace them with classes.
 */
function hmqz_replace_csp_inline_styles($html) {
  $inline_style = 'margin:24px auto 8px;max-width:980px;padding:0 20px;';
  if (strpos($html, $inline_style) === false) {
    return $html;
  }

  return preg_replace_callback(
    '/(<[^>]+)style="margin:24px auto 8px;max-width:980px;padding:0 20px;"([^>]*>)/i',
    static function ($matches) {
      $before = $matches[1];
      $after  = $matches[2];

      if (strpos($before, 'class=') !== false) {
        $before = preg_replace('/class="([^"]*)"/', 'class="$1 hmqz-inline-group"', $before, 1);
      } else {
        $before .= ' class="hmqz-inline-group"';
      }

      return $before . $after;
    },
    $html
  );
}

add_filter('render_block', 'hmqz_replace_csp_inline_styles', 10, 1);
add_filter('the_content', 'hmqz_replace_csp_inline_styles', 10, 1);

function hmqz_current_env() {
  $host = isset($_SERVER['HTTP_HOST']) ? strtolower($_SERVER['HTTP_HOST']) : '';
  if (strpos($host, 'staging.') !== false || strpos($host, 'staging-') !== false) {
    return 'staging';
  }
  return defined('HMQZ_ENV') ? HMQZ_ENV : 'production';
}

function hmqz_is_html_request() {
  $uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
  if ($uri && preg_match('/\.(?:css|js|json|xml|svg|png|jpe?g|gif|ico|woff2?|woff|ttf|map|pdf)$/i', $uri)) {
    return false;
  }
  $accept = isset($_SERVER['HTTP_ACCEPT']) ? strtolower($_SERVER['HTTP_ACCEPT']) : '';
  if ($accept && strpos($accept, 'text/html') === false) {
    return false;
  }
  return true;
}

add_action('send_headers', function() {
  $env = hmqz_current_env();
  if (headers_sent()) {
    return;
  }
  header('X-HMQZ-Env: ' . $env, true);
  header('X-Frame-Options: SAMEORIGIN', true);
  header('X-Content-Type-Options: nosniff', true);
  header('Referrer-Policy: strict-origin-when-cross-origin', true);
  if (is_ssl()) {
    header('Strict-Transport-Security: max-age=63072000; includeSubDomains; preload', true);
  }

  if ($env === 'staging') {
    header('X-Robots-Tag: noindex, nofollow', true);
    header('Cache-Control: no-store', true);
  } else {
    $max = getenv('CACHE_PUBLIC_MAXAGE') ?: '604800';
    if (hmqz_is_html_request()) {
      header('Cache-Control: no-cache, max-age=0, must-revalidate', true);
    } else {
      header('Cache-Control: public, max-age=' . intval($max), true);
    }
  }
});

add_action('wp_head', function () {
  if (hmqz_current_env() !== 'staging') {
    return;
  }
  echo "<meta name=\"robots\" content=\"noindex,nofollow\" />\n";
}, 0);

add_filter('pre_option_blog_public', function ($value) {
  if (hmqz_current_env() === 'staging') {
    return '0';
  }
  return $value;
});
// v0.3.9: "Play" shortcode with card layout, header bar, and footer
add_shortcode('hmqz_play', function($atts = []) {
  // Read quiz bank and meta from query string / shortcode atts
  $bank       = isset($_GET['bank']) ? sanitize_text_field(wp_unslash($_GET['bank'])) : '';
  if (!$bank) {
    return '<p>' . esc_html__('No quiz selected.', 'hmquiz') . '</p>';
  }

  $topic      = isset($_GET['topics'])     ? sanitize_text_field(wp_unslash($_GET['topics']))     : '';
  $category   = isset($_GET['categories']) ? sanitize_text_field(wp_unslash($_GET['categories'])) : '';
  $level_cur  = isset($_GET['level'])      ? intval($_GET['level'])   : 1;
  $level_max  = isset($_GET['levels'])     ? intval($_GET['levels'])  : 1;
  $difficulty = isset($_GET['difficulty']) ? sanitize_text_field(wp_unslash($_GET['difficulty'])) : '';
  $total_q    = isset($_GET['per'])        ? intval($_GET['per'])     : 0;

  // Title: prefer explicit shortcode title, then category, then topic, then bank name
  if (isset($atts['title']) && $atts['title'] !== '') {
    $title = sanitize_text_field($atts['title']);
  } elseif ($category) {
    $title = $category;
  } elseif ($topic) {
    $title = $topic;
  } else {
    $title = $bank;
  }

  // Use the same logo the JS app uses
  $logo_url = HMQZ_PLUGIN_URL . 'assets/img/hmquiz-logo.png';

  // Inner engine still uses [hmquiz] shortcode (existing logic)
  $inner = do_shortcode(
    '[hmquiz bank="' . esc_attr($bank) . '" title="' . esc_attr($title) . '"]'
  );

  ob_start();
  ?>
  <div class="hmqz-play-page-inner">
    <div class="hmqz-play-card">
      <div class="hmqz-play-card-inner">

        <!-- HEADER: logo left, topic/title center, timer + Q meta right -->
        <header class="hmqz-play-header">
          <div class="hmqz-play-header-left">
            <?php if (!empty($logo_url)) : ?>
              <img
                class="hmqz-play-logo"
                src="<?php echo esc_url($logo_url); ?>"
                alt="<?php esc_attr_e('HMQUIZ logo', 'hmquiz'); ?>"
                loading="lazy"
              />
            <?php else : ?>
              <div class="hmqz-play-logo-text">HMQUIZ</div>
            <?php endif; ?>
          </div>

          <div class="hmqz-play-header-center">
            <?php if ($topic) : ?>
              <div class="hmqz-play-topic">
                <?php echo esc_html($topic); ?>
              </div>
            <?php endif; ?>
            <div class="hmqz-play-title">
              <?php echo esc_html($title); ?>
            </div>
          </div>

          <div class="hmqz-play-header-right">
            <div class="hmqz-play-timer">
              <span class="hmqz-play-timer-label">
                <?php esc_html_e('Time', 'hmquiz'); ?>
              </span>
              <span class="hmqz-play-timer-value js-hmqz-timer">
                00:00
              </span>
            </div>
            <div class="hmqz-play-qmeta js-hmqz-qmeta">
              <?php
              if ($total_q > 0) {
                printf(
                  /* translators: 1: current question number, 2: total questions */
                  esc_html__('Q %1$d/%2$d', 'hmquiz'),
                  1,
                  $total_q
                );
              }
              ?>
            </div>
          </div>
        </header>

        <!-- PROGRESS BAR -->
        <div class="hmqz-play-progress">
          <div class="hmqz-play-progress-bar">
            <div
              class="hmqz-play-progress-fill js-hmqz-progress"
            ></div>
          </div>
        </div>

        <!-- BODY: existing [hmquiz] output lives here -->
        <div class="hmqz-play-body">
          <?php
          // Existing engine output (question + options) lives here.
          // Your JS can sync header/timer/progress via the JS hooks.
          echo $inner;
          ?>
        </div>

        <!-- FOOTER -->
        <footer class="hmqz-play-footer">
          <div class="hmqz-play-footer-meta">
            <span class="hmqz-level">
              <?php
              printf(
                /* translators: 1: current level, 2: total levels */
                esc_html__('Level %1$d/%2$d', 'hmquiz'),
                max(1, $level_cur),
                max(1, $level_max)
              );
              ?>
            </span>
            <?php if ($difficulty) : ?>
              <span class="hmqz-pill hmqz-pill-difficulty">
                <?php echo esc_html($difficulty); ?>
              </span>
            <?php endif; ?>
          </div>

          <button
            type="button"
            class="hmqz-btn-primary hmqz-btn-next js-hmqz-next"
            disabled
          >
            <span><?php esc_html_e('Next question', 'hmquiz'); ?></span>
            <span class="hmqz-next-arrow">➜</span>
          </button>
        </footer>

      </div>
    </div>
  </div>
  <?php
  return ob_get_clean();
});



// Force [hmqz_play] to use the new card renderer from includes/shortcode_play.php
add_action('init', function () {
  if (function_exists('hmqz_render_play_shortcode')) {
    remove_shortcode('hmqz_play');
    add_shortcode('hmqz_play', 'hmqz_render_play_shortcode');
  }
}, 99);

// HMQUIZ: turn off the React app on the Quiz Hub page (/quiz/, ID 19)
add_action('wp_enqueue_scripts', function() {
    if (is_admin()) return;

    // Only target the Quiz Hub page
    if (!is_page(19)) return;

    // Dequeue the main HMQUIZ app script on /quiz/
    wp_dequeue_script('hmqz-app-js');

    // If there's an extra inline/manifest script handle, try to dequeue that too
    wp_dequeue_script('hmqz-app-js-extra');
}, 50);

// HMQUIZ: Hard-disable the React app on the Quiz Hub page (/quiz/, ID 19)
add_action('wp_print_scripts', function() {
    if (is_admin()) {
        return;
    }

    // Only affect the Quiz Hub page
    if (!is_page(19)) {
        return;
    }

    // Dequeue and deregister HMQUIZ app scripts so they don't run on /quiz/
    wp_dequeue_script('hmqz-app-js');
    wp_deregister_script('hmqz-app-js');

    wp_dequeue_script('hmqz-app-js-extra');
    wp_deregister_script('hmqz-app-js-extra');
}, 999);

// HMQUIZ: Block the React app scripts on the Quiz Hub page (/quiz/, ID 19)
add_filter('script_loader_tag', function($tag, $handle, $src) {
    // Don't affect admin
    if (is_admin()) {
        return $tag;
    }

    // Only affect the Quiz Hub page
    if (!is_page(19)) {
        return $tag;
    }

    // If this is one of our app scripts, suppress it on /quiz/
    if ($handle === 'hmqz-app-js' || $handle === 'hmqz-app-js-extra') {
        return '';
    }

    return $tag;
}, 10, 3);

// HMQUIZ: Strip React app scripts from the Quiz Hub page HTML (/quiz/, ID 19)
add_action('template_redirect', function() {
    // Front-end only
    if (is_admin()) {
        return;
    }

    // Only wrap output for the Quiz Hub page
    if (!is_page(19)) {
        return;
    }

    // Start an output buffer that removes the two app scripts by id
    ob_start(function($html) {
        // Remove the inline extra script
        $html = preg_replace(
            '#<script[^>]+id=["\']hmqz-app-js-extra["\'][^>]*>.*?</script>#si',
            '',
            $html
        );

        // Remove the main app script
        $html = preg_replace(
            '#<script[^>]+id=["\']hmqz-app-js["\'][^>]*>.*?</script>#si',
            '',
            $html
        );

        return $html;
    });
}, 0);
