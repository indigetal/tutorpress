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
import { isKnownQuizQuestionType } from "../../../../utils/quizQuestionTypes";

// Import all question components
export { TrueFalseQuestion } from "./TrueFalseQuestion";
export { MultipleChoiceQuestion } from "./MultipleChoiceQuestion";
export { OpenEndedQuestion } from "./OpenEndedQuestion";
export { ShortAnswerQuestion } from "./ShortAnswerQuestion";
export { OrderingQuestion } from "./OrderingQuestion";
export { ImageAnsweringQuestion } from "./ImageAnsweringQuestion";
export { MatchingQuestion } from "./MatchingQuestion";
export { FillInTheBlanksQuestion } from "./FillInTheBlanksQuestion";
export { ScaleQuestion } from "./ScaleQuestion";
export { CoordinatesQuestion } from "./CoordinatesQuestion";
export { DrawImageQuestion } from "./DrawImageQuestion";
export { PinImageQuestion } from "./PinImageQuestion";
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
import { ScaleQuestion } from "./ScaleQuestion";
import { CoordinatesQuestion } from "./CoordinatesQuestion";
import { DrawImageQuestion } from "./DrawImageQuestion";
import { PinImageQuestion } from "./PinImageQuestion";

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
 *
 *              Registration is deliberately explicit and is the sole authority on local
 *              editor availability: a type is not locally authorable until its component
 *              appears here. The Tutor 4.0 native types without a TutorPress editor yet,
 *              and `h5p`, are therefore absent on purpose. `h5p` stays out permanently
 *              because it is authored through the separate Interactive Quiz modal.
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
  scale: ScaleQuestion, // Range (Tutor 4.0 native)
  coordinates: CoordinatesQuestion, // Graph (Tutor 4.0 native)
  draw_image: DrawImageQuestion, // Draw Image (Tutor 4.0 native)
  pin_image: PinImageQuestion, // Pin Image (Tutor 4.0 native)
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

/**
 * Check whether TutorPress can author a question type locally
 *
 * @description A type is locally authorable only when TutorPress has metadata for the
 *              slug and a registered editor for it. This is a necessary condition, never
 *              a sufficient one: the server capability contract must also permit the
 *              type. Callers combine both, so metadata can only subtract capability.
 *
 * @param questionType The stored or selected question type slug
 * @returns True when a local editor exists for the type
 */
export const isLocallyAuthorable = (questionType: string): boolean => {
  return isKnownQuizQuestionType(questionType) && hasQuestionComponent(questionType);
};
