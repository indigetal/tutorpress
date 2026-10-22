<?php
/**
 * Authorized topic-content projection for dormant live lessons.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Legacy topic rows, plus a shared sync summary on both live CPTs. */
final class TutorPress_Live_Lesson_Topic_Projector {
	/** Null unless the topic and course context is pre-authorized. */
	public static function project( array $topic, array $course, $can, array $rows, array $envelopes ) {
		if ( null === TutorPress_Live_Lesson_Authorization::authorize_projection( $topic, $course, $can ) ) {
			return null;
		}
		$live      = array( 'tutor-google-meet' => true, 'tutor_zoom_meeting' => true );
		$projected = array();
		foreach ( $rows as $row ) {
			$post_type = is_array( $row ) && isset( $row['post_type'], $row['ID'] ) ? $row['post_type'] : '';
			$item      = $row;
			if ( isset( $live[ $post_type ] ) && 'publish' !== ( isset( $row['post_status'] ) ? $row['post_status'] : '' ) ) {
				$item['post_status'] = 'publish';
			}
			$summary = isset( $live[ $post_type ] ) ? TutorPress_Live_Lesson_Response_Projector::project_summary( $item, $topic, $course, $can, isset( $envelopes[ (int) $row['ID'] ] ) ? $envelopes[ (int) $row['ID'] ] : null ) : null;
			if ( ! is_array( $summary ) ) {
				$projected[] = $row;
				continue;
			}
			$row['sync_state']   = $summary['sync_state'];
			$row['sync_version'] = $summary['sync_version'];
			$row['menuOrder']    = $summary['menuOrder'];
			$row['actions']      = $summary['actions'];
			$projected[]         = $row;
		}
		return $projected;
	}
}
