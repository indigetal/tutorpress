/**
 * API-related type definitions
 *
 * Contains types specific to API responses and requests for TutorPress.
 */

import { BaseCourse, BaseTopic, BaseContentItem, Topic, ContentItem, Course } from "./courses";

/**
 * Generic response type for TutorPress API endpoints
 */
export interface TutorResponse<T> {
  status_code: number;
  message: string;
  data: T;
}

/**
 * API-specific Types
 */

/**
 * API response for a topic
 */
export interface TopicResponse extends BaseTopic {
  course_id: number;
  content_items: BaseContentItem[];
  order: number;
}

/**
 * API response for a course with topics
 */
export interface CourseResponse extends BaseCourse {
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
  order?: number;
}

/**
 * Request body for updating topic order
 */
export interface UpdateTopicOrderRequest {
  course_id: number;
  topics: {
    topic_id: number;
    order: number;
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
  ...response,
  isCollapsed: false,
  contents: response.content_items.map(
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
