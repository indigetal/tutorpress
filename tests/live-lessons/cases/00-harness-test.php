<?php

tutorpress_live_lessons_assert(
	function_exists( 'tutorpress_live_lessons_production_files' ),
	'production loader is available'
);

$files = tutorpress_live_lessons_production_files();
tutorpress_live_lessons_assert( array( 'includes/live-lessons/class-tutorpress-live-lesson-datetime.php', 'includes/live-lessons/class-tutorpress-live-lesson-sync-envelope.php', 'includes/live-lessons/class-tutorpress-live-lesson-sync-repository.php', 'includes/live-lessons/interface-tutorpress-live-lesson-provider-adapter.php', 'includes/live-lessons/class-tutorpress-live-lesson-provider-result.php', 'includes/live-lessons/class-tutorpress-live-lesson-authorization.php', 'includes/live-lessons/class-tutorpress-live-lesson-owner-resolver.php', 'includes/live-lessons/class-tutorpress-google-meet-provider-adapter.php', 'includes/live-lessons/class-tutorpress-zoom-provider-adapter.php', 'includes/live-lessons/class-tutorpress-live-lesson-response-projector.php', 'includes/live-lessons/class-tutorpress-live-lesson-ordering.php', 'includes/live-lessons/class-tutorpress-live-lesson-collection-query.php', 'includes/live-lessons/class-tutorpress-live-lesson-topic-projector.php', 'includes/live-lessons/class-tutorpress-topic-duplication-service.php', 'includes/live-lessons/class-tutorpress-live-lesson-duplicate-service.php', 'includes/live-lessons/class-tutorpress-live-lesson-cascade-delete.php', 'includes/live-lessons/class-tutorpress-live-lesson-sync-orchestrator.php' ) === $files, 'production file list is datetime, the sync envelope, the sync repository, the provider adapter, the provider result, authorization, the owner resolver, the Google adapter, the Zoom adapter, the cutover gate, the response projector, ordering, the collection query, the topic projector, the topic duplication service, the direct duplicate service, the cascade delete service, then the sync orchestrator' );

tutorpress_live_lessons_assert(
	'00-harness-test.php' === basename( __FILE__ ),
	'harness case sorts first lexicographically'
);

$continued = false;
try {
	tutorpress_live_lessons_fail( 'probe' );
	$continued = true;
} catch ( TutorPress_Live_Lessons_Assertion_Failure $e ) {
	tutorpress_live_lessons_assert( 'probe' === $e->getMessage(), 'fail-fast message is preserved' );
}
tutorpress_live_lessons_assert( false === $continued, 'fail does not continue' );

tutorpress_live_lessons_assert( defined( 'ABSPATH' ) && 'x' === ( new WP_Error( 'x', 'y' ) )->get_error_code() && new WP_REST_Request() instanceof WP_REST_Request && new WP_REST_Response() instanceof WP_REST_Response, 'ABSPATH and error/request/response shapes' );
do_action( 'tutorpress_test', 1 );
tutorpress_live_lessons_assert( array( 'tutorpress_test', array( 1 ) ) === TutorPress_Live_Lessons_Hook_Fake::$calls[0], 'hook recorded' );
TutorPress_Live_Lessons_Hook_Fake::$throws['tutorpress_test'] = array( new RuntimeException( 'hook boom' ) );
try {
	do_action( 'tutorpress_test' );
	tutorpress_live_lessons_fail( 'hook throw did not fire' );
} catch ( RuntimeException $e ) {
	tutorpress_live_lessons_assert( 'hook boom' === $e->getMessage(), 'hook throw queue' );
}
try {
	wp_remote_request( 'https://example.com', array( 'api_secret' => 'fake-credential-value' ) );
	tutorpress_live_lessons_fail( 'http boundary did not fail' );
} catch ( TutorPress_Live_Lessons_Assertion_Failure $e ) {
	tutorpress_live_lessons_assert( 'unstubbed http access' === $e->getMessage(), 'http hard-fail omits credentials' );
}

$files = tutorpress_live_lessons_production_files();
tutorpress_live_lessons_assert( 17 === count( $files ) && 'includes/live-lessons/class-tutorpress-live-lesson-response-projector.php' === $files[9] && 'includes/live-lessons/class-tutorpress-live-lesson-ordering.php' === $files[10] && 'includes/live-lessons/class-tutorpress-live-lesson-collection-query.php' === $files[11] && 'includes/live-lessons/class-tutorpress-live-lesson-topic-projector.php' === $files[12] && 'includes/live-lessons/class-tutorpress-topic-duplication-service.php' === $files[13] && 'includes/live-lessons/class-tutorpress-live-lesson-duplicate-service.php' === $files[14] && 'includes/live-lessons/class-tutorpress-live-lesson-cascade-delete.php' === $files[15] && 'includes/live-lessons/class-tutorpress-live-lesson-sync-orchestrator.php' === $files[16] && false === strpos( implode( ' ', $files ), 'provider-adapter-fake.php' ), 'production loader stays eighteen files and excludes the adapter fake' );
$adapter = new TutorPress_Live_Lessons_Adapter_Fake();
tutorpress_live_lessons_assert( $adapter instanceof TutorPress_Live_Lesson_Provider_Adapter, 'adapter fake implements the production interface' );
TutorPress_Live_Lessons_Adapter_Fake::$calls = array();
TutorPress_Live_Lessons_Adapter_Fake::$queue = array(
	'capabilities' => array( array( 'settings' => true, 'hosts' => false, 'delete_convergence' => true ) ),
	'settings'     => array( null ),
	'list_hosts'   => array( array( 'outcome' => 'not_applicable', 'hosts' => array() ) ),
	'create'       => array( array( 'outcome' => 'confirmed', 'provider_id' => 'id-1', 'item' => null, 'diagnostics' => array(), 'provenance' => 'create' ) ),
);
tutorpress_live_lessons_assert( array( 'settings' => true, 'hosts' => false, 'delete_convergence' => true ) === $adapter->capabilities() && null === $adapter->settings( 7 ) && array( 'outcome' => 'not_applicable', 'hosts' => array() ) === $adapter->list_hosts( 7 ) && 'id-1' === $adapter->create( 7, array( 'title' => 'Lesson', 'api_secret' => 'fake-credential-value' ) )['provider_id'], 'scripted capability, settings, host, and result returns' );
tutorpress_live_lessons_assert( array( array( 'capabilities', array() ), array( 'settings', array( 'owner_id' => 7 ) ), array( 'list_hosts', array( 'owner_id' => 7 ) ), array( 'create', array( 'owner_id' => 7, 'target' => array( 'title' => 'Lesson' ) ) ) ) === TutorPress_Live_Lessons_Adapter_Fake::$calls, 'ledger keeps method, owner, and target and drops the credential' );
try {
	$adapter->fetch( 7, 'id-1' );
	tutorpress_live_lessons_fail( 'unstubbed adapter did not fail' );
} catch ( TutorPress_Live_Lessons_Assertion_Failure $e ) {
	tutorpress_live_lessons_assert( 'unstubbed adapter fetch' === $e->getMessage() && false === strpos( $e->getMessage(), 'fake-credential-value' ) && false === strpos( $e->getMessage(), 'id-1' ), 'unstubbed adapter failure omits operand values' );
}
tutorpress_live_lessons_pass( 'harness loader, fakes, and fail-fast' );
