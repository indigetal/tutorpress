<?php
/**
 * Verify Tutor's YouTube-duration JSON response envelope.
 */

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

if (!class_exists('\TUTOR\Ajax')) {
    $fail('Tutor Ajax class is unavailable.');
}

if (defined('DOING_AJAX') && !DOING_AJAX) {
    $fail('DOING_AJAX is already defined as false.');
}

if (!defined('DOING_AJAX')) {
    define('DOING_AJAX', true);
}

$completion_marker = 'tutorpress_json_response_complete';
$wp_die_handler = static function () use ($completion_marker) {
    throw new RuntimeException($completion_marker);
};
$wp_die_filter = static function () use ($wp_die_handler) {
    return $wp_die_handler;
};

$capture_response = static function ($callback) use ($completion_marker, $fail, $wp_die_filter) {
    $initial_buffer_level = ob_get_level();
    $terminated = false;
    $body = '';

    add_filter('wp_die_ajax_handler', $wp_die_filter);
    ob_start();

    try {
        try {
            $callback();
        } catch (RuntimeException $exception) {
            if ($completion_marker !== $exception->getMessage()) {
                throw $exception;
            }
            $terminated = true;
        }

        $body = (string) ob_get_clean();
    } finally {
        remove_filter('wp_die_ajax_handler', $wp_die_filter);
        while (ob_get_level() > $initial_buffer_level) {
            ob_end_clean();
        }
    }

    if (!$terminated) {
        $fail('Tutor json_response() did not terminate through wp_die().');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        $fail('Tutor json_response() did not emit a JSON object.');
    }

    return $decoded;
};

$ajax = new \TUTOR\Ajax(false);
$success = $capture_response(static function () use ($ajax) {
    $ajax->json_response('Fetched duration successfully', ['duration' => 'PT1M2S']);
});
$failure = $capture_response(static function () use ($ajax) {
    $ajax->json_response('Failed to fetch duration', null, 400);
});

$expected_success = [
    'status_code' => 200,
    'message' => 'Fetched duration successfully',
    'data' => ['duration' => 'PT1M2S'],
];
$expected_failure = [
    'status_code' => 400,
    'message' => 'Failed to fetch duration',
    'data' => null,
];

if ($expected_success !== $success) {
    $fail('Tutor success response envelope does not match the expected contract.');
}

if ($expected_failure !== $failure) {
    $fail('Tutor failure response envelope does not match the expected contract.');
}

if (array_key_exists('success', $success) || array_key_exists('success', $failure)) {
    $fail('Tutor response unexpectedly contains a top-level success key.');
}

WP_CLI::log('PASS: Tutor YouTube-duration response contract is valid.');
