<?php
/**
 * Compatibility Service Interface
 *
 * Defines the contract for compatibility detection and business logic services.
 * This interface enables future implementation swapping and testing flexibility.
 *
 * @package TutorPress
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Interface for compatibility service implementations.
 *
 * Provides standardized methods for:
 * - Environment detection (addon vs standalone mode)
 * - Feature availability checking  
 * - User capability integration
 * - Business logic decisions
 *
 * @since 1.0.0
 */
interface TutorPress_Feature_Flags_Interface {

    /**
     * Get the current operational mode.
     *
     * @since 1.0.0
     * @return string Either 'addon' or 'standalone'
     */
    public function get_mode(): string;

    /**
     * Get available features based on current environment.
     *
     * @since 1.0.0
     * @return array Associative array of feature flags and capabilities
     */
    public function get_available_features(): array;

    /**
     * Check if user can access a specific feature (delegating capability API).
     *
     * @since 1.0.0
     * @param string $feature Feature identifier
     * @param int|null $user_id User ID (null for current user)
     * @param array $context Additional context for capability checks
     * @return bool True if user can access feature
     */
    public function can_user_access_feature(string $feature, ?int $user_id = null, array $context = []): bool;

    /**
     * Get payment engine for current environment.
     *
     * @since 1.0.0
     * @return string Payment engine identifier ('tutor_pro', 'wc', 'edd', etc.)
     */
    public function get_payment_engine(): string;

    /**
     * Check if Tutor LMS is available and meets minimum requirements.
     *
     * @since 1.0.0
     * @return bool True if Tutor LMS is properly available
     */
    public function is_tutor_lms_available(): bool;

    /**
     * Check if Tutor Pro is available.
     *
     * @since 1.0.0
     * @return bool True if Tutor Pro is active and functional
     */
    public function is_tutor_pro_available(): bool;

    /**
     * Get Tutor LMS version if available.
     *
     * @since 1.0.0
     * @return string|null Version string or null if not available
     */
    public function get_tutor_lms_version(): ?string;

    /**
     * Check if a specific Tutor LMS minimum version is met.
     *
     * @since 1.0.0
     * @param string $min_version Minimum required version
     * @return bool True if version requirement is met
     */
    public function meets_tutor_version_requirement(string $min_version): bool;
}
