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

import React from "react";
import { __ } from "@wordpress/i18n";
import type { QuizQuestion, QuizQuestionOption, DataStatus } from "../../../../types/quiz";

interface TrueFalseQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
}

export const TrueFalseQuestion: React.FC<TrueFalseQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  /**
   * Ensure True/False answers exist for question
   */
  const ensureTrueFalseAnswers = (question: QuizQuestion): QuizQuestionOption[] => {
    let answers = [...question.question_answers];

    // Check if True answer exists
    let trueAnswer = answers.find((answer: QuizQuestionOption) => answer.answer_title === "True");
    if (!trueAnswer) {
      trueAnswer = {
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

    // Check if False answer exists
    let falseAnswer = answers.find((answer: QuizQuestionOption) => answer.answer_title === "False");
    if (!falseAnswer) {
      falseAnswer = {
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

    // Update question if answers were added
    if (answers.length !== question.question_answers.length) {
      const preservedStatus = question._data_status === "new" ? "new" : "update";

      onQuestionUpdate(questionIndex, "question_answers", answers);
      onQuestionUpdate(questionIndex, "_data_status", preservedStatus);
    }

    return answers;
  };

  /**
   * Handle True/False correct answer selection
   */
  const handleCorrectAnswerSelection = (selectedAnswerId: number) => {
    const updatedAnswers = question.question_answers.map((answer: QuizQuestionOption) => ({
      ...answer,
      is_correct: (answer.answer_id === selectedAnswerId ? "1" : "0") as "0" | "1",
      _data_status: (answer._data_status === "new" ? "new" : "update") as DataStatus,
    }));

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
  };

  // Ensure we have True/False answer options
  const trueFalseAnswers = ensureTrueFalseAnswers(question);
  const trueAnswer = trueFalseAnswers.find((answer: QuizQuestionOption) => answer.answer_title === "True");
  const falseAnswer = trueFalseAnswers.find((answer: QuizQuestionOption) => answer.answer_title === "False");
  const correctAnswerId = trueFalseAnswers.find((answer: QuizQuestionOption) => answer.is_correct === "1")?.answer_id;

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

      {!correctAnswerId && (
        <div className="quiz-modal-validation-error">
          <p>{__("Please select the correct answer (True or False)", "tutorpress")}</p>
        </div>
      )}
    </div>
  );
};
