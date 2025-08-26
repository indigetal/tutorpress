<?php
/**
 * Subscriptions REST Controller Class
 *
 * Handles REST API functionality for subscription plans.
 * Replicates Tutor LMS subscription addon functionality with modern REST API.
 *
 * @package TutorPress
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Subscriptions_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->rest_base = 'subscriptions';
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        try {
            // Get subscription plans for a course
            register_rest_route(
                $this->namespace,
                '/courses/(?P<course_id>[\d]+)/subscriptions',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_course_subscriptions'],
                        'permission_callback' => function($request) {
                            $course_id = (int) $request->get_param('course_id');
                            
                            // Check if user can edit the specific course
                            if ($course_id && current_user_can('edit_post', $course_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to get subscription plans for.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Get subscription plans for a bundle
            register_rest_route(
                $this->namespace,
                '/bundles/(?P<bundle_id>[\d]+)/subscriptions',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_bundle_subscriptions'],
                        'permission_callback' => function($request) {
                            $bundle_id = (int) $request->get_param('bundle_id');
                            
                            // Check if user can edit the specific bundle
                            if ($bundle_id && current_user_can('edit_post', $bundle_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'bundle_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the bundle to get subscription plans for.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Create new subscription plan
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'create_subscription_plan'],
                        'permission_callback' => function($request) {
                            // Support both course_id and object_id
                            $object_id = (int) ($request->get_param('object_id') ?? $request->get_param('course_id'));
                            
                            // Check if user can edit the specific object (course or bundle)
                            if ($object_id && current_user_can('edit_post', $object_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'course_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to create the plan for (legacy).', 'tutorpress'),
                            ],
                            'object_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the object (course or bundle) to create the plan for.', 'tutorpress'),
                            ],
                            'plan_name' => [
                                'required'          => true,
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('The name of the subscription plan.', 'tutorpress'),
                            ],
                            'regular_price' => [
                                'required'          => true,
                                'type'             => 'number',
                                'minimum'           => 0,
                                'description'       => __('The regular price of the plan.', 'tutorpress'),
                            ],
                            'recurring_value' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'minimum'           => 1,
                                'description'       => __('The recurring value (e.g., 1 for monthly).', 'tutorpress'),
                            ],
                            'recurring_interval' => [
                                'required'          => true,
                                'type'             => 'string',
                                'enum'              => ['day', 'week', 'month', 'year'],
                                'description'       => __('The recurring interval (day, week, month, year).', 'tutorpress'),
                            ],
                            'payment_type' => [
                                'required'          => true,
                                'type'             => 'string',
                                'enum'              => ['recurring'],
                                'default'           => 'recurring',
                                'description'       => __('The payment type (currently only recurring supported).', 'tutorpress'),
                            ],
                            'plan_type' => [
                                'required'          => true,
                                'type'             => 'string',
                                'enum'              => ['course'],
                                'default'           => 'course',
                                'description'       => __('The plan type (currently only course supported).', 'tutorpress'),
                            ],
                            'recurring_limit' => [
                                'type'             => 'integer',
                                'minimum'           => 0,
                                'default'           => 0,
                                'description'       => __('How many times the plan can recur (0 = until cancelled).', 'tutorpress'),
                            ],
                            'sale_price' => [
                                'type'             => ['number', 'null'],
                                'minimum'           => 0,
                                'description'       => __('The sale price of the plan.', 'tutorpress'),
                            ],
                            'sale_price_from' => [
                                'type'             => ['string', 'null'],
                                'format'            => 'date-time',
                                'description'       => __('When the sale price starts (ISO 8601 format).', 'tutorpress'),
                            ],
                            'sale_price_to' => [
                                'type'             => ['string', 'null'],
                                'format'            => 'date-time',
                                'description'       => __('When the sale price ends (ISO 8601 format).', 'tutorpress'),
                            ],
                            'enrollment_fee' => [
                                'type'             => 'number',
                                'minimum'           => 0,
                                'default'           => 0,
                                'description'       => __('The enrollment fee for the plan.', 'tutorpress'),
                            ],
                            'provide_certificate' => [
                                'type'             => 'boolean',
                                'default'           => true,
                                'description'       => __('Whether the plan provides certificates.', 'tutorpress'),
                            ],
                            'is_featured' => [
                                'type'             => 'boolean',
                                'default'           => false,
                                'description'       => __('Whether the plan is featured.', 'tutorpress'),
                            ],
                            'featured_text' => [
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Featured badge text for the plan.', 'tutorpress'),
                            ],
                            'trial_value' => [
                                'type'             => 'integer',
                                'minimum'           => 0,
                                'default'           => 0,
                                'description'       => __('Trial period value.', 'tutorpress'),
                            ],
                            'trial_interval' => [
                                'type'             => ['string', 'null'],
                                'enum'              => ['day', 'week', 'month', 'year', null],
                                'description'       => __('Trial period interval.', 'tutorpress'),
                            ],
                            'short_description' => [
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Short description of the plan.', 'tutorpress'),
                            ],
                            'description' => [
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_textarea_field',
                                'description'       => __('Full description of the plan.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Update existing subscription plan
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<id>[\d]+)',
                [
                    [
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => [$this, 'update_subscription_plan'],
                        'permission_callback' => function($request) {
                            // Support both course_id and object_id
                            $object_id = (int) ($request->get_param('object_id') ?? $request->get_param('course_id'));
                            
                            // Check if user can edit the specific object (course or bundle)
                            if ($object_id && current_user_can('edit_post', $object_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the subscription plan to update.', 'tutorpress'),
                            ],
                            'course_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course the plan belongs to (legacy).', 'tutorpress'),
                            ],
                            'object_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the object (course or bundle) the plan belongs to.', 'tutorpress'),
                            ],
                            'plan_name' => [
                                'required'          => true,
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('The name of the subscription plan.', 'tutorpress'),
                            ],
                            'regular_price' => [
                                'required'          => true,
                                'type'             => 'number',
                                'minimum'           => 0,
                                'description'       => __('The regular price of the plan.', 'tutorpress'),
                            ],
                            'recurring_value' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'minimum'           => 1,
                                'description'       => __('The recurring value (e.g., 1 for monthly).', 'tutorpress'),
                            ],
                            'recurring_interval' => [
                                'required'          => true,
                                'type'             => 'string',
                                'enum'              => ['day', 'week', 'month', 'year'],
                                'description'       => __('The recurring interval (day, week, month, year).', 'tutorpress'),
                            ],
                            'payment_type' => [
                                'required'          => true,
                                'type'             => 'string',
                                'enum'              => ['recurring'],
                                'default'           => 'recurring',
                                'description'       => __('The payment type (currently only recurring supported).', 'tutorpress'),
                            ],
                            'plan_type' => [
                                'required'          => true,
                                'type'             => 'string',
                                'enum'              => ['course'],
                                'default'           => 'course',
                                'description'       => __('The plan type (currently only course supported).', 'tutorpress'),
                            ],
                            'recurring_limit' => [
                                'type'             => 'integer',
                                'minimum'           => 0,
                                'default'           => 0,
                                'description'       => __('How many times the plan can recur (0 = until cancelled).', 'tutorpress'),
                            ],
                            'sale_price' => [
                                'type'             => ['number', 'null'],
                                'minimum'           => 0,
                                'description'       => __('The sale price of the plan.', 'tutorpress'),
                            ],
                            'sale_price_from' => [
                                'type'             => ['string', 'null'],
                                'format'            => 'date-time',
                                'description'       => __('When the sale price starts (ISO 8601 format).', 'tutorpress'),
                            ],
                            'sale_price_to' => [
                                'type'             => ['string', 'null'],
                                'format'            => 'date-time',
                                'description'       => __('When the sale price ends (ISO 8601 format).', 'tutorpress'),
                            ],
                            'enrollment_fee' => [
                                'type'             => 'number',
                                'minimum'           => 0,
                                'default'           => 0,
                                'description'       => __('The enrollment fee for the plan.', 'tutorpress'),
                            ],
                            'provide_certificate' => [
                                'type'             => 'boolean',
                                'default'           => true,
                                'description'       => __('Whether the plan provides certificates.', 'tutorpress'),
                            ],
                            'is_featured' => [
                                'type'             => 'boolean',
                                'default'           => false,
                                'description'       => __('Whether the plan is featured.', 'tutorpress'),
                            ],
                            'featured_text' => [
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Featured badge text for the plan.', 'tutorpress'),
                            ],
                            'trial_value' => [
                                'type'             => 'integer',
                                'minimum'           => 0,
                                'default'           => 0,
                                'description'       => __('Trial period value.', 'tutorpress'),
                            ],
                            'trial_interval' => [
                                'type'             => ['string', 'null'],
                                'enum'              => ['day', 'week', 'month', 'year', null],
                                'description'       => __('Trial period interval.', 'tutorpress'),
                            ],
                            'short_description' => [
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Short description of the plan.', 'tutorpress'),
                            ],
                            'description' => [
                                'type'             => 'string',
                                'sanitize_callback' => 'sanitize_textarea_field',
                                'description'       => __('Full description of the plan.', 'tutorpress'),
                            ],
                        ],
                    ],
                    [
                        'methods'             => WP_REST_Server::DELETABLE,
                        'callback'            => [$this, 'delete_subscription_plan'],
                        'permission_callback' => function($request) {
                            // Support both course_id and object_id
                            $object_id = (int) ($request->get_param('object_id') ?? $request->get_param('course_id'));
                            
                            // Check if user can edit the specific object (course or bundle)
                            if ($object_id && current_user_can('edit_post', $object_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the subscription plan to delete.', 'tutorpress'),
                            ],
                            'course_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course the plan belongs to (legacy).', 'tutorpress'),
                            ],
                            'object_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the object (course or bundle) the plan belongs to.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Duplicate subscription plan
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'duplicate_subscription_plan'],
                        'permission_callback' => function($request) {
                            // Support both course_id and object_id
                            $object_id = (int) ($request->get_param('object_id') ?? $request->get_param('course_id'));
                            
                            // Check if user can edit the specific object (course or bundle)
                            if ($object_id && current_user_can('edit_post', $object_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the subscription plan to duplicate.', 'tutorpress'),
                            ],
                            'course_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course the plan belongs to (legacy).', 'tutorpress'),
                            ],
                            'object_id' => [
                                'required'          => false,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the object (course or bundle) the plan belongs to.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Sort subscription plans
            register_rest_route(
                $this->namespace,
                '/courses/(?P<course_id>[\d]+)/subscriptions/sort',
                [
                    [
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => [$this, 'sort_subscription_plans'],
                        'permission_callback' => function($request) {
                            $course_id = (int) $request->get_param('course_id');
                            
                            // Check if user can edit the specific course
                            if ($course_id && current_user_can('edit_post', $course_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course the plans belong to.', 'tutorpress'),
                            ],
                            'plan_order' => [
                                'required'          => true,
                                'type'             => 'array',
                                'description'       => __('Array of plan IDs in the desired order.', 'tutorpress'),
                                'items'             => [
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                    ],
                ]
            );

            // Sort subscription plans for bundles
            register_rest_route(
                $this->namespace,
                '/bundles/(?P<bundle_id>[\d]+)/subscriptions/sort',
                [
                    [
                        'methods'             => WP_REST_Server::EDITABLE,
                        'callback'            => [$this, 'sort_bundle_subscription_plans'],
                        'permission_callback' => function($request) {
                            $bundle_id = (int) $request->get_param('bundle_id');
                            
                            // Check if user can edit the specific bundle
                            if ($bundle_id && current_user_can('edit_post', $bundle_id)) {
                                return true;
                            }
                            
                            // Fallback to general permission check
                            return $this->check_permission($request);
                        },
                        'args'               => [
                            'bundle_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the bundle the plans belong to.', 'tutorpress'),
                            ],
                            'plan_order' => [
                                'required'          => true,
                                'type'             => 'array',
                                'description'       => __('Array of plan IDs in the desired order.', 'tutorpress'),
                                'items'             => [
                                    'type' => 'integer',
                                ],
                            ],
                        ],
                    ],
                ]
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: Failed to register routes - ' . $e->getMessage());
        }
    }

    /**
     * Get subscription plans for a course.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_course_subscriptions($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $course_id = $request->get_param('course_id');

            // Validate course
            $validation_result = $this->validate_course_id($course_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Get subscription plans for this course
            $plans = $this->get_subscription_plans($course_id);

            return rest_ensure_response(
                $this->format_response(
                    $plans,
                    __('Subscription plans retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: get_course_subscriptions error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plans_fetch_error',
                __('Failed to fetch subscription plans.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get subscription plans for a specific bundle.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function get_bundle_subscriptions($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $bundle_id = $request->get_param('bundle_id');

            // Validate bundle
            $validation_result = $this->validate_bundle_id($bundle_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Get subscription plans for this bundle
            $plans = $this->get_subscription_plans($bundle_id);

            return rest_ensure_response(
                $this->format_response(
                    $plans,
                    __('Bundle subscription plans retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: get_bundle_subscriptions error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plans_fetch_error',
                __('Failed to fetch bundle subscription plans.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Create a new subscription plan.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function create_subscription_plan($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            // Support both course_id (legacy) and object_id (universal)
            $object_id = $request->get_param('object_id') ?? $request->get_param('course_id');
            
            if (!$object_id) {
                return new WP_Error(
                    'missing_object_id',
                    __('Object ID is required (course_id or object_id).', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Validate object (course or bundle)
            $validation_result = $this->validate_object_id($object_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Prepare plan data
            $plan_data = $this->prepare_plan_data($request);
            
            // Create the plan
            $plan_id = $this->create_subscription_plan_in_db($object_id, $plan_data);

            if (is_wp_error($plan_id)) {
                return $plan_id;
            }

            // Get the created plan
            $plan = $this->get_plan_by_id($plan_id);
            
            if (!$plan) {
                return new WP_Error(
                    'plan_retrieval_failed',
                    __('Failed to retrieve created subscription plan.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            return rest_ensure_response(
                $this->format_response(
                    $plan,
                    __('Subscription plan created successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: create_subscription_plan error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plan_create_error',
                __('Failed to create subscription plan.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Update an existing subscription plan.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function update_subscription_plan($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $plan_id = (int) $request->get_param('id');
            
            // Support both course_id (legacy) and object_id (universal)
            $object_id = $request->get_param('object_id') ?? $request->get_param('course_id');
            
            if (!$object_id) {
                return new WP_Error(
                    'missing_object_id',
                    __('Object ID is required (course_id or object_id).', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Validate object (course or bundle)
            $validation_result = $this->validate_object_id($object_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Validate plan exists and belongs to the course
            $plan_validation = $this->validate_plan_belongs_to_object($plan_id, $object_id);
            if (is_wp_error($plan_validation)) {
                return $plan_validation;
            }

            // Validate plan data
            $validation_result = $this->validate_plan_data($request);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Prepare plan data
            $plan_data = $this->prepare_plan_data($request);
            
            // Update the plan
            $update_result = $this->update_subscription_plan_in_db($plan_id, $plan_data);

            if (is_wp_error($update_result)) {
                return $update_result;
            }

            // Get the updated plan
            $plan = $this->get_plan_by_id($plan_id);
            
            if (!$plan) {
                return new WP_Error(
                    'plan_retrieval_failed',
                    __('Failed to retrieve updated subscription plan.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            return rest_ensure_response(
                $this->format_response(
                    $plan,
                    __('Subscription plan updated successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: update_subscription_plan error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plan_update_error',
                __('Failed to update subscription plan.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a subscription plan.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function delete_subscription_plan($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $plan_id = (int) $request->get_param('id');
            
            // Support both course_id (legacy) and object_id (universal)
            $object_id = $request->get_param('object_id') ?? $request->get_param('course_id');
            
            if (!$object_id) {
                return new WP_Error(
                    'missing_object_id',
                    __('Object ID is required (course_id or object_id).', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Validate object (course or bundle)
            $validation_result = $this->validate_object_id($object_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Validate plan exists and belongs to the course
            $plan_validation = $this->validate_plan_belongs_to_object($plan_id, $object_id);
            if (is_wp_error($plan_validation)) {
                return $plan_validation;
            }

            // Delete the plan
            $delete_result = $this->delete_subscription_plan_in_db($plan_id);

            if (is_wp_error($delete_result)) {
                return $delete_result;
            }

            return rest_ensure_response(
                $this->format_response(
                    null,
                    __('Subscription plan deleted successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: delete_subscription_plan error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plan_delete_error',
                __('Failed to delete subscription plan.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Duplicate a subscription plan.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function duplicate_subscription_plan($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $plan_id = (int) $request->get_param('id');
            
            // Support both course_id (legacy) and object_id (universal)
            $object_id = $request->get_param('object_id') ?? $request->get_param('course_id');
            
            if (!$object_id) {
                return new WP_Error(
                    'missing_object_id',
                    __('Object ID is required (course_id or object_id).', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Validate object (course or bundle)
            $validation_result = $this->validate_object_id($object_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Validate plan exists and belongs to the object (course or bundle)
            $plan_validation = $this->validate_plan_belongs_to_object($plan_id, $object_id);
            if (is_wp_error($plan_validation)) {
                return $plan_validation;
            }

            // Duplicate the plan
            $duplicate_result = $this->duplicate_subscription_plan_in_db($plan_id, $object_id);

            if (is_wp_error($duplicate_result)) {
                return $duplicate_result;
            }

            // Get the duplicated plan
            $plan = $this->get_plan_by_id($duplicate_result);

            return rest_ensure_response(
                $this->format_response(
                    $plan,
                    __('Subscription plan duplicated successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: duplicate_subscription_plan error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plan_duplicate_error',
                __('Failed to duplicate subscription plan.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Sort subscription plans.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function sort_subscription_plans($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $course_id = (int) $request->get_param('course_id');
            $plan_order = $request->get_param('plan_order');

            // Validate course
            $validation_result = $this->validate_course_id($course_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Validate plan order array
            if (!is_array($plan_order) || empty($plan_order)) {
                return new WP_Error(
                    'invalid_plan_order',
                    __('Plan order must be a non-empty array.', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Validate that all plans belong to the course
            $validation_result = $this->validate_plans_belong_to_course($plan_order, $course_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Sort the plans
            $sort_result = $this->sort_subscription_plans_in_db($plan_order);

            if (is_wp_error($sort_result)) {
                return $sort_result;
            }

            return rest_ensure_response(
                $this->format_response(
                    null,
                    __('Subscription plans sorted successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: sort_subscription_plans error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plan_sort_error',
                __('Failed to sort subscription plans.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Sort subscription plans for a specific bundle.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response or error.
     */
    public function sort_bundle_subscription_plans($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $bundle_id = (int) $request->get_param('bundle_id');
            $plan_order = $request->get_param('plan_order');

            // Validate bundle
            $validation_result = $this->validate_bundle_id($bundle_id);
            if (is_wp_error($validation_result)) {
                return $validation_result;
            }

            // Check if subscription tables exist
            if (!$this->subscription_tables_exist()) {
                return new WP_Error(
                    'subscription_tables_not_found',
                    __('Subscription tables not found. Please ensure the Tutor LMS subscription addon is properly installed.', 'tutorpress'),
                    ['status' => 500]
                );
            }

            // Validate plan order array
            if (!is_array($plan_order) || empty($plan_order)) {
                return new WP_Error(
                    'invalid_plan_order',
                    __('Plan order must be a non-empty array.', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Sort the plans (same logic as course plans)
            $result = $this->sort_subscription_plans_in_db($plan_order);
            
            if (is_wp_error($result)) {
                return $result;
            }

            // Get updated plans
            $plans = $this->get_subscription_plans($bundle_id);

            return rest_ensure_response(
                $this->format_response(
                    $plans,
                    __('Bundle subscription plans sorted successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            error_log('TutorPress Subscriptions Controller: sort_bundle_subscription_plans error - ' . $e->getMessage());
            return new WP_Error(
                'subscription_plan_sort_error',
                __('Failed to sort bundle subscription plans.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Validate course ID.
     *
     * @param int $course_id The course ID.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    private function validate_course_id($course_id) {
        $course = get_post($course_id);
        if (!$course || $course->post_type !== tutor()->course_post_type) {
            return new WP_Error(
                'invalid_course',
                __('Invalid course ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        return true;
    }

    /**
     * Validate bundle ID.
     *
     * @since 1.0.0
     * @param int $bundle_id Bundle ID to validate.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    private function validate_bundle_id($bundle_id) {
        $bundle = get_post($bundle_id);
        if (!$bundle || $bundle->post_type !== tutor()->bundle_post_type) {
            return new WP_Error(
                'invalid_bundle',
                __('Invalid bundle ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        return true;
    }

    /**
     * Validate object ID (course or bundle).
     *
     * @since 1.0.0
     * @param int $object_id Object ID to validate.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    private function validate_object_id($object_id) {
        $post = get_post($object_id);
        if (!$post) {
            return new WP_Error(
                'invalid_object_id',
                __('Invalid object ID.', 'tutorpress'),
                ['status' => 404]
            );
        }

        // Check if it's a valid course or bundle
        $valid_types = [tutor()->course_post_type, tutor()->bundle_post_type];
        if (!in_array($post->post_type, $valid_types, true)) {
            return new WP_Error(
                'invalid_object_type',
                __('Object must be a course or bundle.', 'tutorpress'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Validate that a plan belongs to the specified object (course or bundle).
     *
     * @since 1.0.0
     * @param int $plan_id Plan ID to validate.
     * @param int $object_id Object ID (course or bundle) to validate against.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    private function validate_plan_belongs_to_object($plan_id, $object_id) {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_subscription_plan_items 
             WHERE plan_id = %d AND object_id = %d",
            $plan_id,
            $object_id
        ));

        if (!$exists) {
            return new WP_Error(
                'plan_object_mismatch',
                __('Plan does not belong to the specified object.', 'tutorpress'),
                ['status' => 400]
            );
        }

        return true;
    }

    /**
     * Check if subscription tables exist.
     *
     * @return bool True if tables exist, false otherwise.
     */
    private function subscription_tables_exist() {
        global $wpdb;
        
        $plans_table = $wpdb->prefix . 'tutor_subscription_plans';
        $items_table = $wpdb->prefix . 'tutor_subscription_plan_items';
        
        return $wpdb->get_var("SHOW TABLES LIKE '$plans_table'") === $plans_table &&
               $wpdb->get_var("SHOW TABLES LIKE '$items_table'") === $items_table;
    }

    /**
     * Get subscription plans for a course.
     *
     * @param int $course_id The course ID.
     * @return array Array of subscription plans.
     */
    private function get_subscription_plans($course_id) {
        global $wpdb;
        
        $plans = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT plan.* FROM {$wpdb->prefix}tutor_subscription_plans AS plan
                INNER JOIN {$wpdb->prefix}tutor_subscription_plan_items AS item
                ON item.plan_id = plan.id
                WHERE plan.payment_type = %s
                AND item.object_id = %d
                ORDER BY plan.plan_order ASC",
                'recurring',
                $course_id
            )
        );

        // Format plans for response
        return array_map(function($plan) {
            return [
                'id' => (int) $plan->id,
                'plan_name' => $plan->plan_name,
                'short_description' => $plan->short_description,
                'description' => $plan->description,
                'payment_type' => $plan->payment_type,
                'plan_type' => $plan->plan_type,
                'recurring_value' => (int) $plan->recurring_value,
                'recurring_interval' => $plan->recurring_interval,
                'recurring_limit' => (int) $plan->recurring_limit,
                'regular_price' => (float) $plan->regular_price,
                'sale_price' => $plan->sale_price ? (float) $plan->sale_price : null,
                'sale_price_from' => $plan->sale_price_from,
                'sale_price_to' => $plan->sale_price_to,
                'provide_certificate' => (bool) $plan->provide_certificate,
                'enrollment_fee' => (float) $plan->enrollment_fee,
                'trial_value' => (int) $plan->trial_value,
                'trial_interval' => $plan->trial_interval,
                'trial_fee' => (float) $plan->trial_fee,
                'is_featured' => (bool) $plan->is_featured,
                'featured_text' => $plan->featured_text,
                'is_enabled' => (bool) $plan->is_enabled,
                'plan_order' => (int) $plan->plan_order,
                'in_sale_price' => $this->in_sale_price($plan),
            ];
        }, $plans);
    }

    /**
     * Check if a plan is currently on sale.
     *
     * @param object $plan The plan object.
     * @return bool True if on sale, false otherwise.
     */
    private function in_sale_price($plan) {
        if (!$plan->sale_price || $plan->sale_price <= 0) {
            return false;
        }

        $current_time = current_time('mysql');
        
        if ($plan->sale_price_from && $current_time < $plan->sale_price_from) {
            return false;
        }
        
        if ($plan->sale_price_to && $current_time > $plan->sale_price_to) {
            return false;
        }
        
        return true;
    }

    /**
     * Prepare plan data from request.
     *
     * @param WP_REST_Request $request The request object.
     * @return array Plan data.
     */
    private function prepare_plan_data($request) {
        $data = $request->get_params();
        
        // Process request data
        
        // Convert boolean fields
        $data['provide_certificate'] = (!empty($data['provide_certificate']) && $data['provide_certificate'] !== false) ? 1 : 0;
        $data['is_featured'] = (!empty($data['is_featured']) && $data['is_featured'] !== false) ? 1 : 0;
        $data['is_enabled'] = 1; // Default to enabled
        
        // Set defaults for all required fields
        $data['payment_type'] = $data['payment_type'] ?? 'recurring';
        $data['plan_type'] = $data['plan_type'] ?? 'course';
        $data['restriction_mode'] = $data['restriction_mode'] ?? null; // Can be null
        $data['recurring_value'] = $data['recurring_value'] ?? 1;
        $data['recurring_interval'] = $data['recurring_interval'] ?? 'month';
        $data['recurring_limit'] = $data['recurring_limit'] ?? 0;
        $data['enrollment_fee'] = $data['enrollment_fee'] ?? 0;
        $data['trial_value'] = $data['trial_value'] ?? 0;
        $data['trial_interval'] = $data['trial_interval'] ?? null; // Can be null
        $data['trial_fee'] = $data['trial_fee'] ?? 0;
        $data['plan_order'] = $data['plan_order'] ?? 0;
        
        // Ensure required fields have values
        $data['plan_name'] = $data['plan_name'] ?? '';
        $data['regular_price'] = $data['regular_price'] ?? 0;
        
        // Handle null values properly - convert empty strings to null (matching Tutor LMS)
        $data['short_description'] = (empty($data['short_description']) || $data['short_description'] === '' || $data['short_description'] === '0') ? null : $data['short_description'];
        $data['description'] = (empty($data['description']) || $data['description'] === '') ? null : $data['description'];
        $data['featured_text'] = (empty($data['featured_text']) || $data['featured_text'] === '') ? null : $data['featured_text'];
        $data['trial_interval'] = (empty($data['trial_interval']) || $data['trial_interval'] === '' || $data['trial_interval'] === '0') ? null : $data['trial_interval'];
        $data['restriction_mode'] = (empty($data['restriction_mode']) || $data['restriction_mode'] === '') ? null : $data['restriction_mode'];
        
        // Convert date formats if provided, or set to null if empty (matching Tutor LMS)
        if (!empty($data['sale_price_from']) && $data['sale_price_from'] !== '0000-00-00 00:00:00') {
            $data['sale_price_from'] = $this->convert_to_mysql_datetime($data['sale_price_from']);
        } else {
            $data['sale_price_from'] = null;
        }
        
        if (!empty($data['sale_price_to']) && $data['sale_price_to'] !== '0000-00-00 00:00:00') {
            $data['sale_price_to'] = $this->convert_to_mysql_datetime($data['sale_price_to']);
        } else {
            $data['sale_price_to'] = null;
        }
        
        // Return processed data
        
        return $data;
    }

    /**
     * Convert ISO 8601 datetime to MySQL format.
     *
     * @param string $iso_datetime ISO 8601 datetime string.
     * @return string MySQL datetime string.
     */
    private function convert_to_mysql_datetime($iso_datetime) {
        $date = new DateTime($iso_datetime);
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Create subscription plan in database.
     *
     * @param int $course_id The course ID.
     * @param array $plan_data The plan data.
     * @return int|WP_Error Plan ID on success, error on failure.
     */
    private function create_subscription_plan_in_db($course_id, $plan_data) {
        global $wpdb;
        
        // Filter out non-database fields
        $db_fields = [
            'payment_type', 'plan_type', 'restriction_mode', 'plan_name', 
            'short_description', 'description', 'is_featured', 'featured_text',
            'recurring_value', 'recurring_interval', 'recurring_limit',
            'regular_price', 'sale_price', 'sale_price_from', 'sale_price_to',
            'provide_certificate', 'enrollment_fee', 'trial_value', 
            'trial_interval', 'trial_fee', 'is_enabled', 'plan_order'
        ];
        
        $filtered_data = array_intersect_key($plan_data, array_flip($db_fields));
        
        // Insert plan with explicit data type casting to prevent corruption
        $insert_data = [];
        foreach ($filtered_data as $key => $value) {
            // Force string values for specific fields to prevent type conversion issues
            if (in_array($key, ['recurring_interval', 'trial_interval', 'payment_type', 'plan_type', 'restriction_mode', 'plan_name', 'short_description', 'description', 'featured_text'])) {
                $insert_data[$key] = (string) $value;
            } else {
                $insert_data[$key] = $value;
            }
        }
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'tutor_subscription_plans',
            $insert_data
        );
        
        // Log database errors if any
        if ($result === false) {
            error_log('TutorPress Subscriptions: Database error: ' . $wpdb->last_error);
        }
        
        if ($result === false) {
            return new WP_Error(
                'database_error',
                __('Failed to create subscription plan in database.', 'tutorpress'),
                ['status' => 500]
            );
        }
        
        $plan_id = $wpdb->insert_id;
        
        // Associate plan with course
        $result = $wpdb->insert(
            $wpdb->prefix . 'tutor_subscription_plan_items',
            [
                'plan_id' => $plan_id,
                'object_name' => $plan_data['plan_type'],
                'object_id' => $course_id,
            ],
            ['%d', '%s', '%d']
        );
        
        if ($result === false) {
            // Rollback plan creation if association fails
            $wpdb->delete($wpdb->prefix . 'tutor_subscription_plans', ['id' => $plan_id], ['%d']);
            return new WP_Error(
                'database_error',
                __('Failed to associate subscription plan with course.', 'tutorpress'),
                ['status' => 500]
            );
        }
        
        return $plan_id;
    }

    /**
     * Get plan by ID.
     *
     * @param int $plan_id The plan ID.
     * @return array|false Plan data or false if not found.
     */
    private function get_plan_by_id($plan_id) {
        global $wpdb;
        
        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tutor_subscription_plans WHERE id = %d",
                $plan_id
            )
        );
        
        if (!$plan) {
            return false;
        }
        
        return [
            'id' => (int) $plan->id,
            'plan_name' => $plan->plan_name,
            'short_description' => $plan->short_description,
            'description' => $plan->description,
            'payment_type' => $plan->payment_type,
            'plan_type' => $plan->plan_type,
            'recurring_value' => (int) $plan->recurring_value,
            'recurring_interval' => $plan->recurring_interval,
            'recurring_limit' => (int) $plan->recurring_limit,
            'regular_price' => (float) $plan->regular_price,
            'sale_price' => $plan->sale_price ? (float) $plan->sale_price : null,
            'sale_price_from' => $plan->sale_price_from,
            'sale_price_to' => $plan->sale_price_to,
            'provide_certificate' => (bool) $plan->provide_certificate,
            'enrollment_fee' => (float) $plan->enrollment_fee,
            'trial_value' => (int) $plan->trial_value,
            'trial_interval' => $plan->trial_interval,
            'trial_fee' => (float) $plan->trial_fee,
            'is_featured' => (bool) $plan->is_featured,
            'featured_text' => $plan->featured_text,
            'is_enabled' => (bool) $plan->is_enabled,
            'plan_order' => (int) $plan->plan_order,
            'in_sale_price' => $this->in_sale_price($plan),
        ];
    }

    /**
     * Validate that a plan exists and belongs to the specified course.
     *
     * @param int $plan_id The plan ID.
     * @param int $course_id The course ID.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    private function validate_plan_belongs_to_course($plan_id, $course_id) {
        global $wpdb;
        
        $plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT plan.* FROM {$wpdb->prefix}tutor_subscription_plans AS plan
                INNER JOIN {$wpdb->prefix}tutor_subscription_plan_items AS item
                ON item.plan_id = plan.id
                WHERE plan.id = %d AND item.object_id = %d",
                $plan_id,
                $course_id
            )
        );
        
        if (!$plan) {
            return new WP_Error(
                'plan_not_found',
                __('Subscription plan not found or does not belong to this course.', 'tutorpress'),
                ['status' => 404]
            );
        }
        
        return true;
    }

    /**
     * Validate plan data according to Tutor LMS rules.
     *
     * @param WP_REST_Request $request The request object.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    private function validate_plan_data($request) {
        $data = $request->get_params();
        
        // Required fields validation
        if (empty($data['plan_name'])) {
            return new WP_Error(
                'invalid_plan_name',
                __('Plan name is required.', 'tutorpress'),
                ['status' => 400]
            );
        }
        
        if (!isset($data['regular_price']) || $data['regular_price'] < 0) {
            return new WP_Error(
                'invalid_regular_price',
                __('Regular price must be a positive number.', 'tutorpress'),
                ['status' => 400]
            );
        }
        
        if (!isset($data['recurring_value']) || $data['recurring_value'] < 1) {
            return new WP_Error(
                'invalid_recurring_value',
                __('Recurring value must be at least 1.', 'tutorpress'),
                ['status' => 400]
            );
        }
        
        $valid_intervals = ['day', 'week', 'month', 'year'];
        if (!isset($data['recurring_interval']) || !in_array($data['recurring_interval'], $valid_intervals)) {
            return new WP_Error(
                'invalid_recurring_interval',
                __('Recurring interval must be one of: day, week, month, year.', 'tutorpress'),
                ['status' => 400]
            );
        }
        
        // Sale price validation
        if (!empty($data['sale_price'])) {
            if ($data['sale_price'] < 0) {
                return new WP_Error(
                    'invalid_sale_price',
                    __('Sale price must be a positive number.', 'tutorpress'),
                    ['status' => 400]
                );
            }
            
            if ($data['sale_price'] >= $data['regular_price']) {
                return new WP_Error(
                    'invalid_sale_price',
                    __('Sale price must be less than regular price.', 'tutorpress'),
                    ['status' => 400]
                );
            }
        }
        
        // Trial validation - only validate trial_interval if trial_value is greater than 0
        if (isset($data['trial_value']) && $data['trial_value'] > 0) {
            if (empty($data['trial_interval']) || !in_array($data['trial_interval'], $valid_intervals)) {
                return new WP_Error(
                    'invalid_trial_interval',
                    __('Trial interval must be one of: day, week, month, year.', 'tutorpress'),
                    ['status' => 400]
                );
            }
        }
        
        // Date validation
        if (!empty($data['sale_price_from']) && !empty($data['sale_price_to'])) {
            $from_date = strtotime($data['sale_price_from']);
            $to_date = strtotime($data['sale_price_to']);
            
            if ($from_date === false || $to_date === false) {
                return new WP_Error(
                    'invalid_date_format',
                    __('Sale price dates must be in valid format.', 'tutorpress'),
                    ['status' => 400]
                );
            }
            
            if ($from_date >= $to_date) {
                return new WP_Error(
                    'invalid_date_range',
                    __('Sale price start date must be before end date.', 'tutorpress'),
                    ['status' => 400]
                );
            }
        }
        
        return true;
    }

    /**
     * Update subscription plan in database.
     *
     * @param int $plan_id The plan ID.
     * @param array $plan_data The plan data.
     * @return bool|WP_Error True on success, error on failure.
     */
    private function update_subscription_plan_in_db($plan_id, $plan_data) {
        global $wpdb;
        
        // Filter out non-database fields
        $db_fields = [
            'payment_type', 'plan_type', 'restriction_mode', 'plan_name', 
            'short_description', 'description', 'is_featured', 'featured_text',
            'recurring_value', 'recurring_interval', 'recurring_limit',
            'regular_price', 'sale_price', 'sale_price_from', 'sale_price_to',
            'provide_certificate', 'enrollment_fee', 'trial_value', 
            'trial_interval', 'trial_fee', 'is_enabled', 'plan_order'
        ];
        
        $filtered_data = array_intersect_key($plan_data, array_flip($db_fields));
        
        // Debug logging only when WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TutorPress Subscriptions: Filtered plan data for update: ' . print_r($filtered_data, true));
        }
        
        // Apply explicit type casting to prevent data corruption (same as create method)
        $update_data = [];
        foreach ($filtered_data as $key => $value) {
            // Force string values for specific fields to prevent type conversion issues
            if (in_array($key, ['recurring_interval', 'trial_interval', 'payment_type', 'plan_type', 'restriction_mode', 'plan_name', 'short_description', 'description', 'featured_text'])) {
                $update_data[$key] = (string) $value;
            } else {
                $update_data[$key] = $value;
            }
        }
        
        // Update plan (without explicit format specifiers to prevent type conversion issues)
        $result = $wpdb->update(
            $wpdb->prefix . 'tutor_subscription_plans',
            $update_data,
            ['id' => $plan_id]
        );
        
        // Debug: Log any database errors
        if ($result === false) {
            error_log('TutorPress Subscriptions: Database update error: ' . $wpdb->last_error);
        }
        
        if ($result === false) {
            return new WP_Error(
                'database_error',
                __('Failed to update subscription plan in database.', 'tutorpress'),
                ['status' => 500]
            );
        }
        
        return true;
    }

    /**
     * Delete subscription plan from database.
     *
     * @param int $plan_id The plan ID.
     * @return bool|WP_Error True on success, error on failure.
     */
    private function delete_subscription_plan_in_db($plan_id) {
        global $wpdb;
        
        // Check if plan has active subscriptions
        $active_subscriptions = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_subscriptions WHERE plan_id = %d AND status IN ('active', 'pending')",
                $plan_id
            )
        );
        
        if ($active_subscriptions > 0) {
            return new WP_Error(
                'plan_has_active_subscriptions',
                __('Cannot delete plan with active subscriptions.', 'tutorpress'),
                ['status' => 400]
            );
        }
        
        // Delete plan (cascade will handle plan_items)
        $result = $wpdb->delete(
            $wpdb->prefix . 'tutor_subscription_plans',
            ['id' => $plan_id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error(
                'database_error',
                __('Failed to delete subscription plan from database.', 'tutorpress'),
                ['status' => 500]
            );
        }
        
        return true;
    }

    /**
     * Duplicate subscription plan in database.
     *
     * @param int $plan_id The plan ID to duplicate.
     * @param int $object_id The object ID (course or bundle).
     * @return int|WP_Error New plan ID on success, error on failure.
     */
    private function duplicate_subscription_plan_in_db($plan_id, $object_id) {
        global $wpdb;
        
        // Get the original plan
        $original_plan = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tutor_subscription_plans WHERE id = %d",
                $plan_id
            )
        );
        
        if (!$original_plan) {
            return new WP_Error(
                'plan_not_found',
                __('Original plan not found.', 'tutorpress'),
                ['status' => 404]
            );
        }
        
        // Prepare duplicate data
        $duplicate_data = [
            'payment_type' => $original_plan->payment_type,
            'plan_type' => $original_plan->plan_type,
            'restriction_mode' => $original_plan->restriction_mode,
            'plan_name' => $original_plan->plan_name . ' (Copy)',
            'short_description' => $original_plan->short_description,
            'description' => $original_plan->description,
            'is_featured' => 0, // Reset featured status
            'featured_text' => '', // Clear featured text
            'recurring_value' => $original_plan->recurring_value,
            'recurring_interval' => $original_plan->recurring_interval,
            'recurring_limit' => $original_plan->recurring_limit,
            'regular_price' => $original_plan->regular_price,
            'sale_price' => null, // Clear sale price
            'sale_price_from' => null, // Clear sale dates
            'sale_price_to' => null,
            'provide_certificate' => $original_plan->provide_certificate,
            'enrollment_fee' => $original_plan->enrollment_fee,
            'trial_value' => $original_plan->trial_value,
            'trial_interval' => $original_plan->trial_interval,
            'trial_fee' => $original_plan->trial_fee,
            'is_enabled' => 1, // Enable the duplicate
            'plan_order' => $original_plan->plan_order + 1, // Increment order
        ];
        
        // Filter out non-database fields
        $db_fields = [
            'payment_type', 'plan_type', 'restriction_mode', 'plan_name', 
            'short_description', 'description', 'is_featured', 'featured_text',
            'recurring_value', 'recurring_interval', 'recurring_limit',
            'regular_price', 'sale_price', 'sale_price_from', 'sale_price_to',
            'provide_certificate', 'enrollment_fee', 'trial_value', 
            'trial_interval', 'trial_fee', 'is_enabled', 'plan_order'
        ];
        
        $filtered_data = array_intersect_key($duplicate_data, array_flip($db_fields));
        
        // Insert duplicate plan
        $result = $wpdb->insert(
            $wpdb->prefix . 'tutor_subscription_plans',
            $filtered_data,
            [
                '%s', // payment_type
                '%s', // plan_type
                '%s', // restriction_mode (can be null)
                '%s', // plan_name
                '%s', // short_description
                '%s', // description
                '%d', // is_featured
                '%s', // featured_text
                '%d', // recurring_value
                '%s', // recurring_interval
                '%d', // recurring_limit
                '%f', // regular_price
                '%f', // sale_price
                '%s', // sale_price_from
                '%s', // sale_price_to
                '%d', // provide_certificate
                '%f', // enrollment_fee
                '%d', // trial_value
                '%s', // trial_interval (can be null)
                '%f', // trial_fee
                '%d', // is_enabled
                '%d', // plan_order
            ]
        );
        
        if ($result === false) {
            return new WP_Error(
                'database_error',
                __('Failed to duplicate subscription plan in database.', 'tutorpress'),
                ['status' => 500]
            );
        }
        
        $new_plan_id = $wpdb->insert_id;
        
        // Associate duplicate plan with object (course or bundle)
        $result = $wpdb->insert(
            $wpdb->prefix . 'tutor_subscription_plan_items',
            [
                'plan_id' => $new_plan_id,
                'object_name' => $duplicate_data['plan_type'],
                'object_id' => $object_id,
            ],
            ['%d', '%s', '%d']
        );
        
        if ($result === false) {
            // Rollback plan creation if association fails
            $wpdb->delete($wpdb->prefix . 'tutor_subscription_plans', ['id' => $new_plan_id], ['%d']);
            return new WP_Error(
                'database_error',
                __('Failed to associate duplicated subscription plan with object.', 'tutorpress'),
                ['status' => 500]
            );
        }
        
        return $new_plan_id;
    }

    /**
     * Validate that all plans belong to the specified course.
     *
     * @param array $plan_ids Array of plan IDs.
     * @param int $course_id The course ID.
     * @return bool|WP_Error True if valid, error otherwise.
     */
    private function validate_plans_belong_to_course($plan_ids, $course_id) {
        global $wpdb;
        
        if (empty($plan_ids)) {
            return new WP_Error(
                'invalid_plan_ids',
                __('No plan IDs provided.', 'tutorpress'),
                ['status' => 400]
            );
        }
        
        // Convert to integers and create placeholders
        $plan_ids = array_map('intval', $plan_ids);
        $placeholders = implode(',', array_fill(0, count($plan_ids), '%d'));
        
        // Check if all plans belong to the course
        $plans = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT plan.id FROM {$wpdb->prefix}tutor_subscription_plans AS plan
                INNER JOIN {$wpdb->prefix}tutor_subscription_plan_items AS item
                ON item.plan_id = plan.id
                WHERE plan.id IN ($placeholders) AND item.object_id = %d",
                array_merge($plan_ids, [$course_id])
            )
        );
        
        $found_plan_ids = array_map(function($plan) {
            return (int) $plan->id;
        }, $plans);
        
        $missing_plans = array_diff($plan_ids, $found_plan_ids);
        
        if (!empty($missing_plans)) {
            return new WP_Error(
                'plans_not_found',
                sprintf(
                    __('Some plans do not belong to this course: %s', 'tutorpress'),
                    implode(', ', $missing_plans)
                ),
                ['status' => 404]
            );
        }
        
        return true;
    }

    /**
     * Sort subscription plans in database.
     *
     * @param array $plan_order Array of plan IDs in the desired order.
     * @return bool|WP_Error True on success, error on failure.
     */
    private function sort_subscription_plans_in_db($plan_order) {
        global $wpdb;
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($plan_order as $order => $plan_id) {
                $result = $wpdb->update(
                    $wpdb->prefix . 'tutor_subscription_plans',
                    ['plan_order' => $order],
                    ['id' => $plan_id],
                    ['%d'],
                    ['%d']
                );
                
                if ($result === false) {
                    throw new Exception('Failed to update plan order');
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $wpdb->query('ROLLBACK');
            
            error_log('TutorPress Subscriptions: Sort error - ' . $e->getMessage());
            
            return new WP_Error(
                'database_error',
                __('Failed to sort subscription plans in database.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }
} 