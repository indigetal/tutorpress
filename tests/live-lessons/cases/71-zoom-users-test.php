<?php

$bag = (object) array( 'user' => 4, 'seen' => array(), 'mode' => true, 'list_mode' => 'ok', 'list' => null, 'factories' => 0 );
$secret = array( 'account_id' => 'acct-sentinel', 'api_key' => 'key-sentinel', 'api_secret' => 'secret-sentinel' );
$token = 'token-sentinel'; $ledger = TutorPress_Live_Lessons_Boundary_Fake::$ledger; ob_start();
$client = new class() {
	public $bag; public $calls = 0;
	public function userlist( $query ) {
		$this->calls++;
		$this->bag->seen[] = 'userlist:' . $this->bag->user . ':' . json_encode( $query );
		if ( 'throw' === $this->bag->list_mode ) { throw new RuntimeException( 'userlist boom' ); }
		return $this->bag->list;
	}
};
$client->bag = $bag;
$adapter = new TutorPress_Zoom_Provider_Adapter( static function () use ( $bag ) { return $bag->user; }, static function ( $id ) use ( $bag ) { $bag->seen[] = 'switch:' . $id; $bag->user = $id; }, static function ( $prior ) use ( $bag ) { $bag->seen[] = 'restore:' . $prior; $bag->user = $prior; }, static function () use ( &$secret ) { return $secret; }, static function () use ( $bag, $token ) { $bag->seen[] = 'token'; return 'string' === $bag->mode ? $token : $bag->mode; }, static function () { return 'meetings-client'; }, static function () use ( $bag, $client ) { $bag->seen[] = 'users:' . count( func_get_args() ); $bag->factories++; return $client; } );
$bag->list = array( 'users' => array( array( 'id' => 'h1', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com', 'instructor_id' => 'inst-sentinel', 'instructor_name' => 'Inst Name' ), array( 'id' => '', 'email' => 'skip@example.com', 'first_name' => 'Skip', 'last_name' => 'Row' ), array( 'id' => 'no-mail', 'email' => '', 'first_name' => 'No', 'last_name' => 'Mail' ) ) );
$hosts = $adapter->list_hosts( 7 ); $while_owner = $bag->seen; $bag->seen = array();
$seq7 = array( 'switch:7', 'token', 'users:0', 'userlist:7:{"page_size":2000}', 'restore:4' );
$matched = $adapter->validate_host( 7, 'h1' ); $matched_seen = $bag->seen; $bag->seen = array();
$name_host = $adapter->validate_host( 7, 'Ada Lovelace' ); $name_seen = $bag->seen; $bag->seen = array();
$email_host = $adapter->validate_host( 7, 'ada@example.com' ); $email_seen = $bag->seen; $bag->seen = array();
$cross_7 = $adapter->validate_host( 7, 'h2' ); $cross_7_seen = $bag->seen; $bag->seen = array();
$before_early = $client->calls; $early = array( $adapter->validate_host( 7, '' ), $adapter->validate_host( 7, null ), $adapter->validate_host( 7, 7 ) ); $early_seen = $bag->seen; $early_calls = $client->calls === $before_early;
$bag->list = array( 'users' => array( array( 'id' => 'h2', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'email' => 'grace@example.com' ) ) );
$other = $adapter->list_hosts( 8 );
$seq8 = array( 'switch:8', 'token', 'users:0', 'userlist:8:{"page_size":2000}', 'restore:4' ); $bag->seen = array(); $cross_8 = $adapter->validate_host( 8, 'h1' ); $cross_8_seen = $bag->seen; $bag->seen = array();
$bag->list = array( 'users' => array() ); $empty = $adapter->list_hosts( 7 );
$before_calls = $client->calls; $empty_host = $adapter->validate_host( 7, 'h1' ); $empty_called = $client->calls === $before_calls + 1;
$bag->list = array( 'users' => array( array( 'id' => '', 'email' => 'skip@example.com' ), array( 'id' => 'no-mail', 'email' => '' ) ) ); $filtered = $adapter->list_hosts( 7 );
$bag->list = array( 'message' => 'zoom-error-sentinel' ); $error = $adapter->list_hosts( 7 );
$before_calls = $client->calls; $error_host = $adapter->validate_host( 7, 'h1' ); $error_called = $client->calls === $before_calls + 1;
$bag->list = false; $false_list = $adapter->list_hosts( 7 ); $bag->list = 0; $zero_list = $adapter->list_hosts( 7 ); $bag->list = null; $null_list = $adapter->list_hosts( 7 );
$blocked = array(); $gates = array(); foreach ( array( false, 'string', null, 1, 'true' ) as $bag->mode ) { $before_calls = $client->calls; $before_factories = $bag->factories; $blocked[] = $adapter->list_hosts( 7 ); $gates[] = $client->calls === $before_calls && $bag->factories === $before_factories; }
$host_blocked = array(); $host_gates = array(); foreach ( array( false, 'string', null, 1, 'true' ) as $bag->mode ) { $before_calls = $client->calls; $host_blocked[] = $adapter->validate_host( 7, 'h1' ); $host_gates[] = $client->calls === $before_calls; }
$bag->mode = true; $bag->list_mode = 'throw'; try { $adapter->list_hosts( 7 ); } catch ( RuntimeException $e ) { $boom = $e->getMessage() . ':' . $bag->user; }
try { $adapter->validate_host( 7, 'h1' ); } catch ( RuntimeException $e ) { $host_boom = $e->getMessage() . ':' . $bag->user; }
$bag->list_mode = 'ok'; $seen = $bag->seen; $secret['api_secret'] = ''; $quiet = $adapter->list_hosts( 7 );
$before_calls = $client->calls; $host_quiet = $adapter->validate_host( 7, 'h1' ); $quiet_calls = $client->calls === $before_calls;
$output = ob_get_clean();
$expected = array( 'outcome' => 'hosts', 'hosts' => array( array( 'id' => 'h1', 'name' => 'Ada Lovelace', 'email' => 'ada@example.com' ) ) );
$other_expected = array( 'outcome' => 'hosts', 'hosts' => array( array( 'id' => 'h2', 'name' => 'Grace Hopper', 'email' => 'grace@example.com' ) ) );
$empty_result = array( 'outcome' => 'empty', 'hosts' => array() ); $error_result = array( 'outcome' => 'definite_rejection', 'hosts' => array() ); $uncertain_list = array( 'outcome' => 'uncertain', 'hosts' => array() ); $host_failure = array( 'outcome' => 'pre_dispatch_failure', 'hosts' => array() );
$blob = json_encode( array( $hosts, $other, $empty, $filtered, $error, $false_list, $zero_list, $null_list, $blocked, $boom, $quiet, $while_owner ) );
tutorpress_live_lessons_assert( $expected === $hosts && array( 'id', 'name', 'email' ) === array_keys( $hosts['hosts'][0] ) && array( 'switch:7', 'token', 'users:0', 'userlist:7:{"page_size":2000}', 'restore:4' ) === $while_owner && 4 === $bag->user && $other_expected === $other && $expected === $hosts && $empty_result === $empty && $empty_result === $filtered && $error_result === $error && $uncertain_list === $false_list && $uncertain_list === $zero_list && $uncertain_list === $null_list && array_fill( 0, 5, $host_failure ) === $blocked && array_fill( 0, 5, true ) === $gates && 'userlist boom:4' === $boom && $host_failure === $quiet && $seen === $bag->seen && '' === $output && false === strpos( $blob, 'acct-sentinel' ) && false === strpos( $blob, 'key-sentinel' ) && false === strpos( $blob, 'secret-sentinel' ) && false === strpos( $blob, $token ) && false === strpos( $blob, 'inst-sentinel' ) && false === strpos( $blob, 'Inst Name' ) && false === strpos( $blob, 'zoom-error-sentinel' ) && false === strpos( json_encode( $hosts ), 'instructor_id' ) && false === strpos( json_encode( $hosts ), 'instructor_name' ) && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'zoom users stay on one owner' );
tutorpress_live_lessons_pass( 'zoom owner users' );
$host_blob = json_encode( array( $matched, $name_host, $email_host, $cross_7, $cross_8, $early, $empty_host, $error_host, $host_blocked, $host_boom, $host_quiet ) );
tutorpress_live_lessons_assert( true === $matched && $seq7 === $matched_seen && false === $name_host && $seq7 === $name_seen && false === $email_host && $seq7 === $email_seen && false === $cross_7 && $seq7 === $cross_7_seen && false === $cross_8 && $seq8 === $cross_8_seen && array( false, false, false ) === $early && array() === $early_seen && $early_calls && false === $empty_host && $empty_called && false === $error_host && $error_called && array_fill( 0, 5, false ) === $host_blocked && array_fill( 0, 5, true ) === $host_gates && 'userlist boom:4' === $host_boom && false === $host_quiet && $quiet_calls && $seen === $bag->seen && '' === $output && false === strpos( $host_blob, 'acct-sentinel' ) && false === strpos( $host_blob, 'key-sentinel' ) && false === strpos( $host_blob, 'secret-sentinel' ) && false === strpos( $host_blob, $token ) && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'zoom host id comes from the owner list' );
tutorpress_live_lessons_pass( 'zoom host revalidation' );
ob_start(); $secret['api_secret'] = 'secret-sentinel'; $bag->mode = true; $bag->list_mode = 'ok'; $bag->user = 4; $bag->seen = array();
$scope_lists = array( array( 'users' => array( array( 'id' => 'h1', 'email' => 'ada@example.com', 'first_name' => 'Ada', 'last_name' => 'Lovelace' ) ) ), array( 'users' => array( array( 'id' => 'h2', 'email' => 'grace@example.com', 'first_name' => 'Grace', 'last_name' => 'Hopper' ) ) ), array( 'message' => 'zoom-error-sentinel' ), array( 'users' => array() ) );
$scope_hidden = 0; foreach ( $scope_lists as $scope_list ) { $scope_hidden += (int) ( isset( $scope_list['status'] ) || isset( $scope_list['http_status'] ) || isset( $scope_list['outcome'] ) ); }
$bag->list = $scope_lists[0]; $scope_hosts = $adapter->list_hosts( 7 ); $scope_seen = $bag->seen; $bag->seen = array(); $bag->list = $scope_lists[1]; $scope_other = $adapter->list_hosts( 8 );
$bag->list = $scope_lists[2]; $scope_error = $adapter->list_hosts( 7 ); $bag->list = $scope_lists[3]; $scope_empty = $adapter->list_hosts( 7 ); $bag->list = $scope_lists[0]; $scope_host = $adapter->validate_host( 7, 'h1' ); $scope_cross = $adapter->validate_host( 7, 'h2' );
$scope_output = ob_get_clean(); $scope_blob = json_encode( array( $scope_hosts, $scope_other, $scope_error, $scope_empty, $scope_host, $scope_cross, $bag->seen ) );
tutorpress_live_lessons_assert( 0 === $scope_hidden && array( 'id' => 'h1', 'name' => 'Ada Lovelace', 'email' => 'ada@example.com' ) === $scope_hosts['hosts'][0] && 1 === count( $scope_hosts['hosts'] ) && array( 'id' => 'h2', 'name' => 'Grace Hopper', 'email' => 'grace@example.com' ) === $scope_other['hosts'][0] && 1 === count( $scope_other['hosts'] ) && false === strpos( json_encode( $scope_hosts ), 'h2' ) && false === strpos( json_encode( $scope_other ), 'h1' ) && 'definite_rejection' === $scope_error['outcome'] && array() === $scope_error['hosts'] && 'empty' === $scope_empty['outcome'] && true === $scope_host && false === $scope_cross && 4 === $bag->user && array( 'switch:7', 'token', 'users:0', 'userlist:7:{"page_size":2000}', 'restore:4' ) === $scope_seen && 'restore:4' === $bag->seen[ count( $bag->seen ) - 1 ] && '' === $scope_output && false === strpos( $scope_blob, $token ) && false === strpos( $scope_blob, 'acct-sentinel' ) && false === strpos( $scope_blob, 'key-sentinel' ) && false === strpos( $scope_blob, 'secret-sentinel' ) && false === strpos( $scope_blob, 'zoom-error-sentinel' ) && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger && array( 'TutorPress_Live_Lesson_Provider_Adapter' => 'TutorPress_Live_Lesson_Provider_Adapter' ) === class_implements( $adapter ), 'zoom owner matrix stays scoped' );
tutorpress_live_lessons_pass( 'zoom owner matrix stays scoped' );
