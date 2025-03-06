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
        register_setting('tutorpress_settings_group', 'tutorpress_settings');

        add_settings_section('tutorpress_main_section', __('Enable or Disable Features', 'tutorpress'), null, 'tutorpress-settings');

        $settings = [
            'enable_sidebar_tabs' => __('Enable Sidebar Tabs in Lessons', 'tutorpress'),
            'enable_admin_redirects' => __('Redirect Backend Course Editing to Gutenberg', 'tutorpress'),
            'enable_dashboard_redirects' => __('Redirect Frontend Dashboard Editing to Gutenberg', 'tutorpress'),
            'enable_template_loader' => __('Use WordPress Template Hierarchy for Course Archives', 'tutorpress'),
            'enable_extra_dashboard_links' => __('Add Media Library & H5P Links to Instructor Dashboard', 'tutorpress')
        ];

        foreach ($settings as $key => $label) {
            add_settings_field(
                $key,
                $label,
                [__CLASS__, 'render_toggle'],
                'tutorpress-settings',
                'tutorpress_main_section',
                ['key' => $key]
            );
        }
    }

    public static function render_toggle($args) {
        $options = get_option('tutorpress_settings', []);
        $checked = isset($options[$args['key']]) ? 'checked' : '';
        echo "<label class='tutorpress-switch'>
                <input type='checkbox' name='tutorpress_settings[{$args['key']}]' value='1' $checked />
                <span class='tutorpress-slider'></span>
              </label>";
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
