<?php
/**
 * Handles script and style enqueuing for TutorPress.
 */

defined('ABSPATH') || exit;

class TutorPress_Scripts {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_common_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_lesson_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
    }

    /**
     * Enqueue JavaScript that runs on both lesson pages and the Tutor LMS dashboard.
     */
    public static function enqueue_common_assets() {
        wp_enqueue_script(
            'tutorpress-override-tutorlms',
            TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
            true
        );

        // Debugging: Log to console if script is loading
        wp_add_inline_script('tutorpress-override-tutorlms', 'console.log("TutorPress Common Scripts Loaded");');
    }

    /**
     * Enqueue CSS and JavaScript for lesson sidebar and wpDiscuz integration.
     */
    public static function enqueue_lesson_assets() {
        if (!is_singular('lesson')) {
            return;
        }

        wp_enqueue_style(
            'tutorpress-comments-style',
            TUTORPRESS_URL . 'assets/css/tutor-comments.css',
            [],
            filemtime(TUTORPRESS_PATH . 'assets/css/tutor-comments.css'),
            'all'
        );

        wp_enqueue_script(
            'tutorpress-sidebar-tabs',
            TUTORPRESS_URL . 'assets/js/sidebar-tabs.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/sidebar-tabs.js'),
            true
        );

        // Debugging: Log to console if script is loading
        wp_add_inline_script('tutorpress-sidebar-tabs', 'console.log("TutorPress Sidebar Tabs Loaded");');
    }

    /**
     * Enqueue JavaScript for the Tutor LMS frontend dashboard.
     */
    public static function enqueue_dashboard_assets() {
        if (!is_page('dashboard')) { // Ensure we are on the Tutor LMS dashboard
            return;
        }

        wp_enqueue_script(
            'tutorpress-override-tutorlms',
            TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
            true
        );

        // Dynamically get the correct admin URL (for single-site & multisite)
        $admin_url = get_admin_url();

        // Pass the correct admin URL to JavaScript
        wp_localize_script('tutorpress-override-tutorlms', 'TutorPressData', [
            'adminUrl' => $admin_url,
        ]);

        // Debugging: Log to console if script is loading
        wp_add_inline_script('tutorpress-override-tutorlms', 'console.log("TutorPress Dashboard Scripts Loaded");');
    }

}

// Initialize the class
TutorPress_Scripts::init();
