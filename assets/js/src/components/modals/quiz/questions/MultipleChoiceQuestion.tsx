/**
 * Multiple Choice Question Component
 *
 * @description Complex component for Multiple Choice question type in quiz modal. Handles
 *              option creation/editing/deletion, drag & drop reordering, image support,
 *              correct answer selection, and validation. Extracted from QuizModal during
 *              Phase 2 refactoring to create focused, reusable question type components.
 *
 * @features
 * - Dynamic option creation and editing
 * - Image support via WordPress Media Library
 * - Drag & drop option reordering with dnd-kit
 * - Single/Multiple correct answer modes
 * - Inline option editing with TinyMCE-style textarea
 * - Comprehensive validation
 * - Option duplication and deletion
 * - Visual correct answer indicators
 *
 * @dependencies
 * - @dnd-kit/core for drag and drop
 * - @dnd-kit/sortable for sortable lists
 * - WordPress Media Library integration
 *
 * @usage
 * <MultipleChoiceQuestion
 *   question={question}
 *   questionIndex={questionIndex}
 *   onQuestionUpdate={handleQuestionFieldUpdate}
 *   onSettingUpdate={handleQuestionSettingUpdate}
 *   showValidationErrors={showValidationErrors}
 *   isSaving={isSaving}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React, { useState } from "react";
import { Button, Icon } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { edit, copy, trash, dragHandle, check } from "@wordpress/icons";
import { DndContext, useSensor, useSensors, PointerSensor } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { arrayMove } from "@dnd-kit/sortable";
import type { QuizQuestion, QuizQuestionOption, DataStatus } from "../../../../types/quiz";

interface MultipleChoiceQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
}

// SortableOption Component
interface SortableOptionProps {
  option: QuizQuestionOption;
  index: number;
  questionIndex: number;
  isCorrect: boolean;
  isEditing: boolean;
  currentOptionText: string;
  currentOptionImage: { id: number; url: string } | null;
  onEdit: () => void;
  onDuplicate: () => void;
  onDelete: () => void;
  onCorrectToggle: () => void;
  onTextChange: (value: string) => void;
  onImageAdd: () => void;
  onImageRemove: () => void;
  onSave: () => void;
  onCancel: () => void;
  isSaving: boolean;
}

const SortableOption: React.FC<SortableOptionProps> = ({
  option,
  index,
  questionIndex,
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
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: option.answer_id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    position: "relative" as const,
    opacity: isDragging ? 0.5 : 1,
  };

  const classNames = [
    "quiz-modal-option-card",
    isCorrect && "is-correct",
    isEditing && "quiz-modal-option-card-editing",
    isDragging && "is-dragging",
  ]
    .filter(Boolean)
    .join(" ");

  if (isEditing) {
    // Transform this option card into the editor
    return (
      <div ref={setNodeRef} style={style} className={classNames}>
        <div className="quiz-modal-option-editor">
          <div className="quiz-modal-option-editor-header">
            <span className="quiz-modal-option-label">{String.fromCharCode(65 + index)}.</span>
            <button
              type="button"
              className={`quiz-modal-add-image-btn ${currentOptionImage ? "has-image" : ""}`}
              onClick={currentOptionImage ? onImageRemove : onImageAdd}
              disabled={isSaving}
            >
              {currentOptionImage ? __("Remove Image", "tutorpress") : __("Add Image", "tutorpress")}
            </button>
          </div>

          {/* Display image above textarea if present */}
          {currentOptionImage && (
            <div className="quiz-modal-option-image-container">
              <img
                src={currentOptionImage.url}
                alt={__("Option image", "tutorpress")}
                className="quiz-modal-option-image"
              />
            </div>
          )}

          <textarea
            className="quiz-modal-option-textarea"
            placeholder={__("Write option...", "tutorpress")}
            value={currentOptionText}
            onChange={(e) => onTextChange(e.target.value)}
            rows={3}
            disabled={isSaving}
            autoFocus
          />

          <div className="quiz-modal-option-editor-actions">
            <button type="button" className="quiz-modal-option-cancel-btn" onClick={onCancel} disabled={isSaving}>
              {__("Cancel", "tutorpress")}
            </button>
            <button
              type="button"
              className="quiz-modal-option-ok-btn"
              onClick={onSave}
              disabled={isSaving || !currentOptionText.trim()}
            >
              {__("Ok", "tutorpress")}
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Regular option card display
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

export const MultipleChoiceQuestion: React.FC<MultipleChoiceQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  // Option editing state
  const [showOptionEditor, setShowOptionEditor] = useState(false);
  const [currentOptionText, setCurrentOptionText] = useState("");
  const [editingOptionIndex, setEditingOptionIndex] = useState<number | null>(null);
  const [currentOptionImage, setCurrentOptionImage] = useState<{
    id: number;
    url: string;
  } | null>(null);

  // Drag and drop state
  const [activeOptionId, setActiveOptionId] = useState<number | null>(null);
  const [isDraggingOption, setIsDraggingOption] = useState(false);

  // Configure drag sensors
  const sensors = useSensors(
    useSensor(PointerSensor, {
      activationConstraint: {
        distance: 8,
      },
    })
  );

  const existingOptions = question.question_answers || [];
  const hasOptions = existingOptions.length > 0;
  const optionIds = existingOptions.map((option) => option.answer_id);

  /**
   * Validate multiple choice question
   */
  const validateMultipleChoiceQuestion = (question: QuizQuestion): string[] => {
    const errors: string[] = [];
    const options = question.question_answers || [];
    const correctAnswers = options.filter((answer) => answer.is_correct === "1");
    const isAnswerRequired = question.question_settings.answer_required;

    // Minimum 2 options required
    if (options.length < 2) {
      errors.push(__("Multiple choice questions must have at least 2 options.", "tutorpress"));
    }

    // Check for empty option text
    const emptyOptions = options.filter((option) => !option.answer_title?.trim());
    if (emptyOptions.length > 0) {
      errors.push(__("All options must have text content.", "tutorpress"));
    }

    // Check for duplicate option content
    const optionTexts = options.map((option) => option.answer_title?.trim().toLowerCase()).filter(Boolean);
    const uniqueTexts = new Set(optionTexts);
    if (optionTexts.length !== uniqueTexts.size) {
      errors.push(__("Options cannot have duplicate content.", "tutorpress"));
    }

    // At least 1 correct answer required (unless answer not required)
    if (isAnswerRequired && correctAnswers.length === 0) {
      errors.push(__("At least one option must be marked as correct.", "tutorpress"));
    }

    return errors;
  };

  // Get validation errors for this question
  const validationErrors = validateMultipleChoiceQuestion(question);
  const shouldShowValidationErrors = showValidationErrors && hasOptions && validationErrors.length > 0;

  /**
   * Handle starting option editing
   */
  const handleStartOptionEditing = (optionIndex?: number) => {
    setEditingOptionIndex(optionIndex ?? null);

    // If editing existing option, load its text and image
    if (optionIndex !== undefined && existingOptions[optionIndex]) {
      const option = existingOptions[optionIndex];
      setCurrentOptionText(option.answer_title || "");

      // Load existing image if available
      const imageId = typeof option.image_id === "string" ? parseInt(option.image_id, 10) : option.image_id;
      if (imageId && imageId > 0 && option.image_url) {
        setCurrentOptionImage({
          id: imageId,
          url: option.image_url,
        });
      } else {
        setCurrentOptionImage(null);
      }
    } else {
      setCurrentOptionText("");
      setCurrentOptionImage(null);
    }

    setShowOptionEditor(true);
  };

  /**
   * Handle saving option
   */
  const handleSaveOption = () => {
    if (!currentOptionText.trim()) {
      return;
    }

    let updatedAnswers = [...existingOptions];

    if (editingOptionIndex === null) {
      // Adding new option
      const newOptionOrder = updatedAnswers.length + 1;
      const newOption: QuizQuestionOption = {
        answer_id: -(Date.now() + Math.floor(Math.random() * 1000)),
        belongs_question_id: question.question_id,
        belongs_question_type: question.question_type,
        answer_title: currentOptionText.trim(),
        is_correct: "0",
        image_id: currentOptionImage?.id || 0,
        image_url: currentOptionImage?.url || "",
        answer_two_gap_match: "",
        answer_view_format: "",
        answer_order: newOptionOrder,
        _data_status: "new",
      };
      updatedAnswers.push(newOption);
    } else {
      // Editing existing option
      if (editingOptionIndex >= 0 && editingOptionIndex < updatedAnswers.length) {
        updatedAnswers[editingOptionIndex] = {
          ...updatedAnswers[editingOptionIndex],
          answer_title: currentOptionText.trim(),
          image_id: currentOptionImage?.id || 0,
          image_url: currentOptionImage?.url || "",
          _data_status: updatedAnswers[editingOptionIndex]._data_status === "new" ? "new" : "update",
        };
      }
    }

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
    handleCancelOptionEditing();
  };

  /**
   * Handle canceling option editing
   */
  const handleCancelOptionEditing = () => {
    setShowOptionEditor(false);
    setCurrentOptionText("");
    setCurrentOptionImage(null);
    setEditingOptionIndex(null);
  };

  /**
   * Handle editing existing option
   */
  const handleEditOption = (optionIndex: number) => {
    handleStartOptionEditing(optionIndex);
  };

  /**
   * Handle duplicating option
   */
  const handleDuplicateOption = (optionIndex: number) => {
    const optionToDuplicate = existingOptions[optionIndex];
    if (!optionToDuplicate) return;

    // Create duplicate option
    const duplicatedOption: QuizQuestionOption = {
      ...optionToDuplicate,
      answer_id: -(Date.now() + Math.floor(Math.random() * 1000)),
      answer_title: `${optionToDuplicate.answer_title} (Copy)`,
      is_correct: "0", // Reset to not correct
      answer_order: existingOptions.length + 1,
      _data_status: "new",
    };

    const updatedAnswers = [...existingOptions, duplicatedOption];
    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
  };

  /**
   * Handle deleting option
   */
  const handleDeleteOption = (optionIndex: number) => {
    const optionToDelete = existingOptions[optionIndex];
    if (!optionToDelete) return;

    // Remove option and reorder remaining options
    const updatedAnswers = existingOptions
      .filter((_, index) => index !== optionIndex)
      .map((answer, index) => ({
        ...answer,
        answer_order: index + 1,
        _data_status: answer._data_status === "new" ? ("new" as DataStatus) : ("update" as DataStatus),
      }));

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
  };

  /**
   * Handle correct answer selection
   */
  const handleCorrectAnswerSelection = (optionIndex: number) => {
    const targetOption = existingOptions[optionIndex];
    if (!targetOption) return;

    // Check if this is single or multiple mode
    const isMultipleMode = question.question_settings.has_multiple_correct_answer;

    let updatedAnswers;

    if (isMultipleMode) {
      // Multiple mode: toggle the clicked option
      updatedAnswers = existingOptions.map((answer, index) => ({
        ...answer,
        is_correct: index === optionIndex ? ((answer.is_correct === "1" ? "0" : "1") as "0" | "1") : answer.is_correct,
        _data_status: (answer._data_status === "new" ? "new" : "update") as DataStatus,
      }));
    } else {
      // Single mode: only the clicked option is correct, all others are incorrect
      updatedAnswers = existingOptions.map((answer, index) => ({
        ...answer,
        is_correct: (index === optionIndex ? "1" : "0") as "0" | "1",
        _data_status: (answer._data_status === "new" ? "new" : "update") as DataStatus,
      }));
    }

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
  };

  /**
   * Handle drag start
   */
  const handleDragStart = (event: any) => {
    setActiveOptionId(Number(event.active.id));
    setIsDraggingOption(true);
  };

  /**
   * Handle drag end
   */
  const handleDragEnd = (event: any) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      const oldIndex = existingOptions.findIndex((answer) => answer.answer_id === Number(active.id));
      const newIndex = existingOptions.findIndex((answer) => answer.answer_id === Number(over.id));

      if (oldIndex !== -1 && newIndex !== -1) {
        // Reorder the options
        const reorderedAnswers = arrayMove(existingOptions, oldIndex, newIndex);

        // Update answer_order for all options
        const updatedAnswers = reorderedAnswers.map((answer, index) => ({
          ...answer,
          answer_order: index + 1,
          _data_status: (answer._data_status === "new" ? "new" : "update") as DataStatus,
        }));

        onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
      }
    }

    setActiveOptionId(null);
    setIsDraggingOption(false);
  };

  /**
   * Handle drag cancel
   */
  const handleDragCancel = () => {
    setActiveOptionId(null);
    setIsDraggingOption(false);
  };

  /**
   * Handle image addition
   */
  const handleImageAdd = (optionIndex?: number) => {
    // Open WordPress media library
    if (typeof (window as any).wp !== "undefined" && (window as any).wp.media) {
      const mediaFrame = (window as any).wp.media({
        title: __("Select Image for Option", "tutorpress"),
        button: {
          text: __("Use this image", "tutorpress"),
        },
        multiple: false,
        library: {
          type: "image",
          uploadedTo: null,
        },
        states: [
          new (window as any).wp.media.controller.Library({
            title: __("Select Image for Option", "tutorpress"),
            library: (window as any).wp.media.query({
              type: "image",
            }),
            multiple: false,
            date: false,
          }),
        ],
      });

      mediaFrame.on("select", () => {
        const attachment = mediaFrame.state().get("selection").first().toJSON();

        if (!attachment.type || attachment.type !== "image") {
          console.error("Selected file is not an image");
          return;
        }

        const imageData = {
          id: attachment.id,
          url: attachment.url,
        };

        setCurrentOptionImage(imageData);

        // If editing existing option, update the option immediately
        if (optionIndex !== undefined) {
          handleSaveOptionImage(optionIndex, imageData);
        }
      });

      mediaFrame.open();
    } else {
      console.error("WordPress media library not available");
    }
  };

  /**
   * Handle image removal
   */
  const handleImageRemove = (optionIndex?: number) => {
    setCurrentOptionImage(null);

    // If editing existing option, update the option immediately
    if (optionIndex !== undefined) {
      handleSaveOptionImage(optionIndex, null);
    }
  };

  /**
   * Save image data to option
   */
  const handleSaveOptionImage = (optionIndex: number, imageData: { id: number; url: string } | null) => {
    if (optionIndex >= 0 && optionIndex < existingOptions.length) {
      let updatedAnswers = [...existingOptions];
      updatedAnswers[optionIndex] = {
        ...updatedAnswers[optionIndex],
        image_id: imageData?.id || 0,
        image_url: imageData?.url || "",
        _data_status: updatedAnswers[optionIndex]._data_status === "new" ? "new" : "update",
      };

      onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
    }
  };

  return (
    <div className="quiz-modal-multiple-choice-content">
      {/* Display validation errors */}
      {shouldShowValidationErrors && (
        <div className="quiz-modal-validation-error">
          {validationErrors.map((error, index) => (
            <p key={index}>{error}</p>
          ))}
        </div>
      )}

      {/* Display existing options with drag and drop */}
      {hasOptions && (
        <DndContext
          sensors={sensors}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
          onDragCancel={handleDragCancel}
        >
          <SortableContext items={optionIds} strategy={verticalListSortingStrategy}>
            <div className="quiz-modal-multiple-choice-options">
              {existingOptions.map((option, index) => {
                // Check if this specific option is being edited
                const isEditingThisOption = showOptionEditor && editingOptionIndex === index;

                return (
                  <SortableOption
                    key={option.answer_id}
                    option={option}
                    index={index}
                    questionIndex={questionIndex}
                    isCorrect={option.is_correct === "1"}
                    isEditing={isEditingThisOption}
                    currentOptionText={currentOptionText}
                    currentOptionImage={currentOptionImage}
                    onEdit={() => handleEditOption(index)}
                    onDuplicate={() => handleDuplicateOption(index)}
                    onDelete={() => handleDeleteOption(index)}
                    onCorrectToggle={() => handleCorrectAnswerSelection(index)}
                    onTextChange={setCurrentOptionText}
                    onImageAdd={() => handleImageAdd(index)}
                    onImageRemove={() => handleImageRemove(index)}
                    onSave={handleSaveOption}
                    onCancel={handleCancelOptionEditing}
                    isSaving={isSaving}
                  />
                );
              })}
            </div>
          </SortableContext>
        </DndContext>
      )}

      {/* Add Option Editor - only show when adding new option */}
      {showOptionEditor && editingOptionIndex === null && (
        <div className="quiz-modal-option-editor">
          <div className="quiz-modal-option-editor-header">
            <span className="quiz-modal-option-label">{String.fromCharCode(65 + existingOptions.length)}.</span>
            <button
              type="button"
              className={`quiz-modal-add-image-btn ${currentOptionImage ? "has-image" : ""}`}
              onClick={() => (currentOptionImage ? handleImageRemove() : handleImageAdd())}
              disabled={isSaving}
            >
              {currentOptionImage ? __("Remove Image", "tutorpress") : __("Add Image", "tutorpress")}
            </button>
          </div>

          {/* Display image above textarea if present */}
          {currentOptionImage && (
            <div className="quiz-modal-option-image-container">
              <img
                src={currentOptionImage.url}
                alt={__("Option image", "tutorpress")}
                className="quiz-modal-option-image"
              />
            </div>
          )}

          <textarea
            className="quiz-modal-option-textarea"
            placeholder={__("Write option...", "tutorpress")}
            value={currentOptionText}
            onChange={(e) => setCurrentOptionText(e.target.value)}
            rows={3}
            disabled={isSaving}
            autoFocus
          />

          <div className="quiz-modal-option-editor-actions">
            <button
              type="button"
              className="quiz-modal-option-cancel-btn"
              onClick={handleCancelOptionEditing}
              disabled={isSaving}
            >
              {__("Cancel", "tutorpress")}
            </button>
            <button
              type="button"
              className="quiz-modal-option-ok-btn"
              onClick={handleSaveOption}
              disabled={isSaving || !currentOptionText.trim()}
            >
              {__("Ok", "tutorpress")}
            </button>
          </div>
        </div>
      )}

      {/* Add Option Button */}
      <div className="quiz-modal-add-option-container">
        <button
          type="button"
          className="quiz-modal-add-option-btn"
          onClick={() => handleStartOptionEditing()}
          disabled={showOptionEditor || isSaving}
        >
          <span className="quiz-modal-add-option-icon">+</span>
          <span className="quiz-modal-add-option-text">{__("Add Option", "tutorpress")}</span>
        </button>
      </div>
    </div>
  );
};
