/**
 * API-related type definitions
 *
 * Contains types specific to API responses and requests for TutorPress.
 */

import { Course } from "./courses";
import { BaseTopic, BaseContentItem, Topic, ContentItem } from "./curriculum";

/**
 * Standardized API response type for TutorPress frontend
 */
export interface APIResponse<T> {
  success: boolean;
  message?: string;
  data: T;
}

/**
 * Generic response type for TutorPress API endpoints (legacy)
 */
export interface TutorResponse<T> {
  status_code: number;
  message?: string;
  data: T;
}

/**
 * Error response from TutorPress API
 */
export interface TutorErrorResponse {
  status_code: number;
  message: string;
  data?: null;
}

/**
 * API-specific Types
 */

/**
 * API response for a topic
 */
export interface TopicResponse extends BaseTopic {
  course_id: number;
  content_items?: BaseContentItem[]; // Make optional since some endpoints use contents
  contents?: BaseContentItem[]; // Add contents as an alternative field
  menu_order: number;
}

/**
 * API response for a course
 */
export interface CourseResponse extends Omit<Course, "topics"> {
  topics: TopicResponse[];
}

/**
 * API request types
 */

/**
 * Request body for creating/updating a topic
 */
export interface TopicRequest {
  title: string;
  course_id: number;
  content?: string;
  menu_order?: number;
  summary?: string;
}

/**
 * Request body for updating topic order
 */
export interface UpdateTopicOrderRequest {
  course_id: number;
  topics: {
    topic_id: number;
    menu_order: number;
  }[];
}

/**
 * Request body for updating content order
 */
export interface UpdateContentOrderRequest {
  topic_id: number;
  contents: {
    content_id: number;
    order: number;
  }[];
}

/**
 * Type transformation utilities
 */

/**
 * Transform a TopicResponse to a Topic (UI format)
 */
export const transformTopicResponse = (response: TopicResponse): Topic => ({
  id: response.id,
  title: response.title,
  content: response.content,
  menu_order: response.menu_order,
  isCollapsed: false,
  contents: (response.content_items || response.contents || []).map(
    (item): ContentItem => ({
      ...item,
      topic_id: response.id,
      order: 0, // Default order, should be updated from API
    })
  ),
});

/**
 * Transform a CourseResponse to a Course (UI format)
 */
export const transformCourseResponse = (response: CourseResponse): Course => ({
  ...response,
  topics: response.topics.map(transformTopicResponse),
});
