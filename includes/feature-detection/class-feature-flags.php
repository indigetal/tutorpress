<?php
/**
 * Compatibility Service Implementation
 *
 * Provides centralized compatibility detection and business logic decisions.
 * Delegates low-level checks to existing TutorPress_Addon_Checker while providing
 * higher-level business logic and decision-making capabilities.
 *
 * @package TutorPress
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Compatibility service for environment detection and business logic.
 *
 * This service acts as an orchestration layer that:
 * - Delegates low-level checks to TutorPress_Addon_Checker
 * - Provides business logic for mode determination
 * - Handles user capability integration
 * - Makes recommendations based on environment
 *
 * @since 1.0.0
 */
class TutorPress_Feature_Flags implements TutorPress_Feature_Flags_Interface {

    /**
     * Cached addon checker instance.
     *
     * @since 1.0.0
     * @var TutorPress_Addon_Checker|null
     */
    private $addon_checker = null;

    /**
     * Cached mode determination.
     *
     * @since 1.0.0
     * @var string|null
     */
    private $mode_cache = null;

    /**
     * Cached feature matrix.
     *
     * @since 1.0.0
     * @var array|null
     */
    private $features_cache = null;

    /**
     * Get the addon checker instance.
     *
     * @since 1.0.0
     * @return TutorPress_Addon_Checker
     */
    private function get_addon_checker(): TutorPress_Addon_Checker {
        if (null === $this->addon_checker) {
            $this->addon_checker = new TutorPress_Addon_Checker();
        }
        return $this->addon_checker;
    }

    /**
     * Get the current operational mode.
     *
     * @since 1.0.0
     * @return string Either 'addon' or 'standalone'
     */
    public function get_mode(): string {
        if (null === $this->mode_cache) {
            $this->mode_cache = $this->is_tutor_lms_available() ? 'addon' : 'standalone';
        }
        return $this->mode_cache;
    }

    /**
     * Get available features based on current environment.
     *
     * @since 1.0.0
     * @return array Associative array of feature flags and capabilities
     */
    public function get_available_features(): array {
        if (null === $this->features_cache) {
            $this->features_cache = $this->build_feature_matrix();
        }
        return $this->features_cache;
    }

    /**
     * Check if user can access a specific feature (delegating capability API).
     *
     * @since 1.0.0
     * @param string $feature Feature identifier
     * @param int|null $user_id User ID (null for current user)
     * @param array $context Additional context for capability checks
     * @return bool True if user can access feature
     */
    public function can_user_access_feature(string $feature, ?int $user_id = null, array $context = []): bool {
        // Step 1: Check if feature is available in environment
        $available_features = $this->get_available_features();
        if (!isset($available_features[$feature]) || !$available_features[$feature]) {
            return false;
        }

        // Step 2: Get capability rule for feature (delegate to existing checks)
        $capability_rule = $this->get_capability_rule_for_feature($feature);
        
        // Step 3: Evaluate permission (delegate to existing logic)
        $has_permission = $this->evaluate_capability_rule($capability_rule, $user_id, $context);
        
        // Step 4: Apply filter for site-specific overrides
        return apply_filters(
            'tutorpress_can_user_access_feature', 
            $has_permission, 
            $feature, 
            $user_id, 
            $context
        );
    }

    /**
     * Get payment engine for current environment.
     *
     * @since 1.0.0
     * @return string Payment engine identifier
     */
    public function get_payment_engine(): string {
        // Delegate to addon checker for the established logic
        return $this->get_addon_checker()->get_payment_engine();
    }

    /**
     * Check if Tutor LMS is available and meets minimum requirements.
     *
     * @since 1.0.0
     * @return bool True if Tutor LMS is properly available
     */
    public function is_tutor_lms_available(): bool {
        return $this->get_addon_checker()->is_tutor_lms_active();
    }

    /**
     * Check if Tutor Pro is available.
     *
     * @since 1.0.0
     * @return bool True if Tutor Pro is active and functional
     */
    public function is_tutor_pro_available(): bool {
        return $this->get_addon_checker()->is_tutor_pro_active();
    }

    /**
     * Get Tutor LMS version if available.
     *
     * @since 1.0.0
     * @return string|null Version string or null if not available
     */
    public function get_tutor_lms_version(): ?string {
        if (!$this->is_tutor_lms_available()) {
            return null;
        }

        return $this->get_addon_checker()->get_tutor_version();
    }

    /**
     * Check if a specific Tutor LMS minimum version is met.
     *
     * @since 1.0.0
     * @param string $min_version Minimum required version
     * @return bool True if version requirement is met
     */
    public function meets_tutor_version_requirement(string $min_version): bool {
        $current_version = $this->get_tutor_lms_version();
        
        if (null === $current_version) {
            return false;
        }

        return version_compare($current_version, $min_version, '>=');
    }

    /**
     * Build the feature matrix based on current environment.
     *
     * @since 1.0.0
     * @return array Feature availability matrix
     */
    private function build_feature_matrix(): array {
        $mode = $this->get_mode();
        $is_pro = $this->is_tutor_pro_available();
        $version = $this->get_tutor_lms_version();

        $features = [
            // Core features available in all modes
            'gutenberg_blocks' => true,
            'course_creation' => true,
            'lesson_management' => true,
            
            // Mode-specific features
            'standalone_mode' => ($mode === 'standalone'),
            'addon_mode' => ($mode === 'addon'),
            
            // Version-dependent features
            'tutor_integration' => $this->is_tutor_lms_available(),
            'pro_features' => $is_pro,
            'min_version_met' => $this->meets_tutor_version_requirement('2.4.0'),
        ];

        // Get addon checker for feature detection
        $addon_checker = $this->get_addon_checker();

        // Add Pro-specific features
        if ($is_pro) {
            $features['advanced_quizzes'] = true;
        }

        // Certificates available if addon is enabled (delegate to existing working logic)
        $features['certificates'] = $addon_checker->is_certificate_enabled();

        // Course preview available if addon is enabled (delegate to existing working logic)
        $features['course_preview'] = $addon_checker->is_course_preview_enabled();

        // Content drip available if addon is enabled (delegate to existing working logic)
        $features['content_drip'] = $addon_checker->is_content_drip_enabled();

        // Prerequisites available if addon is enabled (delegate to existing working logic)  
        $features['prerequisites'] = $addon_checker->is_prerequisites_enabled();

        // Subscriptions available if addon is enabled (delegate to existing working logic)
        $features['subscriptions'] = $addon_checker->is_subscription_enabled();

        // Live lessons available if either Google Meet or Zoom addon is enabled (delegate to existing logic)
        $features['live_lessons'] = ($addon_checker->is_google_meet_enabled() || $addon_checker->is_zoom_enabled());

        // H5P integration available if H5P plugin is enabled (delegate to existing working logic)
        $features['h5p_integration'] = $addon_checker->is_h5p_plugin_active();

        return $features;
    }

    /**
     * Get capability rule for feature (maps to existing permission checks).
     *
     * @since 1.0.0
     * @param string $feature Feature identifier
     * @return mixed Capability string, array, or callable
     */
    private function get_capability_rule_for_feature(string $feature): mixed {
        $rules = [
            // Course and lesson management
            'course_creation' => 'edit_posts',
            'lesson_management' => 'edit_posts',
            'gutenberg_blocks' => 'edit_posts',
            'course_curriculum' => 'edit_posts',
            'lesson_settings' => 'edit_posts', 
            'assignment_settings' => 'edit_posts',
            
            // Admin/settings features
            'pricing_models' => 'manage_options',
            'course_bundles' => 'edit_posts',
            'course_preview' => 'edit_posts',
            
            // Pro features with delegated logic  
            'h5p_integration' => function($user_id, $context) {
                return user_can($user_id ?: get_current_user_id(), 'edit_posts');
            },
            'live_lessons' => function($user_id, $context) {
                // Delegate to existing Tutor LMS capability checks
                return user_can($user_id ?: get_current_user_id(), 'edit_posts');
            },
            'content_drip' => function($user_id, $context) {
                return user_can($user_id ?: get_current_user_id(), 'edit_posts');
            },
            'prerequisites' => function($user_id, $context) {
                return user_can($user_id ?: get_current_user_id(), 'edit_posts');
            },
            'subscriptions' => function($user_id, $context) {
                return user_can($user_id ?: get_current_user_id(), 'edit_posts');
            },
            'certificates' => function($user_id, $context) {
                return user_can($user_id ?: get_current_user_id(), 'edit_posts');
            },
            'advanced_quizzes' => function($user_id, $context) {
                return user_can($user_id ?: get_current_user_id(), 'edit_posts');
            }
        ];
        
        return $rules[$feature] ?? 'edit_posts';
    }

    /**
     * Evaluate capability rule (delegates to existing WordPress/Tutor checks).
     *
     * @since 1.0.0
     * @param mixed $rule Capability rule (string, array, or callable)
     * @param int|null $user_id User ID
     * @param array $context Additional context
     * @return bool True if user has permission
     */
    private function evaluate_capability_rule(mixed $rule, ?int $user_id, array $context): bool {
        $user_id = $user_id ?: get_current_user_id();
        
        if (is_callable($rule)) {
            return $rule($user_id, $context);
        }
        
        if (is_string($rule)) {
            return user_can($user_id, $rule);
        }
        
        if (is_array($rule)) {
            // Multiple capabilities (OR logic)
            foreach ($rule as $capability) {
                if (user_can($user_id, $capability)) {
                    return true;
                }
            }
            return false;
        }
        
        return false;
    }
}
