/**
 * Additional Content TypeScript Interfaces
 *
 * @description Type definitions for Additional Course Content fields and Content Drip settings.
 *              Provides comprehensive interfaces for state management, API responses, and component props.
 *              Follows TutorPress typing patterns for consistency.
 *
 * @package TutorPress
 * @subpackage Types
 * @since 1.0.0
 */

// ============================================================================
// Core Data Interfaces
// ============================================================================

/**
 * Additional content data structure
 */
export interface AdditionalContentData {
  what_will_learn: string;
  target_audience: string;
  requirements: string;
}

/**
 * Content drip settings structure
 */
export interface ContentDripSettings {
  enabled: boolean;
  type: "unlock_by_date" | "specific_days" | "unlock_sequentially" | "after_finishing_prerequisites";
}

/**
 * Content drip type options for UI
 */
export interface ContentDripTypeOption {
  value: ContentDripSettings["type"];
  label: string;
  description: string;
}

// ============================================================================
// State Management Interfaces
// ============================================================================

/**
 * Main store state for additional content
 */
export interface AdditionalContentState {
  courseId: number | null;
  data: AdditionalContentData;
  contentDrip: ContentDripSettings;
  isLoading: boolean;
  isSaving: boolean;
  isDirty: boolean;
  error: string | null;
  lastSaved: number | null;
}

/**
 * Filters for additional content (future extensibility)
 */
export interface AdditionalContentFilters {
  showContentDrip: boolean;
}

// ============================================================================
// API Response Interfaces
// ============================================================================

/**
 * GET API response structure
 */
export interface AdditionalContentResponse {
  success: boolean;
  data: {
    what_will_learn: string;
    target_audience: string;
    requirements: string;
    content_drip: ContentDripSettings;
    course_id: number;
  };
}

/**
 * POST API response structure
 */
export interface AdditionalContentSaveResponse {
  success: boolean;
  message: string;
  data: {
    course_id: number;
    additional_data_saved: boolean | number;
    content_drip_saved: boolean;
    content_drip_addon_enabled: boolean;
  };
}

/**
 * API error response structure
 */
export interface AdditionalContentError {
  code: string;
  message: string;
  data?: {
    status: number;
    [key: string]: any;
  };
}

// ============================================================================
// Component Props Interfaces
// ============================================================================

/**
 * Main Additional Content component props
 */
export interface AdditionalContentProps {
  courseId?: number;
  className?: string;
}

/**
 * Content Drip Settings component props
 */
export interface ContentDripSettingsProps {
  enabled: boolean;
  type: ContentDripSettings["type"];
  onEnabledChange: (enabled: boolean) => void;
  onTypeChange: (type: ContentDripSettings["type"]) => void;
  isDisabled?: boolean;
  showDescription?: boolean;
}

/**
 * Additional content field component props
 */
export interface AdditionalContentFieldProps {
  label: string;
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  description?: string;
  isDisabled?: boolean;
  rows?: number;
}

// ============================================================================
// Store Action Interfaces
// ============================================================================

/**
 * Store action types
 */
export type AdditionalContentActionType =
  | "SET_COURSE_ID"
  | "SET_LOADING"
  | "SET_SAVING"
  | "SET_DATA"
  | "SET_CONTENT_DRIP"
  | "SET_ERROR"
  | "SET_DIRTY"
  | "RESET_STATE"
  | "UPDATE_FIELD"
  | "SET_LAST_SAVED";

/**
 * Base action interface
 */
export interface AdditionalContentAction {
  type: AdditionalContentActionType;
  payload?: any;
}

/**
 * Specific action interfaces
 */
export interface SetCourseIdAction extends AdditionalContentAction {
  type: "SET_COURSE_ID";
  payload: number;
}

export interface SetDataAction extends AdditionalContentAction {
  type: "SET_DATA";
  payload: AdditionalContentData;
}

export interface SetContentDripAction extends AdditionalContentAction {
  type: "SET_CONTENT_DRIP";
  payload: ContentDripSettings;
}

export interface UpdateFieldAction extends AdditionalContentAction {
  type: "UPDATE_FIELD";
  payload: {
    field: keyof AdditionalContentData;
    value: string;
  };
}

export interface SetErrorAction extends AdditionalContentAction {
  type: "SET_ERROR";
  payload: string | null;
}

// ============================================================================
// Store Selector Return Types
// ============================================================================

/**
 * Selector return types for type safety
 */
export interface AdditionalContentSelectors {
  getCourseId: () => number | null;
  getAdditionalContentData: () => AdditionalContentData;
  getContentDripSettings: () => ContentDripSettings;
  getField: (field: keyof AdditionalContentData) => string;
  isLoading: () => boolean;
  isSaving: () => boolean;
  isDirty: () => boolean;
  getError: () => string | null;
  hasError: () => boolean;
  getLastSaved: () => number | null;
  isContentDripEnabled: () => boolean;
  getContentDripType: () => ContentDripSettings["type"];
}

// ============================================================================
// Utility Types
// ============================================================================

/**
 * Form validation result
 */
export interface ValidationResult {
  isValid: boolean;
  errors: Record<string, string>;
}

/**
 * Auto-save configuration
 */
export interface AutoSaveConfig {
  enabled: boolean;
  delay: number;
  fields: (keyof AdditionalContentData)[];
}

// All interfaces and types are already exported above
