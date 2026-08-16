<?php
/**
 * Step 10d: learner-bridge client contracts (D16 + D14 + D31; no Pro URLs).
 *
 * Aggregates Step 10 JS/inject surface. Does not replace Step 7/9 helpers.
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-client-contracts.php --allow-root
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

$bridge    = 'TutorPress_H5P_Learner_Bridge';
$quiz_js   = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-quiz-bridge.js';
$iframe_js = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-iframe-bridge.js';
$assert( file_exists( $quiz_js ) && file_exists( $iframe_js ), 'Quiz + iframe bridge JS must exist.' );
$quiz = file_get_contents( $quiz_js );
$iframe = file_get_contents( $iframe_js );
$assert( is_string( $quiz ) && '' !== $quiz && is_string( $iframe ) && '' !== $iframe, 'Bridge JS must be readable.' );

// D16 dual identity (parent quiz bridge).
$assert( false !== strpos( $quiz, 'tutor-answering-quiz' ), 'D16: must detect #tutor-answering-quiz.' );
$assert( false !== strpos( $quiz, 'data-h5p-quiz-content-id' ), 'D16: must use data-h5p-quiz-content-id.' );
$assert( false !== strpos( $quiz, 'quiz-attempt-form-' ), 'D16: must parse quiz-attempt-form- ids.' );
$assert( false !== strpos( $quiz, 'http://h5p.org/x-api/h5p-local-content-id' ), 'D16: Pro content-id extension URL.' );
$assert( false !== strpos( $quiz, 'http://h5p.org/x-api/h5p-local-question-id' ), 'D16: Pro question-id extension URL.' );
$assert( false !== strpos( $quiz, 'tutorpressH5PQuiz' ), 'Must use tutorpressH5PQuiz localize object.' );

// D14 required-answer selectors + requestSubmit allow-path; never Next.
$assert( false !== strpos( $quiz, 'button.tutor-quiz-submit-btn' ), 'D14: modern linear .tutor-quiz-submit-btn.' );
$assert( false !== strpos( $quiz, "form^='quiz-attempt-form-'" ), 'D14: modern non-linear form^=.' );
$assert( false !== strpos( $quiz, "button[name='quiz_answer_submit_btn']" ), 'D14: legacy quiz_answer_submit_btn.' );
$assert( false !== strpos( $quiz, 'requestSubmit' ), 'D14: requestSubmit allow-path.' );
$assert( false === strpos( $quiz, 'tutor-quiz-answer-next-btn' ), 'D14: must not bind Next.' );
$assert( false === strpos( $quiz, 'tutor-quiz-next-btn-all' ), 'D14: must not bind legacy-linear next-btn-all.' );

// D31: set_iframe in parent + iframe; inject registered under predicate.
$assert( false !== strpos( $quiz, 'set_iframe' ), 'D31: parent must post set_iframe.' );
$assert( false !== strpos( $iframe, 'set_iframe' ), 'D31: iframe must handle set_iframe.' );
$assert( false !== strpos( $iframe, 'H5P.XAPIEvent.prototype.setObject' ), 'D31: iframe patches setObject after gate.' );
$assert(
	false !== has_action( 'h5p_alter_library_scripts', array( $bridge, 'inject_h5p_iframe_bridge_script' ) ),
	'D31: h5p_alter_library_scripts must register inject.'
);

// No Pro addon asset URLs in either bridge.
foreach ( array( 'quiz' => $quiz, 'iframe' => $iframe ) as $label => $src ) {
	$assert( false === strpos( $src, 'tutor-pro' ), "{$label}: must not reference tutor-pro." );
	$assert( false === strpos( $src, '/addons/h5p/' ), "{$label}: must not reference Pro /addons/h5p/." );
	$assert( false === strpos( $src, '_tutorobject' ), "{$label}: must not use Pro _tutorobject." );
}

echo "PASS: Step 10d client contracts (D16 + D14 + D31; no Pro URLs).\n";
exit( 0 );
