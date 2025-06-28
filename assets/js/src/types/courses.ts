/**
 * Course-related type definitions
 *
 * Contains base types for course functionality.
 */

/**
 * Base course interface
 */
export interface BaseCourse {
  id: number;
  title: string;
  content: string;
  status: string;
}

/**
 * Course with UI-specific properties for the curriculum editor
 */
export interface Course extends BaseCourse {
  topics: import("./curriculum").Topic[];
}

/**
 * Course Settings Infrastructure
 * Following TutorPress patterns and Tutor LMS compatibility
 */

/**
 * Course difficulty level options matching Tutor LMS
 */
export type CourseDifficultyLevel = "beginner" | "intermediate" | "expert" | "all_levels";

/**
 * Course duration settings
 */
export interface CourseDuration {
  hours: number;
  minutes: number;
}

/**
 * Course enrollment period settings
 */
export interface CourseEnrollmentPeriod {
  start_date: string;
  end_date: string;
}

/**
 * Course schedule settings
 */
export interface CourseSchedule {
  enabled: boolean;
  start_date: string;
  start_time: string;
  show_coming_soon: boolean;
}

/**
 * Featured video settings
 */
export interface CourseFeaturedVideo {
  source: "" | "youtube" | "vimeo" | "external_url" | "embedded";
  source_youtube: string;
  source_vimeo: string;
  source_external_url: string;
  source_embedded: string;
}

/**
 * Complete course settings interface matching Tutor LMS _tutor_course_settings structure
 */
export interface CourseSettings {
  // Course Details Section
  course_level: CourseDifficultyLevel;
  is_public_course: boolean;
  enable_qna: boolean;
  course_duration: CourseDuration;

  // Course Access & Enrollment Section
  course_prerequisites: number[];
  maximum_students: number | null; // null means unlimited
  maximum_students_allowed?: number | null; // Legacy field, synced with maximum_students
  schedule: CourseSchedule;
  course_enrollment_period: "yes" | "no"; // Tutor LMS uses "yes"/"no" strings
  enrollment_starts_at: string; // ISO 8601 date-time string
  enrollment_ends_at: string; // ISO 8601 date-time string
  pause_enrollment: "yes" | "no"; // Tutor LMS uses "yes"/"no" strings
  enrollment_status?: "yes" | "no"; // Legacy field, synced with pause_enrollment

  // Course Media Section
  featured_video: CourseFeaturedVideo;
  attachments: number[];
  materials_included: string;

  // Pricing Model Section
  is_free: boolean;
  pricing_model: string;
  price: number;
  sale_price: number;
  subscription_enabled: boolean;

  // Instructors Section
  instructors: number[];
  additional_instructors: number[];

  // Content Drip (existing)
  enable_content_drip?: boolean;
  content_drip_type?: string;
}

/**
 * Default course settings values
 */
export const defaultCourseSettings: CourseSettings = {
  // Course Details
  course_level: "all_levels",
  is_public_course: false,
  enable_qna: true,
  course_duration: {
    hours: 0,
    minutes: 0,
  },

  // Course Access & Enrollment
  course_prerequisites: [],
  maximum_students: null, // null means unlimited
  maximum_students_allowed: null, // Legacy field, synced with maximum_students
  schedule: {
    enabled: false,
    start_date: "",
    start_time: "",
    show_coming_soon: false,
  },
  course_enrollment_period: "no",
  enrollment_starts_at: "",
  enrollment_ends_at: "",
  pause_enrollment: "no",
  enrollment_status: undefined, // Legacy field, synced with pause_enrollment

  // Course Media
  featured_video: {
    source: "",
    source_youtube: "",
    source_vimeo: "",
    source_external_url: "",
    source_embedded: "",
  },
  attachments: [],
  materials_included: "",

  // Pricing Model
  is_free: true,
  pricing_model: "",
  price: 0,
  sale_price: 0,
  subscription_enabled: false,

  // Instructors
  instructors: [],
  additional_instructors: [],
};

/**
 * Course settings API response interface
 */
export interface CourseSettingsResponse {
  success: boolean;
  message: string;
  data: CourseSettings;
}

/**
 * Course difficulty level options for UI
 */
export const courseDifficultyLevels: Array<{
  label: string;
  value: CourseDifficultyLevel;
}> = [
  { label: "All Levels", value: "all_levels" },
  { label: "Beginner", value: "beginner" },
  { label: "Intermediate", value: "intermediate" },
  { label: "Expert", value: "expert" },
];

/**
 * Course with settings for Gutenberg editor integration
 */
export interface CourseWithSettings extends Course {
  course_settings: CourseSettings;
}
