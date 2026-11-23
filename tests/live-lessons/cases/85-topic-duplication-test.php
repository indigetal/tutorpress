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
$meet        = array( 'ID' => 40, 'post_type' => 'tutor-google-meet', 'post_parent' => 8, 'menu_order' => 5, 'post_title' => 'Meet', 'post_content' => 'G', 'post_status' => 'draft', 'post_author' => 3, 'meta' => array( 'tutor-google-meet-start-datetime' => array( '2026-09-21 15:04:00' ), 'tutor-google-meet-end-datetime' => array( '2026-09-21 16:04:00' ), 'tutor-google-meet-event-details' => array( '{"id":"evt-1","meet_link":"https://meet.example","html_link":"https://calendar.example","timezone":"America/New_York","attendees":"Yes","start_datetime":"2026-09-21 15:04:00","end_datetime":"2026-09-21 16:04:00"}' ), 'tutor-google-meet-link' => array( 'https://meet.example' ), '_tutor_gmt_data' => array( '{"id":"gmt-1"}' ) ) );
$zoom        = array( 'ID' => 15, 'post_type' => 'tutor_zoom_meeting', 'post_parent' => 8, 'menu_order' => 5, 'post_title' => 'Zoom', 'post_content' => 'Z', 'post_status' => 'draft', 'post_author' => 3, 'meta' => array( '_tutor_zm_start_date' => array( '2026-09-21' ), '_tutor_zm_start_datetime' => array( '2026-09-21 15:04:00' ), '_tutor_zm_duration' => array( '60' ), '_tutor_zm_duration_unit' => array( 'min' ), '_tutor_zm_for_course' => array( '7' ), '_tutor_zm_for_topic' => array( '8' ), '_tutor_zm_data' => array( '{"id":"z-1","timezone":"America/New_York","host_id":"host-1","password":"a\\\\b","join_url":"https://zoom.example","start_url":"https://zoom.example/s","start_time":"2026-09-21T19:04:00Z","duration":60,"settings":{"auto_recording":"local","waiting_room":true}}' ) ) );
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
	$live_meta[ $row['post_id'] ][ $row['meta_key'] ] = $row['meta_value'];
}
ksort( $live_meta[ $live[0]['ID'] ] );
ksort( $live_meta[ $live[1]['ID'] ] );
tutorpress_live_lessons_assert( array( 'post_type' => 'tutor_zoom_meeting', 'post_title' => 'Zoom', 'post_content' => 'Z', 'post_status' => 'publish', 'post_author' => 42, 'post_parent' => 80, 'menu_order' => 4, 'ID' => $live[0]['ID'] ) === TutorPress_Live_Lessons_Store_Fake::$posts[ $live[0]['ID'] ] && array( 'post_type' => 'tutor-google-meet', 'post_title' => 'Meet', 'post_content' => 'G', 'post_status' => 'publish', 'post_author' => 42, 'post_parent' => 80, 'menu_order' => 5, 'ID' => $live[1]['ID'] ) === TutorPress_Live_Lessons_Store_Fake::$posts[ $live[1]['ID'] ], 'live copies are publish rows authored by the destination course' );
tutorpress_live_lessons_assert( array( '_tutor_zm_data' => '{"timezone":"America/New_York","host_id":"host-1","password":"a\\\\b","settings":{"auto_recording":"local","waiting_room":true}}', '_tutor_zm_duration' => '60', '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 15:04:00' ) === $live_meta[ $live[0]['ID'] ] && array( 'tutor-google-meet-end-datetime' => '2026-09-21 16:04:00', 'tutor-google-meet-event-details' => '{"timezone":"America/New_York","attendees":"Yes"}', 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00' ) === $live_meta[ $live[1]['ID'] ] && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'live copies keep the schedule keys, relation keys, and rewritten form blobs and make no provider, hook, or boundary call' );
tutorpress_live_lessons_assert( ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $live[0]['ID'] ], TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $live[1]['ID'] ] ) && array() === TutorPress_Topic_Copy_Harness::$deletes, 'live copies write no lock and keep the inserted posts' );
$op_calls  = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$op_hooks  = TutorPress_Live_Lessons_Hook_Fake::$calls;
$op_ledger = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$sync      = static function ( $state, array $operation ) { return array( json_encode( array( 'state' => $state, 'operation' => $operation ) ) ); };
$bare      = static function ( $id, $type, $order, array $meta ) { return array( 'ID' => $id, 'post_type' => $type, 'post_parent' => 8, 'menu_order' => $order, 'post_title' => 'Op', 'post_content' => 'O', 'post_status' => 'draft', 'meta' => $meta ); };
$g_op      = array( 'start_local' => '2026-09-22 10:00:00', 'end_local' => '2026-09-22 11:00:00', 'timezone' => 'America/Chicago', 'provider_id' => 'evt-hidden', 'provider_target' => array( 'add_enrolled_students' => 'Yes', 'id' => 'evt-hidden', 'meet_link' => 'https://meet.example/h', 'html_link' => 'https://calendar.example/h' ) );
$g_no      = $g_op; $g_no['start_local'] = '2026-09-23 12:00:00'; $g_no['end_local'] = '2026-09-23 13:00:00'; $g_no['provider_id'] = 'evt-hidden-no'; $g_no['provider_target']['add_enrolled_students'] = 'No';
$z_op      = array( 'start_local' => '2026-09-22 10:00:00', 'timezone' => 'America/Chicago', 'duration' => array( 'value' => 90, 'unit' => 'minutes' ), 'provider_id' => 'z-hidden', 'provider_target' => array( 'host_id' => 'host-op', 'auto_recording' => 'cloud', 'waiting_room' => false, 'password' => 'secret-op', 'id' => 'z-hidden', 'join_url' => 'https://zoom.example/j', 'start_url' => 'https://zoom.example/s' ) );
$z_hr      = $z_op; $z_hr['duration'] = array( 'value' => 2, 'unit' => 'hours' ); $z_hr['provider_target']['auto_recording'] = 'local'; $z_hr['provider_target']['waiting_room'] = true;
$z_bad     = $z_op; $z_bad['duration'] = array( 'value' => 3, 'unit' => 'days' );
$kept_zoom = $zoom; $kept_zoom['ID'] = 48; $kept_zoom['menu_order'] = 8; $kept_zoom['meta']['_tutorpress_live_lesson_sync'] = $sync( 'error', array( 'start_local' => '2027-01-01 00:00:00', 'timezone' => 'UTC', 'provider_id' => 'evt-other', 'duration' => array( 'value' => 15, 'unit' => 'minutes' ), 'provider_target' => array( 'host_id' => 'host-other', 'auto_recording' => 'none', 'waiting_room' => false, 'password' => 'other-pass' ) ) );
$op_children = array( $bare( 41, 'tutor-google-meet', 1, array( '_tutorpress_live_lesson_sync' => $sync( 'pending', $g_op ) ) ), $bare( 42, 'tutor-google-meet', 2, array( '_tutorpress_live_lesson_sync' => $sync( 'error', $g_no ) ) ), $bare( 43, 'tutor_zoom_meeting', 3, array( '_tutorpress_live_lesson_sync' => $sync( 'pending', $z_op ) ) ), $bare( 44, 'tutor_zoom_meeting', 4, array( '_tutorpress_live_lesson_sync' => $sync( 'error', $z_hr ) ) ), $bare( 45, 'tutor_zoom_meeting', 5, array( '_tutorpress_live_lesson_sync' => $sync( 'pending', $z_bad ) ) ), $bare( 46, 'tutor-google-meet', 6, array() ), $bare( 47, 'tutor-google-meet', 7, array( '_tutorpress_live_lesson_sync' => $sync( 'synced', $g_op ) ) ), $kept_zoom );
$op_live = TutorPress_Topic_Duplication_Service::copy_live( $source, $course, $destination, $target, $op_children, $allow );
$op_meta = array();
foreach ( $op_live as $op_copy ) { $op_meta[ $op_copy['ID'] ] = array(); }
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) { if ( isset( $op_meta[ (int) $row['post_id'] ] ) ) { $op_meta[ (int) $row['post_id'] ][ $row['meta_key'] ] = $row['meta_value']; } }
foreach ( $op_meta as $op_id => $op_bag ) { ksort( $op_meta[ $op_id ] ); }
$op_ids = array( $op_live[0]['ID'], $op_live[1]['ID'], $op_live[2]['ID'], $op_live[3]['ID'], $op_live[4]['ID'], $op_live[5]['ID'], $op_live[6]['ID'], $op_live[7]['ID'] );
$op_zoom_blob = '{"timezone":"America/Chicago","host_id":"host-op","settings":{"auto_recording":"cloud","waiting_room":false}}';
$op_hour_blob = '{"timezone":"America/Chicago","host_id":"host-op","settings":{"auto_recording":"local","waiting_room":true}}';
$op_locked = false;
foreach ( $op_ids as $op_id ) { $op_locked = $op_locked || isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $op_id ] ) || isset( $op_meta[ $op_id ]['_tutorpress_live_lesson_sync'] ) || isset( $op_meta[ $op_id ]['tutor-google-meet-link'] ); }
tutorpress_live_lessons_assert( array( 'tutor-google-meet-end-datetime' => '2026-09-22 11:00:00', 'tutor-google-meet-event-details' => '{"timezone":"America/Chicago","attendees":"Yes"}', 'tutor-google-meet-start-datetime' => '2026-09-22 10:00:00' ) === $op_meta[ $op_ids[0] ] && array( 'tutor-google-meet-end-datetime' => '2026-09-23 13:00:00', 'tutor-google-meet-event-details' => '{"timezone":"America/Chicago","attendees":"No"}', 'tutor-google-meet-start-datetime' => '2026-09-23 12:00:00' ) === $op_meta[ $op_ids[1] ] && array( '_tutor_zm_data' => $op_zoom_blob, '_tutor_zm_duration' => 90, '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-22', '_tutor_zm_start_datetime' => '2026-09-22 10:00:00' ) === $op_meta[ $op_ids[2] ] && array( '_tutor_zm_data' => $op_hour_blob, '_tutor_zm_duration' => 2, '_tutor_zm_duration_unit' => 'hr', '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-22', '_tutor_zm_start_datetime' => '2026-09-22 10:00:00' ) === $op_meta[ $op_ids[3] ] && array( '_tutor_zm_data' => $op_zoom_blob, '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-22', '_tutor_zm_start_datetime' => '2026-09-22 10:00:00' ) === $op_meta[ $op_ids[4] ] && array() === $op_meta[ $op_ids[5] ] && array() === $op_meta[ $op_ids[6] ] && array( '_tutor_zm_data' => '{"timezone":"America/New_York","host_id":"host-1","password":"a\\\\b","settings":{"auto_recording":"local","waiting_room":true}}', '_tutor_zm_duration' => '60', '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 15:04:00' ) === $op_meta[ $op_ids[7] ] && false === $op_locked && $op_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $op_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $op_ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && false === strpos( json_encode( $op_meta ), 'evt-hidden' ) && false === strpos( json_encode( $op_meta ), 'evt-other' ) && false === strpos( json_encode( $op_meta ), 'z-hidden' ) && false === strpos( json_encode( $op_meta ), 'secret-op' ) && false === strpos( json_encode( $op_meta ), 'other-pass' ) && false === strpos( json_encode( $op_meta ), 'meet.example' ) && false === strpos( json_encode( $op_meta ), 'zoom.example' ), 'a pending or error operation supplies the shown schedule, a stored schedule wins, and a missing or other state adds no schedule' );
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
$matrix_live       = TutorPress_Topic_Duplication_Service::copy_live( $source, $course, $destination, $target, $equal, $allow );
tutorpress_live_lessons_assert( array( 10, 15, 20, 30, 40 ) === $matrix_ids && array( 1, 2, 3, 4, 5 ) === $matrix_orders && array( 'tutor_quiz', 'tutor_assignments', 'lesson' ) === array( $matrix_non_live[0]['post_type'], $matrix_non_live[1]['post_type'], $matrix_non_live[2]['post_type'] ) && array( 1, 3, 4 ) === array( $matrix_non_live[0]['menu_order'], $matrix_non_live[1]['menu_order'], $matrix_non_live[2]['menu_order'] ) && 'draft' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[0]['ID'] ]['post_status'] && 'private' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[1]['ID'] ]['post_status'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_non_live[2]['ID'] ]['post_status'] && array( 'tutor_zoom_meeting', 'tutor-google-meet' ) === array( $matrix_live[0]['post_type'], $matrix_live[1]['post_type'] ) && array( 2, 5 ) === array( $matrix_live[0]['menu_order'], $matrix_live[1]['menu_order'] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[0]['ID'] ]['post_status'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[1]['ID'] ]['post_status'] && 42 === (int) TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[0]['ID'] ]['post_author'] && 42 === (int) TutorPress_Live_Lessons_Store_Fake::$posts[ $matrix_live[1]['ID'] ]['post_author'], 'equal source order copies mixed children by post ID onto the destination' );
$matrix_zoom   = (int) $matrix_live[0]['ID'];
$matrix_meet   = (int) $matrix_live[1]['ID'];
$matrix_meta   = array( $matrix_zoom => array(), $matrix_meet => array() );
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	$pid = (int) $row['post_id'];
	if ( $pid !== $matrix_zoom && $pid !== $matrix_meet ) {
		continue;
	}
	$matrix_meta[ $pid ][ $row['meta_key'] ] = $row['meta_value'];
}
ksort( $matrix_meta[ $matrix_zoom ] );
ksort( $matrix_meta[ $matrix_meet ] );
tutorpress_live_lessons_assert( array( '_tutor_zm_data' => '{"timezone":"America/New_York","host_id":"host-1","password":"a\\\\b","settings":{"auto_recording":"local","waiting_room":true}}', '_tutor_zm_duration' => '60', '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 9, '_tutor_zm_for_topic' => 80, '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 15:04:00' ) === $matrix_meta[ $matrix_zoom ] && array( 'tutor-google-meet-end-datetime' => '2026-09-21 16:04:00', 'tutor-google-meet-event-details' => '{"timezone":"America/New_York","attendees":"Yes"}', 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00' ) === $matrix_meta[ $matrix_meet ] && $matrix_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $matrix_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $matrix_ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'matrix copies keep the schedule keys, relation keys, and rewritten form blobs and make no provider, hook, or boundary call' );
tutorpress_live_lessons_assert( array() === TutorPress_Topic_Copy_Harness::$releases && array() === TutorPress_Topic_Copy_Harness::$deletes && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $matrix_zoom ], TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $matrix_meet ] ), 'matrix copies write no lock and keep the inserted posts' );
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
tutorpress_live_lessons_assert( array( 'tutor_assignments', 'tutor_quiz', 'lesson' ) === $non_live_types && array( 'tutor_zoom_meeting', 'tutor-google-meet' ) === $live_types && 0 === $sql_at( $success_events, 'START TRANSACTION' ) && null === $sql_at( $success_events, 'ROLLBACK' ) && is_int( $success_commit ) && $success_commit === count( $success_events ) - 1 && $writes_before( $success_events, $success_commit ) && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'copy_topic commits both copy groups after START and writes' );
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
TutorPress_Topic_Copy_Harness::$fail_commit = true;
$commit_result = $topic_copy();
$commit_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
$commit_at   = $sql_at( $commit_events, 'COMMIT' );
$rollback_at = $sql_at( $commit_events, 'ROLLBACK' );
tutorpress_live_lessons_assert( null === $commit_result && 0 === $sql_at( $commit_events, 'START TRANSACTION' ) && is_int( $commit_at ) && is_int( $rollback_at ) && $rollback_at === count( $commit_events ) - 1 && $commit_at < $rollback_at && $writes_before( $commit_events, $commit_at ), 'failed COMMIT rolls back after writes and returns null' );
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
$commit_failed = static function ( $events ) use ( $sql_at, $writes_before ) {
	$commit_at   = $sql_at( $events, 'COMMIT' );
	$rollback_at = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && is_int( $commit_at ) && is_int( $rollback_at ) && $rollback_at === count( $events ) - 1 && $commit_at < $rollback_at && $writes_before( $events, $commit_at );
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
TutorPress_Topic_Copy_Harness::$fail_commit = 'wp_error';
$commit_wp_error_result = $topic_copy();
$commit_wp_error_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
tutorpress_live_lessons_assert( null === $commit_wp_error_result && $commit_failed( $commit_wp_error_events ) && $no_provider(), 'COMMIT WP_Error rolls back and returns null' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_commit = 'zero';
$commit_zero_result = $topic_copy();
$commit_zero_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
tutorpress_live_lessons_assert( is_array( $commit_zero_result ) && isset( $commit_zero_result['non_live'], $commit_zero_result['live'] ) && is_int( $sql_at( $commit_zero_events, 'COMMIT' ) ) && null === $sql_at( $commit_zero_events, 'ROLLBACK' ) && $sql_at( $commit_zero_events, 'COMMIT' ) === count( $commit_zero_events ) - 1 && $no_provider(), 'COMMIT zero is a successful commit' );
$reset_topic();
TutorPress_Topic_Copy_Harness::$fail_commit = 'string_zero';
$commit_string_zero_result = $topic_copy();
$commit_string_zero_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_commit = false;
tutorpress_live_lessons_assert( is_array( $commit_string_zero_result ) && isset( $commit_string_zero_result['non_live'], $commit_string_zero_result['live'] ) && is_int( $sql_at( $commit_string_zero_events, 'COMMIT' ) ) && null === $sql_at( $commit_string_zero_events, 'ROLLBACK' ) && $sql_at( $commit_string_zero_events, 'COMMIT' ) === count( $commit_string_zero_events ) - 1 && $no_provider(), 'COMMIT string zero is a successful commit' );
$reset_topic();
$cleanup_zoom = TutorPress_Live_Lessons_Store_Fake::$next_post_id + 3;
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $cleanup_zoom ] = true;
TutorPress_Topic_Copy_Harness::$fail_init = true;
$cleanup = $topic_copy();
$cleanup_events = TutorPress_Topic_Copy_Harness::$events;
TutorPress_Topic_Copy_Harness::$fail_init = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $cleanup_zoom ] );
$cleanup_commit = $sql_at( $cleanup_events, 'COMMIT' );
tutorpress_live_lessons_assert( is_array( $cleanup ) && isset( $cleanup['non_live'], $cleanup['live'] ) && is_int( $cleanup_commit ) && $cleanup_commit === count( $cleanup_events ) - 1 && null === $sql_at( $cleanup_events, 'ROLLBACK' ) && array() === TutorPress_Topic_Copy_Harness::$deletes && $no_provider(), 'init and delete flags still commit the copied topic and do not delete it' );
tutorpress_live_lessons_pass( 'topic duplication source snapshot' );
$pass_2e_no_commit = static function ( $events ) use ( $sql_at ) {
	$rollback = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && null === $sql_at( $events, 'COMMIT' ) && is_int( $rollback ) && $rollback === count( $events ) - 1;
};
$pass_2e_no_commit_ok = null === $assign_throw_result && null === $assign_wp_error_result && null === $assign_false_result;
foreach ( array( $assign_throw_events, $assign_wp_error_events, $assign_false_events ) as $pass_2e_events ) {
	$pass_2e_no_commit_ok = $pass_2e_no_commit_ok && $pass_2e_no_commit( $pass_2e_events );
}
tutorpress_live_lessons_assert( $pass_2e_no_commit_ok && $no_provider(), 'assignment failures return null with no COMMIT on the fake ledger' );
tutorpress_live_lessons_pass( 'topic copy no-COMMIT rollback' );
$pass_2e_commit_order = static function ( $events ) use ( $sql_at ) {
	$commit_at   = $sql_at( $events, 'COMMIT' );
	$rollback_at = $sql_at( $events, 'ROLLBACK' );
	return 0 === $sql_at( $events, 'START TRANSACTION' ) && is_int( $commit_at ) && is_int( $rollback_at ) && $commit_at < $rollback_at && $rollback_at === count( $events ) - 1;
};
$pass_2e_commit_ok = null === $commit_result && null === $commit_wp_error_result;
foreach ( array( $commit_events, $commit_wp_error_events ) as $pass_2e_events ) {
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
$slash_rows      = is_array( $slash_staged ) ? TutorPress_Live_Lessons_Store_Fake::meta_rows( $slash_staged['post_id'], '_tutorpress_live_lesson_sync' ) : array();
$slash_operation = is_array( $slash_staged ) && isset( $slash_staged['operation'] ) && is_array( $slash_staged['operation'] ) ? $slash_staged['operation'] : array();
$slash_post      = is_array( $slash_staged ) ? TutorPress_Live_Lessons_Store_Fake::$posts[ $slash_staged['post_id'] ] : array();
tutorpress_live_lessons_assert( is_array( $slash_staged ) && array() === $slash_rows && ! array_key_exists( 'envelope', $slash_staged ) && $slash_title === $slash_operation['title'] && $slash_content === $slash_operation['content'] && $slash_title === $slash_post['post_title'] && $slash_content === $slash_post['post_content'], 'slash-safe create keeps a quoted, backslash, newline, and Cafe title and content without a sync row' );
tutorpress_live_lessons_pass( 'slash-safe create insert' );
