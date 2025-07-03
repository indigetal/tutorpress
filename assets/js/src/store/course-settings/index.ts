/**
 * Course Settings Store for TutorPress
 *
 * Handles course settings state management following TutorPress patterns.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { select, dispatch as wpDispatch } from "@wordpress/data";
import type { CourseSettings, PrerequisiteCourse, CourseAttachment } from "../../types/courses";
import { defaultCourseSettings } from "../../types/courses";
import { createCurriculumError } from "../../utils/errors";
import { CurriculumErrorCode } from "../../types/curriculum";

// Add type definitions for WordPress editor store
interface EditorStore {
  editPost: (edits: { course_settings?: CourseSettings }) => void;
}

interface APIResponse<T> {
  success: boolean;
  message?: string;
  data: T;
}

interface State {
  settings: CourseSettings | null;
  fetchState: {
    isLoading: boolean;
    error: string | null;
  };
  courseSelection: {
    availableCourses: PrerequisiteCourse[];
    isLoading: boolean;
    error: string | null;
  };
  attachments: {
    metadata: CourseAttachment[];
    isLoading: boolean;
    error: string | null;
  };
}

const DEFAULT_STATE: State = {
  settings: null,
  fetchState: {
    isLoading: false,
    error: null,
  },
  courseSelection: {
    availableCourses: [],
    isLoading: false,
    error: null,
  },
  attachments: {
    metadata: [],
    isLoading: false,
    error: null,
  },
};

type CourseSettingsAction =
  | { type: "SET_SETTINGS"; payload: CourseSettings }
  | { type: "SET_FETCH_STATE"; payload: Partial<State["fetchState"]> }
  | { type: "SET_AVAILABLE_COURSES"; payload: PrerequisiteCourse[] }
  | { type: "SET_COURSE_SELECTION_STATE"; payload: Partial<State["courseSelection"]> }
  | { type: "SET_ATTACHMENTS_METADATA"; payload: CourseAttachment[] }
  | { type: "SET_ATTACHMENTS_STATE"; payload: Partial<State["attachments"]> };

const actions = {
  setSettings(settings: CourseSettings) {
    return {
      type: "SET_SETTINGS" as const,
      payload: settings,
    };
  },

  setFetchState(state: Partial<State["fetchState"]>) {
    return {
      type: "SET_FETCH_STATE" as const,
      payload: state,
    };
  },

  setAvailableCourses(courses: PrerequisiteCourse[]) {
    return {
      type: "SET_AVAILABLE_COURSES" as const,
      payload: courses,
    };
  },

  setCourseSelectionState(state: Partial<State["courseSelection"]>) {
    return {
      type: "SET_COURSE_SELECTION_STATE" as const,
      payload: state,
    };
  },

  setAttachmentsMetadata(attachments: CourseAttachment[]) {
    return {
      type: "SET_ATTACHMENTS_METADATA" as const,
      payload: attachments,
    };
  },

  setAttachmentsState(state: Partial<State["attachments"]>) {
    return {
      type: "SET_ATTACHMENTS_STATE" as const,
      payload: state,
    };
  },

  // Fetch attachment metadata for display
  *fetchAttachmentsMetadata(attachmentIds: number[]): Generator {
    try {
      yield actions.setAttachmentsState({ isLoading: true, error: null });

      if (attachmentIds.length === 0) {
        yield actions.setAttachmentsMetadata([]);
        yield actions.setAttachmentsState({ isLoading: false, error: null });
        return;
      }

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "fetchAttachmentsMetadata",
          "Failed to get course ID"
        );
      }

      // Get attachment metadata through our API wrapper
      const queryParams = new URLSearchParams();
      attachmentIds.forEach((id) => queryParams.append("attachment_ids[]", id.toString()));

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/attachments?${queryParams.toString()}`,
          method: "GET",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "API Error",
          CurriculumErrorCode.FETCH_FAILED,
          "fetchAttachmentsMetadata",
          "Failed to fetch attachment metadata"
        );
      }

      yield actions.setAttachmentsMetadata(response.data);
      yield actions.setAttachmentsState({ isLoading: false, error: null });
    } catch (error: any) {
      yield actions.setAttachmentsState({ isLoading: false, error: error.message });
      throw error;
    }
  },

  // Fetch available courses for prerequisites dropdown
  *fetchAvailableCourses(): Generator {
    try {
      yield actions.setCourseSelectionState({ isLoading: true, error: null });

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "fetchAvailableCourses",
          "Failed to get course ID"
        );
      }

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/prerequisites?exclude=${courseId}&per_page=50`,
          method: "GET",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to fetch courses",
          CurriculumErrorCode.FETCH_FAILED,
          "fetchAvailableCourses",
          "API Error"
        );
      }

      yield actions.setAvailableCourses(response.data);
      yield actions.setCourseSelectionState({ isLoading: false, error: null });
    } catch (error: any) {
      yield actions.setCourseSelectionState({ isLoading: false, error: error.message });
      throw error;
    }
  },

  // Update settings in Gutenberg store and our local state
  *updateSettings(settings: Partial<CourseSettings>): Generator {
    try {
      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "updateSettings",
          "Failed to get course ID"
        );
      }

      // Get current settings from both stores
      const currentSettings = yield select("tutorpress/course-settings").getSettings();
      const currentGutenbergSettings = yield select("core/editor").getEditedPostAttribute("course_settings") || {};

      // Merge settings, prioritizing new settings
      let mergedSettings = {
        ...defaultCourseSettings,
        ...currentGutenbergSettings,
        ...currentSettings,
        ...settings,
      };

      // Apply validation and auto-correction for date/time fields
      mergedSettings = actions.validateAndCorrectSettings(mergedSettings);

      // Update Gutenberg store
      const editorDispatch = wpDispatch("core/editor") as EditorStore;
      if (editorDispatch) {
        editorDispatch.editPost({
          course_settings: mergedSettings,
        });
      }

      // Update our local store state
      yield actions.setSettings(mergedSettings);
    } catch (error: any) {
      yield actions.setFetchState({ error: error.message });
      throw error;
    }
  },

  // Validate and auto-correct course settings
  validateAndCorrectSettings(settings: CourseSettings): CourseSettings {
    const correctedSettings = { ...settings };

    // Validate enrollment period dates
    if (
      correctedSettings.course_enrollment_period === "yes" &&
      correctedSettings.enrollment_starts_at &&
      correctedSettings.enrollment_ends_at
    ) {
      const startDate = new Date(correctedSettings.enrollment_starts_at.replace(" ", "T") + "Z");
      const endDate = new Date(correctedSettings.enrollment_ends_at.replace(" ", "T") + "Z");

      // If end date is before start date, auto-correct to 30 minutes after start
      if (endDate <= startDate) {
        const correctedEnd = new Date(startDate);
        correctedEnd.setMinutes(correctedEnd.getMinutes() + 30);
        correctedSettings.enrollment_ends_at = correctedEnd.toISOString().replace("T", " ").slice(0, 19);
      }
    }

    return correctedSettings;
  },
};

const selectors = {
  getSettings(state: State) {
    // Get settings from both stores and merge
    const gutenbergSettings = select("core/editor").getEditedPostAttribute("course_settings") || {};
    return {
      ...defaultCourseSettings,
      ...gutenbergSettings,
      ...state.settings,
    };
  },
  getFetchState(state: State) {
    return state.fetchState;
  },
  getIsLoading(state: State) {
    return state.fetchState.isLoading;
  },
  getError(state: State) {
    return state.fetchState.error;
  },
  getAvailableCourses(state: State) {
    return state.courseSelection.availableCourses;
  },
  getCourseSelectionLoading(state: State) {
    return state.courseSelection.isLoading;
  },
  getCourseSelectionError(state: State) {
    return state.courseSelection.error;
  },
  getAttachmentsMetadata(state: State) {
    return state.attachments.metadata;
  },
  getAttachmentsLoading(state: State) {
    return state.attachments.isLoading;
  },
  getAttachmentsError(state: State) {
    return state.attachments.error;
  },
};

const resolvers = {
  *getSettings(): Generator<unknown, void, APIResponse<CourseSettings>> {
    try {
      yield actions.setFetchState({ isLoading: true, error: null });

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "getSettings",
          "Failed to get course ID"
        );
      }

      // Get settings through our API wrapper
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings`,
          method: "GET",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "API Error",
          CurriculumErrorCode.FETCH_FAILED,
          "getSettings",
          "Failed to fetch settings"
        );
      }

      // Get current Gutenberg settings
      const gutenbergSettings = yield select("core/editor").getEditedPostAttribute("course_settings") || {};

      // Merge settings, prioritizing API response
      const mergedSettings = {
        ...defaultCourseSettings,
        ...gutenbergSettings,
        ...response.data,
      };

      // Update both stores
      const editorDispatch = wpDispatch("core/editor") as EditorStore;
      if (editorDispatch) {
        editorDispatch.editPost({
          course_settings: mergedSettings,
        });
      }

      yield actions.setSettings(mergedSettings);
      yield actions.setFetchState({ isLoading: false, error: null });
    } catch (error: any) {
      yield actions.setFetchState({ isLoading: false, error: error.message });
      throw error;
    }
  },
};

const store = createReduxStore("tutorpress/course-settings", {
  reducer(state = DEFAULT_STATE, action: CourseSettingsAction | { type: string }) {
    switch (action.type) {
      case "SET_SETTINGS":
        return {
          ...state,
          settings: (action as { type: "SET_SETTINGS"; payload: CourseSettings }).payload,
        };
      case "SET_FETCH_STATE":
        return {
          ...state,
          fetchState: {
            ...state.fetchState,
            ...(action as { type: "SET_FETCH_STATE"; payload: Partial<State["fetchState"]> }).payload,
          },
        };
      case "SET_AVAILABLE_COURSES":
        return {
          ...state,
          courseSelection: {
            ...state.courseSelection,
            availableCourses: (action as { type: "SET_AVAILABLE_COURSES"; payload: PrerequisiteCourse[] }).payload,
          },
        };
      case "SET_COURSE_SELECTION_STATE":
        return {
          ...state,
          courseSelection: {
            ...state.courseSelection,
            ...(action as { type: "SET_COURSE_SELECTION_STATE"; payload: Partial<State["courseSelection"]> }).payload,
          },
        };
      case "SET_ATTACHMENTS_METADATA":
        return {
          ...state,
          attachments: {
            ...state.attachments,
            metadata: (action as { type: "SET_ATTACHMENTS_METADATA"; payload: CourseAttachment[] }).payload,
          },
        };
      case "SET_ATTACHMENTS_STATE":
        return {
          ...state,
          attachments: {
            ...state.attachments,
            ...(action as { type: "SET_ATTACHMENTS_STATE"; payload: Partial<State["attachments"]> }).payload,
          },
        };
      default:
        return state;
    }
  },
  actions,
  selectors,
  resolvers,
  controls,
});

register(store);

export default store;
