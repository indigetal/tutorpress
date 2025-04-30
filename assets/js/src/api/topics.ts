import { Topic, ContentItem } from "../types/curriculum";
import {
  TutorResponse,
  TopicResponse,
  TopicData,
  TopicReorderRequest,
  TopicDuplicateRequest,
  transformTopicResponse,
  TopicContent,
} from "./types";
import { apiService } from "./service";

/**
 * Get topics for a course
 */
export const getTopics = async (courseId: number): Promise<Topic[]> => {
  try {
    const response = await apiService.get<TopicResponse[]>(`/topics?course_id=${courseId}`);

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

    const response = await apiService.post<TopicData[]>("/topics/reorder", payload);

    // Check if we have data and it's an array
    if (!Array.isArray(response.data)) {
      throw new Error("Invalid response data from server");
    }

    return response.data;
  } catch (error) {
    // Only treat it as an error if it's not a success message
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error reordering topics:", error);
    }
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

    const response = await apiService.post<TopicResponse>(`/topics/${topicId}/duplicate`, payload);

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
