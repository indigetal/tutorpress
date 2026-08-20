<?php
/**
 * H5P review-answers chrome (read-only). No iframe.
 *
 * @package TutorPress
 * @since 2.2.0
 */

use Tutor\Components\SvgIcon;
use TUTOR\Icon;

defined( 'ABSPATH' ) || exit;

$data           = ( isset( $data ) && is_array( $data ) ) ? $data : array();
$question       = $data['question'] ?? null;
$question_title = ( is_object( $question ) && isset( $question->question_title ) ) ? $question->question_title : '';
$index          = isset( $data['index'] ) ? (int) $data['index'] + 1 : 0;
$statement      = $data['statement'] ?? null;
$is_correct     = ( is_object( $question ) && isset( $question->is_correct ) ) ? (int) $question->is_correct : 0;
$template_path  = ( isset( $data['template_path'] ) && is_string( $data['template_path'] ) ) ? $data['template_path'] : '';
$question_type  = isset( $data['question_type'] ) ? (string) $data['question_type'] : 'default';
$attempt_id           = (int) ( $data['attempt_id'] ?? 0 );
$is_instructor_review = ! empty( $data['is_instructor_review'] );
$review_field_name    = isset( $data['review_field_name'] ) ? (string) $data['review_field_name'] : '';
?>
<div class="tutor-quiz-question" data-question="<?php echo esc_attr( $question_type ); ?>">
	<div class="tutor-quiz-question-header">
		<div class="tutor-quiz-question-number"><?php echo esc_html( (string) $index ); ?></div>
		<div class="tutor-quiz-question-title"><?php echo esc_html( wp_unslash( $question_title ) ); ?></div>
		<div class="tutor-quiz-question-header-status"><?php echo esc_html( $is_correct ? __( 'Correct', 'tutorpress' ) : __( 'Incorrect', 'tutorpress' ) ); ?></div>
		<?php if ( $is_instructor_review && $attempt_id && $review_field_name ) : ?>
			<div class="tutor-quiz-question-header-divider" aria-hidden="true"></div>
			<div class="tutor-quiz-question-review-actions">
				<input
					type="hidden"
					name="<?php echo esc_attr( $review_field_name ); ?>"
					value="<?php echo esc_attr( $is_correct ); ?>"
					x-bind="register('<?php echo esc_attr( $review_field_name ); ?>')"
				/>
				<label
					class="tutor-quiz-question-review-action"
					data-review-status="correct"
					title="<?php esc_attr_e( 'Mark as correct', 'tutorpress' ); ?>"
					@click="setValue('<?php echo esc_attr( $review_field_name ); ?>', 'correct', { shouldDirty: true })"
				>
					<input
						class="tutor-quiz-question-review-input"
						type="radio"
						name="<?php echo esc_attr( $review_field_name ); ?>"
						value="correct"
						:checked="watch('<?php echo esc_attr( $review_field_name ); ?>') === 'correct'"
						tabindex="-1"
						aria-hidden="true"
					/>
					<?php SvgIcon::make()->name( Icon::CHECK_2 )->size( 20 )->render(); ?>
				</label>
				<label
					class="tutor-quiz-question-review-action"
					data-review-status="incorrect"
					title="<?php esc_attr_e( 'Mark as incorrect', 'tutorpress' ); ?>"
					@click="setValue('<?php echo esc_attr( $review_field_name ); ?>', 'incorrect', { shouldDirty: true })"
				>
					<input
						class="tutor-quiz-question-review-input"
						type="radio"
						name="<?php echo esc_attr( $review_field_name ); ?>"
						value="incorrect"
						:checked="watch('<?php echo esc_attr( $review_field_name ); ?>') === 'incorrect'"
						tabindex="-1"
						aria-hidden="true"
					/>
					<?php SvgIcon::make()->name( Icon::CROSS )->size( 20 )->render(); ?>
				</label>
			</div>
		<?php endif; ?>
	</div>
	<?php
	if ( $template_path && file_exists( $template_path ) ) {
		include $template_path;
	} else {
		include __DIR__ . '/questions/default.php';
	}
	?>
</div>
