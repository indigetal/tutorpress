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
tutorpress_live_lessons_assert( is_array( $inserted ) && 11 === $inserted['owner_id'] && 'not_dispatched' === $inserted['dispatch_state'] && TutorPress_Live_Lesson_Sync_Repository::owns( $inserted['lock'], 'token-create' ) && 'publish' === $stored['post_status'] && 8 === (int) $stored['post_parent'] && 42 === (int) $stored['post_author'] && 10 === (int) $stored['menu_order'] && 'tutor-google-meet' === $stored['post_type'] && 'Standup' === $stored['post_title'] && 'Notes' === $stored['post_content'] && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $inserted['post_id'], '_tutorpress_live_lesson_sync' ) && array( 'preflight' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && $before === $children, 'create insert stores a publish post and a not-dispatched lock' );
$delete_count = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$locked_out = TutorPress_Live_Lesson_Sync_Orchestrator::insert_locked( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-fail' );
$removed_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id - 1;
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $locked_out && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $removed_id ] ) && array( array( $removed_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $delete_count ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $removed_id, '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $removed_id ] ) && array( 'preflight' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $before === $children, 'a failed create lock force-deletes the publish post and returns no projected record' );
tutorpress_live_lessons_pass( 'create insert locks a publish post' );

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
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $meet_input, $children, $adapter, $now, 'token-stage-google' );
$decoded = is_array( $staged ) ? json_decode( $staged['envelope'], true ) : null;
$operation = is_array( $decoded ) && isset( $decoded['operation'] ) ? $decoded['operation'] : array();
$sync_rows = is_array( $staged ) ? TutorPress_Live_Lessons_Store_Fake::meta_rows( $staged['post_id'], '_tutorpress_live_lesson_sync' ) : array();
$sync_bytes = isset( $sync_rows[0]['meta_value'] ) ? $sync_rows[0]['meta_value'] : '';
tutorpress_live_lessons_assert( is_array( $staged ) && is_array( $decoded ) && $sync_bytes === $staged['envelope'] && $sync_bytes === TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $decoded ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $decoded ) ) && 1 === $decoded['version'] && 'pending' === $decoded['state'] && 'google_meet' === $decoded['provider'] && 11 === $decoded['owner_id'] && null === $decoded['provider_id'] && null === $decoded['provenance'] && false === $decoded['reconciliation_required'] && 1 === preg_match( '/\A[0-9a-f]{32}\z/', $operation['operation_id'] ) && 'create' === $operation['kind'] && '2026-09-21 16:00:00' === $operation['started_at'] && 0 === $operation['base_version'] && 'not_dispatched' === $operation['dispatch_state'] && 7 === $operation['course_id'] && 8 === $operation['topic_id'] && 42 === $operation['post_author'] && 10 === $operation['menu_order'] && 'Standup' === $operation['title'] && 'Notes' === $operation['content'] && '2026-09-21 15:04:00' === $operation['start_local'] && '2026-09-21 16:04:00' === $operation['end_local'] && 'America/New_York' === $operation['timezone'] && array( 'unit' => 'minutes', 'value' => 60 ) === $operation['duration'] && array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 30, 'send_updates' => 'all', 'status' => 'confirmed', 'transparency' => 'transparent', 'visibility' => 'public' ) === $operation['provider_target'] && null === $operation['delete_hook'] && null === $operation['provider_id'] && TutorPress_Live_Lesson_Sync_Repository::owns( $staged['lock'], 'token-stage-google' ) && false === strpos( $sync_bytes, $secret ) && false === strpos( $sync_bytes, 'token-stage-google' ) && false === strpos( $sync_bytes, 'hidden-pass' ) && false === strpos( $sync_bytes, 'student@example.com' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $staged['post_id'] ) && array( 'preflight' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && $before === $children, 'google create stages a version-1 pending snapshot before dispatch' );
$room_input = $zoom;
$room_input['title'] = 'Room';
$room_input['content'] = 'Agenda';
$room_input['settings'] = array( 'password' => 'hidden-pass', 'auto_recording' => 'cloud', 'join_before_host' => true, 'waiting_room' => false, 'host_video' => true );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'validate_host' => array( true ) );
$room_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $room_input, $children, $adapter, $now, 'token-stage-zoom' );
$room_decoded = is_array( $room_staged ) ? json_decode( $room_staged['envelope'], true ) : null;
$room_operation = is_array( $room_decoded ) && isset( $room_decoded['operation'] ) ? $room_decoded['operation'] : array();
$room_bytes = is_array( $room_staged ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $room_staged['post_id'], '_tutorpress_live_lesson_sync' ) : '';
tutorpress_live_lessons_assert( is_array( $room_decoded ) && 'zoom' === $room_decoded['provider'] && 42 === $room_decoded['owner_id'] && null === $room_decoded['provider_id'] && 'pending' === $room_decoded['state'] && 1 === $room_decoded['version'] && array( 'unit' => 'hours', 'value' => 2 ) === $room_operation['duration'] && '2026-09-21 17:04:00' === $room_operation['end_local'] && '2026-09-21 15:04:00' === $room_operation['start_local'] && 42 === $room_operation['post_author'] && array( 'auto_recording' => 'cloud', 'enforce_login' => false, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => true, 'mute_upon_entry' => false, 'participant_video' => false, 'waiting_room' => false ) === $room_operation['provider_target'] && 'not_dispatched' === $room_operation['dispatch_state'] && null === $room_operation['delete_hook'] && $room_bytes === $room_staged['envelope'] && false === strpos( (string) $room_bytes, 'hidden-pass' ) && false === strpos( (string) $room_bytes, $secret ) && false === strpos( (string) $room_bytes, 'token-stage-zoom' ) && array( 'preflight', 'validate_host' ) === $methods() && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $room_staged['post_id'] ), 'zoom create stages the typed duration and omits the password' );
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
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
TutorPress_Topic_Copy_Harness::$fail_init = true;
$unstaged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stage-open' );
$open_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id - 1;
$open_release = $release_for( $open_id, $open_from );
TutorPress_Topic_Copy_Harness::$fail_init = false;
tutorpress_live_lessons_assert( null === $unstaged && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $open_id ] ) && array( array( $open_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $open_deletes ) && is_array( $open_release ) && false !== strpos( $open_release[0], 'CONVERT(option_value USING BINARY)' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $open_release[2], 'token-stage-open' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $open_id ] ) && array() === TutorPress_Live_Lessons_Store_Fake::meta_rows( $open_id, '_tutorpress_live_lesson_sync' ) && array( 'preflight' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $before === $children, 'a failed pending snapshot force-deletes inside the lock and returns no projected record' );
tutorpress_live_lessons_pass( 'create stages a version-1 pending envelope' );

$stuck_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$stuck_from = count( $GLOBALS['wpdb']->statements );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck_id ] = true;
TutorPress_Topic_Copy_Harness::$fail_init = true;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$stuck = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stage-stuck' );
$stuck_release = $release_for( $stuck_id, $stuck_from );
$stuck_data = $stuck instanceof WP_Error ? $stuck->get_error_data() : array();
$stuck_record = isset( $stuck_data['projected_record'] ) ? $stuck_data['projected_record'] : null;
$stuck_json = json_encode( $stuck_data );
TutorPress_Topic_Copy_Harness::$fail_init = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck_id ] );
tutorpress_live_lessons_assert( $stuck instanceof WP_Error && 'live_lesson_persisted_error' === $stuck->get_error_code() && is_array( $stuck_record ) && $stuck_id === $stuck_record['id'] && 'error' === $stuck_record['sync_state'] && 0 === $stuck_record['sync_version'] && null === $stuck_record['operation'] && false === $stuck_record['persisted_envelope'] && $stuck_id === $stuck_data['record_id'] && 7 === $stuck_data['course_id'] && 8 === $stuck_data['topic_id'] && null === $stuck_data['operation_id'] && 'error' === $stuck_data['sync_state'] && 0 === $stuck_data['sync_version'] && false === $stuck_data['persisted'] && true === $stuck_data['refetch_required'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck_id ]['post_status'] && is_array( $stuck_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $stuck_release[2], 'token-stage-stuck' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $stuck_id ] ) && array( 'preflight' ) === $methods() && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( $stuck_json, $secret ) && false === strpos( $stuck_json, 'dispatch_possible' ) && false === strpos( $stuck_json, 'confirmed' ) && $before === $children, 'failed envelope cleanup returns the persisted record and releases the exact lock' );

$orphan_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$orphan_from = count( $GLOBALS['wpdb']->statements );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $orphan_id ] = true;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$orphan = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stage-orphan' );
$orphan_data = $orphan instanceof WP_Error ? $orphan->get_error_data() : array();
$orphan_record = isset( $orphan_data['projected_record'] ) ? $orphan_data['projected_record'] : null;
TutorPress_Topic_Copy_Harness::$fail_lock = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $orphan_id ] );
tutorpress_live_lessons_assert( $orphan instanceof WP_Error && is_array( $orphan_record ) && $orphan_id === $orphan_record['id'] && 'error' === $orphan_record['sync_state'] && 0 === $orphan_record['sync_version'] && null === $orphan_record['operation'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $orphan_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $orphan_id ]['post_status'] && null === $release_for( $orphan_id, $orphan_from ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $orphan_id ] ) && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( json_encode( $orphan_data ), $secret ) && array( 'preflight' ) === $methods(), 'failed lock cleanup returns the persisted record without a lock release' );

$throw_from = count( $GLOBALS['wpdb']->statements );
$throw_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Topic_Copy_Harness::$fail_insert = 'throw';
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$thrown = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $draft, $children, $adapter, $now, 'token-stage-throw' );
$throw_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id - 1;
$throw_release = $release_for( $throw_id, $throw_from );
TutorPress_Topic_Copy_Harness::$fail_insert = null;
tutorpress_live_lessons_assert( null === $thrown && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $throw_id ] ) && array( array( $throw_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $throw_deletes ) && is_array( $throw_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $throw_release[2], 'token-stage-throw' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $throw_id ] ) && false === strpos( json_encode( TutorPress_Live_Lessons_Store_Fake::$posts ), 'envelope failed' ) && false === strpos( json_encode( TutorPress_Live_Lessons_Store_Fake::$options ), 'envelope failed' ) && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( 'preflight' ) === $methods(), 'a thrown envelope write is discarded and the locked insert is force-deleted' );
tutorpress_live_lessons_pass( 'pre-dispatch initialization cleanup' );

$sync_read     = array( 'TutorPress_Live_Lessons_Store_Fake', 'meta_rows' );
$sync_update   = static function ( $post_id, $key, $meta_id, $value, $prior ) { return TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post_id, $key, $meta_id, $value, $prior ); };
$sync_affected = static function () { return TutorPress_Live_Lessons_Store_Fake::$affected; };
$sync_write    = static function ( $post_id, $key, $value ) {
	$rows = TutorPress_Live_Lessons_Store_Fake::meta_rows( $post_id, $key );
	if ( 1 === count( $rows ) && isset( $rows[0]['meta_value'] ) && (string) $rows[0]['meta_value'] === (string) $value ) {
		return false;
	}
	if ( 1 === count( $rows ) ) {
		return TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post_id, $key, $rows[0]['meta_id'], $value, $rows[0]['meta_value'] );
	}
	return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, $value );
};
$dispatch      = static function ( $staged, $token, $adapter, $update = null, $write = null, array $context = array() ) use ( $sync_read, $sync_update, $sync_affected, $sync_write ) {
	return TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_create( $staged, $token, $adapter, $sync_read, null === $update ? $sync_update : $update, $sync_affected, null === $write ? $sync_write : $write, isset( $context['topic'] ) ? $context['topic'] : array(), isset( $context['course'] ) ? $context['course'] : array(), array_key_exists( 'can', $context ) ? $context['can'] : null );
};
$stage_for = static function ( array $input, array $queue, $token ) use ( $topic, $course, $allow, $children, $adapter, $now ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $input, $children, $adapter, $now, $token );
};
$confirmed = array( 'outcome' => 'confirmed', 'provider_id' => 'evt-1', 'item' => array( 'start_at' => '2026-09-21T15:04:00-04:00', 'end_at' => '2026-09-21T16:04:00-04:00', 'timezone' => 'America/New_York', 'duration_minutes' => 60, 'join_url' => 'https://meet.example/a', 'details' => array( 'id' => 'decoy-id' ) ), 'diagnostics' => array( 'error' => 'ignore-me' ), 'provenance' => 'create' );
$google_map = array( 'tutor-google-meet-link' => 'https://meet.example/a', 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00' );
$ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-google' );
$spy = new class( $ready['post_id'] ) extends TutorPress_Live_Lessons_Adapter_Fake {
	public $seen = null;
	private $post_id;
	public function __construct( $post_id ) { $this->post_id = (int) $post_id; }
	public function create( $owner_id, array $target ) {
		$this->seen = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $this->post_id, '_tutorpress_live_lesson_sync' ), true );
		return parent::create( $owner_id, $target );
	}
};
$google_from = count( $GLOBALS['wpdb']->statements );
$google_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$dispatched = $dispatch( $ready, 'token-dispatch-google', $spy );
$done = is_array( $dispatched ) ? json_decode( $dispatched['envelope'], true ) : null;
$done_operation = is_array( $done ) && isset( $done['operation'] ) ? $done['operation'] : array();
$create_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] : array();
$stored_link = is_array( $dispatched ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dispatched['post_id'], 'tutor-google-meet-link' ) : '';
$google_release = is_array( $dispatched ) ? $release_for( $dispatched['post_id'], $google_from ) : null;
$google_hook = is_array( $dispatched ) ? array( 'tutor_google_meet_after_save_meeting', array( (int) $dispatched['post_id'] ) ) : array();
tutorpress_live_lessons_assert( is_array( $spy->seen ) && 2 === $spy->seen['version'] && 'pending' === $spy->seen['state'] && 'dispatch_possible' === $spy->seen['operation']['dispatch_state'] && is_array( $done ) && 3 === $done['version'] && 'synced' === $done['state'] && 'evt-1' === $done['provider_id'] && 'create' === $done['provenance'] && false === $done['reconciliation_required'] && null === $done['error'] && 'evt-1' === $done_operation['provider_id'] && 'confirmed' === $done_operation['dispatch_state'] && 'No' === $create_target['add_enrolled_students'] && 'No' === $create_target['provider_target']['add_enrolled_students'] && 'https://meet.example/a' === $stored_link && array( 'create', 'metadata_map' ) === $methods() && is_array( $google_release ) && false !== strpos( $google_release[0], 'CONVERT(option_value USING BINARY)' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $google_release[2], 'token-dispatch-google' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $dispatched['post_id'] ] ) && array( $google_hook ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $google_hooks ) ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $dispatched['post_id'] ]['post_status'] && false === strpos( json_encode( $done ), 'decoy-id' ) && false === strpos( json_encode( $done ), 'ignore-me' ) && false === strpos( json_encode( $google_hook ), $secret ), 'confirmed google create copies scalars, writes the re-read map, releases the lock, and ignores the save hook return' );
$rewrite_returns = array();
$rewrite_write   = static function ( $post_id, $key, $value ) use ( $sync_write, &$rewrite_returns ) {
	$wrote             = $sync_write( $post_id, $key, $value );
	$rewrite_returns[] = $wrote;
	return $wrote;
};
$rewrite_method = new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'map_stored' );
if ( PHP_VERSION_ID < 80100 ) {
	$rewrite_method->setAccessible( true );
}
$rewritten    = $rewrite_method->invoke( null, $dispatched['post_id'], $google_map, $rewrite_write, $sync_read );
$rewrite_rows = array();
foreach ( array_keys( $google_map ) as $rewrite_key ) {
	$rewrite_rows[ $rewrite_key ] = count( TutorPress_Live_Lessons_Store_Fake::meta_rows( $dispatched['post_id'], $rewrite_key ) );
}
tutorpress_live_lessons_assert( true === $rewritten && array( false, false ) === $rewrite_returns && array( 'tutor-google-meet-link' => 1, 'tutor-google-meet-start-datetime' => 1 ) === $rewrite_rows && 1 === count( TutorPress_Live_Lessons_Store_Fake::meta_rows( $dispatched['post_id'], '_tutorpress_live_lesson_sync' ) ), 'an unchanged provider-meta rewrite succeeds and leaves one row per key' );

$zoom_ready = $stage_for( $room_input, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-dispatch-zoom' );
$zoom_from = count( $GLOBALS['wpdb']->statements );
$zoom_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( array( '_tutor_zm_data' => '{"id":"evt-1"}' ) ) );
$zoom_done = $dispatch( $zoom_ready, 'token-dispatch-zoom', $adapter );
$zoom_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] : array();
$zoom_envelope = is_array( $zoom_done ) ? json_decode( $zoom_done['envelope'], true ) : null;
$zoom_release = is_array( $zoom_done ) ? $release_for( $zoom_done['post_id'], $zoom_from ) : null;
$zoom_hook = is_array( $zoom_done ) ? array( 'tutor_zoom_after_save_meeting', array( (int) $zoom_done['post_id'] ) ) : array();
tutorpress_live_lessons_assert( is_array( $zoom_envelope ) && 'synced' === $zoom_envelope['state'] && 'evt-1' === $zoom_envelope['provider_id'] && 'host-1' === $zoom_target['provider_target']['host_id'] && false === array_key_exists( 'host_id', $zoom_target ) && '{"id":"evt-1"}' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_done['post_id'], '_tutor_zm_data' ) && array( 'create', 'metadata_map' ) === $methods() && is_array( $zoom_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_release[2], 'token-dispatch-zoom' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_done['post_id'] ] ) && array( $zoom_hook ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $zoom_hooks ) ), 'zoom create keeps the nested provider target and fires the zoom save hook' );

$blocked_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-block' );
$blocked_before = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $blocked_ready['post_id'], '_tutorpress_live_lesson_sync' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
tutorpress_live_lessons_assert( null === $dispatch( $blocked_ready, 'token-dispatch-block', $adapter, static function () { return false; } ) && array() === $methods() && $blocked_before === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $blocked_ready['post_id'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $blocked_ready['post_id'] ), 'a failed dispatch swap does not call the adapter' );

$uncertain_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-uncertain' );
$uncertain_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( array( 'outcome' => 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array( 'error' => 'decoy-id' ), 'provenance' => 'create' ) ) );
$uncertain = $dispatch( $uncertain_ready, 'token-dispatch-uncertain', $adapter );
$uncertain_envelope = is_array( $uncertain ) ? json_decode( $uncertain['envelope'], true ) : null;
$uncertain_post = is_array( $uncertain ) ? TutorPress_Live_Lessons_Store_Fake::$posts[ $uncertain['post_id'] ] : array();
$uncertain_projected = is_array( $uncertain ) ? TutorPress_Live_Lesson_Response_Projector::project_item( $uncertain_post, $topic, $course, $allow, $uncertain_envelope, null ) : null;
tutorpress_live_lessons_assert( is_array( $uncertain_envelope ) && is_array( $uncertain_projected ) && $uncertain['post_id'] === $uncertain_projected['id'] && 'error' === $uncertain_projected['sync_state'] && 3 === $uncertain_envelope['version'] && 'error' === $uncertain_envelope['state'] && null === $uncertain_envelope['provider_id'] && null === $uncertain_envelope['provenance'] && true === $uncertain_envelope['reconciliation_required'] && 'provider_create_failed' === $uncertain_envelope['error'] && 'dispatch_possible' === $uncertain_envelope['operation']['dispatch_state'] && 'publish' === $uncertain_post['post_status'] && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $uncertain['post_id'] ) && array( 'create' ) === $methods() && TutorPress_Live_Lesson_Sync_Repository::owns( $uncertain['lock'], 'token-dispatch-uncertain' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $uncertain['post_id'] ] ) && false === strpos( json_encode( $uncertain_envelope ), 'decoy-id' ) && $uncertain_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an id-less uncertain create stays a discoverable publish error and writes no provider meta' );

$throw_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-throw' );
$throw_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$throwing = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public function create( $owner_id, array $target ) { throw new RuntimeException( 'calendar exploded token' ); }
};
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$exploded = $dispatch( $throw_ready, 'token-dispatch-throw', $throwing );
$exploded_envelope = is_array( $exploded ) ? json_decode( $exploded['envelope'], true ) : null;
$exploded_bytes = is_array( $exploded ) ? $exploded['envelope'] : '';
tutorpress_live_lessons_assert( is_array( $exploded_envelope ) && 'error' === $exploded_envelope['state'] && 'provider_create_failed' === $exploded_envelope['error'] && null === $exploded_envelope['provider_id'] && true === $exploded_envelope['reconciliation_required'] && false === strpos( $exploded_bytes, 'calendar exploded' ) && false === strpos( $exploded_bytes, 'token' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $exploded['post_id'] ) && array() === $methods() && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $exploded['post_id'] ] ) && $throw_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a thrown create discards the exception text' );

$mismatch_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-mismatch' );
$mismatch_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$mismatch = $dispatch( $mismatch_ready, 'token-dispatch-mismatch', $adapter, null, static function ( $post_id, $key, $value ) { unset( $value ); return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, 'wrong' ); } );
$mismatch_envelope = is_array( $mismatch ) ? json_decode( $mismatch['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $mismatch_envelope ) && 'error' === $mismatch_envelope['state'] && true === $mismatch_envelope['reconciliation_required'] && 'create' === $mismatch_envelope['provenance'] && 'dispatch_possible' === $mismatch_envelope['operation']['dispatch_state'] && 'evt-1' === $mismatch_envelope['provider_id'] && 'wrong' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $mismatch['post_id'], 'tutor-google-meet-link' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $mismatch['post_id'] ] ) && $mismatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a mismatched metadata re-read does not transition synced' );
$rejected_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-rejected' );
$rejected_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( array( 'outcome' => 'definite_rejection', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) ) );
$rejected = $dispatch( $rejected_ready, 'token-dispatch-rejected', $adapter );
$rejected_envelope = is_array( $rejected ) ? json_decode( $rejected['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $rejected_envelope ) && 'error' === $rejected_envelope['state'] && false === $rejected_envelope['reconciliation_required'] && null === $rejected_envelope['provider_id'] && 'provider_create_failed' === $rejected_envelope['error'] && 'dispatch_possible' === $rejected_envelope['operation']['dispatch_state'] && array( 'create' ) === $methods() && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $rejected['post_id'] ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $rejected['post_id'] ] ) && $rejected_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a definite rejection stays an error without reconciliation' );
$raw_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-raw' );
$raw = $confirmed;
$raw['provider_id'] = 'evt-raw';
$raw['extra'] = true;
$raw_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $raw ), 'metadata_map' => array( $google_map ) );
$normalized = $dispatch( $raw_ready, 'token-dispatch-raw', $adapter );
$normalized_envelope = is_array( $normalized ) ? json_decode( $normalized['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $normalized_envelope ) && 'error' === $normalized_envelope['state'] && null === $normalized_envelope['provider_id'] && true === $normalized_envelope['reconciliation_required'] && array( 'create' ) === $methods() && false === strpos( json_encode( $normalized_envelope ), 'evt-raw' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $normalized['post_id'] ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $normalized['post_id'] ] ) && $raw_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an unnormalized create result is rejected before identity is read' );
tutorpress_live_lessons_pass( 'provider create dispatch finalizes synced metadata' );

$hook_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-hook' );
$hook_from = count( $GLOBALS['wpdb']->statements );
$hook_calls = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$hooked = $dispatch( $hook_ready, 'token-dispatch-hook', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$hook_data = $hooked instanceof WP_Error ? $hooked->get_error_data() : array();
$hook_record = isset( $hook_data['projected_record'] ) ? $hook_data['projected_record'] : null;
$hook_release = isset( $hook_data['record_id'] ) ? $release_for( $hook_data['record_id'], $hook_from ) : null;
$hook_bytes = isset( $hook_data['record_id'] ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_data['record_id'], '_tutorpress_live_lesson_sync' ) : '';
$hook_envelope = is_string( $hook_bytes ) ? json_decode( $hook_bytes, true ) : null;
$hook_json = json_encode( $hook_data );
tutorpress_live_lessons_assert( $hooked instanceof WP_Error && 'live_lesson_persisted_transition' === $hooked->get_error_code() && 'Live lesson transition failed.' === $hooked->get_error_message() && true === $hook_data['persisted'] && true === $hook_data['refetch_required'] && is_array( $hook_record ) && 'synced' === $hook_record['sync_state'] && 3 === $hook_record['sync_version'] && $hook_data['record_id'] === $hook_record['id'] && is_array( $hook_envelope ) && 3 === $hook_envelope['version'] && 'synced' === $hook_envelope['state'] && 'evt-1' === $hook_envelope['provider_id'] && 'confirmed' === $hook_envelope['operation']['dispatch_state'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_data['record_id'] ]['post_status'] && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_data['record_id'], 'tutor-google-meet-link' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $hook_data['record_id'] ] ) && is_array( $hook_release ) && false !== strpos( $hook_release[0], 'CONVERT(option_value USING BINARY)' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $hook_release[2], 'token-dispatch-hook' ) && array( array( 'tutor_google_meet_after_save_meeting', array( (int) $hook_data['record_id'] ) ) ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $hook_calls ) ) && false === strpos( (string) $hook_json, 'hook-exploded-secret' ) && false === strpos( (string) $hook_bytes, 'hook-exploded-secret' ) && false === strpos( (string) $hook_json, 'exactly' ), 'a thrown save hook keeps the synced record and returns a persisted transition error' );

$bare_ready = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-dispatch-bare' );
$bare_calls = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$bare = $dispatch( $bare_ready, 'token-dispatch-bare', $adapter );
$bare_bytes = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $bare_ready['post_id'], '_tutorpress_live_lesson_sync' );
$bare_envelope = is_string( $bare_bytes ) ? json_decode( $bare_bytes, true ) : null;
$bare_json = json_encode( TutorPress_Live_Lessons_Store_Fake::$posts[ $bare_ready['post_id'] ] );
tutorpress_live_lessons_assert( null === $bare && is_array( $bare_envelope ) && 'synced' === $bare_envelope['state'] && 3 === $bare_envelope['version'] && 'evt-1' === $bare_envelope['provider_id'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $bare_ready['post_id'] ] ) && 1 === count( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $bare_calls ) ) ) && false === strpos( (string) $bare_bytes, 'hook-exploded-secret' ) && false === strpos( (string) $bare_json, 'hook-exploded-secret' ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $bare_ready['post_id'] ]['post_status'], 'an unauthorized hook failure releases the lock and returns no projected record' );
tutorpress_live_lessons_pass( 'synced create releases the lock before the provider save hook' );

$plant_unlinked = static function ( $post_type, $provider, $menu_order ) {
	$id    = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => $post_type, 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => $menu_order, 'post_author' => 42 ) );
	$prior = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( array( 'schema_version' => 1, 'version' => 1, 'state' => 'unlinked', 'provider' => $provider, 'owner_id' => 42, 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => null ) );
	add_post_meta( $id, '_tutorpress_live_lesson_sync', $prior );
	$item = array( 'ID' => $id, 'post_type' => $post_type, 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => $menu_order );
	return array( $item, $prior );
};
$stage_copy = static function ( array $item, array $input, array $queue, $token, $update = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
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
tutorpress_live_lessons_assert( is_array( $google_saved ) && $google_item['ID'] === $google_saved['post_id'] && $google_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 'Copied' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_title'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_status'] && is_array( $google_decoded ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $google_decoded ) ) && 1 === $google_decoded['schema_version'] && 2 === $google_decoded['version'] && 'pending' === $google_decoded['state'] && 'google_meet' === $google_decoded['provider'] && 11 === $google_decoded['owner_id'] && null === $google_decoded['provider_id'] && null === $google_decoded['provenance'] && false === $google_decoded['reconciliation_required'] && 1 === $google_operation['base_version'] && 'create' === $google_operation['kind'] && 'not_dispatched' === $google_operation['dispatch_state'] && 4 === $google_operation['menu_order'] && 'Standup' === $google_operation['title'] && 'Notes' === $google_operation['content'] && '2026-09-21 16:00:00' === $google_operation['started_at'] && array( 'unit' => 'minutes', 'value' => 60 ) === $google_operation['duration'] && null === $google_operation['delete_hook'] && null === $google_operation['provider_id'] && $google_bytes === $google_saved['envelope'] && TutorPress_Live_Lesson_Sync_Repository::owns( $google_saved['lock'], 'token-unlinked-google' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_item['ID'] ] ) && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $google_item['ID'] ) && $google_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( (string) $google_bytes, $secret ) && false === strpos( (string) $google_bytes, 'hidden-pass' ) && false === strpos( (string) $google_bytes, 'student@example.com' ) && false === strpos( (string) $google_bytes, 'token-unlinked-google' ), 'google unlinked save stages a version-2 pending create on the same post' );
$google_from = count( $GLOBALS['wpdb']->statements );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$google_dispatch = $dispatch( $google_saved, 'token-unlinked-google', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$google_final = is_array( $google_dispatch ) ? json_decode( $google_dispatch['envelope'], true ) : null;
$google_create = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] : array();
$google_release = is_array( $google_dispatch ) ? $release_for( $google_dispatch['post_id'], $google_from ) : null;
$google_hook = is_array( $google_dispatch ) ? array( 'tutor_google_meet_after_save_meeting', array( (int) $google_dispatch['post_id'] ) ) : array();
tutorpress_live_lessons_assert( is_array( $google_final ) && $google_item['ID'] === $google_dispatch['post_id'] && $google_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 'Copied' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_title'] && 4 === $google_final['version'] && 'synced' === $google_final['state'] && 'create' === $google_final['provenance'] && 'evt-1' === $google_final['provider_id'] && 'confirmed' === $google_final['operation']['dispatch_state'] && null === $google_create['provider_id'] && false === strpos( json_encode( $google_create ), 'evt-evil' ) && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_item['ID'], 'tutor-google-meet-link' ) && array( 'create', 'metadata_map' ) === $methods() && is_array( $google_release ) && false !== strpos( $google_release[0], 'CONVERT(option_value USING BINARY)' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $google_release[2], 'token-unlinked-google' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_item['ID'] ] ) && array( $google_hook ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $google_hooks ) ) && 1 === count( $google_hook[1] ), 'unlinked google create keeps the post, writes the map, releases the lock, then fires the save hook' );
$zoom_source = array( 'ID' => 1, 'post_type' => 'tutor_zoom_meeting', 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Room', 'post_content' => '' );
$zoom_dup_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$zoom_dup = TutorPress_Live_Lesson_Duplicate_Service::duplicate( $zoom_source, $topic, $course, $topic, $course, $children, array(), $allow );
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
tutorpress_live_lessons_assert( null === $zoom_dup && $zoom_dup_next === $zoom_item['ID'] && $zoom_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && is_array( $zoom_decoded ) && 2 === $zoom_decoded['version'] && 'pending' === $zoom_decoded['state'] && 'zoom' === $zoom_decoded['provider'] && 42 === $zoom_decoded['owner_id'] && null === $zoom_decoded['provider_id'] && 6 === $zoom_operation['menu_order'] && array( 'unit' => 'hours', 'value' => 2 ) === $zoom_operation['duration'] && '2026-09-21 17:04:00' === $zoom_operation['end_local'] && array( 'auto_recording' => 'cloud', 'enforce_login' => false, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => true, 'mute_upon_entry' => false, 'participant_video' => false, 'waiting_room' => false ) === $zoom_operation['provider_target'] && 'not_dispatched' === $zoom_operation['dispatch_state'] && $zoom_bytes === $zoom_saved['envelope'] && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_saved['lock'], 'token-unlinked-zoom' ) && array( 'preflight', 'validate_host' ) === $methods() && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $zoom_item['ID'] ) && false === strpos( (string) $zoom_bytes, 'hidden-pass' ) && false === strpos( (string) $zoom_bytes, $secret ) && $zoom_stage_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'topic zoom unlinked save is allowed and direct zoom duplication inserts nothing' );
$zoom_hook_before = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( array( '_tutor_zm_data' => '{"id":"evt-1"}' ) ) );
$zoom_final_dispatch = $dispatch( $zoom_saved, 'token-unlinked-zoom', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$zoom_final = is_array( $zoom_final_dispatch ) ? json_decode( $zoom_final_dispatch['envelope'], true ) : null;
$zoom_hook = is_array( $zoom_final_dispatch ) ? array( 'tutor_zoom_after_save_meeting', array( (int) $zoom_final_dispatch['post_id'] ) ) : array();
tutorpress_live_lessons_assert( is_array( $zoom_final ) && $zoom_item['ID'] === $zoom_final_dispatch['post_id'] && $zoom_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 4 === $zoom_final['version'] && 'synced' === $zoom_final['state'] && 'evt-1' === $zoom_final['provider_id'] && '{"id":"evt-1"}' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_item['ID'], '_tutor_zm_data' ) && array( $zoom_hook ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $zoom_hook_before ) ) && 1 === count( $zoom_hook[1] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_item['ID'] ] ), 'unlinked zoom create keeps the post and fires only the zoom save hook' );
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
$unlinked_error = static function ( array $queue, $token, $adapter, $write = null ) use ( $google_save, $stage_copy, $dispatch, $plant_unlinked ) {
	$planted = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
	$staged  = $stage_copy( $planted[0], $google_save, array( 'preflight' => array( true ) ), $token );
	$hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	$result = is_array( $staged ) ? $dispatch( $staged, $token, $adapter, null, $write ) : null;
	return array( $planted[0], $result, $hooks );
};
list( $uncertain_item, $uncertain_saved, $uncertain_hooks ) = $unlinked_error( array( 'create' => array( array( 'outcome' => 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array( 'error' => 'decoy-id' ), 'provenance' => 'create' ) ) ), 'token-unlinked-uncertain', $adapter );
$uncertain_envelope = is_array( $uncertain_saved ) ? json_decode( $uncertain_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $uncertain_envelope ) && $uncertain_item['ID'] === $uncertain_saved['post_id'] && 4 === $uncertain_envelope['version'] && 'error' === $uncertain_envelope['state'] && null === $uncertain_envelope['provider_id'] && true === $uncertain_envelope['reconciliation_required'] && 'provider_create_failed' === $uncertain_envelope['error'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $uncertain_item['ID'] ]['post_status'] && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $uncertain_item['ID'] ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $uncertain_item['ID'] ] ) && $uncertain_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( json_encode( $uncertain_envelope ), 'decoy-id' ), 'an unlinked id-less uncertain create stays locked without a save hook' );
list( $thrown_item, $thrown_saved, $thrown_hooks ) = $unlinked_error( array(), 'token-unlinked-throw', $throwing );
$thrown_envelope = is_array( $thrown_saved ) ? json_decode( $thrown_saved['envelope'], true ) : null;
$thrown_bytes = is_array( $thrown_saved ) ? $thrown_saved['envelope'] : '';
tutorpress_live_lessons_assert( is_array( $thrown_envelope ) && $thrown_item['ID'] === $thrown_saved['post_id'] && 4 === $thrown_envelope['version'] && 'error' === $thrown_envelope['state'] && true === $thrown_envelope['reconciliation_required'] && false === strpos( (string) $thrown_bytes, 'calendar exploded' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $thrown_item['ID'] ] ) && $thrown_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an unlinked thrown create discards the exception text and does not fire a save hook' );
list( $rejected_item, $rejected_saved, $rejected_hooks ) = $unlinked_error( array( 'create' => array( array( 'outcome' => 'definite_rejection', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) ) ), 'token-unlinked-rejected', $adapter );
$rejected_envelope = is_array( $rejected_saved ) ? json_decode( $rejected_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $rejected_envelope ) && 4 === $rejected_envelope['version'] && 'error' === $rejected_envelope['state'] && false === $rejected_envelope['reconciliation_required'] && null === $rejected_envelope['provider_id'] && 'dispatch_possible' === $rejected_envelope['operation']['dispatch_state'] && $rejected_item['ID'] === $rejected_saved['post_id'] && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $rejected_item['ID'] ] ) && $rejected_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an unlinked definite rejection stays locked without a save hook' );
list( $mismatch_item, $mismatch_saved, $mismatch_hooks ) = $unlinked_error( array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) ), 'token-unlinked-mismatch', $adapter, static function ( $post_id, $key, $value ) { unset( $value ); return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, 'wrong' ); } );
$mismatch_envelope = is_array( $mismatch_saved ) ? json_decode( $mismatch_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $mismatch_envelope ) && 4 === $mismatch_envelope['version'] && 'error' === $mismatch_envelope['state'] && 'evt-1' === $mismatch_envelope['provider_id'] && 'dispatch_possible' === $mismatch_envelope['operation']['dispatch_state'] && 'wrong' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $mismatch_item['ID'], 'tutor-google-meet-link' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $mismatch_item['ID'] ] ) && $mismatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an unlinked metadata mismatch stays locked without a save hook' );
$raw_unlinked = $confirmed;
$raw_unlinked['provider_id'] = 'evt-raw';
$raw_unlinked['extra'] = true;
list( $raw_item, $raw_saved, $raw_hooks ) = $unlinked_error( array( 'create' => array( $raw_unlinked ), 'metadata_map' => array( $google_map ) ), 'token-unlinked-raw', $adapter );
$raw_envelope = is_array( $raw_saved ) ? json_decode( $raw_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $raw_envelope ) && 4 === $raw_envelope['version'] && 'error' === $raw_envelope['state'] && null === $raw_envelope['provider_id'] && true === $raw_envelope['reconciliation_required'] && false === strpos( json_encode( $raw_envelope ), 'evt-raw' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $raw_item['ID'] ] ) && $raw_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an unlinked extra-key result stays locked without a save hook' );
$hook_plant = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$hook_staged = $stage_copy( $hook_plant[0], $google_save, array( 'preflight' => array( true ) ), 'token-unlinked-hook' );
$hook_before = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$hooked = $dispatch( $hook_staged, 'token-unlinked-hook', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$hook_data = $hooked instanceof WP_Error ? $hooked->get_error_data() : array();
$hook_bytes = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_plant[0]['ID'], '_tutorpress_live_lesson_sync' );
$hook_envelope = is_string( $hook_bytes ) ? json_decode( $hook_bytes, true ) : null;
tutorpress_live_lessons_assert( $hooked instanceof WP_Error && 'live_lesson_persisted_transition' === $hooked->get_error_code() && true === $hook_data['persisted'] && is_array( $hook_envelope ) && 4 === $hook_envelope['version'] && 'synced' === $hook_envelope['state'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_plant[0]['ID'] ]['post_status'] && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_plant[0]['ID'], 'tutor-google-meet-link' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $hook_plant[0]['ID'] ] ) && false === strpos( json_encode( $hook_data ), 'hook-exploded-secret' ) && 1 === count( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $hook_before ) ) ), 'an unlinked hook failure keeps the synced post and returns a persisted transition' );
$bare_plant = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$bare_staged = $stage_copy( $bare_plant[0], $google_save, array( 'preflight' => array( true ) ), 'token-unlinked-bare' );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$bare = $dispatch( $bare_staged, 'token-unlinked-bare', $adapter );
$bare_envelope = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $bare_plant[0]['ID'], '_tutorpress_live_lesson_sync' ), true );
tutorpress_live_lessons_assert( null === $bare && is_array( $bare_envelope ) && 4 === $bare_envelope['version'] && 'synced' === $bare_envelope['state'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $bare_plant[0]['ID'] ]['post_status'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $bare_plant[0]['ID'] ] ) && false === strpos( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $bare_plant[0]['ID'], '_tutorpress_live_lesson_sync' ), 'hook-exploded-secret' ), 'an unlinked hook failure without topic context returns null after release' );
$release_plant = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$release_staged = $stage_copy( $release_plant[0], $google_save, array( 'preflight' => array( true ) ), 'token-unlinked-release' );
$release_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $release_plant[0]['ID'] ] = 'replaced-lock-bytes';
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
$release_saved = $dispatch( $release_staged, 'token-unlinked-release', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$release_envelope = is_array( $release_saved ) ? json_decode( $release_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $release_envelope ) && 4 === $release_envelope['version'] && 'synced' === $release_envelope['state'] && $release_plant[0]['ID'] === $release_saved['post_id'] && 'replaced-lock-bytes' === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $release_plant[0]['ID'] ] && $release_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a failed unlinked release returns the synced post and does not fire the save hook' );
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
$queue_create = static function () use ( $confirmed, $google_map ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $confirmed ), 'metadata_map' => array( $google_map ) );
};
unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] );

$done = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-done' );
$done_id = $done['post_id'];
$done_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$done_mark = $mark();
$queue_create();
$done_saved = $dispatch( $done, 'token-matrix-pending-done', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$done_envelope = is_array( $done_saved ) ? json_decode( $done_saved['envelope'], true ) : null;
$done_hook = array( 'tutor_google_meet_after_save_meeting', array( (int) $done_id ) );
tutorpress_live_lessons_assert( is_array( $done_envelope ) && 3 === $done_envelope['version'] && 'synced' === $done_envelope['state'] && $done_id === $done_saved['post_id'] && $done_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $token_delete( $done_id, 'token-matrix-pending-done', $done['lock'], $done_mark ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $done_id ] ) && array( $done_hook ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $done_mark[1] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $done_id ]['post_status'], 'pending completion deletes the acquired token before the save hook' );

$thrown_create = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-throw' );
$thrown_id = $thrown_create['post_id'];
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$thrown_mark = $mark();
$thrown_saved = $dispatch( $thrown_create, 'token-matrix-pending-throw', $throwing );
$thrown_envelope = is_array( $thrown_saved ) ? json_decode( $thrown_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $thrown_envelope ) && 'error' === $thrown_envelope['state'] && $thrown_create['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $thrown_id ] && $quiet( $thrown_mark ) && $thrown_mark[4] === TutorPress_Live_Lessons_Adapter_Fake::$calls && false === strpos( (string) $thrown_saved['envelope'], 'calendar exploded' ), 'a pending provider throw adds no delete and leaves the lock' );

$hook_create = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-hook' );
$hook_id = $hook_create['post_id'];
$hook_mark = $mark();
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
$queue_create();
$hook_saved = $dispatch( $hook_create, 'token-matrix-pending-hook', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$hook_bytes = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_id, '_tutorpress_live_lesson_sync' );
$hook_state = is_string( $hook_bytes ) ? json_decode( $hook_bytes, true ) : null;
unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] );
tutorpress_live_lessons_assert( $hook_saved instanceof WP_Error && 'live_lesson_persisted_transition' === $hook_saved->get_error_code() && is_array( $hook_state ) && 3 === $hook_state['version'] && 'synced' === $hook_state['state'] && $token_delete( $hook_id, 'token-matrix-pending-hook', $hook_create['lock'], $hook_mark ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $hook_id ] ) && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_id, 'tutor-google-meet-link' ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_id ]['post_status'] && false === strpos( json_encode( $hook_saved->get_error_data() ), 'hook-exploded-secret' ) && false === strpos( (string) $hook_bytes, 'hook-exploded-secret' ), 'a pending hook throw follows one token delete and keeps the synced post' );

$acquire_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$acquire_mark = $mark();
$acquire_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$acquire_saved = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-acquire' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $acquire_saved && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $acquire_id ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $acquire_id ] ) && $quiet( $acquire_mark ) && array( array( $acquire_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $acquire_deletes ), 'a failed pending acquire force-deletes and stores no token' );
$kept_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$kept_mark = $mark();
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $kept_id ] = true;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$kept_saved = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-kept' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $kept_id ] );
$kept_data = $kept_saved instanceof WP_Error ? $kept_saved->get_error_data() : array();
tutorpress_live_lessons_assert( $kept_saved instanceof WP_Error && 'live_lesson_persisted_error' === $kept_saved->get_error_code() && false === $kept_data['persisted'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $kept_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $kept_id ]['post_status'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $kept_id ] ) && $quiet( $kept_mark ), 'a failed pending acquire whose force-delete fails returns the persisted-record error and adds no delete' );
$init_id = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$init_mark = $mark();
TutorPress_Topic_Copy_Harness::$fail_init = true;
$init_saved = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-init' );
TutorPress_Topic_Copy_Harness::$fail_init = false;
$init_lock = isset( $GLOBALS['wpdb']->statements[ $init_mark[0] ][2] ) ? $GLOBALS['wpdb']->statements[ $init_mark[0] ][2] : '';
tutorpress_live_lessons_assert( null === $init_saved && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $init_id ] ) && $token_delete( $init_id, 'token-matrix-pending-init', $init_lock, $init_mark ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $init_id ] ) && $init_mark[1] === count( TutorPress_Live_Lessons_Hook_Fake::$calls ), 'pending envelope initialization failure releases only that token after a successful force-delete' );
$stuck_init = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$stuck_mark = $mark();
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck_init ] = true;
TutorPress_Topic_Copy_Harness::$fail_init = true;
$stuck_saved = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-init-kept' );
TutorPress_Topic_Copy_Harness::$fail_init = false;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck_init ] );
$stuck_lock = isset( $GLOBALS['wpdb']->statements[ $stuck_mark[0] ][2] ) ? $GLOBALS['wpdb']->statements[ $stuck_mark[0] ][2] : '';
$stuck_error = $stuck_saved instanceof WP_Error ? $stuck_saved->get_error_data() : array();
tutorpress_live_lessons_assert( $stuck_saved instanceof WP_Error && 'live_lesson_persisted_error' === $stuck_saved->get_error_code() && false === $stuck_error['persisted'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck_init ] ) && $token_delete( $stuck_init, 'token-matrix-pending-init-kept', $stuck_lock, $stuck_mark ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $stuck_init ] ) && $stuck_mark[1] === count( TutorPress_Live_Lessons_Hook_Fake::$calls ), 'pending envelope initialization failure still releases only that token when force-delete fails' );

$stale = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-stale' );
$stale_name = 'tutorpress_live_lesson_lock_' . $stale['post_id'];
$stale_envelope = $stale['envelope'];
$stale_next = TutorPress_Live_Lesson_Sync_Repository::takeover( $stale['post_id'], 'create', 'token-matrix-pending-next', $stale['lock'], $expired, $option_update );
$stale_mark = $mark();
$stale_released = TutorPress_Live_Lesson_Sync_Repository::release( $stale['post_id'], 'token-matrix-pending-stale', $stale['lock'], $release_sql );
tutorpress_live_lessons_assert( is_string( $stale_next ) && $stale_next === TutorPress_Live_Lessons_Store_Fake::$options[ $stale_name ] && TutorPress_Live_Lesson_Sync_Repository::owns( $stale_next, 'token-matrix-pending-next' ) && false === $stale_released && $token_delete( $stale['post_id'], 'token-matrix-pending-stale', $stale['lock'], $stale_mark ) && $stale_next === TutorPress_Live_Lessons_Store_Fake::$options[ $stale_name ] && $stale_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale['post_id'], '_tutorpress_live_lesson_sync' ), 'pending stale release leaves the replacement lock and the staged envelope' );

$died = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-matrix-pending-died' );
$died_name = 'tutorpress_live_lesson_lock_' . $died['post_id'];
$died_mark = $mark();
$died_held = TutorPress_Live_Lessons_Store_Fake::$options[ $died_name ];
$died_envelope = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died['post_id'], '_tutorpress_live_lesson_sync' );
$died_same = $quiet( $died_mark ) && $died['lock'] === $died_held && $died['envelope'] === $died_envelope && $died_mark[4] === TutorPress_Live_Lessons_Adapter_Fake::$calls;
$died_next = $died_same ? TutorPress_Live_Lesson_Sync_Repository::takeover( $died['post_id'], 'create', 'token-matrix-pending-later', $died['lock'], $expired, $option_update ) : null;
tutorpress_live_lessons_assert( $died_same && is_string( $died_next ) && $died_next === TutorPress_Live_Lessons_Store_Fake::$options[ $died_name ] && TutorPress_Live_Lesson_Sync_Repository::owns( $died_next, 'token-matrix-pending-later' ) && $died_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died['post_id'], '_tutorpress_live_lesson_sync' ) && $quiet( $died_mark ) && $died_mark[4] === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'pending process death keeps the staged envelope until takeover replaces only the lock' );

$copy_done = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$copy_saved = $stage_copy( $copy_done[0], $google_save, array( 'preflight' => array( true ) ), 'token-matrix-unlinked-done' );
$copy_id = $copy_done[0]['ID'];
$copy_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$copy_mark = $mark();
$queue_create();
$copy_final = $dispatch( $copy_saved, 'token-matrix-unlinked-done', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$copy_envelope = is_array( $copy_final ) ? json_decode( $copy_final['envelope'], true ) : null;
$copy_hook = array( 'tutor_google_meet_after_save_meeting', array( (int) $copy_id ) );
tutorpress_live_lessons_assert( is_array( $copy_envelope ) && 4 === $copy_envelope['version'] && 'synced' === $copy_envelope['state'] && $copy_id === $copy_final['post_id'] && $copy_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $token_delete( $copy_id, 'token-matrix-unlinked-done', $copy_saved['lock'], $copy_mark ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $copy_id ] ) && array( $copy_hook ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $copy_mark[1] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $copy_id ]['post_status'], 'unlinked completion deletes the acquired token before the save hook' );

$copy_throw = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$copy_throw_saved = $stage_copy( $copy_throw[0], $google_save, array( 'preflight' => array( true ) ), 'token-matrix-unlinked-throw' );
$copy_throw_id = $copy_throw[0]['ID'];
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$copy_throw_mark = $mark();
$copy_throw_final = $dispatch( $copy_throw_saved, 'token-matrix-unlinked-throw', $throwing );
$copy_throw_envelope = is_array( $copy_throw_final ) ? json_decode( $copy_throw_final['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $copy_throw_envelope ) && 'error' === $copy_throw_envelope['state'] && $copy_throw_saved['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $copy_throw_id ] && $quiet( $copy_throw_mark ) && $copy_throw_mark[4] === TutorPress_Live_Lessons_Adapter_Fake::$calls && false === strpos( (string) $copy_throw_final['envelope'], 'calendar exploded' ), 'an unlinked provider throw adds no delete and leaves the lock' );

$copy_hook_plant = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$copy_hook_saved = $stage_copy( $copy_hook_plant[0], $google_save, array( 'preflight' => array( true ) ), 'token-matrix-unlinked-hook' );
$copy_hook_id = $copy_hook_plant[0]['ID'];
$copy_hook_mark = $mark();
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
$queue_create();
$copy_hook_final = $dispatch( $copy_hook_saved, 'token-matrix-unlinked-hook', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$copy_hook_bytes = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $copy_hook_id, '_tutorpress_live_lesson_sync' );
$copy_hook_state = is_string( $copy_hook_bytes ) ? json_decode( $copy_hook_bytes, true ) : null;
unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] );
tutorpress_live_lessons_assert( $copy_hook_final instanceof WP_Error && 'live_lesson_persisted_transition' === $copy_hook_final->get_error_code() && is_array( $copy_hook_state ) && 4 === $copy_hook_state['version'] && 'synced' === $copy_hook_state['state'] && $token_delete( $copy_hook_id, 'token-matrix-unlinked-hook', $copy_hook_saved['lock'], $copy_hook_mark ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $copy_hook_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $copy_hook_id ]['post_status'] && false === strpos( json_encode( $copy_hook_final->get_error_data() ), 'hook-exploded-secret' ) && false === strpos( (string) $copy_hook_bytes, 'hook-exploded-secret' ), 'an unlinked hook throw follows one token delete and keeps the synced post' );

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
$pass_2d_create = $draft;
$pass_2d_create['provider_id'] = 'evt-evil';
$pass_2d_unlinked = $google_save;
$pass_2d_unlinked['provider_id'] = 'evt-evil';
$pass_2d_copy = $plant_unlinked( 'tutor-google-meet', 'google_meet', 4 );
$pass_2d_rejected = $stage_for( $pass_2d_create, array( 'preflight' => array( true ) ), 'token-2d-create-id' );
$pass_2d_copy_rejected = $stage_copy( $pass_2d_copy[0], $pass_2d_unlinked, array( 'preflight' => array( true ) ), 'token-2d-unlinked-id' );
$pass_2d_staged = $stage_for( $draft, array( 'preflight' => array( true ) ), 'token-2d-early' );
$pass_2d_name = 'tutorpress_live_lesson_lock_' . $pass_2d_staged['post_id'];
$pass_2d_early = TutorPress_Live_Lesson_Sync_Repository::takeover( $pass_2d_staged['post_id'], 'create', 'token-2d-early-next', $pass_2d_staged['lock'], '2026-09-21 16:00:00', $option_update );
$pass_2d_held = TutorPress_Live_Lessons_Store_Fake::$options[ $pass_2d_name ];
$pass_2d_envelope = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_staged['post_id'], '_tutorpress_live_lesson_sync' );
$pass_2d_mark = $mark();
$queue_create();
$pass_2d_saved = $dispatch( $pass_2d_staged, 'token-2d-early', $adapter, null, null, array( 'topic' => $topic, 'course' => $course, 'can' => $allow ) );
$pass_2d_row = is_array( $pass_2d_saved ) ? json_decode( $pass_2d_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( null === $pass_2d_rejected && null === $pass_2d_copy_rejected && $pass_2d_copy[1] === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_copy[0]['ID'], '_tutorpress_live_lesson_sync' ) && null === $pass_2d_early && $pass_2d_staged['lock'] === $pass_2d_held && $pass_2d_staged['envelope'] === $pass_2d_envelope && is_array( $pass_2d_row ) && 'synced' === $pass_2d_row['state'] && $token_delete( $pass_2d_staged['post_id'], 'token-2d-early', $pass_2d_staged['lock'], $pass_2d_mark ) && array( array( 'tutor_google_meet_after_save_meeting', array( (int) $pass_2d_staged['post_id'] ) ) ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $pass_2d_mark[1] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass_2d_name ] ), 'a takeover before expiry stays null, ordinary provider-id input stays rejected, and completion records the save hook after the exact-token delete' );
$GLOBALS['wpdb'] = $saved_wpdb;
tutorpress_live_lessons_pass( 'create and unlinked locks release by exact token' );
