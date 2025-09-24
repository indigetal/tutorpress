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
  | "course_attachments"
  | "subscription"
  | "edd"
  | "certificate_builder";

/**
 * Payment engine types
 */
export type PaymentEngine = "pmpro" | "surecart" | "tutor_pro" | "wc" | "edd" | "none";

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
  subscription: boolean;
  edd: boolean;
  // Payment engine status
  tutor_pro: boolean;
  paid_memberships_pro: boolean;
  surecart: boolean;
  payment_engine: PaymentEngine;
  monetization_enabled: boolean;
  available_payment_engines: Record<string, string>;
  woocommerce: boolean;
  woocommerce_monetization: boolean;
  edd_monetization: boolean;
  h5p_plugin_active: boolean; // Added for H5P plugin status
  certificate_builder: boolean; // Added for Certificate Builder plugin status
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
        subscription: false,
        edd: false,
        // Payment engine status
        tutor_pro: false,
        paid_memberships_pro: false,
        surecart: false,
        payment_engine: "none" as PaymentEngine,
        monetization_enabled: false,
        available_payment_engines: {},
        woocommerce: false,
        woocommerce_monetization: false,
        edd_monetization: false,
        h5p_plugin_active: false, // Added for H5P plugin status
        certificate_builder: false, // Added for Certificate Builder plugin status
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
   * Check if H5P addon is available (Tutor Pro addon)
   */
  public static isH5pEnabled(): boolean {
    return this.isAddonEnabled("h5p");
  }

  /**
   * Check if H5P plugin is active (independent of Tutor Pro)
   */
  public static isH5pPluginActive(): boolean {
    // This will be populated by the server-side data
    // The server will check if H5P plugin is active directly
    return this.getAddonData().h5p_plugin_active || false;
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
   * Check if Subscription addon is available
   */
  public static isSubscriptionEnabled(): boolean {
    return this.isAddonEnabled("subscription");
  }

  /**
   * Check if EDD (Easy Digital Downloads) is available
   */
  public static isEddEnabled(): boolean {
    return this.isAddonEnabled("edd");
  }

  /**
   * Check if Certificate Builder plugin is enabled
   */
  public static isCertificateBuilderEnabled(): boolean {
    return !!this.getAddonData().certificate_builder;
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

  /**
   * Check if Tutor Pro is enabled (for non-payment features)
   */
  public static isTutorProEnabled(): boolean {
    const addonData = this.getAddonData();
    return addonData.tutor_pro || false;
  }

  /**
   * Check if Paid Memberships Pro is enabled
   */
  public static isPMPEnabled(): boolean {
    const addonData = this.getAddonData();
    return addonData.paid_memberships_pro || false;
  }

  /**
   * Check if SureCart is enabled
   */
  public static isSureCartEnabled(): boolean {
    const addonData = this.getAddonData();
    return addonData.surecart || false;
  }

  /**
   * Check if WooCommerce is enabled
   */
  public static isWooCommerceEnabled(): boolean {
    const addonData = this.getAddonData();
    return addonData.woocommerce || false;
  }

  /**
   * Check if WooCommerce is selected as the monetization engine
   */
  public static isWooCommerceMonetization(): boolean {
    const addonData = this.getAddonData();
    return addonData.woocommerce_monetization || false;
  }

  /**
   * Check if EDD is selected as the monetization engine
   */
  public static isEddMonetization(): boolean {
    const addonData = this.getAddonData();
    return addonData.edd_monetization || false;
  }

  /**
   * Get the current payment engine
   */
  public static getPaymentEngine(): PaymentEngine {
    const addonData = this.getAddonData();
    return addonData.payment_engine || "none";
  }

  /**
   * Get available payment engines with their display names
   */
  public static getAvailablePaymentEngines(): Record<string, string> {
    const addonData = this.getAddonData();
    return addonData.available_payment_engines || {};
  }

  /**
   * Check if monetization is enabled for the current payment engine
   */
  public static isMonetizationEnabled(): boolean {
    const addonData = this.getAddonData();
    return !!addonData.monetization_enabled;
  }
}

/**
 * Convenience functions for common checks
 */
export const isCoursePreviewEnabled = (): boolean => AddonChecker.isCoursePreviewEnabled();
export const isGoogleMeetEnabled = (): boolean => AddonChecker.isGoogleMeetEnabled();
export const isZoomEnabled = (): boolean => AddonChecker.isZoomEnabled();
export const isH5pEnabled = (): boolean => AddonChecker.isH5pEnabled();
export const isH5pPluginActive = (): boolean => AddonChecker.isH5pPluginActive();
export const isCertificateEnabled = (): boolean => AddonChecker.isCertificateEnabled();
export const isContentDripEnabled = (): boolean => AddonChecker.isContentDripEnabled();
export const isPrerequisitesEnabled = (): boolean => AddonChecker.isPrerequisitesEnabled();
export const isMultiInstructorsEnabled = (): boolean => AddonChecker.isMultiInstructorsEnabled();
export const isEnrollmentsEnabled = (): boolean => AddonChecker.isEnrollmentsEnabled();
export const isCourseAttachmentsEnabled = (): boolean => AddonChecker.isCourseAttachmentsEnabled();
export const isSubscriptionEnabled = (): boolean => AddonChecker.isSubscriptionEnabled();
export const isEddEnabled = (): boolean => AddonChecker.isEddEnabled();
export const isAnyLiveLessonEnabled = (): boolean => AddonChecker.isAnyLiveLessonEnabled();
export const getAvailableLiveLessonTypes = (): AddonKey[] => AddonChecker.getAvailableLiveLessonTypes();
export const isCertificateBuilderEnabled = (): boolean => AddonChecker.isCertificateBuilderEnabled();

// Payment engine convenience functions
export const isTutorProEnabled = (): boolean => AddonChecker.isTutorProEnabled();
export const isPMPEnabled = (): boolean => AddonChecker.isPMPEnabled();
export const isSureCartEnabled = (): boolean => AddonChecker.isSureCartEnabled();
export const isWooCommerceEnabled = (): boolean => AddonChecker.isWooCommerceEnabled();
export const isWooCommerceMonetization = (): boolean => AddonChecker.isWooCommerceMonetization();
export const isEddMonetization = (): boolean => AddonChecker.isEddMonetization();
export const getPaymentEngine = (): PaymentEngine => AddonChecker.getPaymentEngine();
export const getAvailablePaymentEngines = (): Record<string, string> => AddonChecker.getAvailablePaymentEngines();
export const isMonetizationEnabled = (): boolean => AddonChecker.isMonetizationEnabled();

export const isPmproMonetization = (): boolean => AddonChecker.getPaymentEngine() === "pmpro";

export const isPmproAvailable = (): boolean => !!(window as any).tutorpressAddons?.paid_memberships_pro;
