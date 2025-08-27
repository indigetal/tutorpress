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
        return defined( 'TUTORPRESS_VERSION' ) ? TUTORPRESS_VERSION : '1.15.5';
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