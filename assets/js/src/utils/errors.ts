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

/**
 * Type guard for WordPress REST API response
 */
export function isWpRestResponse(response: unknown): response is { success: boolean; message: string; data: unknown } {
  return typeof response === "object" && response !== null && "success" in response && "data" in response;
}

/**
 * Create a standardized curriculum error from an operation
 */
export function createCurriculumError(
  error: unknown,
  code: CurriculumErrorCode,
  action: string,
  fallbackMessage: string,
  context?: Record<string, unknown>
): CurriculumError {
  // Filter out undefined values from context
  const filteredContext = context
    ? Object.fromEntries(Object.entries(context).filter(([_, value]) => value !== undefined))
    : {};

  return {
    code,
    message: error instanceof Error ? error.message : fallbackMessage,
    context: {
      action,
      details: error instanceof Error ? error.stack : JSON.stringify(error),
      ...filteredContext,
    },
  };
}
