<?php
/**
 * Thin Quiz Summary wrapper: restore student H5P `$answers`, then include Core summary.php.
 *
 * Runs in tutor_load_template() extract() scope. Do not resolve Summary by name.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

$answers = TutorPress_H5P_Review_Overrides::answers_for_student_summary(
	isset( $answers ) ? $answers : array(),
	TutorPress_H5P_Review_Overrides::unfiltered_answers_for_attempt(
		isset( $attempt_data ) ? $attempt_data : null
	),
	isset( $is_instructor_review ) ? $is_instructor_review : false
);

$tutorpress_core_summary = TutorPress_H5P_Review_Overrides::get_stored_core_summary_path();
if ( is_string( $tutorpress_core_summary ) && '' !== $tutorpress_core_summary ) {
	include $tutorpress_core_summary;
}
