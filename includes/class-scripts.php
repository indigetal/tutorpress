<?php
/**
 * Handles script and style enqueuing for TutorPress.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_Scripts {

    /**
     * Initialize script and style handlers.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        // Frontend scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_common_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_lesson_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_script_data']);

        // Admin scripts
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /**
     * Enqueue JavaScript that runs on both lesson pages and the Tutor LMS dashboard.
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_common_assets() {
        $options = get_option('tutorpress_settings', []);
        
        // Conditionally load override-tutorlms.js
        if (!empty($options['enable_sidebar_tabs']) || !empty($options['enable_dashboard_redirects'])) {
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
     *
     * @since 0.1.0
     * @return void
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
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_dashboard_assets() {
        if (!is_page('dashboard')) { // Ensure we are on the Tutor LMS dashboard
            return;
        }

        $options = get_option('tutorpress_settings', []);
        if (!empty($options['enable_dashboard_redirects'])) {
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
     * Enqueue admin-specific assets.
     *
     * @since 0.1.0
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public static function enqueue_admin_assets($hook_suffix) {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['courses', 'lesson', 'tutor_assignments'], true)) {
            return;
        }

        // Gutenberg-specific styles
        wp_enqueue_style(
            'tutorpress-gutenberg',
            TUTORPRESS_URL . 'assets/css/gutenberg.css',
            ['wp-components'], // Depend on Gutenberg styles
            filemtime(TUTORPRESS_PATH . 'assets/css/gutenberg.css'),
            'all'
        );

        // Get the asset file for dependencies and version
        $asset_file = include TUTORPRESS_PATH . 'assets/js/build/index.asset.php';

        // Enqueue the built admin script
        wp_enqueue_script(
            'tutorpress-curriculum-metabox',
            TUTORPRESS_URL . 'assets/js/build/index.js',
            array_merge(['wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-plugins', 'wp-edit-post', 'wp-i18n'], $asset_file['dependencies']),
            $asset_file['version'],
            true
        );

        // Localize script with necessary data
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorPressCurriculum', [
            'restUrl' => rest_url(),
            'restNonce' => wp_create_nonce('wp_rest'),
            'isLesson' => 'lesson' === $screen->post_type,
            'isAssignment' => 'tutor_assignments' === $screen->post_type,
            'adminUrl' => admin_url(),
        ]);
    }

    /**
     * Localize script data to pass settings to JavaScript.
     *
     * @since 0.1.0
     * @return void
     */
    public static function localize_script_data() {
        $options = get_option('tutorpress_settings', []);
        
        wp_localize_script('tutorpress-override-tutorlms', 'TutorPressData', [
            'enableSidebarTabs' => !empty($options['enable_sidebar_tabs']),
            'enableDashboardRedirects' => !empty($options['enable_dashboard_redirects']),
            'adminUrl' => admin_url(),
        ]);
    }
}

// Initialize the class
TutorPress_Scripts::init();
