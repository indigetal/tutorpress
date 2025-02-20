<?php
// File: /classes/Course_Editor_Sidebar.php

namespace TUTOR;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Course_Editor_Sidebar {
    public function __construct() {
        add_action('enqueue_block_editor_assets', [$this, 'enqueue_assets']);
        add_action('rest_api_init', [$this, 'register_meta']);
    }

    /**
     * Enqueue assets for the Gutenberg editor.
     */
    public function enqueue_assets() {
        $post = get_post();
        if ($post && get_post_type($post) === 'courses') {
            wp_enqueue_script(
                'course-editor-sidebar',
                plugin_dir_url(__FILE__) . '../assets/js/course-editor-sidebar.js',
                ['wp-edit-post', 'wp-components', 'wp-data', 'wp-hooks', 'wp-i18n'],
                filemtime(plugin_dir_path(__FILE__) . '../assets/js/course-editor-sidebar.js'),
                true
            );

            wp_enqueue_style(
                'course-editor-sidebar-style',
                plugin_dir_url(__FILE__) . '../assets/css/course-editor-sidebar.css',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/css/course-editor-sidebar.css')
            );
        }
    }

    /**
     * Register meta fields for future use (optional for now)
     */
    public function register_meta() {
        register_post_meta('courses', '_course_details', [
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }
}
