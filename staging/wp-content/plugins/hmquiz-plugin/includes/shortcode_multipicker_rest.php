<?php
// HMQUIZ — REST-driven multipicker shortcode
if (!defined('ABSPATH')) exit;

/**
 * Usage: [hmqz_multipicker_rest]
 * Renders a lightweight shell that fetches /hmqz/v1/topics client-side and builds links to /play/.
 */
add_shortcode('hmqz_multipicker_rest', function () {
  $play_url = home_url('/play/');
  $quizzes = get_posts([
    'post_type'      => 'hmqz_quiz',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
  ]);

  $topicBlocks = [];

  foreach ($quizzes as $quiz) {
    $quiz_id   = $quiz->ID;
    $quiz_title = get_the_title($quiz);
    $quiz_bank = (string) get_post_meta($quiz_id, 'hmqz_bank_file', true);
    $quiz_per  = max(1, (int) get_post_meta($quiz_id, 'hmqz_per_level', true));
    $quiz_pass = (float) get_post_meta($quiz_id, 'hmqz_pass_ratio', true);
    if ($quiz_pass <= 0 || $quiz_pass > 1) $quiz_pass = 0.6;

    $topics = wp_get_post_terms($quiz_id, 'hmqz_topic');
    $categories = wp_get_post_terms($quiz_id, 'hmqz_category');
    if (is_wp_error($topics) || is_wp_error($categories)) continue;
    if (empty($topics)) continue; // topic determines quiz entry

    foreach ($topics as $topic) {
      $topic_id = $topic->term_id;
      if (!isset($topicBlocks[$topic_id])) {
        $topicBlocks[$topic_id] = [
          'topic'       => $topic,
          'quiz_id'     => $quiz_id,
          'quiz_title'  => $quiz_title,
          'default_bank'=> $quiz_bank,
          'default_per' => $quiz_per,
          'default_pass'=> $quiz_pass,
          'categories'  => [],
        ];
      }

      foreach ($categories as $cat) {
        $cat_id = $cat->term_id;
        if (isset($topicBlocks[$topic_id]['categories'][$cat_id])) continue;

        $cat_bank = (string) get_term_meta($cat_id, 'hmqz_bank_file', true);
        if ($cat_bank === '') $cat_bank = $quiz_bank;
        $cat_per = (int) get_term_meta($cat_id, 'hmqz_per_level', true);
        if ($cat_per <= 0) $cat_per = $quiz_per;
        $cat_pass = (float) get_term_meta($cat_id, 'hmqz_pass_ratio', true);
        if ($cat_pass <= 0 || $cat_pass > 1) $cat_pass = $quiz_pass;

        $topicBlocks[$topic_id]['categories'][$cat_id] = [
          'term' => $cat,
          'bank' => $cat_bank,
          'per'  => $cat_per,
          'pass' => $cat_pass,
        ];
      }
    }
  }

  // Sort topics alphabetically
  uasort($topicBlocks, function ($a, $b) {
    return strcasecmp($a['topic']->name ?? '', $b['topic']->name ?? '');
  });

  ob_start(); ?>
  <div class="hmqz-multipicker-rest" style="max-width:1080px;margin:0 auto;padding:24px 12px;">
    <?php if (empty($topicBlocks)): ?>
      <div class="hmqz-card" style="padding:18px;border:1px solid #e5e7eb;border-radius:14px;background:#fff">
        No quiz topics have been configured yet. Assign <code>hmqz_topic</code> and <code>hmqz_category</code> terms to your quizzes.
      </div>
    <?php else: ?>
      <?php foreach ($topicBlocks as $block):
        $topic   = $block['topic'];
        $quiz_id = $block['quiz_id'];
        $quiz_title = $block['quiz_title'];
        $defaultLink = add_query_arg('quiz', $quiz_id, $play_url);
      ?>
        <section class="hmqz-topic-block" style="margin-bottom:32px;border:1px solid #e5e7eb;border-radius:18px;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.08);">
          <header style="padding:18px 20px;border-bottom:1px solid rgba(15,23,42,.06);display:flex;flex-wrap:wrap;align-items:center;gap:12px;">
            <div>
              <div style="font:600 1rem/1.1 system-ui;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Topic</div>
              <h3 style="margin:4px 0 0;font:700 1.5rem/1.3 'Poppins',system-ui;color:#0f172a;"><?php echo esc_html($topic->name); ?></h3>
              <p style="margin:4px 0 0;color:#475569;"><?php echo esc_html($topic->description ?: $quiz_title); ?></p>
            </div>
            <div style="margin-left:auto;">
              <a class="hmqz-btn primary" style="text-decoration:none;background:#111;color:#fff;border:none;"
                 href="<?php echo esc_url($defaultLink); ?>">Play all questions</a>
            </div>
          </header>

          <?php
          $categories = $block['categories'];
          if (empty($categories)) :
          ?>
            <div style="padding:20px;color:#475569;">No categories linked to this topic yet.</div>
          <?php else: ?>
            <div class="hmqz-topic-cats" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;padding:20px;">
              <?php foreach ($categories as $catData):
                $cat = $catData['term'];
                $bank = $catData['bank'];
                if ($bank === '') continue;
                $params = [
                  'quiz'          => $quiz_id,
                  'allowOverride' => '1',
                  'bank'          => $bank,
                  'per'           => max(1, (int)$catData['per']),
                  'threshold'     => max(0.1, min(0.95, (float) $catData['pass'])),
                ];
                $catLink = add_query_arg($params, $play_url);
              ?>
                <a class="hmqz-cat-card"
                   style="text-decoration:none;padding:16px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;display:flex;flex-direction:column;gap:6px;color:#0f172a;box-shadow:0 8px 20px rgba(15,23,42,.05);"
                   href="<?php echo esc_url($catLink); ?>">
                  <span style="font:600 1rem system-ui;"><?php echo esc_html($cat->name); ?></span>
                  <span style="font-size:.9rem;color:#475569;"><?php echo esc_html($cat->description ?: 'Play this category'); ?></span>
                  <span style="font-size:.8rem;color:#64748b;">Bank: <?php echo esc_html($bank); ?></span>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php
  return ob_get_clean();
});
