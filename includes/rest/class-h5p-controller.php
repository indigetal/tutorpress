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
                            'search' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Search term to filter H5P content.', 'tutorpress'),
                            ],
                            'search_filter' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Legacy: Search term to filter H5P content.', 'tutorpress'),
                            ],
                            'contentType' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Filter by H5P content type.', 'tutorpress'),
                            ],
                            'content_type' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Legacy: Filter by H5P content type.', 'tutorpress'),
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
                            'order' => [
                                'type'              => 'string',
                                'enum'              => ['asc', 'desc'],
                                'default'           => 'asc',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Sort order (asc or desc).', 'tutorpress'),
                            ],
                            'orderby' => [
                                'type'              => 'string',
                                'enum'              => ['title', 'date', 'author'],
                                'default'           => 'title',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Sort by field.', 'tutorpress'),
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
    /**
     * Get H5P contents with search and filtering.
     * Replicates: tutor_h5p_list_quiz_contents AJAX endpoint
     *
     * @since 1.4.0
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error
     */
    public function get_contents($request) {
        try {
            // Check if H5P plugin is active
            if (!class_exists('H5PContentQuery')) {
                return new WP_Error(
                    'h5p_plugin_missing',
                    __('H5P plugin is not installed or activated.', 'tutorpress'),
                    ['status' => 503]
                );
            }

            // Get request parameters (supporting both new and legacy parameter names)
            $search_filter = $request->get_param('search') ?? $request->get_param('search_filter') ?? '';
            $content_type = $request->get_param('contentType') ?? $request->get_param('content_type') ?? '';
            $per_page = $request->get_param('per_page') ?? 20;
            $page = $request->get_param('page') ?? 1;
            $order = $request->get_param('order') ?? 'asc';
            $orderby = $request->get_param('orderby') ?? 'title';
            $debug = $request->get_param('debug') ?? false;

            // Use H5P plugin's content query (same as Tutor LMS implementation)
            $order_field = null;
            $reverse = null;
            $filter = null;

            if (isset($orderby)) {
                $order_field = 'updated_at';
                $reverse = 'ASC' === strtoupper($order) ? true : false;
            }

            // Build search filter (replicate Tutor LMS Utils::get_h5p_contents logic)
            if (!empty($search_filter)) {
                global $wpdb;
                $search_filter_escaped = '%' . $wpdb->esc_like($search_filter) . '%';

                $filter = [
                    [
                        'title',
                        $search_filter_escaped,
                        'LIKE',
                    ],
                    [
                        'content_type',
                        $search_filter_escaped,
                        'LIKE',
                    ],
                ];
            }

            // Add user filter (only show current user's content like Tutor LMS)
            if (!isset($filter)) {
                $filter = [
                    [
                        'user_id',
                        get_current_user_id(),
                        '=',
                    ],
                ];
            } else {
                $filter[] = [
                    'user_id',
                    get_current_user_id(),
                    '=',
                ];
            }

            // Query H5P content using the same fields as Tutor LMS
            $fields = ['title', 'content_type', 'user_name', 'tags', 'updated_at', 'id', 'user_id'];
            $h5p_contents_query = new \H5PContentQuery($fields, null, null, $order_field, $reverse, $filter);
            $h5p_contents = $h5p_contents_query->get_rows();

            // Apply Tutor LMS filtering logic (exclude certain content types from quizzes)
            $filtered_h5p_contents = [];
            $excluded_content_types = ['Game Map', 'Question Set', 'Interactive Book', 'Interactive Video', 'Course Presentation', 'Personality Quiz'];
            
            foreach ($h5p_contents as $content) {
                if (!in_array($content->content_type, $excluded_content_types, true)) {
                    // Ensure all required fields are present and properly formatted
                    $filtered_content = (object) [
                        'id' => (int) $content->id,
                        'title' => $content->title ?? '',
                        'content_type' => $content->content_type ?? '',
                        'user_id' => (int) $content->user_id,
                        'user_name' => $content->user_name ?? '',
                        'description' => $content->tags ?? '', // H5P uses tags field for description
                        'library' => $content->content_type ?? '',
                        'updated_at' => $content->updated_at ?? '',
                        'created_at' => $content->updated_at ?? '', // H5P query doesn't provide created_at
                        'tags' => $content->tags ?? '',
                    ];
                    
                    $filtered_h5p_contents[] = $filtered_content;
                }
            }

            // Apply pagination to filtered results (since H5P query doesn't support pagination)
            $total_items = count($filtered_h5p_contents);
            $total_pages = ceil($total_items / $per_page);
            $offset = ($page - 1) * $per_page;
            $paginated_contents = array_slice($filtered_h5p_contents, $offset, $per_page);

            // Return response in format expected by our interfaces
            $response_data = [
                'items'       => $paginated_contents,
                'total'       => $total_items,
                'page'        => $page,
                'per_page'    => $per_page,
                'total_pages' => $total_pages,
            ];

            // Add debug information if requested
            if ($debug) {
                $response_data['debug'] = [
                    'search_filter' => $search_filter,
                    'content_type' => $content_type,
                    'filter' => $filter,
                    'raw_contents' => count($h5p_contents),
                    'filtered_contents' => count($filtered_h5p_contents),
                    'paginated_contents' => count($paginated_contents),
                    'total_items' => $total_items,
                    'total_pages' => $total_pages,
                    'excluded_content_types' => $excluded_content_types,
                ];
            }

            return new WP_REST_Response($response_data);

        } catch (Exception $e) {
            error_log('TutorPress H5P Controller: get_contents error - ' . $e->getMessage());
            return new WP_Error(
                'h5p_content_fetch_error',
                __('Failed to fetch H5P contents.', 'tutorpress'),
                ['status' => 500]
            );
        }
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