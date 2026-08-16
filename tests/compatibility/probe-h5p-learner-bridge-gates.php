<?php
/**
 * Step 10a: learner-bridge registration gates (pure A/B/C + live A + source seam).
 *
 * Product-target A: hooks present. B/C: pure helper false + register_bridge early-return
 * tied to should_register_learner_bridge (no Pro activation / no WP-off flip).
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/probe-h5p-learner-bridge-gates.php --allow-root
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

// --- Env snapshot (live product target) ---
$tutor_ver  = defined( 'TUTOR_VERSION' ) ? (string) TUTOR_VERSION : '';
$has_pro    = function_exists( 'tutor' ) && tutor() && (bool) tutor()->has_pro;
$h5p_plugin = TutorPress_Addon_Checker::is_h5p_plugin_active();
$pro_addon  = TutorPress_Addon_Checker::is_h5p_enabled();
$pro_rt     = $pro_addon && $h5p_plugin;

$assert( '' !== $tutor_ver && version_compare( $tutor_ver, '4.0.0', '>=' ), "Tutor must be ≥ 4.x (got {$tutor_ver})." );
$assert( false === $has_pro, 'has_pro must be false on product target.' );
$assert( true === $h5p_plugin, 'WP H5P must be active.' );
$assert( false === $pro_addon && false === $pro_rt, 'Pro H5P runtime must be absent.' );

// --- Pure A/B/C ---
$assert( true === $bridge::should_register_learner_bridge( true, false ), 'A: WP-on Pro-off must register.' );
$assert( false === $bridge::should_register_learner_bridge( false, false ), 'B: WP-off must not register.' );
$assert( false === $bridge::should_register_learner_bridge( true, true ), 'C: Pro-runtime-on must not register.' );
$assert( false === $bridge::should_register_learner_bridge( false, true ), 'B+C: WP-off + Pro-on must not register.' );

// --- Live A: hooks present (same register_bridge list) ---
$cbs = array(
	'save'     => array( $bridge, 'save_h5p_question_xAPI_statement' ),
	'check'    => array( $bridge, 'check_h5p_question_answered' ),
	'deleted'  => array( $bridge, 'delete_h5p_quiz_result_by_attempt_id' ),
	'total'    => array( $bridge, 'filter_total_marks' ),
	'earned'   => array( $bridge, 'filter_total_quiz_marks' ),
	'answer'   => array( $bridge, 'filter_quiz_answer_data' ),
	'enqueue'  => array( $bridge, 'enqueue_quiz_bridge_assets' ),
	'inject'   => array( $bridge, 'inject_h5p_iframe_bridge_script' ),
);

$assert( false !== has_action( 'wp_ajax_save_h5p_question_xAPI_statement', $cbs['save'] ), 'A: save AJAX registered.' );
$assert( false !== has_action( 'wp_ajax_check_h5p_question_answered', $cbs['check'] ), 'A: check AJAX registered.' );
$assert( false !== has_action( 'tutor_quiz/attempt_deleted', $cbs['deleted'] ), 'A: attempt_deleted registered.' );
$assert( false !== has_filter( 'tutor_filter_update_before_question_mark', $cbs['total'] ), 'A: filter_total_marks registered.' );
$assert( false !== has_filter( 'tutor_filter_quiz_total_marks', $cbs['earned'] ), 'A: filter_total_quiz_marks registered.' );
$assert( false !== has_filter( 'tutor_filter_quiz_answer_data', $cbs['answer'] ), 'A: filter_quiz_answer_data registered.' );
$assert( false !== has_action( 'tutor_quiz/body/before', $cbs['enqueue'] ), 'A: enqueue registered.' );
$assert( false !== has_action( 'h5p_alter_library_scripts', $cbs['inject'] ), 'A: D31 inject registered.' );

// --- B/C seam: register_bridge early-return tied to should_register_learner_bridge ---
$src_path = TUTORPRESS_PATH . 'includes/tutorlms/overrides/class-tutorpress-h5p-learner-bridge.php';
$src      = file_get_contents( $src_path );
$assert( is_string( $src ) && '' !== $src, 'Bridge source must be readable.' );
$assert(
	1 === preg_match(
		'/function\s+register_bridge\s*\(\s*\)\s*\{.*?should_register_learner_bridge\s*\([^)]*\)\s*;?\s*.*?\{\s*return\s*;/s',
		$src
	),
	'register_bridge must early-return when should_register_learner_bridge fails.'
);

echo 'PASS: Step 10a gates (env A; pure A/B/C; live A hooks; B/C source seam).' . "\n";
exit( 0 );
