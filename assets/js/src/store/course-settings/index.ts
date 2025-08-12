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
import type { CourseSettings, CourseAttachment, CourseInstructors, InstructorSearchResult } from "../../types/courses";
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
  // prerequisites list moved to dedicated store `tutorpress/prerequisites`
  attachments: {
    metadata: CourseAttachment[];
    isLoading: boolean;
    error: string | null;
  };
  // EDD list/detail logic moved to `tutorpress/commerce`
  instructors: {
    courseInstructors: CourseInstructors | null;
    searchResults: InstructorSearchResult[];
    isLoading: boolean;
    isSearching: boolean;
    error: string | null;
    searchError: string | null;
  };
}

const DEFAULT_STATE: State = {
  settings: null,
  fetchState: {
    isLoading: false,
    error: null,
  },
  // prerequisites list moved to dedicated store `tutorpress/prerequisites`
  attachments: {
    metadata: [],
    isLoading: false,
    error: null,
  },
  // EDD list/detail logic moved to `tutorpress/commerce`
  instructors: {
    courseInstructors: null,
    searchResults: [],
    isLoading: false,
    isSearching: false,
    error: null,
    searchError: null,
  },
};

type CourseSettingsAction =
  | { type: "SET_SETTINGS"; payload: CourseSettings }
  | { type: "SET_FETCH_STATE"; payload: Partial<State["fetchState"]> }
  // prerequisites list actions removed (moved to dedicated store)
  | { type: "SET_ATTACHMENTS_METADATA"; payload: CourseAttachment[] }
  | { type: "SET_ATTACHMENTS_STATE"; payload: Partial<State["attachments"]> }
  // EDD actions removed (migrated to tutorpress/commerce)
  | { type: "SET_COURSE_INSTRUCTORS"; payload: CourseInstructors }
  | { type: "SET_INSTRUCTOR_SEARCH_RESULTS"; payload: InstructorSearchResult[] }
  | { type: "SET_INSTRUCTORS_STATE"; payload: Partial<State["instructors"]> };

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

  // prerequisites list actions removed (moved to dedicated store)

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

  // EDD actions removed (migrated to tutorpress/commerce)

  setCourseInstructors(instructors: CourseInstructors) {
    return {
      type: "SET_COURSE_INSTRUCTORS" as const,
      payload: instructors,
    };
  },

  setInstructorSearchResults(results: InstructorSearchResult[]) {
    return {
      type: "SET_INSTRUCTOR_SEARCH_RESULTS" as const,
      payload: results,
    };
  },

  setInstructorsState(state: Partial<State["instructors"]>) {
    return {
      type: "SET_INSTRUCTORS_STATE" as const,
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

  // EDD resolvers removed (migrated to tutorpress/commerce)

  // prerequisites list fetch moved to dedicated store

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
      ...gutenbergSettings, // Gutenberg cache as fallback base
      ...state.settings, // Our store takes precedence over Gutenberg cache
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
  // prerequisites list selectors removed (moved to dedicated store)
  getAttachmentsMetadata(state: State) {
    return state.attachments.metadata;
  },
  getAttachmentsLoading(state: State) {
    return state.attachments.isLoading;
  },
  getAttachmentsError(state: State) {
    return state.attachments.error;
  },
  // EDD selectors removed (migrated to tutorpress/commerce)
  getInstructors(state: State) {
    return state.instructors.courseInstructors;
  },
  getInstructorSearchResults(state: State) {
    return state.instructors.searchResults;
  },
  getInstructorsLoading(state: State) {
    return state.instructors.isLoading;
  },
  getInstructorsSearching(state: State) {
    return state.instructors.isSearching;
  },
  getInstructorsError(state: State) {
    return state.instructors.error;
  },
  getInstructorSearchError(state: State) {
    return state.instructors.searchError;
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

      // Merge settings, prioritizing API response over Gutenberg cache
      const mergedSettings = {
        ...defaultCourseSettings,
        ...gutenbergSettings, // Start from any editor cache
        ...response.data, // Fresh API response takes precedence
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

  /**
   * Get course instructors (author + co-instructors)
   */
  *getCourseInstructors(): Generator<unknown, void, APIResponse<CourseInstructors>> {
    try {
      yield actions.setInstructorsState({ isLoading: true, error: null });

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "getCourseInstructors",
          "Failed to get course ID"
        );
      }

      // Get instructors through our API wrapper
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings/instructors`,
          method: "GET",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "API Error",
          CurriculumErrorCode.FETCH_FAILED,
          "getCourseInstructors",
          "Failed to fetch course instructors"
        );
      }

      yield actions.setCourseInstructors(response.data);
      yield actions.setInstructorsState({ isLoading: false, error: null });
    } catch (error: any) {
      yield actions.setInstructorsState({ isLoading: false, error: error.message });
      throw error;
    }
  },

  /**
   * Search for available instructors to add
   */
  *searchInstructors(search: string): Generator<unknown, void, APIResponse<InstructorSearchResult[]>> {
    try {
      yield actions.setInstructorsState({ isSearching: true, searchError: null });

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "searchInstructors",
          "Failed to get course ID"
        );
      }

      // Build query parameters
      const queryParams = new URLSearchParams();
      if (search.trim()) {
        queryParams.append("search", search.trim());
      }

      // Search instructors through our API wrapper
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings/instructors/search?${queryParams.toString()}`,
          method: "GET",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "API Error",
          CurriculumErrorCode.FETCH_FAILED,
          "searchInstructors",
          "Failed to search instructors"
        );
      }

      yield actions.setInstructorSearchResults(response.data);
      yield actions.setInstructorsState({ isSearching: false, searchError: null });
    } catch (error: any) {
      yield actions.setInstructorsState({ isSearching: false, searchError: error.message });
      throw error;
    }
  },

  /**
   * Update course author
   */
  *updateCourseAuthor(authorId: number): Generator<unknown, void, APIResponse<void>> {
    try {
      yield actions.setInstructorsState({ isLoading: true, error: null });

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "updateCourseAuthor",
          "Failed to get course ID"
        );
      }

      // Update author through our API wrapper
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings/author`,
          method: "POST",
          data: {
            author_id: authorId,
          },
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "API Error",
          CurriculumErrorCode.FETCH_FAILED,
          "updateCourseAuthor",
          "Failed to update course author"
        );
      }

      // Refresh instructor data after update
      yield resolvers.getCourseInstructors();

      // Reset loading state on success
      yield actions.setInstructorsState({ isLoading: false, error: null });
    } catch (error: any) {
      yield actions.setInstructorsState({ isLoading: false, error: error.message });
      throw error;
    }
  },

  /**
   * Update course instructors (co-instructors only, not author)
   */
  *updateCourseInstructors(instructorIds: number[]): Generator<unknown, void, APIResponse<void>> {
    try {
      yield actions.setInstructorsState({ isLoading: true, error: null });

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "updateCourseInstructors",
          "Failed to get course ID"
        );
      }

      // Update instructors through our API wrapper
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings/instructors`,
          method: "POST",
          data: {
            instructor_ids: instructorIds,
          },
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "API Error",
          CurriculumErrorCode.FETCH_FAILED,
          "updateCourseInstructors",
          "Failed to update course instructors"
        );
      }

      // Refresh instructor data after update
      yield resolvers.getCourseInstructors();
    } catch (error: any) {
      yield actions.setInstructorsState({ isLoading: false, error: error.message });
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
      // prerequisites list reducer cases removed (moved to dedicated store)
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
      // WooCommerce slice removed (migrated to tutorpress/commerce)
      // EDD slice removed (migrated to tutorpress/commerce)
      case "SET_COURSE_INSTRUCTORS":
        return {
          ...state,
          instructors: {
            ...state.instructors,
            courseInstructors: (action as { type: "SET_COURSE_INSTRUCTORS"; payload: CourseInstructors }).payload,
          },
        };
      case "SET_INSTRUCTOR_SEARCH_RESULTS":
        return {
          ...state,
          instructors: {
            ...state.instructors,
            searchResults: (action as { type: "SET_INSTRUCTOR_SEARCH_RESULTS"; payload: InstructorSearchResult[] })
              .payload,
          },
        };
      case "SET_INSTRUCTORS_STATE":
        return {
          ...state,
          instructors: {
            ...state.instructors,
            ...(action as { type: "SET_INSTRUCTORS_STATE"; payload: Partial<State["instructors"]> }).payload,
          },
        };
      default:
        return state;
    }
  },
  actions: {
    ...actions,
    ...resolvers,
  },
  selectors,
  controls,
});

register(store);

export default store;
