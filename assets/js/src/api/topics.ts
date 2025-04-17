import apiFetch from "@wordpress/api-fetch";
import { Topic } from "../types/courses";
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
 * Get topics for a course
 */
export const getTopics = async (courseId: number): Promise<Topic[]> => {
  try {
    const response = await apiFetch<TutorResponse<TopicResponse[]>>({
      path: `/tutorpress/v1/topics?course_id=${courseId}`,
    });

    if (response.status_code !== 200) {
      throw new Error(response.message);
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
