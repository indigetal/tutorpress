<?php

function tutorpress_live_lesson_empty_edit() {
	return array(
		'startDateTime'  => '',
		'endDateTime'    => '',
		'duration'       => null,
		'settings'       => array(
			'timezone'            => 'UTC',
			'autoRecording'       => '',
			'waitingRoom'         => false,
			'addEnrolledStudents' => '',
			'status'              => '',
			'transparency'        => '',
			'visibility'          => '',
			'reminder_minutes'    => null,
		),
		'providerConfig' => array( 'host' => '' ),
		'password'       => '',
	);
}

$can      = function ( $cap, $id ) { return 'edit_post' === $cap && 7 === $id; };
$course   = array( 'ID' => 7, 'post_type' => 'courses' );
$topic    = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$item     = array( 'ID' => 9, 'post_type' => 'tutor_zoom_meeting', 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Live', 'post_content' => 'Notes' );
$base     = array( 'schema_version' => 1, 'version' => 1, 'state' => 'pending', 'provider' => 'zoom', 'owner_id' => 42, 'provider_id' => null, 'provenance' => null, 'error' => 'safe', 'warning' => 'note', 'reconciliation_required' => false, 'operation' => null );
$project  = function ( $envelope, $metadata, $can ) use ( $item, $topic, $course ) { return TutorPress_Live_Lesson_Response_Projector::project_item( $item, $topic, $course, $can, $envelope, $metadata ); };
tutorpress_live_lessons_assert( null === $project( null, null, function () { return false; } ), 'unauthorized context projects nothing' );
$legacy = $project( null, null, $can );
$legacy_edit = array();
foreach ( array_keys( tutorpress_live_lesson_empty_edit() ) as $legacy_key ) {
	$legacy_edit[ $legacy_key ] = $legacy[ $legacy_key ];
}
tutorpress_live_lessons_assert( is_array( $legacy ) && 'error' === $legacy['sync_state'] && 0 === $legacy['sync_version'] && false === $legacy['persisted_envelope'] && null === $legacy['resolution'] && tutorpress_live_lesson_empty_edit() === $legacy_edit && 'scheduled' === $legacy['status'] && 9 === $legacy['id'], 'absence is version-zero legacy error and stays discoverable' );
foreach ( array( 'pending', 'error', 'unlinked' ) as $state ) {
	$projected = $project( array_replace( $base, array( 'state' => $state ) ), null, $can );
	tutorpress_live_lessons_assert( is_array( $projected ) && $state === $projected['sync_state'] && 1 === $projected['sync_version'] && true === $projected['persisted_envelope'], $state . ' stays discoverable' );
}
$operation = array( 'operation_id' => 'op-1', 'kind' => 'create', 'started_at' => '2026-09-21 15:04:00', 'base_version' => 1, 'provider' => 'zoom', 'owner_id' => 42, 'provider_id' => null, 'dispatch_state' => 'not_dispatched', 'course_id' => 7, 'topic_id' => 8, 'post_author' => 42, 'menu_order' => 3, 'title' => 'Live', 'content' => 'Notes', 'start_local' => '2026-09-21 15:04:00', 'end_local' => '2026-09-21 16:04:00', 'timezone' => 'UTC', 'duration' => array( 'value' => 60, 'unit' => 'minutes' ), 'provider_target' => array( 'auto_recording' => 'none', 'enforce_login' => false, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => true, 'mute_upon_entry' => false, 'participant_video' => false, 'waiting_room' => true ), 'delete_hook' => null );
$pending   = $project( array_replace( $base, array( 'operation' => $operation ) ), null, $can );
$encoded   = json_encode( $pending );
$target_edit = tutorpress_live_lesson_empty_edit();
$target_edit['startDateTime'] = '2026-09-21 15:04:00';
$target_edit['endDateTime'] = '2026-09-21 16:04:00';
$target_edit['duration'] = array( 'value' => 60, 'unit' => 'minutes' );
$target_edit['settings']['autoRecording'] = 'none';
$target_edit['settings']['waitingRoom'] = true;
$target_edit['providerConfig']['host'] = 'host-1';
$pending_edit = array();
foreach ( array_keys( tutorpress_live_lesson_empty_edit() ) as $pending_key ) {
	$pending_edit[ $pending_key ] = $pending[ $pending_key ];
}
tutorpress_live_lessons_assert( is_array( $pending ) && 'Live' === $pending['record']['title'] && 'host-1' === $pending['record']['provider_target']['host_id'] && 'op-1' === $pending['operation']['operation_id'] && 'safe' === $pending['error'] && 'note' === $pending['warning'] && is_string( $encoded ) && false === strpos( $encoded, 'token' ) && $target_edit === $pending_edit && '' === $pending['password'] && ! isset( $pending['record']['password'] ), 'pending projection uses the sanitized operation target' );
$with_password = array( '_tutor_zm_data' => array( 'password' => 'pw-sentinel', 'host_id' => 'host-9', 'timezone' => 'UTC', 'start_time' => '2026-09-21T15:04:00Z', 'duration' => 60, 'settings' => array( 'auto_recording' => 'cloud', 'waiting_room' => true ) ), '_tutor_zm_duration_unit' => 'min' );
$opened = $project( array_replace( $base, array( 'state' => 'synced' ) ), $with_password, $can );
$opened_summary = TutorPress_Live_Lesson_Response_Projector::project_summary( $item, $topic, $course, $can, array_replace( $base, array( 'state' => 'synced' ) ) );
$opened_summary_json = json_encode( $opened_summary );
tutorpress_live_lessons_assert( is_array( $opened ) && 'pw-sentinel' === $opened['password'] && 'host-9' === $opened['providerConfig']['host'] && '2026-09-21 15:04:00' === $opened['startDateTime'] && is_array( $opened['record'] ) && ! isset( $opened['record']['_tutor_zm_data']['password'] ) && is_array( $opened_summary ) && ! isset( $opened_summary['password'] ) && is_string( $opened_summary_json ) && false === strpos( $opened_summary_json, 'pw-sentinel' ), 'password stays on the authorized item and off the record and summary' );
$synced = $project( array_replace( $base, array( 'state' => 'synced' ) ), array( 'provider_id' => 'evt-1', 'api_secret' => 'hidden' ), $can );
tutorpress_live_lessons_assert( is_array( $synced ) && 'evt-1' === $synced['record']['provider_id'] && ! isset( $synced['record']['api_secret'] ), 'confirmed metadata is projected and credential keys are omitted' );
tutorpress_live_lessons_pass( 'authorized item projection' );
$ordered = $item;
$ordered['menu_order'] = 4;
$summary = TutorPress_Live_Lesson_Response_Projector::project_summary( $ordered, $topic, $course, $can, null );
$summary_json = json_encode( $summary );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Response_Projector::project_summary( $ordered, $topic, $course, function () { return false; }, null ), 'unauthorized summary projects nothing' );
tutorpress_live_lessons_assert( is_array( $summary ) && 4 === $summary['menuOrder'] && 'error' === $summary['sync_state'] && 0 === $summary['sync_version'] && 'resolve_owner' === $summary['actions'][0]['action'] && 'attach' === $summary['actions'][1]['action'] && 'legacy_identity' === $summary['actions'][1]['kind'] && ! isset( $summary['actions'][1]['operation_id'] ) && is_string( $summary_json ) && false === strpos( $summary_json, 'token' ) && false === strpos( $summary_json, 'api_key' ) && false === strpos( $summary_json, 'account' ), 'authorized summary exposes menu order, sync fields, and a version-zero attach' );
$names     = function ( $row ) { $found = array(); foreach ( $row['actions'] as $action ) { $found[] = $action['action'] . ( isset( $action['kind'] ) ? ':' . $action['kind'] : '' ); } return $found; };
$uncertain = TutorPress_Live_Lesson_Response_Projector::project_summary( $ordered, $topic, $course, $can, array_replace( $base, array( 'operation' => $operation ) ) );
$retry     = TutorPress_Live_Lesson_Response_Projector::project_summary( $ordered, $topic, $course, $can, array_replace( $base, array( 'provider_id' => 'evt-1', 'state' => 'error', 'operation' => array_replace( $operation, array( 'provider_id' => 'evt-1', 'kind' => 'update' ) ) ) ) );
$action_json = json_encode( array( $uncertain, $retry ) );
tutorpress_live_lessons_assert( is_array( $uncertain ) && array( 'inspect', 'attach:uncertain_create', 'abandon' ) === $names( $uncertain ) && 'op-1' === $uncertain['actions'][1]['operation_id'] && ! in_array( 'retry', $names( $uncertain ), true ), 'an id-less create offers inspect, attach, and abandon' );
tutorpress_live_lessons_assert( is_array( $retry ) && array( 'retry', 'inspect' ) === $names( $retry ) && ! isset( $retry['actions'][0]['provider_id'] ) && ! isset( $retry['actions'][1]['provider_id'] ) && is_string( $action_json ) && false === strpos( $action_json, 'token' ) && false === strpos( $action_json, 'api_key' ) && false === strpos( $action_json, 'account' ), 'a known pre-dispatch operation retries without a provider-id action' );
tutorpress_live_lessons_pass( 'authorized topic summary' );
$kept    = TutorPress_Live_Lesson_Response_Projector::persisted_result( $item, $topic, $course, $can, array_replace( $base, array( 'state' => 'synced' ) ), array( 'provider_id' => 'evt-1' ), array( 'persisted' => true ), 'schedule refreshed late' );
$hidden  = TutorPress_Live_Lesson_Response_Projector::persisted_result( $item, $topic, $course, $can, array_replace( $base, array( 'state' => 'synced' ) ), null, array( 'persisted' => true ), 'api_secret leaked' );
$failed  = TutorPress_Live_Lesson_Response_Projector::persisted_result( $item, $topic, $course, $can, $base, null, array( 'persisted' => false, 'status' => 500 ), null );
$failed_data = $failed instanceof WP_Error ? $failed->get_error_data() : null;
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Response_Projector::persisted_result( $item, $topic, $course, function () { return false; }, null, null, array( 'persisted' => true ), 'refresh' ), 'unauthorized persistence projects nothing' );
tutorpress_live_lessons_assert( is_array( $kept ) && true === $kept['persisted'] && 'synced' === $kept['sync_state'] && 'schedule refreshed late' === $kept['warning'] && true === $kept['refetch_required'] && ! ( $kept instanceof WP_Error ), 'refresh warning keeps a persisted success' );
tutorpress_live_lessons_assert( is_array( $hidden ) && 'refresh_failed' === $hidden['warning'] && true === $hidden['persisted'] && $failed instanceof WP_Error && is_array( $failed_data ) && false === $failed_data['persisted'] && 9 === $failed_data['projected_record']['id'] && 'pending' === $failed_data['sync_state'], 'a secret refresh warning is replaced and a failed persistence stays failed' );
tutorpress_live_lessons_pass( 'persisted outcome keeps precedence over refresh' );
$descriptor_clean = static function ( $summary ) {
	if ( ! is_array( $summary ) || ! isset( $summary['actions'] ) || ! is_array( $summary['actions'] ) ) { return false; }
	foreach ( $summary['actions'] as $action ) {
		if ( ! is_array( $action ) || array_key_exists( 'provider_id', $action ) ) { return false; }
	}
	$encoded = json_encode( $summary['actions'] );
	return is_string( $encoded ) && false === strpos( $encoded, 'provider_id' ) && false === strpos( $encoded, 'token' ) && false === strpos( $encoded, 'api_key' ) && false === strpos( $encoded, 'account' );
};
$known_only = TutorPress_Live_Lesson_Response_Projector::project_summary( $ordered, $topic, $course, $can, array_replace( $base, array( 'provider_id' => 'evt-1', 'state' => 'synced', 'operation' => array_replace( $operation, array( 'provider_id' => 'evt-1', 'kind' => 'update', 'dispatch_state' => 'confirmed' ) ) ) ) );
$retry_only = TutorPress_Live_Lesson_Response_Projector::project_summary( $ordered, $topic, $course, $can, array_replace( $base, array( 'provider_id' => null, 'operation' => array_replace( $operation, array( 'provider_id' => null, 'kind' => 'update', 'dispatch_state' => 'not_dispatched' ) ) ) ) );
tutorpress_live_lessons_assert( is_array( $summary ) && array( array( 'action' => 'resolve_owner', 'version' => 0 ), array( 'action' => 'attach', 'kind' => 'legacy_identity', 'version' => 0 ) ) === $summary['actions'] && $descriptor_clean( $summary ), 'an absent envelope offers resolve_owner and legacy attach at version 0' );
tutorpress_live_lessons_assert( is_array( $uncertain ) && array( array( 'action' => 'inspect', 'version' => 1, 'operation_id' => 'op-1' ), array( 'action' => 'attach', 'kind' => 'uncertain_create', 'version' => 1, 'operation_id' => 'op-1' ), array( 'action' => 'abandon', 'version' => 1, 'operation_id' => 'op-1' ) ) === $uncertain['actions'] && ! in_array( 'retry', $names( $uncertain ), true ) && $descriptor_clean( $uncertain ), 'an id-less create offers inspect, uncertain attach, and abandon, and does not offer retry' );
tutorpress_live_lessons_assert( is_array( $known_only ) && array( array( 'action' => 'inspect', 'version' => 1 ) ) === $known_only['actions'] && is_array( $retry_only ) && array( array( 'action' => 'retry', 'version' => 1, 'operation_id' => 'op-1' ) ) === $retry_only['actions'] && $descriptor_clean( $known_only ) && $descriptor_clean( $retry_only ) && $descriptor_clean( $retry ), 'a known provider id offers inspect, not_dispatched offers retry, and action descriptors have no provider-id field' );
