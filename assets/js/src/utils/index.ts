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
