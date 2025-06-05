/**
 * Sortable Option Component
 *
 * @description Reusable sortable option component for quiz questions. Handles display and editing
 *              of individual question options with drag & drop functionality, image support,
 *              and inline editing capabilities. Extracted from MultipleChoiceQuestion and QuizModal
 *              during Phase 2.5 refactoring to eliminate code duplication.
 *
 * @features
 * - Drag & drop reordering with dnd-kit
 * - Inline editing with textarea
 * - Image support with upload/remove
 * - Correct answer selection
 * - Option actions (edit, duplicate, delete)
 * - Visual states (correct, editing, dragging)
 * - Accessibility features
 *
 * @dependencies
 * - @dnd-kit/sortable for drag and drop
 * - WordPress components and icons
 *
 * @usage
 * <SortableOption
 *   option={option}
 *   index={index}
 *   isCorrect={isCorrect}
 *   isEditing={isEditing}
 *   currentOptionText={currentOptionText}
 *   currentOptionImage={currentOptionImage}
 *   onEdit={handleEdit}
 *   onDuplicate={handleDuplicate}
 *   onDelete={handleDelete}
 *   onCorrectToggle={handleCorrectToggle}
 *   onTextChange={handleTextChange}
 *   onImageAdd={handleImageAdd}
 *   onImageRemove={handleImageRemove}
 *   onSave={handleSave}
 *   onCancel={handleCancel}
 *   isSaving={isSaving}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React from "react";
import { Button, Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { edit, copy, trash, dragHandle, check } from "@wordpress/icons";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { OptionEditor } from "./OptionEditor";
import type { QuizQuestionOption } from "../../../../types/quiz";

/**
 * Props interface for SortableOption component
 */
export interface SortableOptionProps {
  /** The question option data */
  option: QuizQuestionOption;
  /** The index of this option in the list (for A, B, C labels) */
  index: number;
  /** Whether this option is marked as correct */
  isCorrect: boolean;
  /** Whether this option is currently being edited */
  isEditing: boolean;
  /** Current text being edited (only used when isEditing=true) */
  currentOptionText: string;
  /** Current image being edited (only used when isEditing=true) */
  currentOptionImage: { id: number; url: string } | null;
  /** Callback when edit button is clicked */
  onEdit: () => void;
  /** Callback when duplicate button is clicked */
  onDuplicate: () => void;
  /** Callback when delete button is clicked */
  onDelete: () => void;
  /** Callback when correct answer indicator is clicked */
  onCorrectToggle: () => void;
  /** Callback when option text changes during editing */
  onTextChange: (value: string) => void;
  /** Callback when add image button is clicked during editing */
  onImageAdd: () => void;
  /** Callback when remove image button is clicked during editing */
  onImageRemove: () => void;
  /** Callback when save button is clicked during editing */
  onSave: () => void;
  /** Callback when cancel button is clicked during editing */
  onCancel: () => void;
  /** Whether the parent component is currently saving */
  isSaving: boolean;
}

/**
 * SortableOption Component
 *
 * Displays a single quiz option with drag & drop, editing, and action capabilities.
 * Can switch between display mode and editing mode based on isEditing prop.
 */
export const SortableOption: React.FC<SortableOptionProps> = ({
  option,
  index,
  isCorrect,
  isEditing,
  currentOptionText,
  currentOptionImage,
  onEdit,
  onDuplicate,
  onDelete,
  onCorrectToggle,
  onTextChange,
  onImageAdd,
  onImageRemove,
  onSave,
  onCancel,
  isSaving,
}) => {
  // Set up drag and drop functionality
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: option.answer_id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    position: "relative" as const,
    opacity: isDragging ? 0.5 : 1,
  };

  // Build CSS classes for different states
  const classNames = [
    "quiz-modal-option-card",
    isCorrect && "is-correct",
    isEditing && "quiz-modal-option-card-editing",
    isDragging && "is-dragging",
  ]
    .filter(Boolean)
    .join(" ");

  // Render editing mode
  if (isEditing) {
    return (
      <div ref={setNodeRef} style={style} className={classNames}>
        <OptionEditor
          optionLabel={String.fromCharCode(65 + index)}
          currentText={currentOptionText}
          currentImage={currentOptionImage}
          onTextChange={onTextChange}
          onImageAdd={onImageAdd}
          onImageRemove={onImageRemove}
          onSave={onSave}
          onCancel={onCancel}
          isSaving={isSaving}
        />
      </div>
    );
  }

  // Render display mode
  return (
    <div ref={setNodeRef} style={style} className={classNames}>
      <div className="quiz-modal-option-card-header">
        <div className="quiz-modal-option-card-icon">
          <span className="quiz-modal-option-label">{String.fromCharCode(65 + index)}.</span>
          <Button
            icon={dragHandle}
            label={__("Drag to reorder", "tutorpress")}
            isSmall
            variant="tertiary"
            className="quiz-modal-drag-handle"
            ref={setActivatorNodeRef}
            {...attributes}
            {...listeners}
          />
        </div>
        <div className="quiz-modal-option-card-actions">
          <Button
            icon={edit}
            label={__("Edit option", "tutorpress")}
            isSmall
            variant="tertiary"
            onClick={onEdit}
            disabled={isSaving}
          />
          <Button
            icon={copy}
            label={__("Duplicate option", "tutorpress")}
            isSmall
            variant="tertiary"
            onClick={onDuplicate}
            disabled={isSaving}
          />
          <Button
            icon={trash}
            label={__("Delete option", "tutorpress")}
            isSmall
            variant="tertiary"
            onClick={onDelete}
            disabled={isSaving}
          />
        </div>
      </div>
      <div className="quiz-modal-option-card-content">
        <div
          className="quiz-modal-correct-answer-indicator"
          onClick={onCorrectToggle}
          title={isCorrect ? __("Correct answer", "tutorpress") : __("Mark as correct answer", "tutorpress")}
        >
          <Icon icon={check} />
        </div>
        <div className="quiz-modal-option-content-wrapper">
          {/* Display image above text if present */}
          {(() => {
            const imageId = typeof option.image_id === "string" ? parseInt(option.image_id, 10) : option.image_id;
            return imageId && imageId > 0 && option.image_url ? (
              <div className="quiz-modal-option-image-container">
                <img
                  src={option.image_url}
                  alt={__("Option image", "tutorpress")}
                  className="quiz-modal-option-image"
                />
              </div>
            ) : null;
          })()}
          <div className="quiz-modal-option-text">{option.answer_title || __("(Empty option)", "tutorpress")}</div>
        </div>
      </div>
    </div>
  );
};
