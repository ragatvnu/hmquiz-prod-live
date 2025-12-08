<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalize legacy / flat bank paths into the new folder structure.
 *
 * Examples:
 *   mcq_confusing_words_affect_vs_effect.json
 *      → english_grammar/confusing_words/mcq_confusables_affect_vs_effect.json
 *
 *   mcq_confusables_affect_vs_effect.json
 *      → english_grammar/confusing_words/mcq_confusables_affect_vs_effect.json
 */
function hmqz_normalize_bank_rel($rel) {
  $rel = ltrim(trim((string) $rel), '/');

  if (function_exists('hmqz_normalize_bank_slug')) {
    return hmqz_normalize_bank_slug($rel);
  }

  // 1) Old "mcq_confusing_words_*" names → new confusables path
  $old_prefix = 'mcq_confusing_words_';
  if (strpos($rel, $old_prefix) === 0) {
    $suffix = substr($rel, strlen($old_prefix)); // e.g. 'affect_vs_effect.json'
    $rel = 'english_grammar/confusing_words/mcq_confusables_' . $suffix;
  }

  // 2) Flat confusables file in root → move into confusing_words folder
  if (strpos($rel, 'mcq_confusables_') === 0 && strpos($rel, '/') === false) {
    $rel = 'english_grammar/confusing_words/' . $rel;
  }

  return $rel;
}


/**
 * Load MCQ items for a given quiz post.
 * Priority:
 *   1) post meta 'hmqz_mcq_json' (JSON string array of {id,text,choices:[{label,correct}]})
 *   2) post meta 'hmqz_bank_file' (relative to uploads/hmquiz/banks/, e.g., 'math/level1.json')
 *
 * @return array{items: array<int,array>, source: string}
 */
function hmqz_load_mcq_items($post_id) {
  $raw = get_post_meta($post_id, 'hmqz_mcq_json', true);
  if ($raw) {
    $arr = json_decode($raw, true);
    if (is_array($arr) && !empty($arr)) {
      return ['items' => $arr, 'source' => 'meta:hmqz_mcq_json'];
    }
  }

  $rel = trim((string) get_post_meta($post_id, 'hmqz_bank_file', true));
  if ($rel !== '') {
    $rel = hmqz_normalize_bank_rel($rel); 
    $upload = wp_get_upload_dir();
    $base = trailingslashit($upload['basedir']) . 'hmquiz/banks/';
    $path = wp_normalize_path($base . ltrim($rel, '/'));
    if (file_exists($path)) {
      $json = file_get_contents($path);
      $arr = json_decode($json, true);
      if (is_array($arr) && !empty($arr)) {
        if (isset($arr['items']) && is_array($arr['items'])) {
          $items = $arr['items'];
        } elseif (isset($arr['questions']) && is_array($arr['questions'])) {
          $items = $arr['questions'];
        } elseif (array_values($arr) === $arr) {
          $items = $arr;
        } else {
          $items = [];
        }
        if (!empty($items)) {
          return ['items' => $items, 'source' => 'file:' . $rel];
        }
      }
    }
  }
  return ['items' => [], 'source' => 'none'];
}

/**
 * Build levels from items by chunking.
 *
 * @param array $items
 * @param int $per_level default 10
 * @param float $pass_ratio default 0.6
 * @return array levels [{id, passScore, questions:[qid,...]}]
 */
function hmqz_build_levels(array $items, int $per_level = 10, float $pass_ratio = 0.6) {
  $levels = [];
  $chunks = array_chunk($items, max(1, $per_level));
  foreach ($chunks as $i => $chunk) {
    $qids = [];
    foreach ($chunk as $index => $q) {
      $qid = !empty($q['id']) ? (string)$q['id'] : 'q' . ($i + 1) . '_' . ($index + 1);
      $qids[] = $qid;
    }
    $total = count($qids);
    $pass = max(1, (int) ceil($total * $pass_ratio));
    $levels[] = [
      'id'        => 'L' . ($i + 1),
      'passScore' => $pass,
      'questions' => $qids,
    ];
  }
  return $levels;
}

function hmqz_level_rules_for_post($post_id) {
  $per = (int) get_post_meta($post_id, 'hmqz_per_level', true);
  if ($per <= 0) $per = 10;
  $ratio = (float) get_post_meta($post_id, 'hmqz_pass_ratio', true);
  if ($ratio <= 0 || $ratio >= 1) $ratio = 0.6;
  return [$per, $ratio];
}
