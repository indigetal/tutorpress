<?php

$course    = array( 'ID' => 7, 'post_type' => 'courses' );
$topic     = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$bad_topic = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 3 );
$allow     = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === (int) $id; };
$deny      = static function () { return false; };
$broad     = static function ( $cap ) { return 'edit_posts' === $cap; };
$lesson    = array( 'ID' => 1, 'post_type' => 'lesson', 'post_parent' => 8, 'menu_order' => 4 );
$google    = array( 'ID' => 2, 'post_type' => 'tutor-google-meet', 'post_parent' => 8, 'menu_order' => 2, 'post_title' => 'Standup' );
$assignment = array( 'ID' => 3, 'post_type' => 'tutor_assignments', 'post_parent' => 8, 'menu_order' => 9 );
$zoom      = array( 'ID' => 4, 'post_type' => 'tutor_zoom_meeting', 'post_parent' => 8, 'menu_order' => 1, 'post_title' => 'Room' );
$quiz      = array( 'ID' => 5, 'post_type' => 'tutor_quiz', 'post_parent' => 8, 'menu_order' => 3 );
$page      = array( 'ID' => 6, 'post_type' => 'page', 'post_parent' => 8, 'menu_order' => 100 );
$stray_lesson = array( 'ID' => 1, 'post_type' => 'lesson', 'post_parent' => 4, 'menu_order' => 4 );
$stray_zoom   = array( 'ID' => 4, 'post_type' => 'tutor_zoom_meeting', 'post_parent' => 4, 'menu_order' => 1 );
$children  = array( $lesson, $google, $assignment, $zoom, $quiz );
$before    = $children;
$posts     = TutorPress_Live_Lessons_Store_Fake::$posts;
$meta      = TutorPress_Live_Lessons_Store_Fake::$meta;
$calls     = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$hooks     = TutorPress_Live_Lessons_Hook_Fake::$calls;
$ledger    = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
$plan      = TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, $children, $allow );
$unchanged = static function () use ( $posts, $meta, $calls, $hooks, $ledger ) {
	return $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $meta === TutorPress_Live_Lessons_Store_Fake::$meta && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $hooks === TutorPress_Live_Lessons_Hook_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger;
};

tutorpress_live_lessons_assert( array( 'course_id' => 7, 'topic_id' => 8, 'children' => array( $google, $zoom, $lesson, $assignment, $quiz ) ) === $plan && $before === $children && $unchanged(), 'cascade preflight orders provider-backed children before non-live children' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $bad_topic, $course, $children, $allow ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, $children, $deny ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, $children, $broad ) && $unchanged(), 'cascade preflight requires edit_post on the topic course' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( $lesson, $stray_zoom ), $allow ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( $google, $stray_lesson ), $allow ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( $lesson, $page ), $allow ) && $unchanged(), 'a child on another topic or an unsupported type rejects the plan' );
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( 'not-a-child' ), $allow ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( array( 'post_type' => 'lesson', 'post_parent' => 8 ) ), $allow ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( array( 'ID' => false, 'post_type' => 'lesson', 'post_parent' => 8 ) ), $allow ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( array( 'ID' => 1, 'post_type' => 'lesson', 'post_parent' => false ) ), $allow ) && null === TutorPress_Live_Lesson_Cascade_Delete::preflight( $topic, $course, array( array( 'ID' => 1, 'post_type' => 'lesson', 'post_parent' => 0 ) ), $allow ) && $unchanged(), 'a malformed non-live child rejects the plan' );
tutorpress_live_lessons_pass( 'cascade preflight is read-only' );

$seen   = array();
$delete = static function ( $child ) use ( &$seen ) {
	$seen[] = (int) $child['ID'];
	return true;
};
TutorPress_Live_Lessons_Hook_Fake::$calls = array();
$done = TutorPress_Live_Lesson_Cascade_Delete::delete_provider_children( $topic, $course, $children, $allow, $delete );
tutorpress_live_lessons_assert( array( 2, 4 ) === $seen && array( 'deleted' => array( 2, 4 ), 'stopped' => null, 'parent_retained' => true ) === $done && array( array( 'tutorpress_live_lesson_deleted', array( 2, array( 'id' => 2, 'title' => 'Standup', 'type' => 'google_meet', 'topicId' => 8 ) ) ), array( 'tutorpress_live_lesson_deleted', array( 4, array( 'id' => 4, 'title' => 'Room', 'type' => 'zoom', 'topicId' => 8 ) ) ) ) === TutorPress_Live_Lessons_Hook_Fake::$calls && $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $meta === TutorPress_Live_Lessons_Store_Fake::$meta && $calls === TutorPress_Live_Lessons_Adapter_Fake::$calls && $ledger === TutorPress_Live_Lessons_Boundary_Fake::$ledger, 'provider deletes run in plan order and keep the parent' );

$seen   = array();
$delete = static function ( $child ) use ( &$seen ) {
	$seen[] = (int) $child['ID'];
	return 4 === (int) $child['ID'] ? array( 'outcome' => 'uncertain' ) : array( 'outcome' => 'confirmed' );
};
TutorPress_Live_Lessons_Hook_Fake::$calls = array();
$stopped = TutorPress_Live_Lesson_Cascade_Delete::delete_provider_children( $topic, $course, $children, $allow, $delete );
tutorpress_live_lessons_assert( array( 2, 4 ) === $seen && array( 'deleted' => array( 2 ), 'stopped' => 4, 'parent_retained' => true ) === $stopped && array( array( 'tutorpress_live_lesson_deleted', array( 2, array( 'id' => 2, 'title' => 'Standup', 'type' => 'google_meet', 'topicId' => 8 ) ) ) ) === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an uncertain live delete stops before the remaining children' );

foreach ( array( new WP_Error( 'stop', 'stop' ), false, null, 0 ) as $bad ) {
	$seen   = array();
	$delete = static function () use ( &$seen, $bad ) {
		$seen[] = 1;
		return $bad;
	};
	TutorPress_Live_Lessons_Hook_Fake::$calls = array();
	$halted = TutorPress_Live_Lesson_Cascade_Delete::delete_provider_children( $topic, $course, $children, $allow, $delete );
	tutorpress_live_lessons_assert( array( 1 ) === $seen && array( 'deleted' => array(), 'stopped' => 2, 'parent_retained' => true ) === $halted && array() === TutorPress_Live_Lessons_Hook_Fake::$calls, 'an unconfirmed live delete does not fire the hook' );
}
$seen   = array();
$delete = static function () use ( &$seen ) {
	$seen[] = 1;
	return true;
};
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Cascade_Delete::delete_provider_children( $topic, $course, $children, $deny, $delete ) && null === TutorPress_Live_Lesson_Cascade_Delete::delete_provider_children( $topic, $course, $children, $broad, $delete ) && null === TutorPress_Live_Lesson_Cascade_Delete::delete_provider_children( $topic, $course, $children, $allow, 'not-callable' ) && array() === $seen, 'unauthorized cascade delete does not dispatch' );
tutorpress_live_lessons_pass( 'cascade provider delete stops' );

$order  = array();
$delete = static function ( $child ) use ( &$order ) {
	$order[] = (int) $child['ID'];
	return true;
};
$remove = static function ( $child ) use ( &$order ) {
	$order[] = (int) $child['ID'];
	return true;
};
$parent_calls = array();
$delete_parent = static function ( $row ) use ( &$parent_calls ) {
	$parent_calls[] = (int) $row['ID'];
	return true;
};
$done = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, $remove, $delete_parent );
tutorpress_live_lessons_assert( array( 2, 4, 1, 3, 5 ) === $order && array( 8 ) === $parent_calls && array( 'completed' => array( 2, 4, 1, 3, 5 ), 'failed' => null, 'parent_deleted' => true, 'status' => 'complete' ) === $done && false === strpos( json_encode( $done ), '207' ), 'cascade execute deletes the parent only after every child confirms' );

$order        = array();
$remove_calls = array();
$parent_calls = array();
$delete       = static function ( $child ) use ( &$order ) {
	$order[] = (int) $child['ID'];
	return 4 === (int) $child['ID'] ? array( 'outcome' => 'uncertain' ) : true;
};
$remove = static function ( $child ) use ( &$remove_calls ) {
	$remove_calls[] = (int) $child['ID'];
	return true;
};
$delete_parent = static function ( $row ) use ( &$parent_calls ) {
	$parent_calls[] = (int) $row['ID'];
	return true;
};
$stopped = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, $remove, $delete_parent );
tutorpress_live_lessons_assert( array( 2, 4 ) === $order && array() === $remove_calls && array() === $parent_calls && array( 'completed' => array( 2 ), 'failed' => 4, 'parent_deleted' => false, 'status' => 'stopped' ) === $stopped, 'a stopped live delete skips non-live handlers and the parent' );

$order        = array();
$remove_calls = array();
$parent_calls = array();
$delete       = static function ( $child ) use ( &$order ) {
	$order[] = (int) $child['ID'];
	return true;
};
$remove = static function ( $child ) use ( &$remove_calls ) {
	$remove_calls[] = (int) $child['ID'];
	return 1 === (int) $child['ID'] ? new WP_Error( 'stop', 'secret-child-error' ) : true;
};
$delete_parent = static function ( $row ) use ( &$parent_calls ) {
	$parent_calls[] = (int) $row['ID'];
	return true;
};
$halted = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, $remove, $delete_parent );
tutorpress_live_lessons_assert( array( 1 ) === $remove_calls && array() === $parent_calls && array( 'completed' => array( 2, 4 ), 'failed' => 1, 'parent_deleted' => false, 'status' => 'stopped' ) === $halted && false === strpos( json_encode( $halted ), 'secret-child-error' ) && false === strpos( json_encode( $halted ), '207' ), 'a failed non-live child stops before the parent' );

$parent_calls = array();
$delete       = static function () { return true; };
$remove       = static function () { return true; };
$delete_parent = static function ( $row ) use ( &$parent_calls ) {
	$parent_calls[] = (int) $row['ID'];
	return false;
};
$kept = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, $remove, $delete_parent );
tutorpress_live_lessons_assert( array( 8 ) === $parent_calls && array( 'completed' => array( 2, 4, 1, 3, 5 ), 'failed' => 8, 'parent_deleted' => false, 'status' => 'stopped' ) === $kept, 'an unconfirmed parent delete stays stopped' );

$calls = 0;
$delete = static function () use ( &$calls ) {
	$calls++;
	return true;
};
tutorpress_live_lessons_assert( null === TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $deny, $delete, $remove, $delete_parent ) && null === TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, 'nope', $delete_parent ) && 0 === $calls, 'unauthorized or non-callable cascade execute does not dispatch' );
tutorpress_live_lessons_pass( 'cascade execute stops without 207' );

$foreign    = array( 'ID' => 99, 'post_type' => 'lesson', 'post_parent' => 4, 'menu_order' => 1 );
$dispatches = 0;
$noop       = static function () use ( &$dispatches ) {
	$dispatches++;
	return true;
};
$denied = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, array( $lesson, $foreign ), $allow, $noop, $noop, $noop );
tutorpress_live_lessons_assert( null === $denied && null === TutorPress_Live_Lesson_Cascade_Delete::execute( $bad_topic, $course, $children, $allow, $noop, $noop, $noop ) && null === TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $broad, $noop, $noop, $noop ) && 0 === $dispatches && false === strpos( (string) json_encode( $denied ), '99' ), 'an unauthorized child id is not projected' );

$snapshot = $children;
$adapter  = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$boundary = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
TutorPress_Live_Lessons_Hook_Fake::$calls = array();
$confirm = static function () { return true; };
$ran = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $confirm, $confirm, $confirm );
tutorpress_live_lessons_assert( $snapshot === $children && $posts === TutorPress_Live_Lessons_Store_Fake::$posts && $meta === TutorPress_Live_Lessons_Store_Fake::$meta && $adapter === TutorPress_Live_Lessons_Adapter_Fake::$calls && $boundary === TutorPress_Live_Lessons_Boundary_Fake::$ledger && array( array( 'tutorpress_live_lesson_deleted', array( 2, array( 'id' => 2, 'title' => 'Standup', 'type' => 'google_meet', 'topicId' => 8 ) ) ), array( 'tutorpress_live_lesson_deleted', array( 4, array( 'id' => 4, 'title' => 'Room', 'type' => 'zoom', 'topicId' => 8 ) ) ) ) === TutorPress_Live_Lessons_Hook_Fake::$calls, 'confirming execute keeps the child list and orders the live hooks' );

$remove_calls = array();
$parent_calls = array();
$delete       = static function () { return array( 'outcome' => 'uncertain' ); };
$remove       = static function ( $child ) use ( &$remove_calls ) {
	$remove_calls[] = (int) $child['ID'];
	return true;
};
$delete_parent = static function ( $row ) use ( &$parent_calls ) {
	$parent_calls[] = (int) $row['ID'];
	return true;
};
TutorPress_Live_Lessons_Hook_Fake::$calls = array();
$first = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, $remove, $delete_parent );
tutorpress_live_lessons_assert( array( 'completed' => array(), 'failed' => 2, 'parent_deleted' => false, 'status' => 'stopped' ) === $first && array() === $remove_calls && array() === $parent_calls && array() === TutorPress_Live_Lessons_Hook_Fake::$calls, 'the first live child stops the cascade' );

$remove_calls = array();
$parent_calls = array();
$delete       = static function () { return true; };
$remove       = static function ( $child ) use ( &$remove_calls ) {
	$remove_calls[] = (int) $child['ID'];
	return 3 === (int) $child['ID'] ? new WP_Error( 'stop', 'secret-position' ) : true;
};
$delete_parent = static function ( $row ) use ( &$parent_calls ) {
	$parent_calls[] = (int) $row['ID'];
	return true;
};
$middle = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, $remove, $delete_parent );
tutorpress_live_lessons_assert( array( 1, 3 ) === $remove_calls && array() === $parent_calls && array( 'completed' => array( 2, 4, 1 ), 'failed' => 3, 'parent_deleted' => false, 'status' => 'stopped' ) === $middle && array( 'completed', 'failed', 'parent_deleted', 'status' ) === array_keys( $middle ) && false === strpos( json_encode( $middle ), 'secret-position' ) && false === strpos( json_encode( $middle ), '207' ), 'an assignment failure redacts the partial result' );

$remove_calls = array();
$parent_calls = array();
$remove       = static function ( $child ) use ( &$remove_calls ) {
	$remove_calls[] = (int) $child['ID'];
	return 5 === (int) $child['ID'] ? false : true;
};
$delete_parent = static function ( $row ) use ( &$parent_calls ) {
	$parent_calls[] = (int) $row['ID'];
	return true;
};
$last = TutorPress_Live_Lesson_Cascade_Delete::execute( $topic, $course, $children, $allow, $delete, $remove, $delete_parent );
tutorpress_live_lessons_assert( array( 1, 3, 5 ) === $remove_calls && array() === $parent_calls && array( 'completed' => array( 2, 4, 1, 3 ), 'failed' => 5, 'parent_deleted' => false, 'status' => 'stopped' ) === $last, 'a quiz failure retains the parent' );
tutorpress_live_lessons_pass( 'cascade matrix' );
