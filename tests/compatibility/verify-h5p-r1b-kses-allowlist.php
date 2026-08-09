<?php
/**
 * Verify R1-B H5P iframe kses allowlist (Decisions D2–D5, D11 A′).
 *
 * Proves armed-window survival across multi-tag wp_kses_post, cleanup consume
 * via tutor_quiz_question_after_answers, hard-exclude disarm, security
 * stripping, non-post behavior, callback metadata (kses + clear),
 * merge-not-replace, and description→markup→wp_kses_post→cleanup integration.
 * Does not claim live take-quiz interactivity or Phase 2 (quiz.js / xAPI / marks).
 *
 * Usage (from WordPress root):
 *   wp eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-r1b-kses-allowlist.php
 */

$fail = static function ( $message ) {
	if ( class_exists( 'TutorPress_H5P_Runtime_Overrides' ) ) {
		TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();
	}
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

// Evidence pack Content 2 shape (pre-kses H5P shortcode output).
$evidence_html = '<div class="h5p-iframe-wrapper"><iframe id="h5p-iframe-2" class="h5p-iframe" data-content-id="2" style="height:1px" src="about:blank" frameBorder="0" scrolling="no" title="Test True/False Question"></iframe></div>';

$kses_cb     = array( 'TutorPress_H5P_Runtime_Overrides', 'allow_h5p_iframe_html' );
$clear_cb    = array( 'TutorPress_H5P_Runtime_Overrides', 'clear_h5p_iframe_kses_after_question' );
$added_kses  = false;
$added_clear = false;

$cleanup_hooks = static function () use ( &$added_kses, &$added_clear, $kses_cb, $clear_cb ) {
	if ( $added_kses ) {
		remove_filter( 'wp_kses_allowed_html', $kses_cb, 10 );
		$added_kses = false;
	}
	if ( $added_clear ) {
		remove_action( 'tutor_quiz_question_after_answers', $clear_cb, 999 );
		$added_clear = false;
	}
	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();
};

try {
	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();

	if ( false === has_filter( 'wp_kses_allowed_html', $kses_cb ) ) {
		add_filter( 'wp_kses_allowed_html', $kses_cb, 10, 2 );
		$added_kses = true;
	}

	// 1. Armed → iframe + D2 attrs + height:1px survive wp_kses_post (flag may remain armed).
	TutorPress_H5P_Runtime_Overrides::arm_h5p_iframe_kses();
	$armed_out = wp_kses_post( $evidence_html );
	$assert(
		false !== strpos( $armed_out, '<iframe' ),
		'1: Armed wp_kses_post must retain iframe; got ' . var_export( $armed_out, true )
	);
	$assert(
		false !== strpos( $armed_out, 'h5p-iframe-wrapper' ),
		'1: Wrapper must remain non-empty around iframe.'
	);
	foreach ( array( 'id=', 'class=', 'data-content-id=', 'style=', 'src=', 'frameborder=', 'scrolling=', 'title=' ) as $attr_needle ) {
		$assert(
			false !== stripos( $armed_out, $attr_needle ),
			'1: Expected D2 attr fragment ' . $attr_needle . ' in output; got ' . var_export( $armed_out, true )
		);
	}
	$assert(
		false !== strpos( $armed_out, 'height:1px' ) || false !== strpos( $armed_out, 'height: 1px' ),
		'1: height:1px must survive (D3); got ' . var_export( $armed_out, true )
	);

	// 2. Disarmed → iframe stripped (baseline FAIL mode).
	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();
	$disarmed_out = wp_kses_post( $evidence_html );
	$assert(
		false === strpos( $disarmed_out, '<iframe' ),
		'2: Disarmed wp_kses_post must strip iframe; got ' . var_export( $disarmed_out, true )
	);
	$assert(
		false !== strpos( $disarmed_out, 'h5p-iframe-wrapper' ),
		'2: Wrapper div should still survive without iframe.'
	);

	// 3. Window consume via after-answers cleanup (exact mechanism).
	TutorPress_H5P_Runtime_Overrides::arm_h5p_iframe_kses();
	$first = wp_kses_post( $evidence_html );
	$assert( false !== strpos( $first, '<iframe' ), '3: Armed pass must retain iframe.' );

	$clear_was_added = false;
	if ( false === has_action( 'tutor_quiz_question_after_answers', $clear_cb ) ) {
		add_action( 'tutor_quiz_question_after_answers', $clear_cb, 999, 3 );
		$added_clear     = true;
		$clear_was_added = true;
	}
	do_action( 'tutor_quiz_question_after_answers', null, array(), null );
	$assert(
		! TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed(),
		'3: Flag must be disarmed after tutor_quiz_question_after_answers cleanup.'
	);
	$second = wp_kses_post( $evidence_html );
	$assert(
		false === strpos( $second, '<iframe' ),
		'3: After cleanup, wp_kses_post without re-arm must strip iframe; got ' . var_export( $second, true )
	);
	if ( $clear_was_added ) {
		remove_action( 'tutor_quiz_question_after_answers', $clear_cb, 999 );
		$added_clear = false;
	}

	// 4. Hard-exclude via content_save_pre: no merge + disarmed after.
	TutorPress_H5P_Runtime_Overrides::arm_h5p_iframe_kses();
	$exclude_tags_in  = array( 'div' => array( 'class' => true ) );
	$exclude_tags_out = null;
	$exclude_cb       = static function ( $content ) use ( &$exclude_tags_out, $exclude_tags_in ) {
		$exclude_tags_out = TutorPress_H5P_Runtime_Overrides::allow_h5p_iframe_html( $exclude_tags_in, 'post' );
		return $content;
	};
	add_filter( 'content_save_pre', $exclude_cb, 10, 1 );
	apply_filters( 'content_save_pre', 'tutorpress-h5p-kses-exclude-probe' );
	remove_filter( 'content_save_pre', $exclude_cb, 10 );
	$assert( is_array( $exclude_tags_out ), '4: Hard-exclude callback must run.' );
	$assert(
		! isset( $exclude_tags_out['iframe'] ),
		'4: Hard-exclude must not merge iframe; got ' . var_export( $exclude_tags_out, true )
	);
	$assert(
		! TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed(),
		'4: Flag must be disarmed after excluded post attempt.'
	);

	// 5. Security: script / event attr / unlisted attr / javascript: src stripped.
	TutorPress_H5P_Runtime_Overrides::arm_h5p_iframe_kses();
	$hostile = '<div class="h5p-iframe-wrapper"><script>alert(1)</script>'
		. '<iframe id="h5p-iframe-x" class="h5p-iframe" data-content-id="2" style="height:1px" '
		. 'src="javascript:alert(1)" frameBorder="0" scrolling="no" title="Bad" '
		. 'onload="alert(1)" allowfullscreen="true"></iframe></div>';
	$secure_out = wp_kses_post( $hostile );
	$assert(
		false === stripos( $secure_out, '<script' ),
		'5: <script> must be stripped; got ' . var_export( $secure_out, true )
	);
	$assert(
		false === stripos( $secure_out, 'onload=' ),
		'5: onload event attr must be stripped; got ' . var_export( $secure_out, true )
	);
	$assert(
		false === stripos( $secure_out, 'allowfullscreen' ),
		'5: Unlisted iframe attr allowfullscreen must be stripped; got ' . var_export( $secure_out, true )
	);
	$assert(
		false === stripos( $secure_out, 'javascript:' ),
		'5: javascript: src must be sanitized/removed; got ' . var_export( $secure_out, true )
	);
	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();

	// 6. Non-post context: tags unchanged; flag remains armed; then disarm.
	TutorPress_H5P_Runtime_Overrides::arm_h5p_iframe_kses();
	$non_post_in  = array( 'p' => array( 'class' => true ) );
	$non_post_out = TutorPress_H5P_Runtime_Overrides::allow_h5p_iframe_html( $non_post_in, 'title' );
	$assert(
		$non_post_in === $non_post_out,
		'6: Non-post must return tags unchanged; got ' . var_export( $non_post_out, true )
	);
	$assert(
		TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed(),
		'6: Non-post must not disarm the flag.'
	);
	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();

	// 7. Callback metadata: kses @10 and clear @999 when registered; absent when removed.
	$kses_priority = has_filter( 'wp_kses_allowed_html', $kses_cb );
	$assert(
		10 === (int) $kses_priority,
		'7: has_filter(wp_kses_allowed_html, allow_h5p_iframe_html) must be 10; got ' . var_export( $kses_priority, true )
	);

	$clear_was_present = ( false !== has_action( 'tutor_quiz_question_after_answers', $clear_cb ) );
	if ( ! $clear_was_present ) {
		add_action( 'tutor_quiz_question_after_answers', $clear_cb, 999, 3 );
		$added_clear = true;
	}
	$clear_priority = has_action( 'tutor_quiz_question_after_answers', $clear_cb );
	$assert(
		999 === (int) $clear_priority,
		'7: has_action(tutor_quiz_question_after_answers, clear_h5p_iframe_kses_after_question) must be 999; got ' . var_export( $clear_priority, true )
	);

	remove_filter( 'wp_kses_allowed_html', $kses_cb, 10 );
	$added_kses = false;
	remove_action( 'tutor_quiz_question_after_answers', $clear_cb, 999 );
	$added_clear = false;
	$assert(
		false === has_filter( 'wp_kses_allowed_html', $kses_cb ),
		'7: After remove_filter, kses callback must be absent.'
	);
	$assert(
		false === has_action( 'tutor_quiz_question_after_answers', $clear_cb ),
		'7: After remove_action, clear callback must be absent.'
	);

	add_filter( 'wp_kses_allowed_html', $kses_cb, 10, 2 );
	$added_kses = true;
	if ( $clear_was_present ) {
		// Restore live registration if this env already had it under the R1-B gate.
		add_action( 'tutor_quiz_question_after_answers', $clear_cb, 999, 3 );
	}

	// 8. Merge-not-replace: pre-existing iframe attrs preserved alongside D2.
	$preexisting = array(
		'div'    => array( 'class' => true ),
		'iframe' => array(
			'allowfullscreen' => true,
		),
	);
	$merged = TutorPress_H5P_Runtime_Overrides::merge_h5p_iframe_allowed_html( $preexisting );
	$assert(
		isset( $merged['iframe']['allowfullscreen'] ) && true === $merged['iframe']['allowfullscreen'],
		'8: Pre-existing allowfullscreen must survive merge.'
	);
	foreach ( array( 'id', 'class', 'data-content-id', 'style', 'src', 'frameborder', 'scrolling', 'title' ) as $key ) {
		$assert(
			isset( $merged['iframe'][ $key ] ) && true === $merged['iframe'][ $key ],
			'8: D2 key ' . $key . ' must be present after merge.'
		);
	}

	// 9. Integration: description expand → Core-like markup → wp_kses_post → cleanup.
	if ( ! class_exists( 'H5P_Plugin' ) ) {
		$cleanup_hooks();
		$fail( 'H5P_Plugin unavailable; cannot assert integration (missing required env).' );
	}

	global $wpdb;
	$content_id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$wpdb->prefix}h5p_contents WHERE id > %d ORDER BY id ASC LIMIT 1",
			0
		)
	);
	if ( ! $content_id ) {
		$cleanup_hooks();
		$fail( 'No h5p_contents row found; create at least one H5P content for integration asserts.' );
	}

	$content_id = (string) absint( $content_id );
	$has_pro    = function_exists( 'tutor' ) && tutor() && (bool) tutor()->has_pro;

	TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();

	if ( ! $has_pro ) {
		$expanded = TutorPress_H5P_Runtime_Overrides::allow_h5p_question_description( $content_id );
		$assert(
			is_string( $expanded ) && $expanded !== $content_id && '' !== $expanded,
			'9: Live allow_h5p_question_description must expand valid H5P id; got ' . var_export( $expanded, true )
		);
		$assert(
			TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed(),
			'9: Live R1-B path must arm flag when !has_pro and description expands.'
		);
	} else {
		$expanded = TutorPress_H5P_Runtime_Overrides::filter_question_description( $content_id, false );
		$assert(
			is_string( $expanded ) && '' !== $expanded && '[h5p id=' . absint( $content_id ) . ']' !== $expanded,
			'9: filter_question_description(id, false) must return expanded HTML; got ' . var_export( $expanded, true )
		);
		TutorPress_H5P_Runtime_Overrides::arm_h5p_iframe_kses();
	}

	$markup    = '<div class="tutor-p2 tutor-text-secondary">' . $expanded . '</div>';
	$post_kses = wp_kses_post( $markup );
	$assert(
		false !== strpos( $post_kses, '<iframe' ),
		'9: wp_kses_post on Core-like markup must retain iframe; got ' . var_export( $post_kses, true )
	);

	$clear_was_added = false;
	if ( false === has_action( 'tutor_quiz_question_after_answers', $clear_cb ) ) {
		add_action( 'tutor_quiz_question_after_answers', $clear_cb, 999, 3 );
		$added_clear     = true;
		$clear_was_added = true;
	}
	do_action( 'tutor_quiz_question_after_answers', null, array(), null );
	$assert(
		! TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed(),
		'9: Flag must be disarmed after integration cleanup do_action.'
	);
	if ( $clear_was_added ) {
		remove_action( 'tutor_quiz_question_after_answers', $clear_cb, 999 );
		$added_clear = false;
	}

	$cleanup_hooks();

	fwrite(
		STDOUT,
		"PASS: H5P R1-B kses allowlist A′ (survival, after-answers cleanup, hard-exclude disarm, security, non-post, metadata, merge, integration).\n"
		. "Note: This fixture does not claim live take-quiz interactivity or Phase 2 PASS.\n"
	);
} catch ( Throwable $e ) {
	$cleanup_hooks();
	$fail( $e->getMessage() );
}
