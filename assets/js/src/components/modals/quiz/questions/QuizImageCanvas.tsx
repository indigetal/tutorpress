/**
 * Quiz Image Canvas Component
 *
 * @description Shared background-image selection and freehand mask authoring UI for Tutor
 *              LMS 4.0's `draw_image` and `pin_image` question types, which differ only in
 *              their wording. Image selection reuses TutorPress's existing WordPress Media
 *              Library hook; mask behaviour lives in `useQuizImageCanvas`. This component
 *              owns no answer-row state: it reports a selected image, a cleared image, and a
 *              committed mask, and the calling editor decides what to persist.
 *
 * @features
 * - Media Library selection, replacement, and removal of the background image
 * - Freehand mask drawing by pointer, touch, or keyboard, with a live-region status
 * - A mask that survives resize without being re-encoded or re-committed
 * - A failed image or mask load is reported, never repaired, and never clears stored data
 *
 * @usage
 * <QuizImageCanvas
 *   imageUrl={option.image_url}
 *   maskValue={option.answer_two_gap_match}
 *   onImageSelect={handleImageSelect}
 *   onImageClear={handleImageClear}
 *   onMaskCommit={handleMaskCommit}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React, { useCallback, useRef } from "react";
import { __ } from "@wordpress/i18n";
import { useImageManagement, type ImageData } from "../../../../hooks/quiz";
import { isSafeImageSource, useQuizImageCanvas } from "../../../../hooks/quiz/useQuizImageCanvas";

interface QuizImageCanvasProps {
  /** Background image URL from the answer row, when one is set. */
  imageUrl?: string;
  /** Stored mask value from the answer row. Displayed, never re-committed. */
  maskValue?: string;
  /** Called with the Media Library selection. */
  onImageSelect: (imageData: ImageData) => void;
  /** Called when the background image is removed. */
  onImageClear: () => void;
  /** Called with an exported PNG data URL, or the empty string when the mask is cleared. */
  onMaskCommit: (maskDataUrl: string) => void;
  /** Media Library dialog title. */
  mediaTitle?: string;
  /** Guidance shown before an image is selected. */
  emptyImageHelpText?: string;
  /** Heading for the mask section. */
  maskSectionTitle?: string;
  /** Instructions for the drawing surface, announced to screen readers. */
  maskInstructions?: string;
  /** Accessible name for the drawing surface. */
  maskLabel?: string;
  /** Confirmation shown once a mask is stored. */
  savedMaskHint?: string;
  /** Disables the controls while a save is in flight. */
  isSaving?: boolean;
}

export const QuizImageCanvas: React.FC<QuizImageCanvasProps> = ({
  imageUrl,
  maskValue,
  onImageSelect,
  onImageClear,
  onMaskCommit,
  mediaTitle = __("Select Background Image", "tutorpress"),
  emptyImageHelpText = __("Upload the base image students will work on.", "tutorpress"),
  maskSectionTitle = __("Mark the correct area", "tutorpress"),
  maskInstructions = __(
    "Drag to trace the correct area. Using the keyboard: press Enter to start a stroke, trace with the arrow keys, then press Enter again to finish. Escape cancels a stroke in progress, and C clears the saved area.",
    "tutorpress"
  ),
  maskLabel = __("Drawing surface: mark the correct answer area.", "tutorpress"),
  savedMaskHint = __("Correct area saved. Students are graded against this area.", "tutorpress"),
  isSaving = false,
}) => {
  const imageRef = useRef<HTMLImageElement | null>(null);
  const canvasRef = useRef<HTMLCanvasElement | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);
  const liveRegionRef = useRef<HTMLDivElement | null>(null);

  const { openMediaLibrary } = useImageManagement();

  // A stored value reaches an image source only after validation, since rows are external data.
  const safeImageUrl = isSafeImageSource(imageUrl) ? imageUrl : undefined;
  const safeMaskValue = isSafeImageSource(maskValue) ? maskValue : undefined;

  const announce = useCallback((message: string) => {
    const liveRegion = liveRegionRef.current;
    if (!liveRegion) {
      return;
    }

    // Clearing first makes repeat announcements register with screen readers.
    liveRegion.textContent = "";
    window.requestAnimationFrame(() => {
      liveRegion.textContent = message;
    });
  }, []);

  const { clearMask, hasMask, hasLoadError, hasExportError, handleImageLoad, handleImageError } = useQuizImageCanvas({
    imageRef,
    canvasRef,
    containerRef,
    imageUrl: safeImageUrl,
    initialMaskValue: safeMaskValue,
    onMaskCommit,
    onAnnounce: announce,
  });

  const handleSelectImage = useCallback(() => {
    openMediaLibrary(
      {
        title: mediaTitle,
        buttonText: __("Use this image", "tutorpress"),
        multiple: false,
        allowedTypes: ["image"],
      },
      onImageSelect
    );
  }, [mediaTitle, onImageSelect, openMediaLibrary]);

  const handleClearMask = useCallback(() => {
    clearMask();
    announce(__("Correct area cleared.", "tutorpress"));
  }, [announce, clearMask]);

  return (
    <div className="quiz-modal-image-canvas">
      <div className="quiz-modal-image-canvas-actions">
        <button
          type="button"
          className="quiz-modal-image-canvas-button"
          onClick={handleSelectImage}
          disabled={isSaving}
        >
          {safeImageUrl ? __("Replace Image", "tutorpress") : __("Upload Image", "tutorpress")}
        </button>
        {safeImageUrl && (
          <button
            type="button"
            className="quiz-modal-image-canvas-button"
            onClick={onImageClear}
            disabled={isSaving}
          >
            {__("Remove Image", "tutorpress")}
          </button>
        )}
      </div>

      {!safeImageUrl && <p className="quiz-modal-image-canvas-help-text">{emptyImageHelpText}</p>}

      {safeImageUrl && (
        <div className="quiz-modal-image-canvas-section">
          <div className="quiz-modal-image-canvas-header">
            <span className="quiz-modal-image-canvas-section-title">{maskSectionTitle}</span>
            {hasMask && (
              <button
                type="button"
                className="quiz-modal-image-canvas-button"
                onClick={handleClearMask}
                disabled={isSaving}
              >
                {__("Clear Area", "tutorpress")}
              </button>
            )}
          </div>

          <p id="quiz-modal-image-canvas-instructions" className="screen-reader-text">
            {maskInstructions}
          </p>
          <div
            ref={liveRegionRef}
            className="screen-reader-text"
            role="status"
            aria-live="polite"
            aria-atomic="true"
          />

          <div className="quiz-modal-image-canvas-stage" ref={containerRef}>
            <img
              ref={imageRef}
              src={safeImageUrl}
              alt={__("Background image for the marked area", "tutorpress")}
              className="quiz-modal-image-canvas-image"
              onLoad={handleImageLoad}
              onError={handleImageError}
            />
            <canvas
              ref={canvasRef}
              className="quiz-modal-image-canvas-surface"
              tabIndex={0}
              role="application"
              aria-label={maskLabel}
              aria-describedby="quiz-modal-image-canvas-instructions"
            />
          </div>

          {hasLoadError && (
            <p className="quiz-modal-image-canvas-notice">
              {__(
                "This image or its saved area could not be loaded. The stored answer has been left unchanged.",
                "tutorpress"
              )}
            </p>
          )}

          {hasExportError && (
            <p className="quiz-modal-image-canvas-notice">
              {__(
                "This area could not be saved because the image blocked reading the drawing surface. The stored answer has been left unchanged.",
                "tutorpress"
              )}
            </p>
          )}

          {hasMask && !hasExportError && <p className="quiz-modal-image-canvas-saved-hint">{savedMaskHint}</p>}
        </div>
      )}
    </div>
  );
};
