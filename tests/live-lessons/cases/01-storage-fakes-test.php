<?php

$id = TutorPress_Live_Lessons_Store_Fake::insert_publish_post( array( 'post_title' => 'live' ) );
tutorpress_live_lessons_assert( 'publish' === TutorPress_Live_Lessons_Store_Fake::$posts[ $id ]['post_status'], 'insert is publish' );

$first  = TutorPress_Live_Lessons_Store_Fake::add_meta( $id, 'k', 'one' );
$second = TutorPress_Live_Lessons_Store_Fake::add_meta( $id, 'k', 'two' );
$rows   = TutorPress_Live_Lessons_Store_Fake::meta_rows( $id, 'k' );
tutorpress_live_lessons_assert( 2 === count( $rows ) && $first !== $second && 'one' === $rows[0]['meta_value'] && 'two' === $rows[1]['meta_value'], 'duplicate meta rows remain' );

TutorPress_Live_Lessons_Store_Fake::$fail_deletes[ $id ] = true;
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::delete_post( $id ), 'cleanup can fail' );
tutorpress_live_lessons_assert( isset( TutorPress_Live_Lessons_Store_Fake::$posts[ $id ] ) && 2 === count( TutorPress_Live_Lessons_Store_Fake::meta_rows( $id, 'k' ) ), 'failed cleanup leaves post and meta' );

tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Store_Fake::add_option( 'lock', 'v1' ) && 1 === TutorPress_Live_Lessons_Store_Fake::$affected, 'add_option writes' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::add_option( 'lock', 'v2' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && 'v1' === TutorPress_Live_Lessons_Store_Fake::$options['lock'], 'duplicate add_option refuses' );
tutorpress_live_lessons_assert( false === TutorPress_Live_Lessons_Store_Fake::update_option_bytes( 'lock', 'v2', 'wrong' ) && 0 === TutorPress_Live_Lessons_Store_Fake::$affected && 'v1' === TutorPress_Live_Lessons_Store_Fake::$options['lock'] && array() === TutorPress_Live_Lessons_Store_Fake::$cache, 'exact-byte mismatch does not write' );
tutorpress_live_lessons_assert( true === TutorPress_Live_Lessons_Store_Fake::update_option_bytes( 'lock', 'v2', 'v1' ) && 1 === TutorPress_Live_Lessons_Store_Fake::$affected && 'v2' === TutorPress_Live_Lessons_Store_Fake::$options['lock'] && array( 'lock' ) === TutorPress_Live_Lessons_Store_Fake::$cache, 'matching update invalidates cache' );

tutorpress_live_lessons_pass( 'storage fakes: duplicates, failed cleanup, and option CAS' );
