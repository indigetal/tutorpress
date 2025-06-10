/**
 * Interactive Quiz Type Definitions for TutorPress
 *
 * @description Type definitions for H5P Interactive Quiz Modal settings.
 *              Defines a subset of quiz settings specifically needed for Interactive Quiz Modal,
 *              which has fewer settings than the regular Quiz Modal but uses the same SettingsTab component.
 *
 * @package TutorPress
 * @subpackage Types/InteractiveQuiz
 * @since 1.0.0
 */

import type { QuestionOrder, QuizError, QuizErrorCode, DataStatus } from "./quiz";

// ============================================================================
// H5P Content Types
// ============================================================================

/**
 * H5P Content interface based on Tutor LMS H5P integration
 *
 * Represents H5P content that can be selected for Interactive Quiz
 */
export interface H5PContent {
  /** Unique H5P content ID */
  id: number;

  /** Display title of the H5P content */
  title: string;

  /** H5P content type (e.g., 'tutor_h5p_quiz') */
  content_type: string;

  /** User ID who created the content */
  user_id: number;

  /** Username of the content creator */
  user_name: string;

  /** Last updated timestamp */
  updated_at: string;

  /** Optional description of the H5P content */
  description?: string;

  /** H5P library used (e.g., 'H5P.InteractiveVideo') */
  library?: string;
}

/**
 * H5P Content search/filter parameters
 */
export interface H5PContentSearchParams {
  /** Search term for content title */
  search?: string;

  /** Filter by content type */
  contentType?: string;

  /** Pagination: items per page */
  per_page?: number;

  /** Pagination: page number */
  page?: number;

  /** Sort order */
  order?: "asc" | "desc";

  /** Sort by field */
  orderby?: "title" | "date" | "author";
}

/**
 * H5P Content API response
 */
export interface H5PContentResponse {
  /** Array of H5P content items */
  items: H5PContent[];

  /** Total number of items available */
  total: number;

  /** Current page number */
  page: number;

  /** Items per page */
  per_page: number;

  /** Total number of pages */
  total_pages: number;
}

// ============================================================================
// Interactive Quiz Settings
// ============================================================================

/**
 * Interactive Quiz Settings Interface
 *
 * Subset of quiz settings needed for H5P Interactive Quiz Modal.
 * Based on user specification: only 4 settings are needed.
 */
export interface InteractiveQuizSettings {
  /** Number of attempts allowed (0 = unlimited) */
  attempts_allowed: number;

  /** Minimum percentage required to pass (0-100) */
  passing_grade: number;

  /** Whether quiz starts automatically when page loads */
  quiz_auto_start: boolean;

  /** Order in which questions are presented */
  questions_order: QuestionOrder;
}

/**
 * Interactive Quiz form state interface
 *
 * Extends the shared modal form patterns for Interactive Quiz specific data
 */
export interface InteractiveQuizFormState {
  /** Quiz title */
  title: string;

  /** Quiz description */
  description: string;

  /** Interactive Quiz settings (4 core settings) */
  settings: InteractiveQuizSettings;

  /** Selected H5P content */
  h5pContent: H5PContent | null;

  /** Form validation errors */
  errors: InteractiveQuizFormErrors;

  /** Whether form has unsaved changes */
  isDirty: boolean;

  /** Whether form passes validation */
  isValid: boolean;
}

/**
 * Interactive Quiz form validation errors
 */
export interface InteractiveQuizFormErrors {
  /** Title validation error */
  title?: string;

  /** Description validation error */
  description?: string;

  /** H5P content selection error */
  h5pContent?: string;

  /** Passing grade validation error */
  passing_grade?: string;

  /** Attempts allowed validation error */
  attempts_allowed?: string;
}

// ============================================================================
// Interactive Quiz Form and API Interfaces
// ============================================================================

/**
 * Interactive Quiz form data structure for saving
 *
 * Similar to QuizForm but adapted for Interactive Quiz with H5P content
 */
export interface InteractiveQuizForm {
  /** Quiz ID for editing existing quiz */
  ID?: number;

  /** Quiz title */
  post_title: string;

  /** Quiz description */
  post_content: string;

  /** Interactive Quiz settings */
  quiz_option: InteractiveQuizSettings;

  /** Associated H5P content ID */
  h5p_content_id: number;

  /** Menu order for curriculum positioning */
  menu_order?: number;

  /** Content type identifier */
  content_type: "interactive_quiz";
}

/**
 * Interactive Quiz details response from API
 */
export interface InteractiveQuizDetails {
  /** Quiz ID */
  ID: number;

  /** Quiz title */
  post_title: string;

  /** Quiz description */
  post_content: string;

  /** Post status */
  post_status: string;

  /** Author ID */
  post_author: string;

  /** Parent topic ID */
  post_parent: number;

  /** Menu order */
  menu_order: number;

  /** Interactive Quiz settings */
  quiz_option: InteractiveQuizSettings;

  /** Associated H5P content */
  h5p_content: H5PContent;
}

// ============================================================================
// Interactive Quiz Store State Interfaces
// ============================================================================

/**
 * Interactive Quiz operation states for store management
 */
export type InteractiveQuizOperationState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "saving" }
  | { status: "deleting" }
  | { status: "success"; data: InteractiveQuizDetails }
  | { status: "error"; error: QuizError };

/**
 * H5P content loading state
 */
export type H5PContentLoadingState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "success"; data: H5PContentResponse }
  | { status: "error"; error: QuizError };

/**
 * Interactive Quiz creation state
 */
export type InteractiveQuizCreationState =
  | { status: "idle" }
  | { status: "creating" }
  | { status: "success"; data: InteractiveQuizDetails }
  | { status: "error"; error: QuizError };

// ============================================================================
// Props Interfaces for Components
// ============================================================================

/**
 * Props interface for SettingsTab when used with Interactive Quiz Modal
 *
 * This interface defines the minimal props needed to render SettingsTab
 * for Interactive Quiz, making most Quiz-specific props optional.
 */
export interface InteractiveQuizSettingsTabProps {
  // Required Interactive Quiz settings
  attemptsAllowed: number;
  passingGrade: number;
  quizAutoStart: boolean;
  questionsOrder: QuestionOrder;

  // Required UI state
  isSaving: boolean;
  saveSuccess: boolean;
  saveError: string | null;

  // Required handlers
  onSettingChange: (settings: Record<string, any>) => void;
  onSaveErrorDismiss: () => void;

  // Required validation errors (subset)
  errors: {
    passing_grade?: string;
    attempts_allowed?: string;
  };
}

/**
 * Props interface for H5P Content Selection Tab
 */
export interface H5PContentTabProps {
  /** Currently selected H5P content */
  selectedContent: H5PContent | null;

  /** Available H5P content items */
  contentItems: H5PContent[];

  /** Loading state for content fetching */
  isLoading: boolean;

  /** Error state for content fetching */
  error: string | null;

  /** Current search term */
  searchTerm: string;

  /** Whether save operation is in progress */
  isSaving: boolean;

  /** Content selection handler */
  onContentSelect: (content: H5PContent) => void;

  /** Search term change handler */
  onSearchChange: (searchTerm: string) => void;

  /** Retry content loading handler */
  onRetry: () => void;

  /** Create new H5P content handler (external link) */
  onCreateNew: () => void;
}

// ============================================================================
// API Request/Response Interfaces
// ============================================================================

/**
 * Interactive Quiz save request
 */
export interface InteractiveQuizSaveRequest {
  action: "tutor_interactive_quiz_save";
  _tutor_nonce: string;
  payload: string; // JSON stringified InteractiveQuizForm
  course_id: string;
  topic_id: string;
}

/**
 * H5P content fetch request
 */
export interface H5PContentFetchRequest {
  action: "tutor_get_h5p_contents";
  _tutor_nonce: string;
  search?: string;
  content_type?: string;
  per_page?: number;
  page?: number;
}

// ============================================================================
// Utility Functions and Defaults
// ============================================================================

/**
 * Default Interactive Quiz Settings
 */
export const getDefaultInteractiveQuizSettings = (): InteractiveQuizSettings => ({
  attempts_allowed: 0, // Unlimited attempts by default
  passing_grade: 80, // 80% passing grade
  quiz_auto_start: false, // Manual start by default
  questions_order: "sorting", // Default sorting order
});

/**
 * Create default Interactive Quiz form state
 */
export const getDefaultInteractiveQuizFormState = (): InteractiveQuizFormState => ({
  title: "",
  description: "",
  settings: getDefaultInteractiveQuizSettings(),
  h5pContent: null,
  errors: {},
  isDirty: false,
  isValid: false,
});

/**
 * Type guard for H5P Content
 */
export const isValidH5PContent = (content: unknown): content is H5PContent => {
  return (
    typeof content === "object" &&
    content !== null &&
    "id" in content &&
    "title" in content &&
    "content_type" in content &&
    typeof (content as any).id === "number" &&
    typeof (content as any).title === "string" &&
    typeof (content as any).content_type === "string"
  );
};

/**
 * Type guard for Interactive Quiz Details
 */
export const isValidInteractiveQuizDetails = (quiz: unknown): quiz is InteractiveQuizDetails => {
  return (
    typeof quiz === "object" &&
    quiz !== null &&
    "ID" in quiz &&
    "post_title" in quiz &&
    "quiz_option" in quiz &&
    "h5p_content" in quiz &&
    typeof (quiz as any).ID === "number" &&
    typeof (quiz as any).post_title === "string" &&
    isValidH5PContent((quiz as any).h5p_content)
  );
};

/**
 * Create Interactive Quiz error
 */
export const createInteractiveQuizError = (
  code: QuizErrorCode,
  message: string,
  context?: {
    action?: string;
    quizId?: number;
    topicId?: number;
    h5pContentId?: number;
    details?: string;
  }
): QuizError => ({
  code,
  message,
  context,
});
