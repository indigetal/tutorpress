<?php
/**
 * Handles the tabbed sidebar navigation for Tutor LMS lessons and removes default Tutor LMS comment templates.
 */

defined('ABSPATH') || exit;

class TutorPress_Sidebar_Tabs {
    public static function init() {
        // Check if the feature is enabled in the settings
        $options = get_option('tutorpress_settings', []);
        if (!isset($options['enable_sidebar_tabs']) || !$options['enable_sidebar_tabs']) {
            return;
        }
        
        add_filter('tutor_lesson/single/lesson_sidebar', [__CLASS__, 'modify_sidebar']);
        add_filter('tutor_get_template', [__CLASS__, 'block_tutor_comments_templates'], 10, 2);
    }

    /**
     * Modifies the Tutor LMS lesson sidebar to include a tabbed navigation system.
     *
     * @param string $sidebar_content The existing sidebar content.
     * @return string Modified sidebar content with tabbed navigation.
     */
    public static function modify_sidebar($sidebar_content) {
        ob_start();
        ?>
        <div class="tutorpress-sidebar-tabs">
            <div class="tutor-sidebar-close-mobile">
                <button class="tutor-hide-course-single-sidebar tutor-iconic-btn">×</button>
            </div>
            <ul class="tutorpress-tabs">
                <li class="tutorpress-tab active" data-tab="course-content">Course Content</li>
                <li class="tutorpress-tab" data-tab="discussion">Discussion</li>
            </ul>
            <div class="tutorpress-tab-content" id="course-content">
                <?php echo $sidebar_content; ?>
            </div>
            <div class="tutorpress-tab-content" id="discussion" style="display: none;">
                <?php comments_template(); ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Prevent Tutor LMS from loading its custom comment templates.
     *
     * @param string $template The current template file.
     * @param string $template_name The template being requested.
     * @return string Modified template file (empty if blocked).
     */
    public static function block_tutor_comments_templates($template, $template_name) {
        if ($template_name === 'single.lesson.comment' || $template_name === 'single.lesson.comments-loop') {
            return ''; // Prevent Tutor LMS from loading custom comment templates
        }
        return $template;
    }
}

// Initialize the class
TutorPress_Sidebar_Tabs::init();
