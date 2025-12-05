<?php
// HMQUIZ Quiz Hub shortcode: [hmqz_hub]
// Renders quiz cards from mcq_manifest.json

if (!defined('ABSPATH')) exit;

/**
 * Get the full path to mcq_manifest.json
 */
function hmqz_manifest_path() {
    // Prefer the existing helper if available
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
 * Load manifest data as an array:
 * [ 'version' => ..., 'updated_at' => ..., 'banks' => [ ... ] ]
 */
function hmqz_load_manifest() {
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
 * Build a /play/ URL for a given bank entry from the manifest.
 */
function hmqz_build_play_url_from_bank(array $bank) {
    // You said: page ID 8 is /play/ with [hmqz_play]
    $play_page_id = 8;
    $base = get_permalink($play_page_id);

    if (!$base) {
        // Fallback if the ID ever changes but /play/ still exists.
        $base = home_url('/play/');
    }

    $args = array(
        'bank'       => isset($bank['bank']) ? $bank['bank'] : '',
        'topics'     => isset($bank['topic']) ? $bank['topic'] : '',
        'categories' => isset($bank['category']) ? $bank['category'] : '',
        'per'        => isset($bank['per_play_default']) ? (int) $bank['per_play_default'] : 10,
        'level'      => 1,
        'levels'     => 3,
        'title'      => isset($bank['title']) ? $bank['title'] : '',
    );

    // Allow future customisation
    $args = apply_filters('hmqz_hub_play_url_args', $args, $bank);

    return add_query_arg($args, $base);
}

/**
 * Main renderer for [hmqz_hub]
 */
function hmqz_render_hub_shortcode($atts = array(), $content = '') {
    $atts = shortcode_atts(array(
        // 'live' => only live banks
        // 'all'  => show everything (staging/debug)
        'status' => 'live',
    ), $atts, 'hmqz_hub');

    $manifest = hmqz_load_manifest();
    $banks = isset($manifest['banks']) && is_array($manifest['banks'])
        ? $manifest['banks']
        : array();

    if (empty($banks)) {
        ob_start();
        ?>
        <div class="hmqz-hub-wrapper">
            <p class="hmqz-hub-empty">
                No quizzes are available yet. Please check back soon.
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    $status_filter = strtolower($atts['status']);

    // Filter by status if not "all"
    if ($status_filter !== 'all') {
        $banks = array_filter($banks, function($b) {
            return !isset($b['status']) || $b['status'] === 'live';
        });
    }

    if (empty($banks)) {
        ob_start();
        ?>
        <div class="hmqz-hub-wrapper">
            <p class="hmqz-hub-empty">
                Quizzes are being prepared. Please check back soon.
            </p>
        </div>
        <?php
        return ob_get_clean();
    }

    // Sort banks by topic then difficulty then title
    usort($banks, function($a, $b) {
        $ta = isset($a['topic']) ? $a['topic'] : '';
        $tb = isset($b['topic']) ? $b['topic'] : '';

        $da = isset($a['difficulty']) ? $a['difficulty'] : '';
        $db = isset($b['difficulty']) ? $b['difficulty'] : '';

        $na = isset($a['title']) ? $a['title'] : '';
        $nb = isset($b['title']) ? $b['title'] : '';

        return [$ta, $da, $na] <=> [$tb, $db, $nb];
    });

    ob_start();
    ?>
    <div class="hmqz-hub-wrapper">
        <div class="hmqz-hub-grid">
            <?php foreach ($banks as $bank): ?>
                <?php
                $title      = isset($bank['title']) ? $bank['title']
                              : (isset($bank['category']) ? $bank['category'] : $bank['bank']);
                $topic      = isset($bank['topic']) ? $bank['topic'] : '';
                $category   = isset($bank['category']) ? $bank['category'] : '';
                $difficulty = isset($bank['difficulty']) ? strtolower($bank['difficulty']) : '';
                $questions  = isset($bank['questions']) ? (int) $bank['questions'] : 0;
                $status     = isset($bank['status']) ? $bank['status'] : 'live';
                $play_url   = hmqz_build_play_url_from_bank($bank);
                ?>
                <article class="hmqz-card hmqz-card-status-<?php echo esc_attr($status); ?>">
                    <header class="hmqz-card-header">
                        <?php if ($topic): ?>
                            <p class="hmqz-card-topic">
                                <?php echo esc_html($topic); ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($difficulty): ?>
                            <span class="hmqz-card-badge hmqz-badge-diff-<?php echo esc_attr($difficulty); ?>">
                                <?php echo esc_html(ucfirst($difficulty)); ?>
                            </span>
                        <?php endif; ?>
                    </header>

                    <h2 class="hmqz-card-title">
                        <?php echo esc_html($title); ?>
                    </h2>

                    <?php if ($category && $category !== $title): ?>
                        <p class="hmqz-card-subtitle">
                            <?php echo esc_html($category); ?>
                        </p>
                    <?php endif; ?>

                    <div class="hmqz-card-meta">
                        <?php if ($questions > 0): ?>
                            <span class="hmqz-card-meta-item">
                                <?php echo esc_html($questions); ?> questions
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="hmqz-card-actions">
                        <a class="hmqz-card-button" href="<?php echo esc_url($play_url); ?>">
                            Play Quiz
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return ob_get_clean();
}

add_shortcode('hmqz_hub', 'hmqz_render_hub_shortcode');


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
    if (strpos($content, 'hmqz-hub-wrapper') !== false) {
        return $content;
    }

    // Render the hub and append it below the existing content
    $hub = hmqz_render_hub_shortcode();

    return $content . "\n" . $hub;
}, 20);
