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
        return defined( 'TUTORPRESS_VERSION' ) ? TUTORPRESS_VERSION : '1.14.8';
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