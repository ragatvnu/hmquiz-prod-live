<?php
if (!defined('ABSPATH')) exit;

function hmqz_leaderboard_shortcode($atts) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'hmqz_leads';

    $atts = shortcode_atts([
        'quiz_id' => 0,
        'limit' => 10,
    ], $atts, 'hmquiz_leaderboard');

    $quiz_id = intval($atts['quiz_id']);
    $limit = intval($atts['limit']);

    if (!$quiz_id) {
        return '<p>Please specify a quiz_id for the leaderboard.</p>';
    }

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT name, score, total, percent, timestamp FROM $table_name WHERE title = (SELECT post_title FROM $wpdb->posts WHERE ID = %d) ORDER BY percent DESC, score DESC LIMIT %d",
        $quiz_id,
        $limit
    ));

    if (empty($results)) {
        return '<p>No scores yet for this quiz.</p>';
    }

    $output = '<div class="hmqz-leaderboard">';
    $output .= '<h3>Leaderboard</h3>';
    $output .= '<table class="widefat striped">';
    $output .= '<thead><tr><th>Rank</th><th>Name</th><th>Score</th><th>Date</th></tr></thead>';
    $output .= '<tbody>';

    foreach ($results as $i => $row) {
        $output .= '<tr>';
        $output .= '<td>' . ($i + 1) . '</td>';
        $output .= '<td>' . esc_html($row->name) . '</td>';
        $output .= '<td>' . intval($row->score) . '/' . intval($row->total) . ' (' . floatval($row->percent) . '%)</td>';
        $output .= '<td>' . date('Y-m-d', strtotime($row->timestamp)) . '</td>';
        $output .= '</tr>';
    }

    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';

    return $output;
}
add_shortcode('hmquiz_leaderboard', 'hmqz_leaderboard_shortcode');
