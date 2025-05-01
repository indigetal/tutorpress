import { Topic, ContentItem, BaseContentItem } from "../types/curriculum";
import { TutorResponse, TopicResponse, TopicRequest, transformTopicResponse } from "../types/api";
import { apiService } from "./service";
import { TopicResponse as API_TopicResponse } from "../types/api";

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
 * Reorder topics within a course
 */
export const reorderTopics = async (courseId: number, topicIds: number[]): Promise<void> => {
  try {
    const response = await apiService.post("/topics/reorder", {
      course_id: courseId,
      topic_orders: topicIds.map((id, index) => ({
        id,
        order: index,
      })),
    });

    // Only throw if it's not a success message
    if (response.status_code !== 200 && !response.message.includes("successfully")) {
      throw new Error(response.message);
    }
  } catch (error) {
    // Only log if it's not a success message
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error reordering topics:", error);
    }
    throw error;
  }
};

/**
 * Duplicate a topic
 */
export const duplicateTopic = async (topicId: number, courseId: number): Promise<Topic> => {
  try {
    const response = await apiService.post<TopicResponse>(`/topics/${topicId}/duplicate`, {
      course_id: courseId,
    });

    // Only throw if it's not a success message
    if (response.status_code !== 201 && !response.message.includes("successfully")) {
      throw new Error(response.message);
    }

    return {
      id: response.data.id,
      title: response.data.title,
      content: response.data.content || "",
      menu_order: response.data.menu_order || 0,
      isCollapsed: false,
      contents: (response.data.content_items || []).map((item: BaseContentItem) => ({
        ...item,
        topic_id: response.data.id,
        order: 0,
      })),
    };
  } catch (error) {
    // Only log if it's not a success message
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error duplicating topic:", error);
    }
    throw error;
  }
};

/**
 * Create a new topic
 */
export const createTopic = async (data: TopicRequest): Promise<Topic> => {
  try {
    const response = await apiService.post<TopicResponse>("/topics", data);

    if (response.status_code !== 201 && !response.message.includes("successfully")) {
      throw new Error(response.message);
    }

    return {
      id: response.data.id,
      title: response.data.title,
      content: response.data.content || "",
      menu_order: response.data.menu_order || 0,
      isCollapsed: false,
      contents: (response.data.content_items || []).map((item: BaseContentItem) => ({
        ...item,
        topic_id: response.data.id,
        order: 0,
      })),
    };
  } catch (error) {
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error creating topic:", error);
    }
    throw error;
  }
};

/**
 * Update an existing topic
 */
export const updateTopic = async (topicId: number, data: Partial<TopicRequest>): Promise<Topic> => {
  try {
    const response = await apiService.patch<TopicResponse>(`/topics/${topicId}`, data);

    if (response.status_code !== 200 && !response.message.includes("successfully")) {
      throw new Error(response.message);
    }

    return {
      id: response.data.id,
      title: response.data.title,
      content: response.data.content || "",
      menu_order: response.data.menu_order || 0,
      isCollapsed: false,
      contents: (response.data.content_items || []).map((item: BaseContentItem) => ({
        ...item,
        topic_id: response.data.id,
        order: 0,
      })),
    };
  } catch (error) {
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error updating topic:", error);
    }
    throw error;
  }
};

/**
 * Delete a topic
 */
export const deleteTopic = async (topicId: number): Promise<void> => {
  try {
    const response = await apiService.delete<void>(`/topics/${topicId}`);

    if (response.status_code !== 200 && !response.message.includes("successfully")) {
      throw new Error(response.message);
    }
  } catch (error) {
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error deleting topic:", error);
    }
    throw error;
  }
};
