<?php
/**
 * Step 4: verify H5P learner-bridge mark filters + attempt-delete cleanup.
 *
 * Product-target (predicate true): hooks registered. Isolated synthetic attempt_id
 * exercises answer-data / earned / D20 missing-result / D27 inflation. Cleans up.
 *
 * Usage:
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-learner-bridge-marks.php --allow-root
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

if ( ! class_exists( 'TutorPress_H5P_Learner_Bridge' ) ) {
	$fail( 'TutorPress_H5P_Learner_Bridge unavailable.' );
}

$bridge = 'TutorPress_H5P_Learner_Bridge';

// --- Predicate helper matrix (A/B/C) ---
$assert( true === $bridge::should_register_learner_bridge( true, false ), 'A: WP-on Pro-off must register.' );
$assert( false === $bridge::should_register_learner_bridge( false, false ), 'B: WP-off must not register.' );
$assert( false === $bridge::should_register_learner_bridge( true, true ), 'C: Pro-runtime-on must not register.' );

// --- Live registration (product target: predicate true) ---
$assert(
	false !== has_filter( 'tutor_filter_update_before_question_mark', array( $bridge, 'filter_total_marks' ) ),
	'filter_total_marks must be registered.'
);
$assert(
	false !== has_filter( 'tutor_filter_quiz_total_marks', array( $bridge, 'filter_total_quiz_marks' ) ),
	'filter_total_quiz_marks must be registered.'
);
$assert(
	false !== has_filter( 'tutor_filter_quiz_answer_data', array( $bridge, 'filter_quiz_answer_data' ) ),
	'filter_quiz_answer_data must be registered.'
);
$assert(
	false !== has_action( 'tutor_quiz/attempt_deleted', array( $bridge, 'delete_h5p_quiz_result_by_attempt_id' ) ),
	'attempt_deleted cleanup must be registered.'
);
$assert(
	false === has_filter( 'tutor_quiz_process_custom_question_answer' ),
	'D17: must not register tutor_quiz_process_custom_question_answer.'
);

global $wpdb;

// Discover two live h5p questions (prefer quiz 9706 fixtures; else any pair).
$h5p_rows = $wpdb->get_results(
	"SELECT question_id, quiz_id, question_description AS content_id
	FROM {$wpdb->prefix}tutor_quiz_questions
	WHERE question_type = 'h5p'
	ORDER BY quiz_id ASC, question_id ASC
	LIMIT 10"
);
$assert( is_array( $h5p_rows ) && count( $h5p_rows ) >= 2, 'Need at least two h5p questions for mark fixtures.' );

$q1        = (int) $h5p_rows[0]->question_id;
$q2        = (int) $h5p_rows[1]->question_id;
$content1  = (int) trim( (string) $h5p_rows[0]->content_id );
$content2  = (int) trim( (string) $h5p_rows[1]->content_id );
$quiz_id   = (int) $h5p_rows[0]->quiz_id;
$user_id   = (int) get_current_user_id();
if ( $user_id < 1 ) {
	$user_id = 1;
}
$attempt_id = 999999401; // synthetic; isolated from live attempts

echo "FIXTURE: quiz={$quiz_id} q1={$q1}/c{$content1} q2={$q2}/c{$content2} attempt={$attempt_id} user={$user_id}\n";

$q1_row = \Tutor\Models\QuizModel::get_quiz_question_by_id( $q1 );
$q2_row = \Tutor\Models\QuizModel::get_quiz_question_by_id( $q2 );
$assert( $q1_row && 'h5p' === $q1_row->question_type, "Question {$q1} must be h5p." );
$assert( $q2_row && 'h5p' === $q2_row->question_type, "Question {$q2} must be h5p." );

$cleanup = static function () use ( $bridge, $attempt_id ) {
	$bridge::delete_h5p_quiz_result_by_attempt_id( $attempt_id );
};

$cleanup();

$insert_result = static function ( $question_id, $content_id, $max, $raw ) use ( $wpdb, $user_id, $attempt_id, $quiz_id, $fail ) {
	$ok = $wpdb->insert(
		"{$wpdb->prefix}tutor_h5p_quiz_result",
		array(
			'quiz_id'     => (int) $quiz_id,
			'question_id' => (int) $question_id,
			'content_id'  => (int) $content_id,
			'user_id'     => (int) $user_id,
			'attempt_id'  => (int) $attempt_id,
			'max_score'   => (int) $max,
			'raw_score'   => (int) $raw,
			'finished'    => time(),
		),
		array( '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d' )
	);
	if ( false === $ok ) {
		$fail( 'Could not insert fixture result row.' );
	}
};

// --- Answer-data + earned marks ---
$insert_result( $q1, $content1, 5, 3 );
$answers = $bridge::filter_quiz_answer_data(
	array(
		'question_mark' => 0,
		'achieved_mark' => 0,
		'is_correct'    => null,
	),
	$q1,
	'h5p',
	$user_id,
	$attempt_id
);
$assert( 5 == $answers['question_mark'], 'answer_data question_mark must be max_score 5.' );
$assert( 3 == $answers['achieved_mark'], 'answer_data achieved_mark must be raw_score 3.' );
$assert( false === $answers['is_correct'], 'answer_data is_correct must be false when raw !== max.' );

$earned = $bridge::filter_total_quiz_marks( 10, $q1, 'h5p', $user_id, $attempt_id );
$assert( 13 == $earned, 'total_quiz_marks must add raw_score (10+3=13).' );

$passthrough = $bridge::filter_total_quiz_marks( 10, $q1, 'true_false', $user_id, $attempt_id );
$assert( 10 == $passthrough, 'non-h5p total_quiz_marks must passthrough.' );

// --- D20 missing-result path (Pro quirk): answered H5P < count(question_ids) ---
$cleanup();
$insert_result( $q1, $content1, 5, 3 ); // only q1 answered; question_ids lists both → zero + delete
$missing_total = $bridge::filter_total_marks( 10, array( $q1, $q2 ), $user_id, $attempt_id );
$assert( 0 == $missing_total, 'D20 missing-result must set total_marks to 0.' );
$left = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}tutor_h5p_quiz_result WHERE attempt_id = %d",
		$attempt_id
	)
);
$assert( 0 === $left, 'D20 missing-result must delete attempt H5P results.' );

// --- D27 inflation: Core sum + Σ H5P max when all listed H5P answered ---
$insert_result( $q1, $content1, 5, 3 );
$insert_result( $q2, $content2, 7, 7 );
$inflated = $bridge::filter_total_marks( 10, array( $q1, $q2 ), $user_id, $attempt_id );
$assert( 22 == $inflated, 'D27 inflation: 10 + max(5) + max(7) = 22.' );
echo "NOTE: D27 total_marks inflation observed (Core 10 + H5P max 5+7 = 22). Pro-exact; not a bug.\n";

// --- delete accepts comma-string ---
$cleanup();
$insert_result( $q1, $content1, 1, 1 );
$bridge::delete_h5p_quiz_result_by_attempt_id( (string) $attempt_id );
$left2 = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->prefix}tutor_h5p_quiz_result WHERE attempt_id = %d",
		$attempt_id
	)
);
$assert( 0 === $left2, 'delete_h5p_quiz_result_by_attempt_id must accept string attempt id.' );

$cleanup();

echo "PASS: Step 4 mark filters + attempt-delete cleanup.\n";
exit( 0 );
