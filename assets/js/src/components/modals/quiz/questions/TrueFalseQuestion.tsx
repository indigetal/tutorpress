/**
 * True/False Question Component
 *
 * @description Specialized component for True/False question type in quiz modal. Handles
 *              automatic answer creation, correct answer selection, and validation for
 *              True/False questions. Extracted from QuizModal during Phase 2 refactoring
 *              to create focused, reusable question type components.
 *
 * @features
 * - Automatic True/False answer generation
 * - Visual correct answer indication
 * - Click-to-select correct answer
 * - Validation error display
 * - Answer persistence and state management
 * - Tutor LMS compatibility
 *
 * @usage
 * <TrueFalseQuestion
 *   question={question}
 *   questionIndex={questionIndex}
 *   onQuestionUpdate={handleQuestionFieldUpdate}
 *   onSettingUpdate={handleQuestionSettingUpdate}
 *   isSaving={isSaving}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React, { useEffect, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { useQuestionValidation } from "../../../../hooks/quiz";
import { ValidationDisplay } from "./ValidationDisplay";
import type { QuizQuestion, QuizQuestionOption, DataStatus } from "../../../../types/quiz";

interface TrueFalseQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

export const TrueFalseQuestion: React.FC<TrueFalseQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
  onDeletedAnswerId,
}) => {
  // Track if we've already initialized True/False answers for this question
  const initializedRef = useRef<Set<number>>(new Set());

  /**
   * Ensure True/False answers exist using useEffect - similar to original pattern
   */
  useEffect(() => {
    // Only run if we haven't initialized this question yet
    if (initializedRef.current.has(question.question_id)) {
      return;
    }

    const existingAnswers = question.question_answers || [];
    const hasTrue = existingAnswers.some((answer) => answer.answer_title === "True");
    const hasFalse = existingAnswers.some((answer) => answer.answer_title === "False");

    // Only update if we're missing True or False answers
    if (!hasTrue || !hasFalse) {
      let answers = [...existingAnswers];

      // Add True answer if missing
      if (!hasTrue) {
        const trueAnswer: QuizQuestionOption = {
          answer_id: -(Date.now() + Math.floor(Math.random() * 1000) + 1),
          belongs_question_id: question.question_id,
          belongs_question_type: question.question_type,
          answer_title: "True",
          is_correct: "0",
          image_id: 0,
          image_url: "",
          answer_two_gap_match: "",
          answer_view_format: "",
          answer_order: 1,
          _data_status: "new",
        };
        answers.push(trueAnswer);
      }

      // Add False answer if missing
      if (!hasFalse) {
        const falseAnswer: QuizQuestionOption = {
          answer_id: -(Date.now() + Math.floor(Math.random() * 1000) + 2),
          belongs_question_id: question.question_id,
          belongs_question_type: question.question_type,
          answer_title: "False",
          is_correct: "0",
          image_id: 0,
          image_url: "",
          answer_two_gap_match: "",
          answer_view_format: "",
          answer_order: 2,
          _data_status: "new",
        };
        answers.push(falseAnswer);
      }

      // Update the question with new answers
      onQuestionUpdate(questionIndex, "question_answers", answers);

      // Mark this question as initialized
      initializedRef.current.add(question.question_id);
    } else {
      // Even if no update needed, mark as initialized
      initializedRef.current.add(question.question_id);
    }
  }, [question.question_id, question.question_answers.length, questionIndex, onQuestionUpdate]);

  /**
   * Handle True/False correct answer selection - following MultipleChoice pattern
   */
  const handleCorrectAnswerSelection = (selectedAnswerId: number) => {
    const existingAnswers = question.question_answers || [];

    const updatedAnswers = existingAnswers.map((answer: QuizQuestionOption) => ({
      ...answer,
      is_correct: (answer.answer_id === selectedAnswerId ? "1" : "0") as "0" | "1",
      _data_status: (answer._data_status === "new" ? "new" : "update") as DataStatus,
    }));

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
  };

  // Get existing answers - following MultipleChoice pattern
  const existingAnswers = question.question_answers || [];
  const trueAnswer = existingAnswers.find((answer: QuizQuestionOption) => answer.answer_title === "True");
  const falseAnswer = existingAnswers.find((answer: QuizQuestionOption) => answer.answer_title === "False");
  const correctAnswerId = existingAnswers.find((answer: QuizQuestionOption) => answer.is_correct === "1")?.answer_id;

  // Use centralized validation hook
  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);

  return (
    <div className="quiz-modal-true-false-content">
      <div className="quiz-modal-true-false-options">
        <div
          className={`quiz-modal-true-false-option ${correctAnswerId === trueAnswer?.answer_id ? "is-correct" : ""}`}
          onClick={() => !isSaving && handleCorrectAnswerSelection(trueAnswer?.answer_id || 0)}
        >
          {correctAnswerId === trueAnswer?.answer_id && <span className="quiz-modal-correct-indicator">✓</span>}
          <span className="quiz-modal-answer-text">{__("True", "tutorpress")}</span>
        </div>

        <div
          className={`quiz-modal-true-false-option ${correctAnswerId === falseAnswer?.answer_id ? "is-correct" : ""}`}
          onClick={() => !isSaving && handleCorrectAnswerSelection(falseAnswer?.answer_id || 0)}
        >
          {correctAnswerId === falseAnswer?.answer_id && <span className="quiz-modal-correct-indicator">✓</span>}
          <span className="quiz-modal-answer-text">{__("False", "tutorpress")}</span>
        </div>
      </div>

      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />
    </div>
  );
};
