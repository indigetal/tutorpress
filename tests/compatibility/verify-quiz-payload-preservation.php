<?php
/**
 * Verify TutorPress quiz payload preservation across both lossy hops.
 *
 * Tutor serializes question_settings wholesale, so any key TutorPress fails to load or
 * fails to send back is destroyed on update. This script proves the complete round trip:
 *
 *   raw DB row -> TutorPress REST loader -> client serializer -> Tutor save_quiz -> DB row
 *
 * Coverage: a pre-4.0 question, every known Tutor 4.0 native settings key, an unknown
 * future settings key, an unknown question slug, a content_id-linked row, H5P
 * description/content-ID preservation, untrusted settings payloads, and both the
 * no_change and genuinely edited save paths.
 *
 * All fixtures are created and removed by this script. Real course and quiz data is
 * never touched.
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

if (!class_exists('TutorPress_REST_Quizzes_Controller')) {
    $fail('TutorPress quizzes REST controller is unavailable.');
}

global $wpdb;

$prefix           = 'tp_pres_' . wp_generate_password(8, false, false);
$original_user_id = get_current_user_id();
$original_post    = isset($_POST) && is_array($_POST) ? $_POST : [];
$course_id        = 0;
$topic_id         = 0;
$quiz_id          = 0;
$question_ids     = [];
$failure_message  = '';
$notes            = [];
// Files this script creates on disk for the temporary-mask deletion fixture. Removed
// here so an assertion failure cannot strand them in the uploads directory.
$mask_fixture_files = [];

$cleanup = static function () use (
    &$course_id,
    &$topic_id,
    &$quiz_id,
    &$question_ids,
    &$mask_fixture_files,
    $original_user_id,
    $original_post,
    $wpdb
) {
    $_POST = $original_post;
    wp_set_current_user($original_user_id);

    foreach ($mask_fixture_files as $fixture_file) {
        if (is_string($fixture_file) && is_file($fixture_file)) {
            wp_delete_file($fixture_file);
        }
    }

    if ($quiz_id > 0) {
        $remaining = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT question_id FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d",
                $quiz_id
            )
        );
        $all_ids = array_unique(
            array_map('intval', array_merge($question_ids, is_array($remaining) ? $remaining : []))
        );

        foreach ($all_ids as $qid) {
            if ($qid <= 0) {
                continue;
            }
            $wpdb->delete($wpdb->prefix . 'tutor_quiz_question_answers', ['belongs_question_id' => $qid], ['%d']);
            $wpdb->delete($wpdb->prefix . 'tutor_quiz_questions', ['question_id' => $qid], ['%d']);
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

/**
 * Read one raw question row, including the columns TutorPress must never mutate.
 */
$read_row = static function ($question_id) use ($wpdb, $assert) {
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT question_id, content_id, question_title, question_description, answer_explanation,
                    question_type, question_mark, question_settings, question_order
             FROM {$wpdb->prefix}tutor_quiz_questions
             WHERE question_id = %d",
            $question_id
        )
    );
    $assert(is_object($row), 'Failed to read question row.');
    return $row;
};

$read_settings = static function ($question_id) use ($read_row) {
    $settings = maybe_unserialize($read_row($question_id)->question_settings);
    return is_array($settings) ? $settings : [];
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
    $assert(is_array($rows), 'Failed to read question answers.');
    return $rows;
};

/**
 * Load the quiz through the real TutorPress REST route.
 *
 * This is the hop that previously rebuilt question_settings from a seven-key allowlist.
 */
$load_via_rest = static function ($quiz_id) use ($assert) {
    $request  = new WP_REST_Request('GET', '/tutorpress/v1/quizzes/' . (int) $quiz_id);
    $response = rest_do_request($request);
    $assert(!$response->is_error(), 'REST quiz load failed with status ' . $response->get_status() . '.');

    $body = $response->get_data();
    $assert(is_array($body) && !empty($body['data']), 'REST quiz load returned no data.');
    $assert(!empty($body['data']['questions']), 'REST quiz load returned no questions.');

    $indexed = [];
    foreach ($body['data']['questions'] as $question) {
        $question = (array) $question;
        $indexed[(int) $question['question_id']] = $question;
    }
    return $indexed;
};

/**
 * Reproduce the client serializer in assets/js/src/store/curriculum/index.ts exactly:
 * spread the loaded settings, then normalize only the known booleans to 0/1, with the
 * question_type column authoritative.
 */
$serialize_question = static function (array $loaded, $data_status, $order, $title_override = null) {
    $settings = is_array($loaded['question_settings']) ? $loaded['question_settings'] : [];

    $serialized_settings = array_merge(
        $settings,
        [
            'question_type' => $loaded['question_type'],
            'answer_required' => !empty($settings['answer_required']) ? 1 : 0,
            'randomize_question' => !empty($settings['randomize_question']) ? 1 : 0,
            'question_mark' => (int) $loaded['question_mark'],
            'show_question_mark' => !empty($settings['show_question_mark']) ? 1 : 0,
            'has_multiple_correct_answer' => !empty($settings['has_multiple_correct_answer']) ? 1 : 0,
            'is_image_matching' => !empty($settings['is_image_matching']) ? 1 : 0,
        ]
    );

    $answers = [];
    foreach ((array) $loaded['question_answers'] as $answer) {
        $answer = (array) $answer;
        $answer['_data_status'] = 'no_change';
        $answers[] = $answer;
    }

    $question = [
        '_data_status' => $data_status,
        'question_id' => (int) $loaded['question_id'],
        'question_title' => null === $title_override ? (string) $loaded['question_title'] : $title_override,
        'question_description' => (string) $loaded['question_description'],
        'answer_explanation' => (string) $loaded['answer_explanation'],
        'question_type' => (string) $loaded['question_type'],
        'question_mark' => (int) $loaded['question_mark'],
        'question_order' => (int) $order,
        'question_settings' => $serialized_settings,
        'question_answers' => $answers,
    ];

    // content_id is forwarded unchanged so Tutor keeps its linked-row semantics.
    if (isset($loaded['content_id']) && null !== $loaded['content_id']) {
        $question['content_id'] = $loaded['content_id'];
    }

    return $question;
};

try {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID']]);
    $assert(!empty($admins), 'No administrator available for quiz save.');
    $admin_id = (int) $admins[0]->ID;
    wp_set_current_user($admin_id);
    $assert(current_user_can('edit_posts'), 'Administrator cannot edit_posts.');

    $tutor            = tutor();
    $course_post_type = is_string($tutor->course_post_type) ? $tutor->course_post_type : 'courses';
    $topic_post_type  = is_string($tutor->topics_post_type) ? $tutor->topics_post_type : 'topics';
    $quiz_post_type   = is_string($tutor->quiz_post_type) ? $tutor->quiz_post_type : 'tutor_quiz';

    $is_legacy_mode = function_exists('tutor_utils')
        && method_exists(tutor_utils(), 'is_legacy_learning_mode')
        && tutor_utils()->is_legacy_learning_mode();

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
            'time_limit' => ['time_value' => 0, 'time_type' => 'minutes'],
            'passing_grade' => 80,
        ]
    );

    // Known booleans are stored as Tutor stores them: '1'/'0' strings.
    $base_settings = [
        'question_type' => 'true_false',
        'answer_required' => '1',
        'randomize_question' => '0',
        'question_mark' => 1,
        'show_question_mark' => '1',
        'has_multiple_correct_answer' => '0',
        'is_image_matching' => '0',
    ];

    $fixtures = [
        'pre40' => [
            'type' => 'true_false',
            'description' => 'Pre-4.0 baseline question.',
            'settings' => $base_settings,
            'content_id' => null,
        ],
        'coordinates' => [
            'type' => 'coordinates',
            'description' => 'Graph native question.',
            'settings' => array_merge(
                $base_settings,
                [
                    'question_type' => 'coordinates',
                    'coordinates_axis_range' => 20,
                    'tp_future_setting' => 'preserve-me',
                    'tp_future_nested' => ['deep' => ['keep' => 'yes']],
                ]
            ),
            'content_id' => null,
        ],
        'draw_image' => [
            'type' => 'draw_image',
            'description' => 'Draw Image native question.',
            'settings' => array_merge(
                $base_settings,
                ['question_type' => 'draw_image', 'draw_image_threshold_percent' => 85]
            ),
            'content_id' => null,
        ],
        'puzzle' => [
            'type' => 'puzzle',
            'description' => 'Puzzle native question.',
            'settings' => array_merge(
                $base_settings,
                ['question_type' => 'puzzle', 'puzzle_grid_size' => 6]
            ),
            'content_id' => null,
        ],
        'unknown_slug' => [
            'type' => 'tp_future_question_type',
            'description' => 'Unknown future slug.',
            'settings' => array_merge(
                $base_settings,
                ['question_type' => 'tp_future_question_type', 'tp_unknown_only_key' => 'opaque']
            ),
            'content_id' => null,
        ],
        'linked' => [
            'type' => 'multiple_choice',
            'description' => 'Content Bank linked row.',
            'settings' => array_merge($base_settings, ['question_type' => 'multiple_choice']),
            'content_id' => 987654,
        ],
        'h5p' => [
            'type' => 'h5p',
            'description' => '910042',
            'settings' => array_merge($base_settings, ['question_type' => 'h5p']),
            'content_id' => null,
        ],
    ];

    $order = 0;
    foreach ($fixtures as $key => $def) {
        $order++;
        $data = [
            'quiz_id' => $quiz_id,
            'question_title' => $prefix . '_' . $key,
            'question_description' => $def['description'],
            'answer_explanation' => '',
            'question_type' => $def['type'],
            'question_mark' => 1,
            'question_settings' => maybe_serialize($def['settings']),
            'question_order' => $order,
        ];
        $format = ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d'];

        if (null !== $def['content_id']) {
            $data['content_id'] = (int) $def['content_id'];
            $format[] = '%d';
        }

        $inserted = $wpdb->insert($wpdb->prefix . 'tutor_quiz_questions', $data, $format);
        $assert(false !== $inserted && $wpdb->insert_id > 0, "Failed to insert {$key} fixture.");
        $question_ids[$key] = (int) $wpdb->insert_id;
    }

    // A native answer row carrying the contract Range/Graph depend on.
    $answer_json = wp_json_encode(['value' => 7, 'min' => 0, 'max' => 10]);
    $answer_inserted = $wpdb->insert(
        $wpdb->prefix . 'tutor_quiz_question_answers',
        [
            'belongs_question_id' => $question_ids['coordinates'],
            'belongs_question_type' => 'coordinates',
            'answer_title' => $prefix . '_answer',
            'is_correct' => 1,
            'image_id' => 0,
            'answer_two_gap_match' => $answer_json,
            'answer_view_format' => 'scale',
            'answer_settings' => null,
            'answer_order' => 1,
        ],
        ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d']
    );
    $assert(false !== $answer_inserted && $wpdb->insert_id > 0, 'Failed to insert answer fixture.');

    // ------------------------------------------------------------------
    // Hop 1: the REST loader must hand every stored key to the client.
    // ------------------------------------------------------------------
    $loaded = $load_via_rest($quiz_id);
    $assert(count($loaded) === count($fixtures), 'REST load returned an unexpected question count.');

    foreach ($fixtures as $key => $def) {
        $question = $loaded[$question_ids[$key]];
        $settings = (array) $question['question_settings'];

        foreach ($def['settings'] as $setting_key => $expected) {
            $assert(
                array_key_exists($setting_key, $settings),
                "REST loader dropped settings key '{$setting_key}' from the {$key} fixture."
            );
        }

        // The question_type column is authoritative on the way out.
        $assert(
            $settings['question_type'] === $def['type'],
            "REST loader did not report the column question_type for {$key}."
        );

        // Known booleans keep their existing client types and defaults.
        $assert(true === $settings['answer_required'], "answer_required is not boolean true for {$key}.");
        $assert(false === $settings['randomize_question'], "randomize_question is not boolean false for {$key}.");
        $assert(true === $settings['show_question_mark'], "show_question_mark is not boolean true for {$key}.");
        $assert(
            false === $settings['has_multiple_correct_answer'],
            "has_multiple_correct_answer is not boolean false for {$key}."
        );
        $assert(false === $settings['is_image_matching'], "is_image_matching is not boolean false for {$key}.");
        $assert(1 === $settings['question_mark'], "question_mark is not integer 1 for {$key}.");
    }

    // Native and unknown keys arrive with their exact stored values.
    $graph_settings = (array) $loaded[$question_ids['coordinates']]['question_settings'];
    $assert(20 === (int) $graph_settings['coordinates_axis_range'], 'coordinates_axis_range value changed on load.');
    $assert('preserve-me' === $graph_settings['tp_future_setting'], 'Unknown settings value changed on load.');
    $assert(
        is_array($graph_settings['tp_future_nested'])
        && 'yes' === $graph_settings['tp_future_nested']['deep']['keep'],
        'Nested unknown settings structure was flattened on load.'
    );

    $draw_settings = (array) $loaded[$question_ids['draw_image']]['question_settings'];
    $assert(85 === (int) $draw_settings['draw_image_threshold_percent'], 'draw_image_threshold_percent changed on load.');

    $puzzle_settings = (array) $loaded[$question_ids['puzzle']]['question_settings'];
    $assert(6 === (int) $puzzle_settings['puzzle_grid_size'], 'puzzle_grid_size changed on load.');

    // content_id reaches the client so it can be forwarded unchanged.
    $assert(
        987654 === (int) $loaded[$question_ids['linked']]['content_id'],
        'REST loader did not expose content_id for the linked row.'
    );

    // H5P stores its content ID in question_description.
    $assert(
        '910042' === (string) $loaded[$question_ids['h5p']]['question_description'],
        'H5P question_description/content ID changed on load.'
    );

    // Native answer contract survives the loader.
    $loaded_answers = (array) $loaded[$question_ids['coordinates']]['question_answers'];
    $assert(1 === count($loaded_answers), 'Expected one loaded answer on the Graph fixture.');
    $loaded_answer = (array) $loaded_answers[0];
    $assert(
        $answer_json === (string) $loaded_answer['answer_two_gap_match'],
        'answer_two_gap_match changed on load.'
    );
    $assert('scale' === (string) $loaded_answer['answer_view_format'], 'answer_view_format changed on load.');
    $assert(
        array_key_exists('answer_settings', $loaded_answer),
        'answer_settings is missing from the loaded answer.'
    );

    // ------------------------------------------------------------------
    // Untrusted stored settings must not fatal, leak objects, or persist.
    // ------------------------------------------------------------------
    $hostile_cases = [
        'object' => serialize(['question_type' => 'true_false', 'tp_evil' => new stdClass()]),
        'scalar' => 'not-a-serialized-array',
        'broken' => 'a:2:{s:13:"question_type";s:10:"true_false";s:4:"oops"',
    ];

    foreach ($hostile_cases as $case => $stored) {
        $wpdb->update(
            $wpdb->prefix . 'tutor_quiz_questions',
            ['question_settings' => $stored],
            ['question_id' => $question_ids['pre40']],
            ['%s'],
            ['%d']
        );

        $hostile_loaded = $load_via_rest($quiz_id);
        $hostile_settings = (array) $hostile_loaded[$question_ids['pre40']]['question_settings'];

        foreach ($hostile_settings as $setting_key => $value) {
            $assert(
                !is_object($value),
                "Hostile '{$case}' settings leaked an object at key '{$setting_key}'."
            );
        }
        $assert(
            !array_key_exists('tp_evil', $hostile_settings),
            "Hostile '{$case}' settings retained an object value."
        );
        $assert(
            true === $hostile_settings['answer_required'],
            "Hostile '{$case}' settings lost the known boolean defaults."
        );
    }

    // Restore the pre-4.0 fixture for the save hop.
    $wpdb->update(
        $wpdb->prefix . 'tutor_quiz_questions',
        ['question_settings' => maybe_serialize($fixtures['pre40']['settings'])],
        ['question_id' => $question_ids['pre40']],
        ['%s'],
        ['%d']
    );

    $loaded = $load_via_rest($quiz_id);
    $builder = new \TUTOR\QuizBuilder(false);
    unset($_POST['deleted_question_ids'], $_POST['deleted_answer_ids'], $_POST['deleted_temp_mask_values']);

    $base_payload = [
        'ID' => $quiz_id,
        'post_title' => $prefix . '_quiz',
        'post_content' => $prefix . '_desc',
        'quiz_option' => ['passing_grade' => 80],
    ];

    // ------------------------------------------------------------------
    // Hop 2a: no_change save. Tutor must not rewrite any question row.
    // ------------------------------------------------------------------
    $before_no_change = [];
    foreach ($question_ids as $key => $qid) {
        $before_no_change[$key] = $read_row($qid);
    }

    $no_change_questions = [];
    $order = 0;
    foreach ($question_ids as $key => $qid) {
        $order++;
        $no_change_questions[] = $serialize_question($loaded[$qid], 'no_change', $order);
    }

    $result = $builder->save_quiz($topic_id, wp_slash(array_merge($base_payload, ['questions' => $no_change_questions])));
    $assert(is_object($result) && !empty($result->success), 'no_change save_quiz failed.');

    foreach ($question_ids as $key => $qid) {
        $after = $read_row($qid);
        $assert(
            maybe_unserialize($after->question_settings) == maybe_unserialize($before_no_change[$key]->question_settings),
            "no_change save altered settings for {$key}."
        );
        $assert(
            (string) $after->question_title === (string) $before_no_change[$key]->question_title,
            "no_change save altered the title for {$key}."
        );
        $assert(
            (string) $after->content_id === (string) $before_no_change[$key]->content_id,
            "no_change save altered content_id for {$key}."
        );
    }

    // ------------------------------------------------------------------
    // Hop 2b: edited save. This is the destructive path before Step 3.
    // ------------------------------------------------------------------
    // Tutor 4.0 throws for draw_image/pin_image/scale/puzzle in Legacy mode, so those
    // rows stay no_change there. That guard is Tutor-owned and Step 12 covers it.
    $legacy_blocked = ['draw_image', 'puzzle'];
    $edited_keys    = ['pre40', 'coordinates', 'draw_image', 'puzzle', 'unknown_slug', 'linked', 'h5p'];

    $edited_questions = [];
    $order = 0;
    $actually_edited = [];
    foreach ($question_ids as $key => $qid) {
        $order++;
        $is_edited = in_array($key, $edited_keys, true)
            && !($is_legacy_mode && in_array($key, $legacy_blocked, true));

        if ($is_edited) {
            $actually_edited[] = $key;
            $edited_questions[] = $serialize_question($loaded[$qid], 'update', $order, $prefix . '_' . $key . '_edited');
            continue;
        }

        $edited_questions[] = $serialize_question($loaded[$qid], 'no_change', $order);
    }

    if ($is_legacy_mode) {
        $notes[] = 'legacy mode: draw_image/puzzle update path skipped (Tutor-owned guard)';
    }

    $result = $builder->save_quiz($topic_id, wp_slash(array_merge($base_payload, ['questions' => $edited_questions])));
    $assert(is_object($result) && !empty($result->success), 'Edited save_quiz failed.');

    foreach ($actually_edited as $key) {
        $qid = $question_ids[$key];
        $row = $read_row($qid);
        $assert(
            (string) $row->question_title === $prefix . '_' . $key . '_edited',
            "Edited save did not persist the new title for {$key}."
        );

        $persisted = $read_settings($qid);
        foreach ($fixtures[$key]['settings'] as $setting_key => $expected) {
            $assert(
                array_key_exists($setting_key, $persisted),
                "Edited save erased settings key '{$setting_key}' from {$key}."
            );
        }
        $assert(
            $fixtures[$key]['type'] === (string) $row->question_type,
            "Edited save changed the question_type column for {$key}."
        );
        $assert(
            $fixtures[$key]['type'] === (string) $persisted['question_type'],
            "Edited save wrote a question_type disagreeing with the column for {$key}."
        );
        // Known booleans are normalized to Tutor's 0/1 contract.
        $assert('1' === (string) $persisted['answer_required'], "answer_required is not '1' for {$key}.");
        $assert('0' === (string) $persisted['randomize_question'], "randomize_question is not '0' for {$key}.");
        $assert('1' === (string) $persisted['show_question_mark'], "show_question_mark is not '1' for {$key}.");
    }

    // The native and unknown keys are the whole point: assert exact persisted values.
    $persisted_graph = $read_settings($question_ids['coordinates']);
    $assert(20 === (int) $persisted_graph['coordinates_axis_range'], 'coordinates_axis_range did not survive the save.');
    $assert('preserve-me' === $persisted_graph['tp_future_setting'], 'Unknown settings key did not survive the save.');
    $assert(
        is_array($persisted_graph['tp_future_nested'])
        && 'yes' === $persisted_graph['tp_future_nested']['deep']['keep'],
        'Nested unknown settings did not survive the save.'
    );

    $persisted_unknown = $read_settings($question_ids['unknown_slug']);
    $assert('opaque' === $persisted_unknown['tp_unknown_only_key'], 'Unknown-slug settings key did not survive the save.');

    if (!$is_legacy_mode) {
        $persisted_draw = $read_settings($question_ids['draw_image']);
        $assert(
            85 === (int) $persisted_draw['draw_image_threshold_percent'],
            'draw_image_threshold_percent did not survive the save.'
        );

        $persisted_puzzle = $read_settings($question_ids['puzzle']);
        $assert(6 === (int) $persisted_puzzle['puzzle_grid_size'], 'puzzle_grid_size did not survive the save.');
    }

    // content_id linkage is Tutor-owned and must be untouched by an edit.
    $assert(
        987654 === (int) $read_row($question_ids['linked'])->content_id,
        'Edited save changed content_id on the linked row.'
    );

    // H5P content ID lives in question_description.
    $assert(
        '910042' === (string) $read_row($question_ids['h5p'])->question_description,
        'Edited save changed the H5P question_description/content ID.'
    );

    // Native answer values survive the generic answer path.
    $persisted_answers = $read_answers($question_ids['coordinates']);
    $assert(1 === count($persisted_answers), 'Expected one persisted answer after the edited save.');
    $assert(
        $answer_json === (string) $persisted_answers[0]->answer_two_gap_match,
        'answer_two_gap_match did not survive the save.'
    );
    $assert(
        'scale' === (string) $persisted_answers[0]->answer_view_format,
        'answer_view_format did not survive the save.'
    );

    // ------------------------------------------------------------------
    // Hop 3: reload and confirm the client sees everything it started with.
    // ------------------------------------------------------------------
    $reloaded = $load_via_rest($quiz_id);
    $reloaded_graph = (array) $reloaded[$question_ids['coordinates']]['question_settings'];
    $assert(
        20 === (int) $reloaded_graph['coordinates_axis_range']
        && 'preserve-me' === $reloaded_graph['tp_future_setting'],
        'Native/unknown settings did not survive the full round trip.'
    );
    $assert(
        987654 === (int) $reloaded[$question_ids['linked']]['content_id'],
        'content_id did not survive the full round trip.'
    );

    // ------------------------------------------------------------------
    // Empty deletion state must add no request fields.
    // ------------------------------------------------------------------
    $assert(
        !isset($_POST['deleted_question_ids']) && !isset($_POST['deleted_answer_ids'])
        && !isset($_POST['deleted_temp_mask_values']),
        'A deletion field was posted for empty deletion state.'
    );

    // ------------------------------------------------------------------
    // Tutor's temporary-mask deletion contract, exercised on the installed Tutor.
    // ------------------------------------------------------------------
    // TutorPress hands Tutor a list of abandoned values and never touches the filesystem.
    // That is only safe because Tutor deletes a submitted value exclusively when it
    // resolves to a readable file inside its own uploads/tutor/quiz-images directory.
    // The whole safety case rests on that discrimination, so prove it against the
    // installed Tutor rather than the reference tree, using files created here.
    //
    // Path-traversal values are deliberately not submitted. The guard belongs to Tutor,
    // and a hostile probe Tutor failed would destroy a real file on this site.
    $handle_delete        = new ReflectionMethod('\\TUTOR\\QuizBuilder', 'handle_delete');
    $handle_delete_params = $handle_delete->getParameters();
    $supports_temp_mask_deletion = isset($handle_delete_params[2])
        && 'deleted_temp_mask_values' === $handle_delete_params[2]->getName();

    $upload_dir = wp_upload_dir();
    if (
        $supports_temp_mask_deletion
        && class_exists('\\TUTOR_PRO\\QuizImageStorage')
        && empty($upload_dir['error'])
    ) {
        $uploads_base = trailingslashit($upload_dir['basedir']);
        $quiz_dir     = $uploads_base . \TUTOR_PRO\QuizImageStorage::QUIZ_IMAGES_SUBDIR;
        wp_mkdir_p($quiz_dir);
        $assert(is_dir($quiz_dir), 'Could not prepare the Tutor quiz-images directory.');

        // The submitted file carries a mask prefix so Tutor resolves it by basename. The
        // outside file deliberately carries none, so it resolves through the uploads-URL
        // branch and is rejected by the directory guard rather than by the filename rules.
        $submitted_name = 'draw-mask-' . $prefix . '-submitted.png';
        $bystander_name = 'draw-mask-' . $prefix . '-bystander.png';
        $outside_name   = $prefix . '-outside.png';

        $submitted_path = trailingslashit($quiz_dir) . $submitted_name;
        $bystander_path = trailingslashit($quiz_dir) . $bystander_name;
        $outside_path   = $uploads_base . $outside_name;

        foreach ([$submitted_path, $bystander_path, $outside_path] as $fixture_path) {
            $assert(
                false !== file_put_contents($fixture_path, 'tutorpress-temp-mask-fixture'),
                'Could not write a temporary-mask fixture file.'
            );
            $mask_fixture_files[] = $fixture_path;
        }

        $before_listing = scandir($quiz_dir);
        $assert(is_array($before_listing), 'Could not read the quiz-images directory.');

        // Of the three submitted values only the first names a file inside quiz-images.
        $builder->handle_delete(
            [],
            [],
            [$submitted_name, trailingslashit($upload_dir['baseurl']) . $outside_name, '']
        );

        $assert(!file_exists($submitted_path), 'Tutor did not delete the submitted temporary mask file.');
        $assert(file_exists($bystander_path), 'Tutor deleted a quiz-images file that was never submitted.');
        $assert(file_exists($outside_path), 'Tutor deleted a submitted uploads file outside quiz-images.');

        $after_listing = scandir($quiz_dir);
        $assert(is_array($after_listing), 'Could not re-read the quiz-images directory.');
        $assert(
            [$submitted_name] === array_values(array_diff($before_listing, $after_listing)),
            'Temporary-mask deletion removed more from quiz-images than the one submitted file.'
        );
        $assert(
            [] === array_values(array_diff($after_listing, $before_listing)),
            'Temporary-mask deletion added files to the quiz-images directory.'
        );

        $notes[] = 'temp-mask deletion contract exercised';
    } elseif (!$supports_temp_mask_deletion) {
        // Tutor 3.9.x has the two-argument handle_delete() contract. Step 13 owns the
        // version-boundary runtime check; this preservation script must remain runnable
        // there without passing a field that version does not accept.
        $notes[] = 'temp-mask deletion contract skipped (unsupported Tutor version)';
    } else {
        $notes[] = 'temp-mask deletion contract skipped (Tutor Pro storage unavailable)';
    }

    // ------------------------------------------------------------------
    // Client wiring. Skipped on built installations with no TypeScript source.
    // ------------------------------------------------------------------
    $src_dir = dirname(__DIR__, 2) . '/assets/js/src';
    if (is_dir($src_dir)) {
        $store = (string) file_get_contents($src_dir . '/store/curriculum/index.ts');
        $assert('' !== $store, 'Could not read the curriculum store source.');
        $assert(
            (bool) preg_match('/\.\.\.question_settings,\s*\n\s*question_type:/', $store),
            'Serializer does not spread question_settings before normalizing known keys.'
        );
        $assert(
            false === strpos($store, 'question_answers: question.question_answers.map'),
            'Serializer still reconstructs answer objects.'
        );
        $assert(
            (bool) preg_match('/supportsTempMaskDeletion\s*&&\s*quizData\.deleted_temp_mask_values/', $store),
            'deleted_temp_mask_values is not gated on capability plus explicit form state.'
        );
        $assert(
            (bool) preg_match('/deleted_temp_mask_values\[\$\{index\}\]/', $store),
            'The FormData key is not exactly deleted_temp_mask_values[].'
        );

        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/rest/class-quizzes-controller.php');
        $assert('' !== $controller, 'Could not read the quizzes controller source.');
        $assert(
            (bool) preg_match('/array_merge\(\s*\$question_settings,/', $controller),
            'REST loader does not spread stored settings before normalizing known keys.'
        );
        $assert(
            false === strpos($controller, 'unserialize($question->question_settings)'),
            'REST loader still calls unserialize() without the allowed_classes guard.'
        );
        $notes[] = 'client wiring asserted';
    } else {
        $notes[] = 'client wiring skipped (no TypeScript source)';
    }
} catch (Throwable $e) {
    $failure_message = $e->getMessage();
} finally {
    $cleanup();
}

if ('' !== $failure_message) {
    $fail($failure_message);
}

$suffix = empty($notes) ? '' : ' (' . implode('; ', $notes) . ')';
WP_CLI::log('PASS: Quiz payload preservation round trip is lossless.' . $suffix);
