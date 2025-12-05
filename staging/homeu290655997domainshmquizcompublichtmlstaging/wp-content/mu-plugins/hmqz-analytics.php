<?php
/**
 * Plugin Name: HMQZ GA4 (MU)
 * Description: Inject GA4 using env GA_MEASUREMENT_ID.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;
add_action('wp_head', function () {
  $mid = getenv('GA_MEASUREMENT_ID') ?: '';
  if (!$mid) return;
  $env = getenv('HMQZ_ENV') ?: 'local';
  $debug = ($env !== 'production') ? 'true' : 'false';
  ?>
  <!-- HMQZ GA4 -->
  <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($mid); ?>"></script>
  <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', '<?php echo esc_js($mid); ?>', { anonymize_ip: true, debug_mode: <?php echo $debug; ?> });
  </script>
  <?php
}, 1);
