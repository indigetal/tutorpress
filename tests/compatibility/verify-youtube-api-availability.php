<?php
/**
 * Verify TutorPress's Gutenberg YouTube API availability localization.
 */

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

if (!class_exists('TutorPress_Assets')) {
    $fail('TutorPress assets class is unavailable.');
}

if (!function_exists('set_current_screen')) {
    require_once ABSPATH . 'wp-admin/includes/screen.php';
}

$assert = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$tracked_globals = [
    'current_screen',
    'pagenow',
    'wp_scripts',
    'wp_styles',
];
$original_globals = [];

foreach ($tracked_globals as $global_name) {
    $original_globals[$global_name] = [
        'exists' => array_key_exists($global_name, $GLOBALS),
        'value' => $GLOBALS[$global_name] ?? null,
    ];
}

$failure_message = '';

try {
    $GLOBALS['pagenow'] = 'post.php';
    set_current_screen('lesson');

    $screen = get_current_screen();
    $assert($screen && 'lesson' === $screen->post_type, 'Could not establish a lesson admin screen.');

    unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles']);
    wp_scripts();
    wp_styles();

    TutorPress_Assets::enqueue_admin_assets('post.php');

    $scripts = wp_scripts();
    $handle = 'tutorpress-curriculum-metabox';
    $registered = $scripts->registered[$handle] ?? null;
    $assert($registered instanceof _WP_Dependency, 'TutorPress Gutenberg bundle was not registered.');

    $localized_output = $scripts->get_data($handle, 'data');
    $assert(is_string($localized_output) && '' !== $localized_output, 'TutorPress curriculum localization is empty.');

    $matched = preg_match(
        '/var tutorPressCurriculum = (\{.*?\});/s',
        $localized_output,
        $matches
    );
    $assert(1 === $matched, 'Could not parse tutorPressCurriculum localization.');

    $curriculum = json_decode($matches[1], true);
    $assert(
        is_array($curriculum) && JSON_ERROR_NONE === json_last_error(),
        'tutorPressCurriculum localization is not valid JSON.'
    );

    $assert(
        !array_key_exists('youtubeApiKeyExists', $curriculum),
        'youtubeApiKeyExists was localized as a string-prone scalar.'
    );

    $before_scripts = $scripts->get_data($handle, 'before');
    $assert(is_array($before_scripts), 'TutorPress typed availability assignment is absent.');
    $before_output = implode("\n", $before_scripts);

    $matched = preg_match(
        '/tutorPressCurriculum\.youtubeApiKeyExists = (true|false);/',
        $before_output,
        $matches
    );
    $assert(1 === $matched, 'Could not parse the typed YouTube availability assignment.');

    $curriculum['youtubeApiKeyExists'] = json_decode($matches[1], true);
    $assert(
        is_bool($curriculum['youtubeApiKeyExists']),
        'youtubeApiKeyExists is not a strict boolean.'
    );

    $tutor_options = function_exists('tutor') ? get_option('tutor_option', []) : [];
    $expected = is_array($tutor_options)
        && !empty($tutor_options['lesson_video_duration_youtube_api_key']);

    $assert(
        $expected === $curriculum['youtubeApiKeyExists'],
        'youtubeApiKeyExists does not match Tutor option state.'
    );

    $raw_key = is_array($tutor_options)
        ? ($tutor_options['lesson_video_duration_youtube_api_key'] ?? '')
        : '';

    if (is_string($raw_key) && '' !== $raw_key) {
        $assert(
            false === strpos($localized_output . "\n" . $before_output, $raw_key),
            'Raw YouTube API key was exposed in localized output.'
        );
    }

    $forbidden_dependencies = [
        'tutor-course-builder',
        'tutor-core',
    ];
    $unexpected_dependencies = array_intersect(
        $forbidden_dependencies,
        $registered->deps
    );
    $assert(
        [] === array_values($unexpected_dependencies),
        'TutorPress bundle depends on a Tutor course-builder/core handle.'
    );
} catch (Throwable $exception) {
    $failure_message = $exception->getMessage();
} finally {
    foreach ($original_globals as $global_name => $original) {
        if ($original['exists']) {
            $GLOBALS[$global_name] = $original['value'];
        } else {
            unset($GLOBALS[$global_name]);
        }
    }
}

if ('' !== $failure_message) {
    $fail($failure_message);
}

WP_CLI::log('PASS: TutorPress YouTube API availability localization is valid.');
