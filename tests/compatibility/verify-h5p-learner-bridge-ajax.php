<?php
/**
 * Step 10c: AJAX registration (no nopriv) + D13/D15/D29/D30 fixtures.
 * Usage: wp82 eval-file .../verify-h5p-learner-bridge-ajax.php --allow-root
 */

$fail = static function ( $message ) {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
};
$assert = static function ( $condition, $message ) use ( $fail ) {
	if ( ! $condition ) {
		$fail( $message );
	}
};
if ( ! class_exists( 'TutorPress_H5P_Learner_Bridge' ) ) {
	$fail( 'TutorPress_H5P_Learner_Bridge unavailable.' );
}

$bridge = 'TutorPress_H5P_Learner_Bridge';
$save   = array( $bridge, 'save_h5p_question_xAPI_statement' );
$check  = array( $bridge, 'check_h5p_question_answered' );

// 10c1: registration + no nopriv.
$assert( false !== has_action( 'wp_ajax_save_h5p_question_xAPI_statement', $save ), 'save must register wp_ajax_.' );
$assert( false !== has_action( 'wp_ajax_check_h5p_question_answered', $check ), 'check must register wp_ajax_.' );
$assert( false === has_action( 'wp_ajax_nopriv_save_h5p_question_xAPI_statement' ), 'save must not register nopriv.' );
$assert( false === has_action( 'wp_ajax_nopriv_check_h5p_question_answered' ), 'check must not register nopriv.' );
$src = file_get_contents( TUTORPRESS_PATH . 'includes/tutorlms/overrides/class-tutorpress-h5p-learner-bridge.php' );
$assert( is_string( $src ) && false === strpos( $src, 'wp_ajax_nopriv_save_h5p_question_xAPI_statement' ) && false === strpos( $src, 'wp_ajax_nopriv_check_h5p_question_answered' ), 'Source must not add nopriv handlers.' );

// 10c2: D13/D29 via maybe_save_scored_result (9718 / 578→2; synth attempt).
global $wpdb;
$quiz_id       = 9718;
$question_id   = 578;
$content_id    = 2;
$user_id       = 1;
$synth_attempt = 999999402;
$auth          = array(
	'user_id'     => $user_id,
	'quiz_id'     => $quiz_id,
	'question_id' => $question_id,
	'content_id'  => $content_id,
	'attempt_id'  => $synth_attempt,
);
$assert( $bridge::is_valid_h5p_question_pair( $quiz_id, $question_id, $content_id ), '9718/578→2 must be valid h5p pair.' );
$cleanup = static function () use ( $bridge, $synth_attempt ) {
	$bridge::delete_h5p_quiz_result_by_attempt_id( $synth_attempt );
};
$cleanup();

$generic_table  = $wpdb->prefix . 'tutor_h5p_statement';
$generic_before = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $generic_table ) ) === $generic_table )
	? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$generic_table}" ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	: null;

$scored  = (object) array( 'result' => (object) array( 'score' => (object) array( 'raw' => 1, 'max' => 1 ), 'completion' => true ) );
$written = $bridge::maybe_save_scored_result( $scored, $auth );
$assert( ! is_wp_error( $written ) && 'written' === $written['result_status'] && (int) $written['result_id'] > 0, 'D13/D29: scored insert must be written.' );
$exists = $bridge::maybe_save_scored_result( $scored, $auth );
$assert( ! is_wp_error( $exists ) && 'exists' === $exists['result_status'] && (int) $exists['result_id'] === (int) $written['result_id'], 'D7/D13: second scored save must be exists.' );
$no_result = $bridge::maybe_save_scored_result(
	(object) array( 'result' => (object) array( 'score' => (object) array( 'raw' => 1 ) ) ),
	array_merge( $auth, array( 'question_id' => 579, 'content_id' => 4 ) )
);
$assert( ! is_wp_error( $no_result ) && 'no_result' === $no_result['result_status'] && 0 === (int) $no_result['result_id'], 'D29: missing score.max must be no_result.' );
if ( null !== $generic_before ) {
	$assert( $generic_before === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$generic_table}" ), 'D28: must not write tutor_h5p_statement.' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// D15/D30: ended attempt 42 → forbidden.
$ended = tutor_utils()->get_attempt( 42 );
$assert( $ended && 'attempt_ended' === $ended->attempt_status && (int) $ended->quiz_id === 9718, 'Attempt 42 must be ended on quiz 9718.' );
wp_set_current_user( (int) $ended->user_id );
$_POST = array(
	tutor()->nonce => wp_create_nonce( tutor()->nonce_action ),
	'quiz_id'      => (int) $ended->quiz_id,
	'attempt_id'   => (int) $ended->attempt_id,
);
$ended_auth = $bridge::authorize_h5p_quiz_attempt_ajax();
$assert( is_wp_error( $ended_auth ) && 'forbidden' === $ended_auth->get_error_code(), 'D30: ended attempt must be forbidden.' );

$cleanup();
echo "PASS: Step 10c AJAX registration + D13/D15/D29/D30 fixtures.\n";
exit( 0 );
