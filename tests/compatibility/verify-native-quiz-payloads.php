<?php
/**
 * Verify the five Tutor 4.0 native question payloads through QuizBuilder.
 *
 * Creates one temporary quiz, submits all five native rows through Tutor's generic
 * save path, inspects the exact stored question/answer contracts, and removes every
 * fixture row, post, and generated Tutor Pro quiz-image file in a finally block.
 */

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assert = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

if (!function_exists('tutor') || !is_object(tutor())) {
    $fail('Tutor is not active.');
}

if (!class_exists('\\TUTOR\\QuizBuilder')) {
    $fail('Tutor QuizBuilder is unavailable.');
}

if (!defined('TUTOR_VERSION') || version_compare(TUTOR_VERSION, '4.0.0', '<')) {
    $fail('Tutor 4.0 or later is required.');
}

if (!defined('TUTOR_PRO_VERSION')) {
    $fail('Matching Tutor Pro 4.0 is required.');
}

if (
    function_exists('tutor_utils')
    && method_exists(tutor_utils(), 'is_legacy_learning_mode')
    && tutor_utils()->is_legacy_learning_mode()
) {
    $fail('Run this payload fixture in Modern or Kids learning mode.');
}

global $wpdb;

$prefix             = 'tp_native_payload_' . wp_generate_password(8, false, false);
$original_user_id   = get_current_user_id();
$original_post      = isset($_POST) && is_array($_POST) ? $_POST : [];
$course_id          = 0;
$topic_id           = 0;
$quiz_id            = 0;
$question_ids       = [];
$generated_files    = [];
$failure_message    = '';

$read_questions = static function ($quiz_id) use ($wpdb, $assert) {
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT question_id, quiz_id, content_id, question_title, question_description,
                    answer_explanation, question_type, question_mark, question_settings, question_order
             FROM {$wpdb->prefix}tutor_quiz_questions
             WHERE quiz_id = %d
             ORDER BY question_order ASC, question_id ASC",
            $quiz_id
        )
    );
    $assert(is_array($rows), 'Failed to read native question fixtures.');
    return $rows;
};

$read_answers = static function ($question_id) use ($wpdb, $assert) {
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT answer_id, belongs_question_id, belongs_question_type, answer_title, is_correct,
                    image_id, answer_two_gap_match, answer_view_format, answer_settings, answer_order
             FROM {$wpdb->prefix}tutor_quiz_question_answers
             WHERE belongs_question_id = %d
             ORDER BY answer_order ASC, answer_id ASC",
            $question_id
        )
    );
    $assert(is_array($rows), 'Failed to read native answer fixtures.');
    return $rows;
};

$record_generated_files = static function () use (
    &$generated_files,
    &$question_ids,
    $read_answers
) {
    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        return;
    }

    $quiz_images_dir = trailingslashit($upload_dir['basedir']) . 'tutor/quiz-images/';
    foreach ($question_ids as $question_id) {
        foreach ($read_answers($question_id) as $answer) {
            $stored = wp_basename((string) $answer->answer_two_gap_match);
            if (!preg_match('/^(?:draw-mask|pin-mask|puzzle)-[A-Za-z0-9._-]+\.png$/', $stored)) {
                continue;
            }

            $path = $quiz_images_dir . $stored;
            if (is_file($path)) {
                $generated_files[$path] = true;
            }
        }
    }
};

$cleanup = static function () use (
    &$course_id,
    &$topic_id,
    &$quiz_id,
    &$question_ids,
    &$generated_files,
    $record_generated_files,
    $original_user_id,
    $original_post,
    $wpdb
) {
    $_POST = $original_post;
    wp_set_current_user($original_user_id);

    if ($quiz_id > 0) {
        $remaining = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT question_id FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d",
                $quiz_id
            )
        );
        $question_ids = array_unique(
            array_map('intval', array_merge($question_ids, is_array($remaining) ? $remaining : []))
        );
    }

    // Discover generated files from fixture-owned answer rows before deleting those rows.
    $record_generated_files();
    foreach (array_keys($generated_files) as $path) {
        if (is_file($path)) {
            wp_delete_file($path);
        }
    }

    if ($quiz_id > 0) {
        foreach ($question_ids as $question_id) {
            if ($question_id <= 0) {
                continue;
            }
            $wpdb->delete(
                $wpdb->prefix . 'tutor_quiz_question_answers',
                ['belongs_question_id' => $question_id],
                ['%d']
            );
            $wpdb->delete(
                $wpdb->prefix . 'tutor_quiz_questions',
                ['question_id' => $question_id],
                ['%d']
            );
        }

        wp_delete_post($quiz_id, true);
    }

    if ($topic_id > 0) {
        wp_delete_post($topic_id, true);
    }

    if ($course_id > 0) {
        wp_delete_post($course_id, true);
    }
};

try {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID']]);
    $assert(!empty($admins), 'No administrator is available for the native payload fixture.');
    $admin_id = (int) $admins[0]->ID;
    wp_set_current_user($admin_id);
    $assert(current_user_can('edit_posts'), 'The fixture administrator cannot edit_posts.');

    $tutor            = tutor();
    $course_post_type = is_string($tutor->course_post_type) ? $tutor->course_post_type : 'courses';
    $topic_post_type  = is_string($tutor->topics_post_type) ? $tutor->topics_post_type : 'topics';
    $quiz_post_type   = is_string($tutor->quiz_post_type) ? $tutor->quiz_post_type : 'tutor_quiz';

    $course_id = wp_insert_post(
        [
            'post_type' => $course_post_type,
            'post_title' => $prefix . '_course',
            'post_status' => 'publish',
            'post_author' => $admin_id,
        ],
        true
    );
    $assert(!is_wp_error($course_id) && $course_id > 0, 'Failed to create the temporary course.');

    $topic_id = wp_insert_post(
        [
            'post_type' => $topic_post_type,
            'post_title' => $prefix . '_topic',
            'post_status' => 'publish',
            'post_parent' => $course_id,
            'post_author' => $admin_id,
        ],
        true
    );
    $assert(!is_wp_error($topic_id) && $topic_id > 0, 'Failed to create the temporary topic.');

    $quiz_id = wp_insert_post(
        [
            'post_type' => $quiz_post_type,
            'post_title' => $prefix . '_quiz',
            'post_content' => $prefix . '_description',
            'post_status' => 'publish',
            'post_parent' => $topic_id,
            'post_author' => $admin_id,
            'menu_order' => 1,
        ],
        true
    );
    $assert(!is_wp_error($quiz_id) && $quiz_id > 0, 'Failed to create the temporary quiz.');

    $base_settings = [
        'answer_required' => 1,
        'randomize_question' => 0,
        'question_mark' => 1,
        'show_question_mark' => 1,
        'has_multiple_correct_answer' => 0,
        'is_image_matching' => 0,
    ];

    $scale_value = wp_json_encode(
        [
            'value' => 25,
            'config' => [
                'min' => -10,
                'max' => 40,
                'step' => 1,
                'defaultValue' => 25,
                'pxPerUnit' => 4,
                'labelEvery' => 5,
                'minorTickEvery' => 1,
                'precision' => 0,
            ],
        ],
        JSON_UNESCAPED_SLASHES
    );
    $coordinates_value = wp_json_encode(
        [
            ['x' => -3, 'y' => 4],
            ['x' => 10, 'y' => -8],
        ],
        JSON_UNESCAPED_SLASHES
    );
    $png_data_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    $definitions = [
        'scale' => [
            'settings' => [],
            'answer_value' => $scale_value,
            'answer_format' => 'scale',
        ],
        'coordinates' => [
            'settings' => ['coordinates_axis_range' => 20],
            'answer_value' => $coordinates_value,
            'answer_format' => 'coordinates',
        ],
        'draw_image' => [
            'settings' => ['draw_image_threshold_percent' => 70],
            'answer_value' => $png_data_url,
            'answer_format' => 'draw_image',
            'stored_prefix' => 'draw-mask-',
        ],
        'pin_image' => [
            'settings' => [],
            'answer_value' => $png_data_url,
            'answer_format' => 'pin_image',
            'stored_prefix' => 'pin-mask-',
        ],
        'puzzle' => [
            'settings' => ['puzzle_grid_size' => 4],
            'answer_value' => $png_data_url,
            'answer_format' => 'puzzle',
            'stored_prefix' => 'puzzle-',
        ],
    ];

    $questions = [];
    $order     = 0;
    foreach ($definitions as $question_type => $definition) {
        $order++;
        $question_id = -1000 - $order;
        $answer_id   = -2000 - $order;
        $settings    = array_merge(
            $base_settings,
            ['question_type' => $question_type],
            $definition['settings']
        );

        $questions[] = [
            '_data_status' => 'new',
            'question_id' => $question_id,
            'question_title' => $prefix . '_' . $question_type,
            'question_description' => $prefix . '_' . $question_type . '_description',
            'answer_explanation' => '',
            'question_type' => $question_type,
            'question_mark' => 1,
            'question_order' => $order,
            'question_settings' => $settings,
            'question_answers' => [
                [
                    '_data_status' => 'new',
                    'answer_id' => $answer_id,
                    'belongs_question_id' => $question_id,
                    'belongs_question_type' => $question_type,
                    'answer_title' => '',
                    'is_correct' => '1',
                    'image_id' => 0,
                    'answer_two_gap_match' => $definition['answer_value'],
                    'answer_view_format' => $definition['answer_format'],
                    'answer_settings' => null,
                    'answer_order' => 0,
                ],
            ],
        ];
    }

    $_POST = [];
    $builder = new \TUTOR\QuizBuilder(false);
    $result = $builder->save_quiz(
        $topic_id,
        wp_slash(
            [
                'ID' => $quiz_id,
                'post_title' => $prefix . '_quiz',
                'post_content' => $prefix . '_description',
                'quiz_option' => [
                    'time_limit' => ['time_value' => 0, 'time_type' => 'minutes'],
                    'passing_grade' => 80,
                ],
                'questions' => $questions,
            ]
        )
    );
    $assert(is_object($result) && !empty($result->success), 'Tutor QuizBuilder rejected the native payload.');

    $stored_questions = $read_questions($quiz_id);
    $assert(5 === count($stored_questions), 'Expected exactly five stored native questions.');

    $stored_by_type = [];
    foreach ($stored_questions as $index => $question) {
        $question_type = (string) $question->question_type;
        $assert(isset($definitions[$question_type]), 'QuizBuilder stored an unexpected native question type.');
        $assert((int) $question->question_order === $index + 1, "Stored {$question_type} order changed.");
        $assert(null === $question->content_id, "Stored {$question_type} unexpectedly gained content_id.");
        $question_ids[] = (int) $question->question_id;
        $stored_by_type[$question_type] = $question;
    }

    foreach ($definitions as $question_type => $definition) {
        $assert(isset($stored_by_type[$question_type]), "Missing stored {$question_type} question.");
        $question = $stored_by_type[$question_type];
        $settings = maybe_unserialize($question->question_settings);
        $assert(is_array($settings), "Stored {$question_type} settings are not an array.");

        $expected_settings = array_merge(
            $base_settings,
            ['question_type' => $question_type],
            $definition['settings']
        );
        $assert(
            $expected_settings == $settings,
            "Stored {$question_type} settings do not match the submitted native contract."
        );

        $answers = $read_answers((int) $question->question_id);
        $assert(1 === count($answers), "Expected exactly one {$question_type} answer.");
        $answer = $answers[0];

        $assert((int) $answer->answer_id > 0, "{$question_type} answer_id is not persisted.");
        $assert(
            (int) $question->question_id === (int) $answer->belongs_question_id,
            "{$question_type} belongs_question_id changed."
        );
        $assert(
            $question_type === (string) $answer->belongs_question_type,
            "{$question_type} belongs_question_type changed."
        );
        $assert(
            $definition['answer_format'] === (string) $answer->answer_view_format,
            "{$question_type} answer_view_format changed."
        );
        $assert('1' === (string) $answer->is_correct, "{$question_type} is_correct changed.");
        $assert('' === (string) $answer->answer_title, "{$question_type} answer_title changed.");
        $assert(0 === (int) $answer->image_id, "{$question_type} image_id changed.");
        $assert(1 === (int) $answer->answer_order, "{$question_type} answer_order was not normalized to 1.");
        $assert(null === $answer->answer_settings, "{$question_type} answer_settings changed.");

        if (isset($definition['stored_prefix'])) {
            $stored_value = (string) $answer->answer_two_gap_match;
            $assert(
                0 === strpos($stored_value, $definition['stored_prefix'])
                && '.png' === substr($stored_value, -4),
                "Tutor Pro did not persist {$question_type} with its native file prefix."
            );
        } else {
            $assert(
                $definition['answer_value'] === (string) $answer->answer_two_gap_match,
                "{$question_type} answer_two_gap_match changed."
            );
        }
    }

    $record_generated_files();
    $upload_dir = wp_upload_dir();
    $assert(empty($upload_dir['error']), 'Could not resolve the uploads directory.');
    foreach (array_keys($generated_files) as $path) {
        $assert(is_file($path), 'A generated native quiz-image file is missing.');
    }
    $assert(3 === count($generated_files), 'Expected exactly three generated native quiz-image files.');
} catch (Throwable $e) {
    $failure_message = $e->getMessage();
} finally {
    $cleanup();
}

if ('' !== $failure_message) {
    $fail($failure_message);
}

WP_CLI::log('PASS: All five Tutor 4.0 native payload contracts are valid.');
