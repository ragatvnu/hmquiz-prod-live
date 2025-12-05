<?php
if (!defined('ABSPATH')) exit;

function hmqz_shortcode($atts = []) {
  $atts = shortcode_atts([
    'id'   => '',       // optional quiz id via [hmquiz id="6"]
    'mode' => 'mcq',
  ], $atts, 'hmquiz');

  $quiz_id = absint($atts['id']) ?: (isset($_GET['quiz']) ? absint($_GET['quiz']) : 0);

  ob_start(); ?>
  <div class="hmqz-wrapper">
    <div id="hmqz-app" class="hmqz-app"
         data-hmqz="mcq"
         <?php if ($quiz_id): ?>data-quiz-id="<?php echo esc_attr($quiz_id); ?>"<?php endif; ?>>
      <noscript>Enable JavaScript to play this quiz.</noscript>
    </div>
  </div>
  <?php
  return ob_get_clean();
}
add_shortcode('hmquiz', 'hmqz_shortcode');

