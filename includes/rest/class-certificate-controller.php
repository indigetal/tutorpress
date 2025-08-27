<?php
/**
 * Certificate REST Controller Class
 *
 * Handles REST API functionality for certificate templates and selection.
 * Only loads when Certificate addon is enabled.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_Certificate_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'certificate';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        // Get available certificate templates
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/templates',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_templates'],
                    'permission_callback' => [$this, 'check_certificate_permission'],
                    'args'               => [
                        'include_none' => [
                            'type'        => 'boolean',
                            'default'     => true,
                            'description' => __('Include "none" and "off" template options.', 'tutorpress'),
                        ],
                    ],
                ],
            ]
        );

        // Save certificate template selection for a course
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/save',
            [
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'save_selection'],
                    'permission_callback' => [$this, 'check_course_edit_permission'],
                    'args'               => [
                        'course_id' => [
                            'required'          => true,
                            'type'             => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to save certificate template for.', 'tutorpress'),
                            'validate_callback' => [$this, 'validate_course_id'],
                        ],
                        'template_key' => [
                            'required'          => true,
                            'type'             => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                            'description'       => __('The template key to assign to the course.', 'tutorpress'),
                            'validate_callback' => [$this, 'validate_template_key'],
                        ],
                    ],
                ],
            ]
        );

        // Get current certificate template selection for a course
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/selection/(?P<course_id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_selection'],
                    'permission_callback' => function($request) {
                        $course_id = (int) $request->get_param('course_id');
                        return $this->check_course_edit_permission_by_id($course_id);
                    },
                    'args'               => [
                        'course_id' => [
                            'type'              => 'integer',
                            'sanitize_callback' => 'absint',
                            'description'       => __('The ID of the course to get certificate selection for.', 'tutorpress'),
                            'validate_callback' => [$this, 'validate_course_id'],
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Get available certificate templates.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response data or error.
     */
    public function get_templates($request) {
        // Ensure Certificate addon is available
        $addon_check = $this->ensure_certificate_addon();
        if (is_wp_error($addon_check)) {
            return $addon_check;
        }

        $include_none = $request->get_param('include_none');

        try {
            // Get templates from Tutor Certificate class
            if (class_exists('TUTOR_CERT\Certificate')) {
                $cert_instance = new TUTOR_CERT\Certificate(true); // true = reuse without hooks
                // Pass true to include 'none' and 'off' templates
                $templates = $cert_instance->get_templates(true);
                
                if (empty($templates)) {
                    return new WP_Error(
                        'no_templates',
                        __('No certificate templates found.', 'tutorpress'),
                        ['status' => 404]
                    );
                }

                // Convert associative array to indexed array with proper field mapping
                // Filter out 'off' template to avoid duplicate "None" options
                $formatted_templates = [];
                foreach ($templates as $key => $template) {
                    // Skip 'off' template - we only want 'none' template for TutorPress
                    if ($key === 'off') {
                        continue;
                    }
                    
                    // Ensure all template fields are properly mapped
                    $formatted_template = [
                        'key' => $key,
                        'slug' => $key, // Same as key for compatibility
                        'name' => $template['name'] ?? $key,
                        'orientation' => $template['orientation'] ?? 'landscape',
                        'is_default' => $template['is_default'] ?? false,
                        'path' => $template['path'] ?? '',
                        'url' => $template['url'] ?? '',
                        'preview_src' => $template['preview_src'] ?? '',
                        'background_src' => $template['background_src'] ?? '',
                    ];
                    
                    $formatted_templates[] = $formatted_template;
                }

                return rest_ensure_response($this->format_response(
                    $formatted_templates,
                    __('Certificate templates retrieved successfully.', 'tutorpress')
                ));

            } else {
                return new WP_Error(
                    'certificate_class_missing',
                    __('Certificate class not available.', 'tutorpress'),
                    ['status' => 500]
                );
            }

        } catch (Exception $e) {
            return new WP_Error(
                'template_fetch_error',
                sprintf(__('Error fetching templates: %s', 'tutorpress'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Save certificate template selection for a course.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response data or error.
     */
    public function save_selection($request) {
        $course_id = $request->get_param('course_id');
        $template_key = $request->get_param('template_key');

        // Ensure Certificate addon is available
        $addon_check = $this->ensure_certificate_addon();
        if (is_wp_error($addon_check)) {
            return $addon_check;
        }

        try {
            // Use the same meta key as Tutor LMS Certificate addon
            $meta_key = 'tutor_course_certificate_template';
            
            // WordPress update_post_meta returns meta_id on success, true if updated, false if failed
            $result = update_post_meta($course_id, $meta_key, $template_key);
            
            // Get the saved value to confirm the operation was successful
            $saved_template = get_post_meta($course_id, $meta_key, true);
            
            // Check if the save was actually successful by comparing values
            if ($saved_template !== $template_key) {
                return new WP_Error(
                    'save_failed',
                    sprintf(__('Failed to save certificate template selection. Expected: %s, Got: %s', 'tutorpress'), $template_key, $saved_template),
                    ['status' => 500]
                );
            }

            return rest_ensure_response($this->format_response(
                [
                    'course_id' => $course_id,
                    'template_key' => $saved_template,
                    'meta_key' => $meta_key,
                ],
                __('Certificate template selection saved successfully.', 'tutorpress')
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'save_error',
                sprintf(__('Error saving template selection: %s', 'tutorpress'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Get current certificate template selection for a course.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response data or error.
     */
    public function get_selection($request) {
        $course_id = (int) $request->get_param('course_id');

        // Ensure Certificate addon is available
        $addon_check = $this->ensure_certificate_addon();
        if (is_wp_error($addon_check)) {
            return $addon_check;
        }

        try {
            // Use the same meta key as Tutor LMS Certificate addon
            $meta_key = 'tutor_course_certificate_template';
            $template_key = get_post_meta($course_id, $meta_key, true);
            
            // Default to 'default' template if none selected
            if (empty($template_key)) {
                $template_key = 'default';
            }

            return rest_ensure_response($this->format_response(
                [
                    'course_id' => $course_id,
                    'template_key' => $template_key,
                    'meta_key' => $meta_key,
                ],
                __('Certificate template selection retrieved successfully.', 'tutorpress')
            ));

        } catch (Exception $e) {
            return new WP_Error(
                'get_selection_error',
                sprintf(__('Error retrieving template selection: %s', 'tutorpress'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }

    /**
     * Check if user has permission to access certificate endpoints.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_certificate_permission($request) {
        // Must be logged in
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_forbidden',
                __('You must be logged in to access certificate templates.', 'tutorpress'),
                ['status' => 401]
            );
        }

        // Must have admin or instructor capabilities
        if (!current_user_can('edit_posts')) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to access certificate templates.', 'tutorpress'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check if user has permission to edit course-specific certificate settings.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_course_edit_permission($request) {
        $course_id = (int) $request->get_param('course_id');
        return $this->check_course_edit_permission_by_id($course_id);
    }

    /**
     * Check if user has permission to edit a specific course.
     *
     * @since 0.1.0
     * @param int $course_id The course ID.
     * @return bool|WP_Error Whether user has permission.
     */
    private function check_course_edit_permission_by_id($course_id) {
        if (!$course_id) {
            return new WP_Error(
                'invalid_course_id',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 400]
            );
        }

        // Check if user can edit this specific course (TutorPress pattern)
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit this course\'s certificate settings.', 'tutorpress'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Validate course ID exists and is a course post type.
     *
     * @since 0.1.0
     * @param int $course_id The course ID to validate.
     * @return bool Whether the course ID is valid.
     */
    public function validate_course_id($course_id) {
        if (!$course_id || !is_numeric($course_id)) {
            return false;
        }

        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return false;
        }

        return true;
    }

    /**
     * Validate template key exists in available templates.
     *
     * @since 0.1.0
     * @param string $template_key The template key to validate.
     * @return bool Whether the template key is valid.
     */
    public function validate_template_key($template_key) {
        if (empty($template_key)) {
            return false;
        }

        try {
            // Get available templates to validate against
            if (class_exists('TUTOR_CERT\Certificate')) {
                $cert_instance = new TUTOR_CERT\Certificate(true);
                $templates = $cert_instance->get_templates(true); // Include none/off options
                
                return array_key_exists($template_key, $templates);
            }
        } catch (Exception $e) {
            // If we can't validate, allow it and let the save operation handle any issues
            return true;
        }

        return false;
    }

    /**
     * Ensure Certificate addon is active and available.
     *
     * @since 0.1.0
     * @return bool|WP_Error True if active, WP_Error if not.
     */
    private function ensure_certificate_addon() {
        if (!tutorpress_feature_flags()->can_user_access_feature('certificates')) {
            return new WP_Error(
                'certificate_addon_disabled',
                __('Certificate addon is not enabled. Contact the site admin.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Ensure Tutor LMS is active
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        return true;
    }
} 