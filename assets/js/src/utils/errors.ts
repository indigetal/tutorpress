import { __ } from "@wordpress/i18n";
import { CurriculumError, CurriculumErrorCode } from "../types/curriculum";

/**
 * Get user-friendly error message based on error code
 */
export function getErrorMessage(error: CurriculumError): string {
  switch (error.code) {
    case CurriculumErrorCode.CREATION_FAILED:
      return __("Unable to create topic. Please try again.", "tutorpress");
    case CurriculumErrorCode.VALIDATION_ERROR:
      return __("Please fill in all required fields.", "tutorpress");
    case CurriculumErrorCode.NETWORK_ERROR:
      return __(
        "Unable to save changes - you appear to be offline. Please check your connection and try again.",
        "tutorpress"
      );
    case CurriculumErrorCode.FETCH_FAILED:
      return __("Unable to load topics. Please refresh the page to try again.", "tutorpress");
    case CurriculumErrorCode.REORDER_FAILED:
      return __("Unable to save topic order. Your changes will be restored.", "tutorpress");
    case CurriculumErrorCode.INVALID_RESPONSE:
      return __("Received invalid response from server. Please try again.", "tutorpress");
    case CurriculumErrorCode.SERVER_ERROR:
      return __("The server encountered an error. Please try again.", "tutorpress");
    default:
      return __("An unexpected error occurred. Please try again.", "tutorpress");
  }
}

/**
 * Check if an error is a network error
 */
export function isNetworkError(error: Error): boolean {
  return error.message.includes("offline") || error.message.includes("network") || error.message.includes("fetch");
}
