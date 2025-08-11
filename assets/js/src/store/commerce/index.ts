/**
 * Commerce Store
 *
 * Dedicated feature store for WooCommerce/EDD product lists and product details.
 * Entity-prop remains the source of truth for pricing ids/fields; this store
 * only fetches lists/metadata and caches results (cache-aware, de-duped).
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { select } from "@wordpress/data";
import type { WcProduct, WcProductDetails } from "../../types/courses";

// Minimal EDD types (match usage in pricing panel and details endpoints)
export interface EddProduct {
  ID: string;
  post_title: string;
}

export interface EddProductDetails {
  name: string;
  regular_price: string;
  sale_price: string;
}

interface LoadingErrorState {
  isLoading: boolean;
  error: string | null;
}

interface CommerceState {
  wc: {
    products: WcProduct[];
    productDetailsById: Record<string, WcProductDetails>;
    state: LoadingErrorState;
  };
  edd: {
    products: EddProduct[];
    productDetailsById: Record<string, EddProductDetails>;
    state: LoadingErrorState;
  };
}

const initialState: CommerceState = {
  wc: {
    products: [],
    productDetailsById: {},
    state: { isLoading: false, error: null },
  },
  edd: {
    products: [],
    productDetailsById: {},
    state: { isLoading: false, error: null },
  },
};

const TYPES = {
  WC_SET_PRODUCTS: "WC_SET_PRODUCTS",
  WC_SET_DETAILS: "WC_SET_DETAILS",
  WC_SET_STATE: "WC_SET_STATE",
  EDD_SET_PRODUCTS: "EDD_SET_PRODUCTS",
  EDD_SET_DETAILS: "EDD_SET_DETAILS",
  EDD_SET_STATE: "EDD_SET_STATE",
} as const;

type Actions =
  | { type: typeof TYPES.WC_SET_PRODUCTS; payload: WcProduct[] }
  | { type: typeof TYPES.WC_SET_DETAILS; payload: { id: string; details: WcProductDetails } }
  | { type: typeof TYPES.WC_SET_STATE; payload: Partial<LoadingErrorState> }
  | { type: typeof TYPES.EDD_SET_PRODUCTS; payload: EddProduct[] }
  | { type: typeof TYPES.EDD_SET_DETAILS; payload: { id: string; details: EddProductDetails } }
  | { type: typeof TYPES.EDD_SET_STATE; payload: Partial<LoadingErrorState> };

const actionCreators = {
  wcSetProducts(products: WcProduct[]) {
    return { type: TYPES.WC_SET_PRODUCTS, payload: products };
  },
  wcSetDetails(id: string, details: WcProductDetails) {
    return { type: TYPES.WC_SET_DETAILS, payload: { id, details } };
  },
  wcSetState(state: Partial<LoadingErrorState>) {
    return { type: TYPES.WC_SET_STATE, payload: state };
  },
  eddSetProducts(products: EddProduct[]) {
    return { type: TYPES.EDD_SET_PRODUCTS, payload: products };
  },
  eddSetDetails(id: string, details: EddProductDetails) {
    return { type: TYPES.EDD_SET_DETAILS, payload: { id, details } };
  },
  eddSetState(state: Partial<LoadingErrorState>) {
    return { type: TYPES.EDD_SET_STATE, payload: state };
  },

  *fetchWooProducts(
    params: {
      course_id?: number;
      search?: string;
      per_page?: number;
      page?: number;
      exclude_linked_products?: boolean;
    } = {}
  ): Generator {
    const { course_id, search, per_page, page } = params || {};
    // If products already loaded and no search/page filters, avoid refetch (unless explicitly requesting include-linked)
    const existing: WcProduct[] = (yield select("tutorpress/commerce").getWooProducts()) as WcProduct[];
    if (!search && !page && existing && existing.length > 0 && params.exclude_linked_products !== false) {
      return existing;
    }

    yield actionCreators.wcSetState({ isLoading: true, error: null });
    try {
      const queryParams = new URLSearchParams();
      if (course_id) queryParams.append("course_id", String(course_id));
      if (search) queryParams.append("search", search);
      if (per_page) queryParams.append("per_page", String(per_page));
      if (page) queryParams.append("page", String(page));
      // Default: exclude linked products (can be turned off by passing exclude_linked_products: false)
      const excludeLinked = params.exclude_linked_products === false ? false : true;
      queryParams.append("exclude_linked_products", excludeLinked ? "true" : "false");

      const path = `/tutorpress/v1/woocommerce/products${queryParams.toString() ? `?${queryParams.toString()}` : ""}`;
      const response: { success?: boolean; data?: any } = (yield {
        type: "API_FETCH",
        request: { path, method: "GET" },
      }) as any;

      const data = response?.data;
      const list: WcProduct[] = Array.isArray(data)
        ? (data as WcProduct[])
        : Array.isArray(data?.products)
          ? (data.products as WcProduct[])
          : [];
      yield actionCreators.wcSetProducts(list);
      yield actionCreators.wcSetState({ isLoading: false, error: null });
      return list;
    } catch (error: any) {
      yield actionCreators.wcSetState({
        isLoading: false,
        error: error?.message || "Failed to fetch WooCommerce products",
      });
      throw error;
    }
  },

  *fetchWooProductDetails(productId: string | number, courseId?: number): Generator {
    if (!productId && productId !== 0) return null;
    const pid = String(productId);

    // Cache-aware: return existing details if available
    const existing: WcProductDetails | undefined = (yield select("tutorpress/commerce").getWooProductDetails(
      pid
    )) as any;
    if (existing) return existing;

    yield actionCreators.wcSetState({ isLoading: true, error: null });
    try {
      const query = new URLSearchParams();
      if (courseId) query.append("course_id", String(courseId));
      const path = `/tutorpress/v1/woocommerce/products/${pid}${query.toString() ? `?${query.toString()}` : ""}`;

      const response: { success?: boolean; data?: WcProductDetails } = (yield {
        type: "API_FETCH",
        request: { path, method: "GET" },
      }) as any;

      const details = (response && (response as any).data) || null;
      if (details) {
        yield actionCreators.wcSetDetails(pid, details);
      }
      yield actionCreators.wcSetState({ isLoading: false, error: null });
      return details;
    } catch (error: any) {
      yield actionCreators.wcSetState({
        isLoading: false,
        error: error?.message || "Failed to fetch WooCommerce product details",
      });
      throw error;
    }
  },

  *fetchEddProducts(
    params: {
      course_id?: number;
      search?: string;
      per_page?: number;
      page?: number;
      exclude_linked_products?: boolean;
    } = {}
  ): Generator {
    const { course_id, search, per_page, page } = params || {};
    const existing: EddProduct[] = (yield select("tutorpress/commerce").getEddProducts()) as EddProduct[];
    if (!search && !page && existing && existing.length > 0) {
      return existing;
    }

    yield actionCreators.eddSetState({ isLoading: true, error: null });
    try {
      const queryParams = new URLSearchParams();
      if (course_id) queryParams.append("course_id", String(course_id));
      if (search) queryParams.append("search", search);
      if (per_page) queryParams.append("per_page", String(per_page));
      if (page) queryParams.append("page", String(page));
      // Default: exclude linked products unless explicitly disabled
      const excludeLinked = params.exclude_linked_products === false ? false : true;
      queryParams.append("exclude_linked_products", excludeLinked ? "true" : "false");

      const path = `/tutorpress/v1/edd/products${queryParams.toString() ? `?${queryParams.toString()}` : ""}`;
      const response: { success?: boolean; data?: any } = (yield {
        type: "API_FETCH",
        request: { path, method: "GET" },
      }) as any;

      const data = response?.data;
      const list: EddProduct[] = Array.isArray(data)
        ? (data as EddProduct[])
        : Array.isArray(data?.products)
          ? (data.products as EddProduct[])
          : [];
      yield actionCreators.eddSetProducts(list);
      yield actionCreators.eddSetState({ isLoading: false, error: null });
      return list;
    } catch (error: any) {
      yield actionCreators.eddSetState({ isLoading: false, error: error?.message || "Failed to fetch EDD products" });
      throw error;
    }
  },

  *fetchEddProductDetails(productId: string | number, courseId?: number): Generator {
    if (!productId && productId !== 0) return null;
    const pid = String(productId);

    const existing: EddProductDetails | undefined = (yield select("tutorpress/commerce").getEddProductDetails(
      pid
    )) as any;
    if (existing) return existing;

    yield actionCreators.eddSetState({ isLoading: true, error: null });
    try {
      const query = new URLSearchParams();
      if (courseId) query.append("course_id", String(courseId));
      const path = `/tutorpress/v1/edd/products/${pid}${query.toString() ? `?${query.toString()}` : ""}`;

      const response: { success?: boolean; data?: EddProductDetails } = (yield {
        type: "API_FETCH",
        request: { path, method: "GET" },
      }) as any;

      const details = (response && (response as any).data) || null;
      if (details) {
        yield actionCreators.eddSetDetails(pid, details);
      }
      yield actionCreators.eddSetState({ isLoading: false, error: null });
      return details;
    } catch (error: any) {
      yield actionCreators.eddSetState({
        isLoading: false,
        error: error?.message || "Failed to fetch EDD product details",
      });
      throw error;
    }
  },
};

const selectors = {
  // WooCommerce
  getWooProducts(state: CommerceState): WcProduct[] {
    return state.wc.products;
  },
  getWooProductDetails(state: CommerceState, productId: string): WcProductDetails | undefined {
    return state.wc.productDetailsById[productId];
  },
  getWooLoading(state: CommerceState): boolean {
    return state.wc.state.isLoading;
  },
  getWooError(state: CommerceState): string | null {
    return state.wc.state.error;
  },

  // EDD
  getEddProducts(state: CommerceState): EddProduct[] {
    return state.edd.products;
  },
  getEddProductDetails(state: CommerceState, productId: string): EddProductDetails | undefined {
    return state.edd.productDetailsById[productId];
  },
  getEddLoading(state: CommerceState): boolean {
    return state.edd.state.isLoading;
  },
  getEddError(state: CommerceState): string | null {
    return state.edd.state.error;
  },
};

const store = createReduxStore("tutorpress/commerce", {
  reducer(state: CommerceState = initialState, action: Actions | { type: string }): CommerceState {
    switch (action.type) {
      case TYPES.WC_SET_PRODUCTS:
        return { ...state, wc: { ...state.wc, products: (action as any).payload } };
      case TYPES.WC_SET_DETAILS: {
        const { id, details } = (action as any).payload as { id: string; details: WcProductDetails };
        return {
          ...state,
          wc: {
            ...state.wc,
            productDetailsById: { ...state.wc.productDetailsById, [id]: details },
          },
        };
      }
      case TYPES.WC_SET_STATE:
        return { ...state, wc: { ...state.wc, state: { ...state.wc.state, ...(action as any).payload } } };

      case TYPES.EDD_SET_PRODUCTS:
        return { ...state, edd: { ...state.edd, products: (action as any).payload } };
      case TYPES.EDD_SET_DETAILS: {
        const { id, details } = (action as any).payload as { id: string; details: EddProductDetails };
        return {
          ...state,
          edd: {
            ...state.edd,
            productDetailsById: { ...state.edd.productDetailsById, [id]: details },
          },
        };
      }
      case TYPES.EDD_SET_STATE:
        return { ...state, edd: { ...state.edd, state: { ...state.edd.state, ...(action as any).payload } } };

      default:
        return state;
    }
  },
  actions: {
    ...actionCreators,
    fetchWooProducts: actionCreators.fetchWooProducts,
    fetchWooProductDetails: actionCreators.fetchWooProductDetails,
    fetchEddProducts: actionCreators.fetchEddProducts,
    fetchEddProductDetails: actionCreators.fetchEddProductDetails,
  },
  selectors,
  controls,
});

register(store);

export default store;
export const { fetchWooProducts, fetchWooProductDetails, fetchEddProducts, fetchEddProductDetails } =
  actionCreators as any;
export const {
  getWooProducts,
  getWooProductDetails,
  getWooLoading,
  getWooError,
  getEddProducts,
  getEddProductDetails,
  getEddLoading,
  getEddError,
} = selectors as any;
