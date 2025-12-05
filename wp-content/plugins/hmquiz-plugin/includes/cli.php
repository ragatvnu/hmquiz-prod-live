<?php
if (!defined('ABSPATH')) exit;

if (defined('WP_CLI') && WP_CLI) {
  WP_CLI::add_command('hmqz bank:validate', function($args, $assoc) {
    $file = $assoc['file'] ?? '';
    if (!$file) { WP_CLI::error('Pass --file=<bank.json>'); }

    $up = wp_get_upload_dir();
    $abs = trailingslashit($up['basedir']) . 'hmquiz/banks/' . ltrim($file, '/');
    if (!file_exists($abs)) WP_CLI::error("Not found: $abs");

    $j = json_decode(file_get_contents($abs), true);
    $items = (array)($j['items'] ?? array());

    $errors = 0; $i = 0;
    foreach ($items as $q) {
      $i++;
      $text = '';
      if (isset($q['q'])) $text = trim((string)$q['q']);
      elseif (isset($q['text'])) $text = trim((string)$q['text']);
      elseif (isset($q['question'])) $text = trim((string)$q['question']);

      if ($text === '') {
        WP_CLI::warning("Q#$i: missing prompt (expected 'q'|'text'|'question')");
        $errors++;
      }
      $opts = isset($q['options']) ? (array)$q['options'] : (isset($q['choices']) ? (array)$q['choices'] : array());
      if (count($opts) < 2) {
        WP_CLI::warning("Q#$i: needs >=2 options");
        $errors++;
      }
    }
    if ($errors) WP_CLI::error("Failed with $errors issue(s)");
    WP_CLI::success('OK');
  });
}

