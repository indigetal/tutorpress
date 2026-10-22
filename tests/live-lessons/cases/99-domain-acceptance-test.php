<?php

$ledger = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$post   = TutorPress_Live_Lessons_Store_Fake::insert_publish_post();
$key    = '_tutorpress_live_lesson_sync';
$read   = array( 'TutorPress_Live_Lessons_Store_Fake', 'meta_rows' );
tutorpress_live_lessons_assert( array() === $read( $post, $key ) && 0 === TutorPress_Live_Lesson_Sync_Envelope::expected_version( null ) && null === TutorPress_Live_Lesson_Sync_Envelope::envelope( null ), 'a publish post with no sync envelope is expected version 0' );
$legacy    = array( 'operation_id' => null, 'provider_id' => 'evt-1', 'version' => 0 );
$idless    = array( 'operation_id' => 'op-1', 'provider_id' => null, 'version' => 1 );
$operation = array( 'operation_id' => 'op-1', 'kind' => 'create', 'started_at' => '2026-09-21 15:04:00', 'base_version' => 1, 'provider' => 'zoom', 'owner_id' => 42, 'provider_id' => null, 'dispatch_state' => 'not_dispatched', 'course_id' => 7, 'topic_id' => 8, 'post_author' => 42, 'menu_order' => 3, 'title' => 'Live', 'content' => 'Notes', 'start_local' => '2026-09-21 15:04:00', 'end_local' => '2026-09-21 16:04:00', 'timezone' => 'UTC', 'duration' => array( 'value' => 60, 'unit' => 'minutes' ), 'provider_target' => array( 'auto_recording' => 'none', 'enforce_login' => false, 'host_id' => 'host-1', 'host_video' => false, 'join_before_host' => true, 'mute_upon_entry' => false, 'participant_video' => false, 'waiting_room' => true ), 'delete_hook' => null );
$pending   = TutorPress_Live_Lesson_Sync_Envelope::envelope( array( 'schema_version' => 1, 'version' => 1, 'state' => 'pending', 'provider' => 'zoom', 'owner_id' => 42, 'provider_id' => null, 'provenance' => null, 'error' => null, 'warning' => null, 'reconciliation_required' => false, 'operation' => $operation ) );
tutorpress_live_lessons_assert( $legacy === TutorPress_Live_Lesson_Sync_Envelope::identity_request( $legacy ) && null === TutorPress_Live_Lesson_Sync_Envelope::identity_request( $idless ) && is_array( $pending ) && 1 === TutorPress_Live_Lesson_Sync_Envelope::expected_version( $pending ) && null === $pending['provider_id'] && 'not_dispatched' === $pending['operation']['dispatch_state'], 'legacy version 0 is distinct from an id-less uncertain create' );
$lock  = TutorPress_Live_Lesson_Sync_Repository::acquire( $post, 'create', 'token-accept', '2026-09-21 19:10:00', array( 'TutorPress_Live_Lessons_Store_Fake', 'add_option' ) );
$next  = array( 'error' => null, 'operation' => null, 'owner_id' => 42, 'provenance' => null, 'provider' => 'google', 'provider_id' => null, 'reconciliation_required' => false, 'schema_version' => 1, 'state' => 'pending', 'version' => 1, 'warning' => null );
$write = array( 'TutorPress_Live_Lessons_Store_Fake', 'add_meta' );
$cache = function ( $meta_key ) { TutorPress_Live_Lessons_Store_Fake::$cache[] = $meta_key; };
$bytes = TutorPress_Live_Lesson_Sync_Repository::initialize( $post, $lock, 'token-accept', 0, $next, $read, $write, $cache );
tutorpress_live_lessons_assert( is_string( $bytes ) && 1 === count( $read( $post, $key ) ) && 1 === json_decode( $bytes, true )['version'] && null === TutorPress_Live_Lesson_Sync_Repository::initialize( $post, $lock, 'token-accept', 0, $next, $read, $write, $cache ), 'absence initializes one version-1 row' );
TutorPress_Live_Lessons_Store_Fake::add_meta( $post, $key, $bytes );
tutorpress_live_lessons_assert( 2 === count( $read( $post, $key ) ) && null === TutorPress_Live_Lessons_Store_Fake::meta_bytes( $post, $key ) && null === TutorPress_Live_Lesson_Sync_Repository::initialize( $post, $lock, 'token-accept', 0, $next, $read, $write, $cache ) && 2 === count( $read( $post, $key ) ), 'duplicate envelope rows stay a conflict' );
$can      = static function () { return true; };
$course   = TutorPress_Live_Lesson_Authorization::authorize_course( array( 'ID' => 1, 'post_type' => 'courses' ), $can );
$owner    = TutorPress_Live_Lesson_Owner_Resolver::legacy_candidates( $course, 'google', array( array( 'id' => 7, 'label' => 'Ada', 'source' => 'acting_user' ), array( 'id' => 8, 'label' => 'Bea', 'source' => 'acting_user' ) ), null );
$identity = TutorPress_Live_Lesson_Owner_Resolver::legacy_candidates( $course, 'zoom', array( array( 'id' => 4, 'label' => 'Author', 'source' => 'course_author' ) ), null );
tutorpress_live_lessons_assert( 'legacy_owner_resolution_required' === $owner['code'] && null === $owner['owner_id'] && 'legacy_identity_resolution_required' === $identity['code'] && 4 === $identity['owner_id'] && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'unresolved owner or identity makes no provider call' );
tutorpress_live_lessons_pass( 'legacy version-zero acceptance' );
$domain = array( 'TutorPress_Live_Lesson_Datetime', 'TutorPress_Live_Lesson_Sync_Envelope', 'TutorPress_Live_Lesson_Sync_Repository', 'TutorPress_Live_Lesson_Provider_Result', 'TutorPress_Live_Lesson_Authorization', 'TutorPress_Live_Lesson_Owner_Resolver', 'TutorPress_Google_Meet_Provider_Adapter', 'TutorPress_Zoom_Provider_Adapter' );
$found  = array();
foreach ( $domain as $class_name ) {
	$found[] = class_exists( $class_name, false );
}
tutorpress_live_lessons_assert( array_fill( 0, 8, true ) === $found && interface_exists( 'TutorPress_Live_Lesson_Provider_Adapter', false ), 'dormant domain classes and the provider interface are loaded' );
tutorpress_live_lessons_assert( ! class_exists( 'TutorPress_REST_Live_Lessons_Controller', false ) && ! class_exists( 'Composer\\Autoload\\ClassLoader', false ) && ! class_exists( 'Google\\Client', false ), 'the domain run loads no controller, Composer, or Google client' );
tutorpress_live_lessons_assert( method_exists( 'TutorPress_Live_Lesson_Datetime', 'parse_wall_clock' ) && method_exists( 'TutorPress_Live_Lesson_Datetime', 'zoom_duration' ) && method_exists( 'TutorPress_Live_Lesson_Sync_Envelope', 'expected_version' ) && method_exists( 'TutorPress_Live_Lesson_Owner_Resolver', 'legacy_candidates' ) && method_exists( 'TutorPress_Google_Meet_Provider_Adapter', 'settings' ) && method_exists( 'TutorPress_Google_Meet_Provider_Adapter', 'metadata_map' ) && in_array( 'TutorPress_Live_Lesson_Provider_Adapter', class_implements( 'TutorPress_Google_Meet_Provider_Adapter' ), true ) && method_exists( 'TutorPress_Zoom_Provider_Adapter', 'metadata_map' ) && in_array( 'TutorPress_Live_Lesson_Provider_Adapter', class_implements( 'TutorPress_Zoom_Provider_Adapter' ), true ) && false === method_exists( 'TutorPress_Zoom_Provider_Adapter', 'persist_metadata' ) && method_exists( 'TutorPress_Live_Lesson_Provider_Adapter', 'hydrate_item' ), 'datetime, metadata, owner, provider, and settings contracts are present' );
$list = implode( "\n", tutorpress_live_lessons_production_files() );
tutorpress_live_lessons_assert( 18 === count( tutorpress_live_lessons_production_files() ) && false === strpos( $list, 'class-live-lessons-controller.php' ) && false === strpos( $list, 'vendor/' ) && false === strpos( $list, 'references/tutor-pro/' ), 'the production list stays eighteen dormant files' );
tutorpress_live_lessons_pass( 'dormant domain contract checklist' );
$files = tutorpress_live_lessons_production_files();
$domain_classes = array( 'TutorPress_Google_Meet_Provider_Adapter', 'TutorPress_Zoom_Provider_Adapter', 'TutorPress_Live_Lessons_Adapter_Fake' );
$domain_match = true;
foreach ( $domain_classes as $class_name ) { $domain_match = $domain_match && array( 'TutorPress_Live_Lesson_Provider_Adapter' => 'TutorPress_Live_Lesson_Provider_Adapter' ) === class_implements( $class_name ) && false === method_exists( $class_name, 'persist_metadata' ) && false === method_exists( $class_name, 'extract_identity' ); }
tutorpress_live_lessons_assert( 18 === count( $files ) && 'includes/live-lessons/class-tutorpress-live-lesson-response-projector.php' === $files[10] && 'includes/live-lessons/class-tutorpress-live-lesson-ordering.php' === $files[11] && 'includes/live-lessons/class-tutorpress-live-lesson-collection-query.php' === $files[12] && 'includes/live-lessons/class-tutorpress-live-lesson-topic-projector.php' === $files[13] && 'includes/live-lessons/class-tutorpress-topic-duplication-service.php' === $files[14] && 'includes/live-lessons/class-tutorpress-live-lesson-duplicate-service.php' === $files[15] && 'includes/live-lessons/class-tutorpress-live-lesson-cascade-delete.php' === $files[16] && 'includes/live-lessons/class-tutorpress-live-lesson-sync-orchestrator.php' === $files[17] && $domain_match, 'the dormant loader, adapters, and fake stay on the shared interface' );
tutorpress_live_lessons_pass( 'dormant domain revalidation' );
$acceptance_order = array( 'includes/live-lessons/class-tutorpress-live-lesson-datetime.php', 'includes/live-lessons/class-tutorpress-live-lesson-sync-envelope.php', 'includes/live-lessons/class-tutorpress-live-lesson-sync-repository.php', 'includes/live-lessons/interface-tutorpress-live-lesson-provider-adapter.php', 'includes/live-lessons/class-tutorpress-live-lesson-provider-result.php', 'includes/live-lessons/class-tutorpress-live-lesson-authorization.php', 'includes/live-lessons/class-tutorpress-live-lesson-owner-resolver.php', 'includes/live-lessons/class-tutorpress-google-meet-provider-adapter.php', 'includes/live-lessons/class-tutorpress-zoom-provider-adapter.php', 'includes/live-lessons/class-tutorpress-live-lesson-cutover.php', 'includes/live-lessons/class-tutorpress-live-lesson-response-projector.php', 'includes/live-lessons/class-tutorpress-live-lesson-ordering.php', 'includes/live-lessons/class-tutorpress-live-lesson-collection-query.php', 'includes/live-lessons/class-tutorpress-live-lesson-topic-projector.php', 'includes/live-lessons/class-tutorpress-topic-duplication-service.php', 'includes/live-lessons/class-tutorpress-live-lesson-duplicate-service.php', 'includes/live-lessons/class-tutorpress-live-lesson-cascade-delete.php', 'includes/live-lessons/class-tutorpress-live-lesson-sync-orchestrator.php' );
$acceptance_classes = array( 'TutorPress_Live_Lesson_Datetime', 'TutorPress_Live_Lesson_Sync_Envelope', 'TutorPress_Live_Lesson_Sync_Repository', 'TutorPress_Live_Lesson_Provider_Result', 'TutorPress_Live_Lesson_Authorization', 'TutorPress_Live_Lesson_Owner_Resolver', 'TutorPress_Google_Meet_Provider_Adapter', 'TutorPress_Zoom_Provider_Adapter', 'TutorPress_Live_Lesson_Cutover', 'TutorPress_Live_Lesson_Response_Projector', 'TutorPress_Live_Lesson_Ordering', 'TutorPress_Live_Lesson_Collection_Query', 'TutorPress_Live_Lesson_Topic_Projector', 'TutorPress_Topic_Duplication_Service', 'TutorPress_Live_Lesson_Duplicate_Service', 'TutorPress_Live_Lesson_Cascade_Delete', 'TutorPress_Live_Lesson_Sync_Orchestrator' );
$acceptance_loaded = interface_exists( 'TutorPress_Live_Lesson_Provider_Adapter', false );
foreach ( $acceptance_classes as $acceptance_class ) {
	$acceptance_loaded = $acceptance_loaded && class_exists( $acceptance_class, false );
}
$acceptance_methods = array( 'authorize', 'authorize_course', 'authorize_create', 'authorize_projection', 'authorize_duplicate', 'authorize_cascade' );
$acceptance_exposed = true;
foreach ( $acceptance_methods as $acceptance_method ) {
	$acceptance_exposed = $acceptance_exposed && method_exists( 'TutorPress_Live_Lesson_Authorization', $acceptance_method );
}
$acceptance_course = array( 'ID' => 7, 'post_type' => 'courses' );
$acceptance_topic  = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$acceptance_item   = array( 'ID' => 21, 'post_type' => 'tutor-google-meet', 'post_parent' => 8 );
$acceptance_child  = array( 'item' => $acceptance_item, 'topic' => $acceptance_topic );
$acceptance_call   = static function ( $can ) use ( $acceptance_item, $acceptance_topic, $acceptance_course, $acceptance_child ) {
	return array(
		TutorPress_Live_Lesson_Authorization::authorize( $acceptance_item, $acceptance_topic, $acceptance_course, $can ),
		TutorPress_Live_Lesson_Authorization::authorize_course( $acceptance_course, $can ),
		TutorPress_Live_Lesson_Authorization::authorize_create( $acceptance_topic, $acceptance_course, $can ),
		TutorPress_Live_Lesson_Authorization::authorize_projection( $acceptance_topic, $acceptance_course, $can ),
		TutorPress_Live_Lesson_Authorization::authorize_duplicate( $acceptance_topic, $acceptance_course, $acceptance_course, $can ),
		TutorPress_Live_Lesson_Authorization::authorize_cascade( $acceptance_topic, $acceptance_course, array( $acceptance_child ), $can ),
	);
};
$acceptance_root    = dirname( __DIR__, 3 );
$acceptance_classmap = file_get_contents( $acceptance_root . '/vendor/composer/autoload_classmap.php' );
$acceptance_static   = file_get_contents( $acceptance_root . '/vendor/composer/autoload_static.php' );
$acceptance_list     = tutorpress_live_lessons_production_files();
$acceptance_mapped   = is_string( $acceptance_classmap ) && is_string( $acceptance_static );
$acceptance_text     = implode( "\n", $acceptance_list );
foreach ( $acceptance_list as $acceptance_path ) {
	$acceptance_mapped = $acceptance_mapped && false !== strpos( $acceptance_classmap, $acceptance_path ) && false !== strpos( $acceptance_static, $acceptance_path );
}
tutorpress_live_lessons_assert( false === TutorPress_Live_Lesson_Cutover::ENABLED && $acceptance_loaded && $acceptance_exposed && array( null, null, null, null, null, null ) === $acceptance_call( static function ( $capability ) { return 'edit_posts' === $capability; } ) && array( 7, 7, 7, 7, 7, 7 ) === $acceptance_call( static function ( $capability, $id = 0 ) { return 'edit_post' === $capability && 7 === (int) $id; } ) && $acceptance_order === $acceptance_list && $acceptance_mapped && false === strpos( $acceptance_text, 'class-live-lessons-controller.php' ) && false === strpos( $acceptance_text, 'class-topics-controller.php' ) && false === strpos( $acceptance_text, 'vendor/' ) && false === strpos( $acceptance_text, 'references/tutor-pro/' ) && ! class_exists( 'TutorPress_REST_Live_Lessons_Controller', false ) && ! class_exists( 'TutorPress_REST_Topics_Controller', false ) && ! class_exists( 'Composer\\Autoload\\ClassLoader', false ) && ! class_exists( 'Google\\Client', false ), 'false-gate acceptance keeps the domain dormant' );
tutorpress_live_lessons_pass( 'false-gate acceptance keeps the domain dormant' );
