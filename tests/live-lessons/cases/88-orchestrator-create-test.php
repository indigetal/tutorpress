<?php

$course    = array( 'ID' => 7, 'post_type' => 'courses', 'post_author' => 42 );
$topic     = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$bad_topic = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 3 );
$allow     = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === (int) $id; };
$deny      = static function () { return false; };
$broad     = static function ( $cap ) { return 'edit_posts' === $cap; };
$now       = new DateTimeImmutable( '2026-09-21 16:00:00', new DateTimeZone( 'UTC' ) );
$later     = new DateTimeImmutable( '2026-09-21 19:04:00', new DateTimeZone( 'UTC' ) );
$lesson    = array( 'ID' => 1, 'post_type' => 'lesson', 'menu_order' => 9 );
$page      = array( 'ID' => 6, 'post_type' => 'page', 'menu_order' => 100 );
$children  = array( $lesson, $page );
$before    = $children;
$posts     = TutorPress_Live_Lessons_Store_Fake::$posts;
$meta      = TutorPress_Live_Lessons_Store_Fake::$meta;
$hooks     = TutorPress_Live_Lessons_Hook_Fake::$calls;
$ledger    = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$secret    = 'cred-sentinel';
$creds     = array( 'account_id' => $secret, 'api_key' => $secret, 'api_secret' => $secret );
$adapter   = new TutorPress_Live_Lessons_Adapter_Fake();
$google    = array( 'provider' => 'google_meet', 'acting_user_id' => 11, 'owner_id' => 99, 'host_id' => 'host-ignored', 'start' => '2026-09-21 15:04:00', 'end' => '2026-09-21 16:04:00', 'timezone' => 'America/New_York', 'credentials' => $creds );
$zoom      = array( 'provider' => 'zoom', 'course_author_id' => 42, 'acting_instructor_id' => 9, 'host_id' => 'host-1', 'start' => '2026-09-21 15:04:00', 'timezone' => 'America/New_York', 'duration' => array( 'value' => 2, 'unit' => 'hours' ), 'credentials' => $creds );
$unchanged = static function () use ( $posts, $meta, $hooks, $ledger, $before, $children ) {
	return $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $meta === TutorPress_Live_Lessons_Store_Fake::$meta && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && $before === $children;
};
$methods   = static function () {
	$names = array();
	foreach ( TutorPress_Live_Lessons_Adapter_Fake::$calls as $call ) { $names[] = $call[0]; }
	return $names;
};
$check     = static function ( array $input, array $queue, $can, DateTimeImmutable $clock ) use ( $topic, $course, $children, $adapter ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::validate_create( $topic, $course, $can, $input, $children, $adapter, $clock );
};

$order = TutorPress_Live_Lesson_Ordering::next_menu_order( $children );
$meet  = $check( $google, array( 'preflight' => array( true ) ), $allow, $now );
tutorpress_live_lessons_assert( 10 === $order && $before === $children && array( 'course_id' => 7, 'topic_id' => 8, 'provider' => 'google_meet', 'owner_id' => 11, 'menu_order' => $order, 'start' => '2026-09-21 15:04:00', 'timezone' => 'America/New_York', 'end' => '2026-09-21 16:04:00', 'duration' => null, 'host_id' => null ) === $meet && array( 'preflight' ) === $methods() && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && false === strpos( json_encode( $meet ), $secret ) && $unchanged(), 'google create validation uses the acting owner and does not call validate_host' );

$room = $check( $zoom, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), $allow, $now );
tutorpress_live_lessons_assert( array( 'course_id' => 7, 'topic_id' => 8, 'provider' => 'zoom', 'owner_id' => 42, 'menu_order' => 10, 'start' => '2026-09-21 15:04:00', 'timezone' => 'America/New_York', 'end' => null, 'duration' => array( 'value' => 2, 'unit' => 'hours' ), 'host_id' => 'host-1' ) === $room && array( 'preflight', 'validate_host' ) === $methods() && false === strpos( json_encode( $room ), $secret ) && false === strpos( json_encode( TutorPress_Live_Lessons_Adapter_Fake::$calls ), $secret ) && $before === $children && $unchanged(), 'zoom create validation keeps the typed duration and the course-author owner' );

$fallback = $zoom;
$fallback['course_author_id'] = 0;
tutorpress_live_lessons_assert( 9 === $check( $fallback, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), $allow, $now )['owner_id'], 'zoom owner falls back to the acting instructor' );

$empty = $zoom;
$empty['credentials'] = array( 'account_id' => '', 'api_key' => $secret, 'api_secret' => $secret );
tutorpress_live_lessons_assert( null === $check( $empty, array(), $allow, $now ) && array() === $methods() && $unchanged(), 'zoom validation stops when credentials are incomplete' );

$with_id = $google;
$with_id['provider_id'] = 'evt-evil';
$with_key = $google;
$with_key['id'] = 'evt-evil';
tutorpress_live_lessons_assert( null === $check( $with_id, array(), $allow, $now ) && null === $check( $with_key, array(), $allow, $now ) && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && $unchanged(), 'a provider id on create input is rejected before preflight' );

tutorpress_live_lessons_assert( null === $check( $google, array(), $deny, $now ) && null === $check( $google, array(), $broad, $now ) && null === TutorPress_Live_Lesson_Sync_Orchestrator::validate_create( $bad_topic, $course, $allow, $google, $children, $adapter, $now ) && array() === $methods() && $unchanged(), 'create validation requires edit_post on the topic course' );

$other = $google;
$other['provider'] = 'teams';
tutorpress_live_lessons_assert( null === $check( $other, array(), $allow, $now ) && array() === $methods(), 'an unknown provider is rejected before preflight' );

tutorpress_live_lessons_assert( null === $check( $google, array( 'preflight' => array( false ) ), $allow, $now ) && array( 'preflight' ) === $methods() && $unchanged(), 'a failed preflight rejects create validation' );
tutorpress_live_lessons_assert( null === $check( $zoom, array( 'preflight' => array( true ), 'validate_host' => array( false ) ), $allow, $now ) && array( 'preflight', 'validate_host' ) === $methods() && $unchanged(), 'a failed zoom host check rejects create validation' );

$past = $check( $google, array( 'preflight' => array( true ) ), $allow, $later );
$gap  = $google;
$gap['start'] = '2026-03-08 02:30:00';
$gap['end']   = '2026-03-08 03:30:00';
$fold = $google;
$fold['start'] = '2026-11-01 01:30:00';
$fold['end']   = '2026-11-01 02:30:00';
tutorpress_live_lessons_assert( null === $past && null === $check( $gap, array( 'preflight' => array( true ) ), $allow, $now ) && null === $check( $fold, array( 'preflight' => array( true ) ), $allow, $now ) && array( 'preflight' ) === $methods() && $unchanged(), 'a start that is not a unique future instant is rejected' );

$same = $google;
$same['end'] = '2026-09-21 15:04:00';
$long = $zoom;
$long['duration'] = array( 'value' => 9, 'unit' => 'hours' );
tutorpress_live_lessons_assert( null === $check( $same, array( 'preflight' => array( true ) ), $allow, $now ) && null === $check( $long, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), $allow, $now ) && $before === $children && $unchanged(), 'google end order and an out-of-range zoom duration are rejected' );
tutorpress_live_lessons_pass( 'create validation stays before mutation' );

$draft = $google;
$draft['status'] = 'draft';
$draft['title'] = 'Standup';
$draft['content'] = 'Notes';
$posts_before = TutorPress_Live_Lessons_Store_Fake::$posts;
$next_before  = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$denied_insert = TutorPress_Live_Lesson_Sync_Orchestrator::insert_locked( $topic, $course, $deny, $draft, $children, $adapter, $now, 'token-create' );
tutorpress_live_lessons_assert( null === $denied_insert && $posts_before === TutorPress_Live_Lessons_Store_Fake::$posts && $next_before === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $before === $children, 'denied create validation does not insert' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$inserted = TutorPress_Live_Lesson_Sync_Orchestrator::insert_locked( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-create' );
$stored   = is_array( $inserted ) ? TutorPress_Live_Lessons_Store_Fake::$posts[ $inserted['post_id'] ] : array();
tutorpress_live_lessons_assert( is_array( $inserted ) && 11 === $inserted['owner_id'] && is_int( $inserted['post_id'] ) && $inserted['post_id'] > 0 && ! array_key_exists( 'lock', $inserted ) && ! array_key_exists( 'dispatch_state', $inserted ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $inserted['post_id'] ] ) && 'publish' === $stored['post_status'] && 8 === (int) $stored['post_parent'] && 42 === (int) $stored['post_author'] && 10 === (int) $stored['menu_order'] && 'tutor-google-meet' === $stored['post_type'] && 'Standup' === $stored['post_title'] && 'Notes' === $stored['post_content'] && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $inserted['post_id'], '_tutorpress_live_lesson_sync' ) && array( 'preflight' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && $before === $children, 'create insert stores a publish post without a lock' );
tutorpress_live_lessons_pass( 'create insert stores a publish post' );

$meta_keys = static function ( $post_id ) {
	$keys = array();
	foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
		if ( (int) $row['post_id'] === (int) $post_id ) {
			$keys[] = $row['meta_key'];
		}
	}
	return $keys;
};
$meet_input = $draft;
$meet_input['settings'] = array( 'attendees' => array( array( 'email' => 'student@example.com' ) ), 'password' => 'hidden-pass' );
$denied_stage = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $deny, $meet_input, $children, $adapter, $now, 'token-stage-deny' ) && $denied_stage === TutorPress_Live_Lessons_Store_Fake::$next_post_id, 'denied create staging does not insert' );
$google_settings = array( 'is_authorized' => true, 'has_credentials' => true, 'settings' => array( 'event_status' => array( 'value' => 'tentative', 'label' => 'Status', 'type' => 'select', 'options' => array( 'confirmed', 'tentative' ) ), 'transparency' => array( 'value' => 'opaque', 'label' => 'Transparency', 'type' => 'select', 'options' => array( 'transparent', 'opaque' ) ), 'event_visibility' => array( 'value' => 'private', 'label' => 'Visibility', 'type' => 'select', 'options' => array( 'public', 'private' ) ), 'reminder_time' => array( 'value' => '15', 'label' => 'Reminder', 'type' => 'select', 'options' => array( '5', '15', '30' ) ), 'send_updates' => array( 'value' => 'none', 'label' => 'Updates', 'type' => 'select', 'options' => array( 'all', 'none' ) ) ), 'authorization_url' => null, 'setup_url' => null );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( $google_settings ) );
$staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $meet_input, $children, $adapter, $now, 'token-stage-google' );
$operation = is_array( $staged ) && isset( $staged['operation'] ) && is_array( $staged['operation'] ) ? $staged['operation'] : array();
tutorpress_live_lessons_assert( is_array( $staged ) && is_int( $staged['post_id'] ) && $staged['post_id'] > 0 && 11 === $staged['owner_id'] && 'google_meet' === $staged['provider'] && ! array_key_exists( 'envelope', $staged ) && ! array_key_exists( 'lock', $staged ) && ! array_key_exists( 'dispatch_state', $staged ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $staged['post_id'] ] ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $staged['post_id'], '_tutorpress_live_lesson_sync' ) && 1 === preg_match( '/\A[0-9a-f]{32}\z/', $operation['operation_id'] ) && 'create' === $operation['kind'] && '2026-09-21 16:00:00' === $operation['started_at'] && 0 === $operation['base_version'] && 'not_dispatched' === $operation['dispatch_state'] && 7 === $operation['course_id'] && 8 === $operation['topic_id'] && 42 === $operation['post_author'] && 10 === $operation['menu_order'] && 'Standup' === $operation['title'] && 'Notes' === $operation['content'] && '2026-09-21 15:04:00' === $operation['start_local'] && '2026-09-21 16:04:00' === $operation['end_local'] && 'America/New_York' === $operation['timezone'] && array( 'value' => 60, 'unit' => 'minutes' ) === $operation['duration'] && array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 15, 'send_updates' => 'none', 'status' => 'tentative', 'transparency' => 'opaque', 'visibility' => 'private' ) === $operation['provider_target'] && null === $operation['delete_hook'] && null === $operation['provider_id'] && array( 'preflight', 'settings' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && $before === $children, 'google create stages an in-memory operation without a sync row' );
function get_user_meta( $user_id, $key, $single = false ) {
	if ( class_exists( 'TutorPress_Zoom_Users_Legacy', false ) ) {
		TutorPress_Zoom_Users_Legacy::$meta++;
	}
	if ( 42 === (int) $user_id && 'tutor_zoom_settings' === $key ) {
		return json_encode(
			array(
				'host_video'         => '',
				'participants_video' => '1',
				'mute_participants'  => '',
				'enforce_login'      => '1',
				'join_before_host'   => '',
			)
		);
	}
	if ( 'tutor_zoom_api' === $key ) {
		return json_encode(
			array(
				'api_key'    => 'configured-key',
				'api_secret' => 'configured-secret',
			)
		);
	}
	return '';
}
$zoom_owner_target = array( 'auto_recording' => 'cloud', 'enforce_login' => true, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => false, 'mute_upon_entry' => false, 'participant_video' => true, 'waiting_room' => false );
$room_input = $zoom;
$room_input['title'] = 'Room';
$room_input['content'] = 'Agenda';
$room_input['settings'] = array( 'password' => 'hidden-pass', 'auto_recording' => 'cloud', 'join_before_host' => true, 'waiting_room' => false, 'host_video' => true );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'validate_host' => array( true ) );
$room_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $room_input, $children, $adapter, $now, 'token-stage-zoom' );
$room_operation = is_array( $room_staged ) && isset( $room_staged['operation'] ) && is_array( $room_staged['operation'] ) ? $room_staged['operation'] : array();
tutorpress_live_lessons_assert( is_array( $room_staged ) && is_int( $room_staged['post_id'] ) && $room_staged['post_id'] > 0 && 42 === $room_staged['owner_id'] && 'zoom' === $room_staged['provider'] && ! array_key_exists( 'envelope', $room_staged ) && ! array_key_exists( 'lock', $room_staged ) && ! array_key_exists( 'dispatch_state', $room_staged ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $room_staged['post_id'] ] ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $room_staged['post_id'], '_tutorpress_live_lesson_sync' ) && array( 'value' => 2, 'unit' => 'hours' ) === $room_operation['duration'] && '2026-09-21 17:04:00' === $room_operation['end_local'] && '2026-09-21 15:04:00' === $room_operation['start_local'] && 42 === $room_operation['post_author'] && $zoom_owner_target === $room_operation['provider_target'] && 'not_dispatched' === $room_operation['dispatch_state'] && null === $room_operation['delete_hook'] && null === $room_operation['provider_id'] && array( 'preflight', 'validate_host' ) === $methods(), 'zoom create stages the typed duration and omits the password' );
$evil = $draft;
$evil['provider_id'] = 'evt-evil';
$before_evil = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $evil, $children, $adapter, $now, 'token-stage-evil' ) && $before_evil === TutorPress_Live_Lessons_Store_Fake::$next_post_id && array() === $methods(), 'a provider id is still rejected before staging' );
$release_for = static function ( $post_id, $from ) {
	$match = null;
	foreach ( array_slice( $GLOBALS['wpdb']->statements, $from ) as $statement ) {
		if ( is_array( $statement ) && isset( $statement[1] ) && 'tutorpress_live_lesson_lock_' . (int) $post_id === $statement[1] ) {
			$match = $statement;
		}
	}
	return $match;
};
$open_from = count( $GLOBALS['wpdb']->statements );
$open_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
TutorPress_Topic_Copy_Harness::$fail_init = true;
$unstaged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stage-open' );
$open_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id - 1;
$open_release = $release_for( $open_id, $open_from );
TutorPress_Topic_Copy_Harness::$fail_init = false;
tutorpress_live_lessons_assert( is_array( $unstaged ) && $open_id === $unstaged['post_id'] && 11 === $unstaged['owner_id'] && 'google_meet' === $unstaged['provider'] && ! array_key_exists( 'envelope', $unstaged ) && ! array_key_exists( 'lock', $unstaged ) && ! array_key_exists( 'dispatch_state', $unstaged ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $open_id ] ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $open_id, '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $open_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $open_id ]['post_status'] && 'Standup' === TutorPress_Live_Lessons_Store_Fake::$posts[ $open_id ]['post_title'] && 'Notes' === TutorPress_Live_Lessons_Store_Fake::$posts[ $open_id ]['post_content'] && array() === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $open_deletes ) && null === $open_release && isset( $unstaged['operation'] ) && is_array( $unstaged['operation'] ) && array( 'value' => 60, 'unit' => 'minutes' ) === $unstaged['operation']['duration'] && 'not_dispatched' === $unstaged['operation']['dispatch_state'] && null === $unstaged['operation']['delete_hook'] && null === $unstaged['operation']['provider_id'] && array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 30, 'send_updates' => 'all', 'status' => 'confirmed', 'transparency' => 'transparent', 'visibility' => 'public' ) === $unstaged['operation']['provider_target'] && array( 'preflight', 'settings' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $before === $children, 'a null settings payload still stages the publish post without a lock' );
tutorpress_live_lessons_pass( 'create stages an in-memory operation' );

$stuck_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$stuck_from = count( $GLOBALS['wpdb']->statements );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck_id ] = true;
TutorPress_Topic_Copy_Harness::$fail_init = true;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$stuck = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stage-stuck' );
$stuck_release = $release_for( $stuck_id, $stuck_from );
TutorPress_Topic_Copy_Harness::$fail_init = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck_id ] );
tutorpress_live_lessons_assert( is_array( $stuck ) && $stuck_id === $stuck['post_id'] && 11 === $stuck['owner_id'] && 'google_meet' === $stuck['provider'] && ! array_key_exists( 'envelope', $stuck ) && ! array_key_exists( 'lock', $stuck ) && ! array_key_exists( 'dispatch_state', $stuck ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $stuck_id ] ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $stuck_id, '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck_id ]['post_status'] && 'Standup' === TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck_id ]['post_title'] && 'Notes' === TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck_id ]['post_content'] && null === $stuck_release && isset( $stuck['operation'] ) && is_array( $stuck['operation'] ) && array( 'value' => 60, 'unit' => 'minutes' ) === $stuck['operation']['duration'] && 'not_dispatched' === $stuck['operation']['dispatch_state'] && null === $stuck['operation']['delete_hook'] && null === $stuck['operation']['provider_id'] && array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 30, 'send_updates' => 'all', 'status' => 'confirmed', 'transparency' => 'transparent', 'visibility' => 'public' ) === $stuck['operation']['provider_target'] && array( 'preflight', 'settings' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $before === $children, 'a blocked delete still stages the publish post without a lock' );

$orphan_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$orphan_from = count( $GLOBALS['wpdb']->statements );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $orphan_id ] = true;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$orphan = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stage-orphan' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $orphan_id ] );
tutorpress_live_lessons_assert( is_array( $orphan ) && $orphan_id === $orphan['post_id'] && 11 === $orphan['owner_id'] && 'google_meet' === $orphan['provider'] && ! array_key_exists( 'envelope', $orphan ) && ! array_key_exists( 'lock', $orphan ) && ! array_key_exists( 'dispatch_state', $orphan ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $orphan_id ] ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $orphan_id, '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $orphan_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $orphan_id ]['post_status'] && 'Standup' === TutorPress_Live_Lessons_Store_Fake::$posts[ $orphan_id ]['post_title'] && 'Notes' === TutorPress_Live_Lessons_Store_Fake::$posts[ $orphan_id ]['post_content'] && null === $release_for( $orphan_id, $orphan_from ) && isset( $orphan['operation'] ) && is_array( $orphan['operation'] ) && array( 'value' => 60, 'unit' => 'minutes' ) === $orphan['operation']['duration'] && 'not_dispatched' === $orphan['operation']['dispatch_state'] && null === $orphan['operation']['delete_hook'] && null === $orphan['operation']['provider_id'] && array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 30, 'send_updates' => 'all', 'status' => 'confirmed', 'transparency' => 'transparent', 'visibility' => 'public' ) === $orphan['operation']['provider_target'] && array( 'preflight', 'settings' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $before === $children, 'a blocked lock still stages the publish post without a lock' );

$sync_read     = array( 'TutorPress_Live_Lessons_Store_Fake', 'meta_rows' );
$sync_update   = static function ( $post_id, $key, $meta_id, $value, $prior ) { return TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post_id, $key, $meta_id, $value, $prior ); };
$sync_affected = static function () { return TutorPress_Live_Lessons_Store_Fake::$affected; };

$plant_unlinked = static function ( $post_type, $provider, $menu_order ) {
	$id    = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => $post_type, 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => $menu_order, 'post_author' => 42 ) );
	$prior = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( array( 'schema_version' => 1, 'version' => 1, 'state' => 'unlinked', 'provider' => $provider, 'owner_id' => 42, 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => null ) );
	add_post_meta( $id, '_tutorpress_live_lesson_sync', $prior );
	$item = array( 'ID' => $id, 'post_type' => $post_type, 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => $menu_order );
	return array( $item, $prior );
};
$stage_copy = static function ( array $item, array $input, array $queue, $token, $update = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	if ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] && ! array_key_exists( 'settings', $queue ) ) {
		$queue['settings'] = array( null );
	}
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::stage_unlinked( $item, $topic, $course, $allow, $input, $adapter, $now, $token, $sync_read, null === $update ? $sync_update : $update, $sync_affected );
};
$google_save = $google;
$google_save['title'] = 'Standup';
$google_save['content'] = 'Notes';
$google_save['settings'] = array( 'attendees' => array( array( 'email' => 'student@example.com' ) ), 'password' => 'hidden-pass' );
unset( $google_save['provider'] );
list( $google_item, $google_prior ) = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$google_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$google_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$google_saved = $stage_copy( $google_item, $google_save, array( 'preflight' => array( true ) ), 'token-unlinked-google' );
$google_decoded = is_array( $google_saved ) ? json_decode( $google_saved['envelope'], true ) : null;
$google_operation = is_array( $google_decoded ) && isset( $google_decoded['operation'] ) ? $google_decoded['operation'] : array();
$google_bytes = is_array( $google_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_saved['post_id'], '_tutorpress_live_lesson_sync' ) : '';
tutorpress_live_lessons_assert( is_array( $google_saved ) && $google_item['ID'] === $google_saved['post_id'] && $google_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 'Copied' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_title'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_status'] && is_array( $google_decoded ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $google_decoded ) ) && 1 === $google_decoded['schema_version'] && 2 === $google_decoded['version'] && 'pending' === $google_decoded['state'] && 'google_meet' === $google_decoded['provider'] && 11 === $google_decoded['owner_id'] && null === $google_decoded['provider_id'] && null === $google_decoded['provenance'] && false === $google_decoded['reconciliation_required'] && 1 === $google_operation['base_version'] && 'create' === $google_operation['kind'] && 'not_dispatched' === $google_operation['dispatch_state'] && 4 === $google_operation['menu_order'] && 'Standup' === $google_operation['title'] && 'Notes' === $google_operation['content'] && '2026-09-21 16:00:00' === $google_operation['started_at'] && array( 'unit' => 'minutes', 'value' => 60 ) === $google_operation['duration'] && null === $google_operation['delete_hook'] && null === $google_operation['provider_id'] && $google_bytes === $google_saved['envelope'] && TutorPress_Live_Lesson_Sync_Repository::owns( $google_saved['lock'], 'token-unlinked-google' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_item['ID'] ] ) && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $google_item['ID'] ) && $google_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( (string) $google_bytes, $secret ) && false === strpos( (string) $google_bytes, 'hidden-pass' ) && false === strpos( (string) $google_bytes, 'student@example.com' ) && false === strpos( (string) $google_bytes, 'token-unlinked-google' ), 'google unlinked save stages a version-2 pending create on the same post' );
list( $zoom_item, $zoom_prior ) = $plant_unlinked( 'tutor_zoom_meeting', 'zoom', 6 );
$zoom_save = $zoom;
$zoom_save['title'] = 'Room';
$zoom_save['content'] = 'Agenda';
$zoom_save['settings'] = array( 'password' => 'hidden-pass', 'auto_recording' => 'cloud', 'join_before_host' => true, 'waiting_room' => false, 'host_video' => true );
$zoom_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$zoom_stage_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_saved = $stage_copy( $zoom_item, $zoom_save, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-unlinked-zoom' );
$zoom_decoded = is_array( $zoom_saved ) ? json_decode( $zoom_saved['envelope'], true ) : null;
$zoom_operation = is_array( $zoom_decoded ) && isset( $zoom_decoded['operation'] ) ? $zoom_decoded['operation'] : array();
$zoom_bytes = is_array( $zoom_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_saved['post_id'], '_tutorpress_live_lesson_sync' ) : '';
tutorpress_live_lessons_assert( $zoom_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && is_array( $zoom_decoded ) && 2 === $zoom_decoded['version'] && 'pending' === $zoom_decoded['state'] && 'zoom' === $zoom_decoded['provider'] && 42 === $zoom_decoded['owner_id'] && null === $zoom_decoded['provider_id'] && 6 === $zoom_operation['menu_order'] && array( 'unit' => 'hours', 'value' => 2 ) === $zoom_operation['duration'] && '2026-09-21 17:04:00' === $zoom_operation['end_local'] && $zoom_owner_target === $zoom_operation['provider_target'] && 'not_dispatched' === $zoom_operation['dispatch_state'] && $zoom_bytes === $zoom_saved['envelope'] && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_saved['lock'], 'token-unlinked-zoom' ) && array( 'preflight', 'validate_host' ) === $methods() && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $zoom_item['ID'] ) && false === strpos( (string) $zoom_bytes, 'hidden-pass' ) && false === strpos( (string) $zoom_bytes, $secret ) && $zoom_stage_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'topic zoom unlinked save is allowed' );
$pass_save = $zoom_save;
$pass_save['password'] = 'meeting-pass';
list( $pass_item ) = $plant_unlinked( 'tutor_zoom_meeting', 'zoom', 7 );
$pass_saved = $stage_copy( $pass_item, $pass_save, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-unlinked-zoom-pass' );
$pass_bytes = is_array( $pass_saved ) ? (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_saved['post_id'], '_tutorpress_live_lesson_sync' ) : '';
$pass_row = is_array( $pass_saved ) ? json_decode( $pass_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $pass_saved ) && 'meeting-pass' === $pass_saved['password'] && is_array( $pass_row ) && $zoom_owner_target === $pass_row['operation']['provider_target'] && false === strpos( $pass_bytes, 'meeting-pass' ) && false === strpos( $pass_bytes, '"password"' ), 'an unlinked zoom envelope has no password key' );
$evil_copy = $google_save;
$evil_copy['provider'] = 'google_meet';
$evil_copy['provider_id'] = 'evt-evil';
$id_copy = $google_save;
$id_copy['provider'] = 'google_meet';
$id_copy['id'] = 'evt-evil';
list( $evil_item, $evil_prior ) = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
tutorpress_live_lessons_assert( null === $stage_copy( $evil_item, $evil_copy, array(), 'token-unlinked-evil' ) && null === $stage_copy( $evil_item, $id_copy, array(), 'token-unlinked-id' ) && $evil_prior === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $evil_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $evil_item['ID'] ] ), 'a provider id on an unlinked save is rejected before preflight' );
$mismatch_provider = $google_save;
$mismatch_provider['provider'] = 'zoom';
tutorpress_live_lessons_assert( null === $stage_copy( $evil_item, $mismatch_provider, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-unlinked-mismatch' ) && array() === $methods() && $evil_prior === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $evil_item['ID'], '_tutorpress_live_lesson_sync' ), 'a provider that disagrees with the post type is rejected' );
list( $order_item, $order_prior ) = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
unset( $order_item['menu_order'] );
tutorpress_live_lessons_assert( null === $stage_copy( $order_item, $google_save, array( 'preflight' => array( true ) ), 'token-unlinked-order' ) && array( 'preflight' ) === $methods() && $order_prior === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $order_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $order_item['ID'] ] ), 'a missing menu order is rejected after validation and does not acquire a lock' );
$past_item = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$past_saved = TutorPress_Live_Lesson_Sync_Orchestrator::stage_unlinked( $past_item[0], $topic, $course, $allow, $google_save, $adapter, $later, 'token-unlinked-past', $sync_read, $sync_update, $sync_affected );
tutorpress_live_lessons_assert( null === $past_saved && $past_item[1] === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $past_item[0]['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $past_item[0]['ID'] ] ) && array( 'preflight' ) === $methods(), 'a selected-zone start that is not future is rejected before the lock' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Orchestrator::stage_unlinked( $past_item[0], $topic, $course, $broad, $google_save, $adapter, $now, 'token-unlinked-broad', $sync_read, $sync_update, $sync_affected ) && null === TutorPress_Live_Lesson_Sync_Orchestrator::stage_unlinked( $past_item[0], $bad_topic, $course, $allow, $google_save, $adapter, $now, 'token-unlinked-topic', $sync_read, $sync_update, $sync_affected ) && array() === $methods(), 'unlinked save requires edit_post on the item course' );
$locked_item = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$locked_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$locked_from = count( $GLOBALS['wpdb']->statements );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$locked_out = $stage_copy( $locked_item[0], $google_save, array( 'preflight' => array( true ) ), 'token-unlinked-lock' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $locked_out && $locked_item[1] === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $locked_item[0]['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $locked_item[0]['ID'] ] ) && $locked_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && null === $release_for( $locked_item[0]['ID'], $locked_from ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $locked_item[0]['ID'] ] ), 'a failed unlinked lock leaves the publish copy and does not release' );
$swap_item = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$swap_from = count( $GLOBALS['wpdb']->statements );
$swap_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$swap_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$swapped = $stage_copy( $swap_item[0], $google_save, array( 'preflight' => array( true ) ), 'token-unlinked-swap', static function () { return false; } );
$swap_release = $release_for( $swap_item[0]['ID'], $swap_from );
tutorpress_live_lessons_assert( null === $swapped && $swap_item[1] === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $swap_item[0]['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $swap_item[0]['ID'] ] ) && $swap_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && is_array( $swap_release ) && false !== strpos( $swap_release[0], 'CONVERT(option_value USING BINARY)' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $swap_release[2], 'token-unlinked-swap' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $swap_item[0]['ID'] ] ) && $swap_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a failed unlinked exchange releases the exact lock and keeps the copy' );
tutorpress_live_lessons_pass( 'unlinked explicit save stages a pending create' );

$lock_sql = 'DELETE FROM wp_options WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)';
$release_sql = static function ( $name, $prior ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior ) );
	return 1 === (int) $wpdb->rows_affected;
};
$saved_wpdb = $GLOBALS['wpdb'];
$watched = new class() extends TutorPress_Topic_Copy_Wpdb {
	public $hooks_at_delete = array();
	public function query( $prepared ) {
		$this->hooks_at_delete[] = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
		return parent::query( $prepared );
	}
};
$GLOBALS['wpdb'] = $watched;
$expired = '2026-09-21 16:05:01';
$option_update = array( 'TutorPress_Live_Lessons_Store_Fake', 'update_option_bytes' );
$mark = static function () use ( $watched ) {
	return array( count( $GLOBALS['wpdb']->statements ), count( TutorPress_Live_Lessons_Hook_Fake::$calls ), count( $watched->hooks_at_delete ), count( TutorPress_Topic_Copy_Harness::$deletes ), TutorPress_Live_Lessons_Adapter_Fake::$calls );
};
$quiet = static function ( array $marked ) use ( $watched ) {
	return $marked[0] === count( $GLOBALS['wpdb']->statements ) && $marked[1] === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $marked[2] === count( $watched->hooks_at_delete );
};
$token_delete = static function ( $post_id, $token, $lock, array $marked ) use ( $lock_sql, $watched ) {
	$statements = array_slice( $GLOBALS['wpdb']->statements, $marked[0] );
	$hooks_at = array_slice( $watched->hooks_at_delete, $marked[2] );
	$name = 'tutorpress_live_lesson_lock_' . (int) $post_id;
	return 1 === count( $statements ) && $lock_sql === $statements[0][0] && $name === $statements[0][1] && $lock === $statements[0][2] && TutorPress_Live_Lesson_Sync_Repository::owns( $statements[0][2], $token ) && array( $marked[1] ) === $hooks_at;
};
unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] );

$copy_lock = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$copy_lock_mark = $mark();
$copy_lock_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$copy_lock_saved = $stage_copy( $copy_lock[0], $google_save, array( 'preflight' => array( true ) ), 'token-matrix-unlinked-acquire' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $copy_lock_saved && $copy_lock[1] === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $copy_lock[0]['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $copy_lock[0]['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $copy_lock[0]['ID'] ] ) && $quiet( $copy_lock_mark ) && $copy_lock_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ), 'a failed unlinked acquire leaves the copy and does not force-delete or release' );
$copy_swap = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$copy_swap_mark = $mark();
$copy_swap_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$copy_swap_saved = $stage_copy( $copy_swap[0], $google_save, array( 'preflight' => array( true ) ), 'token-matrix-unlinked-swap', static function () { return false; } );
$copy_swap_lock = isset( $GLOBALS['wpdb']->statements[ $copy_swap_mark[0] ][2] ) ? $GLOBALS['wpdb']->statements[ $copy_swap_mark[0] ][2] : '';
tutorpress_live_lessons_assert( null === $copy_swap_saved && ! ( $copy_swap_saved instanceof WP_Error ) && $copy_swap[1] === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $copy_swap[0]['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $copy_swap[0]['ID'] ] ) && $token_delete( $copy_swap[0]['ID'], 'token-matrix-unlinked-swap', $copy_swap_lock, $copy_swap_mark ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $copy_swap[0]['ID'] ] ) && $copy_swap_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ), 'a failed unlinked exchange releases that token and does not return a persisted-record error' );

$copy_stale_plant = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$copy_stale = $stage_copy( $copy_stale_plant[0], $google_save, array( 'preflight' => array( true ) ), 'token-matrix-unlinked-stale' );
$copy_stale_name = 'tutorpress_live_lesson_lock_' . $copy_stale['post_id'];
$copy_stale_envelope = $copy_stale['envelope'];
$copy_stale_next = TutorPress_Live_Lesson_Sync_Repository::takeover( $copy_stale['post_id'], 'create', 'token-matrix-unlinked-next', $copy_stale['lock'], $expired, $option_update );
$copy_stale_mark = $mark();
$copy_stale_released = TutorPress_Live_Lesson_Sync_Repository::release( $copy_stale['post_id'], 'token-matrix-unlinked-stale', $copy_stale['lock'], $release_sql );
tutorpress_live_lessons_assert( is_string( $copy_stale_next ) && $copy_stale_next === TutorPress_Live_Lessons_Store_Fake::$options[ $copy_stale_name ] && false === $copy_stale_released && $token_delete( $copy_stale['post_id'], 'token-matrix-unlinked-stale', $copy_stale['lock'], $copy_stale_mark ) && $copy_stale_next === TutorPress_Live_Lessons_Store_Fake::$options[ $copy_stale_name ] && $copy_stale_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $copy_stale['post_id'], '_tutorpress_live_lesson_sync' ), 'unlinked stale release leaves the replacement lock and the staged envelope' );

$copy_died_plant = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$copy_died = $stage_copy( $copy_died_plant[0], $google_save, array( 'preflight' => array( true ) ), 'token-matrix-unlinked-died' );
$copy_died_name = 'tutorpress_live_lesson_lock_' . $copy_died['post_id'];
$copy_died_mark = $mark();
$copy_died_held = TutorPress_Live_Lessons_Store_Fake::$options[ $copy_died_name ];
$copy_died_envelope = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $copy_died['post_id'], '_tutorpress_live_lesson_sync' );
$copy_died_same = $quiet( $copy_died_mark ) && $copy_died['lock'] === $copy_died_held && $copy_died['envelope'] === $copy_died_envelope && $copy_died_mark[4] === TutorPress_Live_Lessons_Adapter_Fake::$calls;
$copy_died_next = $copy_died_same ? TutorPress_Live_Lesson_Sync_Repository::takeover( $copy_died['post_id'], 'create', 'token-matrix-unlinked-later', $copy_died['lock'], $expired, $option_update ) : null;
tutorpress_live_lessons_assert( $copy_died_same && is_string( $copy_died_next ) && $copy_died_next === TutorPress_Live_Lessons_Store_Fake::$options[ $copy_died_name ] && $copy_died_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $copy_died['post_id'], '_tutorpress_live_lesson_sync' ) && $quiet( $copy_died_mark ) && $copy_died_mark[4] === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'unlinked process death keeps the staged envelope until takeover replaces only the lock' );
$cleared_id = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => 'tutor-google-meet', 'post_title' => 'Partial' ) );
TutorPress_Live_Lessons_Store_Fake::add_meta( $cleared_id, 'tutor-google-meet-link', 'https://meet.example/a' );
delete_post_meta( $cleared_id, 'tutor-google-meet-link' );
tutorpress_live_lessons_assert( array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $cleared_id, 'tutor-google-meet-link' ), 'delete_post_meta removes one stored key' );
$stored_write = static function ( $post_id, $key, $value ) { return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, $value ); };
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$stored_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stored-create' );
$stored_item = array( 'details' => array( 'id' => 'evt-1' ), 'duration_minutes' => 60, 'end_at' => '2026-09-21T16:04:00-04:00', 'join_url' => 'https://meet.example/a', 'start_at' => '2026-09-21T15:04:00-04:00', 'timezone' => 'America/New_York' );
$stored_result = array( 'diagnostics' => array( 'error' => 'ignore-me' ), 'item' => $stored_item, 'outcome' => 'confirmed', 'provenance' => 'create', 'provider_id' => 'evt-1' );
$stored_map = array( 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00', 'tutor-google-meet-end-datetime' => '2026-09-21 16:04:00', 'tutor-google-meet-event-details' => '{"id":"evt-1"}', 'tutor-google-meet-link' => 'https://meet.example/a' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $stored_result ), 'metadata_map' => array( $stored_map ) );
$stored_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$stored_done = TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create( $stored_staged, 'token-stored-create', $adapter, $sync_read, $sync_update, $sync_affected, $stored_write, $topic, $course, $allow );
$stored_id = is_array( $stored_staged ) ? $stored_staged['post_id'] : 0;
tutorpress_live_lessons_assert( is_array( $stored_done ) && 'evt-1' === $stored_done['provider_id'] && ! array_key_exists( 'envelope', $stored_done ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $stored_id ] ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $stored_id, '_tutorpress_live_lesson_sync' ) && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_rows( $stored_id, 'tutor-google-meet-link' )[0]['meta_value'] && false !== strpos( (string) TutorPress_Live_Lessons_Store_Fake::meta_rows( $stored_id, 'tutor-google-meet-event-details' )[0]['meta_value'], 'evt-1' ) && array( 'create', 'metadata_map' ) === $methods() && array( array( 'tutor_google_meet_after_save_meeting', array( $stored_id ) ) ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $stored_hooks ) ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $stored_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $stored_id ]['post_status'], 'a stored google create writes the tutor meta and the provider id without a sync row' );
$pass_spy = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public $seen = null;
	public function create( $owner_id, array $target ) {
		$this->seen = isset( $target['provider_target']['password'] ) ? $target['provider_target']['password'] : null;
		return parent::create( $owner_id, $target );
	}
};
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'validate_host' => array( true ) );
$pass_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $pass_save, $children, $pass_spy, $now, 'token-zoom-pass' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( array( 'diagnostics' => array(), 'item' => null, 'outcome' => 'pre_dispatch_failure', 'provenance' => 'create', 'provider_id' => null ) ) );
$pass_id = is_array( $pass_staged ) ? $pass_staged['post_id'] : 0;
TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create( $pass_staged, 'token-zoom-pass', $pass_spy, $sync_read, $sync_update, $sync_affected, $stored_write, $topic, $course, $allow );
tutorpress_live_lessons_assert( is_array( $pass_staged ) && 'meeting-pass' === $pass_spy->seen && array( 'create' ) === $methods() && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $pass_id, '_tutorpress_live_lesson_sync' ) && $zoom_owner_target === $pass_staged['operation']['provider_target'], 'a zoom create passes the typed password and writes no sync row' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$pass_staged['password'] = '';
$blank_done = TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create( $pass_staged, 'token-zoom-pass', $pass_spy, $sync_read, $sync_update, $sync_affected, $stored_write, $topic, $course, $allow );
tutorpress_live_lessons_assert( null === $blank_done && array() === $methods(), 'an empty staged zoom password does not call the adapter' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$rejected_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-rejected-create' );
$rejected_result = array( 'diagnostics' => array( 'error' => '400' ), 'item' => null, 'outcome' => 'definite_rejection', 'provenance' => 'create', 'provider_id' => null );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $rejected_result ) );
$rejected_done = TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create( $rejected_staged, 'token-rejected-create', $adapter, $sync_read, $sync_update, $sync_affected, $stored_write, $topic, $course, $allow );
$rejected_id = is_array( $rejected_staged ) ? $rejected_staged['post_id'] : 0;
tutorpress_live_lessons_assert( is_wp_error( $rejected_done ) && 'google_meet_rejected' === $rejected_done->get_error_code() && false !== strpos( $rejected_done->get_error_message(), '400' ) && 500 === $rejected_done->get_error_data()['status'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $rejected_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $rejected_id ]['post_status'] && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $rejected_id, '_tutorpress_live_lesson_sync' ) && array( 'create' ) === $methods(), 'a calendar 400 returns google_meet_rejected and leaves the post' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$client_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-client-create' );
$client_result = array( 'diagnostics' => array(), 'item' => null, 'outcome' => 'uncertain', 'provenance' => 'create', 'provider_id' => null );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $client_result ) );
$client_done = TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create( $client_staged, 'token-client-create', $adapter, $sync_read, $sync_update, $sync_affected, $stored_write, $topic, $course, $allow );
tutorpress_live_lessons_assert( is_wp_error( $client_done ) && 'google_meet_client_unusable' === $client_done->get_error_code() && 'The Google client could not be used.' === $client_done->get_error_message(), 'an uncertain google create returns google_meet_client_unusable' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$kept_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-kept-create' );
$kept_item = array( 'details' => array( 'id' => 'evt-kept' ), 'duration_minutes' => 60, 'end_at' => '2026-09-21T16:04:00-04:00', 'join_url' => 'https://meet.example/a', 'start_at' => '2026-09-21T15:04:00-04:00', 'timezone' => 'America/New_York' );
$kept_result = array( 'diagnostics' => array( 'error' => 'ignore-me' ), 'item' => $kept_item, 'outcome' => 'confirmed', 'provenance' => 'create', 'provider_id' => 'evt-kept' );
$kept_map = array( 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00', 'tutor-google-meet-end-datetime' => '2026-09-21 16:04:00', 'tutor-google-meet-event-details' => '{"id":"evt-kept"}', 'tutor-google-meet-link' => 'https://meet.example/a' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $kept_result ), 'metadata_map' => array( $kept_map ) );
$kept_write = static function ( $post_id, $key, $value ) { return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, 'tutor-google-meet-event-details' === $key ? 'wrong' : $value ); };
$kept_done = TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create( $kept_staged, 'token-kept-create', $adapter, $sync_read, $sync_update, $sync_affected, $kept_write, $topic, $course, $allow );
$kept_id = is_array( $kept_staged ) ? $kept_staged['post_id'] : 0;
tutorpress_live_lessons_assert( is_wp_error( $kept_done ) && 'google_meet_event_exists' === $kept_done->get_error_code() && false !== strpos( $kept_done->get_error_message(), 'evt-kept' ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $kept_id, 'tutor-google-meet-start-datetime' ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $kept_id, 'tutor-google-meet-end-datetime' ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $kept_id, 'tutor-google-meet-event-details' ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $kept_id, '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $kept_id ] ), 'a failed meta write returns google_meet_event_exists and clears the stored keys' );
$GLOBALS['wpdb'] = $saved_wpdb;
tutorpress_live_lessons_pass( 'create and unlinked locks release by exact token' );
