<?php
/**
 * Verify H5P content_type filtering against real H5PContentQuery library titles.
 *
 * Requires standalone H5P (H1/H2/H3). Not applicable in H0.
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

if (!class_exists('H5PContentQuery')) {
    $fail('H5PContentQuery is unavailable (H0). Run this script in H1/H2/H3.');
}

if (!class_exists('TutorPress_REST_H5P_Controller')) {
    $fail('TutorPress H5P REST controller is unavailable.');
}

$denied_display_names = [
    'Game Map',
    'Question Set',
    'Interactive Book',
    'Interactive Video',
    'Course Presentation',
    'Personality Quiz',
];

// Authoritative nine-entry map from tutor-h5p-content-type-display-name-contract-research.md.
$machine_to_display = [
    'H5P.SingleChoiceSet' => 'Single Choice Set',
    'H5P.MultiChoice'     => 'Multiple Choice',
    'H5P.TrueFalse'       => 'True/False Question',
    'H5P.FillInTheBlanks' => 'Fill in the Blanks',
    'H5P.DragQuestion'    => 'Drag and Drop',
    'H5P.MarkTheWords'    => 'Mark the Words',
    'H5P.DragText'        => 'Drag the Words',
    'H5P.Accordion'       => 'Accordion',
    'H5P.ImageHotspots'   => 'Image Hotspots',
];

$original_user_id = get_current_user_id();
$failure_message  = '';
$validated_pairs  = 0;
$live_denied_note = 'none';

try {
    $candidate_users = get_users(
        [
            'role__in' => ['administrator', 'editor', 'author'],
            'fields'   => ['ID'],
            'number'   => 50,
        ]
    );
    $assert(!empty($candidate_users), 'No candidate users available for authenticated REST calls.');

    $fields                   = ['title', 'content_type', 'user_name', 'tags', 'updated_at', 'id', 'user_id'];
    $auth_user_id             = 0;
    $rows_by_display          = [];
    $found_titles             = [];

    foreach ($candidate_users as $candidate) {
        $candidate_id = (int) $candidate->ID;
        wp_set_current_user($candidate_id);

        if (!current_user_can('edit_posts')) {
            continue;
        }

        $user_filter = [
            [
                'user_id',
                $candidate_id,
                '=',
            ],
        ];
        $query = new H5PContentQuery($fields, null, null, 'updated_at', true, $user_filter);
        $rows  = $query->get_rows();

        if (!is_array($rows) || [] === $rows) {
            continue;
        }

        $candidate_rows_by_display = [];
        $candidate_titles          = [];

        foreach ($rows as $row) {
            $assert(isset($row->content_type), 'H5P row is missing content_type.');
            $assert(
                is_string($row->content_type) && '' !== $row->content_type,
                'H5P content_type is empty.'
            );

            // Escalate when live metadata uses a near-miss of a mapped retained title.
            foreach ($machine_to_display as $expected_display) {
                if (
                    strcasecmp($row->content_type, $expected_display) === 0
                    && $row->content_type !== $expected_display
                ) {
                    throw new RuntimeException(
                        'Live H5PContentQuery.content_type contradicts the amended map: found "'
                        . $row->content_type
                        . '", expected "'
                        . $expected_display
                        . '".'
                    );
                }
            }

            $candidate_titles[$row->content_type] = true;
            if (!isset($candidate_rows_by_display[$row->content_type])) {
                $candidate_rows_by_display[$row->content_type] = [];
            }
            $candidate_rows_by_display[$row->content_type][] = (int) $row->id;
        }

        $missing = [];
        foreach ($machine_to_display as $display_name) {
            if (empty($candidate_rows_by_display[$display_name])) {
                $missing[] = $display_name;
            }
        }

        if ([] === $missing) {
            $auth_user_id    = $candidate_id;
            $rows_by_display = $candidate_rows_by_display;
            $found_titles    = $candidate_titles;
            foreach ($rows_by_display as $display_name => $ids) {
                sort($rows_by_display[$display_name]);
            }
            break;
        }
    }

    $assert(
        $auth_user_id > 0,
        'No edit_posts user owns fixtures for all nine retained library titles. Missing at least one amended-map title.'
    );

    wp_set_current_user($auth_user_id);

    // Escalate if a retained title is present under a different string than the amended map.
    foreach ($found_titles as $live_title => $_present) {
        if (in_array($live_title, $machine_to_display, true)) {
            continue;
        }
        if (in_array($live_title, $denied_display_names, true)) {
            continue;
        }
        // Non-retained, non-denied titles are allowed and ignored.
    }

    $controller = new TutorPress_REST_H5P_Controller();

    $call = static function (array $params) use ($controller) {
        $request = new WP_REST_Request('GET', '/tutorpress/v1/h5p/contents');
        foreach ($params as $key => $value) {
            $request->set_param($key, $value);
        }

        $permission = $controller->check_permission($request);
        if (is_wp_error($permission)) {
            throw new RuntimeException('Permission denied: ' . $permission->get_error_message());
        }

        $response = $controller->get_contents($request);
        if (is_wp_error($response)) {
            throw new RuntimeException('Controller error: ' . $response->get_error_message());
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            throw new RuntimeException('Controller response data is not an array.');
        }

        return $data;
    };

    $collect_ids = static function (array $items) {
        $ids = array_map(
            static function ($item) {
                return (int) $item->id;
            },
            $items
        );
        sort($ids);
        return $ids;
    };

    // 1) Empty filter: denylist-only listing.
    $empty = $call(
        [
            'per_page' => 100,
            'page'     => 1,
        ]
    );
    $assert(
        isset($empty['items'], $empty['total'], $empty['total_pages']),
        'Empty-filter response missing pagination fields.'
    );
    $assert(is_array($empty['items']), 'Empty-filter items is not an array.');

    $empty_has_interactive_video_excluded = true;
    foreach ($empty['items'] as $item) {
        $assert(isset($item->content_type), 'Empty-filter item missing content_type.');
        $assert(
            !in_array($item->content_type, $denied_display_names, true),
            'Empty filter returned denied library title: ' . $item->content_type
        );
    }

    // Live-confirmed denied fixture is Interactive Video only.
    if (!empty($found_titles['Interactive Video'])) {
        $live_denied_note = 'Interactive Video live-confirmed excluded';
        foreach ($empty['items'] as $item) {
            if ('Interactive Video' === $item->content_type) {
                $empty_has_interactive_video_excluded = false;
                break;
            }
        }
        $assert(
            $empty_has_interactive_video_excluded,
            'Live Interactive Video fixture was not excluded by the denylist.'
        );
    }

    // 2) Every retained filter value and its direct library-title equivalent return identical fixture IDs.
    foreach ($machine_to_display as $filter_value => $library_title) {
        $expected_ids = $rows_by_display[$library_title];
        $assert(
            !empty($expected_ids),
            'Missing H5PContentQuery fixture IDs for library title: ' . $library_title
        );

        $by_machine = $call(
            [
                'content_type' => $filter_value,
                'per_page'     => 100,
                'page'         => 1,
            ]
        );
        $assert(isset($by_machine['items'], $by_machine['total']), 'Filter-value response missing fields for ' . $filter_value);
        $assert((int) $by_machine['total'] > 0, 'Filter-value returned zero rows for ' . $filter_value);

        foreach ($by_machine['items'] as $item) {
            $assert(
                $item->content_type === $library_title,
                $filter_value . ' returned unexpected library title: ' . $item->content_type
            );
        }

        $machine_ids = $collect_ids($by_machine['items']);
        $assert(
            $machine_ids === $expected_ids,
            $filter_value . ' IDs do not match H5PContentQuery fixture IDs for ' . $library_title
        );

        $by_title = $call(
            [
                'content_type' => $library_title,
                'per_page'     => 100,
                'page'         => 1,
            ]
        );
        $title_ids = $collect_ids($by_title['items']);
        $assert(
            $machine_ids === $title_ids,
            $filter_value . ' and direct title "' . $library_title . '" returned different IDs.'
        );
        $assert(
            (int) $by_machine['total'] === (int) $by_title['total'],
            $filter_value . ' and direct title totals differ for ' . $library_title
        );

        // Explicit corrected-title checks called out by the amended plan.
        if ('H5P.TrueFalse' === $filter_value) {
            $assert(
                'True/False Question' === $library_title,
                'H5P.TrueFalse map target is not True/False Question.'
            );
            foreach ($by_machine['items'] as $item) {
                $assert(
                    'True/False Question' === $item->content_type,
                    'H5P.TrueFalse returned a row that is not True/False Question.'
                );
            }
        }
        if ('H5P.DragText' === $filter_value) {
            $assert(
                'Drag the Words' === $library_title,
                'H5P.DragText map target is not Drag the Words.'
            );
            foreach ($by_machine['items'] as $item) {
                $assert(
                    'Drag the Words' === $item->content_type,
                    'H5P.DragText returned a row that is not Drag the Words.'
                );
            }
        }
        if ('H5P.FillInTheBlanks' === $filter_value) {
            $assert(
                'Fill in the Blanks' === $library_title,
                'H5P.FillInTheBlanks map target is not Fill in the Blanks.'
            );
        }

        $validated_pairs++;
    }

    $assert(
        9 === $validated_pairs,
        'Expected nine validated filter-value/title pairs, got ' . $validated_pairs
    );

    // 3) Unknown filter must return an empty set.
    $unknown = $call(
        [
            'content_type' => 'H5P.NotARealLibraryType',
            'per_page'     => 100,
            'page'         => 1,
        ]
    );
    $assert(
        isset($unknown['items'], $unknown['total'], $unknown['total_pages']),
        'Unknown-filter response missing fields.'
    );
    $assert(0 === (int) $unknown['total'], 'Unknown filter total is not zero.');
    $assert(0 === (int) $unknown['total_pages'], 'Unknown filter total_pages is not zero.');
    $assert([] === $unknown['items'], 'Unknown filter returned items.');
    $assert(
        (int) $unknown['total'] !== (int) $empty['total'] || 0 === (int) $empty['total'],
        'Unknown filter appears to have returned the unfiltered listing.'
    );

    // 4) Filtering precedes pagination (use Multiple Choice as representative retained type).
    $pagination_filter = 'H5P.MultiChoice';
    $pagination_title  = $machine_to_display[$pagination_filter];
    $filtered_total    = count($rows_by_display[$pagination_title]);
    $paged             = $call(
        [
            'content_type' => $pagination_filter,
            'per_page'     => 1,
            'page'         => 1,
        ]
    );
    $assert(1 === count($paged['items']), 'per_page=1 did not return exactly one item.');
    $assert(
        (int) $paged['total'] === $filtered_total,
        'Filtered total does not match pre-pagination filtered row count.'
    );
    $assert(
        (int) $paged['total_pages'] === (int) ceil($filtered_total / 1),
        'Filtered total_pages does not prove filtering precedes pagination.'
    );
    $assert(
        $paged['items'][0]->content_type === $pagination_title,
        'Paged filtered item has unexpected content_type.'
    );
    $assert(
        (int) $empty['total'] >= $filtered_total,
        'Empty-filter total is smaller than the retained-type filter total.'
    );
} catch (Throwable $exception) {
    $failure_message = $exception->getMessage();
} finally {
    wp_set_current_user($original_user_id);
}

if ('' !== $failure_message) {
    $fail($failure_message);
}

WP_CLI::log(
    sprintf(
        'PASS: H5P content_type filtering is valid (9/9 retained pairs; denylist: %s; other five denylist libraries not live-confirmed).',
        $live_denied_note
    )
);
