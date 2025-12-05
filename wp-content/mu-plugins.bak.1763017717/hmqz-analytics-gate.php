<?php
/*
Plugin Name: HMQZ Analytics Gate
*/
add_action('init', function () {
  $env = function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'production';
  if ($env !== 'production') {
    add_action('wp_enqueue_scripts', function() {
      // Kill common GA injectors on non-prod
      add_filter('script_loader_tag', function($tag, $handle){
        if (stripos($tag, 'googletagmanager')!==false || stripos($tag,'gtag(')!==false) return '';
        return $tag;
      }, 10, 2);
    }, 0);
  }
});
