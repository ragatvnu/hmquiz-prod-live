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

  class HMQZ_Hub_Command {
    public function scaffold($args, $assoc_args) {
      $topic_key = trim((string)($assoc_args['topic'] ?? ''));
      if ($topic_key === '') {
        WP_CLI::error('Pass --topic=<topic_key>');
      }

      $subtopic_key = trim((string)($assoc_args['subtopic'] ?? ''));
      $title        = trim((string)($assoc_args['title'] ?? ''));
      $description  = trim((string)($assoc_args['description'] ?? ''));
      $icon         = trim((string)($assoc_args['icon'] ?? ''));
      $order_raw    = $assoc_args['order'] ?? null;
      $order_val    = is_null($order_raw) ? null : (int)$order_raw;

      $slug_source  = (string)($assoc_args['slug'] ?? '');
      $slug_base    = $slug_source !== '' ? $slug_source : ($subtopic_key !== '' ? $subtopic_key : $topic_key);
      $slug         = sanitize_title(str_replace('_', '-', $slug_base));
      if ($slug === '') {
        WP_CLI::error('Unable to derive slug; pass --slug');
      }

      $path = isset($assoc_args['path']) ? hmqz_normalize_hub_path($assoc_args['path']) : '';
      if ($path === '') {
        $path = '/quiz/' . $slug . '/';
      }

      $page_title = $title !== '' ? $title : hmqz_humanize_key($subtopic_key !== '' ? $subtopic_key : $topic_key);
      if ($page_title === '') {
        $page_title = $subtopic_key !== '' ? $subtopic_key : $topic_key;
      }

      // Build shortcode content.
      if ($subtopic_key !== '') {
        $shortcode = sprintf('[hmqz_hub topic="%s" subtopic="%s"]', esc_attr($topic_key), esc_attr($subtopic_key));
      } else {
        $shortcode = sprintf('[hmqz_hub topic="%s" mode="topic"]', esc_attr($topic_key));
      }

      // Load existing hubs JSON.
      $json_path = hmqz_get_hubs_json_path();
      $hubs_data = array();
      if (file_exists($json_path) && is_readable($json_path)) {
        $raw = file_get_contents($json_path);
        $decoded = json_decode((string)$raw, true);
        if (is_array($decoded)) {
          $hubs_data = $decoded;
        }
      }

      // Ensure parent topic exists in data.
      if (!isset($hubs_data[$topic_key]) || !is_array($hubs_data[$topic_key])) {
        $hubs_data[$topic_key] = array();
      }

      // Default topic metadata if missing.
      if (empty($hubs_data[$topic_key]['slug'])) {
        $hubs_data[$topic_key]['slug'] = sanitize_title(str_replace('_', '-', $topic_key));
      }
      if (empty($hubs_data[$topic_key]['path'])) {
        $hubs_data[$topic_key]['path'] = '/quiz/' . $hubs_data[$topic_key]['slug'] . '/';
      }
      if (empty($hubs_data[$topic_key]['title'])) {
        $hubs_data[$topic_key]['title'] = hmqz_humanize_key($topic_key);
      }

      if ($subtopic_key !== '') {
        if (empty($hubs_data[$topic_key]['subtopics']) || !is_array($hubs_data[$topic_key]['subtopics'])) {
          $hubs_data[$topic_key]['subtopics'] = array();
        }
        if (!isset($hubs_data[$topic_key]['subtopics'][$subtopic_key]) || !is_array($hubs_data[$topic_key]['subtopics'][$subtopic_key])) {
          $hubs_data[$topic_key]['subtopics'][$subtopic_key] = array();
        }

        $hubs_data[$topic_key]['subtopics'][$subtopic_key]['slug'] = $slug;
        $hubs_data[$topic_key]['subtopics'][$subtopic_key]['path'] = $path;
        if ($title !== '') {
          $hubs_data[$topic_key]['subtopics'][$subtopic_key]['title'] = $title;
        }
        if ($description !== '') {
          $hubs_data[$topic_key]['subtopics'][$subtopic_key]['description'] = $description;
        }
        if ($icon !== '') {
          $hubs_data[$topic_key]['subtopics'][$subtopic_key]['icon'] = $icon;
        }
        if (!is_null($order_val)) {
          $hubs_data[$topic_key]['subtopics'][$subtopic_key]['order'] = $order_val;
        }
      } else {
        $hubs_data[$topic_key]['slug'] = $slug;
        $hubs_data[$topic_key]['path'] = $path;
        if ($title !== '') {
          $hubs_data[$topic_key]['title'] = $title;
        }
        if ($description !== '') {
          $hubs_data[$topic_key]['description'] = $description;
        }
        if ($icon !== '') {
          $hubs_data[$topic_key]['icon'] = $icon;
        }
        if (!is_null($order_val)) {
          $hubs_data[$topic_key]['order'] = $order_val;
        }
      }

      // Persist hubs JSON.
      $json_dir = dirname($json_path);
      if (!is_dir($json_dir)) {
        wp_mkdir_p($json_dir);
      }
      $encoded = wp_json_encode($hubs_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($encoded === false) {
        WP_CLI::error('Failed to encode hubs JSON.');
      }
      $bytes = file_put_contents($json_path, $encoded . "\n", LOCK_EX);
      if ($bytes === false) {
        WP_CLI::error('Failed to write hubs JSON to ' . $json_path);
      }
      WP_CLI::log('Updated hubs JSON: ' . $json_path);

      // Create or update the WP page with the shortcode.
      $page_path = trim($path, '/');
      $page      = $page_path ? get_page_by_path($page_path, OBJECT, 'page') : null;
      if (!$page) {
        $page = get_page_by_path($slug, OBJECT, 'page');
      }

      $parent_id = 0;
      $segments = $page_path ? explode('/', $page_path) : array();
      if (count($segments) > 1) {
        $parent_path = implode('/', array_slice($segments, 0, -1));
        $parent_page = get_page_by_path($parent_path, OBJECT, 'page');
        if ($parent_page) {
          $parent_id = (int)$parent_page->ID;
        }
      }

      $post_data = array(
        'post_title'   => $page_title,
        'post_content' => $shortcode,
        'post_status'  => 'publish',
        'post_type'    => 'page',
      );

      if ($page) {
        $post_data['ID'] = $page->ID;
        if ($parent_id && $page->post_parent !== $parent_id) {
          $post_data['post_parent'] = $parent_id;
        }
        $result = wp_update_post($post_data, true);
        if (is_wp_error($result)) {
          WP_CLI::error($result->get_error_message());
        }
        WP_CLI::success(sprintf('Updated page #%d at %s', $page->ID, $path));
      } else {
        $post_data['post_name'] = $slug;
        if ($parent_id) {
          $post_data['post_parent'] = $parent_id;
        }
        $page_id = wp_insert_post($post_data, true);
        if (is_wp_error($page_id)) {
          WP_CLI::error($page_id->get_error_message());
        }
        WP_CLI::success(sprintf('Created page #%d at %s', $page_id, $path));
      }
    }
  }

  WP_CLI::add_command('hmqz hub', 'HMQZ_Hub_Command');

  class HMQZ_Banks_Command {
    public function rebuild_manifest($args, $assoc_args) {
      $include_plugin = \WP_CLI\Utils\get_flag_value($assoc_args, 'include-plugin', false);

      $sources = [];

      $upload_base = function_exists('hmqz_bank_dir')
        ? wp_normalize_path(hmqz_bank_dir())
        : wp_normalize_path(WP_CONTENT_DIR . '/uploads/hmquiz/banks');
      $sources[] = ['base' => $upload_base, 'label' => 'uploads'];

      $plugin_base = wp_normalize_path(HMQZ_PLUGIN_DIR . 'banks');
      if ($include_plugin && is_dir($plugin_base)) {
        $sources[] = ['base' => $plugin_base, 'label' => 'plugin'];
      }

      $files = [];
      foreach ($sources as $src) {
        $files = array_merge($files, $this->collect_bank_files($src['base'], $src['label']));
      }

      if (empty($files)) {
        WP_CLI::warning('No bank JSON files found to scan.');
        return;
      }

      $records = [];
      $skipped = 0;
      foreach ($files as $file) {
        $rec = $this->build_record_from_bank($file['full'], $file['rel'], $file['source']);
        if ($rec) {
          $records[] = $rec;
        } else {
          $skipped++;
        }
      }

      $manifest = [
        'version'      => '1',
        'generated_at' => current_time('mysql'),
        'banks'        => array_values($records),
      ];

      $manifest_path = function_exists('hmqz_get_bank_manifest_path')
        ? hmqz_get_bank_manifest_path()
        : wp_normalize_path($upload_base . '/mcq_manifest.json');

      $manifest_dir = dirname($manifest_path);
      if (!is_dir($manifest_dir)) {
        wp_mkdir_p($manifest_dir);
      }

      $encoded = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      if ($encoded === false) {
        WP_CLI::error('Failed to encode manifest JSON.');
      }

      $bytes = file_put_contents($manifest_path, $encoded . "\n", LOCK_EX);
      if ($bytes === false) {
        WP_CLI::error('Failed to write manifest to ' . $manifest_path);
      }

      WP_CLI::log(sprintf(
        'Scanned %d file(s), wrote %d record(s)%s.',
        count($files),
        count($records),
        $skipped ? " (skipped {$skipped} invalid file(s))" : ''
      ));
      WP_CLI::success('Manifest rebuilt at ' . $manifest_path);
    }

    protected function collect_bank_files(string $base_dir, string $source): array {
      $result = [];
      if (!is_dir($base_dir)) {
        return $result;
      }

      $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($base_dir, FilesystemIterator::SKIP_DOTS)
      );

      foreach ($iter as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) continue;
        if (strtolower($file->getExtension()) !== 'json') continue;
        if (strtolower($file->getBasename()) === 'mcq_manifest.json') continue;

        $full = wp_normalize_path($file->getPathname());
        $base = wp_normalize_path($base_dir);
        $rel  = ltrim(str_replace($base, '', $full), '/');

        $result[] = [
          'full'   => $full,
          'rel'    => $rel,
          'source' => $source,
        ];
      }

      return $result;
    }

    protected function build_record_from_bank(string $full_path, string $rel_path, string $source) {
      $raw = file_get_contents($full_path);
      $data = json_decode($raw, true);
      if (!is_array($data)) {
        WP_CLI::warning("Invalid JSON in {$rel_path} ({$source}); skipping.");
        return null;
      }

      $meta   = is_array($data['meta'] ?? null) ? $data['meta'] : [];
      $config = is_array($data['config'] ?? null) ? $data['config'] : [];

      $title = $this->first_non_empty([
        $data['title'] ?? null,
        $meta['title'] ?? null,
        $config['title'] ?? null,
      ]);

      $topic_key = $this->first_non_empty([
        $data['topic_key'] ?? null,
        $meta['topic_key'] ?? null,
        $config['topic_key'] ?? null,
        $data['topic'] ?? null,
        $meta['topic'] ?? null,
        $config['topic'] ?? null,
      ]);

      $subtopic_key = $this->first_non_empty([
        $data['subtopic_key'] ?? null,
        $meta['subtopic_key'] ?? null,
        $config['subtopic_key'] ?? null,
        $data['subtopic'] ?? null,
        $meta['subtopic'] ?? null,
        $config['subtopic'] ?? null,
      ]);

      $per = $this->first_non_empty([
        $data['per'] ?? null,
        $meta['per'] ?? null,
        $config['per'] ?? null,
        $data['per_play_default'] ?? null,
        $meta['per_play_default'] ?? null,
        $config['per_play_default'] ?? null,
      ], 10);

      $levels = $this->first_non_empty([
        $data['levels'] ?? null,
        $meta['levels'] ?? null,
        $config['levels'] ?? null,
        $data['level'] ?? null,
        $meta['level'] ?? null,
        $config['level'] ?? null,
      ], 3);

      $rel_parts = explode('/', $rel_path);
      if ($topic_key === '' && isset($rel_parts[0]) && $rel_parts[0] !== '') {
        $topic_key = $rel_parts[0];
      }
      if ($subtopic_key === '' && isset($rel_parts[1]) && $rel_parts[1] !== '') {
        $subtopic_key = $rel_parts[1];
      }

      $filename = pathinfo($rel_path, PATHINFO_FILENAME);
      if ($title === '' && function_exists('hmqz_humanize_key')) {
        $title = $this->humanize_filename($filename);
      } elseif ($title === '') {
        $title = ucwords(str_replace(['_', '-'], ' ', $filename));
      }

      $questions = 0;
      if (!empty($data['questions']) && is_array($data['questions'])) {
        $questions = count($data['questions']);
      } elseif (!empty($data['items']) && is_array($data['items'])) {
        $questions = count($data['items']);
      }

      $record = [
        'bank'          => str_replace('\\', '/', $rel_path),
        'title'         => $title,
        'topic_key'     => $topic_key,
        'subtopic_key'  => $subtopic_key,
        'topic'         => $topic_key,
        'subtopic'      => $subtopic_key,
        'per'           => (int) $per,
        'per_play_default' => (int) $per,
        'levels'        => (int) $levels,
      ];

      if ($questions > 0) {
        $record['questions'] = $questions;
      }

      return $record;
    }

    protected function humanize_filename(string $filename): string {
      $name = $filename;
      $name = preg_replace('/\\.[^.]+$/', '', $name);
      $name = preg_replace('/^(mcq[_-]?)/i', '', $name);
      $name = preg_replace('/^(confusables[_-]?)/i', '', $name);
      $name = str_replace(['_', '-'], ' ', $name);
      $name = trim(preg_replace('/\\s+/', ' ', $name));

      if (function_exists('hmqz_humanize_key')) {
        $pretty = hmqz_humanize_key($name);
        if ($pretty !== '') return $pretty;
      }

      return ucwords(strtolower($name));
    }

    protected function first_non_empty(array $candidates, $default = '') {
      foreach ($candidates as $val) {
        if (isset($val) && (string)$val !== '') {
          return $val;
        }
      }
      return $default;
    }
  }

  WP_CLI::add_command('hmqz banks', 'HMQZ_Banks_Command');
}
