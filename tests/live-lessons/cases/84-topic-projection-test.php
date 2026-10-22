<?php

$course = array( 'ID' => 7, 'post_type' => 'courses' );
$topic  = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$allow  = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === $id; };
$deny   = static function () { return false; };
$lesson = array( 'ID' => 11, 'post_type' => 'lesson', 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Read', 'id' => 11, 'title' => 'Read', 'type' => 'lesson', 'menu_order' => 1, 'status' => 'publish', 'video' => array( 'source' => 'html5' ) );
$quiz   = array( 'ID' => 12, 'post_type' => 'tutor_quiz', 'post_status' => 'draft', 'post_parent' => 8, 'id' => 12, 'title' => 'Check', 'type' => 'tutor_quiz', 'menu_order' => 2, 'status' => 'draft' );
$meet   = array( 'ID' => 21, 'post_type' => 'tutor-google-meet', 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Meet', 'id' => 21, 'title' => 'Meet', 'type' => 'meet_lesson', 'menu_order' => 3, 'status' => 'publish' );
$zoom   = array( 'ID' => 22, 'post_type' => 'tutor_zoom_meeting', 'post_status' => 'publish', 'post_parent' => 8, 'post_title' => 'Zoom', 'id' => 22, 'title' => 'Zoom', 'type' => 'zoom_lesson', 'menu_order' => 4, 'status' => 'publish' );
$rows   = array( $lesson, $quiz, $meet, $zoom );

tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Topic_Projector::project( $topic, $course, $deny, $rows, array() ), 'topic projection requires pre-authorized topic and course context' );
$projected = TutorPress_Live_Lesson_Topic_Projector::project( $topic, $course, $allow, $rows, array() );
tutorpress_live_lessons_assert( $lesson === $projected[0] && $quiz === $projected[1], 'non-live topic rows stay unchanged' );
foreach ( array( 2 => $meet, 3 => $zoom ) as $index => $source ) {
	$row   = $projected[ $index ];
	$added = array_keys( $row );
	sort( $added );
	$base = array_keys( $source );
	$base = array_merge( $base, array( 'actions', 'menuOrder', 'sync_state', 'sync_version' ) );
	sort( $base );
	tutorpress_live_lessons_assert( $base === $added && $source['id'] === $row['id'] && $source['title'] === $row['title'] && $source['type'] === $row['type'] && $source['menu_order'] === $row['menu_order'] && $source['status'] === $row['status'] && 'error' === $row['sync_state'] && 0 === $row['sync_version'] && $source['menu_order'] === $row['menuOrder'] && is_array( $row['actions'] ), 'live topic rows keep legacy fields and add only the shared summary' );
}
$statuses = array( 'draft', 'private', 'pending', 'future' );
$held     = array();
foreach ( $statuses as $index => $post_status ) {
	$held[] = array( 'ID' => 40 + $index, 'post_type' => 0 === $index % 2 ? 'tutor-google-meet' : 'tutor_zoom_meeting', 'post_status' => $post_status, 'post_parent' => 8, 'post_title' => 'Held', 'id' => 40 + $index, 'title' => 'Held', 'type' => 0 === $index % 2 ? 'meet_lesson' : 'zoom_lesson', 'menu_order' => 5 + $index, 'status' => $post_status );
}
$held_rows = TutorPress_Live_Lesson_Topic_Projector::project( $topic, $course, $allow, $held, array() );
foreach ( $held as $index => $source ) {
	$row = $held_rows[ $index ];
	tutorpress_live_lessons_assert( $source['status'] === $row['status'] && $source['post_status'] === $row['post_status'] && $source['type'] === $row['type'] && $source['menu_order'] === $row['menuOrder'] && 'error' === $row['sync_state'] && 0 === $row['sync_version'] && is_array( $row['actions'] ), 'non-publish live topic rows keep their status and receive the shared summary' );
}
$wrong_topic = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 4 );
$broad      = static function ( $cap ) { return 'edit_posts' === $cap; };
$orphan     = $meet;
$orphan['post_parent'] = 99;
$orphan_rows = TutorPress_Live_Lesson_Topic_Projector::project( $topic, $course, $allow, array( $orphan ), array() );
$redacted    = true;
foreach ( $projected[2]['actions'] as $action ) {
	$redacted = $redacted && array() === array_diff( array_keys( $action ), array( 'action', 'kind', 'version', 'operation_id' ) );
}
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Topic_Projector::project( $wrong_topic, $course, $allow, $rows, array() ) && null === TutorPress_Live_Lesson_Topic_Projector::project( $topic, $course, $broad, $rows, array() ) && $orphan === $orphan_rows[0] && $redacted, 'a mismatched relation and broad edit_posts cannot authorize projection' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lesson_Cutover::ENABLED && is_array( $projected ), 'the cutover constant stays false while an authorized projection still succeeds' );
tutorpress_live_lessons_pass( 'authorized topic-content projection' );
