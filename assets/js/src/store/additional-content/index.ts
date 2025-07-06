/**
 * Additional Content Store for TutorPress
 *
 * Dedicated store for additional course content operations, following the Certificate store pattern
 * for better organization and maintainability. Handles What Will I Learn, Target Audience,
 * Requirements, and Content Drip settings.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { __ } from "@wordpress/i18n";
import {
  AdditionalContentData,
  ContentDripSettings,
  AdditionalContentState,
  AdditionalContentResponse,
  AdditionalContentSaveResponse,
  AdditionalContentError,
} from "../../types/additional-content";
import type { PrerequisitesByTopic, ContentDripItemSettings } from "../../types/content-drip";
import { isContentDripEnabled } from "../../utils/addonChecker";

// Import modular content drip functionality
import {
  contentDripResolvers,
  getContentDripSettings,
  isContentDripLoading,
  getContentDripError,
  isContentDripSaving,
  getContentDripSaveError,
  getPrerequisites,
  isPrerequisitesLoading,
  getPrerequisitesError,
  hasContentDripSettings,
  isContentDripEnabled as isContentDripEnabledForPost,
  getContentDripType as getContentDripTypeForPost,
  hasPrerequisites,
  getContentDripInfo,
  getPrerequisitesInfo,
} from "./content-drip";
import { CONTENT_DRIP_ACTION_TYPES, type ContentDripAction } from "./content-drip/actions";

// ============================================================================
// STORE STATE INTERFACE
// ============================================================================

/**
 * Additional Content Store State Interface
 */
interface StoreState {
  /** Course additional content data */
  data: AdditionalContentData;
  /** Content drip settings */
  contentDrip: ContentDripSettings;
  /** Current course ID */
  courseId: number | null;
  /** Loading states */
  isLoading: boolean;
  isSaving: boolean;
  /** Dirty state tracking */
  isDirty: boolean;
  /** Error handling */
  error: string | null;
  /** Last saved timestamp */
  lastSaved: number | null;

  /** Content Drip Module State */
  contentDripItems: {
    [postId: number]: {
      settings: ContentDripItemSettings;
      loading: boolean;
      error: string | null;
      saving: boolean;
      saveError: string | null;
    };
  };
  prerequisites: {
    [courseId: number]: {
      data: PrerequisitesByTopic[];
      loading: boolean;
      error: string | null;
    };
  };
}

// ============================================================================
// INITIAL STATE
// ============================================================================

const DEFAULT_STATE: StoreState = {
  data: {
    what_will_learn: "",
    target_audience: "",
    requirements: "",
  },
  contentDrip: {
    enabled: false,
    type: "unlock_by_date",
  },
  courseId: null,
  isLoading: false,
  isSaving: false,
  isDirty: false,
  error: null,
  lastSaved: null,
  contentDripItems: {},
  prerequisites: {},
};

// ============================================================================
// ACTION TYPES
// ============================================================================

export type AdditionalContentAction =
  // Data Loading Actions
  | { type: "FETCH_ADDITIONAL_CONTENT"; payload: { courseId: number } }
  | { type: "FETCH_ADDITIONAL_CONTENT_START"; payload: { courseId: number } }
  | {
      type: "FETCH_ADDITIONAL_CONTENT_SUCCESS";
      payload: { data: AdditionalContentData; contentDrip: ContentDripSettings };
    }
  | { type: "FETCH_ADDITIONAL_CONTENT_ERROR"; payload: { error: string } }
  // Data Saving Actions
  | {
      type: "SAVE_ADDITIONAL_CONTENT";
      payload: { courseId: number; data: AdditionalContentData; contentDrip: ContentDripSettings };
    }
  | { type: "SAVE_ADDITIONAL_CONTENT_START" }
  | { type: "SAVE_ADDITIONAL_CONTENT_SUCCESS"; payload: { timestamp: number } }
  | { type: "SAVE_ADDITIONAL_CONTENT_ERROR"; payload: { error: string } }
  // State Management Actions
  | { type: "SET_COURSE_ID"; payload: { courseId: number | null } }
  | { type: "SET_ADDITIONAL_CONTENT_DATA"; payload: { data: AdditionalContentData } }
  | { type: "SET_CONTENT_DRIP_SETTINGS"; payload: { contentDrip: ContentDripSettings } }
  | { type: "UPDATE_FIELD"; payload: { field: keyof AdditionalContentData; value: string } }
  | { type: "UPDATE_CONTENT_DRIP_ENABLED"; payload: { enabled: boolean } }
  | { type: "UPDATE_CONTENT_DRIP_TYPE"; payload: { type: ContentDripSettings["type"] } }
  | { type: "SET_LOADING"; payload: { isLoading: boolean } }
  | { type: "SET_DIRTY_STATE"; payload: { isDirty: boolean } }
  | { type: "SET_ERROR"; payload: { error: string | null } }
  | { type: "CLEAR_ERROR" }
  | { type: "RESET_STATE" }
  // Content Drip Actions
  | ContentDripAction;

// ============================================================================
// REDUCER
// ============================================================================

const reducer = (state = DEFAULT_STATE, action: AdditionalContentAction): StoreState => {
  switch (action.type) {
    // Data Loading
    case "FETCH_ADDITIONAL_CONTENT_START": {
      const typedAction = action as { type: "FETCH_ADDITIONAL_CONTENT_START"; payload: { courseId: number } };
      return {
        ...state,
        courseId: typedAction.payload.courseId,
        isLoading: true,
        error: null,
      };
    }

    case "FETCH_ADDITIONAL_CONTENT_SUCCESS": {
      const typedAction = action as {
        type: "FETCH_ADDITIONAL_CONTENT_SUCCESS";
        payload: { data: AdditionalContentData; contentDrip: ContentDripSettings };
      };
      return {
        ...state,
        data: typedAction.payload.data,
        contentDrip: typedAction.payload.contentDrip,
        isLoading: false,
        isDirty: false,
        error: null,
      };
    }

    case "FETCH_ADDITIONAL_CONTENT_ERROR": {
      const typedAction = action as { type: "FETCH_ADDITIONAL_CONTENT_ERROR"; payload: { error: string } };
      return {
        ...state,
        isLoading: false,
        error: typedAction.payload.error,
      };
    }

    // Data Saving
    case "SAVE_ADDITIONAL_CONTENT_START":
      return {
        ...state,
        isSaving: true,
        error: null,
      };

    case "SAVE_ADDITIONAL_CONTENT_SUCCESS": {
      const typedAction = action as { type: "SAVE_ADDITIONAL_CONTENT_SUCCESS"; payload: { timestamp: number } };
      return {
        ...state,
        isSaving: false,
        isDirty: false,
        error: null,
        lastSaved: typedAction.payload.timestamp,
      };
    }

    case "SAVE_ADDITIONAL_CONTENT_ERROR": {
      const typedAction = action as { type: "SAVE_ADDITIONAL_CONTENT_ERROR"; payload: { error: string } };
      return {
        ...state,
        isSaving: false,
        error: typedAction.payload.error,
      };
    }

    // State Management
    case "SET_COURSE_ID": {
      const typedAction = action as { type: "SET_COURSE_ID"; payload: { courseId: number | null } };
      return {
        ...state,
        courseId: typedAction.payload.courseId,
      };
    }

    case "SET_ADDITIONAL_CONTENT_DATA": {
      const typedAction = action as { type: "SET_ADDITIONAL_CONTENT_DATA"; payload: { data: AdditionalContentData } };
      return {
        ...state,
        data: typedAction.payload.data,
        isDirty: true,
      };
    }

    case "SET_CONTENT_DRIP_SETTINGS": {
      const typedAction = action as {
        type: "SET_CONTENT_DRIP_SETTINGS";
        payload: { contentDrip: ContentDripSettings };
      };
      return {
        ...state,
        contentDrip: typedAction.payload.contentDrip,
        isDirty: true,
      };
    }

    case "UPDATE_FIELD": {
      const typedAction = action as {
        type: "UPDATE_FIELD";
        payload: { field: keyof AdditionalContentData; value: string };
      };
      return {
        ...state,
        data: {
          ...state.data,
          [typedAction.payload.field]: typedAction.payload.value,
        },
        isDirty: true,
      };
    }

    case "UPDATE_CONTENT_DRIP_ENABLED": {
      const typedAction = action as { type: "UPDATE_CONTENT_DRIP_ENABLED"; payload: { enabled: boolean } };
      return {
        ...state,
        contentDrip: {
          ...state.contentDrip,
          enabled: typedAction.payload.enabled,
        },
        isDirty: true,
      };
    }

    case "UPDATE_CONTENT_DRIP_TYPE": {
      const typedAction = action as {
        type: "UPDATE_CONTENT_DRIP_TYPE";
        payload: { type: ContentDripSettings["type"] };
      };
      return {
        ...state,
        contentDrip: {
          ...state.contentDrip,
          type: typedAction.payload.type,
        },
        isDirty: true,
      };
    }

    case "SET_LOADING": {
      const typedAction = action as { type: "SET_LOADING"; payload: { isLoading: boolean } };
      return {
        ...state,
        isLoading: typedAction.payload.isLoading,
      };
    }

    case "SET_DIRTY_STATE": {
      const typedAction = action as { type: "SET_DIRTY_STATE"; payload: { isDirty: boolean } };
      return {
        ...state,
        isDirty: typedAction.payload.isDirty,
      };
    }

    case "SET_ERROR": {
      const typedAction = action as { type: "SET_ERROR"; payload: { error: string | null } };
      return {
        ...state,
        error: typedAction.payload.error,
      };
    }

    case "CLEAR_ERROR":
      return {
        ...state,
        error: null,
      };

    case "RESET_STATE":
      return DEFAULT_STATE;

    // Content Drip Actions (Individual Items)
    case CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ITEM_SETTINGS: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ITEM_SETTINGS;
        payload: { postId: number; settings: ContentDripItemSettings };
      };
      const existingItem = state.contentDripItems[typedAction.payload.postId] || {
        settings: null,
        loading: false,
        error: null,
        saving: false,
        saveError: null,
      };
      return {
        ...state,
        contentDripItems: {
          ...state.contentDripItems,
          [typedAction.payload.postId]: {
            ...existingItem,
            settings: typedAction.payload.settings,
          },
        },
      };
    }

    case CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_LOADING: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_LOADING;
        payload: { postId: number; loading: boolean };
      };
      const existingItem = state.contentDripItems[typedAction.payload.postId] || {
        settings: null,
        loading: false,
        error: null,
        saving: false,
        saveError: null,
      };
      return {
        ...state,
        contentDripItems: {
          ...state.contentDripItems,
          [typedAction.payload.postId]: {
            ...existingItem,
            loading: typedAction.payload.loading,
          },
        },
      };
    }

    case CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ERROR: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ERROR;
        payload: { postId: number; error: string | null };
      };
      const existingItem = state.contentDripItems[typedAction.payload.postId] || {
        settings: null,
        loading: false,
        error: null,
        saving: false,
        saveError: null,
      };
      return {
        ...state,
        contentDripItems: {
          ...state.contentDripItems,
          [typedAction.payload.postId]: {
            ...existingItem,
            error: typedAction.payload.error,
          },
        },
      };
    }

    case CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVING: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVING;
        payload: { postId: number; saving: boolean };
      };
      const existingItem = state.contentDripItems[typedAction.payload.postId] || {
        settings: null,
        loading: false,
        error: null,
        saving: false,
        saveError: null,
      };
      return {
        ...state,
        contentDripItems: {
          ...state.contentDripItems,
          [typedAction.payload.postId]: {
            ...existingItem,
            saving: typedAction.payload.saving,
          },
        },
      };
    }

    case CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVE_ERROR: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVE_ERROR;
        payload: { postId: number; error: string | null };
      };
      const existingItem = state.contentDripItems[typedAction.payload.postId] || {
        settings: null,
        loading: false,
        error: null,
        saving: false,
        saveError: null,
      };
      return {
        ...state,
        contentDripItems: {
          ...state.contentDripItems,
          [typedAction.payload.postId]: {
            ...existingItem,
            saveError: typedAction.payload.error,
          },
        },
      };
    }

    case CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES;
        payload: { courseId: number; prerequisites: PrerequisitesByTopic[] };
      };
      return {
        ...state,
        prerequisites: {
          ...state.prerequisites,
          [typedAction.payload.courseId]: {
            ...state.prerequisites[typedAction.payload.courseId],
            data: typedAction.payload.prerequisites,
          },
        },
      };
    }

    case CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_LOADING: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_LOADING;
        payload: { courseId: number; loading: boolean };
      };
      return {
        ...state,
        prerequisites: {
          ...state.prerequisites,
          [typedAction.payload.courseId]: {
            ...state.prerequisites[typedAction.payload.courseId],
            loading: typedAction.payload.loading,
          },
        },
      };
    }

    case CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_ERROR: {
      const typedAction = action as {
        type: typeof CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_ERROR;
        payload: { courseId: number; error: string | null };
      };
      return {
        ...state,
        prerequisites: {
          ...state.prerequisites,
          [typedAction.payload.courseId]: {
            ...state.prerequisites[typedAction.payload.courseId],
            error: typedAction.payload.error,
          },
        },
      };
    }

    default:
      return state;
  }
};

// ============================================================================
// ACTION CREATORS
// ============================================================================

const actions = {
  // Data Operations
  fetchAdditionalContent(courseId: number) {
    return {
      type: "FETCH_ADDITIONAL_CONTENT" as const,
      payload: { courseId },
    };
  },

  saveAdditionalContent(courseId: number, data: AdditionalContentData, contentDrip: ContentDripSettings) {
    return {
      type: "SAVE_ADDITIONAL_CONTENT" as const,
      payload: { courseId, data, contentDrip },
    };
  },

  // State Management
  setCourseId(courseId: number | null) {
    return {
      type: "SET_COURSE_ID" as const,
      payload: { courseId },
    };
  },

  setAdditionalContentData(data: AdditionalContentData) {
    return {
      type: "SET_ADDITIONAL_CONTENT_DATA" as const,
      payload: { data },
    };
  },

  setContentDripSettings(contentDrip: ContentDripSettings) {
    return {
      type: "SET_CONTENT_DRIP_SETTINGS" as const,
      payload: { contentDrip },
    };
  },

  updateField(field: keyof AdditionalContentData, value: string) {
    return {
      type: "UPDATE_FIELD" as const,
      payload: { field, value },
    };
  },

  // Field-specific update actions
  updateWhatWillILearn(value: string) {
    return {
      type: "UPDATE_FIELD" as const,
      payload: { field: "what_will_learn", value },
    };
  },

  updateTargetAudience(value: string) {
    return {
      type: "UPDATE_FIELD" as const,
      payload: { field: "target_audience", value },
    };
  },

  updateRequirements(value: string) {
    return {
      type: "UPDATE_FIELD" as const,
      payload: { field: "requirements", value },
    };
  },

  updateContentDripEnabled(enabled: boolean) {
    return {
      type: "UPDATE_CONTENT_DRIP_ENABLED" as const,
      payload: { enabled },
    };
  },

  updateContentDripType(type: ContentDripSettings["type"]) {
    return {
      type: "UPDATE_CONTENT_DRIP_TYPE" as const,
      payload: { type },
    };
  },

  setLoading(isLoading: boolean) {
    return {
      type: "SET_LOADING" as const,
      payload: { isLoading },
    };
  },

  setDirtyState(isDirty: boolean) {
    return {
      type: "SET_DIRTY_STATE" as const,
      payload: { isDirty },
    };
  },

  setError(error: string | null) {
    return {
      type: "SET_ERROR" as const,
      payload: { error },
    };
  },

  clearError() {
    return {
      type: "CLEAR_ERROR" as const,
    };
  },

  resetState() {
    return {
      type: "RESET_STATE" as const,
    };
  },
};

// ============================================================================
// SELECTORS
// ============================================================================

const selectors = {
  // Data Selectors
  getCourseId(state: StoreState) {
    return state.courseId;
  },

  getAdditionalContentData(state: StoreState) {
    return state.data;
  },

  getContentDripSettings(state: StoreState) {
    return state.contentDrip;
  },

  getField(state: StoreState, field: keyof AdditionalContentData) {
    return state.data[field];
  },

  // State Selectors
  isLoading(state: StoreState) {
    return state.isLoading;
  },

  isSaving(state: StoreState) {
    return state.isSaving;
  },

  isDirty(state: StoreState) {
    return state.isDirty;
  },

  getError(state: StoreState) {
    return state.error;
  },

  hasError(state: StoreState) {
    return state.error !== null;
  },

  getLastSaved(state: StoreState) {
    return state.lastSaved;
  },

  // Content Drip Specific Selectors
  isContentDripAddonAvailable(state: StoreState) {
    // Check if Content Drip addon is available
    return isContentDripEnabled();
  },

  isContentDripEnabled(state: StoreState) {
    // Check if content drip is enabled at course level (requires addon to be available)
    const isAddonAvailable = selectors.isContentDripAddonAvailable(state);
    const courseContentDripEnabled = state.contentDrip?.enabled === true;
    return isAddonAvailable && courseContentDripEnabled;
  },

  getContentDripType(state: StoreState) {
    return state.contentDrip?.type || null;
  },

  // Computed Selectors
  hasUnsavedChanges(state: StoreState) {
    return state.isDirty && !state.isSaving;
  },

  canSave(state: StoreState) {
    return state.courseId !== null && state.isDirty && !state.isSaving;
  },
};

// ============================================================================
// RESOLVERS
// ============================================================================

const resolvers = {
  *fetchAdditionalContent(courseId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "FETCH_ADDITIONAL_CONTENT_START",
        payload: { courseId },
      };

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/additional-content/${courseId}`,
          method: "GET",
        },
      }) as AdditionalContentResponse;

      if (response.success) {
        yield {
          type: "FETCH_ADDITIONAL_CONTENT_SUCCESS",
          payload: {
            data: {
              what_will_learn: response.data.what_will_learn,
              target_audience: response.data.target_audience,
              requirements: response.data.requirements,
            },
            contentDrip: response.data.content_drip,
          },
        };
      } else {
        yield {
          type: "FETCH_ADDITIONAL_CONTENT_ERROR",
          payload: {
            error: __("Failed to load additional content", "tutorpress"),
          },
        };
      }
    } catch (error) {
      yield {
        type: "FETCH_ADDITIONAL_CONTENT_ERROR",
        payload: {
          error: error instanceof Error ? error.message : __("Unknown error occurred", "tutorpress"),
        },
      };
    }
  },

  *saveAdditionalContent(
    courseId: number,
    data: AdditionalContentData,
    contentDrip: ContentDripSettings
  ): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "SAVE_ADDITIONAL_CONTENT_START",
      };

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/additional-content/save`,
          method: "POST",
          data: {
            course_id: courseId,
            what_will_learn: data.what_will_learn,
            target_audience: data.target_audience,
            requirements: data.requirements,
            content_drip_enabled: contentDrip.enabled,
            content_drip_type: contentDrip.type,
          },
        },
      }) as AdditionalContentSaveResponse;

      if (response.success) {
        yield {
          type: "SAVE_ADDITIONAL_CONTENT_SUCCESS",
          payload: {
            timestamp: Date.now(),
          },
        };
      } else {
        yield {
          type: "SAVE_ADDITIONAL_CONTENT_ERROR",
          payload: {
            error: response.message || __("Failed to save additional content", "tutorpress"),
          },
        };
      }
    } catch (error) {
      yield {
        type: "SAVE_ADDITIONAL_CONTENT_ERROR",
        payload: {
          error: error instanceof Error ? error.message : __("Unknown error occurred", "tutorpress"),
        },
      };
    }
  },
};

// ============================================================================
// STORE CONFIGURATION
// ============================================================================

export const additionalContentStore = createReduxStore("tutorpress/additional-content", {
  reducer,
  actions: {
    ...actions,
    ...resolvers, // Merge resolvers with actions so they can be called as actions
    ...contentDripResolvers, // Add content drip resolvers
  },
  selectors: {
    ...selectors,
    // Add content drip selectors with prefixed names to avoid conflicts
    getContentDripSettingsForPost: getContentDripSettings,
    isContentDripLoadingForPost: isContentDripLoading,
    getContentDripErrorForPost: getContentDripError,
    isContentDripSavingForPost: isContentDripSaving,
    getContentDripSaveErrorForPost: getContentDripSaveError,
    getPrerequisitesForCourse: getPrerequisites,
    isPrerequisitesLoadingForCourse: isPrerequisitesLoading,
    getPrerequisitesErrorForCourse: getPrerequisitesError,
    hasContentDripSettingsForPost: hasContentDripSettings,
    isContentDripEnabledForPost,
    getContentDripTypeForPost,
    hasPrerequisitesForCourse: hasPrerequisites,
    getContentDripInfoForPost: getContentDripInfo,
    getPrerequisitesInfoForCourse: getPrerequisitesInfo,
  },
  controls,
});

// Register the store
register(additionalContentStore);

// Export for external use
export default additionalContentStore;
