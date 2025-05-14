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
 * @returns The course ID from either the URL query parameter (course editor) or the parent topic's parent (lesson editor)
 */
export function useCourseId(): number | null {
  // Get the context from the localized script data
  const isLesson = (window as any).tutorPressCurriculum?.isLesson;

  // Get the post ID from the URL query parameter
  const postId = Number(new URLSearchParams(window.location.search).get("post"));

  // If we're in the course editor, return the post ID directly
  if (!isLesson) {
    return postId;
  }

  // If we're in the lesson editor, we need to get the course ID from the parent topic's parent
  const { courseId, operationState } = useSelect(
    (select) => ({
      courseId: select(curriculumStore).getCourseId(),
      operationState: select(curriculumStore).getOperationState(),
    }),
    []
  );
  const { fetchCourseId } = useDispatch(curriculumStore);

  useEffect(() => {
    if (!isLesson || !postId) {
      return;
    }

    fetchCourseId(postId);
  }, [isLesson, postId, fetchCourseId]);

  // Return null while loading or if no course ID is available
  if (operationState.status === "loading" || !courseId) {
    return null;
  }

  return courseId;
}
