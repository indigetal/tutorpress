/**
 * Bundle Settings Store
 *
 * WordPress Data Store for bundle settings operations.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import type {
  CourseBundlesState,
  BundleOperationState,
  Bundle,
  BundleListResponse,
  BundleResponse,
  BundleCourse,
  BundlePricing,
  BundleError,
  BundleCourseSearch,
  AvailableCourse,
} from "../../types/bundle";
import { BundleErrorCode } from "../../types/bundle";

// Initial state
const initialState: CourseBundlesState = {
  bundles: [],
  currentBundle: null,
  operationState: { status: "idle" },
  creationState: { status: "idle" },
  editState: { isEditing: false, bundleId: null },
  activeOperation: { type: "none" },
  fetchState: {
    isLoading: false,
    error: null,
    lastFetchedBundleId: null,
  },
  courseSelection: {
    availableCourses: [],
    isLoading: false,
    error: null,
  },
  // Bundle Benefits state
  bundleBenefits: {
    data: { benefits: "" },
    isLoading: false,
    isSaving: false,
    isDirty: false,
    error: null,
    lastSaved: null,
  },
};

// Action types
const ACTION_TYPES = {
  SET_LOADING: "SET_LOADING",
  SET_ERROR: "SET_ERROR",
  SET_BUNDLES: "SET_BUNDLES",
  SET_CURRENT_BUNDLE: "SET_CURRENT_BUNDLE",
  SET_OPERATION_STATE: "SET_OPERATION_STATE",
  CLEAR_ERROR: "CLEAR_ERROR",
  SET_AVAILABLE_COURSES: "SET_AVAILABLE_COURSES",
  SET_COURSE_SELECTION_STATE: "SET_COURSE_SELECTION_STATE",
  // Bundle Benefits actions
  FETCH_BUNDLE_BENEFITS: "FETCH_BUNDLE_BENEFITS",
  FETCH_BUNDLE_BENEFITS_START: "FETCH_BUNDLE_BENEFITS_START",
  FETCH_BUNDLE_BENEFITS_SUCCESS: "FETCH_BUNDLE_BENEFITS_SUCCESS",
  FETCH_BUNDLE_BENEFITS_ERROR: "FETCH_BUNDLE_BENEFITS_ERROR",
  SAVE_BUNDLE_BENEFITS: "SAVE_BUNDLE_BENEFITS",
  SAVE_BUNDLE_BENEFITS_START: "SAVE_BUNDLE_BENEFITS_START",
  SAVE_BUNDLE_BENEFITS_SUCCESS: "SAVE_BUNDLE_BENEFITS_SUCCESS",
  SAVE_BUNDLE_BENEFITS_ERROR: "SAVE_BUNDLE_BENEFITS_ERROR",
  SET_BUNDLE_BENEFITS_DATA: "SET_BUNDLE_BENEFITS_DATA",
  UPDATE_BUNDLE_BENEFITS: "UPDATE_BUNDLE_BENEFITS",
  SET_DIRTY_STATE: "SET_DIRTY_STATE",
} as const;

// Define action types for TypeScript
export type CourseBundlesAction =
  | { type: "SET_LOADING"; payload: boolean }
  | { type: "SET_ERROR"; payload: BundleError }
  | { type: "SET_BUNDLES"; payload: BundleListResponse }
  | { type: "SET_CURRENT_BUNDLE"; payload: BundleResponse }
  | { type: "SET_OPERATION_STATE"; payload: Partial<BundleOperationState> }
  | { type: "CLEAR_ERROR" }
  | { type: "SET_AVAILABLE_COURSES"; payload: AvailableCourse[] }
  | { type: "SET_COURSE_SELECTION_STATE"; payload: Partial<CourseBundlesState["courseSelection"]> };

// Action creators
const actions = {
  // Basic bundle actions - will be expanded as settings are implemented
  *getBundles(params?: BundleCourseSearch) {
    yield { type: ACTION_TYPES.SET_LOADING, payload: true };
    yield { type: ACTION_TYPES.CLEAR_ERROR };

    try {
      const queryParams = new URLSearchParams();
      if (params?.search) queryParams.append("search", params.search);
      if (params?.per_page) queryParams.append("per_page", params.per_page.toString());
      if (params?.page) queryParams.append("page", params.page.toString());

      const response: BundleListResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/bundles?${queryParams.toString()}`,
          method: "GET",
        },
      };

      yield { type: ACTION_TYPES.SET_BUNDLES, payload: response };
      return response;
    } catch (error) {
      const bundleError: BundleError = {
        code: BundleErrorCode.NETWORK_ERROR,
        message: error instanceof Error ? error.message : "Failed to fetch bundles",
        context: { action: "getBundles", details: `Failed to fetch bundles` },
      };
      yield { type: ACTION_TYPES.SET_ERROR, payload: bundleError };
      throw bundleError;
    } finally {
      yield { type: ACTION_TYPES.SET_LOADING, payload: false };
    }
  },

  *getBundle(id: number) {
    yield { type: ACTION_TYPES.SET_OPERATION_STATE, payload: { status: "loading" } };

    try {
      const response: BundleResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/bundles/${id}`,
          method: "GET",
        },
      };

      yield { type: ACTION_TYPES.SET_CURRENT_BUNDLE, payload: response };
      return response;
    } catch (error) {
      const bundleError: BundleError = {
        code: BundleErrorCode.NETWORK_ERROR,
        message: error instanceof Error ? error.message : "Failed to fetch bundle",
        context: { action: "getBundle", bundleId: id, details: `Failed to fetch bundle ${id}` },
      };
      yield { type: ACTION_TYPES.SET_OPERATION_STATE, payload: { status: "error", error: bundleError } };
      throw bundleError;
    } finally {
      yield { type: ACTION_TYPES.SET_OPERATION_STATE, payload: { status: "idle" } };
    }
  },

  *updateBundle(id: number, data: Partial<Bundle>) {
    // Placeholder - will be expanded as needed
    return { bundle: { id, ...data } as Bundle };
  },

  // Course selection actions for Setting 1
  *fetchAvailableCourses(params?: { search?: string; per_page?: number; page?: number; exclude?: string }) {
    yield { type: "SET_COURSE_SELECTION_STATE", payload: { isLoading: true, error: null } };

    try {
      const queryParams = new URLSearchParams();
      if (params?.search) queryParams.append("search", params.search);
      if (params?.per_page) queryParams.append("per_page", params.per_page.toString());
      if (params?.page) queryParams.append("page", params.page.toString());
      if (params?.exclude) queryParams.append("exclude", params.exclude);

      const response: { data: AvailableCourse[]; total_found: number; search_term?: string; status: string } = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/search?${queryParams.toString()}`,
          method: "GET",
        },
      };

      yield { type: "SET_AVAILABLE_COURSES", payload: response.data };
      return response;
    } catch (error) {
      const bundleError: BundleError = {
        code: BundleErrorCode.NETWORK_ERROR,
        message: error instanceof Error ? error.message : "Failed to fetch available courses",
        context: { action: "fetchAvailableCourses", details: "Failed to fetch available courses" },
      };
      yield { type: "SET_COURSE_SELECTION_STATE", payload: { error: bundleError } };
      throw bundleError;
    } finally {
      yield { type: "SET_COURSE_SELECTION_STATE", payload: { isLoading: false } };
    }
  },

  *updateBundleCourses(id: number, courseIds: number[]) {
    yield { type: "SET_OPERATION_STATE", payload: { status: "saving" } };

    try {
      const response: { message: string } = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/bundles/${id}/courses`,
          method: "PATCH",
          data: { course_ids: courseIds },
        },
      };

      yield { type: "SET_OPERATION_STATE", payload: { status: "success", data: response as any } };
      return response;
    } catch (error) {
      const bundleError: BundleError = {
        code: BundleErrorCode.NETWORK_ERROR,
        message: error instanceof Error ? error.message : "Failed to update bundle courses",
        context: { action: "updateBundleCourses", bundleId: id, details: `Failed to update bundle ${id} courses` },
      };
      yield { type: "SET_OPERATION_STATE", payload: { status: "error", error: bundleError } };
      throw bundleError;
    } finally {
      yield { type: "SET_OPERATION_STATE", payload: { status: "idle" } };
    }
  },

  // Placeholder actions for future settings - will be expanded incrementally
  *getBundleCourses(id: number) {
    yield { type: "SET_OPERATION_STATE", payload: { status: "loading" } };

    try {
      const response: { data: AvailableCourse[]; total_found: number } = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/bundles/${id}/courses`,
          method: "GET",
        },
      };

      yield { type: "SET_OPERATION_STATE", payload: { status: "success", data: response } };
      return response;
    } catch (error) {
      const bundleError: BundleError = {
        code: BundleErrorCode.NETWORK_ERROR,
        message: error instanceof Error ? error.message : "Failed to fetch bundle courses",
        context: { action: "getBundleCourses", bundleId: id, details: `Failed to fetch bundle ${id} courses` },
      };
      yield { type: "SET_OPERATION_STATE", payload: { status: "error", error: bundleError } };
      throw bundleError;
    } finally {
      yield { type: "SET_OPERATION_STATE", payload: { status: "idle" } };
    }
  },

  *getBundlePricing(id: number) {
    // Placeholder - will be expanded in Setting 3
    return { pricing: { regular_price: 0, sale_price: 0, ribbon_type: "none" } };
  },

  *updateBundlePricing(id: number, pricing: Partial<BundlePricing>) {
    // Placeholder - will be expanded in Setting 3
    return { message: "Updated" };
  },

  *getBundleInstructors(id: number) {
    // Placeholder - will be expanded in Setting 4
    return { instructors: [] };
  },

  // Utility Actions
  clearError() {
    return { type: ACTION_TYPES.CLEAR_ERROR };
  },

  // Bundle Benefits actions (following Additional Content pattern)
  *fetchBundleBenefits(bundleId: number) {
    yield { type: ACTION_TYPES.FETCH_BUNDLE_BENEFITS_START, payload: { bundleId } };

    try {
      const response: {
        success: boolean;
        data: {
          benefits: string;
          bundle_id: number;
        };
      } = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/bundles/${bundleId}/benefits`,
          method: "GET",
        },
      };

      if (response.success) {
        yield {
          type: ACTION_TYPES.FETCH_BUNDLE_BENEFITS_SUCCESS,
          payload: {
            data: { benefits: response.data.benefits || "" },
          },
        };
      } else {
        yield {
          type: ACTION_TYPES.FETCH_BUNDLE_BENEFITS_ERROR,
          payload: { error: "Failed to load bundle benefits" },
        };
      }
    } catch (error) {
      yield {
        type: ACTION_TYPES.FETCH_BUNDLE_BENEFITS_ERROR,
        payload: {
          error: error instanceof Error ? error.message : "Unknown error occurred",
        },
      };
    }
  },

  *saveBundleBenefits(bundleId: number, data: { benefits: string }) {
    yield { type: ACTION_TYPES.SAVE_BUNDLE_BENEFITS_START };

    try {
      const response: { success: boolean; message: string } = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/bundles/benefits/save`,
          method: "POST",
          data: {
            bundle_id: bundleId,
            benefits: data.benefits,
          },
        },
      };

      if (response.success) {
        yield {
          type: ACTION_TYPES.SAVE_BUNDLE_BENEFITS_SUCCESS,
          payload: { timestamp: Date.now() },
        };
      } else {
        yield {
          type: ACTION_TYPES.SAVE_BUNDLE_BENEFITS_ERROR,
          payload: { error: response.message || "Failed to save bundle benefits" },
        };
      }
    } catch (error) {
      yield {
        type: ACTION_TYPES.SAVE_BUNDLE_BENEFITS_ERROR,
        payload: {
          error: error instanceof Error ? error.message : "Unknown error occurred",
        },
      };
    }
  },

  setBundleBenefitsData(data: { benefits: string }) {
    return { type: ACTION_TYPES.SET_BUNDLE_BENEFITS_DATA, payload: { data } };
  },

  updateBundleBenefits(value: string) {
    return { type: ACTION_TYPES.UPDATE_BUNDLE_BENEFITS, payload: { value } };
  },

  setDirtyState(isDirty: boolean) {
    return { type: ACTION_TYPES.SET_DIRTY_STATE, payload: { isDirty } };
  },
};

// Resolvers (for async operations)
const resolvers = {
  getBundles: actions.getBundles,
  getBundle: actions.getBundle,
  getBundleCourses: actions.getBundleCourses,
  getBundlePricing: actions.getBundlePricing,
  getBundleInstructors: actions.getBundleInstructors,
  fetchAvailableCourses: actions.fetchAvailableCourses,
};

// Selectors
const selectors = {
  getBundles: (state: CourseBundlesState) => state.bundles,
  getCurrentBundle: (state: CourseBundlesState) => state.currentBundle,
  isLoading: (state: CourseBundlesState) => state.fetchState.isLoading,
  getError: (state: CourseBundlesState) => state.fetchState.error,
  getBundleById: (state: CourseBundlesState, id: number) => state.bundles.find((bundle) => bundle.id === id) || null,
  getOperationState: (state: CourseBundlesState) => state.operationState,
  // Course selection selectors
  getAvailableCourses: (state: CourseBundlesState) => state.courseSelection.availableCourses,
  getCourseSelectionLoading: (state: CourseBundlesState) => state.courseSelection.isLoading,
  getCourseSelectionError: (state: CourseBundlesState) => state.courseSelection.error,
  // Bundle Benefits selectors (following Additional Content pattern)
  getBundleBenefitsData: (state: CourseBundlesState) => state.bundleBenefits.data,
  getBundleBenefitsLoading: (state: CourseBundlesState) => state.bundleBenefits.isLoading,
  getBundleBenefitsSaving: (state: CourseBundlesState) => state.bundleBenefits.isSaving,
  getBundleBenefitsDirty: (state: CourseBundlesState) => state.bundleBenefits.isDirty,
  getBundleBenefitsError: (state: CourseBundlesState) => state.bundleBenefits.error,
  getBundleBenefitsLastSaved: (state: CourseBundlesState) => state.bundleBenefits.lastSaved,
  hasBundleBenefitsUnsavedChanges: (state: CourseBundlesState) => state.bundleBenefits.isDirty,
  canSaveBundleBenefits: (state: CourseBundlesState) =>
    !state.bundleBenefits.isLoading && !state.bundleBenefits.isSaving && state.bundleBenefits.isDirty,
};

// Create and register the store
const store = createReduxStore("tutorpress/course-bundles", {
  reducer(state = initialState, action: CourseBundlesAction | { type: string }) {
    switch (action.type) {
      case ACTION_TYPES.SET_LOADING:
        return {
          ...state,
          fetchState: {
            ...state.fetchState,
            isLoading: (action as { type: "SET_LOADING"; payload: boolean }).payload,
          },
        };

      case ACTION_TYPES.SET_ERROR:
        return {
          ...state,
          fetchState: {
            ...state.fetchState,
            error: (action as { type: "SET_ERROR"; payload: BundleError }).payload,
          },
        };

      case ACTION_TYPES.SET_BUNDLES:
        return {
          ...state,
          bundles: (action as { type: "SET_BUNDLES"; payload: BundleListResponse }).payload.bundles || [],
        };

      case ACTION_TYPES.SET_CURRENT_BUNDLE:
        return {
          ...state,
          currentBundle: (action as { type: "SET_CURRENT_BUNDLE"; payload: BundleResponse }).payload.bundle,
        };

      case ACTION_TYPES.SET_OPERATION_STATE:
        return {
          ...state,
          operationState: (action as { type: "SET_OPERATION_STATE"; payload: Partial<BundleOperationState> })
            .payload as BundleOperationState,
        };

      case ACTION_TYPES.CLEAR_ERROR:
        return {
          ...state,
          fetchState: {
            ...state.fetchState,
            error: null,
          },
          operationState: { status: "idle" },
        };

      case ACTION_TYPES.SET_AVAILABLE_COURSES:
        return {
          ...state,
          courseSelection: {
            ...state.courseSelection,
            availableCourses: (action as { type: "SET_AVAILABLE_COURSES"; payload: AvailableCourse[] }).payload,
          },
        };

      case ACTION_TYPES.SET_COURSE_SELECTION_STATE:
        return {
          ...state,
          courseSelection: {
            ...state.courseSelection,
            ...(
              action as { type: "SET_COURSE_SELECTION_STATE"; payload: Partial<CourseBundlesState["courseSelection"]> }
            ).payload,
          },
        };

      // Bundle Benefits reducer cases (following Additional Content pattern)
      case ACTION_TYPES.FETCH_BUNDLE_BENEFITS_START:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            isLoading: true,
            error: null,
          },
        };

      case ACTION_TYPES.FETCH_BUNDLE_BENEFITS_SUCCESS:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            data: (action as { type: "FETCH_BUNDLE_BENEFITS_SUCCESS"; payload: { data: { benefits: string } } }).payload
              .data,
            isLoading: false,
            error: null,
          },
        };

      case ACTION_TYPES.FETCH_BUNDLE_BENEFITS_ERROR:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            isLoading: false,
            error: (action as { type: "FETCH_BUNDLE_BENEFITS_ERROR"; payload: { error: string } }).payload.error,
          },
        };

      case ACTION_TYPES.SAVE_BUNDLE_BENEFITS_START:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            isSaving: true,
            error: null,
          },
        };

      case ACTION_TYPES.SAVE_BUNDLE_BENEFITS_SUCCESS:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            isSaving: false,
            isDirty: false,
            lastSaved: (action as { type: "SAVE_BUNDLE_BENEFITS_SUCCESS"; payload: { timestamp: number } }).payload
              .timestamp,
            error: null,
          },
        };

      case ACTION_TYPES.SAVE_BUNDLE_BENEFITS_ERROR:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            isSaving: false,
            error: (action as { type: "SAVE_BUNDLE_BENEFITS_ERROR"; payload: { error: string } }).payload.error,
          },
        };

      case ACTION_TYPES.SET_BUNDLE_BENEFITS_DATA:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            data: (action as { type: "SET_BUNDLE_BENEFITS_DATA"; payload: { data: { benefits: string } } }).payload
              .data,
          },
        };

      case ACTION_TYPES.UPDATE_BUNDLE_BENEFITS:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            data: {
              ...state.bundleBenefits.data,
              benefits: (action as { type: "UPDATE_BUNDLE_BENEFITS"; payload: { value: string } }).payload.value,
            },
            isDirty: true,
          },
        };

      case ACTION_TYPES.SET_DIRTY_STATE:
        return {
          ...state,
          bundleBenefits: {
            ...state.bundleBenefits,
            isDirty: (action as { type: "SET_DIRTY_STATE"; payload: { isDirty: boolean } }).payload.isDirty,
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

// Export actions and selectors for direct access
export const {
  getBundles,
  getBundle,
  updateBundle,
  getBundleCourses,
  updateBundleCourses,
  getBundlePricing,
  updateBundlePricing,
  getBundleInstructors,
  fetchAvailableCourses,
  clearError,
} = actions;

export const {
  getBundles: getBundlesSelector,
  getCurrentBundle,
  isLoading,
  getError,
  getBundleById,
  getOperationState,
  getAvailableCourses,
  getCourseSelectionLoading,
  getCourseSelectionError,
} = selectors;

// Export types for components
export type {
  CourseBundlesState,
  BundleOperationState,
  BundleCreationState,
  BundleEditState,
} from "../../types/bundle";
