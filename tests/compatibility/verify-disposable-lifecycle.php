<?php
/**
 * Generic WP71 disposable courses lifecycle fixture.
 * Creates a uniquely named draft, verifies WP/REST read-back, force-deletes in finally.
 */

require_once __DIR__ . '/bootstrap.php';

$post_id = 0;
$title   = tutorpress_wp71_compat_unique_name();

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
	update_post_meta( $post_id, '_wp71_compat_foundation_sentinel', '1' );

	$post = get_post( $post_id );
	tutorpress_wp71_compat_assert( $post instanceof WP_Post, 'WordPress read-back missing post' );
	tutorpress_wp71_compat_assert( 'courses' === $post->post_type && 'draft' === $post->post_status, 'WordPress read-back type/status mismatch' );
	tutorpress_wp71_compat_assert( $title === $post->post_title && (int) $post->post_author === $actor_id, 'WordPress read-back title/author mismatch' );
	tutorpress_wp71_compat_assert( '1' === get_post_meta( $post_id, '_wp71_compat_foundation_sentinel', true ), 'sentinel metadata missing' );

	$request = new WP_REST_Request( 'GET', '/wp/v2/courses/' . $post_id );
	$request->set_param( 'context', 'edit' );
	$response = rest_do_request( $request );
	tutorpress_wp71_compat_assert( 200 === $response->get_status() && ! $response->is_error(), 'REST read-back failed' );
	$data       = $response->get_data();
	$rest_title = isset( $data['title']['raw'] ) ? $data['title']['raw'] : '';
	tutorpress_wp71_compat_assert(
		(int) $data['id'] === $post_id && 'draft' === $data['status'] && 'courses' === $data['type'] && $title === $rest_title && (int) $data['author'] === $actor_id,
		'REST response mismatch'
	);

	tutorpress_wp71_compat_pass( 'lifecycle create/read id=' . $post_id . ' actor=' . $actor_id . ' title=' . $title );
} finally {
	tutorpress_wp71_compat_cleanup();
}

tutorpress_wp71_compat_assert( ! get_post( $post_id ), 'disposable post remains after cleanup' );
tutorpress_wp71_compat_assert( '' === (string) get_post_meta( $post_id, '_wp71_compat_foundation_sentinel', true ), 'sentinel metadata remains after cleanup' );
tutorpress_wp71_compat_pass( 'lifecycle cleanup' );
