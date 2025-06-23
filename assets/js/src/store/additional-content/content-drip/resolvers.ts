/**
 * Content Drip Resolvers
 * Resolvers for fetching content drip data via WordPress Data Store
 */

import {
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
  ContentDripSettingsResponse,
  ContentDripSaveResponse,
  PrerequisitesResponse,
} from "../../../types/content-drip";

// Resolvers
export function* getContentDripSettings(postId: number) {
  try {
    // Set loading state
    yield setContentDripLoading(postId, true);
    yield setContentDripError(postId, null);

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
      yield setContentDripSettings(postId, response.data.settings);
    } else {
      yield setContentDripError(postId, "Failed to fetch content drip settings");
    }
  } catch (error) {
    yield setContentDripError(postId, error instanceof Error ? error.message : "Unknown error occurred");
  } finally {
    yield setContentDripLoading(postId, false);
  }
}

export function* updateContentDripSettings(postId: number, settings: any) {
  try {
    // Set saving state
    yield setContentDripSaving(postId, true);
    yield setContentDripSaveError(postId, null);

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
      yield setContentDripSettings(postId, response.data.settings);
    } else {
      yield setContentDripSaveError(postId, response.message || "Failed to save content drip settings");
    }

    return response;
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : "Unknown error occurred";
    yield setContentDripSaveError(postId, errorMessage);
    throw error;
  } finally {
    yield setContentDripSaving(postId, false);
  }
}

export function* getPrerequisites(courseId: number) {
  try {
    // Set loading state
    yield setPrerequisitesLoading(courseId, true);
    yield setPrerequisitesError(courseId, null);

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
      yield setPrerequisites(courseId, response.data.prerequisites);
    } else {
      yield setPrerequisitesError(courseId, "Failed to fetch prerequisites");
    }
  } catch (error) {
    yield setPrerequisitesError(courseId, error instanceof Error ? error.message : "Unknown error occurred");
  } finally {
    yield setPrerequisitesLoading(courseId, false);
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
    console.error("Failed to duplicate content drip settings:", error);
    throw error;
  }
}

export function* getCourseContentDripSettings(courseId: number) {
  try {
    // Fetch lightweight course content drip settings
    const response: any = yield {
      type: "API_FETCH",
      request: {
        path: `/tutorpress/v1/content-drip/course/${courseId}/settings`,
        method: "GET",
      },
    };

    if (response.success) {
      // Return the course content drip settings
      return response.data.content_drip;
    } else {
      throw new Error("Failed to fetch course content drip settings");
    }
  } catch (error) {
    console.error("Failed to fetch course content drip settings:", error);
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
