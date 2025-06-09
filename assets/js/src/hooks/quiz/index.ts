/**
 * Quiz Hooks - Central Export File
 *
 * @description Central export file for all quiz-related React hooks. Provides a single
 *              import point for quiz functionality including form management, validation,
 *              image management, and other quiz operations. Updated during Phase 2 refactoring
 *              to include extracted and organized hook modules.
 *
 * @package TutorPress
 * @subpackage Quiz/Hooks
 * @since 1.0.0
 */

// Form management hook
export { useQuizForm } from "./useQuizForm";
export type { UseQuizFormReturn, QuizFormState } from "./useQuizForm";

// Question validation hook
export { useQuestionValidation } from "./useQuestionValidation";
export type { QuestionValidationResult, QuizValidationResult, ValidationRule } from "./useQuestionValidation";

// Image management hook
export { useImageManagement } from "./useImageManagement";
export type {
  ImageData,
  MediaLibraryConfig,
  ImageSelectCallback,
  ImageRemoveCallback,
  UseImageManagementReturn,
} from "./useImageManagement";

// Additional hooks will be exported here as we extract them
// export { useQuestionManagement } from './useQuestionManagement';
