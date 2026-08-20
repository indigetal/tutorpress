<?php
/**
 * H5P review true-false (read-only).
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

use Tutor\Components\SvgIcon;
use TUTOR\Icon;

$response_result = $response_result ?? ( $data['response_results'] ?? array() );
?>
<div class="tutor-quiz-question-options">
	<?php if ( tutor_utils()->count( $response_result ) ) : ?>
		<?php
		foreach ( $response_result as $key => $response ) :
			if ( ! is_object( $response ) ) {
				continue;
			}
			$option_attr = '';
			$is_correct  = isset( $response->is_correct ) && $response->is_correct;
			if ( $is_correct ) {
				$option_attr = 'correct';
			} elseif ( isset( $response->is_correct ) && ! $response->is_correct ) {
				$option_attr = 'incorrect';
			}
			?>
			<div class="tutor-quiz-question-option" data-option="<?php echo esc_attr( $option_attr ); ?>" data-readonly="true">
				<?php SvgIcon::make()->name( $is_correct ? Icon::CHECK_2 : Icon::CROSS )->size( 20 )->render(); ?>
				<?php echo esc_html( $response->description ?? '' ); ?>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>
