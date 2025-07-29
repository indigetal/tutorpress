<?php
/**
 * Bundle Settings REST Controller Class
 *
 * Handles REST API functionality for bundle settings.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Course_Bundles_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'bundles';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        // Basic bundle operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base,
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_bundles'],
                    'permission_callback' => [$this, 'check_permission'],
                    'args'               => [
                        'per_page' => [
                            'type'              => 'integer',
                            'default'           => 10,
                            'minimum'           => 1,
                            'maximum'           => 100,
                            'sanitize_callback' => 'absint',
                        ],
                        'page' => [
                            'type'              => 'integer',
                            'default'           => 1,
                            'minimum'           => 1,
                            'sanitize_callback' => 'absint',
                        ],
                        'search' => [
                            'type'              => 'string',
                            'sanitize_callback' => 'sanitize_text_field',
                        ],
                    ],
                ],
            ]
        );

        // Single bundle operations
        register_rest_route(
            $this->namespace,
            '/' . $this->rest_base . '/(?P<id>[\d]+)',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'get_bundle'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
                [
                    'methods'             => 'PATCH',
                    'callback'            => [$this, 'update_bundle'],
                    'permission_callback' => [$this, 'check_permission'],
                ],
            ]
        );
    }

    /**
     * Check if user has permission to access bundle endpoints.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error Whether user has permission.
     */
    public function check_permission($request) {
        // Ensure Tutor LMS is active
        $tutor_check = $this->ensure_tutor_lms();
        if (is_wp_error($tutor_check)) {
            return $tutor_check;
        }

        // Use base permission check
        return parent::check_permission($request);
    }

    /**
     * Get bundles list.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_bundles($request) {
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');
        $search = $request->get_param('search');

        $args = [
            'post_type'      => 'course-bundle',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => 'publish',
        ];

        if ($search) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $bundles = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $bundle_id = get_the_ID();
                $bundles[] = [
                    'id'    => $bundle_id,
                    'title' => get_the_title(),
                    'slug'  => get_post_field('post_name'),
                ];
            }
        }

        wp_reset_postdata();

        return rest_ensure_response([
            'bundles'     => $bundles,
            'total'       => $query->found_posts,
            'total_pages' => $query->max_num_pages,
        ]);
    }

    /**
     * Get single bundle.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function get_bundle($request) {
        $bundle_id = (int) $request->get_param('id');
        $bundle = get_post($bundle_id);

        if (!$bundle || $bundle->post_type !== 'course-bundle') {
            return new WP_Error(
                'bundle_not_found',
                __('Bundle not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        $response = [
            'id'          => $bundle_id,
            'title'       => $bundle->post_title,
            'content'     => $bundle->post_content,
            'slug'        => $bundle->post_name,
            'status'      => $bundle->post_status,
            'created'     => mysql_to_rfc3339($bundle->post_date),
            'modified'    => mysql_to_rfc3339($bundle->post_modified),
        ];

        return rest_ensure_response($response);
    }

    /**
     * Update bundle.
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object.
     */
    public function update_bundle($request) {
        $bundle_id = (int) $request->get_param('id');
        $bundle = get_post($bundle_id);

        if (!$bundle || $bundle->post_type !== 'course-bundle') {
            return new WP_Error(
                'bundle_not_found',
                __('Bundle not found.', 'tutorpress'),
                ['status' => 404]
            );
        }

        $title = $request->get_param('title');
        $content = $request->get_param('content');

        $update_args = [
            'ID' => $bundle_id,
        ];

        if ($title !== null) {
            $update_args['post_title'] = sanitize_text_field($title);
        }

        if ($content !== null) {
            $update_args['post_content'] = wp_kses_post($content);
        }

        $result = wp_update_post($update_args, true);

        if (is_wp_error($result)) {
            return $result;
        }

        return $this->get_bundle($request);
    }


} 