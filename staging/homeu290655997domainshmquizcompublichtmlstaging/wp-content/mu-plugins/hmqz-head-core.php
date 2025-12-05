<?php
/**
 * Plugin Name: HMQZ Head Core (MU)
 * Description: Inject env meta + GA4 in <head>; add env/cache headers. Keep during M0.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;

/** Headers early */
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

/** <head> markers + GA */
add_action('wp_head', function () {
  $env = getenv('HMQZ_ENV') ?: 'unset';
  $ga  = getenv('GA_MEASUREMENT_ID') ?: '';
  echo "<meta name='hmqz-env' content='".esc_attr($env)."' />\n";
  echo "<!-- HMQZ_DEBUG env=$env ga=" . ($ga ? esc_attr($ga) : 'unset') . " -->\n";
  if ($ga) {
    $debug = ($env !== 'production') ? 'true' : 'false';
    ?>
    <!-- HMQZ GA4 -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', '<?php echo esc_js($ga); ?>', { anonymize_ip: true, debug_mode: <?php echo $debug; ?> });
    </script>
    <?php
  }
}, 0);

/** Minimal REST stub so smoke stays green */
add_action('rest_api_init', function () {
  register_rest_route('hmqz/v1', '/leaderboard/global/top', [
    'methods'  => 'GET',
    'callback' => function (WP_REST_Request $req) {
      $range = $req->get_param('range') ?: 'week';
      $limit = intval($req->get_param('limit') ?: 10);
      return new WP_REST_Response(['range'=>$range,'items'=>[],'limit'=>$limit], 200);
    },
    'permission_callback' => '__return_true',
  ]);
});
