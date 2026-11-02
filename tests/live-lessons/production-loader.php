<?php
/**
 * Test-only ordered production loader for live-lesson cases.
 * Never glob-loads production and never uses Composer.
 */

function tutorpress_live_lessons_production_files() {
	return array(
		'includes/live-lessons/class-tutorpress-live-lesson-datetime.php',
		'includes/live-lessons/class-tutorpress-live-lesson-sync-envelope.php',
		'includes/live-lessons/class-tutorpress-live-lesson-sync-repository.php',
		'includes/live-lessons/interface-tutorpress-live-lesson-provider-adapter.php',
		'includes/live-lessons/class-tutorpress-live-lesson-provider-result.php',
		'includes/live-lessons/class-tutorpress-live-lesson-authorization.php',
		'includes/live-lessons/class-tutorpress-live-lesson-owner-resolver.php',
		'includes/live-lessons/class-tutorpress-google-meet-provider-adapter.php',
		'includes/live-lessons/class-tutorpress-zoom-provider-adapter.php',
		'includes/live-lessons/class-tutorpress-live-lesson-response-projector.php',
		'includes/live-lessons/class-tutorpress-live-lesson-ordering.php',
		'includes/live-lessons/class-tutorpress-live-lesson-collection-query.php',
		'includes/live-lessons/class-tutorpress-live-lesson-topic-projector.php',
		'includes/live-lessons/class-tutorpress-topic-duplication-service.php',
		'includes/live-lessons/class-tutorpress-live-lesson-duplicate-service.php',
		'includes/live-lessons/class-tutorpress-live-lesson-cascade-delete.php',
		'includes/live-lessons/class-tutorpress-live-lesson-sync-orchestrator.php',
	);
}

$tutorpress_live_lessons_root = dirname( dirname( __DIR__ ) );

foreach ( tutorpress_live_lessons_production_files() as $relative ) {
	require_once $tutorpress_live_lessons_root . '/' . $relative;
}

