import { __ } from "@wordpress/i18n";
import { CurriculumErrorCode, ContentItem } from "../../../types/curriculum";
import { LiveLesson, LiveLessonFormData, LiveLessonApiResponse } from "../../../types/liveLessons";
import { createSaveContentPayload, createUpdateContentPayload, resolveContentType } from "./topicsUpdate";
import { zoomDuration } from "../../../utils/liveLessonDateTime";
import { normalizeLiveLessonError, preserveLiveLessonMutation } from "../../../utils/liveLessonErrors";
import { refreshTopicsAfterSettledMutation } from "./topicRefresh";

/**
 * Configuration for standard save operations (live lessons, future content types)
 */
export interface StandardSaveConfig {
  type: "standard";
  apiPath: string;
  contentType: ContentItem["type"];
  entityName: string;
  dataTransformer: (data: any, courseId: number, topicId: number) => any;
  responseDataExtractor: (response: any) => { id: number; title: string; [key: string]: any };
  successPayloadCreator: (entity: any, courseId: number) => any;
  topicUpdatePayloadCreator: (topicId: number, entity: any) => any;
}

/**
 * Configuration for standard update operations (live lessons, future content types)
 */
export interface StandardUpdateConfig {
  type: "update";
  apiPath: string;
  entityName: string;
  idField: string;
  responseDataExtractor: (response: any) => { id: number; title: string; [key: string]: any };
  successPayloadCreator: (entity: any) => any;
  topicUpdatePayloadCreator: (entityId: number, entity: any) => any;
  dataTransformer?: (data: any) => any;
}

/**
 * Configuration for legacy save operations (quizzes using Tutor LMS AJAX)
 */
export interface LegacySaveConfig {
  type: "legacy";
  entityName: string;
  // Legacy operations keep their existing implementation for now
}

export type SaveUpdateConfig = StandardSaveConfig | StandardUpdateConfig | LegacySaveConfig;

type SaveResolverResult = {
  outcome: ReturnType<StandardSaveConfig["responseDataExtractor"]>;
  refreshWarning: string | null;
};

const refreshFailure = (warning: string | null): { message: string } | undefined =>
  warning === null ? undefined : { message: warning };

/**
 * Creates a save resolver for standard content types (live lessons)
 */
export function createStandardSaveResolver(config: StandardSaveConfig) {
  return function* (data: any, courseId: number, topicId: number): Generator<unknown, SaveResolverResult, unknown> {
    try {
      yield {
        type: `SAVE_${config.entityName.toUpperCase()}_START`,
        payload: {
          [`${config.entityName}Data`]: data,
          courseId,
          topicId,
        },
      };

      // Transform data for API call
      const transformedData = config.dataTransformer(data, courseId, topicId);

      // Make API call
      const response = yield {
        type: "API_FETCH",
        request: {
          path: config.apiPath,
          method: "POST",
          data: transformedData,
        },
      };

      if (!response || typeof response !== "object" || !("data" in response)) {
        throw new Error(`Invalid ${config.entityName} save response`);
      }

      const entityData = config.responseDataExtractor(response);

      yield {
        type: `SAVE_${config.entityName.toUpperCase()}_SUCCESS`,
        payload: config.successPayloadCreator(entityData, courseId),
      };

      const refreshWarning = yield* refreshTopicsAfterSettledMutation(courseId);
      return preserveLiveLessonMutation(entityData, refreshFailure(refreshWarning));
    } catch (error) {
      const normalized = normalizeLiveLessonError(error);
      const data = normalized.data;
      const settled =
        data?.persisted === true || data?.refetch_required === true || data?.projected_record !== undefined;
      const refreshWarning = settled ? yield* refreshTopicsAfterSettledMutation(courseId) : null;
      const message =
        normalized.message !== "" ? normalized.message : __(`Failed to save ${config.entityName}`, "tutorpress");
      const preserved = preserveLiveLessonMutation({ ...normalized, message }, refreshFailure(refreshWarning));
      yield {
        type: `SAVE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.SAVE_FAILED,
            message,
            ...(preserved.outcome.data ? { data: preserved.outcome.data } : {}),
            context: {
              action: `save${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to save ${config.entityName} for topic ${topicId}`,
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
 * Creates an update resolver for standard content types (live lessons)
 */
export function createStandardUpdateResolver(config: StandardUpdateConfig) {
  return function* (entityId: number, data: any): Generator<unknown, SaveResolverResult, unknown> {
    try {
      yield {
        type: `UPDATE_${config.entityName.toUpperCase()}_START`,
        payload: { [config.idField]: entityId, data },
      };

      const requestData = config.dataTransformer ? config.dataTransformer(data) : data;
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${entityId}`,
          method: "PATCH",
          data: requestData,
        },
      };

      if (!response || typeof response !== "object" || !("data" in response)) {
        throw new Error(`Invalid ${config.entityName} update response`);
      }

      const entityData = config.responseDataExtractor(response);

      yield {
        type: `UPDATE_${config.entityName.toUpperCase()}_SUCCESS`,
        payload: config.successPayloadCreator(entityData),
      };

      const courseId = entityData.courseId;
      const refreshWarning =
        typeof courseId === "number" && courseId > 0 ? yield* refreshTopicsAfterSettledMutation(courseId) : null;
      return preserveLiveLessonMutation(entityData, refreshFailure(refreshWarning));
    } catch (error) {
      const normalized = normalizeLiveLessonError(error);
      const errorData = normalized.data;
      const courseId = errorData?.course_id;
      const settled =
        errorData?.persisted === true ||
        errorData?.refetch_required === true ||
        errorData?.projected_record !== undefined;
      const refreshWarning =
        settled && typeof courseId === "number" && courseId > 0
          ? yield* refreshTopicsAfterSettledMutation(courseId)
          : null;
      const message =
        normalized.message !== "" ? normalized.message : __(`Failed to update ${config.entityName}`, "tutorpress");
      const preserved = preserveLiveLessonMutation({ ...normalized, message }, refreshFailure(refreshWarning));
      yield {
        type: `UPDATE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.UPDATE_FAILED,
            message,
            ...(preserved.outcome.data ? { data: preserved.outcome.data } : {}),
            context: {
              action: `update${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to update ${config.entityName} ${entityId}`,
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
 * Data transformers for different content types
 */
const providerIdentityKeys = ["provider_id", "event_id", "meeting_id", "eventId", "meetingId"];

function liveLessonProviderConfig(config: LiveLessonFormData["providerConfig"]) {
  const next: Record<string, unknown> = {};
  Object.entries(config ?? {}).forEach(([key, value]) => {
    if (!providerIdentityKeys.includes(key)) {
      next[key] = value;
    }
  });
  return next;
}

function liveLessonMutationBody(data: LiveLessonFormData) {
  const settings = data.settings;
  const meetingSettings = {
    allow_early_join: settings.allowEarlyJoin,
    auto_record: settings.autoRecord,
    require_password: settings.requirePassword,
    waiting_room: settings.waitingRoom,
    add_enrolled_students: settings.add_enrolled_students,
  };
  const body: Record<string, unknown> = {
    title: data.title,
    description: data.description,
    type: data.type,
    start_date_time: data.startDateTime,
    end_date_time: data.endDateTime,
    provider_config: liveLessonProviderConfig(data.providerConfig),
  };
  if (window.tutorPressCurriculum?.liveLessonSyncEnabled !== true) {
    body.settings = {
      timezone: settings.timezone,
      duration: settings.duration,
      ...meetingSettings,
    };
    return body;
  }
  const duration =
    data.type === "zoom" && typeof settings.duration === "number" ? zoomDuration(settings.duration, "minutes") : null;
  if (duration) {
    body.duration = duration;
  }
  body.timezone = settings.timezone;
  body.settings = {
    timezone: settings.timezone,
    ...meetingSettings,
  };
  return body;
}

export function liveLessonCreateBody(data: LiveLessonFormData, courseId: number, topicId: number): Record<string, unknown> {
  return {
    ...liveLessonMutationBody(data),
    topic_id: topicId,
    course_id: courseId,
  };
}

export function liveLessonPatchBody(data: LiveLessonFormData) {
  return liveLessonMutationBody(data);
}

export const dataTransformers = {
  liveLesson: liveLessonCreateBody,
};

/**
 * Response data extractors for save/update operations
 */
export const saveUpdateResponseDataExtractors = {
  liveLesson: (response: LiveLessonApiResponse) => {
    const liveLesson = response.data as LiveLesson;
    return {
      ...liveLesson,
      id: liveLesson.id,
      title: liveLesson.title,
      type: liveLesson.type,
      courseId: liveLesson.courseId,
    };
  },
};

/**
 * Success payload creators for different content types
 */
export const successPayloadCreators = {
  liveLesson: {
    save: (liveLesson: LiveLesson, courseId: number) => ({ liveLesson, courseId }),
    update: (liveLesson: LiveLesson) => ({ liveLesson, courseId: liveLesson.courseId }),
  },
};

/**
 * Topic update payload creators for different content types
 */
export const topicUpdatePayloadCreators = {
  liveLesson: {
    save: (topicId: number, liveLesson: LiveLesson) =>
      createSaveContentPayload(topicId, liveLesson.id, liveLesson.title, resolveContentType(liveLesson.type)),
    update: (liveLessonId: number, liveLesson: LiveLesson) =>
      createUpdateContentPayload(liveLessonId, { title: liveLesson.title }),
  },
};

/**
 * Pre-configured save/update resolvers for all content types
 */
export const saveUpdateResolvers = {
  saveLiveLesson: createStandardSaveResolver({
    type: "standard",
    apiPath: "/tutorpress/v1/live-lessons",
    contentType: "meet_lesson", // Will be resolved by resolveContentType
    entityName: "live_lesson",
    dataTransformer: dataTransformers.liveLesson,
    responseDataExtractor: saveUpdateResponseDataExtractors.liveLesson,
    successPayloadCreator: (liveLesson: LiveLesson, courseId: number) =>
      successPayloadCreators.liveLesson.save(liveLesson, courseId),
    topicUpdatePayloadCreator: (topicId: number, liveLesson: LiveLesson) =>
      topicUpdatePayloadCreators.liveLesson.save(topicId, liveLesson),
  }),

  updateLiveLesson: createStandardUpdateResolver({
    type: "update",
    apiPath: "/tutorpress/v1/live-lessons",
    entityName: "live_lesson",
    idField: "liveLessonId",
    dataTransformer: liveLessonPatchBody,
    responseDataExtractor: saveUpdateResponseDataExtractors.liveLesson,
    successPayloadCreator: (liveLesson: LiveLesson) => successPayloadCreators.liveLesson.update(liveLesson),
    topicUpdatePayloadCreator: (liveLessonId: number, liveLesson: LiveLesson) =>
      topicUpdatePayloadCreators.liveLesson.update(liveLessonId, liveLesson),
  }),

  // Quiz save keeps legacy pattern for now due to complex Tutor LMS AJAX integration
  // saveQuiz: Uses existing complex implementation with FormData and Tutor LMS AJAX
};
