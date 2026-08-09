<?php
/**
 * Step 8b: verify D1 modern Alpine radio unlock contract.
 *
 * Asserts written|exists gate, modern-only unlock, radio-only fill + bubbling input,
 * no unlock on no_result, and no Next/Submit binds (Step 9 separate).
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-d1.php --allow-root
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

$quiz_js = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-quiz-bridge.js';
$assert( file_exists( $quiz_js ), 'Parent quiz bridge JS must exist.' );
$src     = file_get_contents( $quiz_js );
$assert( is_string( $src ) && '' !== $src, 'Parent quiz bridge JS must be readable.' );

// D1 helper + modern-only gate.
$assert( false !== strpos( $src, 'function unlockModernNext' ), 'Source must define unlockModernNext.' );
$assert( false !== strpos( $src, 'isLegacyShell()' ), 'Source must call isLegacyShell for modern-only.' );
$assert( false !== strpos( $src, 'input[type="radio"]' ), 'Source must target type=radio only.' );
$assert( false !== strpos( $src, '[quiz_question][' ), 'Source must use attempt[a][quiz_question][q] name.' );
$assert( false !== strpos( $src, "String(contentId)" ), 'Source must set radio value as string contentId.' );
$assert( false !== strpos( $src, "new Event('input'" ), 'Source must dispatch input Event.' );
$assert( false !== strpos( $src, 'bubbles: true' ), 'Source must bubble the input event.' );

// Scored unlock gate (D13/D29); no_result must not unlock.
$assert( false !== strpos( $src, "'written'" ), 'Source must gate on written.' );
$assert( false !== strpos( $src, "'exists'" ), 'Source must gate on exists.' );
$assert( false !== strpos( $src, 'unlockModernNext(questionId, contentId)' ), 'Source must call unlockModernNext after save success.' );
$assert( false === strpos( $src, "'no_result'" ), 'Source must not unlock on no_result.' );

// Still no Next / required-answer binds (Step 9).
$assert( false === strpos( $src, 'tutor-quiz-answer-next-btn' ), 'Must not bind Next (.tutor-quiz-answer-next-btn).' );
$assert( false === strpos( $src, 'tutor-quiz-submit-btn' ), 'Must not bind modern linear Submit yet.' );
$assert( false === strpos( $src, "form^='quiz-attempt-form-'" ) && false === strpos( $src, 'form^=\'quiz-attempt-form-\'' ), 'Must not bind modern form^= Submit yet.' );
$assert( false === strpos( $src, 'quiz_answer_submit_btn' ), 'Must not bind legacy quiz_answer_submit_btn yet.' );
$assert( false === strpos( $src, 'check_h5p_question_answered' ), 'Must not call check_h5p_question_answered yet.' );
$assert( false === strpos( $src, 'requestSubmit' ), 'Must not use requestSubmit yet.' );

echo "PASS: Step 8b D1 modern unlock contract (written|exists; no Next/Submit binds).\n";
exit( 0 );
