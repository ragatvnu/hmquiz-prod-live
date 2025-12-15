<?php
if (!defined('ABSPATH')) exit;

/**
 * Normalize incoming bank slugs so legacy query params still resolve.
 *
 * Keeps old-style ?bank=mcq_confusables_*.json links working after the banks
 * were moved under english_grammar/confusing_words/. Extend this if new
 * legacy patterns appear.
 */
function hmqz_normalize_bank_slug($bank) {
  $bank = trim((string) $bank);
  $bank = ltrim($bank, '/');
  if ($bank === '') return '';

  // Already a nested path → leave as-is.
  if (strpos($bank, '/') !== false) {
    return $bank;
  }

  // Older prefix variant that was renamed alongside the move.
  if (strpos($bank, 'mcq_confusing_words_') === 0) {
    $suffix = substr($bank, strlen('mcq_confusing_words_'));
    return 'english_grammar/confusing_words/mcq_confusables_' . $suffix;
  }

  // Legacy flat confusables filename → new folder path.
  if (preg_match('/^mcq_confusables_.*\\.json$/', $bank)) {
    return 'english_grammar/confusing_words/' . $bank;
  }

  return $bank;
}

/**
 * Infer a quiz hub URL from a normalized bank slug/path.
 *
 * Examples:
 * - english_grammar/confusing_words/mcq_confusables_affect_vs_effect.json
 *   => /quiz/confusing-words/
 *
 * @param string $bank Normalized bank path (no base dir).
 * @return string Hub URL (absolute) or empty string if unknown.
 */
function hmqz_get_hub_url_for_bank($bank) {
  $bank = (string) $bank;
  if ($bank === '') return '';

  $parts = explode('/', ltrim($bank, '/'));
  $topic = isset($parts[0]) ? $parts[0] : '';
  $subtopic = isset($parts[1]) ? $parts[1] : '';

  if ($topic && $subtopic && function_exists('hmqz_get_hub_url_for_topic_and_subtopic')) {
    $registry_url = hmqz_get_hub_url_for_topic_and_subtopic($topic, $subtopic);
    if (!empty($registry_url)) {
      return $registry_url;
    }
  }

  // Legacy fallback mapping (kept for backward compatibility).
  $hub_slug = '';
  if ($topic === 'english_grammar') {
    switch ($subtopic) {
      case 'confusing_words':
        $hub_slug = 'confusing-words';
        break;
      case 'punctuation':
        $hub_slug = 'punctuation';
        break;
      case 'tenses':
        $hub_slug = 'tenses';
        break;
    }
  }

  if ($hub_slug === '') return '';

  $path = '/quiz/' . $hub_slug . '/';

  return home_url($path);
}

/**
 * Path to the banks manifest (mcq_manifest.json) inside uploads/hmquiz/banks/.
 *
 * @return string
 */
function hmqz_get_bank_manifest_path() {
  if (function_exists('hmqz_bank_dir')) {
    $dir = trailingslashit(hmqz_bank_dir());
  } else {
    $dir = WP_CONTENT_DIR . '/uploads/hmquiz/banks/';
    if (!is_dir($dir)) {
      wp_mkdir_p($dir);
    }
  }

  return $dir . 'mcq_manifest.json';
}

/**
 * Return the full banks index manifest as an array.
 *
 * Structure example:
 * [ 'version' => '...', 'updated_at' => '...', 'banks' => [ ... ] ]
 *
 * @return array
 */
function hmqz_get_banks_index() {
  static $manifest_cache = null;
  if (is_array($manifest_cache)) {
    return $manifest_cache;
  }

  $default = ['banks' => []];
  $path = hmqz_get_bank_manifest_path();
  if (!file_exists($path)) {
    $manifest_cache = $default;
    return $manifest_cache;
  }

  $json = file_get_contents($path);
  if ($json === false) {
    $manifest_cache = $default;
    return $manifest_cache;
  }

  $data = json_decode($json, true);
  if (!is_array($data)) {
    $manifest_cache = $default;
    return $manifest_cache;
  }

  if (!isset($data['banks']) || !is_array($data['banks'])) {
    $data['banks'] = [];
  }

  $manifest_cache = $data;
  return $manifest_cache;
}

/**
 * Extract topic/subtopic keys from a bank record.
 *
 * @param array $bank
 * @return array{topic:string,subtopic:string}
 */
function hmqz_bank_topic_keys(array $bank) {
  $topic = '';
  $subtopic = '';

  if (!empty($bank['topic_key'])) {
    $topic = (string) $bank['topic_key'];
  } elseif (!empty($bank['topic'])) {
    $topic = (string) $bank['topic'];
  }

  if (!empty($bank['subtopic_key'])) {
    $subtopic = (string) $bank['subtopic_key'];
  } elseif (!empty($bank['subtopic'])) {
    $subtopic = (string) $bank['subtopic'];
  }

  if ((!$topic || !$subtopic) && !empty($bank['bank'])) {
    $rel = ltrim((string) $bank['bank'], '/');
    $parts = explode('/', $rel);
    if (!$topic && isset($parts[0])) $topic = $parts[0];
    if (!$subtopic && isset($parts[1])) $subtopic = $parts[1];
  }

  return [
    'topic'    => $topic,
    'subtopic' => $subtopic,
  ];
}

/**
 * Return all banks for a given topic key.
 *
 * @param string $topic_key
 * @return array
 */
function hmqz_get_banks_for_topic($topic_key) {
  $topic_key = trim((string) $topic_key);
  if ($topic_key === '') return [];

  $index = hmqz_get_banks_index();
  $banks = isset($index['banks']) && is_array($index['banks']) ? $index['banks'] : [];

  $filtered = array_filter($banks, function($bank) use ($topic_key) {
    $keys = hmqz_bank_topic_keys(is_array($bank) ? $bank : []);
    return !empty($keys['topic']) && $keys['topic'] === $topic_key;
  });


  // fallback: allow virtual hubs by subtopic_key (e.g. confusing_words)
  if (empty($filtered)) {
    $filtered = array_filter($banks, function($bank) use ($topic_key) {
      if (!is_array($bank)) return false;
      $sk = isset($bank["subtopic_key"]) ? trim((string)$bank["subtopic_key"]) : "";
      $s  = isset($bank["subtopic"]) ? trim((string)$bank["subtopic"]) : "";
      return ($sk !== "" && $sk === $topic_key) || ($s !== "" && $s === $topic_key);
    });
  }

  return array_values($filtered);
}

/**
 * Return banks for a given topic + subtopic combination.
 *
 * @param string $topic_key
 * @param string $subtopic_key
 * @return array
 */
function hmqz_get_banks_for_topic_and_subtopic($topic_key, $subtopic_key) {
  $topic_key = trim((string) $topic_key);
  $subtopic_key = trim((string) $subtopic_key);
  if ($topic_key === '' || $subtopic_key === '') return [];

  $index = hmqz_get_banks_index();
  $banks = isset($index['banks']) && is_array($index['banks']) ? $index['banks'] : [];

  $filtered = array_filter($banks, function($bank) use ($topic_key, $subtopic_key) {
    $keys = hmqz_bank_topic_keys(is_array($bank) ? $bank : []);
    return !empty($keys['topic']) && !empty($keys['subtopic'])
      && $keys['topic'] === $topic_key
      && $keys['subtopic'] === $subtopic_key;
  });

  return array_values($filtered);
}

/**
 * Normalize incoming bank filenames into the correct folder structure.
 *
 * Handles:
 *   - Legacy mcq_confusing_words_* → english_grammar/confusing_words/mcq_confusables_*
 *   - Flat mcq_confusables_*      → english_grammar/confusing_words/mcq_confusables_*
 */
function hmqz_sanitize_bank_relpath($filename) {
  // Normalize slashes and trim
  $rel = wp_normalize_path((string) $filename);
  $rel = trim($rel);
  $rel = ltrim($rel, '/');

  // Backward-compat layer for legacy bank slugs.
  $rel = hmqz_normalize_bank_slug($rel);

  // Allow only safe characters: letters, numbers, underscore, dash, dot, slash
  $rel = preg_replace('~[^a-zA-Z0-9_\-./]+~', '', $rel);

  // Block directory traversal
  if (strpos($rel, '..') !== false) {
    return '';
  }

  return $rel;
}

function hmqz_bank_dir() {
  $upload_dir = wp_upload_dir();
  $dir = trailingslashit($upload_dir['basedir']) . 'hmquiz/banks';
  if (!is_dir($dir)) wp_mkdir_p($dir);
  return $dir;
}

function hmqz_list_bank_files() {
  $dir = hmqz_bank_dir();
  if (!is_dir($dir)) return [];

  $result = [];

  $it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
  );

  foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (!$file->isFile()) continue;
    if (strtolower($file->getExtension()) !== 'json') continue;

    $full = wp_normalize_path($file->getPathname());
    $base = wp_normalize_path($dir);

    // Relative path under banks/, e.g. "english_grammar/confusing_words/xyz.json"
    if (strpos($full, $base) === 0) {
      $rel = ltrim(substr($full, strlen($base)), '/');
      $result[] = $rel;
    }
  }

  sort($result);
  return $result;
}

function hmqz_load_bank($filename) {
  $rel = hmqz_sanitize_bank_relpath($filename);
  if ($rel === '') return null;

  $file = wp_normalize_path(trailingslashit(hmqz_bank_dir()) . $rel);
  if (!file_exists($file)) return null;

  $key    = 'hmqz_bank_' . md5($file . ':' . filemtime($file));
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
  $rel = hmqz_sanitize_bank_relpath($filename);
  if ($rel === '') {
    return ['topics'=>[], 'categories'=>[], 'topic_to_categories'=>[]];
  }

  $file = wp_normalize_path(trailingslashit(hmqz_bank_dir()) . $rel);
  if (!file_exists($file)) {
    return ['topics'=>[], 'categories'=>[], 'topic_to_categories'=>[]];
  }

  $cache_key = 'hmqz_bank_counts_' . md5($file . ':' . filemtime($file));
  $cached    = get_transient($cache_key);
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
      $topic = isset($q['topic']) && $q['topic'] !== '' ? trim($q['topic']) : 'General';
      $cat   = isset($q['category']) && $q['category'] !== '' ? trim($q['category']) : 'General';
      $topics[$topic] = true;
      $categories[$cat] = true;
      if (!isset($topic_to_categories[$topic])) {
        $topic_to_categories[$topic] = [];
      }
      $topic_to_categories[$topic][$cat] = true;
    }
  }

  $result = [
    'topics'             => array_keys($topics),
    'categories'         => array_keys($categories),
    'topic_to_categories'=> array_map('array_keys', $topic_to_categories),
  ];
  set_transient($cache_key, $result, HOUR_IN_SECONDS);
  return $result;
}

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
