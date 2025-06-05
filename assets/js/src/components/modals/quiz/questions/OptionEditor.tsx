/**
 * Option Editor Component
 *
 * @description Reusable option editor component for quiz questions. Provides a consistent
 *              interface for editing option text and images across different question types.
 *              Extracted from SortableOption and MultipleChoiceQuestion during Phase 2.5
 *              refactoring to eliminate UI code duplication and provide consistent UX.
 *
 * @features
 * - Inline text editing with textarea
 * - Image support with WordPress Media Library integration
 * - Consistent UI with header, content, and action areas
 * - Save/Cancel actions with validation
 * - Accessibility features and keyboard support
 * - Responsive design with consistent styling
 *
 * @usage
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
 *   autoFocus={true}
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
 */
export const OptionEditor: React.FC<OptionEditorProps> = ({
  optionLabel,
  currentText,
  currentImage,
  placeholder = __("Write option...", "tutorpress"),
  onTextChange,
  onImageAdd,
  onImageRemove,
  onSave,
  onCancel,
  isSaving,
  autoFocus = true,
  rows = 3,
}) => {
  return (
    <div className="quiz-modal-option-editor">
      {/* Editor Header */}
      <div className="quiz-modal-option-editor-header">
        <span className="quiz-modal-option-label">{optionLabel}.</span>
        <button
          type="button"
          className={`quiz-modal-add-image-btn ${currentImage ? "has-image" : ""}`}
          onClick={currentImage ? onImageRemove : onImageAdd}
          disabled={isSaving}
        >
          {currentImage ? __("Remove Image", "tutorpress") : __("Add Image", "tutorpress")}
        </button>
      </div>

      {/* Image Display */}
      {currentImage && (
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

      {/* Action Buttons */}
      <div className="quiz-modal-option-editor-actions">
        <button type="button" className="quiz-modal-option-cancel-btn" onClick={onCancel} disabled={isSaving}>
          {__("Cancel", "tutorpress")}
        </button>
        <button
          type="button"
          className="quiz-modal-option-ok-btn"
          onClick={onSave}
          disabled={isSaving || !currentText.trim()}
        >
          {__("Ok", "tutorpress")}
        </button>
      </div>
    </div>
  );
};
