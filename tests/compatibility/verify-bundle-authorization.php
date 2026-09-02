<?php
/**
 * WP71 Bundle authorization regression fixture.
 */

require_once __DIR__ . '/bootstrap.php';

function tutorpress_wp71_compat_insert_bundle_record( $type, $author ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'draft',
			'post_title'   => tutorpress_wp71_compat_unique_name(),
			'post_author'  => $author,
			'post_content' => '',
		),
		true
	);
	tutorpress_wp71_compat_assert( ! is_wp_error( $post_id ) && (int) $post_id > 0, 'failed to create disposable ' . $type );
	$post_id = (int) $post_id;
	tutorpress_wp71_compat_register_post( $post_id );
	update_post_meta( $post_id, '_wp71_compat_foundation_sentinel', '1' );
	return $post_id;
}

function tutorpress_wp71_compat_core_bundle_request( $method, $post_id, $context, $params = array() ) {
	$object  = get_post_type_object( get_post_type( $post_id ) );
	$base    = ( $object && $object->rest_base ) ? $object->rest_base : get_post_type( $post_id );
	$request = new WP_REST_Request( $method, '/wp/v2/' . $base . '/' . (int) $post_id );
	$request->set_param( 'context', $context );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
}

function tutorpress_wp71_compat_bundle_request( $method, $route, $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
}

tutorpress_wp71_compat_run(
	function () {
		try {
			wp_set_current_user( 3 );
			$course_type = get_post_type_object( 'courses' );
			$bundle_type = get_post_type_object( 'course-bundle' );
			tutorpress_wp71_compat_assert(
				$course_type && $bundle_type && user_can( 3, $course_type->cap->create_posts ) && user_can( 3, $course_type->cap->publish_posts ) && user_can( 3, $bundle_type->cap->create_posts ) && user_can( 3, $bundle_type->cap->publish_posts ),
				'instructor missing course/bundle create/publish caps'
			);

			$course_id  = tutorpress_wp71_compat_insert_bundle_record( 'courses', 3 );
			$course_pub = wp_update_post( array( 'ID' => $course_id, 'post_status' => 'publish' ), true );
			$course     = get_post( $course_id );
			tutorpress_wp71_compat_assert( ! is_wp_error( $course_pub ) && $course && 'courses' === $course->post_type && 3 === (int) $course->post_author && 'publish' === $course->post_status, 'course publish/type/author mismatch' );

			$bundle_id  = tutorpress_wp71_compat_insert_bundle_record( 'course-bundle', 3 );
			$bundle_pub = wp_update_post( array( 'ID' => $bundle_id, 'post_status' => 'publish' ), true );
			$bundle     = get_post( $bundle_id );
			tutorpress_wp71_compat_assert( ! is_wp_error( $bundle_pub ) && $bundle && 'course-bundle' === $bundle->post_type && 3 === (int) $bundle->post_author && 'publish' === $bundle->post_status, 'bundle publish/type/author mismatch' );

			$cases = array(
				array( 1, true ),
				array( 3, true ),
				array( 2, false ),
			);
			foreach ( $cases as $case ) {
				list( $user_id, $allowed ) = $case;
				wp_set_current_user( $user_id );
				foreach ( array( $course_id, $bundle_id ) as $post_id ) {
					tutorpress_wp71_compat_assert(
						$allowed === user_can( $user_id, 'edit_post', $post_id ) && $allowed === user_can( $user_id, 'delete_post', $post_id ),
						sprintf( 'user %d edit/delete_post %d expected %s', $user_id, $post_id, $allowed ? 'true' : 'false' )
					);
				}
			}

			tutorpress_wp71_compat_pass( 'bundle scaffold and author caps course=' . $course_id . ' bundle=' . $bundle_id );

			$get_cases = array(
				array( 1, 200 ),
				array( 3, 200 ),
				array( 2, 403 ),
			);
			foreach ( $get_cases as $case ) {
				list( $user_id, $expected ) = $case;
				wp_set_current_user( $user_id );
				$get = tutorpress_wp71_compat_core_bundle_request( 'GET', $bundle_id, 'edit' );
				tutorpress_wp71_compat_assert( $expected === $get->get_status(), sprintf( 'user %d GET edit bundle %d expected %d got %d', $user_id, $bundle_id, $expected, $get->get_status() ) );
			}

			wp_set_current_user( 3 );
			$put_title = tutorpress_wp71_compat_unique_name();
			$put_ok    = tutorpress_wp71_compat_core_bundle_request( 'PUT', $bundle_id, 'edit', array( 'title' => $put_title ) );
			tutorpress_wp71_compat_assert( 200 === $put_ok->get_status() && $put_title === get_post( $bundle_id )->post_title, 'authorized bundle Core PUT failed' );

			wp_set_current_user( 2 );
			$before = get_post( $bundle_id );
			$put    = tutorpress_wp71_compat_core_bundle_request( 'PUT', $bundle_id, 'edit', array( 'title' => 'WP71 Compat Unauthorized Mutation' ) );
			$after  = get_post( $bundle_id );
			tutorpress_wp71_compat_assert(
				403 === $put->get_status() && $before->post_modified_gmt === $after->post_modified_gmt && $before->post_title === $after->post_title && '1' === get_post_meta( $bundle_id, '_wp71_compat_foundation_sentinel', true ),
				sprintf( 'user 2 PUT bundle %d mutated or not 403 (status %d)', $bundle_id, $put->get_status() )
			);

			wp_set_current_user( 0 );
			$response = tutorpress_wp71_compat_core_bundle_request( 'GET', $bundle_id, 'view' );
			$data     = $response->get_data();
			tutorpress_wp71_compat_assert(
				200 === $response->get_status() && (int) $data['id'] === $bundle_id && ! isset( $data['title']['raw'] ),
				sprintf( 'public GET bundle %d failed or exposed editor fields (status %d)', $bundle_id, $response->get_status() )
			);

			tutorpress_wp71_compat_pass( 'bundle core REST/public-read users 1/3/2 edit 200/200/403 view 200 bundle=' . $bundle_id );

			wp_set_current_user( 1 );
			$other_id  = tutorpress_wp71_compat_insert_bundle_record( 'course-bundle', 1 );
			$other_pub = wp_update_post( array( 'ID' => $other_id, 'post_status' => 'publish' ), true );
			$other     = get_post( $other_id );
			tutorpress_wp71_compat_assert( ! is_wp_error( $other_pub ) && $other && 'course-bundle' === $other->post_type && 1 === (int) $other->post_author && 'publish' === $other->post_status, 'other bundle publish/type/author mismatch' );
			update_post_meta( $other_id, 'bundle-course-ids', (string) $course_id );

			wp_set_current_user( 3 );
			tutorpress_wp71_compat_assert(
				true === user_can( 3, 'edit_post', $course_id ) && false === user_can( 3, 'edit_post', $other_id ) && false === user_can( 3, 'delete_post', $other_id ),
				'included-course owner must not edit/delete the other bundle'
			);

			$other_gets = array(
				array( 1, 200 ),
				array( 3, 403 ),
				array( 2, 403 ),
			);
			foreach ( $other_gets as $case ) {
				list( $user_id, $expected ) = $case;
				wp_set_current_user( $user_id );
				$get = tutorpress_wp71_compat_core_bundle_request( 'GET', $other_id, 'edit' );
				tutorpress_wp71_compat_assert( $expected === $get->get_status(), sprintf( 'user %d GET edit other bundle %d expected %d got %d', $user_id, $other_id, $expected, $get->get_status() ) );
			}

			wp_set_current_user( 3 );
			$other_before = get_post( $other_id );
			$other_put    = tutorpress_wp71_compat_core_bundle_request( 'PUT', $other_id, 'edit', array( 'title' => 'WP71 Compat Unauthorized Mutation' ) );
			$other_after  = get_post( $other_id );
			tutorpress_wp71_compat_assert(
				403 === $other_put->get_status() && $other_before->post_modified_gmt === $other_after->post_modified_gmt && $other_before->post_title === $other_after->post_title && '1' === get_post_meta( $other_id, '_wp71_compat_foundation_sentinel', true ),
				sprintf( 'user 3 PUT other bundle %d mutated or not 403 (status %d)', $other_id, $other_put->get_status() )
			);

			wp_set_current_user( 0 );
			$other_response = tutorpress_wp71_compat_core_bundle_request( 'GET', $other_id, 'view' );
			$other_data     = $other_response->get_data();
			tutorpress_wp71_compat_assert(
				200 === $other_response->get_status() && (int) $other_data['id'] === $other_id && ! isset( $other_data['title']['raw'] ),
				sprintf( 'public GET other bundle %d failed or exposed editor fields (status %d)', $other_id, $other_response->get_status() )
			);

			tutorpress_wp71_compat_pass( 'bundle included-course owner 403 other=' . $other_id . ' course=' . $course_id );

			wp_set_current_user( 1 );
			( new TutorPress_Certificate_Controller() )->register_routes();

			$read_ids = array(
				$bundle_id => array( 1 => 200, 3 => 200, 2 => 403 ),
				$other_id  => array( 1 => 200, 3 => 403, 2 => 403 ),
			);
			$paths = array( '/tutorpress/v1/bundles/%d', '/tutorpress/v1/bundles/%d/courses', '/tutorpress/v1/bundles/%d/benefits', '/tutorpress/v1/bundles/%d/instructors', '/tutorpress/v1/certificate/bundle/selection/%d' );
			foreach ( $read_ids as $id => $actors ) {
				foreach ( $actors as $user_id => $expected ) {
					wp_set_current_user( $user_id );
					foreach ( $paths as $path ) {
						$route    = sprintf( $path, $id );
						$response = tutorpress_wp71_compat_bundle_request( 'GET', $route );
						tutorpress_wp71_compat_assert( $expected === $response->get_status(), sprintf( 'user %d GET %s expected %d got %d', $user_id, $route, $expected, $response->get_status() ) );
					}
				}
			}

			$deny_title = tutorpress_wp71_compat_unique_name();
			foreach ( array( array( 2, $bundle_id ), array( 3, $other_id ) ) as $target ) {
				list( $user_id, $id ) = $target;
				wp_set_current_user( $user_id );
				foreach ( array(
					array( 'PATCH', '/tutorpress/v1/bundles/' . $id, array( 'title' => $deny_title ) ),
					array( 'PATCH', '/tutorpress/v1/bundles/' . $id . '/courses', array( 'course_ids' => array( (int) $course_id ) ) ),
					array( 'POST', '/tutorpress/v1/bundles/benefits/save', array( 'bundle_id' => $id, 'benefits' => $deny_title ) ),
					array( 'POST', '/tutorpress/v1/certificate/bundle/save', array( 'bundle_id' => $id, 'template_key' => 'none', 'allow_individual_certificates' => '0' ) ),
				) as $write ) {
					list( $method, $route, $params ) = $write;
					$before = get_post( $id );
					$snap   = array( $before->post_title, $before->post_modified_gmt, get_post_meta( $id, 'bundle-course-ids', true ), get_post_meta( $id, '_tutor_course_benefits', true ), get_post_meta( $id, 'tutor_course_certificate_template', true ), get_post_meta( $id, 'certificate_for_individual_courses', true ), get_post_meta( $id, '_wp71_compat_foundation_sentinel', true ) );
					$response = tutorpress_wp71_compat_bundle_request( $method, $route, $params );
					$after    = get_post( $id );
					tutorpress_wp71_compat_assert( 403 === $response->get_status() && '1' === $snap[6] && $snap === array( $after->post_title, $after->post_modified_gmt, get_post_meta( $id, 'bundle-course-ids', true ), get_post_meta( $id, '_tutor_course_benefits', true ), get_post_meta( $id, 'tutor_course_certificate_template', true ), get_post_meta( $id, 'certificate_for_individual_courses', true ), get_post_meta( $id, '_wp71_compat_foundation_sentinel', true ) ), sprintf( 'user %d %s %s mutated or not 403 (status %d)', $user_id, $method, $route, $response->get_status() ) );
				}
			}

			tutorpress_wp71_compat_pass( 'bundle tutorpress object reads/denies owned=' . $bundle_id . ' other=' . $other_id );

			wp_set_current_user( 3 );
			$item_title = tutorpress_wp71_compat_unique_name();
			$item_ok    = tutorpress_wp71_compat_bundle_request( 'PATCH', '/tutorpress/v1/bundles/' . $bundle_id, array( 'title' => $item_title ) );
			tutorpress_wp71_compat_assert( 200 === $item_ok->get_status() && $item_title === get_post( $bundle_id )->post_title, sprintf( 'authorized bundle item PATCH failed (status %d)', $item_ok->get_status() ) );

			$courses_ok = tutorpress_wp71_compat_bundle_request( 'PATCH', '/tutorpress/v1/bundles/' . $bundle_id . '/courses', array( 'course_ids' => array( (int) $course_id ) ) );
			tutorpress_wp71_compat_assert( 200 === $courses_ok->get_status() && (string) $course_id === get_post_meta( $bundle_id, 'bundle-course-ids', true ), sprintf( 'authorized bundle courses PATCH failed (status %d)', $courses_ok->get_status() ) );

			$benefits    = tutorpress_wp71_compat_unique_name();
			$benefits_ok = tutorpress_wp71_compat_bundle_request( 'POST', '/tutorpress/v1/bundles/benefits/save', array( 'bundle_id' => $bundle_id, 'benefits' => $benefits ) );
			tutorpress_wp71_compat_assert( 200 === $benefits_ok->get_status() && $benefits === get_post_meta( $bundle_id, '_tutor_course_benefits', true ), sprintf( 'authorized bundle benefits save failed (status %d)', $benefits_ok->get_status() ) );

			$cert_ok = tutorpress_wp71_compat_bundle_request( 'POST', '/tutorpress/v1/certificate/bundle/save', array( 'bundle_id' => $bundle_id, 'template_key' => 'none', 'allow_individual_certificates' => '0' ) );
			tutorpress_wp71_compat_assert( 200 === $cert_ok->get_status() && 'none' === get_post_meta( $bundle_id, 'tutor_course_certificate_template', true ) && '0' === get_post_meta( $bundle_id, 'certificate_for_individual_courses', true ), sprintf( 'authorized bundle certificate save failed (status %d)', $cert_ok->get_status() ) );

			tutorpress_wp71_compat_pass( 'bundle tutorpress object writes owned=' . $bundle_id );

			$owned_present = array( 1 => true, 3 => true, 2 => false );
			$other_present = array( 1 => true, 3 => false, 2 => false );
			foreach ( array( 1, 3, 2 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$list = tutorpress_wp71_compat_bundle_request( 'GET', '/tutorpress/v1/bundles', array( 'per_page' => 100 ) );
				$data = $list->get_data();
				$ids  = array_map( 'intval', wp_list_pluck( isset( $data['bundles'] ) ? $data['bundles'] : array(), 'id' ) );
				tutorpress_wp71_compat_assert(
					200 === $list->get_status() && $owned_present[ $user_id ] === in_array( $bundle_id, $ids, true ) && $other_present[ $user_id ] === in_array( $other_id, $ids, true ) && ( 1 !== $user_id || (int) $data['total'] >= 2 ),
					sprintf( 'user %d collection membership mismatch (status %d total %s)', $user_id, $list->get_status(), isset( $data['total'] ) ? $data['total'] : 'n/a' )
				);
			}

			tutorpress_wp71_compat_pass( 'bundle collection/count users 1/3/2 owned=' . $bundle_id . ' other=' . $other_id );

			tutorpress_wp71_compat_cleanup();
			global $wpdb;
			$like           = $wpdb->esc_like( 'WP71 Compatibility Disposable Foundation' ) . '%';
			$course_residue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", 'courses', $like ) );
			$meta_residue   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", '_wp71_compat_foundation_sentinel' ) );
			$id_residue     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID IN ( %d, %d, %d )", $course_id, $bundle_id, $other_id ) );
			tutorpress_wp71_compat_assert( 0 === $course_residue && 0 === $meta_residue && 0 === $id_residue, 'in-fixture residue ' . $course_residue . '/' . $meta_residue . '/' . $id_residue );
		} finally {
			tutorpress_wp71_compat_cleanup();
		}
	}
);
