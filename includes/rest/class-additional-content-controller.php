<?php
/**
 * Additional Content REST Controller
 *
 * @description Handles REST API endpoints for course additional content fields and content drip settings.
 *              Provides GET/POST endpoints for managing What Will I Learn, Target Audience, Requirements,
 *              and Content Drip configuration.
 *
 * @package TutorPress
 * @subpackage REST
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Additional Content REST Controller class
 */
class TutorPress_Additional_Content_Controller extends TutorPress_REST_Controller {

    /**
     * Register REST API routes
     *
     * @return void
     */
    public function register_routes() {
        // Get additional content for a course
        register_rest_route(
            $this->namespace,
            '/additional-content/(?P<course_id>\d+)',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_additional_content'),
                'permission_callback' => array($this, 'check_read_permission'),
                'args'                => array(
                    'course_id' => array(
                        'required'    => true,
                        'type'        => 'integer',
                        'description' => __('Course ID to get additional content for', 'tutorpress'),
                    ),
                ),
            )
        );

        // Save additional content for a course
        register_rest_route(
            $this->namespace,
            '/additional-content/save',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'save_additional_content'),
                'permission_callback' => array($this, 'check_write_permission'),
                'args'                => array(
                    'course_id' => array(
                        'required'    => true,
                        'type'        => 'integer',
                        'description' => __('Course ID to save additional content for', 'tutorpress'),
                    ),
                    'what_will_learn' => array(
                        'type'        => 'string',
                        'description' => __('What students will learn from this course', 'tutorpress'),
                        'default'     => '',
                    ),
                    'target_audience' => array(
                        'type'        => 'string',
                        'description' => __('Target audience for this course', 'tutorpress'),
                        'default'     => '',
                    ),
                    'requirements' => array(
                        'type'        => 'string',
                        'description' => __('Course requirements and instructions', 'tutorpress'),
                        'default'     => '',
                    ),
                    'content_drip_enabled' => array(
                        'type'        => 'boolean',
                        'description' => __('Whether content drip is enabled', 'tutorpress'),
                        'default'     => false,
                    ),
                    'content_drip_type' => array(
                        'type'        => 'string',
                        'description' => __('Content drip type', 'tutorpress'),
                        'enum'        => array('unlock_by_date', 'specific_days', 'unlock_sequentially', 'after_finishing_prerequisites'),
                        'default'     => 'unlock_by_date',
                    ),
                ),
            )
        );
    }

    /**
     * Get additional content for a course
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function get_additional_content($request) {
        $course_id = (int) $request->get_param('course_id');

        // Validate course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'course_not_found',
                __('Course not found', 'tutorpress'),
                array('status' => 404)
            );
        }

        // Get additional content data
        $additional_data = get_post_meta($course_id, '_tutor_course_additional_data', true);
        if (!is_array($additional_data)) {
            $additional_data = array();
        }

        // Get content drip settings
        $course_settings = get_post_meta($course_id, '_tutor_course_settings', true);
        if (!is_array($course_settings)) {
            $course_settings = array();
        }

        $content_drip_enabled = isset($course_settings['enable_content_drip']) ? 
            (bool) $course_settings['enable_content_drip'] : false;
        $content_drip_type = isset($course_settings['content_drip_type']) ? 
            $course_settings['content_drip_type'] : 'unlock_by_date';

        // Prepare response data
        $response_data = array(
            'what_will_learn' => isset($additional_data['what_will_learn']) ? $additional_data['what_will_learn'] : '',
            'target_audience' => isset($additional_data['target_audience']) ? $additional_data['target_audience'] : '',
            'requirements' => isset($additional_data['requirements']) ? $additional_data['requirements'] : '',
            'content_drip' => array(
                'enabled' => $content_drip_enabled,
                'type' => $content_drip_type,
            ),
            'course_id' => $course_id,
        );

        return rest_ensure_response(array(
            'success' => true,
            'data' => $response_data,
        ));
    }

    /**
     * Save additional content for a course
     *
     * @param WP_REST_Request $request The REST request object
     * @return WP_REST_Response|WP_Error Response object or error
     */
    public function save_additional_content($request) {
        $course_id = (int) $request->get_param('course_id');

        // Validate course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return new WP_Error(
                'course_not_found',
                __('Course not found', 'tutorpress'),
                array('status' => 404)
            );
        }

        // Get parameters
        $what_will_learn = sanitize_textarea_field($request->get_param('what_will_learn'));
        $target_audience = sanitize_textarea_field($request->get_param('target_audience'));
        $requirements = sanitize_textarea_field($request->get_param('requirements'));
        $content_drip_enabled = (bool) $request->get_param('content_drip_enabled');
        $content_drip_type = sanitize_text_field($request->get_param('content_drip_type'));

        // Validate content drip type
        $valid_drip_types = array('unlock_by_date', 'specific_days', 'unlock_sequentially', 'after_finishing_prerequisites');
        if (!in_array($content_drip_type, $valid_drip_types)) {
            $content_drip_type = 'unlock_by_date';
        }

        // Save additional content data
        $additional_data = array(
            'what_will_learn' => $what_will_learn,
            'target_audience' => $target_audience,
            'requirements' => $requirements,
        );

        $additional_saved = update_post_meta($course_id, '_tutor_course_additional_data', $additional_data);

        // Save content drip settings (only if content drip addon is enabled)
        $content_drip_saved = true;
        if (TutorPress_Addon_Checker::is_content_drip_enabled()) {
            // Get existing course settings
            $course_settings = get_post_meta($course_id, '_tutor_course_settings', true);
            if (!is_array($course_settings)) {
                $course_settings = array();
            }

            // Update content drip settings
            $course_settings['enable_content_drip'] = $content_drip_enabled;
            $course_settings['content_drip_type'] = $content_drip_type;

            $content_drip_saved = update_post_meta($course_id, '_tutor_course_settings', $course_settings);
        }

        // Check if save was successful
        if ($additional_saved === false || $content_drip_saved === false) {
            return new WP_Error(
                'save_failed',
                __('Failed to save additional content', 'tutorpress'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'success' => true,
            'message' => __('Additional content saved successfully', 'tutorpress'),
            'data' => array(
                'course_id' => $course_id,
                'additional_data_saved' => $additional_saved,
                'content_drip_saved' => $content_drip_saved,
                'content_drip_addon_enabled' => TutorPress_Addon_Checker::is_content_drip_enabled(),
            ),
        ));
    }

    /**
     * Check read permissions for additional content endpoints
     *
     * @param WP_REST_Request $request The REST request object
     * @return bool|WP_Error True if user has permission, error otherwise
     */
    public function check_read_permission($request) {
        $course_id = (int) $request->get_param('course_id');

        // Check if user can edit the specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this course\'s additional content.', 'tutorpress'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Check write permissions for additional content endpoints
     *
     * @param WP_REST_Request $request The REST request object
     * @return bool|WP_Error True if user has permission, error otherwise
     */
    public function check_write_permission($request) {
        $course_id = (int) $request->get_param('course_id');

        // Check if user can edit the specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to edit this course\'s additional content.', 'tutorpress'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Get additional content field configurations
     *
     * @return array Array of field configurations
     */
    public static function get_field_configs() {
        return array(
            'additional_fields' => Additional_Content_Metabox::get_supported_fields(),
            'content_drip_fields' => Additional_Content_Metabox::get_content_drip_fields(),
        );
    }
} 