<?php
if (!defined('ABSPATH')) exit;
function hmqz_bank_dir() {
  $upload_dir = wp_upload_dir();
  $dir = trailingslashit($upload_dir['basedir']) . 'hmquiz/banks';
  if (!is_dir($dir)) wp_mkdir_p($dir);
  return $dir;
}
function hmqz_list_bank_files() {
  $dir = hmqz_bank_dir();
  if (!is_dir($dir)) return [];
  $files = glob($dir . '/*.json');
  return array_map('basename', $files ?: []);
}
function hmqz_load_bank($filename) {
  $safe = sanitize_file_name($filename);
  $file = trailingslashit(hmqz_bank_dir()) . $safe;
  if (!file_exists($file)) return null;
  $key = 'hmqz_bank_' . md5($file . ':' . filemtime($file));
  $cached = get_transient($key);
  if ($cached !== false) return $cached;
  $json = file_get_contents($file);
  $data = json_decode($json, true);
  if (is_array($data)) {
    $is_list = array_values($data) === $data;
    if ($is_list) {
      $data = ['questions' => $data];
    }
    if (empty($data['questions']) && !empty($data['items']) && is_array($data['items'])) {
      $questions = [];
      foreach ($data['items'] as $item) {
        $meta = $item['meta'] ?? [];
        $questions[] = [
          'question'    => $item['q'] ?? $item['question'] ?? '',
          'choices'     => $item['choices'] ?? [],
          'options'     => $item['options'] ?? ($item['choices'] ?? []),
          'answer'      => $item['answer'] ?? 0,
          'explanation' => $item['explain'] ?? $item['explanation'] ?? '',
          'topic'       => $item['topic'] ?? ($meta['topic'] ?? 'General'),
          'category'    => $item['category'] ?? ($meta['category'] ?? 'General'),
          'meta'        => $meta,
        ];
      }
      $data['questions'] = $questions;
    }
    set_transient($key, $data, HOUR_IN_SECONDS);
    return $data;
  }
  return null;
}

function hmqz_bank_counts($filename) {
  $safe = sanitize_file_name($filename);
  $file = trailingslashit(hmqz_bank_dir()) . $safe;
  if (!file_exists($file)) return ['topics'=>[], 'categories'=>[], 'topic_to_categories'=>[]];
  $cache_key = 'hmqz_bank_counts_' . md5($file . ':' . filemtime($file));
  $cached = get_transient($cache_key);
  if ($cached !== false) return $cached;
  $bank = hmqz_load_bank($filename);
  $topics = [];
  $categories = [];
  $topic_to_categories = [];
  $pool = [];
  if (!empty($bank['questions']) && is_array($bank['questions'])) {
    $pool = $bank['questions'];
  } elseif (!empty($bank['items']) && is_array($bank['items'])) {
    $pool = $bank['items'];
  }
  if ($pool) {
    foreach ($pool as $q) {
      $topic = isset($q['topic']) && $q['topic'] !== '' ? trim((string)$q['topic']) : 'General';
      $category = isset($q['category']) && $q['category'] !== '' ? trim((string)$q['category']) : 'General';
      $topics[$topic] = ($topics[$topic] ?? 0) + 1;
      $categories[$category] = ($categories[$category] ?? 0) + 1;
      if (!isset($topic_to_categories[$topic])) {
        $topic_to_categories[$topic] = [];
      }
      if (!in_array($category, $topic_to_categories[$topic], true)) {
        $topic_to_categories[$topic][] = $category;
      }
    }
  }
  foreach ($topic_to_categories as &$cats) {
    sort($cats, SORT_NATURAL | SORT_FLAG_CASE);
  }
  ksort($topics, SORT_NATURAL | SORT_FLAG_CASE);
  ksort($categories, SORT_NATURAL | SORT_FLAG_CASE);
  ksort($topic_to_categories, SORT_NATURAL | SORT_FLAG_CASE);
  $result = [
    'topics' => $topics,
    'categories' => $categories,
    'topic_to_categories' => $topic_to_categories,
  ];
  set_transient($cache_key, $result, HOUR_IN_SECONDS);
  return $result;
}
// --- runtime helpers ---

/**
 * Pick up to $per items from $arr using a reproducible seed (optional).
 */
function hmqz_pick(array $arr, int $per, ?int $seed = null): array {
  $n = count($arr);
  if ($n <= $per) return $arr;
  if ($seed !== null) mt_srand($seed);
  $keys = array_rand($arr, $per);
  if (!is_array($keys)) $keys = [$keys];
  $picked = [];
  foreach ($keys as $k) $picked[] = $arr[$k];
  if ($seed !== null) mt_srand(); // reset RNG
  return $picked;
}

/**
 * Return sorted list of unique categories present in a bank file.
 */
function hmqz_list_categories(string $filename): array {
  $bank = hmqz_load_bank($filename);
  if (empty($bank['questions']) || !is_array($bank['questions'])) return [];
  $cats = [];
  foreach ($bank['questions'] as $q) {
    if (!empty($q['category'])) {
      $cats[] = trim((string)$q['category']);
    }
  }
  $cats = array_values(array_unique($cats));
  sort($cats, SORT_NATURAL | SORT_FLAG_CASE);
  return $cats;
}

/**
 * Load a bank file and optionally adjust question list at runtime.
 * Options: ['category'=>string, 'categories'=>string, 'per'=>int, 'random'=>bool, 'seed'=>int|null]
 */
function hmqz_load_with_options(string $filename, array $opts = []): array {
  $bank = hmqz_load_bank($filename);
  if (empty($bank) || empty($bank['questions']) || !is_array($bank['questions'])) return $bank;

  $cat_inputs = [];
  if (!empty($opts['categories'])) {
    $cat_inputs = preg_split('~[|,]+~', (string)$opts['categories']);
  } elseif (!empty($opts['category'])) {
    $cat_inputs = [ $opts['category'] ];
  }

  $cat_labels = [];
  $cat_slugs  = [];
  foreach ($cat_inputs as $cat_input) {
    $label = trim((string)$cat_input);
    if ($label === '') continue;
    $slug = strtolower($label);
    if (!in_array($slug, $cat_slugs, true)) {
      $cat_slugs[] = $slug;
      $cat_labels[] = $label;
    }
  }

  if (!empty($cat_slugs)) {
    $bank['questions'] = array_values(array_filter($bank['questions'], function($q) use ($cat_slugs) {
      return isset($q['category']) && in_array(strtolower(trim((string)$q['category'])), $cat_slugs, true);
    }));
    if (!empty($bank['title'])) {
      $bank['title'] = trim($bank['title'] . ' — ' . implode(' / ', array_map('ucwords', $cat_labels)));
    } else {
      $bank['title'] = implode(' / ', array_map('ucwords', $cat_labels));
    }
  }

  $per    = isset($opts['per'])    ? max(1, (int)$opts['per']) : 10;
  $random = !empty($opts['random']);
  $seed   = array_key_exists('seed', $opts) ? (is_null($opts['seed']) ? null : (int)$opts['seed']) : null;

  if ($random) {
    $bank['questions'] = hmqz_pick($bank['questions'], $per, $seed);
    $bank['title'] = trim(($bank['title'] ?? 'Quiz') . " — {$per} Qs");
  }

  return $bank;
}
