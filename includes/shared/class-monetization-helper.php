<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TutorPress Monetization Helper
 *
 * Centralized detection and normalization of monetization/payment engines.
 * Integrations should use this helper rather than duplicating detection logic.
 */
class TutorPress_Monetization_Helper {

    /**
     * Get the selected payment engine normalized identifier.
     *
     * @return string One of: 'pmpro', 'wc', 'edd', 'tutor', 'surecart', 'none'
     */
    public function get_payment_engine() {
        // Prefer Tutor LMS option when available
        $engine = '';
        if ( function_exists('tutor_utils') ) {
            $engine = tutor_utils()->get_option('monetize_by');
        }

        if ( ! $engine ) {
            $engine = get_option('tutorpress_payment_engine', 'auto');
        }

        return $this->normalize_engine($engine);
    }

    /**
     * Normalize various engine identifiers to canonical tokens.
     */
    private function normalize_engine($value) {
        $map = [
            'pmp'   => 'pmpro',
            'pmpro' => 'pmpro',
            'wc'    => 'wc',
            'edd'   => 'edd',
            'tutor' => 'tutor',
            'none'  => 'none',
            'free'  => 'none',
        ];

        return $map[$value] ?? $value;
    }

    public function is_pmpro() {
        return 'pmpro' === $this->get_payment_engine();
    }

    public function is_woocommerce() {
        return 'wc' === $this->get_payment_engine();
    }

    public function is_edd() {
        return 'edd' === $this->get_payment_engine();
    }

    public function is_native() {
        return 'tutor' === $this->get_payment_engine();
    }

}

// Convenience wrapper
if (!function_exists('tutorpress_monetization')) {
    function tutorpress_monetization() {
        static $instance = null;
        if (null === $instance) {
            $instance = new TutorPress_Monetization_Helper();
        }
        return $instance;
    }
}


