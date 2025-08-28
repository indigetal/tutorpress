/**
 * H5P Type Definitions for TutorPress
 *
 * These types support the Interactive Quiz Modal and H5P integration
 * with Tutor LMS compatibility maintained.
 */

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

  /** Course ID for collaborative instructor access */
  course_id?: number;

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
// xAPI Statement Types
// ============================================================================

/**
 * xAPI Actor (User)
 */
export interface XAPIActor {
  name: string;
  mbox?: string;
  account?: {
    homePage: string;
    name: string;
  };
}

/**
 * xAPI Verb
 */
export interface XAPIVerb {
  id: string;
  display?: Record<string, string>;
}

/**
 * xAPI Object
 */
export interface XAPIObject {
  id: string;
  definition?: {
    name?: Record<string, string>;
    description?: Record<string, string>;
    type?: string;
    interactionType?: string;
    correctResponsesPattern?: string[];
    choices?: Array<{
      id: string;
      description?: Record<string, string>;
    }>;
  };
}

/**
 * xAPI Result
 */
export interface XAPIResult {
  completion?: boolean;
  success?: boolean;
  score?: {
    min?: number;
    max?: number;
    raw?: number;
    scaled?: number;
  };
  duration?: string;
  response?: string;
}

/**
 * xAPI Statement
 */
export interface XAPIStatement {
  actor: XAPIActor;
  verb: XAPIVerb;
  object: XAPIObject;
  result?: XAPIResult;
  timestamp?: string;
  authority?: XAPIActor;
  context?: {
    instructor?: XAPIActor;
    team?: XAPIActor;
    contextActivities?: {
      parent?: XAPIObject[];
      grouping?: XAPIObject[];
      category?: XAPIObject[];
      other?: XAPIObject[];
    };
    language?: string;
    statement?: {
      id: string;
      objectType: "StatementRef";
    };
  };
}

// ============================================================================
// H5P Quiz Integration Types
// ============================================================================

/**
 * H5P Question Statement Data for Quiz Integration
 */
export interface H5PQuestionStatement {
  quiz_id: number;
  question_id: number;
  content_id: number;
  statement: string; // JSON stringified XAPIStatement
  attempt_id: number;
}

/**
 * H5P Question Validation Data
 */
export interface H5PQuestionValidation {
  question_ids: string; // JSON stringified array of {question_id, content_id}
  quiz_id: number;
  attempt_id: number;
}

/**
 * H5P Quiz Result Data
 */
export interface H5PQuizResult {
  quiz_id: number;
  user_id: number;
  question_id: number;
  content_id: number;
  attempt_id: number;
}

/**
 * H5P Quiz Result Response
 */
export interface H5PQuizResultResponse {
  success: boolean;
  message?: string;
  data: {
    result: XAPIResult;
    statement: XAPIStatement;
    grading: {
      total_points: number;
      earned_points: number;
      percentage: number;
    };
  };
}

// ============================================================================
// H5P Store State Types
// ============================================================================

/**
 * H5P Content Store State
 */
export interface H5PContentState {
  contents: H5PContent[];
  selectedContent: H5PContent | null;
  searchParams: H5PContentSearchParams;
  pagination: {
    total: number;
    total_pages: number;
    current_page: number;
    per_page: number;
  } | null;
  operationState: {
    status: "idle" | "loading" | "success" | "error";
    error?: {
      code: string;
      message: string;
      context?: Record<string, any>;
    };
  };
}

/**
 * H5P Statement Store State
 */
export interface H5PStatementState {
  statements: XAPIStatement[];
  operationState: {
    status: "idle" | "saving" | "success" | "error";
    error?: {
      code: string;
      message: string;
      context?: Record<string, any>;
    };
  };
}

/**
 * H5P Validation Store State
 */
export interface H5PValidationState {
  validationResults: Record<string, boolean>; // question_id -> is_answered
  operationState: {
    status: "idle" | "validating" | "success" | "error";
    error?: {
      code: string;
      message: string;
      context?: Record<string, any>;
    };
  };
}

/**
 * H5P Quiz Results Store State
 */
export interface H5PResultsState {
  results: Record<string, H5PQuizResultResponse>; // quiz_id_user_id -> result
  operationState: {
    status: "idle" | "loading" | "success" | "error";
    error?: {
      code: string;
      message: string;
      context?: Record<string, any>;
    };
  };
}

// ============================================================================
// API Response Types
// ============================================================================

/**
 * Generic H5P API Response
 */
export interface H5PAPIResponse<T = any> {
  success: boolean;
  message?: string;
  data?: T;
  error?: {
    code: string;
    message: string;
    context?: Record<string, any>;
  };
}

/**
 * H5P Statement Save Response
 */
export interface H5PStatementSaveResponse {
  success: boolean;
  message?: string;
  data: {
    statement_id: number;
    processed_at: string;
  };
}

/**
 * H5P Validation Response
 */
export interface H5PValidationResponse {
  success: boolean;
  message?: string;
  data: {
    validation_results: Record<number, boolean>; // question_id -> is_answered
    completion_status: "complete" | "incomplete";
    total_questions: number;
    answered_questions: number;
  };
}

// ============================================================================
// Error Types
// ============================================================================

/**
 * H5P Error Codes
 */
export enum H5PErrorCode {
  CONTENT_NOT_FOUND = "h5p_content_not_found",
  INVALID_STATEMENT = "h5p_invalid_statement",
  VALIDATION_FAILED = "h5p_validation_failed",
  PERMISSION_DENIED = "h5p_permission_denied",
  SERVER_ERROR = "h5p_server_error",
  NETWORK_ERROR = "h5p_network_error",
}

/**
 * H5P Error
 */
export interface H5PError {
  code: H5PErrorCode;
  message: string;
  context?: Record<string, any>;
}
