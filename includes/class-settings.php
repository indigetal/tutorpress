<?php
/**
 * Admin Settings Page for TutorPress
 */

defined('ABSPATH') || exit;

class TutorPress_Settings {
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_settings_page']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
    }

    public static function add_settings_page() {
        add_submenu_page(
            'tutor', // Parent menu (Tutor LMS)
            __('TutorPress', 'tutorpress'),
            __('TutorPress', 'tutorpress'),
            'manage_options',
            'tutorpress-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings() {
        register_setting(
            'tutorpress_settings_group', 
            'tutorpress_settings',
            [
                'sanitize_callback' => [__CLASS__, 'sanitize_settings']
            ]
        );

        add_settings_section('tutorpress_main_section', __('Enable or Disable Features', 'tutorpress'), null, 'tutorpress-settings');

        $settings = self::get_defined_settings();

        foreach ($settings as $key => $setting) {
            add_settings_field(
                $key,
                $setting['label'],
                [__CLASS__, 'render_toggle'],
                'tutorpress-settings',
                'tutorpress_main_section',
                ['key' => $key, 'helper' => $setting['helper']]
            );
        }
    }

    /**
     * Sanitize settings callback for proper checkbox handling
     * 
     * @param array $input The raw input from the form
     * @return array The sanitized settings array
     */
    public static function sanitize_settings($input) {
        $sanitized = [];
        $defined_settings = self::get_defined_settings();
        
        // Only process defined settings
        foreach ($defined_settings as $key => $setting) {
            // Checkboxes: store '1' if checked, omit if unchecked
            if (isset($input[$key]) && $input[$key] === '1') {
                $sanitized[$key] = '1';
            }
            // Note: unchecked checkboxes are not sent in the form data,
            // so we don't need to explicitly set them to false/empty
        }
        
        return $sanitized;
    }

    /**
     * Get the defined settings configuration
     * 
     * @return array Array of setting configurations
     */
    private static function get_defined_settings() {
        return [
            'enable_sidebar_tabs' => [
                'label' => __('Enable Sidebar Tabs in Lessons', 'tutorpress'),
                'helper' => ''
            ],
            'enable_admin_redirects' => [
                'label' => __('Redirect Backend Course Editing to Gutenberg', 'tutorpress'),
                'helper' => ''
            ],
            'enable_dashboard_redirects' => [
                'label' => __('Redirect Frontend Dashboard Editing to Gutenberg', 'tutorpress'),
                'helper' => ''
            ],
            'enable_template_loader' => [
                'label' => __('Use WordPress Template Hierarchy for Course Archives', 'tutorpress'),
                'helper' => ''
            ],
            'enable_extra_dashboard_links' => [
                'label' => __('Add Media Library & H5P Links to Instructor Dashboard', 'tutorpress'),
                'helper' => ''
            ],
            'disable_frontend_course_builder' => [
                'label' => __('Disable Tutor LMS Frontend Course Builder', 'tutorpress'),
                'helper' => __('Significantly reduces resource usage on your site and ensures that all course editing is handled exclusively in the Gutenberg editor. Instructors will no longer be able to access the Tutor LMS course builder, preventing accidental changes that could affect existing Gutenberg blocks in lessons and assignments.', 'tutorpress')
            ],
        ];
    }

    public static function render_toggle($args) {
        $options = get_option('tutorpress_settings', []);
        // Check for '1' as the stored value (proper checkbox handling)
        $checked = isset($options[$args['key']]) && $options[$args['key']] === '1' ? 'checked' : '';
        echo "<label class='tutorpress-switch'>
                <input type='checkbox' name='tutorpress_settings[{$args['key']}]' value='1' $checked />
                <span class='tutorpress-slider'></span>
              </label>";
        if (!empty($args['helper'])) {
            echo "<p class='description' style='max-width: 600px; margin-top: 0;'>{$args['helper']}</p>";
        }
    }

    public static function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('TutorPress', 'tutorpress'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('tutorpress_settings_group');
                do_settings_sections('tutorpress-settings');
                submit_button();
                ?>
            </form>
            <style>
                .tutorpress-switch {
                    position: relative;
                    display: inline-block;
                    width: 34px;
                    height: 20px;
                }
                .tutorpress-switch input {display: none;}
                .tutorpress-slider {
                    position: absolute;
                    cursor: pointer;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background-color: #ccc;
                    transition: .4s;
                    border-radius: 20px;
                }
                .tutorpress-slider:before {
                    position: absolute;
                    content: "";
                    height: 14px;
                    width: 14px;
                    left: 3px;
                    bottom: 3px;
                    background-color: white;
                    transition: .4s;
                    border-radius: 50%;
                }
                input:checked + .tutorpress-slider {
                    background-color: #2196F3;
                }
                input:checked + .tutorpress-slider:before {
                    transform: translateX(14px);
                }
            </style>
        </div>
        <?php
    }
}

TutorPress_Settings::init();
