<?php
/**
 * Verify TutorPress sidebar discussion nav/template adaptation and asset enqueue.
 *
 * Exercises learning-mode fixtures against the installed Tutor version,
 * asserts successful vs fail-open adaptation, enqueue/dequeue of legacy
 * sidebar JS after Step 5 success, and restores options/query/globals.
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

if (!class_exists('TutorPress_Sidebar_Tabs')) {
    $fail('TutorPress_Sidebar_Tabs is unavailable.');
}

if (!class_exists('TutorPress_Assets')) {
    $fail('TutorPress_Assets is unavailable.');
}

if (!method_exists('TutorPress_Assets', 'maybe_dequeue_legacy_sidebar_tabs_script')) {
    $fail('TutorPress_Assets::maybe_dequeue_legacy_sidebar_tabs_script is unavailable.');
}

if (!defined('TUTOR_VERSION')) {
    $fail('TUTOR_VERSION is undefined.');
}

$discussion_template = 'tutorpress.learning-area.lesson.discussion';
$tutor_comments_template = 'learning-area.lesson.comments';
$is_tutor_4 = version_compare((string) TUTOR_VERSION, '4.0.0', '>=');

$original_tutor_option = get_option('tutor_option', []);
$original_tutorpress_settings = get_option('tutorpress_settings', []);
$original_post = $GLOBALS['post'] ?? null;
$original_wp_query = $GLOBALS['wp_query'] ?? null;
$original_wp_the_query = $GLOBALS['wp_the_query'] ?? null;
$original_wp_scripts = $GLOBALS['wp_scripts'] ?? null;
$original_wp_styles = $GLOBALS['wp_styles'] ?? null;

$failure_message = '';
$discussion_file = '';
$discussion_backup = '';
$lesson_id = 0;

$set_learning_mode = static function ($mode) {
    $option = get_option('tutor_option', []);
    if (!is_array($option)) {
        $option = [];
    }
    if ('' === $mode) {
        unset($option['learning_mode']);
    } else {
        $option['learning_mode'] = $mode;
    }
    update_option('tutor_option', $option);
};

$get_discussion_file = static function () {
    $plugin_path = defined('TUTORPRESS_PATH') ? TUTORPRESS_PATH : dirname(__DIR__, 2) . '/';
    return $plugin_path . 'templates/tutorpress/learning-area/lesson/discussion.php';
};

$restore_discussion_file = static function () use (&$discussion_file, &$discussion_backup) {
    if ('' === $discussion_backup || !file_exists($discussion_backup)) {
        $discussion_backup = '';
        return;
    }

    if ('' !== $discussion_file && !file_exists($discussion_file)) {
        rename($discussion_backup, $discussion_file);
    } else {
        unlink($discussion_backup);
    }

    $discussion_backup = '';
};

$make_discussion_unreadable = static function () use ($get_discussion_file, &$discussion_file, &$discussion_backup, $assert) {
    $discussion_file = $get_discussion_file();
    $assert(is_readable($discussion_file), 'Discussion template must exist before unreadable fixture.');
    $discussion_backup = $discussion_file . '.verify-bak';
    $renamed = rename($discussion_file, $discussion_backup);
    $assert($renamed, 'Could not rename discussion template for unreadable fixture.');
};

$assert_keys_equal = static function ($expected, $actual, $label) use ($assert) {
    $assert(is_array($actual), "{$label}: result is not an array.");
    $assert(array_keys($expected) === array_keys($actual), "{$label}: nav keys/order changed.");
};

$run_nav = static function (array $input) {
    return TutorPress_Sidebar_Tabs::filter_nav_items($input);
};

$assert_template_gate = static function ($expected_should_load, $template, $label) use ($assert) {
    $result = TutorPress_Sidebar_Tabs::should_load_tutor_template(true, $template, []);
    $assert(
        $expected_should_load === $result,
        "{$label}: should_load_tutor_template({$template}) expected "
        . ($expected_should_load ? 'true' : 'false')
        . ' got ' . ($result ? 'true' : 'false')
    );
};

$reset_asset_registries = static function () {
    unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles']);
    wp_scripts();
    wp_styles();
};

$assert_localization_attached = static function ($label) use ($assert) {
    $data = wp_scripts()->get_data('tutorpress-sidebar-tabs', 'data');
    $assert(
        is_string($data) && false !== strpos($data, 'TutorPressSidebar'),
        "{$label}: TutorPressSidebar localization must remain attached to the registered handle."
    );
};

/**
 * Enqueue lesson assets, apply nav adaptation, run late dequeue, then assert handles.
 *
 * @param string               $mode            Learning mode option value.
 * @param array                $nav_input       Nav items passed to filter_nav_items.
 * @param bool                 $expect_script   Whether sidebar script should remain enqueued.
 * @param string               $label           Assertion label prefix.
 * @param callable|null        $before_nav      Optional callback before nav filter (e.g. unreadable template).
 * @param callable|null        $after_nav       Optional callback after assertions (e.g. restore template).
 */
$run_enqueue_fixture = static function (
    $mode,
    array $nav_input,
    $expect_script,
    $label,
    $before_nav = null,
    $after_nav = null
) use (
    $set_learning_mode,
    $reset_asset_registries,
    $run_nav,
    $assert,
    $assert_localization_attached
) {
    $set_learning_mode($mode);
    $reset_asset_registries();

    TutorPress_Assets::enqueue_lesson_assets();

    $assert(
        wp_style_is('tutorpress-comments-style', 'enqueued'),
        "{$label}: comments style must be enqueued after enqueue_lesson_assets."
    );
    $assert(
        wp_script_is('tutorpress-sidebar-tabs', 'enqueued'),
        "{$label}: sidebar script must be enqueued before late dequeue."
    );
    $assert_localization_attached("{$label} (pre-dequeue)");

    if (is_callable($before_nav)) {
        $before_nav();
    }

    try {
        $run_nav($nav_input);
        TutorPress_Assets::maybe_dequeue_legacy_sidebar_tabs_script();

        $assert(
            wp_style_is('tutorpress-comments-style', 'enqueued'),
            "{$label}: comments style must remain enqueued after late dequeue."
        );

        if ($expect_script) {
            $assert(
                wp_script_is('tutorpress-sidebar-tabs', 'enqueued'),
                "{$label}: sidebar script must remain enqueued."
            );
            $assert(
                !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
                "{$label}: adaptation must be unsuccessful when script is retained."
            );
        } else {
            $assert(
                TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
                "{$label}: adaptation must succeed before script suppression."
            );
            $assert(
                !wp_script_is('tutorpress-sidebar-tabs', 'enqueued'),
                "{$label}: sidebar script must be dequeued after successful adaptation."
            );
            // Localization remains on the registered handle; print is gated by enqueue.
            $assert_localization_attached("{$label} (post-dequeue)");
        }
    } finally {
        if (is_callable($after_nav)) {
            $after_nav();
        }
    }
};

try {
    if ($is_tutor_4) {
        // --- Tutor 4.0 legacy: remove comments (pre-change legacy path) ---
        $set_learning_mode('legacy');
        $input = [
            'overview' => [
                'label'    => 'Overview',
                'value'    => 'overview',
                'icon'     => 'document-text',
                'template' => 'single.lesson.parts.overview',
            ],
            'comments' => [
                'label'    => 'Comments',
                'value'    => 'comments',
                'icon'     => 'comment',
                'template' => 'single.lesson.parts.comments',
            ],
        ];
        $result = $run_nav($input);
        $assert(!isset($result['comments']), '4.0 legacy: comments nav item should be removed.');
        $assert(isset($result['overview']), '4.0 legacy: overview should remain.');
        $assert(
            !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
            '4.0 legacy: adaptation must be unsuccessful.'
        );
        $assert_template_gate(false, 'single.lesson.parts.comments', '4.0 legacy');

        // Shared successful modern/kids fixture helper.
        $valid_modern_nav = [
            'overview' => [
                'id'       => 'overview',
                'label'    => 'Overview',
                'icon'     => 'courses',
                'template' => 'learning-area.lesson.overview',
                'extra'    => 'keep-me',
            ],
            'comments' => [
                'id'       => 'comments',
                'label'    => 'Comments',
                'icon'     => 'comments',
                'template' => $tutor_comments_template,
                'extra'    => 'preserve',
            ],
        ];

        foreach (['modern', 'kids'] as $mode) {
            $set_learning_mode($mode);
            $result = $run_nav($valid_modern_nav);
            $assert_keys_equal($valid_modern_nav, $result, "4.0 {$mode} success");
            $assert(isset($result['comments']), "4.0 {$mode}: comments item missing.");
            $comments = $result['comments'];
            $assert('comments' === $comments['id'], "4.0 {$mode}: id must remain comments.");
            $assert('comments' === $comments['icon'], "4.0 {$mode}: icon must be preserved.");
            $assert('preserve' === $comments['extra'], "4.0 {$mode}: unrelated keys must be preserved.");
            $assert('Discussion' === $comments['label'], "4.0 {$mode}: label must be Discussion.");
            $assert(
                $discussion_template === $comments['template'],
                "4.0 {$mode}: template must be TutorPress discussion."
            );
            $assert(
                'Overview' === $result['overview']['label'],
                "4.0 {$mode}: unrelated overview must be unchanged."
            );
            $assert(
                TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
                "4.0 {$mode}: adaptation must succeed."
            );
            $assert_template_gate(false, $tutor_comments_template, "4.0 {$mode} success");
        }

        // Fail-open: missing id.
        $set_learning_mode('modern');
        $missing_id = [
            'comments' => [
                'label'    => 'Comments',
                'icon'     => 'comments',
                'template' => $tutor_comments_template,
            ],
        ];
        $result = $run_nav($missing_id);
        $assert($missing_id === $result, '4.0 missing-id: nav must be unchanged.');
        $assert(
            !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
            '4.0 missing-id: adaptation must be unsuccessful.'
        );
        $assert_template_gate(true, $tutor_comments_template, '4.0 missing-id');

        // Fail-open: missing template.
        $missing_template = [
            'comments' => [
                'id'    => 'comments',
                'label' => 'Comments',
                'icon'  => 'comments',
            ],
        ];
        $result = $run_nav($missing_template);
        $assert($missing_template === $result, '4.0 missing-template: nav must be unchanged.');
        $assert(
            !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
            '4.0 missing-template: adaptation must be unsuccessful.'
        );
        $assert_template_gate(true, $tutor_comments_template, '4.0 missing-template');

        // Fail-open: unreadable TutorPress discussion template.
        $make_discussion_unreadable();
        try {
            $result = $run_nav($valid_modern_nav);
            $assert($valid_modern_nav === $result, '4.0 unreadable-template: nav must be unchanged.');
            $assert(
                !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
                '4.0 unreadable-template: adaptation must be unsuccessful.'
            );
            $assert_template_gate(true, $tutor_comments_template, '4.0 unreadable-template');
        } finally {
            $restore_discussion_file();
        }

        // Unrelated item only: no adaptation.
        $unrelated = [
            'overview' => [
                'id'       => 'overview',
                'label'    => 'Overview',
                'icon'     => 'courses',
                'template' => 'learning-area.lesson.overview',
            ],
        ];
        $result = $run_nav($unrelated);
        $assert($unrelated === $result, '4.0 unrelated-item: nav must be unchanged.');
        $assert(
            !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
            '4.0 unrelated-item: adaptation must be unsuccessful.'
        );

        // Unknown mode: legacy-oriented removal when comments recognizable by value.
        $set_learning_mode('');
        $unknown = [
            'comments' => [
                'label' => 'Comments',
                'value' => 'comments',
                'icon'  => 'comment',
            ],
        ];
        $result = $run_nav($unknown);
        $assert(!isset($result['comments']), '4.0 unknown-mode: comments should be removed.');
        $assert(
            !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
            '4.0 unknown-mode: adaptation must be unsuccessful.'
        );
        $assert_template_gate(false, 'single.lesson.parts.comments', '4.0 unknown-mode');
    } else {
        // --- Tutor < 4.0 (installed earlier baseline, e.g. 3.9.15): pre-change branches ---
        $legacy_input = [
            'overview' => [
                'label' => 'Overview',
                'value' => 'overview',
                'icon'  => 'document-text',
            ],
            'comments' => [
                'label' => 'Comments',
                'value' => 'comments',
                'icon'  => 'comment',
            ],
        ];

        $set_learning_mode('legacy');
        $result = $run_nav($legacy_input);
        $assert(!isset($result['comments']), '3.9 legacy: comments nav item should be removed.');
        $assert(isset($result['overview']), '3.9 legacy: overview should remain.');
        $assert(
            !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
            '3.9 legacy: Tutor 4.0 adaptation flag must stay false.'
        );
        $assert_template_gate(false, 'single.lesson.parts.comments', '3.9 legacy');

        foreach (['modern', 'kids'] as $mode) {
            $set_learning_mode($mode);
            $result = $run_nav($legacy_input);
            $assert(isset($result['comments']), "3.9 {$mode}: comments item should remain.");
            $comments = $result['comments'];
            $assert('comments' === $comments['id'], "3.9 {$mode}: id must be set to comments.");
            $assert('Discussion' === $comments['label'], "3.9 {$mode}: label must be Discussion.");
            $assert(
                $discussion_template === $comments['template'],
                "3.9 {$mode}: template must be TutorPress discussion."
            );
            $assert('comment' === $comments['icon'], "3.9 {$mode}: icon must be preserved.");
            $assert(
                !TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed(),
                "3.9 {$mode}: Tutor 4.0 adaptation flag must stay false."
            );
            $assert_template_gate(false, 'single.lesson.parts.comments', "3.9 {$mode}");
            $assert_template_gate(false, $tutor_comments_template, "3.9 {$mode} learning-area template");
        }
    }

    // --- Step 6: enqueue / late-dequeue fixtures on a singular lesson query ---
    $settings = get_option('tutorpress_settings', []);
    if (!is_array($settings)) {
        $settings = [];
    }
    $settings['enable_sidebar_tabs'] = true;
    update_option('tutorpress_settings', $settings);

    $prefix = 'tp_sidebar_verify_' . wp_generate_password(6, false, false);
    $lesson_id = wp_insert_post([
        'post_title'  => $prefix . ' lesson',
        'post_type'   => 'lesson',
        'post_status' => 'publish',
        'post_content'=> 'Sidebar discussion verify fixture.',
    ], true);
    $assert(!is_wp_error($lesson_id) && $lesson_id > 0, 'Failed to create temporary lesson fixture.');

    $lesson_post = get_post($lesson_id);
    $assert($lesson_post instanceof WP_Post, 'Temporary lesson fixture is missing.');

    $query = new WP_Query([
        'p'         => $lesson_id,
        'post_type' => 'lesson',
    ]);
    $GLOBALS['wp_the_query'] = $query;
    $GLOBALS['wp_query'] = $query;
    $query->is_singular = true;
    $query->is_single = true;
    $query->is_page = false;
    $query->queried_object = $lesson_post;
    $query->queried_object_id = $lesson_id;
    if ($query->have_posts()) {
        $query->the_post();
    } else {
        $GLOBALS['post'] = $lesson_post;
        setup_postdata($lesson_post);
    }

    $assert(is_singular('lesson'), 'Could not establish a singular lesson query context.');

    $legacy_nav = [
        'comments' => [
            'label'    => 'Comments',
            'value'    => 'comments',
            'icon'     => 'comment',
            'template' => 'single.lesson.parts.comments',
        ],
    ];
    $valid_modern_nav = [
        'comments' => [
            'id'       => 'comments',
            'label'    => 'Comments',
            'icon'     => 'comments',
            'template' => $tutor_comments_template,
        ],
    ];
    $missing_id_nav = [
        'comments' => [
            'label'    => 'Comments',
            'icon'     => 'comments',
            'template' => $tutor_comments_template,
        ],
    ];
    $unknown_nav = [
        'comments' => [
            'label' => 'Comments',
            'value' => 'comments',
            'icon'  => 'comment',
        ],
    ];

    if ($is_tutor_4) {
        $run_enqueue_fixture('legacy', $legacy_nav, true, '4.0 enqueue legacy');
        $run_enqueue_fixture('modern', $valid_modern_nav, false, '4.0 enqueue modern success');
        $run_enqueue_fixture('kids', $valid_modern_nav, false, '4.0 enqueue kids success');
        $run_enqueue_fixture('modern', $missing_id_nav, true, '4.0 enqueue modern fail-open');
        $run_enqueue_fixture(
            'modern',
            $valid_modern_nav,
            true,
            '4.0 enqueue unreadable-template',
            $make_discussion_unreadable,
            $restore_discussion_file
        );
        $run_enqueue_fixture('', $unknown_nav, true, '4.0 enqueue unknown-mode');
    } else {
        $run_enqueue_fixture('legacy', $legacy_nav, true, '3.9 enqueue legacy');
        $run_enqueue_fixture('modern', $legacy_nav, true, '3.9 enqueue modern');
        $run_enqueue_fixture('kids', $legacy_nav, true, '3.9 enqueue kids');
    }
} catch (Throwable $e) {
    $failure_message = $e->getMessage();
} finally {
    $restore_discussion_file();
    update_option('tutor_option', $original_tutor_option);
    update_option('tutorpress_settings', $original_tutorpress_settings);

    if ($lesson_id > 0) {
        wp_delete_post($lesson_id, true);
    }

    if (null === $original_wp_query) {
        unset($GLOBALS['wp_query']);
    } else {
        $GLOBALS['wp_query'] = $original_wp_query;
    }

    if (null === $original_wp_the_query) {
        unset($GLOBALS['wp_the_query']);
    } else {
        $GLOBALS['wp_the_query'] = $original_wp_the_query;
    }

    if (null === $original_post) {
        unset($GLOBALS['post']);
    } else {
        $GLOBALS['post'] = $original_post;
        if ($original_post instanceof WP_Post) {
            setup_postdata($original_post);
        }
    }

    if (null === $original_wp_scripts) {
        unset($GLOBALS['wp_scripts']);
    } else {
        $GLOBALS['wp_scripts'] = $original_wp_scripts;
    }

    if (null === $original_wp_styles) {
        unset($GLOBALS['wp_styles']);
    } else {
        $GLOBALS['wp_styles'] = $original_wp_styles;
    }
}

if ('' !== $failure_message) {
    $fail($failure_message);
}

$version_label = $is_tutor_4 ? 'Tutor 4.0+' : 'Tutor < 4.0';
WP_CLI::log("PASS: Sidebar discussion adaptation + assets ({$version_label} " . TUTOR_VERSION . ').');
