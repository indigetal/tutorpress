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
                            'course_id' => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('Course ID for collaborative instructor access.', 'tutorpress'),
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
                            'course_id' => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('Course ID to include instructor-shared content.', 'tutorpress'),
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

            // Get H5P content preview HTML for embedding
            // NEW: For Interactive Quiz Modal preview functionality
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/preview/(?P<content_id>\d+)',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_content_preview'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'content_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The H5P content ID to preview.', 'tutorpress'),
                            ],
                            'course_id' => [
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('Course ID for collaborative access context.', 'tutorpress'),
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
            $course_id = $request->get_param('course_id') ?? 0;
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

            // Add user filter - start with current user only (preserve original behavior)
            $user_ids = [get_current_user_id()];
            
            // If course context is provided and collaborative access is enabled, expand visibility
            if ($course_id > 0 && tutorpress_feature_flags()->can_user_access_feature('h5p_integration')) {
                $course_instructors = $this->get_course_instructor_ids($course_id);
                
                if (!empty($course_instructors) && count($course_instructors) > 1) {
                    // Only apply collaborative filtering if there are multiple instructors
                    $user_ids = $course_instructors;
                }
            }
            
            // H5PContentQuery doesn't support multiple user filters (treats them as AND, not OR)
            // Solution: Query each user separately and merge results
            $all_h5p_contents = [];
            $fields = ['title', 'content_type', 'user_name', 'tags', 'updated_at', 'id', 'user_id'];
            
            foreach ($user_ids as $user_id) {
                // Build filter for this specific user
                $user_filter = [];
                if (!empty($search_filter)) {
                    // Include search filters if they exist
                    global $wpdb;
                    $search_filter_escaped = '%' . $wpdb->esc_like($search_filter) . '%';
                    $user_filter = [
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
                        [
                            'user_id',
                            $user_id,
                            '=',
                        ],
                    ];
                } else {
                    // Just user filter
                    $user_filter = [
                        [
                            'user_id',
                            $user_id,
                            '=',
                        ],
                    ];
                }
                
                // Query this user's content
                $user_query = new \H5PContentQuery($fields, null, null, $order_field, $reverse, $user_filter);
                $user_contents = $user_query->get_rows();
                
                // Add to combined results
                $all_h5p_contents = array_merge($all_h5p_contents, $user_contents);
            }
            
            // Remove duplicates based on content ID
            $seen_ids = [];
            $h5p_contents = [];
            foreach ($all_h5p_contents as $content) {
                if (!in_array($content->id, $seen_ids)) {
                    $seen_ids[] = $content->id;
                    $h5p_contents[] = $content;
                }
            }


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
                    'course_id' => $course_id,
                    'user_ids_included' => $user_ids,
                    'filter' => $filter,
                    'raw_contents' => count($h5p_contents),
                    'filtered_contents' => count($filtered_h5p_contents),
                    'paginated_contents' => count($paginated_contents),
                    'total_items' => $total_items,
                    'total_pages' => $total_pages,
                    'excluded_content_types' => $excluded_content_types,
                    'collaborative_access_enabled' => $course_id > 0 && tutorpress_feature_flags()->can_user_access_feature('h5p_integration'),
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

    public function get_content_preview($request) {
        // Check if H5P plugin is active
        if (!class_exists('H5P_Plugin')) {
            return new WP_Error('h5p_not_active', __('H5P plugin is not active.', 'tutorpress'), ['status' => 424]);
        }

        $content_id = absint($request['content_id']);
        $course_id = $request->get_param('course_id') ?? 0; // Get course context from request
        
        if (!$content_id) {
            return new WP_Error('invalid_content_id', __('Invalid H5P content ID.', 'tutorpress'), ['status' => 400]);
        }

        try {
            // First, check if the content exists at all
            $content_query = new \H5PContentQuery(['id', 'title', 'content_type', 'tags', 'user_id'], null, null, null, null, [
                ['id', $content_id, '=']
            ]);
            $content_check = $content_query->get_rows();
            
            if (empty($content_check)) {
                return new WP_Error('content_not_found', __('H5P content not found.', 'tutorpress'), ['status' => 404]);
            }
            
            $content_info = $content_check[0];
            $current_user_id = get_current_user_id();
            
            // Check access: user's own content OR collaborative access
            $has_access = false;
            
            // User always has access to their own content
            if ($content_info->user_id == $current_user_id) {
                $has_access = true;
            }
            // Check collaborative access - use course context if available
            else if (tutorpress_feature_flags()->can_user_access_feature('h5p_integration')) {
                // If we have course context, use the same logic as content listing
                if ($course_id > 0) {
                    $course_instructors = $this->get_course_instructor_ids($course_id);
                    
                    // Check if both users are instructors of this course
                    if (in_array($current_user_id, $course_instructors) && in_array($content_info->user_id, $course_instructors)) {
                        $has_access = true;
                    }
                } else {
                    // Fallback: check all shared courses (keep original logic as backup)
                    $current_user_courses = $this->get_user_instructor_courses($current_user_id);
                    $content_creator_courses = $this->get_user_instructor_courses($content_info->user_id);
                    
                    $shared_courses = array_intersect($current_user_courses, $content_creator_courses);
                    if (!empty($shared_courses)) {
                        $has_access = true;
                    }
                }
            }
            
            if (!$has_access) {
                return new WP_Error('content_not_found', __('H5P content not found or access denied.', 'tutorpress'), ['status' => 404]);
            }
            
            // User has access, content_info is already set from the content_check above

            // Get metadata including description from the query result
            $metadata = [
                'id' => (int) $content_info->id,
                'title' => $content_info->title ?? 'H5P Content',
                'library' => $content_info->content_type ?? 'Unknown',
                'content_type' => $content_info->content_type ?? 'Interactive Content',
                'description' => $content_info->tags ?? '', // H5P uses tags field for description
            ];

            // Use the H5P content description if available, otherwise use a fallback
            $description = !empty($metadata['description']) 
                ? esc_html($metadata['description'])
                : __('This interactive H5P content will be embedded in the quiz for students to complete.', 'tutorpress');

            // Create a functional preview HTML that works in the modal context
            $admin_url = admin_url('admin.php?page=h5p&task=show&id=' . $content_id);
            $edit_url = admin_url('admin.php?page=h5p_new&id=' . $content_id);
            
            $preview_html = sprintf('
                <div class="h5p-content-preview-wrapper">
                    <div class="h5p-content-preview-info">
                        <div class="h5p-content-icon">
                            <span class="dashicons dashicons-format-video"></span>
                        </div>
                        <div class="h5p-content-details">
                            <p class="h5p-content-type">%s</p>
                            <p class="h5p-content-description">%s</p>
                        </div>
                    </div>
                    <div class="h5p-content-actions">
                        <a href="%s" target="_blank" class="button button-secondary h5p-preview-button">
                            <span class="dashicons dashicons-external"></span>
                            %s
                        </a>
                        <a href="%s" target="_blank" class="button button-primary h5p-edit-button">
                            <span class="dashicons dashicons-edit"></span>
                            %s
                        </a>
                    </div>
                    <div class="h5p-content-note">
                        <p><strong>%s</strong> %s</p>
                    </div>
                </div>
                <style>
                .h5p-content-preview-wrapper {
                    border: 1px solid #ddd;
                    border-radius: 8px;
                    padding: 20px;
                    background: #f9f9f9;
                    margin: 10px 0;
                }
                .h5p-content-preview-info {
                    display: flex;
                    align-items: center;
                    margin-bottom: 15px;
                }
                .h5p-content-icon {
                    margin-right: 15px;
                    font-size: 24px;
                    color: #0073aa;
                }
                .h5p-content-type {
                    margin: 0 0 8px 0;
                    color: #666;
                    font-style: italic;
                }
                .h5p-content-description {
                    margin: 0;
                    color: #555;
                }
                .h5p-content-actions {
                    display: flex;
                    gap: 10px;
                    margin-bottom: 15px;
                }
                .h5p-content-actions .button {
                    display: inline-flex;
                    align-items: center;
                    gap: 5px;
                    text-decoration: none;
                }
                .h5p-content-note {
                    background: #e7f3ff;
                    border: 1px solid #b3d9ff;
                    border-radius: 4px;
                    padding: 10px;
                }
                .h5p-content-note p {
                    margin: 0;
                    font-size: 14px;
                    color: #004085;
                }
                </style>
            ',
                esc_html($metadata['content_type']),
                $description,
                esc_url($admin_url),
                __('Preview Full Content', 'tutorpress'),
                esc_url($edit_url),
                __('Edit Content', 'tutorpress'),
                __('Note:', 'tutorpress'),
                __('Students will see the full interactive content embedded directly in the quiz when taking the assessment.', 'tutorpress')
            );

            return rest_ensure_response([
                'success' => true,
                'data' => [
                    'html' => $preview_html,
                    'metadata' => $metadata,
                    'content_id' => $content_id,
                    'preview_type' => 'functional_preview', // Indicate this is our custom preview
                ],
            ]);

        } catch (Exception $e) {
            error_log('TutorPress H5P Preview Error: ' . $e->getMessage());
            return new WP_Error('preview_error', __('Failed to generate H5P preview: ' . $e->getMessage(), 'tutorpress'), ['status' => 500]);
        }
    }

    /**
     * Get all instructor user IDs for a course (author + co-instructors)
     * Enables H5P content sharing between course collaborators
     *
     * @param int $course_id Course ID
     * @return array Array of user IDs who are instructors for this course
     */
    private function get_course_instructor_ids(int $course_id): array {
        $instructor_ids = [];
        
        // Get course author (main instructor)
        $course_author = get_post_field('post_author', $course_id);
        if ($course_author) {
            $instructor_ids[] = (int) $course_author;
        }
        
        // Get co-instructors from Tutor LMS meta
        if (function_exists('tutor_utils')) {
            $co_instructors = get_post_meta($course_id, '_tutor_course_instructors', true);
            if (is_array($co_instructors) && !empty($co_instructors)) {
                foreach ($co_instructors as $instructor_id) {
                    $instructor_ids[] = (int) $instructor_id;
                }
            }
        }
        
        // Apply filters for extensibility
        $instructor_ids = apply_filters('tutorpress_h5p_course_instructor_ids', $instructor_ids, $course_id);
        
        // Remove duplicates and current user (already included in main filter)
        $instructor_ids = array_unique($instructor_ids);
        $instructor_ids = array_filter($instructor_ids, function($id) {
            return $id > 0; // Ensure valid user IDs
        });
        
        return array_values($instructor_ids);
    }

    /**
     * Get all course IDs where the user is an instructor (author or co-instructor)
     * Used for expanding H5P content access through course relationships
     *
     * @param int $user_id User ID
     * @return array Array of course IDs where user is an instructor
     */
    private function get_user_instructor_courses(int $user_id): array {
        $course_ids = [];
        
        // Get courses where user is the author
        $authored_courses = get_posts([
            'post_type' => 'courses',
            'author' => $user_id,
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
        ]);
        
        if (!empty($authored_courses)) {
            $course_ids = array_merge($course_ids, $authored_courses);
        }
        
        // Get courses where user is a co-instructor
        if (function_exists('tutor_utils')) {
            global $wpdb;
            
            $co_instructor_courses = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} 
                 WHERE meta_key = '_tutor_course_instructors' 
                 AND meta_value LIKE %s",
                '%' . $wpdb->esc_like('"' . $user_id . '"') . '%'
            ));
            
            if (!empty($co_instructor_courses)) {
                $course_ids = array_merge($course_ids, array_map('intval', $co_instructor_courses));
            }
        }
        
        // Remove duplicates and ensure valid IDs
        $course_ids = array_unique($course_ids);
        $course_ids = array_filter($course_ids, function($id) {
            return $id > 0 && get_post_type($id) === 'courses';
        });
        
        return array_values($course_ids);
    }


} 