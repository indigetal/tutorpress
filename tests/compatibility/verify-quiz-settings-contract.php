<?php
/**
 * Verify quiz settings persistence contract: REST Pro drip load surface, Tutor
 * save_quiz dual-storage drip writes, identity/max round-trips, raw-versus-
 * normalized options, and H5P result/statement preservation on settings-only saves.
 *
 * Temporary fixtures only; cleanup in finally. Never mutates existing site content.
 * Does not claim external H5P xAPI emission or shortcode markup.
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

if (!function_exists('tutor') || !is_object(tutor()) || !class_exists('TutorPress_REST_Quizzes_Controller')) {
    $fail('Tutor or TutorPress quizzes REST controller unavailable.');
}

if (!class_exists('\\TUTOR\\QuizBuilder')) {
    $fail('Tutor QuizBuilder is unavailable.');
}

global $wpdb;

$prefix           = 'tp_qset_' . wp_generate_password(8, false, false);
$original_user_id = get_current_user_id();
$original_post    = isset($_POST) && is_array($_POST) ? $_POST : [];
$course_id        = 0;
$topic_id         = 0;
$quiz_ids         = [];
$h5p_result_ids   = [];
$h5p_statement_ids = [];
$failure_message  = '';
$notes            = [];

$cleanup = static function () use (
    &$course_id,
    &$topic_id,
    &$quiz_ids,
    &$h5p_result_ids,
    &$h5p_statement_ids,
    $original_user_id,
    $original_post,
    $wpdb
) {
    $_POST = $original_post;
    wp_set_current_user($original_user_id);

    foreach ($h5p_statement_ids as $statement_id) {
        if ((int) $statement_id > 0) {
            $wpdb->delete($wpdb->prefix . 'tutor_h5p_quiz_statement', ['statement_id' => (int) $statement_id], ['%d']);
        }
    }
    foreach ($h5p_result_ids as $result_id) {
        if ((int) $result_id > 0) {
            $wpdb->delete($wpdb->prefix . 'tutor_h5p_quiz_result', ['result_id' => (int) $result_id], ['%d']);
        }
    }
    foreach ($quiz_ids as $quiz_id) {
        if ((int) $quiz_id > 0) {
            wp_delete_post((int) $quiz_id, true);
        }
    }
    if ($topic_id > 0) {
        wp_delete_post($topic_id, true);
    }
    if ($course_id > 0) {
        wp_delete_post($course_id, true);
    }
};

$load = static function ($quiz_id) use ($assert) {
    $request = new WP_REST_Request('GET', '/tutorpress/v1/quizzes/' . (int) $quiz_id);
    $request->set_param('id', (int) $quiz_id);
    $response = (new TutorPress_REST_Quizzes_Controller())->get_item($request);
    $assert(!is_wp_error($response), 'get_item WP_Error for quiz ' . (int) $quiz_id);
    $data = $response->get_data()['data'] ?? null;
    $assert(
        is_array($data)
            && array_key_exists('has_pro_content_drip_settings', $data)
            && array_key_exists('pro_content_drip_settings', $data)
            && is_array($data['quiz_option'] ?? null),
        'Missing Pro drip members or quiz_option.'
    );
    return $data;
};

$raw_option = static function ($quiz_id) {
    $raw = get_post_meta((int) $quiz_id, 'tutor_quiz_option', true);
    return is_array($raw) ? $raw : [];
};

$pro_meta = static function ($quiz_id) {
    if (!metadata_exists('post', (int) $quiz_id, '_content_drip_settings')) {
        return null;
    }
    $meta = get_post_meta((int) $quiz_id, '_content_drip_settings', true);
    return is_array($meta) ? $meta : [];
};

// Tutor's Input::sanitize_array stringifies scalars; compare durable shape, not PHP types.
$same_shape = static function ($expected, $actual) use (&$same_shape) {
    if (!is_array($expected) || !is_array($actual)) {
        return (string) $expected === (string) $actual;
    }
    if (count($expected) !== count($actual)) {
        return false;
    }
    foreach ($expected as $key => $value) {
        if (!array_key_exists($key, $actual)) {
            return false;
        }
        if (is_array($value)) {
            if (!$same_shape($value, $actual[$key])) {
                return false;
            }
            continue;
        }
        if ((string) $value !== (string) $actual[$key]) {
            return false;
        }
    }
    return true;
};

$save_quiz = static function ($quiz_id, array $quiz_option, $top_level_drip = null) use ($assert, &$topic_id, $prefix) {
    unset($_POST['content_drip_settings']);
    if (null !== $top_level_drip) {
        $_POST['content_drip_settings'] = $top_level_drip;
    }

    $builder = new \TUTOR\QuizBuilder(false);
    $result  = $builder->save_quiz(
        $topic_id,
        wp_slash(
            [
                'ID'           => (int) $quiz_id,
                'post_title'   => $prefix . '_persist',
                'post_content' => $prefix . '_desc',
                'quiz_option'  => $quiz_option,
                'questions'    => [],
            ]
        )
    );
    $assert(is_object($result) && !empty($result->success), 'save_quiz failed for quiz ' . (int) $quiz_id);
    unset($_POST['content_drip_settings']);
};

try {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID']]);
    $assert(!empty($admins), 'No administrator available.');
    $admin_id = (int) $admins[0]->ID;
    wp_set_current_user($admin_id);

    $tutor            = tutor();
    $course_post_type = is_string($tutor->course_post_type) ? $tutor->course_post_type : 'courses';
    $topic_post_type  = is_string($tutor->topics_post_type) ? $tutor->topics_post_type : 'topics';
    $quiz_post_type   = is_string($tutor->quiz_post_type) ? $tutor->quiz_post_type : 'tutor_quiz';

    $course_id = wp_insert_post(['post_type' => $course_post_type, 'post_title' => $prefix . '_course', 'post_status' => 'publish', 'post_author' => $admin_id], true);
    $assert(!is_wp_error($course_id) && $course_id > 0, 'Failed to create course.');
    $topic_id = wp_insert_post(['post_type' => $topic_post_type, 'post_title' => $prefix . '_topic', 'post_status' => 'publish', 'post_parent' => $course_id, 'post_author' => $admin_id], true);
    $assert(!is_wp_error($topic_id) && $topic_id > 0, 'Failed to create topic.');

    $make_quiz = static function ($suffix, array $quiz_option) use (&$quiz_ids, $assert, $prefix, $quiz_post_type, $topic_id, $admin_id) {
        $quiz_id = wp_insert_post([
            'post_type'    => $quiz_post_type,
            'post_title'   => $prefix . '_' . $suffix,
            'post_status'  => 'publish',
            'post_parent'  => $topic_id,
            'post_author'  => $admin_id,
            'menu_order'   => count($quiz_ids) + 1,
        ], true);
        $assert(!is_wp_error($quiz_id) && $quiz_id > 0, 'Failed to create quiz ' . $suffix);
        $quiz_ids[] = (int) $quiz_id;
        update_post_meta((int) $quiz_id, 'tutor_quiz_option', $quiz_option);
        return (int) $quiz_id;
    };

    // ------------------------------------------------------------------
    // REST Pro drip load surface (standard + H5P identity).
    // ------------------------------------------------------------------
    $nested = [
        'passing_grade'         => 80,
        'content_drip_settings' => ['unlock_date' => 'nested-date', 'after_xdays_of_enroll' => 3, 'prerequisites' => [11]],
    ];
    $absent_id = $make_quiz('absent', $nested);
    $absent    = $load($absent_id);
    $assert(false === $absent['has_pro_content_drip_settings'] && [] === $absent['pro_content_drip_settings'], 'Absent Pro meta mismatch.');
    $assert($nested === $absent['quiz_option'] && !metadata_exists('post', $absent_id, '_content_drip_settings'), 'Absent/nested-only mismatch.');

    $empty_id = $make_quiz('empty', ['passing_grade' => 70]);
    update_post_meta($empty_id, '_content_drip_settings', []);
    $empty = $load($empty_id);
    $assert(true === $empty['has_pro_content_drip_settings'] && [] === $empty['pro_content_drip_settings'], 'Empty Pro meta mismatch.');
    $assert(!isset($empty['quiz_option']['_content_drip_settings']), 'Pro meta merged into quiz_option.');

    $pro_seed = ['unlock_date' => '2026-08-01', 'future_pro' => 'keep'];
    $pro_id   = $make_quiz('pro_partial', [
        'passing_grade'         => 75,
        'content_drip_settings' => ['unlock_date' => 'nested-other', 'after_xdays_of_enroll' => 9],
    ]);
    update_post_meta($pro_id, '_content_drip_settings', $pro_seed);
    $pro = $load($pro_id);
    $assert(true === $pro['has_pro_content_drip_settings'] && $pro_seed === $pro['pro_content_drip_settings'], 'Pro-present mismatch.');
    $assert('nested-other' === ($pro['quiz_option']['content_drip_settings']['unlock_date'] ?? null), 'Pro case altered nested drip.');

    $conflict_nested = ['unlock_date' => 'nested-conflict', 'after_xdays_of_enroll' => 4, 'prerequisites' => [21]];
    $conflict_pro    = ['unlock_date' => 'pro-conflict', 'after_xdays_of_enroll' => 8, 'unknown_pro' => 'keep'];
    $conflict_id     = $make_quiz('conflict', ['passing_grade' => 60, 'content_drip_settings' => $conflict_nested]);
    update_post_meta($conflict_id, '_content_drip_settings', $conflict_pro);
    $conflict = $load($conflict_id);
    $assert(true === $conflict['has_pro_content_drip_settings'] && $conflict_pro === $conflict['pro_content_drip_settings'], 'Conflict Pro member mismatch.');
    $assert($conflict_nested === ($conflict['quiz_option']['content_drip_settings'] ?? null), 'Conflict altered nested quiz_option drip.');
    $assert($conflict['pro_content_drip_settings'] !== $conflict['quiz_option']['content_drip_settings'], 'Conflict must keep Pro and nested surfaces distinct.');

    $h5p_absent_option = array_merge($nested, ['quiz_type' => 'tutor_h5p_quiz']);
    $h5p_absent_id     = $make_quiz('h5p_absent', $h5p_absent_option);
    $h5p_absent        = $load($h5p_absent_id);
    $assert(false === $h5p_absent['has_pro_content_drip_settings'] && [] === $h5p_absent['pro_content_drip_settings'], 'H5P absent Pro meta mismatch.');
    $assert($h5p_absent_option === $h5p_absent['quiz_option'], 'H5P absent altered quiz_option.');

    $h5p_empty_id = $make_quiz('h5p_empty', ['passing_grade' => 70, 'quiz_type' => 'tutor_h5p_quiz']);
    update_post_meta($h5p_empty_id, '_content_drip_settings', []);
    $h5p_empty = $load($h5p_empty_id);
    $assert(true === $h5p_empty['has_pro_content_drip_settings'] && [] === $h5p_empty['pro_content_drip_settings'], 'H5P empty Pro meta mismatch.');
    $assert('tutor_h5p_quiz' === ($h5p_empty['quiz_option']['quiz_type'] ?? null), 'H5P empty lost quiz_type.');

    $h5p_pro_seed = ['unlock_date' => 'h5p-pro-date', 'future_pro' => 'h5p-keep'];
    $h5p_pro_id   = $make_quiz('h5p_pro', [
        'passing_grade'         => 75,
        'quiz_type'             => 'tutor_h5p_quiz',
        'content_drip_settings' => ['unlock_date' => 'h5p-nested', 'after_xdays_of_enroll' => 2],
    ]);
    update_post_meta($h5p_pro_id, '_content_drip_settings', $h5p_pro_seed);
    $h5p_pro = $load($h5p_pro_id);
    $assert(true === $h5p_pro['has_pro_content_drip_settings'] && $h5p_pro_seed === $h5p_pro['pro_content_drip_settings'], 'H5P Pro-present mismatch.');
    $assert('h5p-nested' === ($h5p_pro['quiz_option']['content_drip_settings']['unlock_date'] ?? null), 'H5P Pro case altered nested drip.');

    // ------------------------------------------------------------------
    // Dual-storage persistence via Tutor save_quiz + Pro drip hook.
    // ------------------------------------------------------------------
    $assert(
        has_action('tutor_quiz_settings_updated'),
        'Pro Content Drip hook (tutor_quiz_settings_updated) is not registered; cannot verify dual-storage writes.'
    );

    $persist_id = $make_quiz('persist_drip', [
        'passing_grade'         => 80,
        'content_drip_settings' => [
            'unlock_date'           => 'old-nested',
            'after_xdays_of_enroll' => 9,
            'prerequisites'         => [11],
            'future_nested'         => 'keep-me',
        ],
    ]);
    update_post_meta($persist_id, '_content_drip_settings', ['unlock_date' => 'old-pro', 'future_pro' => 'replace-me']);

    $active_nested = [
        'unlock_date'           => '2026-03-15',
        'after_xdays_of_enroll' => 9,
        'prerequisites'         => [11],
        'future_nested'         => 'keep-me',
    ];
    $save_quiz(
        $persist_id,
        ['passing_grade' => 80, 'content_drip_settings' => $active_nested],
        ['unlock_date' => '2026-03-15']
    );
    $assert($same_shape($active_nested, $raw_option($persist_id)['content_drip_settings'] ?? null), 'Active date save lost nested drip (incl. inactive/unknown).');
    $assert($same_shape(['unlock_date' => '2026-03-15'], $pro_meta($persist_id)), 'Active date save did not replace Pro meta with posted active mode.');

    $days_nested = $active_nested;
    $days_nested['after_xdays_of_enroll'] = 5;
    $save_quiz(
        $persist_id,
        ['passing_grade' => 80, 'content_drip_settings' => $days_nested],
        ['after_xdays_of_enroll' => 5]
    );
    $assert(5 === (int) ($raw_option($persist_id)['content_drip_settings']['after_xdays_of_enroll'] ?? -1), 'Days save nested mismatch.');
    $assert($same_shape(['after_xdays_of_enroll' => 5], $pro_meta($persist_id)), 'Days save Pro whole-meta mismatch.');

    $prereq_nested = $days_nested;
    $prereq_nested['prerequisites'] = [10, 20];
    $save_quiz(
        $persist_id,
        ['passing_grade' => 80, 'content_drip_settings' => $prereq_nested],
        ['prerequisites' => [10, 20]]
    );
    $assert($same_shape([10, 20], $raw_option($persist_id)['content_drip_settings']['prerequisites'] ?? null), 'Prereq save nested mismatch.');
    $assert($same_shape(['prerequisites' => [10, 20]], $pro_meta($persist_id)), 'Prereq save Pro whole-meta mismatch.');

    $pro_before_unrelated = $pro_meta($persist_id);
    $save_quiz(
        $persist_id,
        [
            'passing_grade'         => 90,
            'content_drip_settings' => $prereq_nested,
        ],
        null
    );
    $assert(90 === (int) ($raw_option($persist_id)['passing_grade'] ?? 0), 'Unrelated save did not update passing_grade.');
    $assert($same_shape($pro_before_unrelated, $pro_meta($persist_id)), 'Unrelated save without top-level drip altered Pro meta.');

    // ------------------------------------------------------------------
    // Identity + maximum questions + raw vs normalized.
    // ------------------------------------------------------------------
    $identity_id = $make_quiz('identity', [
        'passing_grade'            => 70,
        'quiz_type'                => 'tutor_h5p_quiz',
        'max_questions_for_answer' => 0,
        'feedback_mode'            => 'retry',
        'future_top'               => 'opaque',
    ]);
    $save_quiz(
        $identity_id,
        [
            'passing_grade'            => 70,
            'quiz_type'                => 'tutor_h5p_quiz',
            'max_questions_for_answer' => 4,
            'feedback_mode'            => 'retry',
            'future_top'               => 'opaque',
        ],
        null
    );
    $raw_identity = $raw_option($identity_id);
    $assert('tutor_h5p_quiz' === ($raw_identity['quiz_type'] ?? null), 'Interactive identity lost on save.');
    $assert(4 === (int) ($raw_identity['max_questions_for_answer'] ?? -1), 'Positive max questions did not persist.');
    $assert('opaque' === ($raw_identity['future_top'] ?? null), 'Unknown top-level quiz_option key lost.');
    $assert('retry' === ($raw_identity['feedback_mode'] ?? null), 'Raw feedback_mode must remain unnormalized in storage.');

    $effective = tutor_utils()->get_quiz_option($identity_id);
    $assert(is_array($effective), 'get_quiz_option did not return an array.');
    $assert(
        $raw_identity !== $effective || array_key_exists('limit_attempts_allowed', $effective) || !array_key_exists('feedback_mode', $effective),
        'Expected normalized effective options to differ from raw legacy feedback storage.'
    );

    $save_quiz(
        $identity_id,
        [
            'passing_grade'            => 70,
            'max_questions_for_answer' => 0,
            'feedback_mode'            => 'retry',
            'future_top'               => 'opaque',
            'quiz_type'                => 'custom_unknown',
        ],
        null
    );
    $raw_unknown = $raw_option($identity_id);
    $assert('custom_unknown' === ($raw_unknown['quiz_type'] ?? null), 'Unknown identity must passthrough.');
    $assert(0 === (int) ($raw_unknown['max_questions_for_answer'] ?? -1), 'Zero max questions did not persist.');

    $stale_id = $make_quiz('stale_identity', [
        'passing_grade' => 65,
        'quiz_type'     => 'tutor_h5p_quiz',
    ]);
    // Standard save payload omits the exact stale H5P identity (TutorPress contract).
    $save_quiz($stale_id, ['passing_grade' => 65], null);
    $assert(!array_key_exists('quiz_type', $raw_option($stale_id)), 'Standard save failed to remove exact stale H5P identity.');

    // ------------------------------------------------------------------
    // H5P result/statement preservation on settings-only save (when tables exist).
    // ------------------------------------------------------------------
    $result_table    = $wpdb->prefix . 'tutor_h5p_quiz_result';
    $statement_table = $wpdb->prefix . 'tutor_h5p_quiz_statement';
    $has_h5p_tables  = $result_table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $result_table))
        && $statement_table === $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $statement_table));

    if ($has_h5p_tables) {
        $h5p_save_id = $make_quiz('h5p_rows', [
            'passing_grade' => 80,
            'quiz_type'     => 'tutor_h5p_quiz',
        ]);
        $inserted = $wpdb->insert(
            $result_table,
            [
                'quiz_id'     => $h5p_save_id,
                'attempt_id'  => 900001,
                'question_id' => 900002,
                'user_id'     => $admin_id,
                'content_id'  => 900003,
                'response'    => $prefix . '_result',
                'max_score'   => 10,
                'raw_score'   => 8,
                'completion'  => 1,
                'success'     => 1,
            ],
            ['%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%d']
        );
        $assert(false !== $inserted, 'Failed to seed temporary H5P result row.');
        $h5p_result_ids[] = (int) $wpdb->insert_id;
        $result_before    = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$result_table} WHERE result_id = %d", $h5p_result_ids[0]), ARRAY_A);

        $inserted = $wpdb->insert(
            $statement_table,
            [
                'quiz_id'        => $h5p_save_id,
                'question_id'    => 900002,
                'content_id'     => 900003,
                'user_id'        => $admin_id,
                'verb'           => 'completed',
                'quiz_result_id' => $h5p_result_ids[0],
                'created_at'     => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%d', '%s', '%d', '%s']
        );
        $assert(false !== $inserted, 'Failed to seed temporary H5P statement row.');
        $h5p_statement_ids[] = (int) $wpdb->insert_id;
        $statement_before    = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$statement_table} WHERE statement_id = %d", $h5p_statement_ids[0]), ARRAY_A);

        $save_quiz(
            $h5p_save_id,
            [
                'passing_grade' => 85,
                'quiz_type'     => 'tutor_h5p_quiz',
            ],
            null
        );

        $result_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$result_table} WHERE result_id = %d", $h5p_result_ids[0]), ARRAY_A);
        $statement_after = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$statement_table} WHERE statement_id = %d", $h5p_statement_ids[0]), ARRAY_A);
        $assert($result_before === $result_after, 'Settings-only save altered H5P result row.');
        $assert($statement_before === $statement_after, 'Settings-only save altered H5P statement row.');
        $notes[] = 'H5P result/statement preservation asserted';
    } else {
        $notes[] = 'H5P result/statement tables absent; preservation case skipped';
    }

    $suffix = $notes ? (' (' . implode('; ', $notes) . ')') : '';
    fwrite(STDOUT, "PASS: quiz settings contract persistence matrix.{$suffix}\n");
} catch (Throwable $e) {
    $failure_message = $e->getMessage();
} finally {
    $cleanup();
}

if ('' !== $failure_message) {
    $fail($failure_message);
}
