<?php
if (!defined('ABSPATH')) exit;

/**
 * Path to the hubs JSON file in uploads, e.g. wp-content/uploads/hmquiz/hmqz-hubs.json
 */
function hmqz_get_hubs_json_path(): string {
  $upload_dir = wp_get_upload_dir();
  $base_dir   = trailingslashit($upload_dir['basedir']) . 'hmquiz';
  return trailingslashit($base_dir) . 'hmqz-hubs.json';
}

/**
 * Central registry of quiz hubs.
 *
 * Each hub entry can define:
 * - slug / path / title / description / icon / order
 * - subtopics: keyed by subtopic slug, same shape as parent
 *
 * Paths are stored as site-relative (leading slash) for portability.
 *
 * @return array<string, array>
 */
function hmqz_get_hubs_registry() {
  static $registry = null;
  if (is_array($registry)) {
    return $registry;
  }

  $registry = [
    'general_knowledge' => [
      'slug'        => 'general-knowledge',
      'path'        => '/quiz/general-knowledge/',
      'title'       => 'General Knowledge Quizzes',
      'description' => 'Quick quizzes across history, science, geography, and culture.',
      'icon'        => 'general-knowledge',
      'order'       => 5,
      'subtopics'   => [],
    ],
    'confusing_words' => [
      'slug'        => 'confusing-words',
      'path'        => '/quiz/confusing-words/',
      'title'       => 'Confusing Words Quizzes',
      'description' => 'Untangle tricky word pairs and common mix-ups.',
      'icon'        => 'confusing-words',
      'order'       => 8,
      'subtopics'   => [],
    ],
    'emoji_fun' => [
      'slug'        => 'emoji-fun',
      'path'        => '/quiz/emoji-fun/',
      'title'       => 'Emoji & Fun Quizzes',
      'description' => 'Guess the emoji phrases and have fun with light trivia.',
      'icon'        => 'emoji-fun',
      'order'       => 15,
      'subtopics'   => [],
    ],
    'brain_teasers' => [
      'slug'        => 'brain-teasers',
      'path'        => '/quiz/brain-teasers/',
      'title'       => 'Brain Teasers & Riddles',
      'description' => 'Short puzzles and riddles to stretch your thinking.',
      'icon'        => 'brain-teasers',
      'order'       => 18,
      'subtopics'   => [],
    ],
    'english_grammar' => [
      'slug'        => 'english-grammar',
      'path'        => '/quiz/english-grammar/',
      'title'       => 'English Grammar Quizzes – Fix Common Mistakes',
      'description' => 'Short description for SEO and hub intro.',
      'icon'        => 'grammar',
      'order'       => 10,
      'subtopics'   => [
        'confusing_words' => [
          'slug'        => 'confusing-words',
          'path'        => '/quiz/confusing-words/',
          'title'       => 'Confusing Words Quizzes',
          'description' => 'Practice tricky word pairs like affect vs effect.',
          'icon'        => 'confusing-words',
          'order'       => 10,
        ],
        'affect_vs_effect' => [
          'slug'        => 'affect-vs-effect',
          'path'        => '/quiz/affect-vs-effect/',
          'title'       => 'Affect vs Effect – Quiz Hub',
          'description' => 'Practice when to use "affect" vs "effect" with real examples.',
          'icon'        => 'affect-vs-effect',
          'order'       => 20,
        ],
      ],
    ],
  ];

  // Load JSON overrides/additions from uploads if present.
  $json_path = hmqz_get_hubs_json_path();
  if (file_exists($json_path) && is_readable($json_path)) {
    $json = file_get_contents($json_path);
    $data = json_decode((string)$json, true);
    if (is_array($data)) {
      foreach ($data as $topic_key => $topic_cfg) {
        if (!is_string($topic_key) || $topic_key === '' || !is_array($topic_cfg)) {
          continue;
        }

        $base_topic   = isset($registry[$topic_key]) && is_array($registry[$topic_key]) ? $registry[$topic_key] : array();
        $merged_topic = $base_topic;

        foreach ($topic_cfg as $cfg_key => $cfg_val) {
          if ($cfg_key === 'subtopics') {
            if (!is_array($cfg_val)) {
              continue;
            }
            if (!isset($merged_topic['subtopics']) || !is_array($merged_topic['subtopics'])) {
              $merged_topic['subtopics'] = array();
            }
            foreach ($cfg_val as $sub_key => $sub_cfg) {
              if (!is_string($sub_key) || $sub_key === '' || !is_array($sub_cfg)) {
                continue;
              }
              $existing_sub = isset($merged_topic['subtopics'][$sub_key]) && is_array($merged_topic['subtopics'][$sub_key]) ? $merged_topic['subtopics'][$sub_key] : array();
              $merged_topic['subtopics'][$sub_key] = array_replace($existing_sub, $sub_cfg);
            }
          } else {
            $merged_topic[$cfg_key] = $cfg_val;
          }
        }

        $registry[$topic_key] = $merged_topic;
      }
    }
  }

  return $registry;
}

/**
 * Convert a registry topic or subtopic key into a human-friendly label.
 *
 * Examples:
 *   english_grammar -> "English Grammar"
 *   confusing_words -> "Confusing Words"
 */
function hmqz_humanize_key(string $key): string {
  $clean = trim($key);
  if ($clean === '') return '';

  // Replace common separators with spaces and collapse repeats.
  $clean = str_replace(['_', '-'], ' ', $clean);
  $clean = preg_replace('/[^A-Za-z0-9\s]+/', ' ', $clean);
  $clean = preg_replace('/\s+/', ' ', (string) $clean);
  $clean = trim($clean);

  if ($clean === '') return trim($key);

  return ucwords(strtolower($clean));
}

/**
 * Get a flat list of topic-level hubs.
 *
 * @return array<int, array>
 */
function hmqz_get_topic_hubs(): array {
  $registry = hmqz_get_hubs_registry();
  $topics   = [];

  foreach ($registry as $topic_key => $cfg) {
    if (!is_array($cfg)) continue;

    $path = '';
    if (!empty($cfg['path'])) {
      $path = hmqz_normalize_hub_path($cfg['path']);
    } elseif (!empty($cfg['slug'])) {
      $path = '/quiz/' . trim((string) $cfg['slug'], '/') . '/';
    }

    $topics[] = [
      'type'        => 'topic',
      'topic_key'   => $topic_key,
      'title'       => !empty($cfg['title']) ? (string) $cfg['title'] : hmqz_humanize_key($topic_key),
      'description' => !empty($cfg['description']) ? (string) $cfg['description'] : '',
      'path'        => $path,
      'order'       => isset($cfg['order']) ? (int) $cfg['order'] : 50,
      'icon'        => $cfg['icon'] ?? '',
    ];
  }

  usort($topics, function($a, $b) {
    return array($a['order'] ?? 50, $a['title'] ?? '') <=> array($b['order'] ?? 50, $b['title'] ?? '');
  });

  return $topics;
}

/**
 * Get a flat list of subtopic-level hubs.
 *
 * @return array<int, array>
 */
function hmqz_get_subtopic_hubs(): array {
  $registry = hmqz_get_hubs_registry();
  $subs     = [];

  foreach ($registry as $topic_key => $cfg) {
    if (empty($cfg['subtopics']) || !is_array($cfg['subtopics'])) {
      continue;
    }

    $topic_title = !empty($cfg['title']) ? (string) $cfg['title'] : hmqz_humanize_key($topic_key);
    $topic_order = isset($cfg['order']) ? (int) $cfg['order'] : 50;

    foreach ($cfg['subtopics'] as $sub_key => $sub_cfg) {
      if (!is_array($sub_cfg)) continue;

      $path = '';
      if (!empty($sub_cfg['path'])) {
        $path = hmqz_normalize_hub_path($sub_cfg['path']);
      } elseif (!empty($sub_cfg['slug'])) {
        $path = '/quiz/' . trim((string) $sub_cfg['slug'], '/') . '/';
      }

      $subs[] = [
        'type'         => 'subtopic',
        'topic_key'    => $topic_key,
        'subtopic_key' => $sub_key,
        'title'        => !empty($sub_cfg['title']) ? (string) $sub_cfg['title'] : hmqz_humanize_key($sub_key),
        'description'  => !empty($sub_cfg['description']) ? (string) $sub_cfg['description'] : '',
        'path'         => $path,
        'order'        => isset($sub_cfg['order']) ? (int) $sub_cfg['order'] : 50,
        'icon'         => $sub_cfg['icon'] ?? '',
        'topic_title'  => $topic_title,
        'topic_order'  => $topic_order,
      ];
    }
  }

  usort($subs, function($a, $b) {
    return array($a['topic_order'] ?? 50, $a['order'] ?? 50, $a['title'] ?? '') <=> array($b['topic_order'] ?? 50, $b['order'] ?? 50, $b['title'] ?? '');
  });

  return $subs;
}

/**
 * Normalize a hub path to a leading/trailing-slash site-relative form.
 *
 * @param string $path Hub path or URL.
 * @return string Normalized path (e.g., "/quiz/confusing-words/") or empty string.
 */
function hmqz_normalize_hub_path($path) {
  $clean = trim((string)$path);
  if ($clean === '') return '';

  if (strpos($clean, '://') !== false) {
    $parsed = wp_parse_url($clean);
    $clean = isset($parsed['path']) ? $parsed['path'] : '';
  }

  if ($clean === '') return '';

  $clean = '/' . ltrim($clean, '/');
  if (substr($clean, -1) !== '/') {
    $clean .= '/';
  }

  return $clean;
}

/**
 * Locate a hub config by its path (topic or subtopic).
 *
 * @param string $path Path or URL to search for.
 * @return array|null Hub config array or null if not found.
 */
function hmqz_get_hub_by_path($path) {
  $needle = hmqz_normalize_hub_path($path);
  if ($needle === '') return null;

  $registry = hmqz_get_hubs_registry();
  foreach ($registry as $topic_key => $topic) {
    $topic_path = isset($topic['path']) ? hmqz_normalize_hub_path($topic['path']) : '';
    if ($topic_path && $topic_path === $needle) {
      $topic['_topic_key'] = $topic_key;
      return $topic;
    }

    if (!empty($topic['subtopics']) && is_array($topic['subtopics'])) {
      foreach ($topic['subtopics'] as $subtopic_key => $subtopic) {
        $sub_path = isset($subtopic['path']) ? hmqz_normalize_hub_path($subtopic['path']) : '';
        if ($sub_path && $sub_path === $needle) {
          $subtopic['_topic_key']    = $topic_key;
          $subtopic['_subtopic_key'] = $subtopic_key;
          return $subtopic;
        }
      }
    }
  }

  return null;
}

/**
 * Fetch a hub config by topic + subtopic keys.
 *
 * @param string $topic_key
 * @param string $subtopic_key
 * @return array|null
 */
function hmqz_get_hub_by_topic_and_subtopic($topic_key, $subtopic_key) {
  $topic_key = (string)$topic_key;
  $subtopic_key = (string)$subtopic_key;
  if ($topic_key === '' || $subtopic_key === '') return null;

  $registry = hmqz_get_hubs_registry();
  if (empty($registry[$topic_key]) || empty($registry[$topic_key]['subtopics'][$subtopic_key])) {
    return null;
  }

  $hub = $registry[$topic_key]['subtopics'][$subtopic_key];
  $hub['_topic_key'] = $topic_key;
  $hub['_subtopic_key'] = $subtopic_key;
  return $hub;
}

/**
 * Get a hub URL for the given topic/subtopic keys.
 *
 * @param string $topic_key
 * @param string $subtopic_key
 * @return string|null Absolute URL or null if unknown.
 */
function hmqz_get_hub_url_for_topic_and_subtopic($topic_key, $subtopic_key) {
  $hub = hmqz_get_hub_by_topic_and_subtopic($topic_key, $subtopic_key);
  if (!$hub) return null;

  $path = '';
  if (!empty($hub['path'])) {
    $path = hmqz_normalize_hub_path($hub['path']);
  } elseif (!empty($hub['slug'])) {
    $path = '/quiz/' . trim($hub['slug'], '/') . '/';
  }

  if ($path === '') return null;

  // Already absolute URL
  if (strpos($path, '://') !== false) {
    return $path;
  }

  return home_url($path);
}

/**
 * Convenience helper to map a bank path to a hub config, if known.
 *
 * @param string $bank_path Normalized bank path (e.g., english_grammar/confusing_words/...json).
 * @return array|null
 */
function hmqz_get_hub_config_for_bank($bank_path) {
  $bank_path = trim((string)$bank_path);
  if ($bank_path === '') return null;

  $parts = explode('/', ltrim($bank_path, '/'));
  $topic = isset($parts[0]) ? $parts[0] : '';
  $subtopic = isset($parts[1]) ? $parts[1] : '';

  if ($topic === '' || $subtopic === '') {
    return null;
  }

  return hmqz_get_hub_by_topic_and_subtopic($topic, $subtopic);
}
