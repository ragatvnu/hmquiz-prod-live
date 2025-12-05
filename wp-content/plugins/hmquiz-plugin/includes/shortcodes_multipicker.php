<?php
if (!defined('ABSPATH')) exit;

/**
 * [hmquiz_hub]
 * Renders simple cards: MCQ (links inline to multipicker), Sudoku (disabled placeholder).
 */
add_shortcode('hmquiz_hub', function($atts){
  wp_enqueue_style('hmqz-style'); wp_enqueue_script('hmqz-script');
  ob_start(); ?>
  <div class="hmqz-wrapper hmqz-hub">
    <h2 class="hmqz-title">Choose a game</h2>
    <div class="hmqz-hub-grid">
      <button class="hmqz-card hmqz-hub-card" data-action="mcq">🧠 Multiple Choice</button>
      <button class="hmqz-card hmqz-hub-card" disabled>🧩 Sudoku (coming soon)</button>
    </div>
    <div class="hmqz-hub-target"></div>
  </div>
  <?php return ob_get_clean();
});

/**
 * [hmquiz_multipicker bank="my_quiz.json" pick="3" k="10" time="15" theme="violet"]
 * Topic selector chips -> filtered MCQ quiz.
 */
add_shortcode('hmquiz_multipicker', function($atts){
  $a = shortcode_atts([
    'bank'    => '',
    'pick'    => '3',
    'k'       => '10',
    'time'    => '15',
    'theme'   => '',
    'primary' => '',
    'accent'  => '',
  ], $atts);

  if (empty($a['bank'])) {
    $post_id = get_the_ID();
    if ($post_id) { $meta_bank = get_post_meta($post_id, '_hmqz_bank_file', true); if ($meta_bank) $a['bank'] = $meta_bank; }
  }
  if (empty($a['bank'])) return '<div class="hmqz-error">HMQUIZ: Please set a bank via shortcode or meta box.</div>';
  $bank = hmqz_load_bank($a['bank']); if (!$bank) return '<div class="hmqz-error">HMQUIZ: Could not load the bank file.</div>';

  wp_enqueue_style('hmqz-style'); wp_enqueue_script('hmqz-script');

  $node_id = 'hmqz-mp-' . wp_generate_uuid4();
  $json    = wp_json_encode($bank);
  $pick    = max(1, intval($a['pick']));
  $k       = max(1, intval($a['k']));
  $time    = max(5, intval($a['time']));

  // Theming (same variables as main shortcode)
  $preset = strtolower(trim($a['theme']));
  $vars = [
    'blue'    => ['--hmqz-primary' => '#4c8bf5', '--hmqz-accent' => '#2563eb'],
    'violet'  => ['--hmqz-primary' => '#8b5cf6', '--hmqz-accent' => '#6d28d9'],
    'emerald' => ['--hmqz-primary' => '#10b981', '--hmqz-accent' => '#059669'],
    'rose'    => ['--hmqz-primary' => '#f43f5e', '--hmqz-accent' => '#be123c'],
  ];
  $style_vars = '';
  if (isset($vars[$preset])) { foreach ($vars[$preset] as $k2=>$v) $style_vars .= $k2 . ':' . $v . ';'; }
  if (!empty($a['primary'])) $style_vars .= '--hmqz-primary:' . esc_attr($a['primary']) . ';';
  if (!empty($a['accent']))  $style_vars .= '--hmqz-accent:'  . esc_attr($a['accent'])  . ';';

  ob_start(); ?>
  <div class="hmqz-wrapper" style="<?php echo esc_attr($style_vars); ?>">
    <div id="<?php echo esc_attr($node_id); ?>" class="hmqz-multipicker"
         data-pick="<?php echo esc_attr($pick); ?>"
         data-k="<?php echo esc_attr($k); ?>"
         data-time="<?php echo esc_attr($time); ?>"></div>
    <script type="application/json" id="<?php echo esc_attr($node_id); ?>-data"><?php echo $json; ?></script>
  </div>
  <?php return ob_get_clean();
});
