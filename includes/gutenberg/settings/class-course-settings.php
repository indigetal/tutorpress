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
     * Extended field names that are stored in _tutor_course_settings array.
     * These fields are not stored as individual meta fields like Course Details.
     *
     * @since 0.1.0
     */
    const EXTENDED_FIELD_NAMES = [
        'maximum_students',
        'schedule',
        'course_enrollment_period',
        'enrollment_starts_at',
        'enrollment_ends_at',
        'pause_enrollment',
        'is_free',
        'pricing_model',
        'price',
        'sale_price',
        'selling_option',
        'woocommerce_product_id',
        'instructors',
        'additional_instructors'
    ];

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
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_attachments_meta_update'], 10, 4);
        
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
                            'type' => 'string',
                            'enum' => ['yes', 'no'],
                        ],
                        'enrollment_starts_at' => [
                            'type' => 'string',
                        ],
                        'enrollment_ends_at' => [
                            'type' => 'string',
                        ],
                        'pause_enrollment' => [
                            'type' => 'string',
                            'enum' => ['yes', 'no'],
                        ],
                        'intro_video' => [
                            'type'       => 'object',
                            'properties' => [
                                'source'               => ['type' => 'string'],
                                'source_video_id'      => ['type' => 'integer'],
                                'source_youtube'       => ['type' => 'string'],
                                'source_vimeo'         => ['type' => 'string'],
                                'source_external_url'  => ['type' => 'string'],
                                'source_embedded'      => ['type' => 'string'],
                                'source_shortcode'     => ['type' => 'string'],
                                'poster'               => ['type' => 'string'],
                            ],
                        ],
                        'attachments' => [
                            'type'  => 'array',
                            'items' => ['type' => 'integer'],
                        ],
                        'course_material_includes' => [
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
                        'selling_option' => [
                            'type' => 'string',
                            'enum' => ['one_time', 'subscription', 'both', 'membership', 'all'],
                        ],
                        'woocommerce_product_id' => [
                            'type' => 'string',
                            'description' => __('WooCommerce product ID for product linking', 'tutorpress'),
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
        
        // Register individual meta fields for Gutenberg access
        $individual_meta_fields = [
            '_tutor_course_level' => [
                'type' => 'string',
                'description' => __('Course difficulty level', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_is_public_course' => [
                'type' => 'string',
                'description' => __('Whether the course is public', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_enable_qa' => [
                'type' => 'string',
                'description' => __('Whether Q&A is enabled', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_course_duration' => [
                'type' => 'object',
                'description' => __('Course duration', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_price_type' => [
                'type' => 'string',
                'description' => __('Course pricing type', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            'tutor_course_price' => [
                'type' => 'number',
                'description' => __('Course price', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            'tutor_course_sale_price' => [
                'type' => 'number',
                'description' => __('Course sale price', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_selling_option' => [
                'type' => 'string',
                'description' => __('Course selling option', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_product_id' => [
                'type' => 'string',
                'description' => __('WooCommerce product ID for product linking', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_prerequisites_ids' => [
                'type' => 'array',
                'description' => __('Course prerequisites', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_material_includes' => [
                'type' => 'string',
                'description' => __('Course materials', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_video' => [
                'type' => 'object',
                'description' => __('Course intro video', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
            '_tutor_course_attachments' => [
                'type' => 'array',
                'description' => __('Course attachments', 'tutorpress'),
                'single' => true,
                'show_in_rest' => true,
            ],
        ];
        
        foreach ($individual_meta_fields as $meta_key => $config) {
            register_post_meta('courses', $meta_key, $config);
        }
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
        
        // Course Media Section: Read from individual Tutor LMS meta fields
        $course_material_includes = get_post_meta($post_id, '_tutor_course_material_includes', true);
        $intro_video = get_post_meta($post_id, '_video', true);
        
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
            'course_prerequisites' => get_post_meta($post_id, '_tutor_course_prerequisites_ids', true) ?: [],
            'schedule' => $tutor_settings['schedule'] ?? [
                'enabled' => false,
                'start_date' => '',
                'start_time' => '',
                'show_coming_soon' => false,
            ],
            'course_enrollment_period' => $tutor_settings['course_enrollment_period'] ?? 'no',
            'enrollment_starts_at' => $tutor_settings['enrollment_starts_at'] ?? '',
            'enrollment_ends_at' => $tutor_settings['enrollment_ends_at'] ?? '',
            'pause_enrollment' => $tutor_settings['pause_enrollment'] ?? 'no',
            'intro_video' => array_merge([
                'source' => '',
                'source_video_id' => 0,
                'source_youtube' => '',
                'source_vimeo' => '',
                'source_external_url' => '',
                'source_embedded' => '',
                'source_shortcode' => '',
                'poster' => '',
            ], is_array($intro_video) ? $intro_video : [], $tutor_settings['featured_video'] ?? [], $tutor_settings['intro_video'] ?? []),
            'attachments' => get_post_meta($post_id, '_tutor_course_attachments', true) ?: [],
            'course_material_includes' => $course_material_includes ?: '',
            
            // Pricing Model Section: Read from individual Tutor LMS meta fields
            'is_free' => get_post_meta($post_id, '_tutor_course_price_type', true) === 'free',
            'pricing_model' => get_post_meta($post_id, '_tutor_course_price_type', true) ?: 'free',
            'price' => (float) get_post_meta($post_id, 'tutor_course_price', true) ?: 0,
            'sale_price' => (float) get_post_meta($post_id, 'tutor_course_sale_price', true) ?: 0,
            'selling_option' => get_post_meta($post_id, '_tutor_course_selling_option', true) ?: 'one_time',
            'woocommerce_product_id' => get_post_meta($post_id, '_tutor_course_product_id', true) ?: '',
            'subscription_enabled' => get_post_meta($post_id, '_tutor_course_selling_option', true) === 'subscription',
        ];
        
        // Update the course_settings meta field with the complete settings structure
        // This ensures Gutenberg can access all settings through the meta field
        update_post_meta($post_id, 'course_settings', $settings);
        
        return $settings;
    }

    /**
     * Update course settings.
     *
     * @since 0.1.0
     * @param array $value Settings to update.
     * @param WP_Post $post Post object.
     * @return bool Whether the update was successful.
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
        
        // Course Media Section: Update individual Tutor LMS meta fields
        if (isset($value['course_material_includes'])) {
            $results[] = update_post_meta($post_id, '_tutor_course_material_includes', $value['course_material_includes']);
        }
        
        // Handle Video Intro field (stored in _video meta field like Tutor LMS)
        if (isset($value['intro_video'])) {
            $intro_video = $value['intro_video'];
            if (is_array($intro_video)) {
                $results[] = update_post_meta($post_id, '_video', $intro_video);
            }
        }
        
        // Handle prerequisites separately (stored in _tutor_course_prerequisites_ids)
        if (isset($value['course_prerequisites'])) {
            $prerequisite_ids = $value['course_prerequisites'];
            if (is_array($prerequisite_ids) && !empty($prerequisite_ids)) {
                // Filter to ensure all values are valid integers
                $prerequisite_ids = array_filter(array_map('intval', $prerequisite_ids));
                $results[] = update_post_meta($post_id, '_tutor_course_prerequisites_ids', $prerequisite_ids);
            } else {
                // Delete the meta if empty
                $results[] = delete_post_meta($post_id, '_tutor_course_prerequisites_ids');
            }
        }

        // Handle course attachments separately (following lesson exercise files pattern)
        if (isset($value['attachments'])) {
            $attachment_ids = $value['attachments'];
            if (is_array($attachment_ids)) {
                // Filter to ensure all values are valid integers
                $attachment_ids = array_filter(array_map('intval', $attachment_ids));
                $attachment_ids = array_unique($attachment_ids);
                
                if (!empty($attachment_ids)) {
                    // Store in our format
                    $results[] = update_post_meta($post_id, '_tutor_course_attachments', $attachment_ids);
                    // Sync to Tutor LMS format
                    $results[] = update_post_meta($post_id, '_tutor_attachments', $attachment_ids);
                } else {
                    // Delete both if empty
                    $results[] = delete_post_meta($post_id, '_tutor_course_attachments');
                    $results[] = delete_post_meta($post_id, '_tutor_attachments');
                }
            }
        }
        
        // Pricing Model Section: Update individual Tutor LMS meta fields
        if (isset($value['pricing_model'])) {
            $pricing_type = $value['pricing_model'] === 'free' ? 'free' : 'paid';
            $results[] = update_post_meta($post_id, '_tutor_course_price_type', $pricing_type);
        }
        
        if (isset($value['price'])) {
            $price = (float) $value['price'];
            $results[] = update_post_meta($post_id, 'tutor_course_price', $price);
        }
        
        if (isset($value['sale_price'])) {
            $sale_price = (float) $value['sale_price'];
            $results[] = update_post_meta($post_id, 'tutor_course_sale_price', $sale_price);
        }
        
        if (isset($value['selling_option'])) {
            $selling_option = sanitize_text_field($value['selling_option']);
            // Validate the selling option
            $valid_options = ['one_time', 'subscription', 'both', 'membership', 'all'];
            if (!in_array($selling_option, $valid_options)) {
                $selling_option = 'one_time'; // Default fallback
            }
            $results[] = update_post_meta($post_id, '_tutor_course_selling_option', $selling_option);
        }
        
        if (isset($value['woocommerce_product_id'])) {
            $product_id = sanitize_text_field($value['woocommerce_product_id']);
            $results[] = update_post_meta($post_id, '_tutor_course_product_id', $product_id);
        }
        
        // Extended sections: Update _tutor_course_settings for non-Course Details fields
        // Note: course_prerequisites and attachments are handled separately above
        
        // Get existing _tutor_course_settings first
        $existing_tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
        if (!is_array($existing_tutor_settings)) {
            $existing_tutor_settings = [];
        }
        
        // Extract only the extended fields we're updating
        $settings_to_update = array_intersect_key($value, array_flip(self::EXTENDED_FIELD_NAMES));
        


        // Special handling for maximum_students and pause_enrollment
        if (isset($settings_to_update['maximum_students'])) {
            $max_students = $settings_to_update['maximum_students'];
            // Handle all falsy values (0, '', null, false) as empty string for unlimited students
            $max_students_value = empty($max_students) ? '' : absint($max_students);
            $settings_to_update['maximum_students'] = $max_students_value;
            $settings_to_update['maximum_students_allowed'] = $max_students_value; // Legacy field
        }
        
        if (isset($settings_to_update['pause_enrollment'])) {
            $pause_value = $settings_to_update['pause_enrollment'];
            // Ensure we store exactly 'yes' or 'no' strings
            $pause_value_normalized = ($pause_value === true || $pause_value === 'yes') ? 'yes' : 'no';
            $settings_to_update['pause_enrollment'] = $pause_value_normalized;
            $settings_to_update['enrollment_status'] = $pause_value_normalized; // Legacy field
        }

        // Special handling for enrollment period dates
        if (isset($settings_to_update['course_enrollment_period'])) {
            $period_value = $settings_to_update['course_enrollment_period'];
            $settings_to_update['course_enrollment_period'] = ($period_value === 'yes') ? 'yes' : 'no';
            
            // If enrollment period is disabled, clear the dates
            if ($settings_to_update['course_enrollment_period'] === 'no') {
                $settings_to_update['enrollment_starts_at'] = '';
                $settings_to_update['enrollment_ends_at'] = '';
            }
        }

        // Ensure dates are in correct MySQL format (YYYY-MM-DD HH:MM:SS)
        foreach (['enrollment_starts_at', 'enrollment_ends_at'] as $date_field) {
            if (isset($settings_to_update[$date_field]) && $settings_to_update[$date_field]) {
                // Parse the date string
                $date = date_create_from_format('Y-m-d H:i:s', $settings_to_update[$date_field]);
                if ($date) {
                    // Format in MySQL format
                    $settings_to_update[$date_field] = $date->format('Y-m-d H:i:s');
                } else {
                    // If parsing fails, try to handle common formats
                    $timestamp = strtotime($settings_to_update[$date_field]);
                    if ($timestamp !== false) {
                        $settings_to_update[$date_field] = date('Y-m-d H:i:s', $timestamp);
                    } else {
                        // If all parsing fails, clear the field
                        $settings_to_update[$date_field] = '';
                    }
                }
            }
        }
        
        // Merge with existing settings, preserving any fields we're not updating
        if (!empty($settings_to_update)) {
            $merged_settings = array_merge($existing_tutor_settings, $settings_to_update);
            $results[] = update_post_meta($post_id, '_tutor_course_settings', $merged_settings);
        }
        
        // Update the course_settings meta field with the complete settings structure
        // This ensures Gutenberg can access all settings through the meta field
        $complete_settings = self::get_course_settings(['id' => $post_id]);
        $results[] = update_post_meta($post_id, 'course_settings', $complete_settings);
        
        // Remove sync flag
        delete_post_meta($post_id, '_tutorpress_syncing_to_tutor');
        
        // Return true if any update succeeded
        return !empty($results) && in_array(true, $results, true);
    }

    /**
     * Handle REST API course update.
     *
     * @since 0.1.0
     * @param WP_Post $post Post object.
     * @param WP_REST_Request $request Request object.
     * @param bool $creating Whether this is a new post.
     * @return void
     */
    public static function handle_rest_course_update($post, $request, $creating) {
        if ($post->post_type !== 'courses') {
            return;
        }

        // Get course settings from request
        $settings = $request->get_param('course_settings');
        if (!is_array($settings)) {
            return;
        }

        // Get existing settings
        $existing_tutor_settings = get_post_meta($post->ID, '_tutor_course_settings', true);
        if (!is_array($existing_tutor_settings)) {
            $existing_tutor_settings = array();
        }

        // Merge settings, ensuring both primary and legacy fields are updated
        $merged_settings = array_merge($existing_tutor_settings, $settings);

        // Handle maximum_students field
        if (isset($settings['maximum_students'])) {
            $max_students = $settings['maximum_students'];
            $merged_settings['maximum_students'] = $max_students;
            $merged_settings['maximum_students_allowed'] = $max_students;
            update_post_meta($post->ID, '_tutor_maximum_students', $max_students);
        }

        // Handle pause_enrollment field
        if (isset($settings['pause_enrollment'])) {
            $pause_enrollment = $settings['pause_enrollment'];
            $merged_settings['pause_enrollment'] = $pause_enrollment;
            $merged_settings['enrollment_status'] = $pause_enrollment;
            update_post_meta($post->ID, '_tutor_enrollment_status', $pause_enrollment);
        }

        // Handle course_enrollment_period field
        if (isset($settings['course_enrollment_period'])) {
            $enrollment_period = $settings['course_enrollment_period'];
            $merged_settings['course_enrollment_period'] = $enrollment_period;
            update_post_meta($post->ID, '_tutor_course_enrollment_period', $enrollment_period);
        }

        // Handle enrollment_starts_at field
        if (isset($settings['enrollment_starts_at'])) {
            $starts_at = $settings['enrollment_starts_at'];
            $merged_settings['enrollment_starts_at'] = $starts_at;
            update_post_meta($post->ID, '_tutor_enrollment_starts_at', $starts_at);
        }

        // Handle enrollment_ends_at field
        if (isset($settings['enrollment_ends_at'])) {
            $ends_at = $settings['enrollment_ends_at'];
            $merged_settings['enrollment_ends_at'] = $ends_at;
            update_post_meta($post->ID, '_tutor_enrollment_ends_at', $ends_at);
        }

        // Update the course settings
        update_post_meta($post->ID, '_tutor_course_settings', $merged_settings);

        // Handle individual meta fields for Course Details section
        if (isset($settings['course_level'])) {
            update_post_meta($post->ID, '_tutor_course_level', $settings['course_level']);
        }

        if (isset($settings['is_public_course'])) {
            update_post_meta($post->ID, '_tutor_is_public_course', $settings['is_public_course'] ? 'yes' : 'no');
        }

        if (isset($settings['enable_qna'])) {
            update_post_meta($post->ID, '_tutor_enable_qa', $settings['enable_qna'] ? 'yes' : 'no');
        }

        if (isset($settings['course_duration']) && is_array($settings['course_duration'])) {
            update_post_meta($post->ID, '_course_duration', $settings['course_duration']);
        }

        if (isset($settings['course_prerequisites'])) {
            update_post_meta($post->ID, '_tutor_course_prerequisites_ids', $settings['course_prerequisites']);
        }

        // Handle course_material_includes field (individual meta field)
        if (isset($settings['course_material_includes'])) {
            update_post_meta($post->ID, '_tutor_course_material_includes', $settings['course_material_includes']);
        }
        
        // Handle pricing fields (individual meta fields)
        if (isset($settings['pricing_model'])) {
            $pricing_type = $settings['pricing_model'] === 'free' ? 'free' : 'paid';
            update_post_meta($post->ID, '_tutor_course_price_type', $pricing_type);
        }
        
        if (isset($settings['price'])) {
            update_post_meta($post->ID, 'tutor_course_price', (float) $settings['price']);
        }
        
        if (isset($settings['sale_price'])) {
            update_post_meta($post->ID, 'tutor_course_sale_price', (float) $settings['sale_price']);
        }
        
        if (isset($settings['selling_option'])) {
            $selling_option = $settings['selling_option'];
            update_post_meta($post->ID, '_tutor_course_selling_option', $selling_option);
        }
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

        // Skip if we're currently syncing to Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_to_tutor', true)) {
            return;
        }

        // Avoid rapid updates
        $last_sync = get_post_meta($post_id, '_tutorpress_tutor_settings_last_sync', true);
        if ($last_sync && (time() - $last_sync) < 5) {
            return;
        }

        // Set sync flag to prevent infinite loops
        update_post_meta($post_id, '_tutorpress_syncing_from_tutor', true);
        update_post_meta($post_id, '_tutorpress_tutor_settings_last_sync', time());

        // Update our course_settings field to match
        update_post_meta($post_id, 'course_settings', $meta_value);

        // Clear sync flag
        delete_post_meta($post_id, '_tutorpress_syncing_from_tutor');
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
        $tutor_fields = [
            '_tutor_course_level', '_tutor_is_public_course', '_tutor_enable_qa', '_course_duration',
            '_tutor_course_prerequisites_ids', '_tutor_maximum_students', '_tutor_enrollment_status',
            '_tutor_course_enrollment_period', '_tutor_enrollment_starts_at', '_tutor_enrollment_ends_at',
            '_tutor_course_material_includes', '_tutor_course_price_type', 'tutor_course_price', 'tutor_course_sale_price',
            '_tutor_course_selling_option'
        ];
        
        if (!in_array($meta_key, $tutor_fields) || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Skip if we're currently syncing to Tutor LMS
        if (get_post_meta($post_id, '_tutorpress_syncing_to_tutor', true)) {
            return;
        }

        // Get current course_settings
        $current_settings = get_post_meta($post_id, 'course_settings', true);
        if (!is_array($current_settings)) {
            $current_settings = array();
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
                    $current_settings['course_duration'] = array('hours' => 0, 'minutes' => 0);
                }
                break;
            case '_tutor_course_prerequisites_ids':
                $current_settings['course_prerequisites'] = is_array($meta_value) ? $meta_value : array();
                break;
            case '_tutor_maximum_students':
                $current_settings['maximum_students'] = $meta_value;
                $current_settings['maximum_students_allowed'] = $meta_value;
                break;
            case '_tutor_enrollment_status':
                $current_settings['pause_enrollment'] = $meta_value;
                $current_settings['enrollment_status'] = $meta_value;
                break;
            case '_tutor_course_enrollment_period':
                $current_settings['course_enrollment_period'] = $meta_value;
                break;
            case '_tutor_enrollment_starts_at':
                $current_settings['enrollment_starts_at'] = $meta_value;
                break;
            case '_tutor_enrollment_ends_at':
                $current_settings['enrollment_ends_at'] = $meta_value;
                break;
            case '_tutor_course_material_includes':
                $current_settings['course_material_includes'] = $meta_value;
                break;
            case '_tutor_course_price_type':
                $current_settings['pricing_model'] = $meta_value ?: 'free';
                $current_settings['is_free'] = $meta_value === 'free';
                break;
            case 'tutor_course_price':
                $current_settings['price'] = (float) $meta_value ?: 0;
                break;
            case 'tutor_course_sale_price':
                $current_settings['sale_price'] = (float) $meta_value ?: 0;
                break;
            case '_tutor_course_selling_option':
                $current_settings['selling_option'] = $meta_value;
                break;
        }

        // Update our course_settings field
        update_post_meta($post_id, 'course_settings', $current_settings);
    }

    /**
     * Handle Tutor LMS _tutor_attachments meta updates to sync back to our field.
     *
     * @since 0.1.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_attachments_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle _tutor_attachments updates for courses
        if ($meta_key !== '_tutor_attachments' || get_post_type($post_id) !== 'courses') {
            return;
        }

        // Avoid infinite loops
        $our_last_update = get_post_meta($post_id, '_tutorpress_attachments_last_sync', true);
        if ($our_last_update && (time() - $our_last_update) < 5) {
            return;
        }

        // Sync course attachments
        update_post_meta($post_id, '_tutorpress_attachments_last_sync', time());
        $attachment_ids = is_array($meta_value) ? array_map('absint', $meta_value) : [];
        update_post_meta($post_id, '_tutor_course_attachments', $attachment_ids);
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
            
            // Update individual Tutor LMS meta fields for core settings
            if (isset($meta_value['course_level'])) {
                update_post_meta($post_id, '_tutor_course_level', $meta_value['course_level']);
            }
            
            if (isset($meta_value['is_public_course'])) {
                $public_value = $meta_value['is_public_course'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_is_public_course', $public_value);
            }
            
            if (isset($meta_value['enable_qna'])) {
                $qna_value = $meta_value['enable_qna'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_enable_qa', $qna_value);
            }
            
            // Handle course_duration separately (Tutor LMS stores this in _course_duration meta field)
            if (isset($meta_value['course_duration'])) {
                update_post_meta($post_id, '_course_duration', $meta_value['course_duration']);
            }
            
            // Handle course_material_includes separately (Tutor LMS stores this in _tutor_course_material_includes meta field)
            if (isset($meta_value['course_material_includes'])) {
                update_post_meta($post_id, '_tutor_course_material_includes', $meta_value['course_material_includes']);
            }
            
            // Handle pricing fields separately (Tutor LMS stores these as individual meta fields)
            if (isset($meta_value['pricing_model'])) {
                $pricing_type = $meta_value['pricing_model'] === 'free' ? 'free' : 'paid';
                update_post_meta($post_id, '_tutor_course_price_type', $pricing_type);
            }
            
            if (isset($meta_value['price'])) {
                update_post_meta($post_id, 'tutor_course_price', (float) $meta_value['price']);
            }
            
            if (isset($meta_value['sale_price'])) {
                update_post_meta($post_id, 'tutor_course_sale_price', (float) $meta_value['sale_price']);
            }
            
            if (isset($meta_value['selling_option'])) {
                $selling_option = $meta_value['selling_option'];
                update_post_meta($post_id, '_tutor_course_selling_option', $selling_option);
            }
            
            // Update _tutor_course_settings for other extended fields
            $settings_to_update = array_intersect_key($meta_value, array_flip(self::EXTENDED_FIELD_NAMES));
            
            if (!empty($settings_to_update)) {
                $merged_settings = array_merge($existing_tutor_settings, $settings_to_update);
                update_post_meta($post_id, '_tutor_course_settings', $merged_settings);
            }
            
            // Remove sync flag
            delete_post_meta($post_id, '_tutorpress_syncing_to_tutor');
        }
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

        // Get course settings from the course_settings meta field
        $course_settings = get_post_meta($post_id, 'course_settings', true);
        
        if (is_array($course_settings) && !empty($course_settings)) {
            // Set sync flag to prevent infinite loops
            update_post_meta($post_id, '_tutorpress_syncing_to_tutor', true);
            
            // Sync individual meta fields
            if (isset($course_settings['course_level'])) {
                update_post_meta($post_id, '_tutor_course_level', $course_settings['course_level']);
            }
            
            if (isset($course_settings['is_public_course'])) {
                $public_value = $course_settings['is_public_course'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_is_public_course', $public_value);
            }
            
            if (isset($course_settings['enable_qna'])) {
                $qna_value = $course_settings['enable_qna'] ? 'yes' : 'no';
                update_post_meta($post_id, '_tutor_enable_qa', $qna_value);
            }
            
            if (isset($course_settings['course_duration'])) {
                update_post_meta($post_id, '_course_duration', $course_settings['course_duration']);
            }
            
            if (isset($course_settings['course_material_includes'])) {
                update_post_meta($post_id, '_tutor_course_material_includes', $course_settings['course_material_includes']);
            }
            
            // Sync pricing fields
            if (isset($course_settings['pricing_model'])) {
                $pricing_type = $course_settings['pricing_model'] === 'free' ? 'free' : 'paid';
                update_post_meta($post_id, '_tutor_course_price_type', $pricing_type);
            }
            
            if (isset($course_settings['price'])) {
                update_post_meta($post_id, 'tutor_course_price', (float) $course_settings['price']);
            }
            
            if (isset($course_settings['sale_price'])) {
                update_post_meta($post_id, 'tutor_course_sale_price', (float) $course_settings['sale_price']);
            }
            
            if (isset($course_settings['selling_option'])) {
                update_post_meta($post_id, '_tutor_course_selling_option', $course_settings['selling_option']);
            }
            
            // Sync prerequisites
            if (isset($course_settings['course_prerequisites'])) {
                update_post_meta($post_id, '_tutor_course_prerequisites_ids', $course_settings['course_prerequisites']);
            }
            
            // Sync intro video
            if (isset($course_settings['intro_video'])) {
                update_post_meta($post_id, '_video', $course_settings['intro_video']);
            }
            
            // Sync attachments
            if (isset($course_settings['attachments'])) {
                update_post_meta($post_id, '_tutor_course_attachments', $course_settings['attachments']);
            }
            
            // Sync extended fields to _tutor_course_settings
            $existing_tutor_settings = get_post_meta($post_id, '_tutor_course_settings', true);
            if (!is_array($existing_tutor_settings)) {
                $existing_tutor_settings = [];
            }
            
            $settings_to_update = array_intersect_key($course_settings, array_flip(self::EXTENDED_FIELD_NAMES));
            
            if (!empty($settings_to_update)) {
                $merged_settings = array_merge($existing_tutor_settings, $settings_to_update);
                update_post_meta($post_id, '_tutor_course_settings', $merged_settings);
            }
            
            // Remove sync flag
            delete_post_meta($post_id, '_tutorpress_syncing_to_tutor');
        }
    }

    /**
     * Sanitize course settings.
     *
     * @since 0.1.0
     * @param array $settings Course settings to sanitize.
     * @return array Sanitized settings.
     */
    public static function sanitize_course_settings($settings) {
        if (!is_array($settings)) {
            return array();
        }

        $sanitized = array();

        // Course Details Section
        if (isset($settings['course_level'])) {
            $allowed_levels = array('beginner', 'intermediate', 'expert', 'all_levels');
            $sanitized['course_level'] = in_array($settings['course_level'], $allowed_levels) ? $settings['course_level'] : 'all_levels';
        }

        if (isset($settings['is_public_course'])) {
            $sanitized['is_public_course'] = (bool) $settings['is_public_course'];
        }

        if (isset($settings['enable_qna'])) {
            $sanitized['enable_qna'] = (bool) $settings['enable_qna'];
        }

        if (isset($settings['course_duration']) && is_array($settings['course_duration'])) {
            $sanitized['course_duration'] = array(
                'hours'   => isset($settings['course_duration']['hours']) ? max(0, intval($settings['course_duration']['hours'])) : 0,
                'minutes' => isset($settings['course_duration']['minutes']) ? min(59, max(0, intval($settings['course_duration']['minutes']))) : 0,
            );
        }

        // Course Access & Enrollment Section
        if (isset($settings['maximum_students'])) {
            // Handle empty string, null, or 0 as null (unlimited)
            $max_students = $settings['maximum_students'];
            if ($max_students === '' || $max_students === null || $max_students === 0 || $max_students === '0') {
                $sanitized['maximum_students'] = null;
            } else {
                $sanitized['maximum_students'] = max(0, intval($max_students));
            }
            // Also set the legacy field
            $sanitized['maximum_students_allowed'] = $sanitized['maximum_students'];
        }

        if (isset($settings['pause_enrollment'])) {
            // Convert to 'yes'/'no' string
            $pause_enrollment = $settings['pause_enrollment'];
            if (is_bool($pause_enrollment)) {
                $sanitized['pause_enrollment'] = $pause_enrollment ? 'yes' : 'no';
            } else {
                $sanitized['pause_enrollment'] = in_array($pause_enrollment, array('yes', 'no')) ? $pause_enrollment : 'no';
            }
            // Also set the legacy field
            $sanitized['enrollment_status'] = $sanitized['pause_enrollment'];
        }

        if (isset($settings['course_enrollment_period'])) {
            $sanitized['course_enrollment_period'] = in_array($settings['course_enrollment_period'], array('yes', 'no')) ? $settings['course_enrollment_period'] : 'no';
        }

        if (isset($settings['enrollment_starts_at'])) {
            $sanitized['enrollment_starts_at'] = sanitize_text_field($settings['enrollment_starts_at']);
        }

        if (isset($settings['enrollment_ends_at'])) {
            $sanitized['enrollment_ends_at'] = sanitize_text_field($settings['enrollment_ends_at']);
        }

        // Course Prerequisites
        if (isset($settings['course_prerequisites']) && is_array($settings['course_prerequisites'])) {
            $sanitized['course_prerequisites'] = array_map('absint', $settings['course_prerequisites']);
        }

        // Schedule
        if (isset($settings['schedule']) && is_array($settings['schedule'])) {
            $sanitized['schedule'] = array(
                'enabled'          => isset($settings['schedule']['enabled']) ? (bool) $settings['schedule']['enabled'] : false,
                'start_date'       => isset($settings['schedule']['start_date']) ? sanitize_text_field($settings['schedule']['start_date']) : '',
                'start_time'       => isset($settings['schedule']['start_time']) ? sanitize_text_field($settings['schedule']['start_time']) : '',
                'show_coming_soon' => isset($settings['schedule']['show_coming_soon']) ? (bool) $settings['schedule']['show_coming_soon'] : false,
            );
        }

        // Pricing Model Section
        if (isset($settings['pricing_model'])) {
            $allowed_models = array('free', 'paid');
            $sanitized['pricing_model'] = in_array($settings['pricing_model'], $allowed_models) ? $settings['pricing_model'] : 'free';
        }

        if (isset($settings['price'])) {
            $sanitized['price'] = max(0, (float) $settings['price']);
        }

        if (isset($settings['sale_price'])) {
            $sanitized['sale_price'] = max(0, (float) $settings['sale_price']);
        }

        if (isset($settings['selling_option'])) {
            $allowed_options = ['one_time', 'subscription', 'both', 'membership', 'all'];
            $sanitized['selling_option'] = in_array($settings['selling_option'], $allowed_options) ? $settings['selling_option'] : 'one_time';
        }

        return $sanitized;
    }
} 