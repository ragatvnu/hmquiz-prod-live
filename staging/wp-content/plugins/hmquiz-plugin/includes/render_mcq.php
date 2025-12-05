<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/bank_loader.php';

/**
 * Render MCQ quiz HTML for a given post (v0.3.4).
 */
function hmqz_render_mcq_quiz($post_id) {
  $loaded = hmqz_load_mcq_items($post_id);
  $items = $loaded['items'];
  $source = $loaded['source'];
  list($per_level, $pass_ratio) = hmqz_level_rules_for_post($post_id);

  if (!$items) {
    $items = [
      ['id' => 'q_f1', 'text' => '2 + 2 = ?', 'choices' => [
        ['label' => '3', 'correct' => false],
        ['label' => '4', 'correct' => true],
        ['label' => '5', 'correct' => false],
      ]],
      ['id' => 'q_f2', 'text' => 'Capital of France?', 'choices' => [
        ['label' => 'Madrid', 'correct' => false],
        ['label' => 'Paris', 'correct' => true],
        ['label' => 'Rome', 'correct' => false],
      ]],
      ['id' => 'q_f3', 'text' => 'Clear-sky color?', 'choices' => [
        ['label' => 'Blue', 'correct' => true],
        ['label' => 'Green', 'correct' => false],
        ['label' => 'Red', 'correct' => false],
      ]],
    ];
    $source = 'fallback';
  }

  $levels = hmqz_build_levels($items, $per_level, $pass_ratio);
  $levels_json = wp_json_encode($levels);

  ob_start();
  ?>
  <div class="hmqz-wrapper" id="hmqz-app"
       data-levels='<?php echo esc_attr($levels_json); ?>'>
    <div class="hmqz-quiz">
      <?php foreach ($items as $qIndex => $q): ?>
        <section class="hmqz-q" data-qid="<?php echo esc_attr($q['id']); ?>">
          <h3 class="hmqz-q-text"><?php echo esc_html(($qIndex+1) . '. ' . $q['text']); ?></h3>
          <div class="hmqz-choices">
            <?php foreach ($q['choices'] as $c): ?>
              <button class="hmqz-choice"
                      data-qid="<?php echo esc_attr($q['id']); ?>"
                      data-correct="<?php echo $c['correct'] ? '1':'0'; ?>">
                <?php echo esc_html($c['label']); ?>
              </button>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endforeach; ?>
    </div>
    <div id="hmqz-level-ctl" class="hmqz-level-ctl"></div>
    <?php if ($source && $source !== 'none'): ?>
      <small class="hmqz-source">Source: <?php echo esc_html($source); ?></small>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
}
