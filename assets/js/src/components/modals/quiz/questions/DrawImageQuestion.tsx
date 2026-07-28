/**
 * Draw Image Question Component
 *
 * @description Editor for Tutor LMS 4.0's native `draw_image` question type. The
 *              background image lives in `image_id`/`image_url`, while the instructor
 *              mask lives separately in `answer_two_gap_match`. Tutor Pro owns mask
 *              storage, attempt rendering, overlap comparison, grading, and file cleanup.
 *
 * @features
 * - WordPress Media Library background selection through the shared image canvas
 * - Freehand instructor-mask authoring in displayed CSS-pixel coordinates
 * - Tutor's native precision choices and 70-percent creation default
 * - Exact preservation of stored masks until the author changes the answer
 * - Defensive preservation of values unavailable without Tutor Pro
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React from "react";
import { __ } from "@wordpress/i18n";
import { QuizImageCanvas } from "./QuizImageCanvas";
import { ValidationDisplay } from "./ValidationDisplay";
import {
  DRAW_IMAGE_THRESHOLD_OPTIONS,
  getDrawImageAnswerState,
  useQuestionValidation,
} from "../../../../hooks/quiz/useQuestionValidation";
import type { ImageData } from "../../../../hooks/quiz/useImageManagement";
import type { DataStatus, QuizQuestion, QuizQuestionOption } from "../../../../types/quiz";
import { createDefaultAnswerRow } from "../../../../utils/quizQuestionTypes";

interface DrawImageQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

/** Tutor's native answer-row discriminator for Draw Image. */
const DRAW_IMAGE_ANSWER_VIEW_FORMAT = "draw_image";

/** Tutor's fallback when a stored Draw question predates the explicit setting. */
const DRAW_IMAGE_DEFAULT_THRESHOLD = 70;

export const DrawImageQuestion: React.FC<DrawImageQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  const existingAnswers = question.question_answers || [];
  const answerRow = existingAnswers.length === 1 ? existingAnswers[0] : null;
  const answerState = getDrawImageAnswerState(question);

  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);

  /**
   * Update Tutor's single generic answer row, creating it only after the author selects
   * an image. Mounting a new or stored question therefore performs no answer write.
   */
  const writeAnswer = (changes: Partial<QuizQuestionOption>) => {
    const current = answerRow ?? createDefaultAnswerRow(question, 1);
    const updated: QuizQuestionOption = {
      ...current,
      belongs_question_type: "draw_image",
      is_correct: "1",
      answer_view_format: DRAW_IMAGE_ANSWER_VIEW_FORMAT,
      ...changes,
      _data_status: (current._data_status === "new" ? "new" : "update") as DataStatus,
    };

    onQuestionUpdate(questionIndex, "question_answers", [updated]);
  };

  /**
   * A background and its mask are one authoring operation but two stored values. A mask
   * cannot describe a different image, so selecting a background always clears it locally.
   * No temporary-deletion value is produced: an unsaved mask is only a data URL.
   */
  const handleImageSelect = (imageData: ImageData) => {
    writeAnswer({
      image_id: imageData.id,
      image_url: imageData.url,
      answer_two_gap_match: "",
    });
  };

  /**
   * Removing the background also invalidates the mask. Persisted-mask cleanup, if a later
   * valid answer update is submitted, belongs to Tutor's guarded server update path.
   */
  const handleImageClear = () => {
    if (!answerRow) {
      return;
    }

    writeAnswer({
      image_id: 0,
      image_url: "",
      answer_two_gap_match: "",
    });
  };

  /**
   * The shared canvas reports only explicit stroke completion or explicit clearing.
   * Stored-mask load and resize never reach this writer.
   */
  const handleMaskCommit = (maskDataUrl: string) => {
    if (!answerRow) {
      return;
    }

    writeAnswer({ answer_two_gap_match: maskDataUrl });
  };

  const handleThresholdChange = (rawValue: string) => {
    onQuestionUpdate(questionIndex, "question_settings", {
      ...question.question_settings,
      draw_image_threshold_percent: Number(rawValue),
    });
  };

  if (answerState === "preserved") {
    return (
      <div className="quiz-modal-draw-image-content">
        <div className="quiz-modal-draw-image-preserved-notice">
          <strong className="quiz-modal-draw-image-preserved-title">
            {__("This Draw Image answer cannot be edited here.", "tutorpress")}
          </strong>
          <p className="quiz-modal-draw-image-preserved-description">
            {__(
              "TutorPress cannot safely display the stored image or mask, so every value has been left exactly as saved. Restore Tutor LMS Pro or use Tutor's course builder to edit this answer.",
              "tutorpress"
            )}
          </p>
        </div>
      </div>
    );
  }

  const rawThreshold = question.question_settings?.draw_image_threshold_percent;
  const threshold = rawThreshold === undefined || rawThreshold === null ? DRAW_IMAGE_DEFAULT_THRESHOLD : rawThreshold;

  return (
    <div className="quiz-modal-draw-image-content">
      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />

      <div className="quiz-modal-draw-image-section">
        <QuizImageCanvas
          imageUrl={answerRow?.image_url}
          maskValue={answerRow?.answer_two_gap_match}
          onImageSelect={handleImageSelect}
          onImageClear={handleImageClear}
          onMaskCommit={handleMaskCommit}
          mediaTitle={__("Select Draw Image Background", "tutorpress")}
          emptyImageHelpText={__("Upload the base image students will draw on.", "tutorpress")}
          maskSectionTitle={__("Mark the correct area", "tutorpress")}
          maskInstructions={__(
            "Draw the area students should mark. Use a pointer or touch, or press Enter and trace with the arrow keys. Press Enter again to save the stroke, Escape to cancel it, or C to clear the saved area.",
            "tutorpress"
          )}
          maskLabel={__("Draw Image answer area", "tutorpress")}
          savedMaskHint={__("Answer zone saved. Students will be graded against this area.", "tutorpress")}
          isSaving={isSaving}
        />
      </div>

      <div className="quiz-modal-draw-image-field">
        <label
          className="quiz-modal-draw-image-label"
          htmlFor={`quiz-modal-draw-image-threshold-${question.question_id}`}
        >
          {__("Precision Level", "tutorpress")}
        </label>
        <select
          id={`quiz-modal-draw-image-threshold-${question.question_id}`}
          className="quiz-modal-draw-image-select"
          value={String(threshold)}
          onChange={(event) => handleThresholdChange(event.target.value)}
          disabled={isSaving}
        >
          {DRAW_IMAGE_THRESHOLD_OPTIONS.map((value) => (
            <option key={value} value={value}>
              {value}%
            </option>
          ))}
        </select>
        <span className="quiz-modal-draw-image-help-text">
          {__(
            "Minimum overlap score between student and instructor markings. Larger or smaller marked areas lower the score.",
            "tutorpress"
          )}
        </span>
      </div>
    </div>
  );
};
