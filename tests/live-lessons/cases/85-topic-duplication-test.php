<?php

if ( ! function_exists( 'wp_insert_post' ) ) {
	function wp_insert_post( $postarr, $wp_error = false ) {
		$mode = TutorPress_Topic_Copy_Harness::$fail_insert;
		if ( in_array( $mode, array( 'wp_error', 'false', 'zero' ), true ) && isset( $postarr['post_type'] ) && 'tutor_zoom_meeting' === $postarr['post_type'] ) {
			TutorPress_Topic_Copy_Harness::$events[] = array( 'insert', 'tutor_zoom_meeting', $mode );
			if ( 'wp_error' === $mode ) {
				return new WP_Error( 'insert_failed', 'insert failed' );
			}
			if ( 'false' === $mode ) {
				return false;
			}
			return 0;
		}
		$assign = TutorPress_Topic_Copy_Harness::$fail_assign;
		if ( in_array( $assign, array( 'throw', 'wp_error', 'false', 'zero' ), true ) && isset( $postarr['post_type'] ) && 'tutor_assignments' === $postarr['post_type'] ) {
			TutorPress_Topic_Copy_Harness::$events[] = array( 'insert', 'tutor_assignments', $assign );
			if ( 'throw' === $assign ) {
				throw new RuntimeException( 'assign insert failed' );
			}
			if ( 'wp_error' === $assign ) {
				return new WP_Error( 'insert_failed', 'insert failed' );
			}
			if ( 'false' === $assign ) {
				return false;
			}
			return 0;
		}
		$id                                              = TutorPress_Live_Lessons_Store_Fake::$next_post_id++;
		$postarr['ID']                                   = $id;
		TutorPress_Live_Lessons_Store_Fake::$posts[ $id ] = $postarr;
		TutorPress_Topic_Copy_Harness::$events[]         = array( 'insert', isset( $postarr['post_type'] ) ? $postarr['post_type'] : '', $id );
		return $id;
	}
}
if ( ! function_exists( 'add_post_meta' ) ) {
	function add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
		TutorPress_Topic_Copy_Harness::$events[] = array( 'meta', (int) $post_id, $meta_key );
		if ( 'throw' === TutorPress_Topic_Copy_Harness::$fail_insert && '_tutorpress_live_lesson_sync' === $meta_key ) {
			throw new RuntimeException( 'envelope failed' );
		}
		return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $meta_key, wp_unslash( $meta_value ) );
	}
}
if ( ! function_exists( 'add_option' ) ) {
	function add_option( $name, $bytes, $deprecated = '', $autoload = false ) {
		return TutorPress_Topic_Copy_Harness::$fail_lock ? false : TutorPress_Live_Lessons_Store_Fake::add_option( $name, $bytes );
	}
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $name, $default = false ) {
		return isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $name ] : $default;
	}
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, $key = '', $single = false ) {
		if ( TutorPress_Topic_Copy_Harness::$fail_init && '_tutorpress_live_lesson_sync' === $key ) {
			return $single ? 'present' : array( 'present' );
		}
		$values = array();
		foreach ( TutorPress_Live_Lessons_Store_Fake::meta_rows( $post_id, $key ) as $row ) {
			$values[] = $row['meta_value'];
		}
		return $single ? ( isset( $values[0] ) ? $values[0] : '' ) : $values;
	}
}
if ( ! function_exists( 'wp_delete_post' ) ) {
	function wp_delete_post( $post_id, $force_delete = false ) {
		TutorPress_Topic_Copy_Harness::$deletes[] = array( (int) $post_id, true === $force_delete );
		return TutorPress_Live_Lessons_Store_Fake::delete_post( $post_id );
	}
}

class TutorPress_Topic_Copy_Harness {
	public static $fail_lock   = false;
	public static $fail_init   = false;
	public static $fail_insert = null;
	public static $fail_assign = null;
	public static $fail_commit = false;
	public static $releases    = array();
	public static $deletes     = array();
	public static $events      = array();
}

class TutorPress_Topic_Copy_Wpdb {
	public $options       = 'wp_options';
	public $rows_affected = 0;
	public $statements    = array();

	public function prepare( $query, $name, $prior, $update = null ) {
		$prepared = array( $query, $name, $prior );
		if ( func_num_args() > 3 ) {
			$prepared[] = $update;
		}
		return $prepared;
	}

	public function query( $prepared ) {
		$this->statements[] = $prepared;
		if ( is_array( $prepared ) && isset( $prepared[0] ) && is_string( $prepared[0] ) && 0 === strpos( $prepared[0], 'UPDATE ' ) ) {
			$bytes = $prepared[1];
			$name  = $prepared[2];
			$prior = isset( $prepared[3] ) ? $prepared[3] : null;
			if ( ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) || TutorPress_Live_Lessons_Store_Fake::$options[ $name ] !== $prior ) {
				$this->rows_affected = 0;
				return 0;
			}
			TutorPress_Live_Lessons_Store_Fake::$options[ $name ] = $bytes;
			$this->rows_affected = 1;
			return 1;
		}
		if ( is_string( $prepared ) ) {
			TutorPress_Topic_Copy_Harness::$events[] = array( 'sql', $prepared );
			if ( 'COMMIT' === $prepared && TutorPress_Topic_Copy_Harness::$fail_commit ) {
				$commit_mode = TutorPress_Topic_Copy_Harness::$fail_commit;
				if ( 'wp_error' === $commit_mode ) {
					return new WP_Error( 'commit_failed', 'commit failed' );
				}
				if ( 'zero' === $commit_mode ) {
					return 0;
				}
				if ( 'string_zero' === $commit_mode ) {
					return '0';
				}
				return false;
			}
			return true;
		}
		$name  = $prepared[1];
		$prior = $prepared[2];
		if ( ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) || TutorPress_Live_Lessons_Store_Fake::$options[ $name ] !== $prior ) {
			$this->rows_affected = 0;
			TutorPress_Topic_Copy_Harness::$releases[] = array( $name, $prior, false );
			TutorPress_Topic_Copy_Harness::$events[]   = array( 'release', $name, false );
			return 0;
		}
		unset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ], TutorPress_Live_Lessons_Store_Fake::$autoload[ $name ] );
		$this->rows_affected = 1;
		TutorPress_Topic_Copy_Harness::$releases[] = array( $name, $prior, true );
		TutorPress_Topic_Copy_Harness::$events[]   = array( 'release', $name, true );
		return 1;
	}
}

$GLOBALS['wpdb'] = new TutorPress_Topic_Copy_Wpdb();

$course      = array( 'ID' => 7, 'post_type' => 'courses' );
$destination = array( 'ID' => 9, 'post_type' => 'courses', 'post_author' => 42 );
$source      = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$allow       = static function ( $cap, $id ) { return 'edit_post' === $cap && ( 7 === $id || 9 === $id ); };
$deny        = static function () { return false; };
$broad       = static function ( $cap ) { return 'edit_posts' === $cap; };
$lesson      = array( 'ID' => 30, 'post_type' => 'lesson', 'post_parent' => 8, 'menu_order' => 2, 'post_title' => 'Read', 'post_content' => 'Body', 'post_status' => 'publish', 'meta' => array( '_lesson_settings' => array( 'html5' ), '_edit_lock' => array( 'lock' ), '_edit_last' => array( '9' ) ) );
$quiz        = array( 'ID' => 10, 'post_type' => 'tutor_quiz', 'post_parent' => 8, 'menu_order' => 2, 'post_title' => 'Check', 'post_content' => 'Q', 'post_status' => 'draft', 'meta' => array( 'quiz_option' => array( '1' ) ) );
$assign      = array( 'ID' => 20, 'post_type' => 'tutor_assignments', 'post_parent' => 8, 'menu_order' => 0, 'post_title' => 'Work', 'post_content' => 'A', 'post_status' => 'private', 'meta' => array( 'assignment_option' => array( 'on' ) ) );
$meet        = array( 'ID' => 40, 'post_type' => 'tutor-google-meet', 'post_parent' => 8, 'menu_order' => 5, 'post_title' => 'Meet', 'post_content' => 'G', 'post_status' => 'draft', 'post_author' => 3, 'meta' => array( 'tutor-google-meet-start-datetime' => array( '2026-09-21 15:04:00' ), 'tutor-google-meet-end-datetime' => array( '2026-09-21 16:04:00' ), 'tutor-google-meet-event-details' => array( '{"id":"evt-1","meet_link":"https://meet.example"}' ), 'tutor-google-meet-link' => array( 'https://meet.example' ), '_tutor_gmt_data' => array( '{"id":"gmt-1"}' ) ) );
$zoom        = array( 'ID' => 15, 'post_type' => 'tutor_zoom_meeting', 'post_parent' => 8, 'menu_order' => 5, 'post_title' => 'Zoom', 'post_content' => 'Z', 'post_status' => 'draft', 'post_author' => 3, 'meta' => array( '_tutor_zm_start_date' => array( '2026-09-21' ), '_tutor_zm_start_datetime' => array( '2026-09-21 15:04:00' ), '_tutor_zm_duration' => array( '60' ), '_tutor_zm_duration_unit' => array( 'min' ), '_tutor_zm_for_course' => array( '7' ), '_tutor_zm_for_topic' => array( '8' ), '_tutor_zm_data' => array( '{"id":"z-1","host_id":"host-1","join_url":"https://zoom.example"}' ) ) );
$other       = array( 'ID' => 99, 'post_type' => 'page', 'post_parent' => 8, 'menu_order' => 1 );
$away        = array( 'ID' => 50, 'post_type' => 'lesson', 'post_parent' => 4, 'menu_order' => 0 );
$children    = array( $meet, $zoom, $lesson, $quiz, $assign, $other, $away );
$wrong       = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 4 );
$blocked     = array( 'ID' => 4, 'post_type' => 'courses' );
$target      = array( 'ID' => 80, 'post_type' => 'topics', 'post_parent' => 9 );
$off_target  = array( 'ID' => 81, 'post_type' => 'topics', 'post_parent' => 7 );

$posts   = TutorPress_Live_Lessons_Store_Fake::$posts;
$calls   = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$ledger  = TutorPress_Live_Lessons_Boundary_Fake::$ledger;

tutorpress_live_lessons_assert( null === TutorPress_Topic_Duplication_Service::snapshot( $source, $course, $destination, $children, $deny ) && null === TutorPress_Topic_Duplication_Service::snapshot( $source, $course, $destination, $children, $broad ) && null === TutorPress_Topic_Duplication_Service::snapshot( $wrong, $course, $destination, $children, $allow ) && null === TutorPress_Topic_Duplication_Service::snapshot( $source, $course, $blocked, $children, $allow ), 'snapshot requires edit_post on the source course and the destination course' );
$plan = TutorPress_Topic_Duplication_Service::snapshot( $source, $course, $destination, $children, $allow );
$ids  = array();
$orders = array();
$types = array();
foreach ( $plan as $child ) {
	$ids[]    = $child['ID'];
	$orders[] = $child['menu_order'];
	$types[]  = $child['post_type'];
}
tutorpress_live_lessons_assert( array( 20, 10, 30, 15, 40 ) === $ids && array( 1, 2, 3, 4, 5 ) === $orders && array( 'tutor_assignments', 'tutor_quiz', 'lesson', 'tutor_zoom_meeting', 'tutor-google-meet' ) === $types, 'snapshot sorts every supported topic child by menu order then post ID and assigns sequential target orders' );
tutorpress_live_lessons_assert( $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'snapshot makes no write or provider call while the cutover gate stays false' );
tutorpress_live_lessons_assert( null === TutorPress_Topic_Duplication_Service::copy_non_live( $source, $course, $destination, $target, $children, $deny ) && null === TutorPress_Topic_Duplication_Service::copy_non_live( $source, $course, $destination, $off_target, $children, $allow ) && $posts === TutorPress_Live_Lessons_Store_Fake::$posts, 'non-live copies require the same authorization and a topic on the destination course' );
$copies = TutorPress_Topic_Duplication_Service::copy_non_live( $source, $course, $destination, $target, $children, $allow );
$stored = array();
foreach ( $copies as $copy ) {
	$stored[] = TutorPress_Live_Lessons_Store_Fake::$posts[ $copy['ID'] ];
}
$meta_keys = array();
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	if ( (int) $row['post_id'] === (int) $copies[0]['ID'] || (int) $row['post_id'] === (int) $copies[1]['ID'] || (int) $row['post_id'] === (int) $copies[2]['ID'] ) {
		$meta_keys[] = $row['meta_key'];
	}
}
sort( $meta_keys );
tutorpress_live_lessons_assert( array( array( 'post_type' => 'tutor_assignments', 'post_title' => 'Work', 'post_content' => 'A', 'post_status' => 'private', 'post_parent' => 80, 'menu_order' => 1, 'ID' => $copies[0]['ID'] ), array( 'post_type' => 'tutor_quiz', 'post_title' => 'Check', 'post_content' => 'Q', 'post_status' => 'draft', 'post_parent' => 80, 'menu_order' => 2, 'ID' => $copies[1]['ID'] ), array( 'post_type' => 'lesson', 'post_title' => 'Read', 'post_content' => 'Body', 'post_status' => 'publish', 'post_parent' => 80, 'menu_order' => 3, 'ID' => $copies[2]['ID'] ) ) === $stored && array( '_lesson_settings', 'assignment_option', 'quiz_option' ) === $meta_keys && array( 20, 10, 30, 15, 40 ) === $ids, 'non-live copies are stored on the destination topic without edit meta, and live rows stay in the snapshot' );
tutorpress_live_lessons_assert( $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'non-live copies make no hook, boundary, or provider call' );
TutorPress_Topic_Copy_Harness::$releases = array();
TutorPress_Topic_Copy_Harness::$deletes  = array();
$live = TutorPress_Topic_Duplication_Service::copy_live( $source, $course, $destination, $target, $children, $allow );
$live_meta = array();
$envelopes = array();
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	if ( (int) $row['post_id'] !== (int) $live[0]['ID'] && (int) $row['post_id'] !== (int) $live[1]['ID'] ) {
		continue;
	}
	if ( '_tutorpress_live_lesson_sync' === $row['meta_key'] ) {
		$envelopes[ $row['post_id'] ][] = $row['meta_value'];
		continue;
	}
	$live_meta[ $row['post_id'] ][ $row['meta_key'] ] = $row['meta_value'];
}
ksort( $live_meta[ $live[0]['ID'] ] );
ksort( $live_meta[ $live[1]['ID'] ] );
tutorpress_live_lessons_assert( array( 'post_type' => 'tutor_zoom_meeting', 'post_title' => 'Zoom', 'post_content' => 'Z', 'post_status' => 'publish', 'post_author' => 42, 'post_parent' => 80, 'menu_order' => 4, 'ID' => $live[0]['ID'] ) === TutorPress_Live_Lessons_Store_Fake::$posts[ $live[0]['ID'] ] && array( 'post_type' => 'tutor-google-meet', 'post_title' => 'Meet', 'post_content' => 'G', 'post_status' => 'publish', 'post_author' => 42, 'post_parent' => 80, 'menu_order' => 5, 'ID' => $live[1]['ID'] ) === TutorPress_Live_Lessons_Store_Fake::$posts[ $live[1]['ID'] ], 'live copies are publish rows authored by the destination course' );
tutorpress_live_lessons_assert( array( '_tutor_zm_duration' => '60', '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 15:04:00' ) === $live_meta[ $live[0]['ID'] ] && array( 'tutor-google-meet-end-datetime' => '2026-09-21 16:04:00', 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00' ) === $live_meta[ $live[1]['ID'] ] && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'live copies keep the schedule whitelist and make no provider, hook, or boundary call' );
$zoom_envelope = json_decode( $envelopes[ $live[0]['ID'] ][0], true );
$meet_envelope = json_decode( $envelopes[ $live[1]['ID'] ][0], true );
$zoom_lock     = 'tutorpress_live_lesson_lock_' . $live[0]['ID'];
$meet_lock     = 'tutorpress_live_lesson_lock_' . $live[1]['ID'];
$now           = ( new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) ) )->format( 'Y-m-d H:i:s' );
$accept_lock   = static function () { return true; };
tutorpress_live_lessons_assert( 1 === count( $envelopes[ $live[0]['ID'] ] ) && 1 === count( $envelopes[ $live[1]['ID'] ] ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $zoom_envelope ) ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $meet_envelope ) ) && 1 === $zoom_envelope['version'] && 'unlinked' === $zoom_envelope['state'] && 'zoom' === $zoom_envelope['provider'] && null === $zoom_envelope['provider_id'] && null === $zoom_envelope['operation'] && 42 === $zoom_envelope['owner_id'] && 1 === $meet_envelope['version'] && 'unlinked' === $meet_envelope['state'] && 'google_meet' === $meet_envelope['provider'] && null === $meet_envelope['provider_id'] && null === $meet_envelope['operation'] && 42 === $meet_envelope['owner_id'] && array( array( $zoom_lock, true ), array( $meet_lock, true ) ) === array( array( TutorPress_Topic_Copy_Harness::$releases[0][0], TutorPress_Topic_Copy_Harness::$releases[0][2] ), array( TutorPress_Topic_Copy_Harness::$releases[1][0], TutorPress_Topic_Copy_Harness::$releases[1][2] ) ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $zoom_lock ], TutorPress_Live_Lessons_Store_Fake::$options[ $meet_lock ] ) && array() === TutorPress_Topic_Copy_Harness::$deletes && null === TutorPress_Live_Lesson_Sync_Repository::takeover( (int) $live[0]['ID'], 'initialize', 'takeover-token', TutorPress_Topic_Copy_Harness::$releases[0][1], $now, $accept_lock ) && null === TutorPress_Live_Lesson_Sync_Repository::takeover( (int) $live[1]['ID'], 'initialize', 'takeover-token', TutorPress_Topic_Copy_Harness::$releases[1][1], $now, $accept_lock ), 'both live copies initialize one unlinked envelope and release each unexpired lock' );
$lock_sql = 'DELETE FROM wp_options WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)';
tutorpress_live_lessons_assert( array( $lock_sql, $zoom_lock, TutorPress_Topic_Copy_Harness::$releases[0][1] ) === $GLOBALS['wpdb']->statements[0] && array( $lock_sql, $meet_lock, TutorPress_Topic_Copy_Harness::$releases[1][1] ) === $GLOBALS['wpdb']->statements[1], 'each lock release is one conditional delete of the exact stored bytes' );
$posts_before = TutorPress_Live_Lessons_Store_Fake::$posts;
$meta_before  = TutorPress_Live_Lessons_Store_Fake::$meta;
$options_before = TutorPress_Live_Lessons_Store_Fake::$options;
$zoom_id      = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
TutorPress_Topic_Copy_Harness::$releases  = array();
TutorPress_Topic_Copy_Harness::$deletes   = array();
$acquire_failed = TutorPress_Topic_Duplication_Service::copy_live( $source, $course, $destination, $target, $children, $allow );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $acquire_failed && $posts_before === TutorPress_Live_Lessons_Store_Fake::$posts && $meta_before === TutorPress_Live_Lessons_Store_Fake::$meta && $options_before === TutorPress_Live_Lessons_Store_Fake::$options && array() === TutorPress_Topic_Copy_Harness::$releases && array( array( $zoom_id, true ) ) === TutorPress_Topic_Copy_Harness::$deletes && $zoom_id + 1 === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'acquisition failure deletes the first live copy and does not release or initialize the next child' );
$zoom_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
TutorPress_Topic_Copy_Harness::$fail_init = true;
TutorPress_Topic_Copy_Harness::$releases  = array();
TutorPress_Topic_Copy_Harness::$deletes   = array();
$init_failed = TutorPress_Topic_Duplication_Service::copy_live( $source, $course, $destination, $target, $children, $allow );
TutorPress_Topic_Copy_Harness::$fail_init = false;
tutorpress_live_lessons_assert( null === $init_failed && $posts_before === TutorPress_Live_Lessons_Store_Fake::$posts && $meta_before === TutorPress_Live_Lessons_Store_Fake::$meta && $options_before === TutorPress_Live_Lessons_Store_Fake::$options && array( array( 'tutorpress_live_lesson_lock_' . $zoom_id, true ) ) === array( array( TutorPress_Topic_Copy_Harness::$releases[0][0], TutorPress_Topic_Copy_Harness::$releases[0][2] ) ) && 1 === count( TutorPress_Topic_Copy_Harness::$releases ) && array( array( $zoom_id, true ) ) === TutorPress_Topic_Copy_Harness::$deletes && $zoom_id + 1 === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'initialization failure releases the exact lock, deletes the copy, and stops' );
$zoom_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $zoom_id ] = true;
TutorPress_Topic_Copy_Harness::$fail_init = true;
TutorPress_Topic_Copy_Harness::$releases  = array();
TutorPress_Topic_Copy_Harness::$deletes   = array();
$cleanup_failed = TutorPress_Topic_Duplication_Service::copy_live( $source, $course, $destination, $target, $children, $allow );
TutorPress_Topic_Copy_Harness::$fail_init = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $zoom_id ] );
$cleanup_data = $cleanup_failed instanceof WP_Error ? $cleanup_failed->get_error_data() : null;
$sync_rows    = 0;
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	if ( (int) $row['post_id'] === $zoom_id && '_tutorpress_live_lesson_sync' === $row['meta_key'] ) {
		++$sync_rows;
	}
}
tutorpress_live_lessons_assert( is_array( $cleanup_data ) && $zoom_id === $cleanup_data['record_id'] && 9 === $cleanup_data['course_id'] && 80 === $cleanup_data['topic_id'] && 'error' === $cleanup_data['sync_state'] && 0 === $cleanup_data['sync_version'] && true === $cleanup_data['refetch_required'] && $zoom_id === $cleanup_data['projected_record']['id'] && 'error' === $cleanup_data['projected_record']['sync_state'] && 0 === $cleanup_data['projected_record']['sync_version'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_id ] ) && 'tutor_zoom_meeting' === TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_id ]['post_type'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_id + 1 ] ) && 0 === $sync_rows && array( array( 'tutorpress_live_lesson_lock_' . $zoom_id, true ) ) === array( array( TutorPress_Topic_Copy_Harness::$releases[0][0], TutorPress_Topic_Copy_Harness::$releases[0][2] ) ) && 1 === count( TutorPress_Topic_Copy_Harness::$releases ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_id ] ) && $zoom_id + 1 === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'cleanup failure keeps the copied child and returns the destination persisted-record error' );
TutorPress_Live_Lessons_Store_Fake::$options['tutorpress_live_lesson_lock_1'] = 'owned-bytes';
$release = new ReflectionMethod( 'TutorPress_Topic_Duplication_Service', 'delete_lock' );
$kept    = $release->invoke( null, 'tutorpress_live_lesson_lock_1', 'replacement-bytes' );
$still   = TutorPress_Live_Lessons_Store_Fake::$options['tutorpress_live_lesson_lock_1'];
$deleted = $release->invoke( null, 'tutorpress_live_lesson_lock_1', 'owned-bytes' );
tutorpress_live_lessons_assert( false === $kept && 'owned-bytes' === $still && true === $deleted && ! isset( TutorPress_Live_Lessons_Store_Fake::$options['tutorpress_live_lesson_lock_1'] ) && $lock_sql === $GLOBALS['wpdb']->statements[ count( $GLOBALS['wpdb']->statements ) - 1 ][0], 'a different stored lock value survives the conditional delete' );
$equal_order = 4;
$equal       = array();
foreach ( array( $assign, $quiz, $lesson, $zoom, $meet, $other, $away ) as $child ) {
	$child['menu_order'] = $equal_order;
	$equal[]             = $child;
}
$matrix_plan   = TutorPress_Topic_Duplication_Service::snapshot( $source, $course, $destination, $equal, $allow );
$matrix_ids    = array();
$matrix_orders = array();
foreach ( $matrix_plan as $child ) {
	$matrix_ids[]    = $child['ID'];
	$matrix_orders[] = $child['menu_order'];
}
$matrix_calls    = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$matrix_hooks    = TutorPress_Live_Lessons_Hook_Fake::$calls;
$matrix_ledger   = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$matrix_non_live = TutorPress_Topic_Duplication_Service::copy_non_live( $source, $course, $destination, $target, $equal, $allow );
TutorPress_Topic_Copy_Harness::$releases = array();
TutorPress_Topic_Copy_Harness::$deletes  = array();
$matrix_statements = count( $GLOBALS['wpdb']->statements );
$matrix_live       = TutorPress_Topic_Duplication_Service::copy_live( $source, $course, $destination, $target, $equal, $allow );
tutorpress_live_lessons_assert( array( 10, 15, 20, 30, 40 ) === $matrix_ids && array( 1, 2, 3, 4, 5 ) === $matrix_orders && array( 'tutor_quiz', 'tutor_assignments', 'lesson' ) === array( $matrix_non_live[0]['post_type'], $matrix_non_live[1]['post_type'], $matrix_non_live[2]['post_type'] ) && array( 1, 3, 4 ) === array( $matrix_non_live[0]['menu_order'], $matrix_non_live[1]['menu_order'], $matrix_non_live[2]['menu_order'] ) && 'draft' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[0]['ID'] ]['post_status'] && 'private' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[1]['ID'] ]['post_status'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[2]['ID'] ]['post_status'] && array( 'tutor_zoom_meeting', 'tutor-google-meet' ) === array( $matrix_live[0]['post_type'], $matrix_live[1]['post_type'] ) && array( 2, 5 ) === array( $matrix_live[0]['menu_order'], $matrix_live[1]['menu_order'] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[0]['ID'] ]['post_status'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[1]['ID'] ]['post_status'] && 42 === (int) TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[0]['ID'] ]['post_author'] && 42 === (int) TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[1]['ID'] ]['post_author'], 'equal source order copies mixed children by post ID onto the destination' );
$matrix_zoom   = (int) $matrix_live[0]['ID'];
$matrix_meet   = (int) $matrix_live[1]['ID'];
$matrix_meta   = array( $matrix_zoom => array(), $matrix_meet => array() );
$matrix_stored = array();
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	$pid = (int) $row['post_id'];
	if ( $pid !== $matrix_zoom && $pid !== $matrix_meet ) {
		continue;
	}
	if ( '_tutorpress_live_lesson_sync' === $row['meta_key'] ) {
		$matrix_stored[ $pid ][] = json_decode( $row['meta_value'], true );
		continue;
	}
	$matrix_meta[ $pid ][ $row['meta_key'] ] = $row['meta_value'];
}
ksort( $matrix_meta[ $matrix_zoom ] );
ksort( $matrix_meta[ $matrix_meet ] );
$stripped = array( 'tutor-google-meet-event-details', 'tutor-google-meet-link', '_tutor_gmt_data', '_tutor_zm_data' );
$present  = array_merge( array_keys( $matrix_meta[ $matrix_zoom ] ), array_keys( $matrix_meta[ $matrix_meet ] ) );
tutorpress_live_lessons_assert( array( '_tutor_zm_duration' => '60', '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 15:04:00' ) === $matrix_meta[ $matrix_zoom ] && array( 'tutor-google-meet-end-datetime' => '2026-09-21 16:04:00', 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00' ) === $matrix_meta[ $matrix_meet ] && array() === array_values( array_intersect( $stripped, $present ) ), 'live copies keep the schedule whitelist and omit provider identity' );
$matrix_rows      = array( TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[0]['ID'] ], TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_zoom ], TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[1]['ID'] ], TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[2]['ID'] ], TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_meet ] );
$matrix_envelopes = array( $matrix_zoom => $matrix_stored[ $matrix_zoom ][0], $matrix_meet => $matrix_stored[ $matrix_meet ][0] );
$projected        = TutorPress_Live_Lesson_Topic_Projector::project( $target, $destination, $allow, $matrix_rows, $matrix_envelopes );
tutorpress_live_lessons_assert( is_array( $projected ) && 1 === count( $matrix_stored[ $matrix_zoom ] ) && 1 === count( $matrix_stored[ $matrix_meet ] ) && 1 === $matrix_envelopes[ $matrix_zoom ]['version'] && 'unlinked' === $matrix_envelopes[ $matrix_zoom ]['state'] && null === $matrix_envelopes[ $matrix_zoom ]['provider_id'] && null === $matrix_envelopes[ $matrix_zoom ]['operation'] && 1 === $matrix_envelopes[ $matrix_meet ]['version'] && 'unlinked' === $matrix_envelopes[ $matrix_meet ]['state'] && null === $matrix_envelopes[ $matrix_meet ]['provider_id'] && null === $matrix_envelopes[ $matrix_meet ]['operation'] && 'unlinked' === $projected[1]['sync_state'] && $projected[1]['sync_version'] === $matrix_envelopes[ $matrix_zoom ]['version'] && 2 === $projected[1]['menuOrder'] && 'publish' === $projected[1]['post_status'] && array() === $projected[1]['actions'] && 'unlinked' === $projected[4]['sync_state'] && $projected[4]['sync_version'] === $matrix_envelopes[ $matrix_meet ]['version'] && 5 === $projected[4]['menuOrder'] && 'publish' === $projected[4]['post_status'] && array() === $projected[4]['actions'] && ! isset( $projected[0]['sync_state'] ) && ! isset( $projected[2]['sync_state'] ) && ! isset( $projected[3]['sync_state'] ), 'stored version-1 envelopes project both live copies as publish and unlinked' );
$zoom_lock  = 'tutorpress_live_lesson_lock_' . $matrix_zoom;
$meet_lock  = 'tutorpress_live_lesson_lock_' . $matrix_meet;
$hook_names = array();
foreach ( TutorPress_Live_Lessons_Hook_Fake::$calls as $call ) {
	$hook_names[] = $call[0];
}
tutorpress_live_lessons_assert( array( array( $zoom_lock, true ), array( $meet_lock, true ) ) === array( array( TutorPress_Topic_Copy_Harness::$releases[0][0], TutorPress_Topic_Copy_Harness::$releases[0][2] ), array( TutorPress_Topic_Copy_Harness::$releases[1][0], TutorPress_Topic_Copy_Harness::$releases[1][2] ) ) && 2 === count( TutorPress_Topic_Copy_Harness::$releases ) && array() === TutorPress_Topic_Copy_Harness::$deletes && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $zoom_lock ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $meet_lock ] ) && array( $lock_sql, $zoom_lock, TutorPress_Topic_Copy_Harness::$releases[0][1] ) === $GLOBALS['wpdb']->statements[ $matrix_statements ] && array( $lock_sql, $meet_lock, TutorPress_Topic_Copy_Harness::$releases[1][1] ) === $GLOBALS['wpdb']->statements[ $matrix_statements + 1 ] && ! in_array( 'tutorpress_live_lesson_duplicated', $hook_names, true ) && $matrix_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $matrix_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $matrix_ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'matrix releases each exact token, calls no provider, and does not fire the direct-duplicate hook' );
$topic_copy = static function () use ( $source, $course, $destination, $target, $children, $allow ) {
	return TutorPress_Topic_Duplication_Service::copy_topic( $source, $course, $destination, $target, $children, $allow );
};
$reset_topic = static function () {
	TutorPress_Topic_Copy_Harness::$fail_insert = null;
	TutorPress_Topic_Copy_Harness::$fail_assign = null;
	TutorPress_Topic_Copy_Harness::$fail_commit = false;
	TutorPress_Topic_Copy_Harness::$fail_init   = false;
	TutorPress_Live_Lessons_Store_Fake::$meta_result          = null;
	TutorPress_Live_Lessons_Store_Fake::$meta_result_refusals = 0;
	TutorPress_Topic_Copy_Harness::$events      = array();
};
$sql_at = static function ( $events, $sql ) {
	$found = null;
	foreach ( $events as $index => $event ) {
		if ( array( 'sql', $sql ) === $event && null === $found ) {
			$found = $index;
		}
	}
	return $found;
};
$writes_before = static function ( $events, $end ) {
	foreach ( $events as $index => $event ) {
		if ( in_array( $event[0], array( 'insert', 'meta', 'release' ), true ) && ( 0 === $index || $index >= $end ) ) {
			return false;
		}
	}
	return true;
};
$envelopes_before_release = static function ( $events, $expected ) {
	$envelope_at = array();
	$releases    = 0;
	foreach ( $events as $index => $event ) {
		if ( 'meta' === $event[0] && '_tutorpress_live_lesson_sync' === $event[2] ) {
			$envelope_at[ $event[1] ] = $index;
		}
	}
	foreach ( $events as $index => $event ) {
		if ( 'release' !== $event[0] ) {
			continue;
		}
		++$releases;
		$id = (int) substr( (string) $event[1], strlen( 'tutorpress_live_lesson_lock_' ) );
		if ( true !== $event[2] || ! isset( $envelope_at[ $id ] ) || $envelope_at[ $id ] >= $index ) {
			return false;
		}
	}
	return $expected === count( $envelope_at ) && $expected === $releases;
};
$zoom_insert_failed = static function ( $events, $mode ) use ( $sql_at, $writes_before ) {
	$zoom    = null;
	$prior   = false;
	$release = false;
	foreach ( $events as $index => $event ) {
		if ( 'release' === $event[0] ) {
			$release = true;
		}
		if ( 'insert' === $event[0] && 'lesson' === $event[1] && is_int( $event[2] ) ) {
			$prior = true;
		}
		if ( array( 'insert', 'tutor_zoom_meeting', $mode ) === $event ) {
			$zoom = $index;
		}
	}
	$rollback = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && null === $sql_at( $events, 'COMMIT' ) && is_int( $rollback ) && $rollback === count( $events ) - 1 && is_int( $zoom ) && $zoom > 0 && $prior && ! $release && $writes_before( $events, $rollback );
};
TutorPress_Topic_Copy_Harness::$events = array();
tutorpress_live_lessons_assert( null === TutorPress_Topic_Duplication_Service::copy_topic( $source, $course, $destination, $target, $children, $deny ) && array() === TutorPress_Topic_Copy_Harness::$events, 'copy_topic returns null before START TRANSACTION when authorization fails' );
$reset_topic();
$copied         = $topic_copy();
$success_events = TutorPress_Topic_Copy_Harness::$events;
$non_live_types = array();
$live_types     = array();
if ( is_array( $copied ) && isset( $copied['non_live'], $copied['live'] ) && is_array( $copied['non_live'] ) && is_array( $copied['live'] ) ) {
	foreach ( $copied['non_live'] as $row ) {
		$non_live_types[] = $row['post_type'];
	}
	foreach ( $copied['live'] as $row ) {
		$live_types[] = $row['post_type'];
	}
}
$success_commit = $sql_at( $success_events, 'COMMIT' );
tutorpress_live_lessons_assert( array( 'tutor_assignments', 'tutor_quiz', 'lesson' ) === $non_live_types && array( 'tutor_zoom_meeting', 'tutor-google-meet' ) === $live_types && 0 === $sql_at( $success_events, 'START TRANSACTION' ) && null === $sql_at( $success_events, 'ROLLBACK' ) && is_int( $success_commit ) && $success_commit === count( $success_events ) - 1 && $writes_before( $success_events, $success_commit ) && $envelopes_before_release( $success_events, 2 ) && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'copy_topic commits both copy groups after START, writes, and lock release' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_insert = 'wp_error';
$wp_error_result = $topic_copy();
$wp_error_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_insert = null;
tutorpress_live_lessons_assert( null === $wp_error_result && $zoom_insert_failed( $wp_error_events, 'wp_error' ), 'Zoom WP_Error insert returns null and rolls back after START' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_insert = 'false';
$false_result = $topic_copy();
$false_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_insert = null;
tutorpress_live_lessons_assert( null === $false_result && $zoom_insert_failed( $false_events, 'false' ), 'Zoom false insert returns null and rolls back after START' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_insert = 'zero';
$zero_result = $topic_copy();
$zero_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_insert = null;
tutorpress_live_lessons_assert( null === $zero_result && $zoom_insert_failed( $zero_events, 'zero' ), 'Zoom zero insert returns null and rolls back after START' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_insert = 'throw';
$throw_result = $topic_copy();
$throw_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_insert = null;
$throw_rollback = $sql_at( $throw_events, 'ROLLBACK' );
tutorpress_live_lessons_assert( null === $throw_result && 0 === $sql_at( $throw_events, 'START TRANSACTION' ) && null === $sql_at( $throw_events, 'COMMIT' ) && is_int( $throw_rollback ) && $throw_rollback === count( $throw_events ) - 1 && $writes_before( $throw_events, $throw_rollback ) && $envelopes_before_release( $throw_events, 1 ), 'envelope throwable releases the acquired lock before ROLLBACK and returns null' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_commit = true;
$commit_result = $topic_copy();
$commit_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
$commit_at   = $sql_at( $commit_events, 'COMMIT' );
$rollback_at = $sql_at( $commit_events, 'ROLLBACK' );
tutorpress_live_lessons_assert( null === $commit_result && 0 === $sql_at( $commit_events, 'START TRANSACTION' ) && is_int( $commit_at ) && is_int( $rollback_at ) && $rollback_at === count( $commit_events ) - 1 && $commit_at < $rollback_at && $writes_before( $commit_events, $commit_at ) && $envelopes_before_release( $commit_events, 2 ), 'failed COMMIT rolls back after writes and lock release and returns null' );
$no_provider = static function () use ( $calls, $hooks, $ledger ) {
	return $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger;
};
$assign_insert_failed = static function ( $events, $mode ) use ( $sql_at, $writes_before ) {
	$assign  = null;
	$release = false;
	$later   = false;
	foreach ( $events as $index => $event ) {
		if ( 'release' === $event[0] ) {
			$release = true;
		}
		if ( array( 'insert', 'tutor_assignments', $mode ) === $event ) {
			$assign = $index;
		} elseif ( 'insert' === $event[0] ) {
			$later = true;
		}
	}
	$rollback = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && null === $sql_at( $events, 'COMMIT' ) && is_int( $rollback ) && $rollback === count( $events ) - 1 && 1 === $assign && ! $release && ! $later && $writes_before( $events, $rollback );
};
$sync_write_failed = static function ( $events ) use ( $sql_at, $writes_before ) {
	$zoom     = null;
	$sync     = null;
	$release  = null;
	$releases = 0;
	$meet     = false;
	foreach ( $events as $index => $event ) {
		if ( 'insert' === $event[0] && 'tutor_zoom_meeting' === $event[1] && is_int( $event[2] ) && null === $zoom ) {
			$zoom = $index;
		}
		if ( 'insert' === $event[0] && 'tutor-google-meet' === $event[1] ) {
			$meet = true;
		}
		if ( 'meta' === $event[0] && '_tutorpress_live_lesson_sync' === $event[2] && null === $sync ) {
			$sync = $index;
		}
		if ( 'release' === $event[0] ) {
			++$releases;
			if ( null === $release ) {
				$release = $index;
			}
		}
	}
	$rollback = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && null === $sql_at( $events, 'COMMIT' ) && is_int( $zoom ) && is_int( $sync ) && is_int( $release ) && is_int( $rollback ) && $zoom < $sync && $sync < $release && $release < $rollback && $rollback === count( $events ) - 1 && 1 === $releases && ! $meet && $writes_before( $events, $rollback ) && 1 === TutorPress_Live_Lessons_Store_Fake::$meta_result_refusals;
};
$commit_failed = static function ( $events ) use ( $sql_at, $writes_before, $envelopes_before_release ) {
	$commit_at   = $sql_at( $events, 'COMMIT' );
	$rollback_at = $sql_at( $events, 'ROLLBACK' );
	$release     = null;
	foreach ( $events as $index => $event ) {
		if ( 'release' === $event[0] && null === $release ) {
			$release = $index;
		}
	}
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && is_int( $commit_at ) && is_int( $rollback_at ) && is_int( $release ) && $release < $rollback_at && $rollback_at === count( $events ) - 1 && $commit_at < $rollback_at && $writes_before( $events, $commit_at ) && $envelopes_before_release( $events, 2 );
};
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_assign = 'throw';
$assign_throw_result = $topic_copy();
$assign_throw_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_assign = null;
tutorpress_live_lessons_assert( null === $assign_throw_result && $assign_insert_failed( $assign_throw_events, 'throw' ) && $no_provider(), 'assignment throwable insert returns null and rolls back with no lock release' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_assign = 'wp_error';
$assign_wp_error_result = $topic_copy();
$assign_wp_error_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_assign = null;
tutorpress_live_lessons_assert( null === $assign_wp_error_result && $assign_insert_failed( $assign_wp_error_events, 'wp_error' ) && $no_provider(), 'assignment WP_Error insert returns null and rolls back with no lock release' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_assign = 'false';
$assign_false_result = $topic_copy();
$assign_false_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_assign = null;
tutorpress_live_lessons_assert( null === $assign_false_result && $assign_insert_failed( $assign_false_events, 'false' ) && $no_provider(), 'assignment false insert returns null and rolls back with no lock release' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_assign = 'zero';
$assign_zero_result = $topic_copy();
$assign_zero_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_assign = null;
tutorpress_live_lessons_assert( null === $assign_zero_result && $assign_insert_failed( $assign_zero_events, 'zero' ) && $no_provider(), 'assignment zero insert returns null and rolls back with no lock release' );
$reset_topic();
TutorPress_Live_Lessons_Store_Fake::$meta_result = 'wp_error';
$sync_wp_error_result = $topic_copy();
$sync_wp_error_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Live_Lessons_Store_Fake::$meta_result = null;
tutorpress_live_lessons_assert( null === $sync_wp_error_result && $sync_write_failed( $sync_wp_error_events ) && $no_provider(), 'sync WP_Error write releases the lock before ROLLBACK and returns null' );
$reset_topic();
TutorPress_Live_Lessons_Store_Fake::$meta_result = 'false';
$sync_false_result = $topic_copy();
$sync_false_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Live_Lessons_Store_Fake::$meta_result = null;
tutorpress_live_lessons_assert( null === $sync_false_result && $sync_write_failed( $sync_false_events ) && $no_provider(), 'sync false write releases the lock before ROLLBACK and returns null' );
$reset_topic();
TutorPress_Live_Lessons_Store_Fake::$meta_result = 'zero';
$sync_zero_result = $topic_copy();
$sync_zero_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Live_Lessons_Store_Fake::$meta_result = null;
tutorpress_live_lessons_assert( null === $sync_zero_result && $sync_write_failed( $sync_zero_events ) && $no_provider(), 'sync zero write releases the lock before ROLLBACK and returns null' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_commit = 'wp_error';
$commit_wp_error_result = $topic_copy();
$commit_wp_error_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
tutorpress_live_lessons_assert( null === $commit_wp_error_result && $commit_failed( $commit_wp_error_events ) && $no_provider(), 'COMMIT WP_Error rolls back after lock release and returns null' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_commit = 'zero';
$commit_zero_result = $topic_copy();
$commit_zero_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
tutorpress_live_lessons_assert( null === $commit_zero_result && $commit_failed( $commit_zero_events ) && $no_provider(), 'COMMIT zero rolls back after lock release and returns null' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_commit = 'string_zero';
$commit_string_zero_result = $topic_copy();
$commit_string_zero_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
tutorpress_live_lessons_assert( null === $commit_string_zero_result && $commit_failed( $commit_string_zero_events ) && $no_provider(), 'COMMIT string zero rolls back after lock release and returns null' );
$reset_topic();
$cleanup_zoom = TutorPress_Live_Lessons_Store_Fake::$next_post_id + 3;
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $cleanup_zoom ] = true;
TutorPress_Topic_Copy_Harness::$fail_init = true;
TutorPress_Topic_Copy_Harness::$events    = array();
$cleanup = $topic_copy();
$cleanup_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_init = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $cleanup_zoom ] );
$cleanup_data     = $cleanup instanceof WP_Error ? $cleanup->get_error_data() : null;
$cleanup_rollback = $sql_at( $cleanup_events, 'ROLLBACK' );
$cleanup_release  = null;
foreach ( $cleanup_events as $index => $event ) {
	if ( array( 'release', 'tutorpress_live_lesson_lock_' . $cleanup_zoom, true ) === $event ) {
		$cleanup_release = $index;
	}
}
tutorpress_live_lessons_assert( is_array( $cleanup_data ) && $cleanup_zoom === $cleanup_data['record_id'] && 9 === $cleanup_data['course_id'] && 80 === $cleanup_data['topic_id'] && 'error' === $cleanup_data['sync_state'] && 0 === $cleanup_data['sync_version'] && ! isset( $cleanup_data['non_live'] ) && ! isset( $cleanup_data['live'] ) && 0 === $sql_at( $cleanup_events, 'START TRANSACTION' ) && null === $sql_at( $cleanup_events, 'COMMIT' ) && is_int( $cleanup_release ) && is_int( $cleanup_rollback ) && $cleanup_release < $cleanup_rollback && $cleanup_rollback === count( $cleanup_events ) - 1 && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'cleanup failure returns the persisted-record error after lock release and ROLLBACK' );
tutorpress_live_lessons_pass( 'topic duplication source snapshot' );
$pass_2e_no_commit = static function ( $events ) use ( $sql_at ) {
	$rollback = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && null === $sql_at( $events, 'COMMIT' ) && is_int( $rollback ) && $rollback === count( $events ) - 1;
};
$pass_2e_no_commit_ok = null === $assign_throw_result && null === $assign_wp_error_result && null === $assign_false_result && null === $sync_wp_error_result && null === $sync_false_result && null === $sync_zero_result;
foreach ( array( $assign_throw_events, $assign_wp_error_events, $assign_false_events, $sync_wp_error_events, $sync_false_events, $sync_zero_events ) as $pass_2e_events ) {
	$pass_2e_no_commit_ok = $pass_2e_no_commit_ok && $pass_2e_no_commit( $pass_2e_events );
}
tutorpress_live_lessons_assert( $pass_2e_no_commit_ok && $no_provider(), 'assignment and sync failures return null with no COMMIT on the fake ledger' );
tutorpress_live_lessons_pass( 'topic copy no-COMMIT rollback' );
$pass_2e_commit_order = static function ( $events ) use ( $sql_at ) {
	$commit_at   = $sql_at( $events, 'COMMIT' );
	$rollback_at = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && is_int( $commit_at ) && is_int( $rollback_at ) && $commit_at < $rollback_at && $rollback_at === count( $events ) - 1;
};
$pass_2e_commit_ok = null === $commit_result && null === $commit_wp_error_result && null === $commit_zero_result && null === $commit_string_zero_result;
foreach ( array( $commit_events, $commit_wp_error_events, $commit_zero_events, $commit_string_zero_events ) as $pass_2e_events ) {
	$pass_2e_commit_ok = $pass_2e_commit_ok && $pass_2e_commit_order( $pass_2e_events );
}
tutorpress_live_lessons_assert( $pass_2e_commit_ok && $no_provider(), 'failed commit returns null after COMMIT then ROLLBACK on the fake ledger' );
tutorpress_live_lessons_pass( 'topic copy COMMIT then ROLLBACK' );

$slash_queue   = TutorPress_Live_Lessons_Adapter_Fake::$queue;
$slash_title   = "Say \"hi\" \\\nCafé";
$slash_content = "line1\n\"q\" \\ Café";
$slash_course  = array( 'ID' => 7, 'post_type' => 'courses', 'post_author' => 42 );
$slash_topic   = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$slash_allow   = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === (int) $id; };
$slash_now     = new DateTimeImmutable( '2026-09-21 16:00:00', new DateTimeZone( 'UTC' ) );
$slash_input   = array( 'provider' => 'google_meet', 'acting_user_id' => 11, 'title' => $slash_title, 'content' => $slash_content, 'start' => '2026-09-21 15:04:00', 'end' => '2026-09-21 16:04:00', 'timezone' => 'America/New_York' );
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$slash_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $slash_topic, $slash_course, $slash_allow, $slash_input, array(), new TutorPress_Live_Lessons_Adapter_Fake(), $slash_now, 'token-slash' );
TutorPress_Live_Lessons_Adapter_Fake::$queue = $slash_queue;
$slash_rows = is_array( $slash_staged ) ? TutorPress_Live_Lessons_Store_Fake::meta_rows( $slash_staged['post_id'], '_tutorpress_live_lesson_sync' ) : array();
$slash_raw  = isset( $slash_rows[0]['meta_value'] ) ? $slash_rows[0]['meta_value'] : null;
$slash_op   = is_string( $slash_raw ) ? json_decode( $slash_raw, true ) : null;
tutorpress_live_lessons_assert( is_array( $slash_staged ) && 1 === count( $slash_rows ) && $slash_raw === $slash_staged['envelope'] && is_array( $slash_op ) && isset( $slash_op['operation'] ) && $slash_title === $slash_op['operation']['title'] && $slash_content === $slash_op['operation']['content'], 'slash-safe envelope insert round-trips a quoted, backslash, newline, and Cafe title and content' );
tutorpress_live_lessons_pass( 'slash-safe envelope insert' );
