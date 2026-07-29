<?php
/**
 * Verify unavailable-state and cross-version native quiz preservation.
 *
 * The runtime fixture exercises Tutor's generic no_change path for unknown, native,
 * Content Bank-linked/copied, Pro-owned file-reference, H5P, and legacy-broken Image
 * Matching rows whenever the installed Tutor contract can accept that payload.
 *
 * Tutor 4.0 Legacy is intentionally not invoked with native rows: the production
 * client must stop before AJAX because core inspects four modern-only slugs before
 * `_data_status`. Static client assertions prove that pre-request boundary for all five.
 *
 * Every post, custom-table row, answer, attachment, and temporary file created here is
 * removed in a finally block. Existing site content is never selected or mutated.
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

$prefix             = 'tp_native_pres_' . wp_generate_password(8, false, false);
$original_user_id   = get_current_user_id();
$original_post      = isset($_POST) && is_array($_POST) ? $_POST : [];
$course_id          = 0;
$topic_id           = 0;
$quiz_id            = 0;
$attachment_id      = 0;
$attachment_path    = '';
$question_ids       = [];
$failure_message    = '';
$notes              = [];

$read_question = static function ($question_id) use ($wpdb, $assert) {
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT question_id, quiz_id, content_id, question_title, question_description,
                    answer_explanation, question_type, question_mark, question_settings, question_order
             FROM {$wpdb->prefix}tutor_quiz_questions
             WHERE question_id = %d",
            $question_id
        )
    );
    $assert(is_object($row), 'Failed to read a preservation question fixture.');
    return $row;
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
    $assert(is_array($rows), 'Failed to read preservation answer fixtures.');
    return $rows;
};

$snapshot_fixture = static function ($question_id) use ($read_question, $read_answers) {
    return [
        'question' => $read_question($question_id),
        'answers' => $read_answers($question_id),
    ];
};

$assert_snapshot_unchanged = static function (array $before, array $after, $label) use ($assert) {
    $question_fields = [
        'question_id',
        'quiz_id',
        'content_id',
        'question_title',
        'question_description',
        'answer_explanation',
        'question_type',
        'question_mark',
        'question_settings',
        'question_order',
    ];

    foreach ($question_fields as $field) {
        $assert(
            (string) $before['question']->{$field} === (string) $after['question']->{$field},
            "{$label} question field {$field} changed."
        );
    }

    $assert(count($before['answers']) === count($after['answers']), "{$label} answer count changed.");
    foreach ($before['answers'] as $index => $before_answer) {
        $after_answer = $after['answers'][$index];
        foreach (
            [
                'answer_id',
                'belongs_question_id',
                'belongs_question_type',
                'answer_title',
                'is_correct',
                'image_id',
                'answer_two_gap_match',
                'answer_view_format',
                'answer_settings',
                'answer_order',
            ] as $field
        ) {
            $assert(
                (string) $before_answer->{$field} === (string) $after_answer->{$field},
                "{$label} answer {$index} field {$field} changed."
            );
        }
    }
};

$build_question_payload = static function (
    array $snapshot,
    $question_status = 'no_change',
    array $answer_overrides = []
) {
    $row      = $snapshot['question'];
    $settings = maybe_unserialize($row->question_settings);
    $settings = is_array($settings) ? $settings : [];
    $answers  = [];

    foreach ($snapshot['answers'] as $index => $answer) {
        $payload_answer = [
            '_data_status' => 'no_change',
            'answer_id' => (int) $answer->answer_id,
            'belongs_question_id' => (int) $answer->belongs_question_id,
            'belongs_question_type' => (string) $answer->belongs_question_type,
            'answer_title' => (string) $answer->answer_title,
            'is_correct' => (string) $answer->is_correct,
            'image_id' => (int) $answer->image_id,
            'answer_two_gap_match' => (string) $answer->answer_two_gap_match,
            'answer_view_format' => (string) $answer->answer_view_format,
            'answer_settings' => $answer->answer_settings,
            'answer_order' => (int) $answer->answer_order,
        ];

        if (isset($answer_overrides[$index])) {
            $payload_answer = array_merge($payload_answer, $answer_overrides[$index]);
        }
        $answers[] = $payload_answer;
    }

    $question = [
        '_data_status' => $question_status,
        'question_id' => (int) $row->question_id,
        'question_title' => (string) $row->question_title,
        'question_description' => (string) $row->question_description,
        'answer_explanation' => (string) $row->answer_explanation,
        'question_type' => (string) $row->question_type,
        'question_mark' => (float) $row->question_mark,
        'question_order' => (int) $row->question_order,
        'question_settings' => $settings,
        'question_answers' => $answers,
    ];

    if (null !== $row->content_id && '' !== (string) $row->content_id) {
        $question['content_id'] = $row->content_id;
    }

    return $question;
};

$cleanup = static function () use (
    &$course_id,
    &$topic_id,
    &$quiz_id,
    &$attachment_id,
    &$attachment_path,
    &$question_ids,
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
        $all_question_ids = array_unique(
            array_map('intval', array_merge($question_ids, is_array($remaining) ? $remaining : []))
        );

        foreach ($all_question_ids as $question_id) {
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

    if ($attachment_id > 0) {
        wp_delete_attachment($attachment_id, true);
    }

    if ('' !== $attachment_path && is_file($attachment_path)) {
        wp_delete_file($attachment_path);
    }
};

try {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => ['ID']]);
    $assert(!empty($admins), 'No administrator is available for the preservation fixture.');
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

    update_post_meta(
        $quiz_id,
        'tutor_quiz_option',
        [
            'quiz_type' => 'tutor_h5p_quiz',
            'time_limit' => ['time_value' => 0, 'time_type' => 'minutes'],
            'passing_grade' => 80,
        ]
    );

    $png_bytes = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    );
    $assert(is_string($png_bytes), 'Could not decode the temporary attachment.');
    $upload = wp_upload_bits($prefix . '.png', null, $png_bytes);
    $assert(empty($upload['error']) && !empty($upload['file']), 'Could not create the temporary attachment file.');
    $attachment_path = $upload['file'];
    $attachment_id   = wp_insert_attachment(
        [
            'post_mime_type' => 'image/png',
            'post_title' => $prefix . '_image',
            'post_status' => 'inherit',
        ],
        $attachment_path
    );
    $assert(!is_wp_error($attachment_id) && $attachment_id > 0, 'Could not create the temporary attachment.');
    $attachment_url = wp_get_attachment_url($attachment_id);
    $assert(is_string($attachment_url) && '' !== $attachment_url, 'Temporary attachment has no URL.');

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
            'value' => 5,
            'config' => [
                'min' => 0,
                'max' => 10,
                'step' => 1,
                'defaultValue' => 5,
                'pxPerUnit' => 4,
                'labelEvery' => 1,
                'minorTickEvery' => 1,
                'precision' => 0,
            ],
        ],
        JSON_UNESCAPED_SLASHES
    );

    $fixtures = [
        'unknown' => [
            'type' => 'tp_future_question_type',
            'settings' => [
                'tp_unknown_setting' => 'keep-exactly',
                'tp_unknown_nested' => ['one' => ['two' => 'three']],
            ],
            'content_id' => null,
            'answers' => [
                [
                    'title' => 'Unknown answer',
                    'value' => 'tp-unknown-answer-value',
                    'format' => 'tp_future_format',
                    'image_id' => 0,
                ],
            ],
        ],
        'downgraded_scale' => [
            'type' => 'scale',
            'settings' => [],
            'content_id' => null,
            'answers' => [
                [
                    'title' => '',
                    'value' => $scale_value,
                    'format' => 'scale',
                    'image_id' => 0,
                ],
            ],
        ],
        'linked_puzzle' => [
            'type' => 'puzzle',
            'settings' => ['puzzle_grid_size' => 4],
            'content_id' => 987654321,
            'answers' => [
                [
                    'title' => '',
                    'value' => 'puzzle-linked-preserve.png',
                    'format' => 'puzzle',
                    'image_id' => 0,
                ],
            ],
        ],
        'copied_draw' => [
            'type' => 'draw_image',
            'settings' => ['draw_image_threshold_percent' => 80],
            'content_id' => null,
            'answers' => [
                [
                    'title' => '',
                    'value' => 'draw-mask-copied-preserve.png',
                    'format' => 'text_image',
                    'image_id' => 0,
                ],
            ],
        ],
        'pro_file_pin' => [
            'type' => 'pin_image',
            'settings' => [],
            'content_id' => null,
            'answers' => [
                [
                    'title' => '',
                    'value' => 'pin-mask-pro-transition-preserve.png',
                    'format' => 'text_image',
                    'image_id' => 0,
                ],
            ],
        ],
        'image_matching_broken' => [
            'type' => 'matching',
            'settings' => ['is_image_matching' => 1],
            'content_id' => null,
            'answers' => [
                [
                    'title' => '',
                    'value' => 'Stranded author text',
                    'format' => 'text_image',
                    'image_id' => $attachment_id,
                ],
                [
                    'title' => 'Already valid title',
                    'value' => '',
                    'format' => 'text_image',
                    'image_id' => $attachment_id,
                ],
            ],
        ],
        'h5p_keep' => [
            'type' => 'h5p',
            'settings' => [],
            'content_id' => null,
            'description' => '910051',
            'answers' => [],
        ],
        'h5p_delete' => [
            'type' => 'h5p',
            'settings' => [],
            'content_id' => null,
            'description' => '910052',
            'answers' => [
                [
                    'title' => 'Disposable H5P answer',
                    'value' => '',
                    'format' => '',
                    'image_id' => 0,
                ],
            ],
        ],
    ];

    $order = 0;
    foreach ($fixtures as $key => $definition) {
        $order++;
        $settings = array_merge(
            $base_settings,
            ['question_type' => $definition['type']],
            $definition['settings']
        );
        $data = [
            'quiz_id' => $quiz_id,
            'question_title' => $prefix . '_' . $key,
            'question_description' => $definition['description'] ?? $prefix . '_' . $key . '_description',
            'answer_explanation' => $prefix . '_' . $key . '_explanation',
            'question_type' => $definition['type'],
            'question_mark' => 1,
            'question_settings' => maybe_serialize($settings),
            'question_order' => $order,
        ];
        $formats = ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%d'];
        if (null !== $definition['content_id']) {
            $data['content_id'] = $definition['content_id'];
            $formats[] = '%d';
        }

        $inserted = $wpdb->insert($wpdb->prefix . 'tutor_quiz_questions', $data, $formats);
        $assert(false !== $inserted && $wpdb->insert_id > 0, "Failed to insert {$key} question fixture.");
        $question_ids[$key] = (int) $wpdb->insert_id;

        foreach ($definition['answers'] as $answer_order => $answer) {
            $inserted_answer = $wpdb->insert(
                $wpdb->prefix . 'tutor_quiz_question_answers',
                [
                    'belongs_question_id' => $question_ids[$key],
                    'belongs_question_type' => $definition['type'],
                    'answer_title' => $answer['title'],
                    'is_correct' => 1,
                    'image_id' => $answer['image_id'],
                    'answer_two_gap_match' => $answer['value'],
                    'answer_view_format' => $answer['format'],
                    'answer_settings' => null,
                    'answer_order' => $answer_order + 1,
                ],
                ['%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%d']
            );
            $assert(false !== $inserted_answer && $wpdb->insert_id > 0, "Failed to insert {$key} answer fixture.");
        }
    }

    $before = [];
    foreach ($question_ids as $key => $question_id) {
        $before[$key] = $snapshot_fixture($question_id);
    }

    $tutor_version = defined('TUTOR_VERSION') ? TUTOR_VERSION : 'unknown';
    $is_tutor_4    = defined('TUTOR_VERSION') && version_compare(TUTOR_VERSION, '4.0.0', '>=');
    $is_legacy     = function_exists('tutor_utils')
        && method_exists(tutor_utils(), 'is_legacy_learning_mode')
        && tutor_utils()->is_legacy_learning_mode();
    $legacy_native_block = $is_tutor_4 && $is_legacy;

    if ($legacy_native_block) {
        $notes[] = 'Tutor 4 Legacy runtime payload intentionally blocked before QuizBuilder';
    } else {
        $payload_questions = [];
        foreach ($before as $snapshot) {
            $payload_questions[] = $build_question_payload($snapshot);
        }

        $_POST = [];
        $builder = new \TUTOR\QuizBuilder(false);
        $result = $builder->save_quiz(
            $topic_id,
            wp_slash(
                [
                    'ID' => $quiz_id,
                    'post_title' => $prefix . '_quiz_unrelated_edit',
                    'post_content' => $prefix . '_description',
                    'quiz_option' => [
                        'quiz_type' => 'tutor_h5p_quiz',
                        'passing_grade' => 80,
                    ],
                    'questions' => $payload_questions,
                ]
            )
        );
        $assert(is_object($result) && !empty($result->success), 'Generic no_change preservation save failed.');

        foreach ($question_ids as $key => $question_id) {
            $after = $snapshot_fixture($question_id);
            $assert_snapshot_unchanged($before[$key], $after, $key);
        }

        $assert(
            987654321 === (int) $read_question($question_ids['linked_puzzle'])->content_id,
            'The linked Content Bank row lost content_id.'
        );
        $assert(
            null === $read_question($question_ids['copied_draw'])->content_id,
            'The copied row gained Content Bank linkage.'
        );

        $broken_after_no_change = $read_answers($question_ids['image_matching_broken']);
        $assert(
            '' === (string) $broken_after_no_change[0]->answer_title
            && 'Stranded author text' === (string) $broken_after_no_change[0]->answer_two_gap_match,
            'The legacy-broken Image Matching shape was repaired or cleared automatically.'
        );

        // Explicitly repair only the missing title. The known-wrong stranded value is
        // retained because Tutor ignores it in image mode and its presence does not make
        // an otherwise valid image-matching row unacceptable.
        $second_questions = [];
        foreach ($before as $key => $snapshot) {
            if ('h5p_delete' === $key) {
                continue;
            }

            if ('image_matching_broken' === $key) {
                $second_questions[] = $build_question_payload(
                    $snapshot,
                    'no_change',
                    [
                        0 => [
                            '_data_status' => 'update',
                            'answer_title' => 'Explicitly repaired title',
                        ],
                    ]
                );
                continue;
            }

            $second_questions[] = $build_question_payload($snapshot);
        }

        $deleted_h5p_answers = $before['h5p_delete']['answers'];
        $assert(1 === count($deleted_h5p_answers), 'Expected one disposable H5P answer.');
        $_POST = [
            'deleted_question_ids' => [(string) $question_ids['h5p_delete']],
            'deleted_answer_ids' => [(string) $deleted_h5p_answers[0]->answer_id],
        ];

        $result = $builder->save_quiz(
            $topic_id,
            wp_slash(
                [
                    'ID' => $quiz_id,
                    'post_title' => $prefix . '_quiz_unrelated_edit',
                    'post_content' => $prefix . '_description',
                    'quiz_option' => [
                        'quiz_type' => 'tutor_h5p_quiz',
                        'passing_grade' => 80,
                    ],
                    'questions' => $second_questions,
                ]
            )
        );
        $assert(is_object($result) && !empty($result->success), 'Image Matching repair/H5P deletion save failed.');

        $deleted_h5p_question = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT question_id FROM {$wpdb->prefix}tutor_quiz_questions WHERE question_id = %d",
                $question_ids['h5p_delete']
            )
        );
        $deleted_h5p_answer = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT answer_id FROM {$wpdb->prefix}tutor_quiz_question_answers WHERE answer_id = %d",
                $deleted_h5p_answers[0]->answer_id
            )
        );
        $assert(null === $deleted_h5p_question, 'Persisted H5P question deletion regressed.');
        $assert(null === $deleted_h5p_answer, 'Persisted H5P answer deletion regressed.');

        foreach ($question_ids as $key => $question_id) {
            if (in_array($key, ['h5p_delete', 'image_matching_broken'], true)) {
                continue;
            }
            $assert_snapshot_unchanged($before[$key], $snapshot_fixture($question_id), $key . ' after H5P deletion');
        }

        $matching_after = $snapshot_fixture($question_ids['image_matching_broken']);
        $assert(
            'Explicitly repaired title' === (string) $matching_after['answers'][0]->answer_title,
            'The explicit Image Matching title repair was not stored.'
        );
        $assert(
            'Stranded author text' === (string) $matching_after['answers'][0]->answer_two_gap_match,
            'The explicit title repair cleared the preserved stranded value.'
        );
        foreach (
            [
                'answer_id',
                'belongs_question_id',
                'belongs_question_type',
                'is_correct',
                'image_id',
                'answer_view_format',
                'answer_settings',
                'answer_order',
            ] as $field
        ) {
            $assert(
                (string) $before['image_matching_broken']['answers'][0]->{$field}
                === (string) $matching_after['answers'][0]->{$field},
                "Image Matching title repair changed {$field}."
            );
        }

        $notes[] = 'generic no_change preservation exercised';
        $notes[] = 'H5P persisted deletion exercised';
        $notes[] = 'legacy-broken Image Matching value preserved';
    }

    // Client-only failure boundary. Built installations intentionally omit TypeScript
    // source, so these checks run only when the complete source set is staged.
    $src_dir = dirname(__DIR__, 2) . '/assets/js/src';
    if (is_dir($src_dir)) {
        $quiz_modal = (string) file_get_contents($src_dir . '/components/modals/QuizModal.tsx');
        $type_utils = (string) file_get_contents($src_dir . '/utils/quizQuestionTypes.ts');
        $validation = (string) file_get_contents($src_dir . '/hooks/quiz/useQuestionValidation.ts');
        $curriculum = (string) file_get_contents($src_dir . '/store/curriculum/index.ts');
        $matching   = (string) file_get_contents(
            $src_dir . '/components/modals/quiz/questions/MatchingQuestion.tsx'
        );
        $assert(
            '' !== $quiz_modal
            && '' !== $type_utils
            && '' !== $validation
            && '' !== $curriculum
            && '' !== $matching,
            'Client source is incomplete.'
        );

        $assert(
            1 === preg_match(
                '/TUTOR_4_NATIVE_QUESTION_TYPES\s*=\s*\[(.*?)\]\s*as const;/s',
                $type_utils,
                $native_set_match
            ),
            'Could not locate the exact native-type save set.'
        );
        preg_match_all('/"([^"]+)"/', $native_set_match[1], $native_slugs);
        $assert(
            ['draw_image', 'scale', 'pin_image', 'coordinates', 'puzzle'] === $native_slugs[1],
            'The Legacy save guard does not cover exactly all five native types.'
        );
        $assert(
            (bool) preg_match(
                '/learningMode\s*===\s*"legacy"\s*&&\s*capabilities\.hasNativeQuizTypes\s*!==\s*false/',
                $type_utils
            ),
            'The Legacy guard does not distinguish Tutor 4 native contracts from the 3.9 downgrade path.'
        );
        $assert(
            (bool) preg_match('/question\._data_status\s*===\s*"no_change"/', $type_utils),
            'Unavailable rows do not have an explicit no_change-only preservation gate.'
        );
        $assert(
            (bool) preg_match(
                '/capability\?\.registered\s*===\s*true\s*&&\s*capability\.can_edit_existing\s*===\s*true/',
                $type_utils
            ),
            'Loaded-row editing is not positively gated by registration and edit capability.'
        );

        $assert(
            1 === preg_match('/const handleSave = async \(\) => \{(.+?)\n  \};/s', $quiz_modal, $save_handler),
            'Could not delimit QuizModal handleSave.'
        );
        $guard_position      = strpos($save_handler[1], 'getQuestionSaveBlock(');
        $form_position       = strpos($save_handler[1], 'validateEntireForm()');
        $request_position    = strpos($save_handler[1], 'await saveQuiz(');
        $assert(
            false !== $guard_position
            && false !== $form_position
            && false !== $request_position
            && $guard_position < $form_position
            && $form_position < $request_position,
            'The unavailable-row guard does not run before validation and Tutor AJAX.'
        );
        $assert(
            false !== strpos($save_handler[1], 'cannot be saved in Legacy learning mode')
            && false !== strpos($save_handler[1], 'changed while its editing contract was unavailable'),
            'The save guard does not expose focused mode/contract errors.'
        );
        $assert(
            1 === preg_match(
                '/\*saveQuiz\(.*?\n  \},\n\n  \*getQuizDetails/s',
                $curriculum,
                $quiz_resolver
            ),
            'Could not delimit the curriculum saveQuiz resolver.'
        );
        $localized_nonce_position = strpos(
            $quiz_resolver[0],
            'window.tutorPressCurriculum?.tutorNonce'
        );
        $global_nonce_position = strpos($quiz_resolver[0], 'tutorObject?._tutor_nonce');
        $assert(
            false !== $localized_nonce_position
            && false !== $global_nonce_position
            && $localized_nonce_position < $global_nonce_position,
            'Quiz AJAX does not prefer the server-localized Tutor nonce.'
        );
        $assert(
            (bool) preg_match(
                '/catch \(error\) \{.*?type: "SAVE_QUIZ_ERROR".*?throw error;\s*\}/s',
                $quiz_resolver[0]
            ),
            'Quiz AJAX failures do not reject the modal save request.'
        );
        $assert(
            (bool) preg_match(
                '/const editableQuestions = questions\.filter\(\(question\) =>\s*canEditLoadedQuestion/s',
                $quiz_modal
            ),
            'Read-only preservation rows are still sent through editable-row validation.'
        );
        $assert(
            false === strpos($save_handler[1], 'verifyTutorLMSCompatibility(questions)')
            && (bool) preg_match(
                '/const editableQuestions = questions\.filter\(\(question\) =>\s*canEditLoadedQuestion.*?verifyTutorLMSCompatibility\(editableQuestions\)/s',
                $save_handler[1]
            ),
            'Read-only preservation rows are still sent through editable-row compatibility checks.'
        );
        $assert(
            (bool) preg_match(
                '/QuestionComponent\s*&&\s*canEditLoadedQuestion\(/s',
                $quiz_modal
            ),
            'Rendering and save validation do not share the loaded-row decision.'
        );

        $assert(
            (bool) preg_match(
                '/matching:\s*\[.*?emptyOptions\s*=\s*options\.filter\(\(option\)\s*=>\s*!option\.answer_title\?\.trim\(\)\).*?if\s*\(!isImageMatching\).*?!option\.answer_two_gap_match\?\.trim\(\)/s',
                $validation
            ),
            'Image Matching no longer surfaces the missing title while ignoring stranded gap text in image mode.'
        );
        $assert(
            substr_count($matching, 'answer_two_gap_match: currentOptionText') >= 2,
            'Image Matching no longer preserves the existing gap value during explicit title edits.'
        );

        $assert(
            (bool) preg_match(
                '/question\.content_id\s*!==\s*undefined.*?question\.content_id\s*!==\s*null.*?question\.content_id\s*!==\s*""/s',
                $type_utils
            ),
            'Step 8 no longer excludes linked Content Bank rows from temporary cleanup.'
        );
        $notes[] = 'client preservation wiring asserted';
    } else {
        $notes[] = 'client preservation wiring skipped (no TypeScript source)';
    }

    $notes[] = 'Tutor ' . $tutor_version;
    $notes[] = defined('TUTOR_PRO_VERSION') ? 'Pro active' : 'Pro inactive/free';
} catch (Throwable $e) {
    $failure_message = $e->getMessage();
} finally {
    $cleanup();
}

if ('' !== $failure_message) {
    $fail($failure_message);
}

$suffix = empty($notes) ? '' : ' (' . implode('; ', $notes) . ')';
WP_CLI::log('PASS: Native quiz preservation and failure-path safeguards are valid.' . $suffix);
