/**
 * Fill In The Blanks Question Component
 *
 * @description Component for Fill In The Blanks question type in quiz modal. Provides
 *              a specialized interface for creating questions with {dash} variables that
 *              students need to fill in. Uses answer_title for prompt content and
 *              answer_two_gap_match for the answers list separated by vertical bars.
 *
 * @features
 * - Prompt content editor with {dash} variable support
 * - Answers field with vertical bar separation (|)
 * - Helper text for both fields explaining usage
 * - Validation to ensure both fields are filled
 * - Single answer entry (unlike other question types with multiple options)
 * - No correct answer indicators (correctness is built into the answer pattern)
 * - Student preview mode with {dash} converted to underscores and answers in green
 * - Hover edit button in top right corner
 *
 * @usage
 * <FillInTheBlanksQuestion
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

import React, { useState } from "react";
import { __ } from "@wordpress/i18n";
import { ValidationDisplay } from "./ValidationDisplay";
import { useQuestionValidation } from "../../../../hooks/quiz";
import type { QuizQuestion, QuizQuestionOption, DataStatus } from "../../../../types/quiz";

interface FillInTheBlanksQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

export const FillInTheBlanksQuestion: React.FC<FillInTheBlanksQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
  onDeletedAnswerId,
}) => {
  // State for the text field contents - only used during editing
  const [promptContent, setPromptContent] = useState("");
  const [answersContent, setAnswersContent] = useState("");
  const [isEditing, setIsEditing] = useState(false);
  const [isHovering, setIsHovering] = useState(false);

  // Get existing answer data
  const existingOptions = question.question_answers || [];
  const existingAnswer = existingOptions.length > 0 ? existingOptions[0] : null;

  // Use centralized validation hook
  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);

  /**
   * Convert {dash} variables to underscores for student preview
   */
  const convertToStudentPreview = (text: string): string => {
    return text.replace(/\{dash\}/g, "____");
  };

  /**
   * Split answers by vertical bar and clean them up
   */
  const getAnswersList = (answersText: string): string[] => {
    return answersText
      .split("|")
      .map((answer) => answer.trim())
      .filter((answer) => answer.length > 0);
  };

  /**
   * Handle starting the edit mode
   */
  const handleStartEditing = () => {
    // Initialize with existing data when starting to edit
    setPromptContent(existingAnswer?.answer_title || "");
    setAnswersContent(existingAnswer?.answer_two_gap_match || "");
    setIsEditing(true);
  };

  /**
   * Handle saving the fill in the blanks content
   */
  const handleSave = () => {
    if (!promptContent.trim() || !answersContent.trim()) {
      return;
    }

    let updatedAnswers = [...existingOptions];

    if (existingAnswer) {
      // Update existing answer
      updatedAnswers[0] = {
        ...updatedAnswers[0],
        answer_title: promptContent.trim(),
        answer_two_gap_match: answersContent.trim(),
        _data_status: updatedAnswers[0]._data_status === "new" ? "new" : "update",
      };
    } else {
      // Create new answer
      const newAnswer: QuizQuestionOption = {
        answer_id: -(Date.now() + Math.floor(Math.random() * 1000)),
        belongs_question_id: question.question_id,
        belongs_question_type: question.question_type,
        answer_title: promptContent.trim(),
        is_correct: "0", // Fill in the blanks doesn't use traditional correct/incorrect
        image_id: 0,
        image_url: "",
        answer_two_gap_match: answersContent.trim(),
        answer_view_format: "",
        answer_order: 1,
        _data_status: "new",
      };
      updatedAnswers = [newAnswer];
    }

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
    setIsEditing(false);
  };

  /**
   * Handle canceling the edit mode
   */
  const handleCancel = () => {
    setIsEditing(false);
    setPromptContent("");
    setAnswersContent("");
  };

  /**
   * Check if save button should be disabled
   */
  const isSaveDisabled = isSaving || !promptContent.trim() || !answersContent.trim();

  return (
    <div className="quiz-modal-fill-blanks-content">
      {/* Display validation errors */}
      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />

      {/* Display Mode or Edit Mode */}
      {!isEditing ? (
        /* Display Mode */
        existingAnswer ? (
          <div
            className="quiz-modal-fill-blanks-preview"
            onMouseEnter={() => setIsHovering(true)}
            onMouseLeave={() => setIsHovering(false)}
            onClick={handleStartEditing}
          >
            {/* Edit Button - appears on hover */}
            {isHovering && (
              <button
                type="button"
                className="quiz-modal-fill-blanks-edit-btn"
                onClick={(e) => {
                  e.stopPropagation();
                  handleStartEditing();
                }}
              >
                {__("Edit", "tutorpress")}
              </button>
            )}

            {/* Student Preview - {dash} converted to underscores */}
            <div className="quiz-modal-fill-blanks-student-preview">
              <div className="quiz-modal-fill-blanks-question-text">
                {convertToStudentPreview(existingAnswer.answer_title)}
              </div>
            </div>

            {/* Answers List - displayed in green */}
            {existingAnswer.answer_two_gap_match && (
              <div className="quiz-modal-fill-blanks-answers">
                <span className="quiz-modal-fill-blanks-answers-text">{existingAnswer.answer_two_gap_match}</span>
              </div>
            )}
          </div>
        ) : (
          /* Empty State - Show Editor Immediately */
          <div className="quiz-modal-fill-blanks-editor">
            {/* Prompt Content Field */}
            <div className="quiz-modal-fill-blanks-field">
              <textarea
                className="quiz-modal-option-textarea"
                placeholder={__("Prompt content...", "tutorpress")}
                value={promptContent}
                onChange={(e) => setPromptContent(e.target.value)}
                rows={4}
                disabled={isSaving}
              />
              <div className="quiz-modal-fill-blanks-help-text">
                {__(
                  "Please make sure to use the variable {dash} in your question title to show the blanks in your question. You can use multiple {dash} variables in one question.",
                  "tutorpress"
                )}
              </div>
            </div>

            {/* Answers Field */}
            <div className="quiz-modal-fill-blanks-field">
              <textarea
                className="quiz-modal-option-textarea"
                placeholder={__("Answers (in order)", "tutorpress")}
                value={answersContent}
                onChange={(e) => setAnswersContent(e.target.value)}
                rows={3}
                disabled={isSaving}
              />
              <div className="quiz-modal-fill-blanks-help-text">
                {__(
                  "Separate multiple answers by a vertical bar |. 1 answer per {dash} variable is defined in the question. Example: Apple | Banana | Orange",
                  "tutorpress"
                )}
              </div>
            </div>

            {/* Ok Button - Only shows when both fields have content */}
            {promptContent.trim() && answersContent.trim() && (
              <div className="quiz-modal-fill-blanks-actions">
                <button
                  type="button"
                  className="quiz-modal-option-ok-btn"
                  onClick={handleSave}
                  disabled={isSaveDisabled}
                >
                  {__("Ok", "tutorpress")}
                </button>
              </div>
            )}
          </div>
        )
      ) : (
        /* Edit Mode */
        <div className="quiz-modal-fill-blanks-editor">
          {/* Prompt Content Field */}
          <div className="quiz-modal-fill-blanks-field">
            <textarea
              className="quiz-modal-option-textarea"
              placeholder={__("Prompt content...", "tutorpress")}
              value={promptContent}
              onChange={(e) => setPromptContent(e.target.value)}
              rows={4}
              disabled={isSaving}
              autoFocus
            />
            <div className="quiz-modal-fill-blanks-help-text">
              {__(
                "Please make sure to use the variable {dash} in your question title to show the blanks in your question. You can use multiple {dash} variables in one question.",
                "tutorpress"
              )}
            </div>
          </div>

          {/* Answers Field */}
          <div className="quiz-modal-fill-blanks-field">
            <textarea
              className="quiz-modal-option-textarea"
              placeholder={__("Answers (in order)", "tutorpress")}
              value={answersContent}
              onChange={(e) => setAnswersContent(e.target.value)}
              rows={3}
              disabled={isSaving}
            />
            <div className="quiz-modal-fill-blanks-help-text">
              {__(
                "Separate multiple answers by a vertical bar |. 1 answer per {dash} variable is defined in the question. Example: Apple | Banana | Orange",
                "tutorpress"
              )}
            </div>
          </div>

          {/* Action Buttons */}
          <div className="quiz-modal-option-editor-actions">
            <button type="button" className="quiz-modal-option-cancel-btn" onClick={handleCancel} disabled={isSaving}>
              {__("Cancel", "tutorpress")}
            </button>
            <button type="button" className="quiz-modal-option-ok-btn" onClick={handleSave} disabled={isSaveDisabled}>
              {__("Ok", "tutorpress")}
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
