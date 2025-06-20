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
 * - Optional correct answer selection (configurable per question type)
 * - Customizable option labeling
 * - Option actions (edit, duplicate, delete)
 * - Visual states (correct, editing, dragging)
 * - Accessibility features
 *
 * @dependencies
 * - @dnd-kit/sortable for drag and drop
 * - WordPress components and icons
 *
 * @usage
 * // Multiple Choice (with correct answer selection)
 * <SortableOption
 *   option={option}
 *   index={index}
 *   isCorrect={isCorrect}
 *   showCorrectIndicator={true}
 *   optionLabel="A"
 *   // ... other props
 * />
 *
 * // Ordering/Matching (without correct answer selection)
 * <SortableOption
 *   option={option}
 *   index={index}
 *   showCorrectIndicator={false}
 *   optionLabel="Step 1"
 *   // ... other props
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
import { getQuizOptionClasses } from "../../../../components/common";
import type { QuizQuestionOption } from "../../../../types/quiz";

/**
 * Props interface for SortableOption component
 */
export interface SortableOptionProps {
  /** The question option data */
  option: QuizQuestionOption;
  /** The index of this option in the list */
  index: number;
  /** Whether this option is marked as correct (only used when showCorrectIndicator=true) */
  isCorrect?: boolean;
  /** Whether this option is currently being edited */
  isEditing: boolean;
  /** Current text being edited (only used when isEditing=true) */
  currentOptionText: string;
  /** Current matching text being edited (only used when isEditing=true and showMatchingTextField=true) */
  currentMatchingText?: string;
  /** Current image being edited (only used when isEditing=true) */
  currentOptionImage: { id: number; url: string } | null;
  /** Whether to show the correct answer indicator (green checkmark) - defaults to true for backward compatibility */
  showCorrectIndicator?: boolean;
  /** Custom label for the option (e.g., "A", "Step 1", "Term 1") - defaults to A, B, C format */
  optionLabel?: string;
  /** Whether an image is required to save the option (for Image Answering, Matching) */
  requireImage?: boolean;
  /** Whether to show the image upload area above the text field instead of a button */
  showImageUploadArea?: boolean;
  /** Whether to show the matching text field (for text-only matching questions) */
  showMatchingTextField?: boolean;
  /** Placeholder text for the matching text field */
  matchingTextPlaceholder?: string;
  /** Placeholder text for the main text field */
  placeholder?: string;
  /** Helper text to display below the text field */
  helperText?: string;
  /** Callback when edit button is clicked */
  onEdit: () => void;
  /** Callback when duplicate button is clicked */
  onDuplicate: () => void;
  /** Callback when delete button is clicked */
  onDelete: () => void;
  /** Callback when correct answer indicator is clicked (only used when showCorrectIndicator=true) */
  onCorrectToggle?: () => void;
  /** Callback when option text changes during editing */
  onTextChange: (value: string) => void;
  /** Callback when matching text changes during editing */
  onMatchingTextChange?: (value: string) => void;
  /** Callback when add image button is clicked during editing */
  onImageAdd: () => void;
  /** Callback when remove image button is clicked during editing */
  onImageRemove: () => void;
  /** Callback when image is set directly (for drag-and-drop) */
  onImageSet?: (imageData: { id: number; url: string } | null) => void;
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
 * Supports different question types through optional correct answer functionality.
 */
export const SortableOption: React.FC<SortableOptionProps> = ({
  option,
  index,
  isCorrect = false,
  isEditing,
  currentOptionText,
  currentMatchingText,
  currentOptionImage,
  showCorrectIndicator = true, // Default to true for backward compatibility
  optionLabel,
  requireImage,
  showImageUploadArea,
  showMatchingTextField,
  matchingTextPlaceholder,
  placeholder,
  helperText,
  onEdit,
  onDuplicate,
  onDelete,
  onCorrectToggle,
  onTextChange,
  onMatchingTextChange,
  onImageAdd,
  onImageRemove,
  onImageSet,
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

  // Generate default option label if not provided (A, B, C format)
  const displayLabel = optionLabel ?? String.fromCharCode(65 + index);

  // Use shared CSS utility for consistent styling
  const classNames = getQuizOptionClasses(option, isDragging, isEditing, showCorrectIndicator);

  // Render editing mode
  if (isEditing) {
    return (
      <div ref={setNodeRef} style={style} className={classNames}>
        <OptionEditor
          optionLabel={displayLabel}
          currentText={currentOptionText}
          currentMatchingText={currentMatchingText}
          currentImage={currentOptionImage}
          requireImage={requireImage}
          showImageUploadArea={showImageUploadArea}
          showMatchingTextField={showMatchingTextField}
          matchingTextPlaceholder={matchingTextPlaceholder}
          placeholder={placeholder}
          helperText={helperText}
          onTextChange={onTextChange}
          onMatchingTextChange={onMatchingTextChange}
          onImageAdd={onImageAdd}
          onImageRemove={onImageRemove}
          onImageSet={onImageSet}
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
      <div className="quiz-modal-option-card-header tpress-header-actions-sm">
        <div className="quiz-modal-option-card-icon">
          <span className="quiz-modal-option-label">{displayLabel}.</span>
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
        <div className="quiz-modal-option-card-actions tpress-header-actions-group-xs tpress-ml-auto">
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
        {/* Conditionally render correct answer indicator only for question types that need it */}
        {showCorrectIndicator && (
          <div
            className="quiz-modal-correct-answer-indicator"
            onClick={onCorrectToggle}
            title={isCorrect ? __("Correct answer", "tutorpress") : __("Mark as correct answer", "tutorpress")}
          >
            <Icon icon={check} />
          </div>
        )}
        <div className="quiz-modal-option-content-wrapper">
          {/* Display image above text if present */}
          {(() => {
            const imageId = typeof option.image_id === "string" ? parseInt(option.image_id, 10) : option.image_id;
            return imageId && imageId > 0 && option.image_url ? (
              <div
                className="quiz-modal-option-image-container"
                style={{
                  borderRadius: "8px",
                  overflow: "hidden",
                  backgroundColor: "#f3f4f6",
                  marginBottom: "12px",
                  height: "200px",
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                <img
                  src={option.image_url}
                  alt={__("Option image", "tutorpress")}
                  className="quiz-modal-option-image"
                  style={{
                    width: "100%",
                    height: "200px",
                    objectFit: "cover",
                    display: "block",
                  }}
                />
              </div>
            ) : null;
          })()}
          <div className="quiz-modal-option-text">{option.answer_title || __("(Empty option)", "tutorpress")}</div>
          {/* Display matching text if it exists (for text-only matching questions) */}
          {option.answer_two_gap_match && (
            <div
              className="quiz-modal-option-matching-text"
              style={{ marginTop: "8px", fontSize: "14px", color: "#6b7280", fontStyle: "italic" }}
            >
              {__("Matches:", "tutorpress")} {option.answer_two_gap_match}
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
