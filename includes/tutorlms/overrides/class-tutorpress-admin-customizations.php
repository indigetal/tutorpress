<?php
/**
 * Handles custom admin menu and redirections for TutorPress.
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_Admin_Customizations {

    public static function init() {
        $options = get_option('tutorpress_settings', []);

        // Always add these basic menu customizations
        add_action('admin_menu', [__CLASS__, 'add_lessons_menu_item']);
        add_action('admin_menu', [__CLASS__, 'reorder_tutor_submenus'], 100);
        add_action('init', [__CLASS__, 'conditionally_hide_builder_button']);

        // Only add AJAX handlers if admin redirects are enabled (use wrapper to respect Freemius gating)
        if ( function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_admin_redirects', false) : (!empty($options['enable_admin_redirects']) && $options['enable_admin_redirects']) ) {
            // Add hook to ensure our script runs after Tutor's course list is loaded
            add_action('tutor_admin_after_course_list_action', [__CLASS__, 'enqueue_admin_overrides']);
            // Intercept course creation via AJAX, create draft and redirect to Gutenberg
            add_action('wp_ajax_tutor_create_new_draft_course', [__CLASS__, 'intercept_tutor_create_course'], 0);
            // Intercept Tutor LMS's AJAX handler for creating new course bundles
            add_action('wp_ajax_tutor_create_course_bundle', [__CLASS__, 'intercept_tutor_create_course_bundle'], 0);
        }
    }

    /**
     * Enqueue admin overrides script at the right time.
     */
    public static function enqueue_admin_overrides() {
        // Get the asset file for dependencies and version
        $asset_file = include TUTORPRESS_PATH . 'assets/js/build/index.asset.php';
        
        // Enqueue the built script
        wp_enqueue_script(
            'tutorpress-admin',
            TUTORPRESS_URL . 'assets/js/build/index.js',
            array_merge(['jquery'], $asset_file['dependencies']),
            $asset_file['version'],
            true
        );

        // Add TutorPressData for overrides
        wp_localize_script('tutorpress-admin', 'TutorPressData', [
            'enableAdminRedirects' => (function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_admin_redirects', false) : false),
            'adminUrl' => admin_url(),
        ]);
    }

    /**
     * Conditionally hides the "Edit with Course Builder" button via CSS.
     */
    public static function conditionally_hide_builder_button() {
        $options = get_option('tutorpress_settings', []);

        // Use Freemius-aware wrapper to decide whether to remove the frontend builder button
        $remove_button = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('remove_frontend_builder_button', '0') : ($options['remove_frontend_builder_button'] ?? '0');
        if ($remove_button && '1' === $remove_button) {
            add_action('admin_head', [__CLASS__, 'hide_builder_button_css']);
        }
    }

    /**
     * Injects CSS to hide the frontend builder button from the Gutenberg editor header.
     */
    public static function hide_builder_button_css() {
        echo '<style>#tutor-frontend-builder-trigger { display: none !important; }</style>';
    }

    /**
     * Remove the "Edit with Course Builder" button action from Tutor LMS.
     * This prevents the button from being added to the admin bar.
     */
    public static function remove_tutor_admin_bar_button_action() {
        $options = get_option('tutorpress_settings', []);

        $remove_button = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('remove_frontend_builder_button', '0') : ($options['remove_frontend_builder_button'] ?? '0');
        if (empty($remove_button) || '1' !== $remove_button) {
            return;
        }

        if (function_exists('tutor_lms') && !empty(tutor_lms()->admin)) {
            remove_action('admin_bar_menu', [tutor_lms()->admin, 'add_toolbar_items'], 100);
        }
    }

    /**
     * Add "Lessons" menu item under "Tutor LMS" in the WordPress admin menu.
     */
    public static function add_lessons_menu_item() {
        add_submenu_page(
            'tutor',
            __('Lessons', 'tutorpress'),
            __('Lessons', 'tutorpress'),
            'edit_tutor_lesson',
            'edit.php?post_type=lesson'
        );
    }

    /**
     * Adjust the order of submenu items to move "Lessons" below "Courses".
     */
    public static function reorder_tutor_submenus() {
        global $submenu;
        
        // Ensure the Tutor LMS menu exists before modifying it
        if (!isset($submenu['tutor'])) {
            return;
        }

        foreach ($submenu['tutor'] as $key => $item) {
            if ($item[2] === 'edit.php?post_type=lesson') {
                $lesson_menu = $submenu['tutor'][$key];
                unset($submenu['tutor'][$key]); // Remove from original position
                array_splice($submenu['tutor'], 1, 0, [$lesson_menu]); // Move to second position
                break;
            }
        }
    }

    /**
     * Intercept Tutor LMS's AJAX handler for creating new courses.
     * Prevents duplicate course creation when redirecting to Gutenberg.
     */
    public static function intercept_tutor_create_course() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'tutor_create_new_draft_course') {
            return;
        }
        if (!current_user_can('edit_posts')) {
            wp_die(json_encode(['success' => false, 'message' => 'Insufficient permissions']), 403);
        }
        
        // Only intercept backend/admin requests, not frontend requests
        // Check if this is a frontend request by looking at the referer or source
        $is_frontend_request = false;
        
        // Check referer - if it's from frontend dashboard, let Tutor LMS handle it
        if (isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            if (strpos($referer, 'dashboard') !== false || strpos($referer, 'tutor_dashboard') !== false) {
                $is_frontend_request = true;
            }
        }
        
        // Check if source parameter indicates frontend
        if (isset($_POST['source']) && $_POST['source'] === 'frontend') {
            $is_frontend_request = true;
        }
        
        // If this is a frontend request, let Tutor LMS handle it
        if ($is_frontend_request) {
            return;
        }
        
        // Let Tutor LMS create the draft course
        $course_id = wp_insert_post([
            'post_title'  => __('New Course', 'tutor'),
            'post_type'   => tutor()->course_post_type,
            'post_status' => 'draft',
            'post_name'   => 'new-course',
        ]);

        if (is_wp_error($course_id)) {
            wp_die(json_encode(['success' => false, 'message' => $course_id->get_error_message()]), 500);
        }

        // Set default price type like Tutor LMS does
        update_post_meta($course_id, '_tutor_course_price_type', 'free');

        // Return URL to create-course page (our JS will modify this to Gutenberg)
        wp_die(json_encode([
            'success' => true,
            'message' => __('Draft course created', 'tutor'),
            'data'    => admin_url("admin.php?page=create-course&course_id={$course_id}"),
        ]));
    }

    /**
     * Intercept Tutor LMS's AJAX handler for creating new course bundles.
     * Prevents duplicate bundle creation when redirecting to Gutenberg.
     */
    public static function intercept_tutor_create_course_bundle() {
        // Verify this is the expected AJAX request
        if (!isset($_POST['action']) || $_POST['action'] !== 'tutor_create_course_bundle') {
            return;
        }

        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            wp_die(json_encode(['success' => false, 'message' => 'Insufficient permissions']), 403);
        }

        // Only process backend requests (frontend requests handled by class-frontend-customizations.php)
        $source = isset($_POST['source']) ? $_POST['source'] : '';
        if ($source === 'backend') {
            // Create a new course bundle and return the Gutenberg edit URL
            $bundle_id = wp_insert_post([
                'post_type' => 'course-bundle',
                'post_status' => 'draft',
                'post_title' => __('New Course Bundle', 'tutorpress'),
            ]);

            if (!is_wp_error($bundle_id)) {
                wp_die(json_encode([
                    'status_code' => 200,
                    'data' => admin_url("post.php?post={$bundle_id}&action=edit")
                ]));
            }
        }

        // Let the default handler run for other cases
        return;
    }
}

// Class will be initialized by main orchestrator
