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
                        'permission_callback' => [$this, 'check_read_permission'],
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

            // Create new subscription plan
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'create_subscription_plan'],
                        'permission_callback' => [$this, 'check_write_permission'],
                        'args'               => [
                            'course_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the course to create the plan for.', 'tutorpress'),
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
                                'type'             => 'number',
                                'minimum'           => 0,
                                'description'       => __('The sale price of the plan.', 'tutorpress'),
                            ],
                            'sale_price_from' => [
                                'type'             => 'string',
                                'format'            => 'date-time',
                                'description'       => __('When the sale price starts (ISO 8601 format).', 'tutorpress'),
                            ],
                            'sale_price_to' => [
                                'type'             => 'string',
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
                                'type'             => 'string',
                                'enum'              => ['day', 'week', 'month', 'year'],
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

            // Prepare plan data
            $plan_data = $this->prepare_plan_data($request);
            
            // Create the plan
            $plan_id = $this->create_subscription_plan_in_db($course_id, $plan_data);

            if (is_wp_error($plan_id)) {
                return $plan_id;
            }

            // Get the created plan
            $plan = $this->get_plan_by_id($plan_id);

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
     * Check read permissions for subscription endpoints.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if user has permission, error otherwise.
     */
    public function check_read_permission($request) {
        $course_id = (int) $request->get_param('course_id');

        // Check if user can edit the specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to view this course\'s subscription plans.', 'tutorpress'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Check write permissions for subscription endpoints.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return bool|WP_Error True if user has permission, error otherwise.
     */
    public function check_write_permission($request) {
        $course_id = (int) $request->get_param('course_id');

        // Check if user can edit the specific course
        if (!current_user_can('edit_post', $course_id)) {
            return new WP_Error(
                'rest_forbidden',
                __('You do not have permission to manage this course\'s subscription plans.', 'tutorpress'),
                ['status' => 403]
            );
        }

        return true;
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
        
        // Convert boolean fields
        $data['provide_certificate'] = isset($data['provide_certificate']) ? 1 : 0;
        $data['is_featured'] = isset($data['is_featured']) ? 1 : 0;
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
        
        // Convert date formats if provided
        if (!empty($data['sale_price_from'])) {
            $data['sale_price_from'] = $this->convert_to_mysql_datetime($data['sale_price_from']);
        }
        
        if (!empty($data['sale_price_to'])) {
            $data['sale_price_to'] = $this->convert_to_mysql_datetime($data['sale_price_to']);
        }
        
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
        
        // Debug: Log the filtered plan data being inserted
        error_log('TutorPress Subscriptions: Filtered plan data for insertion: ' . print_r($filtered_data, true));
        
        // Insert plan
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
        
        // Debug: Log any database errors
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
} 