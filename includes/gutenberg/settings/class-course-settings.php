<?php
/**
 * Course Settings Meta Fields
 *
 * Handles registration of course settings meta fields for Gutenberg editor.
 * Provides WordPress-first approach while maintaining bidirectional compatibility
 * with Tutor LMS's native course functionality through _tutor_course_settings.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_Course_Settings {

    /**
     * Initialize the course settings.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_meta_fields']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_fields']);
        
        // Bidirectional sync hooks for Tutor LMS compatibility
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_individual_field_update'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_course_settings_update'], 10, 4);
        
        // Sync our fields to Tutor LMS when updated
        add_action('updated_post_meta', [__CLASS__, 'handle_course_settings_update'], 10, 4);
        
        // Also hook into REST API updates (Gutenberg uses REST API, not traditional meta updates)
        add_action('rest_after_insert_courses', [__CLASS__, 'handle_rest_course_update'], 10, 3);
        
        // Sync on course save
        add_action('save_post_courses', [__CLASS__, 'sync_on_course_save'], 999, 3);
    }

    /**
     * Register course settings meta fields.
     *
     * @since 0.1.0
     * @return void
     */
    public static function register_meta_fields() {
        // Register the course_settings meta field for Gutenberg editor
        register_post_meta('courses', 'course_settings', [
            'type'              => 'object',
            'description'       => __('Course settings for TutorPress Gutenberg integration', 'tutorpress'),
            'single'            => true,
            'default'           => [],
            'sanitize_callback' => [__CLASS__, 'sanitize_course_settings'],
            'show_in_rest'      => [
                'schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'course_level' => [
                            'type' => 'string',
                            'enum' => ['beginner', 'intermediate', 'expert', 'all_levels'],
                        ],
                        'is_public_course' => [
                            'type' => 'boolean',
                        ],
                        'enable_qna' => [
                            'type' => 'boolean',
                        ],
                        'course_duration' => [
                            'type'       => 'object',
                            'properties' => [
                                'hours'   => ['type' => 'integer', 'minimum' => 0],
                                'minutes' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 59],
                            ],
                        ],
                        'maximum_students' => [
                            'type'    => 'integer',
                            'minimum' => 0,
                        ],
                        // Additional sections will be added in future steps
                        'course_prerequisites' => [
                            'type'  => 'array',
                            'items' => ['type' => 'integer'],
                        ],
                        'schedule' => [
                            'type'       => 'object',
                            'properties' => [
                                'enabled'          => ['type' => 'boolean'],
                                'start_date'       => ['type' => 'string'],
                                'start_time'       => ['type' => 'string'],
                                'show_coming_soon' => ['type' => 'boolean'],
                            ],
                        ],
                        'course_enrollment_period' => [
                            'type'       => 'object',
                            'properties' => [
                                'start_date' => ['type' => 'string'],
                                'end_date'   => ['type' => 'string'],
                            ],
                        ],
                        'pause_enrollment' => [
                            'type' => 'boolean',
                        ],
                        'featured_video' => [
                            'type'       => 'object',
                            'properties' => [
                                'source'               => ['type' => 'string'],
                                'source_youtube'       => ['type' => 'string'],
                                'source_vimeo'         => ['type' => 'string'],
                                'source_external_url'  => ['type' => 'string'],
                                'source_embedded'      => ['type' => 'string'],
                            ],
                        ],
                        'attachments' => [
                            'type'  => 'array',
                            'items' => ['type' => 'integer'],
                        ],
                        'materials_included' => [
                            'type' => 'string',
                        ],
                        'is_free' => [
                            'type' => 'boolean',
                        ],
                        'pricing_model' => [
                            'type' => 'string',
                        ],
                        'price' => [
                            'type'    => 'number',
                            'minimum' => 0,
                        ],
                        'sale_price' => [
                            'type'    => 'number',
                            'minimum' => 0,
                        ],
                        'subscription_enabled' => [
                            'type' => 'boolean',
                        ],
                        'instructors' => [
                            'type'  => 'array',
                            'items' => ['type' => 'integer'],
                        ],
                        'additional_instructors' => [
                            'type'  => 'array',
                            'items' => ['type' => 'integer'],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * Register REST API fields for course settings.
     *
     * @since 0.1.0
     * @return void
     */
    public static function register_rest_fields() {
        register_rest_field('courses', 'course_settings', [
            'get_callback'    => [__CLASS__, 'get_course_settings'],
            'update_callback' => [__CLASS__, 'update_course_settings'],
            'schema'          => [
                'description' => __('Course settings', 'tutorpress'),
                'type'        => 'object',
            ],
        ]);
    }

    /**
     * Get course settings for REST API.
     *
     * @since 0.1.0
     * @param array $post Post data.
     * @return array Course settings.
     */
    public static function get_course_settings($post) {
        $post_id = $post['id'];
        
        // Course Details Section: Read from individual Tutor LMS meta fields
        $course_level = get_post_meta($post_id, '_tutor_course_level', true);
        $is_public_course = get_post_meta($post_id, '_tutor_is_public_course', true);
        $enable_qna = get_post_meta($post_id, '_tutor_enable_qa', true);
        $course_duration = get_post_meta($post_id, '_course_duration', true);
        
        // Validate and set defaults for Course Details fields
        if (!is_array($course_duration)) {
            $course_duration = ['hours' => 0, 'minutes' => 0];
        }
        
        // Future sections: Read from _tutor_course_settings (when we implement them)
        $tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
        if (!is_array($tutor_settings)) {
            $tutor_settings = [];
        }
        
        // Build settings structure
        $settings = [
            // Course Details Section (individual meta fields)
            'course_level' => $course_level ?: 'all_levels',
            'is_public_course' => $is_public_course === 'yes',
            'enable_qna' => $enable_qna !== 'no',
            'course_duration' => $course_duration,
            
            // Future sections (will be implemented in Steps 3-6)
            'maximum_students' => $tutor_settings['maximum_students'] ?? 0,
            'course_prerequisites' => $tutor_settings['course_prerequisites'] ?? [],
            'schedule' => $tutor_settings['schedule'] ?? [
                'enabled' => false,
                'start_date' => '',
                'start_time' => '',
                'show_coming_soon' => false,
            ],
            'course_enrollment_period' => $tutor_settings['course_enrollment_period'] ?? [
                'start_date' => '',
                'end_date' => '',
            ],
            'pause_enrollment' => $tutor_settings['pause_enrollment'] ?? false,
            'featured_video' => $tutor_settings['featured_video'] ?? [
                'source' => '',
                'source_youtube' => '',
                'source_vimeo' => '',
                'source_external_url' => '',
                'source_embedded' => '',
            ],
            'attachments' => $tutor_settings['attachments'] ?? [],
            'materials_included' => $tutor_settings['materials_included'] ?? '',
            'is_free' => $tutor_settings['is_free'] ?? true,
            'pricing_model' => $tutor_settings['pricing_model'] ?? '',
            'price' => $tutor_settings['price'] ?? 0,
            'sale_price' => $tutor_settings['sale_price'] ?? 0,
            'subscription_enabled' => $tutor_settings['subscription_enabled'] ?? false,
            'instructors' => $tutor_settings['instructors'] ?? [],
            'additional_instructors' => $tutor_settings['additional_instructors'] ?? [],
        ];
        
        return $settings;
    }

    /**
     * Update course settings from REST API.
     *
     * @since 0.1.0
     * @param mixed $value New value.
     * @param object $post Post object.
     * @return bool Success status.
     */
    public static function update_course_settings($value, $post) {
        $post_id = $post->ID;
        
        if (!is_array($value)) {
            return false;
        }
        
        // Set sync flag to prevent infinite loops
        update_post_meta($post_id, '_tutorpress_syncing_to_tutor', true);
        
        $results = [];
        
        // Course Details Section: Update individual Tutor LMS meta fields
        if (isset($value['course_level'])) {
            $results[] = update_post_meta($post_id, '_tutor_course_level', $value['course_level']);
        }
        
        if (isset($value['is_public_course'])) {
            $public_value = $value['is_public_course'] ? 'yes' : 'no';
            $results[] = update_post_meta($post_id, '_tutor_is_public_course', $public_value);
        }
        
        if (isset($value['enable_qna'])) {
            $qna_value = $value['enable_qna'] ? 'yes' : 'no';
            $results[] = update_post_meta($post_id, '_tutor_enable_qa', $qna_value);
        }
        
        if (isset($value['course_duration'])) {
            $results[] = update_post_meta($post_id, '_course_duration', $value['course_duration']);
        }
        
        // Future sections: Update _tutor_course_settings for non-Course Details fields
        $future_section_fields = ['maximum_students', 'course_prerequisites', 'schedule', 
                                 'course_enrollment_period', 'pause_enrollment', 'featured_video', 'attachments',
                                 'materials_included', 'is_free', 'pricing_model', 'price', 'sale_price',
                                 'subscription_enabled', 'instructors', 'additional_instructors'];
        
        $future_updates = array_intersect_key($value, array_flip($future_section_fields));
        
        if (!empty($future_updates)) {
            // Get existing _tutor_course_settings
            $existing_settings = get_post_meta($post_id, '_tutor_course_settings', true);
            if (!is_array($existing_settings)) {
                $existing_settings = [];
            }
            
            // Merge and update
            $updated_settings = array_merge($existing_settings, $future_updates);
            $results[] = update_post_meta($post_id, '_tutor_course_settings', $updated_settings);
        }
        
        // Remove sync flag
        delete_post_meta($post_id, '_tutorpress_syncing_to_tutor');
        
        // Return true if any update succeeded
        return !empty($results) && in_array(true, $results, true);
    }

    /**
     * Handle Tutor LMS _tutor_course_settings meta updates to sync back to our field.
     *
     * @since 0.1.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_course_settings_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle _tutor_course_settings updates for courses
        if ($meta_key !== '_tutor_course_settings' || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Avoid infinite loops - skip if we're syncing to Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_to_tutor', true)) {
            return;
        }

        // Avoid rapid updates
        $last_sync = get_post_meta($post_id, '_tutorpress_course_settings_last_sync', true);
        if ($last_sync && (time() - $last_sync) < 5) {
            return;
        }

        // Update our course_settings field to match
        update_post_meta($post_id, '_tutorpress_course_settings_last_sync', time());
        update_post_meta($post_id, 'course_settings', $meta_value);
    }

    /**
     * Handle individual Tutor LMS meta field updates to sync back to our field.
     *
     * @since 0.1.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_individual_field_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle individual Tutor LMS fields for courses
        $tutor_fields = ['_tutor_course_level', '_tutor_is_public_course', '_tutor_enable_qa', '_course_duration'];
        if (!in_array($meta_key, $tutor_fields) || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Avoid infinite loops - skip if we're syncing to Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_to_tutor', true)) {
            return;
        }

        // Avoid rapid updates
        $last_sync = get_post_meta($post_id, '_tutorpress_course_settings_last_sync', true);
        if ($last_sync && (time() - $last_sync) < 5) {
            return;
        }

        // Get current course_settings
        $current_settings = get_post_meta($post_id, 'course_settings', true);
        if (!is_array($current_settings)) {
            $current_settings = [];
        }

        // Update the specific field in our settings
        switch ($meta_key) {
            case '_tutor_course_level':
                $current_settings['course_level'] = $meta_value ?: 'all_levels';
                break;
            case '_tutor_is_public_course':
                $current_settings['is_public_course'] = $meta_value === 'yes';
                break;
            case '_tutor_enable_qa':
                $current_settings['enable_qna'] = $meta_value !== 'no';
                break;
            case '_course_duration':
                if (is_array($meta_value)) {
                    $current_settings['course_duration'] = $meta_value;
                } else {
                    $current_settings['course_duration'] = ['hours' => 0, 'minutes' => 0];
                }
                break;
        }

        // Update our course_settings field
        update_post_meta($post_id, '_tutorpress_course_settings_last_sync', time());
        update_post_meta($post_id, 'course_settings', $current_settings);
    }

    /**
     * Handle course_settings meta updates to sync to Tutor LMS.
     *
     * @since 0.1.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_course_settings_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle course_settings updates for courses
        if ($meta_key !== 'course_settings' || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Skip if we're currently syncing from Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_from_tutor', true)) {
            return;
        }

        // Avoid rapid updates
        $last_sync = get_post_meta($post_id, '_tutorpress_course_settings_last_sync', true);
        if ($last_sync && (time() - $last_sync) < 5) {
            return;
        }

        // Get existing Tutor LMS settings
        $existing_tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
        if (!is_array($existing_tutor_settings)) {
            $existing_tutor_settings = [];
        }

        // Sync to individual Tutor LMS meta fields and _tutor_course_settings
        if (is_array($meta_value)) {
            // Set sync flag to prevent infinite loops
            update_post_meta($post_id, '_tutorpress_syncing_to_tutor', true);
            update_post_meta($post_id, '_tutorpress_course_settings_last_sync', time());
            
            // DEBUG: Log what we're syncing and set a flag we can check from JavaScript
            error_log("TutorPress Sync Debug - Post ID: $post_id");
            error_log("TutorPress Sync Debug - Meta Value: " . print_r($meta_value, true));
            
            // Set a flag that JavaScript can check to confirm this method was called
            update_post_meta($post_id, '_tutorpress_sync_debug_flag', time());
            
            // Update individual Tutor LMS meta fields for core settings
            if (isset($meta_value['course_level'])) {
                update_post_meta($post_id, '_tutor_course_level', $meta_value['course_level']);
                error_log("TutorPress Sync Debug - Updated course_level: " . $meta_value['course_level']);
            }
            
            if (isset($meta_value['is_public_course'])) {
                $public_value = $meta_value['is_public_course'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_is_public_course', $public_value);
                error_log("TutorPress Sync Debug - Updated is_public_course: " . $public_value);
            }
            
            if (isset($meta_value['enable_qna'])) {
                $qna_value = $meta_value['enable_qna'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_enable_qa', $qna_value);
                error_log("TutorPress Sync Debug - Updated enable_qa: " . $qna_value);
            }
            
            // Handle course_duration separately (Tutor LMS stores this in _course_duration meta field)
            if (isset($meta_value['course_duration'])) {
                update_post_meta($post_id, '_course_duration', $meta_value['course_duration']);
                error_log("TutorPress Sync Debug - Updated _course_duration: " . print_r($meta_value['course_duration'], true));
            }
            
            // Update _tutor_course_settings for other extended fields
            $extended_fields = ['maximum_students', 'course_prerequisites', 'schedule', 
                               'course_enrollment_period', 'pause_enrollment', 'featured_video', 'attachments',
                               'materials_included', 'is_free', 'pricing_model', 'price', 'sale_price',
                               'subscription_enabled', 'instructors', 'additional_instructors'];
            
            $extended_updates = array_intersect_key($meta_value, array_flip($extended_fields));
            error_log("TutorPress Sync Debug - Extended updates: " . print_r($extended_updates, true));
            
            if (!empty($extended_updates)) {
                error_log("TutorPress Sync Debug - Existing tutor settings: " . print_r($existing_tutor_settings, true));
                $updated_settings = array_merge($existing_tutor_settings, $extended_updates);
                error_log("TutorPress Sync Debug - Updated settings: " . print_r($updated_settings, true));
                $result = update_post_meta($post_id, '_tutor_course_settings', $updated_settings);
                error_log("TutorPress Sync Debug - Update result: " . ($result ? 'SUCCESS' : 'FAILED'));
            } else {
                error_log("TutorPress Sync Debug - No extended updates to process");
            }
            
            // Remove sync flag
            delete_post_meta($post_id, '_tutorpress_syncing_to_tutor');
        }
    }

    /**
     * Handle REST API course updates (from Gutenberg).
     *
     * @since 0.1.0
     * @param WP_Post $post Inserted or updated post object.
     * @param WP_REST_Request $request Request object.
     * @param bool $creating True when creating a post, false when updating.
     * @return void
     */
    public static function handle_rest_course_update($post, $request, $creating) {
        // Only handle course updates
        if ($post->post_type !== 'courses') {
            return;
        }

        // Skip if we're currently syncing to prevent infinite loops
        if (get_post_meta($post->ID, '_tutorpress_syncing_to_tutor', true)) {
            return;
        }

        // Check if course_settings was updated in this request
        $meta_data = $request->get_param('meta');
        if (!is_array($meta_data) || !isset($meta_data['course_settings'])) {
            return;
        }

        $course_settings = $meta_data['course_settings'];
        
        // DEBUG: Set flag to confirm this method was called
        update_post_meta($post->ID, '_tutorpress_rest_sync_debug_flag', time());
        error_log("TutorPress REST Sync Debug - Post ID: {$post->ID}");
        error_log("TutorPress REST Sync Debug - Course Settings: " . print_r($course_settings, true));

        // Call our existing sync logic
        self::handle_course_settings_update(0, $post->ID, 'course_settings', $course_settings);
    }

    /**
     * Sync on course save.
     *
     * @since 0.1.0
     * @param int $post_id Post ID.
     * @param object $post Post object.
     * @param bool $update Whether this is an update.
     * @return void
     */
    public static function sync_on_course_save($post_id, $post, $update) {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        // Ensure both meta fields are in sync
        $course_settings = get_post_meta($post_id, 'course_settings', true);
        $tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);

        if (is_array($course_settings) && !empty($course_settings)) {
            if (!is_array($tutor_settings)) {
                $tutor_settings = [];
            }
            
            $merged_settings = array_merge($tutor_settings, $course_settings);
            update_post_meta($post_id, '_tutor_course_settings', $merged_settings);
        }
    }

    /**
     * Sanitize course settings.
     *
     * @since 0.1.0
     * @param mixed $settings Course settings.
     * @return array Sanitized settings.
     */
    public static function sanitize_course_settings($settings) {
        if (!is_array($settings)) {
            return [];
        }

        $sanitized = [];

        // Sanitize course_level
        if (isset($settings['course_level'])) {
            $allowed_levels = ['beginner', 'intermediate', 'expert', 'all_levels'];
            $sanitized['course_level'] = in_array($settings['course_level'], $allowed_levels) 
                ? $settings['course_level'] : 'all_levels';
        }

        // Sanitize boolean fields
        $boolean_fields = ['is_public_course', 'enable_qna', 'pause_enrollment', 'is_free', 'subscription_enabled'];
        foreach ($boolean_fields as $field) {
            if (isset($settings[$field])) {
                $sanitized[$field] = (bool) $settings[$field];
            }
        }

        // Sanitize course_duration
        if (isset($settings['course_duration']) && is_array($settings['course_duration'])) {
            $sanitized['course_duration'] = [
                'hours' => isset($settings['course_duration']['hours']) 
                    ? max(0, (int) $settings['course_duration']['hours']) : 0,
                'minutes' => isset($settings['course_duration']['minutes']) 
                    ? max(0, min(59, (int) $settings['course_duration']['minutes'])) : 0,
            ];
        }

        // Sanitize integer fields
        if (isset($settings['maximum_students'])) {
            $sanitized['maximum_students'] = max(0, (int) $settings['maximum_students']);
        }

        // Sanitize string fields
        $string_fields = ['pricing_model', 'materials_included'];
        foreach ($string_fields as $field) {
            if (isset($settings[$field])) {
                $sanitized[$field] = sanitize_text_field($settings[$field]);
            }
        }

        // Sanitize numeric fields
        $numeric_fields = ['price', 'sale_price'];
        foreach ($numeric_fields as $field) {
            if (isset($settings[$field])) {
                $sanitized[$field] = max(0, (float) $settings[$field]);
            }
        }

        // Sanitize array fields (attachment IDs, user IDs, course IDs)
        $array_fields = ['course_prerequisites', 'attachments', 'instructors', 'additional_instructors'];
        foreach ($array_fields as $field) {
            if (isset($settings[$field]) && is_array($settings[$field])) {
                $sanitized[$field] = array_map('absint', array_filter($settings[$field]));
            }
        }

        return $sanitized;
    }
} 