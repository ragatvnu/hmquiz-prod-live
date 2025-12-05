<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
  register_post_type('hmqz_quiz', [
    'label' => 'HMQUIZ Quizzes',
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true,
    'show_in_rest' => true,
    'has_archive' => false,
    'rewrite' => ['slug' => 'quizzes', 'with_front' => false, 'feeds' => false, 'pages' => false],
    'supports' => ['title', 'editor'],
  ]);

  $tax_common = [
    'public' => true,
    'hierarchical' => true,
    'show_ui' => true,
    'show_in_rest' => true,
    'rewrite' => ['with_front' => false],
  ];

  register_taxonomy('hmqz_topic', ['hmqz_quiz'], array_merge($tax_common, [
    'labels' => [
      'name' => 'HMQUIZ Topics',
      'singular_name' => 'HMQUIZ Topic',
    ],
  ]));

  register_taxonomy('hmqz_category', ['hmqz_quiz'], array_merge($tax_common, [
    'labels' => [
      'name' => 'HMQUIZ Categories',
      'singular_name' => 'HMQUIZ Category',
    ],
  ]));
});

add_action('add_meta_boxes', function () {
  $screens = ['page', 'hmquiz_quiz'];
  foreach ($screens as $s) {
    add_meta_box('hmqz_bank', 'HMQUIZ — Bank File', 'hmqz_bank_metabox', $s, 'side', 'default');
  }
});

function hmqz_bank_metabox($post) {
  wp_nonce_field('hmqz_bank_meta', 'hmqz_bank_nonce');
  $current = get_post_meta($post->ID, '_hmqz_bank_file', true);
  $files = hmqz_list_bank_files();
  echo '<p><label for="hmqz_bank_file">Choose a bank (.json in uploads/hmquiz/banks):</label></p>';
  echo '<select name="hmqz_bank_file" id="hmqz_bank_file" style="width:100%;">';
  echo '<option value="">— None —</option>';
  foreach ($files as $f) {
    $sel = selected($current, $f, false);
    echo "<option value='" . esc_attr($f) . "' $sel>" . esc_html($f) . "</option>";
  }
  echo '</select>';
  if (empty($files)) {
    echo '<p style="color:#666;">No JSON banks found. Upload to <code>wp-content/uploads/hmquiz/banks</code>.</p>';
  }
}

add_action('save_post', function ($post_id) {
  if (!isset($_POST['hmqz_bank_nonce']) || !wp_verify_nonce($_POST['hmqz_bank_nonce'], 'hmqz_bank_meta')) return;
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
  if (!current_user_can('edit_post', $post_id)) return;

  $val = isset($_POST['hmqz_bank_file']) ? sanitize_file_name($_POST['hmqz_bank_file']) : '';
  if ($val) {
    update_post_meta($post_id, '_hmqz_bank_file', $val);
  } else {
    delete_post_meta($post_id, '_hmqz_bank_file');
  }
});
