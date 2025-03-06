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
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_script_data']);
    }

    /**
     * Enqueue JavaScript that runs on both lesson pages and the Tutor LMS dashboard.
     */
    public static function enqueue_common_assets() {
        $options = get_option('tutorpress_settings', []);
        
        // Conditionally load override-tutorlms.js
        if (!empty($options['enable_sidebar_tabs'])) {
            wp_enqueue_script(
                'tutorpress-override-tutorlms',
                TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
                ['jquery'],
                filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
                true
            );
        }
    }

    /**
     * Enqueue CSS and JavaScript for lesson sidebar and wpDiscuz integration.
     */
    public static function enqueue_lesson_assets() {
        if (!is_singular('lesson')) {
            return;
        }
        
        $options = get_option('tutorpress_settings', []);
        if (empty($options['enable_sidebar_tabs'])) {
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
    }

    /**
     * Enqueue JavaScript for the Tutor LMS frontend dashboard.
     */
    public static function enqueue_dashboard_assets() {
        if (!is_page('dashboard')) { // Ensure we are on the Tutor LMS dashboard
            return;
        }

        $options = get_option('tutorpress_settings', []);
        if (!empty($options['enable_sidebar_tabs'])) {
            wp_enqueue_script(
                'tutorpress-override-tutorlms',
                TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
                ['jquery'],
                filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
                true
            );
        }
    }

    /**
     * Localize script data to pass settings to JavaScript.
     */
    public static function localize_script_data() {
        $options = get_option('tutorpress_settings', []);
        
        wp_localize_script('tutorpress-override-tutorlms', 'TutorPressData', [
            'enableSidebarTabs' => !empty($options['enable_sidebar_tabs']),
            'adminUrl' => admin_url(),
        ]);
    }
}

// Initialize the class
TutorPress_Scripts::init();
