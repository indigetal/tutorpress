/**
 * Option Editor Component
 *
 * @description Reusable option editor component for quiz questions. Provides a consistent
 *              interface for editing option text and images across different question types.
 *              Extracted from SortableOption and MultipleChoiceQuestion during Phase 2.5
 *              refactoring to eliminate UI code duplication and provide consistent UX.
 *              Enhanced in Phase 2.6 to support image-required modes for Image Answering
 *              and Matching question types.
 *
 * @features
 * - Inline text editing with textarea
 * - Image support with WordPress Media Library integration
 * - Image-required modes with upload area and conditional save button
 * - Helper text support for question-specific instructions
 * - Consistent UI with header, content, and action areas
 * - Save/Cancel actions with validation
 * - Accessibility features and keyboard support
 * - Responsive design with consistent styling
 * - Backward compatibility with existing question types
 *
 * @usage
 * // Standard mode (existing question types)
 * <OptionEditor
 *   optionLabel="A"
 *   currentText={currentOptionText}
 *   currentImage={currentOptionImage}
 *   placeholder="Write option..."
 *   onTextChange={setCurrentOptionText}
 *   onImageAdd={handleImageAdd}
 *   onImageRemove={handleImageRemove}
 *   onSave={handleSave}
 *   onCancel={handleCancel}
 *   isSaving={isSaving}
 * />
 *
 * // Image-required mode (Image Answering, Matching)
 * <OptionEditor
 *   optionLabel="A"
 *   currentText={currentOptionText}
 *   currentImage={currentOptionImage}
 *   requireImage={true}
 *   showImageUploadArea={true}
 *   helperText="Students need to type their answers exactly as you write them here."
 *   onTextChange={setCurrentOptionText}
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
import { __ } from "@wordpress/i18n";

/**
 * Props interface for OptionEditor component
 */
export interface OptionEditorProps {
  /** The option label to display (e.g., "A", "B", "C") */
  optionLabel: string;
  /** Current text being edited */
  currentText: string;
  /** Current image being edited (if any) */
  currentImage: { id: number; url: string } | null;
  /** Placeholder text for the textarea */
  placeholder?: string;
  /** Whether an image is required to save the option (for Image Answering, Matching) */
  requireImage?: boolean;
  /** Whether to show the image upload area above the text field instead of a button */
  showImageUploadArea?: boolean;
  /** Helper text to display below the text field */
  helperText?: string;
  /** Callback when text changes */
  onTextChange: (text: string) => void;
  /** Callback when add image button is clicked */
  onImageAdd: () => void;
  /** Callback when remove image button is clicked */
  onImageRemove: () => void;
  /** Callback when save button is clicked */
  onSave: () => void;
  /** Callback when cancel button is clicked */
  onCancel: () => void;
  /** Whether the parent component is currently saving */
  isSaving: boolean;
  /** Whether to auto-focus the textarea */
  autoFocus?: boolean;
  /** Number of rows for the textarea */
  rows?: number;
}

/**
 * OptionEditor Component
 *
 * Provides a consistent interface for editing quiz option text and images.
 * Used both for inline editing in SortableOption and standalone editing in question components.
 * Supports both standard mode (with Add Image button) and image-required mode (with upload area).
 */
export const OptionEditor: React.FC<OptionEditorProps> = ({
  optionLabel,
  currentText,
  currentImage,
  placeholder = __("Write option...", "tutorpress"),
  requireImage = false,
  showImageUploadArea = false,
  helperText,
  onTextChange,
  onImageAdd,
  onImageRemove,
  onSave,
  onCancel,
  isSaving,
  autoFocus = true,
  rows = 3,
}) => {
  // Determine if save button should be disabled
  const isSaveDisabled = isSaving || !currentText.trim() || (requireImage && !currentImage);

  return (
    <div className="quiz-modal-option-editor">
      {/* Editor Header */}
      <div className="quiz-modal-option-editor-header">
        <span className="quiz-modal-option-label">{optionLabel}.</span>
        {/* Show Add Image button only in standard mode (not when showImageUploadArea is true) */}
        {!showImageUploadArea && (
          <button
            type="button"
            className={`quiz-modal-add-image-btn ${currentImage ? "has-image" : ""}`}
            onClick={currentImage ? onImageRemove : onImageAdd}
            disabled={isSaving}
          >
            {currentImage ? __("Remove Image", "tutorpress") : __("Add Image", "tutorpress")}
          </button>
        )}
      </div>

      {/* Image Upload Area (for image-required modes) */}
      {showImageUploadArea && (
        <div className="quiz-modal-image-upload-area">
          {currentImage ? (
            <div className="quiz-modal-image-preview">
              <img src={currentImage.url} alt={__("Option image", "tutorpress")} className="quiz-modal-option-image" />
              <button
                type="button"
                className="quiz-modal-remove-image-btn"
                onClick={onImageRemove}
                disabled={isSaving}
                title={__("Remove image", "tutorpress")}
              >
                {__("Remove Image", "tutorpress")}
              </button>
            </div>
          ) : (
            <div className="quiz-modal-image-upload-placeholder">
              <button type="button" className="quiz-modal-upload-image-btn" onClick={onImageAdd} disabled={isSaving}>
                {__("Upload Image", "tutorpress")}
              </button>
              <p className="quiz-modal-upload-instruction">
                {__("Click to upload an image for this option", "tutorpress")}
              </p>
            </div>
          )}
        </div>
      )}

      {/* Image Display (for standard mode when image exists) */}
      {!showImageUploadArea && currentImage && (
        <div className="quiz-modal-option-image-container">
          <img src={currentImage.url} alt={__("Option image", "tutorpress")} className="quiz-modal-option-image" />
        </div>
      )}

      {/* Text Editor */}
      <textarea
        className="quiz-modal-option-textarea"
        placeholder={placeholder}
        value={currentText}
        onChange={(e) => onTextChange(e.target.value)}
        rows={rows}
        disabled={isSaving}
        autoFocus={autoFocus}
      />

      {/* Helper Text */}
      {helperText && (
        <div className="quiz-modal-option-helper-text">
          <p>{helperText}</p>
        </div>
      )}

      {/* Action Buttons */}
      <div className="quiz-modal-option-editor-actions">
        <button type="button" className="quiz-modal-option-cancel-btn" onClick={onCancel} disabled={isSaving}>
          {__("Cancel", "tutorpress")}
        </button>
        <button
          type="button"
          className={`quiz-modal-option-ok-btn ${isSaveDisabled ? "disabled" : ""}`}
          onClick={onSave}
          disabled={isSaveDisabled}
          title={
            requireImage && !currentImage
              ? __("Please upload an image before saving", "tutorpress")
              : !currentText.trim()
              ? __("Please enter option text before saving", "tutorpress")
              : __("Save option", "tutorpress")
          }
        >
          {__("Ok", "tutorpress")}
        </button>
      </div>
    </div>
  );
};
