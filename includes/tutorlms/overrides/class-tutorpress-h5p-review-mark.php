<?php
/**
 * H5P instructor mark-as, Overview column strip, and legacy View shell.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_H5P_Review_Mark {

	/**
	 * Handle tutor_quiz_review_mark_as for H5P questions.
	 *
	 * Non-h5p: return $mark_as so Core writes. H5P: write Pro columns then
	 * return '' so Core classes/Quiz.php:1387-1434 does not run.
	 *
	 * @since 2.2.0
	 * @param mixed $mark_as           Review status (correct|incorrect).
	 * @param mixed $attempt_answer_id Attempt-answer id.
	 * @param mixed $attempt_id        Attempt id.
	 * @param mixed $question          Question object from QuizModel::get_quiz_question_by_id.
	 * @return mixed
	 */
	public static function update_h5p_quiz_answer_review_status( $mark_as, $attempt_answer_id, $attempt_id, $question ) {
		if ( ! is_object( $question ) || ! isset( $question->question_type ) || 'h5p' !== $question->question_type ) {
			return $mark_as;
		}

		$attempt_id        = absint( $attempt_id );
		$attempt_answer_id = absint( $attempt_answer_id );

		if ( ! tutor_utils()->can_user_manage( 'attempt', $attempt_id ) ) {
			return '';
		}

		if ( $attempt_answer_id && ! tutor_utils()->can_user_manage( 'attempt_answer', $attempt_answer_id ) ) {
			return '';
		}

		global $wpdb;

		$attempt        = tutor_utils()->get_attempt( $attempt_id );
		$attempt_answer = \TUTOR\Quiz::get_attempt_answer( $attempt_answer_id );
		if ( ! is_object( $attempt ) || ! is_object( $attempt_answer ) ) {
			return '';
		}

		$user_id        = isset( $attempt->user_id ) ? absint( $attempt->user_id ) : 0;
		$previous_ans   = isset( $attempt_answer->is_correct ) ? $attempt_answer->is_correct : null;
		$answers_table  = $wpdb->prefix . 'tutor_quiz_attempt_answers';
		$attempts_table = $wpdb->prefix . 'tutor_quiz_attempts';

		if ( 'correct' === $mark_as ) {
			$wpdb->update(
				$answers_table,
				array(
					'achieved_mark' => $attempt_answer->question_mark,
					'is_correct'    => 1,
				),
				array( 'attempt_answer_id' => $attempt_answer_id )
			);

			if ( 0 == $previous_ans || null == $previous_ans ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				$earned_marks = (float) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT SUM(achieved_mark) FROM {$answers_table} WHERE quiz_attempt_id = %d",
						$attempt_id
					)
				);
				$wpdb->update(
					$attempts_table,
					array(
						'earned_marks'         => $earned_marks,
						'is_manually_reviewed' => 1,
						'manually_reviewed_at' => date( 'Y-m-d H:i:s', tutor_time() ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					),
					array( 'attempt_id' => $attempt_id )
				);
			}
		} elseif ( 'incorrect' === $mark_as ) {
			$question_id = isset( $question->question_id ) ? (int) $question->question_id : 0;
			$result      = TutorPress_H5P_Review_Results::get_quiz_result( $question_id, $user_id, $attempt_id );
			$raw_score   = 0;
			$max_score   = 0;
			if ( is_object( $result ) ) {
				$raw_score = isset( $result->raw_score ) ? (int) $result->raw_score : 0;
				$max_score = isset( $result->max_score ) ? (int) $result->max_score : 0;
			}

			$wpdb->update(
				$answers_table,
				array(
					'is_correct'    => 0,
					'achieved_mark' => ( $raw_score === $max_score ) ? 0 : (float) $raw_score,
				),
				array( 'attempt_answer_id' => $attempt_answer_id )
			);

			if ( 1 == $previous_ans ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix.
				$earned_marks = (float) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT SUM(achieved_mark) FROM {$answers_table} WHERE quiz_attempt_id = %d",
						$attempt_id
					)
				);
				$wpdb->update(
					$attempts_table,
					array(
						'earned_marks'         => $earned_marks,
						'is_manually_reviewed' => 1,
						'manually_reviewed_at' => date( 'Y-m-d H:i:s', tutor_time() ), // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
					),
					array( 'attempt_id' => $attempt_id )
				);
			}
		}

		return '';
	}

	/**
	 * Drop Overview keys given_answer and correct_answer when the last row is h5p.
	 *
	 * Does not read $answer->given_answer. Does not drop result or manual_review.
	 *
	 * @since 2.2.0
	 * @param mixed $columns Overview column map.
	 * @param mixed $answers Unfiltered attempt-answer rows.
	 * @return mixed
	 */
	public static function filter_columns( $columns, $answers ) {
		$is_h5p = false;

		if ( is_array( $answers ) && count( $answers ) ) {
			foreach ( $answers as $answer ) {
				if ( isset( $answer->question_type ) && 'h5p' === $answer->question_type ) {
					$is_h5p = true;
				} else {
					$is_h5p = false;
				}
			}
		}

		if ( $is_h5p && is_array( $columns ) ) {
			$columns = array_filter(
				$columns,
				function ( $key ) {
					return ! in_array( $key, array( 'given_answer', 'correct_answer' ), true );
				},
				ARRAY_FILTER_USE_KEY
			);
		}

		return $columns;
	}

	/**
	 * Legacy Overview result cell: View button or result-table score span.
	 *
	 * @since 2.2.0
	 * @param mixed $answer        JOIN attempt-answer row.
	 * @param mixed $answer_status Core result status (unused; Core hook arg).
	 * @return void
	 */
	public static function show_question_answer( $answer, $answer_status ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( ! is_object( $answer ) || ! isset( $answer->question_type ) || 'h5p' !== $answer->question_type ) {
			return;
		}

		$results = TutorPress_H5P_Review_Results::get_quiz_results(
			(int) ( $answer->question_id ?? 0 ),
			(int) ( $answer->user_id ?? 0 ),
			(int) ( $answer->quiz_attempt_id ?? 0 ),
			(int) ( $answer->quiz_id ?? 0 ),
			(int) ( $answer->question_description ?? 0 )
		);

		if ( ! is_array( $results ) || ! count( $results ) ) {
			return;
		}

		$first = ( isset( $results[0] ) && is_object( $results[0] ) ) ? $results[0] : null;
		if ( ! $first ) {
			return;
		}

		$has_response = true;
		if ( 1 === count( $results ) && is_null( $first->response ?? null ) ) {
			$has_response = false;
		}

		if ( $has_response ) {
			?>
			<a class="tutor-btn tutor-btn-outline-primary tutor-btn-sm open-tutor-h5p-quiz-result-modal-btn" data-quiz-id="<?php echo esc_attr( (string) ( $answer->quiz_id ?? '' ) ); ?>"
				data-question-id="<?php echo esc_attr( (string) ( $answer->question_id ?? '' ) ); ?>"
				data-user-id="<?php echo esc_attr( (string) ( $answer->user_id ?? '' ) ); ?>"
				data-attempt-id="<?php echo esc_attr( (string) ( $answer->quiz_attempt_id ?? '' ) ); ?>"
				data-content-id="<?php echo esc_attr( (string) ( $answer->question_description ?? '' ) ); ?>"
			>
				<?php esc_html_e( 'View', 'tutorpress' ); ?>
			</a>
			<?php
			return;
		}

		$raw   = isset( $first->raw_score ) ? $first->raw_score : 0;
		$max   = isset( $first->max_score ) ? $first->max_score : 0;
		$class = ( $max === $raw ) ? 'tutor-color-success' : 'tutor-color-danger';
		?>
		<span class="<?php echo esc_attr( $class ); ?> tutor-fw-normal"><?php echo esc_html( $raw . '/' . $max ); ?></span>
		<?php
	}

	/**
	 * Legacy empty H5P result-modal shell. Body is filled in Step 12.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function quiz_attempt_answer_modal() {
		$admin_class = is_admin() ? ' tutor-admin-design-init' : '';
		?>
		<div class="tutor-modal tutor-modal-scrollable<?php echo esc_attr( $admin_class ); ?> h5p-quiz-result-modal">
			<div class="tutor-modal-overlay"></div>
			<div class="tutor-modal-window">
				<div class="tutor-modal-content">
					<div class="tutor-modal-header">
						<div class="tutor-modal-title">
							<?php esc_html_e( 'H5P Question Answer', 'tutorpress' ); ?>
						</div>
						<button class="tutor-iconic-btn tutor-modal-close" data-tutor-modal-close>
							<span class="tutor-icon-times" aria-hidden="true"></span>
						</button>
					</div>
					<div class="tutor-modal-body tutor-modal-container"></div>
				</div>
			</div>
		</div>
		<?php
	}
}
