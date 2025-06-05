import React from "react";
import { Button, SelectControl, Spinner, Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { QuizQuestion, QuizQuestionType } from "../../../types/quiz";

interface QuestionTypeOption {
  label: string;
  value: QuizQuestionType;
  is_pro: boolean;
}

interface QuestionListProps {
  questions: QuizQuestion[];
  selectedQuestionIndex: number | null;
  isAddingQuestion: boolean;
  selectedQuestionType: QuizQuestionType | null;
  questionTypes: QuestionTypeOption[];
  loadingQuestionTypes: boolean;
  formTitle: string;
  isSaving: boolean;
  onAddQuestion: () => void;
  onQuestionSelect: (index: number) => void;
  onQuestionTypeSelect: (type: QuizQuestionType) => void;
  onDeleteQuestion: (index: number) => void;
  onCancelAddQuestion: () => void;
  getQuestionTypeDisplayName: (type: QuizQuestionType) => string;
}

export const QuestionList: React.FC<QuestionListProps> = ({
  questions,
  selectedQuestionIndex,
  isAddingQuestion,
  selectedQuestionType,
  questionTypes,
  loadingQuestionTypes,
  formTitle,
  isSaving,
  onAddQuestion,
  onQuestionSelect,
  onQuestionTypeSelect,
  onDeleteQuestion,
  onCancelAddQuestion,
  getQuestionTypeDisplayName,
}) => {
  return (
    <div className="quiz-modal-questions-section">
      <div className="quiz-modal-questions-header">
        <h4>{__("Questions", "tutorpress")}</h4>
        <Button
          variant="primary"
          className="quiz-modal-add-question-btn"
          onClick={onAddQuestion}
          disabled={!formTitle.trim() || isSaving}
        >
          +
        </Button>
      </div>

      {/* Question Type Dropdown - Show when adding question - Tutor LMS Style */}
      {isAddingQuestion && (
        <div className="quiz-modal-question-type-section">
          <SelectControl
            label={__("Question Type", "tutorpress")}
            value={selectedQuestionType || ""}
            options={[
              { label: __("Select Question Type", "tutorpress"), value: "" },
              ...questionTypes.map((type) => ({
                label: type.is_pro ? `${type.label} ${__("(Pro)", "tutorpress")}` : type.label,
                value: type.value,
              })),
            ]}
            onChange={(value) => {
              if (value) {
                onQuestionTypeSelect(value as QuizQuestionType);
              }
            }}
            disabled={loadingQuestionTypes || isSaving}
            className="quiz-modal-question-type-select"
          />

          {loadingQuestionTypes && (
            <div className="quiz-modal-loading-question-types">
              <Spinner style={{ margin: "0 8px 0 0" }} />
              <span>{__("Loading question types...", "tutorpress")}</span>
            </div>
          )}

          <div className="quiz-modal-question-type-actions">
            <Button variant="secondary" isSmall onClick={onCancelAddQuestion}>
              {__("Cancel", "tutorpress")}
            </Button>
          </div>
        </div>
      )}

      {/* Questions List - Always visible, dropdown overlays when needed */}
      <div className="quiz-modal-questions-list">
        {!formTitle.trim() ? (
          <div className="quiz-modal-no-questions">
            <p>{__("Enter a quiz title to add questions.", "tutorpress")}</p>
          </div>
        ) : questions.length === 0 && !isAddingQuestion ? (
          <div className="quiz-modal-no-questions">
            <p>{__("No questions added yet. Click + to add your first question.", "tutorpress")}</p>
          </div>
        ) : (
          questions.map((question, index) => (
            <div
              key={question.question_id}
              className={`tutorpress-content-item quiz-modal-question-item ${
                selectedQuestionIndex === index ? "is-selected" : ""
              }`}
              onClick={() => onQuestionSelect(index)}
            >
              <div className="tutorpress-content-item-icon">
                <span className="quiz-modal-question-number item-icon">{index + 1}</span>
                <Icon icon="menu" className="drag-icon" />
              </div>
              <div className="quiz-modal-question-content">
                <div className="quiz-modal-question-title">
                  {question.question_title || `${__("Question", "tutorpress")} ${index + 1}`}
                </div>
                <div className="quiz-modal-question-type-badge">
                  {getQuestionTypeDisplayName(question.question_type)}
                </div>
              </div>
              <div className="tutorpress-content-item-actions">
                <Button
                  icon="admin-page"
                  label={__("Duplicate Question", "tutorpress")}
                  isSmall
                  variant="tertiary"
                  onClick={(e: React.MouseEvent<HTMLButtonElement>) => {
                    e.stopPropagation();
                    // TODO: Implement duplication in future step
                    console.log("Duplicate question:", index);
                  }}
                />
                <Button
                  icon="trash"
                  label={__("Delete Question", "tutorpress")}
                  isSmall
                  variant="tertiary"
                  onClick={(e: React.MouseEvent<HTMLButtonElement>) => {
                    e.stopPropagation();
                    onDeleteQuestion(index);
                  }}
                />
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
};
