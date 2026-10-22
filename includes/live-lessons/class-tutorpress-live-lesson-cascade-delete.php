<?php
/**
 * Dormant topic cascade preflight and provider-backed delete dispatch.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Authorized provider-first plan, then injected live deletes. No provider client call. */
final class TutorPress_Live_Lesson_Cascade_Delete {
	/** Null unless the topic, course, and every child relation are authorized. */
	public static function preflight( array $topic, array $course, array $children, $can ) {
		if ( null === TutorPress_Live_Lesson_Authorization::authorize_create( $topic, $course, $can ) ) {
			return null;
		}
		$live  = array( 'tutor-google-meet' => true, 'tutor_zoom_meeting' => true );
		$other = array( 'lesson' => true, 'tutor_quiz' => true, 'tutor_assignments' => true );
		$first = array();
		$rest  = array();
		foreach ( $children as $child ) {
			if ( ! is_array( $child ) || ! isset( $child['ID'], $child['post_parent'], $child['post_type'] ) || ! is_string( $child['post_type'] ) || (int) $child['ID'] < 1 || (int) $child['post_parent'] !== (int) $topic['ID'] ) {
				return null;
			}
			if ( isset( $live[ $child['post_type'] ] ) ) {
				if ( null === TutorPress_Live_Lesson_Authorization::authorize( $child, $topic, $course, $can ) ) {
					return null;
				}
				$first[] = $child;
				continue;
			}
			if ( ! isset( $other[ $child['post_type'] ] ) ) {
				return null;
			}
			$rest[] = $child;
		}
		return array(
			'course_id' => (int) $course['ID'],
			'topic_id'  => (int) $topic['ID'],
			'children'  => array_merge( $first, $rest ),
		);
	}

	/** Null when unauthorized. Stops on the first unconfirmed live delete and keeps the parent. */
	public static function delete_provider_children( array $topic, array $course, array $children, $can, $delete ) {
		$plan = self::preflight( $topic, $course, $children, $can );
		if ( null === $plan || ! is_callable( $delete ) ) {
			return null;
		}
		$deleted = array();
		foreach ( $plan['children'] as $child ) {
			if ( 'tutor-google-meet' !== $child['post_type'] && 'tutor_zoom_meeting' !== $child['post_type'] ) {
				break;
			}
			$snapshot = array(
				'id'      => (int) $child['ID'],
				'title'   => isset( $child['post_title'] ) && is_string( $child['post_title'] ) ? $child['post_title'] : '',
				'type'    => 'tutor-google-meet' === $child['post_type'] ? 'google_meet' : 'zoom',
				'topicId' => (int) $topic['ID'],
			);
			$result   = $delete( $child );
			if ( true !== $result && ( ! is_array( $result ) || ! isset( $result['outcome'] ) || 'confirmed' !== $result['outcome'] ) ) {
				return array( 'deleted' => $deleted, 'stopped' => $snapshot['id'], 'parent_retained' => true );
			}
			do_action( 'tutorpress_live_lesson_deleted', $snapshot['id'], $snapshot );
			$deleted[] = $snapshot['id'];
		}
		return array( 'deleted' => $deleted, 'stopped' => null, 'parent_retained' => true );
	}

	/** Stop on the first unconfirmed child. Delete the parent only after every child confirms. */
	public static function execute( array $topic, array $course, array $children, $can, $delete, $remove, $delete_parent ) {
		if ( ! is_callable( $delete ) || ! is_callable( $remove ) || ! is_callable( $delete_parent ) ) {
			return null;
		}
		$plan = self::preflight( $topic, $course, $children, $can );
		if ( null === $plan ) {
			return null;
		}
		$live = self::delete_provider_children( $topic, $course, $children, $can, $delete );
		if ( null === $live ) {
			return null;
		}
		$completed = $live['deleted'];
		if ( null !== $live['stopped'] ) {
			return array( 'completed' => $completed, 'failed' => $live['stopped'], 'parent_deleted' => false, 'status' => 'stopped' );
		}
		foreach ( $plan['children'] as $child ) {
			if ( 'tutor-google-meet' === $child['post_type'] || 'tutor_zoom_meeting' === $child['post_type'] ) {
				continue;
			}
			$result = $remove( $child );
			if ( true !== $result && ( ! is_array( $result ) || ! isset( $result['outcome'] ) || 'confirmed' !== $result['outcome'] ) ) {
				return array( 'completed' => $completed, 'failed' => (int) $child['ID'], 'parent_deleted' => false, 'status' => 'stopped' );
			}
			$completed[] = (int) $child['ID'];
		}
		$parent = $delete_parent( $topic );
		if ( true !== $parent && ( ! is_array( $parent ) || ! isset( $parent['outcome'] ) || 'confirmed' !== $parent['outcome'] ) ) {
			return array( 'completed' => $completed, 'failed' => (int) $topic['ID'], 'parent_deleted' => false, 'status' => 'stopped' );
		}
		return array( 'completed' => $completed, 'failed' => null, 'parent_deleted' => true, 'status' => 'complete' );
	}
}
