<?php
/**
 * WP71 Lesson/Assignment authorization and Assignment-role regression fixture.
 */

require_once __DIR__ . '/bootstrap.php';

function tutorpress_wp71_compat_insert_child_record( $type, $author, $parent = 0 ) {
	$post_id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_title'   => tutorpress_wp71_compat_unique_name(),
			'post_author'  => $author,
			'post_parent'  => $parent,
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

function tutorpress_wp71_compat_core_child_request( $method, $post_id, $context, $params = array() ) {
	$object  = get_post_type_object( get_post_type( $post_id ) );
	$base    = ( $object && $object->rest_base ) ? $object->rest_base : get_post_type( $post_id );
	$request = new WP_REST_Request( $method, '/wp/v2/' . $base . '/' . (int) $post_id );
	$request->set_param( 'context', $context );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
}

function tutorpress_wp71_compat_lesson_request( $method, $route, $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_do_request( $request );
}

function tutorpress_wp71_compat_assignment_request( $method, $route, $params = array() ) {
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

			$course_id = tutorpress_wp71_compat_insert_child_record( 'courses', 3 );
			tutorpress_wp71_compat_assert( 3 === (int) get_post_field( 'post_author', $course_id ), 'course author mismatch' );

			$topic_id = tutorpress_wp71_compat_insert_child_record( 'topics', 3, $course_id );
			tutorpress_wp71_compat_assert( $course_id === (int) get_post_field( 'post_parent', $topic_id ) && 3 === (int) get_post_field( 'post_author', $topic_id ), 'topic parent/author mismatch' );

			$lesson_id = tutorpress_wp71_compat_insert_child_record( 'lesson', 3, $topic_id );
			tutorpress_wp71_compat_assert( $topic_id === (int) get_post_field( 'post_parent', $lesson_id ) && 3 === (int) get_post_field( 'post_author', $lesson_id ), 'lesson parent/author mismatch' );

			$assignment_id = tutorpress_wp71_compat_insert_child_record( 'tutor_assignments', 3, $topic_id );
			tutorpress_wp71_compat_assert( $topic_id === (int) get_post_field( 'post_parent', $assignment_id ) && 3 === (int) get_post_field( 'post_author', $assignment_id ), 'assignment parent/author mismatch' );

			$cross_id = tutorpress_wp71_compat_insert_child_record( 'tutor_assignments', 2, $topic_id );
			tutorpress_wp71_compat_assert( $topic_id === (int) get_post_field( 'post_parent', $cross_id ) && 2 === (int) get_post_field( 'post_author', $cross_id ), 'cross-author assignment parent/author mismatch' );

			$admin_role      = get_role( 'administrator' );
			$instructor_role = get_role( 'tutor_instructor' );
			tutorpress_wp71_compat_assert( $admin_role && $admin_role->has_cap( 'edit_others_tutor_assignments' ) && $admin_role->has_cap( 'delete_others_tutor_assignments' ), 'administrator missing assignment others caps' );
			tutorpress_wp71_compat_assert( $instructor_role && ! $instructor_role->has_cap( 'edit_others_tutor_assignments' ) && ! $instructor_role->has_cap( 'delete_others_tutor_assignments' ), 'instructor retains assignment others caps' );

			tutorpress_wp71_compat_pass( 'child scaffold and assignment role caps course=' . $course_id . ' topic=' . $topic_id . ' lesson=' . $lesson_id . ' assignment=' . $assignment_id . ' cross-assignment=' . $cross_id );

			$access_cases = array(
				array( 1, $lesson_id, true ),
				array( 1, $assignment_id, true ),
				array( 1, $cross_id, true ),
				array( 3, $lesson_id, true ),
				array( 3, $assignment_id, true ),
				array( 3, $cross_id, true ),
				array( 2, $lesson_id, false ),
				array( 2, $assignment_id, false ),
				array( 2, $cross_id, false ),
			);
			foreach ( $access_cases as $case ) {
				list( $user_id, $post_id, $allowed ) = $case;
				wp_set_current_user( $user_id );
				tutorpress_wp71_compat_assert(
					$allowed === user_can( $user_id, 'edit_post', $post_id ) && $allowed === user_can( $user_id, 'delete_post', $post_id ),
					sprintf( 'user %d edit/delete_post %d expected %s', $user_id, $post_id, $allowed ? 'true' : 'false' )
				);
				$get = tutorpress_wp71_compat_core_child_request( 'GET', $post_id, 'edit' );
				tutorpress_wp71_compat_assert(
					( $allowed ? 200 : 403 ) === $get->get_status(),
					sprintf( 'user %d GET edit %d expected %d got %d', $user_id, $post_id, $allowed ? 200 : 403, $get->get_status() )
				);
			}

			wp_set_current_user( 3 );
			$put_title = tutorpress_wp71_compat_unique_name();
			$put_ok    = tutorpress_wp71_compat_core_child_request( 'PUT', $cross_id, 'edit', array( 'title' => $put_title ) );
			tutorpress_wp71_compat_assert( 200 === $put_ok->get_status() && $put_title === get_post( $cross_id )->post_title, 'authorized cross-author Core PUT failed' );

			wp_set_current_user( 2 );
			foreach ( array( $lesson_id, $assignment_id, $cross_id ) as $post_id ) {
				$before = get_post( $post_id );
				$put    = tutorpress_wp71_compat_core_child_request( 'PUT', $post_id, 'edit', array( 'title' => 'WP71 Compat Unauthorized Mutation' ) );
				$after  = get_post( $post_id );
				tutorpress_wp71_compat_assert(
					403 === $put->get_status() && $before->post_modified_gmt === $after->post_modified_gmt && $before->post_title === $after->post_title && '1' === get_post_meta( $post_id, '_wp71_compat_foundation_sentinel', true ),
					sprintf( 'user 2 PUT %d mutated or not 403 (status %d)', $post_id, $put->get_status() )
				);
			}

			wp_set_current_user( 0 );
			foreach ( array( $lesson_id, $assignment_id, $cross_id ) as $post_id ) {
				$response = tutorpress_wp71_compat_core_child_request( 'GET', $post_id, 'view' );
				$data     = $response->get_data();
				tutorpress_wp71_compat_assert(
					200 === $response->get_status() && (int) $data['id'] === $post_id && ! isset( $data['title']['raw'] ),
					sprintf( 'public GET %d failed or exposed editor fields (status %d)', $post_id, $response->get_status() )
				);
			}

			tutorpress_wp71_compat_pass( 'child core caps/REST/public-read users 1/3/2 edit 200/200/403 view 200' );

			$lesson_read_cases = array(
				array( 1, '/tutorpress/v1/lessons', array( 'topic_id' => $topic_id ), 200 ),
				array( 3, '/tutorpress/v1/lessons', array( 'topic_id' => $topic_id ), 200 ),
				array( 2, '/tutorpress/v1/lessons', array( 'topic_id' => $topic_id ), 403 ),
				array( 1, '/tutorpress/v1/lessons/' . $lesson_id . '/parent-info', array(), 200 ),
				array( 3, '/tutorpress/v1/lessons/' . $lesson_id . '/parent-info', array(), 200 ),
				array( 2, '/tutorpress/v1/lessons/' . $lesson_id . '/parent-info', array(), 403 ),
			);
			foreach ( $lesson_read_cases as $case ) {
				list( $user_id, $route, $params, $expected ) = $case;
				wp_set_current_user( $user_id );
				$response = tutorpress_wp71_compat_lesson_request( 'GET', $route, $params );
				tutorpress_wp71_compat_assert(
					$expected === $response->get_status(),
					sprintf( 'user %d GET %s expected %d got %d', $user_id, $route, $expected, $response->get_status() )
				);
			}

			wp_set_current_user( 2 );
			$lesson_query    = array( 'post_type' => 'lesson', 'post_parent' => $topic_id, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' );
			$before_count    = count( get_posts( $lesson_query ) );
			$before          = get_post( $lesson_id );
			$before_sentinel = get_post_meta( $lesson_id, '_wp71_compat_foundation_sentinel', true );
			$deny_title      = tutorpress_wp71_compat_unique_name();
			$denies          = array(
				array( 'POST', '/tutorpress/v1/lessons', array( 'topic_id' => $topic_id, 'title' => $deny_title, 'content' => '' ) ),
				array( 'PATCH', '/tutorpress/v1/lessons/' . $lesson_id, array( 'title' => $deny_title ) ),
				array( 'DELETE', '/tutorpress/v1/lessons/' . $lesson_id, array() ),
				array( 'POST', '/tutorpress/v1/lessons/reorder', array( 'topic_id' => $topic_id, 'lesson_orders' => array( array( 'id' => $lesson_id, 'order' => (int) $before->menu_order + 9 ) ) ) ),
				array( 'POST', '/tutorpress/v1/lessons/' . $lesson_id . '/duplicate', array( 'topic_id' => $topic_id ) ),
			);
			foreach ( $denies as $deny ) {
				list( $method, $route, $params ) = $deny;
				$response = tutorpress_wp71_compat_lesson_request( $method, $route, $params );
				$after    = get_post( $lesson_id );
				tutorpress_wp71_compat_assert(
					403 === $response->get_status() && $before_count === count( get_posts( $lesson_query ) ) && $before->post_modified_gmt === $after->post_modified_gmt && $before->post_title === $after->post_title && (int) $before->menu_order === (int) $after->menu_order && $before_sentinel === get_post_meta( $lesson_id, '_wp71_compat_foundation_sentinel', true ),
					sprintf( 'user 2 %s %s mutated or not 403 (status %d)', $method, $route, $response->get_status() )
				);
			}

			tutorpress_wp71_compat_pass( 'lesson tutorpress collection/parent-info users 1/3/2 = 200/200/403; user 2 mutations 403' );

			wp_set_current_user( 3 );
			$cross_lesson_id = tutorpress_wp71_compat_insert_child_record( 'lesson', 2, $topic_id );
			tutorpress_wp71_compat_assert( $topic_id === (int) get_post_field( 'post_parent', $cross_lesson_id ) && 2 === (int) get_post_field( 'post_author', $cross_lesson_id ), 'cross-author lesson parent/author mismatch' );

			$created_ids = array();
			foreach ( array( 1, 3 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$create_title = tutorpress_wp71_compat_unique_name();
				$created      = tutorpress_wp71_compat_lesson_request( 'POST', '/tutorpress/v1/lessons', array( 'topic_id' => $topic_id, 'title' => $create_title, 'content' => '' ) );
				$created_data = $created->get_data();
				$created_id   = isset( $created_data['data']['id'] ) ? (int) $created_data['data']['id'] : 0;
				tutorpress_wp71_compat_assert( 201 === $created->get_status() && $created_id > 0 && $topic_id === (int) get_post_field( 'post_parent', $created_id ) && $create_title === get_post( $created_id )->post_title, sprintf( 'user %d lesson create failed', $user_id ) );
				tutorpress_wp71_compat_register_post( $created_id );
				update_post_meta( $created_id, '_wp71_compat_foundation_sentinel', '1' );
				$created_ids[ $user_id ] = $created_id;
			}

			wp_set_current_user( 3 );
			$patch_title = tutorpress_wp71_compat_unique_name();
			$patched     = tutorpress_wp71_compat_lesson_request( 'PATCH', '/tutorpress/v1/lessons/' . $cross_lesson_id, array( 'title' => $patch_title ) );
			tutorpress_wp71_compat_assert( 200 === $patched->get_status() && $patch_title === get_post( $cross_lesson_id )->post_title && 2 === (int) get_post_field( 'post_author', $cross_lesson_id ), 'authorized cross-author lesson PATCH failed' );

			$new_order = (int) get_post( $cross_lesson_id )->menu_order + 5;
			$reordered = tutorpress_wp71_compat_lesson_request( 'POST', '/tutorpress/v1/lessons/reorder', array( 'topic_id' => $topic_id, 'lesson_orders' => array( array( 'id' => $cross_lesson_id, 'order' => $new_order ) ) ) );
			clean_post_cache( $cross_lesson_id );
			$after_order = (int) get_post( $cross_lesson_id )->menu_order;
			tutorpress_wp71_compat_assert( 200 === $reordered->get_status() && $new_order === $after_order, sprintf( 'authorized lesson reorder failed (status %d menu_order %d expected %d)', $reordered->get_status(), $after_order, $new_order ) );

			$dup      = tutorpress_wp71_compat_lesson_request( 'POST', '/tutorpress/v1/lessons/' . $cross_lesson_id . '/duplicate', array( 'topic_id' => $topic_id ) );
			$dup_data = $dup->get_data();
			$dup_id   = isset( $dup_data['data']['id'] ) ? (int) $dup_data['data']['id'] : 0;
			tutorpress_wp71_compat_assert( 200 === $dup->get_status() && $dup_id > 0 && $topic_id === (int) get_post_field( 'post_parent', $dup_id ), 'authorized lesson duplicate failed' );
			tutorpress_wp71_compat_register_post( $dup_id );
			update_post_meta( $dup_id, '_wp71_compat_foundation_sentinel', '1' );

			$deleted_cross = tutorpress_wp71_compat_lesson_request( 'DELETE', '/tutorpress/v1/lessons/' . $cross_lesson_id );
			tutorpress_wp71_compat_assert( 204 === $deleted_cross->get_status() && ! get_post( $cross_lesson_id ), 'authorized cross-author lesson delete failed' );

			wp_set_current_user( 1 );
			$deleted_admin = tutorpress_wp71_compat_lesson_request( 'DELETE', '/tutorpress/v1/lessons/' . $created_ids[1] );
			tutorpress_wp71_compat_assert( 204 === $deleted_admin->get_status() && ! get_post( $created_ids[1] ), 'user 1 lesson delete failed' );

			tutorpress_wp71_compat_pass( 'lesson tutorpress create/update/reorder/duplicate/delete users 1/3 create 201 delete 204; user 3 cross-author write' );

			$assignment_read_cases = array(
				array( 1, '/tutorpress/v1/assignments', array( 'topic_id' => $topic_id ), 200 ),
				array( 3, '/tutorpress/v1/assignments', array( 'topic_id' => $topic_id ), 200 ),
				array( 2, '/tutorpress/v1/assignments', array( 'topic_id' => $topic_id ), 403 ),
				array( 1, '/tutorpress/v1/assignments/' . $assignment_id . '/parent-info', array(), 200 ),
				array( 3, '/tutorpress/v1/assignments/' . $assignment_id . '/parent-info', array(), 200 ),
				array( 2, '/tutorpress/v1/assignments/' . $assignment_id . '/parent-info', array(), 403 ),
			);
			foreach ( $assignment_read_cases as $case ) {
				list( $user_id, $route, $params, $expected ) = $case;
				wp_set_current_user( $user_id );
				$response = tutorpress_wp71_compat_assignment_request( 'GET', $route, $params );
				tutorpress_wp71_compat_assert(
					$expected === $response->get_status(),
					sprintf( 'user %d GET %s expected %d got %d', $user_id, $route, $expected, $response->get_status() )
				);
			}

			wp_set_current_user( 2 );
			$assignment_query = array( 'post_type' => 'tutor_assignments', 'post_parent' => $topic_id, 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' );
			$before_count     = count( get_posts( $assignment_query ) );
			$before           = get_post( $assignment_id );
			$before_sentinel  = get_post_meta( $assignment_id, '_wp71_compat_foundation_sentinel', true );
			$deny_title       = tutorpress_wp71_compat_unique_name();
			$denies           = array(
				array( 'POST', '/tutorpress/v1/assignments', array( 'topic_id' => $topic_id, 'title' => $deny_title, 'content' => '' ) ),
				array( 'PATCH', '/tutorpress/v1/assignments/' . $assignment_id, array( 'title' => $deny_title ) ),
				array( 'DELETE', '/tutorpress/v1/assignments/' . $assignment_id, array() ),
				array( 'POST', '/tutorpress/v1/assignments/reorder', array( 'topic_id' => $topic_id, 'assignment_orders' => array( array( 'id' => $assignment_id, 'order' => (int) $before->menu_order + 9 ) ) ) ),
				array( 'POST', '/tutorpress/v1/assignments/' . $assignment_id . '/duplicate', array( 'topic_id' => $topic_id ) ),
			);
			foreach ( $denies as $deny ) {
				list( $method, $route, $params ) = $deny;
				$response = tutorpress_wp71_compat_assignment_request( $method, $route, $params );
				$after    = get_post( $assignment_id );
				tutorpress_wp71_compat_assert(
					403 === $response->get_status() && $before_count === count( get_posts( $assignment_query ) ) && $before->post_modified_gmt === $after->post_modified_gmt && $before->post_title === $after->post_title && (int) $before->menu_order === (int) $after->menu_order && $before_sentinel === get_post_meta( $assignment_id, '_wp71_compat_foundation_sentinel', true ),
					sprintf( 'user 2 %s %s mutated or not 403 (status %d)', $method, $route, $response->get_status() )
				);
			}

			tutorpress_wp71_compat_pass( 'assignment tutorpress collection/parent-info users 1/3/2 = 200/200/403; user 2 mutations 403' );

			$created_ids = array();
			foreach ( array( 1, 3 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$create_title = tutorpress_wp71_compat_unique_name();
				$created      = tutorpress_wp71_compat_assignment_request( 'POST', '/tutorpress/v1/assignments', array( 'topic_id' => $topic_id, 'title' => $create_title, 'content' => '' ) );
				$created_data = $created->get_data();
				$created_id   = isset( $created_data['data']['id'] ) ? (int) $created_data['data']['id'] : 0;
				tutorpress_wp71_compat_assert( 200 === $created->get_status() && $created_id > 0 && $topic_id === (int) get_post_field( 'post_parent', $created_id ) && $create_title === get_post( $created_id )->post_title && 'draft' === get_post( $created_id )->post_status, sprintf( 'user %d assignment create failed', $user_id ) );
				tutorpress_wp71_compat_register_post( $created_id );
				update_post_meta( $created_id, '_wp71_compat_foundation_sentinel', '1' );
				$created_ids[ $user_id ] = $created_id;
			}

			wp_set_current_user( 3 );
			$patch_title = tutorpress_wp71_compat_unique_name();
			$patched     = tutorpress_wp71_compat_assignment_request( 'PATCH', '/tutorpress/v1/assignments/' . $cross_id, array( 'title' => $patch_title ) );
			tutorpress_wp71_compat_assert( 200 === $patched->get_status() && $patch_title === get_post( $cross_id )->post_title && 2 === (int) get_post_field( 'post_author', $cross_id ), 'authorized cross-author assignment PATCH failed' );

			$new_order   = (int) get_post( $cross_id )->menu_order + 5;
			$reordered   = tutorpress_wp71_compat_assignment_request( 'POST', '/tutorpress/v1/assignments/reorder', array( 'topic_id' => $topic_id, 'assignment_orders' => array( array( 'id' => $cross_id, 'order' => $new_order ) ) ) );
			$after_order = (int) get_post( $cross_id )->menu_order;
			tutorpress_wp71_compat_assert( 200 === $reordered->get_status() && $new_order === $after_order, sprintf( 'authorized assignment reorder failed (status %d menu_order %d expected %d)', $reordered->get_status(), $after_order, $new_order ) );

			$dup      = tutorpress_wp71_compat_assignment_request( 'POST', '/tutorpress/v1/assignments/' . $cross_id . '/duplicate', array( 'topic_id' => $topic_id ) );
			$dup_data = $dup->get_data();
			$dup_id   = isset( $dup_data['data']['id'] ) ? (int) $dup_data['data']['id'] : 0;
			tutorpress_wp71_compat_assert( 200 === $dup->get_status() && $dup_id > 0 && $topic_id === (int) get_post_field( 'post_parent', $dup_id ), 'authorized assignment duplicate failed' );
			tutorpress_wp71_compat_register_post( $dup_id );
			update_post_meta( $dup_id, '_wp71_compat_foundation_sentinel', '1' );

			$deleted_cross = tutorpress_wp71_compat_assignment_request( 'DELETE', '/tutorpress/v1/assignments/' . $cross_id );
			tutorpress_wp71_compat_assert( 200 === $deleted_cross->get_status() && ! get_post( $cross_id ), 'authorized cross-author assignment delete failed' );

			wp_set_current_user( 1 );
			$deleted_admin = tutorpress_wp71_compat_assignment_request( 'DELETE', '/tutorpress/v1/assignments/' . $created_ids[1] );
			tutorpress_wp71_compat_assert( 200 === $deleted_admin->get_status() && ! get_post( $created_ids[1] ), 'user 1 assignment delete failed' );

			tutorpress_wp71_compat_pass( 'assignment tutorpress create/update/reorder/duplicate/delete users 1/3 create 200 delete 200; user 3 cross-author write' );

			tutorpress_wp71_compat_cleanup();
			global $wpdb;
			$like           = $wpdb->esc_like( 'WP71 Compatibility Disposable Foundation' ) . '%';
			$course_residue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", 'courses', $like ) );
			$meta_residue   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", '_wp71_compat_foundation_sentinel' ) );
			tutorpress_wp71_compat_assert( 0 === $course_residue && 0 === $meta_residue, 'in-fixture residue ' . $course_residue . '/' . $meta_residue );
		} finally {
			tutorpress_wp71_compat_cleanup();
		}
	}
);
