/**
 * Quiz Question Details Tab Component
 *
 * @description Main tab component for the Question Details view in the quiz modal. Orchestrates
 *              the three-column layout containing quiz metadata, question list, question form,
 *              and question settings. Acts as a container component that delegates rendering
 *              to specialized child components. Extracted from QuizModal during Phase 1
 *              refactoring for better code organization.
 *
 * @features
 * - Three-column responsive layout
 * - Quiz title and description management
 * - Question list integration with QuestionList component
 * - Dynamic question form rendering based on selection
 * - Dynamic question settings rendering
 * - Success/error message display
 * - Topic context display
 *
 * @layout
 * Left: Quiz info + QuestionList component
 * Center: Dynamic question form (renderQuestionForm)
 * Right: Dynamic question settings (renderQuestionSettings)
 *
 * @usage
 * <QuestionDetailsTab
 *   formTitle={formTitle}
 *   questions={questions}
 *   renderQuestionForm={renderQuestionForm}
 *   renderQuestionSettings={renderQuestionSettings}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Components
 * @since 1.0.0
 */

import React from "react";
import { Notice, TextControl, TextareaControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { QuestionList } from "./QuestionList";
import type { QuizQuestion, QuizQuestionType } from "../../../types/quiz";

interface QuestionTypeOption {
  label: string;
  value: QuizQuestionType;
  is_pro: boolean;
}

interface QuestionDetailsTabProps {
  // Form state
  formTitle: string;
  formDescription: string;
  topicId?: number;

  // Questions state
  questions: QuizQuestion[];
  selectedQuestionIndex: number | null;
  isAddingQuestion: boolean;
  selectedQuestionType: QuizQuestionType | null;
  questionTypes: QuestionTypeOption[];
  loadingQuestionTypes: boolean;

  // UI state
  isSaving: boolean;
  saveSuccess: boolean;
  saveError: string | null;

  // Handlers
  onTitleChange: (value: string) => void;
  onDescriptionChange: (value: string) => void;
  onAddQuestion: () => void;
  onQuestionSelect: (index: number) => void;
  onQuestionTypeSelect: (type: QuizQuestionType) => void;
  onDeleteQuestion: (index: number) => void;
  onQuestionReorder: (items: Array<{ id: number; [key: string]: any }>) => void;
  onCancelAddQuestion: () => void;
  onSaveErrorDismiss: () => void;
  getQuestionTypeDisplayName: (type: QuizQuestionType) => string;

  // Content renderers
  renderQuestionForm: () => JSX.Element;
  renderQuestionSettings: () => JSX.Element;
}

export const QuestionDetailsTab: React.FC<QuestionDetailsTabProps> = ({
  formTitle,
  formDescription,
  topicId,
  questions,
  selectedQuestionIndex,
  isAddingQuestion,
  selectedQuestionType,
  questionTypes,
  loadingQuestionTypes,
  isSaving,
  saveSuccess,
  saveError,
  onTitleChange,
  onDescriptionChange,
  onAddQuestion,
  onQuestionSelect,
  onQuestionTypeSelect,
  onDeleteQuestion,
  onQuestionReorder,
  onCancelAddQuestion,
  onSaveErrorDismiss,
  getQuestionTypeDisplayName,
  renderQuestionForm,
  renderQuestionSettings,
}) => {
  return (
    <div className="quiz-modal-question-details">
      {/* Success/Error Messages */}
      {saveSuccess && (
        <Notice status="success" isDismissible={false}>
          {__("Quiz saved successfully! Updating curriculum...", "tutorpress")}
        </Notice>
      )}

      {saveError && (
        <Notice status="error" isDismissible={true} onRemove={onSaveErrorDismiss}>
          {saveError}
        </Notice>
      )}

      <div className="quiz-modal-three-column-layout">
        {/* Left Column: Quiz name, Question dropdown, Questions list */}
        <div className="quiz-modal-left-column">
          <div className="quiz-modal-quiz-info">
            <TextControl
              label={__("Quiz Title", "tutorpress")}
              value={formTitle}
              onChange={onTitleChange}
              placeholder={__("Enter quiz title...", "tutorpress")}
              disabled={isSaving}
            />

            <TextareaControl
              label={__("Quiz Description", "tutorpress")}
              value={formDescription}
              onChange={onDescriptionChange}
              placeholder={__("Enter quiz description...", "tutorpress")}
              rows={3}
              disabled={isSaving}
            />

            {topicId && <p className="quiz-modal-topic-context">{__("Topic ID: ", "tutorpress") + topicId}</p>}
          </div>

          <QuestionList
            questions={questions}
            selectedQuestionIndex={selectedQuestionIndex}
            isAddingQuestion={isAddingQuestion}
            selectedQuestionType={selectedQuestionType}
            questionTypes={questionTypes}
            loadingQuestionTypes={loadingQuestionTypes}
            formTitle={formTitle}
            isSaving={isSaving}
            onAddQuestion={onAddQuestion}
            onQuestionSelect={onQuestionSelect}
            onQuestionTypeSelect={onQuestionTypeSelect}
            onDeleteQuestion={onDeleteQuestion}
            onQuestionReorder={onQuestionReorder}
            onCancelAddQuestion={() => onCancelAddQuestion()}
            getQuestionTypeDisplayName={getQuestionTypeDisplayName}
          />
        </div>

        {/* Center Column: Contextual question form */}
        <div className="quiz-modal-center-column">
          <div className="quiz-modal-question-form">{renderQuestionForm()}</div>
        </div>

        {/* Right Column: Contextual question settings */}
        <div className="quiz-modal-right-column">
          <div className="quiz-modal-question-settings">
            <h4>{__("Question Settings", "tutorpress")}</h4>
            {renderQuestionSettings()}
          </div>
        </div>
      </div>
    </div>
  );
};
