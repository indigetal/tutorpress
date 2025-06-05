import React from "react";
import {
  Notice,
  SelectControl,
  ToggleControl,
  __experimentalNumberControl as NumberControl,
  __experimentalHStack as HStack,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { TimeUnit, FeedbackMode } from "../../../types/quiz";

interface SettingsTabProps {
  // Form state
  timeValue: number;
  timeType: TimeUnit;
  hideQuizTimeDisplay: boolean;
  feedbackMode: FeedbackMode;
  passingGrade: number;
  maxQuestionsForAnswer: number;
  afterXDaysOfEnroll: number;

  // Addon state
  coursePreviewAddonAvailable: boolean;

  // UI state
  isSaving: boolean;
  saveSuccess: boolean;
  saveError: string | null;

  // Handlers
  onTimeChange: (value: number, type: TimeUnit) => void;
  onSettingChange: (settings: Record<string, any>) => void;
  onContentDripChange: (days: number) => void;
  onSaveErrorDismiss: () => void;

  // Validation errors
  errors: {
    timeLimit?: string;
    passingGrade?: string;
    maxQuestions?: string;
    availableAfterDays?: string;
  };
}

export const SettingsTab: React.FC<SettingsTabProps> = ({
  timeValue,
  timeType,
  hideQuizTimeDisplay,
  feedbackMode,
  passingGrade,
  maxQuestionsForAnswer,
  afterXDaysOfEnroll,
  coursePreviewAddonAvailable,
  isSaving,
  saveSuccess,
  saveError,
  onTimeChange,
  onSettingChange,
  onContentDripChange,
  onSaveErrorDismiss,
  errors,
}) => {
  const timeUnitOptions = [
    { label: __("Seconds", "tutorpress"), value: "seconds" },
    { label: __("Minutes", "tutorpress"), value: "minutes" },
    { label: __("Hours", "tutorpress"), value: "hours" },
    { label: __("Days", "tutorpress"), value: "days" },
    { label: __("Weeks", "tutorpress"), value: "weeks" },
  ];

  const feedbackModeOptions = [
    {
      label: __("Default", "tutorpress"),
      value: "default",
      help: __("Answers are shown after finishing the quiz.", "tutorpress"),
    },
    {
      label: __("Reveal", "tutorpress"),
      value: "reveal",
      help: __("Show answer after attempting the question.", "tutorpress"),
    },
    {
      label: __("Retry", "tutorpress"),
      value: "retry",
      help: __("Allows students to retake the quiz after their first attempt.", "tutorpress"),
    },
  ];

  const selectedFeedbackMode = feedbackModeOptions.find((option) => option.value === feedbackMode);

  return (
    <div className="quiz-modal-settings">
      {/* Success/Error Messages */}
      {saveSuccess && (
        <Notice status="success" isDismissible={false}>
          {__("Quiz saved successfully! Updating curriculum...", "tutorpress")}
        </Notice>
      )}

      {saveError && (
        <Notice status="error" isDismissible={true} onRemove={onSaveErrorDismiss}>
          {saveError}
        </Notice>
      )}

      <div className="quiz-modal-single-column-layout">
        <div className="quiz-modal-settings-content">
          <h3>{__("Quiz Settings", "tutorpress")}</h3>

          <div className="quiz-modal-basic-settings">
            <h4>{__("Basic Settings", "tutorpress")}</h4>

            {/* Time Limit */}
            <div className="quiz-modal-setting-group">
              <label className="quiz-modal-setting-label">{__("Time Limit", "tutorpress")}</label>
              <HStack spacing={2} alignment="flex-start">
                <NumberControl
                  value={timeValue}
                  onChange={(value) => onTimeChange(parseInt(value as string) || 0, timeType)}
                  min={0}
                  step={1}
                  style={{ width: "100px", flexShrink: 0 }}
                  disabled={isSaving}
                />
                <SelectControl
                  value={timeType}
                  options={timeUnitOptions}
                  onChange={(value) => onTimeChange(timeValue, value as TimeUnit)}
                  style={{ width: "100px", flexShrink: 0 }}
                  __nextHasNoMarginBottom
                  disabled={isSaving}
                />
              </HStack>
              <p className="quiz-modal-setting-help">
                {__('Set a time limit for this quiz. A time limit of "0" indicates no time limit', "tutorpress")}
              </p>
              {errors.timeLimit && (
                <Notice status="error" isDismissible={false}>
                  {errors.timeLimit}
                </Notice>
              )}
            </div>

            {/* Hide Quiz Time */}
            <div className="quiz-modal-setting-group">
              <ToggleControl
                label={__("Hide Quiz Time", "tutorpress")}
                checked={hideQuizTimeDisplay}
                onChange={(checked) => onSettingChange({ hide_quiz_time_display: checked })}
                disabled={isSaving}
              />
            </div>

            {/* Feedback Mode */}
            <div className="quiz-modal-setting-group">
              <SelectControl
                label={__("Feedback Mode", "tutorpress")}
                value={feedbackMode}
                options={feedbackModeOptions.map((option) => ({
                  label: option.label,
                  value: option.value,
                }))}
                onChange={(value) => onSettingChange({ feedback_mode: value as FeedbackMode })}
                disabled={isSaving}
              />
              {selectedFeedbackMode && <p className="quiz-modal-setting-help">{selectedFeedbackMode.help}</p>}
            </div>

            {/* Passing Grade */}
            <div className="quiz-modal-setting-group">
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <NumberControl
                  label={__("Passing Grade", "tutorpress")}
                  value={passingGrade}
                  onChange={(value) => onSettingChange({ passing_grade: parseInt(value as string) || 0 })}
                  min={0}
                  max={100}
                  step={1}
                  style={{ width: "120px" }}
                  disabled={isSaving}
                />
                <span style={{ fontSize: "16px", fontWeight: "bold" }}>%</span>
              </div>
              <p className="quiz-modal-setting-help">
                {__("Set the minimum score percentage required to pass this quiz", "tutorpress")}
              </p>
              {errors.passingGrade && (
                <Notice status="error" isDismissible={false}>
                  {errors.passingGrade}
                </Notice>
              )}
            </div>

            {/* Max Questions Allowed to Answer */}
            <div className="quiz-modal-setting-group">
              <NumberControl
                label={__("Max Question Allowed to Answer", "tutorpress")}
                value={maxQuestionsForAnswer}
                onChange={(value) => onSettingChange({ max_questions_for_answer: parseInt(value as string) || 0 })}
                min={0}
                step={1}
                style={{ width: "120px" }}
                disabled={isSaving}
              />
              <p className="quiz-modal-setting-help">
                {__(
                  "Set the number of quiz questions randomly from your question pool. If the set number exceeds available questions, all questions will be included",
                  "tutorpress"
                )}
              </p>
              {errors.maxQuestions && (
                <Notice status="error" isDismissible={false}>
                  {errors.maxQuestions}
                </Notice>
              )}
            </div>

            {/* Available after days (Course Preview addon) */}
            {coursePreviewAddonAvailable && (
              <div className="quiz-modal-setting-group">
                <NumberControl
                  label={__("Available after days", "tutorpress")}
                  value={afterXDaysOfEnroll}
                  onChange={(value) => onContentDripChange(parseInt(value as string) || 0)}
                  min={0}
                  step={1}
                  style={{ width: "120px" }}
                  disabled={isSaving}
                />
                <p className="quiz-modal-setting-help">
                  {__("This quiz will be available after the given number of days.", "tutorpress")}
                </p>
                {errors.availableAfterDays && (
                  <Notice status="error" isDismissible={false}>
                    {errors.availableAfterDays}
                  </Notice>
                )}
              </div>
            )}
          </div>

          <div className="quiz-modal-advanced-settings">
            <h4>{__("Advanced Settings", "tutorpress")}</h4>
            <p>{__("Advanced quiz settings will be implemented in the next step", "tutorpress")}</p>
          </div>
        </div>
      </div>
    </div>
  );
};
