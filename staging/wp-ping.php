<?php
define('SHORTINIT', false);
require __DIR__ . '/wp-load.php';
header('X-HMQZ-WP: loaded');
do_action('init'); // will trigger hmqz-probe MU header if MU is loading
echo "ok\n";
