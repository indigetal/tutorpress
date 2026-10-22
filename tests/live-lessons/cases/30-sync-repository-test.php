<?php

TutorPress_Live_Lessons_Store_Fake::$options  = array();
TutorPress_Live_Lessons_Store_Fake::$autoload = array();
TutorPress_Live_Lessons_Store_Fake::$cache    = array();
TutorPress_Live_Lessons_Store_Fake::$affected = 0;

tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Store_Fake::add_option( 'opt', 'v1' ) && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && false === TutorPress_Live_Lessons_Store_Fake::$autoload['opt'] && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'insert is non-autoloaded and records no cache entry' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::add_option( 'opt', 'v2' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && 'v1' === TutorPress_Live_Lessons_Store_Fake::$options['opt'] && false === TutorPress_Live_Lessons_Store_Fake::$autoload['opt'], 'duplicate insert leaves stored bytes unchanged' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::update_option_bytes( 'missing', 'v2', 'v1' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'missing option update does not write' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::update_option_bytes( 'opt', 'v2', 'wrong' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && 'v1' === TutorPress_Live_Lessons_Store_Fake::$options['opt'] && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'prior mismatch does not write' );
tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Store_Fake::update_option_bytes( 'opt', 'v2', 'v1' ) && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && 'v2' === TutorPress_Live_Lessons_Store_Fake::$options['opt'] && array( 'opt' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'exact prior update writes and records the cache ledger' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::delete_option_bytes( 'opt', 'v1' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && 'v2' === TutorPress_Live_Lessons_Store_Fake::$options['opt'] && array( 'opt' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'prior mismatch does not delete' );
tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Store_Fake::delete_option_bytes( 'opt', 'v2' ) && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && ! isset( TutorPress_Live_Lessons_Store_Fake::$options['opt'] ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$autoload['opt'] ) && array( 'opt', 'opt' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'exact prior delete removes the name and records the cache ledger' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::delete_option_bytes( 'opt', 'v2' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && array( 'opt', 'opt' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'missing option delete does not write the cache ledger' );

tutorpress_live_lessons_pass( 'option insert, exact-prior update, and exact-prior delete operands' );

$name   = 'tutorpress_live_lesson_lock_7';
$insert = array( 'TutorPress_Live_Lessons_Store_Fake', 'add_option' );
$bytes  = TutorPress_Live_Lesson_Sync_Repository::acquire( 7, 'create', 'token-1', '2026-09-21 19:00:00', $insert );
tutorpress_live_lessons_assert( '{"expiry":"2026-09-21 19:00:00","operation":"create","token":"token-1"}' === $bytes && $bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && false === TutorPress_Live_Lessons_Store_Fake::$autoload[ $name ] && true === TutorPress_Live_Lesson_Sync_Repository::owns( $bytes, 'token-1' ) && array( 'opt', 'opt' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'acquired lock is non-autoloaded immutable json owned by its token' );
$failed = TutorPress_Live_Lesson_Sync_Repository::acquire( 7, 'update', 'token-2', '2026-09-21 19:05:00', $insert );
tutorpress_live_lessons_assert( null === $failed && $bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && false === TutorPress_Live_Lesson_Sync_Repository::owns( $bytes, 'token-2' ), 'a second acquire leaves the stored bytes unchanged and returns no token' );
tutorpress_live_lessons_pass( 'lock acquisition and ownership' );

$update = array( 'TutorPress_Live_Lessons_Store_Fake', 'update_option_bytes' );
$delete = array( 'TutorPress_Live_Lessons_Store_Fake', 'delete_option_bytes' );
$clock  = '2026-09-21 19:00:00';
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::takeover( 7, 'reconcile', 'token-2', $bytes, '2026-09-21 18:59:59', $update ) && $bytes === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && array( 'opt', 'opt' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'a future expiry does not write or record cache invalidation' );
$bad = '{"expiry":"2026-09-21 18:99:99","operation":"create","token":"token-1"}';
TutorPress_Live_Lessons_Store_Fake::$options[ $name ] = $bad;
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::takeover( 7, 'reconcile', 'token-2', $bad, $clock, $update ) && $bad === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && array( 'opt', 'opt' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'a malformed expiry does not write or record cache invalidation' );
TutorPress_Live_Lessons_Store_Fake::$options[ $name ] = $bytes;
$next = TutorPress_Live_Lesson_Sync_Repository::takeover( 7, 'reconcile', 'token-2', $bytes, $clock, $update );
tutorpress_live_lessons_assert( '{"expiry":"2026-09-21 19:05:00","operation":"reconcile","token":"token-2"}' === $next && $next === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && true === TutorPress_Live_Lesson_Sync_Repository::owns( $next, 'token-2' ) && array( 'opt', 'opt', $name ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'expired takeover writes the exact prior bytes and a five-minute utc expiry' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::takeover( 7, 'reconcile', 'token-3', $bytes, $clock, $update ) && $next === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && array( 'opt', 'opt', $name ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'a stale prior does not write or record cache invalidation' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lesson_Sync_Repository::release( 7, 'token-1', $next, $delete ) && false === TutorPress_Live_Lesson_Sync_Repository::release( 7, 'token-1', $bytes, $delete ) && $next === TutorPress_Live_Lessons_Store_Fake::$options[ $name ] && array( 'opt', 'opt', $name ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'an old token or old prior does not delete a replacement lock' );
tutorpress_live_lessons_assert( true === TutorPress_Live_Lesson_Sync_Repository::release( 7, 'token-2', $next, $delete ) && ! isset( TutorPress_Live_Lessons_Store_Fake::$options[ $name ] ) && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && array( 'opt', 'opt', $name, $name ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'a matching token releases the exact bytes and records cache invalidation' );
tutorpress_live_lessons_pass( 'expired takeover and token-safe release' );

TutorPress_Live_Lessons_Store_Fake::$cache = array();
$post = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
tutorpress_live_lessons_assert( null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post, 'sync' ) && false === TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post, 'sync', 1, 'v2', 'v1' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'zero meta rows do not write or record cache invalidation' );
$one = TutorPress_Live_Lessons_Store_Fake::add_meta( $post, 'sync', 'v1' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post + 1, 'sync', $one, 'v2', 'v1' ) && false === TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post, 'other', $one, 'v2', 'v1' ) && false === TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post, 'sync', $one + 1, 'v2', 'v1' ) && false === TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post, 'sync', $one, 'v2', 'wrong' ) && 'v1' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post, 'sync' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'a different post, key, meta_id, or prior bytes does not write' );
tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post, 'sync', $one, 'v2', 'v1' ) && 'v2' === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post, 'sync' ) && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && array( 'sync' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'exact meta_id and prior bytes write and record the cache ledger' );
TutorPress_Live_Lessons_Store_Fake::add_meta( $post, 'sync', 'v2' );
$rows = TutorPress_Live_Lessons_Store_Fake::meta_rows( $post, 'sync' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post, 'sync' ) && 2 === count( $rows ) && 'v2' === $rows[0]['meta_value'] && 'v2' === $rows[1]['meta_value'] && false === TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post, 'sync', $one, 'v3', 'v2' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && array( 'sync' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'duplicate rows return null and stay unchanged' );
tutorpress_live_lessons_pass( 'exact meta_id prior bytes and duplicate rows' );

TutorPress_Live_Lessons_Store_Fake::$cache = array();
$record = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$held   = TutorPress_Live_Lesson_Sync_Repository::acquire( $record, 'create', 'token-init', '2026-09-21 19:10:00', array( 'TutorPress_Live_Lessons_Store_Fake', 'add_option' ) );
$read   = array( 'TutorPress_Live_Lessons_Store_Fake', 'meta_rows' );
$write  = array( 'TutorPress_Live_Lessons_Store_Fake', 'add_meta' );
$cache  = function ( $meta_key ) { TutorPress_Live_Lessons_Store_Fake::$cache[] = $meta_key; };
$next   = array( 'error' => null, 'operation' => null, 'owner_id' => 42, 'provenance' => null, 'provider' => 'google', 'provider_id' => null, 'reconciliation_required' => false, 'schema_version' => 1, 'state' => 'pending', 'version' => 1, 'warning' => null );
$key    = '_tutorpress_live_lesson_sync';
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::initialize( $record, null, 'token-init', 0, $next, $read, $write, $cache ) && null === TutorPress_Live_Lesson_Sync_Repository::initialize( $record, $held, 'other', 0, $next, $read, $write, $cache ) && array() === $read( $record, $key ) && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'a missing or unowned lock does not insert' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::initialize( $record, $held, 'token-init', 1, $next, $read, $write, $cache ) && array() === $read( $record, $key ) && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'a non-zero expected version does not insert' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::initialize( $record, $held, 'token-init', 0, $next, $read, function () { return false; }, $cache ) && array() === $read( $record, $key ) && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'a failed insert does not record the cache' );
$bytes  = TutorPress_Live_Lesson_Sync_Repository::initialize( $record, $held, 'token-init', 0, $next, $read, $write, $cache );
tutorpress_live_lessons_assert( is_string( $bytes ) && $bytes === TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $next ) && array( $bytes ) === array_column( $read( $record, $key ), 'meta_value' ) && (int) $record === (int) $read( $record, $key )[0]['post_id'] && array( $key ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'version zero initializes one version-1 envelope for the locked post and records the meta cache' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::initialize( $record, $held, 'token-init', 0, $next, $read, $write, $cache ) && 1 === count( $read( $record, $key ) ) && array( $key ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'an existing row does not insert again' );
$other      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$other_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $other, 'create', 'token-race', '2026-09-21 19:10:00', array( 'TutorPress_Live_Lessons_Store_Fake', 'add_option' ) );
$race       = function ( $post_id, $meta_key, $value ) { TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $meta_key, $value ); TutorPress_Live_Lessons_Store_Fake::add_meta( $post_id, $meta_key, $value ); return 1; };
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::initialize( $other, $other_lock, 'token-race', 0, $next, $read, $race, $cache ) && 2 === count( $read( $other, $key ) ) && array( $key ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'a raced insert leaves the rows and does not record the cache' );
tutorpress_live_lessons_pass( 'version-zero envelope initialization' );

TutorPress_Live_Lessons_Store_Fake::$cache = array();
$calls    = 0;
$update   = function ( $post_id, $meta_key, $meta_id, $value, $prior ) use ( &$calls ) { $calls++; return TutorPress_Live_Lessons_Store_Fake::update_meta_bytes( $post_id, $meta_key, $meta_id, $value, $prior ); };
$affected = function () { return TutorPress_Live_Lessons_Store_Fake::$affected; };
$synced   = $next;
$synced['state'] = 'synced';
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::exchange( $record, $held, 'other', 'create', 'pending', 1, $bytes, $synced, $read, $update, $affected ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $record, $held, 'token-init', 'update', 'pending', 1, $bytes, $synced, $read, $update, $affected ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $record, $held, 'token-init', 'create', 'error', 1, $bytes, $synced, $read, $update, $affected ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $record, $held, 'token-init', 'create', 'pending', 2, $bytes, $synced, $read, $update, $affected ) && 0 === $calls && $bytes === $read( $record, $key )[0]['meta_value'], 'a token, operation, state, or version mismatch does not update' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::exchange( $record, $held, 'token-init', 'create', 'pending', 1, serialize( $next ), $synced, $read, $update, $affected ) && null === TutorPress_Live_Lesson_Sync_Repository::exchange( $other, $other_lock, 'token-race', 'create', 'pending', 1, $bytes, $synced, $read, $update, $affected ) && 0 === $calls && $bytes === $read( $record, $key )[0]['meta_value'], 'a serialized prior or a second row does not update' );
$empty      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$empty_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $empty, 'create', 'token-empty', '2026-09-21 19:10:00', array( 'TutorPress_Live_Lessons_Store_Fake', 'add_option' ) );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::exchange( $empty, $empty_lock, 'token-empty', 'create', 'pending', 1, $bytes, $synced, $read, $update, $affected ) && 0 === $calls && array() === $read( $empty, $key ), 'a missing row does not update' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Repository::exchange( $record, $held, 'token-init', 'create', 'pending', 1, $bytes, $synced, $read, function () use ( &$calls ) { $calls++; return true; }, function () { return 0; } ) && 1 === $calls && $bytes === $read( $record, $key )[0]['meta_value'], 'an affected count other than one is not an accepted write' );
$written = TutorPress_Live_Lesson_Sync_Repository::exchange( $record, $held, 'token-init', 'create', 'pending', 1, $bytes, $synced, $read, $update, $affected );
tutorpress_live_lessons_assert( $written === TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $synced ) && $written === $read( $record, $key )[0]['meta_value'] && 1 === $synced['version'] && false === strpos( $written, 'a:' ) && 2 === $calls && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && array( $key ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'exact canonical bytes update the one row and require one affected row' );
tutorpress_live_lessons_pass( 'canonical envelope compare-and-swap' );
$absent      = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$absent_lock = TutorPress_Live_Lesson_Sync_Repository::acquire( $absent, 'create', 'token-absent', '2026-09-21 19:10:00', array( 'TutorPress_Live_Lessons_Store_Fake', 'add_option' ) );
$before      = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
tutorpress_live_lessons_assert( array() === $read( $absent, $key ) && 0 === TutorPress_Live_Lesson_Sync_Envelope::expected_version( null ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $absent, $key ), 'no envelope is expected version 0' );
$once        = TutorPress_Live_Lesson_Sync_Repository::initialize( $absent, $absent_lock, 'token-absent', 0, $next, $read, $write, $cache );
$decoded     = is_string( $once ) ? json_decode( $once, true ) : null;
tutorpress_live_lessons_assert( is_array( $decoded ) && 1 === $decoded['version'] && 1 === count( $read( $absent, $key ) ) && null === TutorPress_Live_Lesson_Sync_Repository::initialize( $absent, $absent_lock, 'token-absent', 0, $next, $read, $write, $cache ), 'one locked initialization writes one version-1 row' );
TutorPress_Live_Lessons_Store_Fake::add_meta( $absent, $key, $once );
$duplicate   = $read( $absent, $key );
tutorpress_live_lessons_assert( 2 === count( $duplicate ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $absent, $key ) && $once === $duplicate[0]['meta_value'] && $once === $duplicate[1]['meta_value'] && null === TutorPress_Live_Lesson_Sync_Repository::initialize( $absent, $absent_lock, 'token-absent', 0, $next, $read, $write, $cache ) && 2 === count( $read( $absent, $key ) ), 'a second envelope row stays a conflict' );
tutorpress_live_lessons_assert( $before === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'version-zero initialization does not call a provider' );
tutorpress_live_lessons_pass( 'legacy version-zero repository acceptance' );
