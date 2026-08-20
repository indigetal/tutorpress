<?php
/**
 * H5P quiz-result AJAX modal body: statement scores and inline choice/match/essay lists.
 *
 * No iframe. Does not include attempt-details question partials.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! isset( $h5p_quiz_result_statements ) || ! is_array( $h5p_quiz_result_statements ) || ! count( $h5p_quiz_result_statements ) ) {
	return;
}
?>
<div class="tutor-d-flex tutor-flex-column">
<?php
foreach ( $h5p_quiz_result_statements as $statement ) {
	if ( ! is_object( $statement ) ) {
		continue;
	}

	$choices     = isset( $statement->activity_choices ) ? maybe_unserialize( $statement->activity_choices ) : null;
	$h5p_targets = isset( $statement->activity_target ) ? maybe_unserialize( $statement->activity_target ) : null;
	$raw_pattern = isset( $statement->activity_correct_response_pattern ) ? maybe_unserialize( $statement->activity_correct_response_pattern ) : null;
	$pattern     = ( is_array( $raw_pattern ) && isset( $raw_pattern[0] ) ) ? $raw_pattern[0] : null;

	$response_results = TutorPress_H5P_Review_Results::get_h5p_statement_result_response( $statement, $choices, $pattern, $h5p_targets );
	if ( is_array( $response_results ) ) {
		unset( $response_results['template_path'], $response_results['question_type'] );
	} else {
		$response_results = array();
	}

	$name        = isset( $statement->activity_name ) ? (string) $statement->activity_name : '';
	$description = isset( $statement->activity_description ) ? (string) $statement->activity_description : '';
	$raw         = isset( $statement->result_raw_score ) ? $statement->result_raw_score : '';
	$max         = isset( $statement->result_max_score ) ? $statement->result_max_score : '';
	$score_class = ( $raw === $max ) ? 'tutor-color-success' : 'tutor-color-danger';
	$score_text  = (string) $raw . '/' . (string) $max;
	?>
	<div class="tutor-d-flex tutor-flex-column tutor-mb-12">
		<?php if ( '' !== $name ) : ?>
			<div class="tutor-d-flex tutor-align-center tutor-justify-between tutor-mb-12">
				<p class="tutor-fs-5 tutor-text-regular tutor-fw-normal"><?php echo esc_html( $name ); ?></p>
				<p class="tutor-fs-5 <?php echo esc_attr( $score_class ); ?>"><?php echo esc_html( $score_text ); ?></p>
			</div>
		<?php else : ?>
			<div class="tutor-d-flex tutor-align-center tutor-justify-between tutor-mb-12">
				<p class="tutor-fs-5 tutor-text-regular tutor-fw-normal"><?php echo esc_html( $description ); ?></p>
				<p class="tutor-fs-5 tutor-text-regular"><?php echo esc_html( $score_text ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( '' !== $description && $name !== $description ) : ?>
			<div class="tutor-d-flex tutor-align-center tutor-justify-between tutor-mb-12">
				<p class="tutor-fs-6 tutor-text-regular tutor-fw-normal"><?php echo esc_html( $description ); ?></p>
			</div>
		<?php endif; ?>
		<?php if ( is_array( $response_results ) && count( $response_results ) ) : ?>
			<div class="tutor-d-flex tutor-flex-column tutor-mb-16">
				<ul class="tutor-d-flex tutor-flex-column tutor-pl-0" style="padding-left: 0;">
				<?php
				foreach ( $response_results as $result ) {
					if ( ! is_object( $result ) ) {
						if ( is_array( $result ) && isset( $result['essay_result'] ) ) {
							?>
							<li class="tutor-list-item tutor-bg-white tutor-border tutor-p-8 tutor-radius-10 tutor-d-flex tutor-align-center tutor-justify-between">
								<p class="tutor-fw-normal tutor-break-all"><?php echo wp_kses_post( $result['essay_result'] ); ?></p>
							</li>
							<?php
						}
						continue;
					}
					$desc       = isset( $result->description ) ? stripslashes( (string) $result->description ) : '';
					$match_desc = isset( $result->match_description ) ? stripslashes( (string) $result->match_description ) : '';
					$is_default = ! isset( $result->is_correct ) && ! isset( $result->is_solution ) && ! isset( $result->is_match );
					if ( $is_default ) {
						?>
						<li class="tutor-list-item tutor-bg-white tutor-border tutor-p-8 tutor-radius-10 tutor-d-flex tutor-align-center tutor-justify-between">
							<p class="tutor-fw-normal"><?php echo esc_html( $desc ); ?></p>
						</li>
						<?php
					} elseif ( isset( $result->is_match ) ) {
						$ok    = (bool) $result->is_match;
						$color = $ok ? 'success' : 'danger';
						$icon  = $ok ? 'mark' : 'times';
						?>
						<li class="tutor-list-item tutor-d-flex tutor-align-center tutor-justify-between">
							<div class="tutor-d-inline-flex tutor-align-center tutor-justify-between">
								<div class="tutor-bg-white tutor-p-12 tutor-radius-10 tutor-border">
									<p class="tutor-color-success tutor-fw-normal"><?php echo esc_html( $match_desc ); ?></p>
								</div>
								<div class="tutor-p-12">&equals;</div>
								<div class="tutor-bg-white tutor-p-12 tutor-radius-10 tutor-border">
									<p class="tutor-color-<?php echo esc_attr( $color ); ?> tutor-fw-normal"><?php echo esc_html( $desc ); ?></p>
								</div>
							</div>
							<div>
								<span class="tutor-icon-<?php echo esc_attr( $icon ); ?> tutor-color-<?php echo esc_attr( $color ); ?>"></span>
							</div>
						</li>
						<?php
					} elseif ( isset( $result->is_correct ) && true === $result->is_correct ) {
						?>
						<li class="tutor-list-item tutor-bg-white tutor-border tutor-p-8 tutor-radius-10 tutor-d-flex tutor-align-center tutor-justify-between">
							<p class="tutor-fw-normal tutor-color-success"><?php echo esc_html( $desc ); ?></p>
							<div><span class="tutor-icon-mark tutor-color-success"></span></div>
						</li>
						<?php
					} elseif ( isset( $result->is_solution ) ) {
						?>
						<li class="tutor-list-item tutor-btn-ghost-light tutor-border tutor-p-8 tutor-radius-10 tutor-d-flex tutor-align-center tutor-justify-between">
							<p class="tutor-fw-normal tutor-color-muted"><?php echo esc_html( $desc ); ?></p>
							<div><span class="tutor-icon-mark tutor-color-muted"></span></div>
						</li>
						<?php
					} else {
						?>
						<li class="tutor-list-item tutor-bg-white tutor-border tutor-p-8 tutor-radius-10 tutor-d-inline-flex tutor-align-center tutor-justify-between">
							<p class="tutor-fw-normal tutor-color-danger"><?php echo esc_html( $desc ); ?></p>
							<div><span class="tutor-icon-times tutor-color-danger"></span></div>
						</li>
						<?php
					}
				}
				?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
?>
</div>
