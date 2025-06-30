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
import { select } from "@wordpress/data";
import type { CourseSettings } from "../../types/courses";
import { defaultCourseSettings } from "../../types/courses";
import { createCurriculumError } from "../../utils/errors";
import { CurriculumErrorCode } from "../../types/curriculum";

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
  saveState: {
    isSaving: boolean;
    error: string | null;
  };
}

const DEFAULT_STATE: State = {
  settings: null,
  fetchState: {
    isLoading: false,
    error: null,
  },
  saveState: {
    isSaving: false,
    error: null,
  },
};

type CourseSettingsAction =
  | { type: "SET_SETTINGS"; payload: CourseSettings }
  | { type: "SET_FETCH_STATE"; payload: Partial<State["fetchState"]> }
  | { type: "SET_SAVE_STATE"; payload: Partial<State["saveState"]> };

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
  setSaveState(state: Partial<State["saveState"]>) {
    return {
      type: "SET_SAVE_STATE" as const,
      payload: state,
    };
  },
  *updateSettings(settings: Partial<CourseSettings>): Generator<unknown, void, APIResponse<CourseSettings>> {
    try {
      yield actions.setSaveState({ isSaving: true, error: null });

      const courseId = yield select("core/editor").getCurrentPostId();
      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "updateSettings",
          "Failed to get course ID"
        );
      }

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings`,
          method: "POST",
          data: settings,
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "API Error",
          CurriculumErrorCode.SAVE_FAILED,
          "updateSettings",
          "Failed to update settings"
        );
      }

      yield actions.setSettings(response.data);
      yield actions.setSaveState({ isSaving: false, error: null });
    } catch (error: any) {
      yield actions.setSaveState({ isSaving: false, error: error.message });
      throw error;
    }
  },
};

const selectors = {
  getSettings(state: State) {
    return state.settings || defaultCourseSettings;
  },
  getFetchState(state: State) {
    return state.fetchState;
  },
  getSaveState(state: State) {
    return state.saveState;
  },
  getIsLoading(state: State) {
    return state.fetchState.isLoading;
  },
  getIsSaving(state: State) {
    return state.saveState.isSaving;
  },
  getError(state: State) {
    return state.fetchState.error || state.saveState.error;
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

      yield actions.setSettings(response.data);
      yield actions.setFetchState({ isLoading: false, error: null });
    } catch (error: any) {
      yield actions.setFetchState({ isLoading: false, error: error.message });
      throw error;
    }
  },
};

const store = createReduxStore("tutorpress/course-settings", {
  reducer(state = DEFAULT_STATE, action: CourseSettingsAction) {
    switch (action.type) {
      case "SET_SETTINGS":
        return {
          ...state,
          settings: action.payload,
        };
      case "SET_FETCH_STATE":
        return {
          ...state,
          fetchState: {
            ...state.fetchState,
            ...action.payload,
          },
        };
      case "SET_SAVE_STATE":
        return {
          ...state,
          saveState: {
            ...state.saveState,
            ...action.payload,
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
