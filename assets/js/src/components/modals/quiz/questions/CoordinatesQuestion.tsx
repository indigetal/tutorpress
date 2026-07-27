/**
 * Graph (Coordinates) Question Component
 *
 * @description Editor for Tutor LMS 4.0's native `coordinates` question type. Graph keeps
 *              its correct answer in a single generic answer row as a bare JSON array of
 *              integer `{x,y}` points in `answer_two_gap_match`, with `answer_view_format`
 *              set to `coordinates`, and its axis range in the question's
 *              `coordinates_axis_range` setting. Tutor Pro owns attempt rendering and
 *              grading; this component only authors the contract Pro consumes.
 *
 * @features
 * - Axis range selector limited to Tutor's two native values, spanning `-n` through `n`
 * - Click-to-place points on a snapping grid, plus a per-point `x,y` text control
 * - One to five points, with the last point undeletable, matching Tutor's own invariants
 * - Duplicate points permitted, as in Tutor; its grader compares deduplicated point sets
 * - Defensive parsing: a stored value TutorPress cannot read is reported, never replaced
 *
 * @usage
 * <CoordinatesQuestion
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

import React, { useEffect, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { ValidationDisplay } from "./ValidationDisplay";
import { useQuestionValidation } from "../../../../hooks/quiz";
import type { DataStatus, QuizQuestion, QuizQuestionOption } from "../../../../types/quiz";
import {
  COORDINATES_AXIS_RANGE_OPTIONS,
  createDefaultAnswerRow,
  MAX_COORDINATE_POINTS,
  NATIVE_COORDINATES_DEFAULT_POINT,
  parseCoordinatesAnswer,
  resolveCoordinatesAxisRange,
  sanitizeCoordinatePoint,
  serializeCoordinatesAnswer,
  type CoordinatePoint,
  type CoordinatesAxisRange,
} from "../../../../utils/quizQuestionTypes";

interface CoordinatesQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

/** Tutor's stored value for a Graph answer row. */
const COORDINATES_ANSWER_VIEW_FORMAT = "coordinates";

/** Logical grid size. The rendered grid is a responsive square scaled from this viewBox. */
const GRID_VIEWBOX_SIZE = 420;

/** Inset reserved for axis labels, in viewBox units. */
const GRID_PADDING = 16;

/** How near a lattice point a click must land, in grid units. Matches Tutor's threshold. */
const SNAP_THRESHOLD = 0.3;

/** Axis ticks are labelled every this many units, as in Tutor's grid. */
const AXIS_LABEL_STEP = 2;

/**
 * Grid geometry for an axis range, in viewBox units.
 */
const getGridGeometry = (axisRange: CoordinatesAxisRange) => {
  const drawable = GRID_VIEWBOX_SIZE - 2 * GRID_PADDING;
  const center = GRID_PADDING + drawable / 2;

  return { center, pixelsPerUnit: drawable / (axisRange * 2) };
};

/**
 * Map a grid point to viewBox coordinates. The y axis points up, so it is inverted.
 */
const graphToViewBox = (point: CoordinatePoint, axisRange: CoordinatesAxisRange): { x: number; y: number } => {
  const { center, pixelsPerUnit } = getGridGeometry(axisRange);

  return { x: center + point.x * pixelsPerUnit, y: center - point.y * pixelsPerUnit };
};

/**
 * Map viewBox coordinates back to the nearest lattice point, or `null` when the position
 * is not close enough to one or falls outside the axis bounds.
 */
const viewBoxToSnappedGraph = (
  viewBoxX: number,
  viewBoxY: number,
  axisRange: CoordinatesAxisRange
): CoordinatePoint | null => {
  const { center, pixelsPerUnit } = getGridGeometry(axisRange);
  const graphX = (viewBoxX - center) / pixelsPerUnit;
  const graphY = (center - viewBoxY) / pixelsPerUnit;
  const snappedX = Math.round(graphX);
  const snappedY = Math.round(graphY);

  if (Math.abs(graphX - snappedX) > SNAP_THRESHOLD || Math.abs(graphY - snappedY) > SNAP_THRESHOLD) {
    return null;
  }
  if (Math.abs(snappedX) > axisRange || Math.abs(snappedY) > axisRange) {
    return null;
  }

  return { x: snappedX, y: snappedY };
};

/** Format a point for the text control. */
const formatPointText = (point: CoordinatePoint): string => `${point.x},${point.y}`;

/**
 * Parse `"x,y"` text into a point, requiring integers within the axis range.
 *
 * Mirrors the native editor's `parseCoordinateText()`: anything else is rejected so the
 * control can revert rather than storing a coerced value the author did not intend.
 */
const parsePointText = (raw: string, axisRange: CoordinatesAxisRange): CoordinatePoint | null => {
  const parts = (raw ?? "").trim().split(",");
  if (parts.length !== 2) {
    return null;
  }

  const x = Number(parts[0].trim());
  const y = Number(parts[1].trim());
  if (!Number.isInteger(x) || !Number.isInteger(y)) {
    return null;
  }
  if (Math.abs(x) > axisRange || Math.abs(y) > axisRange) {
    return null;
  }

  return { x, y };
};

export const CoordinatesQuestion: React.FC<CoordinatesQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  const existingAnswers = question.question_answers || [];
  const answerRow = existingAnswers.length > 0 ? existingAnswers[0] : null;
  const storedValue = answerRow?.answer_two_gap_match ?? "";
  const parsed = parseCoordinatesAnswer(storedValue);
  const axisRange = resolveCoordinatesAxisRange(question.question_settings?.coordinates_axis_range);

  // Points Tutor is actually storing. Nothing is written until the author commits an edit,
  // so a new question keeps Tutor's empty answer value.
  const committedPoints = parsed.status === "valid" ? parsed.points : [];

  // What the author sees. An uncommitted question shows one point at the origin, as
  // Tutor's own editor does, which is also why the last point can never be deleted.
  const displayPoints = committedPoints.length > 0 ? committedPoints : [NATIVE_COORDINATES_DEFAULT_POINT];

  const [activeIndex, setActiveIndex] = useState(0);
  const [drafts, setDrafts] = useState<string[]>(() => displayPoints.map(formatPointText));

  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);

  /**
   * Re-sync the text controls whenever the stored value changes underneath the editor.
   */
  useEffect(() => {
    const current = parseCoordinatesAnswer(storedValue);
    const points = current.status === "valid" ? current.points : [NATIVE_COORDINATES_DEFAULT_POINT];

    setDrafts(points.map(formatPointText));
    setActiveIndex((previous) => Math.max(0, Math.min(points.length - 1, previous)));
  }, [storedValue]);

  /**
   * Write a point set to the question's single answer row, creating the row if Tutor never
   * stored one. The only writer, so every path stamps the same native fields.
   *
   * An empty set is refused rather than serialized: Tutor cannot store one and its
   * validator rejects it.
   */
  const writeCoordinates = (points: CoordinatePoint[]) => {
    const serialized = serializeCoordinatesAnswer(points.map((point) => sanitizeCoordinatePoint(point, axisRange)));
    if (serialized === null) {
      return;
    }

    let updatedAnswers: QuizQuestionOption[];

    if (answerRow) {
      updatedAnswers = [...existingAnswers];
      updatedAnswers[0] = {
        ...answerRow,
        is_correct: "1",
        answer_view_format: COORDINATES_ANSWER_VIEW_FORMAT,
        answer_two_gap_match: serialized,
        _data_status: (answerRow._data_status === "new" ? "new" : "update") as DataStatus,
      };
    } else {
      updatedAnswers = [
        {
          ...createDefaultAnswerRow(question, 1),
          is_correct: "1",
          answer_view_format: COORDINATES_ANSWER_VIEW_FORMAT,
          answer_two_gap_match: serialized,
        },
      ];
    }

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
  };

  /**
   * Change the axis range.
   *
   * Stored points are left alone. Tutor does the same: a point outside the new grid keeps
   * its value and is reported until the author corrects it or the next commit clamps it.
   */
  const handleAxisRangeChange = (nextAxisRange: CoordinatesAxisRange) => {
    onQuestionUpdate(questionIndex, "question_settings", {
      ...question.question_settings,
      coordinates_axis_range: nextAxisRange,
    });
  };

  /**
   * Place a point at a grid position. An existing point there is selected instead of
   * duplicated, matching Tutor's grid behavior.
   */
  const handleGridSelect = (point: CoordinatePoint) => {
    const existingIndex = displayPoints.findIndex((candidate) => candidate.x === point.x && candidate.y === point.y);
    if (existingIndex !== -1) {
      setActiveIndex(existingIndex);
      return;
    }

    const nextPoints = [...displayPoints];
    nextPoints[activeIndex] = point;
    writeCoordinates(nextPoints);
  };

  const handleGridClick = (event: React.MouseEvent<SVGSVGElement>) => {
    if (isSaving) {
      return;
    }

    const bounds = event.currentTarget.getBoundingClientRect();
    if (!bounds.width || !bounds.height) {
      return;
    }

    // The grid renders as a responsive square, so pointer pixels are scaled back into
    // viewBox units before the snap test. Stored coordinates never depend on rendered size.
    const snapped = viewBoxToSnappedGraph(
      ((event.clientX - bounds.left) * GRID_VIEWBOX_SIZE) / bounds.width,
      ((event.clientY - bounds.top) * GRID_VIEWBOX_SIZE) / bounds.height,
      axisRange
    );

    if (snapped) {
      handleGridSelect(snapped);
    }
  };

  const handleDraftChange = (index: number, rawInput: string) => {
    setDrafts((previous) => previous.map((draft, draftIndex) => (draftIndex === index ? rawInput : draft)));
  };

  const handleDraftCommit = (index: number) => {
    const point = parsePointText(drafts[index] ?? "", axisRange);
    if (!point) {
      setDrafts((previous) =>
        previous.map((draft, draftIndex) =>
          draftIndex === index ? formatPointText(displayPoints[index] ?? NATIVE_COORDINATES_DEFAULT_POINT) : draft
        )
      );
      return;
    }

    const nextPoints = [...displayPoints];
    nextPoints[index] = point;
    writeCoordinates(nextPoints);
  };

  const handleAddPoint = () => {
    if (displayPoints.length >= MAX_COORDINATE_POINTS) {
      return;
    }

    writeCoordinates([...displayPoints, NATIVE_COORDINATES_DEFAULT_POINT]);
    setActiveIndex(displayPoints.length);
  };

  const handleDuplicatePoint = (index: number) => {
    if (displayPoints.length >= MAX_COORDINATE_POINTS) {
      return;
    }

    const nextPoints = [...displayPoints];
    nextPoints.splice(index + 1, 0, { ...displayPoints[index] });
    writeCoordinates(nextPoints);
    setActiveIndex(index + 1);
  };

  const handleRemovePoint = (index: number) => {
    // Tutor's editor hard-returns here too: a Graph question always keeps one point.
    if (displayPoints.length <= 1) {
      return;
    }

    writeCoordinates(displayPoints.filter((_, pointIndex) => pointIndex !== index));
    setActiveIndex((previous) => (previous >= index && previous > 0 ? previous - 1 : previous));
  };

  if (parsed.status === "malformed") {
    return (
      <div className="quiz-modal-coordinates-content">
        <div className="quiz-modal-coordinates-preserved-notice">
          <strong className="quiz-modal-coordinates-preserved-title">
            {__("These Graph coordinates cannot be read.", "tutorpress")}
          </strong>
          <p className="quiz-modal-coordinates-preserved-description">
            {__(
              "TutorPress could not interpret the stored coordinate data, so it has been left exactly as saved. Editing it here would discard it. Open the question in the Tutor LMS course builder to repair it.",
              "tutorpress"
            )}
          </p>
        </div>
      </div>
    );
  }

  const axisTicks: number[] = [];
  for (let tick = -axisRange; tick <= axisRange; tick += 1) {
    axisTicks.push(tick);
  }

  const gridEdgeStart = graphToViewBox({ x: -axisRange, y: -axisRange }, axisRange);
  const gridEdgeEnd = graphToViewBox({ x: axisRange, y: axisRange }, axisRange);
  const origin = graphToViewBox({ x: 0, y: 0 }, axisRange);
  const isAtPointLimit = displayPoints.length >= MAX_COORDINATE_POINTS;
  const isSinglePoint = displayPoints.length <= 1;

  return (
    <div className="quiz-modal-coordinates-content">
      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />

      <div className="quiz-modal-coordinates-section">
        <label
          className="quiz-modal-coordinates-label"
          htmlFor={`quiz-modal-coordinates-axis-${question.question_id}`}
        >
          {__("Axis range", "tutorpress")}
        </label>
        <select
          id={`quiz-modal-coordinates-axis-${question.question_id}`}
          className="quiz-modal-coordinates-axis-select"
          value={String(axisRange)}
          onChange={(event) => handleAxisRangeChange(resolveCoordinatesAxisRange(event.target.value))}
          disabled={isSaving}
        >
          {COORDINATES_AXIS_RANGE_OPTIONS.map((option) => (
            <option key={option} value={String(option)}>
              {sprintf(
                // translators: %d is the number of units the axis spans in each direction.
                __("%d Unit", "tutorpress"),
                option
              )}
            </option>
          ))}
        </select>
        <span className="quiz-modal-coordinates-help-text">
          {sprintf(
            // translators: %1$d is the lowest coordinate value, %2$d is the highest.
            __("Both axes run from %1$d to %2$d.", "tutorpress"),
            -axisRange,
            axisRange
          )}
        </span>
      </div>

      <div className="quiz-modal-coordinates-section">
        <span className="quiz-modal-coordinates-section-title">{__("Correct coordinates", "tutorpress")}</span>

        <div className="quiz-modal-coordinates-list">
          {displayPoints.map((point, index) => (
            <div
              key={`coordinate-${index}`}
              className={`quiz-modal-coordinates-row${
                index === activeIndex ? " quiz-modal-coordinates-row--active" : ""
              }`}
            >
              <span className="quiz-modal-coordinates-row-index">{index + 1}</span>
              <input
                className="quiz-modal-coordinates-input"
                type="text"
                inputMode="text"
                aria-label={sprintf(
                  // translators: %d is the position of the coordinate in the list.
                  __("Coordinate %d, as x,y", "tutorpress"),
                  index + 1
                )}
                placeholder={__("x,y", "tutorpress")}
                value={drafts[index] ?? formatPointText(point)}
                onFocus={() => setActiveIndex(index)}
                onChange={(event) => handleDraftChange(index, event.target.value)}
                onBlur={() => handleDraftCommit(index)}
                onKeyDown={(event) => {
                  if (event.key === "Enter") {
                    event.preventDefault();
                    handleDraftCommit(index);
                  }
                }}
                disabled={isSaving}
              />
              <button
                type="button"
                className="quiz-modal-coordinates-row-action"
                onClick={() => handleDuplicatePoint(index)}
                disabled={isSaving || isAtPointLimit}
                aria-label={__("Duplicate coordinate", "tutorpress")}
              >
                {__("Duplicate", "tutorpress")}
              </button>
              <button
                type="button"
                className="quiz-modal-coordinates-row-action"
                onClick={() => handleRemovePoint(index)}
                disabled={isSaving || isSinglePoint}
                aria-label={__("Delete coordinate", "tutorpress")}
              >
                {__("Delete", "tutorpress")}
              </button>
            </div>
          ))}
        </div>

        <button
          type="button"
          className="quiz-modal-coordinates-add-button"
          onClick={handleAddPoint}
          disabled={isSaving || isAtPointLimit}
        >
          {__("Add coordinate", "tutorpress")}
        </button>
      </div>

      <div className="quiz-modal-coordinates-grid-wrapper">
        <svg
          className="quiz-modal-coordinates-grid"
          viewBox={`0 0 ${GRID_VIEWBOX_SIZE} ${GRID_VIEWBOX_SIZE}`}
          role="img"
          aria-label={sprintf(
            // translators: %s is a comma separated list of the plotted coordinates.
            __("Coordinate grid. Correct answer points: %s. Click a grid intersection to move the selected point.", "tutorpress"),
            displayPoints.map((point) => `(${point.x}, ${point.y})`).join(", ")
          )}
          onClick={handleGridClick}
        >
          {axisTicks.map((tick) =>
            tick === 0 ? null : (
              <g key={`grid-line-${tick}`} className="quiz-modal-coordinates-grid-line">
                <line
                  x1={graphToViewBox({ x: tick, y: 0 }, axisRange).x}
                  y1={gridEdgeEnd.y}
                  x2={graphToViewBox({ x: tick, y: 0 }, axisRange).x}
                  y2={gridEdgeStart.y}
                />
                <line
                  x1={gridEdgeStart.x}
                  y1={graphToViewBox({ x: 0, y: tick }, axisRange).y}
                  x2={gridEdgeEnd.x}
                  y2={graphToViewBox({ x: 0, y: tick }, axisRange).y}
                />
              </g>
            )
          )}

          <line
            className="quiz-modal-coordinates-axis-line"
            x1={gridEdgeStart.x}
            y1={origin.y}
            x2={gridEdgeEnd.x}
            y2={origin.y}
          />
          <line
            className="quiz-modal-coordinates-axis-line"
            x1={origin.x}
            y1={gridEdgeEnd.y}
            x2={origin.x}
            y2={gridEdgeStart.y}
          />

          {axisTicks.map((tick) =>
            tick === 0 || Math.abs(tick % AXIS_LABEL_STEP) !== 0 ? null : (
              <g key={`axis-label-${tick}`} className="quiz-modal-coordinates-axis-label">
                <text x={graphToViewBox({ x: tick, y: 0 }, axisRange).x} y={origin.y + 12} textAnchor="middle">
                  {tick}
                </text>
                <text x={origin.x - 5} y={graphToViewBox({ x: 0, y: tick }, axisRange).y + 4} textAnchor="end">
                  {tick}
                </text>
              </g>
            )
          )}
          <text
            className="quiz-modal-coordinates-axis-label"
            x={origin.x - 5}
            y={origin.y + 12}
            textAnchor="end"
          >
            0
          </text>

          {displayPoints.map((point, index) => {
            const position = graphToViewBox(point, axisRange);

            return (
              <circle
                key={`coordinate-marker-${index}-${point.x}-${point.y}`}
                className={`quiz-modal-coordinates-marker${
                  index === activeIndex ? " quiz-modal-coordinates-marker--active" : ""
                }`}
                cx={position.x}
                cy={position.y}
                r={7}
              />
            );
          })}
        </svg>
      </div>
    </div>
  );
};
