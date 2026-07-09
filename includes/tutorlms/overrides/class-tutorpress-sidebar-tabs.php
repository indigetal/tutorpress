<?php
/**
 * Handles the tabbed sidebar navigation for Tutor LMS lessons and removes default Tutor LMS comment templates.
 */

defined('ABSPATH') || exit;

class TutorPress_Sidebar_Tabs {
    private const DISCUSSION_TEMPLATE = 'tutorpress.learning-area.lesson.discussion';
    private const DISCUSSION_TEMPLATE_PATH = 'tutorpress/learning-area/lesson/discussion';
    private const TUTOR_COMMENTS_TEMPLATES = [
        'single.lesson.parts.comments',
        'single.lesson.comment',
        'single.lesson.comments-loop',
        'learning-area.lesson.comments',
    ];

    public static function init() {
        // Check if the feature is enabled in the settings (use Freemius-aware wrapper)
        $options = get_option('tutorpress_settings', []);
        // Use Freemius-aware wrapper; default to disabled when option is missing
        $enabled = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_sidebar_tabs', false) : (!empty($options['enable_sidebar_tabs']));
        if (!$enabled) {
            return;
        }

        add_filter('tutor_lesson/single/lesson_sidebar', [__CLASS__, 'modify_sidebar']);
        add_filter('tutor_lesson_single_nav_items', [__CLASS__, 'filter_nav_items']);
        add_filter('tutor_lesson_single_nav_contents', [__CLASS__, 'filter_nav_contents']);
        add_filter('should_tutor_load_template', [__CLASS__, 'should_load_tutor_template'], 10, 3);
        add_filter('tutor_get_template_path', [__CLASS__, 'filter_template_path'], 10, 2);
        add_filter('tutor_not_found_template_warning_msg', [__CLASS__, 'suppress_discussion_template_warning']);
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
     * Gets Tutor LMS's configured lesson learning mode.
     *
     * @return string Tutor LMS learning mode.
     */
    private static function get_learning_mode() {
        if (function_exists('tutor_utils') && is_object(tutor_utils()) && method_exists(tutor_utils(), 'get_option')) {
            return (string) tutor_utils()->get_option('learning_mode');
        }

        return '';
    }

    /**
     * Checks whether Tutor LMS is using a learning-area mode.
     *
     * @return bool True when modern or kids learning mode is active.
     */
    private static function is_learning_area_mode() {
        return in_array(self::get_learning_mode(), ['modern', 'kids'], true);
    }

    /**
     * Filters Tutor LMS lesson nav items.
     *
     * @param array $nav_items Tutor LMS lesson nav items.
     * @return array Filtered nav items.
     */
    public static function filter_nav_items($nav_items) {
        foreach ($nav_items as $key => $item) {
            if (!is_array($item)) {
                continue;
            }

            $template = isset($item['template']) ? $item['template'] : '';
            $is_comments_item = $key === 'comments'
                || (isset($item['id']) && $item['id'] === 'comments')
                || (isset($item['value']) && $item['value'] === 'comments')
                || in_array($template, self::TUTOR_COMMENTS_TEMPLATES, true);

            if (!$is_comments_item) {
                continue;
            }

            if (self::is_learning_area_mode()) {
                $nav_items[$key] = array_merge($item, [
                    'id'       => 'comments',
                    'label'    => __('Discussion', 'tutorpress'),
                    'template' => self::DISCUSSION_TEMPLATE,
                ]);
                continue;
            }

            unset($nav_items[$key]);
        }

        return $nav_items;
    }

    /**
     * Filters Tutor LMS lesson nav contents.
     *
     * @param array $nav_contents Tutor LMS lesson nav contents.
     * @return array Filtered nav contents.
     */
    public static function filter_nav_contents($nav_contents) {
        foreach ($nav_contents as $key => $content) {
            if (!is_array($content)) {
                continue;
            }

            $template_path = isset($content['template_path']) ? $content['template_path'] : '';
            $is_comments_content = $key === 'comments'
                || (isset($content['value']) && $content['value'] === 'comments')
                || $template_path === 'single.lesson.parts.comments';

            if ($is_comments_content) {
                unset($nav_contents[$key]);
            }
        }

        return $nav_contents;
    }

    /**
     * Determines whether Tutor LMS should load a requested template.
     *
     * @param bool   $should_load  Whether Tutor LMS should load the template.
     * @param string $template     Template slug being requested.
     * @param array  $variables    Template variables.
     * @return bool Whether Tutor LMS should load the template.
     */
    public static function should_load_tutor_template($should_load, $template, $variables) {
        if (in_array($template, self::TUTOR_COMMENTS_TEMPLATES, true)) {
            return false;
        }

        return $should_load;
    }

    /**
     * Filters Tutor LMS resolved template paths.
     *
     * @param string $template_path Resolved template path.
     * @param string $template      Template slug being requested.
     * @return string Filtered template path.
     */
    public static function filter_template_path($template_path, $template) {
        if (!in_array($template, [self::DISCUSSION_TEMPLATE, self::DISCUSSION_TEMPLATE_PATH], true)) {
            return $template_path;
        }

        $plugin_path = defined('TUTORPRESS_PATH') ? TUTORPRESS_PATH : dirname(__DIR__, 3) . '/';

        return $plugin_path . 'templates/tutorpress/learning-area/lesson/discussion.php';
    }

    /**
     * Suppresses Tutor's premature missing-template warning for the routed TutorPress template.
     *
     * @param string $warning_msg Tutor LMS missing-template warning.
     * @return string Filtered warning.
     */
    public static function suppress_discussion_template_warning($warning_msg) {
        if (strpos($warning_msg, self::DISCUSSION_TEMPLATE_PATH . '.php') !== false) {
            return '';
        }

        return $warning_msg;
    }
}

// Class will be initialized by main orchestrator
