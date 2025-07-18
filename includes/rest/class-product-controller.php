<?php
/**
 * Product Controller Classes
 *
 * Provides shared functionality for product-based controllers (WooCommerce, EDD).
 * Implements DRY principle to reduce code duplication between product integrations.
 * Contains both abstract base class and concrete implementations.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

/**
 * Abstract Product Controller Class
 * Provides shared functionality for product-based controllers (WooCommerce, EDD).
 */
abstract class TutorPress_Product_Controller extends TutorPress_REST_Controller {

    /**
     * The product type (e.g., 'woocommerce', 'edd').
     *
     * @var string
     */
    protected $product_type;

    /**
     * The product class name (e.g., 'WC_Product', 'EDD_Download').
     *
     * @var string
     */
    protected $product_class;

    /**
     * Product-specific function names.
     *
     * @var array
     */
    protected $product_functions;

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = $this->product_type;
        $this->validate_product_config();
    }

    /**
     * Validate that the product configuration is properly set.
     *
     * @since 0.1.0
     * @throws Exception If configuration is invalid.
     */
    protected function validate_product_config() {
        if (empty($this->product_type)) {
            throw new Exception('Product type must be set in child class.');
        }
        if (empty($this->product_class)) {
            throw new Exception('Product class must be set in child class.');
        }
        if (empty($this->product_functions) || !is_array($this->product_functions)) {
            throw new Exception('Product functions must be set in child class.');
        }
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        try {
            // Get products list
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/products',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_products_list'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => $this->get_products_list_args(),
                    ],
                ]
            );

            // Get specific product details
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/products/(?P<product_id>[\d]+)',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_product_details'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => $this->get_product_details_args(),
                    ],
                ]
            );

        } catch (Exception $e) {
            error_log('TutorPress Product Controller registration error: ' . $e->getMessage());
        }
    }

    /**
     * Get arguments for products list endpoint.
     *
     * @since 0.1.0
     * @return array
     */
    protected function get_products_list_args() {
        return [
            'course_id' => [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'description'       => __('Course ID to exclude from linked products check.', 'tutorpress'),
            ],
            'search' => [
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'description'       => sprintf(__('Search term for %s product titles.', 'tutorpress'), $this->product_type),
            ],
            'per_page' => [
                'type'              => 'integer',
                'default'           => 50,
                'minimum'           => 1,
                'maximum'           => 100,
                'sanitize_callback' => 'absint',
                'description'       => __('Number of products per page.', 'tutorpress'),
            ],
            'page' => [
                'type'              => 'integer',
                'default'           => 1,
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
                'description'       => __('Page number for pagination.', 'tutorpress'),
            ],
        ];
    }

    /**
     * Get arguments for product details endpoint.
     *
     * @since 0.1.0
     * @return array
     */
    protected function get_product_details_args() {
        return [
            'product_id' => [
                'required'          => true,
                'type'             => 'integer',
                'sanitize_callback' => 'absint',
                'description'       => sprintf(__('The ID of the %s product.', 'tutorpress'), $this->product_type),
            ],
            'course_id' => [
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'description'       => __('Course ID for context (optional).', 'tutorpress'),
            ],
        ];
    }

    /**
     * Get products list (shared implementation).
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response object.
     */
    public function get_products_list($request) {
        try {
            // Check if product system is enabled
            if (!$this->is_product_system_enabled()) {
                return new WP_Error(
                    $this->product_type . '_not_active',
                    sprintf(__('%s is not active.', 'tutorpress'), ucfirst($this->product_type)),
                    ['status' => 400]
                );
            }

            // Get request parameters
            $course_id = $request->get_param('course_id');
            $search = $request->get_param('search');
            $per_page = $request->get_param('per_page');
            $page = $request->get_param('page');

            // Build query arguments
            $args = $this->build_products_query_args($per_page, $page, $search);

            // Always exclude products linked to other courses and include current course's product
            if ($course_id) {
                // Exclude products linked to other courses
                $linked_products = $this->get_linked_products($course_id);
                if (!empty($linked_products)) {
                    $args = $this->add_exclude_products_to_args($args, $linked_products);
                }

                // Get products using product-specific method
                $products_data = $this->get_products($args);
                $products = $this->format_products_list($products_data);

                // Always include the current course's product if it exists
                $current_course_product_id = get_post_meta($course_id, '_tutor_course_product_id', true);
                if ($current_course_product_id && $this->get_product($current_course_product_id)) {
                    // Check if the current course's product is not already in the list
                    $product_exists = false;
                    foreach ($products as $product) {
                        if ($product['ID'] == $current_course_product_id) {
                            $product_exists = true;
                            break;
                        }
                    }
                    
                    // If not in the list, add it
                    if (!$product_exists) {
                        $current_product = $this->get_product($current_course_product_id);
                        if ($current_product && $this->is_product_valid($current_product) && $this->is_product_published($current_product)) {
                            $current_product_formatted = $this->format_single_product($current_product);
                            array_unshift($products, $current_product_formatted); // Add to beginning of array
                        }
                    }
                }
            } else {
                // If no course_id provided, just get all products without exclusions
                $products_data = $this->get_products($args);
                $products = $this->format_products_list($products_data);
            }

            // Get total count for pagination
            $total = $this->get_products_count($args);

            $response_data = [
                'products' => $products,
                'total' => $total,
                'total_pages' => ceil($total / $per_page),
                'current_page' => $page,
                'per_page' => $per_page,
            ];

            return rest_ensure_response($this->format_response($response_data, __('Products retrieved successfully.', 'tutorpress')));

        } catch (Exception $e) {
            return new WP_Error(
                $this->product_type . '_products_error',
                sprintf(__('Error retrieving %s products.', 'tutorpress'), ucfirst($this->product_type)),
                ['status' => 500]
            );
        }
    }

    /**
     * Get product details (shared implementation).
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response object.
     */
    public function get_product_details($request) {
        try {
            // Check if product system is enabled
            if (!$this->is_product_system_enabled()) {
                return new WP_Error(
                    $this->product_type . '_not_active',
                    sprintf(__('%s is not active.', 'tutorpress'), ucfirst($this->product_type)),
                    ['status' => 400]
                );
            }

            $product_id = $request->get_param('product_id');
            $course_id = $request->get_param('course_id');

            // Get product using product-specific method
            $product = $this->get_product($product_id);

            if (!$product || !$this->is_product_valid($product)) {
                return new WP_Error(
                    'product_not_found',
                    sprintf(__('%s product not found.', 'tutorpress'), ucfirst($this->product_type)),
                    ['status' => 404]
                );
            }

            // Validate product status
            if (!$this->is_product_published($product)) {
                return new WP_Error(
                    'product_not_published',
                    sprintf(__('%s product is not published and cannot be linked to a course.', 'tutorpress'), ucfirst($this->product_type)),
                    ['status' => 400]
                );
            }

            // Check if product is already linked to another course
            if ($course_id && $this->is_product_linked_to_other_course($product_id, $course_id)) {
                return new WP_Error(
                    'product_already_linked',
                    sprintf(__('This %s product is already linked to another course.', 'tutorpress'), $this->product_type),
                    ['status' => 400]
                );
            }

            // Get product details using product-specific method
            $product_details = $this->get_product_price_data($product);

            return rest_ensure_response($this->format_response($product_details, __('Product details retrieved successfully.', 'tutorpress')));

        } catch (Exception $e) {
            return new WP_Error(
                $this->product_type . '_product_details_error',
                sprintf(__('Error retrieving %s product details.', 'tutorpress'), ucfirst($this->product_type)),
                ['status' => 500]
            );
        }
    }

    /**
     * Parse boolean parameter with proper validation.
     *
     * @since 0.1.0
     * @param mixed $value The parameter value.
     * @param bool  $default The default value.
     * @return bool
     */
    protected function parse_boolean_param($value, $default = false) {
        if ($value === 'true') {
            return true;
        } elseif ($value === 'false') {
            return false;
        } elseif ($value === null || $value === '') {
            return $default;
        } else {
            return (bool) $value;
        }
    }

    /**
     * Get linked products (products already associated with courses).
     *
     * @since 0.1.0
     * @param int $exclude_course_id Course ID to exclude from the check.
     * @return array Array of product IDs that are already linked to courses.
     */
    protected function get_linked_products($exclude_course_id = 0) {
        global $wpdb;

        $query = "
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_tutor_course_product_id' 
            AND meta_value != '' 
            AND meta_value != '0'
            AND meta_value IS NOT NULL
        ";

        if ($exclude_course_id > 0) {
            $query .= $wpdb->prepare(" AND post_id != %d", $exclude_course_id);
        }

        $linked_products = $wpdb->get_col($query);

        // Filter out invalid product IDs
        $valid_products = [];
        foreach ($linked_products as $product_id) {
            if ($this->get_product($product_id)) {
                $valid_products[] = (int) $product_id;
            }
        }

        return $valid_products;
    }

    /**
     * Check if product is linked to another course.
     *
     * @since 0.1.0
     * @param int $product_id The product ID.
     * @param int $exclude_course_id The course ID to exclude.
     * @return bool
     */
    protected function is_product_linked_to_other_course($product_id, $exclude_course_id) {
        $linked_products = $this->get_linked_products($exclude_course_id);
        return in_array((int) $product_id, $linked_products);
    }

    /**
     * Get the item schema for the controller.
     *
     * @since 0.1.0
     * @return array The schema for the controller.
     */
    public function get_item_schema() {
        $schema = [
            '$schema' => 'http://json-schema.org/draft-04/schema#',
            'title' => $this->product_type,
            'type' => 'object',
            'properties' => [
                'products' => [
                    'description' => sprintf(__('Array of %s products.', 'tutorpress'), ucfirst($this->product_type)),
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'ID' => [
                                'description' => __('Product ID.', 'tutorpress'),
                                'type' => 'string',
                            ],
                            'post_title' => [
                                'description' => __('Product name.', 'tutorpress'),
                                'type' => 'string',
                            ],
                        ],
                    ],
                ],
                'total' => [
                    'description' => __('Total number of products.', 'tutorpress'),
                    'type' => 'integer',
                ],
                'total_pages' => [
                    'description' => __('Total number of pages.', 'tutorpress'),
                    'type' => 'integer',
                ],
                'current_page' => [
                    'description' => __('Current page number.', 'tutorpress'),
                    'type' => 'integer',
                ],
                'per_page' => [
                    'description' => __('Number of products per page.', 'tutorpress'),
                    'type' => 'integer',
                ],
            ],
        ];

        return $schema;
    }

    // Abstract methods that must be implemented by child classes

    /**
     * Check if the product system is enabled.
     *
     * @since 0.1.0
     * @return bool
     */
    abstract protected function is_product_system_enabled();

    /**
     * Get products using product-specific method.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @return array
     */
    abstract protected function get_products($args);

    /**
     * Get a specific product.
     *
     * @since 0.1.0
     * @param int $product_id The product ID.
     * @return mixed
     */
    abstract protected function get_product($product_id);

    /**
     * Check if a product is valid.
     *
     * @since 0.1.0
     * @param mixed $product The product object.
     * @return bool
     */
    abstract protected function is_product_valid($product);

    /**
     * Check if a product is published.
     *
     * @since 0.1.0
     * @param mixed $product The product object.
     * @return bool
     */
    abstract protected function is_product_published($product);

    /**
     * Get product price data.
     *
     * @since 0.1.0
     * @param mixed $product The product object.
     * @return array
     */
    abstract protected function get_product_price_data($product);

    /**
     * Build query arguments for products list.
     *
     * @since 0.1.0
     * @param int    $per_page Number of products per page.
     * @param int    $page Page number.
     * @param string $search Search term.
     * @return array
     */
    abstract protected function build_products_query_args($per_page, $page, $search);

    /**
     * Add exclude products to query arguments.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @param array $exclude_products Products to exclude.
     * @return array
     */
    abstract protected function add_exclude_products_to_args($args, $exclude_products);

    /**
     * Format products list for response.
     *
     * @since 0.1.0
     * @param array $products_data Raw products data.
     * @return array
     */
    abstract protected function format_products_list($products_data);

    /**
     * Format a single product for response.
     *
     * @since 0.1.0
     * @param mixed $product Raw product data.
     * @return array
     */
    abstract protected function format_single_product($product);

    /**
     * Get products count for pagination.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @return int
     */
    abstract protected function get_products_count($args);
}

/**
 * WooCommerce REST Controller Class
 * Concrete implementation for WooCommerce integration.
 */
class TutorPress_WooCommerce_Controller extends TutorPress_Product_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->product_type = 'woocommerce';
        $this->product_class = 'WC_Product';
        $this->product_functions = [
            'get_products' => 'wc_get_products',
            'get_product' => 'wc_get_product',
        ];
        
        parent::__construct();
    }

    /**
     * Check if WooCommerce is enabled.
     *
     * @since 0.1.0
     * @return bool
     */
    protected function is_product_system_enabled() {
        return TutorPress_Addon_Checker::is_woocommerce_enabled();
    }

    /**
     * Get WooCommerce products using WooCommerce's proper function.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @return array
     */
    protected function get_products($args) {
        return wc_get_products($args);
    }

    /**
     * Get a specific WooCommerce product.
     *
     * @since 0.1.0
     * @param int $product_id The product ID.
     * @return WC_Product|null
     */
    protected function get_product($product_id) {
        return wc_get_product($product_id);
    }

    /**
     * Check if a WooCommerce product is valid.
     *
     * @since 0.1.0
     * @param mixed $product The product object.
     * @return bool
     */
    protected function is_product_valid($product) {
        return $product && is_a($product, 'WC_Product');
    }

    /**
     * Check if a WooCommerce product is published.
     *
     * @since 0.1.0
     * @param mixed $product The product object.
     * @return bool
     */
    protected function is_product_published($product) {
        return $product->get_status() === 'publish';
    }

    /**
     * Get WooCommerce product basic data.
     *
     * @since 0.1.0
     * @param WC_Product $product The product object.
     * @return array
     */
    protected function get_product_price_data($product) {
        // Return basic product information for compatibility
        return [
            'name' => $product->get_name(),
            'regular_price' => '0',
            'sale_price' => '0',
            'price' => '0',
            'type' => $product->get_type(),
            'status' => $product->get_status(),
        ];
    }

    /**
     * Build query arguments for WooCommerce products list.
     *
     * @since 0.1.0
     * @param int    $per_page Number of products per page.
     * @param int    $page Page number.
     * @param string $search Search term.
     * @return array
     */
    protected function build_products_query_args($per_page, $page, $search) {
        $args = [
            'limit' => $per_page,
            'page' => $page,
            'orderby' => 'title',
            'order' => 'ASC',
            'status' => ['publish', 'draft', 'private', 'pending'], // Include all statuses like WooCommerce does
            'return' => 'objects',
        ];

        // Add search if provided
        if (!empty($search)) {
            $args['s'] = $search;
        }

        return $args;
    }

    /**
     * Add exclude products to WooCommerce query arguments.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @param array $exclude_products Products to exclude.
     * @return array
     */
    protected function add_exclude_products_to_args($args, $exclude_products) {
        $args['exclude'] = $exclude_products;
        return $args;
    }

    /**
     * Format WooCommerce products list for response.
     *
     * @since 0.1.0
     * @param array $products_data Raw products data.
     * @return array
     */
    protected function format_products_list($products_data) {
        $products = [];

        if (!empty($products_data)) {
            foreach ($products_data as $product) {
                if ($product && is_a($product, 'WC_Product')) {
                    $products[] = $this->format_single_product($product);
                }
            }
        }

        return $products;
    }

    /**
     * Format a single WooCommerce product for response.
     *
     * @since 0.1.0
     * @param WC_Product $product Raw product data.
     * @return array
     */
    protected function format_single_product($product) {
        return [
            'ID' => (string) $product->get_id(),
            'post_title' => $product->get_name(),
        ];
    }

    /**
     * Get WooCommerce products count for pagination.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @return int
     */
    protected function get_products_count($args) {
        $count_args = $args;
        $count_args['limit'] = -1; // Get all for counting
        $count_args['return'] = 'ids';
        $all_products = wc_get_products($count_args);
        return count($all_products);
    }
}

/**
 * EDD (Easy Digital Downloads) REST Controller Class
 * Concrete implementation for EDD integration.
 */
class TutorPress_EDD_Controller extends TutorPress_Product_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->product_type = 'edd';
        $this->product_class = 'EDD_Download';
        $this->product_functions = [
            'get_products' => 'edd_get_downloads',
            'get_product' => 'edd_get_download',
        ];
        
        parent::__construct();
    }

    /**
     * Check if EDD is enabled.
     *
     * @since 0.1.0
     * @return bool
     */
    protected function is_product_system_enabled() {
        return TutorPress_Addon_Checker::is_edd_enabled();
    }

    /**
     * Get EDD downloads using WordPress get_posts with download post type.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @return array
     */
    protected function get_products($args) {
        // Convert EDD-style args to WordPress get_posts args
        $wp_args = [
            'post_type' => 'download',
            'posts_per_page' => $args['number'] ?? 10,
            'offset' => $args['offset'] ?? 0,
            'orderby' => $args['orderby'] ?? 'title',
            'order' => $args['order'] ?? 'ASC',
            'post_status' => $args['post_status'] ?? ['publish', 'draft', 'private', 'pending'],
        ];

        // Add search if provided
        if (!empty($args['s'])) {
            $wp_args['s'] = $args['s'];
        }

        // Add exclude if provided
        if (!empty($args['exclude'])) {
            $wp_args['post__not_in'] = $args['exclude'];
        }

        $posts = get_posts($wp_args);
        
        // Return WP_Post objects directly since EDD functions might not be available
        return $posts;
    }

    /**
     * Get a specific EDD download.
     *
     * @since 0.1.0
     * @param int $product_id The download ID.
     * @return WP_Post|null
     */
    protected function get_product($product_id) {
        return get_post($product_id);
    }

    /**
     * Check if an EDD download is valid.
     *
     * @since 0.1.0
     * @param mixed $product The download object.
     * @return bool
     */
    protected function is_product_valid($product) {
        return $product && is_a($product, 'WP_Post') && $product->post_type === 'download';
    }

    /**
     * Check if an EDD download is published.
     *
     * @since 0.1.0
     * @param mixed $product The download object.
     * @return bool
     */
    protected function is_product_published($product) {
        return $product->post_status === 'publish';
    }

    /**
     * Get EDD download basic data.
     *
     * @since 0.1.0
     * @param WP_Post $product The download object.
     * @return array
     */
    protected function get_product_price_data($product) {
        // Return basic product information for compatibility
        return [
            'name' => $product->post_title,
            'regular_price' => '0',
            'sale_price' => '0',
            'price' => '0',
            'type' => 'download',
            'status' => $product->post_status,
        ];
    }

    /**
     * Build query arguments for EDD downloads list.
     *
     * @since 0.1.0
     * @param int    $per_page Number of downloads per page.
     * @param int    $page Page number.
     * @param string $search Search term.
     * @return array
     */
    protected function build_products_query_args($per_page, $page, $search) {
        $args = [
            'number' => $per_page,
            'offset' => ($page - 1) * $per_page,
            'orderby' => 'title',
            'order' => 'ASC',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
        ];

        // Add search if provided
        if (!empty($search)) {
            $args['s'] = $search;
        }

        return $args;
    }

    /**
     * Add exclude products to EDD query arguments.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @param array $exclude_products Products to exclude.
     * @return array
     */
    protected function add_exclude_products_to_args($args, $exclude_products) {
        $args['exclude'] = $exclude_products;
        return $args;
    }

    /**
     * Format EDD downloads list for response.
     *
     * @since 0.1.0
     * @param array $products_data Raw downloads data.
     * @return array
     */
    protected function format_products_list($products_data) {
        $products = [];

        if (!empty($products_data)) {
            foreach ($products_data as $product) {
                if ($product && is_a($product, 'WP_Post') && $product->post_type === 'download') {
                    $products[] = $this->format_single_product($product);
                }
            }
        }

        return $products;
    }

    /**
     * Format a single EDD download for response.
     *
     * @since 0.1.0
     * @param WP_Post $product Raw download data.
     * @return array
     */
    protected function format_single_product($product) {
        return [
            'ID' => (string) $product->ID,
            'post_title' => $product->post_title,
        ];
    }

    /**
     * Get EDD downloads count for pagination.
     *
     * @since 0.1.0
     * @param array $args Query arguments.
     * @return int
     */
    protected function get_products_count($args) {
        // Convert EDD-style args to WordPress get_posts args for counting
        $wp_args = [
            'post_type' => 'download',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'post_status' => $args['post_status'] ?? ['publish', 'draft', 'private', 'pending'],
        ];

        // Add search if provided
        if (!empty($args['s'])) {
            $wp_args['s'] = $args['s'];
        }

        // Add exclude if provided
        if (!empty($args['exclude'])) {
            $wp_args['post__not_in'] = $args['exclude'];
        }

        $post_ids = get_posts($wp_args);
        return count($post_ids);
    }
} 