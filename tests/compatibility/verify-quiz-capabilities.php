<?php
/**
 * Verify TutorPress's authoritative quiz capability contract.
 *
 * Asserts the normalized server contract, its Gutenberg localization, and the
 * version/mode/Pro gating matrix for Tutor 4.0's five native question types.
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

$native_slugs = ['draw_image', 'scale', 'pin_image', 'coordinates', 'puzzle'];
$modern_modes = ['modern', 'kids'];

/**
 * Assert the version-aware Quiz Settings portion of the capability contract.
 */
$assert_settings_contract_shape = static function (array $capabilities) use ($assert) {
    $contract = $capabilities['quizSettingsContract'] ?? null;
    $reason   = $capabilities['quizSettingsUnavailableReason'] ?? null;
    $flags    = [
        'supportsOrthogonalFeedback',
        'supportsSeparatePagination',
        'supportsV4TimingNavigation',
        'supportsLegacyFeedbackLayout',
        'supportsV4QuizContentDrip',
    ];

    $assert(in_array($contract, ['v4', 'legacy', 'unavailable'], true), 'Quiz Settings contract is invalid.');
    $assert(
        is_string($reason)
            && in_array(
                $reason,
                ['', 'tutor_inactive', 'tutor_version_missing', 'unsupported_tutor_version', 'legacy_contract_unavailable'],
                true
            ),
        'Quiz Settings unavailable reason is invalid.'
    );
    $assert(
        ('unavailable' === $contract) === ('' !== $reason),
        'Quiz Settings contract and unavailable reason disagree.'
    );

    $expected_flags = [
        'v4'          => [true, true, true, false, true],
        'legacy'      => [false, false, false, true, false],
        'unavailable' => [false, false, false, false, false],
    ][$contract];

    foreach ($flags as $index => $flag) {
        $assert(
            array_key_exists($flag, $capabilities)
                && is_bool($capabilities[$flag])
                && $expected_flags[$index] === $capabilities[$flag],
            "Quiz Settings capability {$flag} disagrees with contract {$contract}."
        );
    }
};

/**
 * Assert the structural contract every capability object must satisfy.
 */
$assert_contract_shape = static function (array $capabilities) use (
    $assert,
    $native_slugs,
    $modern_modes,
    $assert_settings_contract_shape
) {
    $assert_settings_contract_shape($capabilities);

    $booleans = [
        'tutorActive',
        'meetsSupportedFloor',
        'hasNativeQuizTypes',
        'proActive',
        'proNativeQuizSupport',
        'supportsTempMaskDeletion',
    ];

    foreach ($booleans as $key) {
        $assert(
            array_key_exists($key, $capabilities) && is_bool($capabilities[$key]),
            "Capability field {$key} is not a strict boolean."
        );
    }

    $assert(
        isset($capabilities['tutorVersion']) && is_string($capabilities['tutorVersion']),
        'tutorVersion is not a string.'
    );
    $assert(
        in_array($capabilities['learningMode'] ?? null, ['legacy', 'modern', 'kids', 'unknown'], true),
        'learningMode is not a normalized value.'
    );
    $assert(
        isset($capabilities['questionTypes']) && is_array($capabilities['questionTypes']),
        'questionTypes is not an array.'
    );

    $is_modern_mode = in_array($capabilities['learningMode'], $modern_modes, true);

    foreach ($capabilities['questionTypes'] as $entry) {
        $assert(is_array($entry), 'A question-type entry is not an array.');

        foreach (['slug', 'label'] as $key) {
            $assert(
                isset($entry[$key]) && is_string($entry[$key]) && '' !== $entry[$key],
                "A question-type entry has an empty {$key}."
            );
        }

        foreach (['is_pro', 'registered', 'can_create', 'can_edit_existing'] as $key) {
            $assert(
                array_key_exists($key, $entry) && is_bool($entry[$key]),
                "Question type {$entry['slug']} has a non-boolean {$key}."
            );
        }

        $assert(
            in_array(
                $entry['unavailable_reason'] ?? null,
                ['', 'unsupported_tutor_version', 'pro_required', 'legacy_mode'],
                true
            ),
            "Question type {$entry['slug']} has an unrecognized unavailable_reason."
        );

        $assert(
            $entry['can_create'] === ('' === $entry['unavailable_reason']),
            "Question type {$entry['slug']} disagrees with its unavailable_reason."
        );

        $assert(
            false === strpos($entry['label'], '<'),
            "Question type {$entry['slug']} label contains markup."
        );

        if (!in_array($entry['slug'], $native_slugs, true)) {
            continue;
        }

        $assert(
            $capabilities['hasNativeQuizTypes'],
            "Native type {$entry['slug']} was advertised without the Tutor 4.0 registry contract."
        );
        $assert(
            $entry['is_pro'],
            "Native type {$entry['slug']} is not flagged as Pro."
        );

        $expected_create = $capabilities['meetsSupportedFloor']
            && $capabilities['proNativeQuizSupport']
            && $is_modern_mode;
        $assert(
            $expected_create === $entry['can_create'],
            "Native type {$entry['slug']} create capability does not match the gating matrix."
        );

        $expected_edit = $capabilities['meetsSupportedFloor'] && $is_modern_mode;
        $assert(
            $expected_edit === $entry['can_edit_existing'],
            "Native type {$entry['slug']} edit capability does not match the gating matrix."
        );
    }
};

/**
 * Assert the exact create/edit/reason matrix for every reported type.
 *
 * Precedence is asserted independently of the builder so a reordered guard or a
 * collapsed create/edit distinction fails here.
 */
$assert_capability_matrix = static function (array $capabilities) use ($assert, $native_slugs, $modern_modes) {
    $is_modern_mode = in_array($capabilities['learningMode'], $modern_modes, true);

    foreach ($capabilities['questionTypes'] as $entry) {
        $native = in_array($entry['slug'], $native_slugs, true);

        if (!$capabilities['meetsSupportedFloor']) {
            $expected_reason = 'unsupported_tutor_version';
        } elseif ($native && !$capabilities['hasNativeQuizTypes']) {
            $expected_reason = 'unsupported_tutor_version';
        } elseif ($native && !$capabilities['proNativeQuizSupport']) {
            $expected_reason = 'pro_required';
        } elseif ($native && !$is_modern_mode) {
            $expected_reason = 'legacy_mode';
        } elseif ($entry['is_pro'] && !$capabilities['proActive']) {
            $expected_reason = 'pro_required';
        } else {
            $expected_reason = '';
        }

        $assert(
            $expected_reason === $entry['unavailable_reason'],
            sprintf(
                'Type %s reported reason "%s" but the matrix requires "%s".',
                $entry['slug'],
                $entry['unavailable_reason'],
                $expected_reason
            )
        );

        // Editing an existing row must survive Pro deactivation.
        $expected_edit = $capabilities['meetsSupportedFloor']
            && (!$native || ($capabilities['hasNativeQuizTypes'] && $is_modern_mode));
        $assert(
            $expected_edit === $entry['can_edit_existing'],
            sprintf('Type %s edit capability does not match the matrix.', $entry['slug'])
        );

        // The picker derives its disabled state from these two fields alone.
        $assert(
            $entry['registered'] && ('' === $expected_reason) === $entry['can_create'],
            sprintf('Type %s create capability does not match its reason.', $entry['slug'])
        );

        if ('pro_required' === $expected_reason && !$native) {
            $assert(
                $entry['can_edit_existing'],
                sprintf('Pre-4.0 Pro type %s lost edit capability while Pro is inactive.', $entry['slug'])
            );
        }
    }
};

$original_tutor_option = get_option('tutor_option');
$tracked_globals       = ['current_screen', 'pagenow', 'wp_scripts', 'wp_styles'];
$original_globals      = [];

foreach ($tracked_globals as $global_name) {
    $original_globals[$global_name] = [
        'exists' => array_key_exists($global_name, $GLOBALS),
        'value'  => $GLOBALS[$global_name] ?? null,
    ];
}

$failure_message = '';

try {
    $capabilities = TutorPress_Assets::get_quiz_capabilities();
    $assert(is_array($capabilities), 'Capability contract is not an array.');
    $assert_contract_shape($capabilities);
    $assert_capability_matrix($capabilities);

    $legacy_probe = [
        'feedbackModes'      => ['default', 'reveal', 'retry'],
        'questionLayouts'    => ['single_question', 'question_pagination', 'question_below_each_other'],
        'hasPaginationStyle' => true,
    ];
    $classifier_cases = [
        ['V4 boundary', '4.0.0', [], true, 'v4', ''],
        ['V4 later release', '4.9.0', [], true, 'v4', ''],
        ['legacy floor', '3.9.15', $legacy_probe, true, 'legacy', ''],
        ['legacy later release', '3.9.99', $legacy_probe, true, 'legacy', ''],
        ['inactive Tutor', '', [], false, 'unavailable', 'tutor_inactive'],
        ['missing version', '', [], true, 'unavailable', 'tutor_version_missing'],
        ['below floor', '3.9.14', $legacy_probe, true, 'unavailable', 'unsupported_tutor_version'],
        ['missing feedback catalog', '3.9.15', array_merge($legacy_probe, ['feedbackModes' => []]), true, 'unavailable', 'legacy_contract_unavailable'],
        ['missing layout catalog', '3.9.15', array_merge($legacy_probe, ['questionLayouts' => []]), true, 'unavailable', 'legacy_contract_unavailable'],
        ['missing pagination style', '3.9.15', array_merge($legacy_probe, ['hasPaginationStyle' => false]), true, 'unavailable', 'legacy_contract_unavailable'],
    ];

    foreach ($classifier_cases as [$label, $version, $probe, $active, $expected_contract, $expected_reason]) {
        $classified = TutorPress_Assets::classify_quiz_settings_contract($version, $probe, $active);
        $assert_settings_contract_shape($classified);
        $assert(
            $expected_contract === $classified['quizSettingsContract']
                && $expected_reason === $classified['quizSettingsUnavailableReason'],
            "{$label} classification failed."
        );
    }

    // Live installed Tutor must agree with the version-aware settings contract.
    if ($capabilities['tutorActive'] && defined('TUTOR_VERSION')) {
        $live_version = (string) TUTOR_VERSION;
        if (version_compare($live_version, '4.0.0', '>=')) {
            $assert(
                'v4' === $capabilities['quizSettingsContract']
                    && '' === $capabilities['quizSettingsUnavailableReason']
                    && true === $capabilities['supportsV4QuizContentDrip'],
                'Installed Tutor 4+ did not localize the V4 Quiz Settings contract.'
            );
        } elseif (version_compare($live_version, '3.9.15', '>=')) {
            $assert(
                in_array($capabilities['quizSettingsContract'], ['legacy', 'unavailable'], true),
                'Installed pre-4 Tutor did not localize legacy or fail-closed Quiz Settings.'
            );
            if ('unavailable' === $capabilities['quizSettingsContract']) {
                $assert(
                    'legacy_contract_unavailable' === $capabilities['quizSettingsUnavailableReason'],
                    'Pre-4 fail-closed contract missing legacy_contract_unavailable reason.'
                );
            }
        } else {
            $assert(
                'unavailable' === $capabilities['quizSettingsContract'],
                'Tutor below the supported floor must fail closed.'
            );
        }
    }

    $assert(
        function_exists('tutor') === $capabilities['tutorActive'],
        'tutorActive does not match Tutor availability.'
    );
    $assert(
        defined('TUTOR_PRO_VERSION') === $capabilities['proActive'],
        'proActive does not match Tutor Pro availability.'
    );
    $assert(
        !$capabilities['proNativeQuizSupport'] || $capabilities['proActive'],
        'proNativeQuizSupport was reported without active Tutor Pro.'
    );

    if (!$capabilities['tutorActive']) {
        $assert([] === $capabilities['questionTypes'], 'Types were advertised without Tutor active.');
    }

    // The registry must come from Tutor, not from a TutorPress-invented list.
    $reported_slugs = array_column($capabilities['questionTypes'], 'slug');
    if ($capabilities['tutorActive']) {
        if ($capabilities['hasNativeQuizTypes']) {
            $registry = \Tutor\Models\QuizModel::get_question_types();
        } else {
            $registry = tutor_utils()->get_question_types();
        }

        $assert(is_array($registry) && [] !== $registry, 'Tutor question registry is unavailable.');
        $assert(
            array_map('strval', array_keys($registry)) === $reported_slugs,
            'Reported question types do not match Tutor registry keys or order.'
        );
    }

    $present_native = array_values(array_intersect($native_slugs, $reported_slugs));
    if ($capabilities['hasNativeQuizTypes']) {
        $assert(
            [] === array_values(array_diff($native_slugs, $reported_slugs)),
            'Tutor 4.0 registry is present but a native slug is missing from the contract.'
        );
    } else {
        $assert(
            [] === $present_native,
            'A native Tutor 4.0 slug was advertised on a pre-4.0 registry.'
        );
        $assert(
            !$capabilities['supportsTempMaskDeletion'],
            'Temporary-mask deletion was reported on a pre-4.0 contract.'
        );
    }

    // Learning-mode normalization across every stored value, including garbage.
    if ($capabilities['tutorActive'] && is_array($original_tutor_option)) {
        $mode_cases = [
            'legacy'     => 'legacy',
            'modern'     => 'modern',
            'kids'       => 'kids',
            'not-a-mode' => 'unknown',
        ];

        foreach ($mode_cases as $stored => $expected) {
            $fixture_option                  = $original_tutor_option;
            $fixture_option['learning_mode'] = $stored;
            update_option('tutor_option', $fixture_option);

            $fixture = TutorPress_Assets::get_quiz_capabilities();
            $assert_contract_shape($fixture);
            $assert_capability_matrix($fixture);
            $assert(
                $expected === $fixture['learningMode'],
                "Stored learning mode '{$stored}' normalized to '{$fixture['learningMode']}'."
            );

            if (in_array($expected, ['legacy', 'unknown'], true)) {
                foreach ($fixture['questionTypes'] as $entry) {
                    if (!in_array($entry['slug'], $native_slugs, true)) {
                        continue;
                    }
                    $assert(
                        !$entry['can_create'],
                        "Native type {$entry['slug']} is creatable in {$expected} mode."
                    );
                }
            }
        }

        update_option('tutor_option', $original_tutor_option);
    }

    // The contract must reach the Gutenberg bundle with its strict types intact.
    $GLOBALS['pagenow'] = 'post.php';
    set_current_screen('courses');

    $screen = get_current_screen();
    $assert($screen && 'courses' === $screen->post_type, 'Could not establish a course admin screen.');

    unset($GLOBALS['wp_scripts'], $GLOBALS['wp_styles']);
    wp_scripts();
    wp_styles();

    TutorPress_Assets::enqueue_admin_assets('post.php');

    $scripts = wp_scripts();
    $handle  = 'tutorpress-curriculum-metabox';
    $assert(
        ($scripts->registered[$handle] ?? null) instanceof _WP_Dependency,
        'TutorPress Gutenberg bundle was not registered.'
    );

    $before_scripts = $scripts->get_data($handle, 'before');
    $assert(is_array($before_scripts), 'TutorPress typed capability assignment is absent.');
    $before_output = implode("\n", $before_scripts);

    $matched = preg_match(
        '/tutorPressCurriculum\.quizCapabilities = (\{.*?\});$/m',
        $before_output,
        $matches
    );
    $assert(1 === $matched, 'Could not parse the localized quiz capability contract.');

    $localized = json_decode($matches[1], true);
    $assert(
        is_array($localized) && JSON_ERROR_NONE === json_last_error(),
        'Localized quiz capability contract is not valid JSON.'
    );
    $assert_contract_shape($localized);
    $assert_capability_matrix($localized);
    $assert(
        $localized == TutorPress_Assets::get_quiz_capabilities(),
        'Localized contract differs from the server contract.'
    );

    // The contract must carry no credential or key material.
    $assert(
        !preg_match('/nonce|api_key|apikey|secret|password|token/i', $matches[1]),
        'Quiz capability contract exposes sensitive data.'
    );

    $tutor_options = get_option('tutor_option', []);
    $raw_key       = is_array($tutor_options)
        ? ($tutor_options['lesson_video_duration_youtube_api_key'] ?? '')
        : '';
    if (is_string($raw_key) && '' !== $raw_key) {
        $assert(
            false === strpos($matches[1], $raw_key),
            'Quiz capability contract exposes the YouTube API key.'
        );
    }

    // Client wiring: the picker must have exactly one authoritative input and no
    // disabled type may reach the question factory. Built installations ship no
    // TypeScript source, so this block reports itself as skipped rather than
    // failing where it cannot run.
    $source_root = dirname(__DIR__, 2) . '/assets/js/src';
    $read_source = static function ($relative_path) use ($assert, $source_root) {
        $path = $source_root . '/' . $relative_path;
        $assert(is_readable($path), "Client source {$relative_path} is unreadable.");

        $contents = file_get_contents($path);
        $assert(is_string($contents) && '' !== $contents, "Client source {$relative_path} is empty.");

        return $contents;
    };

    $client_wiring = is_dir($source_root) ? 'checked' : 'skipped';

    if ('checked' === $client_wiring) {
        $quiz_modal    = $read_source('components/modals/QuizModal.tsx');
        $question_list = $read_source('components/modals/quiz/QuestionList.tsx');

        $assert(
            !preg_match('#tutor_utils|get_question_types|_tutorobject|/tutor/v1/question-types#', $quiz_modal),
            'QuizModal still references an absent Tutor question-type discovery surface.'
        );
        $assert(
            false !== strpos($quiz_modal, 'quizCapabilities'),
            'QuizModal does not consume the server capability contract.'
        );

        $handler_start = strpos($quiz_modal, 'const handleQuestionTypeSelect');
        $assert(false !== $handler_start, 'handleQuestionTypeSelect is absent.');

        $handler_end = strpos($quiz_modal, 'const handleQuestionSelect', $handler_start);
        $assert(false !== $handler_end, 'Could not delimit handleQuestionTypeSelect.');

        $handler_body = substr($quiz_modal, $handler_start, $handler_end - $handler_start);
        $guard_return = strpos($handler_body, 'return;');
        $factory_call = strpos($handler_body, 'handleCreateNewQuestion(');
        $assert(
            false !== strpos($handler_body, 'disabled'),
            'handleQuestionTypeSelect does not inspect the disabled state.'
        );
        $assert(
            false !== $guard_return && false !== $factory_call && $guard_return < $factory_call,
            'handleQuestionTypeSelect can reach the question factory before rejecting a disabled type.'
        );

        $assert(
            false !== strpos($question_list, 'disabled: type.disabled'),
            'The question-type picker does not forward each option disabled state.'
        );

        // The picker option shape must not drift back into per-component copies.
        $option_consumers = [
            'components/modals/QuizModal.tsx',
            'components/modals/quiz/QuestionList.tsx',
            'components/modals/quiz/QuestionDetailsTab.tsx',
        ];

        foreach ($option_consumers as $relative_path) {
            $assert(
                false === strpos($read_source($relative_path), 'interface QuestionTypeOption'),
                "{$relative_path} redeclares QuestionTypeOption instead of importing it."
            );
        }

        $assert(
            false !== strpos($read_source('types/quiz.ts'), 'interface QuestionTypeOption'),
            'The shared QuestionTypeOption type is missing from types/quiz.ts.'
        );

        // Shared metadata must own the five native entries exactly, and must be the only
        // place native labels live. Entries are checked by slug rather than by counting
        // rows, so the `h5p` and alias-only rows cannot affect the result.
        $type_metadata = $read_source('utils/quizQuestionTypes.ts');

        $native_metadata = [
            'draw_image'  => array( 'label' => 'Image Marking', 'order' => 8 ),
            'scale'       => array( 'label' => 'Range', 'order' => 9 ),
            'pin_image'   => array( 'label' => 'Pin', 'order' => 10 ),
            'coordinates' => array( 'label' => 'Graph', 'order' => 11 ),
            'puzzle'      => array( 'label' => 'Puzzle', 'order' => 12 ),
        ];

        foreach ($native_metadata as $slug => $expected) {
            $pattern = '/^\s*' . preg_quote($slug, '/')
                . ':\s*\{\s*label:\s*__\(\s*"([^"]*)"[^)]*\)\s*,\s*pickerOrder:\s*(\d+)\s*,'
                . '\s*isPro:\s*(true|false)\s*,\s*modernModeOnly:\s*(true|false)\s*\}/m';

            $assert(
                1 === preg_match($pattern, $type_metadata, $entry),
                "Native metadata entry for {$slug} is absent or malformed."
            );
            $assert(
                $expected['label'] === $entry[1],
                "Native metadata label for {$slug} is \"{$entry[1]}\", expected \"{$expected['label']}\"."
            );
            $assert(
                $expected['order'] === (int) $entry[2],
                "Native picker order for {$slug} is {$entry[2]}, expected {$expected['order']}."
            );
            $assert('true' === $entry[3], "Native metadata does not mark {$slug} as Pro.");
            $assert('true' === $entry[4], "Native metadata does not mark {$slug} as modern-mode only.");
        }

        $assert(
            1 === preg_match('/^\s*ordering:\s*\{[^}]*pickerOrder:\s*7\s*,/m', $type_metadata),
            'The five native types no longer follow the established pre-4.0 picker order.'
        );

        // Temporary inline fallback map must be gone, with no native label left
        // behind in QuizModal.
        $assert(
            false === strpos($quiz_modal, 'Partial<Record<QuizQuestionType, string>>'),
            'The temporary QuizModal inline fallback label map is still present.'
        );
        $assert(
            !preg_match('/Image Marking|"Range"|"Pin"|"Graph"|"Puzzle"/', $quiz_modal),
            'QuizModal duplicates a native question-type label that shared metadata owns.'
        );

        // A type with no registered component must not be locally authorable, and the
        // picker must require both server permission and a local editor.
        $question_registry = $read_source('components/modals/quiz/questions/index.ts');

        $assert(
            1 === preg_match('/QuestionComponentMap\s*=\s*\{(.+?)\}\s*as const;/s', $question_registry, $map_block),
            'Could not delimit QuestionComponentMap.'
        );

        $assert(
            1 === preg_match('/^\s*scale:\s*ScaleQuestion\s*,/m', $map_block[1]),
            'scale is not registered to ScaleQuestion, so Range is not locally authorable.'
        );
        $assert(
            1 === preg_match('/^\s*coordinates:\s*CoordinatesQuestion\s*,/m', $map_block[1]),
            'coordinates is not registered to CoordinatesQuestion, so Graph is not locally authorable.'
        );
        $assert(
            1 === preg_match('/^\s*draw_image:\s*DrawImageQuestion\s*,/m', $map_block[1]),
            'draw_image is not registered to DrawImageQuestion, so Draw Image is not locally authorable.'
        );
        $assert(
            1 === preg_match('/^\s*pin_image:\s*PinImageQuestion\s*,/m', $map_block[1]),
            'pin_image is not registered to PinImageQuestion, so Pin Image is not locally authorable.'
        );
        $assert(
            1 === preg_match('/^\s*puzzle:\s*PuzzleQuestion\s*,/m', $map_block[1]),
            'puzzle is not registered to PuzzleQuestion, so Puzzle is not locally authorable.'
        );

        $assert(
            !preg_match('/^\s*h5p:/m', $map_block[1]),
            'h5p was added to the regular component registry; it belongs to the Interactive Quiz modal.'
        );
        $assert(
            1 === preg_match('/isLocallyAuthorable[^}]*isKnownQuizQuestionType[^}]*hasQuestionComponent/s', $question_registry),
            'isLocallyAuthorable does not require both known metadata and a registered component.'
        );
        $assert(
            false !== strpos($quiz_modal, 'isLocallyAuthorable(type.slug)'),
            'The picker does not consult local editor availability.'
        );
        $assert(
            false !== strpos($quiz_modal, 'disabled: !serverAllowsCreate || !hasLocalEditor'),
            'The picker does not require both server permission and a local editor.'
        );

        // Existing pre-4.0 component and validation entries must survive centralization.
        $pre_40_types = [
            'true_false',
            'single_choice',
            'multiple_choice',
            'open_ended',
            'fill_in_the_blank',
            'short_answer',
            'matching',
            'image_matching',
            'image_answering',
            'ordering',
        ];

        $validation = $read_source('hooks/quiz/useQuestionValidation.ts');

        foreach ($pre_40_types as $slug) {
            $assert(
                1 === preg_match('/^\s*' . preg_quote($slug, '/') . ':\s*[A-Z]/m', $map_block[1]),
                "Pre-4.0 component registration for {$slug} was lost."
            );
            $assert(
                1 === preg_match('/^\s*' . preg_quote($slug, '/') . ':\s*\[/m', $validation),
                "Pre-4.0 validation rules for {$slug} were lost."
            );
        }

        // A strict registry key must not cost the unknown-slug dispatch fallback.
        $assert(
            false !== strpos($validation, 'Partial<Record<QuizQuestionType, ValidationRule[]>>'),
            'The validation registry key is not constrained to known question types.'
        );
        $assert(
            false !== strpos($validation, 'validationRegistry[question.question_type] || []'),
            'Validation dispatch lost its no-rules fallback for an unknown slug.'
        );

        // Range (`scale`) native answer contract.
        //
        // The stored value is JSON in the answer row's `answer_two_gap_match`: a top-level
        // `value` plus a `config` object holding Tutor's eight keys, with
        // `answer_view_format` set to `scale`. Tutor Pro's grader reads `value`,
        // `config.step`, and `config.precision`, so the shape and key names are an
        // external contract rather than a TutorPress choice.
        $scale_editor = $read_source('components/modals/quiz/questions/ScaleQuestion.tsx');

        $assert(
            1 === preg_match(
                '/NATIVE_SCALE_DEFAULTS:\s*ScaleAnswerData\s*=\s*\{\s*value:\s*50\s*,\s*config:\s*\{(.+?)\}\s*,?\s*\}/s',
                $type_metadata,
                $defaults_block
            ),
            'Range creation defaults are absent or no longer match the native shape.'
        );

        $native_scale_defaults = [
            'min'            => '0',
            'max'            => '100',
            'step'           => '1',
            'defaultValue'   => '50',
            'pxPerUnit'      => '10',
            'labelEvery'     => '10',
            'minorTickEvery' => '5',
            'precision'      => '0',
        ];

        foreach ($native_scale_defaults as $key => $expected) {
            $assert(
                1 === preg_match('/\b' . preg_quote($key, '/') . ':\s*(-?[\d.]+)\s*,/', $defaults_block[1], $found),
                "Range default config key {$key} is missing."
            );
            $assert(
                $expected === $found[1],
                "Range default {$key} is {$found[1]}, expected {$expected}."
            );
        }

        // The serializer must emit those eight config keys and nothing else, in Tutor's
        // own order, so an untouched default row is byte-identical across both builders.
        $assert(
            1 === preg_match(
                '/serializeScaleAnswer\s*=\s*\(.*?JSON\.stringify\(\{\s*value:\s*data\.value\s*,\s*config:\s*\{(.+?)\}\s*,?\s*\}\)/s',
                $type_metadata,
                $serialized_block
            ),
            'Range serialization no longer emits a top-level value plus a config object.'
        );

        $assert(
            0 < preg_match_all('/(\w+):\s*config\.\w+/', $serialized_block[1], $serialized_keys),
            'Range serialization emits no config keys.'
        );
        $assert(
            array_keys($native_scale_defaults) === $serialized_keys[1],
            'Range serialization emits ' . implode(',', $serialized_keys[1])
                . ' instead of exactly ' . implode(',', array_keys($native_scale_defaults)) . ' in that order.'
        );

        // Tutor pins `step` to 1 on create, on load, and on every config change.
        $assert(
            1 === preg_match('/normalizeScaleConfig\s*=\s*\([^)]*\)[^=]*=>\s*\(\{\s*\.\.\.config\s*,\s*step:\s*1\s*\}\)/s', $type_metadata),
            'Range config normalization no longer pins step to 1 as Tutor does.'
        );

        // The answer row must carry Tutor's view format and correctness flag, and a value
        // TutorPress cannot parse must never be rewritten.
        $assert(
            1 === preg_match('/SCALE_ANSWER_VIEW_FORMAT\s*=\s*"scale"/', $scale_editor),
            'The Range editor does not set answer_view_format to scale.'
        );
        $view_format_writes = preg_match_all('/answer_view_format:/', $scale_editor);
        $assert(
            0 < $view_format_writes
                && $view_format_writes === preg_match_all('/answer_view_format:\s*SCALE_ANSWER_VIEW_FORMAT/', $scale_editor),
            'The Range editor writes an answer_view_format value other than the native scale constant.'
        );
        $assert(
            1 === preg_match('/parsed\.status\s*!==\s*"empty"[^;]*seededRef/s', $scale_editor),
            'The Range editor seeds defaults over a value that is already stored.'
        );
        $assert(
            1 === preg_match('/if\s*\(parsed\.status\s*!==\s*"valid"\)\s*\{\s*return;/s', $scale_editor),
            'The Range editor can commit a change while the stored value is unreadable.'
        );

        // Range has no file lifecycle: it must not touch temporary-mask deletion state,
        // media selection, or any request of its own.
        $assert(
            !preg_match('/deleted_temp_mask_values|fetch\(|wp\.media|apiFetch/', $scale_editor),
            'The Range editor introduces a file or request lifecycle it must not own.'
        );

        // Tutor defines exactly two Range constraints; no step or precision rule exists.
        $assert(
            1 === preg_match('/^\s*scale:\s*\[(.+?)^\s*\],$/ms', $validation, $scale_rules),
            'Range validation rules are absent.'
        );
        $assert(
            2 === preg_match_all('/\(question:\s*QuizQuestion\)\s*=>/', $scale_rules[1], $rule_count),
            'Range has ' . preg_match_all('/\(question:\s*QuizQuestion\)\s*=>/', $scale_rules[1])
                . ' validation rules instead of Tutor\'s two.'
        );
        $assert(
            false !== strpos($scale_rules[1], 'The maximum value must be greater than the minimum value.'),
            'Range is missing the max-greater-than-min rule.'
        );
        $assert(
            false !== strpos($scale_rules[1], 'The correct value must be between the minimum and maximum values.'),
            'Range is missing the correct-value-in-range rule.'
        );
        $assert(
            !preg_match('/config\.step|config\.precision|minorTickEvery/', $scale_rules[1]),
            'Range validation asserts a step, precision, or tick constraint Tutor does not define.'
        );

        // Graph (`coordinates`) native answer contract.
        //
        // The stored value is a BARE JSON array of integer {x,y} points in the answer row's
        // `answer_two_gap_match`, with `answer_view_format` set to `coordinates`. Tutor's own
        // editor writes `JSON.stringify(points)`, Tutor core's validator accepts an array or
        // a legacy single object, and Tutor Pro's review renderer requires an array. A
        // `{points:...}` wrapper is the STUDENT response shape, not the instructor answer.
        $coordinates_editor = $read_source('components/modals/quiz/questions/CoordinatesQuestion.tsx');

        $assert(
            1 === preg_match('/COORDINATES_ANSWER_VIEW_FORMAT\s*=\s*"coordinates"/', $coordinates_editor),
            'The Graph editor does not set answer_view_format to coordinates.'
        );
        $coordinates_view_format_writes = preg_match_all('/answer_view_format:/', $coordinates_editor);
        $assert(
            0 < $coordinates_view_format_writes
                && $coordinates_view_format_writes === preg_match_all(
                    '/answer_view_format:\s*COORDINATES_ANSWER_VIEW_FORMAT/',
                    $coordinates_editor
                ),
            'The Graph editor writes an answer_view_format value other than the native coordinates constant.'
        );

        // Serialization must emit the bare array with Tutor's per-point key order, and must
        // refuse an empty set: Tutor can never store `[]` (its editor coerces an empty set
        // back to the origin and refuses to delete the last point) and core's validator
        // rejects a zero-length array, so writing one produces a row Tutor will not save.
        $assert(
            1 === preg_match(
                '/serializeCoordinatesAnswer\s*=\s*\((.+?)\n\};/s',
                $type_metadata,
                $coordinates_serializer
            ),
            'Graph serialization is absent from the shared contract module.'
        );
        $assert(
            1 === preg_match(
                '/JSON\.stringify\(points\.map\(\(point\)\s*=>\s*\(\{\s*x:\s*point\.x\s*,\s*y:\s*point\.y\s*\}\)\)\)/',
                $coordinates_serializer[1]
            ),
            'Graph serialization no longer emits a bare array of {x,y} points in Tutor\'s key order.'
        );
        $assert(
            1 === preg_match('/points\.length\s*===\s*0\s*\)\s*\{\s*return null;/s', $coordinates_serializer[1]),
            'Graph serialization can emit an empty array, which Tutor refuses to save.'
        );
        $assert(
            !preg_match('/"points"|points:\s*\[|\{\s*points\s*:/', $coordinates_serializer[1]),
            'Graph serialization wraps points in an object; that is the student response shape.'
        );

        // The last point must be undeletable, mirroring Tutor's own hard return.
        $assert(
            1 === preg_match('/displayPoints\.length\s*<=\s*1\s*\)\s*\{\s*return;/s', $coordinates_editor),
            'The Graph editor can delete the last coordinate, which Tutor never allows.'
        );

        // No mount-time write: a newly created Graph row keeps Tutor's empty answer value
        // until the author commits a point.
        $assert(
            !preg_match('/useEffect\([^)]*\)\s*=>\s*\{[^}]*writeCoordinates/s', $coordinates_editor),
            'The Graph editor writes an answer value on mount instead of leaving Tutor\'s empty row.'
        );

        // Only Tutor's two axis ranges exist, spanning -n through n, defaulted at creation
        // by the shared question factory the way Tutor seeds it in its own.
        $assert(
            1 === preg_match(
                '/COORDINATES_AXIS_RANGE_OPTIONS:\s*CoordinatesAxisRange\[\]\s*=\s*\[\s*10\s*,\s*20\s*\]/',
                $type_metadata
            ),
            'Graph offers axis ranges other than Tutor\'s 10 and 20.'
        );
        $assert(
            1 === preg_match(
                '/resolveCoordinatesAxisRange\s*=\s*\([^)]*\)[^=]*=>\s*Number\([^)]*\)\s*===\s*20\s*\?\s*20\s*:\s*NATIVE_COORDINATES_AXIS_RANGE/s',
                $type_metadata
            ),
            'Graph axis-range normalization no longer resolves anything but 20 to the native default.'
        );
        $assert(
            1 === preg_match(
                '/questionType\s*===\s*"coordinates"\s*&&\s*\{\s*coordinates_axis_range:\s*10\s*\}/',
                $read_source('types/quiz.ts')
            ),
            'The shared question factory does not seed coordinates_axis_range at creation.'
        );

        // Graph has no file lifecycle: no temporary-mask state, media selection, or request.
        $assert(
            !preg_match('/deleted_temp_mask_values|fetch\(|wp\.media|apiFetch/', $coordinates_editor),
            'The Graph editor introduces a file or request lifecycle it must not own.'
        );

        // Tutor core defines three Graph constraints (1-5 points, integers, within +/-axis).
        // Duplicates are permitted and ordering is never normalized, so neither may appear.
        $assert(
            1 === preg_match('/^\s*coordinates:\s*\[(.+?)^\s*\],$/ms', $validation, $coordinates_rules),
            'Graph validation rules are absent.'
        );
        $coordinates_rule_count = preg_match_all('/\(question:\s*QuizQuestion\)\s*=>/', $coordinates_rules[1]);
        $assert(
            3 === $coordinates_rule_count,
            "Graph has {$coordinates_rule_count} validation rules instead of Tutor's three."
        );
        $assert(
            false !== strpos($coordinates_rules[1], 'Add at least one correct coordinate.'),
            'Graph is missing the at-least-one-point rule.'
        );
        // Asserted as the comparison rather than a bare mention of the constant: the
        // constant also appears in the rule's own message argument, so a substring test
        // would still pass with a hardcoded limit in the comparison itself.
        $assert(
            1 === preg_match('/parsed\.points\.length\s*>\s*MAX_COORDINATE_POINTS/', $coordinates_rules[1]),
            'Graph does not compare the stored point count against Tutor\'s maximum.'
        );
        $assert(
            false !== strpos($coordinates_rules[1], 'resolveCoordinatesAxisRange'),
            'Graph validation does not check points against the configured axis range.'
        );
        $assert(
            !preg_match('/duplicate|\.sort\(/i', $coordinates_rules[1]),
            'Graph validation asserts a duplicate or ordering constraint Tutor does not define.'
        );

        // A stored value TutorPress cannot parse must produce no validation error, or a
        // preserved row would block every quiz save with no way for the author to repair it.
        $assert(
            1 === preg_match('/status\s*===\s*"empty"\s*\?/', $coordinates_rules[1]),
            'The Graph at-least-one-point rule fires for a malformed value, not just an empty one.'
        );
        $assert(
            !preg_match('/status\s*===\s*"malformed"/', $coordinates_rules[1]),
            'Graph validation reports a malformed stored value, which would trap the author.'
        );

        // Shared image/canvas mask authoring. The layer has no question type of its own;
        // Draw Image and Pin Image consume it in later steps.
        $canvas_hook      = $read_source('hooks/quiz/useQuizImageCanvas.ts');
        $canvas_component = $read_source('components/modals/quiz/questions/QuizImageCanvas.tsx');

        // Files, transfers, and Pro storage stay Tutor-owned. Media Library selection is
        // deliberately absent from this list: it is the existing WordPress contract.
        $canvas_sources = [
            'useQuizImageCanvas.ts' => $canvas_hook,
            'QuizImageCanvas.tsx'   => $canvas_component,
        ];

        foreach ($canvas_sources as $label => $canvas_source) {
            $assert(
                !preg_match(
                    '#\bfetch\(|apiFetch|XMLHttpRequest|FormData|admin-ajax|wp-json|upload_dir'
                        . '|QuizImageStorage|TUTOR_PRO|tutor/quiz-images|draw-mask-|pin-mask-#',
                    $canvas_source
                ),
                "{$label} performs a request, filesystem, or Tutor Pro storage operation it must not own."
            );
        }

        // The exported mask is written back down to the CSS box, independent of the device
        // pixel ratio used for rendering. This is the invariant that regresses invisibly:
        // the instructor mask sets the comparison grid for every future attempt, so a
        // larger raster silently multiplies Tutor Pro's per-pixel grading cost and can
        // cross the threshold where it switches to sampling every other pixel.
        $assert(
            1 === preg_match(
                '/const exportMask = useCallback\((.+?)\n  \}, \[canvasRef\]\);/s',
                $canvas_hook,
                $export_block
            ),
            'Could not delimit the mask export path.'
        );
        $assert(
            1 === preg_match('/const \{ width, height \} = cssSizeRef\.current;/', $export_block[1]),
            'Mask export no longer measures the CSS box.'
        );
        $assert(
            1 === preg_match(
                '/exportCanvas\.width\s*=\s*Math\.round\(width\);\s*exportCanvas\.height\s*=\s*Math\.round\(height\);/',
                $export_block[1]
            ),
            'The exported mask is not sized to the CSS box, which changes the grading grid.'
        );
        $assert(
            !preg_match('/canvas\.width|canvas\.height|devicePixelRatio/', $export_block[1]),
            'Mask export derives its size from the backing store or pixel ratio instead of the CSS box.'
        );

        // The pixel ratio sharpens the visible canvas only, and pointer input is measured
        // against the rendered rect so a CSS-scaled canvas still maps correctly.
        $assert(
            1 === preg_match(
                '/context\.setTransform\(\s*backing\.width \/ cssWidth,\s*0,\s*0,\s*backing\.height \/ cssHeight,\s*0,\s*0\s*\)/',
                $canvas_hook
            ),
            'The canvas does not scale its drawing space back to CSS pixels for high-DPI rendering.'
        );
        $assert(
            1 === preg_match('/scaleX = rect\.width > 0 \? cssWidth \/ rect\.width : 1/', $canvas_hook),
            'Pointer mapping no longer scales client coordinates by the measured display rect.'
        );

        // Only a finished stroke or an explicit clear may commit. Mounting, loading a
        // stored mask, and resizing must not, or opening a saved question at a different
        // window width would re-encode its mask and move the grading basis underneath it.
        $commit_calls = preg_match_all('/onMaskCommitRef\.current\(/', $canvas_hook);
        $assert(
            2 === $commit_calls,
            "The canvas commits from {$commit_calls} places instead of a finished stroke and an explicit clear."
        );
        $assert(
            1 === preg_match_all('/commitMask\(\);/', $canvas_hook),
            'The canvas exports and commits a mask from somewhere other than the finished stroke.'
        );
        $assert(
            1 === preg_match(
                '/useEffect\(\(\) => \{(.+?)\}, \[initialMaskValue, redraw, syncCanvas\]\);/s',
                $canvas_hook,
                $mount_effect
            ),
            'Could not delimit the stored-mask display effect.'
        );
        $assert(
            !preg_match('/commitMask|onMaskCommit/', $mount_effect[1]),
            'Displaying a stored mask commits it, so merely opening a question would dirty its row.'
        );
        $assert(
            1 === preg_match(
                '/const syncCanvas = useCallback\(\(\) => \{(.+?)\n  \}, \[canvasRef, imageRef, redraw\]\);/s',
                $canvas_hook,
                $sync_block
            ),
            'Could not delimit the canvas resize path.'
        );
        $assert(
            !preg_match('/commitMask|onMaskCommit/', $sync_block[1]),
            'Resizing commits the mask, which would re-encode an untouched answer.'
        );

        // Stored values are external data, so they reach an image source only after
        // validation, and a value that cannot be read is reported rather than repaired.
        $assert(
            1 === preg_match('/const isSafeImageSource[\s\S]+?\n\};/', $canvas_hook, $safe_source),
            'The stored-value guard is absent.'
        );
        // Asserted as the two anchored branches rather than a mention of "data:" or "http":
        // the source escapes the slash for its regex literal, so a `data:image/` substring
        // test silently never matches, and an unanchored test would pass for `javascript:`.
        $assert(
            false !== strpos($safe_source[0], 'data:image') && false !== strpos($safe_source[0], ';base64,'),
            'The stored-value guard no longer restricts data URLs to base64 image payloads.'
        );
        $assert(
            1 === preg_match('/\^https\?:/', $safe_source[0]),
            'The stored-value guard no longer anchors URL sources to the http(s) schemes.'
        );
        $assert(
            1 === preg_match('/!isSafeImageSource\(initialMaskValue\)/', $canvas_hook),
            'A stored mask value is loaded without being validated first.'
        );
        $assert(
            1 === preg_match('/isSafeImageSource\(imageUrl\) \? imageUrl : undefined/', $canvas_component)
                && 1 === preg_match('/isSafeImageSource\(maskValue\) \? maskValue : undefined/', $canvas_component),
            'The canvas component assigns a stored value as an image source without validating it.'
        );

        // A failed load or an unreadable canvas must leave the stored answer alone. A
        // tainted canvas in particular must never be mistaken for a cleared mask.
        $assert(
            1 === preg_match(
                '/const handleImageError = useCallback\(\(\) => \{\s*setHasLoadError\(true\);\s*\}, \[\]\);/',
                $canvas_hook
            ),
            'A failed image load does more than report itself.'
        );
        $assert(
            1 === preg_match('/catch \(error\) \{\s*return null;/', $canvas_hook),
            'An unreadable canvas does not report an export failure distinctly from an empty mask.'
        );
        $assert(
            1 === preg_match('/if \(exported === null\) \{\s*setHasExportError\(true\);\s*return;/', $canvas_hook),
            'An unreadable canvas is committed as though the mask had been cleared.'
        );

        // The shared canvas itself is never registered as a question editor. Draw Image
        // registers its complete wrapper component; Pin and Puzzle remain unimplemented.
        $assert(
            !preg_match('/QuizImageCanvas/', $question_registry),
            'The shared canvas component was registered directly instead of through a complete question editor.'
        );

        // The canvas reuses the existing Media Library hook, whose surface four existing
        // image editors depend on.
        $assert(
            false !== strpos($canvas_component, 'useImageManagement()'),
            'The canvas component does not reuse the shared Media Library hook.'
        );

        $image_management = $read_source('hooks/quiz/useImageManagement.ts');
        $assert(
            1 === preg_match('/\n  return \{(.+?)\n  \};/s', $image_management, $image_hook_surface),
            'Could not delimit the shared image hook return value.'
        );

        foreach (['currentImage', 'setCurrentImage', 'openMediaLibrary', 'removeImage', 'isMediaLibraryAvailable', 'createImageHandlers'] as $member) {
            $assert(
                1 === preg_match('/^\s*' . preg_quote($member, '/') . ',$/m', $image_hook_surface[1]),
                "The shared image hook no longer returns {$member}, which existing image editors consume."
            );
        }
        $assert(
            1 === preg_match(
                '/mediaFrame\.on\("select", \(\) => \{.+?'
                    . 'if \(onSelect\) \{\s*onSelect\(imageData\);\s*\}\s*'
                    . 'mediaFrame\.close\(\);\s*\}\);/s',
                $image_management
            ),
            'The shared Media Library frame does not close after applying a valid image selection.'
        );

        // ------------------------------------------------------------------
        // Step 8: Tutor's temporary-mask deletion contract.
        // ------------------------------------------------------------------
        // This is the only step that can destroy a file, so the assertions below are
        // about what TutorPress refuses to register, not only about what it sends. The
        // serializer gate that carries the list to Tutor belongs to Step 3 and is
        // asserted in verify-quiz-payload-preservation.php; here we prove the list is
        // populated from exactly one trigger under exactly three gates.

        // The mask-type list must stay the three slugs Tutor's own builder checks. A
        // fourth entry would start registering values for a type Tutor never cleans up.
        $assert(
            1 === preg_match(
                '/export const MASK_QUESTION_TYPES = \["draw_image", "pin_image", "puzzle"\] as const;/',
                $type_metadata
            ),
            'MASK_QUESTION_TYPES is not exactly Tutor\'s three file-backed question types.'
        );

        $assert(
            1 === preg_match(
                '/export const collectAbandonedTempMaskValues = \(question: QuizQuestion\): string\[\] => \{(.+?)\n\};/s',
                $type_metadata,
                $harvest_block
            ),
            'Could not delimit the abandoned temporary-mask harvest.'
        );
        $harvest_body = $harvest_block[1];

        // Three gates, each of which can only ever register less than Tutor does. The
        // comparisons are asserted rather than the identifiers, because every one of
        // these names also appears in the surrounding code.
        $assert(
            1 === preg_match('/question\._data_status !== "new"/', $harvest_body),
            'The harvest no longer requires an unsaved question, so a persisted value could be registered.'
        );
        $assert(
            1 === preg_match('/question\.question_id < 0/', $harvest_body),
            'The harvest no longer requires a TutorPress temporary question ID.'
        );
        $assert(
            1 === preg_match('/question\.content_id !== undefined/', $harvest_body)
            && 1 === preg_match('/question\.content_id !== null/', $harvest_body),
            'The harvest no longer excludes Content Bank-linked rows, whose files Tutor shares.'
        );
        $assert(
            1 === preg_match('/!isMaskQuestionType\(question\.question_type\)/', $harvest_body),
            'The harvest no longer restricts itself to the file-backed question types.'
        );
        $gate_returns = preg_match_all('/return \[\];/', $harvest_body);
        $assert(
            3 === $gate_returns,
            "The harvest has {$gate_returns} refusal branches instead of the three required gates."
        );

        // Native-exact collection: both fields Tutor harvests, trimmed, empties dropped,
        // exact duplicates collapsed.
        $assert(
            1 === preg_match('/\[answer\.answer_two_gap_match, answer\.image_url\]/', $harvest_body),
            'The harvest does not read both value fields Tutor reads from each answer row.'
        );
        $assert(
            1 === preg_match('/\.filter\(\(value\) => value\.length > 0\)/', $harvest_body),
            'The harvest can register an empty value.'
        );
        $assert(
            1 === preg_match('/Array\.from\(new Set\(values\)\)/', $harvest_body),
            'The harvest no longer deduplicates the values it registers.'
        );

        // Exactly one trigger. Tutor registers only on deleting an unsaved question and
        // has no editor callback; a replace or clear trigger would delete files Tutor
        // itself leaves alone.
        $harvest_consumers = [];
        $source_iterator   = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source_root));
        foreach ($source_iterator as $source_file) {
            if (!$source_file->isFile() || !preg_match('/\.tsx?$/', $source_file->getFilename())) {
                continue;
            }

            $contents = (string) file_get_contents($source_file->getPathname());
            if (false !== strpos($contents, 'collectAbandonedTempMaskValues')) {
                $harvest_consumers[] = str_replace($source_root . '/', '', $source_file->getPathname());
            }
        }
        sort($harvest_consumers);
        $assert(
            ['components/modals/QuizModal.tsx', 'utils/quizQuestionTypes.ts'] === $harvest_consumers,
            'The temporary-mask harvest is referenced from ' . implode(', ', $harvest_consumers)
                . ' instead of its definition and the single delete-question trigger.'
        );

        $assert(
            1 === preg_match(
                '/const handleDeleteQuestion = \(questionIndex: number\) => \{(.+?)\n  \};/s',
                $quiz_modal,
                $delete_handler
            ),
            'Could not delimit handleDeleteQuestion.'
        );
        $assert(
            1 === preg_match_all('/collectAbandonedTempMaskValues\(questionToDelete\)/', $delete_handler[1]),
            'Deleting a question is not the single registration trigger.'
        );
        $modal_calls = preg_match_all('/collectAbandonedTempMaskValues\(/', $quiz_modal);
        $assert(
            1 === $modal_calls,
            "QuizModal calls the temporary-mask harvest from {$modal_calls} places instead of one."
        );

        // Registration merges without duplicating a value already reported this session.
        $assert(
            1 === preg_match('/!prev\.includes\(value\)/', $delete_handler[1]),
            'Registration no longer deduplicates against values already registered.'
        );

        // The list resets wherever the proven deletion lists reset, and nowhere else, so
        // a failed save keeps its cleanup values for the retry.
        $reset_sites = preg_match_all(
            '/setDeletedAnswerIds\(\[\]\);\n\s*setDeletedTempMaskValues\(\[\]\);/',
            $quiz_modal
        );
        $assert(
            4 === $reset_sites,
            "The temporary-mask list resets at {$reset_sites} of the four sites the persisted deletion lists use."
        );
        $assert(
            4 === preg_match_all('/setDeletedTempMaskValues\(\[\]\);/', $quiz_modal),
            'The temporary-mask list is cleared somewhere the persisted deletion lists are not.'
        );

        $assert(
            1 === preg_match('/const handleSave = async \(\) => \{(.+?)\n  \};/s', $quiz_modal, $save_handler),
            'Could not delimit handleSave.'
        );
        $assert(
            !preg_match('/setDeletedTempMaskValues/', $save_handler[1]),
            'handleSave clears the temporary-mask list, so a failed save would drop its cleanup values.'
        );
        $assert(
            1 === preg_match(
                '/formData\.deleted_temp_mask_values = deletedTempMaskValues;/',
                $save_handler[1]
            ),
            'The save path does not attach the temporary-mask list to the quiz form.'
        );

        // Interactive Quiz keeps its own completed deletion path untouched.
        $assert(
            false === strpos($read_source('components/modals/InteractiveQuizModal.tsx'), 'deleted_temp_mask_values'),
            'The Interactive Quiz modal now sends a temporary-mask deletion field.'
        );

        // Step 8A: Image Matching shows Tutor's required title field and hides only
        // the matched-text field. The validation registry remains native-exact.
        $option_editor   = $read_source('components/modals/quiz/questions/OptionEditor.tsx');
        $sortable_option = $read_source('components/modals/quiz/questions/SortableOption.tsx');
        $matching_editor = $read_source('components/modals/quiz/questions/MatchingQuestion.tsx');

        $assert(
            1 === preg_match('/showMainTextField\s*=\s*true/', $option_editor),
            'OptionEditor does not default the shared main text field to visible.'
        );
        $assert(
            1 === preg_match('/const isSaveDisabled =(.+?);/s', $option_editor, $option_save_guard)
                && 1 === preg_match(
                    '/\(showMainTextField && !currentText\.trim\(\)\)/',
                    $option_save_guard[1]
                ),
            'OptionEditor still requires hidden main text before saving.'
        );
        $assert(
            1 === preg_match('/\{showMatchingTextField && \(\s*<textarea/s', $option_editor)
                && 1 === preg_match('/\{showMainTextField && \(\s*<textarea/s', $option_editor),
            'OptionEditor does not independently render its title and main text fields.'
        );
        $assert(
            false !== strpos($sortable_option, 'showMainTextField={showMainTextField}'),
            'SortableOption does not forward the main text field visibility.'
        );
        $assert(
            2 === preg_match_all('/showMatchingTextField=\{true\}/', $matching_editor)
                && 2 === preg_match_all('/showMainTextField=\{!isImageMatching\}/', $matching_editor),
            'MatchingQuestion does not select the native field layout at both editor call sites.'
        );
        $assert(
            1 === preg_match(
                '/const matchingTextPlaceholder = isImageMatching\s*\?\s*__\("Image matched text\.\.", "tutorpress"\)\s*:\s*__\("Question", "tutorpress"\);/',
                $matching_editor
            ) && 2 === preg_match_all('/matchingTextPlaceholder=\{matchingTextPlaceholder\}/', $matching_editor),
            'MatchingQuestion does not use Tutor\'s mode-aware title placeholder at both call sites.'
        );
        $assert(
            1 === preg_match(
                '/const handleSaveOption = \(\) => \{.*?if \(!currentMatchingText\.trim\(\)\) \{\s*return;\s*\}.*?if \(!isImageMatching && !currentOptionText\.trim\(\)\) \{\s*return;\s*\}/s',
                $matching_editor
            ),
            'MatchingQuestion does not require title in both modes and matched text only in text mode.'
        );
        $assert(
            1 === preg_match('/\n      matching:\s*\[(.+?)\n      \],/s', $validation, $matching_rules)
                && 1 === preg_match('/\/\/ Empty option text rule(.+?)\/\/ Matching text requirement/s', $matching_rules[1], $title_rule)
                && false === strpos($title_rule[1], 'isImageMatching')
                && 1 === preg_match('/!option\.answer_title\?\.trim\(\)/', $title_rule[1]),
            'The native matching answer_title rule is missing or gated by image mode.'
        );

        // ------------------------------------------------------------------
        // Step 9: Draw Image authoring.
        // ------------------------------------------------------------------
        $draw_editor = $read_source('components/modals/quiz/questions/DrawImageQuestion.tsx');
        $quiz_types  = $read_source('types/quiz.ts');

        // Draw consumes the completed shared canvas. It must not duplicate its media,
        // raster, coordinate, request, storage, or grading responsibilities.
        $assert(
            1 === preg_match('/import \{ QuizImageCanvas \} from "\.\/QuizImageCanvas";/', $draw_editor)
                && 1 === preg_match_all('/<QuizImageCanvas\b/', $draw_editor),
            'Draw Image does not consume exactly one shared QuizImageCanvas.'
        );
        $assert(
            !preg_match(
                '#\bfetch\(|apiFetch|XMLHttpRequest|FormData|admin-ajax|wp-json|upload_dir'
                    . '|QuizImageStorage|TUTOR_PRO|tutor/quiz-images|draw-mask-'
                    . '|compare_draw_image_masks|grade_draw_image_question|score_ratio|achieved_mark#i',
                $draw_editor
            ),
            'Draw Image performs a request, file-storage, or grading operation Tutor/Tutor Pro must own.'
        );
        $assert(
            !preg_match('/\buseQuizImageCanvas\s*\(/', $draw_editor)
                && !preg_match('/\buseImageManagement\s*\(/', $draw_editor),
            'Draw Image duplicates the shared canvas or Media Library integration.'
        );

        // Tutor's answer row stores the background and instructor mask separately.
        $assert(
            1 === preg_match('/DRAW_IMAGE_ANSWER_VIEW_FORMAT\s*=\s*"draw_image"/', $draw_editor),
            'Draw Image does not declare Tutor\'s draw_image answer view format.'
        );
        foreach ([
            'belongs_question_type: "draw_image"'                  => 'belongs_question_type',
            'is_correct: "1"'                                     => 'is_correct',
            'answer_view_format: DRAW_IMAGE_ANSWER_VIEW_FORMAT'    => 'answer_view_format',
            'image_id: imageData.id'                               => 'background image ID',
            'image_url: imageData.url'                             => 'background image URL',
            'maskValue={answerRow?.answer_two_gap_match}'          => 'raw instructor mask',
            'onQuestionUpdate(questionIndex, "question_answers", [updated])' => 'single answer row',
        ] as $needle => $label) {
            $assert(
                false !== strpos($draw_editor, $needle),
                "Draw Image does not write or display Tutor's {$label} contract."
            );
        }

        // Opening, loading, or resizing cannot write an answer. The component has no
        // mount effect; the already-asserted shared canvas commits only on a completed
        // stroke or explicit clear.
        $assert(
            false === strpos($draw_editor, 'useEffect'),
            'Draw Image writes or normalizes answer data from a component effect.'
        );
        $assert(
            1 === preg_match(
                '/const handleMaskCommit = \(maskDataUrl: string\) => \{(.+?)\n  \};/s',
                $draw_editor,
                $draw_mask_handler
            ) && 1 === preg_match('/writeAnswer\(\{ answer_two_gap_match: maskDataUrl \}\);/', $draw_mask_handler[1]),
            'Draw Image does not write a mask only from the shared canvas commit callback.'
        );

        // Changing or clearing the background invalidates the mask locally. It must not
        // invent Step 8 registration: unsaved masks are data URLs, while persisted-mask
        // cleanup belongs to Tutor core during a later answer update.
        $assert(
            1 === preg_match(
                '/const handleImageSelect = \(imageData: ImageData\) => \{(.+?)\n  \};/s',
                $draw_editor,
                $draw_select_handler
            ) && 1 === preg_match('/answer_two_gap_match:\s*""/', $draw_select_handler[1]),
            'Replacing a Draw background does not clear the instructor mask locally.'
        );
        $assert(
            1 === preg_match(
                '/const handleImageClear = \(\) => \{(.+?)\n  \};/s',
                $draw_editor,
                $draw_clear_handler
            ) && 1 === preg_match('/image_id:\s*0,\s*image_url:\s*"",\s*answer_two_gap_match:\s*""/s', $draw_clear_handler[1]),
            'Clearing a Draw background does not clear both the background and mask locally.'
        );
        $assert(
            false === strpos($draw_editor, 'collectAbandonedTempMaskValues')
                && false === strpos($draw_editor, 'deleted_temp_mask_values'),
            'Draw background replacement/clearing registers a temporary deletion value.'
        );

        // Tutor's frontend builder rewrites image-bearing answers to text_image. Draw
        // accepts only that native loaded form, its own format, or the empty creation
        // form; the semantic type and every opaque-value guard remain independent.
        $assert(
            1 === preg_match(
                '/export const getDrawImageAnswerState = \(question: QuizQuestion\): DrawImageAnswerState => \{(.+?)\n\};/s',
                $validation,
                $draw_state
            ),
            'Could not delimit Draw Image stored-answer classification.'
        );
        $assert(
            1 === preg_match(
                '/answer\.belongs_question_type !== "draw_image"\s*\|\|\s*!\["", "draw_image", "text_image"\]\.includes\(answer\.answer_view_format\)/',
                $draw_state[1]
            ),
            'Draw Image does not accept exactly the native empty, draw_image, and text_image formats.'
        );
        $assert(
            1 === preg_match('/answers\.length !== 1/', $draw_state[1])
                && 1 === preg_match(
                    '/hasStoredImageId && \(!Number\.isInteger\(imageId\) \|\| imageId <= 0\)/',
                    $draw_state[1]
                )
                && 2 === preg_match_all('/!isSafeImageSource\(/', $draw_state[1]),
            'Draw Image does not preserve unsupported row counts, image IDs, or unavailable stored sources.'
        );
        $assert(
            1 === preg_match('/if \(answerState === "preserved"\) \{/', $draw_editor)
                && false !== strpos($draw_editor, 'every value has been left exactly as saved'),
            'Draw Image does not route an unavailable stored row to a preservation notice.'
        );

        // Native threshold provenance: Tutor creates at 70, offers these seven values,
        // and Pro clamps grading to inclusive 40-100.
        $assert(
            1 === preg_match(
                '/DRAW_IMAGE_THRESHOLD_OPTIONS\s*=\s*\[\s*40,\s*50,\s*60,\s*70,\s*80,\s*90,\s*100\s*\]\s*as const/',
                $validation
            ),
            'Draw Image precision choices do not match Tutor\'s native field.'
        );
        $assert(
            1 === preg_match(
                '/questionType\s*===\s*"draw_image"\s*&&\s*\{\s*draw_image_threshold_percent:\s*70\s*\}/',
                $quiz_types
            ),
            'The shared question factory does not seed Draw Image precision at 70.'
        );
        $assert(
            false !== strpos($draw_editor, 'DRAW_IMAGE_THRESHOLD_OPTIONS.map((value)')
                && false !== strpos($draw_editor, 'draw_image_threshold_percent: Number(rawValue)'),
            'Draw Image does not render and store the native precision choices.'
        );

        // Exactly three rules: background, mask, and inclusive threshold. Preserved
        // external rows skip the two answer-shape rules but never get repaired.
        $assert(
            1 === preg_match('/\n      draw_image:\s*\[(.+?)\n      \],/s', $validation, $draw_rules),
            'Could not delimit Draw Image validation rules.'
        );
        $draw_rule_count = preg_match_all('/\(question: QuizQuestion\) => \{/', $draw_rules[1]);
        $assert(
            3 === $draw_rule_count,
            "Draw Image has {$draw_rule_count} validation rules instead of image, mask, and threshold."
        );
        $assert(
            2 === preg_match_all('/getDrawImageAnswerState\(question\) === "preserved"/', $draw_rules[1]),
            'Draw Image answer validation can block an opaque persisted row.'
        );
        $assert(
            1 === preg_match('/imageId > 0[\s\S]+?answer\.image_url\.trim\(\)\.length > 0/', $draw_rules[1]),
            'Draw Image validation does not require both background image ID and URL.'
        );
        $assert(
            1 === preg_match('/answer_two_gap_match[\s\S]+?mask\.trim\(\)\.length > 0/', $draw_rules[1]),
            'Draw Image validation does not require a non-empty instructor mask.'
        );
        $assert(
            1 === preg_match(
                '/threshold >= DRAW_IMAGE_THRESHOLD_MIN\s*&&\s*threshold <= DRAW_IMAGE_THRESHOLD_MAX/',
                $draw_rules[1]
            ),
            'Draw Image validation does not enforce Tutor Pro\'s inclusive threshold bounds.'
        );

        // ------------------------------------------------------------------
        // Step 10: Pin Image authoring.
        // ------------------------------------------------------------------
        $pin_editor = $read_source('components/modals/quiz/questions/PinImageQuestion.tsx');

        // Pin is a thin consumer of the completed freehand canvas. It must not duplicate
        // media, raster, coordinate, request, storage, grading, or student-point logic.
        $assert(
            1 === preg_match('/import \{ QuizImageCanvas \} from "\.\/QuizImageCanvas";/', $pin_editor)
                && 1 === preg_match_all('/<QuizImageCanvas\b/', $pin_editor),
            'Pin Image does not consume exactly one shared QuizImageCanvas.'
        );
        $assert(
            !preg_match(
                '#\bfetch\(|apiFetch|XMLHttpRequest|FormData|admin-ajax|wp-json|upload_dir'
                    . '|QuizImageStorage|TUTOR_PRO|tutor/quiz-images|pin-mask-'
                    . '|is_pin_inside_mask|grade_pin_image_question|studentAnswer|normalizedPoint|achieved_mark#i',
                $pin_editor
            ),
            'Pin Image performs a request, storage, grading, or student-point operation Tutor/Tutor Pro must own.'
        );
        $assert(
            !preg_match('/\buseQuizImageCanvas\s*\(/', $pin_editor)
                && !preg_match('/\buseImageManagement\s*\(/', $pin_editor),
            'Pin Image duplicates the shared canvas or Media Library integration.'
        );

        // Tutor's answer row stores the background and instructor target mask separately.
        $assert(
            1 === preg_match('/PIN_IMAGE_ANSWER_VIEW_FORMAT\s*=\s*"pin_image"/', $pin_editor),
            'Pin Image does not declare Tutor\'s pin_image answer view format.'
        );
        foreach ([
            'belongs_question_type: "pin_image"'                 => 'belongs_question_type',
            'is_correct: "1"'                                   => 'is_correct',
            'answer_view_format: PIN_IMAGE_ANSWER_VIEW_FORMAT'   => 'answer_view_format',
            'image_id: imageData.id'                             => 'background image ID',
            'image_url: imageData.url'                           => 'background image URL',
            'maskValue={answerRow?.answer_two_gap_match}'         => 'raw instructor mask',
            'onQuestionUpdate(questionIndex, "question_answers", [updated])' => 'single answer row',
        ] as $needle => $label) {
            $assert(
                false !== strpos($pin_editor, $needle),
                "Pin Image does not write or display Tutor's {$label} contract."
            );
        }

        // Pin has no circular authoring geometry and no type-specific setting.
        $assert(
            !preg_match('/\b(?:circle|radius|diameter|centerX|centerY)\b/i', $pin_editor),
            'Pin Image reintroduces circular target geometry instead of the native freehand mask.'
        );
        $assert(
            false === strpos($pin_editor, 'threshold')
                && false === strpos($pin_editor, 'question_settings'),
            'Pin Image adds a threshold or type-specific question setting Tutor does not define.'
        );

        // Opening, loading, or resizing cannot write an answer. Only an explicit shared
        // canvas commit may update `answer_two_gap_match`.
        $assert(
            false === strpos($pin_editor, 'useEffect'),
            'Pin Image writes or normalizes answer data from a component effect.'
        );
        $assert(
            1 === preg_match(
                '/const handleMaskCommit = \(maskDataUrl: string\) => \{(.+?)\n  \};/s',
                $pin_editor,
                $pin_mask_handler
            ) && 1 === preg_match('/writeAnswer\(\{ answer_two_gap_match: maskDataUrl \}\);/', $pin_mask_handler[1]),
            'Pin Image does not write a mask only from the shared canvas commit callback.'
        );

        // Replacing or clearing the background invalidates the mask locally and does not
        // create another Step 8 cleanup producer.
        $assert(
            1 === preg_match(
                '/const handleImageSelect = \(imageData: ImageData\) => \{(.+?)\n  \};/s',
                $pin_editor,
                $pin_select_handler
            ) && 1 === preg_match('/answer_two_gap_match:\s*""/', $pin_select_handler[1]),
            'Replacing a Pin background does not clear the instructor mask locally.'
        );
        $assert(
            1 === preg_match(
                '/const handleImageClear = \(\) => \{(.+?)\n  \};/s',
                $pin_editor,
                $pin_clear_handler
            ) && 1 === preg_match('/image_id:\s*0,\s*image_url:\s*"",\s*answer_two_gap_match:\s*""/s', $pin_clear_handler[1]),
            'Clearing a Pin background does not clear both the background and mask locally.'
        );
        $assert(
            false === strpos($pin_editor, 'collectAbandonedTempMaskValues')
                && false === strpos($pin_editor, 'deleted_temp_mask_values'),
            'Pin background replacement/clearing registers a temporary deletion value.'
        );

        // Pin keeps a parallel classifier and the same native text_image compatibility
        // boundary. The opposite type-specific format and every unknown format remain
        // opaque because the exact accepted set is asserted in the rejecting predicate.
        $assert(
            1 === preg_match(
                '/export const getPinImageAnswerState = \(question: QuizQuestion\): PinImageAnswerState => \{(.+?)\n\};/s',
                $validation,
                $pin_state
            ),
            'Could not delimit Pin Image stored-answer classification.'
        );
        $assert(
            1 === preg_match(
                '/answer\.belongs_question_type !== "pin_image"\s*\|\|\s*!\["", "pin_image", "text_image"\]\.includes\(answer\.answer_view_format\)/',
                $pin_state[1]
            ),
            'Pin Image does not accept exactly the native empty, pin_image, and text_image formats.'
        );
        $assert(
            1 === preg_match('/answers\.length !== 1/', $pin_state[1])
                && 1 === preg_match(
                    '/hasStoredImageId && \(!Number\.isInteger\(imageId\) \|\| imageId <= 0\)/',
                    $pin_state[1]
                )
                && 2 === preg_match_all('/!isSafeImageSource\(/', $pin_state[1]),
            'Pin Image does not preserve unsupported row counts, image IDs, or unavailable stored sources.'
        );
        $assert(
            1 === preg_match('/if \(answerState === "preserved"\) \{/', $pin_editor)
                && false !== strpos($pin_editor, 'every value has been left exactly as saved'),
            'Pin Image does not route an unavailable stored row to a preservation notice.'
        );
        $assert(
            false === strpos($validation, 'getImageAnswerState'),
            'The Draw and Pin classifiers were generalized instead of remaining parallel.'
        );

        // Exactly two rules: background and mask. Both no-op for opaque persisted rows.
        $assert(
            1 === preg_match('/\n      pin_image:\s*\[(.+?)\n      \],/s', $validation, $pin_rules),
            'Could not delimit Pin Image validation rules.'
        );
        $pin_rule_count = preg_match_all('/\(question: QuizQuestion\) => \{/', $pin_rules[1]);
        $assert(
            2 === $pin_rule_count,
            "Pin Image has {$pin_rule_count} validation rules instead of image and mask."
        );
        $assert(
            2 === preg_match_all('/getPinImageAnswerState\(question\) === "preserved"/', $pin_rules[1]),
            'Pin Image answer validation can block an opaque persisted row.'
        );
        $assert(
            1 === preg_match('/imageId > 0[\s\S]+?answer\.image_url\.trim\(\)\.length > 0/', $pin_rules[1]),
            'Pin Image validation does not require both background image ID and URL.'
        );
        $assert(
            1 === preg_match('/answer_two_gap_match[\s\S]+?mask\.trim\(\)\.length > 0/', $pin_rules[1]),
            'Pin Image validation does not require a non-empty instructor mask.'
        );

        // ------------------------------------------------------------------
        // Step 11: Puzzle authoring.
        // ------------------------------------------------------------------
        $puzzle_editor = $read_source('components/modals/quiz/questions/PuzzleQuestion.tsx');
        $puzzle_code   = preg_replace('#/\*.*?\*/#s', '', $puzzle_editor);

        // The shared factory is the sole creator: numeric grid 4 and exactly one native
        // order-0 answer, with no initial attachment fields for the Media Library to fake.
        $assert(
            1 === preg_match(
                '/questionType\s*===\s*"puzzle"\s*&&\s*\{\s*puzzle_grid_size:\s*4\s*\}/',
                $quiz_types
            ),
            'The shared question settings factory does not seed Puzzle grid size 4.'
        );
        $assert(
            1 === preg_match(
                '/export const createDefaultQuestion = \(questionType: QuizQuestionType, questionOrder: number\): QuizQuestion => \{(.+?)\n\};/s',
                $type_metadata,
                $question_factory
            ),
            'Could not delimit the shared question factory.'
        );
        $assert(
            1 === preg_match('/questionType === "puzzle"\s*\?\s*\[(.+?)\]\s*:\s*\[\]/s', $question_factory[1], $puzzle_row),
            'The shared factory does not create exactly one Puzzle answer.'
        );
        foreach ([
            'belongs_question_type: "puzzle"' => 'semantic type',
            'answer_title: ""'                => 'empty title',
            'is_correct: "1"'                 => 'correctness',
            'answer_two_gap_match: ""'        => 'empty source reference',
            'answer_view_format: "puzzle"'    => 'native format',
            'answer_order: 0'                 => 'zero-based order',
            '_data_status: "new"'             => 'new status',
        ] as $needle => $label) {
            $assert(false !== strpos($puzzle_row[1], $needle), "Puzzle factory row is missing {$label}.");
        }
        $assert(
            false === strpos($puzzle_row[1], 'image_id') && false === strpos($puzzle_row[1], 'image_url'),
            'Puzzle factory row invents attachment fields before image selection.'
        );
        $assert(
            false === strpos($puzzle_code, 'useEffect')
                && false === strpos($puzzle_code, 'createDefaultAnswerRow'),
            'PuzzleQuestion seeds or repairs an answer from the component.'
        );

        // Loaded rows are editable only for the exact native formats and independent
        // semantic type, attachment-ID, source-safety, and Content Bank guards.
        $assert(
            1 === preg_match(
                '/export const getPuzzleAnswerState = \(question: QuizQuestion\): PuzzleAnswerState => \{(.+?)\n\};/s',
                $validation,
                $puzzle_state
            ),
            'Could not delimit Puzzle stored-answer classification.'
        );
        $assert(
            1 === preg_match(
                '/answer\.belongs_question_type !== "puzzle"\s*\|\|\s*!\["puzzle", "text_image"\]\.includes\(answer\.answer_view_format\)/',
                $puzzle_state[1]
            ),
            'Puzzle does not accept exactly puzzle and text_image with its semantic type.'
        );
        $assert(
            1 === preg_match('/answers\.length !== 1/', $puzzle_state[1])
                && 1 === preg_match('/question\.content_id !== undefined.+question\.content_id !== null.+question\.content_id !== ""/s', $puzzle_state[1])
                && 1 === preg_match('/typeof rawImageId !== "number".+typeof rawImageId !== "string"/s', $puzzle_state[1])
                && 1 === preg_match('/!Number\.isInteger\(imageId\)\s*\|\|\s*imageId <= 0/', $puzzle_state[1])
                && 2 === substr_count($puzzle_state[1], '!isSafeImageSource('),
            'Puzzle does not preserve unsupported rows, links, image IDs, or unsafe sources.'
        );
        $assert(
            !preg_match('/puzzleReference\.(?:includes|startsWith)|puzzle-|uploads-relative|wp_upload_dir/i', $puzzle_state[1]),
            'Puzzle infers Content Bank linkage from a source-reference shape.'
        );

        // Missing/null grid displays 4 without a write; exact integer 2-7 numbers and
        // numeric strings remain editable, while malformed persisted values are opaque.
        $assert(
            1 === preg_match(
                '/export const getPuzzleGridState = \(question: QuizQuestion\): PuzzleGridState => \{(.+?)\n\};/s',
                $validation,
                $puzzle_grid
            ),
            'Could not delimit Puzzle grid classification.'
        );
        $assert(
            false !== strpos($validation, 'NATIVE_PUZZLE_GRID_SIZE = 4')
                && 1 === preg_match('/PUZZLE_GRID_SIZE_OPTIONS = \[2, 3, 4, 5, 6, 7\] as const/', $validation)
                && false !== strpos($puzzle_grid[1], 'rawGridSize === undefined || rawGridSize === null')
                && false !== strpos($puzzle_grid[1], 'value: NATIVE_PUZZLE_GRID_SIZE')
                && 1 === preg_match('/typeof rawGridSize === "string" && rawGridSize\.trim\(\)\.length > 0/', $puzzle_grid[1])
                && 1 === preg_match('/Number\.isInteger\(gridSize\).+PUZZLE_GRID_SIZE_OPTIONS/s', $puzzle_grid[1])
                && false !== strpos($puzzle_grid[1], 'question.question_id > 0 ? { status: "preserved" }'),
            'Puzzle grid handling does not preserve the exact fallback, options, coercion, and opaque boundary.'
        );

        // The editor consumes the existing media hook directly. Explicit image actions
        // write all three source fields; grid-only edits never touch the answer.
        $assert(
            1 === preg_match_all('/useImageManagement\(\)/', $puzzle_code),
            'Puzzle does not consume exactly one shared Media Library hook.'
        );
        $assert(
            1 === preg_match(
                '/const handleSelectedImage = \(imageData: ImageData\) => \{(.+?)\n  \};/s',
                $puzzle_editor,
                $puzzle_select
            )
                && false !== strpos($puzzle_select[1], 'image_id: imageData.id')
                && false !== strpos($puzzle_select[1], 'image_url: imageData.url')
                && false !== strpos($puzzle_select[1], 'answer_two_gap_match: imageData.url'),
            'Puzzle image selection does not write the exact native source fields.'
        );
        $assert(
            1 === preg_match(
                '/const handleClearImage = \(\) => \{(.+?)\n  \};/s',
                $puzzle_editor,
                $puzzle_clear
            )
                && 1 === preg_match('/image_id:\s*undefined,\s*image_url:\s*"",\s*answer_two_gap_match:\s*""/s', $puzzle_clear[1]),
            'Puzzle image clearing does not empty the exact native source fields.'
        );
        $assert(
            1 === preg_match(
                '/const handleGridChange = \(rawValue: string\) => \{(.+?)\n  \};/s',
                $puzzle_editor,
                $puzzle_grid_handler
            )
                && false !== strpos($puzzle_grid_handler[1], 'puzzle_grid_size: gridSize')
                && false === strpos($puzzle_grid_handler[1], 'writeImageAnswer'),
            'Puzzle grid changes rewrite the answer or fail to update only question settings.'
        );
        $assert(
            false !== strpos($puzzle_editor, 'answerRow.image_url.trim()')
                && false !== strpos($puzzle_editor, 'answerRow.answer_two_gap_match.trim()')
                && false !== strpos($puzzle_editor, 'quiz-modal-puzzle-preserved-notice')
                && false !== strpos($puzzle_editor, 'every value has been left exactly as saved'),
            'Puzzle does not support reference-only display and opaque-value presentation.'
        );

        // Exactly two rules: safe source and integer grid. Every opaque state no-ops and
        // source completion never requires an attachment ID.
        $assert(
            1 === preg_match('/\n      puzzle:\s*\[(.+?)\n      \],/s', $validation, $puzzle_rules),
            'Could not delimit Puzzle validation rules.'
        );
        $puzzle_rule_count = preg_match_all('/\(question: QuizQuestion\) => \{/', $puzzle_rules[1]);
        $assert(
            2 === $puzzle_rule_count,
            "Puzzle has {$puzzle_rule_count} validation rules instead of source and grid."
        );
        $assert(
            2 === preg_match_all('/getPuzzleAnswerState\(question\) === "preserved"/', $puzzle_rules[1])
                && 2 === preg_match_all('/getPuzzleGridState\(question\)/', $puzzle_rules[1])
                && 2 === preg_match_all('/status === "preserved"/', $puzzle_rules[1])
                && false === strpos($puzzle_rules[1], 'image_id')
                && 1 === preg_match('/\[answer\?\.image_url, answer\?\.answer_two_gap_match\]/', $puzzle_rules[1]),
            'Puzzle validation can block opaque rows or requires attachment identity.'
        );

        // Puzzle is media-only and adds no second cleanup producer or Pro-owned protocol.
        $assert(
            !preg_match(
                '#QuizImageCanvas|useQuizImageCanvas|\bcanvas\b|toDataURL|useEffect'
                    . '|collectAbandonedTempMaskValues|deleted_temp_mask_values'
                    . '|\bfetch\(|apiFetch|XMLHttpRequest|FormData|admin-ajax|wp-json'
                    . '|QuizImageStorage|TUTOR_PRO|tutor/quiz-images|\btoken\b|\blocks\b'
                    . '|\bsnapshot\b|\bgrading\b|\battempt\b|wp_delete_file#i',
                $puzzle_code
            ),
            'Puzzle duplicates canvas, cleanup, request, storage, attempt, or grading ownership.'
        );
    }
} catch (Throwable $exception) {
    $failure_message = $exception->getMessage();
} finally {
    if (false === $original_tutor_option) {
        delete_option('tutor_option');
    } else {
        update_option('tutor_option', $original_tutor_option);
    }

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

$summary = sprintf(
    'Tutor %s | settings %s | mode %s | pro %s | native %s | pro-native %s | temp-mask %s | %d types | client wiring %s',
    '' !== $capabilities['tutorVersion'] ? $capabilities['tutorVersion'] : 'unknown',
    $capabilities['quizSettingsContract'],
    $capabilities['learningMode'],
    $capabilities['proActive'] ? 'yes' : 'no',
    $capabilities['hasNativeQuizTypes'] ? 'yes' : 'no',
    $capabilities['proNativeQuizSupport'] ? 'yes' : 'no',
    $capabilities['supportsTempMaskDeletion'] ? 'yes' : 'no',
    count($capabilities['questionTypes']),
    $client_wiring
);

WP_CLI::log("PASS: TutorPress quiz capability contract is valid. {$summary}");
