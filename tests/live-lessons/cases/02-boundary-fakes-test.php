<?php

TutorPress_Live_Lessons_Boundary_Fake::link( 3, 2, 1 );
tutorpress_live_lessons_assert( array( 2, 1 ) === TutorPress_Live_Lessons_Boundary_Fake::topic_course( 3 ) && array( 0, 0 ) === TutorPress_Live_Lessons_Boundary_Fake::topic_course( 9 ), 'item maps to topic and course' );
TutorPress_Live_Lessons_Boundary_Fake::allow( 'edit_post', 1, true );
tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Boundary_Fake::can( 'edit_post', 1 ) && false === TutorPress_Live_Lessons_Boundary_Fake::can( 'edit_post', 9 ), 'capability results are scripted' );
TutorPress_Live_Lessons_Boundary_Fake::$now   = '2026-01-01 00:00:00';
TutorPress_Live_Lessons_Boundary_Fake::$uuids = array( 'fixed-uuid' );
tutorpress_live_lessons_assert( '2026-01-01 00:00:00' === TutorPress_Live_Lessons_Boundary_Fake::$now && 'fixed-uuid' === TutorPress_Live_Lessons_Boundary_Fake::uuid() && '' === TutorPress_Live_Lessons_Boundary_Fake::uuid(), 'clock and uuid are injected' );

try {
	TutorPress_Live_Lessons_Boundary_Fake::switch_to( 7, function () {
		tutorpress_live_lessons_assert( 7 === TutorPress_Live_Lessons_Boundary_Fake::$user, 'user switched' );
		throw new RuntimeException( 'switch boom' );
	} );
	tutorpress_live_lessons_fail( 'switch throw did not fire' );
} catch ( RuntimeException $e ) {
	tutorpress_live_lessons_assert( 0 === TutorPress_Live_Lessons_Boundary_Fake::$user && 'switch boom' === $e->getMessage(), 'user restored after throw' );
}

try {
	TutorPress_Live_Lessons_Boundary_Fake::call( 'google', array( 'api_secret' => 'fake-credential-value' ) );
	tutorpress_live_lessons_fail( 'provider did not fail' );
} catch ( TutorPress_Live_Lessons_Assertion_Failure $e ) {
	tutorpress_live_lessons_assert( 'unstubbed provider access' === $e->getMessage() && array( 'google' ) === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'unscripted provider hard-fail redacts the call' );
}

TutorPress_Live_Lessons_Boundary_Fake::script( 'zoom', function ( $outcome ) { return $outcome; }, array( array( 'id' => 'm1' ) ) );
tutorpress_live_lessons_assert( array( 'id' => 'm1' ) === TutorPress_Live_Lessons_Boundary_Fake::call( 'zoom' ) && array( 'google', 'zoom' ) === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'scripted factory returns the queued outcome' );

tutorpress_live_lessons_pass( 'boundary fakes: relations, capabilities, clock, user, and provider' );
