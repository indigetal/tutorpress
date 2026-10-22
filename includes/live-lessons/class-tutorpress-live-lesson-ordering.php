<?php
/**
 * Mixed topic-child order for dormant live lessons.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** One menu order across every supported topic child. */
final class TutorPress_Live_Lesson_Ordering {
	/** MAX(menu_order) + 1. An empty supported set returns 1. */
	public static function next_menu_order( array $children ) {
		$max = null;
		foreach ( self::supported( $children ) as $child ) {
			$order = (int) $child['menu_order'];
			$max   = null === $max || $order > $max ? $order : $max;
		}
		return null === $max ? 1 : $max + 1;
	}

	/** Supported children ordered by menu_order, then post ID. */
	public static function sequence( array $children ) {
		$supported = self::supported( $children );
		usort(
			$supported,
			static function ( $left, $right ) {
				$by_order = ( (int) $left['menu_order'] ) <=> ( (int) $right['menu_order'] );
				return 0 !== $by_order ? $by_order : ( (int) $left['ID'] ) <=> ( (int) $right['ID'] );
			}
		);
		return $supported;
	}

	/** Keep every supported topic child. Order does not vary by type. */
	private static function supported( array $children ) {
		$types = array( 'lesson', 'tutor_quiz', 'tutor_assignments', 'tutor-google-meet', 'tutor_zoom_meeting' );
		$found = array();
		foreach ( $children as $child ) {
			if ( is_array( $child ) && isset( $child['post_type'], $child['menu_order'], $child['ID'] ) && in_array( $child['post_type'], $types, true ) ) {
				$found[] = $child;
			}
		}
		return $found;
	}
}
