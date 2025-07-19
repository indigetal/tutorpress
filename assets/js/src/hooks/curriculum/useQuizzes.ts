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
 * Refactored to use store resolvers following the established pattern:
 * - Uses store resolvers for all operations (deleteQuiz, duplicateQuiz)
 * - Store handles state updates, notices, and loading states
 * - Maintains seamless UI updates and user feedback
 * - No page refreshes needed
 */
export const useQuizzes = ({ courseId, topicId }: UseQuizzesProps): UseQuizzesReturn => {
  const { duplicateQuiz, deleteQuiz } = useDispatch(curriculumStore) as any;
  const { createNotice } = useDispatch(noticesStore);

  // Get state from store
  const { quizDuplicationState, quizState } = useSelect(
    (select: any) => ({
      quizDuplicationState: select(curriculumStore).getQuizDuplicationState(),
      quizState: select(curriculumStore).getQuizState(),
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
   * Handle quiz duplication using store resolver
   */
  const handleQuizDuplicate = async (quizId: number, targetTopicId: number): Promise<void> => {
    if (!courseId) {
      createNotice("error", __("Course ID is required for quiz duplication.", "tutorpress"), {
        type: "snackbar",
      });
      return;
    }

    try {
      console.log("Duplicating quiz:", quizId, "to topic:", targetTopicId);

      // Use store resolver for duplication
      const duplicatedQuiz = await duplicateQuiz(quizId, targetTopicId, courseId);

      console.log("Quiz duplication result:", duplicatedQuiz);

      // Store handles state updates and notices automatically
      // No manual state management needed
    } catch (error) {
      console.error("Error duplicating quiz:", error);
      // Store handles error notices automatically
    }
  };

  /**
   * Handle quiz deletion using store resolver
   */
  const handleQuizDelete = async (quizId: number, sourceTopicId: number): Promise<void> => {
    if (!window.confirm(__("Are you sure you want to delete this quiz? This action cannot be undone.", "tutorpress"))) {
      return;
    }

    try {
      console.log("Deleting quiz:", quizId);

      // Use store resolver for deletion
      await deleteQuiz(quizId);

      console.log("Quiz deleted successfully");
      // Store handles state updates and notices automatically
    } catch (error) {
      console.error("Error deleting quiz:", error);
      // Store handles error notices automatically
    }
  };

  return {
    handleQuizEdit,
    handleQuizDuplicate,
    handleQuizDelete,
    isQuizDuplicating: quizDuplicationState.status === "duplicating",
    isQuizDeleting: quizState.status === "deleting",
    quizDuplicationState,
  };
};
