<?php

tutorpress_live_lessons_assert( class_exists( 'TutorPress_Live_Lesson_Sync_Envelope' ), 'sync envelope class is loaded' );
$left  = array( 'b' => 1, 'path' => 'a/b', 'note' => 'café', 'nested' => array( 'z' => 1, 'a' => array( array( 'm' => 2, 'b' => 3 ), array( 'b' => 1, 'a' => 2 ) ) ) );
$right = array( 'nested' => array( 'a' => array( array( 'b' => 3, 'm' => 2 ), array( 'a' => 2, 'b' => 1 ) ), 'z' => 1 ), 'note' => 'café', 'path' => 'a/b', 'b' => 1 );
$bytes = TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $left );
tutorpress_live_lessons_assert( is_string( $bytes ) && $bytes === TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $right ), 'equivalent arrays produce identical bytes' );
tutorpress_live_lessons_assert( '{"b":1,"nested":{"a":[{"b":3,"m":2},{"a":2,"b":1}],"z":1},"note":"café","path":"a/b"}' === $bytes, 'keys are ordered recursively, lists keep order, and slashes and utf-8 stay unescaped' );
tutorpress_live_lessons_assert( false === strpos( $bytes, ' ' ) && false === strpos( $bytes, "\n" ) && false === strpos( $bytes, '\/' ) && false === strpos( $bytes, '\u' ), 'json has no insignificant whitespace or escapes' );
tutorpress_live_lessons_pass( 'canonical sync envelope bytes' );
$envelope = array( 'schema_version' => 1, 'version' => 1, 'state' => 'pending', 'provider' => 'zoom', 'owner_id' => 42, 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => null );
$stored   = TutorPress_Live_Lesson_Sync_Envelope::envelope( $envelope );
tutorpress_live_lessons_assert( is_array( $stored ) && 42 === $stored['owner_id'] && is_int( $stored['owner_id'] ) && false !== strpos( TutorPress_Live_Lesson_Sync_Envelope::canonical_bytes( $stored ), '"owner_id":42' ), 'owner storage is a wordpress user id' );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( array_replace( $envelope, array( 'state' => 'error' ) ) ) ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( array_replace( $envelope, array( 'state' => 'unlinked' ) ) ) ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( array_replace( $envelope, array( 'state' => 'synced' ) ) ) ), 'persisted states are pending, error, unlinked, or synced' );
$stable = $envelope;
$stable['state'] = 'stable';
$legacy = $envelope;
$legacy['state'] = 'legacy';
$named = $envelope;
$named['owner_id'] = 'owner@example.com';
$secret = $envelope;
$secret['operation'] = array( 'refresh_token' => 'x', 'api_secret' => 'y' );
$raw = $envelope;
$raw['warning'] = array( 'raw_body' => 'payload', 'exception' => 'trace' );
$open = $envelope;
$open['operation'] = array( 'kind' => 'create' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Envelope::envelope( $stable ) && null === TutorPress_Live_Lesson_Sync_Envelope::envelope( $legacy ) && null === TutorPress_Live_Lesson_Sync_Envelope::envelope( $named ) && null === TutorPress_Live_Lesson_Sync_Envelope::envelope( $secret ) && null === TutorPress_Live_Lesson_Sync_Envelope::envelope( $raw ), 'invalid states, credential keys, tokens, and raw provider data are rejected' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Envelope::envelope( $open ), 'an incomplete operation is rejected' );
tutorpress_live_lessons_pass( 'stable sync envelope schema' );
tutorpress_live_lessons_assert( 0 === TutorPress_Live_Lesson_Sync_Envelope::expected_version( null ) && 1 === TutorPress_Live_Lesson_Sync_Envelope::expected_version( $envelope ) && null === TutorPress_Live_Lesson_Sync_Envelope::envelope( array_replace( $envelope, array( 'version' => 0 ) ) ), 'a missing envelope is expected version 0 and a persisted version 0 is rejected' );
$initialized = TutorPress_Live_Lesson_Sync_Envelope::initialize_version_one( null, $envelope );
$verified    = array_replace( $envelope, array( 'provider_id' => 'evt-1' ) );
tutorpress_live_lessons_assert( is_array( $initialized ) && 1 === $initialized['version'] && null === $initialized['operation'] && null === TutorPress_Live_Lesson_Sync_Envelope::initialize_version_one( $initialized, $envelope ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( $verified ) ) && 'evt-1' === TutorPress_Live_Lesson_Sync_Envelope::envelope( $verified )['provider_id'], 'absence initializes one version-1 envelope and a stored provider id may remain' );
tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => null, 'provider_id' => null, 'version' => 1 ) ) ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => 'op-1', 'provider_id' => 'evt-1', 'version' => 1 ) ) ) && is_array( TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => null, 'provider_id' => 'evt-1', 'version' => 0 ) ) ) && null === TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => null, 'provider_id' => 'evt-1', 'version' => 1 ) ) && null === TutorPress_Live_Lesson_Sync_Envelope::identity_request( array( 'operation_id' => 'op-1', 'provider_id' => 'evt-1', 'version' => 0 ) ), 'ordinary create/edit rejects a provider id and mixed attach shapes are rejected' );
tutorpress_live_lessons_pass( 'version zero and attach shapes' );
foreach ( array( 'pending', 'error', 'unlinked', 'synced' ) as $state ) {
	tutorpress_live_lessons_assert( is_array( TutorPress_Live_Lesson_Sync_Envelope::envelope( array_replace( $envelope, array( 'state' => $state, 'version' => 2 ) ) ) ), 'persisted transition stays ' . $state );
}
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Envelope::envelope( array_replace( $envelope, array( 'state' => 'stable', 'version' => 2 ) ) ) && null === TutorPress_Live_Lesson_Sync_Envelope::envelope( array_replace( $envelope, array( 'state' => 'legacy', 'version' => 2 ) ) ), 'a transition cannot become stable or legacy' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Sync_Envelope::envelope( null ) && 0 === TutorPress_Live_Lesson_Sync_Envelope::expected_version( null ), 'absence is a version-zero projection and not a persisted envelope' );
tutorpress_live_lessons_pass( 'persisted transitions and version-zero projection' );
