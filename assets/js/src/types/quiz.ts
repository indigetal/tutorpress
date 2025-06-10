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
  | "h5p";

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

/**
 * Comprehensive quiz settings interface matching Tutor LMS structure
 */
export interface QuizSettings {
  time_limit: QuizTimeLimit;
  hide_quiz_time_display: boolean;
  feedback_mode: FeedbackMode;
  attempts_allowed: number;
  pass_is_required: boolean;
  passing_grade: number;
  max_questions_for_answer: number;
  quiz_auto_start: boolean;
  question_layout_view: QuestionLayoutView;
  questions_order: QuestionOrder;
  hide_question_number_overview: boolean;
  short_answer_characters_limit: number;
  open_ended_answer_characters_limit: number;
  content_drip_settings: QuizContentDripSettings;
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
  menu_order?: number;
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
  time_limit: {
    time_value: 0,
    time_type: "minutes",
  },
  hide_quiz_time_display: false,
  feedback_mode: "default",
  attempts_allowed: 0,
  pass_is_required: false,
  passing_grade: 80,
  max_questions_for_answer: 10,
  quiz_auto_start: false,
  question_layout_view: "",
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
});
