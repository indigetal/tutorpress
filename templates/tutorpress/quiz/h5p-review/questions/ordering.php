<?php
/**
 * H5P review ordering (read-only).
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

$response_result = $response_result ?? ( $data['response_results'] ?? array() );
?>
<div class="tutor-quiz-review-dnd-grid">
	<div class="tutor-quiz-review-dnd-head">
		<div class="tutor-quiz-review-col-title tutor-quiz-review-given"><?php esc_html_e( 'Given Answer', 'tutorpress' ); ?></div>
		<div class="tutor-quiz-review-col-title tutor-quiz-review-correct"><?php esc_html_e( 'Correct Answer', 'tutorpress' ); ?></div>
	</div>

	<div class="tutor-quiz-review-dnd-rows">
		<?php
		foreach ( (array) $response_result as $response ) :
			if ( ! is_object( $response ) ) {
				continue;
			}
			$given_answer   = '';
			$correct_answer = '';
			$option_attr    = 'solution';

			if ( isset( $response->user_response ) ) {
				$given_answer = $response->user_response;
			} else {
				$given_answer = $response->description ?? '';
			}

			if ( isset( $response->match_description ) ) {
				$correct_answer = $response->match_description;
			} else {
				$correct_answer = $response->description ?? '';
			}

			if ( isset( $response->is_correct ) && $response->is_correct ) {
				$option_attr = 'correct';
			} elseif ( isset( $response->is_correct ) && ! $response->is_correct ) {
				$option_attr = 'incorrect';
			}

			if ( isset( $response->is_match ) && $response->is_match ) {
				$option_attr = 'correct';
			} elseif ( isset( $response->is_match ) && ! $response->is_match ) {
				$option_attr = 'incorrect';
			}
			?>
			<div class="tutor-quiz-review-dnd-row">
				<div class="tutor-quiz-review-item tutor-quiz-review-given" data-option="<?php echo esc_attr( $option_attr ); ?>">
					<span><?php echo esc_html( $given_answer ); ?></span>
				</div>
				<div class="tutor-quiz-review-item tutor-quiz-review-correct" data-option="neutral">
					<span><?php echo esc_html( $correct_answer ); ?></span>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
