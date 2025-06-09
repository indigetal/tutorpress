import { useDispatch, useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import { curriculumStore } from "../../store/curriculum";
import { CurriculumErrorCode } from "../../types/curriculum";
import { store as noticesStore } from "@wordpress/notices";

interface UseQuizzesProps {
  courseId?: number;
  topicId?: number;
}

interface UseQuizzesReturn {
  handleQuizEdit: (quizId: number) => void;
  handleQuizDuplicate: (quizId: number, topicId: number) => Promise<void>;
  handleQuizDelete: (quizId: number, topicId: number) => Promise<void>;
  isQuizDuplicating: boolean;
  isQuizDeleting: boolean;
  quizDuplicationState: any;
}

/**
 * Hook for managing quiz operations in the curriculum
 *
 * Follows the same seamless update pattern as topics:
 * - Uses window.wp.apiFetch for proper multisite URL handling
 * - Updates local state immediately after success
 * - Shows WordPress notices for feedback
 * - No page refreshes needed
 */
export const useQuizzes = ({ courseId, topicId }: UseQuizzesProps): UseQuizzesReturn => {
  const { setTopics, setQuizDuplicationState } = useDispatch(curriculumStore) as any;
  const { createNotice } = useDispatch(noticesStore);

  // Get state from store
  const { topics, quizDuplicationState } = useSelect(
    (select: any) => ({
      topics: select(curriculumStore).getTopics(),
      quizDuplicationState: select(curriculumStore).getQuizDuplicationState(),
    }),
    []
  );

  /**
   * Handle quiz edit - redirect to quiz editor
   */
  const handleQuizEdit = (quizId: number) => {
    const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
    const url = new URL("post.php", adminUrl);
    url.searchParams.append("post", quizId.toString());
    url.searchParams.append("action", "edit");
    window.location.href = url.toString();
  };

  /**
   * Update local topics state after quiz deletion (following topics pattern)
   */
  const updateTopicsAfterQuizDeletion = (deletedQuizId: number) => {
    setTopics((currentTopics: any[]) =>
      currentTopics.map((topic) => ({
        ...topic,
        // Filter out the deleted quiz from the contents array
        contents: (topic.contents || []).filter(
          (item: any) => !(item.type === "tutor_quiz" && item.id === deletedQuizId)
        ),
      }))
    );
  };

  /**
   * Update local topics state after quiz duplication (following topics pattern)
   */
  const updateTopicsAfterQuizDuplication = (newQuiz: any, targetTopicId: number) => {
    setTopics((currentTopics: any[]) =>
      currentTopics.map((topic) => {
        if (topic.id === targetTopicId) {
          return {
            ...topic,
            contents: [
              ...(topic.contents || []),
              {
                id: newQuiz.id,
                title: newQuiz.title,
                type: "tutor_quiz",
                menu_order: newQuiz.menu_order,
                status: newQuiz.status,
              },
            ],
          };
        }
        return topic;
      })
    );
  };

  /**
   * Handle quiz duplication with seamless UI updates (following topics pattern)
   */
  const handleQuizDuplicate = async (quizId: number, targetTopicId: number): Promise<void> => {
    try {
      // Set loading state (following topics pattern)
      setQuizDuplicationState({
        status: "duplicating",
        sourceQuizId: quizId,
        targetTopicId,
      });

      console.log("Duplicating quiz:", quizId, "to topic:", targetTopicId);

      // Use window.wp.apiFetch() for proper multisite URL handling (like topics do)
      const response = (await window.wp.apiFetch({
        path: `/tutorpress/v1/quizzes/${quizId}/duplicate`,
        method: "POST",
        data: {
          topic_id: targetTopicId,
        },
      })) as any;

      console.log("Quiz duplication result:", response);

      if (response.success && response.data) {
        // Update local state immediately (following topics pattern)
        updateTopicsAfterQuizDuplication(response.data, targetTopicId);

        // Set success state
        setQuizDuplicationState({
          status: "success",
          sourceQuizId: quizId,
          duplicatedQuizId: response.data.id,
          targetTopicId,
        });

        // Show success notice (following topics pattern)
        createNotice("success", __("Quiz duplicated successfully.", "tutorpress"), {
          type: "snackbar",
        });

        // Reset state after success (following topics pattern)
        setTimeout(() => {
          setQuizDuplicationState({ status: "idle" });
        }, 2000);
      } else {
        throw new Error(response.message || __("Failed to duplicate quiz", "tutorpress"));
      }
    } catch (error) {
      console.error("Error duplicating quiz:", error);

      let errorMessage = __("Failed to duplicate quiz. Please try again.", "tutorpress");
      if (error instanceof Error) {
        errorMessage = error.message;
      }

      // Set error state
      setQuizDuplicationState({
        status: "error",
        sourceQuizId: quizId,
        targetTopicId,
        error: {
          code: CurriculumErrorCode.DUPLICATE_FAILED,
          message: errorMessage,
          context: {
            action: "duplicate",
            details: errorMessage,
          },
        },
      });

      // Show error notice (following topics pattern)
      createNotice("error", errorMessage, {
        type: "snackbar",
      });
    }
  };

  /**
   * Handle quiz deletion with seamless UI updates (following topics pattern)
   */
  const handleQuizDelete = async (quizId: number, sourceTopicId: number): Promise<void> => {
    if (!window.confirm(__("Are you sure you want to delete this quiz? This action cannot be undone.", "tutorpress"))) {
      return;
    }

    try {
      console.log("Deleting quiz:", quizId);

      // Use window.wp.apiFetch() for proper multisite URL handling (like topics do)
      const response = (await window.wp.apiFetch({
        path: `/tutorpress/v1/quizzes/${quizId}`,
        method: "DELETE",
      })) as any;

      console.log("Quiz deletion result:", response);

      if (response.success) {
        // Update local state immediately (following topics pattern)
        updateTopicsAfterQuizDeletion(quizId);

        // Show success notice (following topics pattern)
        createNotice("success", __("Quiz deleted successfully", "tutorpress"), {
          type: "snackbar",
        });

        console.log("Quiz deleted successfully and topics updated");
      } else {
        throw new Error(response.message || __("Failed to delete quiz", "tutorpress"));
      }
    } catch (error) {
      console.error("Error deleting quiz:", error);

      let errorMessage = __("Failed to delete quiz. Please try again.", "tutorpress");
      if (error instanceof Error) {
        errorMessage = error.message;
      }

      // Show error notice (following topics pattern)
      createNotice("error", errorMessage, {
        type: "snackbar",
      });
    }
  };

  return {
    handleQuizEdit,
    handleQuizDuplicate,
    handleQuizDelete,
    isQuizDuplicating: quizDuplicationState.status === "duplicating",
    isQuizDeleting: false, // Could be enhanced with deletion state in store
    quizDuplicationState,
  };
};
