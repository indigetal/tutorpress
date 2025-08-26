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
     * Check if user can access a specific feature.
     *
     * @since 1.0.0
     * @param string $feature Feature identifier
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if user can access feature
     */
    public function can_user_access_feature(string $feature, ?int $user_id = null): bool {
        $features = $this->get_available_features();
        
        // Check if feature exists
        if (!isset($features[$feature])) {
            return false;
        }

        // If feature is disabled, deny access
        if (!$features[$feature]) {
            return false;
        }

        // TODO: Add user capability checks in Step 1C
        // For now, return feature availability
        return true;
    }

    /**
     * Get recommended payment engine for current environment.
     *
     * @since 1.0.0
     * @return string Payment engine identifier
     */
    public function get_recommended_payment_engine(): string {
        // TODO: Implement business logic in Step 1C
        // For now, return basic recommendation
        if ($this->is_tutor_pro_available()) {
            return 'tutor_ecommerce';
        }
        
        return 'woocommerce';
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

        // Add Pro-specific features
        if ($is_pro) {
            $features['advanced_quizzes'] = true;
            $features['certificates'] = true;
            $features['content_drip'] = true;
            $features['live_lessons'] = true;
        }

        return $features;
    }
}
