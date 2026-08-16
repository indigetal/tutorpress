<?php
/**
 * Display-time H5P restore for modern student Quiz Summary (A1 / C1).
 *
 * Mutates Summary `$answers` in memory only. Does not restore parent `$questions`,
 * write `given_answer`, or query `tutor_h5p_quiz_result`. Registers
 * `tutor_get_template_path` when WP H5P is on and Pro H5P runtime is absent.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_H5P_Review_Overrides {

	/** Live Core name after dots → slashes (required match). */
	const SUMMARY_TEMPLATE_SLASH = 'shared/components/quiz/attempt-details/summary';

	/** Dotted Summary name (defensive only). */
	const SUMMARY_TEMPLATE_DOTTED = 'shared.components.quiz.attempt-details.summary';

	/**
	 * Incoming Core summary.php path from tutor_get_template_path.
	 *
	 * @var string
	 */
	private static $core_summary_path = '';

	/**
	 * Register WordPress init hook that attaches the Summary path filter at priority 100.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_review_hooks' ), 100 );
	}

	/**
	 * Whether TutorPress should register H5P review-summary hooks.
	 *
	 * True only when WP H5P is on and Pro H5P runtime is absent. Pure helper for
	 * fixtures; live registration passes Addon Checker detectors.
	 *
	 * @since 2.2.0
	 * @param bool $h5p_plugin_active      WordPress H5P plugin active.
	 * @param bool $pro_h5p_runtime_active Pro H5P runtime present (addon ∧ WP H5P).
	 * @return bool
	 */
	public static function should_register_review_hooks( $h5p_plugin_active, $pro_h5p_runtime_active ) {
		return (bool) $h5p_plugin_active && ! (bool) $pro_h5p_runtime_active;
	}

	/**
	 * Attach the Summary template-path filter when the registration predicate passes.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function register_review_hooks() {
		$h5p_plugin_active = TutorPress_Addon_Checker::is_h5p_plugin_active();
		// Mirrors TutorPro\H5P\H5P::is_enabled(): Pro addon enabled AND WP H5P active.
		$pro_h5p_runtime_active = TutorPress_Addon_Checker::is_h5p_enabled() && $h5p_plugin_active;

		if ( ! self::should_register_review_hooks( $h5p_plugin_active, $pro_h5p_runtime_active ) ) {
			return;
		}

		add_filter( 'tutor_get_template_path', array( __CLASS__, 'filter_summary_template_path' ), 10, 2 );
	}

	/**
	 * Restore skipped H5P attempt-answer rows for the student Summary include.
	 *
	 * Instructor review returns `$answers` unchanged. Student path walks `$unfiltered`
	 * in existing order: keep `question_type === 'h5p'`; other types keep only when
	 * Core would not skip them. Does not mutate `given_answer` or read result tables.
	 *
	 * @since 2.2.0
	 * @param mixed $answers               Skip-filtered list already prepared for Summary.
	 * @param mixed $unfiltered            JOIN rows from `QuizModel::get_quiz_answers_by_attempt_id`.
	 * @param mixed $is_instructor_review  Instructor-review flag from the parent template.
	 * @return mixed
	 */
	public static function answers_for_student_summary( $answers, $unfiltered, $is_instructor_review ) {
		if ( $is_instructor_review ) {
			return $answers;
		}

		if ( ! is_array( $unfiltered ) ) {
			return $answers;
		}

		$kept = array();

		foreach ( $unfiltered as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}

			$type = isset( $row->question_type ) ? (string) $row->question_type : '';
			if ( 'h5p' === $type ) {
				$kept[] = $row;
				continue;
			}

			if ( ! \Tutor\Models\QuizModel::is_attempt_answer_skipped( $row ) ) {
				$kept[] = $row;
			}
		}

		return array_values( $kept );
	}

	/**
	 * @since 2.2.0
	 * @return string Wrapper path.
	 */
	public static function get_summary_wrapper_path() {
		$base = defined( 'TUTORPRESS_PATH' ) ? TUTORPRESS_PATH : dirname( __DIR__, 3 ) . '/';
		return $base . 'templates/tutorpress/quiz/attempt-details-summary.php';
	}

	/**
	 * @since 2.2.0
	 * @return string Stored Core summary.php path.
	 */
	public static function get_stored_core_summary_path() {
		return self::$core_summary_path;
	}

	/**
	 * Swap Core Summary path for the wrapper; remember the Core file.
	 *
		 * Identity guard if incoming is already the wrapper.
	 *
	 * @since 2.2.0
	 * @param string $template_location Resolved template path.
	 * @param string $template          Name after Core's pre-filter str_replace.
	 * @return string
	 */
	public static function filter_summary_template_path( $template_location, $template ) {
		$wrapper = self::get_summary_wrapper_path();

		if ( is_string( $template_location ) && $template_location === $wrapper ) {
			return $template_location;
		}

		$name = is_string( $template ) ? $template : '';
		if ( self::SUMMARY_TEMPLATE_SLASH !== $name && self::SUMMARY_TEMPLATE_DOTTED !== $name ) {
			return $template_location;
		}

		if ( is_string( $template_location ) && '' !== $template_location ) {
			self::$core_summary_path = $template_location;
		}

		return $wrapper;
	}

	/**
	 * Unfiltered JOIN rows for the same attempt already on the page.
	 *
	 * @since 2.2.0
	 * @param mixed $attempt_data Attempt row object from the parent template.
	 * @return array
	 */
	public static function unfiltered_answers_for_attempt( $attempt_data ) {
		$attempt_id = 0;
		if ( is_object( $attempt_data ) && isset( $attempt_data->attempt_id ) ) {
			$attempt_id = absint( $attempt_data->attempt_id );
		}

		if ( $attempt_id <= 0 ) {
			return array();
		}

		$rows = \Tutor\Models\QuizModel::get_quiz_answers_by_attempt_id( $attempt_id );
		return is_array( $rows ) ? $rows : array();
	}
}
