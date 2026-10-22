/**
 * Live Lessons Modal Components Index
 *
 * @description Exports all Live Lessons modal components for easy importing.
 *              Follows the established pattern from other modal directories.
 *
 * @package TutorPress
 * @subpackage Components/Modals/LiveLessons
 * @since 1.5.2
 */

import { localizedTimezoneOptions } from "../../../utils/liveLessonDateTime";

export { LiveLessonModal } from "./LiveLessonModal";
export { GoogleMeetForm } from "./GoogleMeetForm";
export { ZoomForm } from "./ZoomForm";

/** Timezone choices from the localized server list. */
export const generateTimezoneOptions = (): { value: string; label: string }[] =>
  localizedTimezoneOptions(window.tutorPressCurriculum?.liveLessonTimezoneOptions);
