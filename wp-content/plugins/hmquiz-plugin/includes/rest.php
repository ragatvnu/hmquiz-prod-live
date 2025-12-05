<?php
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function () {
  register_rest_route('hmqz/v1', '/topics', [
    'methods'  => 'GET',
    'callback' => 'hmqz_rest_topics',
    'permission_callback' => '__return_true',
  ]);

  register_rest_route('hmqz/v1', '/quiz/(?P<id>\d+)', [
    'methods'  => 'GET',
    'callback' => 'hmqz_rest_quiz',
    'permission_callback' => '__return_true',
    // accept WP core’s validator signature: ($param, $request, $key)
    'args' => [
      'id' => [
        'validate_callback' => function($param){ return is_numeric($param) && $param > 0; },
      ],
    ],
  ]);

  // POST score/email → send via wp_mail, optional store
  register_rest_route('hmqz/v1', '/lead', [
    'methods'  => 'POST',
    'callback' => 'hmqz_rest_lead',
    'permission_callback' => '__return_true',
    'args' => [
      'quiz_id' => ['required' => true],
      'email'   => ['required' => true],
      'name'    => ['required' => false],
      'score'   => ['required' => true],   // e.g. { total, correct, percent, level }
      'meta'    => ['required' => false],  // anything else (mode, timestamp, etc.)
    ],
  ]);
  register_rest_route('hmqz/v1', '/share', [
    'methods'  => 'POST',
    'callback' => 'hmqz_rest_share_by_email',
    'permission_callback' => function (\WP_REST_Request $r) {
        return wp_verify_nonce($r->get_header('X-WP-Nonce'), 'wp_rest');
    },
    'args' => [
      'email'   => ['required' => true],
      'subject' => ['required' => true],
      'body'    => ['required' => true],
    ],
  ]);
});

function hmqz_rest_share_by_email(\WP_REST_Request $req) {
  $email   = sanitize_email((string)$req->get_param('email'));
  $subject = sanitize_text_field((string)$req->get_param('subject'));
  $body    = wp_kses_post((string)$req->get_param('body'));

  if (!is_email($email)) {
    return new \WP_Error('hmqz_bad_request', 'Invalid email', ['status'=>400]);
  }

  $sent = wp_mail($email, $subject, $body);

  return rest_ensure_response(['ok'=> (bool)$sent]);
}

function hmqz_rest_topics(\WP_REST_Request $req) {
  $terms = get_terms(['taxonomy'=>'hmqz_topic','hide_empty'=>false]);
  $out = [];
  foreach ((array)$terms as $t) {
    if (is_wp_error($t)) continue;
    $out[] = ['slug'=>$t->slug,'name'=>$t->name,'count'=>(int)$t->count];
  }
  return rest_ensure_response($out);
}

function hmqz_resolve_bank_path($rel_or_name) {
  $rel_or_name = trim((string)$rel_or_name);
  if ($rel_or_name === '') return '';
  if ($rel_or_name[0] === '/' && file_exists($rel_or_name)) {
    return $rel_or_name;
  }
  $rel = basename($rel_or_name);
  $up = wp_get_upload_dir();
  $dir = trailingslashit($up['basedir']) . 'hmquiz/banks';
  $abs = trailingslashit($dir) . $rel;
  return file_exists($abs) ? $abs : '';
}

function hmqz_rest_quiz(\WP_REST_Request $req) {
  $id   = (int)$req['id'];
  $post = get_post($id);
  if (!$post || $post->post_type !== 'hmqz_quiz') {
    return new \WP_Error('hmqz_not_found', 'Quiz not found', ['status'=>404]);
  }

  $per_level  = max(1, (int)get_post_meta($id, 'hmqz_per_level', true));
  $pass_ratio = (float)get_post_meta($id, 'hmqz_pass_ratio', true);
  if ($pass_ratio <= 0 || $pass_ratio > 1) $pass_ratio = 0.6;

  $bank_rel = (string)get_post_meta($id, 'hmqz_bank_file', true);
  $bank_abs = hmqz_resolve_bank_path($bank_rel);
  if (!$bank_abs) {
    return new \WP_Error('hmqz_bank_missing', 'Bank file not found', ['status'=>500,'bank'=>$bank_rel]);
  }

  $json = file_get_contents($bank_abs);
  $data = json_decode($json, true);
  if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
    return new \WP_Error('hmqz_bank_bad_json', 'Bank JSON invalid', ['status'=>500,'bank'=>$bank_rel]);
  }
  $items = $data['items'];

  // Build levels deterministically: chunk items by per_level
  $levels = [];
  $chunk  = [];
  foreach ($items as $q) {
    // Normalize a safe MCQ structure
    $text = (string)($q['q'] ?? $q['text'] ?? '');
    $choices = (array)($q['options'] ?? $q['choices'] ?? []);
    $answerIdx = null;
    if (isset($q['answer_index'])) $answerIdx = (int)$q['answer_index'];
    elseif (isset($q['answer'])) {
      // map answer text to index if possible
      $answerIdx = array_search((string)$q['answer'], $choices, true);
      if ($answerIdx === false) $answerIdx = 0;
    } else $answerIdx = 0;

    $rawTopics = [];
    if (!empty($q['topics']) && is_array($q['topics'])) {
      $rawTopics = $q['topics'];
    } elseif (!empty($q['topic'])) {
      $rawTopics = [$q['topic']];
    }
    $rawCategories = [];
    if (!empty($q['categories']) && is_array($q['categories'])) {
      $rawCategories = $q['categories'];
    } elseif (!empty($q['category'])) {
      $rawCategories = [$q['category']];
    } elseif (!empty($q['meta']['category'])) {
      $rawCategories = [$q['meta']['category']];
    }
    $normTopics = array_values(array_filter(array_map('trim', array_map('strval', (array)$rawTopics))));
    $normCategories = array_values(array_filter(array_map('trim', array_map('strval', (array)$rawCategories))));

    $chunk[] = [
      'text' => $text,
      'choices' => array_values($choices),
      'correct_index' => (int)$answerIdx,
      'topic' => $normTopics[0] ?? '',
      'category' => $normCategories[0] ?? '',
      'topics' => $normTopics,
      'categories' => $normCategories,
      'meta' => [
        'topics'     => $normTopics,
        'categories' => $normCategories,
      ],
    ];

    if (count($chunk) === $per_level) {
      $levels[] = ['questions' => $chunk];
      $chunk = [];
    }
  }
  if (!empty($chunk)) $levels[] = ['questions' => $chunk];

  $payload = [
    'id' => $id,
    'title' => $post->post_title,
    'question_count' => count($items),
    'rules' => ['per_level'=>$per_level, 'pass_ratio'=>$pass_ratio],
    'bank'  => $bank_rel,
    'levels' => $levels,
  ];
  return rest_ensure_response($payload);
}

function hmqz_rest_lead(\WP_REST_Request $req) {
  $quiz_id = (int)$req->get_param('quiz_id');
  $email   = sanitize_email((string)$req->get_param('email'));
  $name    = sanitize_text_field((string)$req->get_param('name'));
  $score   = $req->get_param('score'); // array
  $meta    = $req->get_param('meta');

  if (!$quiz_id || !is_email($email)) {
    return new \WP_Error('hmqz_bad_request', 'quiz_id or email invalid', ['status'=>400]);
  }

  $title = get_the_title($quiz_id) ?: 'HMQUIZ Result';
  $subj  = "Your HMQUIZ result: $title";
  $body  = "Hi ".($name ?: 'there').",\n\nHere is your result for \"$title\":\n"
         . "Score: ".json_encode($score)."\n"
         . "Meta: ".json_encode($meta)."\n\nThanks for playing!";
  $sent = wp_mail($email, $subj, $body);

  // (Optional) store as a lightweight log
  $log = [
    't' => current_time('mysql'),
    'quiz_id' => $quiz_id,
    'email' => $email,
    'name' => $name,
    'score' => $score,
    'meta'  => $meta,
    'sent'  => (bool)$sent,
  ];
  $logs = (array)get_option('hmqz_leads', []);
  $logs[] = $log;
  update_option('hmqz_leads', $logs, false);

  return rest_ensure_response(['ok'=> (bool)$sent, 'stored'=>true]);
}
