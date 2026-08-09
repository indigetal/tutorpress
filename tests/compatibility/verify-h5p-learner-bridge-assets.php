<?php
/**
 * Step 5: verify H5P learner-bridge asset hooks (enqueue + D31 inject).
 *
 * Product-target (predicate true): tutor_quiz/body/before + h5p_alter_library_scripts
 * registered to TutorPress callbacks. Inject appends TutorPress iframe stub for iframe embeds.
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-assets.php --allow-root
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

$assert( true === $bridge::should_register_learner_bridge( true, false ), 'A: WP-on Pro-off must register.' );
$assert( false === $bridge::should_register_learner_bridge( false, false ), 'B: WP-off must not register.' );
$assert( false === $bridge::should_register_learner_bridge( true, true ), 'C: Pro-runtime-on must not register.' );

// Live product target: predicate true → hooks present.
$assert(
	false !== has_action( 'tutor_quiz/body/before', array( $bridge, 'enqueue_quiz_bridge_assets' ) ),
	'tutor_quiz/body/before must register enqueue_quiz_bridge_assets.'
);
$assert(
	false !== has_action( 'h5p_alter_library_scripts', array( $bridge, 'inject_h5p_iframe_bridge_script' ) ),
	'h5p_alter_library_scripts must register inject_h5p_iframe_bridge_script.'
);

// Asset files exist (TutorPress-owned paths).
$quiz_js   = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-quiz-bridge.js';
$iframe_js = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-iframe-bridge.js';
$assert( file_exists( $quiz_js ), 'Parent quiz bridge stub must exist.' );
$assert( file_exists( $iframe_js ), 'Iframe bridge stub must exist.' );

// Inject contract: iframe embed appends TutorPress path; non-iframe unchanged.
$scripts = array();
$bridge::inject_h5p_iframe_bridge_script( $scripts, array(), 'div' );
$assert( 0 === count( $scripts ), 'Non-iframe embed must not inject.' );

$scripts = array();
$bridge::inject_h5p_iframe_bridge_script( $scripts, array(), 'iframe' );
$assert( 1 === count( $scripts ), 'iframe embed must append one script entry.' );
$assert( isset( $scripts[0]->path, $scripts[0]->version ), 'Inject entry must have path + version.' );
$assert(
	false !== strpos( $scripts[0]->path, 'tutorpress-h5p-iframe-bridge.js' ),
	'Inject path must be TutorPress iframe stub.'
);
$assert(
	false === strpos( $scripts[0]->path, 'tutor-pro' ) && false === strpos( $scripts[0]->path, '/addons/h5p/' ),
	'Inject path must not be a Pro addon URL.'
);
$assert( 0 === strpos( $scripts[0]->version, '?ver=' ), 'Inject version must use ?ver= prefix.' );

// Enqueue gate: non-h5p quiz_type no-ops (does not enqueue).
$bridge::enqueue_quiz_bridge_assets( 9706, array( 'quiz_type' => 'tutor_quiz' ) );
$assert(
	! wp_script_is( 'tutorpress-h5p-quiz-bridge', 'enqueued' ) && ! wp_script_is( 'tutorpress-h5p-quiz-bridge', 'queue' ),
	'Non-h5p quiz_type must not enqueue parent bridge.'
);

echo "PASS: Step 5 asset hooks + D31 inject contract.\n";
exit( 0 );
