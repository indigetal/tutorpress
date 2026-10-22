<?php
/**
 * Development-only live-lesson assertion helpers.
 * Loads neither WordPress nor Composer.
 */

if ( ! function_exists( 'tutorpress_live_lessons_assert' ) ) {

	class TutorPress_Live_Lessons_Assertion_Failure extends RuntimeException {}

	function tutorpress_live_lessons_fail( $message ) {
		throw new TutorPress_Live_Lessons_Assertion_Failure( $message );
	}

	function tutorpress_live_lessons_run( $callback ) {
		try {
			$callback();
		} catch ( TutorPress_Live_Lessons_Assertion_Failure $e ) {
			fwrite( STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL );
			exit( 1 );
		}
	}

	function tutorpress_live_lessons_pass( $message ) {
		echo 'PASS: ' . $message . PHP_EOL;
	}

	function tutorpress_live_lessons_assert( $condition, $message ) {
		if ( ! $condition ) {
			tutorpress_live_lessons_fail( $message );
		}
	}
}
