<?php
/**
 * Handles script and style enqueuing for TutorPress.
 */

defined('ABSPATH') || exit;

class TutorPress_Scripts {

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_common_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_lesson_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_script_data']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_gutenberg_scripts']);
    }

    /**
     * Enqueue JavaScript that runs on both lesson pages and the Tutor LMS dashboard.
     */
    public static function enqueue_common_assets() {
        $options = get_option('tutorpress_settings', []);
        
        // Conditionally load override-tutorlms.js
        if (!empty($options['enable_sidebar_tabs']) || !empty($options['enable_dashboard_redirects'])) {
            wp_enqueue_script(
                'tutorpress-override-tutorlms',
                TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
                ['jquery'],
                filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
                true
            );
        }
    }

    /**
     * Enqueue CSS and JavaScript for lesson sidebar and wpDiscuz integration.
     */
    public static function enqueue_lesson_assets() {
        if (!is_singular('lesson')) {
            return;
        }
        
        $options = get_option('tutorpress_settings', []);
        if (empty($options['enable_sidebar_tabs'])) {
            return;
        }

        wp_enqueue_style(
            'tutorpress-comments-style',
            TUTORPRESS_URL . 'assets/css/tutor-comments.css',
            [],
            filemtime(TUTORPRESS_PATH . 'assets/css/tutor-comments.css'),
            'all'
        );

        wp_enqueue_script(
            'tutorpress-sidebar-tabs',
            TUTORPRESS_URL . 'assets/js/sidebar-tabs.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/sidebar-tabs.js'),
            true
        );
    }

    /**
     * Enqueue JavaScript for the Tutor LMS frontend dashboard.
     */
    public static function enqueue_dashboard_assets() {
        if (!is_page('dashboard')) { // Ensure we are on the Tutor LMS dashboard
            return;
        }

        $options = get_option('tutorpress_settings', []);
        if (!empty($options['enable_dashboard_redirects'])) {
            wp_enqueue_script(
                'tutorpress-override-tutorlms',
                TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
                ['jquery'],
                filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
                true
            );
        }
    }

    /**
     * Localize script data to pass settings to JavaScript.
     */
    public static function localize_script_data() {
        $options = get_option('tutorpress_settings', []);
        
        wp_localize_script('tutorpress-override-tutorlms', 'TutorPressData', [
            'enableSidebarTabs' => !empty($options['enable_sidebar_tabs']),
            'enableDashboardRedirects' => !empty($options['enable_dashboard_redirects']),
            'adminUrl' => admin_url(),
        ]);
    }

  /**
   * Find the correct chunk filename based on its prefix.
   */
  private static function find_lazy_chunk($prefix) {
      $lazy_chunks_path = WP_PLUGIN_DIR . '/tutor/assets/js/lazy-chunks/';
      $lazy_chunks_url = plugins_url('assets/js/lazy-chunks/', 'tutor/tutor.php');
  
      if (!is_dir($lazy_chunks_path)) {
          return null;
      }
  
      $files = scandir($lazy_chunks_path);
      foreach ($files as $file) {
          if (strpos($file, $prefix . '.') === 0 && str_ends_with($file, '.min.js')) {
              return $lazy_chunks_url . $file;
          }
      }
      return null;
  }
  
    /**
     * Enqueue scripts required for the Gutenberg editor.
     */
    public static function enqueue_gutenberg_scripts($hook) {
        global $post;

        if (!isset($post)) {
            return;
        }

        // Ensure we are in the Gutenberg editor
        if (!is_admin() || !function_exists('get_current_screen')) {
            return;
        }
        $screen = get_current_screen();
        if (!$screen || $screen->base !== 'post') {
            return;
        }

        // Only enqueue on Course & Lesson Edit Pages in Gutenberg
        if ($hook === 'post.php' && in_array($post->post_type, ['courses', 'lessons'])) {
            
            // Get Tutor LMS plugin URL
            if (!defined('TUTOR_VERSION')) {
                return; // Exit if Tutor LMS is not active
            }
            
            $tutor_plugin_url = plugins_url('assets/js/', 'tutor/tutor.php');
            $tutorpress_plugin_url = plugins_url('assets/', dirname(__FILE__));

            // **Enqueue Webpack Runtime First**
            wp_enqueue_script(
                'tutor-webpack-runtime',
                $tutor_plugin_url . 'tutor-addon-list.min.js',
                [],
                null,
                true
            );

            // **Enqueue Tutor LMS Scripts**
            wp_enqueue_script(
                'tutor-course-builder',
                $tutor_plugin_url . 'tutor-course-builder.min.js',
                ['wp-element', 'wp-components', 'tutor-webpack-runtime'], // Ensure dependency order
                null,
                true
            );

            // **Find and enqueue the correct 226.js chunk dynamically**
            $lazy_chunk_226 = self::find_lazy_chunk('226');
            if ($lazy_chunk_226) {
                wp_enqueue_script(
                    'tutor-lazy-chunk-226',
                    $lazy_chunk_226,
                    ['tutor-webpack-runtime'],
                    null,
                    true
                );
            }

            // **Find and enqueue the correct 979.js chunk dynamically**
            $lazy_chunk_979 = self::find_lazy_chunk('979');
            if ($lazy_chunk_979) {
                wp_enqueue_script(
                    'tutor-lazy-chunk-979',
                    $lazy_chunk_979,
                    ['tutor-webpack-runtime'],
                    null,
                    true
                );
            }

            // **Enqueue TutorPress-Specific Scripts**
            wp_enqueue_script(
                'curriculum-metabox',
                $tutorpress_plugin_url . 'js/curriculum-metabox.js',
                ['wp-element', 'wp-components', 'tutor-webpack-runtime'],
                null,
                true
            );

            wp_enqueue_script(
                'gutenberg-sidebar',
                $tutorpress_plugin_url . 'js/gutenberg-sidebar.js',
                ['wp-element', 'wp-components', 'tutor-webpack-runtime'],
                null,
                true
            );

            wp_enqueue_script(
                'additional-metabox',
                $tutorpress_plugin_url . 'js/additional-metabox.js',
                ['wp-element', 'wp-components', 'tutor-webpack-runtime'],
                null,
                true
            );

            wp_enqueue_script(
                'certificate-metabox',
                $tutorpress_plugin_url . 'js/certificate-metabox.js',
                ['wp-element', 'wp-components', 'tutor-webpack-runtime'],
                null,
                true
            );

            // **Enqueue Styles**
            wp_enqueue_style(
                'gutenberg-sidebar-style',
                $tutorpress_plugin_url . 'css/gutenberg-sidebar.css',
                [],
                null
            );

            wp_enqueue_style(
                'metaboxes-style',
                $tutorpress_plugin_url . 'css/metaboxes.css',
                [],
                null
            );
        }
    }   
  
}

// Initialize the class
TutorPress_Scripts::init();
