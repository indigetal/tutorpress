<?php

$source_course = array( 'ID' => 7, 'post_type' => 'courses' );
$target_course = array( 'ID' => 9, 'post_type' => 'courses', 'post_author' => 42 );
$ownerless     = array( 'ID' => 9, 'post_type' => 'courses' );
$topic         = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$target        = array( 'ID' => 80, 'post_type' => 'topics', 'post_parent' => 9 );
$bad_target    = array( 'ID' => 80, 'post_type' => 'topics', 'post_parent' => 7 );
$google        = array( 'ID' => 40, 'post_type' => 'tutor-google-meet', 'post_parent' => 8, 'post_title' => 'Standup', 'post_content' => 'Notes', 'post_author' => 3 );
$bad_google    = array( 'ID' => 40, 'post_type' => 'tutor-google-meet', 'post_parent' => 4, 'post_title' => 'Standup', 'post_content' => 'Notes' );
$zoom          = array( 'ID' => 15, 'post_type' => 'tutor_zoom_meeting', 'post_parent' => 8, 'post_title' => 'Zoom', 'post_content' => 'Z' );
$allow         = static function ( $cap, $id ) { return 'edit_post' === $cap && ( 7 === (int) $id || 9 === (int) $id ); };
$source_only   = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === (int) $id; };
$target_only   = static function ( $cap, $id ) { return 'edit_post' === $cap && 9 === (int) $id; };
$deny          = static function () { return false; };
$broad         = static function ( $cap ) { return 'edit_posts' === $cap; };
$children      = array(
	array( 'ID' => 1, 'post_type' => 'lesson', 'menu_order' => 4, 'post_parent' => 80 ),
	array( 'ID' => 2, 'post_type' => 'tutor_quiz', 'menu_order' => 2, 'post_parent' => 80 ),
	array( 'ID' => 3, 'post_type' => 'tutor_assignments', 'menu_order' => 9, 'post_parent' => 80 ),
	array( 'ID' => 4, 'post_type' => 'tutor_zoom_meeting', 'menu_order' => 1, 'post_parent' => 80 ),
	array( 'ID' => 5, 'post_type' => 'page', 'menu_order' => 100, 'post_parent' => 80 ),
);
$meta          = array(
	'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00',
	'tutor-google-meet-end-datetime'   => '2026-09-21 16:04:00',
	'tutor-google-meet-event-details'  => '{"id":"evt-1","meet_link":"https://meet.example"}',
	'tutor-google-meet-link'           => 'https://meet.example',
	'_tutor_gmt_data'                  => '{"id":"gmt-1"}',
	'_tutor_zm_data'                   => '{"id":"z-1","host_id":"host-1"}',
);
$posts         = TutorPress_Live_Lessons_Store_Fake::$posts;
$stored_meta   = TutorPress_Live_Lessons_Store_Fake::$meta;
$calls         = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$hooks         = TutorPress_Live_Lessons_Hook_Fake::$calls;
$ledger        = TutorPress_Live_Lessons_Boundary_Fake::$ledger;

tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $bad_google, $topic, $source_course, $target, $target_course, $children, $meta, $allow ) && null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $target_only ) && null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $deny ) && null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $broad ) && $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $stored_meta === TutorPress_Live_Lessons_Store_Fake::$meta, 'direct duplicate requires an authorized source item and edit_post on the source course' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $bad_target, $target_course, $children, $meta, $allow ) && null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $source_only ) && $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $stored_meta === TutorPress_Live_Lessons_Store_Fake::$meta, 'direct duplicate requires an authorized target topic on the target course' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $zoom, $topic, $source_course, $target, $target_course, $children, $meta, $allow ) && $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $stored_meta === TutorPress_Live_Lessons_Store_Fake::$meta, 'an authorized Zoom source is rejected with no write' );

$lock_sql      = 'DELETE FROM wp_options WHERE option_name = %s AND CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)';
$exact_release = static function ( $post_id, $statements_before, $releases_before ) use ( $lock_sql ) {
	$statements = $GLOBALS['wpdb']->statements;
	$releases   = TutorPress_Topic_Copy_Harness::$releases;
	$name       = 'tutorpress_live_lesson_lock_' . (int) $post_id;
	if ( count( $statements ) !== $statements_before + 1 || count( $releases ) !== $releases_before + 1 ) {
		return false;
	}
	$statement = $statements[ $statements_before ];
	$release   = $releases[ $releases_before ];
	return is_array( $statement ) && array( $lock_sql, $name, $release[1] ) === $statement && array( $name, $release[1], true ) === $release && is_string( $release[1] ) && '' !== $release[1] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] );
};
$success_statements = count( $GLOBALS['wpdb']->statements );
$success_releases   = count( TutorPress_Topic_Copy_Harness::$releases );
$copy_id            = TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $allow );
$stored  = TutorPress_Live_Lessons_Store_Fake::$posts[ $copy_id ];
$written = array();
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	if ( (int) $row['post_id'] === $copy_id ) {
		$written[ $row['meta_key'] ] = $row['meta_value'];
	}
}
ksort( $written );
$sync     = isset( $written['_tutorpress_live_lesson_sync'] ) ? $written['_tutorpress_live_lesson_sync'] : null;
$schedule = $written;
unset( $schedule['_tutorpress_live_lesson_sync'] );
$envelope  = is_string( $sync ) ? json_decode( $sync, true ) : null;
$hook_call = array( 'tutorpress_live_lesson_duplicated', array( $copy_id, 40, array( 'id' => $copy_id, 'title' => 'Standup (Copy)', 'description' => 'Notes', 'type' => 'google_meet', 'topicId' => 80, 'courseId' => 9, 'status' => 'scheduled', 'createdAt' => '', 'updatedAt' => '', 'startDateTime' => '2026-09-21 15:04:00', 'endDateTime' => '2026-09-21 16:04:00', 'settings' => array( 'timezone' => 'UTC', 'add_enrolled_students' => 'No' ), 'providerConfig' => array( 'meetingUrl' => '', 'eventId' => '' ) ) ) );
tutorpress_live_lessons_assert( array( 'post_title' => 'Standup (Copy)', 'post_content' => 'Notes', 'post_status' => 'publish', 'post_type' => 'tutor-google-meet', 'post_parent' => 80, 'menu_order' => 10, 'ID' => $copy_id ) === $stored && false === array_key_exists( 'post_author', $stored ) && array( 'tutor-google-meet-end-datetime' => '2026-09-21 16:04:00', 'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00' ) === $schedule && is_array( $envelope ) && 42 === $envelope['owner_id'] && null === $envelope['provider_id'] && 'unlinked' === $envelope['state'] && 1 === $envelope['version'] && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $copy_id ] ) && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && array( $hook_call ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $hooks ) ) && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && $exact_release( $copy_id, $success_statements, $success_releases ) && false === TutorPress_Live_Lesson_Cutover::ENABLED, 'direct duplicate stores a publish Google copy with schedule fields and one unlinked envelope' );
$hooks     = TutorPress_Live_Lessons_Hook_Fake::$calls;

$ownerless_statements = count( $GLOBALS['wpdb']->statements );
$ownerless_releases   = count( TutorPress_Topic_Copy_Harness::$releases );
$removed              = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $ownerless, $children, $meta, $allow ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $removed ] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $removed ] ) && $ownerless_statements === count( $GLOBALS['wpdb']->statements ) && $ownerless_releases === count( TutorPress_Topic_Copy_Harness::$releases ), 'ownerless initialization deletes the copy without acquiring a lock' );

class TutorPress_Direct_Duplicate_Lock_Wpdb extends TutorPress_Topic_Copy_Wpdb {
	public $release_deletes = null;
	public $release_bytes   = null;

	public function query( $prepared ) {
		if ( is_array( $prepared ) && isset( $prepared[1] ) ) {
			$this->release_deletes = count( TutorPress_Topic_Copy_Harness::$deletes );
			$this->release_bytes   = isset( TutorPress_Live_Lessons_Store_Fake::$options[ $prepared[1] ] ) ? TutorPress_Live_Lessons_Store_Fake::$options[ $prepared[1] ] : null;
		}
		return parent::query( $prepared );
	}
}

$saved_wpdb             = $GLOBALS['wpdb'];
$saved_statement_count  = count( $saved_wpdb->statements );
$sampler                = new TutorPress_Direct_Duplicate_Lock_Wpdb();
$GLOBALS['wpdb']        = $sampler;
$locked_id              = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$locked_deletes         = TutorPress_Topic_Copy_Harness::$deletes;
$locked_release_count   = count( TutorPress_Topic_Copy_Harness::$releases );
$locked_hooks           = TutorPress_Live_Lessons_Hook_Fake::$calls;
$locked_name            = 'tutorpress_live_lesson_lock_' . $locked_id;
TutorPress_Topic_Copy_Harness::$fail_init = true;
$locked_escaped = false;
$locked_null    = null;
try {
	$locked_null = TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $allow );
} catch ( Throwable $error ) {
	$locked_escaped = true;
} finally {
	TutorPress_Topic_Copy_Harness::$fail_init = false;
	$GLOBALS['wpdb']                          = $saved_wpdb;
}
$locked_delete_count = count( $locked_deletes );
$locked_deletes[]    = array( $locked_id, true );
$locked_release      = isset( TutorPress_Topic_Copy_Harness::$releases[ $locked_release_count ] ) ? TutorPress_Topic_Copy_Harness::$releases[ $locked_release_count ] : null;
tutorpress_live_lessons_assert( false === $locked_escaped && null === $locked_null && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $locked_id ] ) && 1 === count( $sampler->statements ) && array( $lock_sql, $locked_name, $sampler->release_bytes ) === $sampler->statements[0] && is_string( $sampler->release_bytes ) && '' !== $sampler->release_bytes && $sampler->release_deletes === $locked_delete_count && count( TutorPress_Topic_Copy_Harness::$releases ) === $locked_release_count + 1 && array( $locked_name, $sampler->release_bytes, true ) === $locked_release && $locked_deletes === TutorPress_Topic_Copy_Harness::$deletes && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $locked_name ] ) && $locked_hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $saved_statement_count === count( $saved_wpdb->statements ), 'post-lock initialization releases the acquired token before deleting the copy' );

$kept             = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$kept_statements  = count( $GLOBALS['wpdb']->statements );
$kept_releases    = count( TutorPress_Topic_Copy_Harness::$releases );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $kept ] = true;
$failed = TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $ownerless, $children, $meta, $allow );
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $kept ] );
$failed_data = $failed instanceof WP_Error ? $failed->get_error_data() : null;
$kept_keys   = array();
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	if ( (int) $row['post_id'] === $kept ) {
		$kept_keys[] = $row['meta_key'];
	}
}
sort( $kept_keys );
tutorpress_live_lessons_assert( is_array( $failed_data ) && 0 === $failed_data['sync_version'] && 'error' === $failed_data['sync_state'] && false === $failed_data['persisted'] && true === $failed_data['refetch_required'] && is_array( $failed_data['projected_record'] ) && 0 === $failed_data['projected_record']['sync_version'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $kept ]['post_status'] && array( 'tutor-google-meet-end-datetime', 'tutor-google-meet-start-datetime' ) === $kept_keys && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $kept ] ) && $kept_statements === count( $GLOBALS['wpdb']->statements ) && $kept_releases === count( TutorPress_Topic_Copy_Harness::$releases ), 'failed cleanup leaves a discoverable publish version-0 record' );

TutorPress_Topic_Copy_Harness::$fail_insert = 'throw';
$thrown_id         = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$thrown_statements = count( $GLOBALS['wpdb']->statements );
$thrown_releases   = count( TutorPress_Topic_Copy_Harness::$releases );
$escaped           = false;
$thrown            = null;
try {
	$thrown = TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $allow );
} catch ( Throwable $error ) {
	$escaped = true;
}
TutorPress_Topic_Copy_Harness::$fail_insert = null;
tutorpress_live_lessons_assert( false === $escaped && null === $thrown && ! isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $thrown_id ] ) && $exact_release( $thrown_id, $thrown_statements, $thrown_releases ), 'a thrown envelope write releases the acquired token and deletes the copy' );

TutorPress_Topic_Copy_Harness::$fail_insert = 'throw';
$stuck             = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$stuck_statements  = count( $GLOBALS['wpdb']->statements );
$stuck_releases    = count( TutorPress_Topic_Copy_Harness::$releases );
TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck ] = true;
$escaped = false;
$stuck_error = null;
try {
	$stuck_error = TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $allow );
} catch ( Throwable $error ) {
	$escaped = true;
}
TutorPress_Topic_Copy_Harness::$fail_insert = null;
unset( TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $stuck ] );
$stuck_data = $stuck_error instanceof WP_Error ? $stuck_error->get_error_data() : null;
tutorpress_live_lessons_assert( false === $escaped && is_array( $stuck_data ) && 0 === $stuck_data['sync_version'] && 'error' === $stuck_data['sync_state'] && false === strpos( $stuck_error->get_error_message(), 'envelope failed' ) && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $stuck ]['post_status'] && $exact_release( $stuck, $stuck_statements, $stuck_releases ) && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls, 'a thrown envelope write leaves a version-0 record when cleanup fails' );

TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_duplicated'] = array( new RuntimeException( 'hook boom' ) );
$hook_id           = TutorPress_Live_Lessons_Store_Fake::$next_post_id;
$hook_statements   = count( $GLOBALS['wpdb']->statements );
$hook_releases     = count( TutorPress_Topic_Copy_Harness::$releases );
$escaped    = false;
$hook_error = null;
try {
	$hook_error = TutorPress_Live_Lesson_Duplicate_Service::duplicate( $google, $topic, $source_course, $target, $target_course, $children, $meta, $allow );
} catch ( Throwable $error ) {
	$escaped = true;
}
unset( TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_live_lesson_duplicated'] );
$hook_data = $hook_error instanceof WP_Error ? $hook_error->get_error_data() : null;
$hook_rows = array();
foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
	if ( (int) $row['post_id'] === $hook_id && '_tutorpress_live_lesson_sync' === $row['meta_key'] ) {
		$hook_rows[] = $row['meta_value'];
	}
}
$hook_envelope = isset( $hook_rows[0] ) && is_string( $hook_rows[0] ) ? json_decode( $hook_rows[0], true ) : null;
$hook_record   = array( 'id' => $hook_id, 'title' => 'Standup (Copy)', 'description' => 'Notes', 'type' => 'google_meet', 'topicId' => 80, 'courseId' => 9, 'status' => 'scheduled', 'sync_state' => 'unlinked', 'sync_version' => 1, 'persisted_envelope' => true, 'operation' => null, 'warning' => null, 'error' => null, 'resolution' => null, 'record' => null );
$hook_failure  = array( 'tutorpress_live_lesson_duplicated', array( $hook_id, 40, array( 'id' => $hook_id, 'title' => 'Standup (Copy)', 'description' => 'Notes', 'type' => 'google_meet', 'topicId' => 80, 'courseId' => 9, 'status' => 'scheduled', 'createdAt' => '', 'updatedAt' => '', 'startDateTime' => '2026-09-21 15:04:00', 'endDateTime' => '2026-09-21 16:04:00', 'settings' => array( 'timezone' => 'UTC', 'add_enrolled_students' => 'No' ), 'providerConfig' => array( 'meetingUrl' => '', 'eventId' => '' ) ) ) );
tutorpress_live_lessons_assert( false === $escaped && $hook_error instanceof WP_Error && 'live_lesson_persisted_transition' === $hook_error->get_error_code() && 'Live lesson transition failed.' === $hook_error->get_error_message() && false === strpos( $hook_error->get_error_message(), 'hook boom' ) && array( 'status' => 500, 'record_id' => $hook_id, 'course_id' => 9, 'topic_id' => 80, 'operation_id' => null, 'sync_state' => 'unlinked', 'sync_version' => 1, 'persisted' => true, 'refetch_required' => true, 'projected_record' => $hook_record ) === $hook_data && is_array( $hook_envelope ) && 'unlinked' === $hook_envelope['state'] && 1 === $hook_envelope['version'] && null === $hook_envelope['provider_id'] && 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $hook_id ]['post_status'] && $exact_release( $hook_id, $hook_statements, $hook_releases ) && array( $hook_failure ) === array_slice( TutorPress_Live_Lessons_Hook_Fake::$calls, count( $hooks ) ) && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'a hook throw keeps the version-1 unlinked record after lock release' );
$duplicate_method = new ReflectionMethod( 'TutorPress_Live_Lesson_Duplicate_Service', 'duplicate' );
$duplicate_params = array();
foreach ( $duplicate_method->getParameters() as $parameter ) {
	$duplicate_params[] = $parameter->getName();
}
tutorpress_live_lessons_assert( array( 'source', 'topic', 'course', 'target', 'target_course', 'children', 'meta', 'can' ) === $duplicate_params && false === TutorPress_Live_Lesson_Cutover::ENABLED, 'direct duplicate accepts no provider id' );
tutorpress_live_lessons_pass( 'direct google duplicate' );
