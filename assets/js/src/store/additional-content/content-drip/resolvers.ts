/**
 * Content Drip Resolvers
 * Resolvers for fetching content drip data via WordPress Data Store
 */

import {
  CONTENT_DRIP_ACTION_TYPES,
  setContentDripSettings,
  setContentDripLoading,
  setContentDripError,
  setContentDripSaving,
  setContentDripSaveError,
  setPrerequisites,
  setPrerequisitesLoading,
  setPrerequisitesError,
} from "./actions";

import type {
  ContentDripItemSettings,
  ContentDripSettingsResponse,
  ContentDripSaveResponse,
  PrerequisitesResponse,
} from "../../../types/content-drip";

// Resolvers
export function* getContentDripSettings(postId: number) {
  try {
    // Set loading state
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_LOADING,
      payload: { postId, loading: true },
    };
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ERROR,
      payload: { postId, error: null },
    };

    // Fetch content drip settings
    const response: ContentDripSettingsResponse = yield {
      type: "API_FETCH",
      request: {
        path: `/tutorpress/v1/content-drip/${postId}`,
        method: "GET",
      },
    };

    if (response.success) {
      // Set the settings in store
      yield {
        type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ITEM_SETTINGS,
        payload: { postId, settings: response.data.settings },
      };
    } else {
      yield {
        type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ERROR,
        payload: { postId, error: "Failed to fetch content drip settings" },
      };
    }
  } catch (error) {
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ERROR,
      payload: { postId, error: error instanceof Error ? error.message : "Unknown error occurred" },
    };
  } finally {
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_LOADING,
      payload: { postId, loading: false },
    };
  }
}

export function* updateContentDripSettings(postId: number, settings: ContentDripItemSettings) {
  try {
    // Set saving state
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVING,
      payload: { postId, saving: true },
    };
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVE_ERROR,
      payload: { postId, error: null },
    };

    // Save content drip settings
    const response: ContentDripSaveResponse = yield {
      type: "API_FETCH",
      request: {
        path: `/tutorpress/v1/content-drip/${postId}`,
        method: "POST",
        data: { settings },
      },
    };

    if (response.success) {
      // Update settings in store
      yield {
        type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ITEM_SETTINGS,
        payload: { postId, settings: response.data.settings },
      };
    } else {
      yield {
        type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVE_ERROR,
        payload: { postId, error: response.message || "Failed to save content drip settings" },
      };
    }

    return response;
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : "Unknown error occurred";
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVE_ERROR,
      payload: { postId, error: errorMessage },
    };
    throw error;
  } finally {
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVING,
      payload: { postId, saving: false },
    };
  }
}

export function* getPrerequisites(courseId: number) {
  try {
    // Set loading state
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_LOADING,
      payload: { courseId, loading: true },
    };
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_ERROR,
      payload: { courseId, error: null },
    };

    // Fetch prerequisites
    const response: PrerequisitesResponse = yield {
      type: "API_FETCH",
      request: {
        path: `/tutorpress/v1/content-drip/${courseId}/prerequisites`,
        method: "GET",
      },
    };

    if (response.success) {
      // Store prerequisites as array (the API already groups them by topic)
      yield {
        type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES,
        payload: { courseId, prerequisites: response.data.prerequisites },
      };
    } else {
      yield {
        type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_ERROR,
        payload: { courseId, error: "Failed to fetch prerequisites" },
      };
    }
  } catch (error) {
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_ERROR,
      payload: { courseId, error: error instanceof Error ? error.message : "Unknown error occurred" },
    };
  } finally {
    yield {
      type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_LOADING,
      payload: { courseId, loading: false },
    };
  }
}

export function* duplicateContentDripSettings(sourcePostId: number, targetPostId: number) {
  try {
    // Fetch source settings directly
    const response: ContentDripSettingsResponse = yield {
      type: "API_FETCH",
      request: {
        path: `/tutorpress/v1/content-drip/${sourcePostId}`,
        method: "GET",
      },
    };

    if (response.success) {
      // Apply to target
      yield* updateContentDripSettings(targetPostId, response.data.settings);
    }
  } catch (error) {
    throw error;
  }
}

export function* getCourseContentDripSettings(
  courseId: number
): Generator<unknown, { enabled: boolean; type: string }, unknown> {
  try {
    // Fetch lightweight course content drip settings
    const response = (yield {
      type: "API_FETCH",
      request: {
        path: `/tutorpress/v1/content-drip/course/${courseId}/settings`,
        method: "GET",
      },
    }) as { success: boolean; data: { content_drip: { enabled: boolean; type: string } } };

    if (response.success) {
      // Return the course content drip settings
      return response.data.content_drip;
    } else {
      throw new Error("Failed to fetch course content drip settings");
    }
  } catch (error) {
    throw error;
  }
}

// Resolver configuration for WordPress Data Store
export const contentDripResolvers = {
  getContentDripSettings,
  updateContentDripSettings,
  getPrerequisites,
  duplicateContentDripSettings,
  getCourseContentDripSettings,
};
