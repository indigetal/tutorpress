<?php
/**
 * Handles script and style enqueuing for Tutor LMS wpDiscuz integration.
 */

defined('ABSPATH') || exit;

class TutorPress_Scripts {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_lesson_assets']);
    }

    /**
     * Enqueue CSS and JavaScript for lesson sidebar and wpDiscuz integration.
     */
    public static function enqueue_lesson_assets() {
        if (!is_singular('lesson')) {
            return;
        }

        // Corrected plugin path (using TUTORPRESS_URL)
        wp_enqueue_style(
            'tutorpress-comments-style',
            TUTORPRESS_URL . 'assets/css/tutor-comments.css',
            [],
            filemtime(TUTORPRESS_PATH . 'assets/css/tutor-comments.css'),
            'all'
        );

        wp_enqueue_script(
            'tutorpress-hide-lesson-tabs',
            TUTORPRESS_URL . 'assets/js/hide-lesson-tabs.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/hide-lesson-tabs.js'),
            true
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
}

// Initialize the class
TutorPress_Scripts::init();
