/**
 * Puzzle Question Component
 *
 * @description Editor for Tutor LMS 4.0's native `puzzle` question type. TutorPress
 *              authors only the source image and grid size. Tutor Pro owns source
 *              cloning, student pieces, signed tokens, locks, snapshots, grading,
 *              attempt rendering, and file deletion.
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React from "react";
import { __ } from "@wordpress/i18n";
import { ValidationDisplay } from "./ValidationDisplay";
import {
  getPuzzleAnswerState,
  getPuzzleGridState,
  NATIVE_PUZZLE_GRID_SIZE,
  PUZZLE_GRID_SIZE_OPTIONS,
  useQuestionValidation,
} from "../../../../hooks/quiz/useQuestionValidation";
import {
  useImageManagement,
  type ImageData,
} from "../../../../hooks/quiz/useImageManagement";
import type { DataStatus, QuizQuestion, QuizQuestionOption } from "../../../../types/quiz";

interface PuzzleQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

/** Explicit Puzzle image operations use Tutor's type-specific format. */
const PUZZLE_ANSWER_VIEW_FORMAT = "puzzle";

const getPuzzleDifficulty = (gridSize: number): string => {
  if (gridSize <= 3) {
    return __("Easy", "tutorpress");
  }
  if (gridSize <= 5) {
    return __("Medium", "tutorpress");
  }
  return __("Hard", "tutorpress");
};

export const PuzzleQuestion: React.FC<PuzzleQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  const answerState = getPuzzleAnswerState(question);
  const gridState = getPuzzleGridState(question);
  const answerRow = question.question_answers?.length === 1 ? question.question_answers[0] : null;
  const sourceUrl =
    (typeof answerRow?.image_url === "string" && answerRow.image_url.trim()) ||
    (typeof answerRow?.answer_two_gap_match === "string" && answerRow.answer_two_gap_match.trim()) ||
    "";

  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);
  const { openMediaLibrary, removeImage, isMediaLibraryAvailable } = useImageManagement();

  const writeImageAnswer = (imageChanges: Partial<QuizQuestionOption>) => {
    if (!answerRow) {
      return;
    }

    const updated: QuizQuestionOption = {
      ...answerRow,
      belongs_question_type: "puzzle",
      is_correct: "1",
      answer_view_format: PUZZLE_ANSWER_VIEW_FORMAT,
      ...imageChanges,
      _data_status: (answerRow._data_status === "new" ? "new" : "update") as DataStatus,
    };

    onQuestionUpdate(questionIndex, "question_answers", [updated]);
  };

  const handleSelectedImage = (imageData: ImageData) => {
    if (!Number.isInteger(imageData.id) || imageData.id <= 0 || !imageData.url) {
      return;
    }

    writeImageAnswer({
      image_id: imageData.id,
      image_url: imageData.url,
      answer_two_gap_match: imageData.url,
    });
  };

  const handleOpenMediaLibrary = () => {
    openMediaLibrary(
      {
        title: __("Select Puzzle Image", "tutorpress"),
        buttonText: __("Use this image", "tutorpress"),
        multiple: false,
        allowedTypes: ["image"],
      },
      handleSelectedImage
    );
  };

  const handleClearImage = () => {
    if (!answerRow) {
      return;
    }

    removeImage(() => {
      writeImageAnswer({
        image_id: undefined,
        image_url: "",
        answer_two_gap_match: "",
      });
    });
  };

  const handleGridChange = (rawValue: string) => {
    const gridSize = Number(rawValue);
    if (!(PUZZLE_GRID_SIZE_OPTIONS as readonly number[]).includes(gridSize)) {
      return;
    }

    onQuestionUpdate(questionIndex, "question_settings", {
      ...question.question_settings,
      puzzle_grid_size: gridSize,
    });
  };

  if (answerState === "preserved" || gridState.status === "preserved") {
    return (
      <div className="quiz-modal-puzzle-content">
        <div className="quiz-modal-puzzle-preserved-notice">
          <strong className="quiz-modal-puzzle-preserved-title">
            {__("This Puzzle answer cannot be edited here.", "tutorpress")}
          </strong>
          <p className="quiz-modal-puzzle-preserved-description">
            {__(
              "TutorPress cannot safely display this stored Puzzle answer, so every value has been left exactly as saved. Restore Tutor LMS Pro or use Tutor's course builder to edit it.",
              "tutorpress"
            )}
          </p>
        </div>
      </div>
    );
  }

  const selectedGridSize =
    gridState.status === "editable" ? gridState.value : NATIVE_PUZZLE_GRID_SIZE;

  return (
    <div className="quiz-modal-puzzle-content">
      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />

      <div className="quiz-modal-puzzle-section">
        <div className="quiz-modal-puzzle-section-header">
          <strong className="quiz-modal-puzzle-section-title">
            {__("Puzzle Image", "tutorpress")}
          </strong>
          <div className="quiz-modal-puzzle-actions">
            <button
              type="button"
              className="quiz-modal-puzzle-button"
              onClick={handleOpenMediaLibrary}
              disabled={isSaving || !isMediaLibraryAvailable()}
            >
              {sourceUrl
                ? __("Replace Puzzle Image", "tutorpress")
                : __("Upload Puzzle Image", "tutorpress")}
            </button>
            {sourceUrl && (
              <button
                type="button"
                className="quiz-modal-puzzle-button quiz-modal-puzzle-button--remove"
                onClick={handleClearImage}
                disabled={isSaving}
              >
                {__("Remove Image", "tutorpress")}
              </button>
            )}
          </div>
        </div>

        {sourceUrl ? (
          <img
            className="quiz-modal-puzzle-image"
            src={sourceUrl}
            alt={__("Selected puzzle source", "tutorpress")}
          />
        ) : (
          <p className="quiz-modal-puzzle-placeholder">
            {__("Upload the source image that will be split into puzzle pieces.", "tutorpress")}
          </p>
        )}
      </div>

      {sourceUrl ? (
        <div className="quiz-modal-puzzle-field">
          <label
            className="quiz-modal-puzzle-label"
            htmlFor={`quiz-modal-puzzle-grid-${question.question_id}`}
          >
            {__("Difficulty Level", "tutorpress")}
          </label>
          <select
            id={`quiz-modal-puzzle-grid-${question.question_id}`}
            className="quiz-modal-puzzle-select"
            value={selectedGridSize}
            onChange={(event) => handleGridChange(event.target.value)}
            disabled={isSaving}
          >
            {PUZZLE_GRID_SIZE_OPTIONS.map((gridSize) => (
              <option key={gridSize} value={gridSize}>
                {`${getPuzzleDifficulty(gridSize)} - ${gridSize}×${gridSize} (${gridSize * gridSize} ${__(
                  "pieces",
                  "tutorpress"
                )})`}
              </option>
            ))}
          </select>
          <span className="quiz-modal-puzzle-help-text">
            {__("Larger grids create more pieces and a harder puzzle for learners.", "tutorpress")}
          </span>
        </div>
      ) : (
        <p className="quiz-modal-puzzle-help-text">
          {__("Upload an image first, then configure the grid size for the puzzle.", "tutorpress")}
        </p>
      )}
    </div>
  );
};
