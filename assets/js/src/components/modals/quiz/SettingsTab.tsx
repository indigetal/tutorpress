/**
 * Quiz Settings Tab Component
 *
 * @description Complete settings management interface for quiz configuration. Handles all
 *              quiz-level settings including time limits, feedback modes, grading criteria,
 *              and integration with WordPress addons like Course Preview. Extracted from
 *              QuizModal during Phase 1 refactoring to create focused, maintainable components.
 *
 *              Made generic in Step 2.2 to support both Quiz Modal and Interactive Quiz Modal
 *              by making most props optional and removing conditional logic.
 *
 * @features
 * - Time limit configuration with multiple units (seconds to weeks)
 * - Feedback mode selection (Default, Reveal, Retry)
 * - Passing grade percentage setting
 * - Maximum questions configuration
 * - Course Preview addon integration (content drip)
 * - Real-time validation with error display
 * - Success/error message handling
 * - Generic interface supporting both Quiz and Interactive Quiz modals
 *
 * @settings
 * - Time Limit: Configurable with units (Quiz Modal only)
 * - Hide Quiz Time: Toggle for time display (Quiz Modal only)
 * - Feedback Mode: How answers are revealed (Quiz Modal only)
 * - Passing Grade: Minimum percentage to pass (Both modals)
 * - Max Questions: Random question selection limit (Quiz Modal only)
 * - Available After Days: Content drip functionality (Quiz Modal only)
 * - Attempts Allowed: Number of retries allowed (Both modals)
 * - Quiz Auto Start: Auto-start behavior (Both modals)
 * - Question Order: Order of question presentation (Both modals)
 *
 * @usage
 * // Quiz Modal (all props)
 * <SettingsTab
 *   timeValue={timeValue}
 *   timeType={timeType}
 *   feedbackMode={feedbackMode}
 *   onTimeChange={updateTimeLimit}
 *   onSettingChange={updateSettings}
 *   // ... all other props
 * />
 *
 * // Interactive Quiz Modal (minimal props)
 * <SettingsTab
 *   attemptsAllowed={attemptsAllowed}
 *   passingGrade={passingGrade}
 *   quizAutoStart={quizAutoStart}
 *   questionsOrder={questionsOrder}
 *   onSettingChange={updateSettings}
 *   // ... minimal required props
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Components
 * @since 1.0.0
 */

import React from "react";
import {
  Notice,
  SelectControl,
  ToggleControl,
  __experimentalNumberControl as NumberControl,
  __experimentalHStack as HStack,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { TimeUnit, FeedbackMode, QuestionLayoutView, QuestionOrder } from "../../../types/quiz";

interface SettingsTabProps {
  // Core settings (required for both Quiz and Interactive Quiz)
  attemptsAllowed: number;
  passingGrade: number;
  quizAutoStart: boolean;
  questionsOrder: QuestionOrder;

  // Quiz Modal specific settings (optional for Interactive Quiz)
  timeValue?: number;
  timeType?: TimeUnit;
  hideQuizTimeDisplay?: boolean;
  feedbackMode?: FeedbackMode;
  maxQuestionsForAnswer?: number;
  afterXDaysOfEnroll?: number;
  questionLayoutView?: QuestionLayoutView;
  hideQuestionNumberOverview?: boolean;
  shortAnswerCharactersLimit?: number;
  openEndedAnswerCharactersLimit?: number;

  // Addon state (optional)
  coursePreviewAddonAvailable?: boolean;

  // UI state (required)
  isSaving: boolean;
  saveSuccess: boolean;
  saveError: string | null;

  // Handlers (required)
  onSettingChange: (settings: Record<string, any>) => void;
  onSaveErrorDismiss: () => void;

  // Optional handlers (Quiz Modal specific)
  onTimeChange?: (value: number, type: TimeUnit) => void;
  onContentDripChange?: (days: number) => void;

  // Validation errors (flexible)
  errors: {
    timeLimit?: string;
    passingGrade?: string;
    maxQuestions?: string;
    availableAfterDays?: string;
    attemptsAllowed?: string;
  };
}

export const SettingsTab: React.FC<SettingsTabProps> = ({
  // Core settings with defaults
  attemptsAllowed,
  passingGrade,
  quizAutoStart,
  questionsOrder,

  // Quiz Modal specific settings with defaults
  timeValue = 0,
  timeType = "minutes",
  hideQuizTimeDisplay = false,
  feedbackMode = "default",
  maxQuestionsForAnswer = 0,
  afterXDaysOfEnroll = 0,
  questionLayoutView = "single_question",
  hideQuestionNumberOverview = false,
  shortAnswerCharactersLimit = 200,
  openEndedAnswerCharactersLimit = 500,

  // Addon state
  coursePreviewAddonAvailable = false,

  // UI state
  isSaving,
  saveSuccess,
  saveError,

  // Handlers
  onSettingChange,
  onSaveErrorDismiss,
  onTimeChange,
  onContentDripChange,

  // Validation errors
  errors,
}) => {
  // Determine if this is Interactive Quiz mode (minimal props provided)
  const isInteractiveQuizMode = !onTimeChange || !onContentDripChange;

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
          {isInteractiveQuizMode
            ? __("Interactive Quiz saved successfully!", "tutorpress")
            : __("Quiz saved successfully! Updating curriculum...", "tutorpress")}
        </Notice>
      )}

      {saveError && (
        <Notice status="error" isDismissible={true} onRemove={onSaveErrorDismiss}>
          {saveError}
        </Notice>
      )}

      <div className="quiz-modal-single-column-layout">
        <div className="quiz-modal-settings-content">
          <h3>
            {isInteractiveQuizMode ? __("Interactive Quiz Settings", "tutorpress") : __("Quiz Settings", "tutorpress")}
          </h3>

          <div className="quiz-modal-basic-settings">
            <h4>{__("Basic Settings", "tutorpress")}</h4>

            {/* Time Limit - Quiz Modal only */}
            {!isInteractiveQuizMode && onTimeChange && (
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
            )}

            {/* Hide Quiz Time Display - Quiz Modal only */}
            {!isInteractiveQuizMode && (
              <div className="quiz-modal-setting-group">
                <ToggleControl
                  label={__("Hide Quiz Time Display", "tutorpress")}
                  checked={hideQuizTimeDisplay}
                  onChange={(checked) => onSettingChange({ hide_quiz_time_display: checked })}
                  disabled={isSaving}
                  help={__("Hide the quiz time display on the frontend", "tutorpress")}
                />
              </div>
            )}

            {/* Feedback Mode - Quiz Modal only */}
            {!isInteractiveQuizMode && (
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
            )}

            {/* Attempts Allowed - Always visible for Interactive Quiz, conditional for Quiz Modal */}
            {(isInteractiveQuizMode || feedbackMode === "retry") && (
              <div className="quiz-modal-setting-group">
                <NumberControl
                  label={__("Attempts Allowed", "tutorpress")}
                  value={attemptsAllowed}
                  onChange={(value) => onSettingChange({ attempts_allowed: parseInt(value as string) || 0 })}
                  min={0}
                  max={20}
                  step={1}
                  disabled={isSaving}
                />
                <p className="quiz-modal-setting-help">
                  {__(
                    'Define how many times a student can retake this quiz. Setting it to "0" allows unlimited attempts.',
                    "tutorpress"
                  )}
                </p>
                {errors.attemptsAllowed && (
                  <Notice status="error" isDismissible={false}>
                    {errors.attemptsAllowed}
                  </Notice>
                )}
              </div>
            )}

            {/* Passing Grade - Always visible */}
            <div className="quiz-modal-setting-group">
              <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                <NumberControl
                  label={__("Passing Grade", "tutorpress")}
                  value={passingGrade}
                  onChange={(value) => onSettingChange({ passing_grade: parseInt(value as string) || 0 })}
                  min={0}
                  max={100}
                  step={1}
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

            {/* Max Questions Allowed to Answer - Quiz Modal only */}
            {!isInteractiveQuizMode && (
              <div className="quiz-modal-setting-group">
                <NumberControl
                  label={__("Max Questions Allowed to Answer", "tutorpress")}
                  value={maxQuestionsForAnswer}
                  onChange={(value) => onSettingChange({ max_questions_for_answer: parseInt(value as string) || 0 })}
                  min={0}
                  step={1}
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
            )}

            {/* Available after days (Course Preview addon) - Quiz Modal only */}
            {!isInteractiveQuizMode && coursePreviewAddonAvailable && onContentDripChange && (
              <div className="quiz-modal-setting-group">
                <NumberControl
                  label={__("Available after days", "tutorpress")}
                  value={afterXDaysOfEnroll}
                  onChange={(value) => onContentDripChange(parseInt(value as string) || 0)}
                  min={0}
                  step={1}
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

            {/* Quiz Auto Start - Always visible */}
            <div className="quiz-modal-setting-group">
              <ToggleControl
                label={__("Quiz Auto Start", "tutorpress")}
                checked={quizAutoStart}
                onChange={(checked) => onSettingChange({ quiz_auto_start: checked })}
                disabled={isSaving}
                help={__("When enabled, the quiz begins immediately as soon as the page loads", "tutorpress")}
              />
            </div>

            {/* Question Layout and Question Order */}
            <div className="quiz-modal-setting-group">
              <div className="quiz-modal-two-column-layout">
                {/* Question Layout - Quiz Modal only */}
                {!isInteractiveQuizMode && (
                  <div className="quiz-modal-setting-column">
                    <SelectControl
                      label={__("Question Layout", "tutorpress")}
                      value={questionLayoutView}
                      options={[
                        { label: __("Select an option", "tutorpress"), value: "" },
                        { label: __("Single question", "tutorpress"), value: "single_question" },
                        { label: __("Question pagination", "tutorpress"), value: "question_pagination" },
                        { label: __("Question below each other", "tutorpress"), value: "question_below_each_other" },
                      ]}
                      onChange={(value) => onSettingChange({ question_layout_view: value as QuestionLayoutView })}
                      disabled={isSaving}
                    />
                  </div>
                )}

                {/* Question Order - Always visible, full width for Interactive Quiz */}
                <div className={isInteractiveQuizMode ? "quiz-modal-setting-full-width" : "quiz-modal-setting-column"}>
                  <SelectControl
                    label={__("Question Order", "tutorpress")}
                    value={questionsOrder}
                    options={[
                      { label: __("Random", "tutorpress"), value: "rand" },
                      { label: __("Sorting", "tutorpress"), value: "sorting" },
                      { label: __("Ascending", "tutorpress"), value: "asc" },
                      { label: __("Descending", "tutorpress"), value: "desc" },
                    ]}
                    onChange={(value) => onSettingChange({ questions_order: value as QuestionOrder })}
                    disabled={isSaving}
                  />
                </div>
              </div>
            </div>

            {/* Hide Question Number - Quiz Modal only */}
            {!isInteractiveQuizMode && (
              <div className="quiz-modal-setting-group">
                <ToggleControl
                  label={__("Hide Question Number", "tutorpress")}
                  checked={hideQuestionNumberOverview}
                  onChange={(checked) => onSettingChange({ hide_question_number_overview: checked })}
                  disabled={isSaving}
                />
              </div>
            )}

            {/* Character Limits - Quiz Modal only */}
            {!isInteractiveQuizMode && (
              <>
                <div className="quiz-modal-setting-group">
                  <NumberControl
                    label={__("Short Answer Character Limit", "tutorpress")}
                    value={shortAnswerCharactersLimit}
                    onChange={(value) =>
                      onSettingChange({ short_answer_characters_limit: parseInt(value as string) || 200 })
                    }
                    min={1}
                    max={10000}
                    step={1}
                    disabled={isSaving}
                  />
                </div>

                <div className="quiz-modal-setting-group">
                  <NumberControl
                    label={__("Essay Answer Character Limit", "tutorpress")}
                    value={openEndedAnswerCharactersLimit}
                    onChange={(value) =>
                      onSettingChange({ open_ended_answer_characters_limit: parseInt(value as string) || 500 })
                    }
                    min={1}
                    max={50000}
                    step={1}
                    disabled={isSaving}
                  />
                </div>
              </>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
