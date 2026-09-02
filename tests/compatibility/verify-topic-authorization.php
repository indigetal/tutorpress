<?php
/**
 * Topic authorization regression fixture.
 * Disposable Course/Topic scaffold; collection, parent-info, create, reorder, update, duplicate, content-order, and delete by actor.
 */

require_once __DIR__ . '/bootstrap.php';

tutorpress_wp71_compat_run(
	function () {
		$course_id = 0;
		$topic_id  = 0;
		$title     = tutorpress_wp71_compat_unique_name();

		try {
			wp_set_current_user( 3 );

			$course_id = wp_insert_post(
				array(
					'post_type'   => 'courses',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_author' => 3,
				),
				true
			);
			tutorpress_wp71_compat_assert( ! is_wp_error( $course_id ) && (int) $course_id > 0, 'failed to create disposable course' );
			$course_id = (int) $course_id;
			tutorpress_wp71_compat_register_post( $course_id );
			update_post_meta( $course_id, '_wp71_compat_foundation_sentinel', '1' );

			$topic_id = wp_insert_post(
				array(
					'post_type'   => 'topics',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_parent' => $course_id,
					'post_author' => 3,
				),
				true
			);
			tutorpress_wp71_compat_assert( ! is_wp_error( $topic_id ) && (int) $topic_id > 0, 'failed to create disposable topic' );
			$topic_id = (int) $topic_id;
			tutorpress_wp71_compat_register_post( $topic_id );
			update_post_meta( $topic_id, '_wp71_compat_foundation_sentinel', '1' );

			$cases = array(
				array( 1, '/tutorpress/v1/topics', array( 'course_id' => $course_id ), 200 ),
				array( 3, '/tutorpress/v1/topics', array( 'course_id' => $course_id ), 200 ),
				array( 2, '/tutorpress/v1/topics', array( 'course_id' => $course_id ), 403 ),
				array( 1, '/tutorpress/v1/topics/' . $topic_id . '/parent-info', array(), 200 ),
				array( 3, '/tutorpress/v1/topics/' . $topic_id . '/parent-info', array(), 200 ),
				array( 2, '/tutorpress/v1/topics/' . $topic_id . '/parent-info', array(), 403 ),
			);

			foreach ( $cases as $case ) {
				list( $user_id, $route, $params, $expected ) = $case;
				wp_set_current_user( $user_id );
				$request = new WP_REST_Request( 'GET', $route );
				foreach ( $params as $key => $value ) {
					$request->set_param( $key, $value );
				}
				$response = rest_do_request( $request );
				tutorpress_wp71_compat_assert(
					$expected === $response->get_status(),
					sprintf( 'user %d GET %s expected %d got %d', $user_id, $route, $expected, $response->get_status() )
				);
			}

			tutorpress_wp71_compat_pass( 'topic reads collection/parent-info users 1/3/2 = 200/200/403 course=' . $course_id . ' topic=' . $topic_id );

			$topic_query = array(
				'post_type'      => 'topics',
				'post_parent'    => $course_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			);
			$before_count = count( get_posts( $topic_query ) );
			$deny_title   = tutorpress_wp71_compat_unique_name();
			wp_set_current_user( 2 );
			$deny_create = new WP_REST_Request( 'POST', '/tutorpress/v1/topics' );
			$deny_create->set_param( 'course_id', $course_id );
			$deny_create->set_param( 'title', $deny_title );
			$deny_create->set_param( 'content', '' );
			$deny_create_response = rest_do_request( $deny_create );
			tutorpress_wp71_compat_assert( 403 === $deny_create_response->get_status(), 'user 2 POST create expected 403 got ' . $deny_create_response->get_status() );
			tutorpress_wp71_compat_assert( $before_count === count( get_posts( $topic_query ) ), 'user 2 create mutated topic count' );

			$before_order = (int) get_post( $topic_id )->menu_order;
			$deny_reorder = new WP_REST_Request( 'POST', '/tutorpress/v1/topics/reorder' );
			$deny_reorder->set_param( 'course_id', $course_id );
			$deny_reorder->set_param( 'topic_orders', array( array( 'id' => $topic_id, 'order' => $before_order + 9 ) ) );
			$deny_reorder_response = rest_do_request( $deny_reorder );
			tutorpress_wp71_compat_assert( 403 === $deny_reorder_response->get_status(), 'user 2 POST reorder expected 403 got ' . $deny_reorder_response->get_status() );
			tutorpress_wp71_compat_assert( $before_order === (int) get_post( $topic_id )->menu_order, 'user 2 reorder mutated menu_order' );

			$topic_ids = array( $topic_id );
			foreach ( array( 1, 3 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$create = new WP_REST_Request( 'POST', '/tutorpress/v1/topics' );
				$create->set_param( 'course_id', $course_id );
				$create->set_param( 'title', tutorpress_wp71_compat_unique_name() );
				$create->set_param( 'content', '' );
				$create_response = rest_do_request( $create );
				tutorpress_wp71_compat_assert( 200 === $create_response->get_status(), sprintf( 'user %d POST create expected 200 got %d', $user_id, $create_response->get_status() ) );
				$data       = $create_response->get_data();
				$created_id = isset( $data['data']['id'] ) ? (int) $data['data']['id'] : 0;
				tutorpress_wp71_compat_assert( $created_id > 0, sprintf( 'user %d create missing topic id', $user_id ) );
				tutorpress_wp71_compat_register_post( $created_id );
				update_post_meta( $created_id, '_wp71_compat_foundation_sentinel', '1' );
				$topic_ids[] = $created_id;
			}

			$orders = array();
			foreach ( $topic_ids as $index => $id ) {
				$orders[] = array( 'id' => $id, 'order' => (int) $index );
			}
			foreach ( array( 1, 3 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$reorder = new WP_REST_Request( 'POST', '/tutorpress/v1/topics/reorder' );
				$reorder->set_param( 'course_id', $course_id );
				$reorder->set_param( 'topic_orders', $orders );
				$reorder_response = rest_do_request( $reorder );
				tutorpress_wp71_compat_assert( 200 === $reorder_response->get_status(), sprintf( 'user %d POST reorder expected 200 got %d', $user_id, $reorder_response->get_status() ) );
			}

			tutorpress_wp71_compat_pass( 'topic create/reorder users 1/3/2 = 200/200/403 course=' . $course_id );

			$before_title = get_post( $topic_id )->post_title;
			wp_set_current_user( 2 );
			$deny_update = new WP_REST_Request( 'PATCH', '/tutorpress/v1/topics/' . $topic_id );
			$deny_update->set_param( 'title', tutorpress_wp71_compat_unique_name() );
			$deny_update->set_param( 'content', '' );
			$deny_update_response = rest_do_request( $deny_update );
			tutorpress_wp71_compat_assert( 403 === $deny_update_response->get_status(), 'user 2 PATCH update expected 403 got ' . $deny_update_response->get_status() );
			tutorpress_wp71_compat_assert( $before_title === get_post( $topic_id )->post_title, 'user 2 update mutated title' );

			foreach ( array( 1, 3 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$update = new WP_REST_Request( 'PATCH', '/tutorpress/v1/topics/' . $topic_id );
				$update->set_param( 'title', tutorpress_wp71_compat_unique_name() );
				$update->set_param( 'content', '' );
				$update_response = rest_do_request( $update );
				tutorpress_wp71_compat_assert( 200 === $update_response->get_status(), sprintf( 'user %d PATCH update expected 200 got %d', $user_id, $update_response->get_status() ) );
			}

			$before_dup_count = count( get_posts( $topic_query ) );
			wp_set_current_user( 2 );
			$deny_dup = new WP_REST_Request( 'POST', '/tutorpress/v1/topics/' . $topic_id . '/duplicate' );
			$deny_dup->set_param( 'course_id', $course_id );
			$deny_dup_response = rest_do_request( $deny_dup );
			tutorpress_wp71_compat_assert( 403 === $deny_dup_response->get_status(), 'user 2 POST duplicate expected 403 got ' . $deny_dup_response->get_status() );
			tutorpress_wp71_compat_assert( $before_dup_count === count( get_posts( $topic_query ) ), 'user 2 duplicate mutated topic count' );

			$target_id = wp_insert_post(
				array(
					'post_type'   => 'courses',
					'post_status' => 'publish',
					'post_title'  => tutorpress_wp71_compat_unique_name(),
					'post_author' => 1,
				),
				true
			);
			tutorpress_wp71_compat_assert( ! is_wp_error( $target_id ) && (int) $target_id > 0, 'failed to create target course' );
			$target_id = (int) $target_id;
			tutorpress_wp71_compat_register_post( $target_id );
			update_post_meta( $target_id, '_wp71_compat_foundation_sentinel', '1' );

			wp_set_current_user( 3 );
			$deny_target = new WP_REST_Request( 'POST', '/tutorpress/v1/topics/' . $topic_id . '/duplicate' );
			$deny_target->set_param( 'course_id', $target_id );
			$deny_target_response = rest_do_request( $deny_target );
			tutorpress_wp71_compat_assert( 403 === $deny_target_response->get_status(), 'user 3 duplicate to unauthorized course expected 403 got ' . $deny_target_response->get_status() );
			$target_query = $topic_query;
			$target_query['post_parent'] = $target_id;
			tutorpress_wp71_compat_assert( 0 === count( get_posts( $target_query ) ), 'user 3 duplicate leaked onto target course' );
			tutorpress_wp71_compat_assert( $course_id === (int) get_post( $topic_id )->post_parent, 'user 3 duplicate mutated source topic parent' );

			foreach ( array( 1, 3 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$dup = new WP_REST_Request( 'POST', '/tutorpress/v1/topics/' . $topic_id . '/duplicate' );
				$dup->set_param( 'course_id', $course_id );
				$dup_response = rest_do_request( $dup );
				tutorpress_wp71_compat_assert( 200 === $dup_response->get_status(), sprintf( 'user %d POST duplicate expected 200 got %d', $user_id, $dup_response->get_status() ) );
				$dup_data = $dup_response->get_data();
				$dup_id   = isset( $dup_data['data']['id'] ) ? (int) $dup_data['data']['id'] : 0;
				tutorpress_wp71_compat_assert( $dup_id > 0, sprintf( 'user %d duplicate missing topic id', $user_id ) );
				tutorpress_wp71_compat_register_post( $dup_id );
				update_post_meta( $dup_id, '_wp71_compat_foundation_sentinel', '1' );
			}

			tutorpress_wp71_compat_pass( 'topic update/duplicate users 1/3/2 = 200/200/403 course=' . $course_id . ' topic=' . $topic_id );

			wp_set_current_user( 3 );
			$lesson_id = wp_insert_post(
				array(
					'post_type'    => 'lesson',
					'post_status'  => 'publish',
					'post_title'   => tutorpress_wp71_compat_unique_name(),
					'post_parent'  => $topic_id,
					'post_author'  => 3,
					'post_content' => '',
				),
				true
			);
			tutorpress_wp71_compat_assert( ! is_wp_error( $lesson_id ) && (int) $lesson_id > 0, 'failed to create disposable lesson' );
			$lesson_id = (int) $lesson_id;
			tutorpress_wp71_compat_register_post( $lesson_id );
			update_post_meta( $lesson_id, '_wp71_compat_foundation_sentinel', '1' );

			$before_lesson_order = (int) get_post( $lesson_id )->menu_order;
			wp_set_current_user( 2 );
			$deny_content = new WP_REST_Request( 'POST', '/tutorpress/v1/topics/' . $topic_id . '/content/reorder' );
			$deny_content->set_param( 'content_orders', array( array( 'id' => $lesson_id, 'order' => $before_lesson_order + 9 ) ) );
			$deny_content_response = rest_do_request( $deny_content );
			tutorpress_wp71_compat_assert( 403 === $deny_content_response->get_status(), 'user 2 POST content-order expected 403 got ' . $deny_content_response->get_status() );
			tutorpress_wp71_compat_assert( $before_lesson_order === (int) get_post( $lesson_id )->menu_order, 'user 2 content-order mutated menu_order' );

			foreach ( array( 1, 3 ) as $user_id ) {
				wp_set_current_user( $user_id );
				$content = new WP_REST_Request( 'POST', '/tutorpress/v1/topics/' . $topic_id . '/content/reorder' );
				$content->set_param( 'content_orders', array( array( 'id' => $lesson_id, 'order' => $before_lesson_order + 1 ) ) );
				$content_response = rest_do_request( $content );
				tutorpress_wp71_compat_assert( 200 === $content_response->get_status(), sprintf( 'user %d POST content-order expected 200 got %d', $user_id, $content_response->get_status() ) );
			}

			wp_set_current_user( 2 );
			$deny_delete = new WP_REST_Request( 'DELETE', '/tutorpress/v1/topics/' . $topic_id );
			$deny_delete_response = rest_do_request( $deny_delete );
			tutorpress_wp71_compat_assert( 403 === $deny_delete_response->get_status(), 'user 2 DELETE expected 403 got ' . $deny_delete_response->get_status() );
			$after_deny_topic = get_post( $topic_id );
			tutorpress_wp71_compat_assert( $after_deny_topic && 'topics' === $after_deny_topic->post_type, 'user 2 delete removed topic' );

			$delete_ids = array( (int) $topic_ids[1], (int) $topic_ids[2] );
			foreach ( array( 1, 3 ) as $index => $user_id ) {
				wp_set_current_user( $user_id );
				$delete = new WP_REST_Request( 'DELETE', '/tutorpress/v1/topics/' . $delete_ids[ $index ] );
				$delete_response = rest_do_request( $delete );
				tutorpress_wp71_compat_assert( 200 === $delete_response->get_status(), sprintf( 'user %d DELETE expected 200 got %d', $user_id, $delete_response->get_status() ) );
				tutorpress_wp71_compat_assert( ! get_post( $delete_ids[ $index ] ), sprintf( 'user %d delete left topic %d', $user_id, $delete_ids[ $index ] ) );
			}

			$final_topic = get_post( $topic_id );
			tutorpress_wp71_compat_assert( $final_topic && 'topics' === $final_topic->post_type && $course_id === (int) $final_topic->post_parent, 'source topic missing or reparented before cleanup' );

			tutorpress_wp71_compat_cleanup();
			global $wpdb;
			$like           = $wpdb->esc_like( 'WP71 Compatibility Disposable Foundation' ) . '%';
			$course_residue = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_title LIKE %s", 'courses', $like ) );
			$meta_residue   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s", '_wp71_compat_foundation_sentinel' ) );
			tutorpress_wp71_compat_assert( 0 === $course_residue && 0 === $meta_residue, 'in-fixture residue ' . $course_residue . '/' . $meta_residue );

			tutorpress_wp71_compat_pass( 'topic content-order/delete users 1/3/2 = 200/200/403 residue=0/0 course=' . $course_id . ' topic=' . $topic_id );
		} finally {
			tutorpress_wp71_compat_cleanup();
		}
	}
);
