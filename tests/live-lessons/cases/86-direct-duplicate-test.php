<?php

$source_course = array( 'ID' => 7, 'post_type' => 'courses' );
$target_course = array( 'ID' => 9, 'post_type' => 'courses', 'post_author' => 42 );
$ownerless     = array( 'ID' => 9, 'post_type' => 'courses' );
$topic         = array( 'ID' => 8, 'post_type' => 'topics', 'post_parent' => 7 );
$target        = array( 'ID' => 80, 'post_type' => 'topics', 'post_parent' => 9 );
$bad_target    = array( 'ID' => 80, 'post_type' => 'topics', 'post_parent' => 7 );
$google        = array( 'ID' => 40, 'post_type' => 'tutor-google-meet', 'post_parent' => 8, 'post_title' => 'Standup', 'post_content' => 'Notes', 'post_author' => 3 );
$bad_google    = array( 'ID' => 40, 'post_type' => 'tutor-google-meet', 'post_parent' => 4, 'post_title' => 'Standup', 'post_content' => 'Notes' );
$zoom          = array( 'ID' => 15, 'post_type' => 'tutor_zoom_meeting', 'post_parent' => 8, 'post_title' => 'Zoom', 'post_content' => 'Z' );
$allow         = static function ( $cap, $id ) { return 'edit_post' === $cap && ( 7 === (int) $id || 9 === (int) $id ); };
$source_only   = static function ( $cap, $id ) { return 'edit_post' === $cap && 7 === (int) $id; };
$target_only   = static function ( $cap, $id ) { return 'edit_post' === $cap && 9 === (int) $id; };
$deny          = static function () { return false; };
$broad         = static function ( $cap ) { return 'edit_posts' === $cap; };
$children      = array(
	array( 'ID' => 1, 'post_type' => 'lesson', 'menu_order' => 4, 'post_parent' => 80 ),
	array( 'ID' => 2, 'post_type' => 'tutor_quiz', 'menu_order' => 2, 'post_parent' => 80 ),
	array( 'ID' => 3, 'post_type' => 'tutor_assignments', 'menu_order' => 9, 'post_parent' => 80 ),
	array( 'ID' => 4, 'post_type' => 'tutor_zoom_meeting', 'menu_order' => 1, 'post_parent' => 80 ),
	array( 'ID' => 5, 'post_type' => 'page', 'menu_order' => 100, 'post_parent' => 80 ),
);
$meta          = array(
	'tutor-google-meet-start-datetime' => '2026-09-21 15:04:00',
	'tutor-google-meet-end-datetime'   => '2026-09-21 16:04:00',
	'tutor-google-meet-event-details'  => '{"id":"evt-1","meet_link":"https://meet.example"}',
	'tutor-google-meet-link'           => 'https://meet.example',
	'_tutor_gmt_data'                  => '{"id":"gmt-1"}',
	'_tutor_zm_data'                   => '{"id":"z-1","host_id":"host-1"}',
);
$posts         = TutorPress_Live_Lessons_Store_Fake::$posts;
$stored_meta   = TutorPress_Live_Lessons_Store_Fake::$meta;
$calls         = TutorPress_Live_Lessons_Adapter_Fake::$calls;
$hooks         = TutorPress_Live_Lessons_Hook_Fake::$calls;
$ledger        = TutorPress_Live_Lessons_Boundary_Fake::$ledger;
