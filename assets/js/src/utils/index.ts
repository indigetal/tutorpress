/**
 * @fileoverview Utility functions index
 * @description Central export point for utility functions used across the application
 */

// Quiz form utilities
export {
  convertTutorBooleans,
  convertBooleansToIntegers,
  createTimeLimit,
  createContentDripSettings,
  safeStringTrim,
  mergeSettings,
  createFormSubmissionData,
  isNumericInRange,
  isRequiredString,
  TUTOR_BOOLEAN_FIELDS,
} from "./quizForm";

export type { TimeUnit, TimeLimit, ContentDripSettings } from "./quizForm";

// Addon detection utilities
export {
  AddonChecker,
  isCoursePreviewEnabled,
  isGoogleMeetEnabled,
  isZoomEnabled,
  isH5pEnabled,
  isH5pPluginActive,
  isCertificateEnabled,
  isContentDripEnabled,
  isPrerequisitesEnabled,
  isMultiInstructorsEnabled,
  isEnrollmentsEnabled,
  isAnyLiveLessonEnabled,
  getAvailableLiveLessonTypes,
} from "./addonChecker";

export type { AddonKey, AddonStatus } from "./addonChecker";
