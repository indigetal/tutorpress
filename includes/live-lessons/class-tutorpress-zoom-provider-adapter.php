<?php
/**
 * Dormant Zoom owner context and meeting operations.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Owner-scoped Meetings and Users calls. Credential strings stay in the injected reader. */
class TutorPress_Zoom_Provider_Adapter implements TutorPress_Live_Lesson_Provider_Adapter {
	/** Injected current, switch, restore, credentials, token, meetings, and users callables. */
	private $call;

	/** Store the owner-context and client factories. No Zoom client is constructed here. */
	public function __construct( $current, $switch_to, $restore, $credentials, $token, $meetings, $users ) {
		$this->call = array(
			'current'     => $current,
			'switch'      => $switch_to,
			'restore'     => $restore,
			'credentials' => $credentials,
			'token'       => $token,
			'meetings'    => $meetings,
			'users'       => $users,
		);
	}

	/** Return true only when account_id, api_key, and api_secret are non-empty strings. */
	public function preflight( $owner_id ): bool {
		if ( ! is_int( $owner_id ) || $owner_id < 1 || ! is_callable( $this->call['credentials'] ) ) {
			return false;
		}
		$credentials = call_user_func( $this->call['credentials'] );
		if ( ! is_array( $credentials ) ) {
			return false;
		}
		foreach ( array( 'account_id', 'api_key', 'api_secret' ) as $key ) {
			if ( ! isset( $credentials[ $key ] ) || ! is_string( $credentials[ $key ] ) || '' === $credentials[ $key ] ) {
				return false;
			}
		}
		return true;
	}

	/** Zoom has no settings surface and cannot prove delete convergence. */
	public function capabilities() {
		return array(
			'settings'           => false,
			'hosts'              => true,
			'delete_convergence' => false,
		);
	}

	/** Zoom settings are not applicable. */
	public function settings( $owner_id ) {
		return null;
	}

	/** Return the Meetings client after preflight and a token result of exactly true. */
	private function meetings( $owner_id ) {
		return $this->in_context( $owner_id, 'meetings', null );
	}

	/** Return one owner account's host rows from Users.userlist. */
	public function list_hosts( $owner_id ) {
		if ( true !== $this->preflight( $owner_id ) ) {
			return array(
				'outcome' => 'pre_dispatch_failure',
				'hosts'   => array(),
			);
		}
		$prior = call_user_func( $this->call['current'] );
		try {
			call_user_func( $this->call['switch'], $owner_id );
			if ( true !== call_user_func( $this->call['token'] ) ) {
				return array(
					'outcome' => 'pre_dispatch_failure',
					'hosts'   => array(),
				);
			}
			$client = call_user_func( $this->call['users'] );
			return $this->host_rows( $client->userlist( array( 'page_size' => 2000 ) ) );
		} finally {
			call_user_func( $this->call['restore'], $prior );
		}
	}

	/** Return true only when the host id is on a fresh owner-scoped host list. */
	public function validate_host( $owner_id, $host_id ): bool {
		if ( ! is_string( $host_id ) || '' === $host_id ) {
			return false;
		}
		$list = $this->list_hosts( $owner_id );
		if ( ! is_array( $list ) || ! isset( $list['outcome'], $list['hosts'] ) || 'hosts' !== $list['outcome'] || ! is_array( $list['hosts'] ) ) {
			return false;
		}
		foreach ( $list['hosts'] as $row ) {
			if ( is_array( $row ) && isset( $row['id'] ) && $row['id'] === $host_id ) {
				return true;
			}
		}
		return false;
	}

	/** Create with offset-less start_time, timezone, and duration minutes. */
	public function create( $owner_id, array $target ) {
		$zone     = isset( $target['timezone'] ) ? $target['timezone'] : '';
		$start    = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $target['start_local'] ) ? $target['start_local'] : '', $zone );
		$duration = null;
		if ( isset( $target['duration'] ) && is_array( $target['duration'] ) ) {
			$duration = TutorPress_Live_Lesson_Datetime::zoom_duration(
				isset( $target['duration']['value'] ) ? $target['duration']['value'] : null,
				isset( $target['duration']['unit'] ) ? $target['duration']['unit'] : ''
			);
		}
		$host = '';
		if ( isset( $target['provider_target'] ) && is_array( $target['provider_target'] ) && isset( $target['provider_target']['host_id'] ) && is_string( $target['provider_target']['host_id'] ) ) {
			$host = $target['provider_target']['host_id'];
		}
		if ( ! ( $start instanceof DateTimeImmutable ) || ! is_array( $duration ) || '' === $host ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) );
		}
		$client = $this->meetings( $owner_id );
		if ( ! is_object( $client ) ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) );
		}
		try {
			$created = $client->create( $host, $this->meeting_body( $target, $start, $duration ) );
		} catch ( Throwable $thrown ) {
			$created = $thrown;
		}
		return $this->classify_create( $created, $start, $zone, $duration );
	}

	/** Meetings body. Password is included only as a non-empty string. */
	private function meeting_body( array $target, DateTimeImmutable $start, array $duration ) {
		$provider = isset( $target['provider_target'] ) && is_array( $target['provider_target'] ) ? $target['provider_target'] : array();
		$password = isset( $provider['password'] ) && is_string( $provider['password'] ) ? $provider['password'] : '';
		$body     = array(
			'topic'      => isset( $target['title'] ) && is_string( $target['title'] ) ? $target['title'] : '',
			'type'       => 2,
			'start_time' => $start->format( 'Y-m-d\TH:i:s' ),
			'timezone'   => isset( $target['timezone'] ) && is_string( $target['timezone'] ) ? $target['timezone'] : '',
			'duration'   => 'hours' === $duration['unit'] ? $duration['value'] * 60 : $duration['value'],
		);
		if ( '' !== $password ) {
			$body['password'] = $password;
		}
		$body['settings'] = array(
			'join_before_host'  => isset( $provider['join_before_host'] ) && true === $provider['join_before_host'],
			'host_video'        => false,
			'participant_video' => false,
			'mute_upon_entry'   => false,
			'auto_recording'    => isset( $provider['auto_recording'] ) && is_string( $provider['auto_recording'] ) && '' !== $provider['auto_recording'] ? $provider['auto_recording'] : 'none',
			'enforce_login'     => false,
			'waiting_room'      => isset( $provider['waiting_room'] ) && true === $provider['waiting_room'],
		);
		return $body;
	}

	/** A non-empty message rejects. A usable id confirms. Wrapper code is ignored. */
	private function classify_create( $created, DateTimeImmutable $start, $timezone, array $duration ) {
		if ( is_array( $created ) && isset( $created['message'] ) && is_string( $created['message'] ) && '' !== $created['message'] ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'definite_rejection', 'provider_id' => null, 'item' => null, 'diagnostics' => array( 'error' => $created['message'] ), 'provenance' => 'create' ) );
		}
		$id = null;
		if ( is_array( $created ) && isset( $created['id'] ) && is_string( $created['id'] ) && '' !== $created['id'] ) {
			$id = $created['id'];
		} elseif ( is_array( $created ) && isset( $created['id'] ) && is_int( $created['id'] ) && $created['id'] > 0 ) {
			$id = (string) $created['id'];
		}
		if ( null === $id ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) );
		}
		$row = is_array( $created ) ? $created : array();
		return $this->confirmed( 'create', $id, $row, $this->create_item( $start, $timezone, $duration, $row ) );
	}

	/** Fetch the stored meeting id. Confirm only when the returned id string matches that id. */
	public function fetch( $owner_id, $provider_id ) {
		$client = is_string( $provider_id ) && '' !== $provider_id ? $this->meetings( $owner_id ) : null;
		if ( ! is_object( $client ) ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => is_string( $provider_id ) && '' !== $provider_id ? $provider_id : null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'fetch' ) );
		}
		try {
			$fetched = $client->meeting( $provider_id );
		} catch ( Throwable $thrown ) {
			$fetched = $thrown;
		}
		return $this->classify_fetch( $fetched, $provider_id );
	}

	/** Update the stored meeting id, then classify the following meeting result. */
	public function update( $owner_id, $provider_id, array $target ) {
		$zone     = isset( $target['timezone'] ) ? $target['timezone'] : '';
		$start    = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $target['start_local'] ) ? $target['start_local'] : '', $zone );
		$duration = null;
		if ( isset( $target['duration'] ) && is_array( $target['duration'] ) ) {
			$duration = TutorPress_Live_Lesson_Datetime::zoom_duration(
				isset( $target['duration']['value'] ) ? $target['duration']['value'] : null,
				isset( $target['duration']['unit'] ) ? $target['duration']['unit'] : ''
			);
		}
		if ( ! is_string( $provider_id ) || '' === $provider_id || ! ( $start instanceof DateTimeImmutable ) || ! is_array( $duration ) ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => is_string( $provider_id ) && '' !== $provider_id ? $provider_id : null, 'item' => null, 'diagnostics' => array(), 'provenance' => 'update' ) );
		}
		$client = $this->meetings( $owner_id );
		if ( ! is_object( $client ) ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'pre_dispatch_failure', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array(), 'provenance' => 'update' ) );
		}
		try {
			$client->update( $provider_id, $this->meeting_body( $target, $start, $duration ) );
		} catch ( Throwable $thrown ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array(), 'provenance' => 'update' ) );
		}
		try {
			$fetched = $client->meeting( $provider_id );
		} catch ( Throwable $thrown ) {
			$fetched = $thrown;
		}
		return $this->classify_known( $fetched, $provider_id );
	}

	/** Zoom removal cannot prove convergence, so it never constructs Meetings or calls remove. */
	public function delete( $owner_id, $provider_id ) {
		return TutorPress_Live_Lesson_Provider_Result::from(
			array(
				'outcome'     => 'pre_dispatch_failure',
				'provider_id' => is_string( $provider_id ) && '' !== $provider_id ? $provider_id : null,
				'item'        => null,
				'diagnostics' => array( 'error' => 'Zoom delete is unsupported before dispatch.' ),
				'provenance'  => 'delete',
			)
		);
	}

	/** Compare a normalized result with canonical expected identity, instant, zone, and minutes. */
	public function compare_target( array $result, array $expected ) {
		$item   = isset( $result['item'] ) && is_array( $result['item'] ) ? $result['item'] : array();
		$fields = array(
			'provider_id'      => isset( $result['provider_id'] ) && is_string( $result['provider_id'] ) ? $result['provider_id'] : '',
			'start_at'         => isset( $item['start_at'] ) && is_string( $item['start_at'] ) ? $item['start_at'] : '',
			'timezone'         => isset( $item['timezone'] ) && is_string( $item['timezone'] ) ? $item['timezone'] : '',
			'duration_minutes' => isset( $item['duration_minutes'] ) && is_int( $item['duration_minutes'] ) ? $item['duration_minutes'] : 0,
		);
		$want   = array(
			'provider_id'      => $this->expected_id( $expected ),
			'start_at'         => $this->expected_instant( $expected ),
			'timezone'         => isset( $expected['timezone'] ) && is_string( $expected['timezone'] ) ? $expected['timezone'] : '',
			'duration_minutes' => $this->expected_minutes( $expected ),
		);
		$match  = '' !== $fields['provider_id'] && '' !== $fields['start_at'] && '' !== $fields['timezone'] && $fields['duration_minutes'] > 0 && $fields === $want;
		return array(
			'match'  => $match,
			'fields' => $fields,
		);
	}

	/** A message rejects before id binding. A different or missing id stays uncertain. */
	private function classify_known( $result, $provider_id ) {
		if ( is_array( $result ) && isset( $result['message'] ) && is_string( $result['message'] ) && '' !== $result['message'] ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'definite_rejection', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array( 'error' => $result['message'] ), 'provenance' => 'update' ) );
		}
		$id = '';
		if ( is_array( $result ) && isset( $result['id'] ) && is_string( $result['id'] ) && '' !== $result['id'] ) {
			$id = $result['id'];
		} elseif ( is_array( $result ) && isset( $result['id'] ) && is_int( $result['id'] ) && $result['id'] > 0 ) {
			$id = (string) $result['id'];
		}
		if ( '' !== $id && $id === $provider_id ) {
			return $this->confirmed( 'update', $id, is_array( $result ) ? $result : array() );
		}
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array(), 'provenance' => 'update' ) );
	}

	/** Confirmed create or fetch keeps only the safe schedule item. */
	private function confirmed( $provenance, $id, array $row, $item = null ) {
		return TutorPress_Live_Lesson_Provider_Result::from(
			array(
				'outcome'     => 'confirmed',
				'provider_id' => $id,
				'item'        => is_array( $item ) ? $item : $this->meeting_item( $row ),
				'diagnostics' => array(),
				'provenance'  => $provenance,
			)
		);
	}

	/** Confirmed create schedule comes only from the authorized target. */
	private function create_item( DateTimeImmutable $start, $timezone, array $duration, array $row ) {
		$minutes = 'hours' === $duration['unit'] ? $duration['value'] * 60 : $duration['value'];
		$end     = $start->modify( '+' . $minutes . ' minutes' );
		return array(
			'start_at'         => $start->format( DateTimeInterface::RFC3339 ),
			'end_at'           => $end instanceof DateTimeImmutable ? $end->format( DateTimeInterface::RFC3339 ) : null,
			'timezone'         => is_string( $timezone ) ? $timezone : '',
			'duration_minutes' => $minutes,
			'join_url'         => isset( $row['join_url'] ) && is_string( $row['join_url'] ) && '' !== $row['join_url'] ? $row['join_url'] : null,
			'details'          => array(),
		);
	}

	/** Fetch confirms only a matching id. A message rejects. Other shapes stay uncertain. */
	private function classify_fetch( $result, $provider_id ) {
		if ( is_array( $result ) && isset( $result['message'] ) && is_string( $result['message'] ) && '' !== $result['message'] ) {
			return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'definite_rejection', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array( 'error' => $result['message'] ), 'provenance' => 'fetch' ) );
		}
		$id = $this->meeting_id( $result );
		if ( '' !== $id && $id === $provider_id ) {
			return $this->confirmed( 'fetch', $id, $result );
		}
		return TutorPress_Live_Lesson_Provider_Result::from( array( 'outcome' => 'uncertain', 'provider_id' => $provider_id, 'item' => null, 'diagnostics' => array(), 'provenance' => 'fetch' ) );
	}

	/** Selected-zone RFC3339 start, derived end, minutes, and join URL. Details stay empty. */
	private function meeting_item( array $row ) {
		$zone    = isset( $row['timezone'] ) && is_string( $row['timezone'] ) && '' !== $row['timezone'] ? $row['timezone'] : null;
		$raw     = isset( $row['start_time'] ) && is_string( $row['start_time'] ) ? str_replace( 'T', ' ', $row['start_time'] ) : '';
		$start   = null !== $zone && '' !== $raw ? TutorPress_Live_Lesson_Datetime::parse_wall_clock( $raw, $zone ) : null;
		$start_at = $start instanceof DateTimeImmutable ? $start->format( DateTimeInterface::RFC3339 ) : null;
		$minutes = isset( $row['duration'] ) && is_int( $row['duration'] ) && $row['duration'] > 0 ? $row['duration'] : null;
		$end_at  = $start instanceof DateTimeImmutable && null !== $minutes ? $start->modify( '+' . $minutes . ' minutes' )->format( DateTimeInterface::RFC3339 ) : null;
		return array(
			'start_at'         => $start_at,
			'end_at'           => $end_at,
			'timezone'         => $zone,
			'duration_minutes' => $minutes,
			'join_url'         => isset( $row['join_url'] ) && is_string( $row['join_url'] ) && '' !== $row['join_url'] ? $row['join_url'] : null,
			'details'          => array(),
		);
	}

	/** Read a string id or a positive integer id from a meeting payload. */
	private function meeting_id( $row ) {
		if ( is_array( $row ) && isset( $row['id'] ) && is_string( $row['id'] ) && '' !== $row['id'] ) {
			return $row['id'];
		}
		if ( is_array( $row ) && isset( $row['id'] ) && is_int( $row['id'] ) && $row['id'] > 0 ) {
			return (string) $row['id'];
		}
		return '';
	}

	/** Expected identity is provider_id, then legacy id. meeting_id is never identity. */
	private function expected_id( array $expected ) {
		foreach ( array( 'provider_id', 'id' ) as $key ) {
			if ( ! isset( $expected[ $key ] ) ) {
				continue;
			}
			$value = $expected[ $key ];
			if ( ( is_string( $value ) && '' !== $value ) || ( is_int( $value ) && $value > 0 ) ) {
				return (string) $value;
			}
		}
		return '';
	}

	/** Canonical expected start is the selected-zone wall clock as RFC3339. */
	private function expected_instant( array $expected ) {
		$zone   = isset( $expected['timezone'] ) && is_string( $expected['timezone'] ) ? $expected['timezone'] : '';
		$parsed = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $expected['start_local'] ) ? $expected['start_local'] : '', $zone );
		return $parsed instanceof DateTimeImmutable ? $parsed->format( DateTimeInterface::RFC3339 ) : '';
	}

	/** Canonical expected minutes come from a positive integer or a typed duration. */
	private function expected_minutes( array $expected ) {
		if ( isset( $expected['duration'] ) && is_int( $expected['duration'] ) && $expected['duration'] > 0 ) {
			return $expected['duration'];
		}
		if ( isset( $expected['duration'] ) && is_array( $expected['duration'] ) ) {
			$typed = TutorPress_Live_Lesson_Datetime::zoom_duration(
				isset( $expected['duration']['value'] ) ? $expected['duration']['value'] : null,
				isset( $expected['duration']['unit'] ) ? $expected['duration']['unit'] : ''
			);
			if ( is_array( $typed ) ) {
				return 'hours' === $typed['unit'] ? $typed['value'] * 60 : $typed['value'];
			}
		}
		return 0;
	}

	/** Switch, require a token result of true, then run the named factory. Restore the prior user in finally. */
	private function in_context( $owner_id, $factory, $failure ) {
		if ( true !== $this->preflight( $owner_id ) ) {
			return null;
		}
		$prior = call_user_func( $this->call['current'] );
		try {
			call_user_func( $this->call['switch'], $owner_id );
			if ( true !== call_user_func( $this->call['token'] ) ) {
				return $failure;
			}
			return call_user_func( $this->call[ $factory ] );
		} finally {
			call_user_func( $this->call['restore'], $prior );
		}
	}

	/** Keep rows with a non-empty string id and email. Name is the trimmed first and last name. */
	private function host_rows( $list ) {
		if ( is_array( $list ) && isset( $list['message'] ) && is_string( $list['message'] ) && '' !== $list['message'] ) {
			return array(
				'outcome' => 'definite_rejection',
				'hosts'   => array(),
			);
		}
		if ( ! is_array( $list ) || ! array_key_exists( 'users', $list ) || ! is_array( $list['users'] ) ) {
			return array(
				'outcome' => 'uncertain',
				'hosts'   => array(),
			);
		}
		$hosts = array();
		foreach ( $list['users'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['id'], $row['email'] ) || ! is_string( $row['id'] ) || ! is_string( $row['email'] ) || '' === $row['id'] || '' === $row['email'] ) {
				continue;
			}
			$first   = isset( $row['first_name'] ) && is_string( $row['first_name'] ) ? $row['first_name'] : '';
			$last    = isset( $row['last_name'] ) && is_string( $row['last_name'] ) ? $row['last_name'] : '';
			$hosts[] = array(
				'id'    => $row['id'],
				'name'  => trim( $first . ' ' . $last ),
				'email' => $row['email'],
			);
		}
		return array(
			'outcome' => array() === $hosts ? 'empty' : 'hosts',
			'hosts'   => $hosts,
		);
	}

	/** Return the seven Zoom keys from a normalized result and target. This method does not write. */
	public function metadata_map( array $result, array $target ) {
		$id       = TutorPress_Live_Lesson_Provider_Result::identity( $result );
		$zone     = isset( $target['timezone'] ) && is_string( $target['timezone'] ) ? $target['timezone'] : '';
		$start    = TutorPress_Live_Lesson_Datetime::parse_wall_clock( isset( $target['start_local'] ) ? $target['start_local'] : '', $zone );
		$duration = isset( $target['duration'] ) && is_array( $target['duration'] ) ? TutorPress_Live_Lesson_Datetime::zoom_duration( isset( $target['duration']['value'] ) ? $target['duration']['value'] : null, isset( $target['duration']['unit'] ) ? $target['duration']['unit'] : '' ) : null;
		$course   = isset( $target['course_id'] ) && is_int( $target['course_id'] ) && $target['course_id'] > 0 ? $target['course_id'] : 0;
		$topic    = isset( $target['topic_id'] ) && is_int( $target['topic_id'] ) && $target['topic_id'] > 0 ? $target['topic_id'] : 0;
		$host     = isset( $target['provider_target'] ) && is_array( $target['provider_target'] ) && isset( $target['provider_target']['host_id'] ) && is_string( $target['provider_target']['host_id'] ) && '' !== $target['provider_target']['host_id'] ? $target['provider_target']['host_id'] : '';
		if ( 'confirmed' !== ( isset( $result['outcome'] ) ? $result['outcome'] : null ) || null === $id || ! ( $start instanceof DateTimeImmutable ) || ! is_array( $duration ) || $course < 1 || $topic < 1 || '' === $host ) {
			return null;
		}
		return array(
			'_tutor_zm_start_date'     => $start->format( 'Y-m-d' ),
			'_tutor_zm_start_datetime' => $start->format( 'Y-m-d H:i:s' ),
			'_tutor_zm_duration'       => $duration['value'],
			'_tutor_zm_duration_unit'  => 'hours' === $duration['unit'] ? 'hr' : 'min',
			'_tutor_zm_for_course'     => $course,
			'_tutor_zm_for_topic'      => $topic,
			'_tutor_zm_data'           => json_encode( array( 'id' => $id, 'timezone' => $zone, 'host_id' => $host ) ),
		);
	}

	/** Stored Zoom identity is the id inside _tutor_zm_data. Decoy keys are ignored. */
	private function stored_id( array $data ) {
		if ( isset( $data['id'] ) && is_string( $data['id'] ) && '' !== $data['id'] ) {
			return $data['id'];
		}
		if ( isset( $data['id'] ) && is_int( $data['id'] ) && $data['id'] > 0 ) {
			return (string) $data['id'];
		}
		return null;
	}

	/** One raw "id" digit match from a blob string that did not decode. */
	private function one_zoom_id( $raw ) {
		if ( ! is_string( $raw ) || '' === $raw ) {
			return null;
		}
		$found = array();
		if ( 1 !== preg_match_all( '/"id"\s*:\s*(?:"([0-9]+)"|([0-9]+))/', $raw, $found, PREG_SET_ORDER ) ) {
			return null;
		}
		$digits = isset( $found[0][1] ) && '' !== $found[0][1] ? $found[0][1] : ( isset( $found[0][2] ) ? $found[0][2] : '' );
		return (int) $digits > 0 ? $digits : null;
	}

	/** Hydrate schedule, typed duration, relations, host, and id from the seven Zoom keys. */
	public function hydrate_item( array $local ) {
		$raw     = isset( $local['_tutor_zm_data'] ) ? $local['_tutor_zm_data'] : '';
		$decoded = is_array( $raw ) ? $raw : ( is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array() );
		$valid   = is_array( $decoded );
		$data    = $valid ? $decoded : array();
		$unit  = isset( $local['_tutor_zm_duration_unit'] ) && 'hr' === $local['_tutor_zm_duration_unit'] ? 'hours' : ( isset( $local['_tutor_zm_duration_unit'] ) && 'min' === $local['_tutor_zm_duration_unit'] ? 'minutes' : '' );
		$value = isset( $local['_tutor_zm_duration'] ) && is_int( $local['_tutor_zm_duration'] ) ? $local['_tutor_zm_duration'] : ( isset( $local['_tutor_zm_duration'] ) && is_string( $local['_tutor_zm_duration'] ) && ctype_digit( $local['_tutor_zm_duration'] ) ? (int) $local['_tutor_zm_duration'] : null );
		$zone     = isset( $data['timezone'] ) && TutorPress_Live_Lesson_Datetime::is_approved_timezone( $data['timezone'] ) ? $data['timezone'] : 'UTC';
		$start    = $this->zoom_start_local( $data, $local, $zone );
		$duration  = $this->zoom_hydrated_duration( $data, $value, $unit );
		$settings  = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
		$recording = isset( $settings['auto_recording'] ) && is_string( $settings['auto_recording'] ) && in_array( $settings['auto_recording'], array( 'local', 'cloud', 'none' ), true ) ? $settings['auto_recording'] : '';
		return array(
			'start_date'     => isset( $local['_tutor_zm_start_date'] ) && is_string( $local['_tutor_zm_start_date'] ) ? $local['_tutor_zm_start_date'] : '',
			'start_local'    => $start,
			'end_local'      => $this->zoom_end_local( $start, $duration, $zone ),
			'timezone'       => $zone,
			'duration'       => $duration,
			'course_id'      => $this->relation_id( isset( $local['_tutor_zm_for_course'] ) ? $local['_tutor_zm_for_course'] : null ),
			'topic_id'       => $this->relation_id( isset( $local['_tutor_zm_for_topic'] ) ? $local['_tutor_zm_for_topic'] : null ),
			'provider_id'    => $valid ? $this->stored_id( $data ) : $this->one_zoom_id( is_string( $raw ) ? $raw : '' ),
			'host_id'        => isset( $data['host_id'] ) && is_string( $data['host_id'] ) ? $data['host_id'] : '',
			'password'       => isset( $data['password'] ) && is_string( $data['password'] ) ? $data['password'] : '',
			'auto_recording' => $recording,
			'waiting_room'   => isset( $settings['waiting_room'] ) && is_bool( $settings['waiting_room'] ) ? $settings['waiting_room'] : false,
		);
	}

	/** Blob start_time is UTC, with or without Z, then the approved zone. Seconds are :00. */
	private function zoom_start_local( array $data, array $local, $zone ) {
		$blob = isset( $data['start_time'] ) && is_string( $data['start_time'] ) ? $data['start_time'] : '';
		if ( '' !== $blob ) {
			$target = new DateTimeZone( $zone );
			try {
				$parsed = new DateTime( $blob, new DateTimeZone( 'UTC' ) );
				return $parsed->setTimezone( $target )->format( 'Y-m-d H:i:00' );
			} catch ( Exception $thrown ) {
				unset( $thrown );
			}
		}
		$scalar = isset( $local['_tutor_zm_start_datetime'] ) && is_string( $local['_tutor_zm_start_datetime'] ) ? $local['_tutor_zm_start_datetime'] : '';
		return 1 === preg_match( '/\A([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}):[0-9]{2}\z/', $scalar, $match ) ? $match[1] . ':00' : $scalar;
	}

	/** Blob minutes win. An even hour count is that total divided by 60. */
	private function zoom_hydrated_duration( array $data, $value, $unit ) {
		$blob = isset( $data['duration'] ) && is_int( $data['duration'] ) ? $data['duration'] : ( isset( $data['duration'] ) && is_string( $data['duration'] ) && ctype_digit( $data['duration'] ) ? (int) $data['duration'] : null );
		if ( null === $blob ) {
			return TutorPress_Live_Lesson_Datetime::zoom_duration( $value, $unit );
		}
		if ( 'hours' === $unit && $blob > 0 && 0 === $blob % 60 ) {
			return TutorPress_Live_Lesson_Datetime::zoom_duration( intdiv( $blob, 60 ), 'hours' );
		}
		return TutorPress_Live_Lesson_Datetime::zoom_duration( $blob, 'hours' === $unit || 'minutes' === $unit ? 'minutes' : '' );
	}

	/** End is the wall-clock start plus the typed minutes. An unusable start is copied. */
	private function zoom_end_local( $start, $duration, $zone ) {
		if ( ! is_string( $start ) ) {
			return '';
		}
		$minutes = is_array( $duration ) && isset( $duration['value'], $duration['unit'] ) && is_int( $duration['value'] ) && $duration['value'] > 0 ? ( 'hours' === $duration['unit'] ? $duration['value'] * 60 : ( 'minutes' === $duration['unit'] ? $duration['value'] : 0 ) ) : 0;
		if ( $minutes < 1 || '' === $start || ! TutorPress_Live_Lesson_Datetime::is_wall_clock( $start ) ) {
			return $start;
		}
		$parsed = TutorPress_Live_Lesson_Datetime::parse_wall_clock( $start, $zone );
		if ( ! $parsed instanceof DateTimeImmutable ) {
			return $start;
		}
		$end = $parsed->modify( '+' . $minutes . ' minutes' );
		return $end instanceof DateTimeImmutable ? $end->format( 'Y-m-d H:i:00' ) : $start;
	}

	/** Positive int, or a digit string whose cast is greater than zero. */
	private function relation_id( $value ) {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}
		if ( is_string( $value ) && ctype_digit( $value ) ) {
			$cast = (int) $value;
			return $cast > 0 ? $cast : null;
		}
		return null;
	}

	/** Legacy provider id is the id inside _tutor_zm_data. */
	public function hydrate_legacy( array $local ) {
		return array( 'provider_id' => $this->hydrate_item( $local )['provider_id'] );
	}
}
