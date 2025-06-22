/**
 * Type definitions
 *
 * Contains all type definitions needed for the Course Curriculum metabox implementation.
 */

export * from "./courses";
export * from "./api";
export * from "./assignments";
export * from "./lessons";
export * from "./wordpress";

// Export curriculum types (keeping existing exports)
export * from "./curriculum";

// Export quiz types with explicit re-exports to resolve conflicts
export type {
  QuizQuestionType,
  TimeUnit,
  FeedbackMode,
  QuestionLayoutView,
  QuestionOrder,
  DataStatus,
  QuizTimeLimit,
  QuizContentDripSettings,
  QuizSettings,
  QuizQuestionSettings,
  QuizQuestionOption,
  QuizQuestion,
  QuizForm,
  QuizDetails,
  QuizOperationState,
  QuizCreationState,
  QuizEditState,
  QuizDeletionState,
  QuizErrorCode,
  QuizError,
  QuizSaveRequest,
  QuizDetailsRequest,
  QuizDeleteRequest,
  QuizOperationResult,
  QuizValidationResult,
} from "./quiz";

// Export quiz functions
export {
  isValidQuizQuestion,
  isValidQuizDetails,
  createQuizError,
  getDefaultQuizSettings,
  getDefaultQuestionSettings,
} from "./quiz";

// Export quiz types that conflict with curriculum (use quiz versions)
export type { QuizApiOperation } from "./quiz";

// Export all Interactive Quiz types
export * from "./interactiveQuiz";

// Export H5P types
export * from "./h5p";

// Export Live Lessons types
export * from "./liveLessons";

// Export Content Drip types
export * from "./content-drip";
