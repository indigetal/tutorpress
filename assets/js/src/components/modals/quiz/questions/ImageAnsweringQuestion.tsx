/**
 * Image Answering Question Component
 *
 * @description Component for Image Answering question type in quiz modal. Handles
 *              option creation/editing/deletion, drag & drop reordering, and required image support.
 *              Students must type their answers exactly as written for each image option.
 *              Based on MultipleChoiceQuestion but without correct answer selection functionality,
 *              since Image Answering questions don't have predefined correct/incorrect answers.
 *
 * @features
 * - Dynamic option creation and editing
 * - Required image support via WordPress Media Library
 * - Drag & drop option reordering with dnd-kit
 * - Enhanced OptionEditor with image upload area
 * - Inline option editing with helper text for students
 * - Comprehensive validation
 * - Option duplication and deletion
 * - No correct answer indicators (students type exact answers)
 *
 * @dependencies
 * - @dnd-kit/core for drag and drop
 * - @dnd-kit/sortable for sortable lists
 * - WordPress Media Library integration
 *
 * @usage
 * <ImageAnsweringQuestion
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
import { DndContext, useSensor, useSensors, PointerSensor } from "@dnd-kit/core";
import { SortableContext, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { arrayMove } from "@dnd-kit/sortable";
import { SortableOption } from "./SortableOption";
import { OptionEditor } from "./OptionEditor";
import { ValidationDisplay } from "./ValidationDisplay";
import { useQuestionValidation, useImageManagement } from "../../../../hooks/quiz";
import { createQuizDragHandlers, createQuizOptionReorder } from "../../../../components/common";
import type { QuizQuestion, QuizQuestionOption, DataStatus } from "../../../../types/quiz";

interface ImageAnsweringQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

export const ImageAnsweringQuestion: React.FC<ImageAnsweringQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
  onDeletedAnswerId,
}) => {
  // Option editing state
  const [showOptionEditor, setShowOptionEditor] = useState(false);
  const [currentOptionText, setCurrentOptionText] = useState("");
  const [editingOptionIndex, setEditingOptionIndex] = useState<number | null>(null);

  // Drag and drop state
  const [activeOptionId, setActiveOptionId] = useState<number | null>(null);
  const [isDraggingOption, setIsDraggingOption] = useState(false);

  // Use centralized image management hook
  const {
    currentImage: currentOptionImage,
    setCurrentImage: setCurrentOptionImage,
    createImageHandlers,
  } = useImageManagement();

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

  // Use centralized validation hook
  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);

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
      // Adding new option - Image Answering questions don't have correct/incorrect answers
      // Students must type their answers exactly as written
      const newOptionOrder = updatedAnswers.length + 1;
      const newOption: QuizQuestionOption = {
        answer_id: -(Date.now() + Math.floor(Math.random() * 1000)),
        belongs_question_id: question.question_id,
        belongs_question_type: question.question_type,
        answer_title: currentOptionText.trim(),
        is_correct: "0", // Image Answering questions don't use this field for correctness
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
      is_correct: "0", // Image Answering questions don't use correct/incorrect
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

    if (onDeletedAnswerId) {
      onDeletedAnswerId(optionToDelete.answer_id);
    }
  };

  // Create shared reorder handler using utility
  const handleOptionReorder = createQuizOptionReorder(onQuestionUpdate, questionIndex);

  // Create shared drag handlers using utility
  const { handleDragStart, handleDragEnd, handleDragCancel } = createQuizDragHandlers({
    items: existingOptions.map((option) => ({ ...option, id: option.answer_id })),
    onReorder: handleOptionReorder,
    onDragStart: (activeId) => {
      setActiveOptionId(activeId);
      setIsDraggingOption(true);
    },
    onDragEnd: () => {
      setActiveOptionId(null);
      setIsDraggingOption(false);
    },
  });

  /**
   * Handle image addition
   */
  const handleImageAdd = (optionIndex?: number) => {
    const { handleImageAdd: addImage } = createImageHandlers((imageData) => {
      setCurrentOptionImage(imageData);

      // If editing existing option, update the option immediately
      if (optionIndex !== undefined) {
        handleSaveOptionImage(optionIndex, imageData);
      }
    });

    addImage();
  };

  /**
   * Handle image removal
   */
  const handleImageRemove = (optionIndex?: number) => {
    const { handleImageRemove: removeImage } = createImageHandlers((imageData) => {
      setCurrentOptionImage(imageData);

      // If editing existing option, update the option immediately
      if (optionIndex !== undefined) {
        handleSaveOptionImage(optionIndex, imageData);
      }
    });

    removeImage();
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
    <div className="quiz-modal-image-answering-content">
      {/* Display validation errors */}
      <ValidationDisplay errors={validationErrors} show={showValidationErrors && hasOptions} severity="error" />

      {/* Display existing options with drag and drop */}
      {hasOptions && (
        <DndContext
          sensors={sensors}
          onDragStart={handleDragStart}
          onDragEnd={handleDragEnd}
          onDragCancel={handleDragCancel}
        >
          <SortableContext items={optionIds} strategy={verticalListSortingStrategy}>
            <div className="quiz-modal-image-answering-options">
              {existingOptions.map((option, index) => {
                // Check if this specific option is being edited
                const isEditingThisOption = showOptionEditor && editingOptionIndex === index;

                return (
                  <SortableOption
                    key={option.answer_id}
                    option={option}
                    index={index}
                    isEditing={isEditingThisOption}
                    currentOptionText={currentOptionText}
                    currentOptionImage={currentOptionImage}
                    showCorrectIndicator={false} // No correct answer selection for Image Answering
                    optionLabel={String.fromCharCode(65 + index)}
                    requireImage={true} // Images are required for Image Answering questions
                    showImageUploadArea={true} // Show upload area instead of button
                    helperText={__(
                      "Students need to type their answers exactly as you write them here, including capitalization of letters.",
                      "tutorpress"
                    )}
                    onEdit={() => handleEditOption(index)}
                    onDuplicate={() => handleDuplicateOption(index)}
                    onDelete={() => handleDeleteOption(index)}
                    onCorrectToggle={undefined} // No correct answer functionality
                    onTextChange={setCurrentOptionText}
                    onImageAdd={() => handleImageAdd(index)}
                    onImageRemove={() => handleImageRemove(index)}
                    onImageSet={(imageData: { id: number; url: string } | null) => {
                      setCurrentOptionImage(imageData);
                      // For new options, we just set the current image - it will be saved when the option is saved
                    }}
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
        <OptionEditor
          optionLabel={String.fromCharCode(65 + existingOptions.length)}
          currentText={currentOptionText}
          currentImage={currentOptionImage}
          requireImage={true} // Images are required for Image Answering questions
          showImageUploadArea={true} // Show upload area instead of button
          helperText={__(
            "Students need to type their answers exactly as you write them here, including capitalization of letters.",
            "tutorpress"
          )}
          onTextChange={setCurrentOptionText}
          onImageAdd={() => handleImageAdd()}
          onImageRemove={() => handleImageRemove()}
          onImageSet={(imageData: { id: number; url: string } | null) => {
            setCurrentOptionImage(imageData);
            // For new options, we just set the current image - it will be saved when the option is saved
          }}
          onSave={handleSaveOption}
          onCancel={handleCancelOptionEditing}
          isSaving={isSaving}
        />
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
