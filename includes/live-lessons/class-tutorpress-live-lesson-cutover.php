<?php
/**
 * Disabled live-lesson cutover gate.
 *
 * @package TutorPress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Final source gate. The constant stays false and has no runtime override. */
final class TutorPress_Live_Lesson_Cutover {
	const ENABLED = false;

	/** Return the source constant. */
	public static function enabled() {
		return self::ENABLED;
	}
}
