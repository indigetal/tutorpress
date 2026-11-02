import { __ } from "@wordpress/i18n";
import { CurriculumErrorCode, ContentItem } from "../../../types/curriculum";
import { normalizeLiveLessonError, preserveLiveLessonMutation } from "../../../utils/liveLessonErrors";
import { createRemoveContentPayload, createRemoveMultiTypeContentPayload } from "./topicsUpdate";
import { refreshTopicsAfterSettledMutation } from "./topicRefresh";

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

export type DeleteConfig = SimpleDeleteConfig | ComplexDeleteConfig;

const refreshFailure = (warning: string | null): { message: string } | undefined => (warning === null ? undefined : { message: warning });

const isRecord = (value: unknown): value is Record<string, unknown> => typeof value === "object" && value !== null && !Array.isArray(value);

const successNotice = /deleted successfully|request successful/i;

const settledDeleteEnvelope = (value: unknown): boolean => {
  if (!isRecord(value) || !isRecord(value.data)) return false;
  const data = value.data;
  const state = data.state === "pending" || data.state === "error" || data.state === "unlinked" || data.state === "synced";
  return state && typeof data.reconciliation_required === "boolean" && (data.operation === null || isRecord(data.operation));
};

const confirmedLiveLessonDelete = (response: unknown): Record<string, unknown> | null => {
  if (!isRecord(response) || !isRecord(response.data)) return null;
  const data = response.data;
  if (data.outcome === "confirmed" && data.provenance === "delete" && data.item === null) return data;
  return null;
};

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
  return function* (entityId: number): Generator<unknown, { outcome: Record<string, unknown>; refreshWarning: string | null }, unknown> {
    let courseId: number | undefined;
    try {
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_START`,
        payload: { [config.idField]: entityId },
      };

      // Get parent info or entity details first if needed
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

      const deleteResponse = yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${entityId}`,
          method: "DELETE",
        },
      };
      const deleted = confirmedLiveLessonDelete(deleteResponse);
      if (!deleted) {
        throw deleteResponse;
      }

      const successPayload: any = { [config.idField]: entityId };
      if (courseId) {
        successPayload.courseId = courseId;
      }

      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_SUCCESS`,
        payload: successPayload,
      };

      const refreshWarning =
        typeof courseId === "number" && courseId > 0 ? yield* refreshTopicsAfterSettledMutation(courseId) : null;
      return preserveLiveLessonMutation(deleted, refreshFailure(refreshWarning));
    } catch (error) {
      const normalized = normalizeLiveLessonError(error);
      const data = normalized.data;
      const settled =
        settledDeleteEnvelope(error) ||
        data?.persisted === true ||
        data?.refetch_required === true ||
        data?.projected_record !== undefined;
      const refreshWarning =
        settled && typeof courseId === "number" && courseId > 0
          ? yield* refreshTopicsAfterSettledMutation(courseId)
          : null;
      const message =
        normalized.message !== "" && !successNotice.test(normalized.message)
          ? normalized.message
          : __(`Failed to delete ${config.entityName}`, "tutorpress");
      const preserved = preserveLiveLessonMutation({ ...normalized, message }, refreshFailure(refreshWarning));
      yield {
        type: `DELETE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message,
            ...(preserved.outcome.data ? { data: preserved.outcome.data } : {}),
            context: {
              action: `delete${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to delete ${config.entityName} ${entityId}`,
            },
          },
        },
      };
      const rejection = new Error(message);
      Object.assign(rejection, preserved);
      throw rejection;
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
};
