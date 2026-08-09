<?php
/**
 * Step 7d: verify parent quiz bridge xAPI / D16 contract (no D1 / no Submit gates).
 *
 * Asserts dual-shell ID strings, set_iframe, save action, tutorpressH5PQuiz usage,
 * and absence of Next/required-answer binds (Steps 8–9).
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-quiz-xapi.php --allow-root
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

// Dual ID / shell strings (D16).
$assert( false !== strpos( $src, 'tutor-answering-quiz' ), 'Source must detect legacy #tutor-answering-quiz.' );
$assert( false !== strpos( $src, 'quiz-attempt-form-' ), 'Source must parse modern quiz-attempt-form- ids.' );
$assert( false !== strpos( $src, 'data-content-id' ), 'Source must use modern data-content-id.' );
$assert( false !== strpos( $src, 'data-h5p-quiz-content-id' ), 'Source must use legacy data-h5p-quiz-content-id.' );
$assert( false !== strpos( $src, 'tutor-quiz-question' ), 'Source must use modern .tutor-quiz-question.' );
$assert( false !== strpos( $src, 'quiz-attempt-single-question' ), 'Source must use legacy .quiz-attempt-single-question.' );

// Identity + save path.
$assert( false !== strpos( $src, 'set_iframe' ), 'Source must postMessage set_iframe.' );
$assert( false !== strpos( $src, 'save_h5p_question_xAPI_statement' ), 'Source must POST save_h5p_question_xAPI_statement.' );
$assert( false !== strpos( $src, 'tutorpressH5PQuiz' ), 'Source must use tutorpressH5PQuiz localize object.' );
$assert( false !== strpos( $src, 'h5p-local-question-id' ), 'Source must read h5p-local-question-id extension.' );
$assert( false !== strpos( $src, 'h5p-local-content-id' ), 'Source must read h5p-local-content-id extension.' );
$assert( false !== strpos( $src, 'externalDispatcher' ), 'Source must bind H5P.externalDispatcher xAPI.' );

// Step 7 must not include D1 / required-answer / Next intercepts.
$assert( false === strpos( $src, 'tutor-quiz-answer-next-btn' ), 'Must not bind Next (.tutor-quiz-answer-next-btn).' );
$assert( false === strpos( $src, 'tutor-quiz-submit-btn' ), 'Must not bind modern linear Submit yet.' );
$assert( false === strpos( $src, "form^='quiz-attempt-form-'" ) && false === strpos( $src, 'form^=\'quiz-attempt-form-\'' ), 'Must not bind modern form^= Submit yet.' );
$assert( false === strpos( $src, 'quiz_answer_submit_btn' ), 'Must not bind legacy quiz_answer_submit_btn yet.' );
$assert( false === strpos( $src, 'check_h5p_question_answered' ), 'Must not call check_h5p_question_answered yet.' );
$assert( false === strpos( $src, 'requestSubmit' ), 'Must not use requestSubmit yet.' );

echo "PASS: Step 7d parent quiz bridge xAPI/D16 contract (no D1/gates).\n";
exit( 0 );
