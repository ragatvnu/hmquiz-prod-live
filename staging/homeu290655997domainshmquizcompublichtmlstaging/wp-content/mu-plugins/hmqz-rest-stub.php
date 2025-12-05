<?php
/**
 * Plugin Name: HMQZ REST Stub (MU)
 * Description: Minimal REST routes for smoke tests. Replace with real plugin later.
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('hmqz/v1', '/leaderboard/global/top', [
    'methods'  => 'GET',
    'callback' => function (WP_REST_Request $req) {
      $range = $req->get_param('range') ?: 'week';
      $limit = intval($req->get_param('limit') ?: 10);
      return new WP_REST_Response([
        'range' => $range,
        'items' => [],
        'limit' => $limit,
      ], 200);
    },
    'permission_callback' => '__return_true',
  ]);
});
