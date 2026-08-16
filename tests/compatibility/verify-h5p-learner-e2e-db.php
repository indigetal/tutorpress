<?php
/**
 * Step 11+: E2E DB asserts for a captured learner attempt (D17/D19/D27/D29/D30).
 *
 * Usage:
 *   TUTORPRESS_E2E_ATTEMPT_ID=<id> wp82 eval-file .../verify-h5p-learner-e2e-db.php --allow-root
 * Optional: TUTORPRESS_E2E_QUIZ_ID=<quiz_id> (must match attempt.quiz_id when set)
 */

$fail = static function ( $message ) {
	fwrite( STDERR, "FAIL: {$message}\n" );
	exit( 1 );
};
$assert = static function ( $condition, $message ) use ( $fail ) {
	if ( ! $condition ) {
		$fail( $message );
	}
};

$attempt_id = (int) ( getenv( 'TUTORPRESS_E2E_ATTEMPT_ID' ) ?: 0 );
$assert( $attempt_id > 0, 'Set TUTORPRESS_E2E_ATTEMPT_ID to a captured attempt id.' );

$attempt = tutor_utils()->get_attempt( $attempt_id );
$assert( $attempt && isset( $attempt->attempt_id ), "Attempt {$attempt_id} not found." );
$assert( 'attempt_ended' === $attempt->attempt_status, "Attempt {$attempt_id} status must be attempt_ended (got {$attempt->attempt_status})." );

$quiz_id = (int) $attempt->quiz_id;
$user_id = (int) $attempt->user_id;
$expect_quiz = (int) ( getenv( 'TUTORPRESS_E2E_QUIZ_ID' ) ?: 0 );
if ( $expect_quiz > 0 ) {
	$assert( $quiz_id === $expect_quiz, "Attempt quiz_id {$quiz_id} must equal TUTORPRESS_E2E_QUIZ_ID {$expect_quiz}." );
}

global $wpdb;
$questions = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT question_id, TRIM(question_description) AS content_id
		FROM {$wpdb->prefix}tutor_quiz_questions
		WHERE quiz_id = %d AND question_type = 'h5p'
		ORDER BY question_id ASC",
		$quiz_id
	)
);
$assert( is_array( $questions ) && count( $questions ) > 0, "Quiz {$quiz_id} must have h5p questions." );

$sum_raw = 0;
$sum_max = 0;
foreach ( $questions as $q ) {
	$qid = (int) $q->question_id;
	$cid = (int) $q->content_id;
	$assert( $cid > 0, "Question {$qid} must have numeric content_id." );

	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT result_id, raw_score, max_score FROM {$wpdb->prefix}tutor_h5p_quiz_result
			WHERE user_id = %d AND quiz_id = %d AND attempt_id = %d AND question_id = %d AND content_id = %d",
			$user_id,
			$quiz_id,
			$attempt_id,
			$qid,
			$cid
		)
	);
	$assert( is_array( $rows ) && 1 === count( $rows ), "D19/D30: need exactly 1 result for {$qid}→{$cid} (got " . count( (array) $rows ) . ').' );
	$raw = (int) $rows[0]->raw_score;
	$max = (int) $rows[0]->max_score;
	$sum_raw += $raw;
	$sum_max += $max;

	$ans = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT given_answer, question_mark, achieved_mark FROM {$wpdb->prefix}tutor_quiz_attempt_answers
			WHERE quiz_attempt_id = %d AND question_id = %d LIMIT 1",
			$attempt_id,
			$qid
		)
	);
	$assert( $ans, "Attempt-answer row missing for question {$qid}." );
	$given = null === $ans->given_answer ? '' : (string) $ans->given_answer;
	$assert( '' === $given || '""' === $given, "D17: given_answer for {$qid} must be empty (got " . wp_json_encode( $ans->given_answer ) . ').' );
	$assert( (float) $ans->achieved_mark === (float) $raw, "achieved_mark for {$qid} must equal result raw {$raw} (got {$ans->achieved_mark})." );
	$assert( (float) $ans->question_mark === (float) $max, "question_mark for {$qid} must equal result max {$max} (got {$ans->question_mark})." );
}

$earned = (float) $attempt->earned_marks;
$total  = (float) $attempt->total_marks;
$assert( $earned === (float) $sum_raw, "earned_marks must equal Σ raw ({$sum_raw}); got {$earned}." );

echo "INFO: attempt={$attempt_id} quiz={$quiz_id} user={$user_id} earned={$earned} total_marks={$total} Σraw={$sum_raw} Σmax={$sum_max}\n";
if ( $total > (float) $sum_max ) {
	echo "INFO: D27 total_marks inflation — total_marks ({$total}) > Σ H5P max ({$sum_max}); Pro-exact, do not fix.\n";
} else {
	echo "INFO: D27 total_marks={$total} vs Σ H5P max={$sum_max} (document; may equal when Core question_mark is 0).\n";
}

echo "PASS: E2E DB asserts for attempt {$attempt_id}.\n";
exit( 0 );
