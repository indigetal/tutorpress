/**
 * Question Types Registry
 *
 * @description Central registry for all quiz question type components. Provides a clean
 *              interface for importing and using question components in the QuizModal.
 *              Extracted during Phase 2 refactoring to create a scalable system for
 *              adding new question types without modifying the main modal component.
 *
 * @features
 * - Centralized component exports
 * - Question type to component mapping
 * - Type-safe component registry
 * - Easy addition of new question types
 * - Consistent import interface
 *
 * @usage
 * import { TrueFalseQuestion, MultipleChoiceQuestion, QuestionComponentMap } from './questions';
 *
 * const QuestionComponent = QuestionComponentMap[question.question_type];
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

// Import component types for the registry
import { TrueFalseQuestion } from "./TrueFalseQuestion";
import { MultipleChoiceQuestion } from "./MultipleChoiceQuestion";

/**
 * Common props interface for all question components
 */
export interface QuestionComponentProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
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
export const QuestionComponentMap: Partial<Record<QuizQuestionType, QuestionComponent>> = {
  true_false: TrueFalseQuestion,
  multiple_choice: MultipleChoiceQuestion,
  // Future question types will be added here:
  // fill_in_the_blank: FillInTheBlankQuestion,
  // open_ended: OpenEndedQuestion,
  // short_answer: ShortAnswerQuestion,
  // matching: MatchingQuestion,
  // image_matching: ImageMatchingQuestion,
  // image_answering: ImageAnsweringQuestion,
  // ordering: OrderingQuestion,
  // h5p: H5pQuestion,
};

/**
 * Get the component for a specific question type
 *
 * @param questionType The question type to get component for
 * @returns The component or null if not found
 */
export const getQuestionComponent = (questionType: QuizQuestionType): QuestionComponent | null => {
  return QuestionComponentMap[questionType] || null;
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
