<?php
/**
 * WooCommerce REST Controller Class
 *
 * Handles REST API functionality for WooCommerce integration.
 * Replicates Tutor LMS's WooCommerce AJAX endpoints as REST endpoints.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_WooCommerce_Controller extends TutorPress_REST_Controller {

    /**
     * Constructor.
     *
     * @since 0.1.0
     */
    public function __construct() {
        $this->rest_base = 'woocommerce';
    }

    /**
     * Register REST API routes.
     *
     * @since 0.1.0
     * @return void
     */
    public function register_routes() {
        try {
            // Get WooCommerce products (replicates tutor_get_wc_products)
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/products',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_woocommerce_products'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'exclude_linked_products' => [
                                'type'              => 'boolean',
                                'default'           => true,
                                'description'       => __('Whether to exclude products already linked to other courses.', 'tutorpress'),
                            ],
                            'course_id' => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('Course ID to exclude from linked products check.', 'tutorpress'),
                            ],
                            'search' => [
                                'type'              => 'string',
                                'sanitize_callback' => 'sanitize_text_field',
                                'description'       => __('Search term for product titles.', 'tutorpress'),
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
                        ],
                    ],
                ]
            );

            // Get specific WooCommerce product details (replicates tutor_get_wc_product)
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/products/(?P<product_id>[\d]+)',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_woocommerce_product_details'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'product_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the WooCommerce product.', 'tutorpress'),
                            ],
                            'course_id' => [
                                'type'              => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('Course ID for context (optional).', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

        } catch (Exception $e) {
            error_log('TutorPress WooCommerce Controller registration error: ' . $e->getMessage());
        }
    }

    /**
     * Get WooCommerce products (replicates tutor_get_wc_products AJAX endpoint).
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response object.
     */
    public function get_woocommerce_products($request) {
        try {
            // Check if WooCommerce is enabled
            if (!TutorPress_Addon_Checker::is_woocommerce_enabled()) {
                return new WP_Error(
                    'woocommerce_not_active',
                    __('WooCommerce is not active.', 'tutorpress'),
                    ['status' => 400]
                );
            }

            // Get request parameters
            $exclude_linked_products = $request->get_param('exclude_linked_products');
            $course_id = $request->get_param('course_id');
            $search = $request->get_param('search');
            $per_page = $request->get_param('per_page');
            $page = $request->get_param('page');

            // Convert string 'true'/'false' to boolean with proper validation
            if ($exclude_linked_products === 'true') {
                $exclude_linked_products = true;
            } elseif ($exclude_linked_products === 'false') {
                $exclude_linked_products = false;
            } elseif ($exclude_linked_products === null || $exclude_linked_products === '') {
                $exclude_linked_products = true; // Default to true
            } else {
                $exclude_linked_products = (bool) $exclude_linked_products; // Handle other boolean values
            }

            // Build query arguments using WooCommerce's proper function
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

            // Exclude products already linked to other courses
            if ($exclude_linked_products) {
                $linked_products = $this->get_linked_woocommerce_products($course_id);
                if (!empty($linked_products)) {
                    $args['exclude'] = $linked_products;
                }
            }

            // Get products using WooCommerce's proper function
            $wc_products = wc_get_products($args);
            $products = [];

            if (!empty($wc_products)) {
                foreach ($wc_products as $product) {
                    if ($product && is_a($product, 'WC_Product')) {
                        $products[] = [
                            'ID' => (string) $product->get_id(),
                            'post_title' => $product->get_name(),
                        ];
                    }
                }
            }

            // Get total count for pagination
            $count_args = $args;
            $count_args['limit'] = -1; // Get all for counting
            $count_args['return'] = 'ids';
            $all_products = wc_get_products($count_args);
            $total = count($all_products);

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
                'woocommerce_products_error',
                __('Error retrieving WooCommerce products.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get specific WooCommerce product details (replicates tutor_get_wc_product AJAX endpoint).
     *
     * @since 0.1.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The response object.
     */
    public function get_woocommerce_product_details($request) {
        try {
            // Check if WooCommerce is enabled
            if (!TutorPress_Addon_Checker::is_woocommerce_enabled()) {
                return new WP_Error(
                    'woocommerce_not_active',
                    __('WooCommerce is not active.', 'tutorpress'),
                    ['status' => 400]
                );
            }

            $product_id = $request->get_param('product_id');
            $course_id = $request->get_param('course_id');

            // Get WooCommerce product using proper function
            $product = wc_get_product($product_id);

            if (!$product || !is_a($product, 'WC_Product')) {
                return new WP_Error(
                    'product_not_found',
                    __('WooCommerce product not found.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Get product details (matching Tutor LMS response format)
            $product_details = [
                'name' => $product->get_name(),
                'regular_price' => $product->get_regular_price() ?: '0',
                'sale_price' => $product->get_sale_price() ?: '0',
                'price' => $product->get_price() ?: '0',
                'type' => $product->get_type(),
                'status' => $product->get_status(),
            ];

            return rest_ensure_response($this->format_response($product_details, __('Product details retrieved successfully.', 'tutorpress')));

        } catch (Exception $e) {
            return new WP_Error(
                'woocommerce_product_details_error',
                __('Error retrieving WooCommerce product details.', 'tutorpress'),
                ['status' => 500]
            );
        }
    }

    /**
     * Get linked WooCommerce products (products already associated with courses).
     *
     * @since 0.1.0
     * @param int $exclude_course_id Course ID to exclude from the check.
     * @return array Array of product IDs that are already linked to courses.
     */
    private function get_linked_woocommerce_products($exclude_course_id = 0) {
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
            if (wc_get_product($product_id)) {
                $valid_products[] = (int) $product_id;
            }
        }

        return $valid_products;
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
            'title' => 'woocommerce',
            'type' => 'object',
            'properties' => [
                'products' => [
                    'description' => __('Array of WooCommerce products.', 'tutorpress'),
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
} 