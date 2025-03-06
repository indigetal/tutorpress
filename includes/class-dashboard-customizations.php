<?php
/**
 * Adds extra menu items and customizations to the Tutor LMS Instructor Dashboard.
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_Dashboard_Customizations {

    public static function init() {
        $options = get_option('tutorpress_settings', []);
        
        if (!isset($options['enable_extra_dashboard_links']) || !$options['enable_extra_dashboard_links']) {
            return;
        }
        
        add_filter('tutor_dashboard/instructor_nav_items', [__CLASS__, 'add_extra_dashboard_links']);
        
        if (!isset($options['enable_dashboard_redirects']) || !$options['enable_dashboard_redirects']) {
            return;
        }
        
        add_filter('tutor_dashboard_url', [__CLASS__, 'override_dashboard_edit_buttons'], 10, 2);
    }

    public static function add_extra_dashboard_links($nav_items) {
        $extra_links = [
            [
                'title' => __('Media Library', 'tutorpress'),
                'url' => admin_url('upload.php'),
                'icon' => 'bb-icon-image-video'
            ],
            [
                'title' => __('Interactive Content', 'tutorpress'),
                'url' => admin_url('admin.php?page=h5p'),
                'icon' => 'bb-icon-file-presentation'
            ],
        ];

        return array_merge($nav_items, $extra_links);
    }

    // Redirect "Edit Course" button icons in Dashboard to Gutenberg
    public static function override_dashboard_edit_buttons($url, $sub_url) {
        if (strpos($sub_url, 'create-course?course_id=') !== false) {
            parse_str(parse_url($sub_url, PHP_URL_QUERY), $query);
            if (isset($query['course_id'])) {
                return admin_url('post.php?post=' . intval($query['course_id']) . '&action=edit');
            }
        }
        return $url;
    }
    
}

// Initialize the class
TutorPress_Dashboard_Customizations::init();
