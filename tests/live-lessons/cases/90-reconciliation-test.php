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
$zoom_target   = array( 'auto_recording' => 'none', 'enforce_login' => false, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => false, 'mute_upon_entry' => false, 'participant_video' => false, 'waiting_room' => false );
$sync_read     = array( 'TutorPress_Live_Lessons_Store_Fake', 'meta_rows' );
$lock_sql      = 'DELETE FROM wp_options WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)';
$option_reads   = 0;
$option_updates = 0;
$option         = static function ( $name ) use ( &$option_reads ) {
	$option_reads++;
	return array_key_exists( $name, TutorPress_Live_Lessons_Store_Fake::$options ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $name ] : null;
};
$takeover = static function ( $name, $bytes, $prior ) use ( &$option_updates ) {
	$option_updates++;
	return TutorPress_Live_Lessons_Store_Fake::update_option_bytes( $name, $bytes, $prior );
};
$methods = static function () {
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
$operation = static function ( $provider, $owner, $provider_id, $start, $end, array $duration, array $target ) {
	return array( 'base_version' => 3, 'content' => 'Body', 'course_id' => 7, 'delete_hook' => null, 'dispatch_state' => 'dispatch_possible', 'duration' => $duration, 'end_local' => $end, 'kind' => 'update', 'menu_order' => 4, 'operation_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'owner_id' => $owner, 'post_author' => 42, 'provider' => $provider, 'provider_id' => $provider_id, 'provider_target' => $target, 'start_local' => $start, 'started_at' => '2026-09-21 12:00:00', 'timezone' => 'America/New_York', 'title' => 'Copied', 'topic_id' => 8 );
};
$envelope = static function ( $provider, $version, $state, $owner, $provider_id, $operation ) {
	return array( 'schema_version' => 1, 'version' => $version, 'state' => $state, 'provider' => $provider, 'owner_id' => $owner, 'provider_id' => $provider_id, 'provenance' => 'create', 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => $operation );
};
$plant = static function ( $post_type, $bytes ) {
	$id = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => $post_type, 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => 4, 'post_author' => 42 ) );
	if ( null !== $bytes ) { add_post_meta( $id, '_tutorpress_live_lesson_sync', wp_slash( $bytes ) ); }
	return array( 'ID' => $id, 'post_type' => $post_type, 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Copied', 'post_content' => 'Body', 'menu_order' => 4 );
};
$lock_bytes = static function ( $token, $operation, $expiry ) {
	return TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( array( 'token' => $token, 'operation' => $operation, 'expiry' => $expiry ) );
};
$inspect = static function ( array $item, array $input, array $queue, $token, $can = null, $adapter_arg = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::inspect( $item, $topic, $course, null === $can ? $allow : $can, $input, null === $adapter_arg ? $adapter : $adapter_arg, $now, $token, $sync_read, $option, $takeover );
};
$fetched = static function ( $provider_id ) {
	return array( 'outcome' => 'confirmed', 'provider_id' => $provider_id, 'item' => array( 'start_at' => '2026-09-21T14:00:00-04:00', 'end_at' => '2026-09-21T15:00:00-04:00', 'timezone' => 'America/New_York', 'duration_minutes' => 60, 'join_url' => 'https://meet.example/a', 'details' => array() ), 'diagnostics' => array(), 'provenance' => 'fetch' );
};
$clean = static function ( $value ) use ( $secret ) {
	$encoded = json_encode( $value );
	return is_string( $encoded ) && false === strpos( $encoded, $secret ) && false === strpos( $encoded, 'hidden-pass' ) && false === strpos( $encoded, 'student@example.com' ) && false === strpos( $encoded, 'inspect-fetch-failed' );
};

$google_operation = $operation( 'google_meet', 11, 'evt-stored', '2026-09-21 10:00:00', '2026-09-21 11:00:00', array( 'value' => 60, 'unit' => 'minutes' ), $google_target );
$zoom_operation   = $operation( 'zoom', 42, 'evt-zoom', '2026-09-21 10:00:00', '2026-09-21 12:00:00', array( 'value' => 2, 'unit' => 'hours' ), $zoom_target );
$google_stored    = $envelope( 'google_meet', 4, 'pending', 11, 'evt-stored', $google_operation );
$zoom_stored      = $envelope( 'zoom', 2, 'pending', 42, 'evt-zoom', $zoom_operation );
$google_bytes     = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $google_stored );
$zoom_bytes       = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $zoom_stored );
$google_input     = array( 'version' => 4, 'operation_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'acting_user_id' => 11, 'credentials' => $creds, 'title' => 'evt-evil', 'settings' => array( 'password' => 'hidden-pass', 'attendees' => array( array( 'email' => 'student@example.com' ) ) ) );
$zoom_input       = array( 'version' => 2, 'operation_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'course_author_id' => 42, 'acting_instructor_id' => 9, 'host_id' => 'host-evil', 'credentials' => $creds, 'settings' => array( 'password' => 'hidden-pass' ) );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $google_stored ) ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $zoom_stored ) ) && is_string( $google_bytes ) && is_string( $zoom_bytes ) && ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'inspect' ) )->isPublic() && ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'snapshot_guard' ) )->isPrivate() && ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'hold' ) )->isPrivate() && ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'release_exact' ) )->isPrivate(), 'planted inspect envelopes are canonical and the new methods stay private except inspect' );

$google_item = $plant( 'tutor-google-meet', $google_bytes );
$google_name = 'tutorpress_live_lesson_lock_' . $google_item['ID'];
$google_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$google_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
$google_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$option_reads = 0;
$option_updates = 0;
$google_sql = count( $GLOBALS['wpdb']->statements );
$google_queue = array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-stored' ) ), 'compare_target' => array( array( 'match' => true ) ) );
$google_result = $inspect( $google_item, $google_input, $google_queue, 'token-inspect-google' );
$google_statements = array_slice( $GLOBALS['wpdb']->statements, $google_sql );
$google_decoded = json_decode( $google_bytes, true );
$google_expected = array_merge( $google_decoded['operation'], $google_decoded['operation']['provider_target'] );
tutorpress_live_lessons_assert( array( 'match' => true ) === $google_result && 1 === $option_reads && 0 === $option_updates && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'fetch', array( 'owner_id' => 11, 'provider_id' => 'evt-stored' ) ), array( 'compare_target', array( 'target' => $google_expected ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( 'preflight', 'fetch', 'compare_target' ) === $methods() && 'evt-stored' === TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['provider_id'] && 'evt-evil' !== TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['provider_id'] && 1 === count( $google_statements ) && $lock_sql === $google_statements[0][0] && $google_name === $google_statements[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $google_statements[0][2], 'token-inspect-google' ) && 'update' === json_decode( $google_statements[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $google_name ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $google_item['ID'] ) && $google_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $google_deletes === count( TutorPress_Topic_Copy_Harness::$deletes ) && $google_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $clean( $google_result ) && $clean( $google_bytes ) && $clean( $google_statements[0][2] ), 'ordinary inspect fetches the stored id, compares the durable target, and releases the new token once' );

$zoom_item = $plant( 'tutor_zoom_meeting', $zoom_bytes );
$zoom_name = 'tutorpress_live_lesson_lock_' . $zoom_item['ID'];
$zoom_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$option_updates = 0;
$zoom_sql = count( $GLOBALS['wpdb']->statements );
$zoom_result = $inspect( $zoom_item, $zoom_input, array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-zoom' ) ), 'compare_target' => array( array( 'match' => true ) ) ), 'token-inspect-zoom' );
$zoom_statements = array_slice( $GLOBALS['wpdb']->statements, $zoom_sql );
$zoom_decoded = json_decode( $zoom_bytes, true );
tutorpress_live_lessons_assert( array( 'match' => true ) === $zoom_result && 1 === $option_reads && 0 === $option_updates && array( array( 'preflight', array( 'owner_id' => 42 ) ), array( 'fetch', array( 'owner_id' => 42, 'provider_id' => 'evt-zoom' ) ), array( 'compare_target', array( 'target' => $zoom_decoded['operation'] ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && 1 === count( $zoom_statements ) && $lock_sql === $zoom_statements[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_statements[0][2], 'token-inspect-zoom' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $zoom_name ] ) && $zoom_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $zoom_item['ID'] ) && $zoom_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $clean( $zoom_result ) && false === strpos( json_encode( $zoom_statements[0][2] ), 'host-evil' ), 'zoom inspect fetches the stored meeting id and does not validate a request host' );

$seen = 0;
$spy = static function () use ( &$seen ) { $seen++; return true; };
$with_id = $google_input;
$with_id['provider_id'] = 'evt-evil';
$with_key = $google_input;
$with_key['id'] = 'evt-body';
$identity_item = $plant( 'tutor-google-meet', $google_bytes );
$option_reads = 0;
$identity_sql = count( $GLOBALS['wpdb']->statements );
$evil = $inspect( $identity_item, $with_id, array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-stored' ) ) ), 'token-inspect-evil', $spy );
$evil_methods = $methods();
$key = $inspect( $identity_item, $with_key, array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-stored' ) ) ), 'token-inspect-key', $spy );
tutorpress_live_lessons_assert( null === $evil && null === $key && array() === $evil_methods && array() === $methods() && 0 === $seen && 0 === $option_reads && $identity_sql === count( $GLOBALS['wpdb']->statements ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $identity_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $identity_item['ID'] ] ), 'a payload provider id or id returns null before authorize, hold, and fetch' );
$pass_2b_inspect = null === $evil && null === $key && 0 === $seen && array() === $evil_methods && array() === $methods();

$auth_item = $plant( 'tutor-google-meet', $google_bytes );
$bad_parent = $auth_item;
$bad_parent['post_parent'] = 9;
$option_reads = 0;
$auth_sql = count( $GLOBALS['wpdb']->statements );
tutorpress_live_lessons_assert( null === $inspect( $auth_item, $google_input, array(), 'token-inspect-broad', $broad ) && null === $inspect( $auth_item, $google_input, array(), 'token-inspect-deny', $deny ) && null === $inspect( $bad_parent, $google_input, array(), 'token-inspect-parent', $allow ) && array() === $methods() && 0 === $option_reads && $auth_sql === count( $GLOBALS['wpdb']->statements ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $auth_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $auth_item['ID'] ] ), 'broad edit_posts returns null before hold and fetch' );

$guard_lock = $lock_bytes( 'token-guard', 'update', '2026-09-21 16:05:00' );
$pretty = json_encode( $google_stored, JSON_PRETTY_PRINT );
$guarded = static function ( $bytes, array $input, $second = false ) use ( $plant, $inspect, $guard_lock, $methods, &$option_reads ) {
	$item = $plant( 'tutor-google-meet', $bytes );
	if ( $second ) { add_post_meta( $item['ID'], '_tutorpress_live_lesson_sync', $bytes ); }
	$name = 'tutorpress_live_lesson_lock_' . $item['ID'];
	TutorPress_Live_Lessons_Store_Fake::$options[ $name ] = $guard_lock;
	$option_reads = 0;
	$saved = $inspect( $item, $input, array(), 'token-inspect-guard' );
	return array( $saved, $option_reads, TutorPress_Live_Lessons_Store_Fake::$options[ $name ], TutorPress_Live_Lessons_Store_Fake::meta_bytes( $item['ID'], '_tutorpress_live_lesson_sync' ), $methods() );
};
$wrong_version = $google_input;
$wrong_version['version'] = 5;
$wrong_operation = $google_input;
$wrong_operation['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$guard_sql = count( $GLOBALS['wpdb']->statements );
list( $version_saved, $version_reads, $version_lock, $version_meta, $version_methods ) = $guarded( $google_bytes, $wrong_version );
list( $operation_saved, $operation_reads, $operation_lock, $operation_meta, $operation_methods ) = $guarded( $google_bytes, $wrong_operation );
list( $pretty_saved, $pretty_reads, $pretty_lock, $pretty_meta, $pretty_methods ) = $guarded( $pretty, $google_input );
list( $second_saved, $second_reads, $second_lock, $second_meta, $second_methods ) = $guarded( $google_bytes, $google_input, true );
$absent = $plant( 'tutor-google-meet', null );
$option_reads = 0;
$absent_saved = $inspect( $absent, $google_input, array(), 'token-inspect-absent' );
tutorpress_live_lessons_assert( is_string( $pretty ) && $pretty !== $google_bytes && null === $version_saved && null === $operation_saved && null === $pretty_saved && null === $second_saved && null === $absent_saved && 0 === $version_reads && 0 === $operation_reads && 0 === $pretty_reads && 0 === $second_reads && 0 === $option_reads && $guard_lock === $version_lock && $guard_lock === $operation_lock && $guard_lock === $pretty_lock && $guard_lock === $second_lock && $google_bytes === $version_meta && $pretty === $pretty_meta && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $absent['ID'], '_tutorpress_live_lesson_sync' ) && array() === $version_methods && array() === $operation_methods && array() === $pretty_methods && array() === $second_methods && array() === $methods() && $guard_sql === count( $GLOBALS['wpdb']->statements ), 'a version, operation id, canonical byte, or row mismatch returns null before hold and fetch' );

$null_operation = $operation( 'google_meet', 11, null, '2026-09-21 10:00:00', '2026-09-21 11:00:00', array( 'value' => 60, 'unit' => 'minutes' ), $google_target );
$null_stored = $envelope( 'google_meet', 4, 'error', 11, null, $null_operation );
$null_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $null_stored );
$null_item = $plant( 'tutor-google-meet', $null_bytes );
$option_reads = 0;
$null_sql = count( $GLOBALS['wpdb']->statements );
$null_saved = $inspect( $null_item, $google_input, array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-stored' ) ) ), 'token-inspect-null' );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $null_stored ) ) && null === $null_saved && 0 === $option_reads && array() === $methods() && $null_sql === count( $GLOBALS['wpdb']->statements ) && $null_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $null_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $null_item['ID'] ] ), 'a null stored provider id returns null without a provider call' );

$owner_item = $plant( 'tutor-google-meet', $google_bytes );
$owner_input = $google_input;
$owner_input['acting_user_id'] = 12;
$owner_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$option_updates = 0;
$owner_sql = count( $GLOBALS['wpdb']->statements );
$owner_saved = $inspect( $owner_item, $owner_input, array(), 'token-inspect-owner' );
$owner_statements = array_slice( $GLOBALS['wpdb']->statements, $owner_sql );
tutorpress_live_lessons_assert( null === $owner_saved && 1 === $option_reads && 0 === $option_updates && array() === $methods() && 1 === count( $owner_statements ) && $lock_sql === $owner_statements[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $owner_statements[0][2], 'token-inspect-owner' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $owner_item['ID'] ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_item['ID'], '_tutorpress_live_lesson_sync' ) && $owner_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $clean( $owner_statements[0][2] ), 'a re-resolved owner that differs from the stored owner releases the new token and does not fetch' );

$preflight_item = $plant( 'tutor-google-meet', $google_bytes );
$option_reads = 0;
$option_updates = 0;
$preflight_sql = count( $GLOBALS['wpdb']->statements );
$preflight_saved = $inspect( $preflight_item, $google_input, array( 'preflight' => array( false ) ), 'token-inspect-preflight' );
$preflight_statements = array_slice( $GLOBALS['wpdb']->statements, $preflight_sql );
tutorpress_live_lessons_assert( null === $preflight_saved && array( 'preflight' ) === $methods() && 1 === count( $preflight_statements ) && TutorPress_Live_Lesson_Sync_Repository::owns( $preflight_statements[0][2], 'token-inspect-preflight' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $preflight_item['ID'] ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $preflight_item['ID'], '_tutorpress_live_lesson_sync' ), 'a failed preflight releases the new token and does not fetch' );

$expired_item = $plant( 'tutor-google-meet', $google_bytes );
$expired_name = 'tutorpress_live_lesson_lock_' . $expired_item['ID'];
$expired_prior = $lock_bytes( 'token-old', 'create', '2026-09-21 16:00:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $expired_name ] = $expired_prior;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$expired_sql = count( $GLOBALS['wpdb']->statements );
$expired_saved = $inspect( $expired_item, $google_input, $google_queue, 'token-inspect-expired' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
$expired_statements = array_slice( $GLOBALS['wpdb']->statements, $expired_sql );
tutorpress_live_lessons_assert( array( 'match' => true ) === $expired_saved && 1 === $option_reads && 1 === $option_updates && array( 'preflight', 'fetch', 'compare_target' ) === $methods() && 'evt-stored' === TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['provider_id'] && 1 === count( $expired_statements ) && $lock_sql === $expired_statements[0][0] && $expired_name === $expired_statements[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_statements[0][2], 'token-inspect-expired' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_statements[0][2], 'token-old' ) && $expired_prior !== $expired_statements[0][2] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_name ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $expired_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $expired_item['ID'] ), 'an expired lock is taken over without acquire, the fetch uses the stored id, and completion releases the new token once' );

$live_item = $plant( 'tutor-google-meet', $google_bytes );
$live_name = 'tutorpress_live_lesson_lock_' . $live_item['ID'];
$live_prior = $lock_bytes( 'token-live', 'update', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $live_name ] = $live_prior;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$live_sql = count( $GLOBALS['wpdb']->statements );
$live_saved = $inspect( $live_item, $google_input, $google_queue, 'token-inspect-live' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $live_saved && 1 === $option_reads && 0 === $option_updates && array() === $methods() && $live_sql === count( $GLOBALS['wpdb']->statements ) && $live_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_name ] && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_item['ID'], '_tutorpress_live_lesson_sync' ), 'a live lock returns null, leaves the original option bytes, and does not fetch' );

$broken_item = $plant( 'tutor-google-meet', $google_bytes );
$broken_name = 'tutorpress_live_lesson_lock_' . $broken_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $broken_name ] = '{';
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$broken_sql = count( $GLOBALS['wpdb']->statements );
$broken_saved = $inspect( $broken_item, $google_input, array(), 'token-inspect-broken' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $broken_saved && 1 === $option_reads && 0 === $option_updates && array() === $methods() && $broken_sql === count( $GLOBALS['wpdb']->statements ) && '{' === TutorPress_Live_Lessons_Store_Fake::$options[ $broken_name ] && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $broken_item['ID'], '_tutorpress_live_lesson_sync' ), 'an unparseable lock returns null and leaves the original option bytes' );

$throwing = new class() implements TutorPress_Live_Lesson_Provider_Adapter {
	public $fetched  = null;
	public $compared = false;
	public function capabilities() { return array( 'settings' => false, 'hosts' => false, 'delete_convergence' => false ); }
	public function preflight( $owner_id ): bool { return true; }
	public function settings( $owner_id ) { return null; }
	public function list_hosts( $owner_id ) { return array( 'outcome' => 'not_applicable', 'hosts' => array() ); }
	public function validate_host( $owner_id, $host_id ): bool { return false; }
	public function create( $owner_id, array $target ) { return null; }
	public function fetch( $owner_id, $provider_id ) { $this->fetched = array( (int) $owner_id, $provider_id ); throw new RuntimeException( 'inspect-fetch-failed' ); }
	public function update( $owner_id, $provider_id, array $target ) { return null; }
	public function delete( $owner_id, $provider_id ) { return null; }
	public function compare_target( array $result, array $expected ) { $this->compared = true; return null; }
	public function metadata_map( array $result, array $target ) { return null; }
	public function hydrate_legacy( array $local ) { return null; }
	public function hydrate_item( array $local ) { return null; }
};
$throw_item = $plant( 'tutor-google-meet', $google_bytes );
$throw_name = 'tutorpress_live_lesson_lock_' . $throw_item['ID'];
$throw_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$option_updates = 0;
$throw_sql = count( $GLOBALS['wpdb']->statements );
$throw_saved = $inspect( $throw_item, $google_input, array(), 'token-inspect-throw', null, $throwing );
$throw_statements = array_slice( $GLOBALS['wpdb']->statements, $throw_sql );
tutorpress_live_lessons_assert( null === $throw_saved && array( 11, 'evt-stored' ) === $throwing->fetched && false === $throwing->compared && 1 === $option_reads && 0 === $option_updates && 1 === count( $throw_statements ) && $lock_sql === $throw_statements[0][0] && $throw_name === $throw_statements[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $throw_statements[0][2], 'token-inspect-throw' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $throw_name ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $throw_item['ID'], '_tutorpress_live_lesson_sync' ) && $throw_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $clean( $google_bytes ) && $clean( $throw_statements[0][2] ), 'a provider throw releases that same token once and returns null' );

tutorpress_live_lessons_pass( 'ordinary inspect uses the stored id' );

$log = new stdClass();
$log->events = array();
$sync_update = static function ( $post_id, $key, $meta_id, $value, $prior ) use ( $log ) {
	$decoded = json_decode( $value, true );
	$log->events[] = array( 'write', isset( $decoded['state'] ) ? $decoded['state'] : '', isset( $decoded['version'] ) ? $decoded['version'] : 0, isset( $decoded['operation']['dispatch_state'] ) ? $decoded['operation']['dispatch_state'] : '', isset( $decoded['operation']['operation_id'] ) ? $decoded['operation']['operation_id'] : '' );
	return TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post_id, $key, $meta_id, $value, $prior );
};
$sync_affected = static function () { return TutorPress_Live_Lessons_Store_Fake::$affected; };
$sync_write = static function ( $post_id, $key, $value ) { return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, $value ); };
$spy = new class( $log ) extends TutorPress_Live_Lessons_Adapter_Fake {
	private $log;
	public function __construct( $log ) { $this->log = $log; }
	public function create( $owner_id, array $target ) { $this->log->events[] = array( 'create', $target ); return parent::create( $owner_id, $target ); }
	public function update( $owner_id, $provider_id, array $target ) { $this->log->events[] = array( 'update', $provider_id, $target ); return parent::update( $owner_id, $provider_id, $target ); }
	public function fetch( $owner_id, $provider_id ) { $this->log->events[] = array( 'fetch', (int) $owner_id, $provider_id ); return parent::fetch( $owner_id, $provider_id ); }
	public function delete( $owner_id, $provider_id ) { $this->log->events[] = array( 'delete', (int) $owner_id, $provider_id ); return parent::delete( $owner_id, $provider_id ); }
};
$retry = static function ( array $item, array $input, array $queue, $token, $adapter_arg = null, $update = null ) use ( $topic, $course, $allow, $now, $sync_read, $sync_update, $sync_affected, $sync_write, $option, $takeover, $spy, $log ) {
	$log->events = array();
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::retry( $item, $topic, $course, $allow, $input, null === $adapter_arg ? $spy : $adapter_arg, $now, $token, $sync_read, null === $update ? $sync_update : $update, $sync_affected, $sync_write, $option, $takeover );
};
$event_at = static function ( $name ) use ( $log ) {
	foreach ( $log->events as $index => $event ) { if ( $name === $event[0] ) { return $index; } }
	return null;
};
$evil = array( 'version' => 4, 'operation_id' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'title' => 'Request title', 'content' => 'Request body', 'start_local' => '2026-01-01 00:00:00', 'end_local' => '2026-01-01 01:00:00', 'timezone' => 'UTC', 'duration' => array( 'value' => 15, 'unit' => 'minutes' ), 'provider_target' => array( 'add_enrolled_students' => 'Yes', 'reminder_minutes' => 5, 'send_updates' => 'none', 'status' => 'tentative', 'transparency' => 'opaque', 'visibility' => 'private' ), 'provider_id' => 'evt-evil', 'id' => 'evt-body', 'credentials' => $creds, 'acting_user_id' => 99 );
$op_id = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$reject = static function ( $provenance, $provider_id = null ) {
	return array( 'outcome' => 'definite_rejection', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array( 'error' => 'no' ), 'provenance' => $provenance );
};
$confirmed_item = array( 'start_at' => '2026-09-21T14:00:00-04:00', 'end_at' => '2026-09-21T15:00:00-04:00', 'timezone' => 'America/New_York', 'duration_minutes' => 60, 'join_url' => 'https://meet.example/a', 'details' => array() );
$provider_result = static function ( $outcome, $provenance, $provider_id, $item = null ) use ( $confirmed_item ) {
	return array( 'outcome' => $outcome, 'provider_id' => $provider_id, 'item' => null === $item && 'confirmed' === $outcome && 'delete' !== $provenance ? $confirmed_item : $item, 'diagnostics' => array(), 'provenance' => $provenance );
};
$caps_true = array( array( 'settings' => true, 'hosts' => false, 'delete_convergence' => true ) );
$plant_row = static function ( $post_type, $provider, $version, $state, $kind, $dispatch_state, $recon, $provider_id, $error ) use ( $plant, $operation, $envelope, $google_target, $zoom_target ) {
	$item = $plant( $post_type, null );
	$target = 'zoom' === $provider ? $zoom_target : $google_target;
	$owner = 'zoom' === $provider ? 42 : 11;
	$row = $operation( $provider, $owner, $provider_id, '2026-09-21 10:00:00', '2026-09-21 11:00:00', array( 'value' => 60, 'unit' => 'minutes' ), $target );
	$row['kind'] = $kind;
	$row['dispatch_state'] = $dispatch_state;
	$row['delete_hook'] = 'delete' === $kind ? array( 'lesson_id' => $item['ID'], 'title' => 'Copied', 'topic_id' => 8, 'type' => $provider ) : null;
	$stored = $envelope( $provider, $version, $state, $owner, $provider_id, $row );
	$stored['error'] = $error;
	$stored['reconciliation_required'] = $recon;
	$bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $stored );
	add_post_meta( $item['ID'], '_tutorpress_live_lesson_sync', $bytes );
	return array( $item, $bytes, $stored );
};
$releases = static function ( $from ) {
	$kept = array();
	foreach ( array_slice( $GLOBALS['wpdb']->statements, $from ) as $statement ) { if ( is_array( $statement ) ) { $kept[] = $statement; } }
	return $kept;
};
$stored_target = static function ( array $target ) {
	$encoded = json_encode( $target );
	return is_string( $encoded ) && false === strpos( $encoded, 'Request title' ) && false === strpos( $encoded, 'Request body' ) && false === strpos( $encoded, '2026-01-01' ) && false === strpos( $encoded, 'evt-evil' ) && false === strpos( $encoded, 'evt-body' ) && false === strpos( $encoded, 'student@example.com' ) && 'Copied' === $target['title'] && 'Body' === $target['content'] && '2026-09-21 10:00:00' === $target['start_local'] && 60 === $target['duration']['value'];
};

list( $error_item, $error_bytes, $error_stored ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
$error_input = $evil;
$option_reads = 0;
$error_sql = count( $GLOBALS['wpdb']->statements );
$error_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$error_saved = $retry( $error_item, $error_input, array( 'create' => array( $reject( 'create' ) ) ), 'token-retry-create-error' );
$error_release = $releases( $error_sql );
$create_at = $event_at( 'create' );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $error_stored ) ) && is_array( $error_saved ) && array( 'write', 'pending', 5, 'not_dispatched', $op_id ) === $log->events[0] && is_int( $create_at ) && $create_at > 0 && $stored_target( $log->events[ $create_at ][1] ) && 'No' === $log->events[ $create_at ][1]['provider_target']['add_enrolled_students'] && null === $log->events[ $create_at ][1]['provider_id'] && 1 === count( $error_release ) && $lock_sql === $error_release[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $error_release[0][2], 'token-retry-create-error' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $error_item['ID'] ] ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $error_item['ID'] ] ) && $error_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && 1 === $option_reads && $clean( $error_saved ), 'an error create retries only after the stored row is pending, not_dispatched, and version plus one' );

list( $update_item, $update_bytes, $update_stored ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', false, 'evt-stored', 'provider_update_failed' );
$option_reads = 0;
$update_saved = $retry( $update_item, $evil, array( 'update' => array( $reject( 'update', 'evt-stored' ) ) ), 'token-retry-update-error' );
$update_at = $event_at( 'update' );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $update_stored ) ) && is_array( $update_saved ) && array( 'write', 'pending', 5, 'not_dispatched', $op_id ) === $log->events[0] && is_int( $update_at ) && $update_at > 0 && 'evt-stored' === $log->events[ $update_at ][1] && $stored_target( $log->events[ $update_at ][2] ) && false === strpos( json_encode( $log->events[ $update_at ][2] ), 'evt-evil' ) && $update_bytes !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $update_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $update_item['ID'] ] ), 'an error update retries only after the same operation id is pending and not_dispatched' );
$pass_2b_retry = is_array( $update_saved ) && is_int( $update_at ) && 'evt-stored' === $log->events[ $update_at ][1];

list( $pending_item, $pending_bytes, $pending_stored ) = $plant_row( 'tutor-google-meet', 'google_meet', 2, 'pending', 'create', 'not_dispatched', false, null, null );
$pending_input = $evil;
$pending_input['version'] = 2;
$pending_saved = $retry( $pending_item, $pending_input, array( 'create' => array( $reject( 'create' ) ) ), 'token-retry-pending' );
$pending_writes = array();
foreach ( $log->events as $event ) { if ( 'write' === $event[0] && 'not_dispatched' === $event[3] ) { $pending_writes[] = $event; } }
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $pending_stored ) ) && is_array( $pending_saved ) && array() === $pending_writes && array( 'write', 'pending', 3, 'dispatch_possible', $op_id ) === $log->events[0] && is_int( $event_at( 'create' ) ) && $event_at( 'create' ) > 0 && $stored_target( $log->events[ $event_at( 'create' ) ][1] ) && $pending_bytes !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pending_item['ID'], '_tutorpress_live_lesson_sync' ), 'a pending not_dispatched create is passed through without another not_dispatched transition' );

list( $idless_item, $idless_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
list( $idless_pending_item, $idless_pending_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 1, 'pending', 'create', 'not_dispatched', true, null, null );
$option_reads = 0;
$idless_sql = count( $GLOBALS['wpdb']->statements );
$idless_saved         = $retry( $idless_item, $evil, array( 'create' => array( $reject( 'create' ) ) ), 'token-retry-idless' );
$idless_pending_saved = $retry( $idless_pending_item, array( 'version' => 1, 'operation_id' => $op_id ), array( 'create' => array( $reject( 'create' ) ) ), 'token-retry-idless-pending' );
tutorpress_live_lessons_assert( null === $idless_saved && null === $idless_pending_saved && array() === $methods() && 0 === $option_reads && $idless_sql === count( $GLOBALS['wpdb']->statements ) && $idless_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $idless_item['ID'], '_tutorpress_live_lesson_sync' ) && $idless_pending_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $idless_pending_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $idless_item['ID'] ] ), 'an id-less uncertain create returns null with no lock and no adapter call' );
$pass_2b_idless = null === $idless_saved && null === $idless_pending_saved && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $idless_item['ID'] ] );
tutorpress_live_lessons_assert( true === $pass_2b_inspect && true === $pass_2b_retry && true === $pass_2b_idless, 'gate-true server identity keeps inspect null, stored-id retry, and id-less uncertain create apart' );

list( $blocked_item, $blocked_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$option_reads = 0;
$blocked_sql = count( $GLOBALS['wpdb']->statements );
tutorpress_live_lessons_assert( null === $retry( $blocked_item, $evil, array( 'update' => array( $reject( 'update', 'evt-stored' ) ) ), 'token-retry-recon' ) && array() === $methods() && 0 === $option_reads && $blocked_sql === count( $GLOBALS['wpdb']->statements ) && $blocked_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $blocked_item['ID'], '_tutorpress_live_lesson_sync' ), 'an error row that still requires reconciliation is not sent to a helper' );

list( $zoom_delete_item, $zoom_delete_bytes ) = $plant_row( 'tutor_zoom_meeting', 'zoom', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-zoom', null );
$option_reads = 0;
$zoom_delete_sql = count( $GLOBALS['wpdb']->statements );
$zoom_delete_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
tutorpress_live_lessons_assert( null === $retry( $zoom_delete_item, array( 'version' => 4, 'operation_id' => $op_id, 'title' => 'Request title' ), array( 'capabilities' => array( array( 'settings' => false, 'hosts' => true, 'delete_convergence' => false ) ) ), 'token-retry-zoom-delete' ) && array( 'capabilities' ) === $methods() && 0 === $option_reads && $zoom_delete_sql === count( $GLOBALS['wpdb']->statements ) && $zoom_delete_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_delete_item['ID'] ] ) && $zoom_delete_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_delete_item['ID'], '_tutorpress_live_lesson_sync' ), 'zoom delete returns null before hold and before delete' );

$delete_input = $evil;
$hook_args = static function ( $item ) {
	return array( 'tutorpress_live_lesson_deleted', array( $item['ID'], array( 'id' => $item['ID'], 'title' => 'Copied', 'type' => 'google_meet', 'topicId' => 8 ) ) );
};
list( $plain_item, $plain_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
$plain_sql = count( $GLOBALS['wpdb']->statements );
$plain_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$plain_saved = $retry( $plain_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-retry-delete-plain' );
$plain_release = $releases( $plain_sql );
$plain_new_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $plain_hooks ) );
tutorpress_live_lessons_assert( is_array( $plain_saved ) && 'confirmed' === $plain_saved['dispatch_state'] && array( 'capabilities', 'delete' ) === $methods() && null === $event_at( 'fetch' ) && array( 'delete', 11, 'evt-stored' ) === $log->events[0] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $plain_item['ID'] ] ) && 1 === count( $plain_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $plain_release[0][2], 'token-retry-delete-plain' ) && array( $hook_args( $plain_item ) ) === $plain_new_hooks && false === strpos( json_encode( $plain_new_hooks ), 'evt-evil' ) && false === strpos( json_encode( $plain_new_hooks ), 'Request title' ), 'a not_dispatched google delete converges from the stored id without a fetch' );

list( $error_delete_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'delete', 'dispatch_possible', false, 'evt-stored', 'provider_delete_failed' );
$error_delete_saved = $retry( $error_delete_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'known_id_not_found', 'delete', 'evt-stored', null ) ) ), 'token-retry-delete-error' );
tutorpress_live_lessons_assert( is_array( $error_delete_saved ) && array( 'capabilities', 'delete' ) === $methods() && null === $event_at( 'fetch' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $error_delete_item['ID'] ] ), 'an error delete with reconciliation false deletes without a fetch and converges on google known-id absence' );

list( $found_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'dispatch_possible', true, 'evt-stored', null );
$found_saved = $retry( $found_item, $delete_input, array( 'capabilities' => $caps_true, 'fetch' => array( $fetched( 'evt-stored' ) ), 'compare_target' => array( array( 'match' => true ) ), 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-retry-delete-found' );
tutorpress_live_lessons_assert( is_array( $found_saved ) && is_int( $event_at( 'fetch' ) ) && is_int( $event_at( 'delete' ) ) && $event_at( 'delete' ) > $event_at( 'fetch' ) && array( 'fetch', 11, 'evt-stored' ) === $log->events[ $event_at( 'fetch' ) ] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $found_item['ID'] ] ) && 'Copied' === TutorPress_Live_Lessons_Adapter_Fake::$calls[2][1]['target']['title'] && false === strpos( json_encode( TutorPress_Live_Lessons_Adapter_Fake::$calls[2][1]['target'] ), 'Request title' ), 'a dispatch_possible delete reaches delete only after inspection proves the stored target' );

list( $absent_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'dispatch_possible', false, 'evt-stored', null );
$absent_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$absent_saved = $retry( $absent_item, $delete_input, array( 'capabilities' => $caps_true, 'fetch' => array( $provider_result( 'known_id_not_found', 'fetch', 'evt-stored', null ) ) ), 'token-retry-delete-absent' );
tutorpress_live_lessons_assert( is_array( $absent_saved ) && array( 'capabilities', 'fetch' ) === $methods() && null === $event_at( 'delete' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $absent_item['ID'] ] ) && count( TutorPress_Live_Lessons_Hook_Fake::$calls ) === $absent_hooks + 1, 'inspection-proven google absence converges locally without delete' );

list( $kept_item, $kept_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'dispatch_possible', true, 'evt-stored', null );
$kept_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$kept_saved = $retry( $kept_item, $delete_input, array( 'capabilities' => $caps_true, 'fetch' => array( $provider_result( 'known_id_not_found', 'fetch', 'evt-stored', null ) ) ), 'token-retry-delete-kept' );
tutorpress_live_lessons_assert( null === $kept_saved && array( 'capabilities', 'fetch' ) === $methods() && null === $event_at( 'delete' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $kept_item['ID'] ] ) && $kept_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $kept_item['ID'], '_tutorpress_live_lesson_sync' ) && $kept_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ), 'a reconciliation-required dispatch_possible row does not converge on known-id absence' );

list( $uncertain_item, $uncertain_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'dispatch_possible', false, 'evt-stored', null );
$uncertain_saved = $retry( $uncertain_item, $delete_input, array( 'capabilities' => $caps_true, 'fetch' => array( $provider_result( 'uncertain', 'fetch', null, null ) ), 'fetch_rejected' => array( $reject( 'fetch', null ) ) ), 'token-retry-delete-uncertain' );
$rejected_saved = $retry( $uncertain_item, $delete_input, array( 'capabilities' => $caps_true, 'fetch' => array( $reject( 'fetch', null ) ) ), 'token-retry-delete-rejected' );
tutorpress_live_lessons_assert( null === $uncertain_saved && null === $rejected_saved && array( 'capabilities', 'fetch' ) === $methods() && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $uncertain_item['ID'] ] ) && $uncertain_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $uncertain_item['ID'], '_tutorpress_live_lesson_sync' ), 'an uncertain or rejected fetch does not delete or converge' );

list( $mismatch_item, $mismatch_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'dispatch_possible', true, 'evt-stored', null );
$mismatch_saved = $retry( $mismatch_item, $delete_input, array( 'capabilities' => $caps_true, 'fetch' => array( $fetched( 'evt-other' ) ) ), 'token-retry-delete-mismatch' );
tutorpress_live_lessons_assert( null === $mismatch_saved && array( 'capabilities', 'fetch' ) === $methods() && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $mismatch_item['ID'] ] ) && $mismatch_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $mismatch_item['ID'], '_tutorpress_live_lesson_sync' ), 'a confirmed fetch for a different id does not delete' );

$throwing_delete = new class() implements TutorPress_Live_Lesson_Provider_Adapter {
	public $deleted = false;
	public function capabilities() { return array( 'settings' => true, 'hosts' => false, 'delete_convergence' => true ); }
	public function preflight( $owner_id ): bool { return true; }
	public function settings( $owner_id ) { return null; }
	public function list_hosts( $owner_id ) { return array( 'outcome' => 'not_applicable', 'hosts' => array() ); }
	public function validate_host( $owner_id, $host_id ): bool { return false; }
	public function create( $owner_id, array $target ) { return null; }
	public function fetch( $owner_id, $provider_id ) { throw new RuntimeException( 'retry-fetch-failed' ); }
	public function update( $owner_id, $provider_id, array $target ) { return null; }
	public function delete( $owner_id, $provider_id ) { $this->deleted = true; return null; }
	public function compare_target( array $result, array $expected ) { return null; }
	public function metadata_map( array $result, array $target ) { return null; }
	public function hydrate_legacy( array $local ) { return null; }
	public function hydrate_item( array $local ) { return null; }
};
list( $throw_delete_item, $throw_delete_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'dispatch_possible', false, 'evt-stored', null );
$throw_delete_sql = count( $GLOBALS['wpdb']->statements );
$throw_delete_saved = $retry( $throw_delete_item, $delete_input, array(), 'token-retry-delete-throw', $throwing_delete );
$throw_delete_release = $releases( $throw_delete_sql );
tutorpress_live_lessons_assert( null === $throw_delete_saved && false === $throwing_delete->deleted && 1 === count( $throw_delete_release ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $throw_delete_item['ID'] ] ) && $throw_delete_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $throw_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && false === strpos( json_encode( $throw_delete_release ), 'retry-fetch-failed' ), 'a thrown fetch releases once and does not delete' );

list( $live_delete_item, $live_delete_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
$live_delete_name = 'tutorpress_live_lesson_lock_' . $live_delete_item['ID'];
$live_delete_prior = $lock_bytes( 'token-live-retry', 'delete', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $live_delete_name ] = $live_delete_prior;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$live_delete_count = count( TutorPress_Topic_Copy_Harness::$deletes );
$live_delete_saved = $retry( $live_delete_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-retry-live' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $live_delete_saved && array( 'capabilities' ) === $methods() && 1 === $option_reads && 0 === $option_updates && $live_delete_count === count( TutorPress_Topic_Copy_Harness::$deletes ) && $live_delete_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_delete_name ] && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $live_delete_item['ID'] ] ), 'a live lock returns null with no fetch and no local delete' );

list( $expired_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
$expired_name = 'tutorpress_live_lesson_lock_' . $expired_item['ID'];
$expired_prior = $lock_bytes( 'token-old-retry', 'delete', '2026-09-21 16:00:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $expired_name ] = $expired_prior;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$expired_sql = count( $GLOBALS['wpdb']->statements );
$expired_saved = $retry( $expired_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-retry-expired' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
$expired_release = $releases( $expired_sql );
tutorpress_live_lessons_assert( is_array( $expired_saved ) && 1 === $option_reads && 1 === $option_updates && array( 'capabilities', 'delete' ) === $methods() && 1 === count( $expired_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_release[0][2], 'token-retry-expired' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_release[0][2], 'token-old-retry' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $expired_item['ID'] ] ), 'an expired lock is taken over before a proven delete retry' );

list( $local_fail_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_fail_item['ID'] ] = true;
$local_fail_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$local_fail_sql = count( $GLOBALS['wpdb']->statements );
$local_fail_saved = $retry( $local_fail_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-retry-local-fail' );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $local_fail_item['ID'] ] );
$local_fail_release = $releases( $local_fail_sql );
tutorpress_live_lessons_assert( null === $local_fail_saved && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $local_fail_item['ID'] ] ) && $local_fail_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && 1 === count( $local_fail_release ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $local_fail_item['ID'] ] ), 'a failed local delete keeps the publish post, skips the hook, and still releases once' );

list( $hook_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'retry-hook-boom' ) );
$hook_saved = $retry( $hook_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-retry-hook' );
unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] );
tutorpress_live_lessons_assert( $hook_saved instanceof WP_Error && 'live_lesson_persisted_transition' === $hook_saved->get_error_code() && true === $hook_saved->get_error_data()['persisted'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_item['ID'] ] ) && false === strpos( json_encode( $hook_saved ), 'retry-hook-boom' ), 'a delete hook throwable leaves the post deleted and returns a persisted transition' );

list( $false_release_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
$false_release_name = 'tutorpress_live_lesson_lock_' . $false_release_item['ID'];
$false_release_adapter = new class( $false_release_name ) extends TutorPress_Live_Lessons_Adapter_Fake {
	private $name;
	public function __construct( $name ) { $this->name = $name; }
	public function delete( $owner_id, $provider_id ) { TutorPress_Live_Lessons_Store_Fake::$options[ $this->name ] = 'replaced-lock'; return parent::delete( $owner_id, $provider_id ); }
};
$false_release_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$false_release_saved = $retry( $false_release_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-retry-false-release', $false_release_adapter );
tutorpress_live_lessons_assert( null === $false_release_saved && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $false_release_item['ID'] ] ) && $false_release_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && 'replaced-lock' === TutorPress_Live_Lessons_Store_Fake::$options[ $false_release_name ], 'a false release does not fire the delete hook' );

$changed_reads = 0;
$changed_read = static function ( $post_id, $key ) use ( &$changed_reads, $sync_read ) {
	$rows = call_user_func( $sync_read, $post_id, $key );
	$changed_reads++;
	if ( 2 === $changed_reads && is_array( $rows ) && isset( $rows[0]['meta_value'] ) ) { $rows[0]['meta_value'] .= ' '; }
	return $rows;
};
list( $changed_item, $changed_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
$option_reads = 0;
$changed_sql = count( $GLOBALS['wpdb']->statements );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( $reject( 'create' ) ) );
$changed_saved = TutorPress_Live_Lesson_Sync_Orchestrator::retry( $changed_item, $topic, $course, $allow, $evil, $spy, $now, 'token-retry-changed', $changed_read, $sync_update, $sync_affected, $sync_write, $option, $takeover );
tutorpress_live_lessons_assert( null === $changed_saved && array() === $methods() && 0 === $option_reads && $changed_sql === count( $GLOBALS['wpdb']->statements ) && $changed_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $changed_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $changed_item['ID'] ] ), 'a changed row returns null before hold and leaves the provider ledger unchanged' );

$failed_update = static function () { return false; };
list( $failed_item, $failed_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
$failed_sql = count( $GLOBALS['wpdb']->statements );
$failed_saved = $retry( $failed_item, $evil, array( 'create' => array( $reject( 'create' ) ) ), 'token-retry-exchange', null, $failed_update );
$failed_release = $releases( $failed_sql );
tutorpress_live_lessons_assert( null === $failed_saved && null === $event_at( 'create' ) && 1 === count( $failed_release ) && $failed_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $failed_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $failed_item['ID'] ] ), 'a failed error exchange releases once and does not call the helper' );

$create_map = array( 'tutor-google-meet-link' => 'https://meet.example/a' );
list( $synced_create_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
$synced_create_sql = count( $GLOBALS['wpdb']->statements );
$synced_create_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$synced_create_saved = $retry( $synced_create_item, $evil, array( 'create' => array( $provider_result( 'confirmed', 'create', 'evt-1' ) ), 'metadata_map' => array( $create_map ) ), 'token-retry-synced-create' );
$synced_create_release = $releases( $synced_create_sql );
$synced_create_new = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $synced_create_hooks );
tutorpress_live_lessons_assert( is_array( $synced_create_saved ) && 'synced' === json_decode( $synced_create_saved['envelope'], true )['state'] && 1 === count( $synced_create_release ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $synced_create_item['ID'] ] ) && array( array( 'tutor_google_meet_after_save_meeting', array( $synced_create_item['ID'] ) ) ) === $synced_create_new, 'a synced create does not release the token a second time' );

$update_map = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-link' => 'https://meet.example/a' );
list( $synced_update_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', false, 'evt-stored', 'provider_update_failed' );
$synced_update_sql = count( $GLOBALS['wpdb']->statements );
$synced_update_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$synced_update_saved = $retry( $synced_update_item, $evil, array( 'update' => array( $provider_result( 'confirmed', 'update', 'evt-stored' ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $update_map ) ), 'token-retry-synced-update' );
$synced_update_release = $releases( $synced_update_sql );
tutorpress_live_lessons_assert( is_array( $synced_update_saved ) && 'synced' === json_decode( $synced_update_saved['envelope'], true )['state'] && 1 === count( $synced_update_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $synced_update_release[0][2], 'token-retry-synced-update' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $synced_update_item['ID'] ] ) && $synced_update_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $stored_target( $log->events[ $event_at( 'update' ) ][2] ), 'a synced update releases once and does not fire a save hook' );

$option_reads = 0;
$broad_sql = count( $GLOBALS['wpdb']->statements );
list( $broad_item, $broad_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$broad_saved = TutorPress_Live_Lesson_Sync_Orchestrator::retry( $broad_item, $topic, $course, $broad, $evil, $spy, $now, 'token-retry-broad', $sync_read, $sync_update, $sync_affected, $sync_write, $option, $takeover );
tutorpress_live_lessons_assert( null === $broad_saved && array() === $methods() && 0 === $option_reads && $broad_sql === count( $GLOBALS['wpdb']->statements ) && $broad_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $broad_item['ID'], '_tutorpress_live_lesson_sync' ), 'broad edit_posts returns null before hold' );

tutorpress_live_lessons_pass( 'retry uses the stored snapshot' );

$abandon = static function ( array $item, array $input, $token, $can = null, $update = null, $read = null, $takeover_arg = null ) use ( $topic, $course, $allow, $now, $sync_read, $sync_update, $sync_affected, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'create' => array( array( 'should-not-run' ) ), 'fetch' => array( array( 'should-not-run' ) ), 'update' => array( array( 'should-not-run' ) ), 'delete' => array( array( 'should-not-run' ) ) );
	return TutorPress_Live_Lesson_Sync_Orchestrator::abandon_unknown( $item, $topic, $course, null === $can ? $allow : $can, $input, $now, $token, null === $read ? $sync_read : $read, null === $update ? $sync_update : $update, $sync_affected, $option, null === $takeover_arg ? $takeover : $takeover_arg );
};
list( $abandon_item, $abandon_bytes, $abandon_stored ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$option_reads = 0;
$option_updates = 0;
$abandon_sql = count( $GLOBALS['wpdb']->statements );
$abandon_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$abandon_saved = $abandon( $abandon_item, $evil, 'token-abandon' );
$abandon_release = $releases( $abandon_sql );
$abandon_decoded = is_array( $abandon_saved ) && isset( $abandon_saved['envelope'] ) ? json_decode( $abandon_saved['envelope'], true ) : null;
$abandon_projected = is_array( $abandon_decoded ) ? TutorPress_Live_Lesson_Response_Projector::project_item( $abandon_item, $topic, $course, $allow, $abandon_decoded, null ) : null;
$abandon_encoded = json_encode( $abandon_decoded );
tutorpress_live_lessons_assert( ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'abandon_unknown' ) )->isPublic() && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $abandon_stored ) ) && is_array( $abandon_saved ) && is_array( $abandon_projected ) && 'orphan_may_remain' === $abandon_projected['warning'] && 'unlinked' === $abandon_projected['sync_state'] && false === $abandon_decoded['reconciliation_required'] && 'unlinked' === $abandon_decoded['state'] && 'synced' !== $abandon_decoded['state'] && null === $abandon_decoded['provider_id'] && null === $abandon_decoded['operation'] && 5 === $abandon_decoded['version'] && 1 === $abandon_decoded['schema_version'] && 'google_meet' === $abandon_decoded['provider'] && 11 === $abandon_decoded['owner_id'] && 'provider_create_failed' === $abandon_decoded['error'] && 'create' === $abandon_decoded['provenance'] && 1 === count( $abandon_release ) && $lock_sql === $abandon_release[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $abandon_release[0][2], 'token-abandon' ) && 'create' === json_decode( $abandon_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $abandon_item['ID'] ] ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $abandon_item['ID'] ] ) && $abandon_saved['envelope'] === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $abandon_item['ID'] ) && $abandon_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods() && 1 === $option_reads && 0 === $option_updates && $clean( $abandon_saved ) && $clean( $abandon_projected ) && is_string( $abandon_encoded ) && false === strpos( $abandon_encoded, $secret ) && false === strpos( $abandon_encoded, 'evt-evil' ), 'abandon exchanges an id-less uncertain create to unlinked and projects the orphan warning' );

$option_reads = 0;
$abandon_broad_sql = count( $GLOBALS['wpdb']->statements );
list( $abandon_broad_item, $abandon_broad_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$abandon_broad_saved = $abandon( $abandon_broad_item, $evil, 'token-abandon-broad', $broad );
tutorpress_live_lessons_assert( null === $abandon_broad_saved && array() === $methods() && 0 === $option_reads && $abandon_broad_sql === count( $GLOBALS['wpdb']->statements ) && $abandon_broad_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_broad_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $abandon_broad_item['ID'] ] ), 'broad edit_posts returns null before hold' );

$abandon_stale_version = $evil;
$abandon_stale_version['version'] = 9;
$abandon_stale_operation = $evil;
$abandon_stale_operation['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$option_reads = 0;
$abandon_stale_sql = count( $GLOBALS['wpdb']->statements );
list( $abandon_stale_item, $abandon_stale_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$abandon_version_saved = $abandon( $abandon_stale_item, $abandon_stale_version, 'token-abandon-version' );
$abandon_version_methods = $methods();
$abandon_operation_saved = $abandon( $abandon_stale_item, $abandon_stale_operation, 'token-abandon-operation' );
tutorpress_live_lessons_assert( null === $abandon_version_saved && null === $abandon_operation_saved && array() === $abandon_version_methods && array() === $methods() && 0 === $option_reads && $abandon_stale_sql === count( $GLOBALS['wpdb']->statements ) && $abandon_stale_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_stale_item['ID'], '_tutorpress_live_lesson_sync' ), 'a stale version or a stale operation id returns null before hold and before exchange' );

list( $abandon_pending_item, $abandon_pending_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'create', 'dispatch_possible', true, null, null );
list( $abandon_update_item, $abandon_update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, null, 'provider_update_failed' );
list( $abandon_known_item, $abandon_known_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, 'evt-stored', 'provider_create_failed' );
list( $abandon_definite_item, $abandon_definite_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'not_dispatched', false, null, 'provider_create_failed' );
$option_reads = 0;
$abandon_other_sql = count( $GLOBALS['wpdb']->statements );
$abandon_pending_saved = $abandon( $abandon_pending_item, $evil, 'token-abandon-pending' );
$abandon_pending_methods = $methods();
$abandon_update_saved = $abandon( $abandon_update_item, $evil, 'token-abandon-update' );
$abandon_known_saved = $abandon( $abandon_known_item, $evil, 'token-abandon-known' );
$abandon_definite_saved = $abandon( $abandon_definite_item, $evil, 'token-abandon-definite' );
tutorpress_live_lessons_assert( null === $abandon_pending_saved && null === $abandon_update_saved && null === $abandon_known_saved && null === $abandon_definite_saved && array() === $abandon_pending_methods && array() === $methods() && 0 === $option_reads && $abandon_other_sql === count( $GLOBALS['wpdb']->statements ) && $abandon_pending_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_pending_item['ID'], '_tutorpress_live_lesson_sync' ) && $abandon_update_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_update_item['ID'], '_tutorpress_live_lesson_sync' ) && $abandon_known_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_known_item['ID'], '_tutorpress_live_lesson_sync' ) && $abandon_definite_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_definite_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $abandon_pending_item['ID'] ] ), 'any other state or kind returns null with no lock and no adapter call' );

$abandon_order = array();
$abandon_takeover = static function ( $name, $bytes, $prior ) use ( &$abandon_order, $takeover ) {
	$abandon_order[] = 'takeover';
	return $takeover( $name, $bytes, $prior );
};
$abandon_exchange = static function ( $post_id, $key, $meta_id, $value, $prior ) use ( &$abandon_order, $sync_update ) {
	$abandon_order[] = 'exchange';
	return $sync_update( $post_id, $key, $meta_id, $value, $prior );
};
list( $abandon_expired_item, $abandon_expired_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$abandon_expired_name = 'tutorpress_live_lesson_lock_' . $abandon_expired_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_expired_name ] = $lock_bytes( 'token-old', 'update', '2026-09-21 15:00:00' );
$abandon_expired_sql = count( $GLOBALS['wpdb']->statements );
$abandon_expired_saved = $abandon( $abandon_expired_item, $evil, 'token-abandon-expired', null, $abandon_exchange, null, $abandon_takeover );
$abandon_expired_release = $releases( $abandon_expired_sql );
$abandon_expired_decoded = is_array( $abandon_expired_saved ) ? json_decode( $abandon_expired_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( array( 'takeover', 'exchange' ) === $abandon_order && is_array( $abandon_expired_decoded ) && 'unlinked' === $abandon_expired_decoded['state'] && 'orphan_may_remain' === $abandon_expired_decoded['warning'] && 1 === count( $abandon_expired_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $abandon_expired_release[0][2], 'token-abandon-expired' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $abandon_expired_release[0][2], 'token-old' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_expired_name ] ) && $abandon_expired_bytes !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_expired_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $methods(), 'an expired lock is taken over before the exchange' );

list( $abandon_live_item, $abandon_live_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$abandon_live_name = 'tutorpress_live_lesson_lock_' . $abandon_live_item['ID'];
$abandon_live_lock = $lock_bytes( 'token-live', 'create', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_live_name ] = $abandon_live_lock;
$option_reads = 0;
$option_updates = 0;
$abandon_live_sql = count( $GLOBALS['wpdb']->statements );
$abandon_live_saved = $abandon( $abandon_live_item, $evil, 'token-abandon-live' );
list( $abandon_bad_item, $abandon_bad_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$abandon_bad_name = 'tutorpress_live_lesson_lock_' . $abandon_bad_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_bad_name ] = 1;
$abandon_bad_saved = $abandon( $abandon_bad_item, $evil, 'token-abandon-bad' );
tutorpress_live_lessons_assert( null === $abandon_live_saved && null === $abandon_bad_saved && $abandon_live_lock === TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_live_name ] && 1 === TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_bad_name ] && $abandon_live_sql === count( $GLOBALS['wpdb']->statements ) && $abandon_live_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_live_item['ID'], '_tutorpress_live_lesson_sync' ) && $abandon_bad_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_bad_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $methods() && 0 === $option_updates, 'a live or unparseable lock returns null and leaves the error envelope unchanged' );

$abandon_changed_reads = 0;
$abandon_changed_read = static function ( $post_id, $key ) use ( &$abandon_changed_reads, $sync_read ) {
	$rows = call_user_func( $sync_read, $post_id, $key );
	$abandon_changed_reads++;
	if ( $abandon_changed_reads >= 2 && is_array( $rows ) && isset( $rows[0]['meta_value'] ) ) {
		$rows[0]['meta_value'] .= ' ';
	}
	return $rows;
};
list( $abandon_changed_item, $abandon_changed_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$abandon_changed_sql = count( $GLOBALS['wpdb']->statements );
$log->events = array();
$abandon_changed_saved = $abandon( $abandon_changed_item, $evil, 'token-abandon-changed', null, null, $abandon_changed_read );
$abandon_changed_release = $releases( $abandon_changed_sql );
tutorpress_live_lessons_assert( null === $abandon_changed_saved && $abandon_changed_reads >= 2 && array() === $log->events && 1 === count( $abandon_changed_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $abandon_changed_release[0][2], 'token-abandon-changed' ) && $abandon_changed_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_changed_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $abandon_changed_item['ID'] ] ) && array() === $methods(), 'a changed row returns null before exchange' );

list( $abandon_failed_item, $abandon_failed_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$abandon_failed_sql = count( $GLOBALS['wpdb']->statements );
$abandon_failed_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$abandon_failed_saved = $abandon( $abandon_failed_item, $evil, 'token-abandon-failed', null, $failed_update );
$abandon_failed_release = $releases( $abandon_failed_sql );
tutorpress_live_lessons_assert( null === $abandon_failed_saved && 1 === count( $abandon_failed_release ) && $lock_sql === $abandon_failed_release[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $abandon_failed_release[0][2], 'token-abandon-failed' ) && $abandon_failed_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_failed_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $abandon_failed_item['ID'] ] ) && $abandon_failed_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array() === $methods() && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $abandon_failed_item['ID'] ] ), 'a failed exchange leaves the prior error envelope and releases once' );

tutorpress_live_lessons_pass( 'abandon leaves an unlinked orphan warning' );

$resolve = static function ( array $item, array $input, $token, $can = null, array $queue = array() ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner( $item, $topic, $course, null === $can ? $allow : $can, $input, $adapter, $now, $token, $sync_read, $option, $takeover );
};
$owner_evidence = array(
	array( 'source' => 'acting_user', 'id' => 11, 'label' => 'Ada', 'identity' => 'meet-event-sentinel' ),
	array( 'source' => 'course_author', 'id' => 42, 'label' => 'Course' ),
	array( 'source' => 'post_author', 'id' => 9, 'label' => 'Post' ),
	array( 'source' => 'admin', 'id' => 3, 'label' => 'Nope' ),
	array( 'source' => 'acting_instructor', 'id' => 8, 'label' => '' ),
);
$owner_candidates = array( array( 'id' => 11, 'label' => 'Ada' ), array( 'id' => 42, 'label' => 'Course' ), array( 'id' => 9, 'label' => 'Post' ) );
$owner_input = array( 'version' => 0, 'selected_owner_id' => 11, 'evidence' => $owner_evidence, 'identity' => 'meet-event-sentinel', 'credentials' => $creds, 'course_author_id' => 42, 'acting_instructor_id' => 9 );
$owner_item = $plant( 'tutor-google-meet', null );
add_post_meta( $owner_item['ID'], '_tutorpress_resolved_owner', '99' );
$owner_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$owner_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$option_reads = 0;
$option_updates = 0;
$owner_sql = count( $GLOBALS['wpdb']->statements );
$owner_saved = $resolve( $owner_item, $owner_input, 'token-owner-google', null, array( 'preflight' => array( true ), 'fetch' => array( array( 'should-not-run' ) ), 'create' => array( array( 'should-not-run' ) ), 'update' => array( array( 'should-not-run' ) ), 'delete' => array( array( 'should-not-run' ) ), 'metadata_map' => array( array( 'should-not-run' ) ) ) );
$owner_release = $releases( $owner_sql );
$owner_encoded = json_encode( $owner_saved );
tutorpress_live_lessons_assert( ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'resolve_owner' ) )->isPublic() && array( 'owner_id' => 11, 'candidates' => $owner_candidates ) === $owner_saved && array( array( 'preflight', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( 'preflight' ) === $methods() && 1 === count( $owner_release ) && $lock_sql === $owner_release[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $owner_release[0][2], 'token-owner-google' ) && 'create' === json_decode( $owner_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $owner_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_item['ID'], '_tutorpress_live_lesson_sync' ) && '99' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_item['ID'], '_tutorpress_resolved_owner' ) && $owner_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $owner_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && 1 === $option_reads && 0 === $option_updates && is_string( $owner_encoded ) && false === strpos( $owner_encoded, $secret ) && false === strpos( $owner_encoded, 'meet-event-sentinel' ) && $clean( $owner_saved ), 'legacy google owner is the recomputed acting user and the lock operation is create' );

$disagree = $owner_input;
$disagree['selected_owner_id'] = 42;
$option_reads = 0;
$disagree_sql = count( $GLOBALS['wpdb']->statements );
$disagree_item = $plant( 'tutor-google-meet', null );
$disagree_saved = $resolve( $disagree_item, $disagree, 'token-owner-disagree', null, array( 'preflight' => array( true ) ) );
tutorpress_live_lessons_assert( null === $disagree_saved && array() === $methods() && 0 === $option_reads && $disagree_sql === count( $GLOBALS['wpdb']->statements ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $disagree_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $disagree_item['ID'], '_tutorpress_live_lesson_sync' ), 'a selected owner that disagrees with the unique recomputed owner returns null before hold' );

$stored_owner_item = $plant( 'tutor-google-meet', $google_bytes );
$version_item = $plant( 'tutor-google-meet', null );
$other_version = $owner_input;
$other_version['version'] = 1;
$string_version = $owner_input;
$string_version['version'] = '0';
$option_reads = 0;
$stored_sql = count( $GLOBALS['wpdb']->statements );
$stored_saved = $resolve( $stored_owner_item, $owner_input, 'token-owner-stored', null, array( 'preflight' => array( true ) ) );
$stored_methods = $methods();
$version_saved = $resolve( $version_item, $other_version, 'token-owner-version', null, array( 'preflight' => array( true ) ) );
$string_saved = $resolve( $version_item, $string_version, 'token-owner-string', null, array( 'preflight' => array( true ) ) );
$broad_saved = $resolve( $version_item, $owner_input, 'token-owner-broad', $broad, array( 'preflight' => array( true ) ) );
tutorpress_live_lessons_assert( null === $stored_saved && null === $version_saved && null === $string_saved && null === $broad_saved && array() === $stored_methods && array() === $methods() && 0 === $option_reads && $stored_sql === count( $GLOBALS['wpdb']->statements ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stored_owner_item['ID'], '_tutorpress_live_lesson_sync' ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $version_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $version_item['ID'] ] ), 'a stored envelope, another version, or broad edit_posts returns null before hold' );

$split_input = $owner_input;
$split_input['evidence'] = array( array( 'source' => 'acting_user', 'id' => 11, 'label' => 'Ada' ), array( 'source' => 'acting_user', 'id' => 12, 'label' => 'Bea' ) );
$split_input['selected_owner_id'] = 12;
$split_item = $plant( 'tutor-google-meet', null );
$option_reads = 0;
$split_sql = count( $GLOBALS['wpdb']->statements );
$split_saved = $resolve( $split_item, $split_input, 'token-owner-split', null, array( 'preflight' => array( true ) ) );
$split_release = $releases( $split_sql );
$split_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$missing_input = $split_input;
$missing_input['selected_owner_id'] = 99;
$option_reads = 0;
$missing_sql = count( $GLOBALS['wpdb']->statements );
$missing_saved = $resolve( $split_item, $missing_input, 'token-owner-missing', null, array( 'preflight' => array( true ) ) );
tutorpress_live_lessons_assert( array( 'owner_id' => 12, 'candidates' => array( array( 'id' => 11, 'label' => 'Ada' ), array( 'id' => 12, 'label' => 'Bea' ) ) ) === $split_saved && 1 === count( $split_release ) && 'create' === json_decode( $split_release[0][2], true )['operation'] && array( array( 'preflight', array( 'owner_id' => 12 ) ) ) === $split_calls && null === $missing_saved && array() === $methods() && 0 === $option_reads && $missing_sql === count( $GLOBALS['wpdb']->statements ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $split_item['ID'], '_tutorpress_live_lesson_sync' ), 'an ambiguous google selection is accepted only when that id is a recomputed candidate' );

$zoom_evidence = array( array( 'source' => 'course_author', 'id' => 42, 'label' => 'Author' ), array( 'source' => 'acting_instructor', 'id' => 9, 'label' => 'Instructor' ), array( 'source' => 'post_author', 'id' => 3, 'label' => 'Post' ) );
$zoom_owner_input = array( 'version' => 0, 'evidence' => $zoom_evidence, 'course_author_id' => 42, 'acting_instructor_id' => 9, 'credentials' => $creds );
$zoom_owner_item = $plant( 'tutor_zoom_meeting', null );
$option_reads = 0;
$option_updates = 0;
$zoom_owner_sql = count( $GLOBALS['wpdb']->statements );
$zoom_owner_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_owner_saved = $resolve( $zoom_owner_item, $zoom_owner_input, 'token-owner-zoom', null, array( 'preflight' => array( true ), 'fetch' => array( array( 'should-not-run' ) ) ) );
$zoom_owner_release = $releases( $zoom_owner_sql );
$zoom_owner_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$zoom_owner_encoded = json_encode( $zoom_owner_saved );
$zoom_empty = $zoom_owner_input;
$zoom_empty['credentials'] = array();
$zoom_empty_item = $plant( 'tutor_zoom_meeting', null );
$zoom_empty_sql = count( $GLOBALS['wpdb']->statements );
$zoom_empty_saved = $resolve( $zoom_empty_item, $zoom_empty, 'token-owner-zoom-empty' );
$zoom_empty_release = $releases( $zoom_empty_sql );
tutorpress_live_lessons_assert( array( 'owner_id' => 42, 'candidates' => array( array( 'id' => 42, 'label' => 'Author' ), array( 'id' => 9, 'label' => 'Instructor' ), array( 'id' => 3, 'label' => 'Post' ) ) ) === $zoom_owner_saved && array() === $zoom_owner_calls && array() === $methods() && 1 === count( $zoom_owner_release ) && 'create' === json_decode( $zoom_owner_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_owner_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_owner_item['ID'], '_tutorpress_live_lesson_sync' ) && $zoom_owner_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_string( $zoom_owner_encoded ) && false === strpos( $zoom_owner_encoded, $secret ) && null === $zoom_empty_saved && 1 === count( $zoom_empty_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $zoom_empty_release[0][2], 'token-owner-zoom-empty' ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_empty_item['ID'], '_tutorpress_live_lesson_sync' ), 'zoom owner selection uses zoom_eligible and does not call the adapter' );

$zoom_split = array( 'version' => 0, 'selected_owner_id' => 5, 'evidence' => array( array( 'source' => 'course_author', 'id' => 4, 'label' => 'Ada' ), array( 'source' => 'course_author', 'id' => 5, 'label' => 'Bea' ) ), 'course_author_id' => 5, 'acting_instructor_id' => 0, 'credentials' => $creds );
$zoom_split_item = $plant( 'tutor_zoom_meeting', null );
$zoom_split_saved = $resolve( $zoom_split_item, $zoom_split, 'token-owner-zoom-split', null, array( 'preflight' => array( true ) ) );
$zoom_split_calls = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$zoom_other = $zoom_split;
$zoom_other['selected_owner_id'] = 4;
$zoom_other_sql = count( $GLOBALS['wpdb']->statements );
$zoom_other_saved = $resolve( $zoom_split_item, $zoom_other, 'token-owner-zoom-other' );
$zoom_other_release = $releases( $zoom_other_sql );
tutorpress_live_lessons_assert( array( 'owner_id' => 5, 'candidates' => array( array( 'id' => 4, 'label' => 'Ada' ), array( 'id' => 5, 'label' => 'Bea' ) ) ) === $zoom_split_saved && array() === $zoom_split_calls && array() === $methods() && null === $zoom_other_saved && 1 === count( $zoom_other_release ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_split_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_split_item['ID'], '_tutorpress_live_lesson_sync' ), 'an ambiguous zoom selection must still pass zoom_eligible' );

$expired_owner_item = $plant( 'tutor-google-meet', null );
$expired_owner_name = 'tutorpress_live_lesson_lock_' . $expired_owner_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $expired_owner_name ] = $lock_bytes( 'token-old', 'update', '2026-09-21 16:00:00' );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$expired_owner_sql = count( $GLOBALS['wpdb']->statements );
$expired_owner_saved = $resolve( $expired_owner_item, $owner_input, 'token-owner-expired', null, array( 'preflight' => array( true ) ) );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
$expired_owner_release = $releases( $expired_owner_sql );
tutorpress_live_lessons_assert( array( 'owner_id' => 11, 'candidates' => $owner_candidates ) === $expired_owner_saved && 1 === $option_reads && 1 === $option_updates && 1 === count( $expired_owner_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_owner_release[0][2], 'token-owner-expired' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_owner_release[0][2], 'token-old' ) && 'create' === json_decode( $expired_owner_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_owner_name ] ), 'an expired lock is taken over' );

$live_owner_item = $plant( 'tutor-google-meet', null );
$live_owner_name = 'tutorpress_live_lesson_lock_' . $live_owner_item['ID'];
$live_owner_lock = $lock_bytes( 'token-live', 'update', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $live_owner_name ] = $live_owner_lock;
$bad_owner_item = $plant( 'tutor-google-meet', null );
$bad_owner_name = 'tutorpress_live_lesson_lock_' . $bad_owner_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $bad_owner_name ] = 1;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$live_owner_sql = count( $GLOBALS['wpdb']->statements );
$live_owner_saved = $resolve( $live_owner_item, $owner_input, 'token-owner-live', null, array( 'preflight' => array( true ) ) );
$bad_owner_saved = $resolve( $bad_owner_item, $owner_input, 'token-owner-bad', null, array( 'preflight' => array( true ) ) );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $live_owner_saved && null === $bad_owner_saved && $live_owner_lock === TutorPress_Live_Lessons_Store_Fake::$options[ $live_owner_name ] && 1 === TutorPress_Live_Lessons_Store_Fake::$options[ $bad_owner_name ] && $live_owner_sql === count( $GLOBALS['wpdb']->statements ) && array() === $methods() && 0 === $option_updates, 'a live or unparseable lock returns null and is not released' );

$preflight_owner_item = $plant( 'tutor-google-meet', null );
$option_reads = 0;
$preflight_owner_sql = count( $GLOBALS['wpdb']->statements );
$preflight_owner_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$preflight_owner_saved = $resolve( $preflight_owner_item, $owner_input, 'token-owner-preflight', null, array( 'preflight' => array( false ) ) );
$preflight_owner_release = $releases( $preflight_owner_sql );
tutorpress_live_lessons_assert( null === $preflight_owner_saved && array( 'preflight' ) === $methods() && 1 === count( $preflight_owner_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $preflight_owner_release[0][2], 'token-owner-preflight' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $preflight_owner_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $preflight_owner_item['ID'], '_tutorpress_live_lesson_sync' ) && $preflight_owner_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a failed google preflight releases the create lock and writes no envelope' );

$edit_operation = $operation( 'google_meet', 5, 'evt-stored', '2026-09-21 10:00:00', '2026-09-21 11:00:00', array( 'value' => 60, 'unit' => 'minutes' ), $google_target );
$edit_stored = $envelope( 'google_meet', 3, 'synced', 5, 'evt-stored', $edit_operation );
$edit_bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $edit_stored );
$edit_item = $plant( 'tutor-google-meet', $edit_bytes );
$edit_input = array( 'acting_user_id' => 11, 'selected_owner_id' => 99, 'credentials' => $creds );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ), 'hydrate_item' => array( array( 'status' => '', 'transparency' => '', 'visibility' => '', 'reminder_minutes' => null ) ), 'settings' => array( null ) );
$edit_saved = TutorPress_Live_Lesson_Sync_Orchestrator::stage_update( $edit_item, $topic, $course, $allow, $edit_input, $adapter, $now, 'token-owner-edit', $sync_read, $sync_update, $sync_affected );
$edit_decoded = is_array( $edit_saved ) && isset( $edit_saved['envelope'] ) ? json_decode( $edit_saved['envelope'], true ) : null;
$edit_encoded = json_encode( $edit_decoded );
tutorpress_live_lessons_assert( is_array( $edit_saved ) && is_array( $edit_decoded ) && 11 === $edit_saved['owner_id'] && 11 === $edit_decoded['owner_id'] && 11 === $edit_decoded['operation']['owner_id'] && 99 !== $edit_decoded['owner_id'] && 5 !== $edit_decoded['owner_id'] && is_string( $edit_encoded ) && false === strpos( $edit_encoded, 'selected_owner_id' ) && false === strpos( $edit_encoded, $secret ) && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'hydrate_item', array( 'target' => array( 'tutor-google-meet-event-details' => '' ) ) ), array( 'settings', array( 'owner_id' => 11 ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a normal edit keeps the re-resolved owner when selected_owner_id is supplied' );

tutorpress_live_lessons_pass( 'legacy owner selection is recomputed' );

$legacy = static function ( array $item, array $input, $token, $can = null, array $queue = array() ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::initialize_legacy( $item, $topic, $course, null === $can ? $allow : $can, $input, $adapter, $now, $token, $sync_read, $option, $takeover );
};
$legacy_detail = '{"id":"evt-legacy"}';
$legacy_local  = array( 'tutor-google-meet-event-details' => $legacy_detail );
$legacy_input  = $owner_input;
$legacy_input['local'] = $legacy_local;
$legacy_input['identity'] = 'meet-event-sentinel';
$legacy_item = $plant( 'tutor-google-meet', null );
add_post_meta( $legacy_item['ID'], 'tutor-google-meet-event-details', $legacy_detail );
$legacy_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$option_updates = 0;
$legacy_sql = count( $GLOBALS['wpdb']->statements );
$legacy_saved = $legacy( $legacy_item, $legacy_input, 'token-legacy-google', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ), 'fetch' => array( array( 'should-not-run' ) ), 'create' => array( array( 'should-not-run' ) ), 'update' => array( array( 'should-not-run' ) ), 'delete' => array( array( 'should-not-run' ) ), 'metadata_map' => array( array( 'should-not-run' ) ) ) );
$legacy_release = $releases( $legacy_sql );
$legacy_decoded = is_array( $legacy_saved ) ? json_decode( $legacy_saved['envelope'], true ) : null;
$legacy_encoded = json_encode( $legacy_decoded );
$legacy_rows = TutorPress_Live_Lessons_Store_Fake::meta_rows( $legacy_item['ID'], '_tutorpress_live_lesson_sync' );
tutorpress_live_lessons_assert( ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'initialize_legacy' ) )->isPublic() && is_array( $legacy_saved ) && is_array( $legacy_decoded ) && 1 === count( $legacy_rows ) && 1 === $legacy_decoded['version'] && 0 === $legacy_input['version'] && 'synced' === $legacy_decoded['state'] && 'google_meet' === $legacy_decoded['provider'] && 'evt-legacy' === $legacy_decoded['provider_id'] && 11 === $legacy_decoded['owner_id'] && null === $legacy_decoded['operation'] && null === $legacy_decoded['error'] && null === $legacy_decoded['warning'] && false === $legacy_decoded['reconciliation_required'] && null === $legacy_decoded['provenance'] && array( 'hydrate_legacy', 'preflight' ) === $methods() && 1 === count( $legacy_release ) && $lock_sql === $legacy_release[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $legacy_release[0][2], 'token-legacy-google' ) && 'create' === json_decode( $legacy_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $legacy_item['ID'] ] ) && $legacy_detail === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_item['ID'], 'tutor-google-meet-event-details' ) && $legacy_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_string( $legacy_encoded ) && false === strpos( $legacy_encoded, $secret ) && false === strpos( $legacy_encoded, 'meet-event-sentinel' ) && false === strpos( $legacy_encoded, 'selected_owner_id' ), 'legacy google identity initializes one version-1 synced row without a provider call or save hook' );

$missing_legacy_item = $plant( 'tutor-google-meet', null );
$option_reads = 0;
$missing_legacy_sql = count( $GLOBALS['wpdb']->statements );
$missing_legacy_saved = $legacy( $missing_legacy_item, $legacy_input, 'token-legacy-missing', null, array( 'hydrate_legacy' => array( array( 'provider_id' => '' ) ) ) );
tutorpress_live_lessons_assert( null === $missing_legacy_saved && array( 'hydrate_legacy' ) === $methods() && 0 === $option_reads && $missing_legacy_sql === count( $GLOBALS['wpdb']->statements ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $missing_legacy_item['ID'], '_tutorpress_live_lesson_sync' ), 'a missing hydrated id returns null and writes no version-1 row' );

$second_legacy_item = $plant( 'tutor-google-meet', 'row-a' );
add_post_meta( $second_legacy_item['ID'], '_tutorpress_live_lesson_sync', 'row-b' );
$option_reads = 0;
$second_legacy_sql = count( $GLOBALS['wpdb']->statements );
$second_legacy_saved = $legacy( $second_legacy_item, $legacy_input, 'token-legacy-second', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) );
$second_legacy_rows = TutorPress_Live_Lessons_Store_Fake::meta_rows( $second_legacy_item['ID'], '_tutorpress_live_lesson_sync' );
$other_legacy = $legacy_input;
$other_legacy['version'] = 1;
$string_legacy = $legacy_input;
$string_legacy['version'] = '0';
$version_legacy_item = $plant( 'tutor-google-meet', null );
$version_legacy_saved = $legacy( $version_legacy_item, $other_legacy, 'token-legacy-version' );
$string_legacy_saved = $legacy( $version_legacy_item, $string_legacy, 'token-legacy-string' );
$present_legacy_item = $plant( 'tutor-google-meet', $google_bytes );
$present_legacy_saved = $legacy( $present_legacy_item, $legacy_input, 'token-legacy-present' );
$broad_legacy_item = $plant( 'tutor-google-meet', null );
$broad_legacy_saved = $legacy( $broad_legacy_item, $legacy_input, 'token-legacy-broad', $broad );
tutorpress_live_lessons_assert( null === $second_legacy_saved && 2 === count( $second_legacy_rows ) && 'row-a' === $second_legacy_rows[0]['meta_value'] && 'row-b' === $second_legacy_rows[1]['meta_value'] && null === $version_legacy_saved && null === $string_legacy_saved && null === $present_legacy_saved && null === $broad_legacy_saved && array() === $methods() && 0 === $option_reads && $second_legacy_sql === count( $GLOBALS['wpdb']->statements ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $version_legacy_item['ID'], '_tutorpress_live_lesson_sync' ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $broad_legacy_item['ID'], '_tutorpress_live_lesson_sync' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $present_legacy_item['ID'], '_tutorpress_live_lesson_sync' ), 'a second sync row, a version other than 0, or broad edit_posts returns null before hold' );

$outside_legacy = $legacy_input;
$outside_legacy['evidence'] = array( array( 'source' => 'acting_user', 'id' => 11, 'label' => 'Ada' ), array( 'source' => 'acting_user', 'id' => 12, 'label' => 'Bea' ) );
$outside_legacy['selected_owner_id'] = 99;
$outside_legacy_item = $plant( 'tutor-google-meet', null );
$option_reads = 0;
$outside_legacy_sql = count( $GLOBALS['wpdb']->statements );
$outside_legacy_saved = $legacy( $outside_legacy_item, $outside_legacy, 'token-legacy-outside' );
$disagree_legacy = $legacy_input;
$disagree_legacy['selected_owner_id'] = 42;
$disagree_legacy_item = $plant( 'tutor-google-meet', null );
$disagree_legacy_saved = $legacy( $disagree_legacy_item, $disagree_legacy, 'token-legacy-disagree' );
tutorpress_live_lessons_assert( null === $outside_legacy_saved && null === $disagree_legacy_saved && array() === $methods() && 0 === $option_reads && $outside_legacy_sql === count( $GLOBALS['wpdb']->statements ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $outside_legacy_item['ID'], '_tutorpress_live_lesson_sync' ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $disagree_legacy_item['ID'], '_tutorpress_live_lesson_sync' ), 'a selected id outside this request’s candidates returns null and writes no version-1 row' );

$zoom_legacy_input = $zoom_owner_input;
$zoom_legacy_input['local'] = array( '_tutor_zm_data' => '{"id":"z-legacy"}' );
$zoom_legacy_item = $plant( 'tutor_zoom_meeting', null );
add_post_meta( $zoom_legacy_item['ID'], '_tutor_zm_data', '{"id":"z-legacy"}' );
$zoom_legacy_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$zoom_legacy_sql = count( $GLOBALS['wpdb']->statements );
$zoom_legacy_saved = $legacy( $zoom_legacy_item, $zoom_legacy_input, 'token-legacy-zoom', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'z-legacy' ) ), 'preflight' => array( true ), 'fetch' => array( array( 'should-not-run' ) ) ) );
$zoom_legacy_release = $releases( $zoom_legacy_sql );
$zoom_legacy_decoded = is_array( $zoom_legacy_saved ) ? json_decode( $zoom_legacy_saved['envelope'], true ) : null;
$zoom_legacy_encoded = json_encode( $zoom_legacy_decoded );
tutorpress_live_lessons_assert( is_array( $zoom_legacy_decoded ) && 1 === count( TutorPress_Live_Lessons_Store_Fake::meta_rows( $zoom_legacy_item['ID'], '_tutorpress_live_lesson_sync' ) ) && 1 === $zoom_legacy_decoded['version'] && 'synced' === $zoom_legacy_decoded['state'] && 'zoom' === $zoom_legacy_decoded['provider'] && 'z-legacy' === $zoom_legacy_decoded['provider_id'] && 42 === $zoom_legacy_decoded['owner_id'] && null === $zoom_legacy_decoded['operation'] && array( 'hydrate_legacy' ) === $methods() && 1 === count( $zoom_legacy_release ) && 'create' === json_decode( $zoom_legacy_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $zoom_legacy_item['ID'] ] ) && '{"id":"z-legacy"}' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_legacy_item['ID'], '_tutor_zm_data' ) && $zoom_legacy_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_string( $zoom_legacy_encoded ) && false === strpos( $zoom_legacy_encoded, $secret ), 'legacy zoom identity stores provider zoom and does not call the adapter' );

$expired_legacy_item = $plant( 'tutor-google-meet', null );
$expired_legacy_name = 'tutorpress_live_lesson_lock_' . $expired_legacy_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $expired_legacy_name ] = $lock_bytes( 'token-old', 'update', '2026-09-21 16:00:00' );
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$expired_legacy_sql = count( $GLOBALS['wpdb']->statements );
$expired_legacy_saved = $legacy( $expired_legacy_item, $legacy_input, 'token-legacy-expired', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
$expired_legacy_release = $releases( $expired_legacy_sql );
$expired_legacy_decoded = is_array( $expired_legacy_saved ) ? json_decode( $expired_legacy_saved['envelope'], true ) : null;
tutorpress_live_lessons_assert( is_array( $expired_legacy_decoded ) && 1 === $expired_legacy_decoded['version'] && 'synced' === $expired_legacy_decoded['state'] && 1 === $option_reads && 1 === $option_updates && 1 === count( $expired_legacy_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_legacy_release[0][2], 'token-legacy-expired' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_legacy_release[0][2], 'token-old' ) && 'create' === json_decode( $expired_legacy_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_legacy_name ] ), 'an expired lock is taken over before version-1 initialization' );

$live_legacy_item = $plant( 'tutor-google-meet', null );
$live_legacy_name = 'tutorpress_live_lesson_lock_' . $live_legacy_item['ID'];
$live_legacy_lock = $lock_bytes( 'token-live', 'create', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $live_legacy_name ] = $live_legacy_lock;
$bad_legacy_item = $plant( 'tutor-google-meet', null );
$bad_legacy_name = 'tutorpress_live_lesson_lock_' . $bad_legacy_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $bad_legacy_name ] = 1;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$live_legacy_sql = count( $GLOBALS['wpdb']->statements );
$live_legacy_saved = $legacy( $live_legacy_item, $legacy_input, 'token-legacy-live', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) );
$bad_legacy_saved = $legacy( $bad_legacy_item, $legacy_input, 'token-legacy-bad', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ) ) );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $live_legacy_saved && null === $bad_legacy_saved && $live_legacy_lock === TutorPress_Live_Lessons_Store_Fake::$options[ $live_legacy_name ] && 1 === TutorPress_Live_Lessons_Store_Fake::$options[ $bad_legacy_name ] && $live_legacy_sql === count( $GLOBALS['wpdb']->statements ) && 0 === $option_updates && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_legacy_item['ID'], '_tutorpress_live_lesson_sync' ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $bad_legacy_item['ID'], '_tutorpress_live_lesson_sync' ), 'a live or unparseable lock returns null and writes no version-1 row' );

$failed_legacy_item = $plant( 'tutor-google-meet', null );
TutorPress_Live_Lessons_Store_Fake::$meta_result = 'false';
$option_reads = 0;
$failed_legacy_sql = count( $GLOBALS['wpdb']->statements );
$failed_legacy_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$failed_legacy_saved = $legacy( $failed_legacy_item, $legacy_input, 'token-legacy-failed', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) );
TutorPress_Live_Lessons_Store_Fake::$meta_result = null;
$failed_legacy_release = $releases( $failed_legacy_sql );
tutorpress_live_lessons_assert( null === $failed_legacy_saved && 1 === count( $failed_legacy_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $failed_legacy_release[0][2], 'token-legacy-failed' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $failed_legacy_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $failed_legacy_item['ID'], '_tutorpress_live_lesson_sync' ) && $failed_legacy_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a failed initialize returns null and leaves version 0' );

tutorpress_live_lessons_pass( 'legacy identity initializes version one' );

$attach = static function ( array $item, array $input, array $queue, $token, $can = null, $adapter_arg = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::attach_provider_identity( $item, $topic, $course, null === $can ? $allow : $can, $input, null === $adapter_arg ? $adapter : $adapter_arg, $now, $token, $sync_read, $option, $takeover );
};
$attach_queue = static function ( $result, $match = true ) {
	return array( 'preflight' => array( true ), 'fetch' => array( $result ), 'compare_target' => array( array( 'match' => $match ) ) );
};
$attach_fetch = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public $post_id = 0;
	public $during  = null;
	public function fetch( $owner_id, $provider_id ) {
		$keys = array();
		foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
			if ( (int) $row['post_id'] === (int) $this->post_id ) { $keys[] = $row['meta_key']; }
		}
		$this->during = array( TutorPress_Live_Lessons_Store_Fake::meta_bytes( $this->post_id, '_tutorpress_live_lesson_sync' ), $keys );
		return parent::fetch( $owner_id, $provider_id );
	}
};
$attach_throw = new class() implements TutorPress_Live_Lesson_Provider_Adapter {
	public function capabilities() { return array( 'settings' => false, 'hosts' => false, 'delete_convergence' => false ); }
	public function preflight( $owner_id ): bool { return true; }
	public function settings( $owner_id ) { return null; }
	public function list_hosts( $owner_id ) { return array( 'outcome' => 'not_applicable', 'hosts' => array() ); }
	public function validate_host( $owner_id, $host_id ): bool { return false; }
	public function create( $owner_id, array $target ) { return null; }
	public function fetch( $owner_id, $provider_id ) { throw new RuntimeException( 'attach-fetch-failed' ); }
	public function update( $owner_id, $provider_id, array $target ) { return null; }
	public function delete( $owner_id, $provider_id ) { return null; }
	public function compare_target( array $result, array $expected ) { return array( 'match' => true ); }
	public function metadata_map( array $result, array $target ) { return array( 'tutor-google-meet-link' => 'https://meet.example/written' ); }
	public function hydrate_legacy( array $local ) { return null; }
	public function hydrate_item( array $local ) { return null; }
};
$attach_google = array( 'provider_id' => 'evt-supplied', 'operation_id' => $op_id, 'version' => 4, 'acting_user_id' => 11, 'credentials' => $creds, 'title' => 'Request title', 'id' => 'evt-body', 'settings' => array( 'password' => 'hidden-pass', 'attendees' => array( array( 'email' => 'student@example.com' ) ) ) );
$attach_zoom   = array( 'provider_id' => 'z-supplied', 'operation_id' => $op_id, 'version' => 4, 'course_author_id' => 42, 'acting_instructor_id' => 9, 'acting_user_id' => 11, 'credentials' => $creds, 'id' => 'evt-body' );
list( $attach_item, $attach_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_decoded = json_decode( $attach_bytes, true );
$attach_expected = array_merge( $attach_decoded['operation'], $attach_decoded['operation']['provider_target'] );
$attach_fetch->post_id = $attach_item['ID'];
$attach_fetch->during  = null;
$attach_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$option_updates = 0;
$attach_sql = count( $GLOBALS['wpdb']->statements );
$attach_saved = $attach( $attach_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-attach-google', null, $attach_fetch );
$attach_release = $releases( $attach_sql );
tutorpress_live_lessons_assert( ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'attach_provider_identity' ) )->isPublic() && $fetched( 'evt-supplied' ) === $attach_saved && 'evt-supplied' === TutorPress_Live_Lesson_Provider_Result::identity( $attach_saved ) && 'confirmed' === $attach_saved['outcome'] && array( $attach_bytes, array( '_tutorpress_live_lesson_sync' ) ) === $attach_fetch->during && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'fetch', array( 'owner_id' => 11, 'provider_id' => 'evt-supplied' ) ), array( 'compare_target', array( 'target' => $attach_expected ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && 1 === $option_reads && 0 === $option_updates && 1 === count( $attach_release ) && $lock_sql === $attach_release[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $attach_release[0][2], 'token-attach-google' ) && 'create' === json_decode( $attach_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $attach_item['ID'] ] ) && $attach_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $clean( $attach_saved ) && $clean( $attach_fetch->during[0] ) && $clean( $attach_release[0][2] ), 'uncertain create attach returns the confirmed supplied id while the fetch still sees the error row' );

list( $attach_zoom_item, $attach_zoom_bytes ) = $plant_row( 'tutor_zoom_meeting', 'zoom', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_zoom_operation = json_decode( $attach_zoom_bytes, true )['operation'];
$attach_fetch->post_id = $attach_zoom_item['ID'];
$attach_fetch->during  = null;
$option_reads = 0;
$attach_zoom_sql = count( $GLOBALS['wpdb']->statements );
$attach_zoom_saved = $attach( $attach_zoom_item, $attach_zoom, $attach_queue( $fetched( 'z-supplied' ) ), 'token-attach-zoom', null, $attach_fetch );
$attach_zoom_release = $releases( $attach_zoom_sql );
tutorpress_live_lessons_assert( $fetched( 'z-supplied' ) === $attach_zoom_saved && array( $attach_zoom_bytes, array( '_tutorpress_live_lesson_sync' ) ) === $attach_fetch->during && array( array( 'preflight', array( 'owner_id' => 42 ) ), array( 'fetch', array( 'owner_id' => 42, 'provider_id' => 'z-supplied' ) ), array( 'compare_target', array( 'target' => $attach_zoom_operation ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && 1 === count( $attach_zoom_release ) && 'create' === json_decode( $attach_zoom_release[0][2], true )['operation'] && $clean( $attach_zoom_saved ) && false === strpos( json_encode( $attach_zoom_saved ), 'evt-body' ), 'zoom attach uses zoom_eligible and compares the stored operation' );

$shape_item_pair = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$shape_item = $shape_item_pair[0];
$shape_bytes = $shape_item_pair[1];
$option_reads = 0;
$shape_sql = count( $GLOBALS['wpdb']->statements );
$shape_legacy = array( 'provider_id' => 'evt-supplied', 'operation_id' => null, 'version' => 0 );
$shape_ordinary = array( 'provider_id' => null, 'operation_id' => null, 'version' => 4 );
$shape_empty = array( 'provider_id' => '', 'operation_id' => $op_id, 'version' => 4, 'acting_user_id' => 11 );
$shape_string = $attach_google;
$shape_string['version'] = '4';
$shape_zero = $attach_google;
$shape_zero['version'] = 0;
$shape_stale_version = $attach_google;
$shape_stale_version['version'] = 5;
$shape_stale_operation = $attach_google;
$shape_stale_operation['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$shape_parent = $shape_item;
$shape_parent['post_parent'] = 9;
tutorpress_live_lessons_assert( null === $attach( $shape_item, $shape_legacy, array(), 'token-attach-legacy' ) && null === $attach( $shape_item, $shape_ordinary, array(), 'token-attach-ordinary' ) && null === $attach( $shape_item, $shape_empty, array(), 'token-attach-empty' ) && null === $attach( $shape_item, $shape_string, array(), 'token-attach-string' ) && null === $attach( $shape_item, $shape_zero, array(), 'token-attach-zero' ) && null === $attach( $shape_item, $shape_stale_version, array(), 'token-attach-stale-version' ) && null === $attach( $shape_item, $shape_stale_operation, array(), 'token-attach-stale-operation' ) && null === $attach( $shape_item, $attach_google, array(), 'token-attach-broad', $broad ) && null === $attach( $shape_parent, $attach_google, array(), 'token-attach-parent' ) && array() === $methods() && 0 === $option_reads && $shape_sql === count( $GLOBALS['wpdb']->statements ) && $shape_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $shape_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $shape_item['ID'] ] ), 'a rejected identity shape, a stale guard, or broad edit_posts returns null before hold and fetch' );

list( $attach_pending_item, $attach_pending_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'create', 'not_dispatched', true, null, null );
list( $attach_known_item, $attach_known_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', false, 'evt-stored', 'provider_update_failed' );
$option_reads = 0;
$attach_row_sql = count( $GLOBALS['wpdb']->statements );
tutorpress_live_lessons_assert( null === $attach( $attach_pending_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-attach-pending' ) && null === $attach( $attach_known_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-attach-known' ) && array() === $methods() && 0 === $option_reads && $attach_row_sql === count( $GLOBALS['wpdb']->statements ) && $attach_pending_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_pending_item['ID'], '_tutorpress_live_lesson_sync' ) && $attach_known_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_known_item['ID'], '_tutorpress_live_lesson_sync' ), 'a pending create or a known-id update returns null before hold and fetch' );

list( $attach_owner_item, $attach_owner_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_owner_input = $attach_google;
$attach_owner_input['acting_user_id'] = 12;
$option_reads = 0;
$attach_owner_sql = count( $GLOBALS['wpdb']->statements );
$attach_owner_saved = $attach( $attach_owner_item, $attach_owner_input, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-attach-owner' );
$attach_owner_release = $releases( $attach_owner_sql );
tutorpress_live_lessons_assert( null === $attach_owner_saved && array() === $methods() && 1 === count( $attach_owner_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $attach_owner_release[0][2], 'token-attach-owner' ) && 'create' === json_decode( $attach_owner_release[0][2], true )['operation'] && $attach_owner_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_owner_item['ID'], '_tutorpress_live_lesson_sync' ), 'a re-resolved owner that differs from the stored owner releases the create lock and does not fetch' );

list( $attach_preflight_item, $attach_preflight_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$option_reads = 0;
$attach_preflight_saved = $attach( $attach_preflight_item, $attach_google, array( 'preflight' => array( false ), 'fetch' => array( $fetched( 'evt-supplied' ) ) ), 'token-attach-preflight' );
tutorpress_live_lessons_assert( null === $attach_preflight_saved && array( 'preflight' ) === $methods() && $attach_preflight_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_preflight_item['ID'], '_tutorpress_live_lesson_sync' ), 'a failed preflight releases the create lock and does not fetch' );

list( $attach_expired_item, $attach_expired_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_expired_name = 'tutorpress_live_lesson_lock_' . $attach_expired_item['ID'];
$attach_expired_prior = $lock_bytes( 'token-old', 'update', '2026-09-21 16:00:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $attach_expired_name ] = $attach_expired_prior;
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$attach_fetch->post_id = $attach_expired_item['ID'];
$attach_fetch->during  = null;
$option_reads = 0;
$option_updates = 0;
$attach_expired_sql = count( $GLOBALS['wpdb']->statements );
$attach_expired_saved = $attach( $attach_expired_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-attach-expired', null, $attach_fetch );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
$attach_expired_release = $releases( $attach_expired_sql );
tutorpress_live_lessons_assert( $fetched( 'evt-supplied' ) === $attach_expired_saved && array( $attach_expired_bytes, array( '_tutorpress_live_lesson_sync' ) ) === $attach_fetch->during && 1 === $option_reads && 1 === $option_updates && array( 'preflight', 'fetch', 'compare_target' ) === $methods() && 1 === count( $attach_expired_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $attach_expired_release[0][2], 'token-attach-expired' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $attach_expired_release[0][2], 'token-old' ) && 'create' === json_decode( $attach_expired_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $attach_expired_name ] ), 'an expired lock is taken over and the fetch still runs' );

list( $attach_live_item, $attach_live_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_live_name = 'tutorpress_live_lesson_lock_' . $attach_live_item['ID'];
$attach_live_prior = $lock_bytes( 'token-live', 'update', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $attach_live_name ] = $attach_live_prior;
list( $attach_broken_item, $attach_broken_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_broken_name = 'tutorpress_live_lesson_lock_' . $attach_broken_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $attach_broken_name ] = '{';
TutorPress_Topic_Copy_Harness::$fail_lock = true;
$option_reads = 0;
$option_updates = 0;
$attach_live_sql = count( $GLOBALS['wpdb']->statements );
$attach_live_saved = $attach( $attach_live_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-attach-live' );
$attach_broken_saved = $attach( $attach_broken_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-attach-broken' );
TutorPress_Topic_Copy_Harness::$fail_lock = false;
tutorpress_live_lessons_assert( null === $attach_live_saved && null === $attach_broken_saved && array() === $methods() && 0 === $option_updates && $attach_live_sql === count( $GLOBALS['wpdb']->statements ) && $attach_live_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $attach_live_name ] && '{' === TutorPress_Live_Lessons_Store_Fake::$options[ $attach_broken_name ] && $attach_live_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_live_item['ID'], '_tutorpress_live_lesson_sync' ) && $attach_broken_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_broken_item['ID'], '_tutorpress_live_lesson_sync' ), 'a live or unparseable lock returns null with no fetch' );

list( $attach_mismatch_item, $attach_mismatch_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_mismatch_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$attach_mismatch_saved = $attach( $attach_mismatch_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ), false ), 'token-attach-mismatch' );
list( $attach_identity_item, $attach_identity_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_identity_saved = $attach( $attach_identity_item, $attach_google, $attach_queue( $fetched( 'evt-other' ) ), 'token-attach-identity' );
tutorpress_live_lessons_assert( null === $attach_mismatch_saved && null === $attach_identity_saved && $attach_mismatch_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_mismatch_item['ID'], '_tutorpress_live_lesson_sync' ) && $attach_identity_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_identity_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $attach_mismatch_item['ID'] ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $attach_identity_item['ID'] ) && $attach_mismatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a compare mismatch or a different top-level id leaves the stored error row' );

$attach_outcome_kept = true;
foreach ( array( 'known_id_not_found', 'uncertain', 'definite_rejection', 'pre_dispatch_failure' ) as $attach_outcome ) {
	list( $attach_outcome_item, $attach_outcome_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	$attach_outcome_id = 'pre_dispatch_failure' === $attach_outcome ? null : 'evt-supplied';
	$attach_outcome_saved = $attach( $attach_outcome_item, $attach_google, $attach_queue( $provider_result( $attach_outcome, 'fetch', $attach_outcome_id, null ) ), 'token-attach-' . $attach_outcome );
	if ( null !== $attach_outcome_saved || $attach_outcome_bytes !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_outcome_item['ID'], '_tutorpress_live_lesson_sync' ) || array( '_tutorpress_live_lesson_sync' ) !== $meta_keys( $attach_outcome_item['ID'] ) ) {
		$attach_outcome_kept = false;
	}
}
tutorpress_live_lessons_assert( $attach_outcome_kept, 'known_id_not_found, uncertain, definite_rejection, and pre_dispatch_failure leave the stored error row' );

list( $attach_throw_item, $attach_throw_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$attach_throw_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$attach_throw_sql = count( $GLOBALS['wpdb']->statements );
$attach_throw_saved = $attach( $attach_throw_item, $attach_google, array(), 'token-attach-throw', null, $attach_throw );
$attach_throw_release = $releases( $attach_throw_sql );
tutorpress_live_lessons_assert( null === $attach_throw_saved && 1 === count( $attach_throw_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $attach_throw_release[0][2], 'token-attach-throw' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $attach_throw_item['ID'] ] ) && $attach_throw_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $attach_throw_item['ID'] ) && $attach_throw_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( $attach_throw_bytes, 'attach-fetch-failed' ) && $clean( $attach_throw_release[0][2] ), 'a thrown fetch returns null, releases once, and leaves the stored error row' );

tutorpress_live_lessons_pass( 'uncertain create attach verifies the supplied id' );

$legacy_attach = static function ( array $item, array $input, array $queue, $token, $can = null, $adapter_arg = null, $write = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_write, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::attach_legacy_identity( $item, $topic, $course, null === $can ? $allow : $can, $input, null === $adapter_arg ? $adapter : $adapter_arg, $now, $token, $sync_read, null === $write ? $sync_write : $write, $option, $takeover );
};
$legacy_local = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00' );
$legacy_item_target = array( 'start_local' => '2026-09-21 10:00:00', 'end_local' => '2026-09-21 11:00:00', 'timezone' => 'America/New_York', 'provider_id' => null );
$legacy_compared = $legacy_item_target;
$legacy_compared['provider_id'] = 'evt-supplied';
$legacy_map = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00', 'tutor-google-meet-event-details' => json_encode( array( 'id' => 'evt-supplied' ) ), 'tutor-google-meet-link' => 'https://meet.example/a' );
$legacy_attach_input = $owner_input;
$legacy_attach_input['provider_id'] = 'evt-supplied';
$legacy_attach_input['operation_id'] = null;
$legacy_attach_input['local'] = $legacy_local;
$legacy_attach_input['start_local'] = '2026-01-01 00:00:00';
$legacy_attach_input['end_local'] = '2026-01-01 01:00:00';
$legacy_attach_input['duration'] = array( 'value' => 15, 'unit' => 'minutes' );
$legacy_attach_input['provider_target'] = array( 'add_enrolled_students' => 'Yes', 'host_id' => 'host-evil' );
$legacy_attach_input['title'] = 'Request title';
$legacy_attach_queue = static function ( $result, $map, $match = true ) use ( $legacy_item_target ) {
	return array( 'hydrate_legacy' => array( array( 'provider_id' => null ) ), 'hydrate_item' => array( $legacy_item_target ), 'preflight' => array( true ), 'fetch' => array( $result ), 'compare_target' => array( array( 'match' => $match ) ), 'metadata_map' => array( $map ) );
};
$legacy_attach_item = $plant( 'tutor-google-meet', null );
$legacy_attach_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$option_updates = 0;
$legacy_attach_sql = count( $GLOBALS['wpdb']->statements );
$legacy_attach_saved = $legacy_attach( $legacy_attach_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-legacy-attach' );
$legacy_attach_release = $releases( $legacy_attach_sql );
$legacy_attach_decoded = is_array( $legacy_attach_saved ) ? json_decode( $legacy_attach_saved['envelope'], true ) : null;
$legacy_attach_encoded = json_encode( $legacy_attach_decoded );
$legacy_attach_order = array_merge( array_keys( $legacy_map ), array( '_tutorpress_live_lesson_sync' ) );
$legacy_attach_values = true;
foreach ( $legacy_map as $legacy_attach_key => $legacy_attach_value ) {
	if ( $legacy_attach_value !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_item['ID'], $legacy_attach_key ) ) { $legacy_attach_values = false; }
}
tutorpress_live_lessons_assert( ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'attach_legacy_identity' ) )->isPublic() && is_array( $legacy_attach_decoded ) && $legacy_attach_values && $legacy_attach_order === $meta_keys( $legacy_attach_item['ID'] ) && 1 === $legacy_attach_decoded['version'] && 'synced' === $legacy_attach_decoded['state'] && 'google_meet' === $legacy_attach_decoded['provider'] && 'evt-supplied' === $legacy_attach_decoded['provider_id'] && 11 === $legacy_attach_decoded['owner_id'] && null === $legacy_attach_decoded['operation'] && null === $legacy_attach_decoded['error'] && null === $legacy_attach_decoded['warning'] && false === $legacy_attach_decoded['reconciliation_required'] && null === $legacy_attach_decoded['provenance'] && array( array( 'hydrate_legacy', array( 'target' => $legacy_local ) ), array( 'hydrate_item', array( 'target' => $legacy_local ) ), array( 'preflight', array( 'owner_id' => 11 ) ), array( 'fetch', array( 'owner_id' => 11, 'provider_id' => 'evt-supplied' ) ), array( 'compare_target', array( 'target' => $legacy_compared ) ), array( 'metadata_map', array( 'target' => $legacy_compared ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && 1 === count( $legacy_attach_release ) && $lock_sql === $legacy_attach_release[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $legacy_attach_release[0][2], 'token-legacy-attach' ) && 'create' === json_decode( $legacy_attach_release[0][2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $legacy_attach_item['ID'] ] ) && $legacy_attach_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && is_string( $legacy_attach_encoded ) && false === strpos( $legacy_attach_encoded, $secret ) && false === strpos( $legacy_attach_encoded, 'meet-event-sentinel' ) && false === strpos( $legacy_attach_encoded, 'Request title' ) && false === strpos( $legacy_attach_encoded, '2026-01-01' ) && false === strpos( json_encode( $legacy_compared ), '2026-01-01' ) && false === strpos( json_encode( $legacy_compared ), 'host-evil' ), 'legacy attach stores the complete google map before the version-1 synced row and does not fire a save hook' );

$zoom_legacy_local = array( '_tutor_zm_start_datetime' => '2026-09-21 10:00:00' );
$zoom_legacy_item_target = array( 'start_local' => '2026-09-21 10:00:00', 'timezone' => 'America/New_York', 'duration' => array( 'value' => 60, 'unit' => 'minutes' ), 'course_id' => 7, 'topic_id' => 8, 'provider_id' => null, 'host_id' => 'host-1' );
$zoom_legacy_compared = $zoom_legacy_item_target;
$zoom_legacy_compared['provider_id'] = 'z-supplied';
$zoom_legacy_map = array( '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 10:00:00', '_tutor_zm_duration' => 60, '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 7, '_tutor_zm_for_topic' => 8, '_tutor_zm_data' => json_encode( array( 'id' => 'z-supplied' ) ) );
$zoom_legacy_attach_input = $zoom_owner_input;
$zoom_legacy_attach_input['provider_id'] = 'z-supplied';
$zoom_legacy_attach_input['operation_id'] = null;
$zoom_legacy_attach_input['local'] = $zoom_legacy_local;
$zoom_legacy_attach_input['duration'] = array( 'value' => 15, 'unit' => 'minutes' );
$zoom_legacy_attach_input['provider_target'] = array( 'host_id' => 'host-evil' );
$zoom_legacy_attach_item = $plant( 'tutor_zoom_meeting', null );
$zoom_legacy_attach_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$option_reads = 0;
$zoom_legacy_attach_sql = count( $GLOBALS['wpdb']->statements );
$zoom_legacy_attach_saved = $legacy_attach( $zoom_legacy_attach_item, $zoom_legacy_attach_input, array( 'hydrate_legacy' => array( array( 'provider_id' => null ) ), 'hydrate_item' => array( $zoom_legacy_item_target ), 'fetch' => array( $fetched( 'z-supplied' ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $zoom_legacy_map ) ), 'token-legacy-attach-zoom' );
$zoom_legacy_attach_release = $releases( $zoom_legacy_attach_sql );
$zoom_legacy_attach_decoded = is_array( $zoom_legacy_attach_saved ) ? json_decode( $zoom_legacy_attach_saved['envelope'], true ) : null;
$zoom_legacy_attach_values = true;
foreach ( $zoom_legacy_map as $zoom_legacy_attach_key => $zoom_legacy_attach_value ) {
	if ( $zoom_legacy_attach_value !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_legacy_attach_item['ID'], $zoom_legacy_attach_key ) ) { $zoom_legacy_attach_values = false; }
}
tutorpress_live_lessons_assert( $zoom_legacy_attach_values && array_merge( array_keys( $zoom_legacy_map ), array( '_tutorpress_live_lesson_sync' ) ) === $meta_keys( $zoom_legacy_attach_item['ID'] ) && is_array( $zoom_legacy_attach_decoded ) && 1 === $zoom_legacy_attach_decoded['version'] && 'synced' === $zoom_legacy_attach_decoded['state'] && 'zoom' === $zoom_legacy_attach_decoded['provider'] && 'z-supplied' === $zoom_legacy_attach_decoded['provider_id'] && 42 === $zoom_legacy_attach_decoded['owner_id'] && null === $zoom_legacy_attach_decoded['operation'] && array( array( 'hydrate_legacy', array( 'target' => $zoom_legacy_local ) ), array( 'hydrate_item', array( 'target' => $zoom_legacy_local ) ), array( 'fetch', array( 'owner_id' => 42, 'provider_id' => 'z-supplied' ) ), array( 'compare_target', array( 'target' => $zoom_legacy_compared ) ), array( 'metadata_map', array( 'target' => $zoom_legacy_compared ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && 1 === count( $zoom_legacy_attach_release ) && 'create' === json_decode( $zoom_legacy_attach_release[0][2], true )['operation'] && $zoom_legacy_attach_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( json_encode( $zoom_legacy_compared ), 'host-evil' ) && false === strpos( json_encode( $zoom_legacy_attach_decoded ), $secret ), 'legacy zoom attach stores the seven compatible keys before version one' );

$legacy_attach_mismatch_item = $plant( 'tutor-google-meet', null );
$legacy_attach_mismatch_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$legacy_attach_mismatch_saved = $legacy_attach( $legacy_attach_mismatch_item, $legacy_attach_input, array( 'hydrate_legacy' => array( array( 'provider_id' => null ) ), 'hydrate_item' => array( $legacy_item_target ), 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-supplied' ) ), 'compare_target' => array( array( 'match' => false ) ) ), 'token-legacy-attach-mismatch' );
$legacy_attach_kept = true;
foreach ( array( 'definite_rejection', 'uncertain', 'known_id_not_found', 'pre_dispatch_failure' ) as $legacy_attach_outcome ) {
	$legacy_attach_outcome_item = $plant( 'tutor-google-meet', null );
	$legacy_attach_outcome_id = 'pre_dispatch_failure' === $legacy_attach_outcome ? null : 'evt-supplied';
	$legacy_attach_outcome_saved = $legacy_attach( $legacy_attach_outcome_item, $legacy_attach_input, array( 'hydrate_legacy' => array( array( 'provider_id' => null ) ), 'hydrate_item' => array( $legacy_item_target ), 'preflight' => array( true ), 'fetch' => array( $provider_result( $legacy_attach_outcome, 'fetch', $legacy_attach_outcome_id, null ) ) ), 'token-legacy-attach-' . $legacy_attach_outcome );
	if ( null !== $legacy_attach_outcome_saved || null !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_outcome_item['ID'], '_tutorpress_live_lesson_sync' ) || array() !== $meta_keys( $legacy_attach_outcome_item['ID'] ) ) {
		$legacy_attach_kept = false;
	}
}
$legacy_attach_throw = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public function fetch( $owner_id, $provider_id ) { throw new RuntimeException( 'legacy-attach-fetch-failed' ); }
};
$legacy_attach_throw_item = $plant( 'tutor-google-meet', null );
$legacy_attach_throw_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$legacy_attach_throw_sql = count( $GLOBALS['wpdb']->statements );
$legacy_attach_throw_saved = $legacy_attach( $legacy_attach_throw_item, $legacy_attach_input, array( 'hydrate_legacy' => array( array( 'provider_id' => null ) ), 'hydrate_item' => array( $legacy_item_target ), 'preflight' => array( true ) ), 'token-legacy-attach-throw', null, $legacy_attach_throw );
$legacy_attach_throw_release = $releases( $legacy_attach_throw_sql );
tutorpress_live_lessons_assert( null === $legacy_attach_mismatch_saved && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_mismatch_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $meta_keys( $legacy_attach_mismatch_item['ID'] ) && $legacy_attach_mismatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $legacy_attach_kept && null === $legacy_attach_throw_saved && 1 === count( $legacy_attach_throw_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $legacy_attach_throw_release[0][2], 'token-legacy-attach-throw' ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && $legacy_attach_throw_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( json_encode( $legacy_attach_throw_release[0][2] ), 'legacy-attach-fetch-failed' ), 'mismatch, rejection, uncertainty, and a thrown fetch leave version zero' );

$legacy_attach_edit = $edit_input;
$legacy_attach_edit['provider_id'] = 'evt-supplied';
$legacy_attach_edit_before = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $edit_item['ID'], '_tutorpress_live_lesson_sync' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$legacy_attach_edit_saved = TutorPress_Live_Lesson_Sync_Orchestrator::stage_update( $edit_item, $topic, $course, $allow, $legacy_attach_edit, $adapter, $now, 'token-legacy-edit', $sync_read, $sync_update, $sync_affected );
tutorpress_live_lessons_assert( null === $legacy_attach_edit_saved && array() === $methods() && $legacy_attach_edit_before === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $edit_item['ID'], '_tutorpress_live_lesson_sync' ), 'an existing stage_update call with a supplied provider id returns null' );

tutorpress_live_lessons_pass( 'legacy attach writes the full map before version one' );

$settle = static function ( array $item, array $input, array $queue, $token, $adapter_arg = null, $write = null, $update = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected, $sync_write, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::attach_provider_identity( $item, $topic, $course, $allow, $input, null === $adapter_arg ? $adapter : $adapter_arg, $now, $token, $sync_read, $option, $takeover, null === $write ? $sync_write : $write, null === $update ? $sync_update : $update, $sync_affected );
};
$finalize = static function ( array $item, array $input, array $queue, $token, $can = null, $adapter_arg = null, $read = null, $update = null, $write = null ) use ( $topic, $course, $allow, $adapter, $now, $sync_read, $sync_update, $sync_affected, $sync_write, $option, $takeover ) {
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = $queue;
	return TutorPress_Live_Lesson_Sync_Orchestrator::finalize_update( $item, $topic, $course, null === $can ? $allow : $can, $input, null === $adapter_arg ? $adapter : $adapter_arg, $now, $token, null === $read ? $sync_read : $read, null === $update ? $sync_update : $update, $sync_affected, null === $write ? $sync_write : $write, $option, $takeover );
};
$full_queue = static function ( $result, $map, $match = true ) {
	return array( 'preflight' => array( true ), 'fetch' => array( $result ), 'compare_target' => array( array( 'match' => $match ) ), 'metadata_map' => array( $map ) );
};
$google_map = array( 'tutor-google-meet-start-datetime' => '2026-09-21 10:00:00', 'tutor-google-meet-end-datetime' => '2026-09-21 11:00:00', 'tutor-google-meet-event-details' => json_encode( array( 'id' => 'evt-supplied' ) ), 'tutor-google-meet-link' => 'https://meet.example/a' );
$stored_map = static function ( $post_id, array $map ) {
	foreach ( $map as $key => $value ) {
		if ( $value !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post_id, $key ) ) {
			return false;
		}
	}
	return true;
};
list( $final_item, $final_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$final_operation = json_decode( $final_bytes, true )['operation'];
$final_expected = array_merge( $final_operation, $final_operation['provider_target'] );
$final_during = array();
$final_write = static function ( $post_id, $key, $value ) use ( &$final_during ) {
	$final_during[] = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post_id, '_tutorpress_live_lesson_sync' );
	return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, $value );
};
$saved_wpdb = $GLOBALS['wpdb'];
$watched    = new class() extends TutorPress_Topic_Copy_Wpdb {
	public $hooks_at_delete = array();
	public function query( $prepared ) {
		$this->hooks_at_delete[] = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
		return parent::query( $prepared );
	}
};
$GLOBALS['wpdb'] = $watched;
$final_hook_count = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$final_saved = $settle( $final_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-final-google', null, $final_write );
$final_statements = $watched->statements;
$final_hook_at = $watched->hooks_at_delete;
$GLOBALS['wpdb'] = $saved_wpdb;
$final_decoded = is_array( $final_saved ) ? json_decode( $final_saved['envelope'], true ) : null;
$final_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $final_hook_count );
$final_same = array() !== $final_during;
foreach ( $final_during as $final_seen ) {
	if ( $final_bytes !== $final_seen ) { $final_same = false; }
}
tutorpress_live_lessons_assert( ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'finalize_update' ) )->isPublic() && ( new ReflectionMethod( 'TutorPress_Live_Lesson_Sync_Orchestrator', 'settle_attach' ) )->isPrivate() && $final_same && $stored_map( $final_item['ID'], $google_map ) && is_array( $final_decoded ) && 5 === $final_decoded['version'] && 'synced' === $final_decoded['state'] && 'evt-supplied' === $final_decoded['provider_id'] && 'fetch' === $final_decoded['provenance'] && false === $final_decoded['reconciliation_required'] && null === $final_decoded['error'] && 'confirmed' === $final_decoded['operation']['dispatch_state'] && $op_id === $final_decoded['operation']['operation_id'] && 'evt-supplied' === $final_decoded['operation']['provider_id'] && 'Copied' === $final_decoded['operation']['title'] && '2026-09-21 10:00:00' === $final_decoded['operation']['start_local'] && 'No' === $final_decoded['operation']['provider_target']['add_enrolled_students'] && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'fetch', array( 'owner_id' => 11, 'provider_id' => 'evt-supplied' ) ), array( 'compare_target', array( 'target' => $final_expected ) ), array( 'metadata_map', array( 'target' => $final_expected ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && 1 === count( $final_statements ) && $lock_sql === $final_statements[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $final_statements[0][2], 'token-final-google' ) && 'create' === json_decode( $final_statements[0][2], true )['operation'] && array( $final_hook_count ) === $final_hook_at && array( array( 'tutor_google_meet_after_save_meeting', array( $final_item['ID'] ) ) ) === $final_hooks && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $final_item['ID'] ] ) && false === strpos( json_encode( $final_expected ), 'Request title' ) && false === strpos( json_encode( $final_expected ), 'evt-body' ) && false === strpos( json_encode( $final_decoded ), $secret ), 'confirmed attach stores the complete map before the synced envelope and releases before the save hook' );

$zoom_map = array( '_tutor_zm_start_date' => '2026-09-21', '_tutor_zm_start_datetime' => '2026-09-21 10:00:00', '_tutor_zm_duration' => 60, '_tutor_zm_duration_unit' => 'min', '_tutor_zm_for_course' => 7, '_tutor_zm_for_topic' => 8, '_tutor_zm_data' => json_encode( array( 'id' => 'z-supplied' ) ) );
list( $zoom_final_item, $zoom_final_bytes ) = $plant_row( 'tutor_zoom_meeting', 'zoom', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$zoom_final_operation = json_decode( $zoom_final_bytes, true )['operation'];
$zoom_final_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$zoom_final_sql = count( $GLOBALS['wpdb']->statements );
$zoom_final_saved = $settle( $zoom_final_item, $attach_zoom, $full_queue( $fetched( 'z-supplied' ), $zoom_map ), 'token-final-zoom' );
$zoom_final_release = $releases( $zoom_final_sql );
$zoom_final_decoded = is_array( $zoom_final_saved ) ? json_decode( $zoom_final_saved['envelope'], true ) : null;
$zoom_final_new = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $zoom_final_hooks ) );
tutorpress_live_lessons_assert( $stored_map( $zoom_final_item['ID'], $zoom_map ) && is_array( $zoom_final_decoded ) && 'synced' === $zoom_final_decoded['state'] && 'fetch' === $zoom_final_decoded['provenance'] && 'z-supplied' === $zoom_final_decoded['provider_id'] && 'confirmed' === $zoom_final_decoded['operation']['dispatch_state'] && array( array( 'compare_target', array( 'target' => $zoom_final_operation ) ), array( 'metadata_map', array( 'target' => $zoom_final_operation ) ) ) === array_slice( TutorPress_Live_Lessons_Adapter_Fake::$calls, 2 ) && 1 === count( $zoom_final_release ) && array( array( 'tutor_zoom_after_save_meeting', array( $zoom_final_item['ID'] ) ) ) === $zoom_final_new && false === strpos( json_encode( $zoom_final_operation ), 'evt-body' ), 'zoom attach fires the one-argument zoom save hook after the stored target matches' );

list( $hook_item, $hook_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
$hook_saved = $settle( $hook_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-final-hook' );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array();
$hook_decoded = json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $hook_item['ID'], '_tutorpress_live_lesson_sync' ), true );
$hook_data = $hook_saved instanceof WP_Error ? $hook_saved->get_error_data() : null;
tutorpress_live_lessons_assert( $hook_saved instanceof WP_Error && 'live_lesson_persisted_transition' === $hook_saved->get_error_code() && is_array( $hook_data ) && true === $hook_data['persisted'] && is_array( $hook_decoded ) && 'synced' === $hook_decoded['state'] && 'evt-supplied' === $hook_decoded['provider_id'] && $stored_map( $hook_item['ID'], $google_map ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_item['ID'] ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_item['ID'] ]['post_status'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $hook_item['ID'] ] ) && false === strpos( $hook_saved->get_error_message(), 'hook-exploded-secret' ) && false === strpos( json_encode( $hook_data ), 'hook-exploded-secret' ) && false === strpos( json_encode( $hook_decoded ), 'hook-exploded-secret' ), 'a save-hook throwable keeps the synced post and returns the persisted transition error' );

list( $empty_item, $empty_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$empty_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$empty_sql = count( $GLOBALS['wpdb']->statements );
$empty_saved = $settle( $empty_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), array() ), 'token-final-empty' );
$empty_release = $releases( $empty_sql );
list( $reread_item, $reread_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$reread_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$reread_saved = $settle( $reread_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-final-reread', null, static function () { return 1; } );
tutorpress_live_lessons_assert( null === $empty_saved && null === $reread_saved && $empty_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $empty_item['ID'], '_tutorpress_live_lesson_sync' ) && $reread_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $reread_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $empty_item['ID'] ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $reread_item['ID'] ) && 1 === count( $empty_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $empty_release[0][2], 'token-final-empty' ) && $empty_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $reread_hooks === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, 0, count( $reread_hooks ) ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $empty_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $reread_item['ID'] ] ), 'an empty or failed metadata re-read leaves the error row and does not fire a hook' );

$update_input = $evil;
$update_input['acting_user_id'] = 11;
$update_map = $google_map;
$update_map['tutor-google-meet-event-details'] = json_encode( array( 'id' => 'evt-stored' ) );
list( $update_item, $update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$update_operation = json_decode( $update_bytes, true )['operation'];
$update_expected = array_merge( $update_operation, $update_operation['provider_target'] );
$update_name = 'tutorpress_live_lesson_lock_' . $update_item['ID'];
TutorPress_Live_Lessons_Store_Fake::$options[ $update_name ] = $lock_bytes( 'token-old', 'create', '2026-09-21 16:00:00' );
$update_during = array();
$update_write = static function ( $post_id, $key, $value ) use ( &$update_during ) {
	$update_during[] = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post_id, '_tutorpress_live_lesson_sync' );
	return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, $value );
};
$option_reads = 0;
$option_updates = 0;
$update_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$update_sql = count( $GLOBALS['wpdb']->statements );
$update_saved = $finalize( $update_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-update', null, null, null, null, $update_write );
$update_release = $releases( $update_sql );
$update_decoded = is_array( $update_saved ) ? json_decode( $update_saved['envelope'], true ) : null;
$update_same = array() !== $update_during;
foreach ( $update_during as $update_seen ) {
	if ( $update_bytes !== $update_seen ) { $update_same = false; }
}
tutorpress_live_lessons_assert( $update_same && $stored_map( $update_item['ID'], $update_map ) && is_array( $update_decoded ) && 5 === $update_decoded['version'] && 'synced' === $update_decoded['state'] && 'update' === $update_decoded['provenance'] && 'evt-stored' === $update_decoded['provider_id'] && false === $update_decoded['reconciliation_required'] && null === $update_decoded['error'] && 'confirmed' === $update_decoded['operation']['dispatch_state'] && $op_id === $update_decoded['operation']['operation_id'] && 'Copied' === $update_decoded['operation']['title'] && '2026-09-21 10:00:00' === $update_decoded['operation']['start_local'] && 1 === $option_reads && 1 === $option_updates && array( array( 'preflight', array( 'owner_id' => 11 ) ), array( 'fetch', array( 'owner_id' => 11, 'provider_id' => 'evt-stored' ) ), array( 'compare_target', array( 'target' => $update_expected ) ), array( 'metadata_map', array( 'target' => $update_expected ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls && 1 === count( $update_release ) && TutorPress_Live_Lesson_Sync_Repository::owns( $update_release[0][2], 'token-final-update' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $update_release[0][2], 'token-old' ) && 'update' === json_decode( $update_release[0][2], true )['operation'] && $update_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $update_name ] ) && $stored_target( $update_expected ) && false === strpos( json_encode( $update_decoded ), 'evt-evil' ) && false === strpos( json_encode( $update_decoded ), $secret ), 'finalize update confirms the stored id and target, syncs, releases once, and fires no save hook' );

$stale_input = $update_input;
$stale_input['version'] = 9;
$stale_operation = $update_input;
$stale_operation['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
list( $stale_item, $stale_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$option_reads = 0;
$stale_sql = count( $GLOBALS['wpdb']->statements );
$broad_saved = $finalize( $stale_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-broad', $broad );
$stale_saved = $finalize( $stale_item, $stale_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-stale-version' );
$stale_operation_saved = $finalize( $stale_item, $stale_operation, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-stale-operation' );
tutorpress_live_lessons_assert( null === $broad_saved && null === $stale_saved && null === $stale_operation_saved && array() === $methods() && 0 === $option_reads && $stale_sql === count( $GLOBALS['wpdb']->statements ) && $stale_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $stale_item['ID'], '_tutorpress_live_lesson_sync' ), 'broad edit_posts, a stale version, or a stale operation does not finalize' );

list( $mismatch_update_item, $mismatch_update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$mismatch_update_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$mismatch_update_saved = $finalize( $mismatch_update_item, $update_input, array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-stored' ) ), 'compare_target' => array( array( 'match' => false ) ) ), 'token-final-mismatch' );
$uncertain_update_saved = $finalize( $mismatch_update_item, $update_input, array( 'preflight' => array( true ), 'fetch' => array( $provider_result( 'uncertain', 'fetch', 'evt-stored', null ) ) ), 'token-final-uncertain' );
$rejected_update_saved = $finalize( $mismatch_update_item, $update_input, array( 'preflight' => array( true ), 'fetch' => array( $reject( 'fetch', 'evt-stored' ) ) ), 'token-final-rejected' );
$update_throw = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
	public function fetch( $owner_id, $provider_id ) { throw new RuntimeException( 'finalize-fetch-failed' ); }
};
$thrown_update_saved = $finalize( $mismatch_update_item, $update_input, array( 'preflight' => array( true ) ), 'token-final-throw', null, $update_throw );
tutorpress_live_lessons_assert( null === $mismatch_update_saved && null === $uncertain_update_saved && null === $rejected_update_saved && null === $thrown_update_saved && $mismatch_update_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $mismatch_update_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $mismatch_update_item['ID'] ) && $mismatch_update_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === strpos( $mismatch_update_bytes, 'finalize-fetch-failed' ), 'a target mismatch, uncertain fetch, rejected fetch, or thrown fetch does not finalize' );

list( $live_update_item, $live_update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$live_update_name = 'tutorpress_live_lesson_lock_' . $live_update_item['ID'];
$live_update_prior = $lock_bytes( 'token-live', 'update', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $live_update_name ] = $live_update_prior;
list( $broken_update_item, $broken_update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $broken_update_item['ID'] ] = '{';
$option_reads = 0;
$option_updates = 0;
$live_update_sql = count( $GLOBALS['wpdb']->statements );
$live_update_saved = $finalize( $live_update_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-live' );
$broken_update_saved = $finalize( $broken_update_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-broken' );
tutorpress_live_lessons_assert( null === $live_update_saved && null === $broken_update_saved && array() === $methods() && 0 === $option_updates && $live_update_sql === count( $GLOBALS['wpdb']->statements ) && $live_update_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_update_name ] && $live_update_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_update_item['ID'], '_tutorpress_live_lesson_sync' ) && $broken_update_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $broken_update_item['ID'], '_tutorpress_live_lesson_sync' ), 'a live or unparseable lock does not finalize and does not fetch' );

list( $meta_update_item, $meta_update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$meta_update_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$meta_update_saved = $finalize( $meta_update_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-meta', null, null, null, null, static function () { return 1; } );
$changed_reads = 0;
$changed_update_read = static function ( $post_id, $key ) use ( &$changed_reads, $sync_read ) {
	if ( '_tutorpress_live_lesson_sync' === $key && ++$changed_reads > 1 ) {
		return array( array( 'meta_id' => 1, 'post_id' => (int) $post_id, 'meta_key' => $key, 'meta_value' => '{' ) );
	}
	return $sync_read( $post_id, $key );
};
list( $changed_update_item, $changed_update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$changed_update_saved = $finalize( $changed_update_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-changed', null, null, $changed_update_read );
$changed_update_methods = $methods();
list( $exchange_item, $exchange_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$exchange_saved = $finalize( $exchange_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-final-exchange', null, null, null, $failed_update );
tutorpress_live_lessons_assert( null === $meta_update_saved && null === $changed_update_saved && null === $exchange_saved && $meta_update_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $meta_update_item['ID'], '_tutorpress_live_lesson_sync' ) && $changed_update_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $changed_update_item['ID'], '_tutorpress_live_lesson_sync' ) && $exchange_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $exchange_item['ID'], '_tutorpress_live_lesson_sync' ) && array( '_tutorpress_live_lesson_sync' ) === $meta_keys( $meta_update_item['ID'] ) && array( 'preflight' ) === $changed_update_methods && $meta_update_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a failed metadata re-read, changed prior, or failed exchange does not finalize' );

list( $google_delete_item, $google_delete_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
$google_delete_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$google_delete_sql = count( $GLOBALS['wpdb']->statements );
$google_delete_saved = $finalize( $google_delete_item, $update_input, array( 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-final-google-delete' );
list( $zoom_delete_final_item, $zoom_delete_final_bytes ) = $plant_row( 'tutor_zoom_meeting', 'zoom', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-zoom', null );
$zoom_delete_final_saved = $finalize( $zoom_delete_final_item, array( 'version' => 4, 'operation_id' => $op_id, 'course_author_id' => 42, 'acting_instructor_id' => 9, 'credentials' => $creds ), array( 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-zoom', null ) ) ), 'token-final-zoom-delete' );
$delete_methods = $methods();
$delete_sql = count( $GLOBALS['wpdb']->statements );
$delete_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
list( $retry_delete_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
$retry_delete_saved = $retry( $retry_delete_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-final-retry-delete' );
$legacy_hook_item = $plant( 'tutor-google-meet', null );
$legacy_hook_before = TutorPress_Live_Lessons_Hook_Fake::$calls;
$legacy_hook_saved = $legacy_attach( $legacy_hook_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-final-legacy-hook' );
tutorpress_live_lessons_assert( null === $google_delete_saved && null === $zoom_delete_final_saved && array() === $delete_methods && $google_delete_sql === $delete_sql && $google_delete_hooks === $delete_hooks && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $google_delete_item['ID'] ] ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $zoom_delete_final_item['ID'] ] ) && $google_delete_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $google_delete_item['ID'], '_tutorpress_live_lesson_sync' ) && $zoom_delete_final_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $zoom_delete_final_item['ID'], '_tutorpress_live_lesson_sync' ) && is_array( $retry_delete_saved ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $retry_delete_item['ID'] ] ) && is_array( $legacy_hook_saved ) && $legacy_hook_before === TutorPress_Live_Lessons_Hook_Fake::$calls, 'google delete still converges in retry, zoom delete does not finalize, and legacy attach fires no save hook' );

tutorpress_live_lessons_pass( 'reconciliation finalizes matched attach and update' );

$matrix_saved_wpdb = $GLOBALS['wpdb'];
$matrix_watch      = new class() extends TutorPress_Topic_Copy_Wpdb {
	public $ledger_at    = array();
	public $script_false = false;
	public function prepare( $query, $name, $prior ) {
		$this->ledger_at[] = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
		return parent::prepare( $query, $name, $prior );
	}
	public function query( $prepared ) {
		$result = parent::query( $prepared );
		if ( $this->script_false ) {
			$this->rows_affected = 0;
		}
		return $result;
	}
};
$GLOBALS['wpdb'] = $matrix_watch;
try {
	$matrix_mark = static function () {
		return array( count( $GLOBALS['wpdb']->statements ), count( $GLOBALS['wpdb']->ledger_at ), count( TutorPress_Live_Lessons_Hook_Fake::$calls ) );
	};
	$matrix_bound = static function ( $sql_from, $ledger_from, $post_id, $token, $hooks_before, $sql_to = null, $ledger_to = null ) use ( $lock_sql ) {
		$found = array();
		foreach ( array_slice( $GLOBALS['wpdb']->statements, $sql_from, null === $sql_to ? null : $sql_to - $sql_from ) as $statement ) {
			if ( is_array( $statement ) && $lock_sql === $statement[0] ) {
				$found[] = $statement;
			}
		}
		$lengths = array_slice( $GLOBALS['wpdb']->ledger_at, $ledger_from, null === $ledger_to ? null : $ledger_to - $ledger_from );
		$name    = 'tutorpress_live_lesson_lock_' . (int) $post_id;
		$once    = 1 === count( $found ) && 1 === count( $lengths ) && $hooks_before === $lengths[0] && $name === $found[0][1] && TutorPress_Live_Lesson_Sync_Repository::owns( $found[0][2], $token );
		return $once ? $found[0] : null;
	};
	$matrix_hooks = static function ( $from, $to = null ) {
		return array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $from, null === $to ? null : $to - $from );
	};
	$matrix_names = static function ( $from, $to = null ) use ( $matrix_hooks ) {
		$names = array();
		foreach ( $matrix_hooks( $from, $to ) as $call ) {
			$names[] = $call[0];
		}
		return $names;
	};
	$matrix_safe = static function ( $value ) use ( $secret ) {
		$encoded = json_encode( $value );
		return is_string( $encoded ) && false === strpos( $encoded, $secret );
	};
	$matrix_hidden = static function ( $saved, $post_id, $hooks_from, $extra = '', $hooks_to = null ) use ( $matrix_safe, $matrix_hooks, $secret ) {
		$meta    = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post_id, '_tutorpress_live_lesson_sync' );
		$data    = $saved instanceof WP_Error ? $saved->get_error_data() : null;
		$message = $saved instanceof WP_Error ? $saved->get_error_message() : '';
		return $matrix_safe( $saved ) && $matrix_safe( $meta ) && $matrix_safe( $data ) && $matrix_safe( $matrix_hooks( $hooks_from, $hooks_to ) ) && false === strpos( (string) $message, $secret ) && ( '' === $extra || false === strpos( (string) $meta, $extra ) ) && ( '' === $extra || false === strpos( (string) $message, $extra ) );
	};
	$matrix_lock = static function ( $post_id, $token, $operation, $expiry ) use ( $lock_bytes ) {
		$name  = 'tutorpress_live_lesson_lock_' . (int) $post_id;
		$prior = $lock_bytes( $token, $operation, $expiry );
		TutorPress_Live_Lessons_Store_Fake::$options[ $name ] = $prior;
		return array( $name, $prior );
	};
	$matrix_swap = static function ( $post_id, $operation, $next_token ) use ( $lock_bytes ) {
		$name        = 'tutorpress_live_lesson_lock_' . (int) $post_id;
		$original    = TutorPress_Live_Lessons_Store_Fake::$options[ $name ];
		$replacement = $lock_bytes( $next_token, $operation, '2026-09-21 16:10:00' );
		TutorPress_Live_Lessons_Store_Fake::update_option_bytes( $name, $replacement, $original );
		return array( $original, $replacement, $name );
	};
	$matrix_spy = new class( $matrix_swap ) extends TutorPress_Live_Lessons_Adapter_Fake {
		public $post_id    = 0;
		public $next_token = '';
		public $operation  = 'create';
		public $swap_on    = '';
		public $seen       = null;
		public $pair       = null;
		private $swap;
		public function __construct( $swap ) { $this->swap = $swap; }
		private function sample( $do_swap ) {
			if ( $do_swap && '' !== $this->next_token ) {
				$this->pair = call_user_func( $this->swap, $this->post_id, $this->operation, $this->next_token );
			}
			$name       = 'tutorpress_live_lesson_lock_' . (int) $this->post_id;
			$this->seen = array( TutorPress_Live_Lessons_Store_Fake::meta_bytes( $this->post_id, '_tutorpress_live_lesson_sync' ), isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $name ] : null );
		}
		public function preflight( $owner_id ): bool { $this->sample( 'preflight' === $this->swap_on ); return parent::preflight( $owner_id ); }
		public function fetch( $owner_id, $provider_id ) { $this->sample( 'fetch' === $this->swap_on ); return parent::fetch( $owner_id, $provider_id ); }
		public function create( $owner_id, array $target ) { $this->sample( 'create' === $this->swap_on ); return parent::create( $owner_id, $target ); }
		public function delete( $owner_id, $provider_id ) { $this->sample( 'delete' === $this->swap_on ); return parent::delete( $owner_id, $provider_id ); }
	};
	$matrix_throw = new class() extends TutorPress_Live_Lessons_Adapter_Fake {
		public $method = '';
		public function preflight( $owner_id ): bool { if ( 'preflight' === $this->method ) { throw new RuntimeException( 'matrix-preflight-secret' ); } return parent::preflight( $owner_id ); }
		public function fetch( $owner_id, $provider_id ) { if ( 'fetch' === $this->method ) { throw new RuntimeException( 'matrix-fetch-secret' ); } return parent::fetch( $owner_id, $provider_id ); }
		public function create( $owner_id, array $target ) { if ( 'create' === $this->method ) { throw new RuntimeException( 'matrix-create-secret' ); } return parent::create( $owner_id, $target ); }
		public function delete( $owner_id, $provider_id ) { if ( 'delete' === $this->method ) { throw new RuntimeException( 'matrix-delete-secret' ); } return parent::delete( $owner_id, $provider_id ); }
	};
	$matrix_call = static function ( $callback ) {
		try {
			return array( $callback(), null );
		} catch ( RuntimeException $caught ) {
			if ( $caught instanceof TutorPress_Live_Lessons_Assertion_Failure ) { throw $caught; }
			return array( null, $caught );
		}
	};
	$matrix_arm = static function ( $post_id, $swap_on, $next_token, $operation ) use ( $matrix_spy ) {
		$matrix_spy->post_id = (int) $post_id; $matrix_spy->swap_on = $swap_on; $matrix_spy->next_token = $next_token; $matrix_spy->operation = $operation; $matrix_spy->seen = null; $matrix_spy->pair = null;
	};
	$matrix_reject_fetch = array( 'preflight' => array( true ), 'fetch' => array( $reject( 'fetch', 'evt-stored' ) ), 'compare_target' => array( array( 'match' => false ) ) );
	$matrix_uncertain_fetch = array( 'preflight' => array( true ), 'fetch' => array( $provider_result( 'uncertain', 'fetch', 'evt-stored', null ) ), 'compare_target' => array( array( 'match' => false ) ) );

	$inspect_ok_item = $plant( 'tutor-google-meet', $google_bytes );
	list( $inspect_ok_sql, $inspect_ok_ledger, $inspect_ok_hooks ) = $matrix_mark();
	$inspect_ok_saved = $inspect( $inspect_ok_item, $google_input, $google_queue, 'token-matrix-inspect-ok' );
	$inspect_ok_delete = $matrix_bound( $inspect_ok_sql, $inspect_ok_ledger, $inspect_ok_item['ID'], 'token-matrix-inspect-ok', $inspect_ok_hooks );
	tutorpress_live_lessons_assert( array( 'match' => true ) === $inspect_ok_saved && is_array( $inspect_ok_delete ) && 'update' === json_decode( $inspect_ok_delete[2], true )['operation'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $inspect_ok_item['ID'] ] ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $inspect_ok_item['ID'], '_tutorpress_live_lesson_sync' ) && $inspect_ok_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $inspect_ok_saved, $inspect_ok_item['ID'], $inspect_ok_hooks ), 'inspect success releases the acquired token once and fires no hook' );

	$inspect_reject_item = $plant( 'tutor-google-meet', $google_bytes );
	list( $inspect_reject_sql, $inspect_reject_ledger, $inspect_reject_hooks ) = $matrix_mark();
	$inspect_reject_saved = $inspect( $inspect_reject_item, $google_input, $matrix_reject_fetch, 'token-matrix-inspect-reject' );
	$inspect_uncertain_item = $plant( 'tutor-google-meet', $google_bytes );
	list( $inspect_uncertain_sql, $inspect_uncertain_ledger, $inspect_uncertain_hooks ) = $matrix_mark();
	$inspect_uncertain_saved = $inspect( $inspect_uncertain_item, $google_input, $matrix_uncertain_fetch, 'token-matrix-inspect-uncertain' );
	$matrix_throw->method = 'fetch';
	$inspect_throw_item = $plant( 'tutor-google-meet', $google_bytes );
	list( $inspect_throw_sql, $inspect_throw_ledger, $inspect_throw_hooks ) = $matrix_mark();
	$inspect_throw_saved = $inspect( $inspect_throw_item, $google_input, array( 'preflight' => array( true ) ), 'token-matrix-inspect-throw', null, $matrix_throw );
	$matrix_throw->method = '';
	tutorpress_live_lessons_assert( array( 'match' => false ) === $inspect_reject_saved && is_array( $matrix_bound( $inspect_reject_sql, $inspect_reject_ledger, $inspect_reject_item['ID'], 'token-matrix-inspect-reject', $inspect_reject_hooks, $inspect_uncertain_sql, $inspect_uncertain_ledger ) ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $inspect_reject_item['ID'], '_tutorpress_live_lesson_sync' ) && array( 'match' => false ) === $inspect_uncertain_saved && is_array( $matrix_bound( $inspect_uncertain_sql, $inspect_uncertain_ledger, $inspect_uncertain_item['ID'], 'token-matrix-inspect-uncertain', $inspect_uncertain_hooks, $inspect_throw_sql, $inspect_throw_ledger ) ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $inspect_uncertain_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $inspect_throw_saved && is_array( $matrix_bound( $inspect_throw_sql, $inspect_throw_ledger, $inspect_throw_item['ID'], 'token-matrix-inspect-throw', $inspect_throw_hooks ) ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $inspect_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && $inspect_reject_hooks === $inspect_uncertain_hooks && $inspect_throw_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $inspect_throw_saved, $inspect_throw_item['ID'], $inspect_throw_hooks, 'matrix-fetch-secret' ), 'inspect rejection, uncertainty, and a provider throw each release one acquired token and leave the envelope' );

	$inspect_stale_item = $plant( 'tutor-google-meet', $google_bytes );
	$matrix_arm( $inspect_stale_item['ID'], 'fetch', 'token-matrix-inspect-stale-next', 'update' );
	list( $inspect_stale_sql, $inspect_stale_ledger, $inspect_stale_hooks ) = $matrix_mark();
	$inspect_stale_saved = $inspect( $inspect_stale_item, $google_input, $google_queue, 'token-matrix-inspect-stale', null, $matrix_spy );
	$inspect_stale_delete = $matrix_bound( $inspect_stale_sql, $inspect_stale_ledger, $inspect_stale_item['ID'], 'token-matrix-inspect-stale', $inspect_stale_hooks );
	$inspect_stale_pair = $matrix_spy->pair;
	tutorpress_live_lessons_assert( array( 'match' => true ) === $inspect_stale_saved && is_array( $inspect_stale_delete ) && is_array( $inspect_stale_pair ) && $inspect_stale_pair[0] === $inspect_stale_delete[2] && $inspect_stale_pair[1] === TutorPress_Live_Lessons_Store_Fake::$options[ $inspect_stale_pair[2] ] && TutorPress_Live_Lesson_Sync_Repository::owns( $inspect_stale_pair[1], 'token-matrix-inspect-stale-next' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $inspect_stale_pair[1], 'token-matrix-inspect-stale' ) && 0 === (int) $GLOBALS['wpdb']->rows_affected && $inspect_stale_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $inspect_stale_item['ID'], '_tutorpress_live_lesson_sync' ) && $matrix_hidden( $inspect_stale_saved, $inspect_stale_item['ID'], $inspect_stale_hooks ), 'a stale inspect owner release returns false and preserves the replacement lock' );

	$matrix_watch->script_false = true;
	$inspect_cleanup_item = $plant( 'tutor-google-meet', $google_bytes );
	list( $inspect_cleanup_sql, $inspect_cleanup_ledger, $inspect_cleanup_hooks ) = $matrix_mark();
	$inspect_cleanup_saved = $inspect( $inspect_cleanup_item, $google_input, $google_queue, 'token-matrix-inspect-cleanup' );
	$matrix_watch->script_false = false;
	tutorpress_live_lessons_assert( array( 'match' => true ) === $inspect_cleanup_saved && is_array( $matrix_bound( $inspect_cleanup_sql, $inspect_cleanup_ledger, $inspect_cleanup_item['ID'], 'token-matrix-inspect-cleanup', $inspect_cleanup_hooks ) ) && 0 === (int) $GLOBALS['wpdb']->rows_affected && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $inspect_cleanup_item['ID'], '_tutorpress_live_lesson_sync' ) && $inspect_cleanup_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && array() === $matrix_names( $inspect_cleanup_hooks ) && $matrix_hidden( $inspect_cleanup_saved, $inspect_cleanup_item['ID'], $inspect_cleanup_hooks ), 'a scripted inspect release callback returns false, leaves the envelope, and prepares one delete' );

	list( $retry_create_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
	list( $retry_create_sql, $retry_create_ledger, $retry_create_hooks ) = $matrix_mark();
	$retry_create_saved = $retry( $retry_create_item, $evil, array( 'create' => array( $provider_result( 'confirmed', 'create', 'evt-1' ) ), 'metadata_map' => array( $create_map ) ), 'token-matrix-retry-create' );
	$retry_create_delete = $matrix_bound( $retry_create_sql, $retry_create_ledger, $retry_create_item['ID'], 'token-matrix-retry-create', $retry_create_hooks );
	tutorpress_live_lessons_assert( is_array( $retry_create_saved ) && 'synced' === json_decode( $retry_create_saved['envelope'], true )['state'] && is_array( $retry_create_delete ) && array( 'tutor_google_meet_after_save_meeting' ) === $matrix_names( $retry_create_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $retry_create_item['ID'] ] ) && $matrix_hidden( $retry_create_saved, $retry_create_item['ID'], $retry_create_hooks ), 'a synced create retry counts the finish_synced delete and adds no second release' );

	list( $retry_update_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', false, 'evt-stored', 'provider_update_failed' );
	list( $retry_update_sql, $retry_update_ledger, $retry_update_hooks ) = $matrix_mark();
	$retry_update_saved = $retry( $retry_update_item, $evil, array( 'update' => array( $provider_result( 'confirmed', 'update', 'evt-stored' ) ), 'compare_target' => array( array( 'match' => true ) ), 'metadata_map' => array( $update_map ) ), 'token-matrix-retry-update' );
	tutorpress_live_lessons_assert( is_array( $retry_update_saved ) && 'synced' === json_decode( $retry_update_saved['envelope'], true )['state'] && is_array( $matrix_bound( $retry_update_sql, $retry_update_ledger, $retry_update_item['ID'], 'token-matrix-retry-update', $retry_update_hooks ) ) && $retry_update_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && array() === $matrix_names( $retry_update_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $retry_update_item['ID'] ] ) && $matrix_hidden( $retry_update_saved, $retry_update_item['ID'], $retry_update_hooks ), 'a successful retried update has one prepared delete, no save hook, and no second release' );

	list( $retry_reject_item, $retry_reject_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
	list( $retry_reject_sql, $retry_reject_ledger, $retry_reject_hooks ) = $matrix_mark();
	$retry_reject_saved = $retry( $retry_reject_item, $evil, array( 'create' => array( $reject( 'create' ) ) ), 'token-matrix-retry-reject' );
	list( $retry_uncertain_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
	list( $retry_uncertain_sql, $retry_uncertain_ledger, $retry_uncertain_hooks ) = $matrix_mark();
	$retry_uncertain_saved = $retry( $retry_uncertain_item, $evil, array( 'create' => array( $provider_result( 'uncertain', 'create', null, null ) ) ), 'token-matrix-retry-uncertain' );
	list( $retry_throw_item, $retry_throw_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'dispatch_possible', false, 'evt-stored', null );
	$matrix_throw->method = 'fetch';
	list( $retry_throw_sql, $retry_throw_ledger, $retry_throw_hooks ) = $matrix_mark();
	$retry_throw_saved = $retry( $retry_throw_item, $delete_input, array( 'capabilities' => $caps_true ), 'token-matrix-retry-throw', $matrix_throw );
	$matrix_throw->method = '';
	tutorpress_live_lessons_assert( is_array( $matrix_bound( $retry_reject_sql, $retry_reject_ledger, $retry_reject_item['ID'], 'token-matrix-retry-reject', $retry_reject_hooks, $retry_uncertain_sql, $retry_uncertain_ledger ) ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $retry_reject_item['ID'] ] ) && array() === $matrix_names( $retry_reject_hooks, $retry_uncertain_hooks ) && is_array( $matrix_bound( $retry_uncertain_sql, $retry_uncertain_ledger, $retry_uncertain_item['ID'], 'token-matrix-retry-uncertain', $retry_uncertain_hooks, $retry_throw_sql, $retry_throw_ledger ) ) && array() === $matrix_names( $retry_uncertain_hooks ) && null === $retry_throw_saved && is_array( $matrix_bound( $retry_throw_sql, $retry_throw_ledger, $retry_throw_item['ID'], 'token-matrix-retry-throw', $retry_throw_hooks ) ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $retry_throw_item['ID'] ] ) && $retry_throw_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $retry_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $matrix_names( $retry_throw_hooks ) && $matrix_hidden( $retry_reject_saved, $retry_reject_item['ID'], $retry_reject_hooks ) && $matrix_hidden( $retry_uncertain_saved, $retry_uncertain_item['ID'], $retry_uncertain_hooks ) && $matrix_hidden( $retry_throw_saved, $retry_throw_item['ID'], $retry_throw_hooks, 'matrix-fetch-secret' ) && false === strpos( (string) $retry_reject_bytes, $secret ), 'retry rejection, uncertainty, and a provider throw each release one token and fire no hook' );

	list( $retry_hook_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
	TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] = array( new RuntimeException( 'matrix-delete-hook-secret' ) );
	list( $retry_hook_sql, $retry_hook_ledger, $retry_hook_hooks ) = $matrix_mark();
	$retry_hook_saved = $retry( $retry_hook_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-matrix-retry-hook' );
	unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_deleted'] );
	list( $retry_save_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
	TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'matrix-save-hook-secret' ) );
	list( $retry_save_sql, $retry_save_ledger, $retry_save_hooks ) = $matrix_mark();
	$retry_save_saved = $retry( $retry_save_item, $evil, array( 'create' => array( $provider_result( 'confirmed', 'create', 'evt-1' ) ), 'metadata_map' => array( $create_map ) ), 'token-matrix-retry-save-hook' );
	unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] );
	tutorpress_live_lessons_assert( $retry_hook_saved instanceof WP_Error && is_array( $matrix_bound( $retry_hook_sql, $retry_hook_ledger, $retry_hook_item['ID'], 'token-matrix-retry-hook', $retry_hook_hooks, $retry_save_sql, $retry_save_ledger ) ) && array( 'tutorpress_live_lesson_deleted' ) === $matrix_names( $retry_hook_hooks, $retry_save_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $retry_hook_item['ID'] ] ) && $retry_save_saved instanceof WP_Error && is_array( $matrix_bound( $retry_save_sql, $retry_save_ledger, $retry_save_item['ID'], 'token-matrix-retry-save-hook', $retry_save_hooks ) ) && array( 'tutor_google_meet_after_save_meeting' ) === $matrix_names( $retry_save_hooks ) && 'synced' === json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $retry_save_item['ID'], '_tutorpress_live_lesson_sync' ), true )['state'] && $matrix_hidden( $retry_hook_saved, $retry_hook_item['ID'], $retry_hook_hooks, 'matrix-delete-hook-secret', $retry_save_hooks ) && $matrix_hidden( $retry_save_saved, $retry_save_item['ID'], $retry_save_hooks, 'matrix-save-hook-secret' ), 'delete-hook and save-hook throws record their single delete at the pre-hook ledger length' );

	list( $retry_stale_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
	$matrix_arm( $retry_stale_item['ID'], 'delete', 'token-matrix-retry-stale-next', 'delete' );
	list( $retry_stale_sql, $retry_stale_ledger, $retry_stale_hooks ) = $matrix_mark();
	$retry_stale_saved = $retry( $retry_stale_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-matrix-retry-stale', $matrix_spy );
	$retry_stale_delete = $matrix_bound( $retry_stale_sql, $retry_stale_ledger, $retry_stale_item['ID'], 'token-matrix-retry-stale', $retry_stale_hooks );
	$retry_stale_pair = $matrix_spy->pair;
	tutorpress_live_lessons_assert( null === $retry_stale_saved && is_array( $retry_stale_delete ) && is_array( $retry_stale_pair ) && $retry_stale_pair[0] === $retry_stale_delete[2] && $retry_stale_pair[1] === TutorPress_Live_Lessons_Store_Fake::$options[ $retry_stale_pair[2] ] && TutorPress_Live_Lesson_Sync_Repository::owns( $retry_stale_pair[1], 'token-matrix-retry-stale-next' ) && 0 === (int) $GLOBALS['wpdb']->rows_affected && array() === $matrix_names( $retry_stale_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $retry_stale_item['ID'] ] ) && $matrix_safe( $retry_stale_delete[2] ), 'a stale retry owner release returns false, preserves the replacement lock, and fires no delete hook' );

	list( $retry_cleanup_item, $retry_cleanup_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'delete', 'not_dispatched', false, 'evt-stored', null );
	TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $retry_cleanup_item['ID'] ] = true;
	list( $retry_cleanup_sql, $retry_cleanup_ledger, $retry_cleanup_hooks ) = $matrix_mark();
	$retry_cleanup_saved = $retry( $retry_cleanup_item, $delete_input, array( 'capabilities' => $caps_true, 'delete' => array( $provider_result( 'confirmed', 'delete', 'evt-stored', null ) ) ), 'token-matrix-retry-cleanup' );
	unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $retry_cleanup_item['ID'] ] );
	tutorpress_live_lessons_assert( null === $retry_cleanup_saved && is_array( $matrix_bound( $retry_cleanup_sql, $retry_cleanup_ledger, $retry_cleanup_item['ID'], 'token-matrix-retry-cleanup', $retry_cleanup_hooks ) ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $retry_cleanup_item['ID'] ] ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $retry_cleanup_item['ID'] ]['post_status'] && array() === $matrix_names( $retry_cleanup_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $retry_cleanup_item['ID'] ] ) && $matrix_hidden( $retry_cleanup_saved, $retry_cleanup_item['ID'], $retry_cleanup_hooks ) && false === strpos( (string) $retry_cleanup_bytes, $secret ), 'a failed google delete convergence keeps the publish post, skips the delete hook, and prepares one delete' );

	list( $abandon_ok_item, $abandon_ok_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $abandon_ok_sql, $abandon_ok_ledger, $abandon_ok_hooks ) = $matrix_mark();
	$abandon_ok_saved = $abandon( $abandon_ok_item, $evil, 'token-matrix-abandon-ok' );
	$abandon_ok_row = is_array( $abandon_ok_saved ) ? json_decode( $abandon_ok_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $abandon_ok_row ) && 'unlinked' === $abandon_ok_row['state'] && is_array( $matrix_bound( $abandon_ok_sql, $abandon_ok_ledger, $abandon_ok_item['ID'], 'token-matrix-abandon-ok', $abandon_ok_hooks ) ) && $abandon_ok_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && array() === $methods() && $matrix_hidden( $abandon_ok_saved, $abandon_ok_item['ID'], $abandon_ok_hooks ) && false === strpos( (string) $abandon_ok_bytes, $secret ), 'abandon success releases one create token and fires no hook' );

	list( $abandon_cleanup_item, $abandon_cleanup_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $abandon_cleanup_sql, $abandon_cleanup_ledger, $abandon_cleanup_hooks ) = $matrix_mark();
	$abandon_cleanup_saved = $abandon( $abandon_cleanup_item, $evil, 'token-matrix-abandon-cleanup', null, $failed_update );
	tutorpress_live_lessons_assert( null === $abandon_cleanup_saved && is_array( $matrix_bound( $abandon_cleanup_sql, $abandon_cleanup_ledger, $abandon_cleanup_item['ID'], 'token-matrix-abandon-cleanup', $abandon_cleanup_hooks ) ) && $abandon_cleanup_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $abandon_cleanup_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $matrix_names( $abandon_cleanup_hooks ) && array() === $methods() && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $abandon_cleanup_item['ID'] ] ) && $matrix_hidden( $abandon_cleanup_saved, $abandon_cleanup_item['ID'], $abandon_cleanup_hooks ), 'a failed abandon exchange leaves the prior id-less error envelope and prepares one delete' );

	list( $abandon_stale_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	$abandon_stale_update = static function ( $post_id, $key, $meta_id, $value, $prior ) use ( $sync_update, $matrix_swap ) {
		$matrix_swap( $post_id, 'create', 'token-matrix-abandon-stale-next' );
		return $sync_update( $post_id, $key, $meta_id, $value, $prior );
	};
	list( $abandon_stale_sql, $abandon_stale_ledger, $abandon_stale_hooks ) = $matrix_mark();
	$abandon_stale_saved = $abandon( $abandon_stale_item, $evil, 'token-matrix-abandon-stale', null, $abandon_stale_update );
	$abandon_stale_delete = $matrix_bound( $abandon_stale_sql, $abandon_stale_ledger, $abandon_stale_item['ID'], 'token-matrix-abandon-stale', $abandon_stale_hooks );
	$abandon_stale_name = 'tutorpress_live_lesson_lock_' . $abandon_stale_item['ID'];
	tutorpress_live_lessons_assert( is_array( $abandon_stale_delete ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_stale_name ] ) && TutorPress_Live_Lesson_Sync_Repository::owns( TutorPress_Live_Lessons_Store_Fake::$options[ $abandon_stale_name ], 'token-matrix-abandon-stale-next' ) && 0 === (int) $GLOBALS['wpdb']->rows_affected && array() === $matrix_names( $abandon_stale_hooks ) && $matrix_safe( $abandon_stale_saved ) && $matrix_safe( $abandon_stale_delete[2] ), 'a stale abandon owner release returns false and preserves the replacement lock' );

	$owner_ok_item = $plant( 'tutor-google-meet', null );
	list( $owner_ok_sql, $owner_ok_ledger, $owner_ok_hooks ) = $matrix_mark();
	$owner_ok_saved = $resolve( $owner_ok_item, $owner_input, 'token-matrix-owner-ok', null, array( 'preflight' => array( true ) ) );
	tutorpress_live_lessons_assert( is_array( $owner_ok_saved ) && 11 === $owner_ok_saved['owner_id'] && is_array( $matrix_bound( $owner_ok_sql, $owner_ok_ledger, $owner_ok_item['ID'], 'token-matrix-owner-ok', $owner_ok_hooks ) ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $owner_ok_item['ID'] ] ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_ok_item['ID'], '_tutorpress_live_lesson_sync' ) && $owner_ok_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $owner_ok_saved, $owner_ok_item['ID'], $owner_ok_hooks ), 'owner resolution success releases one create token and writes no envelope' );

	$owner_reject_item = $plant( 'tutor-google-meet', null );
	list( $owner_reject_sql, $owner_reject_ledger, $owner_reject_hooks ) = $matrix_mark();
	$owner_reject_saved = $resolve( $owner_reject_item, $owner_input, 'token-matrix-owner-reject', null, array( 'preflight' => array( false ) ) );
	$matrix_throw->method = 'preflight';
	$owner_throw_item = $plant( 'tutor-google-meet', null );
	list( $owner_throw_sql, $owner_throw_ledger, $owner_throw_hooks ) = $matrix_mark();
	list( $owner_throw_saved, $owner_throw_error ) = $matrix_call( static function () use ( $owner_throw_item, $topic, $course, $allow, $owner_input, $matrix_throw, $now, $sync_read, $option, $takeover ) {
		return TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner( $owner_throw_item, $topic, $course, $allow, $owner_input, $matrix_throw, $now, 'token-matrix-owner-throw', $sync_read, $option, $takeover );
	} );
	$matrix_throw->method = '';
	tutorpress_live_lessons_assert( null === $owner_reject_saved && is_array( $matrix_bound( $owner_reject_sql, $owner_reject_ledger, $owner_reject_item['ID'], 'token-matrix-owner-reject', $owner_reject_hooks, $owner_throw_sql, $owner_throw_ledger ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_reject_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $owner_throw_saved && $owner_throw_error instanceof RuntimeException && is_array( $matrix_bound( $owner_throw_sql, $owner_throw_ledger, $owner_throw_item['ID'], 'token-matrix-owner-throw', $owner_throw_hooks ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && $owner_reject_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $owner_reject_saved, $owner_reject_item['ID'], $owner_reject_hooks ) && $matrix_hidden( null, $owner_throw_item['ID'], $owner_throw_hooks, 'matrix-preflight-secret' ), 'owner-resolution rejection and a provider throw each release one token and write no envelope' );

	$owner_stale_item = $plant( 'tutor-google-meet', null );
	$matrix_arm( $owner_stale_item['ID'], 'preflight', 'token-matrix-owner-stale-next', 'create' );
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
	list( $owner_stale_sql, $owner_stale_ledger, $owner_stale_hooks ) = $matrix_mark();
	$owner_stale_saved = TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner( $owner_stale_item, $topic, $course, $allow, $owner_input, $matrix_spy, $now, 'token-matrix-owner-stale', $sync_read, $option, $takeover );
	$owner_stale_delete = $matrix_bound( $owner_stale_sql, $owner_stale_ledger, $owner_stale_item['ID'], 'token-matrix-owner-stale', $owner_stale_hooks );
	$owner_stale_pair = $matrix_spy->pair;
	tutorpress_live_lessons_assert( is_array( $owner_stale_saved ) && is_array( $owner_stale_delete ) && is_array( $owner_stale_pair ) && $owner_stale_pair[0] === $owner_stale_delete[2] && $owner_stale_pair[1] === TutorPress_Live_Lessons_Store_Fake::$options[ $owner_stale_pair[2] ] && TutorPress_Live_Lesson_Sync_Repository::owns( $owner_stale_pair[1], 'token-matrix-owner-stale-next' ) && 0 === (int) $GLOBALS['wpdb']->rows_affected && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_stale_item['ID'], '_tutorpress_live_lesson_sync' ) && $owner_stale_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $owner_stale_saved, $owner_stale_item['ID'], $owner_stale_hooks ), 'a stale owner-resolution release returns false and preserves the replacement lock' );

	$matrix_watch->script_false = true;
	$owner_cleanup_item = $plant( 'tutor-google-meet', null );
	list( $owner_cleanup_sql, $owner_cleanup_ledger, $owner_cleanup_hooks ) = $matrix_mark();
	$owner_cleanup_saved = $resolve( $owner_cleanup_item, $owner_input, 'token-matrix-owner-cleanup', null, array( 'preflight' => array( true ) ) );
	$matrix_watch->script_false = false;
	tutorpress_live_lessons_assert( is_array( $owner_cleanup_saved ) && is_array( $matrix_bound( $owner_cleanup_sql, $owner_cleanup_ledger, $owner_cleanup_item['ID'], 'token-matrix-owner-cleanup', $owner_cleanup_hooks ) ) && 0 === (int) $GLOBALS['wpdb']->rows_affected && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner_cleanup_item['ID'], '_tutorpress_live_lesson_sync' ) && $owner_cleanup_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && array() === $matrix_names( $owner_cleanup_hooks ) && $matrix_hidden( $owner_cleanup_saved, $owner_cleanup_item['ID'], $owner_cleanup_hooks ), 'a scripted owner-resolution release callback returns false, leaves no envelope, and prepares one delete' );

	$legacy_ok_item = $plant( 'tutor-google-meet', null );
	list( $legacy_ok_sql, $legacy_ok_ledger, $legacy_ok_hooks ) = $matrix_mark();
	$legacy_ok_saved = $legacy( $legacy_ok_item, $legacy_input, 'token-matrix-legacy-ok', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) );
	$legacy_ok_row = is_array( $legacy_ok_saved ) ? json_decode( $legacy_ok_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $legacy_ok_row ) && 1 === $legacy_ok_row['version'] && 'synced' === $legacy_ok_row['state'] && is_array( $matrix_bound( $legacy_ok_sql, $legacy_ok_ledger, $legacy_ok_item['ID'], 'token-matrix-legacy-ok', $legacy_ok_hooks ) ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $legacy_ok_item['ID'] ] ) && $legacy_ok_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $legacy_ok_saved, $legacy_ok_item['ID'], $legacy_ok_hooks ), 'legacy initialization releases one token and leaves no lock option' );

	$legacy_reject_item = $plant( 'tutor-google-meet', null );
	list( $legacy_reject_sql, $legacy_reject_ledger, $legacy_reject_hooks ) = $matrix_mark();
	$legacy_reject_saved = $legacy( $legacy_reject_item, $legacy_input, 'token-matrix-legacy-reject', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( false ) ) );
	$matrix_throw->method = 'preflight';
	$legacy_throw_item = $plant( 'tutor-google-meet', null );
	list( $legacy_throw_sql, $legacy_throw_ledger, $legacy_throw_hooks ) = $matrix_mark();
	list( $legacy_throw_saved, $legacy_throw_error ) = $matrix_call( static function () use ( $legacy_throw_item, $topic, $course, $allow, $legacy_input, $matrix_throw, $now, $sync_read, $option, $takeover ) {
		TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ) );
		return TutorPress_Live_Lesson_Sync_Orchestrator::initialize_legacy( $legacy_throw_item, $topic, $course, $allow, $legacy_input, $matrix_throw, $now, 'token-matrix-legacy-throw', $sync_read, $option, $takeover );
	} );
	$matrix_throw->method = '';
	TutorPress_Live_Lessons_Store_Fake::$meta_result = 'false';
	$legacy_cleanup_item = $plant( 'tutor-google-meet', null );
	list( $legacy_cleanup_sql, $legacy_cleanup_ledger, $legacy_cleanup_hooks ) = $matrix_mark();
	$legacy_cleanup_saved = $legacy( $legacy_cleanup_item, $legacy_input, 'token-matrix-legacy-cleanup', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) );
	TutorPress_Live_Lessons_Store_Fake::$meta_result = null;
	tutorpress_live_lessons_assert( null === $legacy_reject_saved && is_array( $matrix_bound( $legacy_reject_sql, $legacy_reject_ledger, $legacy_reject_item['ID'], 'token-matrix-legacy-reject', $legacy_reject_hooks, $legacy_throw_sql, $legacy_throw_ledger ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_reject_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $legacy_throw_saved && $legacy_throw_error instanceof RuntimeException && is_array( $matrix_bound( $legacy_throw_sql, $legacy_throw_ledger, $legacy_throw_item['ID'], 'token-matrix-legacy-throw', $legacy_throw_hooks, $legacy_cleanup_sql, $legacy_cleanup_ledger ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $legacy_cleanup_saved && is_array( $matrix_bound( $legacy_cleanup_sql, $legacy_cleanup_ledger, $legacy_cleanup_item['ID'], 'token-matrix-legacy-cleanup', $legacy_cleanup_hooks ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_cleanup_item['ID'], '_tutorpress_live_lesson_sync' ) && $legacy_reject_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( null, $legacy_throw_item['ID'], $legacy_throw_hooks, 'matrix-preflight-secret' ), 'legacy rejection, a provider throw, and a failed initialize each release once and write no version-1 row' );

	$legacy_stale_item = $plant( 'tutor-google-meet', null );
	$matrix_arm( $legacy_stale_item['ID'], 'preflight', 'token-matrix-legacy-stale-next', 'create' );
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) );
	list( $legacy_stale_sql, $legacy_stale_ledger, $legacy_stale_hooks ) = $matrix_mark();
	$legacy_stale_saved = TutorPress_Live_Lesson_Sync_Orchestrator::initialize_legacy( $legacy_stale_item, $topic, $course, $allow, $legacy_input, $matrix_spy, $now, 'token-matrix-legacy-stale', $sync_read, $option, $takeover );
	$legacy_stale_delete = $matrix_bound( $legacy_stale_sql, $legacy_stale_ledger, $legacy_stale_item['ID'], 'token-matrix-legacy-stale', $legacy_stale_hooks );
	$legacy_stale_pair = $matrix_spy->pair;
	$legacy_stale_row = is_array( $legacy_stale_saved ) ? json_decode( $legacy_stale_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $legacy_stale_row ) && 1 === $legacy_stale_row['version'] && is_array( $legacy_stale_delete ) && is_array( $legacy_stale_pair ) && $legacy_stale_pair[0] === $legacy_stale_delete[2] && $legacy_stale_pair[1] === TutorPress_Live_Lessons_Store_Fake::$options[ $legacy_stale_pair[2] ] && 0 === (int) $GLOBALS['wpdb']->rows_affected && $legacy_stale_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $legacy_stale_saved, $legacy_stale_item['ID'], $legacy_stale_hooks ), 'a stale legacy-initialization release returns false and preserves the replacement lock' );

	list( $attach_ok_item, $attach_ok_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $attach_ok_sql, $attach_ok_ledger, $attach_ok_hooks ) = $matrix_mark();
	$attach_ok_saved = $settle( $attach_ok_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-matrix-attach-ok' );
	tutorpress_live_lessons_assert( is_array( $attach_ok_saved ) && 'synced' === json_decode( $attach_ok_saved['envelope'], true )['state'] && is_array( $matrix_bound( $attach_ok_sql, $attach_ok_ledger, $attach_ok_item['ID'], 'token-matrix-attach-ok', $attach_ok_hooks ) ) && array( 'tutor_google_meet_after_save_meeting' ) === $matrix_names( $attach_ok_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $attach_ok_item['ID'] ] ) && $matrix_hidden( $attach_ok_saved, $attach_ok_item['ID'], $attach_ok_hooks ) && false === strpos( (string) $attach_ok_bytes, $secret ), 'uncertain-create attach releases once before the save hook' );

	list( $attach_reject_item, $attach_reject_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $attach_reject_sql, $attach_reject_ledger, $attach_reject_hooks ) = $matrix_mark();
	$attach_reject_saved = $attach( $attach_reject_item, $attach_google, $attach_queue( $reject( 'fetch', 'evt-supplied' ) ), 'token-matrix-attach-reject' );
	list( $attach_uncertain_item, $attach_uncertain_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $attach_uncertain_sql, $attach_uncertain_ledger, $attach_uncertain_hooks ) = $matrix_mark();
	$attach_uncertain_saved = $attach( $attach_uncertain_item, $attach_google, $attach_queue( $provider_result( 'uncertain', 'fetch', 'evt-supplied', null ) ), 'token-matrix-attach-uncertain' );
	$matrix_throw->method = 'fetch';
	list( $attach_throw_matrix_item, $attach_throw_matrix_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $attach_throw_matrix_sql, $attach_throw_matrix_ledger, $attach_throw_matrix_hooks ) = $matrix_mark();
	$attach_throw_matrix_saved = $attach( $attach_throw_matrix_item, $attach_google, array( 'preflight' => array( true ) ), 'token-matrix-attach-throw', null, $matrix_throw );
	$matrix_throw->method = '';
	tutorpress_live_lessons_assert( null === $attach_reject_saved && is_array( $matrix_bound( $attach_reject_sql, $attach_reject_ledger, $attach_reject_item['ID'], 'token-matrix-attach-reject', $attach_reject_hooks, $attach_uncertain_sql, $attach_uncertain_ledger ) ) && $attach_reject_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_reject_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $attach_uncertain_saved && is_array( $matrix_bound( $attach_uncertain_sql, $attach_uncertain_ledger, $attach_uncertain_item['ID'], 'token-matrix-attach-uncertain', $attach_uncertain_hooks, $attach_throw_matrix_sql, $attach_throw_matrix_ledger ) ) && $attach_uncertain_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_uncertain_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $attach_throw_matrix_saved && is_array( $matrix_bound( $attach_throw_matrix_sql, $attach_throw_matrix_ledger, $attach_throw_matrix_item['ID'], 'token-matrix-attach-throw', $attach_throw_matrix_hooks ) ) && $attach_throw_matrix_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_throw_matrix_item['ID'], '_tutorpress_live_lesson_sync' ) && $attach_reject_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $attach_throw_matrix_saved, $attach_throw_matrix_item['ID'], $attach_throw_matrix_hooks, 'matrix-fetch-secret' ), 'uncertain attach rejection, uncertainty, and a provider throw each release once and keep the error envelope' );

	list( $attach_hook_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'matrix-attach-hook-secret' ) );
	list( $attach_hook_sql, $attach_hook_ledger, $attach_hook_hooks ) = $matrix_mark();
	$attach_hook_saved = $settle( $attach_hook_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-matrix-attach-hook' );
	unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] );
	tutorpress_live_lessons_assert( $attach_hook_saved instanceof WP_Error && is_array( $matrix_bound( $attach_hook_sql, $attach_hook_ledger, $attach_hook_item['ID'], 'token-matrix-attach-hook', $attach_hook_hooks ) ) && array( 'tutor_google_meet_after_save_meeting' ) === $matrix_names( $attach_hook_hooks ) && 'synced' === json_decode( (string) TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_hook_item['ID'], '_tutorpress_live_lesson_sync' ), true )['state'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $attach_hook_item['ID'] ] ) && $matrix_hidden( $attach_hook_saved, $attach_hook_item['ID'], $attach_hook_hooks, 'matrix-attach-hook-secret' ), 'an attach save-hook throw records its single delete at the pre-hook ledger length' );

	list( $attach_stale_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	$matrix_arm( $attach_stale_item['ID'], 'fetch', 'token-matrix-attach-stale-next', 'create' );
	list( $attach_stale_sql, $attach_stale_ledger, $attach_stale_hooks ) = $matrix_mark();
	$attach_stale_saved = $settle( $attach_stale_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-matrix-attach-stale', $matrix_spy );
	$attach_stale_delete = $matrix_bound( $attach_stale_sql, $attach_stale_ledger, $attach_stale_item['ID'], 'token-matrix-attach-stale', $attach_stale_hooks );
	$attach_stale_pair = $matrix_spy->pair;
	tutorpress_live_lessons_assert( null === $attach_stale_saved && is_array( $attach_stale_delete ) && is_array( $attach_stale_pair ) && $attach_stale_pair[0] === $attach_stale_delete[2] && $attach_stale_pair[1] === TutorPress_Live_Lessons_Store_Fake::$options[ $attach_stale_pair[2] ] && 0 === (int) $GLOBALS['wpdb']->rows_affected && array() === $matrix_names( $attach_stale_hooks ) && $matrix_safe( $attach_stale_delete[2] ), 'a stale uncertain-attach release returns false, preserves the replacement lock, and fires no save hook' );

	list( $attach_cleanup_item, $attach_cleanup_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $attach_cleanup_sql, $attach_cleanup_ledger, $attach_cleanup_hooks ) = $matrix_mark();
	$attach_cleanup_saved = $settle( $attach_cleanup_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-matrix-attach-cleanup', null, static function () { return 1; } );
	tutorpress_live_lessons_assert( null === $attach_cleanup_saved && is_array( $matrix_bound( $attach_cleanup_sql, $attach_cleanup_ledger, $attach_cleanup_item['ID'], 'token-matrix-attach-cleanup', $attach_cleanup_hooks ) ) && $attach_cleanup_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $attach_cleanup_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $matrix_names( $attach_cleanup_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $attach_cleanup_item['ID'] ] ) && $matrix_hidden( $attach_cleanup_saved, $attach_cleanup_item['ID'], $attach_cleanup_hooks ), 'a failed uncertain-attach re-read leaves the error envelope and prepares one delete' );

	$legacy_attach_ok_item = $plant( 'tutor-google-meet', null );
	list( $legacy_attach_ok_sql, $legacy_attach_ok_ledger, $legacy_attach_ok_hooks ) = $matrix_mark();
	$legacy_attach_ok_saved = $legacy_attach( $legacy_attach_ok_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-matrix-legacy-attach-ok' );
	$legacy_attach_ok_row = is_array( $legacy_attach_ok_saved ) ? json_decode( $legacy_attach_ok_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $legacy_attach_ok_row ) && 1 === $legacy_attach_ok_row['version'] && is_array( $matrix_bound( $legacy_attach_ok_sql, $legacy_attach_ok_ledger, $legacy_attach_ok_item['ID'], 'token-matrix-legacy-attach-ok', $legacy_attach_ok_hooks ) ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $legacy_attach_ok_item['ID'] ] ) && $legacy_attach_ok_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $legacy_attach_ok_saved, $legacy_attach_ok_item['ID'], $legacy_attach_ok_hooks ), 'legacy attach success releases once, writes version one, and fires no save hook' );

	$legacy_attach_reject_item = $plant( 'tutor-google-meet', null );
	list( $legacy_attach_reject_sql, $legacy_attach_reject_ledger, $legacy_attach_reject_hooks ) = $matrix_mark();
	$legacy_attach_reject_saved = $legacy_attach( $legacy_attach_reject_item, $legacy_attach_input, $legacy_attach_queue( $reject( 'fetch', 'evt-supplied' ), $legacy_map ), 'token-matrix-legacy-attach-reject' );
	$legacy_attach_uncertain_item = $plant( 'tutor-google-meet', null );
	list( $legacy_attach_uncertain_sql, $legacy_attach_uncertain_ledger, $legacy_attach_uncertain_hooks ) = $matrix_mark();
	$legacy_attach_uncertain_saved = $legacy_attach( $legacy_attach_uncertain_item, $legacy_attach_input, $legacy_attach_queue( $provider_result( 'uncertain', 'fetch', 'evt-supplied', null ), $legacy_map ), 'token-matrix-legacy-attach-uncertain' );
	$matrix_throw->method = 'fetch';
	$legacy_attach_throw_matrix_item = $plant( 'tutor-google-meet', null );
	list( $legacy_attach_throw_matrix_sql, $legacy_attach_throw_matrix_ledger, $legacy_attach_throw_matrix_hooks ) = $matrix_mark();
	$legacy_attach_throw_matrix_saved = $legacy_attach( $legacy_attach_throw_matrix_item, $legacy_attach_input, array( 'hydrate_legacy' => array( array( 'provider_id' => null ) ), 'hydrate_item' => array( $legacy_item_target ), 'preflight' => array( true ) ), 'token-matrix-legacy-attach-throw', null, $matrix_throw );
	$matrix_throw->method = '';
	tutorpress_live_lessons_assert( null === $legacy_attach_reject_saved && is_array( $matrix_bound( $legacy_attach_reject_sql, $legacy_attach_reject_ledger, $legacy_attach_reject_item['ID'], 'token-matrix-legacy-attach-reject', $legacy_attach_reject_hooks, $legacy_attach_uncertain_sql, $legacy_attach_uncertain_ledger ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_reject_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $legacy_attach_uncertain_saved && is_array( $matrix_bound( $legacy_attach_uncertain_sql, $legacy_attach_uncertain_ledger, $legacy_attach_uncertain_item['ID'], 'token-matrix-legacy-attach-uncertain', $legacy_attach_uncertain_hooks, $legacy_attach_throw_matrix_sql, $legacy_attach_throw_matrix_ledger ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_uncertain_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $legacy_attach_throw_matrix_saved && is_array( $matrix_bound( $legacy_attach_throw_matrix_sql, $legacy_attach_throw_matrix_ledger, $legacy_attach_throw_matrix_item['ID'], 'token-matrix-legacy-attach-throw', $legacy_attach_throw_matrix_hooks ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_throw_matrix_item['ID'], '_tutorpress_live_lesson_sync' ) && $legacy_attach_reject_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( null, $legacy_attach_throw_matrix_item['ID'], $legacy_attach_throw_matrix_hooks, 'matrix-fetch-secret' ), 'legacy attach rejection, uncertainty, and a provider throw each release once and write no version-1 row' );

	$legacy_attach_stale_item = $plant( 'tutor-google-meet', null );
	$matrix_arm( $legacy_attach_stale_item['ID'], 'fetch', 'token-matrix-legacy-attach-stale-next', 'create' );
	list( $legacy_attach_stale_sql, $legacy_attach_stale_ledger, $legacy_attach_stale_hooks ) = $matrix_mark();
	$legacy_attach_stale_saved = $legacy_attach( $legacy_attach_stale_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-matrix-legacy-attach-stale', null, $matrix_spy );
	$legacy_attach_stale_delete = $matrix_bound( $legacy_attach_stale_sql, $legacy_attach_stale_ledger, $legacy_attach_stale_item['ID'], 'token-matrix-legacy-attach-stale', $legacy_attach_stale_hooks );
	$legacy_attach_stale_pair = $matrix_spy->pair;
	$legacy_attach_stale_row = is_array( $legacy_attach_stale_saved ) ? json_decode( $legacy_attach_stale_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $legacy_attach_stale_row ) && 1 === $legacy_attach_stale_row['version'] && is_array( $legacy_attach_stale_delete ) && is_array( $legacy_attach_stale_pair ) && $legacy_attach_stale_pair[0] === $legacy_attach_stale_delete[2] && $legacy_attach_stale_pair[1] === TutorPress_Live_Lessons_Store_Fake::$options[ $legacy_attach_stale_pair[2] ] && 0 === (int) $GLOBALS['wpdb']->rows_affected && $legacy_attach_stale_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $legacy_attach_stale_saved, $legacy_attach_stale_item['ID'], $legacy_attach_stale_hooks ), 'a stale legacy-attach release returns false and preserves the replacement lock' );

	$legacy_attach_cleanup_item = $plant( 'tutor-google-meet', null );
	list( $legacy_attach_cleanup_sql, $legacy_attach_cleanup_ledger, $legacy_attach_cleanup_hooks ) = $matrix_mark();
	$legacy_attach_cleanup_saved = $legacy_attach( $legacy_attach_cleanup_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-matrix-legacy-attach-cleanup', null, null, static function () { return 1; } );
	tutorpress_live_lessons_assert( null === $legacy_attach_cleanup_saved && is_array( $matrix_bound( $legacy_attach_cleanup_sql, $legacy_attach_cleanup_ledger, $legacy_attach_cleanup_item['ID'], 'token-matrix-legacy-attach-cleanup', $legacy_attach_cleanup_hooks ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $legacy_attach_cleanup_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $matrix_names( $legacy_attach_cleanup_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $legacy_attach_cleanup_item['ID'] ] ) && $matrix_hidden( $legacy_attach_cleanup_saved, $legacy_attach_cleanup_item['ID'], $legacy_attach_cleanup_hooks ), 'a failed legacy-attach re-read writes no version-1 row and prepares one delete' );

	list( $final_ok_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	list( $final_ok_sql, $final_ok_ledger, $final_ok_hooks ) = $matrix_mark();
	$final_ok_saved = $finalize( $final_ok_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-matrix-final-ok' );
	tutorpress_live_lessons_assert( is_array( $final_ok_saved ) && 'synced' === json_decode( $final_ok_saved['envelope'], true )['state'] && is_array( $matrix_bound( $final_ok_sql, $final_ok_ledger, $final_ok_item['ID'], 'token-matrix-final-ok', $final_ok_hooks ) ) && $final_ok_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && array() === $matrix_names( $final_ok_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $final_ok_item['ID'] ] ) && $matrix_hidden( $final_ok_saved, $final_ok_item['ID'], $final_ok_hooks ), 'a successful finalize_update has one prepared delete, no save hook, and no second release' );

	list( $final_reject_item, $final_reject_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	list( $final_reject_sql, $final_reject_ledger, $final_reject_hooks ) = $matrix_mark();
	$final_reject_saved = $finalize( $final_reject_item, $update_input, $matrix_reject_fetch, 'token-matrix-final-reject' );
	list( $final_uncertain_item, $final_uncertain_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	list( $final_uncertain_sql, $final_uncertain_ledger, $final_uncertain_hooks ) = $matrix_mark();
	$final_uncertain_saved = $finalize( $final_uncertain_item, $update_input, $matrix_uncertain_fetch, 'token-matrix-final-uncertain' );
	$matrix_throw->method = 'fetch';
	list( $final_throw_item, $final_throw_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	list( $final_throw_sql, $final_throw_ledger, $final_throw_hooks ) = $matrix_mark();
	$final_throw_saved = $finalize( $final_throw_item, $update_input, array( 'preflight' => array( true ) ), 'token-matrix-final-throw', null, $matrix_throw );
	$matrix_throw->method = '';
	tutorpress_live_lessons_assert( null === $final_reject_saved && is_array( $matrix_bound( $final_reject_sql, $final_reject_ledger, $final_reject_item['ID'], 'token-matrix-final-reject', $final_reject_hooks, $final_uncertain_sql, $final_uncertain_ledger ) ) && $final_reject_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $final_reject_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $final_uncertain_saved && is_array( $matrix_bound( $final_uncertain_sql, $final_uncertain_ledger, $final_uncertain_item['ID'], 'token-matrix-final-uncertain', $final_uncertain_hooks, $final_throw_sql, $final_throw_ledger ) ) && $final_uncertain_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $final_uncertain_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $final_throw_saved && is_array( $matrix_bound( $final_throw_sql, $final_throw_ledger, $final_throw_item['ID'], 'token-matrix-final-throw', $final_throw_hooks ) ) && $final_throw_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $final_throw_item['ID'], '_tutorpress_live_lesson_sync' ) && $final_reject_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $final_throw_saved, $final_throw_item['ID'], $final_throw_hooks, 'matrix-fetch-secret' ), 'finalize_update rejection, uncertainty, and a provider throw each release once and leave the error envelope' );

	list( $final_stale_item ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	$matrix_arm( $final_stale_item['ID'], 'fetch', 'token-matrix-final-stale-next', 'update' );
	list( $final_stale_sql, $final_stale_ledger, $final_stale_hooks ) = $matrix_mark();
	$final_stale_saved = $finalize( $final_stale_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-matrix-final-stale', null, $matrix_spy );
	$final_stale_delete = $matrix_bound( $final_stale_sql, $final_stale_ledger, $final_stale_item['ID'], 'token-matrix-final-stale', $final_stale_hooks );
	$final_stale_pair = $matrix_spy->pair;
	tutorpress_live_lessons_assert( null === $final_stale_saved && is_array( $final_stale_delete ) && is_array( $final_stale_pair ) && $final_stale_pair[0] === $final_stale_delete[2] && $final_stale_pair[1] === TutorPress_Live_Lessons_Store_Fake::$options[ $final_stale_pair[2] ] && 0 === (int) $GLOBALS['wpdb']->rows_affected && array() === $matrix_names( $final_stale_hooks ) && $matrix_safe( $final_stale_delete[2] ), 'a stale finalize_update release returns false, preserves the replacement lock, and fires no save hook' );

	list( $final_cleanup_item, $final_cleanup_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	list( $final_cleanup_sql, $final_cleanup_ledger, $final_cleanup_hooks ) = $matrix_mark();
	$final_cleanup_saved = $finalize( $final_cleanup_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-matrix-final-cleanup', null, null, null, null, static function () { return 1; } );
	tutorpress_live_lessons_assert( null === $final_cleanup_saved && is_array( $matrix_bound( $final_cleanup_sql, $final_cleanup_ledger, $final_cleanup_item['ID'], 'token-matrix-final-cleanup', $final_cleanup_hooks ) ) && $final_cleanup_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $final_cleanup_item['ID'], '_tutorpress_live_lesson_sync' ) && array() === $matrix_names( $final_cleanup_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $final_cleanup_item['ID'] ] ) && $matrix_hidden( $final_cleanup_saved, $final_cleanup_item['ID'], $final_cleanup_hooks ), 'a failed finalize_update re-read leaves the error envelope and prepares one delete' );

	$died_item = $plant( 'tutor-google-meet', $google_bytes );
	list( $died_name, $died_prior ) = $matrix_lock( $died_item['ID'], 'token-matrix-died-old', 'update', '2026-09-21 16:00:00' );
	list( $died_sql, $died_ledger, $died_hooks ) = $matrix_mark();
	$died_untouched = $died_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $died_name ] && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_item['ID'], '_tutorpress_live_lesson_sync' ) && $died_sql === count( $GLOBALS['wpdb']->statements ) && $died_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls );
	$matrix_arm( $died_item['ID'], '', '', 'update' );
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$died_saved = $inspect( $died_item, $google_input, $google_queue, 'token-matrix-died-next', null, $matrix_spy );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$died_seen = $matrix_spy->seen;
	$died_delete = $matrix_bound( $died_sql, $died_ledger, $died_item['ID'], 'token-matrix-died-next', $died_hooks );
	tutorpress_live_lessons_assert( $died_untouched && array( 'match' => true ) === $died_saved && is_array( $died_seen ) && $google_bytes === $died_seen[0] && is_string( $died_seen[1] ) && $died_seen[1] !== $died_prior && TutorPress_Live_Lesson_Sync_Repository::owns( $died_seen[1], 'token-matrix-died-next' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $died_seen[1], 'token-matrix-died-old' ) && 1 === $option_reads && 1 === $option_updates && is_array( $died_delete ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $died_delete[2], 'token-matrix-died-old' ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $died_name ] ) && $died_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $died_saved, $died_item['ID'], $died_hooks ), 'process death leaves the pending envelope and lock, then inspect takes over only that lock and continues' );

	list( $expired_retry_item, $expired_retry_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'create', 'not_dispatched', false, null, null );
	list( $expired_retry_name, $expired_retry_prior ) = $matrix_lock( $expired_retry_item['ID'], 'token-matrix-expired-retry-old', 'create', '2026-09-21 16:00:00' );
	$matrix_arm( $expired_retry_item['ID'], '', '', 'create' );
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	list( $expired_retry_sql, $expired_retry_ledger, $expired_retry_hooks ) = $matrix_mark();
	$expired_retry_saved = $retry( $expired_retry_item, $evil, array( 'create' => array( $provider_result( 'confirmed', 'create', 'evt-1' ) ), 'metadata_map' => array( $create_map ) ), 'token-matrix-expired-retry', $matrix_spy );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$expired_retry_seen = $matrix_spy->seen;
	$expired_retry_delete = $matrix_bound( $expired_retry_sql, $expired_retry_ledger, $expired_retry_item['ID'], 'token-matrix-expired-retry', $expired_retry_hooks );
	tutorpress_live_lessons_assert( is_array( $expired_retry_saved ) && is_array( $expired_retry_seen ) && is_string( $expired_retry_seen[1] ) && $expired_retry_seen[1] !== $expired_retry_prior && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_retry_seen[1], 'token-matrix-expired-retry' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_retry_seen[1], 'token-matrix-expired-retry-old' ) && 1 === $option_updates && in_array( 'create', $methods(), true ) && is_array( $expired_retry_delete ) && array( 'tutor_google_meet_after_save_meeting' ) === $matrix_names( $expired_retry_hooks ) && $matrix_hidden( $expired_retry_saved, $expired_retry_item['ID'], $expired_retry_hooks ) && false === strpos( (string) $expired_retry_bytes, $secret ), 'an expired lock is taken over and retry continues the stored create' );

	list( $expired_attach_item, $expired_attach_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $expired_attach_name, $expired_attach_prior ) = $matrix_lock( $expired_attach_item['ID'], 'token-matrix-expired-attach-old', 'create', '2026-09-21 16:00:00' );
	$matrix_arm( $expired_attach_item['ID'], '', '', 'create' );
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	list( $expired_attach_sql, $expired_attach_ledger, $expired_attach_hooks ) = $matrix_mark();
	$expired_attach_saved = $settle( $expired_attach_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-matrix-expired-attach', $matrix_spy );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$expired_attach_seen = $matrix_spy->seen;
	tutorpress_live_lessons_assert( is_array( $expired_attach_saved ) && is_array( $expired_attach_seen ) && $expired_attach_bytes === $expired_attach_seen[0] && is_string( $expired_attach_seen[1] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_attach_seen[1], 'token-matrix-expired-attach' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_attach_seen[1], 'token-matrix-expired-attach-old' ) && 1 === $option_updates && in_array( 'fetch', $methods(), true ) && is_array( $matrix_bound( $expired_attach_sql, $expired_attach_ledger, $expired_attach_item['ID'], 'token-matrix-expired-attach', $expired_attach_hooks ) ) && array( 'tutor_google_meet_after_save_meeting' ) === $matrix_names( $expired_attach_hooks ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_attach_name ] ) && $matrix_hidden( $expired_attach_saved, $expired_attach_item['ID'], $expired_attach_hooks ), 'an expired lock is taken over and uncertain attach continues the fetch' );

	$expired_legacy_attach_item = $plant( 'tutor-google-meet', null );
	list( $expired_legacy_attach_name, $expired_legacy_attach_prior ) = $matrix_lock( $expired_legacy_attach_item['ID'], 'token-matrix-expired-legacy-attach-old', 'create', '2026-09-21 16:00:00' );
	$matrix_arm( $expired_legacy_attach_item['ID'], '', '', 'create' );
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	list( $expired_legacy_attach_sql, $expired_legacy_attach_ledger, $expired_legacy_attach_hooks ) = $matrix_mark();
	$expired_legacy_attach_saved = $legacy_attach( $expired_legacy_attach_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-matrix-expired-legacy-attach', null, $matrix_spy );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$expired_legacy_attach_seen = $matrix_spy->seen;
	$expired_legacy_attach_row = is_array( $expired_legacy_attach_saved ) ? json_decode( $expired_legacy_attach_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $expired_legacy_attach_row ) && 1 === $expired_legacy_attach_row['version'] && is_array( $expired_legacy_attach_seen ) && null === $expired_legacy_attach_seen[0] && is_string( $expired_legacy_attach_seen[1] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_legacy_attach_seen[1], 'token-matrix-expired-legacy-attach' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_legacy_attach_seen[1], 'token-matrix-expired-legacy-attach-old' ) && $expired_legacy_attach_seen[1] !== $expired_legacy_attach_prior && 1 === $option_updates && in_array( 'fetch', $methods(), true ) && is_array( $matrix_bound( $expired_legacy_attach_sql, $expired_legacy_attach_ledger, $expired_legacy_attach_item['ID'], 'token-matrix-expired-legacy-attach', $expired_legacy_attach_hooks ) ) && $expired_legacy_attach_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_legacy_attach_name ] ) && $matrix_hidden( $expired_legacy_attach_saved, $expired_legacy_attach_item['ID'], $expired_legacy_attach_hooks ), 'an expired lock is taken over and legacy attach continues initialization' );

	list( $expired_abandon_item, $expired_abandon_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $expired_abandon_name, $expired_abandon_prior ) = $matrix_lock( $expired_abandon_item['ID'], 'token-matrix-expired-abandon-old', 'create', '2026-09-21 16:00:00' );
	$expired_abandon_seen = null;
	$expired_abandon_update = static function ( $post_id, $key, $meta_id, $value, $prior ) use ( &$expired_abandon_seen, $sync_update, $expired_abandon_name ) {
		$expired_abandon_seen = array( TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post_id, '_tutorpress_live_lesson_sync' ), TutorPress_Live_Lessons_Store_Fake::$options[ $expired_abandon_name ] );
		return $sync_update( $post_id, $key, $meta_id, $value, $prior );
	};
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	list( $expired_abandon_sql, $expired_abandon_ledger, $expired_abandon_hooks ) = $matrix_mark();
	$expired_abandon_saved = $abandon( $expired_abandon_item, $evil, 'token-matrix-expired-abandon', null, $expired_abandon_update );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$expired_abandon_row = is_array( $expired_abandon_saved ) ? json_decode( $expired_abandon_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $expired_abandon_seen ) && $expired_abandon_bytes === $expired_abandon_seen[0] && is_string( $expired_abandon_seen[1] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_abandon_seen[1], 'token-matrix-expired-abandon' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_abandon_seen[1], 'token-matrix-expired-abandon-old' ) && is_array( $expired_abandon_row ) && 'unlinked' === $expired_abandon_row['state'] && 1 === $option_updates && array() === $methods() && is_array( $matrix_bound( $expired_abandon_sql, $expired_abandon_ledger, $expired_abandon_item['ID'], 'token-matrix-expired-abandon', $expired_abandon_hooks ) ) && $expired_abandon_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_abandon_name ] ) && $matrix_hidden( $expired_abandon_saved, $expired_abandon_item['ID'], $expired_abandon_hooks ), 'an expired lock is taken over and abandon continues the exchange' );

	$expired_owner_item = $plant( 'tutor-google-meet', null );
	list( $expired_owner_name, $expired_owner_prior ) = $matrix_lock( $expired_owner_item['ID'], 'token-matrix-expired-owner-old', 'create', '2026-09-21 16:00:00' );
	$matrix_arm( $expired_owner_item['ID'], '', '', 'create' );
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	list( $expired_owner_sql, $expired_owner_ledger, $expired_owner_hooks ) = $matrix_mark();
	$expired_owner_saved = TutorPress_Live_Lesson_Sync_Orchestrator::resolve_owner( $expired_owner_item, $topic, $course, $allow, $owner_input, $matrix_spy, $now, 'token-matrix-expired-owner', $sync_read, $option, $takeover );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$expired_owner_seen = $matrix_spy->seen;
	tutorpress_live_lessons_assert( is_array( $expired_owner_saved ) && 11 === $expired_owner_saved['owner_id'] && is_array( $expired_owner_seen ) && null === $expired_owner_seen[0] && is_string( $expired_owner_seen[1] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_owner_seen[1], 'token-matrix-expired-owner' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_owner_seen[1], 'token-matrix-expired-owner-old' ) && $expired_owner_seen[1] !== $expired_owner_prior && 1 === $option_updates && array( 'preflight' ) === $methods() && is_array( $matrix_bound( $expired_owner_sql, $expired_owner_ledger, $expired_owner_item['ID'], 'token-matrix-expired-owner', $expired_owner_hooks ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $expired_owner_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_owner_name ] ) && $matrix_hidden( $expired_owner_saved, $expired_owner_item['ID'], $expired_owner_hooks ), 'an expired lock is taken over and owner resolution continues preflight' );

	$expired_legacy_item = $plant( 'tutor-google-meet', null );
	list( $expired_legacy_name, $expired_legacy_prior ) = $matrix_lock( $expired_legacy_item['ID'], 'token-matrix-expired-legacy-old', 'create', '2026-09-21 16:00:00' );
	$matrix_arm( $expired_legacy_item['ID'], '', '', 'create' );
	TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
	TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) );
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	list( $expired_legacy_sql, $expired_legacy_ledger, $expired_legacy_hooks ) = $matrix_mark();
	$expired_legacy_saved = TutorPress_Live_Lesson_Sync_Orchestrator::initialize_legacy( $expired_legacy_item, $topic, $course, $allow, $legacy_input, $matrix_spy, $now, 'token-matrix-expired-legacy', $sync_read, $option, $takeover );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$expired_legacy_seen = $matrix_spy->seen;
	$expired_legacy_row = is_array( $expired_legacy_saved ) ? json_decode( $expired_legacy_saved['envelope'], true ) : null;
	tutorpress_live_lessons_assert( is_array( $expired_legacy_row ) && 1 === $expired_legacy_row['version'] && is_array( $expired_legacy_seen ) && null === $expired_legacy_seen[0] && is_string( $expired_legacy_seen[1] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_legacy_seen[1], 'token-matrix-expired-legacy' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_legacy_seen[1], 'token-matrix-expired-legacy-old' ) && $expired_legacy_seen[1] !== $expired_legacy_prior && 1 === $option_updates && is_array( $matrix_bound( $expired_legacy_sql, $expired_legacy_ledger, $expired_legacy_item['ID'], 'token-matrix-expired-legacy', $expired_legacy_hooks ) ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_legacy_name ] ) && $expired_legacy_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && $matrix_hidden( $expired_legacy_saved, $expired_legacy_item['ID'], $expired_legacy_hooks ), 'an expired lock is taken over and legacy initialization continues' );

	list( $expired_final_item, $expired_final_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	list( $expired_final_name, $expired_final_prior ) = $matrix_lock( $expired_final_item['ID'], 'token-matrix-expired-final-old', 'update', '2026-09-21 16:00:00' );
	$matrix_arm( $expired_final_item['ID'], '', '', 'update' );
	$option_reads = 0;
	$option_updates = 0;
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	list( $expired_final_sql, $expired_final_ledger, $expired_final_hooks ) = $matrix_mark();
	$expired_final_saved = $finalize( $expired_final_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-matrix-expired-final', null, $matrix_spy );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$expired_final_seen = $matrix_spy->seen;
	tutorpress_live_lessons_assert( is_array( $expired_final_saved ) && 'synced' === json_decode( $expired_final_saved['envelope'], true )['state'] && is_array( $expired_final_seen ) && $expired_final_bytes === $expired_final_seen[0] && is_string( $expired_final_seen[1] ) && TutorPress_Live_Lesson_Sync_Repository::owns( $expired_final_seen[1], 'token-matrix-expired-final' ) && ! TutorPress_Live_Lesson_Sync_Repository::owns( $expired_final_seen[1], 'token-matrix-expired-final-old' ) && 1 === $option_updates && in_array( 'fetch', $methods(), true ) && is_array( $matrix_bound( $expired_final_sql, $expired_final_ledger, $expired_final_item['ID'], 'token-matrix-expired-final', $expired_final_hooks ) ) && $expired_final_hooks === count( TutorPress_Live_Lessons_Hook_Fake::$calls ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $expired_final_name ] ) && $matrix_hidden( $expired_final_saved, $expired_final_item['ID'], $expired_final_hooks ), 'an expired lock is taken over and finalize_update continues the stored fetch' );

	$live_inspect_item = $plant( 'tutor-google-meet', $google_bytes );
	list( $live_inspect_name, $live_inspect_prior ) = $matrix_lock( $live_inspect_item['ID'], 'token-matrix-live-inspect', 'update', '2026-09-21 16:05:00' );
	$live_inspect_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_inspect_saved = $inspect( $live_inspect_item, $google_input, $google_queue, 'token-matrix-live-inspect-caller' );
	$live_inspect_methods = $methods();
	$live_inspect_after = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	list( $live_retry_item, $live_retry_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'pending', 'create', 'not_dispatched', false, null, null );
	list( $live_retry_name, $live_retry_prior ) = $matrix_lock( $live_retry_item['ID'], 'token-matrix-live-retry', 'create', '2026-09-21 16:05:00' );
	$live_retry_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_retry_saved = $retry( $live_retry_item, $evil, array( 'create' => array( $provider_result( 'confirmed', 'create', 'evt-1' ) ) ), 'token-matrix-live-retry-caller' );
	$live_retry_methods = $methods();
	$live_retry_after = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	list( $live_attach_item, $live_attach_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $live_attach_name, $live_attach_prior ) = $matrix_lock( $live_attach_item['ID'], 'token-matrix-live-attach', 'create', '2026-09-21 16:05:00' );
	$live_attach_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_attach_saved = $attach( $live_attach_item, $attach_google, $attach_queue( $fetched( 'evt-supplied' ) ), 'token-matrix-live-attach-caller' );
	$live_attach_methods = $methods();
	$live_attach_after = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$live_legacy_attach_item = $plant( 'tutor-google-meet', null );
	list( $live_legacy_attach_name, $live_legacy_attach_prior ) = $matrix_lock( $live_legacy_attach_item['ID'], 'token-matrix-live-legacy-attach', 'create', '2026-09-21 16:05:00' );
	$live_legacy_attach_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_legacy_attach_saved = $legacy_attach( $live_legacy_attach_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-matrix-live-legacy-attach-caller' );
	$live_legacy_attach_methods = $methods();
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	tutorpress_live_lessons_assert( null === $live_inspect_saved && $live_inspect_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_inspect_name ] && $live_inspect_sql === $live_inspect_after && array() === $live_inspect_methods && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_inspect_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $live_retry_saved && $live_retry_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_retry_name ] && $live_retry_sql === $live_retry_after && array() === $live_retry_methods && $live_retry_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_retry_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $live_attach_saved && $live_attach_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_attach_name ] && $live_attach_sql === $live_attach_after && array() === $live_attach_methods && $live_attach_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_attach_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $live_legacy_attach_saved && $live_legacy_attach_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_legacy_attach_name ] && $live_legacy_attach_sql === count( $GLOBALS['wpdb']->statements ) && array( 'hydrate_legacy', 'hydrate_item' ) === $live_legacy_attach_methods && false === in_array( 'fetch', $live_legacy_attach_methods, true ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_legacy_attach_item['ID'], '_tutorpress_live_lesson_sync' ), 'a live lock returns null, leaves the option bytes, and does not continue provider work' );

	list( $live_abandon_item, $live_abandon_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
	list( $live_abandon_name, $live_abandon_prior ) = $matrix_lock( $live_abandon_item['ID'], 'token-matrix-live-abandon', 'create', '2026-09-21 16:05:00' );
	$live_abandon_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_abandon_saved = $abandon( $live_abandon_item, $evil, 'token-matrix-live-abandon-caller' );
	$live_abandon_methods = $methods();
	$live_abandon_after = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$live_owner_item = $plant( 'tutor-google-meet', null );
	list( $live_owner_name, $live_owner_prior ) = $matrix_lock( $live_owner_item['ID'], 'token-matrix-live-owner', 'create', '2026-09-21 16:05:00' );
	$live_owner_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_owner_saved = $resolve( $live_owner_item, $owner_input, 'token-matrix-live-owner-caller', null, array( 'preflight' => array( true ) ) );
	$live_owner_methods = $methods();
	$live_owner_after = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	$live_legacy_item = $plant( 'tutor-google-meet', null );
	list( $live_legacy_name, $live_legacy_prior ) = $matrix_lock( $live_legacy_item['ID'], 'token-matrix-live-legacy', 'create', '2026-09-21 16:05:00' );
	$live_legacy_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_legacy_saved = $legacy( $live_legacy_item, $legacy_input, 'token-matrix-live-legacy-caller', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) );
	$live_legacy_methods = $methods();
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	list( $live_final_item, $live_final_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
	list( $live_final_name, $live_final_prior ) = $matrix_lock( $live_final_item['ID'], 'token-matrix-live-final', 'update', '2026-09-21 16:05:00' );
	$live_final_sql = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = true;
	$live_final_saved = $finalize( $live_final_item, $update_input, $full_queue( $fetched( 'evt-stored' ), $update_map ), 'token-matrix-live-final-caller' );
	$live_final_methods = $methods();
	$live_final_after = count( $GLOBALS['wpdb']->statements );
	TutorPress_Topic_Copy_Harness::$fail_lock = false;
	tutorpress_live_lessons_assert( null === $live_abandon_saved && $live_abandon_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_abandon_name ] && $live_abandon_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_abandon_item['ID'], '_tutorpress_live_lesson_sync' ) && $live_abandon_sql === $live_abandon_after && array() === $live_abandon_methods && null === $live_owner_saved && $live_owner_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_owner_name ] && $live_owner_sql === $live_owner_after && array() === $live_owner_methods && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_owner_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $live_legacy_saved && $live_legacy_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_legacy_name ] && $live_legacy_sql === count( $GLOBALS['wpdb']->statements ) && array( 'hydrate_legacy' ) === $live_legacy_methods && false === in_array( 'preflight', $live_legacy_methods, true ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_legacy_item['ID'], '_tutorpress_live_lesson_sync' ) && null === $live_final_saved && $live_final_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $live_final_name ] && $live_final_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live_final_item['ID'], '_tutorpress_live_lesson_sync' ) && $live_final_sql === $live_final_after && array() === $live_final_methods, 'abandon, owner resolution, legacy initialization, and finalize_update follow the same live-lock hold behavior' );
} finally {
	$GLOBALS['wpdb'] = $matrix_saved_wpdb;
}
tutorpress_live_lessons_assert( $matrix_saved_wpdb === $GLOBALS['wpdb'], 'the wpdb global is restored after the reconciliation lock-release matrix' );
tutorpress_live_lessons_pass( 'reconciliation locks release by exact token' );

$pass8j_uncertain = TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => 'op-matrix', 'provider_id' => 'evt-matrix', 'version' => 1 ) );
$pass8j_uncertain_later = TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => 'op-matrix', 'provider_id' => 'evt-matrix', 'version' => 2 ) );
$pass8j_legacy_shape = TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => null, 'provider_id' => 'evt-matrix', 'version' => 0 ) );
$pass8j_mixed_operation = TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => 'op-matrix', 'provider_id' => 'evt-matrix', 'version' => 0 ) );
$pass8j_mixed_version = TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => null, 'provider_id' => 'evt-matrix', 'version' => 1 ) );
tutorpress_live_lessons_assert( is_array( $pass8j_uncertain ) && is_array( $pass8j_uncertain_later ) && is_string( $pass8j_uncertain['provider_id'] ) && '' !== $pass8j_uncertain['provider_id'] && is_string( $pass8j_uncertain['operation_id'] ) && '' !== $pass8j_uncertain['operation_id'] && is_int( $pass8j_uncertain['version'] ) && $pass8j_uncertain['version'] >= 1 && is_int( $pass8j_uncertain_later['version'] ) && $pass8j_uncertain_later['version'] >= 1 && is_array( $pass8j_legacy_shape ) && is_string( $pass8j_legacy_shape['provider_id'] ) && '' !== $pass8j_legacy_shape['provider_id'] && null === $pass8j_legacy_shape['operation_id'] && is_int( $pass8j_legacy_shape['version'] ) && 0 === $pass8j_legacy_shape['version'] && null === $pass8j_mixed_operation && null === $pass8j_mixed_version, 'uncertain-create identity requires a provider id, an operation id, and version of at least 1, and legacy identity requires a provider id, a null operation id, and version 0' );

$pass8j_ok = true;
$pass8j_check = static function ( $saved, $post_id, $bytes, $reads, $sql ) use ( &$pass8j_ok, $methods ) {
	if ( null !== $saved || 0 !== $reads || array() !== $methods() || $sql !== count( $GLOBALS['wpdb']->statements ) || isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $post_id ] ) || $bytes !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post_id, '_tutorpress_live_lesson_sync' ) ) {
		$pass8j_ok = false;
	}
};
$pass8j_auth_input = $google_input;
list( $pass8j_auth_retry_item, $pass8j_auth_retry_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
list( $pass8j_auth_abandon_item, $pass8j_auth_abandon_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
list( $pass8j_auth_attach_item, $pass8j_auth_attach_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
list( $pass8j_auth_final_item, $pass8j_auth_final_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$pass8j_auth_inspect_item = $plant( 'tutor-google-meet', $google_bytes );
$pass8j_auth_owner_item = $plant( 'tutor-google-meet', null );
$pass8j_auth_legacy_item = $plant( 'tutor-google-meet', null );
$pass8j_auth_legacy_attach_item = $plant( 'tutor-google-meet', null );
$option_reads = 0;
$pass8j_auth_sql = count( $GLOBALS['wpdb']->statements );
$pass8j_ok = true;
$pass8j_check( $inspect( $pass8j_auth_inspect_item, $pass8j_auth_input, array(), 'token-8j-auth-inspect', $deny ), $pass8j_auth_inspect_item['ID'], $google_bytes, $option_reads, $pass8j_auth_sql );
$pass8j_check( $inspect( $pass8j_auth_inspect_item, $pass8j_auth_input, array(), 'token-8j-auth-inspect-broad', $broad ), $pass8j_auth_inspect_item['ID'], $google_bytes, $option_reads, $pass8j_auth_sql );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array();
$pass8j_check( TutorPress_Live_Lesson_Sync_Orchestrator::retry( $pass8j_auth_retry_item, $topic, $course, $deny, $evil, $adapter, $now, 'token-8j-auth-retry', $sync_read, $sync_update, $sync_affected, $sync_write, $option, $takeover ), $pass8j_auth_retry_item['ID'], $pass8j_auth_retry_bytes, $option_reads, $pass8j_auth_sql );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$pass8j_check( TutorPress_Live_Lesson_Sync_Orchestrator::retry( $pass8j_auth_retry_item, $topic, $course, $broad, $evil, $adapter, $now, 'token-8j-auth-retry-broad', $sync_read, $sync_update, $sync_affected, $sync_write, $option, $takeover ), $pass8j_auth_retry_item['ID'], $pass8j_auth_retry_bytes, $option_reads, $pass8j_auth_sql );
$pass8j_check( $abandon( $pass8j_auth_abandon_item, $evil, 'token-8j-auth-abandon', $deny ), $pass8j_auth_abandon_item['ID'], $pass8j_auth_abandon_bytes, $option_reads, $pass8j_auth_sql );
$pass8j_check( $resolve( $pass8j_auth_owner_item, $owner_input, 'token-8j-auth-owner', $deny, array( 'preflight' => array( true ) ) ), $pass8j_auth_owner_item['ID'], null, $option_reads, $pass8j_auth_sql );
$pass8j_check( $legacy( $pass8j_auth_legacy_item, $legacy_input, 'token-8j-auth-legacy', $deny, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) ), $pass8j_auth_legacy_item['ID'], null, $option_reads, $pass8j_auth_sql );
$pass8j_check( $attach( $pass8j_auth_attach_item, $attach_google, array(), 'token-8j-auth-attach', $deny ), $pass8j_auth_attach_item['ID'], $pass8j_auth_attach_bytes, $option_reads, $pass8j_auth_sql );
$pass8j_check( $legacy_attach( $pass8j_auth_legacy_attach_item, $legacy_attach_input, array(), 'token-8j-auth-legacy-attach', $deny ), $pass8j_auth_legacy_attach_item['ID'], null, $option_reads, $pass8j_auth_sql );
$pass8j_check( $finalize( $pass8j_auth_final_item, $update_input, array(), 'token-8j-auth-final', $deny ), $pass8j_auth_final_item['ID'], $pass8j_auth_final_bytes, $option_reads, $pass8j_auth_sql );
tutorpress_live_lessons_assert( $pass8j_ok, 'unauthorized reconciliation context returns null before hold and before a provider call' );

$pass8j_stale_version = $google_input;
$pass8j_stale_version['version'] = 9;
$pass8j_stale_operation = $google_input;
$pass8j_stale_operation['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$pass8j_attach_stale_version = $attach_google;
$pass8j_attach_stale_version['version'] = 9;
$pass8j_attach_stale_operation = $attach_google;
$pass8j_attach_stale_operation['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
$pass8j_final_stale_version = $update_input;
$pass8j_final_stale_version['version'] = 9;
$pass8j_final_stale_operation = $update_input;
$pass8j_final_stale_operation['operation_id'] = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
list( $pass8j_stale_inspect_item, $pass8j_stale_inspect_bytes ) = array( $plant( 'tutor-google-meet', $google_bytes ), $google_bytes );
list( $pass8j_stale_retry_item, $pass8j_stale_retry_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', false, null, 'provider_create_failed' );
list( $pass8j_stale_abandon_item, $pass8j_stale_abandon_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
list( $pass8j_stale_attach_item, $pass8j_stale_attach_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
list( $pass8j_stale_final_item, $pass8j_stale_final_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$option_reads = 0;
$pass8j_stale_sql = count( $GLOBALS['wpdb']->statements );
$pass8j_ok = true;
$pass8j_check( $inspect( $pass8j_stale_inspect_item, $pass8j_stale_version, array(), 'token-8j-stale-inspect-version' ), $pass8j_stale_inspect_item['ID'], $pass8j_stale_inspect_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $inspect( $pass8j_stale_inspect_item, $pass8j_stale_operation, array(), 'token-8j-stale-inspect-operation' ), $pass8j_stale_inspect_item['ID'], $pass8j_stale_inspect_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $retry( $pass8j_stale_retry_item, $pass8j_stale_version, array(), 'token-8j-stale-retry-version' ), $pass8j_stale_retry_item['ID'], $pass8j_stale_retry_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $retry( $pass8j_stale_retry_item, $pass8j_stale_operation, array(), 'token-8j-stale-retry-operation' ), $pass8j_stale_retry_item['ID'], $pass8j_stale_retry_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $abandon( $pass8j_stale_abandon_item, $pass8j_stale_version, 'token-8j-stale-abandon-version' ), $pass8j_stale_abandon_item['ID'], $pass8j_stale_abandon_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $abandon( $pass8j_stale_abandon_item, $pass8j_stale_operation, 'token-8j-stale-abandon-operation' ), $pass8j_stale_abandon_item['ID'], $pass8j_stale_abandon_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $attach( $pass8j_stale_attach_item, $pass8j_attach_stale_version, array(), 'token-8j-stale-attach-version' ), $pass8j_stale_attach_item['ID'], $pass8j_stale_attach_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $attach( $pass8j_stale_attach_item, $pass8j_attach_stale_operation, array(), 'token-8j-stale-attach-operation' ), $pass8j_stale_attach_item['ID'], $pass8j_stale_attach_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $finalize( $pass8j_stale_final_item, $pass8j_final_stale_version, array(), 'token-8j-stale-final-version' ), $pass8j_stale_final_item['ID'], $pass8j_stale_final_bytes, $option_reads, $pass8j_stale_sql );
$pass8j_check( $finalize( $pass8j_stale_final_item, $pass8j_final_stale_operation, array(), 'token-8j-stale-final-operation' ), $pass8j_stale_final_item['ID'], $pass8j_stale_final_bytes, $option_reads, $pass8j_stale_sql );
tutorpress_live_lessons_assert( $pass8j_ok, 'a stale version or a stale operation id returns null before hold and before a provider call' );

$pass8j_version_owner = $plant( 'tutor-google-meet', null );
$pass8j_version_legacy = $plant( 'tutor-google-meet', null );
$pass8j_version_legacy_attach = $plant( 'tutor-google-meet', null );
$pass8j_version_one = $owner_input;
$pass8j_version_one['version'] = 1;
$pass8j_version_two = $owner_input;
$pass8j_version_two['version'] = 2;
$pass8j_legacy_version_one = $legacy_input;
$pass8j_legacy_version_one['version'] = 1;
$pass8j_legacy_version_two = $legacy_input;
$pass8j_legacy_version_two['version'] = 2;
$pass8j_legacy_attach_one = $legacy_attach_input;
$pass8j_legacy_attach_one['version'] = 1;
$pass8j_legacy_attach_two = $legacy_attach_input;
$pass8j_legacy_attach_two['version'] = 2;
$option_reads = 0;
$pass8j_version_sql = count( $GLOBALS['wpdb']->statements );
$pass8j_ok = true;
$pass8j_check( $resolve( $pass8j_version_owner, $pass8j_version_one, 'token-8j-owner-one', null, array( 'preflight' => array( true ) ) ), $pass8j_version_owner['ID'], null, $option_reads, $pass8j_version_sql );
$pass8j_check( $resolve( $pass8j_version_owner, $pass8j_version_two, 'token-8j-owner-two', null, array( 'preflight' => array( true ) ) ), $pass8j_version_owner['ID'], null, $option_reads, $pass8j_version_sql );
$pass8j_check( $legacy( $pass8j_version_legacy, $pass8j_legacy_version_one, 'token-8j-legacy-one', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) ), $pass8j_version_legacy['ID'], null, $option_reads, $pass8j_version_sql );
$pass8j_check( $legacy( $pass8j_version_legacy, $pass8j_legacy_version_two, 'token-8j-legacy-two', null, array( 'hydrate_legacy' => array( array( 'provider_id' => 'evt-legacy' ) ), 'preflight' => array( true ) ) ), $pass8j_version_legacy['ID'], null, $option_reads, $pass8j_version_sql );
$pass8j_check( $legacy_attach( $pass8j_version_legacy_attach, $pass8j_legacy_attach_one, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-8j-legacy-attach-one' ), $pass8j_version_legacy_attach['ID'], null, $option_reads, $pass8j_version_sql );
$pass8j_check( $legacy_attach( $pass8j_version_legacy_attach, $pass8j_legacy_attach_two, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-8j-legacy-attach-two' ), $pass8j_version_legacy_attach['ID'], null, $option_reads, $pass8j_version_sql );
tutorpress_live_lessons_assert( $pass8j_ok, 'resolve_owner, initialize_legacy, and attach_legacy_identity reject any version other than 0' );

$pass8j_create = array( 'provider' => 'google_meet', 'acting_user_id' => 11, 'start' => '2026-09-21 15:04:00', 'end' => '2026-09-21 16:04:00', 'timezone' => 'America/New_York', 'title' => 'Copied', 'content' => 'Body', 'credentials' => $creds, 'provider_id' => 'evt-evil' );
$pass8j_create_next = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$pass8j_create_posts = TutorPress_Live_Lessons_Store_Fake::$posts;
$pass8j_create_meta = TutorPress_Live_Lessons_Store_Fake::$meta;
$pass8j_create_options = TutorPress_Live_Lessons_Store_Fake::$options;
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array( 'preflight' => array( true ) );
$pass8j_validated = TutorPress_Live_Lesson_Sync_Orchestrator::validate_create( $topic, $course, $allow, $pass8j_create, array(), $adapter, $now );
$pass8j_staged = TutorPress_Live_Lesson_Sync_Orchestrator::stage_pending( $topic, $course, $allow, $pass8j_create, array(), $adapter, $now, 'token-8j-create' );
$pass8j_create_unchanged = null === $pass8j_validated && null === $pass8j_staged && array() === $methods() && $pass8j_create_next === TutorPress_Live_Lessons_Store_Fake::$next_post_id && $pass8j_create_posts === TutorPress_Live_Lessons_Store_Fake::$posts && $pass8j_create_meta === TutorPress_Live_Lessons_Store_Fake::$meta && $pass8j_create_options === TutorPress_Live_Lessons_Store_Fake::$options;
list( $pass8j_update_item, $pass8j_update_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'synced', 'update', 'confirmed', false, 'evt-stored', null );
$pass8j_update_input = array( 'acting_user_id' => 11, 'credentials' => $creds, 'provider_id' => 'evt-evil' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
$pass8j_updated = TutorPress_Live_Lesson_Sync_Orchestrator::stage_update( $pass8j_update_item, $topic, $course, $allow, $pass8j_update_input, $adapter, $now, 'token-8j-update', $sync_read, $sync_update, $sync_affected );
$pass8j_update_methods = $methods();
$pass8j_inspect_input = $google_input;
$pass8j_inspect_input['provider_id'] = 'evt-evil';
$pass8j_inspect_item = $plant( 'tutor-google-meet', $google_bytes );
$option_reads = 0;
$pass8j_inspect_sql = count( $GLOBALS['wpdb']->statements );
$pass8j_inspected = $inspect( $pass8j_inspect_item, $pass8j_inspect_input, array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-stored' ) ) ), 'token-8j-inspect-id' );
tutorpress_live_lessons_assert( $pass8j_create_unchanged && null === $pass8j_updated && array() === $pass8j_update_methods && array() === $methods() && null === $pass8j_inspected && 0 === $option_reads && $pass8j_inspect_sql === count( $GLOBALS['wpdb']->statements ) && $google_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass8j_inspect_item['ID'], '_tutorpress_live_lesson_sync' ) && $pass8j_update_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass8j_update_item['ID'], '_tutorpress_live_lesson_sync' ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass8j_update_item['ID'] ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $pass8j_inspect_item['ID'] ] ), 'ordinary create, update, and inspect inputs carrying provider_id return null before provider dispatch or local mutation' );

list( $pass8j_mismatch_item, $pass8j_mismatch_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'update', 'dispatch_possible', true, 'evt-stored', 'provider_update_failed' );
$pass8j_mismatch_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass8j_mismatch_saved = $finalize( $pass8j_mismatch_item, $update_input, array( 'preflight' => array( true ), 'fetch' => array( $fetched( 'evt-stored' ) ), 'compare_target' => array( array( 'match' => false ) ) ), 'token-8j-mismatch' );
$pass8j_mismatch_methods = $methods();
$pass8j_mismatch_kept = null === $pass8j_mismatch_saved && $pass8j_mismatch_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass8j_mismatch_item['ID'], '_tutorpress_live_lesson_sync' ) && $pass8j_mismatch_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && false === in_array( 'metadata_map', $pass8j_mismatch_methods, true );
list( $pass8j_zoom_item, $pass8j_zoom_bytes ) = $plant_row( 'tutor_zoom_meeting', 'zoom', 4, 'error', 'update', 'dispatch_possible', true, 'evt-zoom', 'provider_update_failed' );
$pass8j_zoom_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass8j_zoom_saved = $finalize( $pass8j_zoom_item, array( 'version' => 4, 'operation_id' => $op_id, 'course_author_id' => 42, 'acting_instructor_id' => 9, 'credentials' => $creds ), array( 'preflight' => array( true ), 'fetch' => array( $provider_result( 'uncertain', 'fetch', 'evt-zoom', null ) ) ), 'token-8j-zoom-uncertain' );
$pass8j_zoom_methods = $methods();
$pass8j_zoom_id = isset( TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['provider_id'] ) ? TutorPress_Live_Lessons_Adapter_Fake::$calls[1][1]['provider_id'] : '';
$pass8j_zoom_kept = null === $pass8j_zoom_saved && $pass8j_zoom_bytes === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass8j_zoom_item['ID'], '_tutorpress_live_lesson_sync' ) && isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $pass8j_zoom_item['ID'] ] ) && $pass8j_zoom_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && array( 'preflight', 'fetch' ) === $pass8j_zoom_methods && 'evt-zoom' === $pass8j_zoom_id;
$pass8j_absent_item = $plant( 'tutor-google-meet', null );
$pass8j_absent_projected = TutorPress_Live_Lesson_Response_Projector::project_item( $pass8j_absent_item, $topic, $course, $allow, null, null );
$option_reads = 0;
$pass8j_absent_sql = count( $GLOBALS['wpdb']->statements );
$pass8j_absent_inspect = $inspect( $pass8j_absent_item, array( 'version' => 0, 'operation_id' => $op_id, 'acting_user_id' => 11, 'credentials' => $creds ), array(), 'token-8j-absent-inspect' );
$pass8j_absent_inspect_kept = null === $pass8j_absent_inspect && 0 === $option_reads && $pass8j_absent_sql === count( $GLOBALS['wpdb']->statements ) && array() === $methods() && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass8j_absent_item['ID'], '_tutorpress_live_lesson_sync' );
$pass8j_absent_legacy = $legacy( $pass8j_absent_item, array_replace( $legacy_input, array( 'local' => array() ) ), 'token-8j-absent-legacy', null, array( 'hydrate_legacy' => array( array( 'provider_id' => null ) ) ) );
$pass8j_absent_legacy_kept = null === $pass8j_absent_legacy && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass8j_absent_item['ID'], '_tutorpress_live_lesson_sync' ) && array( 'hydrate_legacy' ) === $methods() && 0 === $option_reads;
list( $pass8j_abandon_item, $pass8j_abandon_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$pass8j_abandon_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass8j_abandon_saved = $abandon( $pass8j_abandon_item, $evil, 'token-8j-abandon' );
$pass8j_abandon_row = is_array( $pass8j_abandon_saved ) ? json_decode( $pass8j_abandon_saved['envelope'], true ) : null;
$pass8j_abandon_kept = is_array( $pass8j_abandon_row ) && 'unlinked' === $pass8j_abandon_row['state'] && 'orphan_may_remain' === $pass8j_abandon_row['warning'] && false === $pass8j_abandon_row['reconciliation_required'] && null === $pass8j_abandon_row['provider_id'] && null === $pass8j_abandon_row['operation'] && array() === $methods() && $pass8j_abandon_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls;
tutorpress_live_lessons_assert( $pass8j_mismatch_kept && $pass8j_zoom_kept && is_array( $pass8j_absent_projected ) && 0 === $pass8j_absent_projected['sync_version'] && false === $pass8j_absent_projected['persisted_envelope'] && $pass8j_absent_inspect_kept && $pass8j_absent_legacy_kept && $pass8j_abandon_kept, 'target mismatch and zoom fetch uncertainty stay unresolved, an absent legacy envelope stays version 0, and abandonment becomes unlinked' );

list( $pass8j_success_item, $pass8j_success_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$pass8j_during = array();
$pass8j_write = static function ( $post_id, $key, $value ) use ( &$pass8j_during ) {
	$pass8j_during[] = TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post_id, '_tutorpress_live_lesson_sync' );
	return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $key, $value );
};
$pass8j_saved_wpdb = $GLOBALS['wpdb'];
$pass8j_watch = new class() extends TutorPress_Topic_Copy_Wpdb {
	public $hooks_at = array();
	public function query( $prepared ) {
		$this->hooks_at[] = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
		return parent::query( $prepared );
	}
};
$GLOBALS['wpdb'] = $pass8j_watch;
$pass8j_hook_before = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$pass8j_success = $settle( $pass8j_success_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-8j-success', null, $pass8j_write );
$pass8j_hooks_at = $pass8j_watch->hooks_at;
$pass8j_statements = $pass8j_watch->statements;
$GLOBALS['wpdb'] = $pass8j_saved_wpdb;
$pass8j_success_row = is_array( $pass8j_success ) ? json_decode( $pass8j_success['envelope'], true ) : null;
$pass8j_success_hooks = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $pass8j_hook_before );
$pass8j_success_same = array() !== $pass8j_during;
foreach ( $pass8j_during as $pass8j_seen ) {
	if ( $pass8j_success_bytes !== $pass8j_seen ) { $pass8j_success_same = false; }
}
$pass8j_success_actions = is_array( $pass8j_success_row ) ? TutorPress_Live_Lesson_Response_Projector::project_summary( $pass8j_success_item, $topic, $course, $allow, $pass8j_success_row ) : null;
$pass8j_legacy_success_item = $plant( 'tutor-google-meet', null );
$pass8j_legacy_success_hooks = TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass8j_legacy_success = $legacy_attach( $pass8j_legacy_success_item, $legacy_attach_input, $legacy_attach_queue( $fetched( 'evt-supplied' ), $legacy_map ), 'token-8j-legacy-success' );
$pass8j_legacy_success_same = $pass8j_legacy_success_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls;
$pass8j_legacy_success_row = is_array( $pass8j_legacy_success ) ? json_decode( $pass8j_legacy_success['envelope'], true ) : null;
$pass8j_legacy_rows = 0;
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $pass8j_meta_row ) {
	if ( (int) $pass8j_meta_row['post_id'] === (int) $pass8j_legacy_success_item['ID'] && '_tutorpress_live_lesson_sync' === $pass8j_meta_row['meta_key'] ) { $pass8j_legacy_rows++; }
}
list( $pass8j_error_item, $pass8j_error_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array( new RuntimeException( 'hook-exploded-secret' ) );
$pass8j_error = $settle( $pass8j_error_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-8j-error' );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutor_google_meet_after_save_meeting'] = array();
$pass8j_error_data = $pass8j_error instanceof WP_Error ? $pass8j_error->get_error_data() : null;
$pass8j_abandon_actions = TutorPress_Live_Lesson_Response_Projector::project_summary( $pass8j_abandon_item, $topic, $course, $allow, $pass8j_abandon_row );
$pass8j_blobs = json_encode( array( $pass8j_success_row, $pass8j_success_hooks, $pass8j_success_actions, $pass8j_legacy_success_row, $pass8j_abandon_row, $pass8j_abandon_actions, $pass8j_error_data, $pass8j_error instanceof WP_Error ? $pass8j_error->get_error_message() : '' ) );
$pass8j_action_fields = true;
foreach ( array( $pass8j_success_actions, $pass8j_abandon_actions ) as $pass8j_projected ) {
	foreach ( is_array( $pass8j_projected ) && isset( $pass8j_projected['actions'] ) ? $pass8j_projected['actions'] : array( false ) as $pass8j_action ) {
		if ( ! is_array( $pass8j_action ) || array_key_exists( 'provider_id', $pass8j_action ) ) { $pass8j_action_fields = false; }
	}
}
tutorpress_live_lessons_assert( $pass8j_success_same && $stored_map( $pass8j_success_item['ID'], $google_map ) && is_array( $pass8j_success_row ) && 5 === $pass8j_success_row['version'] && 'synced' === $pass8j_success_row['state'] && 'evt-supplied' === $pass8j_success_row['provider_id'] && 1 === count( $pass8j_statements ) && $lock_sql === $pass8j_statements[0][0] && TutorPress_Live_Lesson_Sync_Repository::owns( $pass8j_statements[0][2], 'token-8j-success' ) && array( $pass8j_hook_before ) === $pass8j_hooks_at && array( array( 'tutor_google_meet_after_save_meeting', array( $pass8j_success_item['ID'] ) ) ) === $pass8j_success_hooks && is_array( $pass8j_legacy_success_row ) && 1 === $pass8j_legacy_success_row['version'] && 'synced' === $pass8j_legacy_success_row['state'] && 1 === $pass8j_legacy_rows && $pass8j_legacy_success_same && $stored_map( $pass8j_legacy_success_item['ID'], $legacy_map ) && $pass8j_error instanceof WP_Error && is_array( $pass8j_error_data ) && $pass8j_action_fields && is_string( $pass8j_blobs ) && false === strpos( $pass8j_blobs, $secret ), 'uncertain-create attach writes the complete map and releases before its one-argument save hook, legacy attach writes one synced version-1 row with no save hook, and no credential sentinel is projected' );
$pass_2d_item = $plant( 'tutor-google-meet', null );
$pass_2d_name = 'tutorpress_live_lesson_lock_' . $pass_2d_item['ID'];
$pass_2d_prior = $lock_bytes( 'token-2d-old', 'create', '2026-09-21 16:05:00' );
TutorPress_Live_Lessons_Store_Fake::$options[ $pass_2d_name ] = $pass_2d_prior;
$pass_2d_updates = $option_updates;
$pass_2d_early = TutorPress_Live_Lesson_Sync_Repository::takeover( $pass_2d_item['ID'], 'create', 'token-2d-next', $pass_2d_prior, '2026-09-21 16:00:00', $takeover );
list( $pass_2d_attach_item, $pass_2d_attach_bytes ) = $plant_row( 'tutor-google-meet', 'google_meet', 4, 'error', 'create', 'dispatch_possible', true, null, 'provider_create_failed' );
$pass_2d_saved_wpdb = $GLOBALS['wpdb'];
$pass_2d_watch = new class() extends TutorPress_Topic_Copy_Wpdb {
	public $hooks_at_delete = array();
	public function query( $prepared ) {
		$this->hooks_at_delete[] = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
		return parent::query( $prepared );
	}
};
$GLOBALS['wpdb'] = $pass_2d_watch;
$pass_2d_hooks = count( TutorPress_Live_Lessons_Hook_Fake::$calls );
$pass_2d_saved = $settle( $pass_2d_attach_item, $attach_google, $full_queue( $fetched( 'evt-supplied' ), $google_map ), 'token-2d-attach' );
$pass_2d_at = $pass_2d_watch->hooks_at_delete;
$GLOBALS['wpdb'] = $pass_2d_saved_wpdb;
$pass_2d_row = is_array( $pass_2d_saved ) ? json_decode( $pass_2d_saved['envelope'], true ) : null;
$pass_2d_new = array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, $pass_2d_hooks );
tutorpress_live_lessons_assert( null === $pass_2d_early && $pass_2d_updates === $option_updates && $pass_2d_prior === TutorPress_Live_Lessons_Store_Fake::$options[ $pass_2d_name ] && is_array( $pass_2d_row ) && 'synced' === $pass_2d_row['state'] && 'evt-supplied' === $pass_2d_row['provider_id'] && array( $pass_2d_hooks ) === $pass_2d_at && array( array( 'tutor_google_meet_after_save_meeting', array( (int) $pass_2d_attach_item['ID'] ) ) ) === $pass_2d_new && $pass_2d_attach_bytes !== TutorPress_Live_Lessons_Store_Fake::meta_bytes( $pass_2d_attach_item['ID'], '_tutorpress_live_lesson_sync' ), 'a takeover before expiry stays null and writer-supplied attach records one save hook after the exact-token delete' );
tutorpress_live_lessons_pass( 'reconciliation matrix' );
