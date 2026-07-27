/**
 * Range (Scale) Question Component
 *
 * @description Editor for Tutor LMS 4.0's native `scale` question type. Range keeps its
 *              entire configuration in a single generic answer row: the correct value and
 *              the scale config live as JSON in `answer_two_gap_match`, with
 *              `answer_view_format` set to `scale`. Tutor Pro owns attempt rendering and
 *              grading; this component only authors the contract Pro consumes.
 *
 * @features
 * - Min value, Max value, and Label entry controls, matching Tutor's native editor
 * - Correct Value control validated against the configured range
 * - Tutor's creation defaults seeded when no configuration is stored yet
 * - Defensive parsing: a stored value TutorPress cannot read is reported, never replaced
 * - Config keys Tutor writes but does not expose are carried through untouched
 *
 * @usage
 * <ScaleQuestion
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

import React, { useEffect, useRef, useState } from "react";
import { __, sprintf } from "@wordpress/i18n";
import { ValidationDisplay } from "./ValidationDisplay";
import { useQuestionValidation } from "../../../../hooks/quiz";
import type { DataStatus, QuizQuestion, QuizQuestionOption } from "../../../../types/quiz";
import {
  createDefaultAnswerRow,
  NATIVE_SCALE_DEFAULTS,
  parseScaleAnswer,
  serializeScaleAnswer,
  type ScaleAnswerData,
} from "../../../../utils/quizQuestionTypes";

interface ScaleQuestionProps {
  question: QuizQuestion;
  questionIndex: number;
  onQuestionUpdate: (questionIndex: number, field: keyof QuizQuestion, value: any) => void;
  showValidationErrors: boolean;
  isSaving: boolean;
  onDeletedAnswerId?: (answerId: number) => void;
}

/** Tutor's stored value for a Range answer row. */
const SCALE_ANSWER_VIEW_FORMAT = "scale";

/** The fields Tutor's native editor exposes. */
type ScaleFieldKey = "min" | "max" | "labelEvery" | "value";

/**
 * Read the number a field currently holds.
 */
const getFieldValue = (data: ScaleAnswerData, field: ScaleFieldKey): number =>
  field === "value" ? data.value : data.config[field];

/**
 * Fallback applied when a field is cleared or typed into an unparseable state.
 *
 * Mirrors the native editor: config fields fall back to Tutor's default and the correct
 * value falls back to the configured minimum.
 */
const getFieldFallback = (data: ScaleAnswerData, field: ScaleFieldKey): number =>
  field === "value" ? data.config.min : NATIVE_SCALE_DEFAULTS.config[field];

/**
 * Apply a field change to the stored contract, leaving every other key untouched.
 */
const applyFieldValue = (data: ScaleAnswerData, field: ScaleFieldKey, value: number): ScaleAnswerData =>
  field === "value" ? { ...data, value } : { ...data, config: { ...data.config, [field]: value } };

export const ScaleQuestion: React.FC<ScaleQuestionProps> = ({
  question,
  questionIndex,
  onQuestionUpdate,
  showValidationErrors,
  isSaving,
}) => {
  const existingAnswers = question.question_answers || [];
  const answerRow = existingAnswers.length > 0 ? existingAnswers[0] : null;
  const storedValue = answerRow?.answer_two_gap_match ?? "";
  const parsed = parseScaleAnswer(storedValue);

  // Draft strings so a partially typed number does not fight the stored contract.
  const [drafts, setDrafts] = useState<Record<ScaleFieldKey, string> | null>(null);

  // One seeding attempt per question, so a rejected update cannot loop.
  const seededRef = useRef<Set<number>>(new Set());

  const { getQuestionErrors } = useQuestionValidation();
  const validationErrors = getQuestionErrors(question);

  /**
   * Write the contract to the question's single answer row, creating the row if Tutor
   * never stored one. The only writer, so every path stamps the same native fields.
   */
  const writeScaleData = (data: ScaleAnswerData) => {
    const serialized = serializeScaleAnswer(data);
    let updatedAnswers: QuizQuestionOption[];

    if (answerRow) {
      updatedAnswers = [...existingAnswers];
      updatedAnswers[0] = {
        ...answerRow,
        is_correct: "1",
        answer_view_format: SCALE_ANSWER_VIEW_FORMAT,
        answer_two_gap_match: serialized,
        _data_status: (answerRow._data_status === "new" ? "new" : "update") as DataStatus,
      };
    } else {
      updatedAnswers = [
        {
          ...createDefaultAnswerRow(question, 1),
          is_correct: "1",
          answer_view_format: SCALE_ANSWER_VIEW_FORMAT,
          answer_two_gap_match: serialized,
        },
      ];
    }

    onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
  };

  /**
   * Seed Tutor's creation defaults when nothing is stored yet.
   *
   * Only runs for an absent or empty value, so it is purely additive: a stored
   * configuration, readable or not, is never overwritten here.
   */
  useEffect(() => {
    if (parsed.status !== "empty" || seededRef.current.has(question.question_id)) {
      return;
    }
    seededRef.current.add(question.question_id);

    writeScaleData(NATIVE_SCALE_DEFAULTS);
  }, [parsed.status, question.question_id, questionIndex, onQuestionUpdate]);

  /**
   * Re-sync the drafts whenever the stored value changes underneath the editor.
   */
  useEffect(() => {
    const current = parseScaleAnswer(storedValue);
    if (current.status !== "valid") {
      setDrafts(null);
      return;
    }

    setDrafts({
      min: String(current.data.config.min),
      max: String(current.data.config.max),
      labelEvery: String(current.data.config.labelEvery),
      value: String(current.data.value),
    });
  }, [storedValue]);

  const handleFieldChange = (field: ScaleFieldKey, rawInput: string) => {
    if (parsed.status !== "valid") {
      return;
    }

    setDrafts((previous) => (previous ? { ...previous, [field]: rawInput } : previous));

    const typed = parseFloat(rawInput);
    const nextValue = Number.isFinite(typed) ? typed : getFieldFallback(parsed.data, field);

    writeScaleData(applyFieldValue(parsed.data, field, nextValue));
  };

  if (parsed.status === "malformed") {
    return (
      <div className="quiz-modal-scale-content">
        <div className="quiz-modal-scale-preserved-notice">
          <strong className="quiz-modal-scale-preserved-title">
            {__("This Range configuration cannot be read.", "tutorpress")}
          </strong>
          <p className="quiz-modal-scale-preserved-description">
            {__(
              "TutorPress could not interpret the stored range data, so it has been left exactly as saved. Editing it here would discard it. Open the question in the Tutor LMS course builder to repair it.",
              "tutorpress"
            )}
          </p>
        </div>
      </div>
    );
  }

  if (parsed.status !== "valid" || !drafts) {
    return <div className="quiz-modal-scale-content" />;
  }

  const { config } = parsed.data;
  const isRangeInvalid = config.max <= config.min;

  const renderField = (field: ScaleFieldKey, label: string, helpText?: string) => (
    <div className="quiz-modal-scale-field">
      <label className="quiz-modal-scale-label" htmlFor={`quiz-modal-scale-${field}-${question.question_id}`}>
        {label}
      </label>
      <input
        id={`quiz-modal-scale-${field}-${question.question_id}`}
        className="quiz-modal-scale-input"
        type="number"
        value={drafts[field]}
        onChange={(event) => handleFieldChange(field, event.target.value)}
        disabled={isSaving}
      />
      {helpText && <span className="quiz-modal-scale-help-text">{helpText}</span>}
    </div>
  );

  return (
    <div className="quiz-modal-scale-content">
      <ValidationDisplay errors={validationErrors} show={showValidationErrors} severity="error" />

      <div className="quiz-modal-scale-section">
        <span className="quiz-modal-scale-section-title">{__("Scale range", "tutorpress")}</span>
        <div className="quiz-modal-scale-grid">
          {renderField("min", __("Min value", "tutorpress"))}
          {renderField("max", __("Max value", "tutorpress"))}
          {renderField("labelEvery", __("Label entry", "tutorpress"))}
        </div>
      </div>

      <div className="quiz-modal-scale-section">
        {renderField(
          "value",
          __("Correct Value", "tutorpress"),
          isRangeInvalid
            ? undefined
            : sprintf(
                // translators: %1$s is the minimum scale value, %2$s is the maximum scale value.
                __("Between %1$s and %2$s", "tutorpress"),
                String(getFieldValue(parsed.data, "min")),
                String(getFieldValue(parsed.data, "max"))
              )
        )}
      </div>
    </div>
  );
};
