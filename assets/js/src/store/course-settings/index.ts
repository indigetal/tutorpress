import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { select } from "@wordpress/data";
import type { CourseSettings } from "../../types/courses";
import { AnyAction } from "redux";

// Action Types
export const TYPES = {
  GET_COURSE_SETTINGS: "GET_COURSE_SETTINGS",
  SET_COURSE_SETTINGS: "SET_COURSE_SETTINGS",
  UPDATE_COURSE_SETTINGS: "UPDATE_COURSE_SETTINGS",
} as const;

interface State {
  settings: CourseSettings;
}

// Actions
export const actions = {
  getCourseSettings() {
    return {
      type: TYPES.GET_COURSE_SETTINGS,
    };
  },
  setCourseSettings(settings: CourseSettings) {
    return {
      type: TYPES.SET_COURSE_SETTINGS,
      settings,
    };
  },
  updateCourseSettings(settings: Partial<CourseSettings>) {
    return {
      type: TYPES.UPDATE_COURSE_SETTINGS,
      settings,
    };
  },
};

// Resolvers
export const resolvers = {
  *getCourseSettings(): Generator<any, any, any> {
    try {
      // Get course ID directly from editor store
      const courseId = yield select("core/editor").getCurrentPostId();

      if (!courseId) {
        throw new Error("No course ID available");
      }

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings`,
          method: "GET",
        },
      };

      if (response.success) {
        return actions.setCourseSettings(response.data);
      }

      throw new Error(response.message || "Failed to fetch course settings");
    } catch (error) {
      console.error("Error fetching course settings:", error);
      throw error;
    }
  },
};

// Selectors
export const selectors = {
  getCourseSettings(state: State) {
    return state.settings;
  },
};

// Initial state
const DEFAULT_STATE: State = {
  settings: {
    course_level: "all_levels",
    is_public_course: false,
    enable_qna: true,
    course_duration: {
      hours: 0,
      minutes: 0,
    },
    course_prerequisites: [],
    maximum_students: 0,
    course_enrollment_period: "no",
    enrollment_starts_at: "",
    enrollment_ends_at: "",
    pause_enrollment: "no",
    featured_video: {
      source: "",
      source_youtube: "",
      source_vimeo: "",
      source_external_url: "",
      source_embedded: "",
    },
    attachments: [],
    materials_included: "",
    is_free: true,
    pricing_model: "",
    price: 0,
    sale_price: 0,
    subscription_enabled: false,
    instructors: [],
    additional_instructors: [],
  },
};

// Store configuration
const store = createReduxStore("tutorpress/course-settings", {
  reducer(state: State = DEFAULT_STATE, action: AnyAction) {
    switch (action.type) {
      case TYPES.SET_COURSE_SETTINGS:
        return {
          ...state,
          settings: action.settings,
        };
      case TYPES.UPDATE_COURSE_SETTINGS:
        return {
          ...state,
          settings: {
            ...state.settings,
            ...action.settings,
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
