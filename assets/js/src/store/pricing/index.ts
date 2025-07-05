/**
 * Pricing Store for TutorPress
 *
 * Dedicated store for course pricing operations, following H5P store architecture
 * for consistency and maintainability.
 *
 * @package TutorPress
 * @since 1.5.0
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls as dataControls } from "@wordpress/data-controls";
import { __ } from "@wordpress/i18n";
import type { CourseSettings } from "../../types/courses";

// ============================================================================
// STATE INTERFACE
// ============================================================================

/**
 * Pricing Store State Interface
 */
interface PricingState {
  /** Course pricing data */
  pricing: {
    pricing_type: "free" | "paid";
    price: number;
    sale_price: number;
    subscription_enabled: boolean;
    selling_option?: string;
    product_id?: string;
  };
  /** Operation states for different pricing operations */
  operationState: {
    status: "idle" | "loading" | "success" | "error";
    error?: string;
  };
}

// ============================================================================
// INITIAL STATE
// ============================================================================

const DEFAULT_STATE: PricingState = {
  pricing: {
    pricing_type: "free",
    price: 0,
    sale_price: 0,
    subscription_enabled: false,
    selling_option: undefined,
    product_id: undefined,
  },
  operationState: {
    status: "idle",
  },
};

// ============================================================================
// ACTION TYPES
// ============================================================================

export type PricingAction =
  // Pricing Data Actions
  | { type: "SET_PRICING_DATA"; payload: Partial<PricingState["pricing"]> }
  | { type: "SET_PRICING_TYPE"; payload: { pricing_type: "free" | "paid" } }
  | { type: "SET_PRICE"; payload: { price: number } }
  | { type: "SET_SALE_PRICE"; payload: { sale_price: number } }
  | { type: "SET_SUBSCRIPTION_ENABLED"; payload: { subscription_enabled: boolean } }
  | { type: "SET_SELLING_OPTION"; payload: { selling_option: string } }
  | { type: "SET_PRODUCT_ID"; payload: { product_id: string } }
  // Operation State Actions
  | { type: "SET_OPERATION_STATE"; payload: { status: "idle" | "loading" | "success" | "error"; error?: string } }
  | { type: "FETCH_PRICING_START" }
  | { type: "FETCH_PRICING_SUCCESS"; payload: { pricing: PricingState["pricing"] } }
  | { type: "FETCH_PRICING_ERROR"; payload: { error: string } }
  | { type: "UPDATE_PRICING_START" }
  | { type: "UPDATE_PRICING_SUCCESS"; payload: { pricing: PricingState["pricing"] } }
  | { type: "UPDATE_PRICING_ERROR"; payload: { error: string } };

// ============================================================================
// REDUCER
// ============================================================================

const reducer = (state = DEFAULT_STATE, action: PricingAction): PricingState => {
  switch (action.type) {
    // Pricing Data Actions
    case "SET_PRICING_DATA":
      return {
        ...state,
        pricing: {
          ...state.pricing,
          ...action.payload,
        },
      };

    case "SET_PRICING_TYPE":
      return {
        ...state,
        pricing: {
          ...state.pricing,
          pricing_type: action.payload.pricing_type,
        },
      };

    case "SET_PRICE":
      return {
        ...state,
        pricing: {
          ...state.pricing,
          price: action.payload.price,
        },
      };

    case "SET_SALE_PRICE":
      return {
        ...state,
        pricing: {
          ...state.pricing,
          sale_price: action.payload.sale_price,
        },
      };

    case "SET_SUBSCRIPTION_ENABLED":
      return {
        ...state,
        pricing: {
          ...state.pricing,
          subscription_enabled: action.payload.subscription_enabled,
        },
      };

    case "SET_SELLING_OPTION":
      return {
        ...state,
        pricing: {
          ...state.pricing,
          selling_option: action.payload.selling_option,
        },
      };

    case "SET_PRODUCT_ID":
      return {
        ...state,
        pricing: {
          ...state.pricing,
          product_id: action.payload.product_id,
        },
      };

    // Operation State Actions
    case "SET_OPERATION_STATE":
      return {
        ...state,
        operationState: {
          status: action.payload.status,
          error: action.payload.error,
        },
      };

    case "FETCH_PRICING_START":
      return {
        ...state,
        operationState: {
          status: "loading",
        },
      };

    case "FETCH_PRICING_SUCCESS":
      return {
        ...state,
        pricing: action.payload.pricing,
        operationState: {
          status: "success",
        },
      };

    case "FETCH_PRICING_ERROR":
      return {
        ...state,
        operationState: {
          status: "error",
          error: action.payload.error,
        },
      };

    case "UPDATE_PRICING_START":
      return {
        ...state,
        operationState: {
          status: "loading",
        },
      };

    case "UPDATE_PRICING_SUCCESS":
      return {
        ...state,
        pricing: action.payload.pricing,
        operationState: {
          status: "success",
        },
      };

    case "UPDATE_PRICING_ERROR":
      return {
        ...state,
        operationState: {
          status: "error",
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
  /**
   * Set pricing data
   */
  setPricingData(pricing: Partial<PricingState["pricing"]>) {
    return {
      type: "SET_PRICING_DATA" as const,
      payload: pricing,
    };
  },

  /**
   * Set pricing type (free/paid)
   */
  setPricingType(pricing_type: "free" | "paid") {
    return {
      type: "SET_PRICING_TYPE" as const,
      payload: { pricing_type },
    };
  },

  /**
   * Set regular price
   */
  setPrice(price: number) {
    return {
      type: "SET_PRICE" as const,
      payload: { price },
    };
  },

  /**
   * Set sale price
   */
  setSalePrice(sale_price: number) {
    return {
      type: "SET_SALE_PRICE" as const,
      payload: { sale_price },
    };
  },

  /**
   * Set subscription enabled status
   */
  setSubscriptionEnabled(subscription_enabled: boolean) {
    return {
      type: "SET_SUBSCRIPTION_ENABLED" as const,
      payload: { subscription_enabled },
    };
  },

  /**
   * Set selling option
   */
  setSellingOption(selling_option: string) {
    return {
      type: "SET_SELLING_OPTION" as const,
      payload: { selling_option },
    };
  },

  /**
   * Set product ID
   */
  setProductId(product_id: string) {
    return {
      type: "SET_PRODUCT_ID" as const,
      payload: { product_id },
    };
  },

  /**
   * Fetch pricing data for a course
   */
  fetchPricing(courseId: number) {
    return {
      type: "FETCH_PRICING" as const,
      payload: { courseId },
    };
  },

  /**
   * Update pricing data for a course
   */
  updatePricing(courseId: number, pricing: PricingState["pricing"]) {
    return {
      type: "UPDATE_PRICING" as const,
      payload: { courseId, pricing },
    };
  },
};

// ============================================================================
// CONTROLS
// ============================================================================

const controls = {
  /**
   * Fetch pricing data from REST API
   */
  async FETCH_PRICING({ payload }: { payload: { courseId: number } }) {
    const response = await fetch(`/wp-json/tutorpress/v1/courses/${payload.courseId}/pricing`, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": (window as any).wpApiSettings?.nonce || "",
      },
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch pricing: ${response.statusText}`);
    }

    const data = await response.json();
    return data;
  },

  /**
   * Update pricing data via REST API
   */
  async UPDATE_PRICING({ payload }: { payload: { courseId: number; pricing: PricingState["pricing"] } }) {
    const response = await fetch(`/wp-json/tutorpress/v1/courses/${payload.courseId}/pricing`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": (window as any).wpApiSettings?.nonce || "",
      },
      body: JSON.stringify(payload.pricing),
    });

    if (!response.ok) {
      throw new Error(`Failed to update pricing: ${response.statusText}`);
    }

    const data = await response.json();
    return data;
  },
};

// ============================================================================
// SELECTORS
// ============================================================================

const selectors = {
  /**
   * Get current pricing data
   */
  getPricing(state: PricingState) {
    return state.pricing;
  },

  /**
   * Get pricing type (free/paid)
   */
  getPricingType(state: PricingState) {
    return state.pricing.pricing_type;
  },

  /**
   * Get regular price
   */
  getPrice(state: PricingState) {
    return state.pricing.price;
  },

  /**
   * Get sale price
   */
  getSalePrice(state: PricingState) {
    return state.pricing.sale_price;
  },

  /**
   * Get subscription enabled status
   */
  getSubscriptionEnabled(state: PricingState) {
    return state.pricing.subscription_enabled;
  },

  /**
   * Get selling option
   */
  getSellingOption(state: PricingState) {
    return state.pricing.selling_option;
  },

  /**
   * Get product ID
   */
  getProductId(state: PricingState) {
    return state.pricing.product_id;
  },

  /**
   * Get operation state
   */
  getOperationState(state: PricingState) {
    return state.operationState;
  },

  /**
   * Check if pricing is loading
   */
  isPricingLoading(state: PricingState) {
    return state.operationState.status === "loading";
  },

  /**
   * Check if pricing has error
   */
  hasPricingError(state: PricingState) {
    return state.operationState.status === "error";
  },

  /**
   * Get pricing error
   */
  getPricingError(state: PricingState) {
    return state.operationState.error;
  },
};

// ============================================================================
// RESOLVERS
// ============================================================================

const resolvers = {
  /**
   * Fetch pricing data for a course
   */
  *fetchPricing(courseId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "FETCH_PRICING_START",
      };

      const data = yield {
        type: "FETCH_PRICING",
        payload: { courseId },
      };

      if ((data as any).success) {
        yield {
          type: "FETCH_PRICING_SUCCESS",
          payload: { pricing: (data as any).data },
        };
      } else {
        throw new Error((data as any).message || "Failed to fetch pricing");
      }
    } catch (error) {
      yield {
        type: "FETCH_PRICING_ERROR",
        payload: { error: error instanceof Error ? error.message : "Unknown error" },
      };
    }
  },

  /**
   * Update pricing data for a course
   */
  *updatePricing(courseId: number, pricing: PricingState["pricing"]): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "UPDATE_PRICING_START",
      };

      const data = yield {
        type: "UPDATE_PRICING",
        payload: { courseId, pricing },
      };

      if ((data as any).success) {
        yield {
          type: "UPDATE_PRICING_SUCCESS",
          payload: { pricing: (data as any).data },
        };
      } else {
        throw new Error((data as any).message || "Failed to update pricing");
      }
    } catch (error) {
      yield {
        type: "UPDATE_PRICING_ERROR",
        payload: { error: error instanceof Error ? error.message : "Unknown error" },
      };
    }
  },
};

// ============================================================================
// STORE REGISTRATION
// ============================================================================

const store = createReduxStore("tutorpress/pricing", {
  reducer,
  actions,
  controls,
  selectors,
  resolvers,
});

register(store);

export default store;
