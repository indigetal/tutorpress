<?php

$course_id = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => 'courses' ) );
$topic_id  = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => 'topics', 'post_parent' => $course_id ) );
$meet_id   = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => 'tutor-google-meet', 'post_parent' => $topic_id ) );
$target_id = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => 'topics', 'post_parent' => $course_id ) );
$posts     = TutorPress_Live_Lessons_Store_Fake::$posts;
$can       = array( 'TutorPress_Live_Lessons_Boundary_Fake', 'can' );
$ledger    = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
TutorPress_Live_Lessons_Boundary_Fake::allow( 'edit_posts', 0, true );
TutorPress_Live_Lessons_Boundary_Fake::allow( 'edit_posts', $course_id, true );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Authorization::authorize( $posts[ $meet_id ], $posts[ $topic_id ], $posts[ $course_id ], $can ), 'edit_posts alone cannot authorize a replacement operation' );
TutorPress_Live_Lessons_Boundary_Fake::allow( 'edit_post', $course_id, true );
$zoom = array( 'post_type' => 'tutor_zoom_meeting' ) + $posts[ $meet_id ];
tutorpress_live_lessons_assert( $course_id === TutorPress_Live_Lesson_Authorization::authorize( $posts[ $meet_id ], $posts[ $topic_id ], $posts[ $course_id ], $can ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize( $zoom, $posts[ $topic_id ], $posts[ $course_id ], $can, $posts[ $target_id ] ), 'course edit_post authorizes both live-lesson types and a target topic on that course' );
$item   = array( 'post_type' => 'lesson' ) + $posts[ $meet_id ];
$link   = array( 'post_parent' => 0 ) + $posts[ $meet_id ];
$topic  = array( 'post_parent' => 0 ) + $posts[ $topic_id ];
$course = array( 'post_type' => 'post' ) + $posts[ $course_id ];
$away   = array( 'post_parent' => 0 ) + $posts[ $target_id ];
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Authorization::authorize( $item, $posts[ $topic_id ], $posts[ $course_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize( $link, $posts[ $topic_id ], $posts[ $course_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize( $posts[ $meet_id ], $topic, $posts[ $course_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize( $posts[ $meet_id ], $posts[ $topic_id ], $course, $can ) && null === TutorPress_Live_Lesson_Authorization::authorize( $zoom, $posts[ $topic_id ], $posts[ $course_id ], $can, $away ) && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'wrong types, a broken item-topic-course chain, and a target topic on another course are rejected without a provider call' );
$dest_id = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => 'courses' ) );
$dt_id   = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_type' => 'topics', 'post_parent' => $dest_id ) );
TutorPress_Live_Lessons_Boundary_Fake::allow( 'edit_posts', $dest_id, true );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Authorization::authorize_course( TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_create( TutorPress_Live_Lessons_Store_Fake::$posts[ $dt_id ], TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_projection( TutorPress_Live_Lessons_Store_Fake::$posts[ $dt_id ], TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_duplicate( TutorPress_Live_Lessons_Store_Fake::$posts[ $dt_id ], TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $posts[ $course_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_cascade( TutorPress_Live_Lessons_Store_Fake::$posts[ $dt_id ], TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], array(), $can ), 'edit_posts alone cannot authorize collection, create, projection, topic duplicate, or cascade' );
TutorPress_Live_Lessons_Boundary_Fake::allow( 'edit_post', $dest_id, true );
tutorpress_live_lessons_assert( $dest_id === TutorPress_Live_Lesson_Authorization::authorize_course( TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && $dest_id === TutorPress_Live_Lesson_Authorization::authorize_create( TutorPress_Live_Lessons_Store_Fake::$posts[ $dt_id ], TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && $dest_id === TutorPress_Live_Lesson_Authorization::authorize_projection( TutorPress_Live_Lessons_Store_Fake::$posts[ $dt_id ], TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize_duplicate( $posts[ $topic_id ], $posts[ $course_id ], TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize_cascade( $posts[ $topic_id ], $posts[ $course_id ], array( array( 'item' => $posts[ $meet_id ], 'topic' => $posts[ $topic_id ] ) ), $can ), 'course edit_post authorizes collection, create, projection, topic duplicate, and cascade' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Authorization::authorize_course( array( 'post_type' => 'post' ) + TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_create( $topic, $posts[ $course_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_projection( $topic, $posts[ $course_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_duplicate( $posts[ $topic_id ], $posts[ $course_id ], array( 'post_type' => 'post' ) + TutorPress_Live_Lessons_Store_Fake::$posts[ $dest_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize_cascade( $posts[ $topic_id ], $posts[ $course_id ], array( array( 'item' => $link, 'topic' => $posts[ $topic_id ] ) ), $can ) && null === TutorPress_Live_Lesson_Authorization::authorize( $link, $posts[ $topic_id ], $posts[ $course_id ], $can ) && null === TutorPress_Live_Lesson_Authorization::authorize( $posts[ $meet_id ], $posts[ $topic_id ], $posts[ $course_id ], $can, $away ) && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'course, topic, destination, cascade child, reconcile, and duplicate target fail without a provider call' );
$chain_course = $posts[ $course_id ];
$chain_topic  = $posts[ $topic_id ];
$chain_item   = $posts[ $meet_id ];
$chain_child  = array( array( 'item' => $chain_item, 'topic' => $chain_topic ) );
$seen_caps    = array();
$broad_only   = static function ( $capability, $post_id ) use ( &$seen_caps ) {
	$seen_caps[] = array( $capability, (int) $post_id );
	return 'edit_posts' === $capability;
};
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Authorization::authorize( $chain_item, $chain_topic, $chain_course, $broad_only ) && null === TutorPress_Live_Lesson_Authorization::authorize_course( $chain_course, $broad_only ) && null === TutorPress_Live_Lesson_Authorization::authorize_create( $chain_topic, $chain_course, $broad_only ) && null === TutorPress_Live_Lesson_Authorization::authorize_projection( $chain_topic, $chain_course, $broad_only ) && null === TutorPress_Live_Lesson_Authorization::authorize_duplicate( $chain_topic, $chain_course, $chain_course, $broad_only ) && null === TutorPress_Live_Lesson_Authorization::authorize_cascade( $chain_topic, $chain_course, $chain_child, $broad_only ), 'edit_posts true and edit_post false cannot authorize an otherwise valid chain' );
$course_only = static function ( $capability, $post_id ) use ( &$seen_caps, $course_id ) {
	$seen_caps[] = array( $capability, (int) $post_id );
	return 'edit_post' === $capability && (int) $post_id === (int) $course_id;
};
tutorpress_live_lessons_assert( $course_id === TutorPress_Live_Lesson_Authorization::authorize( $chain_item, $chain_topic, $chain_course, $course_only ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize_course( $chain_course, $course_only ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize_create( $chain_topic, $chain_course, $course_only ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize_projection( $chain_topic, $chain_course, $course_only ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize_duplicate( $chain_topic, $chain_course, $chain_course, $course_only ) && $course_id === TutorPress_Live_Lesson_Authorization::authorize_cascade( $chain_topic, $chain_course, $chain_child, $course_only ), 'edit_post on that course id still authorizes the same chain' );
$auth_source = file_get_contents( ( new ReflectionClass( 'TutorPress_Live_Lesson_Authorization' ) )->getFileName() );
preg_match_all( '/\$can\(\s*\'([^\']+)\'/', $auth_source, $auth_caps );
$passed_caps = array();
foreach ( $seen_caps as $seen_cap ) {
	$passed_caps[] = $seen_cap[0];
}
tutorpress_live_lessons_assert( array( 'edit_post' ) === array_values( array_unique( $auth_caps[1] ) ) && array() !== $auth_caps[1] && array( 'edit_post' ) === array_values( array_unique( $passed_caps ) ) && false === in_array( 'edit_posts', $passed_caps, true ), 'the only capability string passed to the authorization callable is edit_post' );
tutorpress_live_lessons_pass( 'relationship authorization' );
