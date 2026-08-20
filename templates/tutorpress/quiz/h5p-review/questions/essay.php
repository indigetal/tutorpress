<?php
/**
 * H5P review essay (read-only).
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

$response_result = $response_result ?? ( $data['response_results'] ?? array() );
?>
<div class="tutor-quiz-question-options">
	<div class="tutor-input-field">
		<div class="tutor-input-wrapper">
			<div class="tutor-input tutor-text-area tutor-input-content-clear">
				<?php
				if ( isset( $response_result[0] ) && is_array( $response_result[0] ) && isset( $response_result[0]['essay_result'] ) ) {
					echo wp_kses_post( trim( (string) $response_result[0]['essay_result'] ) );
				}
				?>
			</div>
		</div>
	</div>
</div>
