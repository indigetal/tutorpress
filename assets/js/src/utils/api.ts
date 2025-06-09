import {
  TutorResponse,
  TopicResponse,
  TopicRequest,
  UpdateTopicOrderRequest,
  UpdateContentOrderRequest,
} from "../types/api";
import { Topic } from "../types/curriculum";

/**
 * Get topics for a course
 */
export const getTopics = async (courseId: number): Promise<Topic[]> => {
  try {
    const response = (await window.wp.apiFetch({
      path: `/tutorpress/v1/topics?course_id=${courseId}`,
      method: "GET",
    })) as TutorResponse<TopicResponse[]>;

    if (response.status_code !== 200) {
      throw new Error(response.message);
    }

    return response.data.map((topic) => ({
      id: topic.id,
      title: topic.title,
      content: topic.content || "",
      menu_order: topic.menu_order || 0,
      isCollapsed: true,
      contents: (topic.content_items || []).map((item) => ({
        ...item,
        topic_id: topic.id,
        order: 0,
      })),
    }));
  } catch (error) {
    console.error("Error fetching topics:", error);
    throw error;
  }
};

/**
 * Create a new topic
 */
export const createTopic = async (data: TopicRequest): Promise<Topic> => {
  try {
    const response = (await window.wp.apiFetch({
      path: "/tutorpress/v1/topics",
      method: "POST",
      data,
    })) as TutorResponse<TopicResponse>;

    if (response.status_code !== 201) {
      throw new Error(response.message);
    }

    return {
      id: response.data.id,
      title: response.data.title,
      content: response.data.content || "",
      menu_order: response.data.menu_order || 0,
      isCollapsed: true,
      contents: (response.data.content_items || []).map((item) => ({
        ...item,
        topic_id: response.data.id,
        order: 0,
      })),
    };
  } catch (error) {
    console.error("Error creating topic:", error);
    throw error;
  }
};

/**
 * Update a topic
 */
export const updateTopic = async (topicId: number, data: Partial<TopicRequest>): Promise<Topic> => {
  try {
    const response = (await window.wp.apiFetch({
      path: `/tutorpress/v1/topics/${topicId}`,
      method: "PUT",
      data,
    })) as TutorResponse<TopicResponse>;

    if (response.status_code !== 200) {
      throw new Error(response.message);
    }

    return {
      id: response.data.id,
      title: response.data.title,
      content: response.data.content || "",
      menu_order: response.data.menu_order || 0,
      isCollapsed: true,
      contents: (response.data.content_items || []).map((item) => ({
        ...item,
        topic_id: response.data.id,
        order: 0,
      })),
    };
  } catch (error) {
    console.error("Error updating topic:", error);
    throw error;
  }
};

/**
 * Delete a topic
 */
export const deleteTopic = async (topicId: number): Promise<void> => {
  try {
    const response = (await window.wp.apiFetch({
      path: `/tutorpress/v1/topics/${topicId}`,
      method: "DELETE",
    })) as TutorResponse<null>;

    if (response.status_code !== 200) {
      throw new Error(response.message);
    }
  } catch (error) {
    console.error("Error deleting topic:", error);
    throw error;
  }
};

/**
 * Update topic order
 */
export const updateTopicOrder = async (data: UpdateTopicOrderRequest): Promise<void> => {
  try {
    const response = (await window.wp.apiFetch({
      path: "/tutorpress/v1/topics/order",
      method: "PUT",
      data,
    })) as TutorResponse<null>;

    if (response.status_code !== 200) {
      throw new Error(response.message);
    }
  } catch (error) {
    console.error("Error updating topic order:", error);
    throw error;
  }
};

/**
 * Update content order within a topic
 */
export const updateContentOrder = async (data: UpdateContentOrderRequest): Promise<void> => {
  try {
    const response = (await window.wp.apiFetch({
      path: "/tutorpress/v1/topics/content-order",
      method: "PUT",
      data,
    })) as TutorResponse<null>;

    if (response.status_code !== 200) {
      throw new Error(response.message);
    }
  } catch (error) {
    console.error("Error updating content order:", error);
    throw error;
  }
};
