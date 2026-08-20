<?php
/**
 * H5P review fill-in-the-blanks (read-only).
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

$response_result      = $response_result ?? ( $data['response_results'] ?? array() );
$question_description = $question_description ?? '';

if ( '' === $question_description ) {
	$statement = $statement ?? ( $data['statement'] ?? null );
	if ( is_object( $statement ) && isset( $statement->activity_description ) ) {
		$question_description = $statement->activity_description;
	}
}

$given_answer_count = 0;

$given_answer = preg_replace_callback(
	'/__________/',
	function () use ( $response_result, &$given_answer_count ) {
		$response = is_array( $response_result ) ? ( $response_result[ $given_answer_count ] ?? null ) : null;
		$given_answer_count++;
		if ( ! is_object( $response ) ) {
			return '';
		}
		$option = ( isset( $response->is_correct ) && $response->is_correct ) ? 'correct' : 'incorrect';
		return sprintf(
			"<span class='tutor-quiz-question-input' data-option='%s'>%s</span>",
			esc_attr( $option ),
			esc_html( $response->description ?? '' )
		);
	},
	(string) $question_description,
	-1
);

$correct_answer_count = 0;

$correct_answer = preg_replace_callback(
	'/__________/',
	function () use ( $response_result, &$correct_answer_count ) {
		$response = is_array( $response_result ) ? ( $response_result[ $correct_answer_count ] ?? null ) : null;
		$correct_answer_count++;
		if ( ! is_object( $response ) ) {
			return '';
		}
		$text = ( isset( $response->correct_answer ) && $response->correct_answer )
			? $response->correct_answer
			: ( $response->description ?? '' );
		return sprintf(
			"<span class='tutor-quiz-question-input' data-option='%s'>%s</span>",
			esc_attr( 'correct' ),
			esc_html( $text )
		);
	},
	(string) $question_description,
	-1
);
?>
<div class="tutor-quiz-question-options">
	<div class="tutor-quiz-question-option" data-readonly="true">
		<div class="tutor-quiz-review-col-title"><?php esc_html_e( 'Given Answer', 'tutorpress' ); ?></div>
		<p><?php echo wp_kses_post( $given_answer ?? '' ); ?></p>
	</div>
	<div class="tutor-quiz-question-option" data-readonly="true">
		<div class="tutor-quiz-review-col-title"><?php esc_html_e( 'Correct Answer', 'tutorpress' ); ?></div>
		<p><?php echo wp_kses_post( $correct_answer ?? '' ); ?></p>
	</div>
</div>
