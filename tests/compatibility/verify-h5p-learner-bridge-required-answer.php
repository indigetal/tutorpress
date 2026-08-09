<?php
/**
 * Step 9e: verify D14 required-answer client contract.
 *
 * Asserts all three Submit selectors, requestSubmit allow-path, check AJAX,
 * Next excluded, and no legacy-linear required-answer bind.
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-required-answer.php --allow-root
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

// Three D14 selectors + bind helpers.
$assert( false !== strpos( $src, 'button.tutor-quiz-submit-btn' ), 'Must bind modern linear .tutor-quiz-submit-btn.' );
$assert( false !== strpos( $src, "form^='quiz-attempt-form-'" ), 'Must bind modern non-linear form^= selector.' );
$assert( false !== strpos( $src, "button[name='quiz_answer_submit_btn']" ), 'Must bind legacy quiz_answer_submit_btn.' );
$assert( false !== strpos( $src, 'function bindModernLinearSubmit' ), 'Must define bindModernLinearSubmit.' );
$assert( false !== strpos( $src, 'function bindModernNonLinearSubmit' ), 'Must define bindModernNonLinearSubmit.' );
$assert( false !== strpos( $src, 'function bindLegacyNonLinearSubmit' ), 'Must define bindLegacyNonLinearSubmit.' );
$assert( false !== strpos( $src, 'bindModernLinearSubmit()' ), 'Must wire bindModernLinearSubmit on ready.' );
$assert( false !== strpos( $src, 'bindModernNonLinearSubmit()' ), 'Must wire bindModernNonLinearSubmit on ready.' );
$assert( false !== strpos( $src, 'bindLegacyNonLinearSubmit()' ), 'Must wire bindLegacyNonLinearSubmit on ready.' );

// Allow-path + check AJAX (D14/D15).
$assert( false !== strpos( $src, 'requestSubmit' ), 'Must use requestSubmit allow-path.' );
$assert( false !== strpos( $src, 'function allowSubmitOnce' ), 'Must define allowSubmitOnce.' );
$assert( false !== strpos( $src, 'check_h5p_question_answered' ), 'Must POST check_h5p_question_answered.' );
$assert( false !== strpos( $src, 'tutorpressH5PQuiz' ), 'Must use tutorpressH5PQuiz localize object.' );
$assert( false === strpos( $src, 'AbortController' ), 'Must not use Pro AbortController re-click.' );
$assert( false === strpos( $src, '_tutorobject' ), 'Must not use Pro _tutorobject.' );

// Next excluded; legacy linear unbound (name= only; no next-btn-all gate).
$assert( false === strpos( $src, 'tutor-quiz-answer-next-btn' ), 'Must not bind Next (.tutor-quiz-answer-next-btn).' );
$assert( false === strpos( $src, 'tutor-quiz-next-btn-all' ), 'Must not bind legacy linear next-btn-all.' );
$assert( false !== strpos( $src, 'No legacy-linear bind' ), 'Must document no legacy-linear required-answer bind.' );
$assert( false !== strpos( $src, 'function collectLegacyPairs' ), 'Must define collectLegacyPairs.' );
$assert( false !== strpos( $src, 'answer-help-block' ), 'Must use legacy .answer-help-block errors.' );
$assert( false !== strpos( $src, 'tutor-quiz-questions-error' ), 'Must use modern .tutor-quiz-questions-error.' );

echo "PASS: Step 9e D14 required-answer contract (3 selectors; requestSubmit; no Next/legacy-linear).\n";
exit( 0 );
