<?php
/**
 * Dependency-free live-lesson test runner.
 * Loads neither WordPress nor Composer.
 */

require_once __DIR__ . '/bootstrap.php';

tutorpress_live_lessons_run(
	function () {
		$fakes  = __DIR__ . '/fakes.php';
		$loader = __DIR__ . '/production-loader.php';

		if ( is_readable( $fakes ) ) {
			require_once $fakes;
		}

		if ( is_readable( $loader ) ) {
			require_once $loader;
		}

		$adapter_fake = __DIR__ . '/provider-adapter-fake.php';
		if ( is_readable( $adapter_fake ) ) {
			require_once $adapter_fake;
		}

		$cases = glob( __DIR__ . '/cases/*.php' );
		$cases = is_array( $cases ) ? $cases : array();
		sort( $cases, SORT_STRING );

		foreach ( $cases as $case ) {
			require $case;
		}
	}
);
