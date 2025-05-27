import apiFetch from "@wordpress/api-fetch";
import { TutorResponse } from "./types";

/**
 * API service configuration
 */
interface ApiConfig {
  basePath: string;
  timeout?: number;
}

/**
 * Default configuration
 */
const defaultConfig: ApiConfig = {
  basePath: "/tutorpress/v1",
  timeout: 30000,
};

/**
 * API service class
 */
class ApiService {
  private config: ApiConfig;

  constructor(config: Partial<ApiConfig> = {}) {
    this.config = { ...defaultConfig, ...config };
  }

  /**
   * Make a GET request
   */
  async get<T>(path: string): Promise<TutorResponse<T>> {
    try {
      const response = await apiFetch<TutorResponse<T>>({
        path: `${this.config.basePath}${path}`,
      });
      return response;
    } catch (error) {
      console.error("API GET error:", error);
      throw error;
    }
  }

  /**
   * Make a POST request
   */
  async post<T>(path: string, data: Record<string, unknown> | object): Promise<TutorResponse<T>> {
    try {
      const response = await apiFetch<TutorResponse<T>>({
        path: `${this.config.basePath}${path}`,
        method: "POST",
        data,
      });
      return response;
    } catch (error) {
      console.error("API POST error:", error);
      throw error;
    }
  }

  /**
   * Make a PATCH request
   */
  async patch<T>(path: string, data: Record<string, unknown> | object): Promise<TutorResponse<T>> {
    try {
      const response = await apiFetch<TutorResponse<T>>({
        path: `${this.config.basePath}${path}`,
        method: "PATCH",
        data,
      });
      return response;
    } catch (error) {
      console.error("API PATCH error:", error);
      throw error;
    }
  }

  /**
   * Make a DELETE request
   */
  async delete<T>(path: string): Promise<TutorResponse<T>> {
    try {
      const response = await apiFetch<TutorResponse<T>>({
        path: `${this.config.basePath}${path}`,
        method: "DELETE",
      });
      return response;
    } catch (error) {
      console.error("API DELETE error:", error);
      throw error;
    }
  }
}

// Create and export a singleton instance
export const apiService = new ApiService();
