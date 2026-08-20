<?php
/**
 * H5P review AJAX: wp_ajax_view_h5p_quiz_result only (no nopriv).
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_H5P_Review_Ajax {

	/**
	 * Logged-in View-modal AJAX. Nonce + access, then D28 readers and modal `output`.
	 *
	 * POST user_id is parsed but does not authorize. D28 user_id comes from the attempt row.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function view_h5p_quiz_result() {
		if ( ! function_exists( 'tutor_utils' ) || ! tutor_utils()->is_nonce_verified( 'post' ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_nonce',
					'message' => __( 'Invalid nonce.', 'tutorpress' ),
				)
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above.
		$quiz_id     = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0;
		$user_id     = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0;
		$content_id  = isset( $_POST['content_id'] ) ? absint( wp_unslash( $_POST['content_id'] ) ) : 0;
		$attempt_id  = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0;
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$attempt = ( $attempt_id > 0 ) ? tutor_utils()->get_attempt( $attempt_id ) : null;
		if ( ! is_object( $attempt ) ) {
			wp_send_json_error(
				array(
					'code'    => 'invalid_attempt',
					'message' => __( 'Attempt access denied.', 'tutorpress' ),
				)
			);
		}

		$can_manage = tutor_utils()->can_user_manage( 'attempt', $attempt_id );
		$is_owner   = isset( $attempt->user_id ) && (int) $attempt->user_id === get_current_user_id();
		if ( ! $can_manage && ! $is_owner ) {
			wp_send_json_error(
				array(
					'code'    => 'forbidden',
					'message' => __( 'Attempt access denied.', 'tutorpress' ),
				)
			);
		}

		$user_id = isset( $attempt->user_id ) ? absint( $attempt->user_id ) : 0;
		$results = TutorPress_H5P_Review_Results::get_quiz_results(
			$question_id,
			$user_id,
			$attempt_id,
			$quiz_id,
			$content_id
		);

		$result_ids = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $row ) {
				if ( is_object( $row ) && isset( $row->result_id ) ) {
					$rid = absint( $row->result_id );
					if ( $rid ) {
						$result_ids[] = $rid;
					}
				}
			}
		}

		$h5p_quiz_result_statements = TutorPress_H5P_Review_Results::get_quiz_statements( $result_ids );
		if ( ! is_array( $h5p_quiz_result_statements ) ) {
			$h5p_quiz_result_statements = array();
		}

		if ( count( $h5p_quiz_result_statements ) > 1 ) {
			usort(
				$h5p_quiz_result_statements,
				function ( $result_1, $result_2 ) {
					return $result_2->result_max_score - $result_1->result_max_score;
				}
			);
		}

		$html = '';
		if ( count( $h5p_quiz_result_statements ) ) {
			$base     = defined( 'TUTORPRESS_PATH' ) ? TUTORPRESS_PATH : dirname( __DIR__, 3 ) . '/';
			$template = $base . 'templates/tutorpress/quiz/h5p-review/h5p-quiz-result-modal.php';
			if ( file_exists( $template ) ) {
				ob_start();
				include $template;
				$html = (string) ob_get_clean();
			}
		}

		wp_send_json_success( array( 'output' => $html ) );
	}
}
