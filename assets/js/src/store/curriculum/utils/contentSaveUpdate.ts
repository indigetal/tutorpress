import { __ } from "@wordpress/i18n";
import { CurriculumErrorCode, ContentItem } from "../../../types/curriculum";
import { LiveLesson, LiveLessonFormData, LiveLessonApiResponse } from "../../../types/liveLessons";
import { createSaveContentPayload, createUpdateContentPayload, resolveContentType } from "./topicsUpdate";

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

/**
 * Creates a save resolver for standard content types (live lessons)
 */
export function createStandardSaveResolver(config: StandardSaveConfig) {
  return function* (data: any, courseId: number, topicId: number): Generator<unknown, void, unknown> {
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

      // Update topics directly to add the new content (preserves toggle states)
      yield {
        type: "SET_TOPICS",
        payload: config.topicUpdatePayloadCreator(topicId, entityData),
      };
    } catch (error) {
      yield {
        type: `SAVE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.SAVE_FAILED,
            message: error instanceof Error ? error.message : __(`Failed to save ${config.entityName}`, "tutorpress"),
            context: {
              action: `save${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to save ${config.entityName} for topic ${topicId}`,
            },
          },
        },
      };
    }
  };
}

/**
 * Creates an update resolver for standard content types (live lessons)
 */
export function createStandardUpdateResolver(config: StandardUpdateConfig) {
  return function* (entityId: number, data: any): Generator<unknown, void, unknown> {
    try {
      yield {
        type: `UPDATE_${config.entityName.toUpperCase()}_START`,
        payload: { [config.idField]: entityId, data },
      };

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `${config.apiPath}/${entityId}`,
          method: "PATCH",
          data,
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

      // Update topics directly to reflect the updated content (preserves toggle states)
      yield {
        type: "SET_TOPICS",
        payload: config.topicUpdatePayloadCreator(entityId, entityData),
      };
    } catch (error) {
      yield {
        type: `UPDATE_${config.entityName.toUpperCase()}_ERROR`,
        payload: {
          error: {
            code: CurriculumErrorCode.UPDATE_FAILED,
            message: error instanceof Error ? error.message : __(`Failed to update ${config.entityName}`, "tutorpress"),
            context: {
              action: `update${config.entityName.charAt(0).toUpperCase() + config.entityName.slice(1)}`,
              details: `Failed to update ${config.entityName} ${entityId}`,
            },
          },
        },
      };
    }
  };
}

/**
 * Data transformers for different content types
 */
export const dataTransformers = {
  liveLesson: (liveLessonData: LiveLessonFormData, courseId: number, topicId: number) => ({
    title: liveLessonData.title,
    description: liveLessonData.description,
    type: liveLessonData.type,
    start_date_time: liveLessonData.startDateTime,
    end_date_time: liveLessonData.endDateTime,
    settings: {
      timezone: liveLessonData.settings.timezone,
      duration: liveLessonData.settings.duration,
      allow_early_join: liveLessonData.settings.allowEarlyJoin,
      auto_record: liveLessonData.settings.autoRecord,
      require_password: liveLessonData.settings.requirePassword,
      waiting_room: liveLessonData.settings.waitingRoom,
      add_enrolled_students: liveLessonData.settings.add_enrolled_students,
    },
    provider_config: liveLessonData.providerConfig || {},
    topic_id: topicId,
    course_id: courseId,
  }),
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
    responseDataExtractor: saveUpdateResponseDataExtractors.liveLesson,
    successPayloadCreator: (liveLesson: LiveLesson) => successPayloadCreators.liveLesson.update(liveLesson),
    topicUpdatePayloadCreator: (liveLessonId: number, liveLesson: LiveLesson) =>
      topicUpdatePayloadCreators.liveLesson.update(liveLessonId, liveLesson),
  }),

  // Quiz save keeps legacy pattern for now due to complex Tutor LMS AJAX integration
  // saveQuiz: Uses existing complex implementation with FormData and Tutor LMS AJAX
};
