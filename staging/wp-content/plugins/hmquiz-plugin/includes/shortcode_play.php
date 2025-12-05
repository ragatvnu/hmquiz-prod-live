<?php
if (!defined('ABSPATH')) exit;

/**
 * [hmquiz] — renders an app mount and ensures ?quiz=<id> is present.
 */
add_shortcode('hmquiz', function($atts){
  $quiz = isset($_GET['quiz']) ? intval($_GET['quiz']) : 0;
  if (!$quiz) {
    $ids = get_posts([
      'post_type' => 'hmqz_quiz',
      'post_status' => 'publish',
      'fields' => 'ids',
      'numberposts' => 1,
    ]);
    if ($ids) $quiz = (int)$ids[0];
  }
  ob_start(); ?>
  <div id="hmqz-app">
    <noscript>Enable JavaScript to play quizzes.</noscript>
  </div>
  <script>
  (function(){
    try{
      var u = new URL(window.location.href);
      if(!u.searchParams.get('quiz') && <?php echo $quiz ?: 0; ?>){
        u.searchParams.set('quiz','<?php echo $quiz; ?>');
        history.replaceState({},'',u.toString());
      }
    }catch(e){}
  })();
  </script>
  <?php
  return ob_get_clean();
});

