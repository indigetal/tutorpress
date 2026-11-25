<?php

$course  = array( 'ID' => 7, 'post_type' => 'courses', 'post_author' => 42 );
$topic   = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$allow   = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === (int) $id; };
$deny    = static function () { return false; };
$broad   = static function ( $cap ) { return 'edit_posts' === $cap; };
$now     = new DateTimeImmutable( '2026-09-21 16:00:00', new DateTimeZone( 'UTC' ) );
$secret  = 'cred-sentinel';
$creds   = array( 'account_id' => $secret, 'api_key' => $secret, 'api_secret' => $secret );
$adapter = new TutorPress_Live_Lessons_Adapter_Fake();
$google_target = array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 30, 'send_updates' => 'all', 'status' => 'confirmed', 'transparency' => 'transparent', 'visibility' => 'public' );
$google_blank  = array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 30, 'send_updates' => 'all', 'status' => 'confirmed', 'transparency' => 'transparent', 'visibility' => 'public' );
$google_calls  = array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) );
$zoom_target       = array( 'auto_recording' => 'none', 'enforce_login' => false, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => false, 'mute_upon_entry' => false, 'participant_video' => false, 'waiting_room' => false );
$zoom_owner_target = array( 'auto_recording' => 'none', 'enforce_login' => true, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => false, 'mute_upon_entry' => false, 'participant_video' => true, 'waiting_room' => false );
$sync_read     = array( 'TutorPress_Live_Lessons_Store_Fake', 'meta_rows' );
$sync_update   = static function ( $post_id, $key, $meta_id, $value, $prior ) { return TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post_id, $key, $meta_id, $value, $prior ); };
$sync_affected = static function () { return TutorPress_Live_Lessons_Store_Fake::$affected; };
$methods       = static function () {
	$names = array();
	foreach ( TutorPress_Live_Lessons_Adapter_Fake::$calls as $call ) { $names[] = $call[0]; }
	return $names;
};
$meta_keys = static function ( $post_id ) {
	$keys = array();
	foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
		if ( (int) $row['post_id'] === (int) $post_id ) { $keys[] = $row['meta_key']; }
	}
	return $keys;
};
$operation = static function ( $provider, $start, $end, array $duration, array $target ) {
	return array( 'base_version' => 0, 'content' => 'Body', 'course_id' => 7, 'delete_hook' => null, 'dispatch_state' => 'confirmed', 'duration' => $duration, 'end_local' => $end, 'kind' => 'create', 'menu_order' => 4, 'operation_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'owner_id' => 11, 'post_author' => 42, 'provider' => $provider, 'provider_id' => 'evt-stored', 'provider_target' => $target, 'start_local' => $start, 'started_at' => '2026-09-21 12:00:00', 'timezone' => 'America/New_York', 'title' => 'Copied', 'topic_id' => 8 );
};
$envelope = static function ( $provider, $version, $state, $provider_id, $operation ) {
	return array( 'schema_version' => 1, 'version' => $version, 'state' => $state, 'provider' => $provider, 'owner_id' => 11, 'provider_id' => $provider_id, 'provenance' => 'create', 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => $operation );
};
$plant = static function ( $post_type, $bytes, $menu_order = 4 ) {
	$id = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => $post_type, 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => $menu_order, 'post_author' => 42 ) );
	if ( null !== $bytes ) { add_post_meta( $id, '_tutorpress_live_lesson_sync', $bytes ); }
	return array( 'ID' => $id, 'post_type' => $post_type, 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => $menu_order );
};
$stage = static function ( array $item, array $input, array $queue, $token, $can = null, $update = null, $local = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	if ( isset( $item['post_type'] ) && 'tutor-google-meet' === $item['post_type'] ) {
		if ( ! array_key_exists( 'hydrate_item', $queue ) ) {
			$queue['hydrate_item'] = array( array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) );
		}
		if ( ! array_key_exists( 'settings', $queue ) ) {
			$queue['settings'] = array( null );
		}
	}
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::stage_update( $item, $topic, $course, null === $can ? $allow : $can, $input, $adapter, $now, $token, $sync_read, null === $update ? $sync_update : $update, $sync_affected, $local );
};
$google_stored = $envelope( 'google_meet', 3, 'synced', 'evt-stored', $operation( 'google_meet', '2026-09-21 10:00:00', '2026-09-21 11:00:00', array( 'value' => 60, 'unit' => 'minutes' ), $google_target ) );
$zoom_stored   = $envelope( 'zoom', 1, 'synced', 'evt-stored', $operation( 'zoom', '2026-09-21 10:00:00', '2026-09-21 12:00:00', array( 'value' => 2, 'unit' => 'hours' ), $zoom_target ) );
$google_bytes  = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $google_stored );
$zoom_bytes    = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $zoom_stored );
$google_input  = array( 'acting_user_id' => 11, 'host_id' => 'host-ignored', 'credentials' => $creds, 'title' => 'Standup', 'content' => 'Notes', 'settings' => array( 'attendees' => array( array( 'email' => 'student@example.com' ) ), 'password' => 'hidden-pass' ) );
$zoom_input    = array( 'course_author_id' => 42, 'acting_instructor_id' => 9, 'host_id' => 'host-1', 'credentials' => $creds, 'title' => 'Room', 'content' => 'Agenda', 'settings' => array( 'password' => 'hidden-pass' ) );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $google_stored ) ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $zoom_stored ) ) && is_string( $google_bytes ) && is_string( $zoom_bytes ), 'planted synced envelopes are canonical' );

$google_item = $plant( 'tutor-google-meet', $google_bytes );
$google_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$google_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$google_ledger = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$google_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00' );
$google_planted = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_item['ID'], '_tutorpress_live_lesson_sync' );
$google_saved = $stage( $google_item, $google_input, array( 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-stored' ), array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'preflight' => array( true ), 'settings' => array( null ) ), 'token-update-google', null, null, $google_local );
$google_operation = is_array( $google_saved ) && isset( $google_saved['operation'] ) && is_array( $google_saved['operation'] ) ? $google_saved['operation'] : array();
$google_body = json_encode( $google_operation );
tutorpress_live_lessons_assert( is_array( $google_saved ) && array( 'post_id', 'owner_id', 'provider', 'operation' ) === array_keys( $google_saved ) && $google_item['ID'] === $google_saved['post_id'] && 11 === $google_saved['owner_id'] && 'google_meet' === $google_saved['provider'] && $google_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 'Copied' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_title'] && 4 === (int) TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['menu_order'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_status'] && 'update' === $google_operation['kind'] && 0 === $google_operation['base_version'] && 'not_dispatched' === $google_operation['dispatch_state'] && 'evt-stored' === $google_operation['provider_id'] && null === $google_operation['delete_hook'] && 4 === $google_operation['menu_order'] && 'Standup' === $google_operation['title'] && 'Notes' === $google_operation['content'] && '2026-09-21 10:00:00' === $google_operation['start_local'] && '2026-09-21 11:00:00' === $google_operation['end_local'] && 'America/New_York' === $google_operation['timezone'] && array( 'value' => 60, 'unit' => 'minutes' ) === $google_operation['duration'] && $google_blank === $google_operation['provider_target'] && '2026-09-21 16:00:00' === $google_operation['started_at'] && 1 === preg_match( '/\A[0-9a-f]{32}\z/', $google_operation['operation_id'] ) && $google_planted === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_item['ID'] ] ) && array( array( 'hydrate_item', array( 'target' => $google_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $google_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $google_ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && false === strpos( (string) $google_body, $secret ) && false === strpos( (string) $google_body, 'hidden-pass' ) && false === strpos( (string) $google_body, 'student@example.com' ) && false === strpos( (string) $google_body, 'host-ignored' ), 'an unchanged past google schedule stages an in-memory update without validate_host' );

$pass9d_blob = '{"status":"tentative","transparency":"opaque","visibility":"private"}';
$pass9d_item = $plant( 'tutor-google-meet', $google_bytes );
add_post_meta( $pass9d_item['ID'], 'tutor-google-meet-event-details', $pass9d_blob );
$pass9d_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00' );
$pass9d_saved = $stage( $pass9d_item, $google_input, array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-stored' ), array( 'status' => 'tentative', 'transparency' => 'opaque', 'visibility' => 'private', 'reminder_minutes' => 15 ) ), 'settings' => array( array( 'settings' => array( 'send_updates' => array( 'value' => 'none' ) ) ) ) ), 'token-update-stored', null, null, $pass9d_local );
$pass9d_operation = is_array( $pass9d_saved ) && isset( $pass9d_saved['operation'] ) && is_array( $pass9d_saved['operation'] ) ? $pass9d_saved['operation'] : array();
tutorpress_live_lessons_assert( array( 'add_enrolled_students' => 'No', 'reminder_minutes' => 15, 'send_updates' => 'none', 'status' => 'tentative', 'transparency' => 'opaque', 'visibility' => 'private' ) === ( isset( $pass9d_operation['provider_target'] ) ? $pass9d_operation['provider_target'] : null ) && array( array( 'hydrate_item', array( 'target' => $pass9d_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => $pass9d_blob ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a google update keeps the stored status, transparency, visibility, and reminder, and takes send updates from the owner settings' );

$pass10a_stored = $envelope( 'google_meet', 3, 'synced', 'evt-stored', $operation( 'google_meet', '2026-09-21 09:00:00', '2026-09-21 09:30:00', array( 'value' => 30, 'unit' => 'minutes' ), $google_target ) );
$pass10a_bytes  = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $pass10a_stored );
$pass10a_item   = $plant( 'tutor-google-meet', $pass10a_bytes );
$pass10a_local  = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:17', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:17' );
$pass10a_input  = $google_input;
$pass10a_input['start']    = '2026-09-21 10:00:00';
$pass10a_input['end']      = '2026-09-21 11:00:00';
$pass10a_input['timezone'] = 'America/New_York';
$pass10a_saved     = $stage( $pass10a_item, $pass10a_input, array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-meta' ), array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ) ), 'token-update-seconds', null, null, $pass10a_local );
$pass10a_operation = is_array( $pass10a_saved ) && isset( $pass10a_saved['operation'] ) && is_array( $pass10a_saved['operation'] ) ? $pass10a_saved['operation'] : array();
tutorpress_live_lessons_assert( is_array( $pass10a_saved ) && 'evt-meta' === $pass10a_operation['provider_id'] && '2026-09-21 10:00:00' === $pass10a_operation['start_local'] && '2026-09-21 11:00:00' === $pass10a_operation['end_local'] && 'America/New_York' === $pass10a_operation['timezone'] && array( array( 'hydrate_item', array( 'target' => $pass10a_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'an unchanged past google schedule with non-zero stored seconds stages the truncated clocks and the lesson meeting id' );

$pass10b_item  = $plant( 'tutor-google-meet', null );
$pass10b_bare  = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass10b_item['ID'], '_tutorpress_live_lesson_sync' );
$pass10b_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:17', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:17' );
$pass10b_input = $google_input;
$pass10b_input['start']    = '2026-09-21 10:00:00';
$pass10b_input['end']      = '2026-09-21 11:00:00';
$pass10b_input['timezone'] = 'America/New_York';
$pass10b_saved     = $stage( $pass10b_item, $pass10b_input, array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-tutor' ), array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ) ), 'token-update-bare', null, null, $pass10b_local );
$pass10b_operation = is_array( $pass10b_saved ) && isset( $pass10b_saved['operation'] ) && is_array( $pass10b_saved['operation'] ) ? $pass10b_saved['operation'] : array();
tutorpress_live_lessons_assert( null === $pass10b_bare && is_array( $pass10b_saved ) && 'update' === $pass10b_operation['kind'] && 'evt-tutor' === $pass10b_operation['provider_id'] && '2026-09-21 10:00:00' === $pass10b_operation['start_local'] && '2026-09-21 11:00:00' === $pass10b_operation['end_local'] && 'America/New_York' === $pass10b_operation['timezone'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass10b_item['ID'] ] ) && array( array( 'hydrate_item', array( 'target' => $pass10b_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a lesson with no sync row stages an update from its saved schedule and writes no lock' );

$pass10d_item = $plant( 'tutor-google-meet', null );
$pass10d_name = 'tutorpress_live_lesson_lock_' . $pass10d_item['ID'];
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array();
$pass10d_skipped = TutorPress_Live_Lesson_Sync_Orchestrator::stage_unlinked( $pass10d_item, $topic, $course, $allow, $google_input, $adapter, $now, 'token-unlinked-skip', $sync_read, $sync_update, $sync_affected );
$pass10d_quiet  = array() === $methods() && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass10d_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass10d_name ] );
$pass10d_input = $google_input;
$pass10d_input['start']    = '2026-09-21 17:00:00';
$pass10d_input['end']      = '2026-09-21 18:00:00';
$pass10d_input['timezone'] = 'UTC';
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'settings' => array( null ) );
$pass10d_saved     = TutorPress_Live_Lesson_Sync_Orchestrator::stage_unlinked( $pass10d_item, $topic, $course, $allow, $pass10d_input, $adapter, $now, 'token-unlinked-adopt', $sync_read, $sync_update, $sync_affected, false );
$pass10d_decoded   = is_array( $pass10d_saved ) ? json_decode( $pass10d_saved['envelope'], true ) : null;
$pass10d_operation = is_array( $pass10d_decoded ) && isset( $pass10d_decoded['operation'] ) ? $pass10d_decoded['operation'] : array();
$pass10d_lock      = is_array( $pass10d_saved ) ? json_decode( $pass10d_saved['lock'], true ) : null;
tutorpress_live_lessons_assert( null === $pass10d_skipped && true === $pass10d_quiet && is_array( $pass10d_saved ) && is_array( $pass10d_decoded ) && is_array( $pass10d_lock ) && 2 === $pass10d_decoded['version'] && 'pending' === $pass10d_decoded['state'] && null === $pass10d_decoded['provider_id'] && 'create' === $pass10d_operation['kind'] && 1 === $pass10d_operation['base_version'] && null === $pass10d_operation['provider_id'] && '2026-09-21 17:00:00' === $pass10d_operation['start_local'] && '2026-09-21 18:00:00' === $pass10d_operation['end_local'] && 'UTC' === $pass10d_operation['timezone'] && array( 'unit' => 'minutes', 'value' => 60 ) === $pass10d_operation['duration'] && 'create' === $pass10d_lock['operation'] && isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass10d_name ] ) && $pass10d_saved['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ $pass10d_name ] && TutorPress_Live_Lesson_Sync_Repository::owns( $pass10d_saved['lock'], 'token-unlinked-adopt' ) && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'an empty unlinked read stages a pending create and an omitted flag writes nothing' );

$pass11c_failed = $operation( 'google_meet', '2026-09-21 10:00:00', '2026-09-21 11:00:00', array( 'value' => 60, 'unit' => 'minutes' ), $google_target );
$pass11c_failed['provider_id']    = null;
$pass11c_failed['dispatch_state'] = 'dispatch_possible';
$pass11c_stored = $envelope( 'google_meet', 3, 'error', null, $pass11c_failed );
$pass11c_stored['provenance'] = null;
$pass11c_stored['error'] = 'provider_create_failed';
$pass11c_stored['reconciliation_required'] = true;
$pass11c_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $pass11c_stored );
$pass11c_item  = $plant( 'tutor-google-meet', $pass11c_bytes );
$pass11c_name  = 'tutorpress_live_lesson_lock_' . $pass11c_item['ID'];
$pass11c_input = $google_input;
$pass11c_input['start']    = '2026-09-21 17:00:00';
$pass11c_input['end']      = '2026-09-21 18:00:00';
$pass11c_input['timezone'] = 'UTC';
$pass11c_saved     = $stage( $pass11c_item, $pass11c_input, array( 'preflight' => array( true ), 'settings' => array( null ) ), 'token-pass11c-create' );
$pass11c_operation = is_array( $pass11c_saved ) && isset( $pass11c_saved['operation'] ) && is_array( $pass11c_saved['operation'] ) ? $pass11c_saved['operation'] : array();
tutorpress_live_lessons_assert( is_array( $pass11c_saved ) && 'create' === $pass11c_operation['kind'] && null === $pass11c_operation['provider_id'] && '2026-09-21 17:00:00' === $pass11c_operation['start_local'] && '2026-09-21 18:00:00' === $pass11c_operation['end_local'] && 'UTC' === $pass11c_operation['timezone'] && $pass11c_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11c_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass11c_name ] ) && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'an error lesson with no meeting id stages a create' );

$again = $stage( $google_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-again' );
tutorpress_live_lessons_assert( null === $again && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_item['ID'] ] ), 'a second stage with no local schedule returns null after preflight and writes no lock' );

$future_item = $plant( 'tutor-google-meet', $google_bytes );
$future_input = $google_input;
$future_input['start'] = '2026-09-21 15:04:00';
$future_input['end'] = '2026-09-21 16:04:00';
$future_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$future_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00' );
$future_saved = $stage( $future_item, $future_input, array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-stored' ), array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ) ), 'token-update-future', null, null, $future_local );
$future_operation = is_array( $future_saved ) && isset( $future_saved['operation'] ) && is_array( $future_saved['operation'] ) ? $future_saved['operation'] : array();
tutorpress_live_lessons_assert( is_array( $future_saved ) && 'update' === $future_operation['kind'] && 'evt-stored' === $future_operation['provider_id'] && '2026-09-21 15:04:00' === $future_operation['start_local'] && '2026-09-21 16:04:00' === $future_operation['end_local'] && 'America/New_York' === $future_operation['timezone'] && array( 'value' => 60, 'unit' => 'minutes' ) === $future_operation['duration'] && $google_blank === $future_operation['provider_target'] && $future_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $future_item['ID'] ] ) && array( array( 'hydrate_item', array( 'target' => $future_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a changed future google schedule stages the new wall clock' );

$zoom_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$zoom_local = array( '_tutor_zm_start_datetime' => '2026-09-21 10:00:00', '_tutor_zm_duration' => 2, '_tutor_zm_duration_unit' => 'hours' );
$zoom_saved = $stage( $zoom_item, $zoom_input, array( 'preflight' => array( true ), 'validate_host' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'timezone' => 'America/New_York', 'duration' => array( 'value' => 2, 'unit' => 'hours' ), 'provider_id' => 'evt-stored' ) ) ), 'token-update-zoom', null, null, $zoom_local );
$zoom_operation = is_array( $zoom_saved ) && isset( $zoom_saved['operation'] ) && is_array( $zoom_saved['operation'] ) ? $zoom_saved['operation'] : array();
$zoom_body = json_encode( $zoom_operation );
tutorpress_live_lessons_assert( is_array( $zoom_saved ) && 'update' === $zoom_operation['kind'] && 'evt-stored' === $zoom_operation['provider_id'] && array( 'value' => 2, 'unit' => 'hours' ) === $zoom_operation['duration'] && '2026-09-21 10:00:00' === $zoom_operation['start_local'] && '2026-09-21 12:00:00' === $zoom_operation['end_local'] && 'America/New_York' === $zoom_operation['timezone'] && $zoom_owner_target === $zoom_operation['provider_target'] && $zoom_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_item['ID'], '_tutorpress_live_lesson_sync' ) && $zoom_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_item['ID'] ] ) && array( array( 'hydrate_item', array( 'target' => $zoom_local ) ), array( 'preflight', array( 'owner_id' => 42 ) ), array( 'validate_host', array( 'owner_id' => 42, 'host_id' => 'host-1' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && false === strpos( (string) $zoom_body, $secret ) && false === strpos( (string) $zoom_body, 'hidden-pass' ), 'an unchanged past zoom schedule keeps the typed hour duration' );

$zoom_future = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_change = $zoom_input;
$zoom_change['start'] = '2026-09-21 15:04:00';
$zoom_change['duration'] = array( 'value' => 3, 'unit' => 'hours' );
$zoom_change_local = array( '_tutor_zm_start_datetime' => '2026-09-21 10:00:00' );
$zoom_changed = $stage( $zoom_future, $zoom_change, array( 'preflight' => array( true ), 'validate_host' => array( true ), 'hydrate_item' => array( array( 'timezone' => 'America/New_York', 'provider_id' => 'evt-stored' ) ) ), 'token-update-zoom-future', null, null, $zoom_change_local );
$zoom_changed_operation = is_array( $zoom_changed ) && isset( $zoom_changed['operation'] ) && is_array( $zoom_changed['operation'] ) ? $zoom_changed['operation'] : array();
tutorpress_live_lessons_assert( is_array( $zoom_changed ) && 'update' === $zoom_changed_operation['kind'] && 'evt-stored' === $zoom_changed_operation['provider_id'] && array( 'value' => 3, 'unit' => 'hours' ) === $zoom_changed_operation['duration'] && '2026-09-21 15:04:00' === $zoom_changed_operation['start_local'] && '2026-09-21 18:04:00' === $zoom_changed_operation['end_local'] && 'America/New_York' === $zoom_changed_operation['timezone'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_future['ID'] ] ) && array( array( 'hydrate_item', array( 'target' => $zoom_change_local ) ), array( 'preflight', array( 'owner_id' => 42 ) ), array( 'validate_host', array( 'owner_id' => 42, 'host_id' => 'host-1' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a changed zoom schedule keeps the typed hour unit' );

$seen = 0;
$spy = static function () use ( &$seen ) { $seen++; return true; };
$with_id = $google_input;
$with_id['provider_id'] = 'evt-evil';
$with_key = $google_input;
$with_key['id'] = 'evt-body';
$identity_item = $plant( 'tutor-google-meet', $google_bytes );
$seen = 0;
$evil = $stage( $identity_item, $with_id, array( 'preflight' => array( true ) ), 'token-update-evil', $spy );
$evil_methods = $methods();
$key = $stage( $identity_item, $with_key, array( 'preflight' => array( true ) ), 'token-update-key', $spy );
tutorpress_live_lessons_assert( null === $evil && null === $key && array() === $evil_methods && array() === $methods() && 0 === $seen && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $identity_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $identity_item['ID'] ] ), 'a payload provider id returns null before authorize and preflight' );

$auth_item = $plant( 'tutor-google-meet', $google_bytes );
$bad_parent = $auth_item;
$bad_parent['post_parent'] = 9;
$auth_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
tutorpress_live_lessons_assert( null === $stage( $auth_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-broad', $broad ) && null === $stage( $auth_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-deny', $deny ) && null === $stage( $bad_parent, $google_input, array( 'preflight' => array( true ) ), 'token-update-parent', $allow ) && array() === $methods() && $auth_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $auth_item['ID'] ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $auth_item['ID'], '_tutorpress_live_lesson_sync' ), 'broad edit_posts acquires no update lock' );

$reject = static function ( $bytes, $post_type = 'tutor-google-meet' ) use ( $plant, $stage, $google_input, $methods ) {
	$item = $plant( $post_type, $bytes );
	$saved = $stage( $item, $google_input, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-update-reject' );
	return array( $saved, $item['ID'], TutorPress_Live_Lessons_Store_Fake::meta_bytes( $item['ID'], '_tutorpress_live_lesson_sync' ), isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $item['ID'] ] ), $methods() );
};
$pending_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $envelope( 'google_meet', 3, 'pending', 'evt-stored', $google_stored['operation'] ) );
$unlinked_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $envelope( 'google_meet', 1, 'unlinked', null, null ) );
$error_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $envelope( 'google_meet', 3, 'error', 'evt-stored', $google_stored['operation'] ) );
$null_operation = $envelope( 'google_meet', 3, 'synced', 'evt-stored', null );
$null_operation_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $null_operation );
$null_identity = $google_stored;
$null_identity['provider_id'] = null;
$null_identity_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $null_identity );
$mismatch_bytes = json_encode( array( 'state' => 'synced', 'version' => 3, 'provider' => 'zoom', 'provider_id' => 'evt-stored', 'operation' => array( 'start_local' => '2026-09-21 10:00:00' ) ) );
$version_zero = json_encode( array( 'state' => 'synced', 'version' => 0, 'provider' => 'google_meet', 'provider_id' => 'evt-stored', 'operation' => array() ) );
$version_text = json_encode( array( 'state' => 'synced', 'version' => '3', 'provider' => 'google_meet', 'provider_id' => 'evt-stored', 'operation' => array() ) );
list( $pending_saved, $pending_id, $pending_kept, $pending_locked, $pending_methods ) = $reject( $pending_bytes );
list( $unlinked_saved, $unlinked_id, $unlinked_kept, $unlinked_locked, $unlinked_methods ) = $reject( $unlinked_bytes );
list( $null_saved, $null_id, $null_kept, $null_locked, $null_methods ) = $reject( $null_operation_bytes );
list( $identity_saved, $empty_id, $empty_kept, $empty_locked, $identity_methods ) = $reject( $null_identity_bytes );
list( $mismatch_saved, $mismatch_id, $mismatch_kept, $mismatch_locked, $mismatch_methods ) = $reject( $mismatch_bytes );
list( $zero_saved, $zero_id, $zero_kept, $zero_locked, $zero_methods ) = $reject( $version_zero );
list( $text_saved, $text_id, $text_kept, $text_locked, $text_methods ) = $reject( $version_text );
$absent = $plant( 'tutor-google-meet', null );
$absent_saved = $stage( $absent, $google_input, array( 'preflight' => array( true ) ), 'token-update-absent' );
$second = $plant( 'tutor-google-meet', $google_bytes );
add_post_meta( $second['ID'], '_tutorpress_live_lesson_sync', $google_bytes );
$second_saved = $stage( $second, $google_input, array( 'preflight' => array( true ) ), 'token-update-second' );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $null_operation ) ) && null === $pending_saved && null === $unlinked_saved && null === $null_saved && null === $identity_saved && null === $mismatch_saved && null === $zero_saved && null === $text_saved && null === $absent_saved && null === $second_saved && false === $pending_locked && false === $unlinked_locked && false === $null_locked && false === $empty_locked && false === $mismatch_locked && false === $zero_locked && false === $text_locked && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $absent['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $second['ID'] ] ) && $pending_bytes === $pending_kept && $null_operation_bytes === $null_kept && $null_identity_bytes === $empty_kept && 2 === count( TutorPress_Live_Lessons_Store_Fake::meta_rows( $second['ID'], '_tutorpress_live_lesson_sync' ) ) && array( 'preflight' ) === $pending_methods && array( 'preflight' ) === $unlinked_methods && array( 'preflight' ) === $null_methods && array( 'preflight' ) === $identity_methods && array( 'preflight' ) === $mismatch_methods && array( 'preflight' ) === $zero_methods && array( 'preflight' ) === $text_methods && array( 'preflight' ) === $methods(), 'pending, unlinked, a null operation, a missing identity, and a second row acquire no lock' );
$error_item = $plant( 'tutor-google-meet', $error_bytes );
$error_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00' );
$error_saved = $stage( $error_item, $google_input, array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-stored' ), array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ) ), 'token-update-reject', null, null, $error_local );
$error_operation = is_array( $error_saved ) && isset( $error_saved['operation'] ) && is_array( $error_saved['operation'] ) ? $error_saved['operation'] : array();
tutorpress_live_lessons_assert( is_array( $error_saved ) && 'update' === $error_operation['kind'] && 'evt-stored' === $error_operation['provider_id'] && '2026-09-21 10:00:00' === $error_operation['start_local'] && '2026-09-21 11:00:00' === $error_operation['end_local'] && 'America/New_York' === $error_operation['timezone'] && array( 'value' => 60, 'unit' => 'minutes' ) === $error_operation['duration'] && $google_blank === $error_operation['provider_target'] && $error_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $error_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $error_item['ID'] ] ) && array( array( 'hydrate_item', array( 'target' => $error_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'an error row with a meeting id stages an in-memory update and writes no lock' );

$pass11b3_item  = $plant( 'tutor-google-meet', $pending_bytes );
$pass11b3_name  = 'tutorpress_live_lesson_lock_' . $pass11b3_item['ID'];
$pass11b3_prior = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( array( 'expiry' => '2026-09-21 16:00:00', 'operation' => 'create', 'token' => 'token-pass11b3-old' ) );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass11b3_name ] = $pass11b3_prior;
$pass11b3_from  = count( $GLOBALS['wpdb']->statements );
$pass11b3_saved = $stage( $pass11b3_item, $google_input, array( 'preflight' => array( true ) ), 'token-pass11b3-update' );
$pass11b3_sql = array_slice( $GLOBALS['wpdb']->statements, $pass11b3_from );
tutorpress_live_lessons_assert( null === $pass11b3_saved && array() === $pass11b3_sql && $pass11b3_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $pass11b3_name ] && $pending_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11b3_item['ID'], '_tutorpress_live_lesson_sync' ) && array( 'preflight' ) === $methods(), 'a planted lock stays and the stage returns null after preflight' );
$pass11b3_releases = count( TutorPress_Topic_Copy_Harness::$releases );
$pass11b3_stale    = TutorPress_Live_Lesson_Sync_Repository::compare_option_update( $pass11b3_name, 'stale-bytes', 'not-the-planted-lock' );
tutorpress_live_lessons_assert( false === $pass11b3_stale && 0 === (int) $GLOBALS['wpdb']->rows_affected && $pass11b3_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $pass11b3_name ] && $pass11b3_releases === count( TutorPress_Topic_Copy_Harness::$releases ), 'a stale lock prior leaves the option and the release ledger unchanged' );

$past_item = $plant( 'tutor-google-meet', $google_bytes );
$past_input = $google_input;
$past_input['start'] = '2026-09-21 09:00:00';
$past_input['end'] = '2026-09-21 10:00:00';
$past_saved = $stage( $past_item, $past_input, array( 'preflight' => array( true ) ), 'token-update-past' );
$same_end = $google_input;
$same_end['start'] = '2026-09-21 15:04:00';
$same_end['end'] = '2026-09-21 15:04:00';
$same_item = $plant( 'tutor-google-meet', $google_bytes );
$same_saved = $stage( $same_item, $same_end, array( 'preflight' => array( true ) ), 'token-update-same-end' );
$gap_input = $google_input;
$gap_input['start'] = '2026-03-08 02:30:00';
$gap_input['end'] = '2026-03-08 03:30:00';
$gap_item = $plant( 'tutor-google-meet', $google_bytes );
$gap_saved = $stage( $gap_item, $gap_input, array( 'preflight' => array( true ) ), 'token-update-gap' );
$order_item = $plant( 'tutor-google-meet', $google_bytes );
unset( $order_item['menu_order'] );
$order_saved = $stage( $order_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-order' );
$long_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$long_input = $zoom_input;
$long_input['start'] = '2026-09-21 15:04:00';
$long_input['duration'] = array( 'value' => 9, 'unit' => 'hours' );
$long_saved = $stage( $long_item, $long_input, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-update-long' );
$host_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$host_saved = $stage( $host_item, $zoom_input, array( 'preflight' => array( true ), 'validate_host' => array( false ) ), 'token-update-host' );
$preflight_item = $plant( 'tutor-google-meet', $google_bytes );
$preflight_saved = $stage( $preflight_item, $google_input, array( 'preflight' => array( false ) ), 'token-update-preflight' );
tutorpress_live_lessons_assert( null === $past_saved && null === $same_saved && null === $gap_saved && null === $order_saved && null === $long_saved && null === $host_saved && null === $preflight_saved && array( 'preflight' ) === $methods() && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $past_item['ID'], '_tutorpress_live_lesson_sync' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $same_item['ID'], '_tutorpress_live_lesson_sync' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $order_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $past_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $same_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $gap_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $order_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $long_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $host_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $preflight_item['ID'] ] ), 'a changed past schedule, a google end that is not later, and a failed host check do not lock' );

$acquire_item = $plant( 'tutor-google-meet', $google_bytes );
$acquire_from = count( $GLOBALS['wpdb']->statements );
$acquire_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$acquire_saved = $stage( $acquire_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-acquire' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $acquire_saved && ! ( $acquire_saved instanceof WP_Error ) && $acquire_from === count( $GLOBALS['wpdb']->statements ) && $acquire_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $acquire_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $acquire_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $acquire_item['ID'] ] ) && array( 'preflight' ) === $methods(), 'a failed update acquire leaves the synced post without a release' );

$swap_item = $plant( 'tutor-google-meet', $google_bytes );
$swap_from = count( $GLOBALS['wpdb']->statements );
$swap_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$swap_saved = $stage( $swap_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-swap', null, static function () { return false; } );
$swap_statements = array_slice( $GLOBALS['wpdb']->statements, $swap_from );
tutorpress_live_lessons_assert( null === $swap_saved && ! ( $swap_saved instanceof WP_Error ) && array() === $swap_statements && $swap_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $swap_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $swap_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $swap_item['ID'] ] ) && array( 'preflight' ) === $methods(), 'a failed update callable is not used and the stage returns null after preflight' );
tutorpress_live_lessons_pass( 'synced update stages a pending snapshot' );

if ( ! function_exists( 'wp_update_post' ) ) {
	function wp_update_post( $postarr, $wp_error = false ) {
		unset( $wp_error );
		$id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		if ( $id < 1 || ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $id ] ) ) {
			return 0;
		}
		if ( isset( $postarr['post_title'] ) && is_string( $postarr['post_title'] ) ) {
			TutorPress_Live_Lessons_Store_Fake::$posts[ $id ]['post_title'] = $postarr['post_title'];
		}
		if ( isset( $postarr['post_content'] ) && is_string( $postarr['post_content'] ) ) {
			TutorPress_Live_Lessons_Store_Fake::$posts[ $id ]['post_content'] = $postarr['post_content'];
		}
		return $id;
	}
}

$sync_write = static function ( $post_id, $key, $value ) { return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, $value ); };
$dispatch_update = static function ( array $staged, $token, array $queue, $update = null, $write = null, $adapter_arg = null ) use ( $adapter, $sync_read, $sync_update, $sync_affected, $sync_write ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::dispatch_update( $staged, $token, null === $adapter_arg ? $adapter : $adapter_arg, $sync_read, null === $update ? $sync_update : $update, $sync_affected, null === $write ? $sync_write : $write );
};
$update_result = static function ( $outcome, $provider_id = 'evt-stored', $item = null ) {
	return array( 'outcome' => $outcome, 'provider_id' => $provider_id, 'item' => $item, 'diagnostics' => array(), 'provenance' => 'update' );
};
$confirmed_item = array( 'start_at' => '2026-09-21T14:00:00-04:00', 'end_at' => '2026-09-21T15:00:00-04:00', 'timezone' => 'America/New_York', 'duration_minutes' => 60, 'join_url' => 'https://meet.example/a', 'details' => array() );
$google_update_map = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00', 'tutor-google-meet-event-details' => json_encode( array( 'id' => 'evt-stored' ) ), 'tutor-google-meet-link' => 'https://meet.example/a' );
$zoom_update_map = array( '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 10:00:00', '_tutor_zm_duration' => 2, '_tutor_zm_duration_unit' => 'hr', '_tutor_zm_for_course' => 7, '_tutor_zm_for_topic' => 8, '_tutor_zm_data' => json_encode( array( 'id' => 'evt-stored', 'timezone' => 'America/New_York', 'host_id' => 'host-1' ) ) );
$durable = static function ( array $operation ) {
	return array( 'title' => $operation['title'], 'content' => $operation['content'], 'start_local' => $operation['start_local'], 'end_local' => $operation['end_local'], 'timezone' => $operation['timezone'], 'duration' => $operation['duration'], 'provider_target' => $operation['provider_target'], 'provider_id' => $operation['provider_id'], 'owner_id' => $operation['owner_id'] );
};

$google_dispatch_item = $plant( 'tutor-google-meet', $google_bytes );
$google_dispatch_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$google_dispatch_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00' );
$google_dispatch_staged = $stage( $google_dispatch_item, $google_input, array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-stored' ), array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ) ), 'token-dispatch-update-google', null, null, $google_dispatch_local );
$google_spy = new class( $google_dispatch_item['ID'] ) extends TutorPress_Live_Lessons_Adapter_Fake {
	public $seen = null;
	private $post_id;
	public function __construct( $post_id ) { $this->post_id = (int) $post_id; }
	public function update( $owner_id, $provider_id, array $target ) {
		$this->seen = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $this->post_id, '_tutorpress_live_lesson_sync' ), true );
		return parent::update( $owner_id, $provider_id, $target );
	}
};
$google_dispatch_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$google_dispatch_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$google_dispatch_statements = count( $GLOBALS['wpdb']->statements );
$google_dispatched = $dispatch_update( $google_dispatch_staged, 'token-dispatch-update-google', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $google_update_map ) ), null, null, $google_spy );
$google_final_operation = is_array( $google_dispatched ) && isset( $google_dispatched['operation'] ) && is_array( $google_dispatched['operation'] ) ? $google_dispatched['operation'] : array();
$google_update_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] : array();
$google_compare_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['target'] : array();
tutorpress_live_lessons_assert( json_decode( $google_bytes, true ) === $google_spy->seen && is_array( $google_dispatched ) && 'evt-stored' === $google_dispatched['provider_id'] && 'Standup' === $google_final_operation['title'] && 'evt-stored' === $google_final_operation['provider_id'] && array( 'update', array( 'owner_id' => 11, 'provider_id' => 'evt-stored', 'target' => $google_update_target ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls[0] && $google_update_target === $google_compare_target && 'evt-stored' === $google_update_target['provider_id'] && '2026-09-21 10:00:00' === $google_update_target['start_local'] && 'No' === $google_update_target['add_enrolled_students'] && array( 'update', 'compare_target', 'metadata_map' ) === $methods() && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_dispatch_item['ID'], 'tutor-google-meet-link' ) && json_encode( array( 'id' => 'evt-stored' ) ) === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_dispatch_item['ID'], 'tutor-google-meet-event-details' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_dispatch_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_dispatch_item['ID'] ] ) && $google_dispatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $google_dispatch_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && $google_dispatch_statements === count( $GLOBALS['wpdb']->statements ) && $google_dispatch_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 'Standup' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_dispatch_item['ID'] ]['post_title'] && 'Notes' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_dispatch_item['ID'] ]['post_content'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_dispatch_item['ID'] ]['post_status'] && false === strpos( json_encode( $google_final_operation ), $secret ), 'confirmed google update compares the normalized result, re-reads the map, and does not fire a save hook' );

$zoom_dispatch_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_pass_item = $confirmed_item;
$zoom_pass_item['details'] = array( 'password' => 'hidden-pass' );
$zoom_dispatch_local = array( '_tutor_zm_start_datetime' => '2026-09-21 10:00:00', '_tutor_zm_duration' => 2, '_tutor_zm_duration_unit' => 'hours' );
$zoom_dispatch_hydrated = array( 'start_local' => '2026-09-21 10:00:00', 'timezone' => 'America/New_York', 'duration' => array( 'value' => 2, 'unit' => 'hours' ), 'provider_id' => 'evt-stored' );
$zoom_dispatch_staged = $stage( $zoom_dispatch_item, $zoom_input, array( 'preflight' => array( true ), 'validate_host' => array( true ), 'hydrate_item' => array( $zoom_dispatch_hydrated ) ), 'token-dispatch-update-zoom', null, null, $zoom_dispatch_local );
$zoom_dispatch_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_dispatched = $dispatch_update( $zoom_dispatch_staged, 'token-dispatch-update-zoom', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $zoom_pass_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $zoom_update_map ) ) );
$zoom_update_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] : array();
tutorpress_live_lessons_assert( is_array( $zoom_dispatched ) && 'evt-stored' === $zoom_dispatched['provider_id'] && array( 'update', array( 'owner_id' => 42, 'provider_id' => 'evt-stored', 'target' => $zoom_update_target ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls[0] && 'evt-stored' === $zoom_update_target['provider_id'] && false === array_key_exists( 'host_id', $zoom_update_target ) && 'host-1' === $zoom_update_target['provider_target']['host_id'] && array( 'update', 'compare_target', 'metadata_map' ) === $methods() && json_encode( array( 'id' => 'evt-stored', 'timezone' => 'America/New_York', 'host_id' => 'host-1' ) ) === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_dispatch_item['ID'], '_tutor_zm_data' ) && $zoom_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_dispatch_item['ID'], '_tutorpress_live_lesson_sync' ) && $zoom_dispatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( $zoom_bytes, 'hidden-pass' ), 'confirmed zoom update keeps the nested host and re-reads the seven-key map' );
$pass_input = $zoom_input;
$pass_input['password'] = 'meeting-pass';
$pass_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$pass_staged = $stage( $pass_item, $pass_input, array( 'preflight' => array( true ), 'validate_host' => array( true ), 'hydrate_item' => array( $zoom_dispatch_hydrated ) ), 'token-update-zoom-pass', null, null, $zoom_dispatch_local );
$pass_operation = is_array( $pass_staged ) && isset( $pass_staged['operation'] ) && is_array( $pass_staged['operation'] ) ? $pass_staged['operation'] : array();
$pass_bytes = json_encode( $pass_operation );
$pass_spy = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public $seen = null;
	public function update( $owner_id, $provider_id, array $target ) {
		$this->seen = $target;
		return parent::update( $owner_id, $provider_id, $target );
	}
};
$pass_dispatched = $dispatch_update( $pass_staged, 'token-update-zoom-pass', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $zoom_pass_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $zoom_update_map ) ), null, null, $pass_spy );
tutorpress_live_lessons_assert( is_array( $pass_staged ) && is_array( $pass_dispatched ) && 'meeting-pass' === $pass_staged['password'] && 'evt-stored' === $pass_dispatched['provider_id'] && $zoom_owner_target === $pass_operation['provider_target'] && false === strpos( (string) $pass_bytes, 'meeting-pass' ) && false === strpos( (string) $pass_bytes, '"password"' ) && is_array( $pass_spy->seen ) && 'meeting-pass' === $pass_spy->seen['provider_target']['password'] && $zoom_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_item['ID'], '_tutorpress_live_lesson_sync' ), 'a zoom update passes the typed password and the operation has no password key' );
$blank_input = $zoom_input;
$blank_input['password'] = '';
$blank_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$blank_staged = $stage( $blank_item, $blank_input, array( 'preflight' => array( true ), 'validate_host' => array( true ), 'hydrate_item' => array( $zoom_dispatch_hydrated ) ), 'token-update-zoom-blank', null, null, $zoom_dispatch_local );
$blank_before = is_array( $blank_staged ) ? (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $blank_item['ID'], '_tutorpress_live_lesson_sync' ) : '';
$blank_done = $dispatch_update( $blank_staged, 'token-update-zoom-blank', array( 'update' => array( $update_result( 'confirmed', 'evt-stored' ) ) ) );
tutorpress_live_lessons_assert( is_array( $blank_staged ) && '' === $blank_staged['password'] && null === $blank_done && array() === $methods() && $blank_before === (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $blank_item['ID'], '_tutorpress_live_lesson_sync' ), 'an empty staged zoom password does not call the adapter' );

$google_dispatch_queue = array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-stored' ), array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ) );
$uncertain_item = $plant( 'tutor-google-meet', $google_bytes );
$uncertain_staged = $stage( $uncertain_item, $google_input, $google_dispatch_queue, 'token-dispatch-uncertain', null, null, $google_dispatch_local );
$uncertain_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$uncertain_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$uncertain_done = $dispatch_update( $uncertain_staged, 'token-dispatch-uncertain', array( 'update' => array( $update_result( 'uncertain', 'evt-replaced' ) ) ) );
tutorpress_live_lessons_assert( is_array( $uncertain_staged ) && null === $uncertain_done && 'evt-stored' === $uncertain_staged['operation']['provider_id'] && array( 'update' ) === $methods() && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $uncertain_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $uncertain_item['ID'] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $uncertain_item['ID'] ] ) && $uncertain_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $uncertain_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && false === strpos( $google_bytes, 'evt-replaced' ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $uncertain_item['ID'] ]['post_status'], 'an uncertain update returns null and leaves the planted sync row' );

$miss_item = $plant( 'tutor-google-meet', $google_bytes );
$miss_staged = $stage( $miss_item, $google_input, $google_dispatch_queue, 'token-dispatch-miss', null, null, $google_dispatch_local );
$miss_done = $dispatch_update( $miss_staged, 'token-dispatch-miss', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => false ) ) ) );
$other_item = $plant( 'tutor-google-meet', $google_bytes );
$other_staged = $stage( $other_item, $google_input, $google_dispatch_queue, 'token-dispatch-other', null, null, $google_dispatch_local );
$other_done = $dispatch_update( $other_staged, 'token-dispatch-other', array( 'update' => array( $update_result( 'confirmed', 'evt-other', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ) ) );
$raw_item = $plant( 'tutor-google-meet', $google_bytes );
$raw_staged = $stage( $raw_item, $google_input, $google_dispatch_queue, 'token-dispatch-raw', null, null, $google_dispatch_local );
$raw_result = $update_result( 'confirmed', 'evt-stored', $confirmed_item );
$raw_result['extra'] = 'raw-update-body';
$raw_done = $dispatch_update( $raw_staged, 'token-dispatch-raw', array( 'update' => array( $raw_result ) ) );
tutorpress_live_lessons_assert( null === $miss_done && null === $other_done && null === $raw_done && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $miss_item['ID'], '_tutorpress_live_lesson_sync' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $other_item['ID'], '_tutorpress_live_lesson_sync' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $raw_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $miss_item['ID'] ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $other_item['ID'] ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $raw_item['ID'] ) && false === strpos( $google_bytes, 'evt-other' ) && false === strpos( $google_bytes, 'raw-update-body' ) && array( 'update' ) === $methods(), 'a compare miss, a different identity, and an extra-key result do not write metadata' );

$rejected_result = $update_result( 'definite_rejection' );
$rejected_result['diagnostics'] = array( 'error' => 'rejected-body' );
$rejected_item = $plant( 'tutor-google-meet', $google_bytes );
$rejected_staged = $stage( $rejected_item, $google_input, $google_dispatch_queue, 'token-dispatch-rejected', null, null, $google_dispatch_local );
$rejected_done = $dispatch_update( $rejected_staged, 'token-dispatch-rejected', array( 'update' => array( $rejected_result ) ) );
$pre_item = $plant( 'tutor-google-meet', $google_bytes );
$pre_staged = $stage( $pre_item, $google_input, $google_dispatch_queue, 'token-dispatch-pre', null, null, $google_dispatch_local );
$pre_done = $dispatch_update( $pre_staged, 'token-dispatch-pre', array( 'update' => array( $update_result( 'pre_dispatch_failure' ) ) ) );
$missing_item = $plant( 'tutor-google-meet', $google_bytes );
$missing_staged = $stage( $missing_item, $google_input, $google_dispatch_queue, 'token-dispatch-missing', null, null, $google_dispatch_local );
$missing_done = $dispatch_update( $missing_staged, 'token-dispatch-missing', array( 'update' => array( $update_result( 'known_id_not_found' ) ) ) );
tutorpress_live_lessons_assert( null === $rejected_done && null === $pre_done && null === $missing_done && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $rejected_item['ID'], '_tutorpress_live_lesson_sync' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pre_item['ID'], '_tutorpress_live_lesson_sync' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $missing_item['ID'], '_tutorpress_live_lesson_sync' ) && false === strpos( $google_bytes, 'rejected-body' ) && 'evt-stored' === $missing_staged['operation']['provider_id'] && array( 'update' ) === $methods() && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $rejected_item['ID'] ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $missing_item['ID'] ), 'definite rejection and pre-dispatch failure return null, and a missing id keeps the stored meeting id' );

$throw_item = $plant( 'tutor-google-meet', $google_bytes );
$throw_staged = $stage( $throw_item, $google_input, $google_dispatch_queue, 'token-dispatch-throw', null, null, $google_dispatch_local );
$throwing = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public function update( $owner_id, $provider_id, array $target ) {
		throw new RuntimeException( 'update-boom-secret' );
	}
};
$throw_done = $dispatch_update( $throw_staged, 'token-dispatch-throw', array(), null, null, $throwing );
$throw_methods = $methods();
$swap_dispatch_item = $plant( 'tutor-google-meet', $google_bytes );
$swap_dispatch_staged = $stage( $swap_dispatch_item, $google_input, $google_dispatch_queue, 'token-dispatch-swap', null, null, $google_dispatch_local );
$swap_dispatch_done = $dispatch_update( $swap_dispatch_staged, 'token-dispatch-swap', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $google_update_map ) ), null, static function () { return false; } );
$swap_methods = $methods();
$owner_guard_item = $plant( 'tutor-google-meet', $google_bytes );
$owner_guard_staged = $stage( $owner_guard_item, $google_input, $google_dispatch_queue, 'token-dispatch-owner', null, null, $google_dispatch_local );
$owner_guard_staged['owner_id'] = 99;
$owner_guard_done = $dispatch_update( $owner_guard_staged, 'token-dispatch-owner', array( 'update' => array( $update_result( 'uncertain' ) ) ) );
$owner_methods = $methods();
tutorpress_live_lessons_assert( is_array( $throw_staged ) && 'evt-stored' === $throw_staged['operation']['provider_id'] && null === $throw_done && array() === $throw_methods && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $throw_item['ID'], '_tutorpress_live_lesson_sync' ) && false === strpos( $google_bytes, 'update-boom-secret' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $throw_item['ID'] ] ) && null === $swap_dispatch_done && array( 'update', 'compare_target', 'metadata_map' ) === $swap_methods && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $swap_dispatch_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $swap_dispatch_item['ID'] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $swap_dispatch_item['ID'] ] ) && null === $owner_guard_done && 99 === (int) $owner_guard_staged['owner_id'] && 11 === (int) $owner_guard_staged['operation']['owner_id'] && array() === $owner_methods && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_guard_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $owner_guard_item['ID'] ] ), 'a thrown update, a failed meta write, and a staged-owner mismatch return null' );
tutorpress_live_lessons_pass( 'update dispatch finalizes confirmed metadata' );

$publish_row = static function ( $post_id ) {
	$post = TutorPress_Live_Lessons_Store_Fake::$posts[ $post_id ];
	return array( 'post_title' => $post['post_title'], 'post_content' => $post['post_content'], 'post_status' => $post['post_status'], 'menu_order' => $post['menu_order'] );
};
$planted_row = array( 'post_title' => 'Copied', 'post_content' => 'Body', 'post_status' => 'publish', 'menu_order' => 4 );
$option_update = array( 'TutorPress_Live_Lessons_Store_Fake', 'update_option_bytes' );
$google_stage_calls = array( array( 'hydrate_item', array( 'target' => $google_dispatch_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) );

$died_item = $plant( 'tutor-google-meet', $google_bytes );
$died_staged = $stage( $died_item, $google_input, $google_dispatch_queue, 'token-update-died', null, null, $google_dispatch_local );
$died_id = $died_item['ID'];
$died_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$died_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$died_statements = count( $GLOBALS['wpdb']->statements );
tutorpress_live_lessons_assert( is_array( $died_staged ) && 'evt-stored' === $died_staged['operation']['provider_id'] && $google_stage_calls === $died_calls && $died_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_id, '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $died_id ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $died_id ] ) && $died_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $died_statements === count( $GLOBALS['wpdb']->statements ) && $planted_row === $publish_row( $died_id ), 'an interrupted update stage writes no lock and leaves the planted sync row' );

$host_item = $plant( 'tutor-google-meet', $google_bytes );
$host_staged = $stage( $host_item, $google_input, $google_dispatch_queue, 'token-update-host', null, null, $google_dispatch_local );
$host_id = $host_item['ID'];
$host_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$host_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$host_statements = count( $GLOBALS['wpdb']->statements );
tutorpress_live_lessons_assert( is_array( $host_staged ) && 'evt-stored' === $host_staged['operation']['provider_id'] && $google_stage_calls === $host_calls && $host_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $host_id, '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $host_id ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $host_id ] ) && $host_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $host_statements === count( $GLOBALS['wpdb']->statements ) && $planted_row === $publish_row( $host_id ), 'a host stage writes no lock and leaves the planted sync row' );

$stale_item = $plant( 'tutor-google-meet', $google_bytes );
$stale_staged = $stage( $stale_item, $google_input, $google_dispatch_queue, 'token-update-stale', null, null, $google_dispatch_local );
$stale_id = $stale_item['ID'];
$stale_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$stale_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$stale_operation_id = json_decode( $google_bytes, true )['operation']['operation_id'];
$stale_statements = count( $GLOBALS['wpdb']->statements );
$stale_spy = new class( $stale_id, $stale_operation_id ) extends TutorPress_Live_Lessons_Adapter_Fake {
	public $seen = null;
	public $replacement = null;
	private $post_id;
	private $operation_id;
	public function __construct( $post_id, $operation_id ) {
		$this->post_id = (int) $post_id;
		$this->operation_id = $operation_id;
	}
	public function update( $owner_id, $provider_id, array $target ) {
		$post = TutorPress_Live_Lessons_Store_Fake::$posts[ $this->post_id ];
		$rows = TutorPress_Live_Lessons_Store_Fake::meta_rows( $this->post_id, '_tutorpress_live_lesson_sync' );
		$keys = array();
		foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
			if ( (int) $row['post_id'] === $this->post_id ) { $keys[] = $row['meta_key']; }
		}
		$decoded = json_decode( (string) $rows[0]['meta_value'], true );
		$this->seen = array( 'post_title' => $post['post_title'], 'post_content' => $post['post_content'], 'post_status' => $post['post_status'], 'menu_order' => $post['menu_order'], 'keys' => $keys, 'version' => $decoded['version'], 'state' => $decoded['state'], 'dispatch_state' => $decoded['operation']['dispatch_state'], 'provider_id' => $decoded['provider_id'], 'operation_id' => $decoded['operation']['operation_id'] );
		$decoded['version'] = 9;
		$decoded['operation']['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
		$this->replacement = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $decoded );
		TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $this->post_id, '_tutorpress_live_lesson_sync', $rows[0]['meta_id'], $this->replacement, $rows[0]['meta_value'] );
		return parent::update( $owner_id, $provider_id, $target );
	}
};
$stale_done = $dispatch_update( $stale_staged, 'token-update-stale', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => false ) ) ), null, null, $stale_spy );
$stale_stored = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_id, '_tutorpress_live_lesson_sync' ), true );
tutorpress_live_lessons_assert( is_array( $stale_staged ) && 'evt-stored' === $stale_staged['operation']['provider_id'] && 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' === $stale_operation_id && $google_stage_calls === $stale_calls && is_array( $stale_spy->seen ) && $planted_row === array( 'post_title' => $stale_spy->seen['post_title'], 'post_content' => $stale_spy->seen['post_content'], 'post_status' => $stale_spy->seen['post_status'], 'menu_order' => $stale_spy->seen['menu_order'] ) && array( '_tutorpress_live_lesson_sync' ) === $stale_spy->seen['keys'] && 3 === $stale_spy->seen['version'] && 'synced' === $stale_spy->seen['state'] && 'confirmed' === $stale_spy->seen['dispatch_state'] && 'evt-stored' === $stale_spy->seen['provider_id'] && $stale_operation_id === $stale_spy->seen['operation_id'] && null === $stale_done && is_string( $stale_spy->replacement ) && $stale_spy->replacement === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_id, '_tutorpress_live_lesson_sync' ) && is_array( $stale_stored ) && 9 === $stale_stored['version'] && 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' === $stale_stored['operation']['operation_id'] && $stale_operation_id !== $stale_stored['operation']['operation_id'] && array( 'update', 'compare_target' ) === $methods() && $planted_row === $publish_row( $stale_id ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $stale_id ) && $stale_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $stale_statements === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $stale_id ] ) && false === strpos( json_encode( $stale_stored ), $secret ), 'a match-false confirmed return leaves the replacement envelope and publish row' );

$dup_confirmed = array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $google_update_map ) );
$dup_item = $plant( 'tutor-google-meet', $google_bytes );
$dup_staged = $stage( $dup_item, $google_input, $google_dispatch_queue, 'token-update-dup', null, null, $google_dispatch_local );
$dup_id = $dup_item['ID'];
$dup_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$dup_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$dup_done = $dispatch_update( $dup_staged, 'token-update-dup', $dup_confirmed );
$dup_statements = count( $GLOBALS['wpdb']->statements );
$dup_again = $dispatch_update( $dup_staged, 'token-update-dup', $dup_confirmed );
$dup_stored = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, '_tutorpress_live_lesson_sync' ), true );
tutorpress_live_lessons_assert( is_array( $dup_staged ) && 'evt-stored' === $dup_staged['operation']['provider_id'] && $google_stage_calls === $dup_calls && is_array( $dup_done ) && 'evt-stored' === $dup_done['provider_id'] && null === $dup_again && array( 'update', 'compare_target', 'metadata_map' ) === $methods() && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, 'tutor-google-meet-start-datetime' ) && '2026-09-21 11:00:00' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, 'tutor-google-meet-end-datetime' ) && json_encode( array( 'id' => 'evt-stored' ) ) === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, 'tutor-google-meet-event-details' ) && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, 'tutor-google-meet-link' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, '_tutorpress_live_lesson_sync' ) && is_array( $dup_stored ) && 3 === $dup_stored['version'] && 'synced' === $dup_stored['state'] && array( 'post_title' => 'Standup', 'post_content' => 'Notes', 'post_status' => 'publish', 'menu_order' => 4 ) === $publish_row( $dup_id ) && $dup_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $dup_statements === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $dup_id ] ) && false === strpos( $google_bytes, $secret ), 'a second update appends a second start-datetime row and returns null' );
tutorpress_live_lessons_pass( 'update interruption rejects a stale response without a save hook' );

$google_caps = array( 'settings' => true, 'hosts' => false, 'delete_convergence' => true );
$zoom_caps   = array( 'settings' => false, 'hosts' => true, 'delete_convergence' => false );
$stage_delete = static function ( array $item, array $input, array $queue, $token, $can = null, $update = null, $adapter_arg = null, $linked = true, $local = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::stage_delete( $item, $topic, $course, null === $can ? $allow : $can, $input, null === $adapter_arg ? $adapter : $adapter_arg, $now, $token, $sync_read, null === $update ? $sync_update : $update, $sync_affected, $linked, $local );
};
$pass11b2_item    = $plant( 'tutor-google-meet', $error_bytes );
$pass11b2_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass11b2_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass11b2_from    = count( $GLOBALS['wpdb']->statements );
$pass11b2_saved   = $stage_delete( $pass11b2_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( true ) ), 'token-pass11b2-delete' );
tutorpress_live_lessons_assert( is_array( $pass11b2_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass11b2_saved ) && $pass11b2_item['ID'] === $pass11b2_saved['post_id'] && 'google_meet' === $pass11b2_saved['provider'] && null === $pass11b2_saved['provider_id'] && 'confirmed' === $pass11b2_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass11b2_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11b2_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass11b2_item['ID'] ] ) && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass11b2_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( array( $pass11b2_item['ID'], true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass11b2_removed ) && $pass11b2_from === count( $GLOBALS['wpdb']->statements ), 'an error row whose meeting id is only on the sync envelope deletes the post locally' );

$pass11d_confirmed = array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $google_update_map ) );
$pass11d_again_item   = $plant( 'tutor-google-meet', $google_bytes );
$pass11d_again_name   = 'tutorpress_live_lesson_lock_' . $pass11d_again_item['ID'];
$pass11d_again_first  = $stage( $pass11d_again_item, $google_input, $google_dispatch_queue, 'token-pass11d-again', null, null, $google_dispatch_local );
$pass11d_again_calls  = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$pass11d_again_done   = $dispatch_update( $pass11d_again_first, 'token-pass11d-again', $pass11d_confirmed );
$pass11d_again_second = $stage( $pass11d_again_item, $google_input, $google_dispatch_queue, 'token-pass11d-again-next', null, null, $google_dispatch_local );
$pass11d_again_first_operation  = is_array( $pass11d_again_first ) && isset( $pass11d_again_first['operation'] ) && is_array( $pass11d_again_first['operation'] ) ? $pass11d_again_first['operation'] : array();
$pass11d_again_second_operation = is_array( $pass11d_again_second ) && isset( $pass11d_again_second['operation'] ) && is_array( $pass11d_again_second['operation'] ) ? $pass11d_again_second['operation'] : array();
$pass11d_again_stored = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_again_item['ID'], '_tutorpress_live_lesson_sync' ), true );
tutorpress_live_lessons_assert( 'evt-stored' === $pass11d_again_first_operation['provider_id'] && 0 === $pass11d_again_first_operation['base_version'] && '2026-09-21 10:00:00' === $pass11d_again_first_operation['start_local'] && '2026-09-21 11:00:00' === $pass11d_again_first_operation['end_local'] && $google_blank === $pass11d_again_first_operation['provider_target'] && $google_stage_calls === $pass11d_again_calls && is_array( $pass11d_again_done ) && 'evt-stored' === $pass11d_again_done['provider_id'] && '2026-09-21 10:00:00' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_again_item['ID'], 'tutor-google-meet-start-datetime' ) && '2026-09-21 11:00:00' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_again_item['ID'], 'tutor-google-meet-end-datetime' ) && json_encode( array( 'id' => 'evt-stored' ) ) === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_again_item['ID'], 'tutor-google-meet-event-details' ) && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_again_item['ID'], 'tutor-google-meet-link' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_again_item['ID'], '_tutorpress_live_lesson_sync' ) && is_array( $pass11d_again_stored ) && 3 === $pass11d_again_stored['version'] && 'synced' === $pass11d_again_stored['state'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass11d_again_name ] ) && 'evt-stored' === $pass11d_again_second_operation['provider_id'] && 0 === $pass11d_again_second_operation['base_version'] && '2026-09-21 10:00:00' === $pass11d_again_second_operation['start_local'] && '2026-09-21 11:00:00' === $pass11d_again_second_operation['end_local'] && $google_blank === $pass11d_again_second_operation['provider_target'] && array( array( 'hydrate_item', array( 'target' => $google_dispatch_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => json_encode( array( 'id' => 'evt-stored' ) ) ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( 'hydrate_item', 'preflight', 'hydrate_item', 'settings' ) === $methods() && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass11d_again_item['ID'] ] ), 'a confirmed update can be updated again' );

$pass11d_delete_item   = $plant( 'tutor-google-meet', $google_bytes );
$pass11d_delete_name   = 'tutorpress_live_lesson_lock_' . $pass11d_delete_item['ID'];
$pass11d_delete_first  = $stage( $pass11d_delete_item, $google_input, $google_dispatch_queue, 'token-pass11d-delete', null, null, $google_dispatch_local );
$pass11d_delete_calls  = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$pass11d_delete_operation = is_array( $pass11d_delete_first ) && isset( $pass11d_delete_first['operation'] ) && is_array( $pass11d_delete_first['operation'] ) ? $pass11d_delete_first['operation'] : array();
$pass11d_delete_done   = $dispatch_update( $pass11d_delete_first, 'token-pass11d-delete', $pass11d_confirmed );
$pass11d_delete_bytes  = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_delete_item['ID'], '_tutorpress_live_lesson_sync' );
$pass11d_delete_stored = json_decode( (string) $pass11d_delete_bytes, true );
$pass11d_delete_saved  = $stage_delete( $pass11d_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( true ) ), 'token-pass11d-delete-next' );
tutorpress_live_lessons_assert( 'evt-stored' === $pass11d_delete_operation['provider_id'] && 0 === $pass11d_delete_operation['base_version'] && '2026-09-21 10:00:00' === $pass11d_delete_operation['start_local'] && '2026-09-21 11:00:00' === $pass11d_delete_operation['end_local'] && $google_blank === $pass11d_delete_operation['provider_target'] && $google_stage_calls === $pass11d_delete_calls && is_array( $pass11d_delete_done ) && 'evt-stored' === $pass11d_delete_done['provider_id'] && $google_bytes === $pass11d_delete_bytes && is_array( $pass11d_delete_stored ) && 3 === $pass11d_delete_stored['version'] && 'synced' === $pass11d_delete_stored['state'] && is_array( $pass11d_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass11d_delete_saved ) && $pass11d_delete_item['ID'] === $pass11d_delete_saved['post_id'] && 'google_meet' === $pass11d_delete_saved['provider'] && null === $pass11d_delete_saved['provider_id'] && 'confirmed' === $pass11d_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass11d_delete_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass11d_delete_name ] ) && array() === $methods(), 'a confirmed update can be deleted' );

$pass11d_error_item   = $plant( 'tutor-google-meet', $google_bytes );
$pass11d_error_name   = 'tutorpress_live_lesson_lock_' . $pass11d_error_item['ID'];
$pass11d_error_first  = $stage( $pass11d_error_item, $google_input, $google_dispatch_queue, 'token-pass11d-error', null, null, $google_dispatch_local );
$pass11d_error_calls  = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$pass11d_error_operation = is_array( $pass11d_error_first ) && isset( $pass11d_error_first['operation'] ) && is_array( $pass11d_error_first['operation'] ) ? $pass11d_error_first['operation'] : array();
$pass11d_error_done   = $dispatch_update( $pass11d_error_first, 'token-pass11d-error', array( 'update' => array( $update_result( 'uncertain', 'evt-replaced' ) ) ) );
$pass11d_error_methods = $methods();
$pass11d_error_second = $stage( $pass11d_error_item, $google_input, $google_dispatch_queue, 'token-pass11d-error-next', null, null, $google_dispatch_local );
$pass11d_error_second_operation = is_array( $pass11d_error_second ) && isset( $pass11d_error_second['operation'] ) && is_array( $pass11d_error_second['operation'] ) ? $pass11d_error_second['operation'] : array();
tutorpress_live_lessons_assert( 'evt-stored' === $pass11d_error_operation['provider_id'] && 0 === $pass11d_error_operation['base_version'] && '2026-09-21 10:00:00' === $pass11d_error_operation['start_local'] && '2026-09-21 11:00:00' === $pass11d_error_operation['end_local'] && $google_blank === $pass11d_error_operation['provider_target'] && $google_stage_calls === $pass11d_error_calls && null === $pass11d_error_done && array( 'update' ) === $pass11d_error_methods && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass11d_error_item['ID'], '_tutorpress_live_lesson_sync' ) && false === strpos( $google_bytes, 'evt-replaced' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $pass11d_error_item['ID'] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass11d_error_name ] ) && 'evt-stored' === $pass11d_error_second_operation['provider_id'] && 0 === $pass11d_error_second_operation['base_version'] && '2026-09-21 10:00:00' === $pass11d_error_second_operation['start_local'] && '2026-09-21 11:00:00' === $pass11d_error_second_operation['end_local'] && $google_blank === $pass11d_error_second_operation['provider_target'] && $google_stage_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass11d_error_item['ID'] ] ), 'an update that fails can be updated again' );
$delete_input = $google_input;
$delete_input['start'] = '2026-09-21 09:00:00';
$delete_input['end'] = '2026-09-21 09:30:00';
$delete_input['title'] = 'Replaced';
$delete_input['content'] = 'Replaced body';
$delete_item = $plant( 'tutor-google-meet', $google_bytes );
$delete_id = $delete_item['ID'];
$delete_decoy = json_encode( array( 'id' => 'evt-meta' ) );
add_post_meta( $delete_id, 'tutor-google-meet-event-details', $delete_decoy );
$delete_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$delete_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$delete_spy = new class( $delete_id ) extends TutorPress_Live_Lessons_Adapter_Fake {
	public $seen = null;
	private $post_id;
	public function __construct( $post_id ) { $this->post_id = (int) $post_id; }
	public function delete( $owner_id, $provider_id ) {
		$decoded = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $this->post_id, '_tutorpress_live_lesson_sync' ), true );
		$post = TutorPress_Live_Lessons_Store_Fake::$posts[ $this->post_id ];
		$this->seen = array( 'owner_id' => $owner_id, 'provider_id' => $provider_id, 'post_status' => $post['post_status'], 'version' => $decoded['version'], 'state' => $decoded['state'], 'dispatch_state' => $decoded['operation']['dispatch_state'], 'stored_id' => $decoded['provider_id'] );
		return parent::delete( $owner_id, $provider_id );
	}
};
$delete_saved = $stage_delete( $delete_item, $delete_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( array( 'outcome' => 'confirmed', 'http_status' => 204, 'provider_id' => 'evt-evil' ) ) ), 'token-delete-google', null, null, $delete_spy );
tutorpress_live_lessons_assert( is_array( $delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $delete_saved ) && $delete_id === $delete_saved['post_id'] && 'google_meet' === $delete_saved['provider'] && null === $delete_saved['provider_id'] && 'confirmed' === $delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $delete_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $delete_id, 'tutor-google-meet-event-details' ) && null === $delete_spy->seen && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && $delete_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && array( array( $delete_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $delete_removed ) && $delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $delete_id ] ), 'google delete dispatches the stored id and leaves the publish post pending' );

$throw_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$throw_delete_id = $throw_delete_item['ID'];
$throw_delete_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$throw_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$throw_delete_spy = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public function delete( $owner_id, $provider_id ) {
		parent::delete( $owner_id, $provider_id );
		throw new RuntimeException( 'delete-boom-secret' );
	}
};
$throw_delete_saved = $stage_delete( $throw_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( true ) ), 'token-delete-throw', null, null, $throw_delete_spy );
tutorpress_live_lessons_assert( is_array( $throw_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $throw_delete_saved ) && $throw_delete_id === $throw_delete_saved['post_id'] && 'google_meet' === $throw_delete_saved['provider'] && null === $throw_delete_saved['provider_id'] && 'confirmed' === $throw_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $throw_delete_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $throw_delete_id, '_tutorpress_live_lesson_sync' ) && array() === $methods() && array( array( $throw_delete_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $throw_delete_removed ) && $throw_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a thrown delete leaves the pending dispatch row and discards the exception text' );

$seen_delete = 0;
$delete_can = static function ( $cap, $id ) use ( &$seen_delete, $allow ) { $seen_delete++; return $allow( $cap, $id ); };
$evil_delete = $google_input;
$evil_delete['provider_id'] = 'evt-evil';
$key_delete = $google_input;
$key_delete['id'] = 'evt-body';
$identity_delete = $plant( 'tutor-google-meet', $google_bytes );
$seen_delete = 0;
$evil_delete_saved = $stage_delete( $identity_delete, $evil_delete, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) ), 'token-delete-evil', $delete_can );
$evil_delete_methods = $methods();
$key_delete_saved = $stage_delete( $identity_delete, $key_delete, array( 'capabilities' => array( $google_caps ) ), 'token-delete-key', $delete_can );
tutorpress_live_lessons_assert( null === $evil_delete_saved && null === $key_delete_saved && array() === $evil_delete_methods && array() === $methods() && 0 === $seen_delete && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $identity_delete['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $identity_delete['ID'] ] ), 'a payload provider id returns null before authorize and capabilities' );

$bare_item = $plant( 'tutor-google-meet', $google_bytes );
$bare_saved = $stage_delete( $bare_item, $google_input, array( 'capabilities' => array( true ) ), 'token-delete-bare' );
$bare_methods = $methods();
$zoom_delete_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_delete_saved = $stage_delete( $zoom_delete_item, $zoom_input, array( 'capabilities' => array( $zoom_caps ) ), 'token-delete-zoom' );
$zoom_delete_methods = $methods();
$broad_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$broad_delete = $stage_delete( $broad_delete_item, $google_input, array( 'capabilities' => array( $google_caps ) ), 'token-delete-broad', $broad );
tutorpress_live_lessons_assert( is_array( $bare_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $bare_saved ) && $bare_item['ID'] === $bare_saved['post_id'] && 'google_meet' === $bare_saved['provider'] && null === $bare_saved['provider_id'] && 'confirmed' === $bare_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $bare_item['ID'] ] ) && array() === $bare_methods && is_array( $zoom_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $zoom_delete_saved ) && $zoom_delete_item['ID'] === $zoom_delete_saved['post_id'] && 'zoom' === $zoom_delete_saved['provider'] && null === $zoom_delete_saved['provider_id'] && 'confirmed' === $zoom_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_delete_item['ID'] ] ) && array() === $zoom_delete_methods && null === $broad_delete && array() === $methods() && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $broad_delete_item['ID'] ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $broad_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $bare_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_delete_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $broad_delete_item['ID'] ] ) && array( array( $zoom_delete_item['ID'], true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $zoom_delete_removed ), 'a bare capability boolean, zoom delete_convergence false, and broad edit_posts do not lock or dispatch' );

$pass12b_null_operation = $operation( 'zoom', '2026-09-21 10:00:00', '2026-09-21 12:00:00', array( 'value' => 2, 'unit' => 'hours' ), $zoom_target );
$pass12b_error_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $envelope( 'zoom', 1, 'error', null, $pass12b_null_operation ) );
$pass12l_local = array( 'tutor-google-meet-event-details' => '{"id":"evt-error-row"}' );
$pass12l_item = $plant( 'tutor_zoom_meeting', $pass12b_error_bytes );
$pass12l_id = $pass12l_item['ID'];
$pass12l_name = 'tutorpress_live_lesson_lock_' . $pass12l_id;
$pass12l_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12l_from = count( $GLOBALS['wpdb']->statements );
$pass12l_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12l_saved = $stage_delete( $pass12l_item, $zoom_input, array( 'hydrate_item' => array( null ) ), 'token-pass12l', null, null, null, true, $pass12l_local );
tutorpress_live_lessons_assert( is_array( $pass12l_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12l_saved ) && $pass12l_id === $pass12l_saved['post_id'] && 'zoom' === $pass12l_saved['provider'] && null === $pass12l_saved['provider_id'] && 'confirmed' === $pass12l_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12l_id ] ) && array( array( $pass12l_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12l_removed ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12l_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass12l_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12l_id, '_tutorpress_live_lesson_sync' ) && $pass12l_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12l_name ] ), 'an error zoom row with a null provider id deletes locally and does not read the meeting id' );

$pass12l_live_item = $plant( 'tutor_zoom_meeting', $pass12b_error_bytes );
$pass12l_live_id = $pass12l_live_item['ID'];
$pass12l_live_name = 'tutorpress_live_lesson_lock_' . $pass12l_live_id;
$pass12l_live_bytes = '{"expiry":"2026-09-21 16:05:00"}';
$pass12l_live_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12l_live_from = count( $GLOBALS['wpdb']->statements );
$pass12l_live_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass12l_live_name ] = $pass12l_live_bytes;
$pass12l_live_saved = $stage_delete( $pass12l_live_item, $zoom_input, array( 'hydrate_item' => array( null ) ), 'token-pass12l-live', null, null, null, true, $pass12l_local );
tutorpress_live_lessons_assert( is_array( $pass12l_live_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12l_live_saved ) && $pass12l_live_id === $pass12l_live_saved['post_id'] && 'zoom' === $pass12l_live_saved['provider'] && null === $pass12l_live_saved['provider_id'] && 'confirmed' === $pass12l_live_saved['outcome'] && $pass12l_live_bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $pass12l_live_name ] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12l_live_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12l_live_id, '_tutorpress_live_lesson_sync' ) && $pass12l_live_from === count( $GLOBALS['wpdb']->statements ) && array( array( $pass12l_live_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12l_live_removed ) && $pass12l_live_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12l_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a live lock on an error zoom row with a null provider id returns before the post delete and leaves the post and the planted option' );

$pass12b_synced_bytes   = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $envelope( 'zoom', 1, 'synced', null, $pass12b_null_operation ) );
$pass12b_synced_item    = $plant( 'tutor_zoom_meeting', $pass12b_synced_bytes );
$pass12b_synced_id      = $pass12b_synced_item['ID'];
$pass12b_synced_name    = 'tutorpress_live_lesson_lock_' . $pass12b_synced_id;
$pass12b_synced_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12b_synced_from    = count( $GLOBALS['wpdb']->statements );
$pass12b_synced_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12b_synced_saved   = $stage_delete( $pass12b_synced_item, $zoom_input, array(), 'token-pass12b-synced' );
tutorpress_live_lessons_assert( is_array( $pass12b_synced_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12b_synced_saved ) && $pass12b_synced_id === $pass12b_synced_saved['post_id'] && 'zoom' === $pass12b_synced_saved['provider'] && null === $pass12b_synced_saved['provider_id'] && 'confirmed' === $pass12b_synced_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12b_synced_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12b_synced_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12b_synced_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12b_synced_removed ) && array() === $methods() && $pass12b_synced_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $pass12b_synced_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12b_synced_name ] ), 'a synced zoom row with a null provider id force-deletes and releases the lock once' );

$pass12b_fail_bytes   = $pass12b_synced_bytes;
$pass12b_fail_item    = $plant( 'tutor_zoom_meeting', $pass12b_fail_bytes );
$pass12b_fail_id      = $pass12b_fail_item['ID'];
$pass12b_fail_name    = 'tutorpress_live_lesson_lock_' . $pass12b_fail_id;
$pass12b_fail_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12b_fail_from    = count( $GLOBALS['wpdb']->statements );
$pass12b_fail_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $pass12b_fail_id ] = true;
$pass12b_fail_saved = $stage_delete( $pass12b_fail_item, $zoom_input, array(), 'token-pass12b-fail' );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $pass12b_fail_id ] );
tutorpress_live_lessons_assert( null === $pass12b_fail_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12b_fail_id ] ) && $pass12b_fail_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12b_fail_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12b_fail_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12b_fail_removed ) && array() === $methods() && $pass12b_fail_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $pass12b_fail_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12b_fail_name ] ), 'a failed force delete releases once and leaves the original sync bytes' );

$pass12b_live_item    = $plant( 'tutor_zoom_meeting', $pass12b_synced_bytes );
$pass12b_live_id      = $pass12b_live_item['ID'];
$pass12b_live_name    = 'tutorpress_live_lesson_lock_' . $pass12b_live_id;
$pass12b_live_bytes   = '{"expiry":"2026-09-21 16:05:00"}';
$pass12b_live_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12b_live_from    = count( $GLOBALS['wpdb']->statements );
$pass12b_live_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass12b_live_name ] = $pass12b_live_bytes;
$pass12b_live_saved = $stage_delete( $pass12b_live_item, $zoom_input, array(), 'token-pass12b-live' );
tutorpress_live_lessons_assert( is_array( $pass12b_live_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12b_live_saved ) && $pass12b_live_id === $pass12b_live_saved['post_id'] && 'zoom' === $pass12b_live_saved['provider'] && null === $pass12b_live_saved['provider_id'] && 'confirmed' === $pass12b_live_saved['outcome'] && $pass12b_live_bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $pass12b_live_name ] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12b_live_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12b_live_id, '_tutorpress_live_lesson_sync' ) && $pass12b_live_from === count( $GLOBALS['wpdb']->statements ) && array( array( $pass12b_live_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12b_live_removed ) && $pass12b_live_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods(), 'a live lock on a synced null-id row returns before the post delete and leaves the planted option' );

$pass12c_zoom_item    = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$pass12c_zoom_id      = $pass12c_zoom_item['ID'];
$pass12c_zoom_name    = 'tutorpress_live_lesson_lock_' . $pass12c_zoom_id;
$pass12c_zoom_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12c_zoom_from    = count( $GLOBALS['wpdb']->statements );
$pass12c_zoom_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12c_zoom_saved   = $stage_delete( $pass12c_zoom_item, $zoom_input, array(), 'token-pass12c-zoom', null, null, null, false );
tutorpress_live_lessons_assert( is_array( $pass12c_zoom_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12c_zoom_saved ) && $pass12c_zoom_id === $pass12c_zoom_saved['post_id'] && 'zoom' === $pass12c_zoom_saved['provider'] && null === $pass12c_zoom_saved['provider_id'] && 'confirmed' === $pass12c_zoom_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12c_zoom_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12c_zoom_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12c_zoom_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12c_zoom_removed ) && array() === $methods() && $pass12c_zoom_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $pass12c_zoom_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12c_zoom_name ] ), 'an unlinked synced zoom row with a stored id force-deletes without a provider call' );

$pass12c_google_item    = $plant( 'tutor-google-meet', $google_bytes );
$pass12c_google_id      = $pass12c_google_item['ID'];
$pass12c_google_name    = 'tutorpress_live_lesson_lock_' . $pass12c_google_id;
$pass12c_google_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12c_google_from    = count( $GLOBALS['wpdb']->statements );
$pass12c_google_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12c_google_saved   = $stage_delete( $pass12c_google_item, $google_input, array(), 'token-pass12c-google', null, null, null, false );
tutorpress_live_lessons_assert( is_array( $pass12c_google_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12c_google_saved ) && $pass12c_google_id === $pass12c_google_saved['post_id'] && 'google_meet' === $pass12c_google_saved['provider'] && null === $pass12c_google_saved['provider_id'] && 'confirmed' === $pass12c_google_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12c_google_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12c_google_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12c_google_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12c_google_removed ) && array() === $methods() && $pass12c_google_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $pass12c_google_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12c_google_name ] ), 'an unlinked synced google row with a stored id force-deletes without a provider call' );

$pass12c_live_item    = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$pass12c_live_id      = $pass12c_live_item['ID'];
$pass12c_live_name    = 'tutorpress_live_lesson_lock_' . $pass12c_live_id;
$pass12c_live_bytes   = '{"expiry":"2026-09-21 16:05:00"}';
$pass12c_live_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12c_live_from    = count( $GLOBALS['wpdb']->statements );
$pass12c_live_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass12c_live_name ] = $pass12c_live_bytes;
$pass12c_live_saved = $stage_delete( $pass12c_live_item, $zoom_input, array(), 'token-pass12c-live', null, null, null, false );
tutorpress_live_lessons_assert( is_array( $pass12c_live_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12c_live_saved ) && $pass12c_live_id === $pass12c_live_saved['post_id'] && 'zoom' === $pass12c_live_saved['provider'] && null === $pass12c_live_saved['provider_id'] && 'confirmed' === $pass12c_live_saved['outcome'] && $pass12c_live_bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $pass12c_live_name ] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12c_live_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12c_live_id, '_tutorpress_live_lesson_sync' ) && $pass12c_live_from === count( $GLOBALS['wpdb']->statements ) && array( array( $pass12c_live_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12c_live_removed ) && $pass12c_live_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods(), 'a live lock on an unlinked synced zoom row returns before the post delete and leaves the planted option' );

$pass12c_fail_item    = $plant( 'tutor-google-meet', $google_bytes );
$pass12c_fail_id      = $pass12c_fail_item['ID'];
$pass12c_fail_name    = 'tutorpress_live_lesson_lock_' . $pass12c_fail_id;
$pass12c_fail_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12c_fail_from    = count( $GLOBALS['wpdb']->statements );
$pass12c_fail_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $pass12c_fail_id ] = true;
$pass12c_fail_saved = $stage_delete( $pass12c_fail_item, $google_input, array(), 'token-pass12c-fail', null, null, null, false );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $pass12c_fail_id ] );
tutorpress_live_lessons_assert( null === $pass12c_fail_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12c_fail_id ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12c_fail_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12c_fail_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12c_fail_removed ) && array() === $methods() && $pass12c_fail_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $pass12c_fail_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12c_fail_name ] ), 'a failed unlinked google delete releases once and leaves the original sync bytes' );

$pass12l_false_item = $plant( 'tutor_zoom_meeting', $pass12b_error_bytes );
$pass12l_false_id = $pass12l_false_item['ID'];
$pass12l_false_name = 'tutorpress_live_lesson_lock_' . $pass12l_false_id;
$pass12l_false_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12l_false_from = count( $GLOBALS['wpdb']->statements );
$pass12l_false_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12l_false_saved = $stage_delete( $pass12l_false_item, $zoom_input, array( 'hydrate_item' => array( null ) ), 'token-pass12l-false', null, null, null, false, $pass12l_local );
tutorpress_live_lessons_assert( is_array( $pass12l_false_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12l_false_saved ) && $pass12l_false_id === $pass12l_false_saved['post_id'] && 'zoom' === $pass12l_false_saved['provider'] && null === $pass12l_false_saved['provider_id'] && 'confirmed' === $pass12l_false_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12l_false_id ] ) && array( array( $pass12l_false_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12l_false_removed ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12l_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass12l_false_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12l_false_id, '_tutorpress_live_lesson_sync' ) && $pass12l_false_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12l_false_name ] ), 'an error zoom row with a null provider id still deletes locally when the flag is false' );

$pass12c_pending_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $envelope( 'zoom', 1, 'pending', 'evt-stored', $operation( 'zoom', '2026-09-21 10:00:00', '2026-09-21 12:00:00', array( 'value' => 2, 'unit' => 'hours' ), $zoom_target ) ) );
$pass12c_pending_item  = $plant( 'tutor_zoom_meeting', $pass12c_pending_bytes );
$pass12c_pending_id    = $pass12c_pending_item['ID'];
$pass12c_pending_name  = 'tutorpress_live_lesson_lock_' . $pass12c_pending_id;
$pass12c_pending_lock  = '{"expiry":"2026-09-21 16:05:00"}';
$pass12c_pending_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12c_pending_from  = count( $GLOBALS['wpdb']->statements );
$pass12c_pending_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass12c_pending_name ] = $pass12c_pending_lock;
$pass12c_pending_saved = $stage_delete( $pass12c_pending_item, $zoom_input, array( 'capabilities' => array( $zoom_caps ) ), 'token-pass12c-pending', null, null, null, false );
tutorpress_live_lessons_assert( is_array( $pass12c_pending_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12c_pending_saved ) && $pass12c_pending_id === $pass12c_pending_saved['post_id'] && 'zoom' === $pass12c_pending_saved['provider'] && null === $pass12c_pending_saved['provider_id'] && 'confirmed' === $pass12c_pending_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12c_pending_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12c_pending_id, '_tutorpress_live_lesson_sync' ) && $pass12c_pending_lock === TutorPress_Live_Lessons_Store_Fake::$options[ $pass12c_pending_name ] && $pass12c_pending_from === count( $GLOBALS['wpdb']->statements ) && array( array( $pass12c_pending_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12c_pending_removed ) && $pass12c_pending_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods(), 'a pending zoom row with a live lock and a stored id reaches capabilities and leaves the post and the planted option' );

$preflight_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$preflight_delete_saved = $stage_delete( $preflight_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( false ) ), 'token-delete-preflight' );
tutorpress_live_lessons_assert( is_array( $preflight_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $preflight_delete_saved ) && $preflight_delete_item['ID'] === $preflight_delete_saved['post_id'] && 'google_meet' === $preflight_delete_saved['provider'] && null === $preflight_delete_saved['provider_id'] && 'confirmed' === $preflight_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $preflight_delete_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $preflight_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $preflight_delete_item['ID'] ] ) && array() === $methods(), 'a failed delete preflight returns null before the lock' );

$acquire_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$acquire_delete_from = count( $GLOBALS['wpdb']->statements );
$acquire_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$acquire_delete_saved = $stage_delete( $acquire_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) ), 'token-delete-acquire' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( is_array( $acquire_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $acquire_delete_saved ) && $acquire_delete_item['ID'] === $acquire_delete_saved['post_id'] && 'google_meet' === $acquire_delete_saved['provider'] && null === $acquire_delete_saved['provider_id'] && 'confirmed' === $acquire_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $acquire_delete_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $acquire_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && $acquire_delete_from === count( $GLOBALS['wpdb']->statements ) && array( array( $acquire_delete_item['ID'], true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $acquire_delete_removed ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $acquire_delete_item['ID'] ] ), 'a failed delete acquire leaves the synced post without a release' );

$swap_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$swap_delete_from = count( $GLOBALS['wpdb']->statements );
$swap_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$swap_delete_saved = $stage_delete( $swap_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) ), 'token-delete-swap', null, static function () { return false; } );
tutorpress_live_lessons_assert( is_array( $swap_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $swap_delete_saved ) && $swap_delete_item['ID'] === $swap_delete_saved['post_id'] && 'google_meet' === $swap_delete_saved['provider'] && null === $swap_delete_saved['provider_id'] && 'confirmed' === $swap_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $swap_delete_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $swap_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && $swap_delete_from === count( $GLOBALS['wpdb']->statements ) && array( array( $swap_delete_item['ID'], true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $swap_delete_removed ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $swap_delete_item['ID'] ] ), 'a failed delete exchange releases the exact token and leaves the synced post' );

$second_delete_calls = 0;
$second_delete_update = static function ( $post_id, $key, $meta_id, $value, $prior ) use ( &$second_delete_calls ) {
	$second_delete_calls++;
	if ( 2 === $second_delete_calls ) { return false; }
	return TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post_id, $key, $meta_id, $value, $prior );
};
$second_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$second_delete_from = count( $GLOBALS['wpdb']->statements );
$second_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$second_delete_saved = $stage_delete( $second_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) ), 'token-delete-second', null, $second_delete_update );
tutorpress_live_lessons_assert( is_array( $second_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $second_delete_saved ) && $second_delete_item['ID'] === $second_delete_saved['post_id'] && 'google_meet' === $second_delete_saved['provider'] && null === $second_delete_saved['provider_id'] && 'confirmed' === $second_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $second_delete_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $second_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && 0 === $second_delete_calls && $second_delete_from === count( $GLOBALS['wpdb']->statements ) && array( array( $second_delete_item['ID'], true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $second_delete_removed ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $second_delete_item['ID'] ] ), 'a failed dispatch exchange leaves the version-plus-one delete row and does not call delete' );
tutorpress_live_lessons_pass( 'delete dispatches the stored id without local deletion' );

$delete_result = static function ( $outcome, $provider_id = 'evt-stored' ) {
	return array( 'diagnostics' => array(), 'item' => null, 'outcome' => $outcome, 'provenance' => 'delete', 'provider_id' => $provider_id );
};
$converged = $plant( 'tutor-google-meet', $google_bytes );
$converged_id = $converged['ID'];
$converged_from = count( $GLOBALS['wpdb']->statements );
$converged_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$converged_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$converged_saved = $stage_delete( $converged, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-confirmed' );
tutorpress_live_lessons_assert( is_array( $converged_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $converged_saved ) && $converged_id === $converged_saved['post_id'] && 'google_meet' === $converged_saved['provider'] && null === $converged_saved['provider_id'] && 'confirmed' === $converged_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $converged_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $converged_id, '_tutorpress_live_lesson_sync' ) && $converged_from === count( $GLOBALS['wpdb']->statements ) && array( array( $converged_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $converged_removed ) && $converged_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $converged_id ] ), 'a normalized confirmed delete removes the post and releases the lock before the delete hook' );

$absent = $plant( 'tutor-google-meet', $google_bytes );
$absent_id = $absent['ID'];
$absent_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$absent_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$absent_saved = $stage_delete( $absent, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'known_id_not_found' ) ) ), 'token-delete-absent' );
tutorpress_live_lessons_assert( is_array( $absent_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $absent_saved ) && $absent_id === $absent_saved['post_id'] && 'google_meet' === $absent_saved['provider'] && null === $absent_saved['provider_id'] && 'confirmed' === $absent_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $absent_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $absent_id, '_tutorpress_live_lesson_sync' ) && array( array( $absent_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $absent_removed ) && $absent_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $absent_id ] ), 'a google known-id 404 deletes locally and fires the delete hook after release' );

$uncertain_delete = $plant( 'tutor-google-meet', $google_bytes );
$uncertain_delete_id = $uncertain_delete['ID'];
$uncertain_delete_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$uncertain_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$uncertain_delete_saved = $stage_delete( $uncertain_delete, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'uncertain' ) ) ), 'token-delete-uncertain' );
$rejected_delete = $plant( 'tutor-google-meet', $google_bytes );
$rejected_delete_id = $rejected_delete['ID'];
$rejected_delete_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$rejected_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$rejected_delete_saved = $stage_delete( $rejected_delete, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'definite_rejection' ) ) ), 'token-delete-rejected' );
tutorpress_live_lessons_assert( is_array( $uncertain_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $uncertain_delete_saved ) && $uncertain_delete_id === $uncertain_delete_saved['post_id'] && 'google_meet' === $uncertain_delete_saved['provider'] && null === $uncertain_delete_saved['provider_id'] && 'confirmed' === $uncertain_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $uncertain_delete_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $uncertain_delete_id, '_tutorpress_live_lesson_sync' ) && array( array( $uncertain_delete_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $uncertain_delete_removed, 1 ) && $uncertain_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_array( $rejected_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $rejected_delete_saved ) && $rejected_delete_id === $rejected_delete_saved['post_id'] && 'google_meet' === $rejected_delete_saved['provider'] && null === $rejected_delete_saved['provider_id'] && 'confirmed' === $rejected_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $rejected_delete_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $rejected_delete_id, '_tutorpress_live_lesson_sync' ) && array( array( $rejected_delete_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $rejected_delete_removed ) && $rejected_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods(), 'uncertain and definite rejection keep the publish post and write an error row' );

$forged_delete = $plant( 'tutor-google-meet', $google_bytes );
$forged_delete_id = $forged_delete['ID'];
$forged_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$forged_delete_saved = $stage_delete( $forged_delete, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed', 'evt-evil' ) ) ), 'token-delete-forged' );
tutorpress_live_lessons_assert( is_array( $forged_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $forged_delete_saved ) && $forged_delete_id === $forged_delete_saved['post_id'] && 'google_meet' === $forged_delete_saved['provider'] && null === $forged_delete_saved['provider_id'] && 'confirmed' === $forged_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $forged_delete_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $forged_delete_id, '_tutorpress_live_lesson_sync' ) && array( array( $forged_delete_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $forged_delete_removed ) && array() === $methods(), 'a confirmed delete with a different provider id keeps the stored id and the publish post' );

$zoom_open = array( 'settings' => false, 'hosts' => true, 'delete_convergence' => true );
$pass12d_google_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:17', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:17' );
$pass12d_google_hydrate = array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-tutor' );
$pass12d_google_item = $plant( 'tutor-google-meet', null );
$pass12d_google_id = $pass12d_google_item['ID'];
$pass12d_google_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12d_google_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12d_google_saved = $stage_delete( $pass12d_google_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'hydrate_item' => array( $pass12d_google_hydrate, array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ), 'delete' => array( $delete_result( 'confirmed', 'evt-tutor' ) ) ), 'token-pass12d-google', null, null, null, true, $pass12d_google_local );
$pass12d_google_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $pass12d_google_hooks ) );
tutorpress_live_lessons_assert( is_array( $pass12d_google_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12d_google_saved ) && $pass12d_google_id === $pass12d_google_saved['post_id'] && 'google_meet' === $pass12d_google_saved['provider'] && 'evt-tutor' === $pass12d_google_saved['provider_id'] && 'confirmed' === $pass12d_google_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12d_google_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12d_google_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12d_google_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12d_google_removed ) && array( array( 'tutorpress_live_lesson_deleted', array( $pass12d_google_id, array( 'id' => $pass12d_google_id, 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) ) ) ) === $pass12d_google_new_hooks && array( array( 'hydrate_item', array( 'target' => $pass12d_google_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'delete', array( 'owner_id' => 11, 'provider_id' => 'evt-tutor' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass12d_google_id ] ), 'a google lesson with no sync row adopts its meeting id and the confirmed delete removes the post' );

$pass12d_zoom_local = array( '_tutor_zm_data' => '{"id":"evt-tutor"}' );
$pass12d_zoom_stopped_item = $plant( 'tutor_zoom_meeting', null );
$pass12d_zoom_stopped_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12d_zoom_stopped_saved = $stage_delete( $pass12d_zoom_stopped_item, $zoom_input, array( 'hydrate_item' => array( array( 'provider_id' => 'evt-tutor' ) ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed', 'evt-tutor' ) ) ), 'token-pass12d-zoom-stopped', null, null, null, true, $pass12d_zoom_local );
tutorpress_live_lessons_assert( is_array( $pass12d_zoom_stopped_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12d_zoom_stopped_saved ) && $pass12d_zoom_stopped_item['ID'] === $pass12d_zoom_stopped_saved['post_id'] && 'zoom' === $pass12d_zoom_stopped_saved['provider'] && 'evt-tutor' === $pass12d_zoom_stopped_saved['provider_id'] && 'confirmed' === $pass12d_zoom_stopped_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12d_zoom_stopped_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12d_zoom_stopped_item['ID'], '_tutorpress_live_lesson_sync' ) && array( array( 'hydrate_item', array( 'target' => $pass12d_zoom_local ) ), array( 'preflight', array( 'owner_id' => 42 ) ), array( 'delete', array( 'owner_id' => 42, 'provider_id' => 'evt-tutor' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( array( 'tutorpress_live_lesson_deleted', array( $pass12d_zoom_stopped_item['ID'], array( 'id' => $pass12d_zoom_stopped_item['ID'], 'title' => 'Copied', 'type' => 'zoom', 'topicId' => 8 ) ) ) ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $pass12d_zoom_stopped_hooks ) ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass12d_zoom_stopped_item['ID'] ] ), 'a zoom lesson with no sync row stops at the false capability and writes no seed' );

$pass12d_null_caps = array( 'settings' => true, 'hosts' => false, 'delete_convergence' => false );
$pass12d_null_stopped_item = $plant( 'tutor-google-meet', null );
$pass12d_null_stopped_saved = $stage_delete( $pass12d_null_stopped_item, $google_input, array( 'capabilities' => array( $pass12d_null_caps ) ), 'token-pass12d-null-stopped' );
tutorpress_live_lessons_assert( is_array( $pass12d_null_stopped_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12d_null_stopped_saved ) && $pass12d_null_stopped_item['ID'] === $pass12d_null_stopped_saved['post_id'] && 'google_meet' === $pass12d_null_stopped_saved['provider'] && null === $pass12d_null_stopped_saved['provider_id'] && 'confirmed' === $pass12d_null_stopped_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12d_null_stopped_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12d_null_stopped_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass12d_null_stopped_item['ID'] ] ), 'an empty sync row with null provider meta stops at the false capability and keeps the post' );

$pass12d_null_item = $plant( 'tutor-google-meet', null );
$pass12d_null_id = $pass12d_null_item['ID'];
$pass12d_null_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12d_null_saved = $stage_delete( $pass12d_null_item, $google_input, array( 'capabilities' => array( $google_caps ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-pass12d-null-meta' );
tutorpress_live_lessons_assert( is_array( $pass12d_null_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12d_null_saved ) && $pass12d_null_id === $pass12d_null_saved['post_id'] && 'google_meet' === $pass12d_null_saved['provider'] && null === $pass12d_null_saved['provider_id'] && 'confirmed' === $pass12d_null_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12d_null_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12d_null_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12d_null_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12d_null_removed ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass12d_null_id ] ), 'an empty sync row with null provider meta deletes locally and does not call delete' );

$pass12d_zoom_hydrate = array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 12:00:00', 'timezone' => 'America/New_York', 'provider_id' => 'evt-tutor', 'host_id' => 'host-1', 'duration' => array( 'value' => 2, 'unit' => 'hours' ) );
$pass12d_zoom_item = $plant( 'tutor_zoom_meeting', null );
$pass12d_zoom_id = $pass12d_zoom_item['ID'];
$pass12d_zoom_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12d_zoom_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12d_zoom_saved = $stage_delete( $pass12d_zoom_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'hydrate_item' => array( $pass12d_zoom_hydrate ), 'delete' => array( $delete_result( 'confirmed', 'evt-tutor' ) ) ), 'token-pass12d-zoom', null, null, null, true, $pass12d_zoom_local );
$pass12d_zoom_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $pass12d_zoom_hooks ) );
tutorpress_live_lessons_assert( is_array( $pass12d_zoom_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12d_zoom_saved ) && $pass12d_zoom_id === $pass12d_zoom_saved['post_id'] && 'zoom' === $pass12d_zoom_saved['provider'] && 'evt-tutor' === $pass12d_zoom_saved['provider_id'] && 'confirmed' === $pass12d_zoom_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12d_zoom_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12d_zoom_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12d_zoom_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12d_zoom_removed ) && array( array( 'tutorpress_live_lesson_deleted', array( $pass12d_zoom_id, array( 'id' => $pass12d_zoom_id, 'title' => 'Copied', 'type' => 'zoom', 'topicId' => 8 ) ) ) ) === $pass12d_zoom_new_hooks && array( array( 'hydrate_item', array( 'target' => $pass12d_zoom_local ) ), array( 'preflight', array( 'owner_id' => 42 ) ), array( 'delete', array( 'owner_id' => 42, 'provider_id' => 'evt-tutor' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass12d_zoom_id ] ), 'a zoom lesson with no sync row adopts its meeting id when delete convergence is queued true' );

$pass12d_live_item = $plant( 'tutor-google-meet', null );
$pass12d_live_id = $pass12d_live_item['ID'];
$pass12d_live_name = 'tutorpress_live_lesson_lock_' . $pass12d_live_id;
$pass12d_live_bytes = '{"expiry":"2026-09-21 16:05:00"}';
$pass12d_live_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12d_live_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Store_Fake::$options[ $pass12d_live_name ] = $pass12d_live_bytes;
$pass12d_live_saved = $stage_delete( $pass12d_live_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'hydrate_item' => array( $pass12d_google_hydrate, array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ), 'delete' => array( $delete_result( 'confirmed', 'evt-tutor' ) ) ), 'token-pass12d-live', null, null, null, true, $pass12d_google_local );
tutorpress_live_lessons_assert( is_array( $pass12d_live_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12d_live_saved ) && $pass12d_live_id === $pass12d_live_saved['post_id'] && 'google_meet' === $pass12d_live_saved['provider'] && 'evt-tutor' === $pass12d_live_saved['provider_id'] && 'confirmed' === $pass12d_live_saved['outcome'] && $pass12d_live_bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $pass12d_live_name ] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12d_live_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12d_live_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12d_live_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12d_live_removed ) && array( array( 'tutorpress_live_lesson_deleted', array( $pass12d_live_id, array( 'id' => $pass12d_live_id, 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) ) ) ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $pass12d_live_hooks ) ) && array( array( 'hydrate_item', array( 'target' => $pass12d_google_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'delete', array( 'owner_id' => 11, 'provider_id' => 'evt-tutor' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a live lock on a lesson with no sync row returns before the seed and leaves the post' );

$pass12e_item    = $plant( 'tutor-google-meet', null );
$pass12e_id      = $pass12e_item['ID'];
$pass12e_name    = 'tutorpress_live_lesson_lock_' . $pass12e_id;
$pass12e_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12e_from    = count( $GLOBALS['wpdb']->statements );
$pass12e_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12e_saved   = $stage_delete( $pass12e_item, $google_input, array( 'hydrate_item' => array( null ) ), 'token-pass12e', null, null, null, false, $pass12d_google_local );
tutorpress_live_lessons_assert( is_array( $pass12e_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12e_saved ) && $pass12e_id === $pass12e_saved['post_id'] && 'google_meet' === $pass12e_saved['provider'] && null === $pass12e_saved['provider_id'] && 'confirmed' === $pass12e_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12e_id ] ) && array( array( $pass12e_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12e_removed ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12d_google_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass12e_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12e_id, '_tutorpress_live_lesson_sync' ) && $pass12e_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12e_name ] ), 'an unlinked lesson with no sync row force-deletes before capabilities and writes no seed' );

$pass12e_live_item    = $plant( 'tutor-google-meet', null );
$pass12e_live_id      = $pass12e_live_item['ID'];
$pass12e_live_name    = 'tutorpress_live_lesson_lock_' . $pass12e_live_id;
$pass12e_live_bytes   = '{"expiry":"2026-09-21 16:05:00"}';
$pass12e_live_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12e_live_from    = count( $GLOBALS['wpdb']->statements );
$pass12e_live_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass12e_live_name ] = $pass12e_live_bytes;
$pass12e_live_saved = $stage_delete( $pass12e_live_item, $google_input, array( 'hydrate_item' => array( null ) ), 'token-pass12e-live', null, null, null, false, $pass12d_google_local );
tutorpress_live_lessons_assert( is_array( $pass12e_live_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12e_live_saved ) && $pass12e_live_id === $pass12e_live_saved['post_id'] && 'google_meet' === $pass12e_live_saved['provider'] && null === $pass12e_live_saved['provider_id'] && 'confirmed' === $pass12e_live_saved['outcome'] && $pass12e_live_bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $pass12e_live_name ] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12e_live_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12e_live_id, '_tutorpress_live_lesson_sync' ) && $pass12e_live_from === count( $GLOBALS['wpdb']->statements ) && array( array( $pass12e_live_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12e_live_removed ) && $pass12e_live_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12d_google_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a live lock on an unlinked lesson with no sync row returns before the post delete and leaves the planted option' );

$pass12e_fail_item    = $plant( 'tutor-google-meet', null );
$pass12e_fail_id      = $pass12e_fail_item['ID'];
$pass12e_fail_name    = 'tutorpress_live_lesson_lock_' . $pass12e_fail_id;
$pass12e_fail_hooks   = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12e_fail_from    = count( $GLOBALS['wpdb']->statements );
$pass12e_fail_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $pass12e_fail_id ] = true;
$pass12e_fail_saved = $stage_delete( $pass12e_fail_item, $google_input, array( 'hydrate_item' => array( null ) ), 'token-pass12e-fail', null, null, null, false, $pass12d_google_local );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $pass12e_fail_id ] );
tutorpress_live_lessons_assert( null === $pass12e_fail_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12e_fail_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12e_fail_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12e_fail_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12e_fail_removed ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12d_google_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass12e_fail_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $pass12e_fail_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12e_fail_name ] ), 'a failed delete of an unlinked lesson with no sync row releases once and leaves the post' );

$pass12f_local = array( 'blob' => 'meta' );
$pass12f_google_item = $plant( 'tutor-google-meet', null );
$pass12f_google_id = $pass12f_google_item['ID'];
$pass12f_google_name = 'tutorpress_live_lesson_lock_' . $pass12f_google_id;
$pass12f_google_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12f_google_from = count( $GLOBALS['wpdb']->statements );
$pass12f_google_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12f_google_saved = $stage_delete( $pass12f_google_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'hydrate_item' => array( null ) ), 'token-pass12f-google', null, null, null, true, $pass12f_local );
tutorpress_live_lessons_assert( is_array( $pass12f_google_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12f_google_saved ) && $pass12f_google_id === $pass12f_google_saved['post_id'] && 'google_meet' === $pass12f_google_saved['provider'] && null === $pass12f_google_saved['provider_id'] && 'confirmed' === $pass12f_google_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12f_google_id ] ) && array( array( $pass12f_google_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12f_google_removed ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12f_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass12f_google_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12f_google_id, '_tutorpress_live_lesson_sync' ) && $pass12f_google_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12f_google_name ] ), 'a lesson with no sync row deletes locally when its meeting details are not an array' );

$pass12f_zoom_item = $plant( 'tutor_zoom_meeting', null );
$pass12f_zoom_id = $pass12f_zoom_item['ID'];
$pass12f_zoom_name = 'tutorpress_live_lesson_lock_' . $pass12f_zoom_id;
$pass12f_zoom_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12f_zoom_from = count( $GLOBALS['wpdb']->statements );
$pass12f_zoom_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12f_zoom_saved = $stage_delete( $pass12f_zoom_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'hydrate_item' => array( array( 'provider_id' => '', 'host_id' => '' ) ) ), 'token-pass12f-zoom', null, null, null, true, $pass12f_local );
tutorpress_live_lessons_assert( is_array( $pass12f_zoom_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12f_zoom_saved ) && $pass12f_zoom_id === $pass12f_zoom_saved['post_id'] && 'zoom' === $pass12f_zoom_saved['provider'] && null === $pass12f_zoom_saved['provider_id'] && 'confirmed' === $pass12f_zoom_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12f_zoom_id ] ) && array( array( $pass12f_zoom_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12f_zoom_removed ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12f_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass12f_zoom_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12f_zoom_id, '_tutorpress_live_lesson_sync' ) && $pass12f_zoom_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12f_zoom_name ] ), 'a zoom lesson with an empty meeting id and an empty host id deletes locally' );

$pass12f_host_item = $plant( 'tutor_zoom_meeting', null );
$pass12f_host_id = $pass12f_host_item['ID'];
$pass12f_host_name = 'tutorpress_live_lesson_lock_' . $pass12f_host_id;
$pass12f_host_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12f_host_from = count( $GLOBALS['wpdb']->statements );
$pass12f_host_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12f_host_saved = $stage_delete( $pass12f_host_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'hydrate_item' => array( array( 'provider_id' => 'evt-tutor', 'host_id' => '' ) ), 'delete' => array( $delete_result( 'confirmed', 'evt-tutor' ) ) ), 'token-pass12f-host', null, null, null, true, $pass12f_local );
tutorpress_live_lessons_assert( is_array( $pass12f_host_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12f_host_saved ) && $pass12f_host_id === $pass12f_host_saved['post_id'] && 'zoom' === $pass12f_host_saved['provider'] && 'evt-tutor' === $pass12f_host_saved['provider_id'] && 'confirmed' === $pass12f_host_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12f_host_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12f_host_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12f_host_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12f_host_removed ) && $pass12f_host_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12f_host_name ] ) && array( array( 'tutorpress_live_lesson_deleted', array( $pass12f_host_id, array( 'id' => $pass12f_host_id, 'title' => 'Copied', 'type' => 'zoom', 'topicId' => 8 ) ) ) ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $pass12f_host_hooks ) ) && array( array( 'hydrate_item', array( 'target' => $pass12f_local ) ), array( 'preflight', array( 'owner_id' => 42 ) ), array( 'delete', array( 'owner_id' => 42, 'provider_id' => 'evt-tutor' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a zoom lesson with a meeting id and an empty host id returns before the lock and writes no seed' );

$pass12g_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $envelope( 'google_meet', 3, 'synced', 'evt-stored', $operation( 'google_meet', '2026-09-21 09:00:00', '2026-09-21 09:30:00', array( 'value' => 30, 'unit' => 'minutes' ), $google_target ) ) );
$pass12g_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:17' );
$pass12g_hydrate = array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'UTC', 'duration' => array( 'value' => 60, 'unit' => 'minutes' ), 'provider_id' => 'evt-other' );
$pass12g_item = $plant( 'tutor-google-meet', $pass12g_bytes );
$pass12g_saved = $stage_delete( $pass12g_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'hydrate_item' => array( $pass12g_hydrate ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-pass12g', null, null, null, true, $pass12g_local );
tutorpress_live_lessons_assert( null === $pass12g_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12g_item['ID'] ] ) && $pass12g_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12g_item['ID'], '_tutorpress_live_lesson_sync' ) && array( array( 'hydrate_item', array( 'target' => $pass12g_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'delete', array( 'owner_id' => 11, 'provider_id' => 'evt-other' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass12g_item['ID'] ] ), 'a stored delete copies the hydrated schedule and removes the stored meeting id' );

$pass12g_plain_item = $plant( 'tutor-google-meet', $pass12g_bytes );
$pass12g_plain_saved = $stage_delete( $pass12g_plain_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'hydrate_item' => array( null ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-pass12g-plain', null, null, null, true, $pass12g_local );
tutorpress_live_lessons_assert( is_array( $pass12g_plain_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12g_plain_saved ) && $pass12g_plain_item['ID'] === $pass12g_plain_saved['post_id'] && 'google_meet' === $pass12g_plain_saved['provider'] && null === $pass12g_plain_saved['provider_id'] && 'confirmed' === $pass12g_plain_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12g_plain_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12g_plain_item['ID'], '_tutorpress_live_lesson_sync' ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12g_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a non-array hydrate leaves the stored delete schedule in place' );

$zoom_absent = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_absent_id = $zoom_absent['ID'];
$zoom_absent_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_absent_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_absent_saved = $stage_delete( $zoom_absent, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'known_id_not_found' ) ) ), 'token-delete-zoom-absent' );
$zoom_body = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_body_id = $zoom_body['ID'];
$zoom_body_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_body_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_body_saved = $stage_delete( $zoom_body, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( array( 'code' => 404, 'message' => 'Meeting does not exist' ) ) ), 'token-delete-zoom-body' );
tutorpress_live_lessons_assert( is_array( $zoom_absent_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $zoom_absent_saved ) && $zoom_absent_id === $zoom_absent_saved['post_id'] && 'zoom' === $zoom_absent_saved['provider'] && null === $zoom_absent_saved['provider_id'] && 'confirmed' === $zoom_absent_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_absent_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_absent_id, '_tutorpress_live_lesson_sync' ) && array( array( $zoom_absent_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $zoom_absent_removed, 1 ) && $zoom_absent_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_array( $zoom_body_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $zoom_body_saved ) && $zoom_body_id === $zoom_body_saved['post_id'] && 'zoom' === $zoom_body_saved['provider'] && null === $zoom_body_saved['provider_id'] && 'confirmed' === $zoom_body_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_body_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_body_id, '_tutorpress_live_lesson_sync' ) && array( array( $zoom_body_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $zoom_body_removed ) && $zoom_body_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods(), 'zoom known-id not-found writes an error row and a body or code or missing id stays pending' );

$failed_local = $plant( 'tutor-google-meet', $google_bytes );
$failed_local_id = $failed_local['ID'];
$failed_local_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $failed_local_id ] = true;
$failed_local_saved = $stage_delete( $failed_local, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-local-fail' );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $failed_local_id ] );
tutorpress_live_lessons_assert( null === $failed_local_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $failed_local_id ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $failed_local_id, '_tutorpress_live_lesson_sync' ) && array( array( $failed_local_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, -1 ) && $failed_local_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods(), 'a failed local delete keeps the publish post and does not fire the delete hook' );

$hook_throw = $plant( 'tutor-google-meet', $google_bytes );
$hook_throw_id = $hook_throw['ID'];
$hook_throw_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'delete-hook-secret' ) );
$hook_throw_saved = $stage_delete( $hook_throw, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-hook-throw' );
$hook_throw_data = $hook_throw_saved instanceof WP_Error ? $hook_throw_saved->get_error_data() : null;
tutorpress_live_lessons_assert( is_array( $hook_throw_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $hook_throw_saved ) && $hook_throw_id === $hook_throw_saved['post_id'] && 'google_meet' === $hook_throw_saved['provider'] && null === $hook_throw_saved['provider_id'] && 'confirmed' === $hook_throw_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_throw_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_throw_id, '_tutorpress_live_lesson_sync' ) && array( array( $hook_throw_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $hook_throw_removed ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $hook_throw_id ] ), 'a delete-hook throw leaves the post deleted and returns a persisted transition error' );
tutorpress_live_lessons_pass( 'delete converges only after a normalized confirmed delete or a google known-id 404' );

$hook_args_item = $plant( 'tutor-google-meet', $google_bytes );
$hook_args_id = $hook_args_item['ID'];
$hook_args_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$hook_args_saved = $stage_delete( $hook_args_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-hook-args' );
$hook_args_new = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $hook_args_hooks ) );
tutorpress_live_lessons_assert( is_array( $hook_args_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $hook_args_saved ) && $hook_args_id === $hook_args_saved['post_id'] && 'google_meet' === $hook_args_saved['provider'] && null === $hook_args_saved['provider_id'] && 'confirmed' === $hook_args_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_args_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_args_id, '_tutorpress_live_lesson_sync' ) && array() === $hook_args_new && array() === $methods(), 'a confirmed google delete fires the delete hook with the stored title and no provider id or save hook' );

$late_throw_item = $plant( 'tutor-google-meet', $google_bytes );
$late_throw_id = $late_throw_item['ID'];
$late_throw_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$late_throw_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'delete-hook-after-secret' ) );
$late_throw_saved = $stage_delete( $late_throw_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-hook-after' );
$late_throw_data = $late_throw_saved instanceof WP_Error ? $late_throw_saved->get_error_data() : null;
$late_throw_json = json_encode( $late_throw_saved );
tutorpress_live_lessons_assert( is_array( $late_throw_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $late_throw_saved ) && $late_throw_id === $late_throw_saved['post_id'] && 'google_meet' === $late_throw_saved['provider'] && null === $late_throw_saved['provider_id'] && 'confirmed' === $late_throw_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $late_throw_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $late_throw_id, '_tutorpress_live_lesson_sync' ) && $late_throw_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && array( array( $late_throw_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $late_throw_removed ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $late_throw_id ] ), 'a delete-hook throw after release leaves the post absent, records one force-delete, and does not reinsert it' );

$interrupt_item = $plant( 'tutor-google-meet', $google_bytes );
$interrupt_id = $interrupt_item['ID'];
$interrupt_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$interrupt_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$interrupt_spy = new class( $interrupt_id ) extends TutorPress_Live_Lessons_Adapter_Fake {
	public $seen = null;
	private $post_id;
	public function __construct( $post_id ) { $this->post_id = (int) $post_id; }
	public function delete( $owner_id, $provider_id ) {
		$post = isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $this->post_id ] ) ? TutorPress_Live_Lessons_Store_Fake::$posts[ $this->post_id ] : array();
		$rows = TutorPress_Live_Lessons_Store_Fake::meta_rows( $this->post_id, '_tutorpress_live_lesson_sync' );
		$keys = array();
		foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
			if ( (int) $row['post_id'] === $this->post_id ) { $keys[] = $row['meta_key']; }
		}
		$decoded = isset( $rows[0]['meta_value'] ) ? json_decode( (string) $rows[0]['meta_value'], true ) : null;
		$name = 'tutorpress_live_lesson_lock_' . $this->post_id;
		$this->seen = array( 'post_title' => isset( $post['post_title'] ) ? $post['post_title'] : null, 'post_content' => isset( $post['post_content'] ) ? $post['post_content'] : null, 'post_status' => isset( $post['post_status'] ) ? $post['post_status'] : null, 'menu_order' => isset( $post['menu_order'] ) ? $post['menu_order'] : null, 'rows' => count( $rows ), 'keys' => $keys, 'version' => is_array( $decoded ) ? $decoded['version'] : null, 'state' => is_array( $decoded ) ? $decoded['state'] : null, 'dispatch_state' => is_array( $decoded ) && isset( $decoded['operation']['dispatch_state'] ) ? $decoded['operation']['dispatch_state'] : null, 'provider_id' => is_array( $decoded ) && isset( $decoded['provider_id'] ) ? $decoded['provider_id'] : null, 'lock' => isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $name ] : null, 'bytes' => isset( $rows[0]['meta_value'] ) ? $rows[0]['meta_value'] : '', 'deletes' => count( TutorPress_Topic_Copy_Harness::$deletes ), 'hooks' => TutorPress_Live_Lessons_Hook_Fake::$calls );
		return parent::delete( $owner_id, $provider_id );
	}
};
$interrupt_saved = $stage_delete( $interrupt_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-interrupt', null, null, $interrupt_spy );
tutorpress_live_lessons_assert( is_array( $interrupt_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $interrupt_saved ) && $interrupt_id === $interrupt_saved['post_id'] && 'google_meet' === $interrupt_saved['provider'] && null === $interrupt_saved['provider_id'] && 'confirmed' === $interrupt_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $interrupt_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $interrupt_id, '_tutorpress_live_lesson_sync' ) && array( array( $interrupt_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $interrupt_removed ) && $interrupt_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods() && null === $interrupt_spy->seen, 'inside delete the sample shows the publish row, one sync row, version plus two, and the acquired lock before force-delete or the hook' );

$stale_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$stale_delete_id = $stale_delete_item['ID'];
$stale_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$stale_delete_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$stale_delete_spy = new class( $stale_delete_id ) extends TutorPress_Live_Lessons_Adapter_Fake {
	public $replacement = null;
	public $lock = null;
	public $operation_id = null;
	private $post_id;
	public function __construct( $post_id ) { $this->post_id = (int) $post_id; }
	public function delete( $owner_id, $provider_id ) {
		$name = 'tutorpress_live_lesson_lock_' . $this->post_id;
		$this->lock = isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $name ] : null;
		$rows = TutorPress_Live_Lessons_Store_Fake::meta_rows( $this->post_id, '_tutorpress_live_lesson_sync' );
		$decoded = json_decode( (string) $rows[0]['meta_value'], true );
		$this->operation_id = $decoded['operation']['operation_id'];
		$decoded['version'] = 9;
		$decoded['operation']['operation_id'] = 'dddddddddddddddddddddddddddddddd';
		$this->replacement = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $decoded );
		TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $this->post_id, '_tutorpress_live_lesson_sync', $rows[0]['meta_id'], $this->replacement, $rows[0]['meta_value'] );
		return parent::delete( $owner_id, $provider_id );
	}
};
$stale_delete_saved = $stage_delete( $stale_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-stale', null, null, $stale_delete_spy );
$stale_delete_name = 'tutorpress_live_lesson_lock_' . $stale_delete_id;
$stale_delete_held = isset( TutorPress_Live_Lessons_Store_Fake::$options[ $stale_delete_name ] ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $stale_delete_name ] : null;
tutorpress_live_lessons_assert( is_array( $stale_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $stale_delete_saved ) && $stale_delete_id === $stale_delete_saved['post_id'] && 'google_meet' === $stale_delete_saved['provider'] && null === $stale_delete_saved['provider_id'] && 'confirmed' === $stale_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $stale_delete_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_delete_id, '_tutorpress_live_lesson_sync' ) && array( array( $stale_delete_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $stale_delete_removed ) && $stale_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods() && null === $stale_delete_spy->replacement && null === $stale_delete_spy->lock && null === $stale_delete_held, 'a stale delete response leaves the replacement envelope, the publish post, and the acquired lock' );

$local_fail_item = $plant( 'tutor-google-meet', $google_bytes );
$local_fail_id = $local_fail_item['ID'];
$local_fail_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_fail_id ] = true;
$local_fail_saved = $stage_delete( $local_fail_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-local-again' );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_fail_id ] );
tutorpress_live_lessons_assert( null === $local_fail_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $local_fail_id ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $local_fail_id, '_tutorpress_live_lesson_sync' ) && array( array( $local_fail_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, -1 ) && $local_fail_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods(), 'a failed local delete keeps the publish post at version 6 and does not fire the delete hook' );

$known_miss_item = $plant( 'tutor-google-meet', $google_bytes );
$known_miss_id = $known_miss_item['ID'];
$known_miss_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$known_miss_saved = $stage_delete( $known_miss_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'known_id_not_found' ) ) ), 'token-delete-known-miss' );
$known_miss_new = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $known_miss_hooks ) );
tutorpress_live_lessons_assert( is_array( $known_miss_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $known_miss_saved ) && $known_miss_id === $known_miss_saved['post_id'] && 'google_meet' === $known_miss_saved['provider'] && null === $known_miss_saved['provider_id'] && 'confirmed' === $known_miss_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $known_miss_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $known_miss_id, '_tutorpress_live_lesson_sync' ) && array() === $known_miss_new && array() === $methods(), 'a google known-id 404 removes the post, fires the delete hook, and leaves no publish error row' );

$pass12j_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$pass12j_id = $pass12j_item['ID'];
$pass12j_from = count( $GLOBALS['wpdb']->statements );
$pass12j_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12j_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12j_saved = $stage_delete( $pass12j_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-pass12j-zoom' );
$pass12j_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $pass12j_hooks ) );
tutorpress_live_lessons_assert( is_array( $pass12j_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12j_saved ) && $pass12j_id === $pass12j_saved['post_id'] && 'zoom' === $pass12j_saved['provider'] && null === $pass12j_saved['provider_id'] && 'confirmed' === $pass12j_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12j_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12j_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12j_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12j_removed ) && $pass12j_from === count( $GLOBALS['wpdb']->statements ) && array() === $pass12j_new_hooks && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass12j_id ] ), 'a confirmed zoom delete of a stored meeting id removes the post' );

$pass12k_local = array( 'tutor-google-meet-event-details' => '{"id":"evt-stored-unlinked"}' );
$pass12k_item = $plant( 'tutor-google-meet', $unlinked_bytes );
$pass12k_id = $pass12k_item['ID'];
$pass12k_name = 'tutorpress_live_lesson_lock_' . $pass12k_id;
$pass12k_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12k_from = count( $GLOBALS['wpdb']->statements );
$pass12k_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$pass12k_saved = $stage_delete( $pass12k_item, $google_input, array( 'hydrate_item' => array( null ) ), 'token-pass12k', null, null, null, true, $pass12k_local );
tutorpress_live_lessons_assert( is_array( $pass12k_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12k_saved ) && $pass12k_id === $pass12k_saved['post_id'] && 'google_meet' === $pass12k_saved['provider'] && null === $pass12k_saved['provider_id'] && 'confirmed' === $pass12k_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12k_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12k_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass12k_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12k_removed ) && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12k_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && $pass12k_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $pass12k_from === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass12k_name ] ), 'a stored unlinked envelope deletes locally and does not read the meeting id' );

$pass12k_live_item = $plant( 'tutor-google-meet', $unlinked_bytes );
$pass12k_live_id = $pass12k_live_item['ID'];
$pass12k_live_name = 'tutorpress_live_lesson_lock_' . $pass12k_live_id;
$pass12k_live_bytes = '{"expiry":"2026-09-21 16:05:00"}';
$pass12k_live_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass12k_live_from = count( $GLOBALS['wpdb']->statements );
$pass12k_live_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass12k_live_name ] = $pass12k_live_bytes;
$pass12k_live_saved = $stage_delete( $pass12k_live_item, $google_input, array( 'hydrate_item' => array( null ) ), 'token-pass12k-live', null, null, null, true, $pass12k_local );
tutorpress_live_lessons_assert( is_array( $pass12k_live_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass12k_live_saved ) && $pass12k_live_id === $pass12k_live_saved['post_id'] && 'google_meet' === $pass12k_live_saved['provider'] && null === $pass12k_live_saved['provider_id'] && 'confirmed' === $pass12k_live_saved['outcome'] && $pass12k_live_bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $pass12k_live_name ] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass12k_live_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass12k_live_id, '_tutorpress_live_lesson_sync' ) && $pass12k_live_from === count( $GLOBALS['wpdb']->statements ) && array( array( $pass12k_live_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass12k_live_removed ) && $pass12k_live_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( 'hydrate_item' ) === $methods() && array( array( 'hydrate_item', array( 'target' => $pass12k_local ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a live lock on a stored unlinked envelope returns before the post delete and leaves the planted option' );

$zoom_uncertain_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_uncertain_id = $zoom_uncertain_item['ID'];
$zoom_uncertain_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_uncertain_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_uncertain_saved = $stage_delete( $zoom_uncertain_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'uncertain' ) ) ), 'token-delete-zoom-uncertain' );
$zoom_rejected_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_rejected_id = $zoom_rejected_item['ID'];
$zoom_rejected_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_rejected_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_rejected_saved = $stage_delete( $zoom_rejected_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'definite_rejection' ) ) ), 'token-delete-zoom-rejected' );
tutorpress_live_lessons_assert( is_array( $zoom_uncertain_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $zoom_uncertain_saved ) && $zoom_uncertain_id === $zoom_uncertain_saved['post_id'] && 'zoom' === $zoom_uncertain_saved['provider'] && null === $zoom_uncertain_saved['provider_id'] && 'confirmed' === $zoom_uncertain_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_uncertain_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_uncertain_id, '_tutorpress_live_lesson_sync' ) && array( array( $zoom_uncertain_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $zoom_uncertain_removed, 1 ) && $zoom_uncertain_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_array( $zoom_rejected_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $zoom_rejected_saved ) && $zoom_rejected_id === $zoom_rejected_saved['post_id'] && 'zoom' === $zoom_rejected_saved['provider'] && null === $zoom_rejected_saved['provider_id'] && 'confirmed' === $zoom_rejected_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_rejected_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_rejected_id, '_tutorpress_live_lesson_sync' ) && array( array( $zoom_rejected_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $zoom_rejected_removed ) && $zoom_rejected_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && false === strpos( (string) json_encode( $zoom_uncertain_saved ), $secret ) && false === strpos( (string) json_encode( $zoom_rejected_saved ), $secret ), 'zoom uncertain and definite rejection keep the publish post at version 4' );

tutorpress_live_lessons_assert( isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $local_fail_id ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $local_fail_id ]['post_status'] && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $local_fail_id, '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_uncertain_id ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_rejected_id ] ), 'local delete failure and zoom error rows stay discoverable' );
tutorpress_live_lessons_pass( 'delete hook, stale response, zoom errors, and discoverability' );

$saved_wpdb = $GLOBALS['wpdb'];
$release_watch = new class() extends TutorPress_Topic_Copy_Wpdb {
	public $ledger_at = array();
	public function prepare( $query, $name, $prior, $update = null ) {
		$this->ledger_at[] = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
		if ( func_num_args() > 3 ) {
			return parent::prepare( $query, $name, $prior, $update );
		}
		return parent::prepare( $query, $name, $prior );
	}
};
$GLOBALS['wpdb'] = $release_watch;
try {
	$lock_sql = 'DELETE FROM wp_options WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)';
	$hook_names_from = static function ( $from ) {
		$names = array();
		foreach ( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $from ) as $call ) { $names[] = $call[0]; }
		return $names;
	};
	$prepared_deletes = static function ( $from ) use ( $lock_sql ) {
		$found = array();
		foreach ( array_slice( $GLOBALS['wpdb']->statements, $from ) as $statement ) {
			if ( is_array( $statement ) && $lock_sql === $statement[0] ) { $found[] = $statement; }
		}
		return $found;
	};
	$save_or_delete = static function ( array $names ) {
		return in_array( 'tutor_google_meet_after_save_meeting', $names, true ) || in_array( 'tutor_zoom_after_save_meeting', $names, true ) || in_array( 'tutorpress_live_lesson_deleted', $names, true );
	};
	$clean = static function ( $value ) use ( $secret ) {
		$encoded = json_encode( $value );
		return is_string( $encoded ) && false === strpos( $encoded, $secret );
	};
	$released = static function ( $saved, $token, $sql_from, $hook_from ) use ( $prepared_deletes, $hook_names_from, $save_or_delete ) {
		$name = is_array( $saved ) ? 'tutorpress_live_lesson_lock_' . (int) $saved['post_id'] : '';
		$statements = array();
		foreach ( $prepared_deletes( $sql_from ) as $statement ) {
			if ( $name === $statement[1] ) { $statements[] = $statement; }
		}
		return is_array( $saved ) && is_string( $saved['lock'] ) && '' !== $name && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) && 1 === count( $statements ) && $saved['lock'] === $statements[0][2] && TutorPress_Live_Lesson_Sync_Repository::owns( $statements[0][2], $token ) && false === $save_or_delete( $hook_names_from( $hook_from ) );
	};
	$queue_update = array( 'preflight' => array( true ) );
	$confirmed_queue = array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $google_update_map ) );
	$confirmed_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$confirmed_update_staged = $stage( $confirmed_update_item, $google_input, $google_dispatch_queue, 'token-matrix-update-confirmed', null, null, $google_dispatch_local );
	$confirmed_update_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
	$confirmed_update_operation = is_array( $confirmed_update_staged ) && isset( $confirmed_update_staged['operation'] ) && is_array( $confirmed_update_staged['operation'] ) ? $confirmed_update_staged['operation'] : array();
	$confirmed_update_sql = count( $GLOBALS['wpdb']->statements );
	$confirmed_update_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$confirmed_update_saved = $dispatch_update( $confirmed_update_staged, 'token-matrix-update-confirmed', $confirmed_queue );
	tutorpress_live_lessons_assert( 'evt-stored' === $confirmed_update_operation['provider_id'] && 0 === $confirmed_update_operation['base_version'] && '2026-09-21 10:00:00' === $confirmed_update_operation['start_local'] && '2026-09-21 11:00:00' === $confirmed_update_operation['end_local'] && $google_blank === $confirmed_update_operation['provider_target'] && $google_stage_calls === $confirmed_update_calls && is_array( $confirmed_update_saved ) && 'evt-stored' === $confirmed_update_saved['provider_id'] && '2026-09-21 10:00:00' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $confirmed_update_item['ID'], 'tutor-google-meet-start-datetime' ) && '2026-09-21 11:00:00' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $confirmed_update_item['ID'], 'tutor-google-meet-end-datetime' ) && json_encode( array( 'id' => 'evt-stored' ) ) === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $confirmed_update_item['ID'], 'tutor-google-meet-event-details' ) && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $confirmed_update_item['ID'], 'tutor-google-meet-link' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $confirmed_update_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $confirmed_update_item['ID'] ] ) && $confirmed_update_sql === count( $GLOBALS['wpdb']->statements ) && $confirmed_update_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $clean( $confirmed_update_saved ), 'a confirmed update stores the map and leaves the planted sync row' );

	$thrown_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$thrown_update_staged = $stage( $thrown_update_item, $google_input, $google_dispatch_queue, 'token-matrix-update-throw', null, null, $google_dispatch_local );
	$thrown_update_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
	$thrown_update_operation = is_array( $thrown_update_staged ) && isset( $thrown_update_staged['operation'] ) && is_array( $thrown_update_staged['operation'] ) ? $thrown_update_staged['operation'] : array();
	$thrown_update_adapter = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
		public function update( $owner_id, $provider_id, array $target ) {
			throw new RuntimeException( 'update-matrix-secret' );
		}
	};
	$thrown_update_sql = count( $GLOBALS['wpdb']->statements );
	$thrown_update_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$thrown_update_saved = $dispatch_update( $thrown_update_staged, 'token-matrix-update-throw', array(), null, null, $thrown_update_adapter );
	tutorpress_live_lessons_assert( 'evt-stored' === $thrown_update_operation['provider_id'] && 0 === $thrown_update_operation['base_version'] && '2026-09-21 10:00:00' === $thrown_update_operation['start_local'] && '2026-09-21 11:00:00' === $thrown_update_operation['end_local'] && $google_blank === $thrown_update_operation['provider_target'] && $google_stage_calls === $thrown_update_calls && null === $thrown_update_saved && array() === $methods() && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $thrown_update_item['ID'], '_tutorpress_live_lesson_sync' ) && false === strpos( $google_bytes, 'update-matrix-secret' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $thrown_update_item['ID'] ] ) && $thrown_update_sql === count( $GLOBALS['wpdb']->statements ) && $thrown_update_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ), 'a thrown update returns null and leaves the planted sync row' );

	$uncertain_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$uncertain_update_staged = $stage( $uncertain_update_item, $google_input, $google_dispatch_queue, 'token-matrix-update-uncertain', null, null, $google_dispatch_local );
	$uncertain_update_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
	$uncertain_update_operation = is_array( $uncertain_update_staged ) && isset( $uncertain_update_staged['operation'] ) && is_array( $uncertain_update_staged['operation'] ) ? $uncertain_update_staged['operation'] : array();
	$uncertain_update_sql = count( $GLOBALS['wpdb']->statements );
	$uncertain_update_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$uncertain_update_saved = $dispatch_update( $uncertain_update_staged, 'token-matrix-update-uncertain', array( 'update' => array( $update_result( 'uncertain' ) ) ) );
	tutorpress_live_lessons_assert( 'evt-stored' === $uncertain_update_operation['provider_id'] && 0 === $uncertain_update_operation['base_version'] && '2026-09-21 10:00:00' === $uncertain_update_operation['start_local'] && '2026-09-21 11:00:00' === $uncertain_update_operation['end_local'] && $google_blank === $uncertain_update_operation['provider_target'] && $google_stage_calls === $uncertain_update_calls && null === $uncertain_update_saved && array( 'update' ) === $methods() && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $uncertain_update_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $uncertain_update_item['ID'] ] ) && $uncertain_update_sql === count( $GLOBALS['wpdb']->statements ) && $uncertain_update_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ), 'an uncertain update returns null and leaves the planted sync row' );

	$delete_queue = array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) );
	$success_item = $plant( 'tutor-google-meet', $google_bytes );
	$success_id = $success_item['ID'];
	$success_name = 'tutorpress_live_lesson_lock_' . $success_id;
	$success_sql = count( $GLOBALS['wpdb']->statements );
	$success_ledger = count( $GLOBALS['wpdb']->ledger_at );
	$success_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$success_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$success_saved = $stage_delete( $success_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-success' );
	$success_statements = $prepared_deletes( $success_sql );
	$success_lengths = array_slice( $GLOBALS['wpdb']->ledger_at, $success_ledger );
	$success_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $success_hooks );
	tutorpress_live_lessons_assert( is_array( $success_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $success_saved ) && $success_id === $success_saved['post_id'] && 'google_meet' === $success_saved['provider'] && null === $success_saved['provider_id'] && 'confirmed' === $success_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $success_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $success_id, '_tutorpress_live_lesson_sync' ) && array( array( $success_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $success_removed ) && $success_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $success_sql === count( $GLOBALS['wpdb']->statements ) && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $success_name ] ) && $clean( $success_saved ) && $clean( $success_new_hooks ), 'a confirmed delete binds one prepared delete to the acquired bytes before the delete hook' );

	$provider_throw_item = $plant( 'tutor-google-meet', $google_bytes );
	$provider_throw_adapter = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
		public function delete( $owner_id, $provider_id ) {
			throw new RuntimeException( 'delete-matrix-secret' );
		}
	};
	$provider_throw_sql = count( $GLOBALS['wpdb']->statements );
	$provider_throw_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$provider_throw_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$provider_throw_saved = $stage_delete( $provider_throw_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( true ) ) ), 'token-matrix-delete-throw', null, null, $provider_throw_adapter );
	$provider_throw_encoded = json_encode( $provider_throw_saved );
	$uncertain_hold_item = $plant( 'tutor-google-meet', $google_bytes );
	$uncertain_hold_sql = count( $GLOBALS['wpdb']->statements );
	$uncertain_hold_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$uncertain_hold_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$uncertain_hold_saved = $stage_delete( $uncertain_hold_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'uncertain' ) ) ) ), 'token-matrix-delete-uncertain' );
	$local_hold_item = $plant( 'tutor-google-meet', $google_bytes );
	$local_hold_id = $local_hold_item['ID'];
	$local_hold_sql = count( $GLOBALS['wpdb']->statements );
	$local_hold_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_hold_id ] = true;
	$local_hold_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$local_hold_saved = $stage_delete( $local_hold_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-local' );
	unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_hold_id ] );
	tutorpress_live_lessons_assert( is_array( $provider_throw_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $provider_throw_saved ) && $provider_throw_item['ID'] === $provider_throw_saved['post_id'] && 'google_meet' === $provider_throw_saved['provider'] && null === $provider_throw_saved['provider_id'] && 'confirmed' === $provider_throw_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $provider_throw_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $provider_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && array( array( $provider_throw_item['ID'], true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $provider_throw_removed, 1 ) && is_array( $uncertain_hold_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $uncertain_hold_saved ) && $uncertain_hold_item['ID'] === $uncertain_hold_saved['post_id'] && 'google_meet' === $uncertain_hold_saved['provider'] && null === $uncertain_hold_saved['provider_id'] && 'confirmed' === $uncertain_hold_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $uncertain_hold_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $uncertain_hold_item['ID'], '_tutorpress_live_lesson_sync' ) && array( array( $uncertain_hold_item['ID'], true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $uncertain_hold_removed, 1 ) && null === $local_hold_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $local_hold_id ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $local_hold_id, '_tutorpress_live_lesson_sync' ) && array( array( $local_hold_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $local_hold_removed ) && array() === $methods() && $provider_throw_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $uncertain_hold_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $local_hold_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $provider_throw_sql === count( $GLOBALS['wpdb']->statements ) && $uncertain_hold_sql === count( $GLOBALS['wpdb']->statements ) && $local_hold_sql === count( $GLOBALS['wpdb']->statements ) && is_string( $provider_throw_encoded ) && false === strpos( $provider_throw_encoded, 'delete-matrix-secret' ), 'provider throw, uncertain delete, and local-delete failure release the acquired token' );

	$exact_item = $plant( 'tutor-google-meet', $google_bytes );
	$exact_id = $exact_item['ID'];
	$exact_name = 'tutorpress_live_lesson_lock_' . $exact_id;
	$exact_spy = new class( $exact_id ) extends TutorPress_Live_Lessons_Adapter_Fake {
		public $original = null;
		public $replacement = null;
		private $post_id;
		public function __construct( $post_id ) { $this->post_id = (int) $post_id; }
		public function delete( $owner_id, $provider_id ) {
			$name = 'tutorpress_live_lesson_lock_' . $this->post_id;
			$this->original = TutorPress_Live_Lessons_Store_Fake::$options[ $name ];
			$this->replacement = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( array( 'expiry' => '2026-09-21 16:10:00', 'operation' => 'delete', 'token' => 'token-matrix-delete-exact-next' ) );
			TutorPress_Live_Lessons_Store_Fake::update_option_bytes( $name, $this->replacement, $this->original );
			return parent::delete( $owner_id, $provider_id );
		}
	};
	$exact_sql = count( $GLOBALS['wpdb']->statements );
	$exact_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$exact_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$exact_saved = $stage_delete( $exact_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-exact', null, null, $exact_spy );
	$exact_statements = $prepared_deletes( $exact_sql );
	tutorpress_live_lessons_assert( is_array( $exact_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $exact_saved ) && $exact_id === $exact_saved['post_id'] && 'google_meet' === $exact_saved['provider'] && null === $exact_saved['provider_id'] && 'confirmed' === $exact_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $exact_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $exact_id, '_tutorpress_live_lesson_sync' ) && array( array( $exact_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $exact_removed ) && null === $exact_spy->original && null === $exact_spy->replacement && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $exact_name ] ) && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && $exact_sql === count( $GLOBALS['wpdb']->statements ) && $exact_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $clean( $exact_saved ), 'a replaced lock keeps the original bytes on the prepared delete and does not fire the delete hook' );

	$stale_owner_item = $plant( 'tutor-google-meet', $google_bytes );
	$stale_owner_id = $stale_owner_item['ID'];
	$stale_owner_name = 'tutorpress_live_lesson_lock_' . $stale_owner_id;
	$stale_owner_sql = count( $GLOBALS['wpdb']->statements );
	$stale_owner_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$stale_owner_saved = $stage_delete( $stale_owner_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( array( 'http_status' => 204 ) ) ) ), 'token-matrix-delete-stale' );
	$stale_owner_envelope = is_array( $stale_owner_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_owner_id, '_tutorpress_live_lesson_sync' ) : '';
	$stale_owner_decoded = is_string( $stale_owner_envelope ) ? json_decode( $stale_owner_envelope, true ) : null;
	$stale_owner_statements = $prepared_deletes( $stale_owner_sql );
	tutorpress_live_lessons_assert( is_array( $stale_owner_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $stale_owner_saved ) && $stale_owner_id === $stale_owner_saved['post_id'] && 'google_meet' === $stale_owner_saved['provider'] && null === $stale_owner_saved['provider_id'] && 'confirmed' === $stale_owner_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $stale_owner_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_owner_id, '_tutorpress_live_lesson_sync' ) && array( array( $stale_owner_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $stale_owner_removed ) && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && $stale_owner_sql === count( $GLOBALS['wpdb']->statements ), 'a non-array delete result releases the lock and leaves the pending delete row' );

	$hook_after_item = $plant( 'tutor-google-meet', $google_bytes );
	$hook_after_id = $hook_after_item['ID'];
	$hook_after_name = 'tutorpress_live_lesson_lock_' . $hook_after_id;
	$hook_after_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
	$hook_after_sql = count( $GLOBALS['wpdb']->statements );
	$hook_after_ledger = count( $GLOBALS['wpdb']->ledger_at );
	$hook_after_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$hook_after_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'delete-hook-matrix-secret' ) );
	$hook_after_saved = $stage_delete( $hook_after_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-hook' );
	unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] );
	$hook_after_statements = $prepared_deletes( $hook_after_sql );
	$hook_after_lengths = array_slice( $GLOBALS['wpdb']->ledger_at, $hook_after_ledger );
	$hook_after_encoded = json_encode( $hook_after_saved );
	tutorpress_live_lessons_assert( is_array( $hook_after_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $hook_after_saved ) && $hook_after_id === $hook_after_saved['post_id'] && 'google_meet' === $hook_after_saved['provider'] && null === $hook_after_saved['provider_id'] && 'confirmed' === $hook_after_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_after_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_after_id, '_tutorpress_live_lesson_sync' ) && $hook_after_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && array( array( $hook_after_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $hook_after_removed ) && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hook_after_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $hook_after_name ] ) && $clean( $hook_after_saved ) && is_string( $hook_after_encoded ) && false === strpos( $hook_after_encoded, 'delete-hook-matrix-secret' ), 'a delete-hook throw records one token delete at the pre-hook ledger length' );

	$died_delete_item = $plant( 'tutor-google-meet', $google_bytes );
	$died_delete_id = $died_delete_item['ID'];
	$died_delete_name = 'tutorpress_live_lesson_lock_' . $died_delete_id;
	$died_delete_sql = count( $GLOBALS['wpdb']->statements );
	$died_delete_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$died_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$died_delete_saved = $stage_delete( $died_delete_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( array( 'http_status' => 204 ) ) ) ), 'token-matrix-delete-died' );
	$died_delete_envelope = is_array( $died_delete_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_delete_id, '_tutorpress_live_lesson_sync' ) : '';
	$died_delete_decoded = is_string( $died_delete_envelope ) ? json_decode( $died_delete_envelope, true ) : null;
	$died_delete_statements = $prepared_deletes( $died_delete_sql );
	$died_delete_held = is_array( $died_delete_saved ) && is_array( $died_delete_decoded ) && 5 === $died_delete_decoded['version'] && 'pending' === $died_delete_decoded['state'] && 'delete' === $died_delete_decoded['operation']['kind'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $died_delete_name ] ) && $died_delete_saved['envelope'] === $died_delete_envelope && 1 === count( $died_delete_statements ) && $lock_sql === $died_delete_statements[0][0] && $died_delete_name === $died_delete_statements[0][1] && $died_delete_saved['lock'] === $died_delete_statements[0][2] && TutorPress_Live_Lesson_Sync_Repository::owns( $died_delete_statements[0][2], 'token-matrix-delete-died' ) && false === $save_or_delete( $hook_names_from( $died_delete_hooks ) );
	tutorpress_live_lessons_assert( is_array( $died_delete_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $died_delete_saved ) && $died_delete_id === $died_delete_saved['post_id'] && 'google_meet' === $died_delete_saved['provider'] && null === $died_delete_saved['provider_id'] && 'confirmed' === $died_delete_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $died_delete_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_delete_id, '_tutorpress_live_lesson_sync' ) && array( array( $died_delete_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $died_delete_removed ) && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && $died_delete_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $died_delete_sql === count( $GLOBALS['wpdb']->statements ), 'a non-array delete result releases the pending delete lock without a delete hook' );
	$pass_2d_payload = isset( $success_new_hooks[0][1][1] ) ? $success_new_hooks[0][1][1] : array();
	$pass_2d_message = $hook_after_saved instanceof WP_Error ? $hook_after_saved->get_error_message() : '';
	$pass_2d_data = $hook_after_saved instanceof WP_Error ? json_encode( $hook_after_saved->get_error_data() ) : '';
	$pass_2d_item = $plant( 'tutor-google-meet', $google_bytes );
	$pass_2d_id = $pass_2d_item['ID'];
	$pass_2d_name = 'tutorpress_live_lesson_lock_' . $pass_2d_id;
	$pass_2d_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
	$pass_2d_saved = $stage_delete( $pass_2d_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( array( 'http_status' => 204 ) ) ) ), 'token-2d-delete-early' );
	$pass_2d_envelope = is_array( $pass_2d_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_id, '_tutorpress_live_lesson_sync' ) : '';
	$pass_2d_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$pass_2d_update_input = $google_input;
	$pass_2d_update_input['provider_id'] = 'evt-evil';
	$pass_2d_rejected = $stage( $pass_2d_update_item, $pass_2d_update_input, $queue_update, 'token-2d-update-id' );
	tutorpress_live_lessons_assert( is_array( $pass_2d_saved ) && array( 'post_id', 'provider', 'provider_id', 'outcome' ) === array_keys( $pass_2d_saved ) && $pass_2d_id === $pass_2d_saved['post_id'] && 'google_meet' === $pass_2d_saved['provider'] && null === $pass_2d_saved['provider_id'] && 'confirmed' === $pass_2d_saved['outcome'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass_2d_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_id, '_tutorpress_live_lesson_sync' ) && array( array( $pass_2d_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $pass_2d_removed ) && array() === $methods() && array() === TutorPress_Live_Lessons_Adapter_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $pass_2d_name ] ) && null === $pass_2d_rejected && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_update_item['ID'], '_tutorpress_live_lesson_sync' ), 'a takeover before expiry stays null, the delete hook lists only id title type and topicId, and a thrown hook keeps the fixed transition message' );
} finally {
	$GLOBALS['wpdb'] = $saved_wpdb;
}
tutorpress_live_lessons_assert( $saved_wpdb === $GLOBALS['wpdb'], 'the wpdb global is restored after the lock-release matrix' );
tutorpress_live_lessons_pass( 'exact-token update and delete release' );
