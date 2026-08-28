<?php
/**
 * Development-only WP71 compatibility fixture helpers.
 * Cleanup is idempotent; callers invoke it from finally.
 */

if ( ! function_exists( 'tutorpress_wp71_compat_assert' ) ) {

	function tutorpress_wp71_compat_fail( $message ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}

	function tutorpress_wp71_compat_pass( $message ) {
		echo 'PASS: ' . $message . PHP_EOL;
	}

	function tutorpress_wp71_compat_assert( $condition, $message ) {
		if ( ! $condition ) {
			tutorpress_wp71_compat_fail( $message );
		}
	}

	function tutorpress_wp71_compat_unique_name() {
		return 'WP71 Compatibility Disposable Foundation ' . wp_generate_uuid4();
	}

	function tutorpress_wp71_compat_registry( $post_id = 0, $reset = false ) {
		static $ids = array();
		if ( $reset ) {
			$ids = array();
			return $ids;
		}
		$post_id = (int) $post_id;
		if ( $post_id > 0 ) {
			$ids[ $post_id ] = $post_id;
		}
		return $ids;
	}

	function tutorpress_wp71_compat_register_post( $post_id ) {
		tutorpress_wp71_compat_registry( (int) $post_id );
	}

	function tutorpress_wp71_compat_cleanup() {
		foreach ( tutorpress_wp71_compat_registry() as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		tutorpress_wp71_compat_registry( 0, true );
	}
}
