import { __ } from "@wordpress/i18n";
import { CurriculumErrorCode, ContentItem } from "../../../types/curriculum";

/**
 * Content reorder configuration interface
 */
export interface ContentReorderConfig {
  /** API endpoint path for reordering content items */
  apiPath: string;
  /** Human-readable entity name for error messages */
  entityName: string;
  /** Action prefix for state management */
  actionPrefix: string;
}

/**
 * Content order item interface matching the API contract
 */
export interface ContentOrder {
  /** Content item ID */
  id: number;
  /** New order position (0-based) */
  order: number;
}

/**
 * Content reorder operation state
 */
export interface ContentReorderOperationState {
  status: "idle" | "reordering" | "success" | "error";
  error?: {
    code: CurriculumErrorCode;
    message: string;
    context?: {
      action: string;
      details: string;
    };
  };
  topicId?: number;
}

/**
 * Content reorder response from API
 */
export interface ContentReorderResponse {
  success: boolean;
  message: string;
  data: ContentItem[];
}

/**
 * Default configuration for content reordering
 */
export const DEFAULT_CONTENT_REORDER_CONFIG: ContentReorderConfig = {
  apiPath: "/tutorpress/v1/topics",
  entityName: "content item",
  actionPrefix: "CONTENT_REORDER",
};

/**
 * Creates action types for content reorder operations
 */
export function createContentReorderActionTypes(config: ContentReorderConfig = DEFAULT_CONTENT_REORDER_CONFIG) {
  return {
    SET_STATE: `SET_${config.actionPrefix}_STATE`,
    START: `${config.actionPrefix}_START`,
    SUCCESS: `${config.actionPrefix}_SUCCESS`,
    ERROR: `${config.actionPrefix}_ERROR`,
  } as const;
}

/**
 * Creates state management actions for content reordering
 */
export function createContentReorderStateActions(config: ContentReorderConfig = DEFAULT_CONTENT_REORDER_CONFIG) {
  const actionTypes = createContentReorderActionTypes(config);

  return {
    setContentReorderState: (state: ContentReorderOperationState) => ({
      type: actionTypes.SET_STATE,
      payload: state,
    }),

    startContentReorder: (topicId: number) => ({
      type: actionTypes.START,
      payload: { topicId },
    }),

    successContentReorder: (topicId: number, updatedContents: ContentItem[]) => ({
      type: actionTypes.SUCCESS,
      payload: { topicId, updatedContents },
    }),

    errorContentReorder: (topicId: number, error: ContentReorderOperationState["error"]) => ({
      type: actionTypes.ERROR,
      payload: { topicId, error },
    }),
  };
}

/**
 * Creates a content reorder resolver following the established store pattern
 */
export function createContentReorderResolver(config: ContentReorderConfig = DEFAULT_CONTENT_REORDER_CONFIG) {
  return function* reorderTopicContent(
    topicId: number,
    contentOrders: ContentOrder[]
  ): Generator<unknown, void, unknown> {
    try {
      // Set reordering state
      yield {
        type: `SET_${config.actionPrefix}_STATE`,
        payload: {
          status: "reordering" as const,
          topicId,
        },
      };

      // Validate inputs
      if (!topicId || topicId <= 0) {
        throw new Error(__("Invalid topic ID provided", "tutorpress"));
      }

      if (!Array.isArray(contentOrders) || contentOrders.length === 0) {
        throw new Error(__("No content items to reorder", "tutorpress"));
      }

      // Validate content orders structure
      for (const contentOrder of contentOrders) {
        if (!contentOrder.id || typeof contentOrder.id !== "number" || contentOrder.id <= 0) {
          throw new Error(__("Invalid content item ID in reorder data", "tutorpress"));
        }
        if (typeof contentOrder.order !== "number" || contentOrder.order < 0) {
          throw new Error(__("Invalid order value in reorder data", "tutorpress"));
        }
      }

      // Make API call to reorder content items
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${topicId}/content/reorder`,
          method: "POST",
          data: {
            content_orders: contentOrders,
          },
        },
      };

      // Validate response
      if (!response || typeof response !== "object") {
        throw new Error(__("Invalid response from content reorder API", "tutorpress"));
      }

      const reorderResponse = response as ContentReorderResponse;

      if (!reorderResponse.success) {
        throw new Error(reorderResponse.message || __("Content reorder failed", "tutorpress"));
      }

      if (!Array.isArray(reorderResponse.data)) {
        throw new Error(__("Invalid content data in reorder response", "tutorpress"));
      }

      // Set success state
      yield {
        type: `SET_${config.actionPrefix}_STATE`,
        payload: {
          status: "success" as const,
        },
      };

      // Update the topic's contents with the new order
      // This preserves the topic's state while updating content order
      yield {
        type: "SET_TOPICS",
        payload: (currentTopics: any[]) => {
          return currentTopics.map((topic) => {
            if (topic.id === topicId) {
              return {
                ...topic,
                contents: reorderResponse.data,
              };
            }
            return topic;
          });
        },
      };
    } catch (error) {
      // Handle errors with comprehensive error information
      const errorMessage = error instanceof Error ? error.message : __("Failed to reorder content items", "tutorpress");

      yield {
        type: `SET_${config.actionPrefix}_STATE`,
        payload: {
          status: "error" as const,
          topicId,
          error: {
            code: CurriculumErrorCode.REORDER_FAILED,
            message: errorMessage,
            context: {
              action: "reorderTopicContent",
              details: `Failed to reorder content items for topic ${topicId}`,
            },
          },
        },
      };

      // Re-throw error for component-level handling if needed
      throw error;
    }
  };
}

/**
 * Creates selectors for content reorder state
 */
export function createContentReorderSelectors(config: ContentReorderConfig = DEFAULT_CONTENT_REORDER_CONFIG) {
  return {
    getContentReorderState: (state: any) => state.contentReorderState || { status: "idle" },
    isContentReordering: (state: any) => {
      const reorderState = state.contentReorderState || { status: "idle" };
      return reorderState.status === "reordering";
    },
    hasContentReorderError: (state: any) => {
      const reorderState = state.contentReorderState || { status: "idle" };
      return reorderState.status === "error" && !!reorderState.error;
    },
    getContentReorderError: (state: any) => {
      const reorderState = state.contentReorderState || { status: "idle" };
      return reorderState.status === "error" ? reorderState.error : null;
    },
  };
}

/**
 * Creates a complete content reorder utility with actions, resolver, and selectors
 */
export function createContentReorderUtility(config: ContentReorderConfig = DEFAULT_CONTENT_REORDER_CONFIG) {
  return {
    config,
    actionTypes: createContentReorderActionTypes(config),
    stateActions: createContentReorderStateActions(config),
    resolver: createContentReorderResolver(config),
    selectors: createContentReorderSelectors(config),
  };
}

/**
 * Default content reorder utility instance
 */
export const contentReorderUtility = createContentReorderUtility();

/**
 * Export the resolver and state actions for integration with the curriculum store
 */
export const {
  resolver: reorderTopicContent,
  stateActions: contentReorderStateActions,
  selectors: contentReorderSelectors,
} = contentReorderUtility;
