<?php
// HMQUIZ Quiz Hub shortcode: [hmqz_hub]
// Uses the hubs registry + banks manifest to render hub pages.

if (!defined('ABSPATH')) exit;

/**
 * Get the full path to mcq_manifest.json
 */
function hmqz_manifest_path() {
    if (function_exists('hmqz_get_bank_manifest_path')) {
        return hmqz_get_bank_manifest_path();
    }

    // Fallback to legacy path resolver.
    if (function_exists('hmqz_bank_dir')) {
        return trailingslashit(hmqz_bank_dir()) . 'mcq_manifest.json';
    }

    $dir = WP_CONTENT_DIR . '/uploads/hmquiz/banks/';
    if (!is_dir($dir)) {
        wp_mkdir_p($dir);
    }
    return $dir . 'mcq_manifest.json';
}

/**
 * Load manifest data as an array:
 * [ 'version' => ..., 'updated_at' => ..., 'banks' => [ ... ] ]
 */
function hmqz_load_manifest() {
    if (function_exists('hmqz_get_banks_index')) {
        return hmqz_get_banks_index();
    }

    // Legacy fallback: direct read.
    $path = hmqz_manifest_path();
    if (!file_exists($path)) {
        return array('banks' => array());
    }

    $json = file_get_contents($path);
    if ($json === false) {
        return array('banks' => array());
    }

    $data = json_decode($json, true);
    if (!is_array($data) || !isset($data['banks']) || !is_array($data['banks'])) {
        return array('banks' => array());
    }

    return $data;
}

/**
 * Build a /play/ URL for a given bank entry from the manifest/index.
 */
function hmqz_build_play_url_from_bank(array $bank) {
    $base = home_url('/play/');

    $args = array(
        'bank' => isset($bank['bank']) ? $bank['bank'] : '',
    );

    if (!empty($bank['title'])) {
        $args['title'] = $bank['title'];
    }
    if (!empty($bank['per_play_default'])) {
        $args['per'] = (int) $bank['per_play_default'];
    }
    if (!empty($bank['level'])) {
        $args['level'] = (int) $bank['level'];
    }
    if (!empty($bank['levels'])) {
        $args['levels'] = (int) $bank['levels'];
    }
    if (!empty($bank['topics'])) {
        $args['topics'] = $bank['topics'];
    } elseif (!empty($bank['topic'])) {
        $args['topics'] = $bank['topic'];
    }
    if (!empty($bank['categories'])) {
        $args['categories'] = $bank['categories'];
    } elseif (!empty($bank['category'])) {
        $args['categories'] = $bank['category'];
    }

    // Allow future customisation
    $args = apply_filters('hmqz_hub_play_url_args', $args, $bank);

    return add_query_arg($args, $base);
}

/**
 * Main renderer for [hmqz_hub]
 *
 * Usage: [hmqz_hub topic=\"english_grammar\" subtopic=\"confusing_words\"]
 */
function hmqz_render_hub_shortcode($atts = array(), $content = '') {
    $atts = shortcode_atts(array(
        'topic'    => '',
        'subtopic' => '',
        'mode'     => 'auto',
    ), $atts, 'hmqz_hub');

    $topic_key    = trim((string) $atts['topic']);
    $subtopic_key = trim((string) $atts['subtopic']);
    $mode         = strtolower(trim((string) $atts['mode']));
    if (!in_array($mode, array('auto', 'topic', 'subtopic'), true)) {
        $mode = 'auto';
    }
    $render_subtopic = ($subtopic_key !== '' && $mode !== 'topic');

    $humanize = function($key) {
        if (function_exists('hmqz_humanize_key')) {
            $label = hmqz_humanize_key((string) $key);
            if ($label !== '') {
                return $label;
            }
        }
        $key = trim((string) $key);
        if ($key === '') return '';
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        return ucwords(strtolower($key));
    };

    $registry = function_exists('hmqz_get_hubs_registry') ? hmqz_get_hubs_registry() : array();
    $hub      = null;
    $topic_cfg = null;

    if ($topic_key !== '' && isset($registry[$topic_key])) {
        $topic_cfg = $registry[$topic_key];
        if ($render_subtopic && !empty($topic_cfg['subtopics'][$subtopic_key])) {
            $hub = $topic_cfg['subtopics'][$subtopic_key];
            $hub['_topic_key'] = $topic_key;
            $hub['_subtopic_key'] = $subtopic_key;
        } else {
            $hub = $topic_cfg;
        }
    } elseif ($topic_key !== '' && $render_subtopic && function_exists('hmqz_get_hub_by_topic_and_subtopic')) {
        $hub = hmqz_get_hub_by_topic_and_subtopic($topic_key, $subtopic_key);
    }

    if (!$hub && $topic_key !== '') {
        $error = sprintf(
            'Hub not found for topic \"%s\"%s.',
            $topic_key,
            ($render_subtopic && $subtopic_key ? ' / subtopic \"' . $subtopic_key . '\"' : '')
        );
        return '<div class=\"hmqz-hub-error\">' . esc_html($error) . '</div>';
    }

    if (!$hub) {
        $hub = array(
            'title'       => __('Quiz Hub', 'hmquiz'),
            'description' => '',
        );
    }

    $banks = array();
    if ($topic_key !== '' && $render_subtopic && function_exists('hmqz_get_banks_for_topic_and_subtopic')) {
        $banks = hmqz_get_banks_for_topic_and_subtopic($topic_key, $subtopic_key);
    } elseif ($topic_key !== '' && function_exists('hmqz_get_banks_for_topic')) {
        $banks = hmqz_get_banks_for_topic($topic_key);
    } elseif (function_exists('hmqz_get_banks_index')) {
        $idx   = hmqz_get_banks_index();
        $banks = isset($idx['banks']) && is_array($idx['banks']) ? $idx['banks'] : array();
    }

    if (!is_array($banks)) {
        $banks = array();
    }

    usort($banks, function($a, $b) {
        $ta = isset($a['topic']) ? $a['topic'] : '';
        $tb = isset($b['topic']) ? $b['topic'] : '';
        $na = isset($a['title']) ? $a['title'] : (isset($a['bank']) ? $a['bank'] : '');
        $nb = isset($b['title']) ? $b['title'] : (isset($b['bank']) ? $b['bank'] : '');
        return array($ta, $na) <=> array($tb, $nb);
    });

    $topic_label = '';
    if ($topic_key !== '') {
        $topic_label = $humanize($topic_key);
        if ($topic_label === '' && $topic_cfg && !empty($topic_cfg['title'])) {
            $topic_label = trim((string) $topic_cfg['title']);
        }
    }

    $subtopic_label = '';
    if ($render_subtopic && $subtopic_key !== '') {
        $subtopic_label = $humanize($subtopic_key);
        if ($subtopic_label === '' && !empty($hub['title'])) {
            $subtopic_label = trim((string) $hub['title']);
        }
    }

    $hub_title = '';
    if (!empty($hub['title'])) {
        $hub_title = $hub['title'];
    } elseif ($subtopic_label !== '') {
        $hub_title = $subtopic_label;
    } elseif ($topic_label !== '') {
        $hub_title = $topic_label;
    } else {
        $hub_title = __('Quiz Hub', 'hmquiz');
    }

    $breadcrumb_parts = array();
    if ($topic_label !== '') {
        $breadcrumb_parts[] = $topic_label;
    }
    if ($subtopic_label !== '') {
        $breadcrumb_parts[] = $subtopic_label;
    }

    $hub_description = !empty($hub['description']) ? $hub['description'] : '';

    $hub_mode = 'general';
    if ($topic_key !== '' && !$render_subtopic) {
        $hub_mode = 'topic';
    } elseif ($render_subtopic && $subtopic_key !== '') {
        $hub_mode = 'subtopic';
    }

    $wrapper_classes = 'hmqz-hub hmqz-hub--' . $hub_mode;

    $grouped_banks = array();
    if ($topic_key !== '' && !$render_subtopic) {
        foreach ($banks as $bank) {
            if (!is_array($bank)) continue;
            $keys = array('topic' => '', 'subtopic' => '');
            if (function_exists('hmqz_bank_topic_keys')) {
                $keys = hmqz_bank_topic_keys($bank);
            } elseif (!empty($bank['bank'])) {
                $rel = ltrim((string)$bank['bank'], '/');
                $parts = explode('/', $rel);
                $keys['topic'] = isset($parts[0]) ? $parts[0] : '';
                $keys['subtopic'] = isset($parts[1]) ? $parts[1] : '';
            }
            $sub = $keys['subtopic'] ?: 'misc';
            if (!isset($grouped_banks[$sub])) {
                $grouped_banks[$sub] = array();
            }
            $grouped_banks[$sub][] = $bank;
        }
    }

    $ordered_groups = array();
    if ($topic_key !== '' && !$render_subtopic && $topic_cfg && !empty($topic_cfg['subtopics']) && is_array($topic_cfg['subtopics'])) {
        $sorted_subtopics = $topic_cfg['subtopics'];
        uasort($sorted_subtopics, function($a, $b) {
            $oa = isset($a['order']) ? (int) $a['order'] : 50;
            $ob = isset($b['order']) ? (int) $b['order'] : 50;
            return $oa <=> $ob;
        });
        foreach ($sorted_subtopics as $sub_key => $sub_cfg) {
            if (!empty($grouped_banks[$sub_key])) {
                $ordered_groups[$sub_key] = $grouped_banks[$sub_key];
                unset($grouped_banks[$sub_key]);
            }
        }
    }
    foreach ($grouped_banks as $sub_key => $list) {
        $ordered_groups[$sub_key] = $list;
    }

    $render_card = function($bank) use ($humanize) {
        if (!is_array($bank)) return '';
        $bank_id = isset($bank['bank']) ? (string) $bank['bank'] : '';
        if ($bank_id === '') return '';
        $bank_title = (isset($bank['title']) && $bank['title'] !== '') ? $bank['title'] : $bank_id;
        $meta_bits = array();
        if (!empty($bank['difficulty'])) {
            $meta_bits[] = ucfirst(strtolower((string) $bank['difficulty']));
        }
        if (!empty($bank['questions'])) {
            $meta_bits[] = intval($bank['questions']) . ' ' . __('questions', 'hmquiz');
        }
        if (!empty($bank['category'])) {
            $meta_bits[] = $bank['category'];
        }
        $topic_label = '';
        if (!empty($bank['topic'])) {
            $topic_label = $humanize($bank['topic']);
        }
        $play_url = hmqz_build_play_url_from_bank($bank);
        ob_start();
        ?>
        <article class="hmqz-quiz-card">
          <h3 class="hmqz-quiz-title hmqz-quiz-card-title"><?php echo esc_html($bank_title); ?></h3>
          <?php if ($topic_label): ?>
            <p class="hmqz-quiz-meta"><?php echo esc_html($topic_label); ?></p>
          <?php endif; ?>
          <?php if (!empty($meta_bits)): ?>
            <p class="hmqz-quiz-meta"><?php echo esc_html(implode(' • ', $meta_bits)); ?></p>
          <?php endif; ?>
          <a class="hmqz-quiz-cta" href="<?php echo esc_url($play_url); ?>">
            <?php esc_html_e('Play this quiz', 'hmquiz'); ?>
          </a>
        </article>
        <?php
        return ob_get_clean();
    };

    ob_start();
    ?>
    <div class="<?php echo esc_attr($wrapper_classes); ?>">
      <header class="hmqz-hub-header">
        <?php if ($breadcrumb_parts): ?>
          <p class="hmqz-hub-breadcrumb"><?php echo esc_html(implode(' > ', $breadcrumb_parts)); ?></p>
        <?php endif; ?>
        <?php if ($hub_title !== ''): ?>
          <h1 class="hmqz-hub-title"><?php echo esc_html($hub_title); ?></h1>
        <?php endif; ?>
        <?php if ($hub_description !== ''): ?>
          <p class="hmqz-hub-description"><?php echo esc_html($hub_description); ?></p>
        <?php endif; ?>
      </header>

      <section class="hmqz-hub-quizzes">
        <?php if (empty($banks)): ?>
          <p class="hmqz-hub-empty"><?php esc_html_e('No quizzes found for this hub yet.', 'hmquiz'); ?></p>
        <?php elseif ($topic_key !== '' && !$render_subtopic): ?>
          <?php if (empty($ordered_groups)): ?>
            <p class="hmqz-hub-empty"><?php esc_html_e('No quizzes found for this topic yet.', 'hmquiz'); ?></p>
          <?php else: ?>
            <?php foreach ($ordered_groups as $sub_key => $list): ?>
              <?php
              $sub_cfg = ($topic_cfg && !empty($topic_cfg['subtopics'][$sub_key])) ? $topic_cfg['subtopics'][$sub_key] : array();
              $group_title = !empty($sub_cfg['title']) ? $sub_cfg['title'] : ($sub_key === 'misc' ? __('Other Quizzes', 'hmquiz') : $humanize($sub_key));
              $group_desc  = !empty($sub_cfg['description']) ? $sub_cfg['description'] : '';
              ?>
              <section class="hmqz-hub-subtopic">
                <div class="hmqz-hub-subtopic-header">
                  <h2 class="hmqz-hub-subtopic-title"><?php echo esc_html($group_title); ?></h2>
                  <?php if ($group_desc): ?>
                    <p class="hmqz-hub-subtopic-description"><?php echo esc_html($group_desc); ?></p>
                  <?php endif; ?>
                </div>
                <div class="hmqz-hub-grid">
                  <?php foreach ($list as $bank): ?>
                    <?php echo $render_card($bank); ?>
                  <?php endforeach; ?>
                </div>
              </section>
            <?php endforeach; ?>
          <?php endif; ?>
        <?php else: ?>
          <div class="hmqz-hub-grid">
            <?php foreach ($banks as $bank): ?>
              <?php echo $render_card($bank); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
    <?php

    return ob_get_clean();
}

/**
 * Global hubs index shortcode: [hmqz_hubs_index]
 *
 * Attributes:
 * - levels: all|topics|subtopics (default: all)
 */
function hmqz_render_hubs_index_shortcode($atts = array(), $content = '') {
    $atts = shortcode_atts(array(
        'levels' => 'all',
        'title' => '',
        'description' => '',
    ), $atts, 'hmqz_hubs_index');

    $level = strtolower(trim((string) $atts['levels']));
    $show_topics = true;
    $show_subtopics = true;
    if (in_array($level, array('topic', 'topics'), true)) {
        $show_subtopics = false;
    } elseif (in_array($level, array('subtopic', 'subtopics'), true)) {
        $show_topics = false;
    }

    $humanize = function($key) {
        if (function_exists('hmqz_humanize_key')) {
            $label = hmqz_humanize_key((string) $key);
            if ($label !== '') {
                return $label;
            }
        }
        $key = trim((string) $key);
        if ($key === '') return '';
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/\s+/', ' ', $key);
        return ucwords(strtolower($key));
    };

    $index_title = trim((string) $atts['title']);
    if ($index_title === '') {
        $index_title = __('Browse Quiz Hubs', 'hmquiz');
    }

    $index_description = trim((string) $atts['description']);
    if ($index_description === '' && trim((string) $content) !== '') {
        $index_description = trim(wp_strip_all_tags((string) $content));
    }

    $items = array();
    if ($show_topics && function_exists('hmqz_get_topic_hubs')) {
        $items = array_merge($items, hmqz_get_topic_hubs());
    }
    if ($show_subtopics && function_exists('hmqz_get_subtopic_hubs')) {
        $items = array_merge($items, hmqz_get_subtopic_hubs());
    }

    // Final sort across all items: order then title.
    usort($items, function($a, $b) {
        $a_primary = isset($a['topic_order']) ? (int) $a['topic_order'] : (int) ($a['order'] ?? 50);
        $b_primary = isset($b['topic_order']) ? (int) $b['topic_order'] : (int) ($b['order'] ?? 50);

        $a_secondary = (int) ($a['order'] ?? 50);
        $b_secondary = (int) ($b['order'] ?? 50);

        return array($a_primary, $a_secondary, $a['title'] ?? '') <=> array($b_primary, $b_secondary, $b['title'] ?? '');
    });

    $render_card = function($hub) use ($humanize) {
        if (!is_array($hub)) return '';

        $type = $hub['type'] ?? '';
        $title = !empty($hub['title']) ? (string) $hub['title'] : '';
        if ($title === '' && $type === 'topic' && !empty($hub['topic_key'])) {
            $title = $humanize($hub['topic_key']);
        } elseif ($title === '' && $type === 'subtopic' && !empty($hub['subtopic_key'])) {
            $title = $humanize($hub['subtopic_key']);
        }

        $description = isset($hub['description']) ? (string) $hub['description'] : '';

        $url = '';
        $path = isset($hub['path']) ? (string) $hub['path'] : '';
        if ($type === 'subtopic' && !empty($hub['topic_key']) && !empty($hub['subtopic_key']) && function_exists('hmqz_get_hub_url_for_topic_and_subtopic')) {
            $url = hmqz_get_hub_url_for_topic_and_subtopic($hub['topic_key'], $hub['subtopic_key']);
        }
        if ($url === '' && $path !== '') {
            if (strpos($path, '://') !== false) {
                $url = $path;
            } else {
                $url = home_url($path);
            }
        }
        if ($url === '') {
            return '';
        }

        $meta = '';
        if ($type === 'topic') {
            $meta = __('Topic Hub', 'hmquiz');
        } elseif ($type === 'subtopic') {
            $topic_label = '';
            if (!empty($hub['topic_title'])) {
                $topic_label = (string) $hub['topic_title'];
            } elseif (!empty($hub['topic_key'])) {
                $topic_label = $humanize($hub['topic_key']);
            }
            if ($topic_label !== '') {
                $meta = sprintf(
                    /* translators: %s is the topic label */
                    __('Subtopic of %s', 'hmquiz'),
                    $topic_label
                );
            } else {
                $meta = __('Subtopic Hub', 'hmquiz');
            }
        }

        $card_type_class = $type ? ' hmqz-hub-card--' . $type : '';

        ob_start();
        ?>
        <article class="hmqz-quiz-card hmqz-hub-card<?php echo esc_attr($card_type_class); ?>">
          <h2 class="hmqz-hub-card-title hmqz-quiz-title">
            <a href="<?php echo esc_url($url); ?>"><?php echo esc_html($title); ?></a>
          </h2>
          <?php if ($meta !== ''): ?>
            <p class="hmqz-quiz-meta hmqz-hub-card-meta"><?php echo esc_html($meta); ?></p>
          <?php endif; ?>
          <?php if ($description !== ''): ?>
            <p class="hmqz-hub-card-description hmqz-quiz-description"><?php echo esc_html($description); ?></p>
          <?php endif; ?>
          <a class="hmqz-hub-card-cta hmqz-quiz-cta" href="<?php echo esc_url($url); ?>">
            <?php esc_html_e('View hub', 'hmquiz'); ?>
          </a>
        </article>
        <?php
        return ob_get_clean();
    };

    ob_start();
    ?>
    <div class="hmqz-hubs-index hmqz-hubs-index--<?php echo esc_attr($level ?: 'all'); ?>">
      <header class="hmqz-hubs-index-header">
        <?php if ($index_title !== ''): ?>
          <h1 class="hmqz-hubs-index-title"><?php echo esc_html($index_title); ?></h1>
        <?php endif; ?>
        <?php if ($index_description !== ''): ?>
          <p class="hmqz-hubs-index-description"><?php echo esc_html($index_description); ?></p>
        <?php endif; ?>
      </header>

      <div class="hmqz-hubs-index-body">
        <?php if (empty($items)): ?>
          <p class="hmqz-hub-empty"><?php esc_html_e('No quiz hubs found yet.', 'hmquiz'); ?></p>
        <?php else: ?>
          <div class="hmqz-hubs-index-grid hmqz-hub-grid">
            <?php foreach ($items as $hub): ?>
              <?php echo $render_card($hub); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('hmqz_hub', 'hmqz_render_hub_shortcode');
add_shortcode('hmqz_hubs_index', 'hmqz_render_hubs_index_shortcode');


// Auto-inject the Quiz Hub on the /quiz/ page (ID 19).
add_filter('the_content', function($content) {
    // Don't affect admin/editor screens
    if (is_admin()) {
        return $content;
    }

    // Only affect the Quiz page (ID 19)
    if (!is_page(19)) {
        return $content;
    }

    // If the hub is already present, avoid double-injecting
    if (strpos($content, 'hmqz-hub-wrapper') !== false || strpos($content, 'hmqz-hub') !== false) {
        return $content;
    }

    // Render the hub and append it below the existing content
    $hub = hmqz_render_hub_shortcode();

    return $content . "\n" . $hub;
}, 20);
