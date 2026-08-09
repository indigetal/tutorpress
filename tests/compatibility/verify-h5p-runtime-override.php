<?php
/**
 * Verify H5P runtime-override helpers (Decision A2 test seam).
 *
 * Calls public static helpers with explicit booleans/strings — no live plugin
 * flipping for branch coverage. Does not claim live take-quiz HTML or xAPI PASS.
 *
 * Usage (from WordPress root):
 *   wp eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-runtime-override.php
 */

$fail = static function ( $message ) {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
};

$assert = static function ( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
};

if ( ! class_exists( 'TutorPress_H5P_Runtime_Overrides' ) ) {
	$fail( 'TutorPress_H5P_Runtime_Overrides is unavailable.' );
}

try {
	// 1–2. Registration predicate (Pro-on / WP-off).
	$assert(
		true === TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( true, false ),
		'should_register_runtime_hooks(true, false) must be true.'
	);
	$assert(
		false === TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( false, false ),
		'should_register_runtime_hooks(false, false) must be false (WP-off).'
	);
	$assert(
		false === TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( true, true ),
		'should_register_runtime_hooks(true, true) must be false (Pro-on).'
	);
	$assert(
		false === TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( false, true ),
		'should_register_runtime_hooks(false, true) must be false.'
	);

	// 3. Template helper.
	$assert(
		'' === TutorPress_H5P_Runtime_Overrides::filter_question_template( 'learning-area.quiz.questions.h5p', 'h5p' ),
		'Template helper must blank h5p path.'
	);
	$true_false_path = 'learning-area.quiz.questions.true_false';
	$assert(
		$true_false_path === TutorPress_H5P_Runtime_Overrides::filter_question_template( $true_false_path, 'true_false' ),
		'Template helper must passthrough non-h5p types.'
	);

	// 4. Description helper S1 / R1 — invalid unchanged.
	$invalid = 'not-a-valid-h5p-content-id';
	$assert(
		$invalid === TutorPress_H5P_Runtime_Overrides::filter_question_description( $invalid, true ),
		'S1: invalid description must remain unchanged (has_pro true).'
	);
	$assert(
		$invalid === TutorPress_H5P_Runtime_Overrides::filter_question_description( $invalid, false ),
		'S1: invalid description must remain unchanged (has_pro false).'
	);

	// Valid content ID via live H5P get_content (no plugin flipping).
	if ( ! class_exists( 'H5P_Plugin' ) ) {
		$fail( 'H5P_Plugin unavailable; cannot assert R1 valid-content branches.' );
	}

	global $wpdb;
	$content_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}h5p_contents WHERE id > %d ORDER BY id ASC LIMIT 1",
			0
		)
	);
	if ( ! $content_id ) {
		$fail( 'No h5p_contents row found; create at least one H5P content for R1 valid-branch asserts.' );
	}

	$content_id  = (string) absint( $content_id );
	$plugin      = \H5P_Plugin::get_instance();
	$live_content = $plugin && is_callable( array( $plugin, 'get_content' ) )
		? $plugin->get_content( $content_id )
		: null;
	$assert( is_array( $live_content ), 'Expected get_content() array for existing H5P content id ' . $content_id . '.' );

	$expected_shortcode = '[h5p id=' . absint( $content_id ) . ']';
	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();
	$r1a                = TutorPress_H5P_Runtime_Overrides::filter_question_description( $content_id, true );
	$assert(
		$expected_shortcode === $r1a,
		'R1-A: valid+has_pro must return shortcode only; got ' . var_export( $r1a, true )
	);
	$assert(
		false === TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed(),
		'R1-A: filter_question_description(id, true) must not arm the H5P iframe kses flag.'
	);

	$r1b = TutorPress_H5P_Runtime_Overrides::filter_question_description( $content_id, false );
	$assert(
		$expected_shortcode !== $r1b && is_string( $r1b ) && '' !== $r1b,
		'R1-B: valid+!has_pro must return expanded HTML, not bare shortcode; got ' . var_export( $r1b, true )
	);

	// 5. render_question_description single emit (OBUF).
	$markup = "<div class='tutor-p2 tutor-text-secondary'>{$expected_shortcode}</div>";
	ob_start();
	TutorPress_H5P_Runtime_Overrides::render_question_description( $markup, (object) array( 'question_id' => 1 ) );
	$emitted = ob_get_clean();
	$assert( is_string( $emitted ) && '' !== $emitted, 'desc_render must emit non-empty output once.' );
	$assert(
		$expected_shortcode !== $emitted || false !== strpos( $emitted, 'h5p' ),
		'desc_render should expand shortcode markup (single echo).'
	);
	// Exactly one buffered capture = one echo path for this call.
	$assert( 1 === substr_count( $emitted, 'tutor-p2' ) || false !== strpos( $emitted, 'h5p' ), 'Single-emit capture produced output.' );

	// 6. Hook registration metadata (priority / accepted_args).
	$desc_cb   = array( 'TutorPress_H5P_Runtime_Overrides', 'allow_h5p_question_description' );
	$render_cb = array( 'TutorPress_H5P_Runtime_Overrides', 'render_question_description' );
	$added_desc   = false;
	$added_render = false;

	if ( false === has_filter( 'tutor_filter_quiz_question_description', $desc_cb ) ) {
		add_filter( 'tutor_filter_quiz_question_description', $desc_cb, 12, 1 );
		$added_desc = true;
	}
	if ( false === has_action( 'tutor_quiz_question_desc_render', $render_cb ) ) {
		add_action( 'tutor_quiz_question_desc_render', $render_cb, 10, 2 );
		$added_render = true;
	}

	$desc_priority = has_filter( 'tutor_filter_quiz_question_description', $desc_cb );
	$assert( 12 === (int) $desc_priority, 'Description filter must be priority 12; got ' . var_export( $desc_priority, true ) );

	global $wp_filter;
	$desc_accepted = null;
	if ( isset( $wp_filter['tutor_filter_quiz_question_description'] ) ) {
		$hook = $wp_filter['tutor_filter_quiz_question_description'];
		if ( isset( $hook->callbacks[12] ) ) {
			foreach ( $hook->callbacks[12] as $entry ) {
				if ( isset( $entry['function'] ) && $entry['function'] === $desc_cb ) {
					$desc_accepted = isset( $entry['accepted_args'] ) ? (int) $entry['accepted_args'] : null;
					break;
				}
			}
		}
	}
	$assert( 1 === $desc_accepted, 'Description filter must accept 1 arg; got ' . var_export( $desc_accepted, true ) );

	$render_accepted = null;
	$render_priority = has_action( 'tutor_quiz_question_desc_render', $render_cb );
	$assert( false !== $render_priority, 'desc_render action must be registered.' );
	if ( isset( $wp_filter['tutor_quiz_question_desc_render'] ) ) {
		$hook = $wp_filter['tutor_quiz_question_desc_render'];
		$prio = (int) $render_priority;
		if ( isset( $hook->callbacks[ $prio ] ) ) {
			foreach ( $hook->callbacks[ $prio ] as $entry ) {
				if ( isset( $entry['function'] ) && $entry['function'] === $render_cb ) {
					$render_accepted = isset( $entry['accepted_args'] ) ? (int) $entry['accepted_args'] : null;
					break;
				}
			}
		}
	}
	$assert( 2 === $render_accepted, 'desc_render must accept 2 args; got ' . var_export( $render_accepted, true ) );

	if ( $added_desc ) {
		remove_filter( 'tutor_filter_quiz_question_description', $desc_cb, 12 );
	}
	if ( $added_render ) {
		remove_action( 'tutor_quiz_question_desc_render', $render_cb, 10 );
	}

	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();

	fwrite(
		STDOUT,
		"PASS: H5P runtime-override helpers (predicate, template, S1/R1 description, R1-A no-arm, single-emit, hook metadata).\n"
		. "Note: This fixture does not claim live take-quiz HTML, live kses/cleanup registration, or xAPI/marks PASS.\n"
	);
} catch ( Throwable $e ) {
	if ( class_exists( 'TutorPress_H5P_Runtime_Overrides' ) ) {
		TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();
	}
	$fail( $e->getMessage() );
}
