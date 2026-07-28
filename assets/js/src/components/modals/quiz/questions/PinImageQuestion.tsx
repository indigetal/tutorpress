/**
 * Pin Image Question Component
 *
 * @description Editor for Tutor LMS 4.0's native `pin_image` question type. The
 *              background image lives in `image_id`/`image_url`, while the instructor
 *              target mask lives separately in `answer_two_gap_match`. Tutor Pro owns
 *              mask storage, student point capture, grading, rendering, and file cleanup.
 *
 * @features
 * - WordPress Media Library background selection through the shared image canvas
 * - Freehand target-mask authoring in displayed CSS-pixel coordinates
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
  getPinImageAnswerState,
  useQuestionValidation,
} from "../../../../hooks/quiz/useQuestionValidation";
import type { ImageData } from "../../../../hooks/quiz/useImageManagement";
import type { DataStatus, QuizQuestion, QuizQuestionOption } from "../../../../types/quiz";
import { createDefaultAnswerRow } from "../../../../utils/quizQuestionTypes";

interface PinImageQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

/** Tutor's native answer-row discriminator for Pin Image. */
const PIN_IMAGE_ANSWER_VIEW_FORMAT = "pin_image";

export const PinImageQuestion: React.FC<PinImageQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  const existingAnswers = question.question_answers || [];
  const answerRow = existingAnswers.length === 1 ? existingAnswers[0] : null;
  const answerState = getPinImageAnswerState(question);

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
      belongs_question_type: "pin_image",
      is_correct: "1",
      answer_view_format: PIN_IMAGE_ANSWER_VIEW_FORMAT,
      ...changes,
      _data_status: (current._data_status === "new" ? "new" : "update") as DataStatus,
    };

    onQuestionUpdate(questionIndex, "question_answers", [updated]);
  };

  /**
   * A background and its target mask are one authoring operation but two stored values.
   * A mask cannot describe a different image, so selecting a background clears it locally.
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

  if (answerState === "preserved") {
    return (
      <div className="quiz-modal-pin-image-content">
        <div className="quiz-modal-pin-image-preserved-notice">
          <strong className="quiz-modal-pin-image-preserved-title">
            {__("This Pin Image answer cannot be edited here.", "tutorpress")}
          </strong>
          <p className="quiz-modal-pin-image-preserved-description">
            {__(
              "TutorPress cannot safely display the stored image or target area, so every value has been left exactly as saved. Restore Tutor LMS Pro or use Tutor's course builder to edit this answer.",
              "tutorpress"
            )}
          </p>
        </div>
      </div>
    );
  }

  return (
    <div className="quiz-modal-pin-image-content">
      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />

      <div className="quiz-modal-pin-image-section">
        <QuizImageCanvas
          imageUrl={answerRow?.image_url}
          maskValue={answerRow?.answer_two_gap_match}
          onImageSelect={handleImageSelect}
          onImageClear={handleImageClear}
          onMaskCommit={handleMaskCommit}
          mediaTitle={__("Select Pin Image Background", "tutorpress")}
          emptyImageHelpText={__("Upload the base image students will place a pin on.", "tutorpress")}
          maskSectionTitle={__("Mark the correct area", "tutorpress")}
          maskInstructions={__(
            "Draw the area where students should place their pin. Use a pointer or touch, or press Enter and trace with the arrow keys. Press Enter again to save the stroke, Escape to cancel it, or C to clear the saved area.",
            "tutorpress"
          )}
          maskLabel={__("Pin Image answer area", "tutorpress")}
          savedMaskHint={__("Target zone saved. Student pins will be graded against this area.", "tutorpress")}
          isSaving={isSaving}
        />
      </div>
    </div>
  );
};
