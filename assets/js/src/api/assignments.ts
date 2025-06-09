import { apiService } from "./service";
import type { Assignment } from "../types";
import type { TutorResponse } from "./types";

/**
 * Get assignments for a topic
 */
export const getAssignments = async (topicId: number): Promise<Assignment[]> => {
  const response = await apiService.get<Assignment[]>(`/assignments?topic_id=${topicId}`);
  return response.data;
};

/**
 * Create a new assignment
 */
export const createAssignment = async (data: {
  title: string;
  content: string;
  topic_id: number;
}): Promise<Assignment> => {
  const response = await apiService.post<Assignment>("/assignments", data);
  return response.data;
};

/**
 * Update an assignment
 */
export const updateAssignment = async (
  assignmentId: number,
  data: Partial<{
    title: string;
    content: string;
    topic_id: number;
  }>
): Promise<Assignment> => {
  const response = await apiService.patch<Assignment>(`/assignments/${assignmentId}`, data);
  return response.data;
};

/**
 * Delete an assignment
 */
export const deleteAssignment = async (assignmentId: number): Promise<void> => {
  await apiService.delete(`/assignments/${assignmentId}`);
};

/**
 * Duplicate an assignment
 */
export const duplicateAssignment = async (assignmentId: number, topicId: number): Promise<Assignment> => {
  try {
    const response = await apiService.post<Assignment>(`/assignments/${assignmentId}/duplicate`, {
      topic_id: topicId,
    });

    // Only throw if it's not a success message
    if (response.status_code !== 201 && !response.message.includes("successfully")) {
      throw new Error(response.message);
    }

    // Return the assignment data from the response
    return response.data;
  } catch (error) {
    // Only log if it's not a success message
    if (error instanceof Error && !error.message.includes("successfully")) {
      console.error("Error duplicating assignment:", error);
    }
    throw error;
  }
};

/**
 * Get parent info for an assignment
 */
export const getParentInfo = async (assignmentId: number): Promise<{ course_id: number; topic_id: number }> => {
  const response = await apiService.get<{ course_id: number; topic_id: number }>(
    `/assignments/${assignmentId}/parent-info`
  );
  return response.data;
};
