/**
 * Quiz Question Components Registry
 *
 * @description Central registry for all question type components and shared question utilities.
 *              Maps question types to their corresponding React components for dynamic loading.
 *              Created during Phase 2 refactoring to enable modular question type architecture.
 *
 * @usage
 * import { getQuestionComponent, MultipleChoiceQuestion } from './questions';
 * const Component = getQuestionComponent('multiple_choice');
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React from "react";
import type { QuizQuestion, QuizQuestionType } from "../../../../types/quiz";

// Import all question components
export { TrueFalseQuestion } from "./TrueFalseQuestion";
export { MultipleChoiceQuestion } from "./MultipleChoiceQuestion";
export { OpenEndedQuestion } from "./OpenEndedQuestion";
export { ShortAnswerQuestion } from "./ShortAnswerQuestion";
export { OrderingQuestion } from "./OrderingQuestion";
export { ImageAnsweringQuestion } from "./ImageAnsweringQuestion";
export { MatchingQuestion } from "./MatchingQuestion";
export { FillInTheBlanksQuestion } from "./FillInTheBlanksQuestion";
export { SortableOption } from "./SortableOption";
export type { SortableOptionProps } from "./SortableOption";
export { OptionEditor } from "./OptionEditor";
export type { OptionEditorProps } from "./OptionEditor";
export { ValidationDisplay } from "./ValidationDisplay";
export type { ValidationError, ValidationSeverity, ValidationDisplayProps } from "./ValidationDisplay";

// Import component types for the registry
import { TrueFalseQuestion } from "./TrueFalseQuestion";
import { MultipleChoiceQuestion } from "./MultipleChoiceQuestion";
import { OpenEndedQuestion } from "./OpenEndedQuestion";
import { ShortAnswerQuestion } from "./ShortAnswerQuestion";
import { OrderingQuestion } from "./OrderingQuestion";
import { ImageAnsweringQuestion } from "./ImageAnsweringQuestion";
import { MatchingQuestion } from "./MatchingQuestion";
import { FillInTheBlanksQuestion } from "./FillInTheBlanksQuestion";

/**
 * Common props interface for all question components
 */
export interface QuestionComponentProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

/**
 * Question component type definition
 */
export type QuestionComponent = React.FC<QuestionComponentProps>;

/**
 * Registry mapping question types to their components
 *
 * @description This registry allows the QuizModal to dynamically render
 *              the appropriate component based on the question type without
 *              having to maintain a large switch statement.
 */
export const QuestionComponentMap = {
  true_false: TrueFalseQuestion,
  multiple_choice: MultipleChoiceQuestion,
  single_choice: MultipleChoiceQuestion, // Single choice uses the same component as multiple choice
  open_ended: OpenEndedQuestion, // Open Ended/Essay question component
  short_answer: ShortAnswerQuestion, // Short Answer question component
  ordering: OrderingQuestion, // Ordering question component
  image_answering: ImageAnsweringQuestion,
  matching: MatchingQuestion,
  image_matching: MatchingQuestion, // Image matching uses the same component as matching
  fill_in_the_blank: FillInTheBlanksQuestion,
  // Additional question types will be added here as they are implemented
  // h5p: H5PQuestion,
} as const;

/**
 * Get the component for a specific question type
 *
 * @param questionType The question type to get component for
 * @returns The component or null if not found
 */
export const getQuestionComponent = (questionType: QuizQuestionType): QuestionComponent | null => {
  return QuestionComponentMap[questionType as keyof typeof QuestionComponentMap] || null;
};

/**
 * Check if a question type has a dedicated component
 *
 * @param questionType The question type to check
 * @returns True if component exists, false otherwise
 */
export const hasQuestionComponent = (questionType: QuizQuestionType): boolean => {
  return questionType in QuestionComponentMap;
};
