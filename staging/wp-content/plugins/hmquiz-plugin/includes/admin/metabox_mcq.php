<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
  $screens = ['hmquiz_quiz', 'post'];
  foreach ($screens as $screen) {
    add_meta_box('hmqz_mcq_box', 'HMQUIZ — MCQ Settings', 'hmqz_mcq_box_render', $screen, 'normal', 'default');
  }
});

function hmqz_mcq_box_render($post) {
  wp_nonce_field('hmqz_mcq_save', 'hmqz_mcq_nonce');
  $bank_file  = get_post_meta($post->ID, 'hmqz_bank_file', true);
  $per_level  = (int) get_post_meta($post->ID, 'hmqz_per_level', true) ?: 10;
  $pass_ratio = get_post_meta($post->ID, 'hmqz_pass_ratio', true);
  $pass_ratio = ($pass_ratio !== '' ? $pass_ratio : '0.6');
  $inline     = get_post_meta($post->ID, 'hmqz_mcq_json', true);
  ?>
  <p><label><strong>Bank file</strong> (uploads/hmquiz/banks/...):<br>
    <input type="text" name="hmqz_bank_file" style="width:100%" value="<?php echo esc_attr($bank_file); ?>" placeholder="e.g. geography/world-capitals.json">
  </label></p>
  <div style="display:flex;gap:1rem;">
    <label>Questions per level<br>
      <input type="number" name="hmqz_per_level" min="1" value="<?php echo esc_attr($per_level); ?>">
    </label>
    <label>Pass ratio (0.5–0.95)<br>
      <input type="number" name="hmqz_pass_ratio" min="0.5" max="0.95" step="0.05" value="<?php echo esc_attr($pass_ratio); ?>">
    </label>
  </div>
  <p><em>Inline JSON (optional, overrides bank)</em></p>
  <textarea name="hmqz_mcq_json" rows="8" style="width:100%" placeholder='[{"id":"q1","text":"...","choices":[{"label":"A","correct":true},{"label":"B","correct":false}]}]'><?php echo esc_textarea($inline); ?></textarea>
  <?php
}

add_action('save_post', function ($post_id) {
  if (!isset($_POST['hmqz_mcq_nonce']) || !wp_verify_nonce($_POST['hmqz_mcq_nonce'], 'hmqz_mcq_save')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $bank = sanitize_text_field($_POST['hmqz_bank_file'] ?? '');
  update_post_meta($post_id, 'hmqz_bank_file', $bank);

  $per_level = max(1, (int) ($_POST['hmqz_per_level'] ?? 10));
  update_post_meta($post_id, 'hmqz_per_level', $per_level);

  $pass_ratio = (float) ($_POST['hmqz_pass_ratio'] ?? 0.6);
  if ($pass_ratio <= 0 || $pass_ratio >= 1) $pass_ratio = 0.6;
  update_post_meta($post_id, 'hmqz_pass_ratio', $pass_ratio);

  if (isset($_POST['hmqz_mcq_json'])) {
    $json = trim((string) $_POST['hmqz_mcq_json']);
    if ($json === '') {
      delete_post_meta($post_id, 'hmqz_mcq_json');
    } else {
      update_post_meta($post_id, 'hmqz_mcq_json', wp_kses_post($json));
    }
  }
});
