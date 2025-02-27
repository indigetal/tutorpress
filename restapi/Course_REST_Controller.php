<?php
namespace TutorLMS\REST;

use WP_REST_Controller;
use WP_REST_Server;
use Tutor\Helpers\MetaKeysHelper;
use WP_REST_Request;
use WP_Error;
use WP_Post;
use TUTOR\Course;

class Course_REST_Controller extends WP_REST_Controller {

    public function __construct() {
        $this->namespace = 'tutorpress/v1';
        $this->rest_base = 'course';
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/meta', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_course_meta'],
            'permission_callback' => '__return_true',
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]);
    
        register_rest_route($this->namespace, '/meta', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'update_course_meta'],
            'permission_callback' => [$this, 'permissions_check'],
        ]);

        // Expose additional course metadata fields
        register_rest_route($this->namespace, '/course-meta/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_course_additional_meta'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]);                 

        // Allow updates to additional course metadata
        register_rest_route($this->namespace, '/course-meta/update', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_course_additional_meta'],
            'permission_callback' => [$this, 'permissions_check'],
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'validate_callback' => 'is_numeric',
                ],
                'meta_key' => [
                    'required'          => true,
                    'validate_callback' => function ($value) {
                        return in_array($value, [
                            '_tutor_course_benefits',
                            '_tutor_course_target_audience',
                            '_tutor_course_requirements',
                            '_tutor_course_material_includes'
                        ]);
                    },
                ],
                'value' => [
                    'required' => true,
                ],
            ],
        ]);
    
        register_rest_route($this->namespace, '/levels', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_course_levels'],
            'permission_callback' => '__return_true',
        ]);
    
        // Instructor Permissions Routes
        register_rest_route($this->namespace, '/instructor-settings', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_instructor_settings'],
            'permission_callback' => [$this, 'permissions_check'],
        ]);
    
        register_rest_route($this->namespace, '/instructor-settings', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_instructor_settings'],
            'permission_callback' => [$this, 'permissions_check'],
            'args'                => [
                'can_delete_course' => [
                    'required'          => true,
                    'validate_callback' => 'rest_validate_boolean',
                ],
            ],
        ]);
    
        // Retrieve login settings
        register_rest_route($this->namespace, '/settings/login', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_login_settings'],
            'permission_callback' => [$this, 'permissions_check'],
        ]);
    
        // Update login settings
        register_rest_route($this->namespace, '/settings/login', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_login_settings'],
            'permission_callback' => [$this, 'permissions_check'],
            'args'                => [
                'enable_native_login' => [
                    'required'          => true,
                    'validate_callback' => 'rest_validate_boolean',
                ],
            ],
        ]);

        // Get Course Availability (Fully Booked Status)
        register_rest_route($this->namespace, '/course-availability/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_course_availability'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]);

        // Get Course Duration
        register_rest_route($this->namespace, '/course-duration/(?P<id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_course_duration'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]);

        // Update Course Duration
        register_rest_route($this->namespace, '/course-duration/update', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [$this, 'update_course_duration'],
            'permission_callback' => [$this, 'permissions_check'],
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'validate_callback' => 'is_numeric',
                ],
                'duration' => [
                    'required'          => true,
                    'validate_callback' => function ($value) {
                        return isset($value['hours']) && isset($value['minutes']);
                    },
                ],
            ],
        ]);

        // Get WC Product Details
        register_rest_route($this->namespace, '/wc-product/(?P<product_id>\d+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_wc_product'],
            'permission_callback' => [$this, 'permissions_check'],
            'args'                => [
                'product_id' => [
                    'required'          => true,
                    'validate_callback' => 'is_numeric',
                ],
                'course_id' => [
                    'required'          => false,
                    'validate_callback' => 'is_numeric',
                ],
            ],
        ]);

    }    

    public function get_course_meta(WP_REST_Request $request) {
        $post_id = $request->get_param('post_id');
        if (!get_post($post_id) || get_post_type($post_id) !== 'courses') {
            return new WP_Error('invalid_course', __('Invalid course ID.', 'tutor'), ['status' => 400]);
        }
        
        $response = [];
        foreach (MetaKeysHelper::get_meta_fields() as $key => $config) {
            $response[$key] = MetaKeysHelper::get_course_meta($post_id, $key);
        }
    
        // Include instructor delete permission
        $response['can_delete_course'] = get_option('tutor_instructor_can_delete_course', false);
    
        // Expose additional data fields for the REST API
        $response['_tutor_course_benefits']        = get_post_meta($post_id, '_tutor_course_benefits', true);
        $response['_tutor_course_target_audience'] = get_post_meta($post_id, '_tutor_course_target_audience', true);
        $response['_tutor_course_requirements']    = get_post_meta($post_id, '_tutor_course_requirements', true);
        $response['_tutor_course_material_includes'] = get_post_meta($post_id, '_tutor_course_material_includes', true);
    
        // Fetch and format course duration
        $duration = get_post_meta($post_id, '_tutor_course_duration', true);
        $duration_parts = explode(':', $duration); // Expecting HH:MM format
        $response['_tutor_course_duration'] = [
            'hours'   => isset($duration_parts[0]) ? (int) $duration_parts[0] : 0,
            'minutes' => isset($duration_parts[1]) ? (int) $duration_parts[1] : 0,
        ];
        
        return rest_ensure_response($response);
    }    

    public function update_course_meta(WP_REST_Request $request) {
        $post_id  = $request->get_param('post_id');
        $meta_key = $request->get_param('meta_key');
        $value    = $request->get_param('value');
    
        if (!get_post($post_id) || get_post_type($post_id) !== 'courses') {
            return new WP_Error('invalid_course', __('Invalid course ID.', 'tutor'), ['status' => 400]);
        }
    
        if (!MetaKeysHelper::is_valid_meta_key($meta_key) && !in_array($meta_key, [
            '_tutor_course_benefits',
            '_tutor_course_target_audience',
            '_tutor_course_requirements',
            '_tutor_course_material_includes',
            '_tutor_course_duration',
            '_video'
        ])) {
            return new WP_Error('invalid_meta_key', __('Invalid metadata key.', 'tutor'), ['status' => 400]);
        }
    
        // Special handling for course duration (ensure structured format)
        if ($meta_key === '_tutor_course_duration' && is_array($value)) {
            $hours   = isset($value['hours']) ? absint($value['hours']) : 0;
            $minutes = isset($value['minutes']) ? absint($value['minutes']) : 0;
            $formatted_duration = sprintf('%02d:%02d', $hours, $minutes);
            update_post_meta($post_id, '_tutor_course_duration', $formatted_duration);
            $updated_value = $formatted_duration;
        } 
        // Special handling for video metadata (_video)
        elseif ($meta_key === '_video' && is_array($value)) {
            $sanitized_video = [
                'source_external_url' => isset($value['source_external_url']) ? esc_url($value['source_external_url']) : '',
                'source_embedded'     => isset($value['source_embedded']) ? wp_kses_post($value['source_embedded']) : '',
            ];
            update_post_meta($post_id, '_video', $sanitized_video);
            $updated_value = $sanitized_video;
        } 
        // Default case: sanitize and update other metadata
        else {
            update_post_meta($post_id, $meta_key, sanitize_text_field($value));
            $updated_value = get_post_meta($post_id, $meta_key, true);
        }
    
        return rest_ensure_response([
            'updated' => true,
            'value'   => $updated_value
        ]);
    }        

    public function get_course_details(WP_REST_Request $request) {
        $post_id = $request->get_param('post_id');
        if (!get_post($post_id) || get_post_type($post_id) !== 'courses') {
            return new WP_Error('invalid_course', __('Invalid course ID.', 'tutor'), ['status' => 400]);
        }
        
        return rest_ensure_response([
            'ID'           => $post_id,
            'title'        => get_the_title($post_id),
            'content'      => get_post_field('post_content', $post_id),
            'difficulty'   => MetaKeysHelper::get_course_meta($post_id, '_tutor_course_level'),
            'is_public'    => MetaKeysHelper::get_course_meta($post_id, '_tutor_is_public_course'),
        ]);
    }

    public function get_course_levels() {
        return rest_ensure_response([
            'beginner'     => __('Beginner', 'tutor'),
            'intermediate' => __('Intermediate', 'tutor'),
            'advanced'     => __('Advanced', 'tutor'),
        ]);
    }

    // Get instructor settings
    public function get_instructor_settings(WP_REST_Request $request) {
        return rest_ensure_response([
            'can_delete_course' => (bool) get_option('tutor_instructor_can_delete_course', false),
        ]);
    }

    // Update instructor settings & apply capability changes
    public function update_instructor_settings(WP_REST_Request $request) {
        $can_delete = $request->get_param('can_delete_course');

        update_option('tutor_instructor_can_delete_course', (bool) $can_delete);

        // Apply capability changes immediately.
        $course_instance = new Course();
        $course_instance->disable_course_trash_instructor();

        return rest_ensure_response([
            'updated'            => true,
            'can_delete_course'  => (bool) get_option('tutor_instructor_can_delete_course'),
        ]);
    }

    // Get login settings
    public function get_login_settings(WP_REST_Request $request) {
        return rest_ensure_response([
            'enable_native_login' => (bool) get_option('enable_tutor_native_login', false),
        ]);
    }

    // Update login settings
    public function update_login_settings(WP_REST_Request $request) {
        $enable_login = $request->get_param('enable_native_login');

        update_option('enable_tutor_native_login', (bool) $enable_login);

        return rest_ensure_response([
            'updated'              => true,
            'enable_native_login'  => (bool) get_option('enable_tutor_native_login'),
        ]);
    }

    /**
     * Get Course Availability (Fully Booked Status)
     *
     * @param WP_REST_Request $request REST request object.
     * @return WP_REST_Response
     */
    public function get_course_availability(WP_REST_Request $request) {
        $course_id = $request->get_param('id');

        // Validate Course ID
        if (!get_post($course_id) || get_post_type($course_id) !== 'courses') {
            return new WP_REST_Response([
                'code'    => 'invalid_course',
                'message' => __('Invalid course ID.', 'tutor'),
            ], 400);
        }

        // Determine if the course is fully booked
        $fully_booked = tutor_utils()->is_course_fully_booked();

        return new WP_REST_Response([
            'code'         => 'success',
            'fully_booked' => (bool) $fully_booked,
        ], 200);
    }

    /**
     * Get WC Product Details via REST API (Replaces AJAX)
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response JSON response.
     */
    public function get_wc_product(WP_REST_Request $request) {
        // Ensure WooCommerce is Active
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response([
                'code'    => 'woocommerce_missing',
                'message' => __('WooCommerce is not installed or activated.', 'tutor'),
            ], 400);
        }

        $product_id = absint($request->get_param('product_id'));
        $course_id  = absint($request->get_param('course_id', 0));

        // Ensure Product Exists
        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_REST_Response([
                'code'    => 'product_not_found',
                'message' => __('Product not found', 'tutor'),
            ], 404);
        }

        // Check if Product is Already Linked to Another Course
        $is_linked_with_course = tutor_utils()->product_belongs_with_course($product_id);
        if (is_object($is_linked_with_course) && $is_linked_with_course->post_id !== $course_id) {
            return new WP_REST_Response([
                'code'    => 'product_already_assigned',
                'message' => __('This product is already linked to another course.', 'tutor'),
            ], 400);
        }

        // Return Product Data
        return new WP_REST_Response([
            'code'   => 'success',
            'product' => [
                'name'          => $product->get_name(),
                'regular_price' => $product->get_regular_price(),
                'sale_price'    => $product->get_sale_price(),
            ],
        ], 200);
    }

    public function permissions_check(WP_REST_Request $request) {
        return current_user_can('edit_posts');
    }

}
