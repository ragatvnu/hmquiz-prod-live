<?php
/**
 * Plugin Name: HMQUIZ – Quiz Rating
 * Description: Simple 1–5 star rating widget + title badge for quiz pages.
 * Version: 0.1.3
 * Author: HMQUIZ
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper: does this page look like a quiz page?
 */
function hmqz_rating_is_quiz_page( $post ) {
    if ( ! $post || $post->post_type !== 'page' ) {
        return false;
    }

    $slug    = isset( $post->post_name ) ? $post->post_name : '';
    $content = isset( $post->post_content ) ? $post->post_content : '';

    // Most quiz pages have "-quiz-" in slug.
    if ( strpos( $slug, '-quiz-' ) !== false ) {
        return true;
    }

    // Fallback: content contains [hmqz_play].
    if ( strpos( $content, '[hmqz_play' ) !== false ) {
        return true;
    }

    return false;
}

/**
 * Enqueue rating script on quiz pages only.
 */
function hmqz_rating_enqueue_script() {
    if ( ! is_singular( 'page' ) ) {
        return;
    }

    global $post;
    if ( ! hmqz_rating_is_quiz_page( $post ) ) {
        return;
    }

    $src = plugins_url( 'hmqz-rating.js', __FILE__ );
    wp_enqueue_script( 'hmqz-rating', $src, array( 'jquery' ), '0.1.3', true );

    wp_localize_script(
        'hmqz-rating',
        'hmqzRating',
        array(
            'restUrl' => esc_url_raw( rest_url( 'hmqz/v1/rate' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'postId'  => $post->ID,
            'strings' => array(
                'thanks' => 'Thanks for rating!',
                'rated'  => 'You rated',
                'outOf'  => 'out of 5.',
            ),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'hmqz_rating_enqueue_script' );

/**
 * PREPEND rating widget markup to quiz pages (above quiz).
 */
function hmqz_rating_append_widget( $content ) {
    if ( ! is_singular( 'page' ) ) {
        return $content;
    }

    global $post;
    if ( ! hmqz_rating_is_quiz_page( $post ) ) {
        return $content;
    }

    $total = (int) get_post_meta( $post->ID, 'hmqz_rating_total', true );
    $count = (int) get_post_meta( $post->ID, 'hmqz_rating_count', true );
    $avg   = $count > 0 ? round( $total / $count, 1 ) : 0;

    ob_start();
    ?>
    <section class="hmqz-rating" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
        <div class="hmqz-rating-label">Rate this quiz</div>
        <div class="hmqz-rating-stars"
             data-current-rating="<?php echo esc_attr( $avg ); ?>"
             data-votes="<?php echo esc_attr( $count ); ?>">
        </div>
        <div class="hmqz-rating-summary">
            <?php if ( $count > 0 ) : ?>
                <?php echo esc_html( $avg . ' / 5 (' . $count . ' votes)' ); ?>
            <?php else : ?>
                No ratings yet. Be the first to rate!
            <?php endif; ?>
        </div>
        <div class="hmqz-rating-message" aria-live="polite"></div>
    </section>
    <?php
    $widget = ob_get_clean();

    return $widget . $content;
}
add_filter( 'the_content', 'hmqz_rating_append_widget', 99 );

/**
 * Add a tiny average rating badge next to the quiz title on quiz pages.
 */
function hmqz_rating_title_badge( $title, $post_id ) {
    if ( ! in_the_loop() || ! is_main_query() || ! is_singular( 'page' ) ) {
        return $title;
    }

    $post = get_post( $post_id );
    if ( ! hmqz_rating_is_quiz_page( $post ) ) {
        return $title;
    }

    $total = (int) get_post_meta( $post_id, 'hmqz_rating_total', true );
    $count = (int) get_post_meta( $post_id, 'hmqz_rating_count', true );

    if ( $count <= 0 ) {
        // No ratings yet — no badge.
        return $title;
    }

    $avg = $count > 0 ? round( $total / $count, 1 ) : 0;

    $badge_html = sprintf(
        ' <span class="hmqz-rating-badge">⭐ %s / 5</span>',
        esc_html( $avg )
    );

    return $title . $badge_html;
}
add_filter( 'the_title', 'hmqz_rating_title_badge', 20, 2 );

/**
 * Register REST route for rating submissions.
 */
function hmqz_rating_register_route() {
    register_rest_route(
        'hmqz/v1',
        '/rate',
        array(
            'methods'             => 'POST',
            'callback'            => 'hmqz_rating_handle_request',
            'permission_callback' => function () {
                return true;
            },
        )
    );
}
add_action( 'rest_api_init', 'hmqz_rating_register_route' );

/**
 * Handle rating POST requests.
 */
function hmqz_rating_handle_request( WP_REST_Request $request ) {
    $post_id = (int) $request->get_param( 'post_id' );
    $rating  = (int) $request->get_param( 'rating' );

    if ( $post_id <= 0 || $rating < 1 || $rating > 5 ) {
        return new WP_REST_Response( array( 'error' => 'Invalid data' ), 400 );
    }

    if ( ! get_post( $post_id ) ) {
        return new WP_REST_Response( array( 'error' => 'Invalid post' ), 404 );
    }

    $total = (int) get_post_meta( $post_id, 'hmqz_rating_total', true );
    $count = (int) get_post_meta( $post_id, 'hmqz_rating_count', true );

    $total += $rating;
    $count += 1;

    update_post_meta( $post_id, 'hmqz_rating_total', $total );
    update_post_meta( $post_id, 'hmqz_rating_count', $count );

    $avg = $count > 0 ? round( $total / $count, 1 ) : 0;

    return array(
        'success' => true,
        'average' => $avg,
        'count'   => $count,
    );
}
