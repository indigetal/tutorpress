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
 * Course intro video settings (renamed from featured_video for clarity)
 * Full compatibility with lesson video sources including html5 and shortcode
 */
export interface CourseIntroVideo {
  source: "" | "html5" | "youtube" | "vimeo" | "external_url" | "embedded" | "shortcode";
  source_video_id: number; // For HTML5 uploads
  source_youtube: string;
  source_vimeo: string;
  source_external_url: string;
  source_embedded: string;
  source_shortcode: string; // For shortcode support
  poster: string; // Video poster image
}

/**
 * Instructor user data for display and management
 */
export interface InstructorUser {
  id: number;
  display_name: string;
  user_email: string;
  user_login: string;
  avatar_url: string;
}

/**
 * Course instructors data structure
 * Includes author and co-instructors with full user objects
 */
export interface CourseInstructors {
  author_id: number;
  author: InstructorUser | null;
  instructor_ids: number[];
  instructors: InstructorUser[];
}

/**
 * Instructor search result for user selection
 */
export interface InstructorSearchResult {
  id: number;
  display_name: string;
  user_email: string;
  user_login: string;
  avatar_url: string;
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
  maximum_students: number | null;
  course_enrollment_period: "yes" | "no";
  enrollment_starts_at: string;
  enrollment_ends_at: string;
  pause_enrollment: "yes" | "no";

  // Course Media Section
  intro_video: CourseIntroVideo; // Renamed from featured_video
  attachments: number[];
  course_material_includes: string; // Match Tutor LMS field name

  // Pricing Model Section
  is_free: boolean;
  pricing_model: string;
  price: number;
  sale_price: number | null;
  subscription_enabled: boolean;
  selling_option: string; // Purchase option: "one_time", "subscription", "both", "membership", "all"
  woocommerce_product_id?: string; // WooCommerce product ID for product linking
  edd_product_id?: string; // EDD product ID for product linking

  // Instructors Section
  instructors: number[];
  additional_instructors: number[];
  course_instructors?: CourseInstructors; // Enhanced instructor data with user objects

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
  maximum_students: null,
  course_enrollment_period: "no",
  enrollment_starts_at: "",
  enrollment_ends_at: "",
  pause_enrollment: "no",

  // Course Media
  intro_video: {
    source: "",
    source_video_id: 0,
    source_youtube: "",
    source_vimeo: "",
    source_external_url: "",
    source_embedded: "",
    source_shortcode: "",
    poster: "",
  },
  attachments: [],
  course_material_includes: "", // Match Tutor LMS field name

  // Pricing Model
  is_free: true,
  pricing_model: "",
  price: 0,
  sale_price: null,
  subscription_enabled: false,
  selling_option: "one_time",
  woocommerce_product_id: "",
  edd_product_id: "",

  // Instructors
  instructors: [],
  additional_instructors: [],
  course_instructors: {
    author_id: 0,
    author: null,
    instructor_ids: [],
    instructors: [],
  },
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

import type { TutorResponse } from "./api";

export interface PrerequisiteCourse {
  id: number;
  title: string;
  status: string;
  // Enhanced fields from new search endpoint (optional for backward compatibility)
  permalink?: string;
  featured_image?: string;
  author?: string;
  date_created?: string;
  price?: string;
  duration?: string;
  lesson_count?: number;
  quiz_count?: number;
  resource_count?: number;
}

export type PrerequisiteCoursesResponse = TutorResponse<PrerequisiteCourse[]>;

/**
 * Course attachment metadata interface
 */
export interface CourseAttachment {
  id: number;
  title: string;
  filename: string;
  url: string;
  mime_type: string;
  filesize: number;
}

export type CourseAttachmentsResponse = TutorResponse<CourseAttachment[]>;

/**
 * WooCommerce Product Interfaces
 * Following Tutor LMS patterns for WooCommerce integration
 */

/**
 * WooCommerce product for selection dropdown
 */
export interface WcProduct {
  ID: string;
  post_title: string;
}

/**
 * WooCommerce product details for price synchronization
 */
export interface WcProductDetails {
  name: string;
  regular_price: string;
  sale_price: string;
}

/**
 * WooCommerce products API response
 */
export interface WcProductsResponse {
  products: WcProduct[];
  total: number;
  total_pages: number;
  current_page: number;
  per_page: number;
}

/**
 * WooCommerce product details API response
 */
export interface WcProductDetailsResponse {
  name: string;
  regular_price: string;
  sale_price: string;
}
