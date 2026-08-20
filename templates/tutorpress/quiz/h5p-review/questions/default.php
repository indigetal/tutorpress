<?php
/**
 * H5P review fallback: statement score line.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

$statement  = $statement ?? null;
$is_correct = $is_correct ?? 0;
$raw        = ( is_object( $statement ) && isset( $statement->result_raw_score ) ) ? $statement->result_raw_score : '';
$max        = ( is_object( $statement ) && isset( $statement->result_max_score ) ) ? $statement->result_max_score : '';
?>
<div class="tutor-quiz-question-options">
	<div class="tutor-quiz-question-option" data-option="<?php echo esc_attr( $is_correct ? 'correct' : 'incorrect' ); ?>" data-readonly="true">
		<?php echo esc_html( $raw . '/' . $max ); ?>
	</div>
</div>
