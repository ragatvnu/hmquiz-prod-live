<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function(){
  add_menu_page('HMQUIZ','HMQUIZ','manage_options','hmqz','hmqz_admin_home','dashicons-welcome-learn-more',58);
  add_submenu_page('hmqz','Banks','Banks','manage_options','hmqz-banks','hmqz_admin_banks');
  add_submenu_page('hmqz','Settings','Settings','manage_options','hmqz-settings','hmqz_admin_settings');
});

function hmqz_admin_settings() {
    if (isset($_POST['hmqz_topics_page'])) {
        update_option('hmqz_topics_page', intval($_POST['hmqz_topics_page']));
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }

    $selected_page = get_option('hmqz_topics_page');
    ?>
    <div class="wrap">
        <h1>HMQUIZ Settings</h1>
        <form method="post" action="">
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Topics Page</th>
                    <td>
                        <?php wp_dropdown_pages([
                            'name' => 'hmqz_topics_page',
                            'selected' => $selected_page,
                            'show_option_none' => '— Select a page —',
                        ]); ?>
                        <p class="description">Select the page to use for the topics list.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function hmqz_admin_home(){ echo '<div class="wrap"><h1>HMQUIZ</h1><p>See Banks for JSON validations.</p></div>'; }
function hmqz_admin_banks(){
  $files = hmqz_list_bank_files();
  echo '<div class="wrap"><h1>HMQUIZ — Banks</h1>';
  if (empty($files)) { echo '<p>No banks found. Upload to <code>'.esc_html(hmqz_bank_dir()).'</code></p></div>'; return; }
  echo '<table class="widefat striped"><thead><tr><th>File</th><th>Status</th><th>Questions</th><th>Preview</th></tr></thead><tbody>';
  foreach($files as $f){
    $status = 'OK'; $qcount = 0; $preview = '';
    $data = hmqz_load_bank($f);
    if (!is_array($data) || !isset($data['questions']) || !is_array($data['questions'])) {
      $status = '<span style="color:#b00;">Invalid JSON bank</span>';
    } else {
      $qcount = count($data['questions']);
      $first = $qcount ? sanitize_text_field($data['questions'][0]['question'] ?? '') : '';
      $last  = $qcount > 1 ? sanitize_text_field($data['questions'][$qcount-1]['question'] ?? '') : $first;
      $preview = esc_html(mb_strimwidth($first,0,60,'…'));
      if ($qcount > 1) $preview .= ' / ' . esc_html(mb_strimwidth($last,0,60,'…'));
      foreach ($data['questions'] as $i => $q){
        if (!isset($q['question']) || !isset($q['options']) || !is_array($q['options']) || count($q['options'])<2 || !isset($q['answer'])) {
          $status = '<span style="color:#d60;">Issues in question #'.($i+1).'</span>'; break;
        }
      }
    }
    echo '<tr><td><code>'.esc_html($f).'</code></td><td>'.$status.'</td><td>'.intval($qcount).'</td><td>'.$preview.'</td></tr>';
  }
  echo '</tbody></table></div>';
}
