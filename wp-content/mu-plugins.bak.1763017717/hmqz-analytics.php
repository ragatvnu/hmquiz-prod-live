  <?php
  /**
   * Plugin Name: HMQZ GA4 (Legacy Stub)
   * Description: Back-compat loader that now defers to hmquiz-analytics.
   * Version: 0.1.1
   */
  if (!defined('ABSPATH')) {
    exit;
  }

  if (defined('HMQZ_MU_ANALYTICS_LEGACY')) {
    return;
  }

  define('HMQZ_MU_ANALYTICS_LEGACY', true);

  // Ensure the primary injector is loaded once.
  require_once __DIR__ . '/hmquiz-analytics.php';
