<?php
/**
 * Step 8 WP-H5P-off registration absence probe.
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

$out = array(
	'h5p_plugin'                  => $h5p_plugin ? 1 : 0,
	'pro_h5p_addon'               => $pro_addon ? 1 : 0,
	'should_register_false_false' => TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( false, false ) ? 1 : 0,
	'should_register_false_true'  => TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( false, true ) ? 1 : 0,
	'should_register_live'        => TutorPress_H5P_Runtime_Overrides::should_register_runtime_hooks( $h5p_plugin, $pro_addon && $h5p_plugin ) ? 1 : 0,
	'tp_has_filter_template'      => has_filter( 'tutor_filter_quiz_question_template', $cbs['template'] ),
	'tp_has_filter_description'   => has_filter( 'tutor_filter_quiz_question_description', $cbs['description'] ),
	'tp_has_action_desc_render'   => has_action( 'tutor_quiz_question_desc_render', $cbs['desc_render'] ),
	'tp_has_action_after_answers' => has_action( 'tutor_quiz_question_after_answers', $cbs['after_answers'] ),
	'tp_has_action_require_file'  => has_action( 'tutor_require_question_answer_file', $cbs['require_file'] ),
	'tp_has_filter_kses_iframe'   => has_filter( 'wp_kses_allowed_html', $cbs['kses_iframe'] ),
	'tp_has_action_kses_clear'    => has_action( 'tutor_quiz_question_after_answers', $cbs['kses_clear'] ),
);

$tp_absent = (
	false === $out['tp_has_filter_template']
	&& false === $out['tp_has_filter_description']
	&& false === $out['tp_has_action_desc_render']
	&& false === $out['tp_has_action_after_answers']
	&& false === $out['tp_has_action_require_file']
	&& false === $out['tp_has_filter_kses_iframe']
	&& false === $out['tp_has_action_kses_clear']
);

$out['tutorpress_callbacks_absent'] = $tp_absent ? 1 : 0;
$out['wp_off_agent_pass']             = (
	0 === (int) $out['h5p_plugin']
	&& 0 === (int) $out['should_register_false_false']
	&& 0 === (int) $out['should_register_false_true']
	&& 0 === (int) $out['should_register_live']
	&& 1 === (int) $out['tutorpress_callbacks_absent']
) ? 1 : 0;

echo wp_json_encode( $out, JSON_PRETTY_PRINT ) . "\n";
