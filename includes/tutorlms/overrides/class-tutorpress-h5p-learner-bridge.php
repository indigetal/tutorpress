<?php
/**
 * Learner take-quiz bridge for Interactive Quiz H5P (xAPI, marks, assets).
 *
 * Owns schema ensure, take-quiz AJAX, Pro-exact mark filters, and quiz-bridge
 * enqueue when WP H5P is active and the Tutor Pro H5P runtime is absent.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_H5P_Learner_Bridge {

	/**
	 * Register WordPress init hook that arms the bridge when the predicate passes.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_bridge' ), 100 );
	}

	/**
	 * Whether TutorPress should register the learner H5P take-quiz bridge.
	 *
	 * True only when WP H5P is on and Pro H5P runtime is absent. Pure helper for
	 * fixtures; live registration passes Addon Checker detectors.
	 *
	 * @since 2.2.0
	 * @param bool $h5p_plugin_active      WordPress H5P plugin active.
	 * @param bool $pro_h5p_runtime_active Pro H5P runtime present (addon ∧ WP H5P).
	 * @return bool
	 */
	public static function should_register_learner_bridge( $h5p_plugin_active, $pro_h5p_runtime_active ) {
		return (bool) $h5p_plugin_active && ! (bool) $pro_h5p_runtime_active;
	}

	/**
	 * Attach bridge bootstrap when the registration predicate passes.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function register_bridge() {
		$h5p_plugin_active = TutorPress_Addon_Checker::is_h5p_plugin_active();
		// Mirrors TutorPro\H5P\H5P::is_enabled(): Pro addon enabled AND WP H5P active.
		$pro_h5p_runtime_active = TutorPress_Addon_Checker::is_h5p_enabled() && $h5p_plugin_active;

		if ( ! self::should_register_learner_bridge( $h5p_plugin_active, $pro_h5p_runtime_active ) ) {
			return;
		}

		self::maybe_create_tables();
		add_action( 'wp_ajax_save_h5p_question_xAPI_statement', array( __CLASS__, 'save_h5p_question_xAPI_statement' ) );
		add_action( 'wp_ajax_check_h5p_question_answered', array( __CLASS__, 'check_h5p_question_answered' ) );
		add_action( 'tutor_quiz/attempt_deleted', array( __CLASS__, 'delete_h5p_quiz_result_by_attempt_id' ), 10, 1 );
		add_filter( 'tutor_filter_update_before_question_mark', array( __CLASS__, 'filter_total_marks' ), 10, 4 );
		add_filter( 'tutor_filter_quiz_total_marks', array( __CLASS__, 'filter_total_quiz_marks' ), 10, 5 );
		add_filter( 'tutor_filter_quiz_answer_data', array( __CLASS__, 'filter_quiz_answer_data' ), 10, 5 );
		add_action( 'tutor_quiz/body/before', array( __CLASS__, 'enqueue_quiz_bridge_assets' ), 10, 2 );
		add_action( 'h5p_alter_library_scripts', array( __CLASS__, 'inject_h5p_iframe_bridge_script' ), 10, 3 );
	}

	/**
	 * Enqueue parent H5P quiz bridge on started Interactive Quiz (modern + legacy).
	 *
	 * Localized object: window.tutorpressH5PQuiz { ajaxurl, _tutor_nonce }.
	 * Does not enqueue the iframe helper (H5P injects that — D31).
	 *
	 * @since 2.2.0
	 * @param int   $quiz_id            Quiz id.
	 * @param array $quiz_attempt_info  Attempt info or quiz details (must include quiz_type).
	 * @return void
	 */
	public static function enqueue_quiz_bridge_assets( $quiz_id, $quiz_attempt_info = array() ) {
		if ( ! is_array( $quiz_attempt_info ) || ! isset( $quiz_attempt_info['quiz_type'] ) || 'tutor_h5p_quiz' !== $quiz_attempt_info['quiz_type'] ) {
			return;
		}
		if ( ! function_exists( 'tutor_utils' ) || ! tutor_utils()->is_started_quiz() ) {
			return;
		}

		$rel  = 'assets/js/tutorpress-h5p-quiz-bridge.js';
		$path = TUTORPRESS_PATH . $rel;
		if ( ! file_exists( $path ) ) {
			return;
		}

		$handle = 'tutorpress-h5p-quiz-bridge';
		wp_enqueue_script( $handle, TUTORPRESS_URL . $rel, array( 'jquery' ), filemtime( $path ), true );

		$nonce_action = ( function_exists( 'tutor' ) && tutor() ) ? tutor()->nonce_action : 'tutor_nonce_action';
		wp_localize_script(
			$handle,
			'tutorpressH5PQuiz',
			array(
				'ajaxurl'      => admin_url( 'admin-ajax.php' ),
				'_tutor_nonce' => wp_create_nonce( $nonce_action ),
			)
		);
	}

	/**
	 * Inject TutorPress iframe helper into H5P iframe embeds (D31 Pro-like global).
	 *
	 * May apply to non-quiz H5P iframes under the bridge predicate; helper no-op
	 * outside quiz parent is owned by Step 6.
	 *
	 * @since 2.2.0
	 * @param array  $scripts    H5P iframe script list (by ref).
	 * @param mixed  $libraries  Dependent libraries.
	 * @param string $embed_type Embed type.
	 * @return void
	 */
	public static function inject_h5p_iframe_bridge_script( &$scripts, $libraries, $embed_type ) {
		if ( 'iframe' !== $embed_type ) {
			return;
		}

		$rel  = 'assets/js/tutorpress-h5p-iframe-bridge.js';
		$path = TUTORPRESS_PATH . $rel;
		if ( ! file_exists( $path ) ) {
			return;
		}

		$scripts[] = (object) array(
			'path'    => TUTORPRESS_URL . $rel,
			'version' => '?ver=' . filemtime( $path ),
		);
	}

	/**
	 * D13 success JSON and exit.
	 *
	 * @since 2.2.0
	 * @param array $data Response data payload.
	 * @return void
	 */
	public static function send_success( $data ) {
		wp_send_json_success( $data );
	}

	/**
	 * D13 error JSON and exit.
	 *
	 * @since 2.2.0
	 * @param string $code    Error code from D13.
	 * @param string $message Human-readable message.
	 * @return void
	 */
	public static function send_error( $code, $message ) {
		wp_send_json_error(
			array(
				'code'    => sanitize_key( $code ),
				'message' => sanitize_text_field( $message ),
			)
		);
	}

	/**
	 * D15 attempt-scoped authorization (check + shared by save). Active-attempt only (D30).
	 *
	 * @since 2.2.0
	 * @return array|\WP_Error { user_id, quiz_id, attempt_id } or WP_Error with D13 code.
	 */
	public static function authorize_h5p_quiz_attempt_ajax() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in.', 'tutorpress' ) );
		}

		// AJAX is always POST; force method so WP-CLI/eval fixtures and live admin-ajax agree.
		if ( ! function_exists( 'tutor_utils' ) || ! tutor_utils()->is_nonce_verified( 'post' ) ) {
			return new WP_Error( 'invalid_nonce', __( 'Invalid nonce.', 'tutorpress' ) );
		}

		$quiz_id    = isset( $_POST['quiz_id'] ) ? absint( wp_unslash( $_POST['quiz_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$attempt_id = isset( $_POST['attempt_id'] ) ? absint( wp_unslash( $_POST['attempt_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $quiz_id < 1 || $attempt_id < 1 ) {
			return new WP_Error( 'invalid_input', __( 'Invalid quiz input.', 'tutorpress' ) );
		}

		$attempt = tutor_utils()->get_attempt( $attempt_id );
		if ( ! $attempt || (int) $attempt->user_id !== $user_id || (int) $attempt->quiz_id !== $quiz_id ) {
			return new WP_Error( 'forbidden', __( 'Attempt access denied.', 'tutorpress' ) );
		}

		if ( \Tutor\Models\QuizModel::ATTEMPT_STARTED !== $attempt->attempt_status ) {
			return new WP_Error( 'forbidden', __( 'Attempt is not active.', 'tutorpress' ) );
		}

		if ( ! \Tutor\Models\QuizModel::has_quiz_access( $quiz_id, 0, false ) ) {
			return new WP_Error( 'forbidden', __( 'Quiz access denied.', 'tutorpress' ) );
		}

		return array(
			'user_id'    => $user_id,
			'quiz_id'    => $quiz_id,
			'attempt_id' => $attempt_id,
		);
	}

	/**
	 * D15 authorization for single-question H5P save AJAX. Active-attempt only (D30).
	 *
	 * @since 2.2.0
	 * @return array|\WP_Error Validated ids or WP_Error with D13 code.
	 */
	public static function authorize_h5p_quiz_ajax() {
		$attempt_auth = self::authorize_h5p_quiz_attempt_ajax();
		if ( is_wp_error( $attempt_auth ) ) {
			return $attempt_auth;
		}

		$question_id = isset( $_POST['question_id'] ) ? absint( wp_unslash( $_POST['question_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$content_id  = isset( $_POST['content_id'] ) ? absint( wp_unslash( $_POST['content_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( $question_id < 1 || $content_id < 1 ) {
			return new WP_Error( 'invalid_input', __( 'Invalid quiz input.', 'tutorpress' ) );
		}

		if ( ! self::is_valid_h5p_question_pair( (int) $attempt_auth['quiz_id'], $question_id, $content_id ) ) {
			return new WP_Error( 'forbidden', __( 'Question does not match this quiz content.', 'tutorpress' ) );
		}

		return array(
			'user_id'     => (int) $attempt_auth['user_id'],
			'quiz_id'     => (int) $attempt_auth['quiz_id'],
			'question_id' => $question_id,
			'content_id'  => $content_id,
			'attempt_id'  => (int) $attempt_auth['attempt_id'],
		);
	}

	/**
	 * Whether a question/content pair belongs to the quiz as type h5p (D15).
	 *
	 * @since 2.2.0
	 * @param int $quiz_id     Quiz id.
	 * @param int $question_id Question id.
	 * @param int $content_id  H5P content id.
	 * @return bool
	 */
	public static function is_valid_h5p_question_pair( $quiz_id, $question_id, $content_id ) {
		$question = \Tutor\Models\QuizModel::get_quiz_question_by_id( (int) $question_id );
		return (
			$question
			&& (int) $question->quiz_id === (int) $quiz_id
			&& 'h5p' === $question->question_type
			&& trim( (string) $question->question_description ) === (string) (int) $content_id
		);
	}

	/**
	 * Fetch tutor_h5p_quiz_result rows (Pro Utils::get_h5p_quiz_result shape).
	 *
	 * Always scopes by question_id + user_id. Optional attempt/quiz/content only when non-zero.
	 *
	 * @since 2.2.0
	 * @param int $question_id Question id.
	 * @param int $user_id     User id.
	 * @param int $attempt_id  Attempt id (0 = omit).
	 * @param int $quiz_id     Quiz id (0 = omit).
	 * @param int $content_id  Content id (0 = omit).
	 * @return array Result row objects (0–1).
	 */
	public static function get_h5p_quiz_result( $question_id, $user_id, $attempt_id = 0, $quiz_id = 0, $content_id = 0 ) {
		global $wpdb;

		$question_id = (int) $question_id;
		$user_id     = (int) $user_id;
		$attempt_id  = (int) $attempt_id;
		$quiz_id     = (int) $quiz_id;
		$content_id  = (int) $content_id;

		$where  = $wpdb->prepare( ' AND question_id = %d AND user_id = %d', $question_id, $user_id );
		$where .= ( 0 !== $content_id ) ? $wpdb->prepare( ' AND content_id = %d', $content_id ) : '';
		$where .= ( 0 !== $attempt_id ) ? $wpdb->prepare( ' AND attempt_id = %d', $attempt_id ) : '';
		$where .= ( 0 !== $quiz_id ) ? $wpdb->prepare( ' AND quiz_id = %d', $quiz_id ) : '';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where built via $wpdb->prepare above.
		$rows = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}tutor_h5p_quiz_result WHERE 1=1 {$where} ORDER BY finished DESC LIMIT 1"
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Whether a tutor_h5p_quiz_result row exists for the attempt pair.
	 *
	 * @since 2.2.0
	 * @param int $question_id Question id.
	 * @param int $user_id     User id.
	 * @param int $attempt_id  Attempt id.
	 * @param int $quiz_id     Quiz id.
	 * @param int $content_id  Content id.
	 * @return bool
	 */
	public static function has_h5p_quiz_result( $question_id, $user_id, $attempt_id, $quiz_id, $content_id ) {
		return count( self::get_h5p_quiz_result( $question_id, $user_id, $attempt_id, $quiz_id, $content_id ) ) > 0;
	}

	/**
	 * Delete H5P quiz result rows for attempt id(s) (D23 supporting cleanup).
	 *
	 * Accepts a single int or Core/Pro comma-string of attempt ids.
	 *
	 * @since 2.2.0
	 * @param int|string $attempt_ids Attempt id or comma-separated ids.
	 * @return void
	 */
	public static function delete_h5p_quiz_result_by_attempt_id( $attempt_ids ) {
		global $wpdb;

		if ( is_array( $attempt_ids ) ) {
			$ids = $attempt_ids;
		} elseif ( is_string( $attempt_ids ) ) {
			$ids = explode( ',', $attempt_ids );
		} else {
			$ids = array( $attempt_ids );
		}

		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( ! $ids ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- placeholders from absint id count.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}tutor_h5p_quiz_result WHERE attempt_id IN ({$placeholders})", ...$ids ) );
	}

	/**
	 * Add H5P max_score onto Core SUM(question_mark) (D20/D27).
	 *
	 * D27: attempt total_marks may equal Core sum plus Σ H5P max_score — Pro-exact inflation, not a bug.
	 * Missing answered H5P vs listed question_ids zeros total and deletes attempt results (Pro comparison quirk).
	 *
	 * @since 2.2.0
	 * @param string|int $total_question_marks Core total before H5P add-on.
	 * @param array      $question_ids         Question ids from the attempt POST.
	 * @param int        $user_id              Learner user id.
	 * @param int        $attempt_id           Attempt id.
	 * @return string|int|float
	 */
	public static function filter_total_marks( $total_question_marks, $question_ids, $user_id, $attempt_id ) {
		$count_answered_h5p_question = 0;
		$has_h5p_question            = false;
		$question_ids                = is_array( $question_ids ) ? $question_ids : array();

		foreach ( $question_ids as $question_id ) {
			$question = \Tutor\Models\QuizModel::get_quiz_question_by_id( $question_id );
			if ( ! $question || 'h5p' !== $question->question_type ) {
				continue;
			}

			$has_h5p_question = true;
			$attempt_result   = self::get_h5p_quiz_result( (int) $question_id, (int) $user_id, (int) $attempt_id );
			if ( is_array( $attempt_result ) && count( $attempt_result ) ) {
				$total_question_marks += $attempt_result[0]->max_score;
				++$count_answered_h5p_question;
			}
		}

		if ( $count_answered_h5p_question < count( $question_ids ) && $has_h5p_question ) {
			$total_question_marks = 0;
			self::delete_h5p_quiz_result_by_attempt_id( $attempt_id );
		}

		return $total_question_marks;
	}

	/**
	 * Add H5P raw_score into earned total marks (D20).
	 *
	 * @since 2.2.0
	 * @param string|int $total_marks   Running earned marks.
	 * @param int        $question_id   Question id.
	 * @param string     $question_type Question type.
	 * @param int        $user_id       User id.
	 * @param int        $attempt_id    Attempt id.
	 * @return string|int|float
	 */
	public static function filter_total_quiz_marks( $total_marks, $question_id, $question_type, $user_id, $attempt_id ) {
		if ( 'h5p' === $question_type ) {
			$attempt_result = self::get_h5p_quiz_result( (int) $question_id, (int) $user_id, (int) $attempt_id );
			if ( is_array( $attempt_result ) && count( $attempt_result ) ) {
				$total_marks += $attempt_result[0]->raw_score;
			}
		}
		return $total_marks;
	}

	/**
	 * Overwrite H5P attempt-answer mark fields from result row (D20).
	 *
	 * @since 2.2.0
	 * @param array  $answers_data  Attempt answer row data.
	 * @param int    $question_id   Question id.
	 * @param string $question_type Question type.
	 * @param int    $user_id       User id.
	 * @param int    $attempt_id    Attempt id.
	 * @return array
	 */
	public static function filter_quiz_answer_data( $answers_data, $question_id, $question_type, $user_id, $attempt_id ) {
		if ( 'h5p' === $question_type ) {
			$attempt_result = self::get_h5p_quiz_result( (int) $question_id, (int) $user_id, (int) $attempt_id );
			if ( is_array( $attempt_result ) && count( $attempt_result ) ) {
				$row                           = $attempt_result[0];
				$answers_data['question_mark'] = $row->max_score;
				$answers_data['achieved_mark'] = $row->raw_score;
				$answers_data['is_correct']    = $row->max_score === $row->raw_score;
			}
		}
		return $answers_data;
	}

	/**
	 * AJAX: list unanswered H5P question/content pairs (Pro required_answers shape).
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function check_h5p_question_answered() {
		$auth = self::authorize_h5p_quiz_attempt_ajax();
		if ( is_wp_error( $auth ) ) {
			self::send_error( $auth->get_error_code(), $auth->get_error_message() );
			return;
		}

		$raw = isset( $_POST['question_ids'] ) ? wp_unslash( $_POST['question_ids'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$pairs = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $pairs ) ) {
			self::send_error( 'invalid_input', __( 'Invalid question_ids payload.', 'tutorpress' ) );
			return;
		}

		$required_answers = array();
		foreach ( $pairs as $id_data ) {
			if ( ! is_array( $id_data ) ) {
				continue;
			}
			$question_id = isset( $id_data['question_id'] ) ? absint( $id_data['question_id'] ) : 0;
			$content_id  = isset( $id_data['content_id'] ) ? absint( $id_data['content_id'] ) : 0;
			if ( 0 === $question_id || 0 === $content_id ) {
				continue;
			}

			$pair = array(
				'question_id' => $question_id,
				'content_id'  => $content_id,
			);

			// Fail-closed: invalid D15 relationship → treat as unanswered (do not 403 whole request).
			if ( ! self::is_valid_h5p_question_pair( (int) $auth['quiz_id'], $question_id, $content_id ) ) {
				$required_answers[] = $pair;
				continue;
			}

			if ( ! self::has_h5p_quiz_result( $question_id, (int) $auth['user_id'], (int) $auth['attempt_id'], (int) $auth['quiz_id'], $content_id ) ) {
				$required_answers[] = $pair;
			}
		}

		self::send_success( array( 'required_answers' => wp_json_encode( $required_answers ) ) );
	}

	/**
	 * AJAX: save H5P xAPI statement / result (D13 / D8 / D28 / D30).
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function save_h5p_question_xAPI_statement() {
		$auth = self::authorize_h5p_quiz_ajax();
		if ( is_wp_error( $auth ) ) {
			self::send_error( $auth->get_error_code(), $auth->get_error_message() );
			return;
		}

		$raw = isset( $_POST['statement'] ) ? wp_unslash( $_POST['statement'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! is_string( $raw ) || '' === $raw ) {
			self::send_error( 'malformed_statement', __( 'Missing xAPI statement.', 'tutorpress' ) );
			return;
		}

		$statement = json_decode( $raw );
		if ( ! is_object( $statement ) ) {
			self::send_error( 'malformed_statement', __( 'Invalid xAPI statement JSON.', 'tutorpress' ) );
			return;
		}

		$saved = self::maybe_save_scored_result( $statement, $auth );
		if ( is_wp_error( $saved ) ) {
			self::send_error( $saved->get_error_code(), $saved->get_error_message() );
			return;
		}

		// D30: prefer result status even if quiz-statement insert fails.
		$statement_id = self::maybe_save_quiz_statement( $statement, $auth, (int) $saved['result_id'] );

		self::send_success(
			array(
				'result_status' => $saved['result_status'],
				'result_id'     => (int) $saved['result_id'],
				'statement_id'  => (int) $statement_id,
			)
		);
	}

	/**
	 * Insert tutor_h5p_quiz_statement only (D8/D28). Returns 0 on failure (D30).
	 *
	 * @since 2.2.0
	 * @param object $statement Decoded xAPI statement.
	 * @param array  $auth      Validated ids.
	 * @param int    $result_id Related quiz result id (0 if none).
	 * @return int statement_id or 0.
	 */
	public static function maybe_save_quiz_statement( $statement, $auth, $result_id = 0 ) {
		global $wpdb;

		$quiz_id   = (int) $auth['quiz_id'];
		$course_id = function_exists( 'tutor_utils' ) ? (int) tutor_utils()->get_course_id_by_subcontent( $quiz_id ) : 0;
		$topic_id  = (int) wp_get_post_parent_id( $quiz_id );
		$instructors = ( $course_id && function_exists( 'tutor_utils' ) ) ? tutor_utils()->get_instructors_by_course( $course_id ) : array();

		$row = array(
			'content_id'    => (int) $auth['content_id'],
			'user_id'       => (int) $auth['user_id'],
			'created_at'    => current_time( 'mysql', true ),
			'instructor_id' => ( is_array( $instructors ) && isset( $instructors[0]->ID ) ) ? (int) $instructors[0]->ID : 0,
			'quiz_id'       => $quiz_id,
			'question_id'   => (int) $auth['question_id'],
			'course_id'     => $course_id,
			'topic_id'      => $topic_id,
		);

		if ( isset( $statement->verb ) && is_object( $statement->verb ) ) {
			$verb = $statement->verb;
			if ( isset( $verb->id ) ) {
				$row['verb_id'] = esc_url_raw( (string) $verb->id );
			}
			if ( isset( $verb->display ) ) {
				$row['verb'] = sanitize_text_field( self::get_xapi_locale_text( $verb->display ) );
			}
		}

		if ( isset( $statement->object->definition ) && is_object( $statement->object->definition ) ) {
			$definition = $statement->object->definition;
			if ( isset( $definition->name ) ) {
				$row['activity_name'] = sanitize_text_field( self::get_xapi_locale_text( $definition->name ) );
			} elseif ( isset( $definition->description ) ) {
				$row['activity_name'] = sanitize_text_field( self::get_xapi_locale_text( $definition->description ) );
			}
			$row['activity_description'] = isset( $definition->description )
				? sanitize_textarea_field( self::get_xapi_locale_text( $definition->description ) )
				: '';
			$row['activity_interaction_type'] = isset( $definition->interactionType )
				? sanitize_text_field( (string) $definition->interactionType )
				: '';
			if ( ! empty( $definition->correctResponsesPattern ) && is_array( $definition->correctResponsesPattern ) ) {
				$row['activity_correct_response_pattern'] = maybe_serialize( $definition->correctResponsesPattern );
			}
			$choices = $definition->choices ?? ( $definition->source ?? null );
			if ( is_array( $choices ) && $choices ) {
				$row['activity_choices'] = maybe_serialize( $choices );
			}
			if ( ! empty( $definition->target ) && is_array( $definition->target ) ) {
				$row['activity_target'] = maybe_serialize( $definition->target );
			}
		}

		if ( isset( $statement->result ) && is_object( $statement->result ) ) {
			$result = $statement->result;
			if ( isset( $result->score ) && is_object( $result->score ) ) {
				$score = $result->score;
				$row['result_max_score']    = isset( $score->max ) && is_numeric( $score->max ) ? (int) $score->max : null;
				$row['result_min_score']    = isset( $score->min ) && is_numeric( $score->min ) ? (int) $score->min : null;
				$row['result_raw_score']    = isset( $score->raw ) && is_numeric( $score->raw ) ? (int) $score->raw : null;
				$row['result_scaled_score'] = isset( $score->scaled ) && is_numeric( $score->scaled ) ? (int) $score->scaled : null;
			}
			$row['result_completion'] = isset( $result->completion ) ? (int) (bool) $result->completion : null;
			$row['result_success']    = isset( $result->success ) ? (int) (bool) $result->success : null;
			$row['result_response']   = isset( $result->response ) ? sanitize_textarea_field( (string) $result->response ) : null;
			$row['result_duration']   = isset( $result->duration ) ? sanitize_text_field( (string) $result->duration ) : null;
		}

		if ( $result_id > 0 ) {
			$row['quiz_result_id'] = $result_id;
		}

		$inserted = $wpdb->insert( "{$wpdb->prefix}tutor_h5p_quiz_statement", $row );
		if ( false === $inserted || ! $wpdb->insert_id ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Resolve an xAPI locale map to a display string (Pro Utils::get_xpi_locale_property).
	 *
	 * @since 2.2.0
	 * @param mixed $display Language map object or string.
	 * @return string
	 */
	private static function get_xapi_locale_text( $display ) {
		if ( is_string( $display ) ) {
			return $display;
		}
		if ( ! is_object( $display ) ) {
			return '';
		}
		$locale = str_replace( '_', '-', get_locale() );
		if ( property_exists( $display, $locale ) ) {
			return (string) $display->{ $locale };
		}
		if ( property_exists( $display, 'en-US' ) ) {
			return (string) $display->{'en-US'};
		}
		return '';
	}

	/**
	 * Insert a scored tutor_h5p_quiz_result row (D7 / D21 / D29) or report status.
	 *
	 * @since 2.2.0
	 * @param object $statement Decoded xAPI statement.
	 * @param array  $auth      Validated ids from authorize_h5p_quiz_ajax().
	 * @return array|\WP_Error { result_status, result_id }
	 */
	public static function maybe_save_scored_result( $statement, $auth ) {
		global $wpdb;

		if ( ! isset( $statement->result ) || ! is_object( $statement->result ) ) {
			return array(
				'result_status' => 'no_result',
				'result_id'     => 0,
			);
		}

		$result = $statement->result;
		$score  = ( isset( $result->score ) && is_object( $result->score ) ) ? $result->score : null;
		if ( ! $score || ! is_numeric( $score->raw ) || ! is_numeric( $score->max ) ) {
			return array(
				'result_status' => 'no_result',
				'result_id'     => 0,
			);
		}

		$quiz_id     = (int) $auth['quiz_id'];
		$question_id = (int) $auth['question_id'];
		$content_id  = (int) $auth['content_id'];
		$user_id     = (int) $auth['user_id'];
		$attempt_id  = (int) $auth['attempt_id'];

		$existing_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT result_id FROM {$wpdb->prefix}tutor_h5p_quiz_result
				WHERE quiz_id = %d AND question_id = %d AND content_id = %d
				AND user_id = %d AND attempt_id = %d
				AND raw_score IS NOT NULL AND max_score IS NOT NULL
				LIMIT 1",
				$quiz_id,
				$question_id,
				$content_id,
				$user_id,
				$attempt_id
			)
		);

		if ( $existing_id && (int) $existing_id > 0 ) {
			return array(
				'result_status' => 'exists',
				'result_id'     => (int) $existing_id,
			);
		}

		$opened = $finished = $duration = null;
		$timing = self::get_wp_h5p_timing( $content_id, $user_id );
		if ( is_array( $timing ) ) {
			$opened   = $timing['opened'];
			$finished = $timing['finished'];
			$duration = $timing['duration'];
		}

		$row = array(
			'quiz_id'     => $quiz_id,
			'question_id' => $question_id,
			'content_id'  => $content_id,
			'user_id'     => $user_id,
			'attempt_id'  => $attempt_id,
			'max_score'   => (int) $score->max,
			'raw_score'   => (int) $score->raw,
			'min_score'   => isset( $score->min ) && is_numeric( $score->min ) ? (int) $score->min : null,
			'scaled_score'=> isset( $score->scaled ) && is_numeric( $score->scaled ) ? (int) $score->scaled : null,
			'completion'  => isset( $result->completion ) ? (int) (bool) $result->completion : null,
			'success'     => isset( $result->success ) ? (int) (bool) $result->success : null,
			'response'    => isset( $result->response ) ? sanitize_textarea_field( (string) $result->response ) : null,
			'opened'      => $opened,
			'finished'    => $finished,
			'duration'    => $duration,
		);

		$inserted = $wpdb->insert( "{$wpdb->prefix}tutor_h5p_quiz_result", $row );
		if ( false === $inserted || ! $wpdb->insert_id ) {
			return new WP_Error( 'db_error', __( 'Could not save H5P quiz result.', 'tutorpress' ) );
		}

		return array(
			'result_status' => 'written',
			'result_id'     => (int) $wpdb->insert_id,
		);
	}

	/**
	 * Prefer WP H5P result timing fields; else NULL (D21). Never invent scores.
	 *
	 * @since 2.2.0
	 * @param int $content_id H5P content id.
	 * @param int $user_id    WP user id.
	 * @return array|null { opened, finished, duration } or null.
	 */
	private static function get_wp_h5p_timing( $content_id, $user_id ) {
		if ( ! class_exists( 'H5P_Plugin_Admin' ) ) {
			return null;
		}

		$admin   = H5P_Plugin_Admin::get_instance();
		$results = $admin->get_results( (int) $content_id, (int) $user_id, 0, 10, 1, 0 );
		if ( ! is_array( $results ) || empty( $results[0] ) ) {
			return null;
		}

		$row = $results[0];
		if ( ! isset( $row->opened, $row->finished ) || ! is_numeric( $row->opened ) || ! is_numeric( $row->finished ) ) {
			return null;
		}

		return array(
			'opened'   => (int) $row->opened,
			'finished' => (int) $row->finished,
			'duration' => (int) abs( (int) $row->finished - (int) $row->opened ),
		);
	}

	/**
	 * Idempotent dbDelta for quiz result + quiz statement tables only (D5a/D28).
	 *
	 * Never DROP. Does not create tutor_h5p_statement or tutor_h5p_lesson_statement.
	 * SQL mirrors Pro Database.php quiz-table CREATE statements.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function maybe_create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$h5p_quiz_result_sql = "CREATE TABLE {$wpdb->prefix}tutor_h5p_quiz_result (
            result_id  BIGINT(20) NOT NULL AUTO_INCREMENT,
            quiz_id BIGINT(20),
            attempt_id BIGINT(20),
            question_id BIGINT(20),
            user_id BIGINT(20),
            content_id BIGINT(20),
            response TEXT,
            max_score INT,
            raw_score INT,
            scaled_score INT,
            min_score INT,
            completion BOOLEAN,
            success BOOLEAN,
            opened INT(10) ,
            finished INT(10) ,
            duration BIGINT(20),
            PRIMARY KEY (result_id)
        ) $charset_collate;";

		$h5p_quiz_statement_sql = "CREATE TABLE {$wpdb->prefix}tutor_h5p_quiz_statement (
            statement_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            instructor_id BIGINT(20) DEFAULT NULL,
            course_id BIGINT(20) DEFAULT NULL,
            topic_id  BIGINT(20) DEFAULT NULL,
            quiz_id      BIGINT(20) DEFAULT NULL,
            question_id  BIGINT(20) DEFAULT NULL,
            content_id   BIGINT(20) DEFAULT NULL,
            user_id      BIGINT(20) DEFAULT NULL,
            verb         VARCHAR(20),
            verb_id      TEXT,
            activity_name TEXT,
            activity_description TEXT,
            activity_choices TEXT,
            activity_target TEXT,
            activity_interaction_type TEXT,
            activity_correct_response_pattern TEXT,
            result_response TEXT,
            result_max_score INT,
            result_raw_score INT,
            result_scaled_score INT,
            result_min_score INT,
            result_completion BOOLEAN,
            result_success BOOLEAN,
            result_duration TEXT,
            created_at DATETIME,
            quiz_result_id BIGINT(20),
            PRIMARY KEY (statement_id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $h5p_quiz_result_sql );
		dbDelta( $h5p_quiz_statement_sql );
	}
}
