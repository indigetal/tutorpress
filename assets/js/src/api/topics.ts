import apiFetch from "@wordpress/api-fetch";
import { Topic, ContentItem } from "../types/curriculum";
import { TutorResponse, TopicResponse, transformTopicResponse } from "../types/api";

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
interface TopicReorderRequest {
  course_id: number;
  topic_orders: Array<{
    id: number;
    order: number;
  }>;
}

/**
 * Topic duplicate request interface
 */
interface TopicDuplicateRequest {
  course_id: number;
}

/**
 * Get topics for a course
 */
export const getTopics = async (courseId: number): Promise<Topic[]> => {
  try {
    const response = await apiFetch<TutorResponse<TopicResponse[]>>({
      path: `/tutorpress/v1/topics?course_id=${courseId}`,
    });

    // A success message with status code 200 is expected
    // The message "Topics retrieved successfully" is not an error
    if (!response.data) {
      throw new Error("No topics data received from server");
    }

    return response.data.map(transformTopicResponse);
  } catch (error) {
    console.error("Error fetching topics:", error);
    throw error;
  }
};

/**
 * Reorder topics in a course
 *
 * @param courseId The ID of the course containing the topics
 * @param topicOrders Array of topic IDs and their new order positions
 * @returns Promise resolving to the updated array of topics
 * @throws Error if the request fails
 */
export const reorderTopics = async (
  courseId: number,
  topicOrders: Array<{ id: number; order: number }>
): Promise<TopicData[]> => {
  try {
    const payload: TopicReorderRequest = {
      course_id: courseId,
      topic_orders: topicOrders,
    };

    const response = await apiFetch<TutorResponse<TopicData[]>>({
      path: "/tutorpress/v1/topics/reorder",
      method: "POST",
      data: payload,
    });

    if (response.status_code !== 200) {
      throw new Error(response.message);
    }

    return response.data;
  } catch (error) {
    console.error("Error reordering topics:", error);
    throw error;
  }
};

/**
 * Transform TopicContent to ContentItem
 */
const transformTopicContent = (content: TopicContent, topicId: number): ContentItem => ({
  id: content.id,
  title: content.title,
  type: content.type,
  topic_id: topicId,
  order: content.menu_order,
});

/**
 * Duplicate a topic in a course
 */
export const duplicateTopic = async (courseId: number, topicId: number): Promise<Topic> => {
  try {
    const payload: TopicDuplicateRequest = {
      course_id: courseId,
    };

    const response = await apiFetch<TutorResponse<TopicResponse>>({
      path: `/tutorpress/v1/topics/${topicId}/duplicate`,
      method: "POST",
      data: payload,
    });

    if (!response.data || !response.data.id) {
      throw new Error("Invalid response data from server");
    }

    return transformTopicResponse(response.data);
  } catch (error) {
    // Only treat it as an error if it's not a success message
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error duplicating topic:", error);
    }
    throw error;
  }
};
