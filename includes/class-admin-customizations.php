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
        
        // Only add redirects and interception if admin redirects are enabled
        if (!empty($options['enable_admin_redirects']) && $options['enable_admin_redirects']) {
            add_action('tutor_admin_after_course_list_action', [__CLASS__, 'override_course_edit_redirect']);
            // Intercept course creation via AJAX, create draft and redirect to Gutenberg
            add_action('wp_ajax_tutor_create_new_draft_course', [__CLASS__, 'intercept_tutor_create_course'], 0);
            // Intercept Tutor LMS's AJAX handler for creating new course bundles
            add_action('wp_ajax_tutor_create_course_bundle', [__CLASS__, 'intercept_tutor_create_course_bundle'], 0);
            // Add PHP redirect as fallback for direct page loads
            add_action('admin_init', [__CLASS__, 'override_add_new_redirect']);
        }
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
     * Redirect "Edit" links and "Add New" buttons in the Courses and Bundles backend page to open Gutenberg.
     */
    public static function override_course_edit_redirect() {
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                // Override edit links for Courses and Bundles
                document.querySelectorAll(".tutor-dropdown-item").forEach(item => {
                    var href = item.getAttribute("href");
                    // Course edit link override
                    if (href && href.includes("admin.php?page=create-course&course_id=")) {
                        var courseId = href.split("course_id=")[1].split("#")[0];
                        item.setAttribute("href", "post.php?post=" + courseId + "&action=edit");
                    }
                    // Bundle edit link override
                    if (href && href.includes("admin.php?page=course-bundle&action=edit&id=")) {
                        var bundleId = href.split("id=")[1].split("#")[0];
                        item.setAttribute("href", "post.php?post=" + bundleId + "&action=edit");
                    }
                });

                // Intercept "New Course" button: create draft via AJAX and redirect to Gutenberg
                var newCourseBtn = document.querySelector("a.tutor-create-new-course, button.tutor-create-new-course");
                if (newCourseBtn) {
                    // Clone the button without event listeners
                    var clonedBtn = newCourseBtn.cloneNode(true);
                    // Remove Tutors class to prevent their handler
                    clonedBtn.classList.remove("tutor-create-new-course");
                    clonedBtn.setAttribute("href", "#");
                    // Add our click handler
                    clonedBtn.onclick = function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        this.onclick = null;
                        jQuery.post(ajaxurl, { 
                            action: "tutor_create_new_draft_course",
                            source: "backend",
                            _wpnonce: window._tutorobject.nonce
                        })
                        .done(function(response) {
                            var data = (typeof response === "string") ? JSON.parse(response) : response;
                            if (data && data.data && typeof data.data === "string") {
                                // Instead of going to create-course page, go to Gutenberg
                                var urlParams = new URLSearchParams(data.data.split("?")[1]);
                                var courseId = urlParams.get("course_id");
                                if (courseId) {
                                    window.location.href = "post.php?post=" + courseId + "&action=edit";
                                } else {
                                    alert("Could not extract course ID from response");
                                }
                            } else {
                                alert("Course Creation Failed " + JSON.stringify(data));
                            }
                        })
                        .fail(function(xhr) {
                            alert("Course Creation Failed: " + xhr.responseText);
                        });
                    };
                    // Replace the original button with our clone
                    newCourseBtn.parentNode.replaceChild(clonedBtn, newCourseBtn);
                }
            });
        </script>';
    }

    /**
     * Intercept Tutor LMS's AJAX handler for creating new courses.
     * Prevents duplicate course creation when redirecting to Gutenberg.
     */
    public static function intercept_tutor_create_course() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'tutor_create_new_draft_course') {
            return;
        }
        if (!current_user_can('edit_courses')) {
            wp_die(json_encode(['success' => false, 'message' => 'Insufficient permissions']), 403);
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
        if (!current_user_can('edit_courses')) {
            wp_die(json_encode(['success' => false, 'message' => 'Insufficient permissions']), 403);
        }

        // Check if this is from backend source and we should redirect
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

    /**
     * Redirect "Add New" button in Courses backend page to Gutenberg.
     * This serves as a fallback for direct page loads.
     */
    public static function override_add_new_redirect() {
        if (is_admin() && isset($_GET['page'])) {
            // Course "Add New" override
            if ($_GET['page'] === 'create-course') {
                wp_safe_redirect(admin_url('post-new.php?post_type=courses'));
                exit;
            }
        }
    }
}

// Initialize the class
TutorPress_Admin_Customizations::init();
