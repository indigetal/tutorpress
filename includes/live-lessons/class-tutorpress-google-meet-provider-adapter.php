<?php
/**
 * Dormant Google owner context.
 *
 * @package TutorPress
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class TutorPress_Google_Meet_Provider_Adapter implements TutorPress_Live_Lesson_Provider_Adapter {
	private $call;
	public function __construct( $ready, $current, $switch_to, $restore, $factory, $settings = null ) {
		$this->call = array( 'ready' => $ready, 'current' => $current, 'switch' => $switch_to, 'restore' => $restore, 'factory' => $factory, 'settings' => $settings );
	}
	public function preflight( $owner_id ): bool {
		return is_int( $owner_id ) && $owner_id > 0 && true === call_user_func( $this->call['ready'], $owner_id );
	}
	public function capabilities() {
		return array( 'settings' => true, 'hosts' => false, 'delete_convergence' => true );
	}
	public function list_hosts( $owner_id ) {
		return array( 'outcome' => 'not_applicable', 'hosts' => array() );
	}
	public function validate_host( $owner_id, $host_id ): bool {
		return false;
	}
	private function calendar( $owner_id ) {
		if ( true !== $this->preflight( $owner_id ) ) { return null; }
		$prior = call_user_func( $this->call['current'] );
		try { call_user_func( $this->call['switch'], $owner_id ); return call_user_func( $this->call['factory'] ); }
		finally { call_user_func( $this->call['restore'], $prior ); }
	}
	public function settings( $owner_id ) {
		if ( true !== $this->preflight( $owner_id ) || ! is_callable( $this->call['settings'] ) ) { return null; }
		$prior = call_user_func( $this->call['current'] );
		try { call_user_func( $this->call['switch'], $owner_id ); return $this->safe_settings( call_user_func( $this->call['settings'] ) ); }
		finally { call_user_func( $this->call['restore'], $prior ); }
	}
	private function safe_settings( $raw ) {
		if ( ! is_array( $raw ) ) { return null; }
		$settings = array();
		foreach ( ( isset( $raw['settings'] ) && is_array( $raw['settings'] ) ? $raw['settings'] : array() ) as $key => $row ) { if ( is_string( $key ) && ! $this->redacted( $key ) && is_array( $row ) ) { $settings[ $key ] = array( 'value' => $this->setting_part( isset( $row['value'] ) ? $row['value'] : null, true ), 'label' => $this->setting_part( isset( $row['label'] ) ? $row['label'] : null, false ), 'type' => $this->setting_part( isset( $row['type'] ) ? $row['type'] : null, false ), 'options' => $this->setting_options( isset( $row['options'] ) ? $row['options'] : null ) ); } }
		return array( 'is_authorized' => isset( $raw['is_authorized'] ) && true === $raw['is_authorized'], 'has_credentials' => isset( $raw['has_credentials'] ) && true === $raw['has_credentials'], 'settings' => $settings, 'authorization_url' => isset( $raw['authorization_url'] ) && is_string( $raw['authorization_url'] ) ? $raw['authorization_url'] : null, 'setup_url' => isset( $raw['setup_url'] ) && is_string( $raw['setup_url'] ) ? $raw['setup_url'] : null );
	}
	private function setting_part( $value, $scalar ) {
		if ( $scalar && ( is_bool( $value ) || is_int( $value ) ) ) { return $value; }
		if ( is_string( $value ) && ! $this->redacted( $value ) ) { return $value; }
		return $scalar ? null : '';
	}
	private function setting_options( $options ) {
		if ( ! is_array( $options ) ) { return null; }
		$kept = array();
		foreach ( $options as $key => $option ) { if ( ( ! is_string( $key ) || ! $this->redacted( $key ) ) && ( ( is_string( $option ) && ! $this->redacted( $option ) ) || is_int( $option ) || is_bool( $option ) ) ) { $kept[ $key ] = $option; } }
		return $kept;
	}
	private function redacted( $value ) { return is_string( $value ) && 1 === preg_match( '/token|secret|credential|password|api_key/i', $value ); }
	private function event_body( array $target, $zone, DateTimeImmutable $start, DateTimeImmutable $end ) {
		$minutes = isset( $target['reminder_minutes'] ) && is_int( $target['reminder_minutes'] ) ? $target['reminder_minutes'] : 30;
		return array( 'summary' => $this->meta_string( $target, 'title' ), 'description' => $this->meta_string( $target, 'content' ), 'start' => array( 'dateTime' => $start->format( DateTimeInterface::RFC3339 ), 'timeZone' => $zone ), 'end' => array( 'dateTime' => $end->format( DateTimeInterface::RFC3339 ), 'timeZone' => $zone ), 'attendees' => $this->request_attendees( $target ), 'reminders' => array( 'useDefault' => false, 'overrides' => array( array( 'method' => 'email', 'minutes' => $minutes ), array( 'method' => 'popup', 'minutes' => $minutes ) ) ), 'sendUpdates' => $this->request_string( $target, 'send_updates', 'all' ), 'transparency' => $this->request_string( $target, 'transparency', 'transparent' ), 'visibility' => $this->request_string( $target, 'visibility', 'public' ), 'status' => $this->request_string( $target, 'status', 'confirmed' ) );
	}
	private function request_string( array $target, $key, $default ) { return isset( $target[ $key ] ) && is_string( $target[ $key ] ) ? $target[ $key ] : $default; }
	private function request_attendees( array $target ) {
		$rows = array();
		foreach ( ( isset( $target['attendees'] ) && is_array( $target['attendees'] ) ? $target['attendees'] : array() ) as $row ) { if ( is_array( $row ) && isset( $row['displayName'], $row['email'] ) && is_string( $row['displayName'] ) && is_string( $row['email'] ) ) { $rows[] = array( 'displayName' => $row['displayName'], 'email' => $row['email'], 'responseStatus' => 'needsAction' ); } }
		return $rows;
	}
	public function create( $owner_id, array $target ) {
		$zone    = isset( $target['timezone'] ) ? $target['timezone'] : '';
		$start   = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $target['start_local'] ) ? $target['start_local'] : '', $zone );
		$end     = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $target['end_local'] ) ? $target['end_local'] : '', $zone );
		$service = ( $start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $end->getTimestamp() > $start->getTimestamp() ) ? $this->calendar( $owner_id ) : null;
		if ( ! is_object( $service ) ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) );
		}
		try {
			$body = $this->event_body( $target, $zone, $start, $end );
			$body['conferenceData'] = array( 'createRequest' => array( 'requestId' => $this->meta_string( $target, 'conference_request_id' ) ) );
			$created = $service->events->insert( $service->current_calendar, $body, array( 'conferenceDataVersion' => 1 ) );
		} catch ( Throwable $thrown ) {
			$created = $thrown;
		}
		return $this->classify_create( $created );
	}
	private function classify_create( $created ) {
		if ( is_object( $created ) && isset( $created->id ) && is_string( $created->id ) && '' !== $created->id ) {
			return $this->confirmed( 'create', $created->id, $created );
		}
		$code = is_object( $created ) && is_callable( array( $created, 'getCode' ) ) ? (int) $created->getCode() : ( is_array( $created ) && isset( $created['code'] ) && is_numeric( $created['code'] ) ? (int) $created['code'] : 0 );
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => ( $code >= 400 && $code <= 499 ) ? 'definite_rejection' : 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) );
	}
	public function fetch( $owner_id, $provider_id ) {
		$service = ( is_string( $provider_id ) && '' !== $provider_id ) ? $this->calendar( $owner_id ) : null;
		if ( ! is_object( $service ) ) { return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => is_string( $provider_id ) && '' !== $provider_id ? $provider_id : null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'fetch' ) ); }
		try { $fetched = $service->events->get( $service->current_calendar, $provider_id ); }
		catch ( Throwable $thrown ) { $fetched = $thrown; }
		$code = is_object( $fetched ) && is_callable( array( $fetched, 'getCode' ) ) ? (int) $fetched->getCode() : ( is_array( $fetched ) && isset( $fetched['code'] ) && is_numeric( $fetched['code'] ) ? (int) $fetched['code'] : 0 );
		if ( 404 === $code ) { return $this->absent( 'fetch', $provider_id ); }
		if ( is_object( $fetched ) && isset( $fetched->id ) && is_string( $fetched->id ) && $fetched->id === $provider_id ) { return $this->confirmed( 'fetch', $provider_id, $fetched ); }
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array(), 'provenance' => 'fetch' ) );
	}
	public function update( $owner_id, $provider_id, array $target ) {
		$zone    = isset( $target['timezone'] ) ? $target['timezone'] : '';
		$start   = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $target['start_local'] ) ? $target['start_local'] : '', $zone );
		$end     = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $target['end_local'] ) ? $target['end_local'] : '', $zone );
		$service = ( is_string( $provider_id ) && '' !== $provider_id && $start instanceof DateTimeImmutable && $end instanceof DateTimeImmutable && $end->getTimestamp() > $start->getTimestamp() ) ? $this->calendar( $owner_id ) : null;
		if ( ! is_object( $service ) ) { return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => is_string( $provider_id ) && '' !== $provider_id ? $provider_id : null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'update' ) ); }
		try { $updated = $service->events->update( $service->current_calendar, $provider_id, $this->event_body( $target, $zone, $start, $end ) ); }
		catch ( Throwable $thrown ) { $updated = $thrown; }
		return $this->classify_known( $updated, $provider_id, 'update' );
	}
	public function delete( $owner_id, $provider_id ) {
		$service = ( is_string( $provider_id ) && '' !== $provider_id ) ? $this->calendar( $owner_id ) : null;
		if ( ! is_object( $service ) ) { return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => is_string( $provider_id ) && '' !== $provider_id ? $provider_id : null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'delete' ) ); }
		try { $deleted = $service->events->delete( $service->current_calendar, $provider_id ); }
		catch ( Throwable $thrown ) { $deleted = $thrown; }
		if ( true === $deleted ) { return $this->closed( $provider_id ); }
		return $this->classify_known( $deleted, $provider_id, 'delete' );
	}
	private function confirmed( $provenance, $id, $event ) {
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => $id, 'item' => $this->event_item( $event ), 'diagnostics' => array(), 'provenance' => $provenance ) );
	}
	private function event_item( $event ) {
		$start = $this->event_bound( $event, 'start' );
		$end   = $this->event_bound( $event, 'end' );
		return array( 'start_at' => $start['at'], 'end_at' => $end['at'], 'timezone' => $start['zone'], 'duration_minutes' => $this->event_minutes( $start['at'], $end['at'] ), 'join_url' => $this->event_text( $event, 'hangoutLink' ), 'details' => $this->event_details( $event ) );
	}
	private function event_bound( $event, $key ) {
		$row = $this->event_row( is_object( $event ) && isset( $event->$key ) ? $event->$key : null );
		$at  = isset( $row['dateTime'] ) && is_string( $row['dateTime'] ) && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $row['dateTime'] ) ? $row['dateTime'] : null;
		return array( 'at' => $at, 'zone' => isset( $row['timeZone'] ) && is_string( $row['timeZone'] ) ? $row['timeZone'] : null );
	}
	private function event_minutes( $start, $end ) {
		if ( ! is_string( $start ) || ! is_string( $end ) ) { return null; }
		$minutes = (int) round( ( ( new DateTimeImmutable( $end ) )->getTimestamp() - ( new DateTimeImmutable( $start ) )->getTimestamp() ) / 60 );
		return $minutes > 0 ? $minutes : null;
	}
	private function event_text( $event, $key ) {
		return is_object( $event ) && isset( $event->$key ) && is_string( $event->$key ) ? $event->$key : null;
	}
	private function event_row( $raw ) {
		return is_object( $raw ) ? get_object_vars( $raw ) : ( is_array( $raw ) ? $raw : array() );
	}
	private function event_details( $event ) {
		return array( 'kind' => $this->event_text( $event, 'kind' ), 'event_type' => $this->event_text( $event, 'eventType' ), 'html_link' => $this->event_text( $event, 'htmlLink' ), 'organizer' => $this->event_organizer( $event ), 'recurrence' => $this->event_recurrence( $event ), 'reminders' => $this->event_reminders( $event ), 'status' => $this->event_text( $event, 'status' ), 'transparency' => $this->event_text( $event, 'transparency' ), 'visibility' => $this->event_text( $event, 'visibility' ), 'meet_link' => $this->event_text( $event, 'hangoutLink' ) );
	}
	private function event_organizer( $event ) {
		$row = $this->event_row( is_object( $event ) && isset( $event->organizer ) ? $event->organizer : null );
		return array( 'displayName' => isset( $row['displayName'] ) && is_string( $row['displayName'] ) ? $row['displayName'] : null, 'email' => isset( $row['email'] ) && is_string( $row['email'] ) ? $row['email'] : null, 'self' => isset( $row['self'] ) && is_bool( $row['self'] ) ? $row['self'] : null );
	}
	private function event_recurrence( $event ) {
		$rows = array();
		foreach ( ( is_object( $event ) && isset( $event->recurrence ) && is_array( $event->recurrence ) ? $event->recurrence : array() ) as $row ) { if ( is_string( $row ) ) { $rows[] = $row; } }
		return $rows;
	}
	private function event_reminders( $event ) {
		$row       = $this->event_row( is_object( $event ) && isset( $event->reminders ) ? $event->reminders : null );
		$overrides = array();
		foreach ( ( isset( $row['overrides'] ) && is_array( $row['overrides'] ) ? $row['overrides'] : array() ) as $item ) { $override = $this->event_row( $item ); if ( isset( $override['method'], $override['minutes'] ) && is_string( $override['method'] ) && is_int( $override['minutes'] ) ) { $overrides[] = array( 'method' => $override['method'], 'minutes' => $override['minutes'] ); } }
		return array( 'useDefault' => isset( $row['useDefault'] ) && is_bool( $row['useDefault'] ) ? $row['useDefault'] : false, 'overrides' => $overrides );
	}
	private function closed( $id ) {
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'confirmed', 'provider_id' => $id, 'item' => null, 'diagnostics' => array(), 'provenance' => 'delete' ) );
	}
	private function absent( $provenance, $id ) {
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'known_id_not_found', 'provider_id' => $id, 'item' => null, 'diagnostics' => array(), 'provenance' => $provenance ) );
	}
	private function classify_known( $result, $provider_id, $provenance ) {
		$code = is_object( $result ) && is_callable( array( $result, 'getCode' ) ) ? (int) $result->getCode() : ( is_array( $result ) && isset( $result['code'] ) && is_numeric( $result['code'] ) ? (int) $result['code'] : 0 );
		if ( 404 === $code ) { return $this->absent( $provenance, $provider_id ); }
		if ( 'delete' !== $provenance && is_object( $result ) && isset( $result->id ) && is_string( $result->id ) && $result->id === $provider_id ) { return $this->confirmed( $provenance, $provider_id, $result ); }
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array(), 'provenance' => $provenance ) );
	}
	public function compare_target( array $result, array $expected ) {
		$item   = isset( $result['item'] ) && is_array( $result['item'] ) ? $result['item'] : array();
		$fields = array( 'provider_id' => isset( $result['provider_id'] ) && is_string( $result['provider_id'] ) ? $result['provider_id'] : '', 'start_at' => isset( $item['start_at'] ) && is_string( $item['start_at'] ) ? $item['start_at'] : '', 'end_at' => isset( $item['end_at'] ) && is_string( $item['end_at'] ) ? $item['end_at'] : '', 'timezone' => isset( $item['timezone'] ) && is_string( $item['timezone'] ) ? $item['timezone'] : '' );
		$want   = array( 'provider_id' => $this->expected_id( $expected ), 'start_at' => $this->expected_instant( $expected, 'start_local' ), 'end_at' => $this->expected_instant( $expected, 'end_local' ), 'timezone' => isset( $expected['timezone'] ) && is_string( $expected['timezone'] ) ? $expected['timezone'] : '' );
		return array( 'match' => '' !== $fields['provider_id'] && $fields === $want, 'fields' => $fields );
	}
	private function expected_id( array $expected ) {
		foreach ( array( 'provider_id', 'id' ) as $key ) { if ( isset( $expected[ $key ] ) && is_string( $expected[ $key ] ) && '' !== $expected[ $key ] ) { return $expected[ $key ]; } }
		return '';
	}
	private function expected_instant( array $expected, $local ) {
		$zone   = isset( $expected['timezone'] ) && is_string( $expected['timezone'] ) ? $expected['timezone'] : '';
		$parsed = isset( $expected[ $local ] ) ? TutorPress_Live_Lesson_Datetime::parse_wall_clock( $expected[ $local ], $zone ) : null;
		return $parsed instanceof DateTimeImmutable ? $parsed->format( DateTimeInterface::RFC3339 ) : '';
	}
	public function metadata_map( array $result, array $target ) {
		$id      = TutorPress_Live_Lesson_Provider_Result::identity( $result );
		$item    = isset( $result['item'] ) && is_array( $result['item'] ) ? $result['item'] : array();
		$details = isset( $item['details'] ) && is_array( $item['details'] ) ? $item['details'] : array();
		$zone    = isset( $target['timezone'] ) && is_string( $target['timezone'] ) ? $target['timezone'] : '';
		$start   = isset( $target['start_local'] ) ? TutorPress_Live_Lesson_Datetime::parse_wall_clock( $target['start_local'], $zone ) : null;
		$end     = isset( $target['end_local'] ) ? TutorPress_Live_Lesson_Datetime::parse_wall_clock( $target['end_local'], $zone ) : null;
		if ( 'confirmed' !== ( isset( $result['outcome'] ) ? $result['outcome'] : null ) || null === $id || ! $start instanceof DateTimeImmutable || ! $end instanceof DateTimeImmutable ) { return null; }
		$link   = isset( $item['join_url'] ) && is_string( $item['join_url'] ) && '' !== $item['join_url'] ? $item['join_url'] : $this->meta_string( $details, 'meet_link' );
		$stored = array( 'id' => $id, 'kind' => $this->meta_string( $details, 'kind' ), 'event_type' => $this->meta_string( $details, 'event_type' ), 'html_link' => $this->meta_string( $details, 'html_link' ), 'organizer' => $this->map_organizer( $details ), 'recurrence' => $this->map_strings( $details, 'recurrence' ), 'reminders' => $this->map_reminders( $details ), 'status' => $this->meta_string( $details, 'status' ), 'transparency' => $this->meta_string( $details, 'transparency' ), 'visibility' => $this->meta_string( $details, 'visibility' ), 'meet_link' => $link, 'start_datetime' => $start->format( 'Y-m-d H:i:s' ), 'end_datetime' => $end->format( 'Y-m-d H:i:s' ), 'attendees' => '' !== $this->meta_string( $target, 'add_enrolled_students' ) ? $this->meta_string( $target, 'add_enrolled_students' ) : 'No', 'timezone' => $zone );
		return array( 'tutor-google-meet-start-datetime' => $stored['start_datetime'], 'tutor-google-meet-end-datetime' => $stored['end_datetime'], 'tutor-google-meet-event-details' => json_encode( $stored ), 'tutor-google-meet-link' => $link );
	}
	private function map_organizer( array $details ) {
		$row = isset( $details['organizer'] ) && is_array( $details['organizer'] ) ? $details['organizer'] : array();
		return array( 'displayName' => isset( $row['displayName'] ) && is_string( $row['displayName'] ) ? $row['displayName'] : null, 'email' => isset( $row['email'] ) && is_string( $row['email'] ) ? $row['email'] : null, 'self' => isset( $row['self'] ) && is_bool( $row['self'] ) ? $row['self'] : null );
	}
	private function map_strings( array $details, $key ) {
		$rows = array();
		foreach ( ( isset( $details[ $key ] ) && is_array( $details[ $key ] ) ? $details[ $key ] : array() ) as $row ) { if ( is_string( $row ) ) { $rows[] = $row; } }
		return $rows;
	}
	private function map_reminders( array $details ) {
		$row       = isset( $details['reminders'] ) && is_array( $details['reminders'] ) ? $details['reminders'] : array();
		$overrides = array();
		foreach ( ( isset( $row['overrides'] ) && is_array( $row['overrides'] ) ? $row['overrides'] : array() ) as $item ) { if ( is_array( $item ) && isset( $item['method'], $item['minutes'] ) && is_string( $item['method'] ) && is_int( $item['minutes'] ) ) { $overrides[] = array( 'method' => $item['method'], 'minutes' => $item['minutes'] ); } }
		return array( 'useDefault' => isset( $row['useDefault'] ) && is_bool( $row['useDefault'] ) ? $row['useDefault'] : false, 'overrides' => $overrides );
	}
	public function hydrate_item( array $local ) {
		$raw     = isset( $local['tutor-google-meet-event-details'] ) ? $local['tutor-google-meet-event-details'] : '';
		$decoded = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array() );
		$valid   = is_array( $decoded );
		$details = $valid ? $decoded : $this->details_array( $raw );
		$zone    = isset( $details['timezone'] ) && TutorPress_Live_Lesson_Datetime::is_approved_timezone( $details['timezone'] ) ? $details['timezone'] : 'UTC';
		return array(
			'start_local' => $this->google_wall( isset( $details['start_datetime'] ) ? $details['start_datetime'] : null, $this->meta_string( $local, 'tutor-google-meet-start-datetime' ) ),
			'end_local'   => $this->google_wall( isset( $details['end_datetime'] ) ? $details['end_datetime'] : null, $this->meta_string( $local, 'tutor-google-meet-end-datetime' ) ),
			'timezone'    => $zone,
			'provider_id'            => $valid ? ( isset( $details['id'] ) && is_string( $details['id'] ) && '' !== $details['id'] ? $details['id'] : null ) : $this->one_google_id( is_string( $raw ) ? $raw : '' ),
			'link'                   => $this->meta_string( $local, 'tutor-google-meet-link' ),
			'add_enrolled_students'  => $this->enrolled_choice( isset( $details['attendees'] ) ? $details['attendees'] : null ),
			'status'                 => $this->meta_string( $details, 'status' ),
			'transparency'           => $this->meta_string( $details, 'transparency' ),
			'visibility'             => $this->meta_string( $details, 'visibility' ),
			'reminder_minutes'       => $this->first_reminder( $details ),
		);
	}
	/** One raw quoted "id" from a blob string that did not decode. Empty quotes count, then lose. */
	private function one_google_id( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$found = array();
		if ( 1 !== preg_match_all( '/"id"\s*:\s*"([^"\\\\]*)"/', $raw, $found, PREG_SET_ORDER ) ) {
			return null;
		}
		$id = isset( $found[0][1] ) ? $found[0][1] : '';
		return '' !== $id ? $id : null;
	}
	/** Yes or No. Any other attendees value, including a missing one, stays empty. */
	private function enrolled_choice( $value ) {
		return is_string( $value ) && ( 'Yes' === $value || 'No' === $value ) ? $value : '';
	}
	/** The first override's integer minutes. A string is not cast, and a later override is ignored. */
	private function first_reminder( array $details ) {
		$row       = isset( $details['reminders'] ) && is_array( $details['reminders'] ) ? $details['reminders'] : array();
		$overrides = isset( $row['overrides'] ) && is_array( $row['overrides'] ) ? $row['overrides'] : array();
		$first     = isset( $overrides[0] ) && is_array( $overrides[0] ) ? $overrides[0] : array();
		return isset( $first['minutes'] ) && is_int( $first['minutes'] ) ? $first['minutes'] : null;
	}
	/** A JSON Y-m-d H:i:s clock wins. Any other JSON value uses the scalar. */
	private function google_wall( $json, $scalar ) {
		if ( is_string( $json ) ) {
			$clock = $this->truncate_wall( $json );
			if ( null !== $clock ) {
				return $clock;
			}
		}
		if ( ! is_string( $scalar ) ) {
			return '';
		}
		$clock = $this->truncate_wall( $scalar );
		return null !== $clock ? $clock : $scalar;
	}
	/** Rewrite a Y-m-d H:i:s clock to :00. A T or an offset is not a clock. */
	private function truncate_wall( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}):[0-9]{2}\z/', $value, $match ) ? $match[1] . ':00' : null;
	}
	public function hydrate_legacy( array $local ) {
		return array( 'provider_id' => $this->hydrate_item( $local )['provider_id'] );
	}
	private function details_array( $raw ) {
		$details = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array() );
		return is_array( $details ) ? $details : array();
	}
	private function meta_string( array $source, $key, $alt = '' ) {
		if ( isset( $source[ $key ] ) && is_string( $source[ $key ] ) ) { return $source[ $key ]; }
		return '' !== $alt && isset( $source[ $alt ] ) && is_string( $source[ $alt ] ) ? $source[ $alt ] : '';
	}
}
