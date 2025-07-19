/**
 * Hook for managing lessons in a course curriculum
 *
 * Orchestrates lesson operations and coordinates with the curriculum store.
 * The store handles all state management, while this hook provides the
 * operations interface for components.
 */
import { useCallback } from "react";
import { LessonDuplicationState, CurriculumError, CurriculumErrorCode } from "../../types/curriculum";
import type { Lesson } from "../../types/lessons";

import { __ } from "@wordpress/i18n";
import { useDispatch, useSelect } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";

export interface UseLessonsOptions {
  courseId?: number;
  topicId?: number;
}

export interface UseLessonsReturn {
  // State
  lessonDuplicationState: LessonDuplicationState;

  // Lesson Operations
  handleLessonDuplicate: (lessonId: number, topicId: number) => Promise<void>;
  handleLessonDelete: (lessonId: number) => Promise<void>;

  // Computed
  isLessonDuplicating: boolean;
  lessonDuplicationError: CurriculumError | null;
}

/**
 * Hook for managing lesson operations
 */
export function useLessons({ courseId, topicId }: UseLessonsOptions): UseLessonsReturn {
  const { createNotice } = useDispatch(noticesStore);
  const { setLessonDuplicationState, deleteLesson, duplicateLesson } = useDispatch("tutorpress/curriculum");

  // Get lesson duplication state from store
  const lessonDuplicationState = useSelect((select) => {
    const curriculumSelectors = select("tutorpress/curriculum") as {
      getLessonDuplicationState: () => LessonDuplicationState;
    };
    return curriculumSelectors.getLessonDuplicationState();
  }, []);

  /** Handle lesson duplication with redirect to editor */
  const handleLessonDuplicate = useCallback(
    async (lessonId: number, targetTopicId: number) => {
      if (!courseId) {
        const errorMessage = __("Course ID not available to duplicate lesson.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
        return;
      }

      try {
        // Set duplication state to loading
        setLessonDuplicationState({
          status: "duplicating",
          sourceLessonId: lessonId,
        });

        // Call the store function to duplicate the lesson (returns lesson data)
        const duplicatedLesson = await duplicateLesson(lessonId, targetTopicId);

        // Set duplication state to success
        setLessonDuplicationState({
          status: "success",
          sourceLessonId: lessonId,
          duplicatedLessonId: duplicatedLesson.id,
        });

        // Show success notice
        createNotice("success", __("Lesson duplicated successfully. Redirecting to editor...", "tutorpress"), {
          type: "snackbar",
        });

        // Redirect to the duplicate lesson editor immediately
        const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
        const url = new URL("post.php", adminUrl);
        url.searchParams.append("post", duplicatedLesson.id.toString());
        url.searchParams.append("action", "edit");

        // Redirect immediately - no delay needed
        window.location.href = url.toString();
      } catch (error) {
        // Set duplication state to error
        setLessonDuplicationState({
          status: "error",
          error: {
            code: CurriculumErrorCode.DUPLICATE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to duplicate lesson.", "tutorpress"),
            context: {
              action: "duplicateLesson",
              details: `Failed to duplicate lesson ${lessonId}`,
            },
          },
          sourceLessonId: lessonId,
        });

        // Handle error
        const errorMessage = error instanceof Error ? error.message : __("Failed to duplicate lesson.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
      }
    },
    [courseId, createNotice, setLessonDuplicationState]
  );

  /** Handle lesson deletion */
  const handleLessonDelete = useCallback(
    async (lessonId: number) => {
      if (!courseId) {
        const errorMessage = __("Course ID not available to delete lesson.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
        return;
      }

      try {
        // Use the store resolver for lesson deletion (uses API_FETCH control type)
        await deleteLesson(lessonId);

        // Show success notice
        createNotice("success", __("Lesson deleted successfully.", "tutorpress"), {
          type: "snackbar",
        });
      } catch (error) {
        // Handle error
        const errorMessage = error instanceof Error ? error.message : __("Failed to delete lesson.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
      }
    },
    [courseId, createNotice, deleteLesson]
  );

  return {
    // State
    lessonDuplicationState,

    // Lesson Operations
    handleLessonDuplicate,
    handleLessonDelete,

    // Computed
    isLessonDuplicating: lessonDuplicationState.status === "duplicating",
    lessonDuplicationError: lessonDuplicationState.status === "error" ? lessonDuplicationState.error || null : null,
  };
}
