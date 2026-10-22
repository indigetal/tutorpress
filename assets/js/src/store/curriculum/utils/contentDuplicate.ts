import { __ } from "@wordpress/i18n";
import { CurriculumErrorCode, ContentItem } from "../../../types/curriculum";
import { LiveLesson, LiveLessonCreateResponse } from "../../../types/liveLessons";
import { Lesson } from "../../../types/lessons";
import { Assignment } from "../../../types/assignments";
import { normalizeLiveLessonError, preserveLiveLessonMutation } from "../../../utils/liveLessonErrors";
import { createDuplicateContentPayload, resolveContentType } from "./topicsUpdate";
import { refreshTopicsAfterSettledMutation } from "./topicRefresh";

const refreshFailure = (warning: string | null): { message: string } | undefined =>
  warning === null ? undefined : { message: warning };

/**
 * Configuration for standard duplicate operations (lessons, quizzes, live lessons)
 */
export interface StandardDuplicateConfig {
  type: "standard";
  apiPath: string;
  contentType: ContentItem["type"];
  entityName: string;
  sourceIdField: string;
  targetIdField: string;
  responseDataExtractor: (response: any) => { id: number; title: string };
  requiresCourseId?: boolean;
}

/**
 * Configuration for topic duplicate operations (legacy pattern)
 */
export interface TopicDuplicateConfig {
  type: "topic";
  entityName: string;
  sourceIdField: string;
  targetIdField: string;
  apiFunction: (sourceId: number, targetId: number) => Promise<any>;
  fetchFunction: (courseId: number) => Promise<any>;
}

export type DuplicateConfig = StandardDuplicateConfig | TopicDuplicateConfig;

/**
 * Creates a duplicate resolver for standard content types (lessons, quizzes, live lessons)
 */
export function createStandardDuplicateResolver(config: StandardDuplicateConfig) {
  return function* (sourceId: number, targetId: number, courseId?: number): Generator<unknown, any, unknown> {
    try {
      const startPayload: any = { [config.sourceIdField]: sourceId };
      if (config.requiresCourseId && courseId !== undefined) {
        startPayload.topicId = targetId;
        startPayload.courseId = courseId;
      }

      yield {
        type: `DUPLICATE_${config.entityName.toUpperCase()}_START`,
        payload: startPayload,
      };

      // Prepare API call data
      const apiData: any = { [config.targetIdField]: targetId };
      if (config.requiresCourseId && courseId !== undefined) {
        apiData.course_id = courseId;
      }

      // Duplicate the entity
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${sourceId}/duplicate`,
          method: "POST",
          data: apiData,
        },
      };

      if (!response || typeof response !== "object" || !("data" in response)) {
        throw new Error(`Invalid ${config.entityName} duplication response`);
      }

      const entityData = config.responseDataExtractor(response);

      const successPayload: any = {
        [config.sourceIdField.replace("Id", "")]: entityData,
        [`source${config.sourceIdField.charAt(0).toUpperCase() + config.sourceIdField.slice(1)}`]: sourceId,
      };

      if (config.requiresCourseId && courseId !== undefined) {
        successPayload.courseId = courseId;
      }

      yield {
        type: `DUPLICATE_${config.entityName.toUpperCase()}_SUCCESS`,
        payload: successPayload,
      };

      if (config.entityName === "live_lesson") {
        const refreshWarning =
          typeof courseId === "number" && courseId > 0 ? yield* refreshTopicsAfterSettledMutation(courseId) : null;
        return preserveLiveLessonMutation(response.data, refreshFailure(refreshWarning));
      }

      // Determine correct content type - special handling for quizzes that might be Interactive Quizzes
      let finalContentType = config.contentType;
      if (config.entityName === "quiz" && (response.data as any)?.quiz_option?.quiz_type === "tutor_h5p_quiz") {
        finalContentType = "interactive_quiz";
      }

      // Update topics directly to add the duplicated content (preserves toggle states)
      yield {
        type: "SET_TOPICS",
        payload: createDuplicateContentPayload(targetId, entityData.id, entityData.title, finalContentType),
      };

      // Return the duplicated entity data for immediate use (e.g., redirects)
      return response.data;
    } catch (error) {
      if (config.entityName === "live_lesson") {
        const normalized = normalizeLiveLessonError(error);
        const data = normalized.data;
        const settled =
          data?.persisted === true || data?.refetch_required === true || data?.projected_record !== undefined;
        const refreshWarning =
          settled && typeof courseId === "number" && courseId > 0
            ? yield* refreshTopicsAfterSettledMutation(courseId)
            : null;
        const message =
          normalized.message !== "" ? normalized.message : __(`Failed to duplicate ${config.entityName}`, "tutorpress");
        const preserved = preserveLiveLessonMutation({ ...normalized, message }, refreshFailure(refreshWarning));
        yield {
          type: `DUPLICATE_${config.entityName.toUpperCase()}_ERROR`,
          payload: {
            error: {
              code: CurriculumErrorCode.DUPLICATE_FAILED,
              message,
              ...(preserved.outcome.data ? { data: preserved.outcome.data } : {}),
              context: {
                action: `duplicate${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
                details: `Failed to duplicate ${config.entityName} ${sourceId}`,
              },
            },
            [config.sourceIdField]: sourceId,
          },
        };
        const rejection = new Error(message);
        Object.assign(rejection, preserved);
        throw rejection;
      }

      const errorPayload: any = {
        error: {
          code: CurriculumErrorCode.DUPLICATE_FAILED,
          message:
            error instanceof Error ? error.message : __(`Failed to duplicate ${config.entityName}`, "tutorpress"),
          context: {
            action: `duplicate${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
            details: `Failed to duplicate ${config.entityName} ${sourceId}`,
          },
        },
        [config.sourceIdField]: sourceId,
      };

      yield {
        type: `DUPLICATE_${config.entityName.toUpperCase()}_ERROR`,
        payload: errorPayload,
      };
    }
  };
}

/**
 * Creates a duplicate resolver for topics (legacy async pattern)
 */
export function createTopicDuplicateResolver(config: TopicDuplicateConfig) {
  return async function (sourceId: number, targetId: number) {
    try {
      // This would need access to the actions object, which is complex to inject
      // For now, we'll keep the topic duplication as-is since it uses a different pattern
      throw new Error("Topic duplication uses legacy pattern - not implemented in factory");
    } catch (error) {
      throw error;
    }
  };
}

/**
 * Response data extractors for different content types
 */
export const responseDataExtractors = {
  lesson: (response: { data: Lesson }) => ({
    id: response.data.id,
    title: response.data.title,
  }),

  quiz: (response: { data: any }) => ({
    id: response.data.id,
    title: response.data.title,
  }),

  liveLesson: (response: LiveLessonCreateResponse) => ({
    id: response.data.id,
    title: response.data.title,
  }),

  assignment: (response: { data: Assignment }) => ({
    id: response.data.id,
    title: response.data.title,
  }),
};

/**
 * Pre-configured duplicate resolvers for all content types
 */
export const duplicateResolvers = {
  lesson: createStandardDuplicateResolver({
    type: "standard",
    apiPath: "/tutorpress/v1/lessons",
    contentType: "lesson",
    entityName: "lesson",
    sourceIdField: "lessonId",
    targetIdField: "topic_id",
    responseDataExtractor: responseDataExtractors.lesson,
    requiresCourseId: false,
  }),

  quiz: createStandardDuplicateResolver({
    type: "standard",
    apiPath: "/tutorpress/v1/quizzes",
    contentType: "tutor_quiz",
    entityName: "quiz",
    sourceIdField: "quizId",
    targetIdField: "topic_id",
    responseDataExtractor: responseDataExtractors.quiz,
    requiresCourseId: true,
  }),

  liveLesson: createStandardDuplicateResolver({
    type: "standard",
    apiPath: "/tutorpress/v1/live-lessons",
    contentType: "meet_lesson", // Will be resolved by resolveContentType
    entityName: "live_lesson",
    sourceIdField: "liveLessonId",
    targetIdField: "topic_id",
    responseDataExtractor: (response: LiveLessonCreateResponse) => ({
      id: response.data.id,
      title: response.data.title,
    }),
    requiresCourseId: true,
  }),

  assignment: createStandardDuplicateResolver({
    type: "standard",
    apiPath: "/tutorpress/v1/assignments",
    contentType: "assignment",
    entityName: "assignment",
    sourceIdField: "assignmentId",
    targetIdField: "topic_id",
    responseDataExtractor: responseDataExtractors.assignment,
    requiresCourseId: true,
  }),

  // Topic duplication keeps legacy pattern for now
  // topic: Uses existing async duplicateTopic implementation
};
