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
import { controls as wpControls } from "@wordpress/data-controls";
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

// ============================================================================
// STATE INTERFACE
// ============================================================================

/**
 * Subscription Store State Interface
 */
interface State extends SubscriptionState {}

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

  // CRUD Operations
  createSubscriptionPlan(planData: CreateSubscriptionPlanData) {
    return {
      type: "CREATE_SUBSCRIPTION_PLAN" as const,
      payload: { planData },
    };
  },

  updateSubscriptionPlan(planId: number, planData: UpdateSubscriptionPlanData) {
    return {
      type: "UPDATE_SUBSCRIPTION_PLAN" as const,
      payload: { planId, planData },
    };
  },

  deleteSubscriptionPlan(planId: number) {
    return {
      type: "DELETE_SUBSCRIPTION_PLAN" as const,
      payload: { planId },
    };
  },

  duplicateSubscriptionPlan(planId: number) {
    return {
      type: "DUPLICATE_SUBSCRIPTION_PLAN" as const,
      payload: { planId },
    };
  },

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
// CONTROLS
// ============================================================================

const controls = {
  // Plan Management
  *fetchSubscriptionPlans(courseId: number): Generator<unknown, SubscriptionPlansResponse, any> {
    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/subscriptions`,
          method: "GET",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to fetch subscription plans",
          CurriculumErrorCode.FETCH_FAILED,
          "fetchSubscriptionPlans",
          "API Error"
        );
      }

      return response;
    } catch (error: any) {
      throw createCurriculumError(
        error.message || "Failed to fetch subscription plans",
        CurriculumErrorCode.FETCH_FAILED,
        "fetchSubscriptionPlans",
        error.message
      );
    }
  },

  // CRUD Operations
  *createSubscriptionPlan(
    planData: CreateSubscriptionPlanData
  ): Generator<unknown, CreateSubscriptionPlanResponse, any> {
    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/subscriptions",
          method: "POST",
          data: planData,
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to create subscription plan",
          CurriculumErrorCode.CREATE_FAILED,
          "createSubscriptionPlan",
          "API Error"
        );
      }

      return response;
    } catch (error: any) {
      throw createCurriculumError(
        error.message || "Failed to create subscription plan",
        CurriculumErrorCode.CREATE_FAILED,
        "createSubscriptionPlan",
        error.message
      );
    }
  },

  *updateSubscriptionPlan(
    planId: number,
    planData: UpdateSubscriptionPlanData
  ): Generator<unknown, UpdateSubscriptionPlanResponse, any> {
    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/subscriptions/${planId}`,
          method: "PUT",
          data: planData,
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to update subscription plan",
          CurriculumErrorCode.UPDATE_FAILED,
          "updateSubscriptionPlan",
          "API Error"
        );
      }

      return response;
    } catch (error: any) {
      throw createCurriculumError(
        error.message || "Failed to update subscription plan",
        CurriculumErrorCode.UPDATE_FAILED,
        "updateSubscriptionPlan",
        error.message
      );
    }
  },

  *deleteSubscriptionPlan(planId: number): Generator<unknown, DeleteSubscriptionPlanResponse, any> {
    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/subscriptions/${planId}`,
          method: "DELETE",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to delete subscription plan",
          CurriculumErrorCode.DELETE_FAILED,
          "deleteSubscriptionPlan",
          "API Error"
        );
      }

      return response;
    } catch (error: any) {
      throw createCurriculumError(
        error.message || "Failed to delete subscription plan",
        CurriculumErrorCode.DELETE_FAILED,
        "deleteSubscriptionPlan",
        error.message
      );
    }
  },

  *duplicateSubscriptionPlan(planId: number): Generator<unknown, DuplicateSubscriptionPlanResponse, any> {
    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/subscriptions/${planId}/duplicate`,
          method: "POST",
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to duplicate subscription plan",
          CurriculumErrorCode.CREATE_FAILED,
          "duplicateSubscriptionPlan",
          "API Error"
        );
      }

      return response;
    } catch (error: any) {
      throw createCurriculumError(
        error.message || "Failed to duplicate subscription plan",
        CurriculumErrorCode.CREATE_FAILED,
        "duplicateSubscriptionPlan",
        error.message
      );
    }
  },

  // Sorting
  *sortSubscriptionPlans(planOrder: number[]): Generator<unknown, SortSubscriptionPlansResponse, any> {
    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/subscriptions/sort",
          method: "POST",
          data: { plan_order: planOrder },
        },
      };

      if (!response.success) {
        throw createCurriculumError(
          response.message || "Failed to sort subscription plans",
          CurriculumErrorCode.UPDATE_FAILED,
          "sortSubscriptionPlans",
          "API Error"
        );
      }

      return response;
    } catch (error: any) {
      throw createCurriculumError(
        error.message || "Failed to sort subscription plans",
        CurriculumErrorCode.UPDATE_FAILED,
        "sortSubscriptionPlans",
        error.message
      );
    }
  },
};

// ============================================================================
// RESOLVERS
// ============================================================================

const resolvers = {
  // Plan Management
  *getSubscriptionPlans(): Generator<unknown, SubscriptionPlansResponse, any> {
    const courseId = yield select("core/editor").getCurrentPostId();
    if (!courseId) {
      throw createCurriculumError(
        "No course ID available",
        CurriculumErrorCode.VALIDATION_ERROR,
        "getSubscriptionPlans",
        "Failed to get course ID"
      );
    }

    const response = yield controls.fetchSubscriptionPlans(courseId);
    return response;
  },

  // CRUD Operations
  *createSubscriptionPlan(
    planData: CreateSubscriptionPlanData
  ): Generator<unknown, CreateSubscriptionPlanResponse, any> {
    const response = yield controls.createSubscriptionPlan(planData);
    return response;
  },

  *updateSubscriptionPlan(
    planId: number,
    planData: UpdateSubscriptionPlanData
  ): Generator<unknown, UpdateSubscriptionPlanResponse, any> {
    const response = yield controls.updateSubscriptionPlan(planId, planData);
    return response;
  },

  *deleteSubscriptionPlan(planId: number): Generator<unknown, DeleteSubscriptionPlanResponse, any> {
    const response = yield controls.deleteSubscriptionPlan(planId);
    return response;
  },

  *duplicateSubscriptionPlan(planId: number): Generator<unknown, DuplicateSubscriptionPlanResponse, any> {
    const response = yield controls.duplicateSubscriptionPlan(planId);
    return response;
  },

  // Sorting
  *sortSubscriptionPlans(planOrder: number[]): Generator<unknown, SortSubscriptionPlansResponse, any> {
    const response = yield controls.sortSubscriptionPlans(planOrder);
    return response;
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
  createSubscriptionPlan,
  updateSubscriptionPlan,
  deleteSubscriptionPlan,
  duplicateSubscriptionPlan,
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
