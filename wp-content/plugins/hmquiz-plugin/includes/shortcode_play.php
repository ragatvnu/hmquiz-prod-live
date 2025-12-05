<?php
if (!defined('ABSPATH')) exit;

// Ensure bank normalizer is available (safety for CLI / edge cases)
if (!function_exists('hmqz_normalize_bank_rel')) {
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
        if (function_exists('hmqz_resolve_bank_rel')) {
            $bank = hmqz_resolve_bank_rel($bank_raw);
        } else {
            $bank = $bank_raw;
        }

        // Ensure inner [hmquiz] + JS see the normalized/sanitized bank value
        $atts['bank'] = $bank;

        if (!$bank) {
            return '<p>' . esc_html__('No quiz selected.', 'hmquiz') . '</p>';
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
        if (isset($atts['title']) && $atts['title'] !== '') {
            $title = sanitize_text_field($atts['title']);
        } elseif ($category) {
            $title = $category;
        } elseif ($topic) {
            $title = $topic;
        } else {
            $title = $bank;
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
        <div class="hmqz-play-page-inner">
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
                  <div class="hmqz-play-title">
                    <?php echo esc_html($title); ?>
                  </div>
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
              <div class="hmqz-play-body">
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

