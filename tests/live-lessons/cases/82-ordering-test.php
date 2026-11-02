<?php

$lesson = array( 'ID' => 30, 'post_type' => 'lesson', 'menu_order' => 2 );
$quiz   = array( 'ID' => 10, 'post_type' => 'tutor_quiz', 'menu_order' => 2 );
$assign = array( 'ID' => 20, 'post_type' => 'tutor_assignments', 'menu_order' => 0 );
$meet   = array( 'ID' => 40, 'post_type' => 'tutor-google-meet', 'menu_order' => 5 );
$zoom   = array( 'ID' => 15, 'post_type' => 'tutor_zoom_meeting', 'menu_order' => 5 );
$other  = array( 'ID' => 99, 'post_type' => 'page', 'menu_order' => 9 );
$mixed  = array( $meet, $zoom, $lesson, $quiz, $assign, $other );

tutorpress_live_lessons_assert( class_exists( 'TutorPress_Live_Lesson_Ordering', false ) && 1 === TutorPress_Live_Lesson_Ordering::next_menu_order( array() ) && array() === TutorPress_Live_Lesson_Ordering::sequence( array() ), 'an empty topic allocates menu order 1' );
tutorpress_live_lessons_assert( 6 === TutorPress_Live_Lesson_Ordering::next_menu_order( $mixed ) && 6 === TutorPress_Live_Lesson_Ordering::next_menu_order( array( $zoom, $meet, $lesson ) ), 'next order is one max across every supported topic child' );
$ids = array();
foreach ( TutorPress_Live_Lesson_Ordering::sequence( $mixed ) as $child ) {
	$ids[] = $child['ID'];
}
tutorpress_live_lessons_assert( array( 20, 10, 30, 15, 40 ) === $ids, 'sequence is menu order then post ID for every supported type' );
tutorpress_live_lessons_pass( 'mixed topic-child order' );
