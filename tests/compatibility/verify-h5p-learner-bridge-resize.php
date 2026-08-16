<?php
/**
 * Height-0 plan Step 6: finalize resize-path contract probe.
 *
 * Quiz bridge presence (Steps 3b–5):
 *   - MutationObserver
 *   - both wrapper selectors
 *   - H5P.trigger + "resize" (primary)
 *   - scrollHeight fail-soft path (secondary; must not be the only path)
 *   - boot: startWrapperVisibilityObserver(resizeH5pUnderWrapper)
 *   - exports: isWrapperHidden, resizeH5pUnderWrapper, startWrapperVisibilityObserver
 * Iframe-bridge height/resize absence remains a regression guard.
 * No Pro addon URL strings in quiz bridge.
 *
 * Substring markers:
 *   Quiz presence:
 *     - 'MutationObserver'
 *     - '.tutor-quiz-question-wrapper'
 *     - '.quiz-attempt-single-question'
 *     - 'H5P.trigger'
 *     - '"resize"'
 *     - 'scrollHeight'
 *     - 'startWrapperVisibilityObserver(resizeH5pUnderWrapper)'
 *     - 'isWrapperHidden:' / 'resizeH5pUnderWrapper:' / 'startWrapperVisibilityObserver:'
 *   Iframe regression (always absent):
 *     - 'MutationObserver', 'H5P.trigger', 'scrollHeight', 'style.height'
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-resize.php --allow-root
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

$quiz_js   = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-quiz-bridge.js';
$iframe_js = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-iframe-bridge.js';

$assert( file_exists( $quiz_js ), 'Parent quiz bridge JS must exist.' );
$assert( file_exists( $iframe_js ), 'Iframe bridge JS must exist.' );

$quiz_src = file_get_contents( $quiz_js );
$assert( is_string( $quiz_src ) && '' !== $quiz_src, 'Parent quiz bridge JS must be readable.' );

$iframe_src = file_get_contents( $iframe_js );
$assert( is_string( $iframe_src ) && '' !== $iframe_src, 'Iframe bridge JS must be readable.' );

// No Pro addon URL strings in quiz bridge.
$assert(
	false === strpos( $quiz_src, 'tutor-pro' ) && false === strpos( $quiz_src, '/addons/h5p/' ),
	'Quiz bridge must not reference Pro addon URL paths.'
);

// Observer + wrappers (3b).
$assert( false !== strpos( $quiz_src, 'MutationObserver' ), 'Quiz bridge must use MutationObserver.' );
$assert(
	false !== strpos( $quiz_src, '.tutor-quiz-question-wrapper' ),
	'Quiz bridge must reference .tutor-quiz-question-wrapper.'
);
$assert(
	false !== strpos( $quiz_src, '.quiz-attempt-single-question' ),
	'Quiz bridge must reference .quiz-attempt-single-question.'
);

// Primary trigger path (4a) + fail-soft (4b); trigger must remain present.
$assert( false !== strpos( $quiz_src, 'H5P.trigger' ), 'Quiz bridge must call H5P.trigger (primary path).' );
$assert( false !== strpos( $quiz_src, '"resize"' ), 'Quiz bridge must pass "resize" event type.' );
$assert( false !== strpos( $quiz_src, 'scrollHeight' ), 'Quiz bridge must include scrollHeight fail-soft path.' );

// Boot wiring + export surface (Step 5 / D7).
$assert(
	false !== strpos( $quiz_src, 'startWrapperVisibilityObserver(resizeH5pUnderWrapper)' ),
	'Boot must call startWrapperVisibilityObserver(resizeH5pUnderWrapper).'
);
$assert( false !== strpos( $quiz_src, 'isWrapperHidden:' ), 'Must export isWrapperHidden on tutorpressH5PQuizBridge.' );
$assert( false !== strpos( $quiz_src, 'resizeH5pUnderWrapper:' ), 'Must export resizeH5pUnderWrapper on tutorpressH5PQuizBridge.' );
$assert(
	false !== strpos( $quiz_src, 'startWrapperVisibilityObserver:' ),
	'Must export startWrapperVisibilityObserver on tutorpressH5PQuizBridge.'
);

// Iframe bridge: height/resize regression guard (stable through Step 6).
$assert( false === strpos( $iframe_src, 'MutationObserver' ), 'Iframe bridge must not use MutationObserver.' );
$assert( false === strpos( $iframe_src, 'H5P.trigger' ), 'Iframe bridge must not call H5P.trigger.' );
$assert( false === strpos( $iframe_src, 'scrollHeight' ), 'Iframe bridge must not touch scrollHeight.' );
$assert( false === strpos( $iframe_src, 'style.height' ), 'Iframe bridge must not write style.height.' );

echo "PASS: Resize contract (MO+wrappers+H5P.trigger+scrollHeight; boot+exports; iframe guard; no Pro URLs).\n";
exit( 0 );
