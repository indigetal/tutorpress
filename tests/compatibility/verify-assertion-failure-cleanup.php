<?php
/**
 * Intentional WP71 assertion-failure cleanup child.
 * Creates a token-titled courses draft, registers it, writes the token sentinel, then fails.
 */

require_once __DIR__ . '/bootstrap.php';

$fixture_args = isset( $args ) && is_array( $args ) ? $args : array();

tutorpress_wp71_compat_run(
	function () use ( $fixture_args ) {
		tutorpress_wp71_compat_assert( 1 === count( $fixture_args ), 'exactly one token argument is required' );
		$token = $fixture_args[0];
		tutorpress_wp71_compat_assert(
			is_string( $token ) && 1 === preg_match( '/\A[a-f0-9]{32}\z/', $token ),
			'token must be 32 lowercase hexadecimal characters'
		);

		$post_id = 0;
		$title   = 'WP71 Compatibility Disposable Foundation Failure ' . $token;

		try {
			$actor_id = 0;
			foreach ( get_users( array( 'fields' => 'ids', 'orderby' => 'ID' ) ) as $uid ) {
				if ( user_can( $uid, 'edit_courses' ) || user_can( $uid, 'manage_options' ) ) {
					$actor_id = (int) $uid;
					break;
				}
			}
			tutorpress_wp71_compat_assert( $actor_id > 0, 'no capable local actor found' );
			wp_set_current_user( $actor_id );

			$post_id = wp_insert_post(
				array(
					'post_type'   => 'courses',
					'post_status' => 'draft',
					'post_title'  => $title,
					'post_author' => $actor_id,
				),
				true
			);
			tutorpress_wp71_compat_assert( ! is_wp_error( $post_id ) && (int) $post_id > 0, 'failed to create disposable courses draft' );
			$post_id = (int) $post_id;
			tutorpress_wp71_compat_register_post( $post_id );
			update_post_meta( $post_id, '_wp71_compat_foundation_sentinel', $token );
			tutorpress_wp71_compat_assert( $token === get_post_meta( $post_id, '_wp71_compat_foundation_sentinel', true ), 'sentinel metadata missing' );

			tutorpress_wp71_compat_assert( false, 'intentional assertion cleanup probe token=' . $token );
		} finally {
			tutorpress_wp71_compat_cleanup();
		}
	}
);
