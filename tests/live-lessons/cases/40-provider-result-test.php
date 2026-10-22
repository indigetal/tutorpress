<?php

$confirmed_item = array( 'start_at' => '2026-09-22T15:00:00-04:00', 'end_at' => '2026-09-22T16:00:00Z', 'timezone' => 'America/New_York', 'duration_minutes' => 60, 'join_url' => null, 'details' => array() );
$confirmed = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => $confirmed_item, 'diagnostics' => array( 'body' => 'raw' ), 'provenance' => 'create' ) );
tutorpress_live_lessons_assert( is_array( $confirmed ) && 'confirmed' === $confirmed['outcome'] && 'id-1' === $confirmed['provider_id'] && 'id-1' === TutorPress_Live_Lesson_Provider_Result::identity( $confirmed ) && array() === $confirmed['diagnostics'] && 'create' === $confirmed['provenance'] && null === $confirmed['item']['join_url'], 'confirmed keeps a top-level provider ID and drops unsanitized diagnostics' );
$rejected = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'definite_rejection', 'provider_id' => null, 'item' => null, 'diagnostics' => array( 'error' => 'rejected' ), 'provenance' => 'update' ) );
tutorpress_live_lessons_assert( 'definite_rejection' === $rejected['outcome'] && null === $rejected['provider_id'] && null === $rejected['item'] && 'rejected' === $rejected['diagnostics']['error'] && 'update' === $rejected['provenance'], 'definite rejection stays definite rejection' );
$missing = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'known_id_not_found', 'provider_id' => 'id-1', 'item' => null, 'diagnostics' => array(), 'provenance' => 'fetch' ) );
tutorpress_live_lessons_assert( 'known_id_not_found' === $missing['outcome'] && 'id-1' === $missing['provider_id'] && null === $missing['item'], 'known-ID not-found stays known-ID not-found' );
$early = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => null, 'item' => null, 'diagnostics' => array( 'warning' => 'stopped' ), 'provenance' => 'create' ) );
tutorpress_live_lessons_assert( 'pre_dispatch_failure' === $early['outcome'] && null === $early['item'] && 'stopped' === $early['diagnostics']['warning'], 'pre-dispatch failure stays pre-dispatch failure' );
$unknown = TutorPress_Live_Lesson_Provider_Result::from( null );
tutorpress_live_lessons_assert( null === $unknown && null === TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array( 'provider_id' => 'id-1', 'error' => 'later' ), 'provenance' => 'fetch' ) ), 'a non-array call and diagnostics identity are rejected' );
$shapes    = TutorPress_Live_Lessons_Boundary_Fake::provider_shapes();
$event     = $shapes['google_event'];
tutorpress_live_lessons_assert( is_object( $event ) && true === $shapes['google_response'] && $event !== $shapes['google_response'] && 'evt-1' === $event->id && 'https://meet.example/a' === $event->hangoutLink && 'https://calendar.example/a' === $event->htmlLink && 'calendar#event' === $event->kind && 'default' === $event->eventType && 'owner' === $event->organizer && array() === $event->recurrence && array() === $event->reminders && 'confirmed' === $event->status && 'opaque' === $event->transparency && 'default' === $event->visibility, 'google event keeps its public fields and the delete response is not that event' );
tutorpress_live_lessons_assert( array( 'code' => 404 ) === $shapes['google_service_exception'] && 'connect_exception' === $shapes['google_network'], 'service exception code is 404 and the network marker has no status' );
$rejected  = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'definite_rejection', 'provider_id' => null, 'item' => null, 'diagnostics' => array( 'error' => 'rejected' ), 'provenance' => 'update' ) );
$empty     = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'fetch' ) );
$malformed = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) );
tutorpress_live_lessons_assert( 'definite_rejection' === $rejected['outcome'] && array( 'message' => 'Invalid meeting' ) === $shapes['zoom_error'], 'recognizable message array rejects and stores no discarded status' );
tutorpress_live_lessons_assert( 'uncertain' === $empty['outcome'] && 'uncertain' === $malformed['outcome'] && array() === $shapes['zoom_empty'] && null === $shapes['zoom_malformed'] && false === $shapes['zoom_token'] && 'z-1' === $shapes['zoom_success']['id'] && 201 === $shapes['zoom_success']['code'] && 'server_exception' === $shapes['zoom_5xx'] && 'connect_exception' === $shapes['zoom_transport'] && ! is_array( $shapes['zoom_5xx'] ) && ! is_array( $shapes['zoom_transport'] ), 'empty and malformed values stay uncertain and zoom markers store no outcome' );
$params  = array();
$returns = array();
foreach ( ( new ReflectionClass( 'TutorPress_Live_Lesson_Provider_Adapter' ) )->getMethods() as $method ) {
	$params[ $method->getName() ]  = array_map( function ( $param ) { return $param->getName(); }, $method->getParameters() );
	$returns[ $method->getName() ] = $method->hasReturnType() ? (string) $method->getReturnType() : '';
}
$expected = array( 'capabilities' => array(), 'preflight' => array( 'owner_id' ), 'settings' => array( 'owner_id' ), 'list_hosts' => array( 'owner_id' ), 'validate_host' => array( 'owner_id', 'host_id' ), 'create' => array( 'owner_id', 'target' ), 'fetch' => array( 'owner_id', 'provider_id' ), 'update' => array( 'owner_id', 'provider_id', 'target' ), 'delete' => array( 'owner_id', 'provider_id' ), 'compare_target' => array( 'result', 'expected' ), 'metadata_map' => array( 'result', 'target' ), 'hydrate_legacy' => array( 'local' ), 'hydrate_item' => array( 'local' ) );
tutorpress_live_lessons_assert( $expected === $params && 'bool' === $returns['preflight'] && 'bool' === $returns['validate_host'] && ! isset( $params['extract_identity'] ) && ! isset( $params['persist_metadata'] ), 'the generic interface uses the exact owner and provider-ID parameter counts' );
tutorpress_live_lessons_assert( false === strpos( implode( ' ', array_keys( $params ) ), 'google' ) && false === strpos( implode( ' ', array_keys( $params ) ), 'zoom' ) && false === strpos( implode( ' ', array_keys( $params ) ), 'http' ) && false === strpos( implode( ' ', array_keys( $params ) ), 'credential' ) && ! in_array( 'provider_id', $params['create'], true ) && in_array( 'provider_id', $params['fetch'], true ) && in_array( 'provider_id', $params['update'], true ) && in_array( 'provider_id', $params['delete'], true ), 'create has no provider ID and fetch, update, and delete require the server-owned ID' );
$schedule = array( 'start_at' => '2026-09-22T15:00:00-04:00', 'end_at' => '2026-09-22T16:00:00Z', 'timezone' => 'America/New_York', 'duration_minutes' => 60, 'join_url' => null, 'details' => array( 'kind' => 'calendar#event', 'flags' => array( true, null ) ) );
$normalized = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => $schedule, 'diagnostics' => array( 'error' => 'kept', 'warning' => 'noted', 'body' => 'raw' ), 'provenance' => 'create' ) );
tutorpress_live_lessons_assert( is_array( $normalized ) && 'id-1' === $normalized['provider_id'] && 'id-1' === TutorPress_Live_Lesson_Provider_Result::identity( $normalized ) && ! isset( $normalized['diagnostics']['provider_id'] ) && ! isset( $normalized['diagnostics']['body'] ) && 'kept' === $normalized['diagnostics']['error'] && 'noted' === $normalized['diagnostics']['warning'] && array( 'start_at', 'end_at', 'timezone', 'duration_minutes', 'join_url', 'details' ) === array_keys( $normalized['item'] ) && 'id-1' === TutorPress_Live_Lesson_Provider_Result::identity( $confirmed ), 'normalized identity is top-level exactly once and retains no raw body' );
$empty_item = array( 'start_at' => null, 'end_at' => null, 'timezone' => null, 'duration_minutes' => null, 'join_url' => null, 'details' => array() );
$nullable = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => $empty_item, 'diagnostics' => array(), 'provenance' => 'update' ) );
$deleted = TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => null, 'diagnostics' => array(), 'provenance' => 'delete' ) );
tutorpress_live_lessons_assert( is_array( $nullable ) && $empty_item === $nullable['item'] && is_array( $deleted ) && null === $deleted['item'], 'nullable confirmed fields stay null and delete has no item' );
$unsafe_rejected = true;
foreach ( array( 'raw_request', 'raw_response', 'exception', 'credential', 'access_token', 'headers', 'cookie' ) as $unsafe_key ) {
	$unsafe_rejected = $unsafe_rejected && null === TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array( $unsafe_key => 'hidden' ), 'provenance' => 'fetch' ) );
}
$bad_instant = $schedule;
$bad_instant['start_at'] = '2026-09-22 15:00:00';
$extra_item = $schedule;
$extra_item['id'] = 'evt-body';
$object_details = $schedule;
$object_details['details'] = array( 'event' => new stdClass() );
tutorpress_live_lessons_assert( $unsafe_rejected && null === TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => $bad_instant, 'diagnostics' => array(), 'provenance' => 'create' ) ) && null === TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => $extra_item, 'diagnostics' => array(), 'provenance' => 'fetch' ) ) && null === TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => $object_details, 'diagnostics' => array(), 'provenance' => 'fetch' ) ) && null === TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => $schedule, 'diagnostics' => array( 'error' => 12 ), 'provenance' => 'zoom' ) ) && null === TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'dispatch', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) ), 'normalized path rejects unsafe keys, bad instants, extra item fields, objects, and unknown provenance' );
tutorpress_live_lessons_pass( 'provider result outcomes' );
$surface = array();
foreach ( ( new ReflectionClass( 'TutorPress_Live_Lesson_Provider_Adapter' ) )->getMethods() as $method ) {
	$surface[ $method->getName() ] = array( $method->getNumberOfRequiredParameters(), $method->hasReturnType() ? (string) $method->getReturnType() : '' );
}
ksort( $surface );
$implementations = array( 'TutorPress_Google_Meet_Provider_Adapter', 'TutorPress_Zoom_Provider_Adapter', 'TutorPress_Live_Lessons_Adapter_Fake' );
$matched = true;
foreach ( $implementations as $class_name ) {
	$public = array();
	foreach ( ( new ReflectionClass( $class_name ) )->getMethods( ReflectionMethod::IS_PUBLIC ) as $method ) {
		if ( '__construct' === $method->getName() ) { continue; }
		$public[ $method->getName() ] = array( $method->getNumberOfRequiredParameters(), $method->hasReturnType() ? (string) $method->getReturnType() : '' );
	}
	ksort( $public );
	$matched = $matched && array( 'TutorPress_Live_Lesson_Provider_Adapter' => 'TutorPress_Live_Lesson_Provider_Adapter' ) === class_implements( $class_name ) && $surface === $public && ! method_exists( $class_name, 'persist_metadata' ) && ! method_exists( $class_name, 'extract_identity' );
}
$from = new ReflectionMethod( 'TutorPress_Live_Lesson_Provider_Result', 'from' );
tutorpress_live_lessons_assert( $matched && array( 'capabilities', 'compare_target', 'create', 'delete', 'fetch', 'hydrate_item', 'hydrate_legacy', 'list_hosts', 'metadata_map', 'preflight', 'settings', 'update', 'validate_host' ) === array_keys( $surface ) && 2 === $surface['create'][0] && 2 === $surface['fetch'][0] && 3 === $surface['update'][0] && 2 === $surface['delete'][0] && 'bool' === $surface['preflight'][1] && 'bool' === $surface['validate_host'][1] && ! in_array( 'provider_id', $params['create'], true ) && in_array( 'provider_id', $params['fetch'], true ) && 1 === $from->getNumberOfRequiredParameters() && 1 === $from->getNumberOfParameters(), 'concrete adapters and the fake match the interface surface' );
tutorpress_live_lessons_pass( 'provider reflection matrix' );
