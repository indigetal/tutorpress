<?php
/**
 * TutorPress Global Functions
 *
 * This file is autoloaded by Composer and contains global functions for TutorPress.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get TutorPress version
 *
 * @return string
 */
if ( ! function_exists( 'tutorpress_get_version' ) ) {
    function tutorpress_get_version() {
        return defined( 'TUTORPRESS_VERSION' ) ? TUTORPRESS_VERSION : '2.0.3';
    }
}

/**
 * Get TutorPress plugin URL
 *
 * @return string
 */
if ( ! function_exists( 'tutorpress_get_plugin_url' ) ) {
    function tutorpress_get_plugin_url() {
        return defined( 'TUTORPRESS_URL' ) ? TUTORPRESS_URL : '';
    }
}

/**
 * Get TutorPress plugin path
 *
 * @return string
 */
if ( ! function_exists( 'tutorpress_get_plugin_path' ) ) {
    function tutorpress_get_plugin_path() {
        return defined( 'TUTORPRESS_PATH' ) ? TUTORPRESS_PATH : '';
    }
}

/**
 * Check if TutorPress is in development mode
 *
 * @return bool
 */
if ( ! function_exists( 'tutorpress_is_dev_mode' ) ) {
    function tutorpress_is_dev_mode() {
        return defined( 'WP_DEBUG' ) && WP_DEBUG;
    }
}

/**
 * Get a service from the service container.
 *
 * @param string $service_id Service identifier
 * @return mixed Service instance
 * @since 1.13.17
 */
if ( ! function_exists( 'tutorpress_service' ) ) {
    function tutorpress_service(string $service_id) {
        return TutorPress_Service_Container::instance()->get($service_id);
    }
}

/**
 * Get the feature flags service instance (typed helper).
 *
 * @return TutorPress_Feature_Flags_Interface
 * @since 1.13.17
 */
if ( ! function_exists( 'tutorpress_feature_flags' ) ) {
    function tutorpress_feature_flags(): TutorPress_Feature_Flags_Interface {
        return tutorpress_service('feature_flags');
    }
}

/**
 * Get the course provider service instance (typed helper).
 *
 * @return TutorPress_Course_Provider
 * @since 1.13.17
 */
if ( ! function_exists( 'tutorpress_course_provider' ) ) {
    function tutorpress_course_provider(): TutorPress_Course_Provider {
        return tutorpress_service('course_provider');
    }
}

/**
 * Get the permissions service instance (typed helper).
 *
 * @return TutorPress_Permissions
 * @since 1.13.17
 */
if ( ! function_exists( 'tutorpress_permissions' ) ) {
    function tutorpress_permissions(): TutorPress_Permissions {
        return tutorpress_service('permissions');
    }
}

/**
 * Check if user can use premium features (trial or paid license)
 *
 * @return bool
 * @since 1.15.14
 */
if ( ! function_exists( 'tutorpress_fs_can_use_premium' ) ) {
    function tutorpress_fs_can_use_premium(): bool {
        // Add transient caching with cache-buster for performance
        $cache_buster = class_exists('TutorPress_Freemius') ? TutorPress_Freemius::get_cache_buster() : 0;
        $user_id = get_current_user_id() ?: '0'; // Handle non-authenticated contexts explicitly
        $cache_key = 'tutorpress_fs_can_premium_' . $user_id . '_' . $cache_buster;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return (bool) $cached;
        }

        $can_use = false;
        // Standardize on tutorpress_fs() with fallback to my_fs()
        if (function_exists('tutorpress_fs')) {
            $can_use = tutorpress_fs()->can_use_premium_code();
        } elseif (function_exists('my_fs')) {
            $can_use = my_fs()->can_use_premium_code();
        }

        // Cache for 5 minutes to avoid repeated Freemius calls
        set_transient($cache_key, $can_use ? 1 : 0, 5 * MINUTE_IN_SECONDS);
        return $can_use;
    }
}

/**
 * Check if user is not paying (no active license)
 *
 * @return bool
 * @since 1.15.14
 */
if ( ! function_exists( 'tutorpress_fs_is_not_paying' ) ) {
    function tutorpress_fs_is_not_paying(): bool {
        // Standardize on tutorpress_fs() with fallback to my_fs()
        if (function_exists('tutorpress_fs')) {
            return tutorpress_fs()->is_not_paying();
        } elseif (function_exists('my_fs')) {
            return my_fs()->is_not_paying();
        }
        return true; // Default to locked if Freemius not available
    }
}

/**
 * Get marketing content HTML for premium features
 *
 * @return string
 * @since 1.15.14
 */
if ( ! function_exists( 'tutorpress_promo_html' ) ) {
    function tutorpress_promo_html(): string {
        // Standardize Freemius accessor and add i18n
        $url = '#';
        if (function_exists('tutorpress_fs')) {
            $url = tutorpress_fs()->get_upgrade_url();
        } elseif (function_exists('my_fs')) {
            $url = my_fs()->get_upgrade_url();
        }
        
        return '<div class="tutorpress-promo">'
            . '<h3>' . esc_html__('Unlock TutorPress Pro', 'tutorpress') . '</h3>'
            . '<p>' . esc_html__('Activate to continue using this feature.', 'tutorpress') . '</p>'
            . '<a class="button" href="' . esc_url($url) . '">' . esc_html__('Upgrade', 'tutorpress') . '</a>'
            . '</div>';
    }
}

/**
 * Get premium setting value (returns default when not paying)
 *
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed Setting value or default
 * @since 1.15.14
 */
if ( ! function_exists( 'tutorpress_get_setting' ) ) {
    function tutorpress_get_setting($key, $default = null) {
        // If Freemius SDK is available, prefer explicit checks to include trial
        if ( function_exists('tutorpress_fs') ) {
            $fs = tutorpress_fs();
            $can_use = method_exists($fs, 'can_use_premium_code') ? (bool) $fs->can_use_premium_code() : false;
            $is_trial = method_exists($fs, 'is_trial') ? (bool) $fs->is_trial() : false;
            if ( ! $can_use && ! $is_trial ) {
                return $default;
            }
        } else {
            // Fallback to cached helper if SDK not present
            if ( function_exists('tutorpress_fs_can_use_premium') && ! tutorpress_fs_can_use_premium() ) {
                return $default;
            }
        }

        // Prefer modern settings key but fall back for older installs
        $opts = get_option('tutorpress_settings', get_option('tutorpress_options', []));
        return $opts[$key] ?? $default;
    }
} 