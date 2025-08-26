/**
 * Bundle TypeScript Interfaces
 *
 * Type definitions for bundle settings and operations.
 * Following TutorPress patterns and Tutor LMS compatibility.
 *
 * @package TutorPress
 * @since 0.1.0
 */

// ============================================================================
// Base Types
// ============================================================================

/**
 * Base bundle interface that represents core bundle data
 */
export interface BaseBundle {
  id: number;
  title: string;
  content: string;
  status: string;
}

// ============================================================================
// Error Types
// ============================================================================

/**
 * Bundle error codes following TutorPress patterns
 */
export enum BundleErrorCode {
  BUNDLE_NOT_FOUND = "bundle_not_found",
  PERMISSION_DENIED = "permission_denied",
  INVALID_DATA = "invalid_data",
  NETWORK_ERROR = "network_error",
  VALIDATION_ERROR = "validation_error",
  UNKNOWN_ERROR = "unknown_error",
}

/**
 * Bundle error interface following TutorPress patterns
 */
export interface BundleError {
  code: BundleErrorCode;
  message: string;
  context?: {
    action?: string;
    bundleId?: number;
    details?: string;
  };
}

// ============================================================================
// Operation Types
// ============================================================================

/**
 * Bundle active operation types following TutorPress patterns
 */
export type BundleActiveOperation =
  | { type: "none" }
  | { type: "edit"; bundleId: number }
  | { type: "delete"; bundleId: number }
  | { type: "create" };

// ============================================================================
// Constants
// ============================================================================

/**
 * Bundle ribbon types following Tutor LMS patterns
 */
export type BundleRibbonType = "in_percentage" | "in_amount" | "none";

/**
 * Bundle ribbon type options
 */
export const BUNDLE_RIBBON_TYPES: BundleRibbonType[] = ["in_percentage", "in_amount", "none"];

// ============================================================================
// Basic Types
// ============================================================================

// Bundle Course Types
export interface BundleCourse {
  id: number;
  title: string;
  slug: string;
  status: string;
}

// Available Course Types (for course selection)
export interface AvailableCourse {
  id: number;
  title: string;
  permalink: string;
  featured_image?: string;
  author: string;
  date_created: string;
  price?: string; // Can contain HTML for sale price formatting
  duration?: string;
  lesson_count?: number;
  quiz_count?: number;
  resource_count?: number;
}

export interface BundleCourseSearch {
  search?: string;
  per_page?: number;
  page?: number;
}

// Bundle Pricing Types
export interface BundlePricing {
  regular_price: number;
  sale_price: number;
  ribbon_type: BundleRibbonType;
}

// Bundle Benefits Types
export interface BundleBenefits {
  content: string;
}

// Bundle Instructor Types
export interface BundleInstructor {
  id: number;
  display_name: string;
  user_email: string;
  user_login: string;
  avatar_url: string;
  role: "author" | "instructor";
  designation?: string;
}

// ============================================================================
// Main Bundle Interface
// ============================================================================

/**
 * Main bundle interface extending base bundle with additional properties
 */
export interface Bundle extends BaseBundle {
  slug: string;
  created: string;
  modified: string;
  courses?: BundleCourse[];
  pricing?: BundlePricing;
  benefits?: BundleBenefits;
  instructors?: BundleInstructor[];
}

// ============================================================================
// API Response Types
// ============================================================================

/**
 * Response type for bundle list operations
 */
export interface BundleListResponse {
  bundles: Bundle[];
  total: number;
  total_pages: number;
}

/**
 * Response type for single bundle operations
 */
export interface BundleResponse {
  bundle: Bundle;
}

// ============================================================================
// State Types
// ============================================================================

/**
 * Bundle operation state following TutorPress patterns
 */
export type BundleOperationState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "saving" }
  | { status: "success"; data: Bundle }
  | { status: "error"; error: BundleError };

/**
 * Bundle creation state following TutorPress patterns
 */
export type BundleCreationState =
  | { status: "idle" }
  | { status: "creating" }
  | { status: "success"; data: Bundle }
  | { status: "error"; error: BundleError };

/**
 * Bundle edit state following TutorPress patterns
 */
export interface BundleEditState {
  isEditing: boolean;
  bundleId: number | null;
}

/**
 * Main bundle settings state following TutorPress patterns
 */
export interface CourseBundlesState {
  bundles: Bundle[];
  currentBundle: Bundle | null;
  operationState: BundleOperationState;
  creationState: BundleCreationState;
  editState: BundleEditState;
  activeOperation: BundleActiveOperation;
  fetchState: {
    isLoading: boolean;
    error: BundleError | null;
    lastFetchedBundleId: number | null;
  };
  courseSelection: {
    availableCourses: AvailableCourse[];
    isLoading: boolean;
    error: BundleError | null;
  };
  // Bundle Benefits state (following Additional Content pattern)
  bundleBenefits: {
    data: { benefits: string };
    isLoading: boolean;
    isSaving: boolean;
    isDirty: boolean;
    error: string | null;
    lastSaved: number | null;
  };
  // Bundle Pricing state - REMOVED (now uses entity-based approach)
  // Bundle Instructors state (following Bundle Benefits pattern)
  bundleInstructors: {
    data: {
      instructors: BundleInstructor[];
      total_instructors: number;
      total_courses: number;
    };
    isLoading: boolean;
    error: string | null;
    lastFetched: number | null;
  };
}
