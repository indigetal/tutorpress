/**
 * Open Ended Question Component
 *
 * @description Component for Open Ended/Essay question type in quiz modal. Since
 *              open ended questions don't require answer options or special controls,
 *              this component primarily provides instructional content and validation.
 *              The main question fields (title, description, answer explanation) are
 *              handled by the parent QuizModal interface.
 *
 * @features
 * - Instructional content for essay questions
 * - Validation error display
 * - Simple, clean interface
 * - Full Tutor LMS compatibility
 *
 * @usage
 * <OpenEndedQuestion
 *   question={question}
 *   questionIndex={questionIndex}
 *   onQuestionUpdate={handleQuestionFieldUpdate}
 *   showValidationErrors={showValidationErrors}
 *   isSaving={isSaving}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React from "react";
import { __ } from "@wordpress/i18n";
import { ValidationDisplay } from "./ValidationDisplay";
import { useQuestionValidation } from "../../../../hooks/quiz";
import type { QuizQuestion } from "../../../../types/quiz";

interface OpenEndedQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void; // Not used but kept for interface consistency
}

export const OpenEndedQuestion: React.FC<OpenEndedQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  // Use centralized validation hook
  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);

  return (
    <div className="quiz-modal-open-ended-content">
      {/* Display validation errors */}
      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />

      {/* Informational notification box for open ended questions */}
      <div className="quiz-modal-notification quiz-modal-notification--info">
        <div className="quiz-modal-notification__icon">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path
              d="M10 0C4.48 0 0 4.48 0 10s4.48 10 10 10 10-4.48 10-10S15.52 0 10 0zm1 15H9v-6h2v6zm0-8H9V5h2v2z"
              fill="currentColor"
            />
          </svg>
        </div>
        <div className="quiz-modal-notification__content">
          {__(
            "Students will see a text area where they can type their essay response to this question. Character limits can be configured in quiz settings. Their answers will need to be manually reviewed and graded.",
            "tutorpress"
          )}
        </div>
      </div>
    </div>
  );
};
