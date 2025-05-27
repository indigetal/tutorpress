import { apiService } from "./service";
import type { Lesson } from "../types/lessons";
import type { TutorResponse } from "./types";

/**
 * Get lessons for a topic
 */
export const getLessons = async (topicId: number): Promise<Lesson[]> => {
  const response = await apiService.get<Lesson[]>(`/lessons?topic_id=${topicId}`);
  return response.data;
};

/**
 * Create a new lesson
 */
export const createLesson = async (data: { title: string; content: string; topic_id: number }): Promise<Lesson> => {
  const response = await apiService.post<Lesson>("/lessons", data);
  return response.data;
};

/**
 * Update a lesson
 */
export const updateLesson = async (
  lessonId: number,
  data: Partial<{
    title: string;
    content: string;
    topic_id: number;
  }>
): Promise<Lesson> => {
  const response = await apiService.patch<Lesson>(`/lessons/${lessonId}`, data);
  return response.data;
};

/**
 * Delete a lesson
 */
export const deleteLesson = async (lessonId: number): Promise<void> => {
  await apiService.delete(`/lessons/${lessonId}`);
};

/**
 * Duplicate a lesson
 */
export const duplicateLesson = async (lessonId: number, topicId: number): Promise<Lesson> => {
  try {
    const response = await apiService.post<Lesson>(`/lessons/${lessonId}/duplicate`, {
      topic_id: topicId,
    });

    // Only throw if it's not a success message
    if (response.status_code !== 201 && !response.message.includes("successfully")) {
      throw new Error(response.message);
    }

    // Return the lesson data from the response
    return response.data;
  } catch (error) {
    // Only log if it's not a success message
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error duplicating lesson:", error);
    }
    throw error;
  }
};

/**
 * Get parent info for a lesson
 */
export const getParentInfo = async (lessonId: number): Promise<{ course_id: number; topic_id: number }> => {
  const response = await apiService.get<{ course_id: number; topic_id: number }>(`/lessons/${lessonId}/parent-info`);
  return response.data;
};
