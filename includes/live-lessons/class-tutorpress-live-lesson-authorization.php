<?php
/**
 * Relationship authorization for live lessons.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Dormant type and parent checks. Course edit_post authorizes. No provider behavior. */
class TutorPress_Live_Lesson_Authorization {
	public static function authorize( array $item, array $topic, array $course, $can, $target = null ) {
		$ok = self::typed( $course, array( 'courses' ) ) && self::typed( $topic, array( 'topics' ) ) && self::typed( $item, array( 'tutor-google-meet', 'tutor_zoom_meeting' ) );
		$ok = $ok && isset( $item['post_parent'], $topic['post_parent'] ) && (int) $item['post_parent'] === (int) $topic['ID'] && (int) $topic['post_parent'] === (int) $course['ID'];
		$ok = $ok && ( null === $target || ( is_array( $target ) && self::typed( $target, array( 'topics' ) ) && isset( $target['post_parent'] ) && (int) $target['post_parent'] === (int) $course['ID'] ) );
		$id = isset( $course['ID'] ) ? (int) $course['ID'] : 0;
		return $ok && $id > 0 && is_callable( $can ) && true === $can( 'edit_post', $id ) ? $id : null;
	}

	private static function typed( array $post, array $types ) {
		return isset( $post['post_type'], $post['ID'] ) && in_array( $post['post_type'], $types, true );
	}

	public static function authorize_course( array $course, $can ) {
		$id = isset( $course['ID'] ) ? (int) $course['ID'] : 0;
		return self::typed( $course, array( 'courses' ) ) && $id > 0 && is_callable( $can ) && true === $can( 'edit_post', $id ) ? $id : null;
	}

	public static function authorize_create( array $topic, array $course, $can ) {
		return self::typed( $topic, array( 'topics' ) ) && isset( $topic['post_parent'] ) && self::typed( $course, array( 'courses' ) ) && (int) $topic['post_parent'] === (int) $course['ID'] ? self::authorize_course( $course, $can ) : null;
	}

	public static function authorize_projection( array $topic, array $course, $can ) {
		return self::authorize_create( $topic, $course, $can );
	}

	public static function authorize_duplicate( array $topic, array $course, array $destination, $can ) {
		return null !== self::authorize_create( $topic, $course, $can ) && null !== self::authorize_course( $destination, $can ) ? (int) $course['ID'] : null;
	}

	public static function authorize_cascade( array $topic, array $course, array $children, $can ) {
		if ( null === self::authorize_create( $topic, $course, $can ) ) {
			return null;
		}
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) || ! isset( $child['item'], $child['topic'] ) || null === self::authorize( $child['item'], $child['topic'], $course, $can ) ) {
				return null;
			}
		}
		return (int) $course['ID'];
	}
}
