/**
 * @fileoverview Shared form utilities for modal implementations
 * @description Common form transformation, validation, and data conversion utilities
 *              that can be reused across different modal types (Quiz, Interactive Quiz, etc.)
 *
 * @features
 * - Tutor LMS boolean conversion utilities
 * - Time limit handling utilities
 * - Content drip settings utilities
 * - Form data transformation helpers
 *
 * @example
 * import { convertTutorBooleans, convertBooleansToIntegers, createTimeLimit } from '../utils/form';
 *
 * const settings = convertTutorBooleans(rawSettings);
 * const tutorSettings = convertBooleansToIntegers(formSettings);
 * const timeLimit = createTimeLimit(30, 'minutes');
 */

/**
 * Fields that Tutor LMS stores as integers (0/1) but we want to work with as booleans
 */
export const TUTOR_BOOLEAN_FIELDS = [
  "hide_quiz_time_display",
  "quiz_auto_start",
  "hide_question_number_overview",
  "pass_is_required",
] as const;

/**
 * Convert Tutor LMS integer booleans (0/1) to actual booleans
 *
 * @param settings - Settings object with potential integer boolean fields
 * @returns Settings object with converted boolean fields
 *
 * @example
 * const converted = convertTutorBooleans({ quiz_auto_start: 1, passing_grade: 80 });
 * // Result: { quiz_auto_start: true, passing_grade: 80 }
 */
export const convertTutorBooleans = (settings: Record<string, any>): Record<string, any> => {
  const converted = { ...settings };

  TUTOR_BOOLEAN_FIELDS.forEach((field) => {
    if (field in converted) {
      // Convert integer (0/1) or string ("0"/"1") to boolean
      converted[field] = converted[field] === 1 || converted[field] === "1" || converted[field] === true;
    }
  });

  return converted;
};

/**
 * Convert booleans back to integers (0/1) for Tutor LMS compatibility
 *
 * @param settings - Settings object with boolean fields
 * @returns Settings object with integer boolean fields for Tutor LMS
 *
 * @example
 * const converted = convertBooleansToIntegers({ quiz_auto_start: true, passing_grade: 80 });
 * // Result: { quiz_auto_start: 1, passing_grade: 80 }
 */
export const convertBooleansToIntegers = (settings: Record<string, any>): Record<string, any> => {
  const converted = { ...settings };

  TUTOR_BOOLEAN_FIELDS.forEach((field) => {
    if (field in converted && typeof converted[field] === "boolean") {
      // Convert boolean to integer (0/1) for Tutor LMS
      converted[field] = converted[field] ? 1 : 0;
    }
  });

  return converted;
};

/**
 * Time unit types for time limit settings
 */
export type TimeUnit = "seconds" | "minutes" | "hours" | "days" | "weeks";

/**
 * Time limit structure used by Tutor LMS
 */
export interface TimeLimit {
  time_value: number;
  time_type: TimeUnit;
}

/**
 * Create a time limit object from value and type
 *
 * @param timeValue - Numeric time value
 * @param timeType - Time unit type
 * @returns TimeLimit object for Tutor LMS
 *
 * @example
 * const timeLimit = createTimeLimit(30, 'minutes');
 * // Result: { time_value: 30, time_type: 'minutes' }
 */
export const createTimeLimit = (timeValue: number, timeType: TimeUnit): TimeLimit => ({
  time_value: timeValue,
  time_type: timeType,
});

/**
 * Content drip settings structure
 */
export interface ContentDripSettings {
  after_xdays_of_enroll: number;
  [key: string]: any;
}

/**
 * Create content drip settings with after-days configuration
 *
 * @param afterDays - Number of days after enrollment
 * @param existingSettings - Existing content drip settings to merge
 * @returns Updated content drip settings
 *
 * @example
 * const dripSettings = createContentDripSettings(7, existingSettings);
 * // Result: { ...existingSettings, after_xdays_of_enroll: 7 }
 */
export const createContentDripSettings = (
  afterDays: number,
  existingSettings: Partial<ContentDripSettings> = {}
): ContentDripSettings => ({
  ...existingSettings,
  after_xdays_of_enroll: afterDays,
});

/**
 * Utility to safely trim string values in form data
 *
 * @param value - String value to trim
 * @returns Trimmed string or empty string if value is null/undefined
 *
 * @example
 * const title = safeStringTrim(formData.title);
 */
export const safeStringTrim = (value: string | null | undefined): string => {
  return (value || "").trim();
};

/**
 * Utility to merge settings objects with proper type handling
 *
 * @param baseSettings - Base settings object
 * @param updates - Settings updates to apply
 * @returns Merged settings object
 *
 * @example
 * const newSettings = mergeSettings(currentSettings, { passing_grade: 85 });
 */
export const mergeSettings = <T extends Record<string, any>>(baseSettings: T, updates: Partial<T>): T => ({
  ...baseSettings,
  ...updates,
});

/**
 * Utility to create form data structure for API submission
 *
 * @param formData - Form data with title, description, and settings
 * @param additionalData - Additional data to include (ID, questions, etc.)
 * @returns Complete form data object ready for API submission
 *
 * @example
 * const apiData = createFormSubmissionData(
 *   { title: 'Quiz Title', description: 'Description', settings: {...} },
 *   { ID: 123, questions: [...] }
 * );
 */
export const createFormSubmissionData = <TSettings = Record<string, any>>(
  formData: {
    title: string;
    description: string;
    settings: TSettings;
  },
  additionalData: Record<string, any> = {}
): Record<string, any> => ({
  post_title: safeStringTrim(formData.title),
  post_content: safeStringTrim(formData.description),
  ...additionalData,
  // Convert settings for Tutor LMS compatibility
  ...(typeof formData.settings === "object" && formData.settings !== null
    ? { quiz_option: convertBooleansToIntegers(formData.settings) }
    : {}),
});

/**
 * Utility to validate numeric field ranges
 *
 * @param value - Numeric value to validate
 * @param min - Minimum allowed value (inclusive)
 * @param max - Maximum allowed value (inclusive)
 * @returns True if value is within range, false otherwise
 *
 * @example
 * const isValid = isNumericInRange(85, 0, 100); // true
 * const isInvalid = isNumericInRange(150, 0, 100); // false
 */
export const isNumericInRange = (value: number, min: number, max: number): boolean => {
  return !isNaN(value) && value >= min && value <= max;
};

/**
 * Utility to validate required string fields
 *
 * @param value - String value to validate
 * @param minLength - Minimum required length (default: 1)
 * @returns True if string meets requirements, false otherwise
 *
 * @example
 * const isValid = isRequiredString('Quiz Title'); // true
 * const isInvalid = isRequiredString('   '); // false
 */
export const isRequiredString = (value: string | null | undefined, minLength: number = 1): boolean => {
  const trimmed = safeStringTrim(value);
  return trimmed.length >= minLength;
};
