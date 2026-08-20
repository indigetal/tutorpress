<?php
/**
 * Read-only D28 lookups for H5P review (quiz_result then quiz_statement).
 *
 * No hooks. No writes. Quiz-result and quiz-statement tables only.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_H5P_Review_Results {

	/**
	 * Result rows. Optional AND when each id ≠ 0. All-zero returns [] with no query.
	 *
	 * @since 2.2.0
	 * @param int $question_id Question id (0 = omit).
	 * @param int $user_id     User id (0 = omit).
	 * @param int $attempt_id  Attempt id (0 = omit).
	 * @param int $quiz_id     Quiz id (0 = omit).
	 * @param int $content_id  Content id (0 = omit).
	 * @return array
	 */
	public static function get_quiz_results( $question_id = 0, $user_id = 0, $attempt_id = 0, $quiz_id = 0, $content_id = 0 ) {
		global $wpdb;

		$question_id = (int) $question_id;
		$user_id     = (int) $user_id;
		$attempt_id  = (int) $attempt_id;
		$quiz_id     = (int) $quiz_id;
		$content_id  = (int) $content_id;

		if ( 0 === $question_id && 0 === $user_id && 0 === $attempt_id && 0 === $quiz_id && 0 === $content_id ) {
			return array();
		}

		$where = self::prepared_optional_result_where( $content_id, $attempt_id, $quiz_id, $question_id, $user_id );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where built via $wpdb->prepare.
		$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tutor_h5p_quiz_result WHERE 1=1 {$where}" );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Latest result row (ORDER BY finished DESC LIMIT 1).
	 *
	 * Always ANDs question_id + user_id; other ids optional when ≠ 0.
	 *
	 * @since 2.2.0
	 * @param int $question_id Question id.
	 * @param int $user_id     User id.
	 * @param int $attempt_id  Attempt id (0 = omit).
	 * @param int $quiz_id     Quiz id (0 = omit).
	 * @param int $content_id  Content id (0 = omit).
	 * @return object|null
	 */
	public static function get_quiz_result( $question_id, $user_id, $attempt_id = 0, $quiz_id = 0, $content_id = 0 ) {
		global $wpdb;

		$question_id = (int) $question_id;
		$user_id     = (int) $user_id;
		$attempt_id  = (int) $attempt_id;
		$quiz_id     = (int) $quiz_id;
		$content_id  = (int) $content_id;

		$where  = $wpdb->prepare( ' AND question_id = %d AND user_id = %d', $question_id, $user_id );
		$where .= self::prepared_optional_result_where( $content_id, $attempt_id, $quiz_id, 0, 0 );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where built via $wpdb->prepare.
		$row = $wpdb->get_row( "SELECT * FROM {$wpdb->prefix}tutor_h5p_quiz_result WHERE 1=1 {$where} ORDER BY finished DESC LIMIT 1" );

		return $row ? $row : null;
	}

	/**
	 * One statement row by quiz_result_id. Zero id returns null with no query.
	 *
	 * @since 2.2.0
	 * @param int $quiz_result_id Result row id.
	 * @return object|null
	 */
	public static function get_quiz_statement( $quiz_result_id ) {
		global $wpdb;

		$quiz_result_id = absint( $quiz_result_id );
		if ( 0 === $quiz_result_id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tutor_h5p_quiz_statement WHERE quiz_result_id = %d LIMIT 1",
				$quiz_result_id
			)
		);
	}

	/**
	 * Statement rows for quiz_result_id IN (…). Empty/zero ids return [] with no query.
	 *
	 * @since 2.2.0
	 * @param array $quiz_result_ids Result row ids.
	 * @return array
	 */
	public static function get_quiz_statements( array $quiz_result_ids ) {
		global $wpdb;

		$ids = array();
		foreach ( $quiz_result_ids as $id ) {
			$id = absint( $id );
			if ( 0 !== $id ) {
				$ids[] = $id;
			}
		}
		if ( array() === $ids ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$rows         = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}tutor_h5p_quiz_statement WHERE quiz_result_id IN ({$placeholders})",
				...$ids
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Map a statement to a TutorPress review partial.
	 *
	 * @since 2.2.0
	 * @param object|null $statement Statement row.
	 * @param mixed       $choices Choices (callers unserialize).
	 * @param mixed       $correct_response_pattern Pattern.
	 * @param mixed       $h5p_targets Targets.
	 * @return array
	 */
	public static function get_h5p_statement_result_response( $statement, $choices = null, $correct_response_pattern = null, $h5p_targets = null ) {
		if ( ! is_object( $statement ) ) {
			return array(
				'template_path' => '',
				'question_type' => 'default',
			);
		}

		$response_results = array();
		$result_response  = isset( $statement->result_response ) ? $statement->result_response : '';
		if ( ! str_contains( $correct_response_pattern ?? '', '[,]' ) && str_contains( $correct_response_pattern ?? '', ',' ) ) {
			$correct_responses        = explode( ',', $correct_response_pattern );
			$correct_response_pattern = implode( '[,]', $correct_responses );
		}
		if ( is_array( $choices ) && count( $choices ) ) {
			$all_choices = array_column( $choices, 'description', 'id' );
			if ( str_contains( $correct_response_pattern, '[,]' ) && ! str_contains( $correct_response_pattern, '[.]' ) ) {
				$correct_response_ids = explode( '[,]', $correct_response_pattern );
				$user_response_ids    = explode( '[,]', $result_response );
				self::prepare_choices_statements_correct_response_results( $correct_response_ids, $user_response_ids, $response_results, $all_choices, $choices );
				if ( count( $choices ) === count( $user_response_ids ) ) {
					self::get_sequencing_statements_result_response( $response_results, $user_response_ids );
				} else {
					self::get_choices_statements_user_result_response( $user_response_ids, $response_results );
				}
			}
			if ( ! str_contains( $correct_response_pattern, '[,]' ) && ! str_contains( $correct_response_pattern, '[.]' ) ) {
				if ( isset( $statement->activity_interaction_type ) && 'true-false' === $statement->activity_interaction_type ) {
					self::get_true_false_statement_response( $correct_response_pattern, $result_response, $response_results );
				} else {
					self::get_choices_statement_response( $all_choices, $correct_response_pattern, $result_response, $response_results );
				}
			}
			if ( str_contains( $correct_response_pattern, '[.]' ) ) {
				self::get_drag_and_drop_statements_result_response( $correct_response_pattern, $statement, $h5p_targets, $choices, $all_choices, $response_results );
			}
		}
		if ( isset( $correct_response_pattern ) && ! isset( $choices ) ) {
			if ( str_contains( $correct_response_pattern, '[,]' ) && ! str_contains( $correct_response_pattern, '[.]' ) ) {
				$correct_responses = explode( '[,]', $correct_response_pattern );
				$user_responses    = explode( '[,]', $result_response );
				if ( count( $correct_responses ) === count( $user_responses ) ) {
					if ( isset( $statement->activity_interaction_type ) && 'fill-in' === $statement->activity_interaction_type ) {
						self::get_fill_in_statement_response( $statement, $user_responses, $correct_responses, $response_results );
					} else {
						self::get_sequencing_statements_result_response( $response_results, $user_responses );
					}
				} else {
					self::prepare_choices_statements_correct_response_results( $correct_responses, $user_responses, $response_results );
					self::get_choices_statements_user_result_response( $user_responses, $response_results );
				}
			}
			if ( ! str_contains( $correct_response_pattern, '[,]' ) && ! str_contains( $correct_response_pattern, '[.]' ) ) {
				if ( isset( $statement->activity_interaction_type ) && 'true-false' === $statement->activity_interaction_type ) {
					self::get_true_false_statement_response( $correct_response_pattern, $result_response, $response_results );
				}
			}
		}
		if ( isset( $statement->activity_interaction_type ) && 'long-fill-in' === $statement->activity_interaction_type ) {
			self::get_essay_statement_response( $statement, $response_results );
		}
		if ( empty( $response_results['template_path'] ) ) {
			return array(
				'template_path' => '',
				'question_type' => 'default',
			);
		}
		return $response_results;
	}

	private static function review_partial_path( $filename ) {
		$base = defined( 'TUTORPRESS_PATH' ) ? TUTORPRESS_PATH : dirname( __DIR__, 3 ) . '/';
		return $base . 'templates/tutorpress/quiz/h5p-review/questions/' . $filename;
	}

	private static function assign_partial( &$response_results, $filename, $question_type ) {
		$response_results['template_path'] = self::review_partial_path( $filename );
		$response_results['question_type'] = $question_type;
	}

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

	private static function prepare_choices_statements_correct_response_results( $correct_responses, $user_responses, &$response_results, $all_choices = null, $choices = null ) {
		if ( isset( $all_choices ) && isset( $choices ) ) {
			foreach ( $correct_responses as $response_id ) {
				if ( isset( $all_choices[ $response_id ] ) && count( $choices ) === count( $user_responses ) ) {
					$response_results[ $response_id ] = (object) array(
						'is_correct'  => true,
						'description' => self::get_xapi_locale_text( $all_choices[ $response_id ] ),
					);
					unset( $all_choices[ $response_id ] );
				} elseif ( isset( $all_choices[ $response_id ] ) ) {
					$response_results[ $response_id ] = (object) array(
						'is_solution' => true,
						'description' => self::get_xapi_locale_text( $all_choices[ $response_id ] ),
					);
					unset( $all_choices[ $response_id ] );
				}
			}
			foreach ( $all_choices as $choice_id => $choice ) {
				$response_results[ $choice_id ] = (object) array(
					'description' => self::get_xapi_locale_text( $all_choices[ $choice_id ] ),
				);
			}
		} else {
			foreach ( $correct_responses as $response_id => $response ) {
				if ( count( $correct_responses ) === count( $user_responses ) ) {
					$response_results[ $response ] = (object) array(
						'is_correct'  => true,
						'description' => $response,
					);
					unset( $correct_responses[ $response_id ] );
				} else {
					$response_results[ $response ] = (object) array(
						'is_solution' => true,
						'description' => $response,
					);
					unset( $correct_responses[ $response_id ] );
				}
			}
			foreach ( $correct_responses as $response ) {
				$response_results[ $response ] = (object) array(
					'description' => $response,
				);
			}
		}
	}

	private static function get_choices_statements_user_result_response( $user_responses, &$response_results ) {
		foreach ( $user_responses as $user_id ) {
			if ( ! isset( $response_results[ $user_id ] ) || ! is_object( $response_results[ $user_id ] ) ) {
				continue;
			}
			$correct_response = $response_results[ $user_id ];
			if ( ! property_exists( $correct_response, 'is_correct' ) && ! property_exists( $correct_response, 'is_solution' ) ) {
				$correct_response               = (array) $correct_response;
				$correct_response['is_correct'] = false;
				$response_results[ $user_id ]   = (object) $correct_response;
			} elseif ( ! property_exists( $correct_response, 'is_correct' ) && property_exists( $correct_response, 'is_solution' ) ) {
				$correct_response               = (array) $correct_response;
				$correct_response['is_correct'] = true;
				unset( $correct_response['is_solution'] );
				$response_results[ $user_id ] = (object) $correct_response;
			}
		}
		self::assign_partial( $response_results, 'multiple-choice.php', 'multiple_choice' );
	}

	private static function get_sequencing_statements_result_response( &$response_results, $user_responses ) {
		$idx              = 0;
		$correct_response = array_values( $response_results );
		foreach ( $response_results as $result_id => $result ) {
			if ( ! is_object( $result ) ) {
				continue;
			}
			if ( ! isset( $user_responses[ $idx ], $response_results[ $result_id ] ) ) {
				++$idx;
				continue;
			}
			$user_response = (int) filter_var( $user_responses[ $idx ], FILTER_SANITIZE_NUMBER_INT );
			if ( $idx !== $user_response ) {
				$result->is_correct             = false;
				$response_results[ $result_id ] = $result;
			}
			$response                  = (array) $response_results[ $result_id ];
			$response['user_response'] = isset( $correct_response[ $user_response ] ) && is_object( $correct_response[ $user_response ] )
				? ( $correct_response[ $user_response ]->description ?? '' )
				: '';
			$response_results[ $result_id ] = (object) $response;
			++$idx;
		}
		self::assign_partial( $response_results, 'ordering.php', 'ordering' );
	}

	private static function get_drag_and_drop_statements_result_response( $correct_response_pattern, $statement, $h5p_targets, $choices, $all_choices, &$response_results ) {
		$result_response   = ( is_object( $statement ) && isset( $statement->result_response ) ) ? $statement->result_response : '';
		$correct_responses = explode( '[,]', $correct_response_pattern );
		$user_responses    = explode( '[,]', $result_response );
		$all_targets       = is_array( $h5p_targets ) && count( $h5p_targets ) ? array_column( $h5p_targets, 'description', 'id' ) : null;
		$choice_count      = is_array( $all_choices ) ? count( $all_choices ) : 0;
		$target_count      = is_array( $all_targets ) ? count( $all_targets ) : 0;

		foreach ( $correct_responses as $correct_response ) {
			$pair = explode( '[.]', $correct_response );
			if ( ! isset( $pair[0], $pair[1] ) ) {
				continue;
			}
			$choice_id = $pair[1];
			$match_id  = $pair[0];
			if ( ! isset( $all_choices[ $choice_id ] ) ) {
				continue;
			}
			$response_results[ $choice_id ] = (object) array(
				'description'       => self::get_xapi_locale_text( $all_choices[ $choice_id ] ),
				'match_id'          => $match_id,
				'match_description' => self::dnd_match_description( $all_targets, $match_id, $all_choices, $choices ),
				'is_match'          => true,
			);
		}

		$final_response = array();
		foreach ( $user_responses as $user_response ) {
			$pair = explode( '[.]', $user_response );
			if ( ! isset( $pair[0], $pair[1] ) ) {
				continue;
			}
			$choice_id = $pair[1];
			$match_id  = $pair[0];
			$response  = isset( $response_results[ $choice_id ] ) && is_object( $response_results[ $choice_id ] ) ? $response_results[ $choice_id ] : null;
			if ( $response && isset( $response->match_id ) && $response->match_id === $match_id ) {
				$final_response[] = $response;
				continue;
			}
			$row = (object) array(
				'description'       => isset( $all_choices[ $choice_id ] ) ? self::get_xapi_locale_text( $all_choices[ $choice_id ] ) : '',
				'match_id'          => $match_id,
				'match_description' => self::dnd_match_description( $all_targets, $match_id, $all_choices, $choices ),
				'is_match'          => false,
			);
			if ( $response && $choice_count <= $target_count ) {
				$response_results[ $choice_id ] = $row;
			}
			$final_response[] = $row;
		}
		$response_results = $final_response;
		self::assign_partial( $response_results, 'ordering.php', 'ordering' );
	}

	private static function dnd_match_description( $all_targets, $match_id, $all_choices = null, $choices = null ) {
		if ( is_array( $all_targets ) && count( $all_targets ) > 1 && isset( $all_targets[ $match_id ] ) ) {
			return self::get_xapi_locale_text( $all_targets[ $match_id ] );
		}
		if ( is_array( $all_targets ) && isset( $all_targets[0] ) ) {
			return self::get_xapi_locale_text( $all_targets[0] );
		}
		if ( is_array( $all_choices ) && isset( $all_choices[ $match_id ] ) ) {
			return self::get_xapi_locale_text( $all_choices[ $match_id ] );
		}
		if ( is_array( $choices ) && isset( $choices[ $match_id ] ) && is_object( $choices[ $match_id ] ) && isset( $choices[ $match_id ]->description ) ) {
			return self::get_xapi_locale_text( $choices[ $match_id ]->description );
		}
		return '';
	}

	private static function get_choices_statement_response( $all_choices, $correct_response_pattern, $user_response, &$response_results ) {
		if ( isset( $all_choices[ $correct_response_pattern ] ) ) {
			$response_results[ $correct_response_pattern ] = (object) array(
				'is_correct'  => true,
				'description' => self::get_xapi_locale_text( $all_choices[ $correct_response_pattern ] ),
			);
			unset( $all_choices[ $correct_response_pattern ] );
		}
		foreach ( $all_choices as $choice_id => $choice ) {
			$response_results[ $choice_id ] = (object) array(
				'description' => self::get_xapi_locale_text( $all_choices[ $choice_id ] ),
			);
		}
		if ( is_string( $user_response ) && str_contains( $user_response, '[,]' ) ) {
			$user_response = explode( '[,]', $user_response );
		}
		if ( is_array( $user_response ) && count( $user_response ) ) {
			foreach ( $user_response as $response ) {
				if ( ! isset( $response_results[ $response ] ) || ! is_object( $response_results[ $response ] ) ) {
					continue;
				}
				$correct_response = $response_results[ $response ];
				if ( ! property_exists( $correct_response, 'is_correct' ) && ! property_exists( $correct_response, 'is_solution' ) ) {
					$correct_response               = (array) $correct_response;
					$correct_response['is_correct'] = false;
					$response_results[ $response ]  = (object) $correct_response;
				}
			}
		} elseif ( isset( $response_results[ $user_response ] ) ) {
			$correct_response = (array) $response_results[ $user_response ];
			if ( ! isset( $correct_response['is_correct'] ) ) {
				$correct_response['is_correct'] = false;
			}
			$response_results[ $user_response ] = (object) $correct_response;
		}
		self::assign_partial( $response_results, 'multiple-choice.php', 'multiple_choice' );
	}

	private static function get_true_false_statement_response( $correct_response_pattern, $user_response, &$response_results ) {
		$response_results = array(
			'true'  => (object) array(
				'description' => __( 'True', 'tutorpress' ),
			),
			'false' => (object) array(
				'description' => __( 'False', 'tutorpress' ),
			),
		);
		if ( isset( $response_results[ $correct_response_pattern ] ) ) {
			$correct_response                              = (array) $response_results[ $correct_response_pattern ];
			$correct_response['is_correct']                = true;
			$response_results[ $correct_response_pattern ] = (object) $correct_response;
		}
		if ( isset( $response_results[ $user_response ] ) ) {
			$correct_response = (array) $response_results[ $user_response ];
			if ( ! isset( $correct_response['is_correct'] ) ) {
				$correct_response['is_correct'] = false;
			}
			$response_results[ $user_response ] = (object) $correct_response;
		}
		self::assign_partial( $response_results, 'true-false.php', 'true_false' );
	}

	private static function h5p_json_content( $content_id ) {
		if ( ! class_exists( '\H5P_Plugin' ) ) {
			return null;
		}
		$plugin = \H5P_Plugin::get_instance();
		if ( ! $plugin || ! method_exists( $plugin, 'get_content' ) || ! method_exists( $plugin, 'get_content_settings' ) ) {
			return null;
		}
		$content          = $plugin->get_content( $content_id );
		$content_settings = $plugin->get_content_settings( $content );
		if ( ! is_array( $content_settings ) || empty( $content_settings['jsonContent'] ) ) {
			return null;
		}
		$decoded = json_decode( $content_settings['jsonContent'] );
		return is_object( $decoded ) ? $decoded : null;
	}

	private static function get_fill_in_statement_response( $statement, $user_responses, $correct_responses, &$response_results ) {
		$content_id   = ( is_object( $statement ) && isset( $statement->content_id ) ) ? $statement->content_id : 0;
		$decoded      = $content_id ? self::h5p_json_content( $content_id ) : null;
		$main_content = ( is_object( $decoded ) && isset( $decoded->content ) ) ? $decoded->content : null;
		if ( isset( $main_content ) ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$correct_responses = isset( $main_content->blanksList ) ? $main_content->blanksList : array();
			foreach ( $user_responses as $key => $user_response ) {
				if ( ! isset( $correct_responses[ $key ] ) ) {
					continue;
				}
				$haystack = (object) $correct_responses[ $key ];
				// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$haystack = isset( $haystack->correctAnswerText ) ? html_entity_decode( $haystack->correctAnswerText ) : '';
				$haystack = explode( '/', $haystack );
				if ( in_array( $user_response, $haystack, true ) ) {
					$response_results[] = (object) array(
						'description' => $user_response,
						'is_correct'  => true,
					);
				} else {
					$response_results[] = (object) array(
						'description'    => $user_response,
						'is_correct'     => false,
						'correct_answer' => implode( ' / ', $haystack ),
					);
				}
			}
		} else {
			foreach ( $user_responses as $key => $user_response ) {
				if ( ! isset( $correct_responses[ $key ] ) ) {
					continue;
				}
				$correct_answer = strtolower( preg_replace( '/\{[^{]+\}/', '', $correct_responses[ $key ] ) );
				$correct_answer = str_replace( ' ', '', preg_replace( '/[^a-zA-Z0-9]+/', '', $correct_answer ) );
				$user_answer    = strtolower( str_replace( ' ', '', preg_replace( '/[^a-zA-Z0-9]+/', '', $user_response ) ) );
				if ( $correct_answer === $user_answer ) {
					$response_results[] = (object) array(
						'description' => $user_answer,
						'is_correct'  => true,
					);
				} else {
					$response_results[] = (object) array(
						'description'    => $user_answer,
						'is_correct'     => false,
						'correct_answer' => $correct_answer,
					);
				}
			}
		}
		self::assign_partial( $response_results, 'fill-in-the-blanks.php', 'fill_in_the_blank' );
	}

	private static function get_essay_statement_response( $statement, &$response_results ) {
		$content_id     = ( is_object( $statement ) && isset( $statement->content_id ) ) ? $statement->content_id : 0;
		$decoded        = $content_id ? self::h5p_json_content( $content_id ) : null;
		$essay_keywords = ( is_object( $decoded ) && isset( $decoded->keywords ) ) ? $decoded->keywords : null;
		if ( is_object( $statement ) && isset( $statement->activity_interaction_type ) && 'long-fill-in' === $statement->activity_interaction_type ) {
			$user_answer = isset( $statement->result_response ) ? $statement->result_response : '';
			if ( isset( $essay_keywords ) ) {
				foreach ( $essay_keywords as $essay_keyword ) {
					if ( isset( $essay_keyword->keyword ) ) {
						$user_answer = str_replace(
							preg_replace( '/[^a-zA-Z]+/', '', $essay_keyword->keyword ),
							"<span class='tutor-fw-bold tutor-color-success'>" . esc_attr( preg_replace( '/[^a-zA-Z]+/', '', $essay_keyword->keyword ) ) . '</span>',
							$user_answer
						);
					}
					if ( isset( $essay_keyword->alternatives ) ) {
						foreach ( $essay_keyword->alternatives as $alternative ) {
							$user_answer = str_replace(
								preg_replace( '/[^a-zA-Z]+/', '', $alternative ),
								"<span class='tutor-fw-bold tutor-color-success'>" . esc_attr( preg_replace( '/[^a-zA-Z]+/', '', $alternative ) ) . '</span>',
								$user_answer
							);
						}
					}
				}
				$response_results[] = array(
					'essay_result' => $user_answer,
				);
			}
		}
		self::assign_partial( $response_results, 'essay.php', 'open_ended' );
	}

	/**
	 * Optional AND fragments for tutor_h5p_quiz_result (id 0 omitted).
	 *
	 * @since 2.2.0
	 * @param int $content_id  Content id.
	 * @param int $attempt_id  Attempt id.
	 * @param int $quiz_id     Quiz id.
	 * @param int $question_id Question id.
	 * @param int $user_id     User id.
	 * @return string
	 */
	private static function prepared_optional_result_where( $content_id, $attempt_id, $quiz_id, $question_id, $user_id ) {
		global $wpdb;

		$where = '';
		if ( 0 !== $content_id ) {
			$where .= $wpdb->prepare( ' AND content_id = %d', $content_id );
		}
		if ( 0 !== $attempt_id ) {
			$where .= $wpdb->prepare( ' AND attempt_id = %d', $attempt_id );
		}
		if ( 0 !== $quiz_id ) {
			$where .= $wpdb->prepare( ' AND quiz_id = %d', $quiz_id );
		}
		if ( 0 !== $question_id ) {
			$where .= $wpdb->prepare( ' AND question_id = %d', $question_id );
		}
		if ( 0 !== $user_id ) {
			$where .= $wpdb->prepare( ' AND user_id = %d', $user_id );
		}

		return $where;
	}
}
