/**
 * Quiz Question List Component
 *
 * @description Sidebar component for managing quiz questions. Handles question creation workflow
 *              with type selection dropdown, displays existing questions with drag handles, and
 *              provides question management actions. Extracted from QuizModal during Phase 1
 *              refactoring to create focused, reusable components.
 *
 * @features
 * - Add Question button with type selection dropdown
 * - Question type loading from Tutor LMS
 * - Question list with selection highlighting
 * - Question type badges and numbering
 * - Duplicate and delete actions per question
 * - Form validation integration (requires quiz title)
 * - Empty state handling
 *
 * @usage
 * <QuestionList
 *   questions={questions}
 *   selectedQuestionIndex={selectedQuestionIndex}
 *   questionTypes={questionTypes}
 *   onQuestionSelect={handleQuestionSelect}
 *   onDeleteQuestion={handleDeleteQuestion}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Components
 * @since 1.0.0
 */

import React, { useState } from "react";
import { Button, SelectControl, Spinner, Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { DndContext, useSensor, useSensors, PointerSensor } from "@dnd-kit/core";
import { SortableContext, verticalListSortingStrategy, useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { createQuizDragHandlers } from "../../../components/common";
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
  onQuestionReorder: (items: Array<{ id: number; [key: string]: any }>) => void;
  onCancelAddQuestion: () => void;
  getQuestionTypeDisplayName: (type: QuizQuestionType) => string;
}

interface SortableQuestionItemProps {
  question: QuizQuestion;
  index: number;
  isSelected: boolean;
  onQuestionSelect: (index: number) => void;
  onDeleteQuestion: (index: number) => void;
  getQuestionTypeDisplayName: (type: QuizQuestionType) => string;
}

const SortableQuestionItem: React.FC<SortableQuestionItemProps> = ({
  question,
  index,
  isSelected,
  onQuestionSelect,
  onDeleteQuestion,
  getQuestionTypeDisplayName,
}) => {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: question.question_id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={`tutorpress-content-item quiz-modal-question-item ${isSelected ? "is-selected" : ""}`}
      onClick={() => onQuestionSelect(index)}
    >
      <div className="tutorpress-content-item-icon tpress-flex-shrink-0">
        <span className="quiz-modal-question-number item-icon">{index + 1}</span>
        <div {...attributes} {...listeners} className="drag-icon" style={{ cursor: isDragging ? "grabbing" : "grab" }}>
          <Icon icon="menu" />
        </div>
      </div>
      <div className="quiz-modal-question-content">
        <div className="quiz-modal-question-title">
          {question.question_title || `${__("Question", "tutorpress")} ${index + 1}`}
        </div>
        <div className="quiz-modal-question-type-badge">{getQuestionTypeDisplayName(question.question_type)}</div>
      </div>
      <div className="quiz-modal-question-actions tpress-item-actions">
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
  );
};

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
  onQuestionReorder,
  onCancelAddQuestion,
  getQuestionTypeDisplayName,
}) => {
  // Drag and drop state
  const [activeQuestionId, setActiveQuestionId] = useState<number | null>(null);
  const [isDraggingQuestion, setIsDraggingQuestion] = useState(false);

  // Configure drag sensors
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    })
  );

  // Create drag handlers using utility
  const { handleDragStart, handleDragEnd, handleDragCancel } = createQuizDragHandlers({
    items: questions.map((question) => ({ ...question, id: question.question_id })),
    onReorder: onQuestionReorder,
    onDragStart: (activeId) => {
      setActiveQuestionId(activeId);
      setIsDraggingQuestion(true);
    },
    onDragEnd: () => {
      setActiveQuestionId(null);
      setIsDraggingQuestion(false);
    },
  });
  return (
    <div className="quiz-modal-questions-section">
      <div className="quiz-modal-questions-header tpress-section-header">
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
                label: type.label,
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
          <DndContext
            sensors={sensors}
            onDragStart={handleDragStart}
            onDragEnd={handleDragEnd}
            onDragCancel={handleDragCancel}
          >
            <SortableContext items={questions.map((q) => q.question_id)} strategy={verticalListSortingStrategy}>
              {questions.map((question, index) => (
                <SortableQuestionItem
                  key={question.question_id}
                  question={question}
                  index={index}
                  isSelected={selectedQuestionIndex === index}
                  onQuestionSelect={onQuestionSelect}
                  onDeleteQuestion={onDeleteQuestion}
                  getQuestionTypeDisplayName={getQuestionTypeDisplayName}
                />
              ))}
            </SortableContext>
          </DndContext>
        )}
      </div>
    </div>
  );
};
