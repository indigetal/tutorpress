/**
 * Quiz Hooks Index
 *
 * @description Central export file for all quiz-related custom hooks.
 *              This follows the established pattern from the curriculum hooks
 *              and provides a clean interface for importing quiz functionality.
 *
 * @usage import { useQuizForm } from '../../hooks/quiz';
 *
 * @package TutorPress
 * @subpackage Quiz/Hooks
 * @since 1.0.0
 */

export { useQuizForm } from "./useQuizForm";
export type { QuizFormState, UseQuizFormReturn } from "./useQuizForm";

// Additional hooks will be exported here as we extract them
// export { useQuestionManagement } from './useQuestionManagement';
// export { useQuestionValidation } from './useQuestionValidation';
// export { useImageManagement } from './useImageManagement';
