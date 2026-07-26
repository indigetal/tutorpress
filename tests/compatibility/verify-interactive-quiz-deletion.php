<?php
/**
 * Verify Tutor quiz-builder deletion arrays delete persisted Interactive Quiz questions.
 *
 * Creates temporary course/topic/quiz/question/answer fixtures, exercises Tutor's
 * save_quiz + deleted_question_ids/deleted_answer_ids contract, then cleans up.
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

global $wpdb;

$prefix             = 'tp_iq_del_' . wp_generate_password(8, false, false);
$original_user_id   = get_current_user_id();
$original_post      = isset($_POST) && is_array($_POST) ? $_POST : [];
$course_id          = 0;
$topic_id           = 0;
$quiz_id            = 0;
$question_ids       = [];
$answer_id          = 0;
$failure_message    = '';

$cleanup = static function () use (
    &$course_id,
    &$topic_id,
    &$quiz_id,
    &$question_ids,
    &$answer_id,
    $original_user_id,
    $original_post,
    $wpdb
) {
    $_POST = $original_post;
    wp_set_current_user($original_user_id);

    if ($answer_id > 0) {
        $wpdb->delete(
            $wpdb->prefix . 'tutor_quiz_question_answers',
            ['answer_id' => $answer_id],
            ['%d']
        );
    }

    if ($quiz_id > 0) {
        $remaining_questions = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT question_id FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d",
                $quiz_id
            )
        );
        $all_question_ids = array_unique(
            array_map('intval', array_merge($question_ids, is_array($remaining_questions) ? $remaining_questions : []))
        );

        foreach ($all_question_ids as $qid) {
            if ($qid <= 0) {
                continue;
            }
            $wpdb->delete(
                $wpdb->prefix . 'tutor_quiz_question_answers',
                ['belongs_question_id' => $qid],
                ['%d']
            );
            $wpdb->delete(
                $wpdb->prefix . 'tutor_quiz_questions',
                ['question_id' => $qid],
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

$snapshot_questions = static function ($quiz_id) use ($wpdb, $assert) {
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT question_id, question_title, question_description, question_type, question_mark, question_settings, question_order
             FROM {$wpdb->prefix}tutor_quiz_questions
             WHERE quiz_id = %d
             ORDER BY question_order ASC, question_id ASC",
            $quiz_id
        )
    );
    $assert(is_array($rows), 'Failed to read quiz questions.');
    return $rows;
};

$snapshot_answers = static function ($question_id) use ($wpdb, $assert) {
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT answer_id, belongs_question_id, belongs_question_type, answer_title, is_correct, answer_order
             FROM {$wpdb->prefix}tutor_quiz_question_answers
             WHERE belongs_question_id = %d
             ORDER BY answer_order ASC, answer_id ASC",
            $question_id
        )
    );
    $assert(is_array($rows), 'Failed to read question answers.');
    return $rows;
};

$build_question_payload = static function ($row, $data_status, $order) {
    $settings = maybe_unserialize($row->question_settings);
    if (!is_array($settings)) {
        $settings = [
            'question_type' => 'h5p',
            'answer_required' => 1,
            'randomize_question' => 0,
            'question_mark' => (int) $row->question_mark,
            'show_question_mark' => 1,
            'has_multiple_correct_answer' => 0,
            'is_image_matching' => 0,
        ];
    }

    return [
        '_data_status' => $data_status,
        'question_id' => (int) $row->question_id,
        'question_title' => (string) $row->question_title,
        'question_description' => (string) $row->question_description,
        'question_type' => (string) $row->question_type,
        'question_mark' => (int) $row->question_mark,
        'question_order' => (int) $order,
        'question_settings' => $settings,
        'question_answers' => [],
    ];
};

try {
    $admins = get_users(
        [
            'role' => 'administrator',
            'number' => 1,
            'fields' => ['ID'],
        ]
    );
    $assert(!empty($admins), 'No administrator available for quiz save.');
    $admin_id = (int) $admins[0]->ID;
    wp_set_current_user($admin_id);
    $assert(current_user_can('edit_posts'), 'Administrator cannot edit_posts.');

    $tutor = tutor();
    $course_post_type = is_string($tutor->course_post_type) ? $tutor->course_post_type : 'courses';
    $topic_post_type = is_string($tutor->topics_post_type) ? $tutor->topics_post_type : 'topics';
    $quiz_post_type = is_string($tutor->quiz_post_type) ? $tutor->quiz_post_type : 'tutor_quiz';

    $course_id = wp_insert_post(
        [
            'post_type' => $course_post_type,
            'post_title' => $prefix . '_course',
            'post_status' => 'publish',
            'post_author' => $admin_id,
        ],
        true
    );
    $assert(!is_wp_error($course_id) && $course_id > 0, 'Failed to create temporary course.');

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
    $assert(!is_wp_error($topic_id) && $topic_id > 0, 'Failed to create temporary topic.');

    $quiz_id = wp_insert_post(
        [
            'post_type' => $quiz_post_type,
            'post_title' => $prefix . '_quiz',
            'post_content' => $prefix . '_desc',
            'post_status' => 'publish',
            'post_parent' => $topic_id,
            'post_author' => $admin_id,
            'menu_order' => 1,
        ],
        true
    );
    $assert(!is_wp_error($quiz_id) && $quiz_id > 0, 'Failed to create temporary quiz.');

    update_post_meta(
        $quiz_id,
        'tutor_quiz_option',
        [
            'quiz_type' => 'tutor_h5p_quiz',
            'time_limit' => [
                'time_value' => 0,
                'time_type' => 'minutes',
            ],
        ]
    );

    $fixture_defs = [
        [
            'title' => $prefix . '_q1',
            'h5p_content_id' => '910001',
            'mark' => 1,
            'order' => 1,
        ],
        [
            'title' => $prefix . '_q2_middle',
            'h5p_content_id' => '910002',
            'mark' => 2,
            'order' => 2,
        ],
        [
            'title' => $prefix . '_q3',
            'h5p_content_id' => '910003',
            'mark' => 3,
            'order' => 3,
        ],
    ];

    foreach ($fixture_defs as $def) {
        $settings = maybe_serialize(
            [
                'question_type' => 'h5p',
                'answer_required' => 1,
                'randomize_question' => 0,
                'question_mark' => (int) $def['mark'],
                'show_question_mark' => 1,
                'has_multiple_correct_answer' => 0,
                'is_image_matching' => 0,
            ]
        );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'tutor_quiz_questions',
            [
                'quiz_id' => $quiz_id,
                'question_title' => $def['title'],
                'question_description' => $def['h5p_content_id'],
                'answer_explanation' => '',
                'question_type' => 'h5p',
                'question_mark' => (int) $def['mark'],
                'question_settings' => $settings,
                'question_order' => (int) $def['order'],
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d']
        );
        $assert(false !== $inserted && $wpdb->insert_id > 0, 'Failed to insert question fixture.');
        $question_ids[] = (int) $wpdb->insert_id;
    }

    $middle_question_id = $question_ids[1];
    $answer_inserted = $wpdb->insert(
        $wpdb->prefix . 'tutor_quiz_question_answers',
        [
            'belongs_question_id' => $middle_question_id,
            'belongs_question_type' => 'h5p',
            'answer_title' => $prefix . '_answer_middle',
            'is_correct' => 0,
            'image_id' => 0,
            'answer_two_gap_match' => '',
            'answer_view_format' => '',
            'answer_settings' => null,
            'answer_order' => 1,
        ],
        ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d']
    );
    $assert(false !== $answer_inserted && $wpdb->insert_id > 0, 'Failed to insert answer fixture.');
    $answer_id = (int) $wpdb->insert_id;

    $initial_questions = $snapshot_questions($quiz_id);
    $assert(3 === count($initial_questions), 'Expected three persisted question fixtures.');
    $assert(
        (int) $initial_questions[0]->question_id === $question_ids[0]
        && (int) $initial_questions[1]->question_id === $question_ids[1]
        && (int) $initial_questions[2]->question_id === $question_ids[2],
        'Initial question order does not match fixtures.'
    );

    $initial_middle_answers = $snapshot_answers($middle_question_id);
    $assert(1 === count($initial_middle_answers), 'Expected one persisted answer on the middle question.');
    $assert((int) $initial_middle_answers[0]->answer_id === $answer_id, 'Middle answer ID mismatch.');

    $builder = new \TUTOR\QuizBuilder(false);

    // Contract A: omitting deletion arrays leaves persisted rows untouched even if omitted from payload.
    unset($_POST['deleted_question_ids'], $_POST['deleted_answer_ids']);
    $omit_payload = [
        'ID' => $quiz_id,
        'post_title' => $prefix . '_quiz',
        'post_content' => $prefix . '_desc',
        'quiz_option' => [
            'quiz_type' => 'tutor_h5p_quiz',
        ],
        'questions' => [
            $build_question_payload($initial_questions[0], 'no_change', 1),
            $build_question_payload($initial_questions[2], 'no_change', 2),
        ],
    ];

    $omit_result = $builder->save_quiz($topic_id, wp_slash($omit_payload));
    $assert(is_object($omit_result) && !empty($omit_result->success), 'Omit-deletion save_quiz failed.');

    $after_omit = $snapshot_questions($quiz_id);
    $assert(3 === count($after_omit), 'Omitting deletion arrays deleted a persisted question.');
    $assert(
        (int) $after_omit[0]->question_id === $question_ids[0]
        && (int) $after_omit[1]->question_id === $question_ids[1]
        && (int) $after_omit[2]->question_id === $question_ids[2],
        'Omit-deletion altered question IDs/order unexpectedly.'
    );
    $after_omit_answers = $snapshot_answers($middle_question_id);
    $assert(1 === count($after_omit_answers), 'Omit-deletion removed the middle answer fixture.');
    $assert((int) $after_omit_answers[0]->answer_id === $answer_id, 'Omit-deletion changed the middle answer ID.');

    // Contract B: deleted_question_ids / deleted_answer_ids remove the middle row; survivors keep content/order.
    $_POST['deleted_question_ids'] = [(string) $middle_question_id];
    $_POST['deleted_answer_ids'] = [(string) $answer_id];

    $delete_payload = [
        'ID' => $quiz_id,
        'post_title' => $prefix . '_quiz',
        'post_content' => $prefix . '_desc',
        'quiz_option' => [
            'quiz_type' => 'tutor_h5p_quiz',
        ],
        'questions' => [
            $build_question_payload($initial_questions[0], 'update', 1),
            $build_question_payload($initial_questions[2], 'update', 2),
        ],
    ];

    $delete_result = $builder->save_quiz($topic_id, wp_slash($delete_payload));
    $assert(is_object($delete_result) && !empty($delete_result->success), 'Deletion save_quiz failed.');

    $survivors = $snapshot_questions($quiz_id);
    $assert(2 === count($survivors), 'Expected exactly two survivor questions after deletion.');
    $assert(
        (int) $survivors[0]->question_id === $question_ids[0]
        && (int) $survivors[1]->question_id === $question_ids[2],
        'Survivor question IDs are incorrect.'
    );
    $assert(
        (int) $survivors[0]->question_order === 1 && (int) $survivors[1]->question_order === 2,
        'Survivor question_order is not exactly 1,2.'
    );

    $expected = [
        0 => $initial_questions[0],
        1 => $initial_questions[2],
    ];
    foreach ($expected as $index => $source) {
        $survivor = $survivors[$index];
        $assert(
            (string) $survivor->question_title === (string) $source->question_title,
            "Survivor {$index} title changed."
        );
        $assert(
            (string) $survivor->question_description === (string) $source->question_description,
            "Survivor {$index} H5P content ID/description changed."
        );
        $assert(
            (string) $survivor->question_type === 'h5p',
            "Survivor {$index} question_type changed."
        );
        $assert(
            (int) $survivor->question_mark === (int) $source->question_mark,
            "Survivor {$index} mark changed."
        );
        $assert(
            (string) $survivor->question_settings === (string) $source->question_settings
            || maybe_unserialize($survivor->question_settings) == maybe_unserialize($source->question_settings),
            "Survivor {$index} settings changed."
        );
    }

    $deleted_question = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT question_id FROM {$wpdb->prefix}tutor_quiz_questions WHERE question_id = %d",
            $middle_question_id
        )
    );
    $assert(null === $deleted_question, 'Middle persisted question was not deleted.');

    $deleted_answer = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT answer_id FROM {$wpdb->prefix}tutor_quiz_question_answers WHERE answer_id = %d",
            $answer_id
        )
    );
    $assert(null === $deleted_answer, 'Middle persisted answer was not deleted.');

    // Prevent finally from re-deleting already-removed answer ID as a soft error.
    $answer_id = 0;
} catch (Throwable $e) {
    $failure_message = $e->getMessage();
} finally {
    $cleanup();
}

if ('' !== $failure_message) {
    $fail($failure_message);
}

WP_CLI::log('PASS: Interactive Quiz persisted deletion contract is valid.');
