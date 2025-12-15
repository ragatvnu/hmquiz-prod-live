<?php
if (!defined('ABSPATH')) exit;

// Ensure bank normalizer is available (safety for CLI / edge cases)
if (!function_exists('hmqz_normalize_bank_slug')) {
    $banks_file = dirname(__DIR__) . '/includes/banks.php';
    if (file_exists($banks_file)) {
        require_once $banks_file;
    }
}

/**
 * Render the /play/ page wrapper around the core [hmquiz] engine.
 *
 * Reads query params like:
 *   ?bank=..., &topics=..., &categories=..., &per=10, &level=1, &levels=3, &difficulty=Easy
 *
 * Then:
 *   - Normalizes the bank filename into the new folder structure
 *   - Wraps [hmquiz] output in a single clean card layout
 */
if (!function_exists('hmqz_render_play_shortcode')) {
    function hmqz_render_play_shortcode($atts = []) {

        // -----------------------------
        // 1) Read bank from URL / atts
        // -----------------------------
        $bank_raw = '';

        // Prefer query string (?bank=...)
        if (isset($_GET['bank'])) {
            $bank_raw = sanitize_text_field(wp_unslash($_GET['bank']));
        }

        // Fallback: bank from shortcode attribute
        if ($bank_raw === '' && isset($atts['bank'])) {
            $bank_raw = sanitize_text_field($atts['bank']);
        }

        // Resolve into normalized / sanitized relpath
        if (function_exists('hmqz_normalize_bank_slug')) {
            $bank = hmqz_normalize_bank_slug($bank_raw);
        } else {
            $bank = $bank_raw;
        }

        // Ensure inner [hmquiz] + JS see the normalized/sanitized bank value
        $atts['bank'] = $bank;

        if (!$bank) {
            return '<p>' . esc_html__('No quiz selected.', 'hmquiz') . '</p>';
        }

        // HMQUIZ: capture return/hub destinations for "Play another quiz".
        $return_url = '';
        if (isset($_GET['return'])) {
            $raw_return = (string) wp_unslash($_GET['return']);
            $raw_return = trim($raw_return);
            if ($raw_return !== '' && strpos($raw_return, '/') === 0 && strpos($raw_return, '://') === false) {
                $return_url = home_url($raw_return);
            }
        }

        $hub_url = '';
        if (!empty($bank) && function_exists('hmqz_get_hub_url_for_bank')) {
            $hub_url = hmqz_get_hub_url_for_bank($bank);
        }

        // -----------------------------
        // 2) Read meta from query string
        // -----------------------------
        $topic      = isset($_GET['topics'])
            ? sanitize_text_field(wp_unslash($_GET['topics']))
            : '';
        // (rest of your function stays as it is)

       // -----------------------------
        // 2) Read meta from query string
        // -----------------------------
        $topic      = isset($_GET['topics'])
            ? sanitize_text_field(wp_unslash($_GET['topics']))
            : '';
        $category   = isset($_GET['categories'])
            ? sanitize_text_field(wp_unslash($_GET['categories']))
            : '';
        $level_cur  = isset($_GET['level'])
            ? intval($_GET['level'])
            : 1;
        $level_max  = isset($_GET['levels'])
            ? intval($_GET['levels'])
            : 1;
        $difficulty = isset($_GET['difficulty'])
            ? sanitize_text_field(wp_unslash($_GET['difficulty']))
            : '';
        $total_q    = isset($_GET['per'])
            ? intval($_GET['per'])
            : 0;

        // -----------------------------
        // 3) Decide display title
        // -----------------------------
        // Prefer explicit title passed via query string.
        if (isset($_GET['title']) && $_GET['title'] !== '') {
            $title = sanitize_text_field(wp_unslash($_GET['title']));
        } elseif (isset($atts['title']) && $atts['title'] !== '') {
            $title = sanitize_text_field($atts['title']);
        } elseif ($category) {
            $title = $category;
        } elseif ($topic) {
            $title = $topic;
        } else {
            // Fallback: humanize the bank slug into a readable label.
            $title_slug = basename((string) $bank);
            $title_slug = preg_replace('/\.json$/', '', $title_slug);
            $title_slug = str_replace(['_', '-'], ' ', $title_slug);
            $title = ucwords(trim($title_slug));
        }

        $config = [
            'bank'       => $bank,
            'title'      => $title,
            'topics'     => $topic,
            'categories' => $category,
            'level'      => max(1, $level_cur),
            'levels'     => max(1, $level_max),
            'difficulty' => $difficulty,
            'per'        => $total_q,
        ];
        $config_json = wp_json_encode($config);
        if (!is_string($config_json)) {
            $config_json = '{}';
        }
        $hmqz_play_cfg = [
            'returnUrl' => $return_url,
            'hubUrl'    => $hub_url,
        ];
        $hmqz_play_cfg_json = wp_json_encode($hmqz_play_cfg);
        if (is_string($hmqz_play_cfg_json)) {
            wp_add_inline_script(
                'hmqz-app',
                'window.HMQZCFG = Object.assign(window.HMQZCFG || {}, ' . $hmqz_play_cfg_json . ');',
                'before'
            );
        }

        // -----------------------------
        // 4) Logo URL
        // -----------------------------
        if (defined('HMQZ_PLUGIN_URL')) {
            $logo_url = HMQZ_PLUGIN_URL . 'assets/img/hmquiz-logo.png';
        } else {
            // Fallback if constant is missing
            $logo_url = plugin_dir_url(dirname(__FILE__)) . 'assets/img/hmquiz-logo.png';
        }

        // -----------------------------
        // 5) Inner engine: core [hmquiz]
        // -----------------------------
        $inner = do_shortcode(
            '[hmquiz bank="' . esc_attr($bank) . '" title="' . esc_attr($title) . '"]'
        );

        // -----------------------------
        // 6) Render card layout
        // -----------------------------
        ob_start();
        ?>
        <div class="hmqz-play-shell">
          <div class="hmqz-play-card">
            <div class="hmqz-play-card-inner">

              <!-- HEADER: logo left, topic/title center, timer + Q meta right -->
              <header class="hmqz-play-header">
                <div class="hmqz-play-header-left">
                  <?php if (!empty($logo_url)) : ?>
                    <img
                      class="hmqz-play-logo"
                      src="<?php echo esc_url($logo_url); ?>"
                      alt="<?php esc_attr_e('HMQUIZ logo', 'hmquiz'); ?>"
                      loading="lazy"
                    />
                  <?php else : ?>
                    <div class="hmqz-play-logo-text">HMQUIZ</div>
                  <?php endif; ?>
                </div>

                <div class="hmqz-play-header-center">
                  <?php if ($topic) : ?>
                    <div class="hmqz-play-topic">
                      <?php echo esc_html($topic); ?>
                    </div>
                  <?php endif; ?>
                  <?php
                  // Prefer human-friendly title → category → bank for header display.
                  $hmqz_display_title = '';
                  if (!empty($config['title'])) {
                      $hmqz_display_title = $config['title'];
                  } elseif (!empty($config['categories'])) {
                      $hmqz_display_title = $config['categories'];
                  } elseif (!empty($config['bank'])) {
                      $hmqz_display_title = $config['bank'];
                  }
                  ?>
                  <div class="hmqz-play-title">
                    <?php echo esc_html($hmqz_display_title); ?>
                  </div>
                  <div class="hmqz-level-pill js-hmqz-level" aria-live="polite"></div>
                </div>

                <div class="hmqz-play-header-right">
                  <div class="hmqz-play-timer">
                    <span class="hmqz-play-timer-label">
                      <?php esc_html_e('Time', 'hmquiz'); ?>
                    </span>
                    <span class="hmqz-play-timer-value js-hmqz-timer">
                      00:00
                    </span>
                  </div>
                  <div class="hmqz-play-qmeta js-hmqz-qmeta">
                    <?php
                    if ($total_q > 0) {
                      printf(
                        /* translators: 1: current question number, 2: total questions */
                        esc_html__('Q %1$d/%2$d', 'hmquiz'),
                        1,
                        $total_q
                      );
                    }
                    ?>
                  </div>
                </div>
              </header>

              <!-- PROGRESS BAR -->
              <div class="hmqz-play-progress">
                <div class="hmqz-play-progress-bar">
                  <div
                    class="hmqz-play-progress-fill js-hmqz-progress"
                  ></div>
                </div>
              </div>

              <!-- BODY: existing [hmquiz] output lives here -->
              <div
                class="hmqz-play-body"
                id="hmqz-play-root"
                data-hmqz-config="<?php echo esc_attr($config_json); ?>"
              >
                <?php echo $inner; ?>
              </div>

              <!-- FOOTER -->
              <footer class="hmqz-play-footer">
                <div class="hmqz-play-footer-meta">
                  <span class="hmqz-level">
                    <?php
                    printf(
                      /* translators: 1: current level, 2: total levels */
                      esc_html__('Level %1$d/%2$d', 'hmquiz'),
                      max(1, $level_cur),
                      max(1, $level_max)
                    );
                    ?>
                  </span>
                  <?php if ($difficulty) : ?>
                    <span class="hmqz-pill hmqz-pill-difficulty">
                      <?php echo esc_html($difficulty); ?>
                    </span>
                  <?php endif; ?>
                </div>

                <button
                  type="button"
                  class="hmqz-btn-primary hmqz-btn-next js-hmqz-next"
                  disabled
                >
                  <span><?php esc_html_e('Next question', 'hmquiz'); ?></span>
                  <span class="hmqz-next-arrow">➜</span>
                </button>
              </footer>

            </div>
          </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
