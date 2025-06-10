<?php
/**
 * H5P REST Controller Class
 *
 * Handles REST API functionality for H5P content integration.
 * Replaces Tutor LMS H5P AJAX endpoints with modern REST API while
 * maintaining full compatibility with existing data structures.
 *
 * @package TutorPress
 * @since 1.4.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_H5P_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 1.4.0
     */
    public function __construct() {
        $this->rest_base = 'h5p';
    }

    /**
     * Register REST API routes.
     *
     * @since 1.4.0
     * @return void
     */
    public function register_routes() {
        try {
            // Get H5P content list with search filtering
            // Replaces: wp_ajax_tutor_h5p_list_quiz_contents
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/contents',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_contents'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'search_filter' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Search term to filter H5P content.', 'tutorpress'),
                            ],
                            'content_type' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Filter by H5P content type.', 'tutorpress'),
                            ],
                            'per_page' => [
                                'type'              => 'integer',
                                'default'           => 20,
                                'minimum'           => 1,
                                'maximum'           => 100,
                                'sanitize_callback' => 'absint',
                                'description'       => __('Number of items per page.', 'tutorpress'),
                            ],
                            'page' => [
                                'type'              => 'integer',
                                'default'           => 1,
                                'minimum'           => 1,
                                'sanitize_callback' => 'absint',
                                'description'       => __('Page number for pagination.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Save H5P xAPI statement
            // Replaces: wp_ajax_save_h5p_question_xAPI_statement
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/statements',
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'save_statement'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'quiz_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The quiz ID.', 'tutorpress'),
                            ],
                            'question_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The question ID.', 'tutorpress'),
                            ],
                            'content_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The H5P content ID.', 'tutorpress'),
                            ],
                            'statement' => [
                                'required'          => true,
                                'type'             => 'string',
                                'description'       => __('The xAPI statement JSON.', 'tutorpress'),
                            ],
                            'attempt_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The quiz attempt ID.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Validate H5P question answers
            // Replaces: wp_ajax_check_h5p_question_answered
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/validate',
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'validate_answers'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'question_ids' => [
                                'required'    => true,
                                'type'       => 'string',
                                'description' => __('JSON string of question and content IDs.', 'tutorpress'),
                            ],
                            'quiz_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The quiz ID.', 'tutorpress'),
                            ],
                            'attempt_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The quiz attempt ID.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // View H5P quiz results
            // Replaces: wp_ajax_view_h5p_quiz_result
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/results',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_results'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'quiz_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The quiz ID.', 'tutorpress'),
                            ],
                            'user_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The user ID.', 'tutorpress'),
                            ],
                            'question_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The question ID.', 'tutorpress'),
                            ],
                            'content_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The H5P content ID.', 'tutorpress'),
                            ],
                            'attempt_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The quiz attempt ID.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

        } catch (Exception $e) {
            error_log('TutorPress H5P Controller: Failed to register routes - ' . $e->getMessage());
        }
    }

    /**
     * Placeholder methods to be implemented
     */
    public function get_contents($request) {
        return new WP_Error('not_implemented', 'Method not yet implemented', ['status' => 501]);
    }

    public function save_statement($request) {
        return new WP_Error('not_implemented', 'Method not yet implemented', ['status' => 501]);
    }

    public function validate_answers($request) {
        return new WP_Error('not_implemented', 'Method not yet implemented', ['status' => 501]);
    }

    public function get_results($request) {
        return new WP_Error('not_implemented', 'Method not yet implemented', ['status' => 501]);
    }
} 