<?php
/**
 * Plugin Name: HMQUIZ – Confusing Words Hub
 * Description: Renders a Confusing Words quiz hub grid as cards.
 * Author: HMQUIZ
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue HMQUIZ hub theme CSS (uploads/hmquiz/hmqz-theme.css)
 * and add a fallback inline style in case the file is missing.
 */
function hmqz_confusing_words_hub_enqueue_styles() {

    // Path to uploads CSS
    $css_path = WP_CONTENT_DIR . '/uploads/hmquiz/hmqz-theme.css';
    $css_url  = content_url( 'uploads/hmquiz/hmqz-theme.css' );

    if ( file_exists( $css_path ) ) {
        // Version with filemtime for cache-busting
        wp_enqueue_style(
            'hmqz-theme',
            $css_url,
            array(),
            filemtime( $css_path )
        );
    } else {
        // Minimal fallback so the hub still looks like a grid
        $fallback_css = <<<CSS
.hmqz-hub {
  max-width: 1100px;
  margin: 0 auto 3rem auto;
  padding: 1rem 1.25rem 2rem;
}
.hmqz-hub-header {
  text-align: left;
  margin-bottom: 1.5rem;
}
.hmqz-hub-title {
  font-size: 2rem;
  line-height: 1.2;
  margin: 0 0 0.4rem;
}
.hmqz-hub-subtitle {
  font-size: 0.98rem;
  color: #555;
  margin: 0;
}
.hmqz-hub-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 1.25rem;
}
.hmqz-hub-card {
  background: #ffffff;
  border-radius: 12px;
  padding: 1rem 1.1rem 1rem;
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(0, 0, 0, 0.04);
  display: flex;
  flex-direction: column;
  transition: transform 0.15s ease-out, box-shadow 0.15s ease-out, border-color 0.15s ease-out;
}
.hmqz-hub-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 14px 30px rgba(0, 0, 0, 0.07);
  border-color: rgba(0, 166, 251, 0.4);
}
.hmqz-hub-card-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.4rem;
}
.hmqz-hub-pill {
  background: rgba(0, 166, 251, 0.08);
  border-radius: 999px;
  padding: 0.15rem 0.65rem;
  display: inline-flex;
  align-items: center;
}
.hmqz-hub-pill-label {
  font-size: 0.75rem;
  font-weight: 600;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  color: #00a6fb;
}
.hmqz-hub-meta {
  display: flex;
  gap: 0.5rem;
  align-items: center;
  font-size: 0.78rem;
}
.hmqz-hub-level {
  padding: 0.05rem 0.4rem;
  border-radius: 999px;
  background: rgba(29, 53, 87, 0.05);
  color: #1d3557;
}
.hmqz-hub-rating {
  padding: 0.05rem 0.4rem;
  border-radius: 999px;
  background: rgba(255, 183, 3, 0.1);
  color: #b46a00;
  font-weight: 600;
}
.hmqz-hub-card-title {
  font-size: 1.2rem;
  margin: 0.3rem 0 0.25rem;
}
.hmqz-hub-card-tagline {
  font-size: 0.92rem;
  color: #444;
  margin: 0 0 0.7rem;
}
.hmqz-hub-card-footer {
  margin-top: auto;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.75rem;
}
.hmqz-hub-questions {
  font-size: 0.82rem;
  color: #666;
}
.hmqz-hub-actions {
  display: flex;
  gap: 0.4rem;
}
.hmqz-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: 999px;
  padding: 0.35rem 0.8rem;
  font-size: 0.82rem;
  font-weight: 600;
  text-decoration: none;
  border: 1px solid transparent;
  white-space: nowrap;
}
.hmqz-btn-primary {
  background: #00a6fb;
  color: #ffffff;
  border-color: #00a6fb;
}
.hmqz-btn-primary:hover {
  background: #0181c1;
  border-color: #0181c1;
  color: #ffffff;
}
.hmqz-btn-ghost {
  background: #ffffff;
  color: #1d3557;
  border-color: rgba(29, 53, 87, 0.18);
}
.hmqz-btn-ghost:hover {
  background: rgba(29, 53, 87, 0.04);
  border-color: rgba(29, 53, 87, 0.4);
}
@media (max-width: 600px) {
  .hmqz-hub {
    padding: 0.75rem 0.5rem 2rem;
  }
  .hmqz-hub-title {
    font-size: 1.6rem;
  }
  .hmqz-hub-card {
    padding: 0.9rem 0.9rem 0.95rem;
  }
  .hmqz-hub-card-footer {
    flex-direction: column;
    align-items: flex-start;
  }
  .hmqz-hub-actions {
    width: 100%;
    justify-content: flex-start;
    flex-wrap: wrap;
  }
}
CSS;

        wp_register_style( 'hmqz-theme-inline', false );
        wp_enqueue_style( 'hmqz-theme-inline' );
        wp_add_inline_style( 'hmqz-theme-inline', $fallback_css );
    }
}
add_action( 'wp_enqueue_scripts', 'hmqz_confusing_words_hub_enqueue_styles' );

/**
 * Confusing Words Hub shortcode.
 *
 * Usage: [hmqz_confusing_words_hub]
 */
function hmqz_confusing_words_hub_shortcode( $atts ) {

    $cards = array(
        array(
            'slug'       => 'affect-vs-effect',
            'topic'      => 'Confusing Words',
            'title'      => 'Affect vs Effect',
            'tagline'    => 'Master the difference so your writing always sounds smart.',
            'level'      => 'Level 1 · Easy',
            'rating'     => '4.8',
            'questions'  => '20 questions',
            'guide_url'  => home_url( '/quiz/affect-vs-effect/' ),
            'play_url'   => home_url( 
'/play/?bank=mcq_confusables_affect_vs_effect.json&topics=English%20Grammar&categories=Affect%20vs%20Effect&title=Affect%20vs%20Effect%20Quiz&per=10&level=1&levels=3&return=%2Fquiz%2Faffect-vs-effect%2F' 
),
        ),
        array(
            'slug'       => 'its-vs-its',
            'topic'      => 'Confusing Words',
            'title'      => "Its vs It's",
            'tagline'    => 'Fix the most common apostrophe mistake in English.',
            'level'      => 'Level 1 · Easy',
            'rating'     => '4.7',
            'questions'  => '20 questions',
            'guide_url'  => home_url( '/quiz/its-vs-its/' ),
            'play_url'   => home_url( 
'/play/?bank=mcq_confusables_its_vs_its.json&topics=English%20Grammar&categories=Its%20vs%20It%27s&title=Its%20vs%20It%27s%20Quiz&per=10&level=1&levels=3&return=%2Fquiz%2Fits-vs-its%2F' 
),
        ),
        array(
            'slug'       => 'then-vs-than',
            'topic'      => 'Confusing Words',
            'title'      => 'Then vs Than',
            'tagline'    => 'Never mix them again in comparisons and sequences.',
            'level'      => 'Level 1 · Easy',
            'rating'     => '4.6',
            'questions'  => '20 questions',
            'guide_url'  => home_url( '/quiz/then-vs-than/' ),
            'play_url'   => home_url( 
'/play/?bank=mcq_confusables_then_vs_than.json&topics=English%20Grammar&categories=Then%20vs%20Than&title=Then%20vs%20Than%20Quiz&per=10&level=1&levels=3&return=%2Fquiz%2Fthen-vs-than%2F' 
),
        ),
        array(
            'slug'       => 'who-vs-whom',
            'topic'      => 'Confusing Words',
            'title'      => 'Who vs Whom',
            'tagline'    => 'Sound formal and correct in exams and emails.',
            'level'      => 'Level 2 · Medium',
            'rating'     => '4.5',
            'questions'  => '20 questions',
            'guide_url'  => home_url( '/quiz/who-vs-whom/' ),
            'play_url'   => home_url( 
'/play/?bank=mcq_confusables_who_vs_whom.json&topics=English%20Grammar&categories=Who%20vs%20Whom&title=Who%20vs%20Whom%20Quiz&per=10&level=1&levels=3&return=%2Fquiz%2Fwho-vs-whom%2F' 
),
        ),
        array(
            'slug'       => 'lose-vs-loose',
            'topic'      => 'Confusing Words',
            'title'      => 'Lose vs Loose',
            'tagline'    => 'Stop losing marks over this loose usage.',
            'level'      => 'Level 1 · Easy',
            'rating'     => '4.6',
            'questions'  => '20 questions',
            'guide_url'  => home_url( '/quiz/lose-vs-loose/' ),
            'play_url'   => home_url( 
'/play/?bank=mcq_confusables_lose_vs_loose.json&topics=English%20Grammar&categories=Lose%20vs%20Loose&title=Lose%20vs%20Loose%20Quiz&per=10&level=1&levels=3&return=%2Fquiz%2Flose-vs-loose%2F' 
),
        ),
    );

    ob_start();
    ?>
    <section class="hmqz-hub hmqz-hub-confusing-words" aria-label="Confusing Words Quiz Hub">
        <header class="hmqz-hub-header">
            <h2 class="hmqz-hub-title">Confusing Words Hub</h2>
            <p class="hmqz-hub-subtitle">
                Fix the most common confusing words in English with quick study guides and bite-sized quizzes.
            </p>
        </header>

        <div class="hmqz-hub-grid">
            <?php foreach ( $cards as $card ) : ?>
                <article class="hmqz-hub-card hmqz-hub-card--<?php echo esc_attr( $card['slug'] ); ?>">
                    <div class="hmqz-hub-card-top">
                        <div class="hmqz-hub-pill">
                            <span class="hmqz-hub-pill-label">
                                <?php echo esc_html( $card['topic'] ); ?>
                            </span>
                        </div>

                        <div class="hmqz-hub-meta">
                            <span class="hmqz-hub-level">
                                <?php echo esc_html( $card['level'] ); ?>
                            </span>
                            <span class="hmqz-hub-rating" aria-label="Average rating <?php echo esc_attr( $card['rating'] ); ?> out of 5">
                                ★ <?php echo esc_html( $card['rating'] ); ?>
                            </span>
                        </div>
                    </div>

                    <h3 class="hmqz-hub-card-title">
                        <?php echo esc_html( $card['title'] ); ?>
                    </h3>

                    <p class="hmqz-hub-card-tagline">
                        <?php echo esc_html( $card['tagline'] ); ?>
                    </p>

                    <div class="hmqz-hub-card-footer">
                        <span class="hmqz-hub-questions">
                            <?php echo esc_html( $card['questions'] ); ?>
                        </span>

                        <div class="hmqz-hub-actions">
                            <a class="hmqz-btn hmqz-btn-ghost" href="<?php echo esc_url( $card['guide_url'] ); ?>">
                                Read guide
                            </a>
                            <a class="hmqz-btn hmqz-btn-primary" href="<?php echo esc_url( $card['play_url'] ); ?>">
                                Play quiz
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php

    return ob_get_clean();
}
add_shortcode( 'hmqz_confusing_words_hub', 'hmqz_confusing_words_hub_shortcode' );

/**
 * Safety net: ensure shortcode is executed on page content.
 */
function hmqz_confusing_words_hub_force_do_shortcode( $content ) {
    if ( false !== strpos( $content, '[hmqz_confusing_words_hub' ) ) {
        $content = do_shortcode( $content );
    }
    return $content;
}
add_filter( 'the_content', 'hmqz_confusing_words_hub_force_do_shortcode', 11 );

