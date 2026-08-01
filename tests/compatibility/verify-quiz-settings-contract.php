<?php
/**
 * Verify quiz REST Pro `_content_drip_settings` surface (Step 12E).
 * 12E.1: standard absent / present-empty / Pro-present partial / nested-only.
 * 12E.2: conflicting Pro-vs-nested + H5P-identified absent / empty / Pro-present.
 * Temporary fixtures only; cleanup in finally. Never mutates existing site content.
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

$prefix = 'tp_qset_' . wp_generate_password(8, false, false);
$original_user_id = get_current_user_id();
$course_id = 0;
$topic_id = 0;
$quiz_ids = [];
$failure_message = '';

$cleanup = static function () use (&$course_id, &$topic_id, &$quiz_ids, $original_user_id) {
    wp_set_current_user($original_user_id);
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

try {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID']]);
    $assert(!empty($admins), 'No administrator available.');
    $admin_id = (int) $admins[0]->ID;
    wp_set_current_user($admin_id);

    $tutor = tutor();
    $course_post_type = is_string($tutor->course_post_type) ? $tutor->course_post_type : 'courses';
    $topic_post_type = is_string($tutor->topics_post_type) ? $tutor->topics_post_type : 'topics';
    $quiz_post_type = is_string($tutor->quiz_post_type) ? $tutor->quiz_post_type : 'tutor_quiz';

    $course_id = wp_insert_post(['post_type' => $course_post_type, 'post_title' => $prefix . '_course', 'post_status' => 'publish', 'post_author' => $admin_id], true);
    $assert(!is_wp_error($course_id) && $course_id > 0, 'Failed to create course.');
    $topic_id = wp_insert_post(['post_type' => $topic_post_type, 'post_title' => $prefix . '_topic', 'post_status' => 'publish', 'post_parent' => $course_id, 'post_author' => $admin_id], true);
    $assert(!is_wp_error($topic_id) && $topic_id > 0, 'Failed to create topic.');

    $make_quiz = static function ($suffix, array $quiz_option) use (&$quiz_ids, $assert, $prefix, $quiz_post_type, $topic_id, $admin_id) {
        $quiz_id = wp_insert_post([
            'post_type' => $quiz_post_type,
            'post_title' => $prefix . '_' . $suffix,
            'post_status' => 'publish',
            'post_parent' => $topic_id,
            'post_author' => $admin_id,
            'menu_order' => count($quiz_ids) + 1,
        ], true);
        $assert(!is_wp_error($quiz_id) && $quiz_id > 0, 'Failed to create quiz ' . $suffix);
        $quiz_ids[] = (int) $quiz_id;
        update_post_meta((int) $quiz_id, 'tutor_quiz_option', $quiz_option);
        return (int) $quiz_id;
    };

    $nested = [
        'passing_grade' => 80,
        'content_drip_settings' => ['unlock_date' => 'nested-date', 'after_xdays_of_enroll' => 3, 'prerequisites' => [11]],
    ];
    $absent_id = $make_quiz('absent', $nested);
    $absent = $load($absent_id);
    $assert(false === $absent['has_pro_content_drip_settings'] && [] === $absent['pro_content_drip_settings'], 'Absent Pro meta mismatch.');
    $assert($nested === $absent['quiz_option'] && !metadata_exists('post', $absent_id, '_content_drip_settings'), 'Absent/nested-only mismatch.');

    $empty_id = $make_quiz('empty', ['passing_grade' => 70]);
    update_post_meta($empty_id, '_content_drip_settings', []);
    $empty = $load($empty_id);
    $assert(true === $empty['has_pro_content_drip_settings'] && [] === $empty['pro_content_drip_settings'], 'Empty Pro meta mismatch.');
    $assert(!isset($empty['quiz_option']['_content_drip_settings']), 'Pro meta merged into quiz_option.');

    $pro_meta = ['unlock_date' => '2026-08-01', 'future_pro' => 'keep'];
    $pro_id = $make_quiz('pro_partial', [
        'passing_grade' => 75,
        'content_drip_settings' => ['unlock_date' => 'nested-other', 'after_xdays_of_enroll' => 9],
    ]);
    update_post_meta($pro_id, '_content_drip_settings', $pro_meta);
    $pro = $load($pro_id);
    $assert(true === $pro['has_pro_content_drip_settings'] && $pro_meta === $pro['pro_content_drip_settings'], 'Pro-present mismatch.');
    $assert('nested-other' === ($pro['quiz_option']['content_drip_settings']['unlock_date'] ?? null), 'Pro case altered nested drip.');

    // Conflicting Pro vs nested: both surfaces returned separately (Pro member ≠ nested).
    $conflict_nested = ['unlock_date' => 'nested-conflict', 'after_xdays_of_enroll' => 4, 'prerequisites' => [21]];
    $conflict_pro = ['unlock_date' => 'pro-conflict', 'after_xdays_of_enroll' => 8, 'unknown_pro' => 'keep'];
    $conflict_option = ['passing_grade' => 60, 'content_drip_settings' => $conflict_nested];
    $conflict_id = $make_quiz('conflict', $conflict_option);
    update_post_meta($conflict_id, '_content_drip_settings', $conflict_pro);
    $conflict = $load($conflict_id);
    $assert(true === $conflict['has_pro_content_drip_settings'] && $conflict_pro === $conflict['pro_content_drip_settings'], 'Conflict Pro member mismatch.');
    $assert($conflict_nested === ($conflict['quiz_option']['content_drip_settings'] ?? null), 'Conflict altered nested quiz_option drip.');
    $assert($conflict['pro_content_drip_settings'] !== $conflict['quiz_option']['content_drip_settings'], 'Conflict must keep Pro and nested surfaces distinct.');

    // H5P-identified quizzes: same REST presence contract (identity is quiz_option only).
    $h5p_absent_option = array_merge($nested, ['quiz_type' => 'tutor_h5p_quiz']);
    $h5p_absent_id = $make_quiz('h5p_absent', $h5p_absent_option);
    $h5p_absent = $load($h5p_absent_id);
    $assert(false === $h5p_absent['has_pro_content_drip_settings'] && [] === $h5p_absent['pro_content_drip_settings'], 'H5P absent Pro meta mismatch.');
    $assert($h5p_absent_option === $h5p_absent['quiz_option'], 'H5P absent altered quiz_option.');

    $h5p_empty_id = $make_quiz('h5p_empty', ['passing_grade' => 70, 'quiz_type' => 'tutor_h5p_quiz']);
    update_post_meta($h5p_empty_id, '_content_drip_settings', []);
    $h5p_empty = $load($h5p_empty_id);
    $assert(true === $h5p_empty['has_pro_content_drip_settings'] && [] === $h5p_empty['pro_content_drip_settings'], 'H5P empty Pro meta mismatch.');
    $assert('tutor_h5p_quiz' === ($h5p_empty['quiz_option']['quiz_type'] ?? null), 'H5P empty lost quiz_type.');

    $h5p_pro_meta = ['unlock_date' => 'h5p-pro-date', 'future_pro' => 'h5p-keep'];
    $h5p_pro_id = $make_quiz('h5p_pro', [
        'passing_grade' => 75,
        'quiz_type' => 'tutor_h5p_quiz',
        'content_drip_settings' => ['unlock_date' => 'h5p-nested', 'after_xdays_of_enroll' => 2],
    ]);
    update_post_meta($h5p_pro_id, '_content_drip_settings', $h5p_pro_meta);
    $h5p_pro = $load($h5p_pro_id);
    $assert(true === $h5p_pro['has_pro_content_drip_settings'] && $h5p_pro_meta === $h5p_pro['pro_content_drip_settings'], 'H5P Pro-present mismatch.');
    $assert('h5p-nested' === ($h5p_pro['quiz_option']['content_drip_settings']['unlock_date'] ?? null), 'H5P Pro case altered nested drip.');

    fwrite(STDOUT, "PASS: quiz settings contract REST drip surface (12E.1–12E.2).\n");
} catch (Throwable $e) {
    $failure_message = $e->getMessage();
} finally {
    $cleanup();
}

if ('' !== $failure_message) {
    $fail($failure_message);
}
