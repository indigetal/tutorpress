<?php
/**
 * Lesson Settings Meta Fields
 *
 * Handles registration of lesson settings meta fields for Gutenberg editor.
 * Provides WordPress-first approach while maintaining bidirectional compatibility
 * with Tutor LMS's native lesson functionality.
 *
 * @package TutorPress
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

class TutorPress_Lesson_Settings {

    /**
     * Initialize the lesson settings.
     *
     * @since 1.4.0
     * @return void
     */
    public static function init() {
        add_action('init', [__CLASS__, 'register_meta_fields']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_fields']);
        
        // Bidirectional sync hooks for Tutor LMS compatibility
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_video_meta_update'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_attachments_meta_update'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_preview_meta_update'], 10, 4);
    }

    /**
     * Register lesson settings meta fields.
     *
     * @since 1.4.0
     * @return void
     */
    public static function register_meta_fields() {
        // Video source type
        register_post_meta('tutor_lessons', '_lesson_video_source', [
            'type'              => 'string',
            'description'       => __('Video source type', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [__CLASS__, 'sanitize_video_source'],
            'show_in_rest'      => true,
        ]);

        // Video source ID (for uploaded videos)
        register_post_meta('tutor_lessons', '_lesson_video_source_id', [
            'type'              => 'integer',
            'description'       => __('Video attachment ID for uploaded videos', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);

        // External video URL
        register_post_meta('tutor_lessons', '_lesson_video_external_url', [
            'type'              => 'string',
            'description'       => __('External video URL', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest'      => true,
        ]);

        // YouTube video URL/ID
        register_post_meta('tutor_lessons', '_lesson_video_youtube', [
            'type'              => 'string',
            'description'       => __('YouTube video URL or ID', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);

        // Vimeo video URL/ID
        register_post_meta('tutor_lessons', '_lesson_video_vimeo', [
            'type'              => 'string',
            'description'       => __('Vimeo video URL or ID', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);

        // Embedded video code
        register_post_meta('tutor_lessons', '_lesson_video_embedded', [
            'type'              => 'string',
            'description'       => __('Embedded video code', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [__CLASS__, 'sanitize_embedded_code'],
            'show_in_rest'      => true,
        ]);

        // Video shortcode
        register_post_meta('tutor_lessons', '_lesson_video_shortcode', [
            'type'              => 'string',
            'description'       => __('Video shortcode', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);

        // Video poster/thumbnail URL
        register_post_meta('tutor_lessons', '_lesson_video_poster', [
            'type'              => 'string',
            'description'       => __('Video poster/thumbnail URL', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest'      => true,
        ]);

        // Video duration - hours
        register_post_meta('tutor_lessons', '_lesson_video_duration_hours', [
            'type'              => 'integer',
            'description'       => __('Video duration in hours', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);

        // Video duration - minutes
        register_post_meta('tutor_lessons', '_lesson_video_duration_minutes', [
            'type'              => 'integer',
            'description'       => __('Video duration in minutes', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => function($value) { return min(59, absint($value)); },
            'show_in_rest'      => true,
        ]);

        // Video duration - seconds
        register_post_meta('tutor_lessons', '_lesson_video_duration_seconds', [
            'type'              => 'integer',
            'description'       => __('Video duration in seconds', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => function($value) { return min(59, absint($value)); },
            'show_in_rest'      => true,
        ]);

        // Exercise files (attachment IDs)
        register_post_meta('tutor_lessons', '_lesson_exercise_files', [
            'type'              => 'array',
            'description'       => __('Exercise file attachment IDs', 'tutorpress'),
            'single'            => true,
            'default'           => [],
            'sanitize_callback' => [__CLASS__, 'sanitize_attachment_ids'],
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]);

        // Lesson preview toggle (requires Tutor Course Preview addon)
        register_post_meta('tutor_lessons', '_lesson_is_preview', [
            'type'              => 'boolean',
            'description'       => __('Whether lesson is available as preview', 'tutorpress'),
            'single'            => true,
            'default'           => false,
            'sanitize_callback' => 'rest_sanitize_boolean',
            'show_in_rest'      => true,
        ]);
    }

    /**
     * Handle Tutor LMS _video meta updates to sync back to individual fields.
     *
     * @since 1.4.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_video_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle _video updates for lessons
        if ($meta_key !== '_video' || get_post_type($post_id) !== 'tutor_lessons') {
            return;
        }

        // Avoid infinite loops
        $our_last_update = get_post_meta($post_id, '_tutorpress_video_last_sync', true);
        if ($our_last_update && (time() - $our_last_update) < 5) {
            return;
        }

        // Sync from Tutor LMS format to our individual fields
        self::sync_from_tutor_video_format($post_id, $meta_value);
    }

    /**
     * Handle Tutor LMS tutor_attachments meta updates.
     *
     * @since 1.4.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_attachments_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle tutor_attachments updates for lessons
        if ($meta_key !== 'tutor_attachments' || get_post_type($post_id) !== 'tutor_lessons') {
            return;
        }

        // Avoid infinite loops
        $our_last_update = get_post_meta($post_id, '_tutorpress_attachments_last_sync', true);
        if ($our_last_update && (time() - $our_last_update) < 5) {
            return;
        }

        // Sync exercise files
        update_post_meta($post_id, '_tutorpress_attachments_last_sync', time());
        $attachment_ids = is_array($meta_value) ? array_map('absint', $meta_value) : [];
        update_post_meta($post_id, '_lesson_exercise_files', $attachment_ids);
    }

    /**
     * Handle Tutor LMS _is_preview meta updates.
     *
     * @since 1.4.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_preview_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle _is_preview updates for lessons
        if ($meta_key !== '_is_preview' || get_post_type($post_id) !== 'tutor_lessons') {
            return;
        }

        // Avoid infinite loops
        $our_last_update = get_post_meta($post_id, '_tutorpress_preview_last_sync', true);
        if ($our_last_update && (time() - $our_last_update) < 5) {
            return;
        }

        // Sync lesson preview setting
        update_post_meta($post_id, '_tutorpress_preview_last_sync', time());
        update_post_meta($post_id, '_lesson_is_preview', rest_sanitize_boolean($meta_value));
    }

    /**
     * Sync from Tutor LMS native video format to individual meta fields.
     *
     * @since 1.4.0
     * @param int $post_id Lesson post ID.
     * @param array $video_data Video data from Tutor LMS.
     * @return void
     */
    private static function sync_from_tutor_video_format($post_id, $video_data = null) {
        if (!$video_data) {
            $video_data = get_post_meta($post_id, '_video', true);
        }
        
        if (empty($video_data) || !is_array($video_data)) {
            return;
        }

        // Mark sync to avoid loops
        update_post_meta($post_id, '_tutorpress_video_last_sync', time());

        // Sync video source and related fields
        if (isset($video_data['source'])) {
            update_post_meta($post_id, '_lesson_video_source', self::sanitize_video_source($video_data['source']));
        }

        if (isset($video_data['source_video_id'])) {
            update_post_meta($post_id, '_lesson_video_source_id', absint($video_data['source_video_id']));
        }

        if (isset($video_data['source_external_url'])) {
            update_post_meta($post_id, '_lesson_video_external_url', esc_url_raw($video_data['source_external_url']));
        }

        if (isset($video_data['source_youtube'])) {
            update_post_meta($post_id, '_lesson_video_youtube', sanitize_text_field($video_data['source_youtube']));
        }

        if (isset($video_data['source_vimeo'])) {
            update_post_meta($post_id, '_lesson_video_vimeo', sanitize_text_field($video_data['source_vimeo']));
        }

        if (isset($video_data['source_embedded'])) {
            update_post_meta($post_id, '_lesson_video_embedded', self::sanitize_embedded_code($video_data['source_embedded']));
        }

        if (isset($video_data['source_shortcode'])) {
            update_post_meta($post_id, '_lesson_video_shortcode', sanitize_text_field($video_data['source_shortcode']));
        }

        if (isset($video_data['poster'])) {
            update_post_meta($post_id, '_lesson_video_poster', esc_url_raw($video_data['poster']));
        }

        // Sync runtime/duration
        if (isset($video_data['runtime']) && is_array($video_data['runtime'])) {
            $runtime = $video_data['runtime'];
            
            if (isset($runtime['hours'])) {
                update_post_meta($post_id, '_lesson_video_duration_hours', absint($runtime['hours']));
            }
            
            if (isset($runtime['minutes'])) {
                update_post_meta($post_id, '_lesson_video_duration_minutes', min(59, absint($runtime['minutes'])));
            }
            
            if (isset($runtime['seconds'])) {
                update_post_meta($post_id, '_lesson_video_duration_seconds', min(59, absint($runtime['seconds'])));
            }
        }
    }

    /**
     * Sync individual meta fields to Tutor LMS native _video format.
     *
     * @since 1.4.0
     * @param int $post_id Lesson post ID.
     * @return void
     */
    private static function sync_to_tutor_video_format($post_id) {
        // Mark that we're syncing to avoid infinite loops
        update_post_meta($post_id, '_tutorpress_video_last_sync', time());

        // Get current values from individual meta fields
        $source = get_post_meta($post_id, '_lesson_video_source', true);
        $source_video_id = (int) get_post_meta($post_id, '_lesson_video_source_id', true);
        $external_url = get_post_meta($post_id, '_lesson_video_external_url', true);
        $youtube = get_post_meta($post_id, '_lesson_video_youtube', true);
        $vimeo = get_post_meta($post_id, '_lesson_video_vimeo', true);
        $embedded = get_post_meta($post_id, '_lesson_video_embedded', true);
        $shortcode = get_post_meta($post_id, '_lesson_video_shortcode', true);
        $poster = get_post_meta($post_id, '_lesson_video_poster', true);
        $hours = (int) get_post_meta($post_id, '_lesson_video_duration_hours', true);
        $minutes = (int) get_post_meta($post_id, '_lesson_video_duration_minutes', true);
        $seconds = (int) get_post_meta($post_id, '_lesson_video_duration_seconds', true);

        // Build Tutor LMS compatible video array
        $video_data = array(
            'source' => $source ?: '-1', // Tutor LMS uses '-1' for no source
        );

        // Add source-specific fields
        if ($source === 'upload' && $source_video_id) {
            $video_data['source_video_id'] = $source_video_id;
        }
        
        if ($source === 'external_url' && $external_url) {
            $video_data['source_external_url'] = $external_url;
        }
        
        if ($source === 'youtube' && $youtube) {
            $video_data['source_youtube'] = $youtube;
        }
        
        if ($source === 'vimeo' && $vimeo) {
            $video_data['source_vimeo'] = $vimeo;
        }
        
        if ($source === 'embedded' && $embedded) {
            $video_data['source_embedded'] = $embedded;
        }
        
        if ($source === 'shortcode' && $shortcode) {
            $video_data['source_shortcode'] = $shortcode;
        }

        // Add poster if available
        if ($poster) {
            $video_data['poster'] = $poster;
        }

        // Add runtime
        $video_data['runtime'] = array(
            'hours' => $hours,
            'minutes' => $minutes,
            'seconds' => $seconds,
        );

        // Update the Tutor LMS native meta field
        update_post_meta($post_id, '_video', $video_data);
    }

    /**
     * Sync exercise files to Tutor LMS tutor_attachments format.
     *
     * @since 1.4.0
     * @param int $post_id Lesson post ID.
     * @return void
     */
    private static function sync_exercise_files($post_id) {
        // Mark sync to avoid loops
        update_post_meta($post_id, '_tutorpress_attachments_last_sync', time());

        // Get exercise files and sync to Tutor LMS format
        $exercise_files = get_post_meta($post_id, '_lesson_exercise_files', true);
        if (!is_array($exercise_files)) {
            $exercise_files = [];
        }

        update_post_meta($post_id, 'tutor_attachments', $exercise_files);
    }

    /**
     * Sync lesson preview to Tutor LMS _is_preview format.
     *
     * @since 1.4.0
     * @param int $post_id Lesson post ID.
     * @return void
     */
    private static function sync_lesson_preview($post_id) {
        // Mark sync to avoid loops
        update_post_meta($post_id, '_tutorpress_preview_last_sync', time());

        // Get lesson preview setting and sync to Tutor LMS format
        $is_preview = get_post_meta($post_id, '_lesson_is_preview', true);
        update_post_meta($post_id, '_is_preview', $is_preview ? 1 : 0);
    }

    /**
     * Get lesson settings for REST API.
     *
     * @since 1.4.0
     * @param array $post Post data.
     * @return array Lesson settings.
     */
    public static function get_lesson_settings($post) {
        $post_id = $post['id'];

        // Check if Tutor Course Preview addon is available
        $course_preview_available = self::is_course_preview_addon_available();

        return [
            'video' => [
                'source' => get_post_meta($post_id, '_lesson_video_source', true),
                'source_video_id' => (int) get_post_meta($post_id, '_lesson_video_source_id', true),
                'source_external_url' => get_post_meta($post_id, '_lesson_video_external_url', true),
                'source_youtube' => get_post_meta($post_id, '_lesson_video_youtube', true),
                'source_vimeo' => get_post_meta($post_id, '_lesson_video_vimeo', true),
                'source_embedded' => get_post_meta($post_id, '_lesson_video_embedded', true),
                'source_shortcode' => get_post_meta($post_id, '_lesson_video_shortcode', true),
                'poster' => get_post_meta($post_id, '_lesson_video_poster', true),
            ],
            'duration' => [
                'hours' => (int) get_post_meta($post_id, '_lesson_video_duration_hours', true),
                'minutes' => (int) get_post_meta($post_id, '_lesson_video_duration_minutes', true),
                'seconds' => (int) get_post_meta($post_id, '_lesson_video_duration_seconds', true),
            ],
            'exercise_files' => array_map('intval', get_post_meta($post_id, '_lesson_exercise_files', true) ?: []),
            'lesson_preview' => [
                'enabled' => (bool) get_post_meta($post_id, '_lesson_is_preview', true),
                'addon_available' => $course_preview_available,
            ],
        ];
    }

    /**
     * Update lesson settings via REST API.
     *
     * @since 1.4.0
     * @param array $value New settings values.
     * @param WP_Post $post Post object.
     * @return bool True on success.
     */
    public static function update_lesson_settings($value, $post) {
        $post_id = $post->ID;

        // Update video settings
        if (isset($value['video'])) {
            $video = $value['video'];
            
            if (isset($video['source'])) {
                update_post_meta($post_id, '_lesson_video_source', self::sanitize_video_source($video['source']));
            }
            
            if (isset($video['source_video_id'])) {
                update_post_meta($post_id, '_lesson_video_source_id', absint($video['source_video_id']));
            }
            
            if (isset($video['source_external_url'])) {
                update_post_meta($post_id, '_lesson_video_external_url', esc_url_raw($video['source_external_url']));
            }
            
            if (isset($video['source_youtube'])) {
                update_post_meta($post_id, '_lesson_video_youtube', sanitize_text_field($video['source_youtube']));
            }
            
            if (isset($video['source_vimeo'])) {
                update_post_meta($post_id, '_lesson_video_vimeo', sanitize_text_field($video['source_vimeo']));
            }
            
            if (isset($video['source_embedded'])) {
                update_post_meta($post_id, '_lesson_video_embedded', self::sanitize_embedded_code($video['source_embedded']));
            }
            
            if (isset($video['source_shortcode'])) {
                update_post_meta($post_id, '_lesson_video_shortcode', sanitize_text_field($video['source_shortcode']));
            }
            
            if (isset($video['poster'])) {
                update_post_meta($post_id, '_lesson_video_poster', esc_url_raw($video['poster']));
            }
        }

        // Update duration settings
        if (isset($value['duration'])) {
            $duration = $value['duration'];
            
            if (isset($duration['hours'])) {
                update_post_meta($post_id, '_lesson_video_duration_hours', absint($duration['hours']));
            }
            
            if (isset($duration['minutes'])) {
                update_post_meta($post_id, '_lesson_video_duration_minutes', min(59, absint($duration['minutes'])));
            }
            
            if (isset($duration['seconds'])) {
                update_post_meta($post_id, '_lesson_video_duration_seconds', min(59, absint($duration['seconds'])));
            }
        }

        // Update exercise files
        if (isset($value['exercise_files'])) {
            $attachment_ids = self::sanitize_attachment_ids($value['exercise_files']);
            update_post_meta($post_id, '_lesson_exercise_files', $attachment_ids);
            
            // Sync to Tutor LMS format
            self::sync_exercise_files($post_id);
        }

        // Update lesson preview (only if addon is available)
        if (isset($value['lesson_preview']['enabled']) && self::is_course_preview_addon_available()) {
            $is_preview = rest_sanitize_boolean($value['lesson_preview']['enabled']);
            update_post_meta($post_id, '_lesson_is_preview', $is_preview);
            
            // Sync to Tutor LMS format
            self::sync_lesson_preview($post_id);
        }

        // Sync to Tutor LMS native formats for compatibility
        self::sync_to_tutor_video_format($post_id);

        return true;
    }

    /**
     * Register REST API fields for lesson settings.
     *
     * @since 1.4.0
     * @return void
     */
    public static function register_rest_fields() {
        register_rest_field('tutor_lessons', 'lesson_settings', [
            'get_callback'    => [__CLASS__, 'get_lesson_settings'],
            'update_callback' => [__CLASS__, 'update_lesson_settings'],
            'schema'          => [
                'description' => __('Lesson settings', 'tutorpress'),
                'type'        => 'object',
                'properties'  => [
                    'video' => [
                        'type'        => 'object',
                        'description' => __('Video settings', 'tutorpress'),
                        'properties'  => [
                            'source' => [
                                'type' => 'string',
                                'enum' => ['', 'upload', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode'],
                            ],
                            'source_video_id' => [
                                'type' => 'integer',
                            ],
                            'source_external_url' => [
                                'type' => 'string',
                                'format' => 'uri',
                            ],
                            'source_youtube' => [
                                'type' => 'string',
                            ],
                            'source_vimeo' => [
                                'type' => 'string',
                            ],
                            'source_embedded' => [
                                'type' => 'string',
                            ],
                            'source_shortcode' => [
                                'type' => 'string',
                            ],
                            'poster' => [
                                'type' => 'string',
                                'format' => 'uri',
                            ],
                        ],
                    ],
                    'duration' => [
                        'type'        => 'object',
                        'description' => __('Video duration', 'tutorpress'),
                        'properties'  => [
                            'hours' => [
                                'type'    => 'integer',
                                'minimum' => 0,
                            ],
                            'minutes' => [
                                'type'    => 'integer',
                                'minimum' => 0,
                                'maximum' => 59,
                            ],
                            'seconds' => [
                                'type'    => 'integer',
                                'minimum' => 0,
                                'maximum' => 59,
                            ],
                        ],
                    ],
                    'exercise_files' => [
                        'type'        => 'array',
                        'description' => __('Exercise file attachment IDs', 'tutorpress'),
                        'items'       => [
                            'type' => 'integer',
                        ],
                    ],
                    'lesson_preview' => [
                        'type'        => 'object',
                        'description' => __('Lesson preview settings', 'tutorpress'),
                        'properties'  => [
                            'enabled' => [
                                'type'        => 'boolean',
                                'description' => __('Whether lesson is available as preview', 'tutorpress'),
                            ],
                            'addon_available' => [
                                'type'        => 'boolean',
                                'description' => __('Whether Tutor Course Preview addon is available', 'tutorpress'),
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        // Register REST endpoint for attachment metadata
        register_rest_route('tutorpress/v1', '/attachments/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_attachment_metadata'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
            'args' => [
                'id' => [
                    'validate_callback' => function($param) {
                        return is_numeric($param);
                    }
                ],
            ],
        ]);
    }

    /**
     * Check if Tutor Course Preview addon is available.
     *
     * @since 1.4.0
     * @return bool True if addon is available.
     */
    private static function is_course_preview_addon_available() {
        // Check if the constant or class exists that indicates the addon is active
        return defined('TUTOR_COURSE_PREVIEW_VERSION') || class_exists('TUTOR_COURSE_PREVIEW\CoursePreview');
    }

    /**
     * Sanitize video source value.
     *
     * @since 1.4.0
     * @param string $source Video source.
     * @return string Sanitized video source.
     */
    public static function sanitize_video_source($source) {
        $allowed_sources = ['', 'upload', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode'];
        return in_array($source, $allowed_sources, true) ? $source : '';
    }

    /**
     * Sanitize embedded video code.
     *
     * @since 1.4.0
     * @param string $code Embedded code.
     * @return string Sanitized embedded code.
     */
    public static function sanitize_embedded_code($code) {
        // Allow iframe and basic HTML tags for embedded videos
        $allowed_tags = [
            'iframe' => [
                'src' => true,
                'width' => true,
                'height' => true,
                'frameborder' => true,
                'allowfullscreen' => true,
                'allow' => true,
            ],
            'video' => [
                'src' => true,
                'width' => true,
                'height' => true,
                'controls' => true,
                'preload' => true,
            ],
            'source' => [
                'src' => true,
                'type' => true,
            ],
        ];
        
        return wp_kses($code, $allowed_tags);
    }

    /**
     * Sanitize attachment IDs array.
     *
     * @since 1.4.0
     * @param mixed $ids Attachment IDs.
     * @return array Sanitized attachment IDs.
     */
    public static function sanitize_attachment_ids($ids) {
        if (!is_array($ids)) {
            return [];
        }
        
        return array_map('absint', array_filter($ids));
    }

    /**
     * Get attachment metadata including video duration.
     *
     * @since 1.4.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function get_attachment_metadata($request) {
        $attachment_id = (int) $request['id'];
        
        if (!wp_attachment_is_image($attachment_id) && !self::is_video_attachment($attachment_id)) {
            return new WP_Error('invalid_attachment', __('Attachment must be an image or video', 'tutorpress'), ['status' => 400]);
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $attachment = get_post($attachment_id);
        
        if (!$attachment) {
            return new WP_Error('attachment_not_found', __('Attachment not found', 'tutorpress'), ['status' => 404]);
        }

        $response_data = [
            'id' => $attachment_id,
            'title' => $attachment->post_title,
            'filename' => basename(get_attached_file($attachment_id)),
            'url' => wp_get_attachment_url($attachment_id),
            'mime_type' => $attachment->post_mime_type,
        ];

        // Add video-specific metadata
        if (self::is_video_attachment($attachment_id)) {
            $duration = self::extract_video_duration($metadata);
            $response_data['duration'] = $duration;
            
            // Add video dimensions if available
            if (isset($metadata['width']) && isset($metadata['height'])) {
                $response_data['width'] = $metadata['width'];
                $response_data['height'] = $metadata['height'];
            }
        }

        return rest_ensure_response($response_data);
    }

    /**
     * Check if attachment is a video file.
     *
     * @since 1.4.0
     * @param int $attachment_id Attachment ID.
     * @return bool True if video attachment.
     */
    private static function is_video_attachment($attachment_id) {
        $mime_type = get_post_mime_type($attachment_id);
        return strpos($mime_type, 'video/') === 0;
    }

    /**
     * Extract video duration from metadata.
     *
     * @since 1.4.0
     * @param array $metadata Attachment metadata.
     * @return array Duration in hours, minutes, seconds.
     */
    private static function extract_video_duration($metadata) {
        $duration = [
            'hours' => 0,
            'minutes' => 0,
            'seconds' => 0,
        ];

        // Check for duration in metadata (WordPress 5.6+)
        if (isset($metadata['length_formatted'])) {
            // Parse formatted duration like "1:23:45" or "23:45"
            $parts = explode(':', $metadata['length_formatted']);
            $parts = array_reverse($parts); // Start from seconds
            
            if (isset($parts[0])) {
                $duration['seconds'] = (int) $parts[0];
            }
            if (isset($parts[1])) {
                $duration['minutes'] = (int) $parts[1];
            }
            if (isset($parts[2])) {
                $duration['hours'] = (int) $parts[2];
            }
        } elseif (isset($metadata['length'])) {
            // Duration in seconds
            $total_seconds = (int) $metadata['length'];
            $duration['hours'] = floor($total_seconds / 3600);
            $duration['minutes'] = floor(($total_seconds % 3600) / 60);
            $duration['seconds'] = $total_seconds % 60;
        }

        return $duration;
    }
} 