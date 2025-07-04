/**
 * TutorPress Addon Checker Utility (Client-side)
 *
 * @description Client-side utility for checking Tutor LMS Pro addon availability.
 *              Gets addon status from server-side data exposed to the frontend.
 *              Provides consistent TypeScript interfaces and helper methods.
 *
 * @package TutorPress
 * @subpackage Utils
 * @since 1.0.0
 */

/**
 * Supported addon keys
 */
export type AddonKey =
  | "course_preview"
  | "google_meet"
  | "zoom"
  | "h5p"
  | "certificate"
  | "content_drip"
  | "prerequisites"
  | "multi_instructors"
  | "enrollments"
  | "course_attachments";

/**
 * Addon status interface
 */
export interface AddonStatus {
  course_preview: boolean;
  google_meet: boolean;
  zoom: boolean;
  h5p: boolean;
  certificate: boolean;
  content_drip: boolean;
  prerequisites: boolean;
  multi_instructors: boolean;
  enrollments: boolean;
  course_attachments: boolean;
}

/**
 * Global window interface extension for addon data
 */
declare global {
  interface Window {
    tutorpressAddons?: AddonStatus;
  }
}

/**
 * Addon Checker utility class
 */
export class AddonChecker {
  private static cache: Partial<AddonStatus> = {};

  /**
   * Get addon status from global window object
   * Falls back to false if data is not available
   */
  private static getAddonData(): AddonStatus {
    return (
      window.tutorpressAddons || {
        course_preview: false,
        google_meet: false,
        zoom: false,
        h5p: false,
        certificate: false,
        content_drip: false,
        prerequisites: false,
        multi_instructors: false,
        enrollments: false,
        course_attachments: false,
      }
    );
  }

  /**
   * Check if a specific addon is available and enabled
   *
   * @param addonKey The addon key to check
   * @returns True if addon is available and enabled
   */
  public static isAddonEnabled(addonKey: AddonKey): boolean {
    // Return cached result if available
    if (addonKey in this.cache) {
      return this.cache[addonKey] as boolean;
    }

    const addonData = this.getAddonData();
    const result = addonData[addonKey] || false;

    // Cache the result
    this.cache[addonKey] = result;

    return result;
  }

  /**
   * Check if Course Preview addon is available
   */
  public static isCoursePreviewEnabled(): boolean {
    return this.isAddonEnabled("course_preview");
  }

  /**
   * Check if Google Meet addon is available
   */
  public static isGoogleMeetEnabled(): boolean {
    return this.isAddonEnabled("google_meet");
  }

  /**
   * Check if Zoom addon is available
   */
  public static isZoomEnabled(): boolean {
    return this.isAddonEnabled("zoom");
  }

  /**
   * Check if H5P addon is available
   */
  public static isH5pEnabled(): boolean {
    return this.isAddonEnabled("h5p");
  }

  /**
   * Check if Certificate addon is available
   */
  public static isCertificateEnabled(): boolean {
    return this.isAddonEnabled("certificate");
  }

  /**
   * Check if Content Drip addon is available
   */
  public static isContentDripEnabled(): boolean {
    return this.isAddonEnabled("content_drip");
  }

  /**
   * Check if Prerequisites addon is available
   */
  public static isPrerequisitesEnabled(): boolean {
    return this.isAddonEnabled("prerequisites");
  }

  /**
   * Check if Multi Instructors addon is available
   */
  public static isMultiInstructorsEnabled(): boolean {
    return this.isAddonEnabled("multi_instructors");
  }

  /**
   * Check if Enrollments addon is available
   */
  public static isEnrollmentsEnabled(): boolean {
    return this.isAddonEnabled("enrollments");
  }

  /**
   * Check if Course Attachments addon is available
   */
  public static isCourseAttachmentsEnabled(): boolean {
    return this.isAddonEnabled("course_attachments");
  }

  /**
   * Test method to verify build process
   */
  public static testEnrollmentsMethod(): boolean {
    return this.isAddonEnabled("enrollments");
  }

  /**
   * Get availability status for all supported addons
   */
  public static getAllAddonStatus(): AddonStatus {
    return this.getAddonData();
  }

  /**
   * Check if any live lesson addon is available (Google Meet or Zoom)
   */
  public static isAnyLiveLessonEnabled(): boolean {
    return this.isGoogleMeetEnabled() || this.isZoomEnabled();
  }

  /**
   * Get available live lesson addon types
   */
  public static getAvailableLiveLessonTypes(): AddonKey[] {
    const types: AddonKey[] = [];

    if (this.isGoogleMeetEnabled()) {
      types.push("google_meet");
    }

    if (this.isZoomEnabled()) {
      types.push("zoom");
    }

    return types;
  }

  /**
   * Clear the addon availability cache
   * Useful for testing or when addon status might change
   */
  public static clearCache(): void {
    this.cache = {};
  }

  /**
   * Get supported addon keys
   */
  public static getSupportedAddons(): AddonKey[] {
    return [
      "course_preview",
      "google_meet",
      "zoom",
      "h5p",
      "certificate",
      "content_drip",
      "prerequisites",
      "multi_instructors",
      "enrollments",
    ];
  }
}

/**
 * Convenience functions for common checks
 */
export const isCoursePreviewEnabled = (): boolean => AddonChecker.isCoursePreviewEnabled();
export const isGoogleMeetEnabled = (): boolean => AddonChecker.isGoogleMeetEnabled();
export const isZoomEnabled = (): boolean => AddonChecker.isZoomEnabled();
export const isH5pEnabled = (): boolean => AddonChecker.isH5pEnabled();
export const isCertificateEnabled = (): boolean => AddonChecker.isCertificateEnabled();
export const isContentDripEnabled = (): boolean => AddonChecker.isContentDripEnabled();
export const isPrerequisitesEnabled = (): boolean => AddonChecker.isPrerequisitesEnabled();
export const isMultiInstructorsEnabled = (): boolean => AddonChecker.isMultiInstructorsEnabled();
export const isEnrollmentsEnabled = (): boolean => AddonChecker.isEnrollmentsEnabled();
export const isCourseAttachmentsEnabled = (): boolean => AddonChecker.isCourseAttachmentsEnabled();
export const isAnyLiveLessonEnabled = (): boolean => AddonChecker.isAnyLiveLessonEnabled();
export const getAvailableLiveLessonTypes = (): AddonKey[] => AddonChecker.getAvailableLiveLessonTypes();
