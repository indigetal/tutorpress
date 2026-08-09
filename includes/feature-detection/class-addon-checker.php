<?php
/**
 * TutorPress Addon Checker Utility
 *
 * @description Low-level utility class for checking Tutor LMS Pro addon availability.
 *              Provides consistent methods for detecting enabled addons across the plugin.
 *              
 *              For high-level feature availability with business logic and capability checks,
 *              prefer using TutorPress_Feature_Flags via tutorpress_feature_flags()->can_user_access_feature().
 *              This class serves as the foundation detector while TutorPress_Feature_Flags provides orchestration.
 *
 * @package TutorPress
 * @subpackage Gutenberg\Utilities
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Addon Checker utility class
 */
class TutorPress_Addon_Checker {
    
    /**
     * Cache for addon availability checks to avoid repeated file system operations
     *
     * @var array
     */
    private static $cache = [];

    /**
     * Supported addons with their file paths and identifiers
     *
     * @var array
     */
    private static $addon_configs = [
        'course_preview' => [
            'file' => 'tutor-pro/addons/tutor-course-preview/tutor-course-preview.php',
            'basename' => 'tutor-pro/addons/tutor-course-preview/tutor-course-preview.php',
            'constant' => 'TUTOR_CP_VERSION',
            'class' => 'TUTOR_CP\CoursePreview',
        ],
        'google_meet' => [
            'file' => 'tutor-pro/addons/google-meet/google-meet.php',
            'basename' => 'tutor-pro/addons/google-meet/google-meet.php',
            'constant' => null, // Google Meet doesn't define a version constant
            'class' => 'TutorPro\GoogleMeet\GoogleMeet',
        ],
        'zoom' => [
            'file' => 'tutor-pro/addons/tutor-zoom/tutor-zoom.php',
            'basename' => 'tutor-pro/addons/tutor-zoom/tutor-zoom.php',
            'constant' => 'TUTOR_ZOOM_VERSION',
            'class' => 'TUTOR_ZOOM\Init',
        ],
        'h5p' => [
            'file' => 'tutor-pro/addons/h5p/h5p.php',
            'basename' => 'tutor-pro/addons/h5p/h5p.php',
            'constant' => 'TUTOR_H5P_VERSION',
            'class' => 'TutorPro\H5P\H5P',
        ],
        'certificate' => [
            'file' => 'tutor-pro/addons/tutor-certificate/tutor-certificate.php',
            'basename' => 'tutor-pro/addons/tutor-certificate/tutor-certificate.php',
            'constant' => 'TUTOR_CERT_VERSION',
            'class' => 'TUTOR_CERT\Init',
        ],
        'content_drip' => [
            'file' => 'tutor-pro/addons/content-drip/content-drip.php',
            'basename' => 'tutor-pro/addons/content-drip/content-drip.php',
            'constant' => 'TUTOR_CONTENT_DRIP_VERSION',
            'class' => 'TUTOR_CONTENT_DRIP\init',
        ],
        'prerequisites' => [
            'file' => 'tutor-pro/addons/tutor-prerequisites/tutor-prerequisites.php',
            'basename' => 'tutor-pro/addons/tutor-prerequisites/tutor-prerequisites.php',
            'constant' => 'TUTOR_PREREQUISITES_VERSION',
            'class' => 'TUTOR_PREREQUISITES\init',
        ],
        'multi_instructors' => [
            'file' => 'tutor-pro/addons/tutor-multi-instructors/tutor-multi-instructors.php',
            'basename' => 'tutor-pro/addons/tutor-multi-instructors/tutor-multi-instructors.php',
            'constant' => 'TUTOR_MULTI_INSTRUCTORS_VERSION',
            'class' => 'TUTOR_MULTI_INSTRUCTORS\init',
        ],
        'enrollments' => [
            'file' => 'tutor-pro/addons/enrollments/enrollments.php',
            'basename' => 'tutor-pro/addons/enrollments/enrollments.php',
            'constant' => 'TUTOR_ENROLLMENTS_VERSION',
            'class' => 'TUTOR_ENROLLMENTS\Init',
        ],
        'course_attachments' => [
            'file' => 'tutor-pro/addons/tutor-course-attachments/tutor-course-attachments.php',
            'basename' => 'tutor-pro/addons/tutor-course-attachments/tutor-course-attachments.php',
            'constant' => 'TUTOR_CA_VERSION',
            'class' => 'TUTOR_CA\Init',
        ],
        'subscription' => [
            'file' => 'tutor-pro/addons/subscription/subscription.php',
            'basename' => 'tutor-pro/addons/subscription/subscription.php',
            'constant' => 'TUTOR_SUBSCRIPTION_FILE',
            'class' => 'TutorPro\Subscription\Subscription',
        ],
        'course_bundle' => [
            'file' => 'tutor-pro/addons/course-bundle/course-bundle.php',
            'basename' => 'tutor-pro/addons/course-bundle/course-bundle.php',
            'constant' => 'TUTOR_BUNDLE_VERSION',
            'class' => 'TutorPro\CourseBundle\CourseBundle',
        ],
        // ADD BELOW: Tutor LMS Certificate Builder plugin detection
        'certificate_builder' => [
            'file' => 'tutor-lms-certificate-builder/tutor-lms-certificate-builder.php',
            'basename' => 'tutor-lms-certificate-builder/tutor-lms-certificate-builder.php',
            'constant' => 'TUTOR_CB_VERSION',
            'class' => 'Tutor\Certificate\Builder\Init',
        ],
    ];

    /**
     * Check if a specific addon is available and enabled
     *
     * @param string $addon_key The addon key (course_preview, google_meet, zoom)
     * @return bool True if addon is available and enabled
     */
    public static function is_addon_enabled($addon_key) {
        // Return cached result if available
        if (isset(self::$cache[$addon_key])) {
            return self::$cache[$addon_key];
        }

        // Check if addon config exists
        if (!isset(self::$addon_configs[$addon_key])) {
            self::$cache[$addon_key] = false;
            return false;
        }

        $config = self::$addon_configs[$addon_key];
        $result = self::check_addon_availability($config);
        
        // Cache the result
        self::$cache[$addon_key] = $result;
        
        return $result;
    }

    /**
     * Check if Course Preview addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_course_preview_enabled() {
        return self::is_addon_enabled('course_preview');
    }

    /**
     * Check if Google Meet addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_google_meet_enabled() {
        return self::is_addon_enabled('google_meet');
    }

    /**
     * Check if Zoom addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_zoom_enabled() {
        return self::is_addon_enabled('zoom');
    }

    /**
     * Check if H5P addon is available (Tutor Pro addon)
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_h5p_enabled() {
        return self::is_addon_enabled('h5p');
    }

    /**
     * Check if H5P plugin is active (independent of Tutor Pro)
     *
     * @return bool True if H5P plugin is active
     */
    public static function is_h5p_plugin_active() {
        // Check if H5P plugin is active
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        
        // Check if H5P plugin is active
        if (is_plugin_active('h5p/h5p.php')) {
            return true;
        }
        
        // Also check if H5P plugin class exists (for cases where plugin might be loaded differently)
        if (class_exists('H5PContentQuery')) {
            return true;
        }
        
        return false;
    }

    /**
     * Check if Certificate addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_certificate_enabled() {
        return self::is_addon_enabled('certificate');
    }

    /**
     * Check if Content Drip addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_content_drip_enabled() {
        return self::is_addon_enabled('content_drip');
    }

    /**
     * Check if Prerequisites addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_prerequisites_enabled() {
        return self::is_addon_enabled('prerequisites');
    }

    /**
     * Check if Multi Instructors addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_multi_instructors_enabled() {
        return self::is_addon_enabled('multi_instructors');
    }

    /**
     * Check if Enrollments addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_enrollments_enabled() {
        return self::is_addon_enabled('enrollments');
    }

    /**
     * Check if Course Attachments addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_course_attachments_enabled() {
        return self::is_addon_enabled('course_attachments');
    }

    /**
     * Check if Subscription addon is available
     *
     * @return bool True if addon is available and enabled
     */
    public static function is_subscription_enabled() {
        return self::is_addon_enabled('subscription');
    }

    public static function is_course_bundle_enabled() {
        return self::is_addon_enabled('course_bundle');
    }

    /**
     * Check if Certificate Builder plugin is available
     *
     * @return bool True if plugin is available and enabled
     */
    public static function is_certificate_builder_enabled() {
        return self::is_addon_enabled('certificate_builder');
    }


    /**
     * Get availability status for all supported addons
     *
     * @return array Associative array of addon availability
     */
    public static function get_all_addon_status() {
        $status = [];
        foreach (array_keys(self::$addon_configs) as $addon_key) {
            $status[$addon_key] = self::is_addon_enabled($addon_key);
        }
        return $status;
    }

    /**
     * Core logic for checking addon availability
     *
     * @param array $config Addon configuration
     * @return bool True if addon is available and enabled
     */
    private static function check_addon_availability($config) {
        // Special case for certificate_builder: check plugin active or class exists (like H5P)
        if ($config['file'] === 'tutor-lms-certificate-builder/tutor-lms-certificate-builder.php') {
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if (is_plugin_active('tutor-lms-certificate-builder/tutor-lms-certificate-builder.php')) {
                return true;
            }
            if (class_exists('Tutor\Certificate\Builder\Init')) {
                return true;
            }
            return false;
        }
        // Primary check: Look for the specific addon file
        $addon_file = WP_PLUGIN_DIR . '/' . $config['file'];
        
        if (!file_exists($addon_file)) {
            return false;
        }
        
        // Check if Tutor Pro is active and addon is enabled using proper Tutor method
        if (function_exists('tutor_utils')) {
            $utils = tutor_utils();
            $addon_basename = $config['basename'];
            
            // Check if the addon is enabled in Tutor's addon system
            if (method_exists($utils, 'is_addon_enabled')) {
                return $utils->is_addon_enabled($addon_basename);
            }
            
            // Fallback: Check tutor options for addon status
            $tutor_options = get_option('tutor_option', array());
            if (isset($tutor_options['tutor_pro_addons'])) {
                $addons = $tutor_options['tutor_pro_addons'];
                
                if (isset($addons[$addon_basename])) {
                    return !empty($addons[$addon_basename]);
                }
            }
        }
        
        // Final fallback: Check for constant or class (but only if file exists)
        $constant_check = $config['constant'] ? defined($config['constant']) : false;
        $class_check = $config['class'] ? class_exists($config['class']) : false;
        
        return $constant_check || $class_check;
    }

    /**
     * Clear the addon availability cache
     * Useful for testing or when addon status might change
     *
     * @return void
     */
    public static function clear_cache() {
        self::$cache = [];
    }

    /**
     * Get supported addon keys
     *
     * @return array List of supported addon keys
     */
    public static function get_supported_addons() {
        return array_keys(self::$addon_configs);
    }



    /**
     * Check if Paid Memberships Pro is enabled
     *
     * @return bool True if PMP is active and functional
     */
    public static function is_pmp_enabled() {
        return defined('PMPRO_VERSION') && 
               function_exists('pmpro_getAllLevels');
    }

    /**
     * Check if SureCart is enabled
     *
     * @return bool True if SureCart is active and functional
     */
    public static function is_surecart_enabled() {
        return class_exists('SureCart') && 
               function_exists('surecart_get_products');
    }

    /**
     * Check if WooCommerce is enabled
     *
     * @return bool True if WooCommerce is active and functional
     */
    public static function is_woocommerce_enabled() {
        // Ensure plugin.php is loaded for is_plugin_active()
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        if (function_exists('is_plugin_active') && is_plugin_active('woocommerce/woocommerce.php')) {
            return true;
        }
        // Fallback for runtime context
        return class_exists('WooCommerce') && function_exists('wc_get_products');
    }

    /**
     * Check if EDD (Easy Digital Downloads) is enabled
     *
     * @return bool True if EDD is active and functional
     */
    public static function is_edd_enabled() {
        // Ensure plugin.php is loaded for is_plugin_active()
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        if (function_exists('is_plugin_active') && is_plugin_active('easy-digital-downloads/easy-digital-downloads.php')) {
            return true;
        }
        // Fallback for runtime context - check for EDD class and post type
        return class_exists('Easy_Digital_Downloads') && post_type_exists('download');
    }

    /**
     * Check if WooCommerce is selected as the monetization engine in Tutor LMS
     *
     * @return bool True if WooCommerce is selected as the monetization engine
     */
    public static function is_woocommerce_monetization() {
        if (!self::is_woocommerce_enabled()) {
            return false;
        }
        
        // Check Tutor LMS monetization settings
        $tutor_options = get_option('tutor_option', []);
        $monetize_by = $tutor_options['monetize_by'] ?? 'none';
        
        return $monetize_by === 'wc';
    }

    /**
     * Check if EDD is selected as the monetization engine in Tutor LMS
     *
     * @return bool True if EDD is selected as the monetization engine
     */
    public static function is_edd_monetization() {
        if (!self::is_edd_enabled()) {
            return false;
        }
        
        // Check Tutor LMS monetization settings
        $tutor_options = get_option('tutor_option', []);
        $monetize_by = $tutor_options['monetize_by'] ?? 'none';
        
        return $monetize_by === 'edd';
    }

    /**
     * Get the current payment engine based on available systems and user preference
     *
     * @return string Payment engine identifier ('pmpro', 'surecart', 'tutor_pro', 'wc', 'edd', 'none')
     */
    public static function get_payment_engine() {
        // Check TutorPress settings first (user preference)
        $tutorpress_engine = get_option('tutorpress_payment_engine', 'auto');
        if ($tutorpress_engine !== 'auto') {
            return $tutorpress_engine;
        }
        
        // Read Tutor LMS monetization setting directly
        $tutor_monetize_by = function_exists('tutor_utils') ? tutor_utils()->get_option('monetize_by') : '';
        
        switch ($tutor_monetize_by) {
            case 'pmpro':
                return 'pmpro';
            case 'wc':
                return 'wc';
            case 'edd':
                return 'edd';
            case 'tutor':
                return 'tutor_pro';
            case 'free':
            case '':
            case 'none':
                return 'none';
            default:
                // Fallback to auto-detect with priority order
                if (self::is_pmp_enabled()) {
                    return 'pmpro';
                }
                
                if (self::is_surecart_enabled()) {
                    return 'surecart';
                }
                
                if (self::is_woocommerce_monetization()) {
                    return 'wc';
                }
                
                if (self::is_edd_monetization()) {
                    return 'edd';
                }
                
                // Check for Tutor native monetization (works with Free and Pro)
                $tutor_options = get_option('tutor_option', []);
                $monetize_by = $tutor_options['monetize_by'] ?? 'none';
                if ($monetize_by === 'tutor') {
                    return 'tutor_pro'; // Keep the same engine name for backwards compatibility
                }
                
                return 'none';
        }
    }

    /**
     * Get available payment engines with their display names
     *
     * @return array Associative array of available payment engines
     */
    public static function get_available_payment_engines() {
        $engines = [];
        
        if (self::is_pmp_enabled()) {
            $engines['pmpro'] = 'Paid Memberships Pro';
        }
        
        if (self::is_surecart_enabled()) {
            $engines['surecart'] = 'SureCart';
        }
        
        if (self::is_woocommerce_enabled()) {
            $engines['wc'] = 'WooCommerce';
        }
        
        if (self::is_edd_enabled()) {
            $engines['edd'] = 'Easy Digital Downloads';
        }
        
        // Check for Tutor native monetization (available in Free and Pro)
        $tutor_options = get_option('tutor_option', []);
        $monetize_by = $tutor_options['monetize_by'] ?? 'none';
        if ($monetize_by === 'tutor') {
            $engines['tutor_pro'] = 'Tutor LMS Native';
        }
        
        return $engines;
    }

    /**
     * Check if monetization is enabled for the current payment engine
     *
     * @return bool True if monetization is enabled
     */
    public static function is_monetization_enabled() {
        $payment_engine = tutorpress_feature_flags()->get_payment_engine();
        
        // If payment engine is none, monetization is explicitly disabled
        if ($payment_engine === 'none') {
            return false;
        }
        
        switch ($payment_engine) {
            case 'pmpro':
                // PMPro is "monetization enabled" when selected as payment engine
                return true;
                
            case 'surecart':
                // SureCart is "monetization enabled" when selected as payment engine
                return true;
                
            case 'wc':
                // WooCommerce is "monetization enabled" when active and selected
                return self::is_woocommerce_monetization();
                
            case 'edd':
                // EDD is "monetization enabled" when active and selected
                return self::is_edd_monetization();
                
            case 'tutor_pro':
                // Check Tutor native monetization settings
                return self::check_native_monetization();
                
            default:
                return false;
        }
    }

    /**
     * Check Tutor native monetization settings (works with both Free and Pro)
     *
     * @return bool True if Tutor native monetization is enabled
     */
    private static function check_native_monetization() {
        // Check Tutor LMS monetization settings (available in both Free and Pro)
        $tutor_options = get_option('tutor_option', []);
        $monetize_by = $tutor_options['monetize_by'] ?? 'none';
        
        // Return true if "tutor" (native) monetization is selected
        // This works with both Tutor Free and Pro - no license check needed
        return $monetize_by === 'tutor';
    }

    /**
     * Get comprehensive addon and payment engine status
     *
     * @return array Complete status array including addons and payment engines
     */
    public static function get_comprehensive_status() {
        // Check if comprehensive status is already cached
        if (isset(self::$cache['comprehensive_status'])) {
            return self::$cache['comprehensive_status'];
        }
        
        $status = self::get_all_addon_status();
        
        // Add payment engine status (cached to avoid redundant calls)
        $payment_status = self::get_payment_engine_status_cached();
        $status = array_merge($status, $payment_status);
        
        // Add H5P plugin status (independent of Tutor Pro)
        $status['h5p_plugin_active'] = self::is_h5p_plugin_active();
        // Add Certificate Builder plugin status
        $status['certificate_builder'] = self::is_certificate_builder_enabled();
        
        // Cache the comprehensive status
        self::$cache['comprehensive_status'] = $status;
        
        return $status;
    }
    
    /**
     * Get payment engine status with caching to avoid redundant calls
     *
     * @return array Payment engine status
     */
    private static function get_payment_engine_status_cached() {
        // Check if payment engine status is already cached
        if (isset(self::$cache['payment_engine_status'])) {
            return self::$cache['payment_engine_status'];
        }
        
        // Get all payment engine status in one batch
        $payment_status = [
            'tutor_pro' => (function_exists('tutor_pro') && !empty(get_option('tutor_license_info', [])['activated'] ?? false)),
            'paid_memberships_pro' => self::is_pmp_enabled(),
            'surecart' => self::is_surecart_enabled(),
            'woocommerce' => self::is_woocommerce_enabled(),
            'woocommerce_monetization' => self::is_woocommerce_monetization(),
            'edd' => self::is_edd_enabled(),
            'edd_monetization' => self::is_edd_monetization(),
            'payment_engine' => tutorpress_feature_flags()->get_payment_engine(),
            'monetization_enabled' => self::is_monetization_enabled(),
            'available_payment_engines' => self::get_available_payment_engines(),
        ];
        
        // Cache the payment engine status
        self::$cache['payment_engine_status'] = $payment_status;
        
        return $payment_status;
    }

    // ===================================================================
    // DELEGATION METHODS FOR TUTORPRESS_FEATURE_FLAGS
    // ===================================================================

    /**
     * Check if Tutor LMS is active and available.
     *
     * @since 1.0.0
     * @return bool True if Tutor LMS is active
     */
    public function is_tutor_lms_active(): bool {
        return class_exists('TUTOR\\Tutor') || function_exists('tutor');
    }

    /**
     * Check if Tutor Pro is active and available.
     *
     * @since 1.0.0
     * @return bool True if Tutor Pro is active
     */
    public function is_tutor_pro_active(): bool {
        // Check if Tutor Pro function exists
        if (!function_exists('tutor_pro')) {
            return false;
        }
        
        // Check for Tutor Pro license status
        $license_info = get_option('tutor_license_info', null);
        if (!$license_info) {
            return false;
        }
        
        // Check if license is activated (matches static method logic)
        return !empty($license_info['activated']);
    }

    /**
     * Get Tutor LMS version if available.
     *
     * @since 1.0.0
     * @return string|null Version string or null if not available
     */
    public function get_tutor_version(): ?string {
        if (!$this->is_tutor_lms_active()) {
            return null;
        }

        // Try different version constants/methods
        if (defined('TUTOR_VERSION')) {
            return TUTOR_VERSION;
        }

        if (function_exists('tutor') && method_exists(tutor(), 'version')) {
            return tutor()->version;
        }

        // Fallback: try to get from plugin data
        if (function_exists('get_plugin_data')) {
            $plugin_file = WP_PLUGIN_DIR . '/tutor/tutor.php';
            if (file_exists($plugin_file)) {
                $plugin_data = get_plugin_data($plugin_file);
                return $plugin_data['Version'] ?? null;
            }
        }

        return null;
    }

} 