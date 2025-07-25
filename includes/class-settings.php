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

        // Register toggle settings
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
        
        // Register checkbox settings for template hierarchy
        add_settings_field(
            'template_overrides',
            __('Use WordPress Template Hierarchy for Tutor LMS Templates', 'tutorpress'),
            [__CLASS__, 'render_template_override_checkboxes'],
            'tutorpress-settings',
            'tutorpress_main_section'
        );
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
        
        // Sanitize toggle settings
        foreach ($defined_settings as $key => $setting) {
            if (isset($input[$key]) && $input[$key] === '1') {
                $sanitized[$key] = '1';
            }
        }
        
        // Sanitize template override checkboxes
        if (isset($input['template_overrides']) && is_array($input['template_overrides'])) {
            $sanitized['template_overrides'] = [];
            // For now, only check for course_archive
            if (isset($input['template_overrides']['course_archive']) && '1' === $input['template_overrides']['course_archive']) {
                $sanitized['template_overrides']['course_archive'] = '1';
            }
        }

        return $sanitized;
    }

    /**
     * Get the defined settings configuration for toggles
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
            'remove_frontend_builder_button' => [
                'label' => __('Remove Button to Frontend Builder in Course Editor', 'tutorpress'),
                'helper' => ''
            ],
            'enable_dashboard_redirects' => [
                'label' => __('Redirect Frontend Dashboard Editing to Gutenberg', 'tutorpress'),
                'helper' => ''
            ],
            'enable_extra_dashboard_links' => [
                'label' => __('Add Media Library & H5P Links to Instructor Dashboard', 'tutorpress'),
                'helper' => ''
            ],
        ];
    }

    public static function render_toggle($args) {
        $options = get_option('tutorpress_settings', []);
        $checked = isset($options[$args['key']]) && $options[$args['key']] === '1' ? 'checked' : '';
        echo "<label class='tutorpress-switch'>
                <input type='checkbox' name='tutorpress_settings[{$args['key']}]' value='1' $checked />
                <span class='tutorpress-slider'></span>
              </label>";
        if (!empty($args['helper'])) {
            echo "<p class='description' style='max-width: 600px; margin-top: 0;'>{$args['helper']}</p>";
        }
    }
    
    public static function render_template_override_checkboxes() {
        $options = get_option('tutorpress_settings', []);
        $template_overrides = isset($options['template_overrides']) ? $options['template_overrides'] : [];
        
        $course_archive_checked = isset($template_overrides['course_archive']) && '1' === $template_overrides['course_archive'] ? 'checked' : '';
        ?>
        <div class="tutorpress-checkbox-group">
            <label>
                <input type="checkbox" name="tutorpress_settings[template_overrides][course_archive]" value="1" <?php echo $course_archive_checked; ?> />
                <?php _e('Course Archive', 'tutorpress'); ?>
            </label>
            <!-- Future checkboxes can be added here -->
        </div>
        <?php
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
                .tutorpress-checkbox-group label {
                    display: inline-block;
                    margin-right: 20px;
                }
            </style>
        </div>
        <?php
    }
}

TutorPress_Settings::init();
