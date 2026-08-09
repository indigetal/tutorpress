<?php
/**
 * Step 8 R1-A environment / hook registration probe.
 * wp eval-file .../tests/compatibility/probe-h5p-runtime-r1a.php
 */

$cbs = array(
	'template'      => array( 'TutorPress_H5P_Runtime_Overrides', 'filter_question_template' ),
	'description'   => array( 'TutorPress_H5P_Runtime_Overrides', 'allow_h5p_question_description' ),
	'desc_render'   => array( 'TutorPress_H5P_Runtime_Overrides', 'render_question_description' ),
	'after_answers' => array( 'TutorPress_H5P_Runtime_Overrides', 'register_h5p_question_input_field' ),
	'require_file'  => array( 'TutorPress_H5P_Runtime_Overrides', 'require_h5p_answer_file' ),
	'kses_iframe'   => array( 'TutorPress_H5P_Runtime_Overrides', 'allow_h5p_iframe_html' ),
	'kses_clear'    => array( 'TutorPress_H5P_Runtime_Overrides', 'clear_h5p_iframe_kses_after_question' ),
);

$h5p_plugin = TutorPress_Addon_Checker::is_h5p_plugin_active();
$pro_addon  = TutorPress_Addon_Checker::is_h5p_enabled();
$has_pro    = function_exists( 'tutor' ) && tutor() && (bool) tutor()->has_pro;
$pro_runtime = $pro_addon && $h5p_plugin;

$pro_is_enabled = null;
if ( class_exists( 'TutorPro\H5P\H5P' ) ) {
	$pro_is_enabled = \TutorPro\H5P\H5P::is_enabled() ? 1 : 0;
}

// R1-A render probe: filter + desc_render single emit for content id 4.
$desc_r1a = TutorPress_H5P_Runtime_Overrides::filter_question_description( '4', true );
$markup   = "<div class='tutor-p2 tutor-text-secondary'>{$desc_r1a}</div>";
ob_start();
TutorPress_H5P_Runtime_Overrides::render_question_description( $markup, (object) array( 'question_id' => 100 ) );
$emitted = ob_get_clean();

$out = array(
	'h5p_plugin'              => $h5p_plugin ? 1 : 0,
	'pro_h5p_addon'           => $pro_addon ? 1 : 0,
	'has_pro'                 => $has_pro ? 1 : 0,
	'should_register'         => TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( $h5p_plugin, $pro_runtime ) ? 1 : 0,
	'pro_h5p_is_enabled'      => $pro_is_enabled,
	'has_filter_template'     => has_filter( 'tutor_filter_quiz_question_template', $cbs['template'] ),
	'has_filter_description'  => has_filter( 'tutor_filter_quiz_question_description', $cbs['description'] ),
	'has_action_desc_render'  => has_action( 'tutor_quiz_question_desc_render', $cbs['desc_render'] ),
	'has_action_after_answers'=> has_action( 'tutor_quiz_question_after_answers', $cbs['after_answers'] ),
	'has_action_require_file' => has_action( 'tutor_require_question_answer_file', $cbs['require_file'] ),
	'has_filter_kses_iframe'  => has_filter( 'wp_kses_allowed_html', $cbs['kses_iframe'] ),
	'has_action_kses_clear'   => has_action( 'tutor_quiz_question_after_answers', $cbs['kses_clear'] ),
	'r1a_filter_shortcode'    => $desc_r1a,
	'r1a_emit_has_literal_shortcode' => ( false !== strpos( $emitted, '[h5p id=' ) ) ? 1 : 0,
	'r1a_emit_len'            => strlen( (string) $emitted ),
	'r1a_emit_has_h5p_marker' => ( false !== stripos( (string) $emitted, 'h5p' ) ) ? 1 : 0,
	's7_2_data_content_id'    => ( false !== strpos( (string) $emitted, 'data-content-id' ) ) ? 1 : 0,
	's7_2_h5p_content_class'  => ( false !== strpos( (string) $emitted, 'h5p-content' ) ) ? 1 : 0,
);

echo wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n";
