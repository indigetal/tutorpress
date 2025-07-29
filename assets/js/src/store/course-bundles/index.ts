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
};

// Action types
const ACTION_TYPES = {
  SET_LOADING: "SET_LOADING",
  SET_ERROR: "SET_ERROR",
  SET_BUNDLES: "SET_BUNDLES",
  SET_CURRENT_BUNDLE: "SET_CURRENT_BUNDLE",
  SET_OPERATION_STATE: "SET_OPERATION_STATE",
  CLEAR_ERROR: "CLEAR_ERROR",
} as const;

// Define action types for TypeScript
export type CourseBundlesAction =
  | { type: "SET_LOADING"; payload: boolean }
  | { type: "SET_ERROR"; payload: BundleError }
  | { type: "SET_BUNDLES"; payload: BundleListResponse }
  | { type: "SET_CURRENT_BUNDLE"; payload: BundleResponse }
  | { type: "SET_OPERATION_STATE"; payload: Partial<BundleOperationState> }
  | { type: "CLEAR_ERROR" };

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

  // Placeholder actions for future settings - will be expanded incrementally
  *getBundleCourses(id: number) {
    // Placeholder - will be expanded in Setting 1
    return { courses: [] };
  },

  *updateBundleCourses(id: number, courseIds: number[]) {
    // Placeholder - will be expanded in Setting 1
    return { message: "Updated" };
  },

  *getBundleBenefits(id: number) {
    // Placeholder - will be expanded in Setting 2
    return { benefits: "" };
  },

  *updateBundleBenefits(id: number, benefits: string) {
    // Placeholder - will be expanded in Setting 2
    return { message: "Updated" };
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
};

// Resolvers (for async operations)
const resolvers = {
  getBundles: actions.getBundles,
  getBundle: actions.getBundle,
  getBundleCourses: actions.getBundleCourses,
  getBundleBenefits: actions.getBundleBenefits,
  getBundlePricing: actions.getBundlePricing,
  getBundleInstructors: actions.getBundleInstructors,
};

// Selectors
const selectors = {
  getBundles: (state: CourseBundlesState) => state.bundles,
  getCurrentBundle: (state: CourseBundlesState) => state.currentBundle,
  isLoading: (state: CourseBundlesState) => state.fetchState.isLoading,
  getError: (state: CourseBundlesState) => state.fetchState.error,
  getBundleById: (state: CourseBundlesState, id: number) => state.bundles.find((bundle) => bundle.id === id) || null,
  getOperationState: (state: CourseBundlesState) => state.operationState,
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
  getBundleBenefits,
  updateBundleBenefits,
  getBundlePricing,
  updateBundlePricing,
  getBundleInstructors,
  clearError,
} = actions;

export const {
  getBundles: getBundlesSelector,
  getCurrentBundle,
  isLoading,
  getError,
  getBundleById,
  getOperationState,
} = selectors;

// Export types for components
export type {
  CourseBundlesState,
  BundleOperationState,
  BundleCreationState,
  BundleEditState,
} from "../../types/bundle";
