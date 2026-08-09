<?php
/**
 * Step 6c: verify H5P iframe bridge contract (D16 extensions + D31 no-op).
 *
 * Asserts: inject registration; Pro extension URL strings; set_iframe gate;
 * setObject only after set_iframe (D31). Does NOT require parent DOM attrs
 * (data-content-id / data-h5p-quiz-content-id — Step 7).
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-iframe.php --allow-root
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

$assert(
	false !== has_action( 'h5p_alter_library_scripts', array( $bridge, 'inject_h5p_iframe_bridge_script' ) ),
	'h5p_alter_library_scripts must register inject_h5p_iframe_bridge_script.'
);

$scripts = array();
$bridge::inject_h5p_iframe_bridge_script( $scripts, array(), 'iframe' );
$assert( 1 === count( $scripts ), 'iframe embed must append one script entry.' );
$assert(
	false !== strpos( $scripts[0]->path, 'tutorpress-h5p-iframe-bridge.js' ),
	'Inject path must be TutorPress iframe bridge.'
);

$iframe_js = TUTORPRESS_PATH . 'assets/js/tutorpress-h5p-iframe-bridge.js';
$assert( file_exists( $iframe_js ), 'Iframe bridge JS must exist.' );
$src = file_get_contents( $iframe_js );
$assert( is_string( $src ) && '' !== $src, 'Iframe bridge JS must be readable.' );

$assert( false !== strpos( $src, 'set_iframe' ), 'Source must handle set_iframe.' );
$assert(
	false !== strpos( $src, 'http://h5p.org/x-api/h5p-local-content-id' ),
	'Source must include Pro h5p-local-content-id extension URL.'
);
$assert(
	false !== strpos( $src, 'http://h5p.org/x-api/h5p-local-question-id' ),
	'Source must include Pro h5p-local-question-id extension URL.'
);
$assert(
	false !== strpos( $src, 'H5P.XAPIEvent.prototype.setObject' ),
	'Source must patch H5P.XAPIEvent.prototype.setObject.'
);

// D31: setObject assignment only after set_iframe gate (string order).
$set_iframe_pos = strpos( $src, "action !== 'set_iframe'" );
if ( false === $set_iframe_pos ) {
	$set_iframe_pos = strpos( $src, 'action !== "set_iframe"' );
}
$set_object_pos = strpos( $src, 'H5P.XAPIEvent.prototype.setObject' );
$assert( false !== $set_iframe_pos, 'Source must early-return unless set_iframe.' );
$assert(
	$set_object_pos > $set_iframe_pos,
	'D31: setObject patch must appear after set_iframe gate (no-op without message).'
);

// Locked: iframe does not parse parent DOM identity attrs (Step 7).
$assert( false === strpos( $src, 'data-content-id' ), 'Iframe must not parse data-content-id.' );
$assert(
	false === strpos( $src, 'data-h5p-quiz-content-id' ),
	'Iframe must not parse data-h5p-quiz-content-id.'
);

echo "PASS: Step 6c iframe contract (extensions + set_iframe + D31 no-op + inject).\n";
exit( 0 );
