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
$stage = static function ( array $item, array $input, array $queue, $token, $can = null, $update = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::stage_update( $item, $topic, $course, null === $can ? $allow : $can, $input, $adapter, $now, $token, $sync_read, null === $update ? $sync_update : $update, $sync_affected );
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
$google_saved = $stage( $google_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-google' );
$google_decoded = is_array( $google_saved ) ? json_decode( $google_saved['envelope'], true ) : null;
$google_operation = is_array( $google_decoded ) && isset( $google_decoded['operation'] ) ? $google_decoded['operation'] : array();
$google_lock = is_array( $google_saved ) ? json_decode( $google_saved['lock'], true ) : null;
$google_body = is_array( $google_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_item['ID'], '_tutorpress_live_lesson_sync' ) : '';
tutorpress_live_lessons_assert( is_array( $google_saved ) && is_array( $google_decoded ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $google_decoded ) ) && $google_item['ID'] === $google_saved['post_id'] && $google_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 'Copied' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_title'] && 4 === (int) TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['menu_order'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_item['ID'] ]['post_status'] && 1 === $google_decoded['schema_version'] && 4 === $google_decoded['version'] && 'pending' === $google_decoded['state'] && 'google_meet' === $google_decoded['provider'] && 11 === $google_decoded['owner_id'] && 'evt-stored' === $google_decoded['provider_id'] && 'create' === $google_decoded['provenance'] && null === $google_decoded['error'] && null === $google_decoded['warning'] && false === $google_decoded['reconciliation_required'] && 'update' === $google_operation['kind'] && 3 === $google_operation['base_version'] && 'not_dispatched' === $google_operation['dispatch_state'] && 'evt-stored' === $google_operation['provider_id'] && null === $google_operation['delete_hook'] && 4 === $google_operation['menu_order'] && 'Standup' === $google_operation['title'] && 'Notes' === $google_operation['content'] && '2026-09-21 10:00:00' === $google_operation['start_local'] && '2026-09-21 11:00:00' === $google_operation['end_local'] && 'America/New_York' === $google_operation['timezone'] && array( 'unit' => 'minutes', 'value' => 60 ) === $google_operation['duration'] && $google_target === $google_operation['provider_target'] && '2026-09-21 16:00:00' === $google_operation['started_at'] && 1 === preg_match( '/\A[0-9a-f]{32}\z/', $google_operation['operation_id'] ) && $google_body === $google_saved['envelope'] && 'not_dispatched' === $google_saved['dispatch_state'] && is_array( $google_lock ) && 'update' === $google_lock['operation'] && '2026-09-21 16:05:00' === $google_lock['expiry'] && TutorPress_Live_Lesson_Sync_Repository::owns( $google_saved['lock'], 'token-update-google' ) && $google_saved['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_item['ID'] ] && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $google_item['ID'] ) && $google_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $google_ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && false === strpos( (string) $google_body, $secret ) && false === strpos( (string) $google_body, 'hidden-pass' ) && false === strpos( (string) $google_body, 'student@example.com' ) && false === strpos( (string) $google_body, 'token-update-google' ) && false === strpos( (string) $google_body, 'host-ignored' ) && false === strpos( (string) $google_body, 'evt-evil' ), 'an unchanged past google schedule stages version 4 without validate_host' );

$again = $stage( $google_item, $google_input, array(), 'token-update-again' );
tutorpress_live_lessons_assert( null === $again && array() === $methods() && TutorPress_Live_Lesson_Sync_Repository::owns( $google_saved['lock'], 'token-update-google' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_again' ] ), 'a pending row is rejected before preflight and the update lock stays' );

$future_item = $plant( 'tutor-google-meet', $google_bytes );
$future_input = $google_input;
$future_input['start'] = '2026-09-21 15:04:00';
$future_input['end'] = '2026-09-21 16:04:00';
$future_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$future_saved = $stage( $future_item, $future_input, array( 'preflight' => array( true ) ), 'token-update-future' );
$future_decoded = is_array( $future_saved ) ? json_decode( $future_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $future_decoded ) && 4 === $future_decoded['version'] && 'pending' === $future_decoded['state'] && 'update' === $future_decoded['operation']['kind'] && '2026-09-21 15:04:00' === $future_decoded['operation']['start_local'] && '2026-09-21 16:04:00' === $future_decoded['operation']['end_local'] && array( 'unit' => 'minutes', 'value' => 60 ) === $future_decoded['operation']['duration'] && 'evt-stored' === $future_decoded['provider_id'] && $future_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && array( 'preflight' ) === $methods() && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $future_item['ID'] ] ), 'a changed future google schedule stages the new wall clock' );

$zoom_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$zoom_saved = $stage( $zoom_item, $zoom_input, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-update-zoom' );
$zoom_decoded = is_array( $zoom_saved ) ? json_decode( $zoom_saved['envelope'], true ) : null;
$zoom_operation = is_array( $zoom_decoded ) && isset( $zoom_decoded['operation'] ) ? $zoom_decoded['operation'] : array();
$zoom_body = is_array( $zoom_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_item['ID'], '_tutorpress_live_lesson_sync' ) : '';
tutorpress_live_lessons_assert( is_array( $zoom_decoded ) && 2 === $zoom_decoded['version'] && 'pending' === $zoom_decoded['state'] && 'zoom' === $zoom_decoded['provider'] && 42 === $zoom_decoded['owner_id'] && 'evt-stored' === $zoom_decoded['provider_id'] && 'create' === $zoom_decoded['provenance'] && 'update' === $zoom_operation['kind'] && 1 === $zoom_operation['base_version'] && 'not_dispatched' === $zoom_operation['dispatch_state'] && 'evt-stored' === $zoom_operation['provider_id'] && array( 'unit' => 'hours', 'value' => 2 ) === $zoom_operation['duration'] && '2026-09-21 10:00:00' === $zoom_operation['start_local'] && '2026-09-21 12:00:00' === $zoom_operation['end_local'] && $zoom_owner_target === $zoom_operation['provider_target'] && null === $zoom_operation['delete_hook'] && $zoom_body === $zoom_saved['envelope'] && $zoom_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && array( 'preflight', 'validate_host' ) === $methods() && array( array( 'preflight', array( 'owner_id' => 42 ) ), array( 'validate_host', array( 'owner_id' => 42, 'host_id' => 'host-1' ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $zoom_item['ID'] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_saved['lock'], 'token-update-zoom' ) && false === strpos( (string) $zoom_body, $secret ) && false === strpos( (string) $zoom_body, 'hidden-pass' ), 'an unchanged past zoom schedule keeps the typed hour duration' );

$zoom_future = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_change = $zoom_input;
$zoom_change['start'] = '2026-09-21 15:04:00';
$zoom_change['duration'] = array( 'value' => 3, 'unit' => 'hours' );
$zoom_changed = $stage( $zoom_future, $zoom_change, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-update-zoom-future' );
$zoom_changed_decoded = is_array( $zoom_changed ) ? json_decode( $zoom_changed['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $zoom_changed_decoded ) && array( 'unit' => 'hours', 'value' => 3 ) === $zoom_changed_decoded['operation']['duration'] && '2026-09-21 15:04:00' === $zoom_changed_decoded['operation']['start_local'] && '2026-09-21 18:04:00' === $zoom_changed_decoded['operation']['end_local'] && 'evt-stored' === $zoom_changed_decoded['provider_id'] && 2 === $zoom_changed_decoded['version'], 'a changed zoom schedule keeps the typed hour unit' );

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
list( $error_saved, $error_id, $error_kept, $error_locked, $error_methods ) = $reject( $error_bytes );
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
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $null_operation ) ) && null === $pending_saved && null === $unlinked_saved && null === $error_saved && null === $null_saved && null === $identity_saved && null === $mismatch_saved && null === $zero_saved && null === $text_saved && null === $absent_saved && null === $second_saved && false === $pending_locked && false === $unlinked_locked && false === $error_locked && false === $null_locked && false === $empty_locked && false === $mismatch_locked && false === $zero_locked && false === $text_locked && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $absent['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $second['ID'] ] ) && $pending_bytes === $pending_kept && $null_operation_bytes === $null_kept && $null_identity_bytes === $empty_kept && 2 === count( TutorPress_Live_Lessons_Store_Fake::meta_rows( $second['ID'], '_tutorpress_live_lesson_sync' ) ) && array() === $pending_methods && array() === $unlinked_methods && array() === $error_methods && array() === $null_methods && array() === $identity_methods && array() === $mismatch_methods && array() === $zero_methods && array() === $text_methods && array() === $methods(), 'pending, unlinked, error, a null operation, a missing identity, and a second row acquire no lock' );

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
tutorpress_live_lessons_assert( null === $swap_saved && ! ( $swap_saved instanceof WP_Error ) && 1 === count( $swap_statements ) && is_array( $swap_statements[0] ) && false !== strpos( $swap_statements[0][0], 'CONVERT(option_value USING BINARY)' ) && 'tutorpress_live_lesson_lock_' . $swap_item['ID'] === $swap_statements[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $swap_statements[0][2], 'token-update-swap' ) && $swap_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $swap_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $swap_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $swap_item['ID'] ] ) && array( 'preflight' ) === $methods(), 'a failed update exchange releases the exact token and leaves the synced post' );
tutorpress_live_lessons_pass( 'synced update stages a pending snapshot' );

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
$google_dispatch_staged = $stage( $google_dispatch_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-update-google' );
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
$google_final = is_array( $google_dispatched ) ? json_decode( $google_dispatched['envelope'], true ) : null;
$google_final_operation = is_array( $google_final ) && isset( $google_final['operation'] ) ? $google_final['operation'] : array();
$google_update_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] : array();
$google_compare_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['target'] : array();
tutorpress_live_lessons_assert( is_array( $google_spy->seen ) && 5 === $google_spy->seen['version'] && 'pending' === $google_spy->seen['state'] && 'dispatch_possible' === $google_spy->seen['operation']['dispatch_state'] && 'evt-stored' === $google_spy->seen['provider_id'] && is_array( $google_final ) && 6 === $google_final['version'] && 'synced' === $google_final['state'] && 'update' === $google_final['provenance'] && 'evt-stored' === $google_final['provider_id'] && null === $google_final['error'] && false === $google_final['reconciliation_required'] && 'confirmed' === $google_final_operation['dispatch_state'] && 'Standup' === $google_final_operation['title'] && 'evt-stored' === $google_final_operation['provider_id'] && array( 'update', array( 'owner_id' => 11, 'provider_id' => 'evt-stored', 'target' => $google_update_target ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls[0] && $google_update_target === $google_compare_target && 'evt-stored' === $google_update_target['provider_id'] && '2026-09-21 10:00:00' === $google_update_target['start_local'] && 'No' === $google_update_target['add_enrolled_students'] && array( 'update', 'compare_target', 'metadata_map' ) === $methods() && 'https://meet.example/a' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_dispatch_item['ID'], 'tutor-google-meet-link' ) && json_encode( array( 'id' => 'evt-stored' ) ) === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_dispatch_item['ID'], 'tutor-google-meet-event-details' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $google_dispatched['lock'], 'token-dispatch-update-google' ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $google_dispatch_item['ID'] ] ) && $google_dispatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $google_dispatch_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && $google_dispatch_statements === count( $GLOBALS['wpdb']->statements ) && $google_dispatch_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 'Copied' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_dispatch_item['ID'] ]['post_title'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $google_dispatch_item['ID'] ]['post_status'] && false === strpos( json_encode( $google_final ), $secret ), 'confirmed google update compares the normalized result, re-reads the map, and does not fire a save hook' );

$zoom_dispatch_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_dispatch_staged = $stage( $zoom_dispatch_item, $zoom_input, array( 'preflight' => array( true ), 'validate_host' => array( true ) ), 'token-dispatch-update-zoom' );
$zoom_dispatch_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_dispatched = $dispatch_update( $zoom_dispatch_staged, 'token-dispatch-update-zoom', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $zoom_update_map ) ) );
$zoom_final = is_array( $zoom_dispatched ) ? json_decode( $zoom_dispatched['envelope'], true ) : null;
$zoom_update_target = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[0][1]['target'] : array();
tutorpress_live_lessons_assert( is_array( $zoom_final ) && 4 === $zoom_final['version'] && 'synced' === $zoom_final['state'] && 'update' === $zoom_final['provenance'] && 'evt-stored' === $zoom_final['provider_id'] && false === $zoom_final['reconciliation_required'] && array( 'update', array( 'owner_id' => 42, 'provider_id' => 'evt-stored', 'target' => $zoom_update_target ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls[0] && 'evt-stored' === $zoom_update_target['provider_id'] && false === array_key_exists( 'host_id', $zoom_update_target ) && 'host-1' === $zoom_update_target['provider_target']['host_id'] && array( 'update', 'compare_target', 'metadata_map' ) === $methods() && json_encode( array( 'id' => 'evt-stored', 'timezone' => 'America/New_York', 'host_id' => 'host-1' ) ) === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_dispatch_item['ID'], '_tutor_zm_data' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_dispatched['lock'], 'token-dispatch-update-zoom' ) && $zoom_dispatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'confirmed zoom update keeps the nested host and re-reads the seven-key map' );

$uncertain_item = $plant( 'tutor-google-meet', $google_bytes );
$uncertain_staged = $stage( $uncertain_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-uncertain' );
$uncertain_before = json_decode( $uncertain_staged['envelope'], true );
$uncertain_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$uncertain_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$uncertain_done = $dispatch_update( $uncertain_staged, 'token-dispatch-uncertain', array( 'update' => array( $update_result( 'uncertain', 'evt-replaced' ) ) ) );
$uncertain_final = is_array( $uncertain_done ) ? json_decode( $uncertain_done['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $uncertain_final ) && 'error' === $uncertain_final['state'] && 6 === $uncertain_final['version'] && 'evt-stored' === $uncertain_final['provider_id'] && 'create' === $uncertain_final['provenance'] && 'provider_update_failed' === $uncertain_final['error'] && true === $uncertain_final['reconciliation_required'] && 'dispatch_possible' === $uncertain_final['operation']['dispatch_state'] && $durable( $uncertain_before['operation'] ) === $durable( $uncertain_final['operation'] ) && array( 'update' ) === $methods() && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $uncertain_item['ID'] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $uncertain_done['lock'], 'token-dispatch-uncertain' ) && $uncertain_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $uncertain_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && false === strpos( json_encode( $uncertain_final ), 'evt-replaced' ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $uncertain_item['ID'] ]['post_status'], 'an uncertain update preserves the durable target' );

$miss_item = $plant( 'tutor-google-meet', $google_bytes );
$miss_staged = $stage( $miss_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-miss' );
$miss_done = $dispatch_update( $miss_staged, 'token-dispatch-miss', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => false ) ) ) );
$miss_final = is_array( $miss_done ) ? json_decode( $miss_done['envelope'], true ) : null;
$other_item = $plant( 'tutor-google-meet', $google_bytes );
$other_staged = $stage( $other_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-other' );
$other_done = $dispatch_update( $other_staged, 'token-dispatch-other', array( 'update' => array( $update_result( 'confirmed', 'evt-other', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ) ) );
$other_final = is_array( $other_done ) ? json_decode( $other_done['envelope'], true ) : null;
$raw_item = $plant( 'tutor-google-meet', $google_bytes );
$raw_staged = $stage( $raw_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-raw' );
$raw_result = $update_result( 'confirmed', 'evt-stored', $confirmed_item );
$raw_result['extra'] = 'raw-update-body';
$raw_done = $dispatch_update( $raw_staged, 'token-dispatch-raw', array( 'update' => array( $raw_result ) ) );
$raw_final = is_array( $raw_done ) ? json_decode( $raw_done['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $miss_final ) && 'error' === $miss_final['state'] && true === $miss_final['reconciliation_required'] && 'evt-stored' === $miss_final['provider_id'] && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $miss_item['ID'] ) && is_array( $other_final ) && 'error' === $other_final['state'] && 'evt-stored' === $other_final['provider_id'] && false === strpos( json_encode( $other_final ), 'evt-other' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $other_item['ID'] ) && is_array( $raw_final ) && 'error' === $raw_final['state'] && true === $raw_final['reconciliation_required'] && array( 'update' ) === $methods() && false === strpos( json_encode( $raw_final ), 'raw-update-body' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $raw_item['ID'] ), 'a compare miss, a different identity, and an extra-key result do not write metadata' );

$rejected_result = $update_result( 'definite_rejection' );
$rejected_result['diagnostics'] = array( 'error' => 'rejected-body' );
$rejected_item = $plant( 'tutor-google-meet', $google_bytes );
$rejected_staged = $stage( $rejected_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-rejected' );
$rejected_done = $dispatch_update( $rejected_staged, 'token-dispatch-rejected', array( 'update' => array( $rejected_result ) ) );
$rejected_final = is_array( $rejected_done ) ? json_decode( $rejected_done['envelope'], true ) : null;
$pre_item = $plant( 'tutor-google-meet', $google_bytes );
$pre_staged = $stage( $pre_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-pre' );
$pre_done = $dispatch_update( $pre_staged, 'token-dispatch-pre', array( 'update' => array( $update_result( 'pre_dispatch_failure' ) ) ) );
$pre_final = is_array( $pre_done ) ? json_decode( $pre_done['envelope'], true ) : null;
$missing_item = $plant( 'tutor-google-meet', $google_bytes );
$missing_staged = $stage( $missing_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-missing' );
$missing_done = $dispatch_update( $missing_staged, 'token-dispatch-missing', array( 'update' => array( $update_result( 'known_id_not_found' ) ) ) );
$missing_final = is_array( $missing_done ) ? json_decode( $missing_done['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $rejected_final ) && 'error' === $rejected_final['state'] && false === $rejected_final['reconciliation_required'] && 'create' === $rejected_final['provenance'] && false === strpos( json_encode( $rejected_final ), 'rejected-body' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $rejected_item['ID'] ) && is_array( $pre_final ) && false === $pre_final['reconciliation_required'] && 'dispatch_possible' === $pre_final['operation']['dispatch_state'] && is_array( $missing_final ) && true === $missing_final['reconciliation_required'] && 'evt-stored' === $missing_final['provider_id'] && $durable( json_decode( $missing_staged['envelope'], true )['operation'] ) === $durable( $missing_final['operation'] ) && array( 'update' ) === $methods() && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $missing_item['ID'] ), 'definite rejection and pre-dispatch failure stay unresolved without reconciliation, and a missing id keeps the target' );

$throw_item = $plant( 'tutor-google-meet', $google_bytes );
$throw_staged = $stage( $throw_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-throw' );
$throwing = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public function update( $owner_id, $provider_id, array $target ) {
		throw new RuntimeException( 'update-boom-secret' );
	}
};
$throw_done = $dispatch_update( $throw_staged, 'token-dispatch-throw', array(), null, null, $throwing );
$throw_final = is_array( $throw_done ) ? json_decode( $throw_done['envelope'], true ) : null;
$throw_methods = $methods();
$swap_dispatch_item = $plant( 'tutor-google-meet', $google_bytes );
$swap_dispatch_staged = $stage( $swap_dispatch_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-swap' );
$swap_dispatch_bytes = $swap_dispatch_staged['envelope'];
$swap_dispatch_done = $dispatch_update( $swap_dispatch_staged, 'token-dispatch-swap', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ) ), static function () { return false; } );
$swap_methods = $methods();
$owner_guard_item = $plant( 'tutor-google-meet', $google_bytes );
$owner_guard_staged = $stage( $owner_guard_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-owner' );
$owner_guard_bytes = $owner_guard_staged['envelope'];
$owner_guard_staged['owner_id'] = 99;
$owner_guard_done = $dispatch_update( $owner_guard_staged, 'token-dispatch-owner', array( 'update' => array( $update_result( 'uncertain' ) ) ) );
$owner_methods = $methods();
$provider_guard_item = $plant( 'tutor-google-meet', $google_bytes );
$provider_guard_staged = $stage( $provider_guard_item, $google_input, array( 'preflight' => array( true ) ), 'token-dispatch-provider' );
$provider_guard_bytes = $provider_guard_staged['envelope'];
$provider_guard_decoded = json_decode( $provider_guard_bytes, true );
$provider_guard_decoded['provider_id'] = 'evt-forged';
$provider_guard_staged['envelope'] = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $provider_guard_decoded );
$provider_guard_done = $dispatch_update( $provider_guard_staged, 'token-dispatch-provider', array( 'update' => array( $update_result( 'uncertain' ) ) ) );
$provider_methods = $methods();
tutorpress_live_lessons_assert( is_array( $throw_final ) && 'error' === $throw_final['state'] && true === $throw_final['reconciliation_required'] && false === strpos( json_encode( $throw_final ), 'update-boom-secret' ) && array() === $throw_methods && null === $swap_dispatch_done && $swap_dispatch_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $swap_dispatch_item['ID'], '_tutorpress_live_lesson_sync' ) && TutorPress_Live_Lesson_Sync_Repository::owns( $swap_dispatch_staged['lock'], 'token-dispatch-swap' ) && array() === $swap_methods && null === $owner_guard_done && 99 !== (int) json_decode( $owner_guard_bytes, true )['operation']['owner_id'] && $owner_guard_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_guard_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $owner_methods && null === $provider_guard_done && 'evt-stored' === $provider_guard_decoded['operation']['provider_id'] && 'evt-forged' === $provider_guard_decoded['provider_id'] && $provider_guard_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $provider_guard_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $provider_methods, 'a thrown update, a failed exchange, a staged-owner mismatch, and an envelope provider-id mismatch do not dispatch' );
tutorpress_live_lessons_pass( 'update dispatch finalizes confirmed metadata' );

$publish_row = static function ( $post_id ) {
	$post = TutorPress_Live_Lessons_Store_Fake::$posts[ $post_id ];
	return array( 'post_title' => $post['post_title'], 'post_content' => $post['post_content'], 'post_status' => $post['post_status'], 'menu_order' => $post['menu_order'] );
};
$planted_row = array( 'post_title' => 'Copied', 'post_content' => 'Body', 'post_status' => 'publish', 'menu_order' => 4 );
$option_update = array( 'TutorPress_Live_Lessons_Store_Fake', 'update_option_bytes' );
$release_lock = static function ( $name, $prior ) {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)", $name, $prior ) );
	return 1 === (int) $wpdb->rows_affected;
};

$died_item = $plant( 'tutor-google-meet', $google_bytes );
$died_staged = $stage( $died_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-died' );
$died_id = $died_item['ID'];
$died_name = 'tutorpress_live_lesson_lock_' . $died_id;
$died_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$died_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$died_envelope = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_id, '_tutorpress_live_lesson_sync' );
$died_statements = count( $GLOBALS['wpdb']->statements );
$died_held = $died_staged['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ $died_name ] && $died_staged['envelope'] === $died_envelope && $died_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === $died_calls && $died_statements === count( $GLOBALS['wpdb']->statements ) && $planted_row === $publish_row( $died_id ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $died_id );
$died_next = $died_held ? TutorPress_Live_Lesson_Sync_Repository::takeover( $died_id, 'update', 'token-update-died-next', $died_staged['lock'], '2026-09-21 16:05:01', $option_update ) : null;
tutorpress_live_lessons_assert( $died_held && is_string( $died_next ) && $died_next === TutorPress_Live_Lessons_Store_Fake::$options[ $died_name ] && TutorPress_Live_Lesson_Sync_Repository::owns( $died_next, 'token-update-died-next' ) && $died_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_id, '_tutorpress_live_lesson_sync' ) && $died_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $died_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $died_statements === count( $GLOBALS['wpdb']->statements ) && $planted_row === $publish_row( $died_id ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $died_id ), 'update interruption leaves the pending envelope until takeover replaces only the lock' );

$host_item = $plant( 'tutor-google-meet', $google_bytes );
$host_staged = $stage( $host_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-host' );
$host_id = $host_item['ID'];
$host_name = 'tutorpress_live_lesson_lock_' . $host_id;
$host_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$host_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$host_envelope = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $host_id, '_tutorpress_live_lesson_sync' );
$host_next = TutorPress_Live_Lesson_Sync_Repository::takeover( $host_id, 'update', 'token-update-host-next', $host_staged['lock'], '2026-09-21 16:05:01', $option_update );
$host_from = count( $GLOBALS['wpdb']->statements );
$host_released = TutorPress_Live_Lesson_Sync_Repository::release( $host_id, 'token-update-host', $host_staged['lock'], $release_lock );
$host_statements = array_slice( $GLOBALS['wpdb']->statements, $host_from );
tutorpress_live_lessons_assert( is_string( $host_next ) && $host_next === TutorPress_Live_Lessons_Store_Fake::$options[ $host_name ] && TutorPress_Live_Lesson_Sync_Repository::owns( $host_next, 'token-update-host-next' ) && false === $host_released && 1 === count( $host_statements ) && 'DELETE FROM wp_options WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)' === $host_statements[0][0] && $host_name === $host_statements[0][1] && $host_staged['lock'] === $host_statements[0][2] && TutorPress_Live_Lesson_Sync_Repository::owns( $host_statements[0][2], 'token-update-host' ) && $host_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $host_id, '_tutorpress_live_lesson_sync' ) && $planted_row === $publish_row( $host_id ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $host_id ) && $host_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === $host_calls && $host_calls === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'old-holder release after takeover leaves the replacement lock and the pending envelope' );

$stale_item = $plant( 'tutor-google-meet', $google_bytes );
$stale_staged = $stage( $stale_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-stale' );
$stale_id = $stale_item['ID'];
$stale_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$stale_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$stale_operation_id = json_decode( $stale_staged['envelope'], true )['operation']['operation_id'];
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
tutorpress_live_lessons_assert( is_array( $stale_spy->seen ) && $planted_row === array( 'post_title' => $stale_spy->seen['post_title'], 'post_content' => $stale_spy->seen['post_content'], 'post_status' => $stale_spy->seen['post_status'], 'menu_order' => $stale_spy->seen['menu_order'] ) && array( '_tutorpress_live_lesson_sync' ) === $stale_spy->seen['keys'] && 5 === $stale_spy->seen['version'] && 'pending' === $stale_spy->seen['state'] && 'dispatch_possible' === $stale_spy->seen['dispatch_state'] && 'evt-stored' === $stale_spy->seen['provider_id'] && $stale_operation_id === $stale_spy->seen['operation_id'] && null === $stale_done && is_string( $stale_spy->replacement ) && $stale_spy->replacement === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_id, '_tutorpress_live_lesson_sync' ) && is_array( $stale_stored ) && 9 === $stale_stored['version'] && 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb' === $stale_stored['operation']['operation_id'] && $stale_operation_id !== $stale_stored['operation']['operation_id'] && array( 'update', 'compare_target' ) === $methods() && $planted_row === $publish_row( $stale_id ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $stale_id ) && $stale_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === $stale_calls && $stale_statements === count( $GLOBALS['wpdb']->statements ) && $stale_staged['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $stale_id ] && false === strpos( json_encode( $stale_stored ), $secret ), 'a match-false confirmed return leaves the replacement envelope and publish row; provider meta stays absent because match false skips metadata_map and map_stored' );

$dup_item = $plant( 'tutor-google-meet', $google_bytes );
$dup_staged = $stage( $dup_item, $google_input, array( 'preflight' => array( true ) ), 'token-update-dup' );
$dup_id = $dup_item['ID'];
$dup_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$dup_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$dup_done = $dispatch_update( $dup_staged, 'token-update-dup', array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $google_update_map ) ) );
$dup_synced = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, '_tutorpress_live_lesson_sync' );
$dup_keys = $meta_keys( $dup_id );
$dup_statements = count( $GLOBALS['wpdb']->statements );
$dup_again = $dispatch_update( $dup_staged, 'token-update-dup', array() );
$dup_final = json_decode( (string) $dup_synced, true );
tutorpress_live_lessons_assert( is_array( $dup_done ) && is_array( $dup_final ) && 6 === $dup_final['version'] && 'synced' === $dup_final['state'] && 'evt-stored' === $dup_final['provider_id'] && null === $dup_again && array() === $methods() && $dup_synced === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dup_id, '_tutorpress_live_lesson_sync' ) && $dup_keys === $meta_keys( $dup_id ) && $planted_row === $publish_row( $dup_id ) && $dup_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === $dup_calls && $dup_statements === count( $GLOBALS['wpdb']->statements ) && $dup_staged['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $dup_id ] && false === strpos( json_encode( $dup_final ), $secret ), 'a second update with the original snapshot does not call update and leaves the synced envelope and publish row' );
tutorpress_live_lessons_pass( 'update interruption rejects a stale response without a save hook' );

$google_caps = array( 'settings' => true, 'hosts' => false, 'delete_convergence' => true );
$zoom_caps   = array( 'settings' => false, 'hosts' => true, 'delete_convergence' => false );
$stage_delete = static function ( array $item, array $input, array $queue, $token, $can = null, $update = null, $adapter_arg = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::stage_delete( $item, $topic, $course, null === $can ? $allow : $can, $input, null === $adapter_arg ? $adapter : $adapter_arg, $now, $token, $sync_read, null === $update ? $sync_update : $update, $sync_affected );
};
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
$delete_decoded = is_array( $delete_saved ) ? json_decode( $delete_saved['envelope'], true ) : null;
$delete_operation = is_array( $delete_decoded ) && isset( $delete_decoded['operation'] ) ? $delete_decoded['operation'] : array();
$delete_lock = is_array( $delete_saved ) ? json_decode( $delete_saved['lock'], true ) : null;
$delete_body = is_array( $delete_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $delete_id, '_tutorpress_live_lesson_sync' ) : '';
tutorpress_live_lessons_assert( is_array( $delete_saved ) && is_array( $delete_decoded ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $delete_decoded ) ) && $delete_id === $delete_saved['post_id'] && 11 === $delete_saved['owner_id'] && 'dispatch_possible' === $delete_saved['dispatch_state'] && $delete_body === $delete_saved['envelope'] && 5 === $delete_decoded['version'] && 'pending' === $delete_decoded['state'] && 'google_meet' === $delete_decoded['provider'] && 11 === $delete_decoded['owner_id'] && 'evt-stored' === $delete_decoded['provider_id'] && 'create' === $delete_decoded['provenance'] && null === $delete_decoded['error'] && false === $delete_decoded['reconciliation_required'] && 'delete' === $delete_operation['kind'] && 3 === $delete_operation['base_version'] && 'dispatch_possible' === $delete_operation['dispatch_state'] && 'evt-stored' === $delete_operation['provider_id'] && 'Copied' === $delete_operation['title'] && 'Body' === $delete_operation['content'] && '2026-09-21 10:00:00' === $delete_operation['start_local'] && '2026-09-21 11:00:00' === $delete_operation['end_local'] && array( 'lesson_id' => $delete_id, 'title' => 'Copied', 'topic_id' => 8, 'type' => 'google_meet' ) === $delete_operation['delete_hook'] && 1 === preg_match( '/\A[0-9a-f]{32}\z/', $delete_operation['operation_id'] ) && is_array( $delete_lock ) && 'delete' === $delete_lock['operation'] && '2026-09-21 16:05:00' === $delete_lock['expiry'] && TutorPress_Live_Lesson_Sync_Repository::owns( $delete_saved['lock'], 'token-delete-google' ) && is_array( $delete_spy->seen ) && 11 === $delete_spy->seen['owner_id'] && 'evt-stored' === $delete_spy->seen['provider_id'] && 'publish' === $delete_spy->seen['post_status'] && 5 === $delete_spy->seen['version'] && 'pending' === $delete_spy->seen['state'] && 'dispatch_possible' === $delete_spy->seen['dispatch_state'] && 'evt-stored' === $delete_spy->seen['stored_id'] && array( 'capabilities', 'preflight', 'delete' ) === $methods() && array( array( 'delete', array( 'owner_id' => 11, 'provider_id' => 'evt-stored' ) ) ) === array_slice( TutorPress_Live_Lessons_Adapter_Fake::$calls, 2 ) && $delete_decoy === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $delete_id, 'tutor-google-meet-event-details' ) && $planted_row === $publish_row( $delete_id ) && $delete_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $delete_saved['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $delete_id ] && false === strpos( (string) $delete_body, $secret ) && false === strpos( (string) $delete_body, 'evt-evil' ) && false === strpos( (string) $delete_body, 'evt-meta' ) && false === strpos( (string) $delete_body, 'Replaced' ) && false === strpos( (string) $delete_body, '204' ), 'google delete dispatches the stored id and leaves the publish post pending' );

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
$throw_delete_decoded = is_array( $throw_delete_saved ) ? json_decode( $throw_delete_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $throw_delete_decoded ) && 5 === $throw_delete_decoded['version'] && 'pending' === $throw_delete_decoded['state'] && 'dispatch_possible' === $throw_delete_decoded['operation']['dispatch_state'] && 'evt-stored' === $throw_delete_decoded['provider_id'] && 'create' === $throw_delete_decoded['provenance'] && null === $throw_delete_decoded['error'] && false === $throw_delete_decoded['reconciliation_required'] && array( 'capabilities', 'preflight', 'delete' ) === $methods() && $planted_row === $publish_row( $throw_delete_id ) && $throw_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $throw_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( json_encode( $throw_delete_decoded ), 'delete-boom-secret' ), 'a thrown delete leaves the pending dispatch row and discards the exception text' );

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
tutorpress_live_lessons_assert( null === $bare_saved && array( 'capabilities' ) === $bare_methods && null === $zoom_delete_saved && array( 'capabilities' ) === $zoom_delete_methods && null === $broad_delete && array() === $methods() && $zoom_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $bare_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_delete_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $broad_delete_item['ID'] ] ) && $zoom_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_delete_item['ID'] ] ), 'a bare capability boolean, zoom delete_convergence false, and broad edit_posts do not lock or dispatch' );

$preflight_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$preflight_delete_saved = $stage_delete( $preflight_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( false ) ), 'token-delete-preflight' );
tutorpress_live_lessons_assert( null === $preflight_delete_saved && array( 'capabilities', 'preflight' ) === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $preflight_delete_item['ID'] ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $preflight_delete_item['ID'], '_tutorpress_live_lesson_sync' ), 'a failed delete preflight returns null before the lock' );

$acquire_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$acquire_delete_from = count( $GLOBALS['wpdb']->statements );
$acquire_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$acquire_delete_saved = $stage_delete( $acquire_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) ), 'token-delete-acquire' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $acquire_delete_saved && $acquire_delete_from === count( $GLOBALS['wpdb']->statements ) && $acquire_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && array( 'capabilities', 'preflight' ) === $methods() && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $acquire_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $acquire_delete_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $acquire_delete_item['ID'] ] ), 'a failed delete acquire leaves the synced post without a release' );

$swap_delete_item = $plant( 'tutor-google-meet', $google_bytes );
$swap_delete_from = count( $GLOBALS['wpdb']->statements );
$swap_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$swap_delete_saved = $stage_delete( $swap_delete_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) ), 'token-delete-swap', null, static function () { return false; } );
$swap_delete_statements = array_slice( $GLOBALS['wpdb']->statements, $swap_delete_from );
tutorpress_live_lessons_assert( null === $swap_delete_saved && 1 === count( $swap_delete_statements ) && is_array( $swap_delete_statements[0] ) && false !== strpos( $swap_delete_statements[0][0], 'CONVERT(option_value USING BINARY)' ) && 'tutorpress_live_lesson_lock_' . $swap_delete_item['ID'] === $swap_delete_statements[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $swap_delete_statements[0][2], 'token-delete-swap' ) && $swap_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && array( 'capabilities', 'preflight' ) === $methods() && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $swap_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $swap_delete_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $swap_delete_item['ID'] ] ), 'a failed delete exchange releases the exact token and leaves the synced post' );

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
$second_delete_decoded = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $second_delete_item['ID'], '_tutorpress_live_lesson_sync' ), true );
tutorpress_live_lessons_assert( null === $second_delete_saved && 2 === $second_delete_calls && $second_delete_from === count( $GLOBALS['wpdb']->statements ) && $second_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && array( 'capabilities', 'preflight' ) === $methods() && is_array( $second_delete_decoded ) && 4 === $second_delete_decoded['version'] && 'pending' === $second_delete_decoded['state'] && 'delete' === $second_delete_decoded['operation']['kind'] && 'not_dispatched' === $second_delete_decoded['operation']['dispatch_state'] && 'evt-stored' === $second_delete_decoded['provider_id'] && isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $second_delete_item['ID'] ] ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $second_delete_item['ID'] ] ), 'a failed dispatch exchange leaves the version-plus-one delete row and does not call delete' );
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
$converged_sql = array_slice( $GLOBALS['wpdb']->statements, $converged_from );
$converged_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $converged_hooks ) );
tutorpress_live_lessons_assert( is_array( $converged_saved ) && 'confirmed' === $converged_saved['dispatch_state'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $converged_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $converged_id, '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $converged_id ] ) && array( array( $converged_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $converged_removed ) && 1 === count( $converged_sql ) && 'DELETE FROM wp_options WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)' === $converged_sql[0][0] && 'tutorpress_live_lesson_lock_' . $converged_id === $converged_sql[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $converged_sql[0][2], 'token-delete-confirmed' ) && array( array( 'tutorpress_live_lesson_deleted', array( $converged_id, array( 'id' => $converged_id, 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) ) ) ) === $converged_new_hooks && array( 'capabilities', 'preflight', 'delete' ) === $methods(), 'a normalized confirmed delete removes the post and releases the lock before the delete hook' );

$absent = $plant( 'tutor-google-meet', $google_bytes );
$absent_id = $absent['ID'];
$absent_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$absent_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$absent_saved = $stage_delete( $absent, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'known_id_not_found' ) ) ), 'token-delete-absent' );
$absent_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $absent_hooks ) );
tutorpress_live_lessons_assert( is_array( $absent_saved ) && 'confirmed' === $absent_saved['dispatch_state'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $absent_id ] ) && array( array( $absent_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $absent_removed ) && array( array( 'tutorpress_live_lesson_deleted', array( $absent_id, array( 'id' => $absent_id, 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) ) ) ) === $absent_new_hooks && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $absent_id ] ), 'a google known-id 404 deletes locally and fires the delete hook after release' );

$uncertain_delete = $plant( 'tutor-google-meet', $google_bytes );
$uncertain_delete_id = $uncertain_delete['ID'];
$uncertain_delete_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$uncertain_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$uncertain_delete_saved = $stage_delete( $uncertain_delete, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'uncertain' ) ) ), 'token-delete-uncertain' );
$uncertain_delete_row = is_array( $uncertain_delete_saved ) ? json_decode( $uncertain_delete_saved['envelope'], true ) : null;
$rejected_delete = $plant( 'tutor-google-meet', $google_bytes );
$rejected_delete_id = $rejected_delete['ID'];
$rejected_delete_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$rejected_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$rejected_delete_saved = $stage_delete( $rejected_delete, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'definite_rejection' ) ) ), 'token-delete-rejected' );
$rejected_delete_row = is_array( $rejected_delete_saved ) ? json_decode( $rejected_delete_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $uncertain_delete_row ) && 6 === $uncertain_delete_row['version'] && 'error' === $uncertain_delete_row['state'] && 'provider_delete_failed' === $uncertain_delete_row['error'] && 'create' === $uncertain_delete_row['provenance'] && 'evt-stored' === $uncertain_delete_row['provider_id'] && true === $uncertain_delete_row['reconciliation_required'] && 'dispatch_possible' === $uncertain_delete_row['operation']['dispatch_state'] && $planted_row === $publish_row( $uncertain_delete_id ) && TutorPress_Live_Lesson_Sync_Repository::owns( $uncertain_delete_saved['lock'], 'token-delete-uncertain' ) && $uncertain_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $uncertain_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_array( $rejected_delete_row ) && 6 === $rejected_delete_row['version'] && 'error' === $rejected_delete_row['state'] && 'provider_delete_failed' === $rejected_delete_row['error'] && false === $rejected_delete_row['reconciliation_required'] && 'evt-stored' === $rejected_delete_row['provider_id'] && $planted_row === $publish_row( $rejected_delete_id ) && $rejected_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $rejected_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'uncertain and definite rejection keep the publish post and write an error row' );

$forged_delete = $plant( 'tutor-google-meet', $google_bytes );
$forged_delete_id = $forged_delete['ID'];
$forged_delete_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$forged_delete_saved = $stage_delete( $forged_delete, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed', 'evt-evil' ) ) ), 'token-delete-forged' );
$forged_delete_row = is_array( $forged_delete_saved ) ? json_decode( $forged_delete_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $forged_delete_row ) && 6 === $forged_delete_row['version'] && 'error' === $forged_delete_row['state'] && true === $forged_delete_row['reconciliation_required'] && 'evt-stored' === $forged_delete_row['provider_id'] && 'evt-stored' === $forged_delete_row['operation']['provider_id'] && $planted_row === $publish_row( $forged_delete_id ) && $forged_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && false === strpos( json_encode( $forged_delete_row ), 'evt-evil' ), 'a confirmed delete with a different provider id keeps the stored id and the publish post' );

$zoom_open = array( 'settings' => false, 'hosts' => true, 'delete_convergence' => true );
$zoom_absent = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_absent_id = $zoom_absent['ID'];
$zoom_absent_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_absent_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_absent_saved = $stage_delete( $zoom_absent, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'known_id_not_found' ) ) ), 'token-delete-zoom-absent' );
$zoom_absent_row = is_array( $zoom_absent_saved ) ? json_decode( $zoom_absent_saved['envelope'], true ) : null;
$zoom_body = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_body_id = $zoom_body['ID'];
$zoom_body_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_body_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_body_saved = $stage_delete( $zoom_body, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( array( 'code' => 404, 'message' => 'Meeting does not exist' ) ) ), 'token-delete-zoom-body' );
$zoom_body_row = is_array( $zoom_body_saved ) ? json_decode( $zoom_body_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $zoom_absent_row ) && 4 === $zoom_absent_row['version'] && 'error' === $zoom_absent_row['state'] && 'provider_delete_failed' === $zoom_absent_row['error'] && true === $zoom_absent_row['reconciliation_required'] && 'evt-stored' === $zoom_absent_row['provider_id'] && 'create' === $zoom_absent_row['provenance'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_absent_id ] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_absent_saved['lock'], 'token-delete-zoom-absent' ) && $zoom_absent_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $zoom_absent_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_array( $zoom_body_row ) && 3 === $zoom_body_row['version'] && 'pending' === $zoom_body_row['state'] && 'dispatch_possible' === $zoom_body_row['operation']['dispatch_state'] && null === $zoom_body_row['error'] && false === $zoom_body_row['reconciliation_required'] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_body_id ] ) && $zoom_body_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $zoom_body_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( 'capabilities', 'preflight', 'delete' ) === $methods() && false === strpos( json_encode( $zoom_body_row ), '404' ), 'zoom known-id not-found writes an error row and a body or code or missing id stays pending' );

$failed_local = $plant( 'tutor-google-meet', $google_bytes );
$failed_local_id = $failed_local['ID'];
$failed_local_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $failed_local_id ] = true;
$failed_local_saved = $stage_delete( $failed_local, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-local-fail' );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $failed_local_id ] );
$failed_local_row = is_array( $failed_local_saved ) ? json_decode( $failed_local_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $failed_local_row ) && 6 === $failed_local_row['version'] && 'error' === $failed_local_row['state'] && 'provider_delete_failed' === $failed_local_row['error'] && true === $failed_local_row['reconciliation_required'] && $planted_row === $publish_row( $failed_local_id ) && TutorPress_Live_Lesson_Sync_Repository::owns( $failed_local_saved['lock'], 'token-delete-local-fail' ) && $failed_local_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a failed local delete keeps the publish post and does not fire the delete hook' );

$hook_throw = $plant( 'tutor-google-meet', $google_bytes );
$hook_throw_id = $hook_throw['ID'];
$hook_throw_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'delete-hook-secret' ) );
$hook_throw_saved = $stage_delete( $hook_throw, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-hook-throw' );
$hook_throw_data = $hook_throw_saved instanceof WP_Error ? $hook_throw_saved->get_error_data() : null;
tutorpress_live_lessons_assert( $hook_throw_saved instanceof WP_Error && 'live_lesson_persisted_transition' === $hook_throw_saved->get_error_code() && is_array( $hook_throw_data ) && true === $hook_throw_data['persisted'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_throw_id ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $hook_throw_id ] ) && array( array( $hook_throw_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $hook_throw_removed ) && false === strpos( json_encode( $hook_throw_saved ), 'delete-hook-secret' ), 'a delete-hook throw leaves the post deleted and returns a persisted transition error' );
tutorpress_live_lessons_pass( 'delete converges only after a normalized confirmed delete or a google known-id 404' );

$hook_args_item = $plant( 'tutor-google-meet', $google_bytes );
$hook_args_id = $hook_args_item['ID'];
$hook_args_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$hook_args_saved = $stage_delete( $hook_args_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-hook-args' );
$hook_args_new = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $hook_args_hooks ) );
$hook_args_names = array();
foreach ( $hook_args_new as $hook_args_call ) { $hook_args_names[] = $hook_args_call[0]; }
$hook_args_payload = isset( $hook_args_new[0][1][1] ) && is_array( $hook_args_new[0][1][1] ) ? $hook_args_new[0][1][1] : array();
tutorpress_live_lessons_assert( is_array( $hook_args_saved ) && array( array( 'tutorpress_live_lesson_deleted', array( $hook_args_id, array( 'id' => $hook_args_id, 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) ) ) ) === $hook_args_new && array( 'tutorpress_live_lesson_deleted' ) === $hook_args_names && 2 === count( $hook_args_new[0][1] ) && array( 'id', 'title', 'type', 'topicId' ) === array_keys( $hook_args_payload ) && 'Copied' === $hook_args_payload['title'] && ! array_key_exists( 'provider_id', $hook_args_payload ) && false === strpos( json_encode( $hook_args_names ), 'after_save' ) && false === strpos( json_encode( $hook_args_new ), 'evt-stored' ) && false === strpos( json_encode( $hook_args_new ), $secret ) && false === strpos( (string) $hook_args_saved['envelope'], $secret ), 'a confirmed google delete fires the delete hook with the stored title and no provider id or save hook' );

$late_throw_item = $plant( 'tutor-google-meet', $google_bytes );
$late_throw_id = $late_throw_item['ID'];
$late_throw_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$late_throw_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'delete-hook-after-secret' ) );
$late_throw_saved = $stage_delete( $late_throw_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-hook-after' );
$late_throw_data = $late_throw_saved instanceof WP_Error ? $late_throw_saved->get_error_data() : null;
$late_throw_json = json_encode( $late_throw_saved );
tutorpress_live_lessons_assert( $late_throw_saved instanceof WP_Error && 'live_lesson_persisted_transition' === $late_throw_saved->get_error_code() && is_array( $late_throw_data ) && true === $late_throw_data['persisted'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $late_throw_id ] ) && $late_throw_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && array( array( $late_throw_id, true ) ) === array_slice( TutorPress_Topic_Copy_Harness::$deletes, $late_throw_removed ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $late_throw_id ] ) && false === strpos( (string) $late_throw_json, 'delete-hook-after-secret' ) && false === strpos( (string) $late_throw_json, $secret ), 'a delete-hook throw after release leaves the post absent, records one force-delete, and does not reinsert it' );

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
$stage_delete( $interrupt_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-interrupt', null, null, $interrupt_spy );
tutorpress_live_lessons_assert( is_array( $interrupt_spy->seen ) && $planted_row === array( 'post_title' => $interrupt_spy->seen['post_title'], 'post_content' => $interrupt_spy->seen['post_content'], 'post_status' => $interrupt_spy->seen['post_status'], 'menu_order' => $interrupt_spy->seen['menu_order'] ) && 1 === $interrupt_spy->seen['rows'] && array( '_tutorpress_live_lesson_sync' ) === $interrupt_spy->seen['keys'] && $google_stored['version'] + 2 === $interrupt_spy->seen['version'] && 'pending' === $interrupt_spy->seen['state'] && 'dispatch_possible' === $interrupt_spy->seen['dispatch_state'] && 'evt-stored' === $interrupt_spy->seen['provider_id'] && is_string( $interrupt_spy->seen['lock'] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $interrupt_spy->seen['lock'], 'token-delete-interrupt' ) && $interrupt_removed === $interrupt_spy->seen['deletes'] && $interrupt_hooks === $interrupt_spy->seen['hooks'] && false === strpos( (string) $interrupt_spy->seen['bytes'], $secret ), 'inside delete the sample shows the publish row, one sync row, version plus two, and the acquired lock before force-delete or the hook' );

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
$stale_delete_stored = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_delete_id, '_tutorpress_live_lesson_sync' ), true );
tutorpress_live_lessons_assert( null === $stale_delete_saved && is_string( $stale_delete_spy->replacement ) && $stale_delete_spy->replacement === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_delete_id, '_tutorpress_live_lesson_sync' ) && is_array( $stale_delete_stored ) && 9 === $stale_delete_stored['version'] && 'dddddddddddddddddddddddddddddddd' === $stale_delete_stored['operation']['operation_id'] && $stale_delete_spy->operation_id !== $stale_delete_stored['operation']['operation_id'] && $planted_row === $publish_row( $stale_delete_id ) && $stale_delete_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $stale_delete_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_string( $stale_delete_spy->lock ) && $stale_delete_spy->lock === $stale_delete_held && TutorPress_Live_Lesson_Sync_Repository::owns( $stale_delete_held, 'token-delete-stale' ) && false === strpos( (string) $stale_delete_spy->replacement, $secret ), 'a stale delete response leaves the replacement envelope, the publish post, and the acquired lock' );

$local_fail_item = $plant( 'tutor-google-meet', $google_bytes );
$local_fail_id = $local_fail_item['ID'];
$local_fail_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_fail_id ] = true;
$local_fail_saved = $stage_delete( $local_fail_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'confirmed' ) ) ), 'token-delete-local-again' );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_fail_id ] );
$local_fail_row = is_array( $local_fail_saved ) ? json_decode( $local_fail_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $local_fail_row ) && 6 === $local_fail_row['version'] && 'error' === $local_fail_row['state'] && 'provider_delete_failed' === $local_fail_row['error'] && true === $local_fail_row['reconciliation_required'] && $planted_row === $publish_row( $local_fail_id ) && $local_fail_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( json_encode( $local_fail_row ), $secret ), 'a failed local delete keeps the publish post at version 6 and does not fire the delete hook' );

$known_miss_item = $plant( 'tutor-google-meet', $google_bytes );
$known_miss_id = $known_miss_item['ID'];
$known_miss_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$known_miss_saved = $stage_delete( $known_miss_item, $google_input, array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'known_id_not_found' ) ) ), 'token-delete-known-miss' );
$known_miss_new = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $known_miss_hooks ) );
$known_miss_names = array();
foreach ( $known_miss_new as $known_miss_call ) { $known_miss_names[] = $known_miss_call[0]; }
$known_miss_payload = isset( $known_miss_new[0][1][1] ) && is_array( $known_miss_new[0][1][1] ) ? $known_miss_new[0][1][1] : array();
tutorpress_live_lessons_assert( is_array( $known_miss_saved ) && 'confirmed' === $known_miss_saved['dispatch_state'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $known_miss_id ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $known_miss_id, '_tutorpress_live_lesson_sync' ) && array( array( 'tutorpress_live_lesson_deleted', array( $known_miss_id, array( 'id' => $known_miss_id, 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) ) ) ) === $known_miss_new && array( 'tutorpress_live_lesson_deleted' ) === $known_miss_names && array( 'id', 'title', 'type', 'topicId' ) === array_keys( $known_miss_payload ) && ! array_key_exists( 'provider_id', $known_miss_payload ) && 'Copied' === $known_miss_payload['title'] && false === strpos( json_encode( $known_miss_new ), 'evt-stored' ) && false === strpos( json_encode( $known_miss_new ), $secret ) && false === strpos( (string) $known_miss_saved['envelope'], $secret ), 'a google known-id 404 removes the post, fires the delete hook, and leaves no publish error row' );

$zoom_uncertain_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_uncertain_id = $zoom_uncertain_item['ID'];
$zoom_uncertain_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_uncertain_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_uncertain_saved = $stage_delete( $zoom_uncertain_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'uncertain' ) ) ), 'token-delete-zoom-uncertain' );
$zoom_uncertain_row = is_array( $zoom_uncertain_saved ) ? json_decode( $zoom_uncertain_saved['envelope'], true ) : null;
$zoom_rejected_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_rejected_id = $zoom_rejected_item['ID'];
$zoom_rejected_removed = count( TutorPress_Topic_Copy_Harness::$deletes );
$zoom_rejected_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_rejected_saved = $stage_delete( $zoom_rejected_item, $zoom_input, array( 'capabilities' => array( $zoom_open ), 'preflight' => array( true ), 'delete' => array( $delete_result( 'definite_rejection' ) ) ), 'token-delete-zoom-rejected' );
$zoom_rejected_row = is_array( $zoom_rejected_saved ) ? json_decode( $zoom_rejected_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $zoom_uncertain_row ) && 4 === $zoom_uncertain_row['version'] && 'error' === $zoom_uncertain_row['state'] && 'provider_delete_failed' === $zoom_uncertain_row['error'] && true === $zoom_uncertain_row['reconciliation_required'] && $planted_row === $publish_row( $zoom_uncertain_id ) && $zoom_uncertain_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $zoom_uncertain_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_array( $zoom_rejected_row ) && 4 === $zoom_rejected_row['version'] && 'error' === $zoom_rejected_row['state'] && 'provider_delete_failed' === $zoom_rejected_row['error'] && false === $zoom_rejected_row['reconciliation_required'] && $planted_row === $publish_row( $zoom_rejected_id ) && $zoom_rejected_removed === count( TutorPress_Topic_Copy_Harness::$deletes ) && $zoom_rejected_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( json_encode( $zoom_uncertain_row ), $secret ) && false === strpos( json_encode( $zoom_rejected_row ), $secret ), 'zoom uncertain and definite rejection keep the publish post at version 4' );

$local_fail_projected = is_array( $local_fail_row ) ? TutorPress_Live_Lesson_Response_Projector::project_item( TutorPress_Live_Lessons_Store_Fake::$posts[ $local_fail_id ], $topic, $course, $allow, $local_fail_row, null ) : null;
$zoom_uncertain_projected = is_array( $zoom_uncertain_row ) ? TutorPress_Live_Lesson_Response_Projector::project_item( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_uncertain_id ], $topic, $course, $allow, $zoom_uncertain_row, null ) : null;
$zoom_rejected_projected = is_array( $zoom_rejected_row ) ? TutorPress_Live_Lesson_Response_Projector::project_item( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_rejected_id ], $topic, $course, $allow, $zoom_rejected_row, null ) : null;
tutorpress_live_lessons_assert( is_array( $local_fail_projected ) && $local_fail_id === $local_fail_projected['id'] && 'error' === $local_fail_projected['sync_state'] && 'provider_delete_failed' === $local_fail_projected['error'] && 6 === $local_fail_projected['sync_version'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $local_fail_id ]['post_status'] && is_array( $zoom_uncertain_projected ) && $zoom_uncertain_id === $zoom_uncertain_projected['id'] && 'error' === $zoom_uncertain_projected['sync_state'] && 'provider_delete_failed' === $zoom_uncertain_projected['error'] && 4 === $zoom_uncertain_projected['sync_version'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_uncertain_id ]['post_status'] && is_array( $zoom_rejected_projected ) && $zoom_rejected_id === $zoom_rejected_projected['id'] && 'error' === $zoom_rejected_projected['sync_state'] && 'provider_delete_failed' === $zoom_rejected_projected['error'] && 4 === $zoom_rejected_projected['sync_version'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_rejected_id ]['post_status'] && false === strpos( json_encode( $local_fail_projected ), $secret ) && false === strpos( json_encode( $zoom_uncertain_projected ), $secret ) && false === strpos( json_encode( $zoom_rejected_projected ), $secret ), 'local delete failure and zoom error rows stay discoverable' );
tutorpress_live_lessons_pass( 'delete hook, stale response, zoom errors, and discoverability' );

$saved_wpdb = $GLOBALS['wpdb'];
$release_watch = new class() extends TutorPress_Topic_Copy_Wpdb {
	public $ledger_at = array();
	public function prepare( $query, $name, $prior ) {
		$this->ledger_at[] = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
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
	$retained = static function ( $saved, $token, $sql_from, $hook_from ) use ( $prepared_deletes, $hook_names_from, $save_or_delete ) {
		$name = is_array( $saved ) ? 'tutorpress_live_lesson_lock_' . (int) $saved['post_id'] : '';
		$option = '' !== $name && isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $name ] : null;
		return is_array( $saved ) && is_string( $saved['lock'] ) && $saved['lock'] === $option && TutorPress_Live_Lesson_Sync_Repository::owns( $option, $token ) && array() === $prepared_deletes( $sql_from ) && false === $save_or_delete( $hook_names_from( $hook_from ) );
	};
	$queue_update = array( 'preflight' => array( true ) );
	$confirmed_queue = array( 'update' => array( $update_result( 'confirmed', 'evt-stored', $confirmed_item ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $google_update_map ) );
	$confirmed_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$confirmed_update_staged = $stage( $confirmed_update_item, $google_input, $queue_update, 'token-matrix-update-confirmed' );
	$confirmed_update_sql = count( $GLOBALS['wpdb']->statements );
	$confirmed_update_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$confirmed_update_saved = $dispatch_update( $confirmed_update_staged, 'token-matrix-update-confirmed', $confirmed_queue );
	tutorpress_live_lessons_assert( $retained( $confirmed_update_saved, 'token-matrix-update-confirmed', $confirmed_update_sql, $confirmed_update_hooks ) && $clean( $confirmed_update_saved ) && $clean( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $confirmed_update_hooks ) ), 'a confirmed update keeps the acquired token without a prepared delete or a save hook' );

	$thrown_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$thrown_update_staged = $stage( $thrown_update_item, $google_input, $queue_update, 'token-matrix-update-throw' );
	$thrown_update_adapter = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
		public function update( $owner_id, $provider_id, array $target ) {
			throw new RuntimeException( 'update-matrix-secret' );
		}
	};
	$thrown_update_sql = count( $GLOBALS['wpdb']->statements );
	$thrown_update_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$thrown_update_saved = $dispatch_update( $thrown_update_staged, 'token-matrix-update-throw', array(), null, null, $thrown_update_adapter );
	$thrown_update_encoded = json_encode( $thrown_update_saved );
	tutorpress_live_lessons_assert( $retained( $thrown_update_saved, 'token-matrix-update-throw', $thrown_update_sql, $thrown_update_hooks ) && is_string( $thrown_update_encoded ) && false === strpos( $thrown_update_encoded, 'update-matrix-secret' ) && $clean( $thrown_update_saved ) && $clean( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $thrown_update_hooks ) ), 'a thrown update keeps the acquired token without a prepared delete or a save hook' );

	$uncertain_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$uncertain_update_staged = $stage( $uncertain_update_item, $google_input, $queue_update, 'token-matrix-update-uncertain' );
	$uncertain_update_sql = count( $GLOBALS['wpdb']->statements );
	$uncertain_update_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$uncertain_update_saved = $dispatch_update( $uncertain_update_staged, 'token-matrix-update-uncertain', array( 'update' => array( $update_result( 'uncertain' ) ) ) );
	tutorpress_live_lessons_assert( $retained( $uncertain_update_saved, 'token-matrix-update-uncertain', $uncertain_update_sql, $uncertain_update_hooks ) && $clean( $uncertain_update_saved ) && $clean( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $uncertain_update_hooks ) ), 'an uncertain update keeps the acquired token without a prepared delete or a save hook' );

	$delete_queue = array( 'capabilities' => array( $google_caps ), 'preflight' => array( true ) );
	$success_item = $plant( 'tutor-google-meet', $google_bytes );
	$success_id = $success_item['ID'];
	$success_name = 'tutorpress_live_lesson_lock_' . $success_id;
	$success_sql = count( $GLOBALS['wpdb']->statements );
	$success_ledger = count( $GLOBALS['wpdb']->ledger_at );
	$success_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$success_saved = $stage_delete( $success_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-success' );
	$success_statements = $prepared_deletes( $success_sql );
	$success_lengths = array_slice( $GLOBALS['wpdb']->ledger_at, $success_ledger );
	$success_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $success_hooks );
	tutorpress_live_lessons_assert( is_array( $success_saved ) && 1 === count( $success_statements ) && $lock_sql === $success_statements[0][0] && $success_name === $success_statements[0][1] && $success_saved['lock'] === $success_statements[0][2] && TutorPress_Live_Lesson_Sync_Repository::owns( $success_statements[0][2], 'token-matrix-delete-success' ) && array( $success_hooks ) === $success_lengths && isset( TutorPress_Live_Lessons_Hook_Fake::$calls[ $success_hooks ] ) && 'tutorpress_live_lesson_deleted' === TutorPress_Live_Lessons_Hook_Fake::$calls[ $success_hooks ][0] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $success_name ] ) && $clean( $success_saved ) && $clean( $success_new_hooks ), 'a confirmed delete binds one prepared delete to the acquired bytes before the delete hook' );

	$provider_throw_item = $plant( 'tutor-google-meet', $google_bytes );
	$provider_throw_adapter = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
		public function delete( $owner_id, $provider_id ) {
			throw new RuntimeException( 'delete-matrix-secret' );
		}
	};
	$provider_throw_sql = count( $GLOBALS['wpdb']->statements );
	$provider_throw_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$provider_throw_saved = $stage_delete( $provider_throw_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( true ) ) ), 'token-matrix-delete-throw', null, null, $provider_throw_adapter );
	$provider_throw_encoded = json_encode( $provider_throw_saved );
	$uncertain_hold_item = $plant( 'tutor-google-meet', $google_bytes );
	$uncertain_hold_sql = count( $GLOBALS['wpdb']->statements );
	$uncertain_hold_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$uncertain_hold_saved = $stage_delete( $uncertain_hold_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'uncertain' ) ) ) ), 'token-matrix-delete-uncertain' );
	$local_hold_item = $plant( 'tutor-google-meet', $google_bytes );
	$local_hold_id = $local_hold_item['ID'];
	$local_hold_sql = count( $GLOBALS['wpdb']->statements );
	$local_hold_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_hold_id ] = true;
	$local_hold_saved = $stage_delete( $local_hold_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-local' );
	unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_hold_id ] );
	tutorpress_live_lessons_assert( $retained( $provider_throw_saved, 'token-matrix-delete-throw', $provider_throw_sql, $provider_throw_hooks ) && is_string( $provider_throw_encoded ) && false === strpos( $provider_throw_encoded, 'delete-matrix-secret' ) && $clean( $provider_throw_saved ) && $retained( $uncertain_hold_saved, 'token-matrix-delete-uncertain', $uncertain_hold_sql, $uncertain_hold_hooks ) && $clean( $uncertain_hold_saved ) && $retained( $local_hold_saved, 'token-matrix-delete-local', $local_hold_sql, $local_hold_hooks ) && $clean( $local_hold_saved ) && $clean( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $provider_throw_hooks ) ), 'provider throw, uncertain delete, and local-delete failure keep the acquired token' );

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
	$exact_saved = $stage_delete( $exact_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-exact', null, null, $exact_spy );
	$exact_statements = $prepared_deletes( $exact_sql );
	tutorpress_live_lessons_assert( is_array( $exact_saved ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $exact_id ] ) && 1 === count( $exact_statements ) && $lock_sql === $exact_statements[0][0] && $exact_name === $exact_statements[0][1] && is_string( $exact_spy->original ) && $exact_spy->original === $exact_statements[0][2] && $exact_saved['lock'] === $exact_statements[0][2] && TutorPress_Live_Lesson_Sync_Repository::owns( $exact_statements[0][2], 'token-matrix-delete-exact' ) && is_string( $exact_spy->replacement ) && $exact_spy->replacement === TutorPress_Live_Lessons_Store_Fake::$options[ $exact_name ] && false === $save_or_delete( $hook_names_from( $exact_hooks ) ) && $clean( $exact_saved ) && $clean( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $exact_hooks ) ), 'a replaced lock keeps the original bytes on the prepared delete and does not fire the delete hook' );

	$stale_owner_item = $plant( 'tutor-google-meet', $google_bytes );
	$stale_owner_id = $stale_owner_item['ID'];
	$stale_owner_name = 'tutorpress_live_lesson_lock_' . $stale_owner_id;
	$stale_owner_saved = $stage_delete( $stale_owner_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( array( 'http_status' => 204 ) ) ) ), 'token-matrix-delete-stale' );
	$stale_owner_envelope = is_array( $stale_owner_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_owner_id, '_tutorpress_live_lesson_sync' ) : '';
	$stale_owner_decoded = is_string( $stale_owner_envelope ) ? json_decode( $stale_owner_envelope, true ) : null;
	$stale_owner_next = is_array( $stale_owner_saved ) && is_array( $stale_owner_decoded ) && 5 === $stale_owner_decoded['version'] && 'pending' === $stale_owner_decoded['state'] && 'delete' === $stale_owner_decoded['operation']['kind'] ? TutorPress_Live_Lesson_Sync_Repository::takeover( $stale_owner_id, 'delete', 'token-matrix-delete-stale-next', $stale_owner_saved['lock'], '2026-09-21 16:05:01', $option_update ) : null;
	$stale_owner_sql = count( $GLOBALS['wpdb']->statements );
	$stale_owner_released = is_string( $stale_owner_next ) ? TutorPress_Live_Lesson_Sync_Repository::release( $stale_owner_id, 'token-matrix-delete-stale', $stale_owner_saved['lock'], $release_lock ) : true;
	$stale_owner_statements = $prepared_deletes( $stale_owner_sql );
	tutorpress_live_lessons_assert( is_string( $stale_owner_next ) && $stale_owner_next === TutorPress_Live_Lessons_Store_Fake::$options[ $stale_owner_name ] && TutorPress_Live_Lesson_Sync_Repository::owns( $stale_owner_next, 'token-matrix-delete-stale-next' ) && false === $stale_owner_released && 1 === count( $stale_owner_statements ) && $lock_sql === $stale_owner_statements[0][0] && $stale_owner_name === $stale_owner_statements[0][1] && $stale_owner_saved['lock'] === $stale_owner_statements[0][2] && TutorPress_Live_Lesson_Sync_Repository::owns( $stale_owner_statements[0][2], 'token-matrix-delete-stale' ) && $stale_owner_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_owner_id, '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $stale_owner_id ] ) && $clean( $stale_owner_envelope ), 'takeover of a locked pending delete replaces only the lock and the old token cannot release it' );

	$hook_after_item = $plant( 'tutor-google-meet', $google_bytes );
	$hook_after_id = $hook_after_item['ID'];
	$hook_after_name = 'tutorpress_live_lesson_lock_' . $hook_after_id;
	$hook_after_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
	$hook_after_sql = count( $GLOBALS['wpdb']->statements );
	$hook_after_ledger = count( $GLOBALS['wpdb']->ledger_at );
	$hook_after_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'delete-hook-matrix-secret' ) );
	$hook_after_saved = $stage_delete( $hook_after_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( $delete_result( 'confirmed' ) ) ) ), 'token-matrix-delete-hook' );
	unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] );
	$hook_after_statements = $prepared_deletes( $hook_after_sql );
	$hook_after_lengths = array_slice( $GLOBALS['wpdb']->ledger_at, $hook_after_ledger );
	$hook_after_encoded = json_encode( $hook_after_saved );
	tutorpress_live_lessons_assert( $hook_after_saved instanceof WP_Error && is_string( $hook_after_encoded ) && false === strpos( $hook_after_encoded, 'delete-hook-matrix-secret' ) && $clean( $hook_after_saved ) && $clean( array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $hook_after_hooks ) ) && 1 === count( $hook_after_statements ) && $lock_sql === $hook_after_statements[0][0] && $hook_after_name === $hook_after_statements[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $hook_after_statements[0][2], 'token-matrix-delete-hook' ) && array( $hook_after_hooks ) === $hook_after_lengths && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_after_id ] ) && $hook_after_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $hook_after_name ] ), 'a delete-hook throw records one token delete at the pre-hook ledger length' );

	$died_delete_item = $plant( 'tutor-google-meet', $google_bytes );
	$died_delete_id = $died_delete_item['ID'];
	$died_delete_name = 'tutorpress_live_lesson_lock_' . $died_delete_id;
	$died_delete_sql = count( $GLOBALS['wpdb']->statements );
	$died_delete_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$died_delete_saved = $stage_delete( $died_delete_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( array( 'http_status' => 204 ) ) ) ), 'token-matrix-delete-died' );
	$died_delete_envelope = is_array( $died_delete_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_delete_id, '_tutorpress_live_lesson_sync' ) : '';
	$died_delete_decoded = is_string( $died_delete_envelope ) ? json_decode( $died_delete_envelope, true ) : null;
	$died_delete_held = is_array( $died_delete_saved ) && is_array( $died_delete_decoded ) && 5 === $died_delete_decoded['version'] && 'pending' === $died_delete_decoded['state'] && 'delete' === $died_delete_decoded['operation']['kind'] && $died_delete_saved['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ $died_delete_name ] && $died_delete_saved['envelope'] === $died_delete_envelope && array() === $prepared_deletes( $died_delete_sql ) && false === $save_or_delete( $hook_names_from( $died_delete_hooks ) );
	$died_delete_next = $died_delete_held ? TutorPress_Live_Lesson_Sync_Repository::takeover( $died_delete_id, 'delete', 'token-matrix-delete-died-next', $died_delete_saved['lock'], '2026-09-21 16:05:01', $option_update ) : null;
	tutorpress_live_lessons_assert( $died_delete_held && is_string( $died_delete_next ) && $died_delete_next === TutorPress_Live_Lessons_Store_Fake::$options[ $died_delete_name ] && TutorPress_Live_Lesson_Sync_Repository::owns( $died_delete_next, 'token-matrix-delete-died-next' ) && $died_delete_saved['lock'] !== $died_delete_next && $died_delete_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_delete_id, '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $died_delete_id ] ) && array() === $prepared_deletes( $died_delete_sql ) && $died_delete_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $clean( $died_delete_envelope ), 'process death leaves the pending delete until takeover replaces only the lock' );
	$pass_2d_payload = isset( $success_new_hooks[0][1][1] ) ? $success_new_hooks[0][1][1] : array();
	$pass_2d_message = $hook_after_saved instanceof WP_Error ? $hook_after_saved->get_error_message() : '';
	$pass_2d_data = $hook_after_saved instanceof WP_Error ? json_encode( $hook_after_saved->get_error_data() ) : '';
	$pass_2d_item = $plant( 'tutor-google-meet', $google_bytes );
	$pass_2d_id = $pass_2d_item['ID'];
	$pass_2d_name = 'tutorpress_live_lesson_lock_' . $pass_2d_id;
	$pass_2d_saved = $stage_delete( $pass_2d_item, $google_input, array_merge( $delete_queue, array( 'delete' => array( array( 'http_status' => 204 ) ) ) ), 'token-2d-delete-early' );
	$pass_2d_envelope = is_array( $pass_2d_saved ) ? TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_id, '_tutorpress_live_lesson_sync' ) : '';
	$pass_2d_early = is_array( $pass_2d_saved ) ? TutorPress_Live_Lesson_Sync_Repository::takeover( $pass_2d_id, 'delete', 'token-2d-delete-next', $pass_2d_saved['lock'], '2026-09-21 16:00:00', $option_update ) : 'ran';
	$pass_2d_update_item = $plant( 'tutor-google-meet', $google_bytes );
	$pass_2d_update_input = $google_input;
	$pass_2d_update_input['provider_id'] = 'evt-evil';
	$pass_2d_rejected = $stage( $pass_2d_update_item, $pass_2d_update_input, $queue_update, 'token-2d-update-id' );
	tutorpress_live_lessons_assert( null === $pass_2d_early && is_array( $pass_2d_saved ) && $pass_2d_saved['lock'] === TutorPress_Live_Lessons_Store_Fake::$options[ $pass_2d_name ] && $pass_2d_envelope === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_id, '_tutorpress_live_lesson_sync' ) && array( 'id' => (int) $success_id, 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) === $pass_2d_payload && ! array_key_exists( 'provider_id', $pass_2d_payload ) && array( $success_hooks ) === $success_lengths && $hook_after_saved instanceof WP_Error && 'live_lesson_persisted_transition' === $hook_after_saved->get_error_code() && 'Live lesson transition failed.' === $pass_2d_message && is_string( $pass_2d_data ) && false === strpos( $pass_2d_data, 'delete-hook-matrix-secret' ) && null === $pass_2d_rejected && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_update_item['ID'], '_tutorpress_live_lesson_sync' ), 'a takeover before expiry stays null, the delete hook lists only id title type and topicId, and a thrown hook keeps the fixed transition message' );
} finally {
	$GLOBALS['wpdb'] = $saved_wpdb;
}
tutorpress_live_lessons_assert( $saved_wpdb === $GLOBALS['wpdb'], 'the wpdb global is restored after the lock-release matrix' );
tutorpress_live_lessons_pass( 'exact-token update and delete release' );
