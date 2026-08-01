/**
 * Quiz Type Definitions for TutorPress
 *
 * These interfaces match the Tutor LMS QuizBuilder structure and follow
 * the established TutorPress patterns for type definitions.
 */

// ============================================================================
// Base Quiz Types
// ============================================================================

/**
 * Quiz question types supported by Tutor LMS
 */
export type QuizQuestionType =
  | "true_false"
  | "single_choice"
  | "multiple_choice"
  | "open_ended"
  | "fill_in_the_blank"
  | "short_answer"
  | "matching"
  | "image_matching"
  | "image_answering"
  | "ordering"
  | "h5p"
  | "draw_image"
  | "scale"
  | "pin_image"
  | "coordinates"
  | "puzzle";

/**
 * Time unit types for quiz time limits
 */
export type TimeUnit = "seconds" | "minutes" | "hours" | "days" | "weeks";

/**
 * Quiz feedback modes
 */
export type FeedbackMode = "default" | "reveal" | "retry";

/**
 * Question layout view options
 */
export type QuestionLayoutView = "" | "single_question" | "question_pagination" | "question_below_each_other";

/**
 * Question order options
 */
export type QuestionOrder = "rand" | "sorting" | "asc" | "desc";

/**
 * Data status tracking for quiz builder operations
 */
export type DataStatus = "new" | "update" | "no_change";

// ============================================================================
// Server Capability Contract
// ============================================================================

/**
 * Normalized Tutor learning mode reported by the server contract.
 */
export type TutorLearningMode = "legacy" | "modern" | "kids" | "unknown";

/**
 * Executable Quiz Settings contract selected by the PHP runtime.
 */
export type QuizSettingsContract = "v4" | "legacy" | "unavailable";

/**
 * Machine-readable reason Quiz Settings compatibility is unavailable.
 *
 * An empty string means the selected contract is executable. The UI owns translation.
 */
export type QuizSettingsUnavailableReason =
  | ""
  | "tutor_inactive"
  | "tutor_version_missing"
  | "unsupported_tutor_version"
  | "legacy_contract_unavailable";

/**
 * Machine-readable reason a question type cannot be created.
 *
 * An empty string means the type is creatable. The client owns translation.
 */
export type QuizTypeUnavailableReason = "" | "unsupported_tutor_version" | "pro_required" | "legacy_mode";

/**
 * Per-type capability entry derived from Tutor's authoritative registry.
 */
export interface QuizQuestionTypeCapability {
  slug: string;
  label: string;
  is_pro: boolean;
  registered: boolean;
  can_create: boolean;
  can_edit_existing: boolean;
  unavailable_reason: QuizTypeUnavailableReason;
}

/**
 * TutorPress-owned quiz capability contract localized by the server.
 *
 * This is the single authoritative source for question-type availability. It
 * replaces the absent Tutor discovery surfaces the client previously probed.
 */
export interface QuizCapabilities {
  tutorActive: boolean;
  tutorVersion: string;
  meetsSupportedFloor: boolean;
  hasNativeQuizTypes: boolean;
  learningMode: TutorLearningMode;
  proActive: boolean;
  proNativeQuizSupport: boolean;
  supportsTempMaskDeletion: boolean;
  quizSettingsContract: QuizSettingsContract;
  quizSettingsUnavailableReason: QuizSettingsUnavailableReason;
  supportsOrthogonalFeedback: boolean;
  supportsSeparatePagination: boolean;
  supportsV4TimingNavigation: boolean;
  supportsLegacyFeedbackLayout: boolean;
  supportsV4QuizContentDrip: boolean;
  questionTypes: QuizQuestionTypeCapability[];
}

/**
 * A question type as presented in the quiz builder's picker.
 *
 * Derived from `QuizCapabilities.questionTypes`. `unavailableReason` is already
 * translated for display; the machine code stays on the server contract.
 */
export interface QuestionTypeOption {
  label: string;
  value: QuizQuestionType;
  is_pro: boolean;
  disabled: boolean;
  unavailableReason: string;
}

// ============================================================================
// Quiz Settings Interfaces
// ============================================================================

/**
 * Quiz time limit settings
 */
export interface QuizTimeLimit {
  time_value: number;
  time_type: TimeUnit;
}

/**
 * Content drip settings for quiz access control
 */
export interface QuizContentDripSettings {
  unlock_date: string;
  after_xdays_of_enroll: number;
  prerequisites: number[];
}

export type QuizContentType = "tutor_quiz" | "tutor_h5p_quiz";

export type QuizPaginationType = "shape" | "number" | "radio";
export type RawQuizBoolean = boolean | 0 | 1 | "0" | "1";
export type RawQuizScalar = string | number | boolean | null;

/**
 * Open storage shapes retain unknown Tutor and third-party keys.
 */
export interface RawQuizTimeLimit {
  time_value?: RawQuizScalar;
  time_type?: RawQuizScalar;
  [key: string]: unknown;
}

export interface RawQuizContentDripSettings {
  unlock_date?: RawQuizScalar;
  after_xdays_of_enroll?: RawQuizScalar;
  prerequisites?: unknown;
  [key: string]: unknown;
}

export interface RawQuizSettings {
  time_limit?: RawQuizTimeLimit;
  hide_quiz_time_display?: RawQuizBoolean;
  feedback_mode?: FeedbackMode | string;
  limit_attempts_allowed?: RawQuizBoolean;
  attempts_allowed?: RawQuizScalar;
  pass_is_required?: RawQuizBoolean;
  passing_grade?: RawQuizScalar;
  max_questions_for_answer?: RawQuizScalar;
  quiz_auto_start?: RawQuizBoolean;
  auto_start_delay?: RawQuizScalar;
  question_layout_view?: QuestionLayoutView | string;
  enable_pagination?: RawQuizBoolean;
  pagination_type?: QuizPaginationType | string;
  question_pagination_style?: QuizPaginationType | string;
  enable_answer_reveal?: RawQuizBoolean;
  answers_reveal_duration?: RawQuizScalar;
  hide_previous_button?: RawQuizBoolean;
  questions_order?: QuestionOrder | string;
  hide_question_number_overview?: RawQuizBoolean;
  short_answer_characters_limit?: RawQuizScalar;
  open_ended_answer_characters_limit?: RawQuizScalar;
  content_drip_settings?: RawQuizContentDripSettings;
  quiz_type?: RawQuizScalar;
  [key: string]: unknown;
}

export type QuizEffectiveLayout = "single_question" | "question_below_each_other";

/**
 * Normalized editor values, including toggles that are never persisted directly.
 */
export interface QuizEffectiveSettings {
  enable_time_limit: boolean;
  time_limit: QuizTimeLimit;
  hide_quiz_time_display: boolean;
  feedback_mode: FeedbackMode;
  limit_attempts_allowed: boolean;
  attempts_allowed: number;
  pass_is_required: boolean;
  passing_grade: number;
  limit_questions_to_answer: boolean;
  max_questions_for_answer: number;
  quiz_auto_start: boolean;
  auto_start_delay: number;
  question_layout_view: QuizEffectiveLayout;
  enable_pagination: boolean;
  pagination_type: QuizPaginationType;
  enable_answer_reveal: boolean;
  answers_reveal_duration: number;
  hide_previous_button: boolean;
  questions_order: QuestionOrder;
  hide_question_number_overview: boolean;
  short_answer_characters_limit: number | "";
  open_ended_answer_characters_limit: number | "";
  content_drip_settings: QuizContentDripSettings;
}

export type QuizSettingsDirtyGroup =
  | "passing_grade"
  | "question_order"
  | "attempts"
  | "answer_reveal"
  | "legacy_feedback"
  | "question_limit"
  | "time_limit"
  | "hide_countdown"
  | "auto_start"
  | "layout"
  | "pagination"
  | "hide_previous"
  | "hide_question_number"
  | "short_answer_character_limit"
  | "open_ended_character_limit"
  | "pass_required"
  | "drip_unlock_date"
  | "drip_available_after_days"
  | "drip_prerequisites";

export interface QuizSettingsFormModel {
  contract: QuizSettingsContract;
  contentType: QuizContentType;
  rawSettings: RawQuizSettings;
  effectiveSettings: QuizEffectiveSettings;
  dirtyGroups: ReadonlySet<QuizSettingsDirtyGroup>;
}

/**
 * Inputs are explicit so stored identity and addon data cannot select editor policy.
 */
export interface QuizSettingsLoadInput {
  contract: QuizSettingsContract;
  contentType: QuizContentType;
  rawSettings: RawQuizSettings;
  contentDripAvailable: boolean;
  hasProContentDripSettings: boolean;
  proContentDripSettings?: RawQuizContentDripSettings | unknown[];
}

/**
 * Unavailable contracts retain raw storage but deliberately have no editable values.
 */
export interface QuizSettingsLoadResult {
  contract: QuizSettingsContract;
  contentType: QuizContentType;
  rawSettings: RawQuizSettings;
  effectiveSettings: QuizEffectiveSettings | null;
  dirtyGroups: ReadonlySet<QuizSettingsDirtyGroup>;
}

export interface QuizSettingsSaveInput {
  contract: QuizSettingsContract;
  contentType: QuizContentType;
  rawSettings: RawQuizSettings;
  effectiveSettings: QuizEffectiveSettings | null;
  dirtyGroups: ReadonlySet<QuizSettingsDirtyGroup>;
  isNewQuiz: boolean;
  h5pRuntimeAvailable: boolean;
}

export type QuizSettingsSaveBlockedReason =
  | "settings_contract_unavailable"
  | "effective_settings_unavailable"
  | "interactive_v4_required"
  | "h5p_runtime_unavailable";

export interface QuizSettingsSaveReadyResult {
  status: "ready";
  settings: RawQuizSettings;
}

export interface QuizSettingsSaveBlockedResult {
  status: "blocked";
  reason: QuizSettingsSaveBlockedReason;
  rawSettings: RawQuizSettings;
}

export type QuizSettingsSaveResult = QuizSettingsSaveReadyResult | QuizSettingsSaveBlockedResult;

/**
 * Comprehensive quiz settings interface matching Tutor LMS structure
 */
export interface QuizSettings {
  /** UI-only enable flag; off serializes time_limit.time_value as 0. */
  enable_time_limit: boolean;
  time_limit: QuizTimeLimit;
  hide_quiz_time_display: boolean;
  feedback_mode: FeedbackMode;
  /** V4 attempts enable flag; legacy derives this from feedback_mode === "retry". */
  limit_attempts_allowed: boolean;
  attempts_allowed: number;
  pass_is_required: boolean;
  passing_grade: number;
  /** UI-only enable flag; off serializes max_questions_for_answer as 0. */
  limit_questions_to_answer: boolean;
  max_questions_for_answer: number;
  quiz_auto_start: boolean;
  /** V4-only companion; legacy form keeps the default and does not emit it. */
  auto_start_delay: number;
  question_layout_view: QuestionLayoutView;
  /** Effective Single Question pagination; legacy UI translates to question_pagination. */
  enable_pagination: boolean;
  pagination_type: QuizPaginationType;
  /** V4 orthogonal reveal; legacy Feedback Mode remains the compatible editor. */
  enable_answer_reveal: boolean;
  answers_reveal_duration: number;
  /** V4-only; hidden while pagination is on. */
  hide_previous_button: boolean;
  questions_order: QuestionOrder;
  hide_question_number_overview: boolean;
  /** Empty or 0 disables the learner-side limit. */
  short_answer_characters_limit: number | "";
  /** Empty or 0 disables the learner-side limit. */
  open_ended_answer_characters_limit: number | "";
  content_drip_settings: QuizContentDripSettings;
  /** Tutor quiz variant, e.g. `tutor_h5p_quiz` for Interactive Quizzes. */
  quiz_type?: string;
}

// ============================================================================
// Question and Answer Interfaces
// ============================================================================

/**
 * Quiz question settings
 */
export interface QuizQuestionSettings {
  question_type: QuizQuestionType;
  answer_required: boolean;
  randomize_question: boolean;
  question_mark: number;
  show_question_mark: boolean;
  has_multiple_correct_answer: boolean;
  is_image_matching: boolean;
  /** Draw Image overlap threshold, integer 40-100. Tutor 4.0 native contract. */
  draw_image_threshold_percent?: number;
  /** Graph axis range, native values 10 or 20. Tutor 4.0 native contract. */
  coordinates_axis_range?: number;
  /** Puzzle grid size, integer 2-7. Tutor 4.0 native contract. */
  puzzle_grid_size?: number;
}

/**
 * Quiz question answer option
 */
export interface QuizQuestionOption {
  answer_id: number;
  belongs_question_id: number;
  belongs_question_type: QuizQuestionType;
  answer_title: string;
  is_correct: "0" | "1";
  image_id?: number;
  image_url?: string;
  answer_two_gap_match: string;
  answer_view_format: string;
  answer_order: number;
  /**
   * Tutor-owned answer settings column, preserved on load.
   *
   * Inert on save: `prepare_answer_data()` hardcodes this column to `null` in both
   * Tutor 3.9.6 and 4.0.2, so nothing TutorPress sends here is ever stored.
   */
  answer_settings?: string | null;
  _data_status?: DataStatus;
}

/**
 * Quiz question interface
 */
export interface QuizQuestion {
  question_id: number;
  question_title: string;
  question_description: string;
  question_mark: number;
  answer_explanation: string;
  question_order: number;
  question_type: QuizQuestionType;
  question_settings: QuizQuestionSettings;
  question_answers: QuizQuestionOption[];
  /**
   * Content Bank linkage owned by Tutor. Opaque to TutorPress: it is forwarded unchanged
   * so Tutor can apply its own linked-row update and delete semantics.
   */
  content_id?: number | string | null;
  _data_status?: DataStatus;
}

// ============================================================================
// Quiz Form and API Interfaces
// ============================================================================

/**
 * Quiz form data structure for saving
 */
export interface QuizForm {
  ID?: number;
  post_title: string;
  post_content: string;
  quiz_option: QuizSettings;
  questions: QuizQuestion[];
  deleted_question_ids?: number[];
  deleted_answer_ids?: number[];
  /**
   * Abandoned temporary Draw/Pin/Puzzle mask values for Tutor 4.0 cleanup.
   *
   * Only unpersisted values belong here; persisted URLs and Content Bank-linked assets
   * must never be registered.
   */
  deleted_temp_mask_values?: string[];
  menu_order?: number;
}

export interface QuizContentDripPostFields {
  "content_drip_settings[unlock_date]"?: string;
  "content_drip_settings[after_xdays_of_enroll]"?: number;
  "content_drip_settings[prerequisites]"?: number[] | "";
}

/**
 * Keeps Tutor's JSON quiz payload separate from top-level Pro POST fields.
 */
export interface QuizSaveEnvelope {
  quiz: QuizForm;
  contentDripPostFields: QuizContentDripPostFields;
}

/**
 * Quiz details response from API
 */
export interface QuizDetails {
  ID: number;
  post_title: string;
  post_content: string;
  post_status: string;
  post_author: string;
  post_parent: number;
  menu_order: number;
  quiz_option: QuizSettings;
  questions: QuizQuestion[];
  /** Explicit Pro `_content_drip_settings` presence from `metadata_exists()`. */
  has_pro_content_drip_settings: boolean;
  /** Guarded Pro drip array/object; separate from nested `quiz_option`. */
  pro_content_drip_settings: RawQuizContentDripSettings | unknown[];
}

// ============================================================================
// Quiz Store State Interfaces
// ============================================================================

/**
 * Quiz operation states for store management
 */
export type QuizOperationState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "saving" }
  | { status: "deleting" }
  | { status: "success"; data: QuizDetails }
  | { status: "error"; error: QuizError };

/**
 * Quiz creation state
 */
export type QuizCreationState =
  | { status: "idle" }
  | { status: "creating" }
  | { status: "success"; data: QuizDetails }
  | { status: "error"; error: QuizError };

/**
 * Quiz edit state
 */
export interface QuizEditState {
  isEditing: boolean;
  quizId: number | null;
  topicId: number | null;
}

/**
 * Quiz deletion state
 */
export interface QuizDeletionState {
  status: "idle" | "deleting" | "error" | "success";
  error?: QuizError;
  quizId?: number;
}

// ============================================================================
// Error Handling
// ============================================================================

/**
 * Quiz error codes
 */
export const enum QuizErrorCode {
  FETCH_FAILED = "fetch_failed",
  SAVE_FAILED = "save_failed",
  DELETE_FAILED = "delete_failed",
  DUPLICATE_FAILED = "duplicate_failed",
  VALIDATION_ERROR = "validation_error",
  INVALID_RESPONSE = "invalid_response",
  SERVER_ERROR = "server_error",
  NETWORK_ERROR = "network_error",
  OPERATION_IN_PROGRESS = "operation_in_progress",
}

/**
 * Structured error type for quiz operations
 */
export interface QuizError {
  code: QuizErrorCode;
  message: string;
  context?: {
    action?: string;
    quizId?: number;
    topicId?: number;
    details?: string;
    operationType?: string;
    operationData?: {
      sourceQuizId?: number;
      targetQuizId?: number;
    };
  };
}

// ============================================================================
// API Operations
// ============================================================================

/**
 * Quiz API operation types for error tracking and context
 */
export type QuizApiOperation =
  | { type: "none" }
  | { type: "create"; topicId: number }
  | { type: "edit"; quizId: number; topicId: number }
  | { type: "delete"; quizId: number }
  | { type: "duplicate"; quizId: number; topicId: number }
  | { type: "save"; quizId?: number; topicId: number };

// ============================================================================
// API Request/Response Types
// ============================================================================

/**
 * Quiz save request for Tutor LMS AJAX endpoint
 */
export interface QuizSaveRequest {
  action: "tutor_quiz_builder_save";
  _tutor_nonce: string;
  payload: string; // JSON stringified QuizForm
  course_id: string;
  topic_id: string;
}

/**
 * Quiz details request
 */
export interface QuizDetailsRequest {
  quiz_id: number;
}

/**
 * Quiz delete request
 */
export interface QuizDeleteRequest {
  quiz_id: number;
  course_id: number;
}

// ============================================================================
// Utility Types
// ============================================================================

/**
 * Operation result type for quiz operations
 */
export type QuizOperationResult<T> = {
  success: boolean;
  data?: T;
  error?: QuizError;
};

/**
 * Quiz form validation result
 */
export interface QuizValidationResult {
  success: boolean;
  errors: Record<string, string[]>;
}

// ============================================================================
// Type Guards
// ============================================================================

/**
 * Type guard for validating QuizQuestion objects
 */
export const isValidQuizQuestion = (question: unknown): question is QuizQuestion => {
  return (
    typeof question === "object" &&
    question !== null &&
    "question_id" in question &&
    "question_title" in question &&
    "question_type" in question &&
    "question_answers" in question &&
    Array.isArray((question as QuizQuestion).question_answers)
  );
};

/**
 * Type guard for validating QuizDetails objects
 */
export const isValidQuizDetails = (quiz: unknown): quiz is QuizDetails => {
  return (
    typeof quiz === "object" &&
    quiz !== null &&
    "ID" in quiz &&
    "post_title" in quiz &&
    "quiz_option" in quiz &&
    "questions" in quiz &&
    Array.isArray((quiz as QuizDetails).questions)
  );
};

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Create a quiz error with context
 */
export const createQuizError = (
  code: QuizErrorCode,
  message: string,
  operation: QuizApiOperation,
  context?: Omit<QuizError["context"], "operationType" | "operationData">
): QuizError => {
  const operationData: {
    sourceQuizId?: number;
    targetQuizId?: number;
  } = {};

  if (operation.type === "duplicate" && "quizId" in operation) {
    operationData.sourceQuizId = operation.quizId;
  }

  if (operation.type === "edit" && "quizId" in operation) {
    operationData.targetQuizId = operation.quizId;
  }

  return {
    code,
    message,
    context: {
      ...context,
      operationType: operation.type,
      operationData,
    },
  };
};

/**
 * Default quiz settings
 */
export const getDefaultQuizSettings = (): QuizSettings => ({
  enable_time_limit: false,
  time_limit: {
    time_value: 0,
    time_type: "minutes",
  },
  hide_quiz_time_display: false,
  feedback_mode: "default",
  limit_attempts_allowed: false,
  attempts_allowed: 0,
  pass_is_required: false,
  passing_grade: 80,
  limit_questions_to_answer: false,
  max_questions_for_answer: 10,
  quiz_auto_start: false,
  auto_start_delay: 5,
  question_layout_view: "",
  enable_pagination: false,
  pagination_type: "shape",
  enable_answer_reveal: false,
  answers_reveal_duration: 5,
  hide_previous_button: false,
  questions_order: "rand",
  hide_question_number_overview: false,
  short_answer_characters_limit: 200,
  open_ended_answer_characters_limit: 500,
  content_drip_settings: {
    unlock_date: "",
    after_xdays_of_enroll: 0,
    prerequisites: [],
  },
});

/**
 * Default question settings
 */
export const getDefaultQuestionSettings = (questionType: QuizQuestionType): QuizQuestionSettings => ({
  question_type: questionType,
  answer_required: true,
  randomize_question: false,
  question_mark: 1,
  show_question_mark: true,
  has_multiple_correct_answer: questionType === "multiple_choice",
  is_image_matching: questionType === "image_matching",
  // Tutor seeds these type-specific settings in its own new-question factory. The
  // literals avoid importing editor modules into this shared type/factory module.
  ...(questionType === "draw_image" && { draw_image_threshold_percent: 70 }),
  ...(questionType === "coordinates" && { coordinates_axis_range: 10 }),
  ...(questionType === "puzzle" && { puzzle_grid_size: 4 }),
});
