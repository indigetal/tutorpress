/**
 * H5P Store for TutorPress
 *
 * Dedicated store for H5P content operations, extracted from curriculum store
 * for better organization and maintainability.
 *
 * @package TutorPress
 * @since 1.5.0
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { __ } from "@wordpress/i18n";
import {
  H5PContentState,
  H5PStatementState,
  H5PValidationState,
  H5PResultsState,
  H5PContentSearchParams,
  H5PContent,
  H5PQuestionStatement,
  H5PQuestionValidation,
  H5PQuizResult,
  H5PContentResponse,
  H5PStatementSaveResponse,
  H5PValidationResponse,
  H5PQuizResultResponse,
  H5PError,
  H5PErrorCode,
} from "../../types/h5p";

// ============================================================================
// STATE INTERFACE
// ============================================================================

/**
 * H5P Store State Interface
 */
interface H5PState {
  /** H5P content search, selection, and pagination */
  content: H5PContentState;
  /** Statement saving and management */
  statements: H5PStatementState;
  /** Answer validation operations */
  validation: H5PValidationState;
  /** Quiz result fetching and caching */
  results: H5PResultsState;
}

// ============================================================================
// INITIAL STATE
// ============================================================================

const DEFAULT_STATE: H5PState = {
  content: {
    contents: [],
    selectedContent: null,
    searchParams: {},
    pagination: null,
    operationState: { status: "idle" },
  },
  statements: {
    statements: [],
    operationState: { status: "idle" },
  },
  validation: {
    validationResults: {},
    operationState: { status: "idle" },
  },
  results: {
    results: {},
    operationState: { status: "idle" },
  },
};

// ============================================================================
// ACTION TYPES
// ============================================================================

export type H5PAction =
  // Content Actions
  | { type: "FETCH_H5P_CONTENTS"; payload: { searchParams: H5PContentSearchParams } }
  | { type: "FETCH_H5P_CONTENTS_START"; payload: { searchParams: H5PContentSearchParams } }
  | { type: "FETCH_H5P_CONTENTS_SUCCESS"; payload: { contents: H5PContent[]; pagination?: any } }
  | { type: "FETCH_H5P_CONTENTS_ERROR"; payload: { error: H5PError } }
  | { type: "SET_H5P_SELECTED_CONTENT"; payload: { content: H5PContent | null } }
  | { type: "SET_H5P_SEARCH_PARAMS"; payload: { searchParams: H5PContentSearchParams } }
  // Statement Actions
  | { type: "SAVE_H5P_STATEMENT"; payload: { statement: H5PQuestionStatement } }
  | { type: "SAVE_H5P_STATEMENT_START"; payload: { statement: H5PQuestionStatement } }
  | { type: "SAVE_H5P_STATEMENT_SUCCESS"; payload: { statement: any; statementId: number } }
  | { type: "SAVE_H5P_STATEMENT_ERROR"; payload: { error: H5PError } }
  // Validation Actions
  | { type: "VALIDATE_H5P_ANSWERS"; payload: { validation: H5PQuestionValidation } }
  | { type: "VALIDATE_H5P_ANSWERS_START"; payload: { validation: H5PQuestionValidation } }
  | { type: "VALIDATE_H5P_ANSWERS_SUCCESS"; payload: { results: Record<number, boolean> } }
  | { type: "VALIDATE_H5P_ANSWERS_ERROR"; payload: { error: H5PError } }
  // Results Actions
  | { type: "FETCH_H5P_RESULTS"; payload: { resultParams: H5PQuizResult } }
  | { type: "FETCH_H5P_RESULTS_START"; payload: { resultParams: H5PQuizResult } }
  | { type: "FETCH_H5P_RESULTS_SUCCESS"; payload: { results: H5PQuizResultResponse; resultKey: string } }
  | { type: "FETCH_H5P_RESULTS_ERROR"; payload: { error: H5PError } };

// ============================================================================
// REDUCER
// ============================================================================

const reducer = (state = DEFAULT_STATE, action: H5PAction): H5PState => {
  switch (action.type) {
    // Content Operations
    case "FETCH_H5P_CONTENTS_START":
      return {
        ...state,
        content: {
          ...state.content,
          operationState: { status: "loading" },
        },
      };

    case "FETCH_H5P_CONTENTS_SUCCESS":
      return {
        ...state,
        content: {
          ...state.content,
          contents: action.payload.contents,
          pagination: action.payload.pagination || null,
          operationState: { status: "success" },
        },
      };

    case "FETCH_H5P_CONTENTS_ERROR":
      return {
        ...state,
        content: {
          ...state.content,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
      };

    case "SET_H5P_SELECTED_CONTENT":
      return {
        ...state,
        content: {
          ...state.content,
          selectedContent: action.payload.content,
        },
      };

    case "SET_H5P_SEARCH_PARAMS":
      return {
        ...state,
        content: {
          ...state.content,
          searchParams: action.payload.searchParams,
        },
      };

    // Statement Operations
    case "SAVE_H5P_STATEMENT_START":
      return {
        ...state,
        statements: {
          ...state.statements,
          operationState: { status: "saving" },
        },
      };

    case "SAVE_H5P_STATEMENT_SUCCESS":
      return {
        ...state,
        statements: {
          ...state.statements,
          statements: [...state.statements.statements, action.payload.statement],
          operationState: { status: "success" },
        },
      };

    case "SAVE_H5P_STATEMENT_ERROR":
      return {
        ...state,
        statements: {
          ...state.statements,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
      };

    // Validation Operations
    case "VALIDATE_H5P_ANSWERS_START":
      return {
        ...state,
        validation: {
          ...state.validation,
          operationState: { status: "validating" },
        },
      };

    case "VALIDATE_H5P_ANSWERS_SUCCESS":
      return {
        ...state,
        validation: {
          ...state.validation,
          validationResults: action.payload.results,
          operationState: { status: "success" },
        },
      };

    case "VALIDATE_H5P_ANSWERS_ERROR":
      return {
        ...state,
        validation: {
          ...state.validation,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
      };

    // Results Operations
    case "FETCH_H5P_RESULTS_START":
      return {
        ...state,
        results: {
          ...state.results,
          operationState: { status: "loading" },
        },
      };

    case "FETCH_H5P_RESULTS_SUCCESS":
      return {
        ...state,
        results: {
          ...state.results,
          results: {
            ...state.results.results,
            [action.payload.resultKey]: action.payload.results,
          },
          operationState: { status: "success" },
        },
      };

    case "FETCH_H5P_RESULTS_ERROR":
      return {
        ...state,
        results: {
          ...state.results,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
      };

    default:
      return state;
  }
};

// ============================================================================
// ACTION CREATORS
// ============================================================================

const actions = {
  // Content Action Creators
  fetchH5PContents(searchParams: H5PContentSearchParams) {
    return {
      type: "FETCH_H5P_CONTENTS" as const,
      payload: { searchParams },
    };
  },

  setH5PSelectedContent(content: H5PContent | null) {
    return {
      type: "SET_H5P_SELECTED_CONTENT" as const,
      payload: { content },
    };
  },

  setH5PSearchParams(searchParams: H5PContentSearchParams) {
    return {
      type: "SET_H5P_SEARCH_PARAMS" as const,
      payload: { searchParams },
    };
  },

  // Statement Action Creators
  saveH5PStatement(statement: H5PQuestionStatement) {
    return {
      type: "SAVE_H5P_STATEMENT" as const,
      payload: { statement },
    };
  },

  // Validation Action Creators
  validateH5PAnswers(validation: H5PQuestionValidation) {
    return {
      type: "VALIDATE_H5P_ANSWERS" as const,
      payload: { validation },
    };
  },

  // Results Action Creators
  fetchH5PResults(resultParams: H5PQuizResult) {
    return {
      type: "FETCH_H5P_RESULTS" as const,
      payload: { resultParams },
    };
  },
};

// ============================================================================
// SELECTORS
// ============================================================================

const selectors = {
  // Content Selectors
  getH5PContents(state: H5PState) {
    return state.content.contents;
  },

  getH5PSelectedContent(state: H5PState) {
    return state.content.selectedContent;
  },

  getH5PSearchParams(state: H5PState) {
    return state.content.searchParams;
  },

  getH5PPagination(state: H5PState) {
    return state.content.pagination;
  },

  getH5PContentOperationState(state: H5PState) {
    return state.content.operationState;
  },

  isH5PContentLoading(state: H5PState) {
    return state.content.operationState.status === "loading";
  },

  hasH5PContentError(state: H5PState) {
    return state.content.operationState.status === "error";
  },

  getH5PContentError(state: H5PState) {
    return state.content.operationState.error;
  },

  // Statement Selectors
  getH5PStatements(state: H5PState) {
    return state.statements.statements;
  },

  getH5PStatementOperationState(state: H5PState) {
    return state.statements.operationState;
  },

  isH5PStatementSaving(state: H5PState) {
    return state.statements.operationState.status === "saving";
  },

  hasH5PStatementError(state: H5PState) {
    return state.statements.operationState.status === "error";
  },

  getH5PStatementError(state: H5PState) {
    return state.statements.operationState.error;
  },

  // Validation Selectors
  getH5PValidationResults(state: H5PState) {
    return state.validation.validationResults;
  },

  getH5PValidationOperationState(state: H5PState) {
    return state.validation.operationState;
  },

  isH5PValidating(state: H5PState) {
    return state.validation.operationState.status === "validating";
  },

  hasH5PValidationError(state: H5PState) {
    return state.validation.operationState.status === "error";
  },

  getH5PValidationError(state: H5PState) {
    return state.validation.operationState.error;
  },

  // Results Selectors
  getH5PResults(state: H5PState) {
    return state.results.results;
  },

  getH5PResultsOperationState(state: H5PState) {
    return state.results.operationState;
  },

  isH5PResultsLoading(state: H5PState) {
    return state.results.operationState.status === "loading";
  },

  hasH5PResultsError(state: H5PState) {
    return state.results.operationState.status === "error";
  },

  getH5PResultsError(state: H5PState) {
    return state.results.operationState.error;
  },
};

// ============================================================================
// RESOLVERS (Generator Functions for API Operations)
// ============================================================================

const resolvers = {
  /**
   * Fetch H5P Contents Resolver
   */
  *fetchH5PContents(searchParams: H5PContentSearchParams): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "FETCH_H5P_CONTENTS_START",
        payload: { searchParams },
      };

      // Build query string from search parameters
      const queryParams = new URLSearchParams();
      if (searchParams.search) {
        queryParams.append("search", searchParams.search);
      }
      if (searchParams.contentType) {
        queryParams.append("content_type", searchParams.contentType);
      }
      if (searchParams.course_id) {
        queryParams.append("course_id", searchParams.course_id.toString());
      }
      if (searchParams.per_page) {
        queryParams.append("per_page", searchParams.per_page.toString());
      }
      if (searchParams.page) {
        queryParams.append("page", searchParams.page.toString());
      }
      if (searchParams.order) {
        queryParams.append("order", searchParams.order);
      }
      if (searchParams.orderby) {
        queryParams.append("orderby", searchParams.orderby);
      }

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/h5p/contents?${queryParams.toString()}`,
          method: "GET",
        },
      }) as H5PContentResponse;

      if (!response || !response.items) {
        throw new Error("Failed to fetch H5P contents");
      }

      yield {
        type: "FETCH_H5P_CONTENTS_SUCCESS",
        payload: {
          contents: response.items,
          pagination: {
            total: response.total,
            total_pages: response.total_pages,
            current_page: response.page,
            per_page: response.per_page,
          },
        },
      };
    } catch (error) {
      yield {
        type: "FETCH_H5P_CONTENTS_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.SERVER_ERROR,
            message: error instanceof Error ? error.message : "Failed to fetch H5P contents",
            context: { action: "fetchH5PContents", searchParams },
          },
        },
      };
    }
  },

  /**
   * Save H5P Statement Resolver
   */
  *saveH5PStatement(statement: H5PQuestionStatement): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "SAVE_H5P_STATEMENT_START",
        payload: { statement },
      };

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/h5p/statements",
          method: "POST",
          data: statement,
        },
      }) as H5PStatementSaveResponse;

      if (!response || !response.success) {
        throw new Error(response?.message || "Failed to save H5P statement");
      }

      yield {
        type: "SAVE_H5P_STATEMENT_SUCCESS",
        payload: {
          statement: statement,
          statementId: response.data?.statement_id || 0,
        },
      };
    } catch (error) {
      yield {
        type: "SAVE_H5P_STATEMENT_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.SERVER_ERROR,
            message: error instanceof Error ? error.message : "Failed to save H5P statement",
            context: { action: "saveH5PStatement", statement },
          },
        },
      };
    }
  },

  /**
   * Validate H5P Answers Resolver
   */
  *validateH5PAnswers(validation: H5PQuestionValidation): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "VALIDATE_H5P_ANSWERS_START",
        payload: { validation },
      };

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/h5p/validate",
          method: "POST",
          data: validation,
        },
      }) as H5PValidationResponse;

      if (!response || !response.success || !response.data) {
        throw new Error(response?.message || "Failed to validate H5P answers");
      }

      yield {
        type: "VALIDATE_H5P_ANSWERS_SUCCESS",
        payload: {
          results: response.data.validation_results,
        },
      };
    } catch (error) {
      yield {
        type: "VALIDATE_H5P_ANSWERS_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.VALIDATION_FAILED,
            message: error instanceof Error ? error.message : "Failed to validate H5P answers",
            context: { action: "validateH5PAnswers", validation },
          },
        },
      };
    }
  },

  /**
   * Fetch H5P Results Resolver
   */
  *fetchH5PResults(resultParams: H5PQuizResult): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "FETCH_H5P_RESULTS_START",
        payload: { resultParams },
      };

      // Build query string from result parameters
      const queryParams = new URLSearchParams({
        quiz_id: resultParams.quiz_id.toString(),
        user_id: resultParams.user_id.toString(),
        question_id: resultParams.question_id.toString(),
        content_id: resultParams.content_id.toString(),
        attempt_id: resultParams.attempt_id.toString(),
      });

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/h5p/results?${queryParams.toString()}`,
          method: "GET",
        },
      }) as H5PQuizResultResponse;

      if (!response || !response.success || !response.data) {
        throw new Error(response?.message || "Failed to fetch H5P results");
      }

      // Create a unique key for caching results
      const resultKey = `${resultParams.quiz_id}_${resultParams.user_id}_${resultParams.attempt_id}`;

      yield {
        type: "FETCH_H5P_RESULTS_SUCCESS",
        payload: {
          results: response,
          resultKey,
        },
      };
    } catch (error) {
      yield {
        type: "FETCH_H5P_RESULTS_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.SERVER_ERROR,
            message: error instanceof Error ? error.message : "Failed to fetch H5P results",
            context: { action: "fetchH5PResults", resultParams },
          },
        },
      };
    }
  },
};

// ============================================================================
// STORE CREATION AND REGISTRATION
// ============================================================================

/**
 * Create the H5P store
 */
const h5pStore = createReduxStore("tutorpress/h5p", {
  reducer,
  actions: {
    ...actions,
    ...resolvers,
  },
  selectors,
  controls,
});

// Register the store with WordPress Data
register(h5pStore);

export { h5pStore };

// Export actions for external use
export const {
  fetchH5PContents,
  setH5PSelectedContent,
  setH5PSearchParams,
  saveH5PStatement,
  validateH5PAnswers,
  fetchH5PResults,
} = actions;

// Export selectors for external use
export const {
  getH5PContents,
  getH5PSelectedContent,
  getH5PSearchParams,
  getH5PPagination,
  getH5PContentOperationState,
  isH5PContentLoading,
  hasH5PContentError,
  getH5PContentError,
  getH5PStatements,
  getH5PStatementOperationState,
  isH5PStatementSaving,
  hasH5PStatementError,
  getH5PStatementError,
  getH5PValidationResults,
  getH5PValidationOperationState,
  isH5PValidating,
  hasH5PValidationError,
  getH5PValidationError,
  getH5PResults,
  getH5PResultsOperationState,
  isH5PResultsLoading,
  hasH5PResultsError,
  getH5PResultsError,
} = selectors;

// Export types for external use
export type { H5PState };
