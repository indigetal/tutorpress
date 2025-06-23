/**
 * Content Drip TypeScript Interfaces
 *
 * @description Type definitions for Content Drip settings at the individual content level.
 *              Provides comprehensive interfaces for lessons and assignments content drip functionality.
 *              Follows TutorPress typing patterns and ensures Tutor LMS compatibility.
 *
 * @package TutorPress
 * @subpackage Types
 * @since 1.0.0
 */

// ============================================================================
// Core Content Drip Interfaces
// ============================================================================

/**
 * Content drip settings for individual content items (lessons, assignments, quizzes)
 * Maps directly to Tutor LMS _content_drip_settings meta field structure
 */
export interface ContentDripItemSettings {
  /** Unlock date for 'unlock_by_date' content drip type (ISO date string) */
  unlock_date?: string;
  /** Days after enrollment for 'specific_days' content drip type */
  after_xdays_of_enroll?: number;
  /** Array of content item IDs for 'after_finishing_prerequisites' content drip type */
  prerequisites?: number[];
}

/**
 * Content drip information context for a content item
 * Includes course-level settings and computed display flags
 */
export interface ContentDripInfo {
  /** Whether content drip is enabled at course level */
  enabled: boolean;
  /** Content drip type from course settings */
  type: "unlock_by_date" | "specific_days" | "unlock_sequentially" | "after_finishing_prerequisites";
  /** Course ID this content belongs to */
  course_id: number;
  /** Whether to show days input field */
  show_days_field: boolean;
  /** Whether to show date picker field */
  show_date_field: boolean;
  /** Whether to show prerequisites field */
  show_prerequisites_field: boolean;
  /** Whether content drip is sequential (no UI needed) */
  is_sequential: boolean;
}

/**
 * Available prerequisite content item
 */
export interface PrerequisiteItem {
  /** Content item ID */
  id: number;
  /** Content item title */
  title: string;
  /** Content type (lesson, quiz, assignment, etc.) */
  type: string;
  /** Topic ID this content belongs to */
  topic_id: number;
  /** Topic title for grouping */
  topic_title: string;
  /** Human-readable content type label */
  type_label: string;
}

/**
 * Prerequisites grouped by topic for better UX
 */
export interface PrerequisitesByTopic {
  /** Topic ID */
  topic_id: number;
  /** Topic title */
  topic_title: string;
  /** Content items in this topic */
  items: PrerequisiteItem[];
}

// ============================================================================
// Component Props Interfaces
// ============================================================================

/**
 * Generic Content Drip Panel component props
 * Uses TypeScript generics to support both lessons and assignments
 */
export interface ContentDripPanelProps<T extends "lesson" | "tutor_assignments"> {
  /** Post type being edited */
  postType: T;
  /** Course ID this content belongs to */
  courseId: number;
  /** Post ID being edited */
  postId: number;
  /** Current content drip settings */
  settings: ContentDripItemSettings;
  /** Callback when settings change */
  onSettingsChange: (settings: ContentDripItemSettings) => void;
  /** Whether the panel is disabled */
  isDisabled?: boolean;
  /** Custom CSS class name */
  className?: string;
}

/**
 * Content Drip Date Picker component props
 */
export interface ContentDripDatePickerProps {
  /** Current unlock date value */
  value?: string;
  /** Callback when date changes */
  onChange: (date: string) => void;
  /** Whether the field is disabled */
  isDisabled?: boolean;
  /** Additional CSS class */
  className?: string;
}

/**
 * Content Drip Days Input component props
 */
export interface ContentDripDaysInputProps {
  /** Current days value */
  value?: number;
  /** Callback when days change */
  onChange: (days: number) => void;
  /** Whether the field is disabled */
  isDisabled?: boolean;
  /** Minimum allowed value */
  min?: number;
  /** Additional CSS class */
  className?: string;
}

/**
 * Enhanced Prerequisites Selector component props
 */
export interface PrerequisitesSelectorProps {
  /** Currently selected prerequisite IDs */
  selectedIds: number[];
  /** Available prerequisite items grouped by topic */
  availablePrerequisites: PrerequisitesByTopic[];
  /** Callback when selection changes */
  onChange: (selectedIds: number[]) => void;
  /** Whether the field is disabled */
  isDisabled?: boolean;
  /** Whether to show search functionality */
  showSearch?: boolean;
  /** Placeholder text for search */
  searchPlaceholder?: string;
  /** Additional CSS class */
  className?: string;
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/**
 * GET content drip settings API response
 */
export interface ContentDripSettingsResponse {
  success: boolean;
  data: {
    /** Content drip settings for this item */
    settings: ContentDripItemSettings;
    /** Content drip context information */
    drip_info: ContentDripInfo;
    /** Post ID */
    post_id: number;
    /** Course ID */
    course_id: number;
  };
}

/**
 * POST content drip settings API response
 */
export interface ContentDripSaveResponse {
  success: boolean;
  message: string;
  data: {
    /** Post ID that was updated */
    post_id: number;
    /** Course ID */
    course_id: number;
    /** Whether settings were saved successfully */
    settings_saved: boolean;
    /** Updated settings */
    settings: ContentDripItemSettings;
  };
}

/**
 * GET prerequisites API response
 */
export interface PrerequisitesResponse {
  success: boolean;
  data: {
    /** Course ID */
    course_id: number;
    /** Available prerequisites grouped by topic */
    prerequisites: PrerequisitesByTopic[];
    /** Total count of available items */
    total_count: number;
  };
}

/**
 * Content drip API error response
 */
export interface ContentDripError {
  code: string;
  message: string;
  data?: {
    status: number;
    post_id?: number;
    course_id?: number;
    [key: string]: any;
  };
}

// ============================================================================
// Store State Interfaces
// ============================================================================

/**
 * Content drip store state for managing content-level settings
 */
export interface ContentDripStoreState {
  /** Settings by post ID */
  settingsByPostId: Record<number, ContentDripItemSettings>;
  /** Content drip info by post ID */
  dripInfoByPostId: Record<number, ContentDripInfo>;
  /** Available prerequisites by course ID */
  prerequisitesByCourseId: Record<number, PrerequisitesByTopic[]>;
  /** Loading states by post ID */
  loadingStates: Record<number, boolean>;
  /** Saving states by post ID */
  savingStates: Record<number, boolean>;
  /** Error states by post ID */
  errorStates: Record<number, string | null>;
  /** Last saved timestamps by post ID */
  lastSavedByPostId: Record<number, number>;
}

/**
 * Content drip store action types
 */
export type ContentDripActionType =
  | "FETCH_CONTENT_DRIP_SETTINGS"
  | "FETCH_CONTENT_DRIP_SETTINGS_SUCCESS"
  | "FETCH_CONTENT_DRIP_SETTINGS_ERROR"
  | "SAVE_CONTENT_DRIP_SETTINGS"
  | "SAVE_CONTENT_DRIP_SETTINGS_SUCCESS"
  | "SAVE_CONTENT_DRIP_SETTINGS_ERROR"
  | "FETCH_PREREQUISITES"
  | "FETCH_PREREQUISITES_SUCCESS"
  | "FETCH_PREREQUISITES_ERROR"
  | "UPDATE_CONTENT_DRIP_SETTING"
  | "SET_LOADING_STATE"
  | "SET_ERROR_STATE"
  | "CLEAR_ERROR_STATE"
  | "RESET_POST_STATE";

/**
 * Base content drip store action interface
 */
export interface ContentDripAction {
  type: ContentDripActionType;
  payload?: any;
}

// ============================================================================
// Utility Type Guards
// ============================================================================

/**
 * Type guard to check if a value is a valid ContentDripItemSettings
 */
export function isContentDripItemSettings(value: any): value is ContentDripItemSettings {
  if (!value || typeof value !== "object") return false;

  const settings = value as ContentDripItemSettings;

  // Check optional fields have correct types
  if (settings.unlock_date !== undefined && typeof settings.unlock_date !== "string") return false;
  if (settings.after_xdays_of_enroll !== undefined && typeof settings.after_xdays_of_enroll !== "number") return false;
  if (settings.prerequisites !== undefined && !Array.isArray(settings.prerequisites)) return false;
  if (settings.prerequisites && !settings.prerequisites.every((id) => typeof id === "number")) return false;

  return true;
}

/**
 * Type guard to check if a value is a valid ContentDripInfo
 */
export function isContentDripInfo(value: any): value is ContentDripInfo {
  if (!value || typeof value !== "object") return false;

  const info = value as ContentDripInfo;
  const validTypes = ["unlock_by_date", "specific_days", "unlock_sequentially", "after_finishing_prerequisites"];

  return (
    typeof info.enabled === "boolean" &&
    typeof info.type === "string" &&
    validTypes.includes(info.type) &&
    typeof info.course_id === "number" &&
    typeof info.show_days_field === "boolean" &&
    typeof info.show_date_field === "boolean" &&
    typeof info.show_prerequisites_field === "boolean" &&
    typeof info.is_sequential === "boolean"
  );
}

// ============================================================================
// Default Values & Helpers
// ============================================================================

/**
 * Get default content drip item settings
 */
export function getDefaultContentDripItemSettings(): ContentDripItemSettings {
  return {
    unlock_date: undefined,
    after_xdays_of_enroll: 7,
    prerequisites: [],
  };
}

/**
 * Get empty content drip info (for when content drip is disabled)
 */
export function getEmptyContentDripInfo(courseId: number): ContentDripInfo {
  return {
    enabled: false,
    type: "unlock_by_date",
    course_id: courseId,
    show_days_field: false,
    show_date_field: false,
    show_prerequisites_field: false,
    is_sequential: false,
  };
}

/**
 * Check if content drip settings are empty/default
 */
export function isContentDripSettingsEmpty(settings: ContentDripItemSettings): boolean {
  return (
    !settings.unlock_date &&
    (!settings.after_xdays_of_enroll || settings.after_xdays_of_enroll === 7) &&
    (!settings.prerequisites || settings.prerequisites.length === 0)
  );
}

/**
 * Validate content drip settings based on drip type
 */
export function validateContentDripSettings(
  settings: ContentDripItemSettings,
  dripType: ContentDripInfo["type"]
): { isValid: boolean; errors: string[] } {
  const errors: string[] = [];

  switch (dripType) {
    case "unlock_by_date":
      // Allow empty unlock_date (content available immediately)
      if (settings.unlock_date && settings.unlock_date.trim()) {
        const date = new Date(settings.unlock_date);
        if (isNaN(date.getTime())) {
          errors.push("Invalid unlock date format");
        }
      }
      break;

    case "specific_days":
      if (settings.after_xdays_of_enroll === undefined || settings.after_xdays_of_enroll < 0) {
        errors.push("Days after enrollment must be a non-negative number");
      }
      break;

    case "after_finishing_prerequisites":
      if (!settings.prerequisites || settings.prerequisites.length === 0) {
        errors.push("At least one prerequisite must be selected");
      }
      break;

    case "unlock_sequentially":
      // No validation needed for sequential unlock
      break;
  }

  return {
    isValid: errors.length === 0,
    errors,
  };
}
