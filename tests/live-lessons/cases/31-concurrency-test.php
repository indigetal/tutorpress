<?php

$insert   = array( 'TutorPress_Live_Lessons_Store_Fake', 'add_option' );
$update   = array( 'TutorPress_Live_Lessons_Store_Fake', 'update_option_bytes' );
$delete   = array( 'TutorPress_Live_Lessons_Store_Fake', 'delete_option_bytes' );
$read     = array( 'TutorPress_Live_Lessons_Store_Fake', 'meta_rows' );
$write    = array( 'TutorPress_Live_Lessons_Store_Fake', 'add_meta' );
$meta     = array( 'TutorPress_Live_Lessons_Store_Fake', 'update_meta_bytes' );
$cache    = function ( $key ) { TutorPress_Live_Lessons_Store_Fake::$cache[] = $key; };
$affected = function () { return TutorPress_Live_Lessons_Store_Fake::$affected; };
$sync     = '_tutorpress_live_lesson_sync';
$expiry   = '2026-09-22 12:00:00';
$pending  = array( 'error' => null, 'operation' => null, 'owner_id' => 42, 'provenance' => null, 'provider' => 'google', 'provider_id' => null, 'reconciliation_required' => false, 'schema_version' => 1, 'state' => 'pending', 'version' => 1, 'warning' => null );
$ledger   = count( TutorPress_Live_Lessons_Boundary_Fake::$ledger );
$post     = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$name     = 'tutorpress_live_lesson_lock_' . $post;
$first    = TutorPress_Live_Lesson_Sync_Repository::acquire( $post, 'create', 'token-a', $expiry, $insert );
tutorpress_live_lessons_assert( is_string( $first ) && null === TutorPress_Live_Lesson_Sync_Repository::acquire( $post, 'create', 'token-b', $expiry, $insert ) && $first === TutorPress_Live_Lessons_Store_Fake::$options[ $name ], 'a competing start leaves the first lock bytes unchanged' );
$taken    = TutorPress_Live_Lesson_Sync_Repository::takeover( $post, 'reconcile', 'token-c', $first, $expiry, $update );
tutorpress_live_lessons_assert( is_string( $taken ) && null === TutorPress_Live_Lesson_Sync_Repository::takeover( $post, 'reconcile', 'token-d', $first, $expiry, $update ) && $taken === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && 0 === TutorPress_Live_Lessons_Store_Fake::$affected, 'a second takeover with the same prior bytes does not replace the lock' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lesson_Sync_Repository::release( $post, 'token-a', $first, $delete ) && $taken === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && 0 === TutorPress_Live_Lessons_Store_Fake::$affected, 'an old token and old prior do not release the replacement lock' );
$envelope = TutorPress_Live_Lesson_Sync_Repository::initialize( $post, $taken, 'token-c', 0, $pending, $read, $write, $cache );
$synced   = $pending;
$synced['state'] = 'synced';
$done     = TutorPress_Live_Lesson_Sync_Repository::exchange( $post, $taken, 'token-c', 'reconcile', 'pending', 1, $envelope, $synced, $read, $meta, $affected );
tutorpress_live_lessons_assert( is_string( $envelope ) && is_string( $done ) && $done === TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $synced ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $post, $taken, 'token-c', 'reconcile', 'pending', 1, $envelope, $synced, $read, $meta, $affected ) && $done === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post, $sync ) && 1 === count( $read( $post, $sync ) ), 'duplicate finalization keeps the first accepted write' );
$dead     = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$deadlock = TutorPress_Live_Lesson_Sync_Repository::acquire( $dead, 'create', 'token-e', $expiry, $insert );
$left     = TutorPress_Live_Lesson_Sync_Repository::initialize( $dead, $deadlock, 'token-e', 0, $pending, $read, $write, $cache );
tutorpress_live_lessons_assert( is_string( $deadlock ) && is_string( $left ) && $deadlock === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $dead ] && $left === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $dead, $sync ), 'process interruption leaves the lock and the pending envelope' );
$held     = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$old      = TutorPress_Live_Lesson_Sync_Repository::acquire( $held, 'create', 'token-f', $expiry, $insert );
$row      = TutorPress_Live_Lesson_Sync_Repository::initialize( $held, $old, 'token-f', 0, $pending, $read, $write, $cache );
$next     = TutorPress_Live_Lesson_Sync_Repository::takeover( $held, 'reconcile', 'token-g', $old, $expiry, $update );
tutorpress_live_lessons_assert( is_string( $row ) && is_string( $next ) && $next === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $held ] && $row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $held, $sync ), 'takeover replaces the expired lock and leaves the pending row unchanged' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::exchange( $held, $next, 'token-g', 'create', 'pending', 1, $row, $synced, $read, $meta, $affected ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $held, $next, 'token-g', 'reconcile', 'error', 1, $row, $synced, $read, $meta, $affected ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $held, $next, 'token-g', 'reconcile', 'pending', 2, $row, $synced, $read, $meta, $affected ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $held, $next, 'token-g', 'reconcile', 'synced', 1, $done, $synced, $read, $meta, $affected ) && $row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $held, $sync ) && $ledger === count( TutorPress_Live_Lessons_Boundary_Fake::$ledger ), 'a stale provider return does not write or call the provider boundary' );
$race      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$race_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $race, 'create', 'token-h', $expiry, $insert );
$double    = function ( $post_id, $meta_key, $value ) { TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $meta_key, $value ); return TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $meta_key, $value ); };
$raced     = TutorPress_Live_Lesson_Sync_Repository::initialize( $race, $race_lock, 'token-h', 0, $pending, $read, $double, $cache );
$rows      = $read( $race, $sync );
tutorpress_live_lessons_assert( null === $raced && 2 === count( $rows ) && $rows[0]['meta_value'] === $rows[1]['meta_value'] && $ledger === count( TutorPress_Live_Lessons_Boundary_Fake::$ledger ), 'a version-zero race returns null and leaves both rows in place' );
tutorpress_live_lessons_pass( 'scripted concurrency and interruption' );
$live      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$live_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $live, 'create', 'token-i', $expiry, $insert );
$live_row  = TutorPress_Live_Lesson_Sync_Repository::initialize( $live, $live_lock, 'token-i', 0, $pending, $read, $write, $cache );
$ran       = TutorPress_Live_Lesson_Sync_Repository::during( $live, 'token-i', $live_lock, function () { return 'ok'; }, $delete );
tutorpress_live_lessons_assert( 'ok' === $ran && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $live ] ) && $live_row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live, $sync ), 'finally releases the held token and leaves the pending row' );
$boom      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$boom_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $boom, 'create', 'token-j', $expiry, $insert );
$boom_row  = TutorPress_Live_Lesson_Sync_Repository::initialize( $boom, $boom_lock, 'token-j', 0, $pending, $read, $write, $cache );
$caught    = null; try { TutorPress_Live_Lesson_Sync_Repository::during( $boom, 'token-j', $boom_lock, function () { throw new RuntimeException( 'stopped' ); }, $delete ); } catch ( RuntimeException $e ) { $caught = $e->getMessage(); }
tutorpress_live_lessons_assert( 'stopped' === $caught && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $boom ] ) && $boom_row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $boom, $sync ), 'finally releases after the callback throws and leaves the pending row' );
$fail      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$fail_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $fail, 'create', 'token-k', $expiry, $insert );
$fail_row  = TutorPress_Live_Lesson_Sync_Repository::initialize( $fail, $fail_lock, 'token-k', 0, $pending, $read, $write, $cache );
$kept      = TutorPress_Live_Lesson_Sync_Repository::during( $fail, 'token-k', $fail_lock, function () { return 'kept'; }, function ( $name, $prior ) { return false; } );
tutorpress_live_lessons_assert( 'kept' === $kept && $fail_lock === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $fail ] && $fail_row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $fail, $sync ), 'a failed release leaves the lock and the pending row and returns the callback result' );
$owner       = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$owner_lock  = TutorPress_Live_Lesson_Sync_Repository::acquire( $owner, 'create', 'token-l', $expiry, $insert );
$owner_row   = TutorPress_Live_Lesson_Sync_Repository::initialize( $owner, $owner_lock, 'token-l', 0, $pending, $read, $write, $cache );
$replacement = null; TutorPress_Live_Lesson_Sync_Repository::during( $owner, 'token-l', $owner_lock, function () use ( $owner, $owner_lock, $expiry, $update, &$replacement ) { $replacement = TutorPress_Live_Lesson_Sync_Repository::takeover( $owner, 'reconcile', 'token-m', $owner_lock, $expiry, $update ); }, $delete );
tutorpress_live_lessons_assert( is_string( $replacement ) && $replacement === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $owner ] && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && $owner_row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $owner, $sync ), 'the old owner finally leaves the replacement lock and the pending row' );
$died      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$died_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $died, 'create', 'token-n', $expiry, $insert );
$died_row  = TutorPress_Live_Lesson_Sync_Repository::initialize( $died, $died_lock, 'token-n', 0, $pending, $read, $write, $cache );
$recovered = TutorPress_Live_Lesson_Sync_Repository::takeover( $died, 'reconcile', 'token-o', $died_lock, $expiry, $update );
tutorpress_live_lessons_assert( is_string( $recovered ) && $recovered === TutorPress_Live_Lessons_Store_Fake::$options[ 'tutorpress_live_lesson_lock_' . $died ] && $died_row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $died, $sync ), 'process death skips finally and takeover leaves the pending row' );
tutorpress_live_lessons_pass( 'token-conditional finally leaves pending state' );
$compare_saved = array_key_exists( 'wpdb', $GLOBALS ) ? $GLOBALS['wpdb'] : null;
$compare_db    = new class() {
	public $options       = 'wp_options';
	public $postmeta      = 'wp_postmeta';
	public $rows_affected = 0;
	public $prepared      = '';
	public $values        = array();

	public function prepare( $query, ...$values ) {
		$this->prepared = $query;
		$this->values   = $values;
		return $query;
	}

	public function query( $prepared ) {
		if ( is_string( $prepared ) && false !== strpos( $prepared, $this->postmeta ) ) {
			return $this->update_meta_row();
		}
		$bytes = $this->values[0];
		$name  = $this->values[1];
		$prior = $this->values[2];
		$same  = is_string( $prepared ) && is_string( $name ) && isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) && TutorPress_Live_Lessons_Store_Fake::$options[ $name ] === $prior;
		if ( ! $same ) {
			$this->rows_affected = 0;
			return 0;
		}
		TutorPress_Live_Lessons_Store_Fake::$options[ $name ] = $bytes;
		$this->rows_affected = 1;
		return 1;
	}

	public function get_results( $prepared ) {
		$post_id = isset( $this->values[0] ) ? (int) $this->values[0] : 0;
		$key     = isset( $this->values[1] ) ? $this->values[1] : '';
		$rows    = array();
		foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $row ) {
			if ( (int) $row['post_id'] === $post_id && $row['meta_key'] === $key ) {
				$rows[] = array( 'meta_id' => (int) $row['meta_id'], 'meta_value' => $row['meta_value'], 'post_id' => (int) $row['post_id'], 'meta_key' => $row['meta_key'] );
			}
		}
		return $rows;
	}

	private function update_meta_row() {
		if ( 5 !== count( $this->values ) ) {
			$this->rows_affected = 0;
			return 0;
		}
		$bytes   = $this->values[0];
		$meta_id = (int) $this->values[1];
		$post_id = (int) $this->values[2];
		$key     = $this->values[3];
		$prior   = $this->values[4];
		$hits    = array();
		foreach ( TutorPress_Live_Lessons_Store_Fake::$meta as $i => $row ) {
			if ( (int) $row['post_id'] === $post_id && $row['meta_key'] === $key && (int) $row['meta_id'] === $meta_id && $row['meta_value'] === $prior ) {
				$hits[] = $i;
			}
		}
		foreach ( $hits as $i ) {
			TutorPress_Live_Lessons_Store_Fake::$meta[ $i ]['meta_value'] = $bytes;
		}
		$this->rows_affected = count( $hits );
		return $this->rows_affected;
	}
};
$GLOBALS['wpdb'] = $compare_db;
try {
	$compare_name  = 'tutorpress_live_lesson_lock_compare';
	$compare_prior = '{"expiry":"2026-09-22 12:00:00","operation":"create","token":"prior"}';
	$compare_next  = '{"expiry":"2026-09-22 12:05:00","operation":"create","token":"next"}';
	$compare_other = '{"expiry":"2026-09-22 12:10:00","operation":"create","token":"other"}';
	TutorPress_Live_Lessons_Store_Fake::$options[ $compare_name ] = $compare_prior;
	$compare_hit = TutorPress_Live_Lesson_Sync_Repository::compare_option_update( $compare_name, $compare_next, $compare_prior );
	tutorpress_live_lessons_assert( true === $compare_hit && $compare_next === TutorPress_Live_Lessons_Store_Fake::$options[ $compare_name ] && 1 === (int) $compare_db->rows_affected && false !== strpos( $compare_db->prepared, 'CONVERT(option_value USING BINARY) = CONVERT(%s USING BINARY)' ) && array( $compare_next, $compare_name, $compare_prior ) === $compare_db->values, 'a matching prior replaces one option row' );
	$compare_miss = TutorPress_Live_Lesson_Sync_Repository::compare_option_update( $compare_name, $compare_other, $compare_prior );
	tutorpress_live_lessons_assert( false === $compare_miss && $compare_next === TutorPress_Live_Lessons_Store_Fake::$options[ $compare_name ] && 0 === (int) $compare_db->rows_affected && $ledger === count( TutorPress_Live_Lessons_Boundary_Fake::$ledger ), 'a mismatched prior leaves the option bytes unchanged' );
	tutorpress_live_lessons_pass( 'compare-and-set option update matches the binary prior' );
	$meta_stored = TutorPress_Live_Lessons_Store_Fake::meta_rows( $live, $sync );
	$meta_next   = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $synced );
	$meta_listed = TutorPress_Live_Lesson_Sync_Repository::meta_rows( $live, $sync );
	tutorpress_live_lessons_assert( 1 === count( $meta_stored ) && array( array( 'meta_id' => (int) $meta_stored[0]['meta_id'], 'meta_value' => $live_row, 'post_id' => (int) $live ) ) === $meta_listed, 'meta rows return the stored sync envelope' );
	$meta_hit = TutorPress_Live_Lesson_Sync_Repository::compare_meta_update( $live, $sync, $meta_stored[0]['meta_id'], $meta_next, $live_row );
	tutorpress_live_lessons_assert( true === $meta_hit && 1 === TutorPress_Live_Lesson_Sync_Repository::last_affected() && $meta_next === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live, $sync ) && $row === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $held, $sync ) && false !== strpos( $compare_db->prepared, 'CONVERT(meta_value USING BINARY) = CONVERT(%s USING BINARY)' ) && false !== strpos( $compare_db->prepared, 'meta_id = %d' ) && false !== strpos( $compare_db->prepared, 'post_id = %d' ) && false !== strpos( $compare_db->prepared, 'meta_key = %s' ) && array( $meta_next, (int) $meta_stored[0]['meta_id'], (int) $live, $sync, $live_row ) === $compare_db->values, 'a matching prior replaces one sync row' );
	$meta_miss = TutorPress_Live_Lesson_Sync_Repository::compare_meta_update( $live, $sync, $meta_stored[0]['meta_id'], $live_row, $live_row );
	tutorpress_live_lessons_assert( false === $meta_miss && 0 === TutorPress_Live_Lesson_Sync_Repository::last_affected() && $meta_next === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $live, $sync ) && $ledger === count( TutorPress_Live_Lessons_Boundary_Fake::$ledger ), 'a mismatched prior leaves the sync bytes unchanged' );
	tutorpress_live_lessons_pass( 'compare-and-set meta update matches the binary prior' );
} finally {
	if ( null === $compare_saved ) {
		unset( $GLOBALS['wpdb'] );
	} else {
		$GLOBALS['wpdb'] = $compare_saved;
	}
}
