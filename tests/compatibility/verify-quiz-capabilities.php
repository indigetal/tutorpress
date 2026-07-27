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
 * Assert the structural contract every capability object must satisfy.
 */
$assert_contract_shape = static function (array $capabilities) use ($assert, $native_slugs, $modern_modes) {
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

        // Step 1's temporary inline fallback map must be gone, with no native label left
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

        // Native types whose TutorPress editor does not exist yet must stay unregistered.
        // `scale` is deliberately excluded: Step 5 implemented its editor, so it must be
        // registered, and the assertion below proves that positively.
        $unimplemented_natives = ['draw_image', 'pin_image', 'coordinates', 'puzzle'];

        foreach ($unimplemented_natives as $slug) {
            $assert(
                !preg_match('/^\s*' . preg_quote($slug, '/') . ':/m', $map_block[1]),
                "{$slug} is registered as a local editor before its component exists."
            );
        }

        $assert(
            1 === preg_match('/^\s*scale:\s*ScaleQuestion\s*,/m', $map_block[1]),
            'scale is not registered to ScaleQuestion, so Range is not locally authorable.'
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
    'Tutor %s | mode %s | pro %s | native %s | pro-native %s | temp-mask %s | %d types | client wiring %s',
    '' !== $capabilities['tutorVersion'] ? $capabilities['tutorVersion'] : 'unknown',
    $capabilities['learningMode'],
    $capabilities['proActive'] ? 'yes' : 'no',
    $capabilities['hasNativeQuizTypes'] ? 'yes' : 'no',
    $capabilities['proNativeQuizSupport'] ? 'yes' : 'no',
    $capabilities['supportsTempMaskDeletion'] ? 'yes' : 'no',
    count($capabilities['questionTypes']),
    $client_wiring
);

WP_CLI::log("PASS: TutorPress quiz capability contract is valid. {$summary}");
