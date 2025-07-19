import { __ } from "@wordpress/i18n";
import { CurriculumErrorCode } from "../../../types/curriculum";

/**
 * Configuration for operation state management
 */
export interface OperationConfig {
  operationType: string;
  statePath: string;
  startStatus?: string;
  successStatus?: string;
  errorStatus?: string;
  loadingStatus?: string;
  idleStatus?: string;
  entityIdField?: string;
  additionalStartPayload?: Record<string, any>;
  additionalSuccessPayload?: Record<string, any>;
  preserveSuccessPayload?: boolean;
}

/**
 * Standard operation statuses
 */
export const OPERATION_STATUSES = {
  IDLE: "idle",
  LOADING: "loading",
  SAVING: "saving",
  DELETING: "deleting",
  DUPLICATING: "duplicating",
  VALIDATING: "validating",
  SUCCESS: "success",
  ERROR: "error",
} as const;

/**
 * Creates action types for an operation (START, SUCCESS, ERROR)
 */
export function createOperationActionTypes(operationType: string) {
  return {
    START: `${operationType.toUpperCase()}_START`,
    SUCCESS: `${operationType.toUpperCase()}_SUCCESS`,
    ERROR: `${operationType.toUpperCase()}_ERROR`,
  };
}

/**
 * Creates a wrapper for operation that handles START/SUCCESS/ERROR dispatching
 */
export function createOperationWrapper<TParams extends any[], TResult>(
  config: OperationConfig,
  operationFn: (...args: TParams) => Generator<unknown, TResult, unknown>
) {
  return function* (...args: TParams): Generator<unknown, TResult, unknown> {
    const actionTypes = createOperationActionTypes(config.operationType);

    try {
      // Dispatch START action
      const startPayload = {
        ...config.additionalStartPayload,
        ...(config.entityIdField && args[0] ? { [config.entityIdField]: args[0] } : {}),
      };

      yield {
        type: actionTypes.START,
        payload: startPayload,
      };

      // Execute the actual operation
      const result = yield* operationFn(...args);

      // Dispatch SUCCESS action
      const successPayload = config.preserveSuccessPayload
        ? result
        : {
            ...config.additionalSuccessPayload,
            ...(typeof result === "object" && result !== null ? result : {}),
          };

      yield {
        type: actionTypes.SUCCESS,
        payload: successPayload,
      };

      return result;
    } catch (error) {
      // Dispatch ERROR action
      const errorPayload = {
        error: {
          code: CurriculumErrorCode.SERVER_ERROR,
          message: error instanceof Error ? error.message : __(`Failed to ${config.operationType}`, "tutorpress"),
          context: {
            action: config.operationType,
            details: `${config.operationType} operation failed`,
          },
        },
        ...(config.entityIdField && args[0] ? { [config.entityIdField]: args[0] } : {}),
      };

      yield {
        type: actionTypes.ERROR,
        payload: errorPayload,
      };

      throw error;
    }
  };
}

/**
 * Creates reducer cases for operation state management
 */
export function createOperationReducerCases(config: OperationConfig) {
  const actionTypes = createOperationActionTypes(config.operationType);

  return {
    [actionTypes.START]: (state: any, action: any) => {
      const startStatus = config.startStatus || config.loadingStatus || OPERATION_STATUSES.LOADING;
      const stateUpdate = { status: startStatus };

      // Add entity ID if specified
      if (config.entityIdField && action.payload[config.entityIdField]) {
        (stateUpdate as any)[`active${config.entityIdField.charAt(0).toUpperCase() + config.entityIdField.slice(1)}`] =
          action.payload[config.entityIdField];
      }

      return {
        ...state,
        [config.statePath]: {
          ...state[config.statePath],
          ...stateUpdate,
        },
      };
    },

    [actionTypes.SUCCESS]: (state: any, action: any) => {
      const successStatus = config.successStatus || OPERATION_STATUSES.SUCCESS;
      const stateUpdate = { status: successStatus };

      // Add entity ID if present in payload
      if (action.payload && typeof action.payload === "object") {
        if (config.entityIdField) {
          const entityKey = `active${config.entityIdField.charAt(0).toUpperCase() + config.entityIdField.slice(1)}`;
          if (action.payload[config.entityIdField]) {
            (stateUpdate as any)[entityKey] = action.payload[config.entityIdField];
          }
        }

        // Add last saved ID for save operations
        if (config.operationType.includes("save") || config.operationType.includes("create")) {
          const entity =
            action.payload[
              Object.keys(action.payload).find(
                (key) => typeof action.payload[key] === "object" && action.payload[key]?.id
              ) || ""
            ];
          if (entity?.id) {
            (stateUpdate as any)[
              `lastSaved${config.operationType.charAt(0).toUpperCase() + config.operationType.slice(1)}Id`
            ] = entity.id;
          }
        }

        // Add duplicated ID for duplicate operations
        if (config.operationType.includes("duplicate")) {
          const entity =
            action.payload[
              Object.keys(action.payload).find(
                (key) => typeof action.payload[key] === "object" && action.payload[key]?.id
              ) || ""
            ];
          if (entity?.id) {
            (stateUpdate as any)[
              `duplicated${config.operationType.replace("duplicate", "").charAt(0).toUpperCase() + config.operationType.replace("duplicate", "").slice(1)}Id`
            ] = entity.id;
          }
        }
      }

      return {
        ...state,
        [config.statePath]: {
          ...state[config.statePath],
          ...stateUpdate,
        },
      };
    },

    [actionTypes.ERROR]: (state: any, action: any) => {
      const errorStatus = config.errorStatus || OPERATION_STATUSES.ERROR;
      const stateUpdate = {
        status: errorStatus,
        error: action.payload.error,
      };

      // Add entity ID if specified in error payload
      if (config.entityIdField && action.payload[config.entityIdField]) {
        (stateUpdate as any)[`active${config.entityIdField.charAt(0).toUpperCase() + config.entityIdField.slice(1)}`] =
          action.payload[config.entityIdField];
      }

      return {
        ...state,
        [config.statePath]: {
          ...state[config.statePath],
          ...stateUpdate,
        },
      };
    },
  };
}

/**
 * Pre-configured operation configs for common patterns
 */
export const OPERATION_CONFIGS = {
  // Lesson operations
  createLesson: {
    operationType: "createLesson",
    statePath: "lessonState",
    entityIdField: "topicId",
  } as OperationConfig,

  updateLesson: {
    operationType: "updateLesson",
    statePath: "lessonState",
    entityIdField: "lessonId",
  } as OperationConfig,

  deleteLesson: {
    operationType: "deleteLesson",
    statePath: "lessonState",
    entityIdField: "lessonId",
  } as OperationConfig,

  duplicateLesson: {
    operationType: "duplicateLesson",
    statePath: "lessonDuplicationState",
    startStatus: OPERATION_STATUSES.DUPLICATING,
    entityIdField: "lessonId",
  } as OperationConfig,

  // Quiz operations
  saveQuiz: {
    operationType: "saveQuiz",
    statePath: "quizState",
    startStatus: OPERATION_STATUSES.SAVING,
  } as OperationConfig,

  getQuiz: {
    operationType: "getQuiz",
    statePath: "quizState",
    entityIdField: "quizId",
  } as OperationConfig,

  deleteQuiz: {
    operationType: "deleteQuiz",
    statePath: "quizState",
    entityIdField: "quizId",
  } as OperationConfig,

  duplicateQuiz: {
    operationType: "duplicateQuiz",
    statePath: "quizDuplicationState",
    startStatus: OPERATION_STATUSES.DUPLICATING,
    entityIdField: "quizId",
  } as OperationConfig,

  // Live Lesson operations
  saveLiveLesson: {
    operationType: "saveLiveLesson",
    statePath: "liveLessonState",
    startStatus: OPERATION_STATUSES.SAVING,
  } as OperationConfig,

  getLiveLesson: {
    operationType: "getLiveLesson",
    statePath: "liveLessonState",
    entityIdField: "liveLessonId",
  } as OperationConfig,

  updateLiveLesson: {
    operationType: "updateLiveLesson",
    statePath: "liveLessonState",
    startStatus: OPERATION_STATUSES.SAVING,
    entityIdField: "liveLessonId",
  } as OperationConfig,

  deleteLiveLesson: {
    operationType: "deleteLiveLesson",
    statePath: "liveLessonState",
    entityIdField: "liveLessonId",
  } as OperationConfig,

  duplicateLiveLesson: {
    operationType: "duplicateLiveLesson",
    statePath: "liveLessonDuplicationState",
    startStatus: OPERATION_STATUSES.DUPLICATING,
    entityIdField: "liveLessonId",
  } as OperationConfig,

  // Topic operations
  fetchTopics: {
    operationType: "fetchTopics",
    statePath: "operationState",
    entityIdField: "courseId",
  } as OperationConfig,

  fetchCourseId: {
    operationType: "fetchCourseId",
    statePath: "operationState",
    entityIdField: "lessonId",
  } as OperationConfig,

  deleteTopic: {
    operationType: "deleteTopic",
    statePath: "deletionState",
    startStatus: OPERATION_STATUSES.DELETING,
    entityIdField: "topicId",
  } as OperationConfig,

  // Assignment operations
  deleteAssignment: {
    operationType: "deleteAssignment",
    statePath: "lessonState", // Note: assignments use lessonState
    entityIdField: "assignmentId",
  } as OperationConfig,

  updateAssignment: {
    operationType: "updateAssignment",
    statePath: "assignmentState",
    entityIdField: "assignmentId",
  } as OperationConfig,
};

/**
 * Creates wrapped operations for all common patterns
 */
export function createWrappedOperations() {
  const wrappedOperations: Record<string, any> = {};

  Object.entries(OPERATION_CONFIGS).forEach(([operationName, config]) => {
    wrappedOperations[operationName] = (operationFn: any) => createOperationWrapper(config, operationFn);
  });

  return wrappedOperations;
}

/**
 * Creates all reducer cases for operation state management
 */
export function createAllOperationReducerCases() {
  const allCases: Record<string, (state: any, action: any) => any> = {};

  Object.values(OPERATION_CONFIGS).forEach((config) => {
    const cases = createOperationReducerCases(config);
    Object.assign(allCases, cases);
  });

  return allCases;
}
