<?php

$course = array( 'ID' => 7, 'post_type' => 'courses' );
$allow  = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === $id; };
$deny   = static function () { return false; };
$topics = array( array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 ), array( 'ID' => 9, 'post_type' => 'topics', 'post_parent' => 4 ) );
$states = array( 'pending', 'error', 'unlinked', 'synced' );
$lessons = array();
foreach ( $states as $index => $state ) {
	$lessons[] = array( 'ID' => 20 + $index, 'post_type' => 0 === $index % 2 ? 'tutor-google-meet' : 'tutor_zoom_meeting', 'post_status' => 'publish', 'post_parent' => 8, 'sync_state' => $state );
}
$lessons[] = array( 'ID' => 30, 'post_type' => 'lesson', 'post_status' => 'publish', 'post_parent' => 8, 'sync_state' => 'synced' );
$lessons[] = array( 'ID' => 31, 'post_type' => 'tutor_zoom_meeting', 'post_status' => 'draft', 'post_parent' => 8, 'sync_state' => 'synced' );
$lessons[] = array( 'ID' => 32, 'post_type' => 'tutor-google-meet', 'post_status' => 'publish', 'post_parent' => 9, 'sync_state' => 'synced' );

tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Collection_Query::query( $course, $deny, $topics, $lessons, array() ), 'collection query requires an authorized course' );
$all = TutorPress_Live_Lesson_Collection_Query::query( $course, $allow, $topics, $lessons, array( 'status' => 'live', 'per_page' => 10, 'page' => 1 ) );
$ids = array();
foreach ( $all['data']['data'] as $row ) {
	$ids[] = $row['sync_state'];
	tutorpress_live_lessons_assert( 'scheduled' === $row['status'], 'collection rows keep compatibility status scheduled' );
}
tutorpress_live_lessons_assert( true === $all['success'] && 'Live lessons retrieved successfully.' === $all['message'] && array( 'pending', 'error', 'unlinked', 'synced' ) === $ids && 4 === $all['data']['pagination']['total'], 'publish sync states stay included without lifecycle filtering' );
$zoom = TutorPress_Live_Lesson_Collection_Query::query( $course, $allow, $topics, $lessons, array( 'type' => 'zoom', 'topic_id' => 8, 'per_page' => 1, 'page' => 2 ) );
tutorpress_live_lessons_assert( 1 === count( $zoom['data']['data'] ) && 'zoom' === $zoom['data']['data'][0]['type'] && 2 === $zoom['data']['pagination']['total'] && 2 === $zoom['data']['pagination']['page'] && 2 === $zoom['data']['pagination']['total_pages'], 'topic and provider constraints paginate the authorized course' );
$foreign = array( array( 'ID' => 32, 'post_type' => 'tutor-google-meet', 'post_status' => 'publish', 'post_parent' => 9, 'sync_state' => 'pending' ) );
$mismatch = TutorPress_Live_Lesson_Collection_Query::query( $course, $allow, array( array( 'ID' => 9, 'post_type' => 'topics', 'post_parent' => 4 ) ), $foreign, array() );
$broad = static function ( $cap ) { return 'edit_posts' === $cap; };
tutorpress_live_lessons_assert( 0 === $mismatch['data']['pagination']['total'] && null === TutorPress_Live_Lesson_Collection_Query::query( $course, $broad, $topics, $lessons, array() ), 'a mismatched topic and broad edit_posts do not authorize the collection' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lesson_Cutover::ENABLED, 'the cutover constant stays false and is not the collection authorization check' );
tutorpress_live_lessons_pass( 'authorized collection query' );
