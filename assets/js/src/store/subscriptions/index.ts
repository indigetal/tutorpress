/**
 * Subscription Store for TutorPress
 *
 * Dedicated store for subscription plan operations, following H5P store pattern
 * for complex state management with CRUD operations, sorting, and form handling.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { select } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import {
  SubscriptionState,
  SubscriptionPlan,
  CreateSubscriptionPlanData,
  UpdateSubscriptionPlanData,
  SubscriptionFormMode,
  SubscriptionFormState,
  SubscriptionOperationsState,
  SubscriptionSortingState,
  SubscriptionValidationErrors,
  SubscriptionValidationResult,
  SubscriptionPlansResponse,
  SubscriptionPlanResponse,
  CreateSubscriptionPlanResponse,
  UpdateSubscriptionPlanResponse,
  DeleteSubscriptionPlanResponse,
  DuplicateSubscriptionPlanResponse,
  SortSubscriptionPlansResponse,
  defaultSubscriptionPlan,
} from "../../types/subscriptions";
import { createCurriculumError } from "../../utils/errors";
import { CurriculumErrorCode } from "../../types/curriculum";
import {
  buildFetchRequest,
  buildCreateRequest,
  buildUpdateRequest,
  buildDeleteRequest,
  buildDuplicateRequest,
  buildSortRequest,
} from "../../utils/subscriptions";

// ============================================================================
// STATE INTERFACE
// ============================================================================

/**
 * Subscription Store State Interface
 */
interface State extends SubscriptionState {
  lastLoadedCourseId?: number | null;
}

// ============================================================================
// INITIAL STATE
// ============================================================================

const DEFAULT_STATE: State = {
  plans: [],
  selectedPlan: null,
  formState: {
    mode: "add",
    data: { ...defaultSubscriptionPlan },
    isDirty: false,
  },
  operations: {
    isLoading: false,
    error: null,
  },
  sorting: {
    isReordering: false,
    error: null,
  },
  lastLoadedCourseId: null,
};

// ============================================================================
// ACTION TYPES
// ============================================================================

export type SubscriptionAction =
  // Plan Management Actions
  | { type: "FETCH_SUBSCRIPTION_PLANS"; payload: { courseId: number } }
  | { type: "FETCH_SUBSCRIPTION_PLANS_START"; payload: { courseId: number } }
  | { type: "FETCH_SUBSCRIPTION_PLANS_SUCCESS"; payload: { plans: SubscriptionPlan[] } }
  | { type: "FETCH_SUBSCRIPTION_PLANS_ERROR"; payload: { error: string } }
  | { type: "SET_SELECTED_PLAN"; payload: { plan: SubscriptionPlan | null } }
  // Form Management Actions
  | { type: "SET_FORM_MODE"; payload: { mode: SubscriptionFormMode } }
  | { type: "SET_FORM_DATA"; payload: { data: Partial<SubscriptionPlan> } }
  | { type: "RESET_FORM" }
  | { type: "SET_FORM_DIRTY"; payload: { isDirty: boolean } }
  // CRUD Operations Actions
  | { type: "CREATE_SUBSCRIPTION_PLAN"; payload: { planData: CreateSubscriptionPlanData } }
  | { type: "CREATE_SUBSCRIPTION_PLAN_START"; payload: { planData: CreateSubscriptionPlanData } }
  | { type: "CREATE_SUBSCRIPTION_PLAN_SUCCESS"; payload: { plan: SubscriptionPlan } }
  | { type: "CREATE_SUBSCRIPTION_PLAN_ERROR"; payload: { error: string } }
  | { type: "UPDATE_SUBSCRIPTION_PLAN"; payload: { planId: number; planData: UpdateSubscriptionPlanData } }
  | { type: "UPDATE_SUBSCRIPTION_PLAN_START"; payload: { planId: number; planData: UpdateSubscriptionPlanData } }
  | { type: "UPDATE_SUBSCRIPTION_PLAN_SUCCESS"; payload: { plan: SubscriptionPlan } }
  | { type: "UPDATE_SUBSCRIPTION_PLAN_ERROR"; payload: { error: string } }
  | { type: "DELETE_SUBSCRIPTION_PLAN"; payload: { planId: number } }
  | { type: "DELETE_SUBSCRIPTION_PLAN_START"; payload: { planId: number } }
  | { type: "DELETE_SUBSCRIPTION_PLAN_SUCCESS"; payload: { planId: number } }
  | { type: "DELETE_SUBSCRIPTION_PLAN_ERROR"; payload: { error: string } }
  | { type: "DUPLICATE_SUBSCRIPTION_PLAN"; payload: { planId: number } }
  | { type: "DUPLICATE_SUBSCRIPTION_PLAN_START"; payload: { planId: number } }
  | { type: "DUPLICATE_SUBSCRIPTION_PLAN_SUCCESS"; payload: { plan: SubscriptionPlan } }
  | { type: "DUPLICATE_SUBSCRIPTION_PLAN_ERROR"; payload: { error: string } }
  // Sorting Actions
  | { type: "SORT_SUBSCRIPTION_PLANS"; payload: { planOrder: number[] } }
  | { type: "SORT_SUBSCRIPTION_PLANS_START"; payload: { planOrder: number[] } }
  | { type: "SORT_SUBSCRIPTION_PLANS_SUCCESS" }
  | { type: "SORT_SUBSCRIPTION_PLANS_ERROR"; payload: { error: string } };

// ============================================================================
// REDUCER
// ============================================================================

const reducer = (state = DEFAULT_STATE, action: SubscriptionAction): State => {
  switch (action.type) {
    // Plan Management
    case "FETCH_SUBSCRIPTION_PLANS_START":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: true,
          error: null,
        },
      };

    case "FETCH_SUBSCRIPTION_PLANS_SUCCESS":
      return {
        ...state,
        plans: action.payload.plans,
        operations: {
          ...state.operations,
          isLoading: false,
          error: null,
        },
        lastLoadedCourseId: (action as any).payload.courseId ?? state.lastLoadedCourseId,
      };

    case "FETCH_SUBSCRIPTION_PLANS_ERROR":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: false,
          error: action.payload.error,
        },
      };

    case "SET_SELECTED_PLAN":
      return {
        ...state,
        selectedPlan: action.payload.plan,
      };

    // Form Management
    case "SET_FORM_MODE":
      return {
        ...state,
        formState: {
          ...state.formState,
          mode: action.payload.mode,
          isDirty: false,
        },
      };

    case "SET_FORM_DATA":
      return {
        ...state,
        formState: {
          ...state.formState,
          data: { ...state.formState.data, ...action.payload.data },
          isDirty: true,
        },
      };

    case "RESET_FORM":
      return {
        ...state,
        formState: {
          mode: "add",
          data: { ...defaultSubscriptionPlan },
          isDirty: false,
        },
      };

    case "SET_FORM_DIRTY":
      return {
        ...state,
        formState: {
          ...state.formState,
          isDirty: action.payload.isDirty,
        },
      };

    // CRUD Operations
    case "CREATE_SUBSCRIPTION_PLAN_START":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: true,
          error: null,
        },
      };

    case "CREATE_SUBSCRIPTION_PLAN_SUCCESS":
      return {
        ...state,
        plans: [...state.plans, action.payload.plan],
        operations: {
          ...state.operations,
          isLoading: false,
          error: null,
        },
        formState: {
          ...state.formState,
          mode: "add",
          data: { ...defaultSubscriptionPlan },
          isDirty: false,
        },
      };

    case "CREATE_SUBSCRIPTION_PLAN_ERROR":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: false,
          error: action.payload.error,
        },
      };

    case "UPDATE_SUBSCRIPTION_PLAN_START":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: true,
          error: null,
        },
      };

    case "UPDATE_SUBSCRIPTION_PLAN_SUCCESS":
      return {
        ...state,
        plans: state.plans.map((plan) => (plan.id === action.payload.plan.id ? action.payload.plan : plan)),
        selectedPlan: action.payload.plan,
        operations: {
          ...state.operations,
          isLoading: false,
          error: null,
        },
        formState: {
          ...state.formState,
          mode: "add",
          data: { ...defaultSubscriptionPlan },
          isDirty: false,
        },
      };

    case "UPDATE_SUBSCRIPTION_PLAN_ERROR":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: false,
          error: action.payload.error,
        },
      };

    case "DELETE_SUBSCRIPTION_PLAN_START":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: true,
          error: null,
        },
      };

    case "DELETE_SUBSCRIPTION_PLAN_SUCCESS":
      return {
        ...state,
        plans: state.plans.filter((plan) => plan.id !== action.payload.planId),
        selectedPlan: state.selectedPlan?.id === action.payload.planId ? null : state.selectedPlan,
        operations: {
          ...state.operations,
          isLoading: false,
          error: null,
        },
      };

    case "DELETE_SUBSCRIPTION_PLAN_ERROR":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: false,
          error: action.payload.error,
        },
      };

    case "DUPLICATE_SUBSCRIPTION_PLAN_START":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: true,
          error: null,
        },
      };

    case "DUPLICATE_SUBSCRIPTION_PLAN_SUCCESS":
      return {
        ...state,
        plans: [...state.plans, action.payload.plan],
        operations: {
          ...state.operations,
          isLoading: false,
          error: null,
        },
      };

    case "DUPLICATE_SUBSCRIPTION_PLAN_ERROR":
      return {
        ...state,
        operations: {
          ...state.operations,
          isLoading: false,
          error: action.payload.error,
        },
      };

    // Sorting
    case "SORT_SUBSCRIPTION_PLANS_START":
      return {
        ...state,
        sorting: {
          ...state.sorting,
          isReordering: true,
          error: null,
        },
      };

    case "SORT_SUBSCRIPTION_PLANS_SUCCESS":
      return {
        ...state,
        sorting: {
          ...state.sorting,
          isReordering: false,
          error: null,
        },
      };

    case "SORT_SUBSCRIPTION_PLANS_ERROR":
      return {
        ...state,
        sorting: {
          ...state.sorting,
          isReordering: false,
          error: action.payload.error,
        },
      };

    default:
      return state;
  }
};

// ============================================================================
// ACTIONS
// ============================================================================

const actions = {
  // Plan Management
  fetchSubscriptionPlans(courseId: number) {
    return {
      type: "FETCH_SUBSCRIPTION_PLANS" as const,
      payload: { courseId },
    };
  },

  setSelectedPlan(plan: SubscriptionPlan | null) {
    return {
      type: "SET_SELECTED_PLAN" as const,
      payload: { plan },
    };
  },

  // Form Management
  setFormMode(mode: SubscriptionFormMode) {
    return {
      type: "SET_FORM_MODE" as const,
      payload: { mode },
    };
  },

  setFormData(data: Partial<SubscriptionPlan>) {
    return {
      type: "SET_FORM_DATA" as const,
      payload: { data },
    };
  },

  resetForm() {
    return {
      type: "RESET_FORM" as const,
    };
  },

  setFormDirty(isDirty: boolean) {
    return {
      type: "SET_FORM_DIRTY" as const,
      payload: { isDirty },
    };
  },

  // CRUD Operations - Remove duplicate action methods, keep only resolvers
  // createSubscriptionPlan, updateSubscriptionPlan, deleteSubscriptionPlan,
  // duplicateSubscriptionPlan, sortSubscriptionPlans are handled by resolvers

  // Sorting
  sortSubscriptionPlans(planOrder: number[]) {
    return {
      type: "SORT_SUBSCRIPTION_PLANS" as const,
      payload: { planOrder },
    };
  },
};

// ============================================================================
// SELECTORS
// ============================================================================

const selectors = {
  // Plan Management
  getSubscriptionPlans(state: State) {
    return state.plans;
  },

  getSelectedPlan(state: State) {
    return state.selectedPlan;
  },

  getSubscriptionPlansLoading(state: State) {
    return state.operations.isLoading;
  },

  getSubscriptionPlansError(state: State) {
    return state.operations.error;
  },

  // Form Management
  getFormMode(state: State) {
    return state.formState.mode;
  },

  getFormData(state: State) {
    return state.formState.data;
  },

  getFormDirty(state: State) {
    return state.formState.isDirty;
  },

  // Sorting
  getSortingLoading(state: State) {
    return state.sorting.isReordering;
  },

  getSortingError(state: State) {
    return state.sorting.error;
  },
};

// ============================================================================
// RESOLVERS
// ============================================================================

const resolvers = {
  // Plan Management
  *getSubscriptionPlans(): Generator<unknown, SubscriptionPlansResponse, any> {
    try {
      const courseId = yield select("core/editor").getCurrentPostId();
      const postType = yield select("core/editor").getCurrentPostType();

      if (!courseId) {
        throw createCurriculumError(
          "No course ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "getSubscriptionPlans",
          "Failed to get course ID"
        );
      }

      // Dispatch start action
      // Cache guard: if already loaded for this course and not loading, avoid refetch
      const alreadyLoadedForCourse =
        (yield select("tutorpress/subscriptions")).getSubscriptionPlans()?.length > 0 &&
        (yield select("tutorpress/subscriptions")).getState?.()?.lastLoadedCourseId === courseId;
      if (alreadyLoadedForCourse) {
        return { success: true, data: (yield select("tutorpress/subscriptions")).getSubscriptionPlans() } as any;
      }

      yield { type: "FETCH_SUBSCRIPTION_PLANS_START", payload: { courseId } };

      // Determine the correct endpoint based on post type (via request builder)
      const response = yield {
        type: "API_FETCH",
        request: buildFetchRequest(courseId, postType === "course-bundle" ? "course-bundle" : "course"),
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to fetch subscription plans",
          CurriculumErrorCode.FETCH_FAILED,
          "getSubscriptionPlans",
          "API Error"
        );
      }

      // Dispatch success action
      yield { type: "FETCH_SUBSCRIPTION_PLANS_SUCCESS", payload: { plans: response.data, courseId } };

      return response;
    } catch (error: any) {
      // Dispatch error action
      yield {
        type: "FETCH_SUBSCRIPTION_PLANS_ERROR",
        payload: { error: error.message },
      };
      throw error;
    }
  },

  // CRUD Operations
  *createSubscriptionPlan(
    planData: CreateSubscriptionPlanData
  ): Generator<unknown, CreateSubscriptionPlanResponse, any> {
    try {
      // Get object ID and post type from current post
      const objectId = yield select("core/editor").getCurrentPostId();
      const postType = yield select("core/editor").getCurrentPostType();

      if (!objectId) {
        throw createCurriculumError(
          "No object ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "createSubscriptionPlan",
          "Failed to get object ID"
        );
      }

      // Dispatch start action
      yield {
        type: "CREATE_SUBSCRIPTION_PLAN_START",
        payload: { planData },
      };

      // Use object_id consistently for both courses and bundles
      const requestData = { ...planData, object_id: objectId };

      const response = yield {
        type: "API_FETCH",
        request: buildCreateRequest(objectId, planData),
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to create subscription plan",
          CurriculumErrorCode.CREATE_FAILED,
          "createSubscriptionPlan",
          "API Error"
        );
      }

      // Dispatch success action
      yield {
        type: "CREATE_SUBSCRIPTION_PLAN_SUCCESS",
        payload: { plan: response.data },
      };

      return response;
    } catch (error: any) {
      // Dispatch error action
      yield {
        type: "CREATE_SUBSCRIPTION_PLAN_ERROR",
        payload: { error: error.message },
      };
      throw error;
    }
  },

  *updateSubscriptionPlan(
    planId: number,
    planData: UpdateSubscriptionPlanData
  ): Generator<unknown, UpdateSubscriptionPlanResponse, any> {
    try {
      // Get object ID and post type from current post
      const objectId = yield select("core/editor").getCurrentPostId();
      const postType = yield select("core/editor").getCurrentPostType();

      if (!objectId) {
        throw createCurriculumError(
          "No object ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "updateSubscriptionPlan",
          "Failed to get object ID"
        );
      }

      // Dispatch start action
      yield {
        type: "UPDATE_SUBSCRIPTION_PLAN_START",
        payload: { planId, planData },
      };

      // Use object_id consistently for both courses and bundles
      const requestData = { ...planData, object_id: objectId };

      const response = yield {
        type: "API_FETCH",
        request: buildUpdateRequest(planId, requestData),
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to update subscription plan",
          CurriculumErrorCode.UPDATE_FAILED,
          "updateSubscriptionPlan",
          "API Error"
        );
      }

      // Dispatch success action
      yield {
        type: "UPDATE_SUBSCRIPTION_PLAN_SUCCESS",
        payload: { plan: response.data },
      };

      return response;
    } catch (error: any) {
      // Dispatch error action
      yield {
        type: "UPDATE_SUBSCRIPTION_PLAN_ERROR",
        payload: { error: error.message },
      };
      throw error;
    }
  },

  *deleteSubscriptionPlan(planId: number): Generator<unknown, DeleteSubscriptionPlanResponse, any> {
    try {
      // Get object ID and post type from current post
      const objectId = yield select("core/editor").getCurrentPostId();
      const postType = yield select("core/editor").getCurrentPostType();

      if (!objectId) {
        throw createCurriculumError(
          "No object ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "deleteSubscriptionPlan",
          "Failed to get object ID"
        );
      }

      // Dispatch start action
      yield {
        type: "DELETE_SUBSCRIPTION_PLAN_START",
        payload: { planId },
      };

      // Use object_id consistently for both courses and bundles
      const requestData = { object_id: objectId };

      const response = yield {
        type: "API_FETCH",
        request: buildDeleteRequest(planId, objectId),
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to delete subscription plan",
          CurriculumErrorCode.DELETE_FAILED,
          "deleteSubscriptionPlan",
          "API Error"
        );
      }

      // Dispatch success action
      yield {
        type: "DELETE_SUBSCRIPTION_PLAN_SUCCESS",
        payload: { planId },
      };

      return response;
    } catch (error: any) {
      // Dispatch error action
      yield {
        type: "DELETE_SUBSCRIPTION_PLAN_ERROR",
        payload: { error: error.message },
      };
      throw error;
    }
  },

  *duplicateSubscriptionPlan(planId: number): Generator<unknown, DuplicateSubscriptionPlanResponse, any> {
    try {
      // Get object ID and post type from current post
      const objectId = yield select("core/editor").getCurrentPostId();
      const postType = yield select("core/editor").getCurrentPostType();

      if (!objectId) {
        throw createCurriculumError(
          "No object ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "duplicateSubscriptionPlan",
          "Failed to get object ID"
        );
      }

      // Dispatch start action
      yield {
        type: "DUPLICATE_SUBSCRIPTION_PLAN_START",
        payload: { planId },
      };

      // Use object_id consistently for both courses and bundles
      const requestData = { object_id: objectId };

      const response = yield {
        type: "API_FETCH",
        request: buildDuplicateRequest(planId, objectId),
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to duplicate subscription plan",
          CurriculumErrorCode.CREATE_FAILED,
          "duplicateSubscriptionPlan",
          "API Error"
        );
      }

      // Dispatch success action
      yield {
        type: "DUPLICATE_SUBSCRIPTION_PLAN_SUCCESS",
        payload: { plan: response.data },
      };

      return response;
    } catch (error: any) {
      // Dispatch error action
      yield {
        type: "DUPLICATE_SUBSCRIPTION_PLAN_ERROR",
        payload: { error: error.message },
      };
      throw error;
    }
  },

  // Sorting
  *sortSubscriptionPlans(planOrder: number[]): Generator<unknown, SortSubscriptionPlansResponse, any> {
    try {
      // Get object ID and post type from current post
      const objectId = yield select("core/editor").getCurrentPostId();
      const postType = yield select("core/editor").getCurrentPostType();

      if (!objectId) {
        throw createCurriculumError(
          "No object ID available",
          CurriculumErrorCode.VALIDATION_ERROR,
          "sortSubscriptionPlans",
          "Failed to get object ID"
        );
      }

      // Dispatch start action
      yield {
        type: "SORT_SUBSCRIPTION_PLANS_START",
        payload: { planOrder },
      };

      // Determine the correct endpoint based on post type
      const endpoint =
        postType === "course-bundle"
          ? `/tutorpress/v1/bundles/${objectId}/subscriptions/sort`
          : `/tutorpress/v1/courses/${objectId}/subscriptions/sort`;

      const response = yield {
        type: "API_FETCH",
        request: buildSortRequest(objectId, postType === "course-bundle" ? "course-bundle" : "course", planOrder),
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to sort subscription plans",
          CurriculumErrorCode.UPDATE_FAILED,
          "sortSubscriptionPlans",
          "API Error"
        );
      }

      // Dispatch success action
      yield {
        type: "SORT_SUBSCRIPTION_PLANS_SUCCESS",
      };

      return response;
    } catch (error: any) {
      // Dispatch error action
      yield {
        type: "SORT_SUBSCRIPTION_PLANS_ERROR",
        payload: { error: error.message },
      };
      throw error;
    }
  },
};

// ============================================================================
// STORE CREATION AND REGISTRATION
// ============================================================================

/**
 * Create the subscription store
 */
const subscriptionStore = createReduxStore("tutorpress/subscriptions", {
  reducer,
  actions: {
    ...actions,
    ...resolvers,
  },
  selectors,
  controls,
});

// Register the store with WordPress Data
register(subscriptionStore);

export { subscriptionStore };

// Export actions for external use
export const {
  fetchSubscriptionPlans,
  setSelectedPlan,
  setFormMode,
  setFormData,
  resetForm,
  setFormDirty,
  sortSubscriptionPlans,
} = actions;

// Export selectors for external use
export const {
  getSubscriptionPlans,
  getSelectedPlan,
  getSubscriptionPlansLoading,
  getSubscriptionPlansError,
  getFormMode,
  getFormData,
  getFormDirty,
  getSortingLoading,
  getSortingError,
} = selectors;

// Export types for external use
export type { SubscriptionState };
