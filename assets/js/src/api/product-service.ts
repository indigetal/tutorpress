import { apiService } from "./service";

/**
 * Base Product interface
 */
export interface BaseProduct {
  ID: string;
  post_title: string;
}

/**
 * Base Product Details interface
 */
export interface BaseProductDetails {
  name: string;
  regular_price: string;
  sale_price: string;
  price: string;
  type: string;
  status: string;
}

/**
 * Base Products Response interface
 */
export interface BaseProductsResponse {
  products: BaseProduct[];
  total: number;
  total_pages: number;
  current_page: number;
  per_page: number;
}

/**
 * Product query parameters interface
 */
export interface ProductQueryParams {
  course_id?: number;
  search?: string;
  per_page?: number;
  page?: number;
}

/**
 * Generic Product Service class
 * Provides shared functionality for product-based APIs (WooCommerce, EDD)
 */
export class ProductService {
  private productType: "woocommerce" | "edd";

  constructor(productType: "woocommerce" | "edd") {
    this.productType = productType;
  }

  /**
   * Get products using product-specific endpoint
   */
  async getProducts(params: ProductQueryParams = {}): Promise<BaseProductsResponse> {
    try {
      const queryParams = this.buildQueryParams(params);
      const queryString = queryParams.toString();
      const path = `/${this.productType}/products${queryString ? `?${queryString}` : ""}`;

      const response = await apiService.get<BaseProductsResponse>(path);

      if (!response.data) {
        throw new Error(`No ${this.productType} products data received from server`);
      }

      return response.data;
    } catch (error) {
      console.error(`Error fetching ${this.productType} products:`, error);
      throw error;
    }
  }

  /**
   * Get product details using product-specific endpoint
   */
  async getProductDetails(productId: string, courseId?: number): Promise<BaseProductDetails> {
    try {
      const queryParams = new URLSearchParams();

      if (courseId) {
        queryParams.append("course_id", courseId.toString());
      }

      const queryString = queryParams.toString();
      const path = `/${this.productType}/products/${productId}${queryString ? `?${queryString}` : ""}`;

      const response = await apiService.get<BaseProductDetails>(path);

      if (!response.data) {
        throw new Error(`No ${this.productType} product details received from server`);
      }

      return response.data;
    } catch (error) {
      console.error(`Error fetching ${this.productType} product details:`, error);
      throw error;
    }
  }

  /**
   * Build query parameters for product requests
   */
  private buildQueryParams(params: ProductQueryParams): URLSearchParams {
    const queryParams = new URLSearchParams();

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

    return queryParams;
  }

  /**
   * Get the product type name for this service
   */
  getProductType(): string {
    return this.productType;
  }

  /**
   * Get the display name for this product type
   */
  getProductTypeDisplayName(): string {
    return this.productType === "woocommerce" ? "WooCommerce" : "EDD";
  }
}

/**
 * Create a ProductService instance for a specific product type
 */
export function createProductService(productType: "woocommerce" | "edd"): ProductService {
  return new ProductService(productType);
}

/**
 * WooCommerce-specific service instance
 */
export const wcService = createProductService("woocommerce");

/**
 * EDD-specific service instance
 */
export const eddService = createProductService("edd");

/**
 * Type aliases for backward compatibility and clarity
 */
export type WcProduct = BaseProduct;
export type WcProductDetails = BaseProductDetails;
export type WcProductsResponse = BaseProductsResponse;
export type EddProduct = BaseProduct;
export type EddProductDetails = BaseProductDetails;
export type EddProductsResponse = BaseProductsResponse;

/**
 * WooCommerce API functions (backward compatibility)
 */
export const getWcProducts = wcService.getProducts.bind(wcService);
export const getWcProductDetails = wcService.getProductDetails.bind(wcService);

/**
 * EDD API functions
 */
export const getEddProducts = eddService.getProducts.bind(eddService);
export const getEddProductDetails = eddService.getProductDetails.bind(eddService);

/**
 * WooCommerce API module (for backward compatibility)
 */
export const woocommerce = {
  wcService,
  getWcProducts,
  getWcProductDetails,
};

/**
 * EDD API module
 */
export const edd = {
  eddService,
  getEddProducts,
  getEddProductDetails,
};

/**
 * Main product API export
 */
export const productApi = {
  // Services
  wcService,
  eddService,
  createProductService,

  // WooCommerce functions
  getWcProducts,
  getWcProductDetails,

  // EDD functions
  getEddProducts,
  getEddProductDetails,

  // Modules
  woocommerce,
  edd,
};

// Default export for backward compatibility
export default productApi;
