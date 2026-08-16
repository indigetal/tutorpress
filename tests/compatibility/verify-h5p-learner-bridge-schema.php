<?php
/**
 * Step 10b: D5a Field/Type/Null/Key + D28 quiz-only CREATE + idempotent ensure.
 *
 * Type: case-insensitive, width ignored (int vs int(11)). No Default/Extra hard-fail.
 * Usage: wp82 eval-file .../verify-h5p-learner-bridge-schema.php --allow-root
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
global $wpdb;
$norm = static function ( $type ) {
	return strtolower( (string) preg_replace( '/\(\d+\)/', '', (string) $type ) );
};

/** Compare DESCRIBE to compact "field|type|null|key" rows. */
$assert_describe = static function ( $table, $rows ) use ( $wpdb, $assert, $norm ) {
	$expect = array();
	foreach ( $rows as $row ) {
		$p                 = explode( '|', $row );
		$expect[ $p[0] ] = array( $p[1], $p[2], $p[3] );
	}
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed suffix table.
	$live = $wpdb->get_results( "DESCRIBE `{$table}`", ARRAY_A );
	$assert( is_array( $live ) && count( $live ) === count( $expect ), "{$table}: column count mismatch." );
	foreach ( $live as $col ) {
		$f = $col['Field'];
		$assert( isset( $expect[ $f ] ), "{$table}: unexpected Field {$f}." );
		list( $t, $n, $k ) = $expect[ $f ];
		$assert( $norm( $t ) === $norm( $col['Type'] ), "{$table}.{$f} Type: {$col['Type']} vs {$t}." );
		$assert( $n === $col['Null'] && $k === $col['Key'], "{$table}.{$f} Null/Key drift." );
		unset( $expect[ $f ] );
	}
	$assert( 0 === count( $expect ), "{$table}: missing " . implode( ',', array_keys( $expect ) ) );
};

$result_rows = array(
	'result_id|bigint(20)|NO|PRI', 'quiz_id|bigint(20)|YES|', 'attempt_id|bigint(20)|YES|',
	'question_id|bigint(20)|YES|', 'user_id|bigint(20)|YES|', 'content_id|bigint(20)|YES|',
	'response|text|YES|', 'max_score|int(11)|YES|', 'raw_score|int(11)|YES|',
	'scaled_score|int(11)|YES|', 'min_score|int(11)|YES|', 'completion|tinyint(1)|YES|',
	'success|tinyint(1)|YES|', 'opened|int(10)|YES|', 'finished|int(10)|YES|', 'duration|bigint(20)|YES|',
);
$statement_rows = array(
	'statement_id|bigint(20)|NO|PRI', 'instructor_id|bigint(20)|YES|', 'course_id|bigint(20)|YES|',
	'topic_id|bigint(20)|YES|', 'quiz_id|bigint(20)|YES|', 'question_id|bigint(20)|YES|',
	'content_id|bigint(20)|YES|', 'user_id|bigint(20)|YES|', 'verb|varchar(20)|YES|',
	'verb_id|text|YES|', 'activity_name|text|YES|', 'activity_description|text|YES|',
	'activity_choices|text|YES|', 'activity_target|text|YES|', 'activity_interaction_type|text|YES|',
	'activity_correct_response_pattern|text|YES|', 'result_response|text|YES|',
	'result_max_score|int(11)|YES|', 'result_raw_score|int(11)|YES|', 'result_scaled_score|int(11)|YES|',
	'result_min_score|int(11)|YES|', 'result_completion|tinyint(1)|YES|', 'result_success|tinyint(1)|YES|',
	'result_duration|text|YES|', 'created_at|datetime|YES|', 'quiz_result_id|bigint(20)|YES|',
);

$rt = $wpdb->prefix . 'tutor_h5p_quiz_result';
$st = $wpdb->prefix . 'tutor_h5p_quiz_statement';
$assert_describe( $rt, $result_rows );
$assert_describe( $st, $statement_rows );
$bridge::maybe_create_tables();
$assert_describe( $rt, $result_rows );
$assert_describe( $st, $statement_rows );

$src = file_get_contents( TUTORPRESS_PATH . 'includes/tutorlms/overrides/class-tutorpress-h5p-learner-bridge.php' );
$assert( is_string( $src ) && '' !== $src, 'Bridge source unreadable.' );
$assert( 1 === preg_match( '/function\s+maybe_create_tables\s*\(\s*\)\s*\{(.*?)\n\t\}/s', $src, $m ), 'maybe_create_tables body missing.' );
$body = $m[1];
$assert( 2 === preg_match_all( '/CREATE TABLE/', $body ), 'Expected exactly 2 CREATE TABLE.' );
$assert( false !== strpos( $body, 'tutor_h5p_quiz_result' ) && false !== strpos( $body, 'tutor_h5p_quiz_statement' ), 'Must CREATE both quiz tables.' );
$assert( false === strpos( $body, 'tutor_h5p_statement' ) && false === strpos( $body, 'tutor_h5p_lesson_statement' ), 'D28: no generic/lesson CREATE.' );

echo "PASS: Step 10b D5a schema + D28 quiz-only CREATE + idempotent ensure.\n";
exit( 0 );
