<?php
/**
 * R1-B product-target probe (has_pro false, WP H5P on) + A′ kses window.
 *
 * Proves env, kses/clear registration, live description arm → wp_kses_post
 * iframe retention, and after-answers cleanup disarm. Does not claim browser
 * interactivity or Phase 2 (quiz.js / xAPI / marks).
 *
 * Usage (from WordPress root):
 *   wp eval-file wp-content/plugins/tutorpress/tests/compatibility/probe-h5p-runtime-r1b.php
 */

$cbs = array(
	'template'      => array( 'TutorPress_H5P_Runtime_Overrides', 'filter_question_template' ),
	'description'   => array( 'TutorPress_H5P_Runtime_Overrides', 'allow_h5p_question_description' ),
	'desc_render'   => array( 'TutorPress_H5P_Runtime_Overrides', 'render_question_description' ),
	'kses_iframe'   => array( 'TutorPress_H5P_Runtime_Overrides', 'allow_h5p_iframe_html' ),
	'kses_clear'    => array( 'TutorPress_H5P_Runtime_Overrides', 'clear_h5p_iframe_kses_after_question' ),
);

$h5p_plugin  = TutorPress_Addon_Checker::is_h5p_plugin_active();
$pro_addon   = TutorPress_Addon_Checker::is_h5p_enabled();
$has_pro     = ( function_exists( 'tutor' ) && tutor() && (bool) tutor()->has_pro );
$pro_runtime = $pro_addon && $h5p_plugin;

$content_id = '4';
$r1b_filter = TutorPress_H5P_Runtime_Overrides::filter_question_description( $content_id, false );
$r1a_filter = TutorPress_H5P_Runtime_Overrides::filter_question_description( $content_id, true );

$kses_priority  = has_filter( 'wp_kses_allowed_html', $cbs['kses_iframe'] );
$clear_priority = has_action( 'tutor_quiz_question_after_answers', $cbs['kses_clear'] );

// Live arm path when !has_pro; else helper expand + explicit arm (documented fallback).
TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();
$arm_path = 'none';
$expanded = '';

if ( ! $has_pro ) {
	$expanded = TutorPress_H5P_Runtime_Overrides::allow_h5p_question_description( $content_id );
	$arm_path = 'live_allow_h5p_question_description';
} else {
	$expanded = TutorPress_H5P_Runtime_Overrides::filter_question_description( $content_id, false );
	TutorPress_H5P_Runtime_Overrides::arm_h5p_iframe_kses();
	$arm_path = 'helper_filter_plus_explicit_arm';
}

$armed_after_desc = TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed() ? 1 : 0;
$markup           = '<div class="tutor-p2 tutor-text-secondary">' . $expanded . '</div>';
$post_kses        = wp_kses_post( $markup );
$post_kses_has_iframe = ( false !== strpos( (string) $post_kses, '<iframe' ) ) ? 1 : 0;

$added_clear = false;
if ( false === has_action( 'tutor_quiz_question_after_answers', $cbs['kses_clear'] ) ) {
	add_action( 'tutor_quiz_question_after_answers', $cbs['kses_clear'], 999, 3 );
	$added_clear = true;
}
do_action( 'tutor_quiz_question_after_answers', null, array(), null );
$disarmed_after_cleanup = TutorPress_H5P_Runtime_Overrides::is_h5p_iframe_kses_armed() ? 0 : 1;
if ( $added_clear ) {
	remove_action( 'tutor_quiz_question_after_answers', $cbs['kses_clear'], 999 );
}

TutorPress_H5P_Runtime_Overrides::disarm_h5p_iframe_kses();

$out = array(
	'h5p_plugin'                   => $h5p_plugin ? 1 : 0,
	'pro_h5p_addon'                => $pro_addon ? 1 : 0,
	'has_pro'                      => $has_pro ? 1 : 0,
	'tutor_pro_active'             => ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'tutor-pro/tutor-pro.php' ) ) ? 1 : 0,
	'should_register'              => TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( $h5p_plugin, $pro_runtime ) ? 1 : 0,
	'tp_has_filter_template'       => has_filter( 'tutor_filter_quiz_question_template', $cbs['template'] ),
	'tp_has_filter_description'    => has_filter( 'tutor_filter_quiz_question_description', $cbs['description'] ),
	'tp_has_action_desc_render'    => has_action( 'tutor_quiz_question_desc_render', $cbs['desc_render'] ),
	'has_filter_kses_iframe'       => $kses_priority,
	'has_action_kses_clear'        => $clear_priority,
	'r1b_filter_is_bare_shortcode' => ( '[h5p id=4]' === $r1b_filter ) ? 1 : 0,
	'r1b_filter_len'               => strlen( (string) $r1b_filter ),
	'r1b_filter_has_html'          => ( false !== strpos( (string) $r1b_filter, '<' ) ) ? 1 : 0,
	'r1b_filter_has_iframe'        => ( false !== strpos( (string) $r1b_filter, '<iframe' ) ) ? 1 : 0,
	'r1a_still_shortcode'          => ( '[h5p id=4]' === $r1a_filter ) ? 1 : 0,
	'arm_path'                     => $arm_path,
	'armed_after_desc'             => $armed_after_desc,
	'post_kses_has_iframe'         => $post_kses_has_iframe,
	'disarmed_after_cleanup'       => $disarmed_after_cleanup,
	'clear_temp_add_action_used'   => $added_clear ? 1 : 0,
);

$out['r1b_env_ok'] = (
	1 === (int) $out['h5p_plugin']
	&& 0 === (int) $out['has_pro']
	&& 0 === (int) $out['tutor_pro_active']
	&& 1 === (int) $out['should_register']
	&& false !== $out['tp_has_filter_template']
	&& false !== $out['tp_has_filter_description']
	&& 0 === (int) $out['r1b_filter_is_bare_shortcode']
	&& 1 === (int) $out['r1b_filter_has_html']
) ? 1 : 0;

$out['r1b_agent_pass'] = (
	1 === (int) $out['r1b_env_ok']
	&& 10 === (int) $out['has_filter_kses_iframe']
	&& 999 === (int) $out['has_action_kses_clear']
	&& 1 === (int) $out['r1b_filter_has_iframe']
	&& 1 === (int) $out['armed_after_desc']
	&& 1 === (int) $out['post_kses_has_iframe']
	&& 1 === (int) $out['disarmed_after_cleanup']
	&& 'live_allow_h5p_question_description' === $out['arm_path']
) ? 1 : 0;

echo wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n";
