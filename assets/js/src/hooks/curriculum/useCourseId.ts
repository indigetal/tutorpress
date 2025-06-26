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
 * Hook to get the course ID in course editor, lesson editor, and assignment editor contexts
 * @returns The course ID from either:
 * - URL query parameter (course editor)
 * - Parent topic's parent (existing lesson/assignment editor)
 * - Topic's parent (new lesson/assignment with topic_id parameter)
 */
export function useCourseId(): number | null {
  // Get the root element data attributes
  const rootElement = document.getElementById("tutorpress-curriculum-builder");
  const postId = rootElement?.dataset.postId ? parseInt(rootElement.dataset.postId, 10) : 0;
  const postType = rootElement?.dataset.postType || "";
  const isLesson = postType === "lesson";
  const isAssignment = postType === "tutor_assignments";

  // Get the topic_id from the URL query parameters
  const urlParams = new URLSearchParams(window.location.search);
  const topicId = Number(urlParams.get("topic_id"));

  // Get the store state
  const { courseId, operationState } = useSelect(
    (select) => ({
      courseId: select(curriculumStore).getCourseId(),
      operationState: select(curriculumStore).getOperationState(),
    }),
    []
  );
  const { fetchCourseId, setCourseId } = useDispatch(curriculumStore);

  useEffect(() => {
    // If we're in the course editor, set the post ID as the course ID
    if (!isLesson && !isAssignment && postId && courseId === null) {
      setCourseId(postId);
      return;
    }

    // Only fetch if we don't already have a course ID and we're in a lesson/assignment
    if (!isLesson && !isAssignment) {
      return;
    }

    if (courseId === null) {
      // If we have a topic_id, use that to get the course ID
      // Otherwise use the lesson/assignment ID (postId) to get the course ID
      const idToUse = topicId || postId;
      if (idToUse) {
        fetchCourseId(idToUse);
      }
    }
  }, [isLesson, isAssignment, postId, topicId, fetchCourseId, courseId, setCourseId]);

  // Return null only if we're actively loading the course ID
  if (operationState.status === "loading") {
    return null;
  }

  return courseId;
}
