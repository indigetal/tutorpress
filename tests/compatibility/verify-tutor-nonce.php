<?php
/**
 * Verify Tutor's canonical AJAX nonce contract.
 */

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

if (!function_exists('tutor')) {
    $fail('Tutor is not active.');
}

$tutor_instance = tutor();
if (!is_object($tutor_instance)) {
    $fail('Tutor did not expose its configuration object.');
}

$nonce_field = $tutor_instance->nonce;
if (!is_string($nonce_field) || '_tutor_nonce' !== $nonce_field) {
    $fail('Tutor nonce field is not _tutor_nonce.');
}

$nonce_action = $tutor_instance->nonce_action;
if (!is_string($nonce_action) || '' === $nonce_action) {
    $fail('Tutor nonce action is unavailable.');
}

$nonce = wp_create_nonce($nonce_action);
if (!wp_verify_nonce($nonce, $nonce_action)) {
    $fail('A generated Tutor nonce did not verify.');
}

WP_CLI::log('PASS: Tutor nonce contract is valid.');
