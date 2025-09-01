<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TutorPress Freemius Integration
 *
 * Handles Freemius SDK event integration, license state changes,
 * and admin notices for trial/license management.
 *
 * @package TutorPress
 * @since 1.15.14
 */
class TutorPress_Freemius {

    /**
     * Initialize the Freemius integration
     *
     * @since 1.15.14
     */
    public static function init() {
        // Hook into Freemius SDK loaded event
        add_action('tutorpress_fs_loaded', [__CLASS__, 'register_hooks']);
        
        // Hook admin notices (admin-only)
        if (is_admin()) {
            add_action('admin_notices', [__CLASS__, 'show_trial_expiry_notice']);
        }
    }

    /**
     * Register Freemius SDK event hooks
     *
     * @since 1.15.14
     */
    public static function register_hooks() {
        // Standardize Freemius accessor with fallback
        $fs = null;
        if (function_exists('tutorpress_fs')) {
            $fs = tutorpress_fs();
        } elseif (function_exists('my_fs')) {
            $fs = my_fs();
        }
        
        if (!$fs) {
            return;
        }

        // Register SDK event handlers
        $fs->add_action('after_trial_expires', [__CLASS__, 'on_trial_expired']);
        $fs->add_action('after_license_activation', [__CLASS__, 'on_license_activated']);
        $fs->add_action('after_license_deactivation', [__CLASS__, 'on_license_deactivated']);
    }

    /**
     * Handle trial expiry event
     *
     * @since 1.15.14
     */
    public static function on_trial_expired() {
        // Set flag to show admin notice
        update_option('tutorpress_show_trial_expiry_notice', true);
        
        // Invalidate all premium status caches efficiently
        self::invalidate_premium_cache();
    }

    /**
     * Handle license activation event
     *
     * @since 1.15.14
     */
    public static function on_license_activated() {
        // Clear trial expiry notice if it exists
        delete_option('tutorpress_show_trial_expiry_notice');
        
        // Invalidate all premium status caches
        self::invalidate_premium_cache();
    }

    /**
     * Handle license deactivation event
     *
     * @since 1.15.14
     */
    public static function on_license_deactivated() {
        // Invalidate all premium status caches
        self::invalidate_premium_cache();
    }

    /**
     * Invalidate premium status cache efficiently
     *
     * Uses a cache-buster approach instead of deleting many transients
     *
     * @since 1.15.14
     */
    private static function invalidate_premium_cache() {
        // Bump cache buster to invalidate all premium status caches
        update_option('tutorpress_fs_cache_buster', time());
    }

    /**
     * Show trial expiry admin notice
     *
     * @since 1.15.14
     */
    public static function show_trial_expiry_notice() {
        if (!get_option('tutorpress_show_trial_expiry_notice')) {
            return;
        }

        // Standardize Freemius accessor and add i18n
        $upgrade_url = '#';
        if (function_exists('tutorpress_fs')) {
            $upgrade_url = tutorpress_fs()->get_upgrade_url();
        } elseif (function_exists('my_fs')) {
            $upgrade_url = my_fs()->get_upgrade_url();
        }
        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php echo esc_html__('TutorPress Premium Features Locked', 'tutorpress'); ?></strong> -
                <?php echo esc_html__('Your trial period of TutorPress has ended. Manage courses with Tutor LMS or activate a license to restore premium features of TutorPress.', 'tutorpress'); ?>
                <a href="<?php echo esc_url($upgrade_url); ?>"><?php echo esc_html__('Activate License', 'tutorpress'); ?></a>
            </p>
        </div>
        <?php
        
        // Clear the flag after showing (one-time notice)
        delete_option('tutorpress_show_trial_expiry_notice');
    }

    /**
     * Get the current cache buster value
     *
     * @return int Cache buster timestamp
     * @since 1.15.14
     */
    public static function get_cache_buster(): int {
        return (int) get_option('tutorpress_fs_cache_buster', 0);
    }
}
