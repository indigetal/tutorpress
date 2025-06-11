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
        
        // Ensure featured image support for lessons
        add_action('init', [__CLASS__, 'ensure_lesson_featured_image_support'], 20);
        
        // Bidirectional sync hooks for Tutor LMS compatibility
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_video_meta_update'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_attachments_meta_update'], 10, 4);
        add_action('updated_post_meta', [__CLASS__, 'handle_tutor_preview_meta_update'], 10, 4);
        
        // Sync our fields to Tutor LMS when updated
        add_action('updated_post_meta', [__CLASS__, 'handle_lesson_settings_update'], 10, 4);
        
        // Sync on lesson save
        add_action('save_post_lesson', [__CLASS__, 'sync_on_lesson_save'], 999, 3);
    }

    /**
     * Ensure featured image support for lessons.
     *
     * @since 1.4.0
     * @return void
     */
    public static function ensure_lesson_featured_image_support() {
        // Add featured image support to lessons if not already present
        if (post_type_exists('lesson')) {
            add_post_type_support('lesson', 'thumbnail');
            
            // Also ensure theme supports post thumbnails
            if (!current_theme_supports('post-thumbnails')) {
                add_theme_support('post-thumbnails', array('lesson'));
            } else {
                // Add lesson to existing thumbnail support
                add_theme_support('post-thumbnails');
            }
        }
    }

    /**
     * Register lesson settings meta fields.
     *
     * @since 1.4.0
     * @return void
     */
    public static function register_meta_fields() {
        // Video source type
        register_post_meta('lesson', '_lesson_video_source', [
            'type'              => 'string',
            'description'       => __('Video source type', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [__CLASS__, 'sanitize_video_source'],
            'show_in_rest'      => true,
        ]);

        // Video source ID (for uploaded videos)
        register_post_meta('lesson', '_lesson_video_source_id', [
            'type'              => 'integer',
            'description'       => __('Video attachment ID for uploaded videos', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);

        // External video URL
        register_post_meta('lesson', '_lesson_video_external_url', [
            'type'              => 'string',
            'description'       => __('External video URL', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest'      => true,
        ]);

        // YouTube video URL/ID
        register_post_meta('lesson', '_lesson_video_youtube', [
            'type'              => 'string',
            'description'       => __('YouTube video URL or ID', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);

        // Vimeo video URL/ID
        register_post_meta('lesson', '_lesson_video_vimeo', [
            'type'              => 'string',
            'description'       => __('Vimeo video URL or ID', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);

        // Embedded video code
        register_post_meta('lesson', '_lesson_video_embedded', [
            'type'              => 'string',
            'description'       => __('Embedded video code', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => [__CLASS__, 'sanitize_embedded_code'],
            'show_in_rest'      => true,
        ]);

        // Video shortcode
        register_post_meta('lesson', '_lesson_video_shortcode', [
            'type'              => 'string',
            'description'       => __('Video shortcode', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]);

        // Video poster/thumbnail URL
        register_post_meta('lesson', '_lesson_video_poster', [
            'type'              => 'string',
            'description'       => __('Video poster/thumbnail URL', 'tutorpress'),
            'single'            => true,
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
            'show_in_rest'      => true,
        ]);

        // Video duration - hours
        register_post_meta('lesson', '_lesson_video_duration_hours', [
            'type'              => 'integer',
            'description'       => __('Video duration in hours', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => 'absint',
            'show_in_rest'      => true,
        ]);

        // Video duration - minutes
        register_post_meta('lesson', '_lesson_video_duration_minutes', [
            'type'              => 'integer',
            'description'       => __('Video duration in minutes', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => function($value) { return min(59, absint($value)); },
            'show_in_rest'      => true,
        ]);

        // Video duration - seconds
        register_post_meta('lesson', '_lesson_video_duration_seconds', [
            'type'              => 'integer',
            'description'       => __('Video duration in seconds', 'tutorpress'),
            'single'            => true,
            'default'           => 0,
            'sanitize_callback' => function($value) { return min(59, absint($value)); },
            'show_in_rest'      => true,
        ]);

        // Exercise files (attachment IDs)
        register_post_meta('lesson', '_lesson_exercise_files', [
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
        register_post_meta('lesson', '_lesson_is_preview', [
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
        if ($meta_key !== '_video' || get_post_type($post_id) !== 'lesson') {
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
     * Handle Tutor LMS _tutor_attachments meta updates.
     *
     * @since 1.4.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_tutor_attachments_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle _tutor_attachments updates for lessons (with underscore)
        if ($meta_key !== '_tutor_attachments' || get_post_type($post_id) !== 'lesson') {
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
        if ($meta_key !== '_is_preview' || get_post_type($post_id) !== 'lesson') {
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

        // Handle source_html5 field that contains the video URL for uploaded videos
        if (isset($video_data['source_html5'])) {
            update_post_meta($post_id, '_lesson_video_external_url', esc_url_raw($video_data['source_html5']));
        } else {
            // If no source_html5 but we have an ID, try to get URL from attachment
            if (isset($video_data['source_video_id']) && $video_data['source_video_id']) {
                $attachment_url = wp_get_attachment_url($video_data['source_video_id']);
                if ($attachment_url) {
                    update_post_meta($post_id, '_lesson_video_external_url', $attachment_url);
                }
            }
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

        // If no source is set, store -1 as Tutor LMS expects
        if (empty($source)) {
            update_post_meta($post_id, '_video', array('source' => '-1'));
            return;
        }

        // Build Tutor LMS compatible video array
        $video_data = array(
            'source' => $source,
        );

        // Add source-specific fields
        if ($source === 'html5' && $source_video_id) {
            $video_data['source_video_id'] = $source_video_id;
            // For HTML5 videos, also include the URL from the attachment
            $attachment_url = wp_get_attachment_url($source_video_id);
            if ($attachment_url) {
                $video_data['source_html5'] = $attachment_url;
            }
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

        // Sync to the correct Tutor LMS meta key (with underscore)
        if (!empty($exercise_files)) {
            update_post_meta($post_id, '_tutor_attachments', $exercise_files);
        } else {
            delete_post_meta($post_id, '_tutor_attachments');
        }
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
        $tutor_value = $is_preview ? 1 : 0;
        
        update_post_meta($post_id, '_is_preview', $tutor_value);
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
        $course_preview_available = TutorPress_Addon_Checker::is_course_preview_enabled();

        // Debug: Check current meta values
        $gutenberg_preview = get_post_meta($post_id, '_lesson_is_preview', true);
        $tutor_preview = get_post_meta($post_id, '_is_preview', true);

        // Sync from Tutor LMS to Gutenberg if there's a mismatch
        // This ensures that changes made in Tutor LMS frontend are reflected in Gutenberg
        if ($course_preview_available) {
            // Convert values to booleans for comparison
            $tutor_preview_bool = !empty($tutor_preview);
            $gutenberg_preview_bool = !empty($gutenberg_preview);
            
            if ($tutor_preview_bool !== $gutenberg_preview_bool) {
                // Set flag to prevent sync loop
                update_post_meta($post_id, '_tutorpress_syncing_from_tutor', time());
                update_post_meta($post_id, '_lesson_is_preview', $tutor_preview_bool);
                delete_post_meta($post_id, '_tutorpress_syncing_from_tutor');
                
                $gutenberg_preview = $tutor_preview_bool;
            }
        }

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
                'enabled' => (bool) $gutenberg_preview,
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
        register_rest_field('lesson', 'lesson_settings', [
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
                                'enum' => ['', 'html5', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode'],
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
    }



    /**
     * Sanitize video source value.
     *
     * @since 1.4.0
     * @param string $source Video source.
     * @return string Sanitized video source.
     */
    public static function sanitize_video_source($source) {
        // Updated to match Tutor LMS's actual video source types
        $allowed_sources = ['', 'html5', 'youtube', 'vimeo', 'external_url', 'embedded', 'shortcode'];
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
     * Sync our fields to Tutor LMS when updated
     *
     * @since 1.4.0
     * @param int $meta_id Meta ID.
     * @param int $post_id Post ID.
     * @param string $meta_key Meta key.
     * @param mixed $meta_value Meta value.
     * @return void
     */
    public static function handle_lesson_settings_update($meta_id, $post_id, $meta_key, $meta_value) {
        // Only handle our lesson settings meta keys (excluding video fields to avoid conflicts)
        $non_video_meta_keys = [
            '_lesson_exercise_files',
            '_lesson_is_preview'
        ];
        
        if (!in_array($meta_key, $non_video_meta_keys) || get_post_type($post_id) !== 'lesson') {
            return;
        }
        
        // Skip if we're currently syncing from Tutor LMS to prevent loops
        if (get_post_meta($post_id, '_tutorpress_syncing_from_tutor', true)) {
            return;
        }
        
        // Avoid infinite loops
        $our_last_update = get_post_meta($post_id, '_tutorpress_sync_last_update', true);
        if ($our_last_update && (time() - $our_last_update) < 5) {
            return;
        }
        
        update_post_meta($post_id, '_tutorpress_sync_last_update', time());
        
        // Only sync non-video fields to avoid conflicts
        if ($meta_key === '_lesson_exercise_files') {
            self::sync_exercise_files($post_id);
        }
        
        if ($meta_key === '_lesson_is_preview') {
            self::sync_lesson_preview($post_id);
        }
    }

    /**
     * Sync on lesson save
     *
     * @since 1.4.0
     * @param int $post_id Post ID.
     * @param WP_Post $post Post object.
     * @param bool $update Whether this is an existing post being updated or not.
     * @return void
     */
    public static function sync_on_lesson_save($post_id, $post, $update) {
        // Don't sync if this save was initiated by Tutor LMS AJAX
        // This prevents us from overwriting changes made in Tutor LMS frontend
        if (wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'tutor_save_lesson') {
            return;
        }
        
        // Don't sync if this is an autosave or revision
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }
        
        // Sync to Tutor LMS native formats for compatibility
        self::sync_to_tutor_video_format($post_id);
        self::sync_exercise_files($post_id);
        self::sync_lesson_preview($post_id);
    }
} 