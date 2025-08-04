import { __ } from "@wordpress/i18n";
import { CurriculumErrorCode, ContentItem } from "../../../types/curriculum";
import { createRemoveContentPayload, createRemoveMultiTypeContentPayload } from "./topicsUpdate";

/**
 * Configuration for simple delete operations (lessons, assignments)
 */
export interface SimpleDeleteConfig {
  type: "simple";
  apiPath: string;
  contentType: ContentItem["type"];
  entityName: string;
  idField: string;
}

/**
 * Configuration for complex delete operations (quizzes, live lessons)
 */
export interface ComplexDeleteConfig {
  type: "complex";
  apiPath: string;
  parentInfoPath: string;
  contentTypes: ContentItem["type"][];
  entityName: string;
  idField: string;
  needsCourseId?: boolean;
}

/**
 * Configuration for topic delete operations
 */
export interface TopicDeleteConfig {
  type: "topic";
  apiPath: string;
  entityName: string;
  idField: string;
  courseIdField: string;
}

export type DeleteConfig = SimpleDeleteConfig | ComplexDeleteConfig | TopicDeleteConfig;

/**
 * Creates a delete resolver for simple content types (lessons, assignments)
 */
export function createSimpleDeleteResolver(config: SimpleDeleteConfig) {
  return function* (entityId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_START`,
        payload: { [config.idField]: entityId },
      };

      // Delete the entity
      yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${entityId}`,
          method: "DELETE",
        },
      };

      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_SUCCESS`,
        payload: { [config.idField]: entityId },
      };

      // Update topics directly to remove the deleted entity (preserves toggle states)
      yield {
        type: "SET_TOPICS",
        payload: createRemoveContentPayload(entityId, config.contentType),
      };
    } catch (error) {
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __(`Failed to delete ${config.entityName}`, "tutorpress"),
            context: {
              action: `delete${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to delete ${config.entityName} ${entityId}`,
            },
          },
        },
      };
    }
  };
}

/**
 * Creates a delete resolver for complex content types (quizzes, live lessons)
 */
export function createComplexDeleteResolver(config: ComplexDeleteConfig) {
  return function* (entityId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_START`,
        payload: { [config.idField]: entityId },
      };

      // Get parent info or entity details first if needed
      let courseId: number | undefined;
      if (config.needsCourseId) {
        const parentInfoResponse = yield {
          type: "API_FETCH",
          request: {
            path: `${config.parentInfoPath}/${entityId}/parent-info`,
            method: "GET",
          },
        };

        if (!parentInfoResponse || typeof parentInfoResponse !== "object" || !("data" in parentInfoResponse)) {
          throw new Error(`Invalid ${config.entityName} details response`);
        }

        const parentInfo = parentInfoResponse as { data: { course_id?: number; courseId?: number } };
        courseId = parentInfo.data.course_id || parentInfo.data.courseId;
      } else {
        // For live lessons, get the entity to extract courseId from response
        const entityResponse = yield {
          type: "API_FETCH",
          request: {
            path: `${config.parentInfoPath}/${entityId}`,
            method: "GET",
          },
        };

        if (!entityResponse || typeof entityResponse !== "object" || !("data" in entityResponse)) {
          throw new Error(`Invalid ${config.entityName} details response`);
        }

        const entityData = entityResponse as { data: { courseId?: number } };
        courseId = entityData.data.courseId;
      }

      // Delete the entity
      yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${entityId}`,
          method: "DELETE",
        },
      };

      const successPayload: any = { [config.idField]: entityId };
      if (courseId) {
        successPayload.courseId = courseId;
      }

      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_SUCCESS`,
        payload: successPayload,
      };

      // Update topics directly to remove the deleted entity (preserves toggle states)
      yield {
        type: "SET_TOPICS",
        payload: createRemoveMultiTypeContentPayload(entityId, config.contentTypes),
      };
    } catch (error) {
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __(`Failed to delete ${config.entityName}`, "tutorpress"),
            context: {
              action: `delete${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to delete ${config.entityName} ${entityId}`,
            },
          },
        },
      };
    }
  };
}

/**
 * Creates a delete resolver for topics (special case with full refresh)
 */
export function createTopicDeleteResolver(config: TopicDeleteConfig) {
  return function* (topicId: number, courseId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_START`,
        payload: { [config.idField]: topicId },
      };

      // Delete the topic
      yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${topicId}`,
          method: "DELETE",
        },
      };

      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_SUCCESS`,
        payload: { [config.idField]: topicId },
      };

      // Fetch updated topics (topics deletion requires full refresh)
      const topicsResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
          method: "GET",
        },
      };

      if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
        throw new Error("Invalid topics response");
      }

      const topics = topicsResponse as { data: any[] };

      // Transform topics to set all to collapsed after deletion (user preference)
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      yield {
        type: "SET_TOPICS",
        payload: transformedTopics,
      };
    } catch (error) {
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __(`Failed to delete ${config.entityName}`, "tutorpress"),
            context: {
              action: `delete${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to delete ${config.entityName} ${topicId}`,
            },
          },
        },
      };
    }
  };
}

/**
 * Pre-configured delete resolvers for all content types
 */
export const deleteResolvers = {
  lesson: createSimpleDeleteResolver({
    type: "simple",
    apiPath: "/tutorpress/v1/lessons",
    contentType: "lesson",
    entityName: "lesson",
    idField: "lessonId",
  }),

  assignment: createSimpleDeleteResolver({
    type: "simple",
    apiPath: "/tutorpress/v1/assignments",
    contentType: "tutor_assignments",
    entityName: "assignment",
    idField: "assignmentId",
  }),

  quiz: function* (quizId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "DELETE_QUIZ_START",
        payload: { quizId },
      };

      // Delete the quiz (no parent info needed)
      yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/quizzes/${quizId}`,
          method: "DELETE",
        },
      };

      yield {
        type: "DELETE_QUIZ_SUCCESS",
        payload: { quizId },
      };

      // Update topics directly to remove the deleted quiz (preserves toggle states)
      yield {
        type: "SET_TOPICS",
        payload: createRemoveMultiTypeContentPayload(quizId, ["tutor_quiz", "interactive_quiz"]),
      };
    } catch (error) {
      yield {
        type: "DELETE_QUIZ_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to delete quiz", "tutorpress"),
            context: {
              action: "deleteQuiz",
              details: `Failed to delete quiz ${quizId}`,
            },
          },
        },
      };
    }
  },

  liveLesson: createComplexDeleteResolver({
    type: "complex",
    apiPath: "/tutorpress/v1/live-lessons",
    parentInfoPath: "/tutorpress/v1/live-lessons",
    contentTypes: ["meet_lesson", "zoom_lesson"],
    entityName: "live_lesson",
    idField: "liveLessonId",
    needsCourseId: false, // Live lesson response includes courseId
  }),

  topic: createTopicDeleteResolver({
    type: "topic",
    apiPath: "/tutorpress/v1/topics",
    entityName: "topic",
    idField: "topicId",
    courseIdField: "courseId",
  }),
};
