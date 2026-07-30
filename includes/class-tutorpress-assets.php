<?php
/**
 * Handles script and style enqueuing for TutorPress.
 *
 * @package TutorPress
 * @since 0.1.0
 */

defined('ABSPATH') || exit;

class TutorPress_Assets {

    /**
     * Lowest Tutor LMS version TutorPress claims compatibility with.
     *
     * @since 0.1.0
     * @var string
     */
    const TUTOR_SUPPORTED_FLOOR = '3.9.15';

    /**
     * Tutor 4.0 question-type slugs that require modern/kids mode and Tutor Pro.
     *
     * @since 0.1.0
     * @var string[]
     */
    const TUTOR_NATIVE_QUESTION_TYPES = ['draw_image', 'scale', 'pin_image', 'coordinates', 'puzzle'];

    /**
     * Initialize the class.
     *
     * @since 0.1.0
     * @return void
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_common_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_lesson_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_dashboard_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'localize_script_data']);
        
        // Add course selling option to Tutor LMS's course details response
        add_filter('tutor_course_details_response', [__CLASS__, 'add_course_selling_option_to_response']);
        
        // Sync data from Tutor LMS frontend to TutorPress
        add_action('save_post', [__CLASS__, 'sync_from_tutor_lms'], 10, 3);

		// Hook into Tutor LMS's course update process to handle selling_option
		add_action('tutor_after_prepare_update_post_meta', [__CLASS__, 'save_course_selling_option_from_tutor_update'], 10, 2);
        
        // Override Tutor Pro H5P addon filtering for frontend display
        add_action('init', [TutorPress_Addon_Checker::class, 'override_h5p_addon_filtering'], 100);
    }

    /**
     * Enqueue JavaScript that runs on both lesson pages and the Tutor LMS dashboard.
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_common_assets() {
        // Localize addon checker data for frontend use
        wp_localize_script('jquery', 'tutorpressAddonChecker', 
            TutorPress_Addon_Checker::get_comprehensive_status()
        );
        
        // Also localize to window.tutorpress for compatibility
        wp_localize_script('jquery', 'tutorpress', [
            'addonChecker' => TutorPress_Addon_Checker::get_comprehensive_status()
        ]);
    }

    /**
     * Enqueue JavaScript for the Tutor LMS frontend dashboard.
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_dashboard_assets() {
        // Only load if setting is enabled (use wrapper to respect Freemius gating)
        $options = get_option('tutorpress_settings', []);
        $enabled = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_dashboard_redirects', false) : (!empty($options['enable_dashboard_redirects']));
        if (!$enabled) {
            return;
        }

        // Enqueue the standalone override script for frontend "New Course" button
        wp_enqueue_script(
            'tutorpress-override-tutorlms',
            TUTORPRESS_URL . 'assets/js/override-tutorlms.js',
            ['jquery'],
            filemtime(TUTORPRESS_PATH . 'assets/js/override-tutorlms.js'),
            true
        );

        // Add TutorPressData for overrides
        $enabledExtraLinks = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_extra_dashboard_links', false) : (!empty($options['enable_extra_dashboard_links']));
        wp_localize_script('tutorpress-override-tutorlms', 'TutorPressData', [
            'enableDashboardRedirects' => $enabled,
            'enableExtraDashboardLinks' => $enabledExtraLinks,
            'adminUrl' => admin_url(),
        ]);
    }

    /**
     * Enqueue CSS and JavaScript for lesson sidebar and wpDiscuz integration.
     *
     * @since 0.1.0
     * @return void
     */
    public static function enqueue_lesson_assets() {
        if (!is_singular('lesson')) {
            return;
        }
        
        $options = get_option('tutorpress_settings', []);
        // Use Freemius-aware wrapper to decide if sidebar tabs should be enabled
        $enabledSidebar = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_sidebar_tabs', false) : (!empty($options['enable_sidebar_tabs']));
        if (!$enabledSidebar) {
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

        // Localize a small object specifically for sidebar tabs to avoid coupling with other flags
        wp_localize_script('tutorpress-sidebar-tabs', 'TutorPressSidebar', [
            'enableSidebarTabs' => $enabledSidebar,
        ]);

        // Late dequeue after lesson content may set Step 5 adaptation success (before core prints footer scripts at 20).
        if (!has_action('wp_footer', [__CLASS__, 'maybe_dequeue_legacy_sidebar_tabs_script'])) {
            add_action('wp_footer', [__CLASS__, 'maybe_dequeue_legacy_sidebar_tabs_script'], 19);
        }
    }

    /**
     * Dequeue legacy sidebar-wrapper JS only after successful Tutor 4.0 modern/kids discussion adaptation.
     *
     * Runs on wp_footer before wp_print_footer_scripts (priority 20). Discussion CSS is never suppressed.
     *
     * @return void
     */
    public static function maybe_dequeue_legacy_sidebar_tabs_script() {
        if (
            !class_exists('TutorPress_Sidebar_Tabs')
            || !method_exists('TutorPress_Sidebar_Tabs', 'did_discussion_adaptation_succeed')
        ) {
            return;
        }

        if (!TutorPress_Sidebar_Tabs::did_discussion_adaptation_succeed()) {
            return;
        }

        wp_dequeue_script('tutorpress-sidebar-tabs');
    }

    /**
     * Enqueue admin-specific assets.
     *
     * @since 0.1.0
     * @param string $hook_suffix The current admin page.
     * @return void
     */
    public static function enqueue_admin_assets($hook_suffix) {
        if (!in_array($hook_suffix, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (!$screen || !in_array($screen->post_type, ['courses', 'lesson', 'tutor_assignments', 'course-bundle'], true)) {
            return;
        }

        // Get the asset file for dependencies and version
        $asset_file = include TUTORPRESS_PATH . 'assets/js/build/index.asset.php';

        // Enqueue the bundled CSS (generated by webpack)
        wp_enqueue_style(
            'tutorpress-gutenberg',
            TUTORPRESS_URL . 'assets/js/build/index.css',
            ['wp-components'],
            $asset_file['version'],
            'all'
        );

        // Enqueue the built admin script
        wp_enqueue_script(
            'tutorpress-curriculum-metabox',
            TUTORPRESS_URL . 'assets/js/build/index.js',
            array_merge(['jquery', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-plugins', 'wp-edit-post', 'wp-i18n'], $asset_file['dependencies']),
            $asset_file['version'],
            true
        );

        // Get settings for localization
        $options = get_option('tutorpress_settings', []);

        $tutor_nonce = '';
        $youtube_api_key_exists = false;
        if (function_exists('tutor')) {
            $tutor_options = get_option('tutor_option', []);
            $youtube_api_key_exists = is_array($tutor_options) && !empty($tutor_options['lesson_video_duration_youtube_api_key']);

            $tutor_instance = tutor();
            if (is_object($tutor_instance)) {
                $tutor_nonce_action = $tutor_instance->nonce_action;
                if (is_string($tutor_nonce_action) && '' !== $tutor_nonce_action) {
                    $tutor_nonce = wp_create_nonce($tutor_nonce_action);
                }
            }
        }

        // Add TutorPressData for overrides (use wrapper to respect Freemius gating)
        $dashboard_enabled = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_dashboard_redirects', false) : !empty($options['enable_dashboard_redirects']);
        $admin_enabled = function_exists('tutorpress_get_setting') ? tutorpress_get_setting('enable_admin_redirects', false) : !empty($options['enable_admin_redirects']);
        wp_localize_script('tutorpress-curriculum-metabox', 'TutorPressData', [
            'enableDashboardRedirects' => $dashboard_enabled,
            'enableAdminRedirects' => $admin_enabled,
            'adminUrl' => admin_url(),
        ]);

        // Localize script with necessary data
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorPressCurriculum', [
            'restUrl' => rest_url(),
            'restNonce' => wp_create_nonce('wp_rest'),
            'tutorNonce' => $tutor_nonce,
            'isLesson' => 'lesson' === $screen->post_type,
            'isAssignment' => 'tutor_assignments' === $screen->post_type,
            'adminUrl' => admin_url(),
            'currentUser' => [
                'isAdmin' => current_user_can('manage_options'),
                'canEditCourses' => current_user_can('edit_courses'),
            ]
        ]);
        wp_add_inline_script(
            'tutorpress-curriculum-metabox',
            'tutorPressCurriculum.youtubeApiKeyExists = ' . wp_json_encode($youtube_api_key_exists) . ';',
            'before'
        );

        // Strict types matter here, so the contract is assigned rather than localized as strings.
        wp_add_inline_script(
            'tutorpress-curriculum-metabox',
            'tutorPressCurriculum.quizCapabilities = ' . wp_json_encode(self::get_quiz_capabilities()) . ';',
            'before'
        );

        // Expose comprehensive addon and payment engine data to frontend
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorpressAddons', 
            TutorPress_Addon_Checker::get_comprehensive_status()
        );

        // Localize Freemius license status for feature gating
        // Pass structured data instead of raw HTML for better security
        $upgrade_url = '#';
        if (function_exists('tutorpress_fs')) {
            $upgrade_url = tutorpress_fs()->get_upgrade_url();
        } elseif (function_exists('my_fs')) {
            $upgrade_url = my_fs()->get_upgrade_url();
        }
        
        wp_localize_script('tutorpress-curriculum-metabox', 'tutorpress_fs', [
            'canUsePremium' => tutorpress_fs_can_use_premium(),
            'upgradeUrl'    => $upgrade_url,
            'promo' => [
                'title'   => __('Unlock TutorPress Pro', 'tutorpress'),
                'message' => __('Activate to continue using this feature.', 'tutorpress'),
                'button'  => __('Upgrade', 'tutorpress')
            ],
        ]);
    }

    /**
     * Build the authoritative quiz capability contract for the Gutenberg bundle.
     *
     * Question types come from Tutor's own registry rather than a TutorPress list.
     * Every creation decision fails closed when a contract is missing or ambiguous.
     *
     * @since 0.1.0
     * @return array
     */
    public static function get_quiz_capabilities() {
        $capabilities = array_merge([
            'tutorActive'              => false,
            'tutorVersion'             => '',
            'meetsSupportedFloor'      => false,
            'hasNativeQuizTypes'       => false,
            'learningMode'             => 'unknown',
            'proActive'                => false,
            'proNativeQuizSupport'     => false,
            'supportsTempMaskDeletion' => false,
            'questionTypes'            => [],
        ], self::classify_quiz_settings_contract('', [], false));

        if (!function_exists('tutor') || !function_exists('tutor_utils')) {
            return $capabilities;
        }

        $capabilities['tutorActive']  = true;
        $capabilities['tutorVersion'] = defined('TUTOR_VERSION') ? (string) TUTOR_VERSION : '';
        $capabilities['meetsSupportedFloor'] = '' !== $capabilities['tutorVersion']
            && version_compare($capabilities['tutorVersion'], self::TUTOR_SUPPORTED_FLOOR, '>=');

        $capabilities['learningMode']             = self::get_normalized_learning_mode();
        $capabilities['hasNativeQuizTypes']       = self::has_native_quiz_type_registry();
        $capabilities['proActive']                = defined('TUTOR_PRO_VERSION');
        $capabilities['proNativeQuizSupport']     = $capabilities['proActive'] && self::has_pro_native_quiz_runtime();
        $capabilities['supportsTempMaskDeletion'] = self::supports_temp_mask_deletion();
        $capabilities['questionTypes']            = self::build_question_type_capabilities($capabilities);

        $capabilities = array_merge(
            $capabilities,
            self::classify_quiz_settings_contract(
                $capabilities['tutorVersion'],
                self::probe_legacy_quiz_settings_capabilities()
            )
        );
        return $capabilities;
    }

    /**
     * Classify the executable Quiz Settings contract.
     *
     * @since 0.1.0
     * @param string $version Tutor LMS version.
     * @param array  $legacy_capabilities Probed legacy settings surfaces.
     * @param bool   $tutor_active Whether Tutor LMS is active.
     * @return array
     */
    public static function classify_quiz_settings_contract($version, array $legacy_capabilities, $tutor_active = true) {
        $contract = [
            'quizSettingsContract'          => 'unavailable',
            'quizSettingsUnavailableReason' => '',
            'supportsOrthogonalFeedback'    => false,
            'supportsSeparatePagination'    => false,
            'supportsV4TimingNavigation'    => false,
            'supportsLegacyFeedbackLayout'  => false,
            'supportsV4QuizContentDrip'     => false,
        ];

        if (!$tutor_active) {
            $contract['quizSettingsUnavailableReason'] = 'tutor_inactive';
            return $contract;
        }
        $version = is_string($version) ? trim($version) : '';
        if ('' === $version) {
            $contract['quizSettingsUnavailableReason'] = 'tutor_version_missing';
            return $contract;
        }
        if (version_compare($version, self::TUTOR_SUPPORTED_FLOOR, '<')) {
            $contract['quizSettingsUnavailableReason'] = 'unsupported_tutor_version';
            return $contract;
        }
        if (version_compare($version, '4.0.0', '>=')) {
            $contract['quizSettingsContract']       = 'v4';
            $contract['supportsOrthogonalFeedback'] = true;
            $contract['supportsSeparatePagination'] = true;
            $contract['supportsV4TimingNavigation'] = true;
            $contract['supportsV4QuizContentDrip']  = true;
            return $contract;
        }
        $feedback_modes = isset($legacy_capabilities['feedbackModes'])
            && is_array($legacy_capabilities['feedbackModes'])
            ? $legacy_capabilities['feedbackModes']
            : [];
        $question_layouts = isset($legacy_capabilities['questionLayouts'])
            && is_array($legacy_capabilities['questionLayouts'])
            ? $legacy_capabilities['questionLayouts']
            : [];
        $has_feedback_modes = [] === array_diff(['default', 'reveal', 'retry'], $feedback_modes);
        $has_question_layouts = [] === array_diff(
            ['single_question', 'question_pagination', 'question_below_each_other'],
            $question_layouts
        );
        $has_pagination_style = true === ($legacy_capabilities['hasPaginationStyle'] ?? false);
        if (!$has_feedback_modes || !$has_question_layouts || !$has_pagination_style) {
            $contract['quizSettingsUnavailableReason'] = 'legacy_contract_unavailable';
            return $contract;
        }
        $contract['quizSettingsContract']         = 'legacy';
        $contract['supportsLegacyFeedbackLayout'] = true;
        return $contract;
    }

    /**
     * Probe Tutor's executable pre-4 Quiz Settings surfaces.
     *
     * @since 0.1.0
     * @return array
     */
    private static function probe_legacy_quiz_settings_capabilities() {
        $unavailable = [
            'feedbackModes'     => [],
            'questionLayouts'   => [],
            'hasPaginationStyle' => false,
        ];
        if (!is_callable(['\TUTOR\Quiz', 'get_default_quiz_settings'])) {
            return $unavailable;
        }
        $defaults = \TUTOR\Quiz::get_default_quiz_settings();
        if (
            !is_array($defaults)
            || !array_key_exists('feedback_mode', $defaults)
            || !array_key_exists('question_layout_view', $defaults)
        ) {
            return $unavailable;
        }
        return [
            'feedbackModes'      => ['default', 'reveal', 'retry'],
            'questionLayouts'    => ['single_question', 'question_pagination', 'question_below_each_other'],
            'hasPaginationStyle' => true,
        ];
    }

    /**
     * Normalize Tutor's stored learning mode.
     *
     * @since 0.1.0
     * @return string One of legacy, modern, kids, or unknown.
     */
    private static function get_normalized_learning_mode() {
        $utils = tutor_utils();
        if (!is_object($utils) || !method_exists($utils, 'get_option')) {
            return 'unknown';
        }

        $mode = $utils->get_option('learning_mode');
        $mode = is_string($mode) ? strtolower(trim($mode)) : '';

        return in_array($mode, ['legacy', 'modern', 'kids'], true) ? $mode : 'unknown';
    }

    /**
     * Detect Tutor 4.0's native question-type registry contract.
     *
     * @since 0.1.0
     * @return bool
     */
    private static function has_native_quiz_type_registry() {
        return is_callable(['\Tutor\Models\QuizModel', 'get_question_types'])
            && is_callable(['\Tutor\Models\QuizModel', 'get_modern_mode_quiz_types']);
    }

    /**
     * Detect Tutor Pro's runtime support for all five native question types.
     *
     * @since 0.1.0
     * @return bool
     */
    private static function has_pro_native_quiz_runtime() {
        if (!class_exists('\TUTOR_PRO\Quiz')) {
            return false;
        }

        $required_methods = [
            'grade_draw_image_question',
            'grade_scale_question',
            'grade_pin_image_question',
            'grade_coordinates_question',
            'grade_puzzle_question',
        ];

        foreach ($required_methods as $method) {
            if (!method_exists('\TUTOR_PRO\Quiz', $method)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Detect Tutor 4.0's temporary-mask deletion contract.
     *
     * Tutor 3.9.x accepts two deletion arrays; Tutor 4.0 adds a third parameter.
     *
     * @since 0.1.0
     * @return bool
     */
    private static function supports_temp_mask_deletion() {
        if (!class_exists('\TUTOR\QuizBuilder') || !method_exists('\TUTOR\QuizBuilder', 'handle_delete')) {
            return false;
        }

        try {
            $reflection = new ReflectionMethod('\TUTOR\QuizBuilder', 'handle_delete');
        } catch (ReflectionException $exception) {
            return false;
        }

        return $reflection->getNumberOfParameters() >= 3;
    }

    /**
     * Read Tutor's authoritative question-type registry.
     *
     * @since 0.1.0
     * @param bool $has_native_registry Whether Tutor 4.0's registry contract is present.
     * @return array
     */
    private static function get_tutor_question_registry($has_native_registry) {
        if ($has_native_registry) {
            $types = \Tutor\Models\QuizModel::get_question_types();
            return is_array($types) ? $types : [];
        }

        $utils = tutor_utils();
        if (is_object($utils) && method_exists($utils, 'get_question_types')) {
            $types = $utils->get_question_types();
            return is_array($types) ? $types : [];
        }

        return [];
    }

    /**
     * Derive per-type creation and editing capability from Tutor's registry.
     *
     * @since 0.1.0
     * @param array $capabilities Normalized environment capability values.
     * @return array
     */
    private static function build_question_type_capabilities(array $capabilities) {
        $entries      = [];
        $modern_modes = ['modern', 'kids'];

        foreach (self::get_tutor_question_registry($capabilities['hasNativeQuizTypes']) as $slug => $definition) {
            $slug   = (string) $slug;
            $native = in_array($slug, self::TUTOR_NATIVE_QUESTION_TYPES, true);
            $is_pro = is_array($definition) && !empty($definition['is_pro']);

            // Tutor stores icon markup alongside the label; only the plain label is exposed.
            $label = is_array($definition) && isset($definition['name'])
                ? wp_strip_all_tags((string) $definition['name'])
                : $slug;

            $reason = '';
            if (!$capabilities['meetsSupportedFloor']) {
                $reason = 'unsupported_tutor_version';
            } elseif ($native && !$capabilities['hasNativeQuizTypes']) {
                $reason = 'unsupported_tutor_version';
            } elseif ($native && !$capabilities['proNativeQuizSupport']) {
                $reason = 'pro_required';
            } elseif ($native && !in_array($capabilities['learningMode'], $modern_modes, true)) {
                $reason = 'legacy_mode';
            } elseif ($is_pro && !$capabilities['proActive']) {
                $reason = 'pro_required';
            }

            $can_edit_existing = $capabilities['meetsSupportedFloor'];
            if ($native) {
                // Pro may be inactive and still permit editing an existing native row.
                $can_edit_existing = $can_edit_existing
                    && $capabilities['hasNativeQuizTypes']
                    && in_array($capabilities['learningMode'], $modern_modes, true);
            }

            $entries[] = [
                'slug'               => $slug,
                'label'              => $label,
                'is_pro'             => $is_pro,
                'registered'         => true,
                'can_create'         => '' === $reason,
                'can_edit_existing'  => $can_edit_existing,
                'unavailable_reason' => $reason,
            ];
        }

        return $entries;
    }

    /**
     * Localize script data to pass settings to JavaScript.
     *
     * @since 0.1.0
     * @return void
     */
    public static function localize_script_data() {
        // Moved to enqueue_common_assets()
    }

    /**
     * Add course selling option to Tutor LMS's course details response.
     *
     * @since 0.1.0
     * @param array $response The Tutor LMS course details response.
     * @return array The modified response.
     */
    public static function add_course_selling_option_to_response($response) {
        // Get the course ID from the response
        $post_id = isset($response['ID']) ? (int) $response['ID'] : 0;
        
        if ($post_id > 0) {
            $selling_option = get_post_meta($post_id, 'tutor_course_selling_option', true);
            $response['course_selling_option'] = $selling_option ?: 'one_time'; // Default to one_time if empty
        }
        
        return $response;
    }
    
    /**
     * Sync data from Tutor LMS frontend to TutorPress.
     *
     * @since 0.1.0
     * @param int $post_id The post ID.
     * @param WP_Post $post The post object.
     * @param bool $update Whether this is an update.
     * @return void
     */
    public static function sync_from_tutor_lms($post_id, $post, $update) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Only handle course posts
        if (get_post_type($post_id) !== 'courses') {
            return;
        }
        
        // Check for selling option in various POST formats
        $selling_option = null;
        
        if (isset($_POST['tutor_course_selling_option'])) {
            $selling_option = sanitize_text_field($_POST['tutor_course_selling_option']);
        } elseif (isset($_POST['course_selling_option'])) {
            $selling_option = sanitize_text_field($_POST['course_selling_option']);
        } else {
            // Handle JSON payload from React frontend
            $json_input = file_get_contents('php://input');
            if (!empty($json_input)) {
                $json_data = json_decode($json_input, true);
                if ($json_data && isset($json_data['course_selling_option'])) {
                    $selling_option = sanitize_text_field($json_data['course_selling_option']);
                }
            }
        }
        
        if ($selling_option) {
            update_post_meta($post_id, 'tutor_course_selling_option', $selling_option);
        }
    }

	/**
	 * Save course selling option when Tutor LMS updates a course
	 */
	public static function save_course_selling_option_from_tutor_update($post_id, $params) {
		if (isset($params['course_selling_option'])) {
			$selling_option = sanitize_text_field($params['course_selling_option']);
			update_post_meta($post_id, 'tutor_course_selling_option', $selling_option);
		}
	}
}

// Initialize the class
TutorPress_Assets::init();
