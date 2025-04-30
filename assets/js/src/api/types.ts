import { Topic, ContentItem } from "../types/curriculum";

/**
 * Topic content item interface
 */
export interface TopicContent {
  id: number;
  title: string;
  type: "lesson" | "quiz" | "assignment";
  menu_order: number;
  status: string;
}

/**
 * Topic interface from API
 */
export interface TopicData {
  id: number;
  title: string;
  content: string;
  menu_order: number;
  status: string;
  contents: TopicContent[];
}

/**
 * Topic reorder request interface
 */
export interface TopicReorderRequest {
  course_id: number;
  topic_orders: Array<{
    id: number;
    order: number;
  }>;
}

/**
 * Topic duplicate request interface
 */
export interface TopicDuplicateRequest {
  course_id: number;
}

/**
 * Generic response type for TutorPress API endpoints
 */
export interface TutorResponse<T> {
  status_code: number;
  message: string;
  data: T;
}

/**
 * API response for a topic
 */
export interface TopicResponse extends Omit<Topic, "contents"> {
  course_id: number;
  content_items?: ContentItem[];
  contents?: ContentItem[];
  menu_order: number;
}

/**
 * Transform TopicContent to ContentItem
 */
export const transformTopicContent = (content: TopicContent, topicId: number): ContentItem => ({
  id: content.id,
  title: content.title,
  type: content.type,
  topic_id: topicId,
  order: content.menu_order,
});

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
