<?php

tutorpress_live_lessons_assert( class_exists( 'TutorPress_Live_Lesson_Cutover', false ) && false === TutorPress_Live_Lesson_Cutover::ENABLED, 'the cutover gate constant defaults false' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lesson_Cutover::enabled(), 'the cutover accessor returns the false constant' );
$source = file_get_contents( dirname( __DIR__, 3 ) . '/includes/live-lessons/class-tutorpress-live-lesson-cutover.php' );
tutorpress_live_lessons_assert( is_string( $source ) && false === strpos( $source, 'get_option' ) && false === strpos( $source, 'update_option' ) && false === strpos( $source, 'add_option' ) && false === strpos( $source, 'apply_filters' ) && false === strpos( $source, 'add_filter' ) && false === strpos( $source, '$_GET' ) && false === strpos( $source, '$_POST' ) && false === strpos( $source, '$_REQUEST' ) && false === strpos( $source, '$_SERVER' ) && false === strpos( $source, '$_ENV' ) && false === strpos( $source, 'getenv' ) && false === strpos( $source, 'add_action' ) && false === strpos( $source, 'add_menu_page' ) && false === strpos( $source, 'is_admin' ), 'the cutover gate has no option, filter, request, admin, or environment input' );
tutorpress_live_lessons_pass( 'cutover gate defaults false' );
