import { useEffect } from "react";
import { useSelect, useDispatch } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";

interface ParentInfoResponse {
  success: boolean;
  message: string;
  data: {
    course_id: number;
    topic_id: number;
  };
}

/**
 * Hook to get the course ID in both course editor and lesson editor contexts
 * @returns The course ID from either:
 * - URL query parameter (course editor)
 * - Parent topic's parent (existing lesson editor)
 * - Topic's parent (new lesson with topic_id parameter)
 */
export function useCourseId(): number | null {
  // Get the context from the localized script data
  const isLesson = (window as any).tutorPressCurriculum?.isLesson;

  // Get the post ID and topic_id from the URL query parameters
  const urlParams = new URLSearchParams(window.location.search);
  const postId = Number(urlParams.get("post"));
  const topicId = Number(urlParams.get("topic_id"));

  // If we're in the course editor, return the post ID directly
  if (!isLesson) {
    return postId;
  }

  // If we're in the lesson editor, we need to get the course ID
  const { courseId, operationState } = useSelect(
    (select) => ({
      courseId: select(curriculumStore).getCourseId(),
      operationState: select(curriculumStore).getOperationState(),
    }),
    []
  );
  const { fetchCourseId } = useDispatch(curriculumStore);

  useEffect(() => {
    if (!isLesson) {
      return;
    }

    // If we have a topic_id, use that to get the course ID
    // Otherwise use the lesson ID (postId) to get the course ID
    const idToUse = topicId || postId;
    if (idToUse) {
      fetchCourseId(idToUse);
    }
  }, [isLesson, postId, topicId, fetchCourseId]);

  // Return null while loading or if no course ID is available
  if (operationState.status === "loading" || !courseId) {
    return null;
  }

  return courseId;
}
