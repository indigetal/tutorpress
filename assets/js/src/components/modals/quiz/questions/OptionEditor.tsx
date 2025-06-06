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
  /** Current matching text being edited (for text-only matching questions) */
  currentMatchingText?: string;
  /** Current image being edited (if any) */
  currentImage: { id: number; url: string } | null;
  /** Placeholder text for the textarea */
  placeholder?: string;
  /** Whether an image is required to save the option (for Image Answering, Matching) */
  requireImage?: boolean;
  /** Whether to show the image upload area above the text field instead of a button */
  showImageUploadArea?: boolean;
  /** Whether to show the matching text field (for text-only matching questions) */
  showMatchingTextField?: boolean;
  /** Placeholder text for the matching text field */
  matchingTextPlaceholder?: string;
  /** Helper text to display below the text field */
  helperText?: string;
  /** Callback when text changes */
  onTextChange: (text: string) => void;
  /** Callback when matching text changes */
  onMatchingTextChange?: (text: string) => void;
  /** Callback when add image button is clicked */
  onImageAdd: () => void;
  /** Callback when remove image button is clicked */
  onImageRemove: () => void;
  /** Callback when image is set directly (for drag-and-drop) */
  onImageSet?: (imageData: { id: number; url: string } | null) => void;
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
  currentMatchingText,
  currentImage,
  placeholder = __("Write option...", "tutorpress"),
  requireImage = false,
  showImageUploadArea = false,
  showMatchingTextField = false,
  matchingTextPlaceholder = __("Matching text...", "tutorpress"),
  helperText,
  onTextChange,
  onMatchingTextChange,
  onImageAdd,
  onImageRemove,
  onImageSet,
  onSave,
  onCancel,
  isSaving,
  autoFocus = true,
  rows = 3,
}) => {
  // Determine if save button should be disabled
  const isSaveDisabled =
    isSaving ||
    !currentText.trim() ||
    (requireImage && !currentImage) ||
    (showMatchingTextField && !currentMatchingText?.trim());

  // Drag and drop handlers for upload area
  const [isDragOver, setIsDragOver] = React.useState(false);

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(true);
  };

  const handleDragEnter = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(true);
  };

  const handleDragLeave = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(false);
  };

  const handleDrop = (e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(false);

    const files = Array.from(e.dataTransfer.files);
    const imageFile = files.find((file) => file.type.startsWith("image/"));

    if (imageFile) {
      // Upload the dropped image file directly
      uploadImageFile(imageFile);
    } else {
      // No valid image file, fallback to media library
      onImageAdd();
    }
  };

  // Upload image file directly to WordPress
  const uploadImageFile = async (file: File) => {
    if (!file.type.startsWith("image/")) {
      console.error("File is not an image");
      return;
    }

    try {
      const formData = new FormData();
      formData.append("file", file);

      const response = await fetch((window as any).wpApiSettings?.root + "wp/v2/media", {
        method: "POST",
        headers: {
          "X-WP-Nonce": (window as any).wpApiSettings?.nonce || "",
        },
        body: formData,
      });

      if (response.ok) {
        const attachment = await response.json();
        const imageData = {
          id: attachment.id,
          url: attachment.source_url || attachment.url,
        };

        // For drag-and-drop, we need a way to directly set the image
        // Since the current architecture expects onImageAdd to open media library,
        // we'll need to modify the parent component to handle direct image setting
        // For now, let's try to use the existing hook pattern
        if (onImageSet) {
          onImageSet(imageData);
        } else {
          // Fallback: open media library (but the image should be there now)
          onImageAdd();
        }
      } else {
        console.error("Failed to upload image");
        // Fallback to media library
        onImageAdd();
      }
    } catch (error) {
      console.error("Error uploading image:", error);
      // Fallback to media library
      onImageAdd();
    }
  };

  return (
    <div className="quiz-modal-option-editor">
      {/* Editor Header */}
      <div className="quiz-modal-option-editor-header">
        <span className="quiz-modal-option-label">{optionLabel}.</span>
        {/* Show Add Image button only in standard mode when no image exists and not in text-only matching mode */}
        {!showImageUploadArea && !showMatchingTextField && !currentImage && (
          <button type="button" className="quiz-modal-add-image-btn" onClick={onImageAdd} disabled={isSaving}>
            {__("Add Image", "tutorpress")}
          </button>
        )}
      </div>

      {/* Image Upload Area (for image-required modes) */}
      {showImageUploadArea && (
        <div className="quiz-modal-image-upload-area" style={{ marginBottom: "16px" }}>
          {currentImage ? (
            <div
              className="quiz-modal-image-preview"
              style={{
                position: "relative",
                borderRadius: "8px",
                overflow: "hidden",
                backgroundColor: "#f3f4f6",
                minHeight: "200px",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <img
                src={currentImage.url}
                alt={__("Option image", "tutorpress")}
                className="quiz-modal-option-image"
                style={{
                  width: "100%",
                  height: "200px",
                  objectFit: "cover",
                  display: "block",
                }}
              />

              {/* Hover Overlay */}
              <div
                className="quiz-modal-image-overlay"
                style={{
                  position: "absolute",
                  top: 0,
                  left: 0,
                  right: 0,
                  bottom: 0,
                  backgroundColor: "rgba(0, 0, 0, 0.7)",
                  display: "flex",
                  flexDirection: "column",
                  alignItems: "center",
                  justifyContent: "center",
                  opacity: 0,
                  transition: "opacity 0.2s ease",
                  gap: "8px",
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.opacity = "1";
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.opacity = "0";
                }}
              >
                <button
                  type="button"
                  onClick={onImageAdd}
                  disabled={isSaving}
                  style={{
                    background: "#007cba",
                    color: "white",
                    border: "none",
                    padding: "8px 16px",
                    borderRadius: "4px",
                    cursor: "pointer",
                    fontSize: "14px",
                    fontWeight: "500",
                    transition: "all 0.15s ease",
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.backgroundColor = "#005a87";
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.backgroundColor = "#007cba";
                  }}
                >
                  {__("Replace Image", "tutorpress")}
                </button>

                <button
                  type="button"
                  onClick={onImageRemove}
                  disabled={isSaving}
                  style={{
                    background: "transparent",
                    color: "white",
                    border: "none",
                    padding: "4px 8px",
                    cursor: "pointer",
                    fontSize: "12px",
                    transition: "all 0.15s ease",
                  }}
                  onMouseEnter={(e) => {
                    e.currentTarget.style.color = "#fca5a5";
                  }}
                  onMouseLeave={(e) => {
                    e.currentTarget.style.color = "white";
                  }}
                >
                  {__("Remove", "tutorpress")}
                </button>
              </div>
            </div>
          ) : (
            <div
              className="quiz-modal-image-upload-placeholder"
              onDragOver={handleDragOver}
              onDragEnter={handleDragEnter}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              onClick={onImageAdd}
              style={{
                border: `2px dashed ${isDragOver ? "#007cba" : "#d0d5dd"}`,
                borderRadius: "8px",
                padding: "32px 20px",
                textAlign: "center",
                cursor: "pointer",
                backgroundColor: isDragOver ? "#f0f8ff" : "#fafbfc",
                minHeight: "200px",
                display: "flex",
                flexDirection: "column",
                alignItems: "center",
                justifyContent: "center",
                transition: "all 0.2s ease",
              }}
              onMouseEnter={(e) => {
                if (!isDragOver) {
                  e.currentTarget.style.borderColor = "#007cba";
                  e.currentTarget.style.backgroundColor = "#f8fafc";
                }
              }}
              onMouseLeave={(e) => {
                if (!isDragOver) {
                  e.currentTarget.style.borderColor = "#d0d5dd";
                  e.currentTarget.style.backgroundColor = "#fafbfc";
                }
              }}
            >
              {/* Modern SVG Upload Icon */}
              <div style={{ marginBottom: "12px" }}>
                <svg
                  width="28"
                  height="28"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                  style={{ color: "#9ca3af" }}
                >
                  <path
                    d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </div>

              <button
                type="button"
                className="quiz-modal-upload-image-btn"
                onClick={onImageAdd}
                disabled={isSaving}
                style={{
                  background: "transparent",
                  color: "#374151",
                  border: "1px solid #d1d5db",
                  padding: "8px 16px",
                  borderRadius: "6px",
                  cursor: "pointer",
                  fontSize: "14px",
                  fontWeight: "500",
                  marginBottom: "8px",
                  transition: "all 0.15s ease",
                }}
                onMouseEnter={(e) => {
                  e.currentTarget.style.backgroundColor = "#f9fafb";
                  e.currentTarget.style.borderColor = "#9ca3af";
                }}
                onMouseLeave={(e) => {
                  e.currentTarget.style.backgroundColor = "transparent";
                  e.currentTarget.style.borderColor = "#d1d5db";
                }}
              >
                {__("Upload Image", "tutorpress")}
              </button>

              <p
                style={{
                  margin: "0",
                  fontSize: "12px",
                  color: "#6b7280",
                  lineHeight: "1.4",
                  marginBottom: "4px",
                }}
              >
                {__("or drag and drop", "tutorpress")}
              </p>

              <p
                className="quiz-modal-upload-instruction"
                style={{
                  margin: "0",
                  fontSize: "11px",
                  color: "#9ca3af",
                  lineHeight: "1.4",
                }}
              >
                {__("PNG, JPG up to 10MB • Recommended: 700x430px", "tutorpress")}
              </p>
            </div>
          )}
        </div>
      )}

      {/* Image Display (for standard mode when image exists) */}
      {!showImageUploadArea && currentImage && (
        <div
          className="quiz-modal-option-image-container"
          style={{
            position: "relative",
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
            src={currentImage.url}
            alt={__("Option image", "tutorpress")}
            className="quiz-modal-option-image"
            style={{
              width: "100%",
              height: "200px",
              objectFit: "cover",
              display: "block",
            }}
          />

          {/* Hover Overlay */}
          <div
            className="quiz-modal-image-overlay"
            style={{
              position: "absolute",
              top: 0,
              left: 0,
              right: 0,
              bottom: 0,
              backgroundColor: "rgba(0, 0, 0, 0.7)",
              display: "flex",
              flexDirection: "column",
              alignItems: "center",
              justifyContent: "center",
              opacity: 0,
              transition: "opacity 0.2s ease",
              gap: "8px",
            }}
            onMouseEnter={(e) => {
              e.currentTarget.style.opacity = "1";
            }}
            onMouseLeave={(e) => {
              e.currentTarget.style.opacity = "0";
            }}
          >
            <button
              type="button"
              onClick={onImageAdd}
              disabled={isSaving}
              style={{
                background: "#007cba",
                color: "white",
                border: "none",
                padding: "8px 16px",
                borderRadius: "4px",
                cursor: "pointer",
                fontSize: "14px",
                fontWeight: "500",
                transition: "all 0.15s ease",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.backgroundColor = "#005a87";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.backgroundColor = "#007cba";
              }}
            >
              {__("Replace Image", "tutorpress")}
            </button>

            <button
              type="button"
              onClick={onImageRemove}
              disabled={isSaving}
              style={{
                background: "transparent",
                color: "white",
                border: "none",
                padding: "4px 8px",
                cursor: "pointer",
                fontSize: "12px",
                transition: "all 0.15s ease",
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.color = "#fca5a5";
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.color = "white";
              }}
            >
              {__("Remove", "tutorpress")}
            </button>
          </div>
        </div>
      )}

      {/* Text Fields - Order matters for matching questions */}
      {showMatchingTextField ? (
        <>
          {/* Matching Text Field (first - "Question" placeholder) */}
          <textarea
            className="quiz-modal-option-textarea quiz-modal-matching-textarea"
            placeholder={matchingTextPlaceholder}
            value={currentMatchingText || ""}
            onChange={(e) => onMatchingTextChange?.(e.target.value)}
            rows={2}
            disabled={isSaving}
            autoFocus={autoFocus}
            style={{
              marginBottom: "8px",
            }}
          />

          {/* Main Text Field (second - "Matched option" placeholder) */}
          <textarea
            className="quiz-modal-option-textarea"
            placeholder={placeholder}
            value={currentText}
            onChange={(e) => onTextChange(e.target.value)}
            rows={rows}
            disabled={isSaving}
          />
        </>
      ) : (
        /* Standard Text Editor (for non-matching questions) */
        <textarea
          className="quiz-modal-option-textarea"
          placeholder={placeholder}
          value={currentText}
          onChange={(e) => onTextChange(e.target.value)}
          rows={rows}
          disabled={isSaving}
          autoFocus={autoFocus}
        />
      )}

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
