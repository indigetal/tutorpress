import { apiService } from "./service";

/**
 * WooCommerce Product interface
 */
export interface WcProduct {
  ID: string;
  post_title: string;
}

/**
 * WooCommerce Product Details interface
 */
export interface WcProductDetails {
  name: string;
  regular_price: string;
  sale_price: string;
  price: string;
  type: string;
  status: string;
}

/**
 * WooCommerce Products Response interface
 */
export interface WcProductsResponse {
  products: WcProduct[];
  total: number;
  total_pages: number;
  current_page: number;
  per_page: number;
}

/**
 * Get WooCommerce products
 */
export const getWcProducts = async (
  params: {
    exclude_linked_products?: boolean;
    course_id?: number;
    search?: string;
    per_page?: number;
    page?: number;
  } = {}
): Promise<WcProductsResponse> => {
  try {
    const queryParams = new URLSearchParams();

    if (params.exclude_linked_products !== undefined) {
      queryParams.append("exclude_linked_products", params.exclude_linked_products.toString());
    }

    if (params.course_id) {
      queryParams.append("course_id", params.course_id.toString());
    }

    if (params.search) {
      queryParams.append("search", params.search);
    }

    if (params.per_page) {
      queryParams.append("per_page", params.per_page.toString());
    }

    if (params.page) {
      queryParams.append("page", params.page.toString());
    }

    const queryString = queryParams.toString();
    const path = `/woocommerce/products${queryString ? `?${queryString}` : ""}`;

    const response = await apiService.get<WcProductsResponse>(path);

    if (!response.data) {
      throw new Error("No products data received from server");
    }

    return response.data;
  } catch (error) {
    console.error("Error fetching WooCommerce products:", error);
    throw error;
  }
};

/**
 * Get WooCommerce product details
 */
export const getWcProductDetails = async (productId: string, courseId?: number): Promise<WcProductDetails> => {
  try {
    const queryParams = new URLSearchParams();

    if (courseId) {
      queryParams.append("course_id", courseId.toString());
    }

    const queryString = queryParams.toString();
    const path = `/woocommerce/products/${productId}${queryString ? `?${queryString}` : ""}`;

    const response = await apiService.get<WcProductDetails>(path);

    if (!response.data) {
      throw new Error("No product details received from server");
    }

    return response.data;
  } catch (error) {
    console.error("Error fetching WooCommerce product details:", error);
    throw error;
  }
};

/**
 * WooCommerce API service object
 */
export const wcService = {
  getProducts: getWcProducts,
  getProductDetails: getWcProductDetails,
};

export default wcService;
