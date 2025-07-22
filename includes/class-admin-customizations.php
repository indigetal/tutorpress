<?php
/**
 * Handles custom admin menu and redirections for TutorPress.
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_Admin_Customizations {

    public static function init() {
        $options = get_option('tutorpress_settings', []);
        
        if (!isset($options['enable_admin_redirects']) || !$options['enable_admin_redirects']) {
            return;
        }
        
        add_action('admin_menu', [__CLASS__, 'add_lessons_menu_item']);
        add_action('admin_menu', [__CLASS__, 'reorder_tutor_submenus'], 100);
        add_action('tutor_admin_after_course_list_action', [__CLASS__, 'override_course_edit_redirect']);
        add_action('admin_init', [__CLASS__, 'override_add_new_redirect']);
        add_action('init', [__CLASS__, 'conditionally_hide_builder_button']);
    }

    /**
     * Conditionally hides the "Edit with Course Builder" button via CSS.
     */
    public static function conditionally_hide_builder_button() {
        $options = get_option('tutorpress_settings', []);
        
        if (!empty($options['remove_frontend_builder_button']) && '1' === $options['remove_frontend_builder_button']) {
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
        
        if (empty($options['remove_frontend_builder_button']) || '1' !== $options['remove_frontend_builder_button']) {
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
     * Redirect "Edit" links in the Courses backend page to open Gutenberg.
     */
    public static function override_course_edit_redirect() {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                document.querySelectorAll(".tutor-dropdown-item").forEach(item => {
                    let href = item.getAttribute("href");
                    if (href && href.includes("admin.php?page=create-course&course_id=")) {
                        let courseId = href.split("course_id=")[1].split("#")[0];
                        item.setAttribute("href", "post.php?post=" + courseId + "&action=edit");
                    }
                });
            });
        </script>';
    }

    /**
     * Redirect "Add New" button in Courses backend page to Gutenberg.
     */
    public static function override_add_new_redirect() {
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'create-course') {
            wp_safe_redirect(admin_url('post-new.php?post_type=courses'));
            exit;
        }
    }

}

// Initialize the class
TutorPress_Admin_Customizations::init();
