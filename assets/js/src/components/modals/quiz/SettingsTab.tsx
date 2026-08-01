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
 * Interactive Quiz Default (3 settings):
 * - Passing Grade: Minimum percentage to pass
 * - Quiz Auto Start: Auto-start behavior
 * - Question Order: Order of question presentation
 *
 * Interactive Quiz All Settings (matches Quiz Modal):
 * - Time Limit: Configurable with units
 * - Hide Quiz Time: Toggle for time display
 * - Feedback Mode: How answers are revealed
 *   - Attempts Allowed: Shows when feedback mode is "retry" (DRY principle)
 * - Max Questions: Random question selection limit
 * - Available After Days: Content drip functionality
 * - Hide Question Number: Question numbering display
 * - Character Limits: Short and essay answer limits
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
  CheckboxControl,
  RadioControl,
  __experimentalNumberControl as NumberControl,
  __experimentalHStack as HStack,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type {
  TimeUnit,
  FeedbackMode,
  QuestionLayoutView,
  QuestionOrder,
  QuizContentType,
  QuizPaginationType,
  QuizQuestion,
  QuizSettingsContract,
  QuizSettingsUnavailableReason,
  TutorLearningMode,
} from "../../../types/quiz";
import {
  shouldBlockQuizSettingsEditing,
  shouldShowAnswerReveal,
  shouldShowAnswerRevealDuration,
  shouldShowAutoStartDelay,
  shouldShowCharacterLimitsFrame,
  shouldShowContentDripSettingsFrame,
  shouldShowHideCountdown,
  shouldShowHidePreviousButton,
  shouldShowHideQuestionNumber,
  shouldShowNavigationControls,
  shouldShowOpenEndedCharacterLimit,
  shouldShowPaginationControls,
  shouldShowPassIsRequired,
  shouldShowQuizScopeMaximumQuestions,
  shouldShowShortAnswerCharacterLimit,
  shouldShowTimingTimeLimit,
} from "../../../utils/quizSettingsContract";

interface SettingsTabProps {
  // Explicit settings context (Step 5A)
  quizSettingsContract: QuizSettingsContract;
  quizSettingsUnavailableReason?: QuizSettingsUnavailableReason;
  learningMode: TutorLearningMode;
  contentType: QuizContentType;
  questions: QuizQuestion[];
  h5pRuntimeAvailable: boolean;

  // Core settings (required for both Quiz and Interactive Quiz)
  attemptsAllowed: number;
  passingGrade: number;
  quizAutoStart: boolean;
  questionsOrder: QuestionOrder;

  // Quiz scope toggles (Step 6); defaults keep Pass-required hidden until drip props arrive
  limitAttemptsAllowed?: boolean;
  limitQuestionsToAnswer?: boolean;
  passIsRequired?: boolean;
  contentDripAvailable?: boolean;
  contentDripType?: string;

  // Quiz Modal specific settings (optional for Interactive Quiz)
  enableTimeLimit?: boolean;
  timeValue?: number;
  timeType?: TimeUnit;
  hideQuizTimeDisplay?: boolean;
  /** V4 auto-start delay companion; UI wiring remains Step 7D. */
  autoStartDelay?: number;
  feedbackMode?: FeedbackMode;
  maxQuestionsForAnswer?: number;
  afterXDaysOfEnroll?: number;
  questionLayoutView?: QuestionLayoutView;
  enablePagination?: boolean;
  paginationType?: QuizPaginationType;
  enableAnswerReveal?: boolean;
  answersRevealDuration?: number;
  hidePreviousButton?: boolean;
  hideQuestionNumberOverview?: boolean;
  shortAnswerCharactersLimit?: number | "";
  openEndedAnswerCharactersLimit?: number | "";

  // Addon state (optional)
  coursePreviewAddonAvailable?: boolean;

  // All Settings toggle (for Interactive Quiz)
  showAllSettings?: boolean;
  onShowAllSettingsChange?: (show: boolean) => void;

  // UI state (required)
  isSaving: boolean;
  saveSuccess: boolean;
  saveError: string | null;

  // Handlers (required) — semantic update path for settings groups
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

const getQuizSettingsUnavailableMessage = (reason: QuizSettingsUnavailableReason): string => {
  switch (reason) {
    case "tutor_inactive":
      return __("Tutor LMS is not active. Quiz settings cannot be edited.", "tutorpress");
    case "tutor_version_missing":
      return __("Tutor LMS version could not be determined. Quiz settings cannot be edited.", "tutorpress");
    case "unsupported_tutor_version":
      return __("This Tutor LMS version does not support Quiz Settings editing.", "tutorpress");
    case "legacy_contract_unavailable":
    default:
      return __("Quiz settings are unavailable for the current Tutor LMS configuration.", "tutorpress");
  }
};

export const SettingsTab: React.FC<SettingsTabProps> = ({
  quizSettingsContract,
  quizSettingsUnavailableReason = "",
  learningMode,
  contentType,
  questions,
  h5pRuntimeAvailable,

  // Core settings with defaults
  attemptsAllowed,
  passingGrade,
  quizAutoStart,
  questionsOrder,

  // Quiz scope toggles (Step 6) — drip props stay closed until Steps 11/13
  limitAttemptsAllowed = false,
  limitQuestionsToAnswer = false,
  passIsRequired = false,
  contentDripAvailable = false,
  contentDripType = "",

  // Quiz Modal specific settings with defaults
  enableTimeLimit,
  timeValue = 0,
  timeType = "minutes",
  hideQuizTimeDisplay = false,
  autoStartDelay = 5,
  feedbackMode = "default",
  maxQuestionsForAnswer = 0,
  afterXDaysOfEnroll = 0,
  questionLayoutView = "single_question",
  enablePagination = false,
  paginationType = "shape",
  enableAnswerReveal = false,
  answersRevealDuration = 5,
  hidePreviousButton = false,
  hideQuestionNumberOverview = false,
  shortAnswerCharactersLimit = 200,
  openEndedAnswerCharactersLimit = 500,

  // Addon state
  coursePreviewAddonAvailable = false,

  // All Settings toggle (for Interactive Quiz)
  showAllSettings = false,
  onShowAllSettingsChange,

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
  // Explicit modal content type selects Interactive presentation — never raw quiz_type.
  const isInteractiveQuizMode = contentType === "tutor_h5p_quiz";
  const isV4Contract = quizSettingsContract === "v4";
  const isLegacyContract = quizSettingsContract === "legacy";
  // Notice branching stays here; shared predicates own availability/blocking only.
  const interactiveRuntimeUnavailable = isInteractiveQuizMode && !h5pRuntimeAvailable;
  const interactiveContractUnsupported =
    isInteractiveQuizMode && quizSettingsContract !== "v4";
  const settingsEditingBlocked = shouldBlockQuizSettingsEditing({
    contentType,
    contract: quizSettingsContract,
    h5pRuntimeAvailable,
  });
  const showContentDripSettingsFrame = shouldShowContentDripSettingsFrame({
    contentType,
    showAllSettings,
    contentDripUiAvailable: !!coursePreviewAddonAvailable && !!onContentDripChange,
  });
  const showMaximumQuestions = shouldShowQuizScopeMaximumQuestions({
    contentType,
    showAllSettings,
  });
  const showPassRequired = shouldShowPassIsRequired({
    contract: quizSettingsContract,
    contentDripAvailable,
    contentDripType,
    limitAttemptsAllowed,
    attemptsAllowed,
    contentType,
    showAllSettings,
  });
  // Prefer the form toggle when parents pass it; fall back for older callers.
  const effectiveEnableTimeLimit = enableTimeLimit ?? timeValue > 0;
  const showTimingTimeLimit = shouldShowTimingTimeLimit({
    contentType,
    showAllSettings,
  });
  const showHideCountdown = shouldShowHideCountdown({
    enableTimeLimit: effectiveEnableTimeLimit,
    contentType,
    showAllSettings,
  });
  const showAutoStartDelay = shouldShowAutoStartDelay({
    contract: quizSettingsContract,
    quizAutoStart,
  });
  const showNavigationControls = shouldShowNavigationControls({
    contentType,
    showAllSettings,
  });
  const layoutValue: QuestionLayoutView =
    questionLayoutView === "question_below_each_other"
      ? "question_below_each_other"
      : "single_question";
  const showPaginationControls = shouldShowPaginationControls({
    questionLayoutView: layoutValue,
    contentType,
    showAllSettings,
  });
  const isLegacyLearningMode = learningMode === "legacy";
  const showAnswerReveal = shouldShowAnswerReveal({
    contract: quizSettingsContract,
    questionLayoutView: layoutValue,
    contentType,
  });
  const showAnswerRevealDuration = shouldShowAnswerRevealDuration({
    contract: quizSettingsContract,
    questionLayoutView: layoutValue,
    contentType,
    enableAnswerReveal,
  });
  const showHidePrevious = shouldShowHidePreviousButton({
    contract: quizSettingsContract,
    questionLayoutView: layoutValue,
    enablePagination,
    contentType,
    showAllSettings,
  });
  const showHideQuestionNumber = shouldShowHideQuestionNumber({
    questionLayoutView: layoutValue,
    contentType,
    showAllSettings,
  });
  const showCharacterLimitsFrame = shouldShowCharacterLimitsFrame({
    contentType,
    questions,
  });
  const showShortAnswerCharacterLimit = shouldShowShortAnswerCharacterLimit({
    contentType,
    questions,
  });
  const showOpenEndedCharacterLimit = shouldShowOpenEndedCharacterLimit({
    contentType,
    questions,
  });
  const autoStartDelayPresets = [2, 5, 7, 10];
  const revealDurationPresets = [2, 5, 7, 10];
  const autoStartDelayOptions = [
    ...(autoStartDelayPresets.includes(autoStartDelay)
      ? []
      : [{ label: String(autoStartDelay), value: String(autoStartDelay) }]),
    ...autoStartDelayPresets.map((preset) => ({
      label: String(preset),
      value: String(preset),
    })),
  ];
  const revealDurationOptions = [
    ...(revealDurationPresets.includes(answersRevealDuration)
      ? []
      : [{ label: String(answersRevealDuration), value: String(answersRevealDuration) }]),
    ...revealDurationPresets.map((preset) => ({
      label: String(preset),
      value: String(preset),
    })),
  ];

  const timeUnitOptions = [
    { label: __("Sec", "tutorpress"), value: "seconds" },
    { label: __("Min", "tutorpress"), value: "minutes" },
    { label: __("Hour", "tutorpress"), value: "hours" },
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

  const saveNotices = (
    <>
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
    </>
  );

  if (settingsEditingBlocked) {
    let blockedMessage = getQuizSettingsUnavailableMessage(quizSettingsUnavailableReason);
    if (interactiveRuntimeUnavailable) {
      blockedMessage = __(
        "Interactive Quiz settings require the Tutor Pro H5P addon and an active WordPress H5P plugin.",
        "tutorpress"
      );
    } else if (interactiveContractUnsupported) {
      blockedMessage =
        quizSettingsContract === "unavailable"
          ? getQuizSettingsUnavailableMessage(quizSettingsUnavailableReason)
          : __(
              "Interactive Quiz settings require Tutor LMS 4.0 or newer.",
              "tutorpress"
            );
    }

    return (
      <div className="quiz-modal-settings">
        {saveNotices}
        <Notice status="warning" isDismissible={false}>
          {blockedMessage}
        </Notice>
      </div>
    );
  }

  return (
    <div className="quiz-modal-settings">
      {saveNotices}

      <div className="quiz-modal-single-column-layout">
        <div className="quiz-modal-settings-content">
          <div
            className="quiz-modal-settings-header"
            style={{
              display: "flex",
              justifyContent: "space-between",
              alignItems: "center",
              marginBottom: "20px",
            }}
          >
            <h3 style={{ margin: 0 }}>
              {isInteractiveQuizMode
                ? __("Interactive Quiz Settings", "tutorpress")
                : __("Quiz Settings", "tutorpress")}
            </h3>
            {isInteractiveQuizMode && onShowAllSettingsChange && (
              <div
                className="quiz-modal-settings-toggle"
                style={{
                  flexShrink: 0,
                  marginLeft: "20px",
                }}
              >
                <ToggleControl
                  label={__("Reveal All Quiz Settings", "tutorpress")}
                  checked={showAllSettings}
                  onChange={onShowAllSettingsChange}
                  disabled={isSaving}
                />
              </div>
            )}
          </div>

          <div className="quiz-modal-settings-frame">
            <h4>{__("Quiz scope", "tutorpress")}</h4>

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

            {/* Question Order - Always visible */}
            <div className="quiz-modal-setting-group">
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

            {/* V4: Allow multiple attempts (always visible for standard and valid Interactive) */}
            {isV4Contract && (
              <div className="quiz-modal-setting-group">
                <CheckboxControl
                  label={__("Allow multiple attempts", "tutorpress")}
                  checked={limitAttemptsAllowed}
                  onChange={(checked) => onSettingChange({ limit_attempts_allowed: checked })}
                  disabled={isSaving}
                  help={__(
                    "Set the number of attempts allowed for this quiz. 0 means unlimited.",
                    "tutorpress"
                  )}
                />
                {limitAttemptsAllowed && (
                  <>
                    <NumberControl
                      label={__("Attempts Allowed", "tutorpress")}
                      hideLabelFromVision
                      value={attemptsAllowed}
                      onChange={(value) =>
                        onSettingChange({ attempts_allowed: parseInt(value as string) || 0 })
                      }
                      min={0}
                      step={1}
                      disabled={isSaving}
                    />
                    {errors.attemptsAllowed && (
                      <Notice status="error" isDismissible={false}>
                        {errors.attemptsAllowed}
                      </Notice>
                    )}
                  </>
                )}
              </div>
            )}

            {/* Legacy: Feedback Mode + Attempts Allowed only for Retry */}
            {isLegacyContract && (
              <>
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
                  {selectedFeedbackMode && (
                    <p className="quiz-modal-setting-help">{selectedFeedbackMode.help}</p>
                  )}
                </div>
                {feedbackMode === "retry" && (
                  <div className="quiz-modal-setting-group">
                    <NumberControl
                      label={__("Attempts Allowed", "tutorpress")}
                      value={attemptsAllowed}
                      onChange={(value) =>
                        onSettingChange({ attempts_allowed: parseInt(value as string) || 0 })
                      }
                      min={0}
                      max={20}
                      step={1}
                      disabled={isSaving}
                    />
                    {errors.attemptsAllowed && (
                      <Notice status="error" isDismissible={false}>
                        {errors.attemptsAllowed}
                      </Notice>
                    )}
                  </div>
                )}
              </>
            )}

            {/* Maximum questions: always for standard; Interactive when disclosed */}
            {showMaximumQuestions && (
              <div className="quiz-modal-setting-group">
                <CheckboxControl
                  label={__("Set maximum questions per quiz", "tutorpress")}
                  checked={limitQuestionsToAnswer}
                  onChange={(checked) => onSettingChange({ limit_questions_to_answer: checked })}
                  disabled={isSaving}
                  help={__(
                    "Set the number of quiz questions randomly from your question pool. If the set number exceeds available questions, all questions will be included",
                    "tutorpress"
                  )}
                />
                {limitQuestionsToAnswer && (
                  <>
                    <NumberControl
                      label={__("Maximum questions", "tutorpress")}
                      hideLabelFromVision
                      value={maxQuestionsForAnswer}
                      onChange={(value) =>
                        onSettingChange({
                          max_questions_for_answer: parseInt(value as string) || 0,
                        })
                      }
                      min={1}
                      step={1}
                      disabled={isSaving}
                    />
                    {errors.maxQuestions && (
                      <Notice status="error" isDismissible={false}>
                        {errors.maxQuestions}
                      </Notice>
                    )}
                  </>
                )}
              </div>
            )}

            {/* Pass is required — pure gate; live drip wiring remains Steps 11/13 */}
            {showPassRequired && (
              <div className="quiz-modal-setting-group">
                <ToggleControl
                  label={__("Pass is required", "tutorpress")}
                  checked={passIsRequired}
                  onChange={(checked) => onSettingChange({ pass_is_required: checked })}
                  disabled={isSaving}
                />
              </div>
            )}
          </div>

          <div className="quiz-modal-settings-frame">
            <h4>{__("Timing", "tutorpress")}</h4>

            {/* Set time limit — standard always; Interactive when disclosed */}
            {showTimingTimeLimit && (
              <div className="quiz-modal-setting-group">
                <CheckboxControl
                  label={__("Set time limit", "tutorpress")}
                  checked={effectiveEnableTimeLimit}
                  onChange={(checked) => onSettingChange({ enable_time_limit: checked })}
                  disabled={isSaving}
                />
                {effectiveEnableTimeLimit && (
                  <>
                    <HStack spacing={2} alignment="flex-start">
                      <NumberControl
                        label={__("Time limit value", "tutorpress")}
                        hideLabelFromVision
                        value={timeValue}
                        onChange={(value) =>
                          onSettingChange({
                            time_limit: {
                              time_value: parseInt(value as string) || 0,
                              time_type: timeType,
                            },
                          })
                        }
                        min={1}
                        step={1}
                        style={{ width: "100px", flexShrink: 0 }}
                        disabled={isSaving}
                      />
                      <SelectControl
                        label={__("Time limit unit", "tutorpress")}
                        hideLabelFromVision
                        value={timeType}
                        options={timeUnitOptions}
                        onChange={(value) =>
                          onSettingChange({
                            time_limit: {
                              time_value: timeValue,
                              time_type: value as TimeUnit,
                            },
                          })
                        }
                        style={{ width: "100px", flexShrink: 0 }}
                        __nextHasNoMarginBottom
                        disabled={isSaving}
                      />
                    </HStack>
                    {errors.timeLimit && (
                      <Notice status="error" isDismissible={false}>
                        {errors.timeLimit}
                      </Notice>
                    )}
                  </>
                )}
              </div>
            )}

            {/* Hide countdown — only while time limit is enabled and visible */}
            {showHideCountdown && (
              <div className="quiz-modal-setting-group">
                <ToggleControl
                  label={__("Hide countdown timer", "tutorpress")}
                  checked={hideQuizTimeDisplay}
                  onChange={(checked) => onSettingChange({ hide_quiz_time_display: checked })}
                  disabled={isSaving}
                />
              </div>
            )}

            {/* Auto start quiz — always visible; V4 delay when enabled */}
            <div className="quiz-modal-setting-group">
              <CheckboxControl
                label={__("Auto start quiz", "tutorpress")}
                checked={quizAutoStart}
                onChange={(checked) => onSettingChange({ quiz_auto_start: checked })}
                disabled={isSaving}
              />
              {showAutoStartDelay && (
                <HStack spacing={2} alignment="center">
                  <span>{__("After", "tutorpress")}</span>
                  <SelectControl
                    label={__("Auto start delay", "tutorpress")}
                    hideLabelFromVision
                    value={String(autoStartDelay)}
                    options={autoStartDelayOptions}
                    onChange={(value) => {
                      const parsed = parseInt(value, 10);
                      onSettingChange({
                        auto_start_delay: Number.isFinite(parsed) ? parsed : 5,
                      });
                    }}
                    style={{ width: "80px", flexShrink: 0 }}
                    __nextHasNoMarginBottom
                    disabled={isSaving}
                  />
                  <span>{__("secs", "tutorpress")}</span>
                </HStack>
              )}
            </div>
          </div>

          {showNavigationControls && (
            <div className="quiz-modal-settings-frame">
              <h4>{__("Navigation & Display", "tutorpress")}</h4>

              <div className="quiz-modal-setting-group">
                <RadioControl
                  className="quiz-modal-layout-control"
                  label={__("Layout", "tutorpress")}
                  help={__("Choose how students will answer the questions.", "tutorpress")}
                  selected={layoutValue}
                  options={[
                    {
                      label: __("Single Question", "tutorpress"),
                      value: "single_question",
                    },
                    {
                      label: __("Full Page", "tutorpress"),
                      value: "question_below_each_other",
                    },
                  ]}
                  onChange={(value) =>
                    onSettingChange({
                      question_layout_view: value as QuestionLayoutView,
                    })
                  }
                  disabled={isSaving}
                />
              </div>

              {showPaginationControls && (
                <div className="quiz-modal-setting-group">
                  <CheckboxControl
                    label={__("Show pagination", "tutorpress")}
                    checked={enablePagination}
                    onChange={(checked) => onSettingChange({ enable_pagination: checked })}
                    disabled={isSaving}
                    help={
                      isLegacyLearningMode
                        ? __(
                            "Pagination style is unavailable while learning mode is set to Legacy.",
                            "tutorpress"
                          )
                        : undefined
                    }
                  />
                  {enablePagination && (
                    <SelectControl
                      label={__("Pagination style", "tutorpress")}
                      value={paginationType}
                      options={[
                        { label: __("Shapes", "tutorpress"), value: "shape" },
                        { label: __("Numbers", "tutorpress"), value: "number" },
                        { label: __("Radio", "tutorpress"), value: "radio" },
                      ]}
                      onChange={(value) =>
                        onSettingChange({
                          pagination_type: value as QuizPaginationType,
                        })
                      }
                      disabled={isSaving || isLegacyLearningMode}
                      __nextHasNoMarginBottom
                    />
                  )}
                </div>
              )}

              {showAnswerReveal && (
                <div className="quiz-modal-setting-group">
                  <CheckboxControl
                    label={__("Reveal answers after submission", "tutorpress")}
                    checked={enableAnswerReveal}
                    onChange={(checked) => onSettingChange({ enable_answer_reveal: checked })}
                    disabled={isSaving}
                  />
                  {showAnswerRevealDuration && (
                    <HStack spacing={2} alignment="center">
                      <span>{__("For", "tutorpress")}</span>
                      <SelectControl
                        label={__("Reveal duration", "tutorpress")}
                        hideLabelFromVision
                        value={String(answersRevealDuration)}
                        options={revealDurationOptions}
                        onChange={(value) => {
                          const parsed = parseInt(value, 10);
                          onSettingChange({
                            answers_reveal_duration: Number.isFinite(parsed) ? parsed : 5,
                          });
                        }}
                        style={{ width: "80px", flexShrink: 0 }}
                        __nextHasNoMarginBottom
                        disabled={isSaving}
                      />
                      <span>{__("secs", "tutorpress")}</span>
                    </HStack>
                  )}
                </div>
              )}

              {showHidePrevious && (
                <div className="quiz-modal-setting-group">
                  <ToggleControl
                    label={__('Hide "Previous" button', "tutorpress")}
                    checked={hidePreviousButton}
                    onChange={(checked) => onSettingChange({ hide_previous_button: checked })}
                    disabled={isSaving}
                  />
                </div>
              )}

              {showHideQuestionNumber && (
                <div className="quiz-modal-setting-group">
                  <ToggleControl
                    label={__("Hide question number", "tutorpress")}
                    checked={hideQuestionNumberOverview}
                    onChange={(checked) =>
                      onSettingChange({ hide_question_number_overview: checked })
                    }
                    disabled={isSaving}
                  />
                </div>
              )}
            </div>
          )}

          {showCharacterLimitsFrame && (
            <div className="quiz-modal-settings-frame">
              <h4>{__("Character Limits", "tutorpress")}</h4>

              {showOpenEndedCharacterLimit && (
                <div className="quiz-modal-setting-group">
                  <NumberControl
                    label={__("Open-Ended/Essay Answer", "tutorpress")}
                    value={openEndedAnswerCharactersLimit}
                    onChange={(value) => {
                      if (value === undefined || value === "") {
                        onSettingChange({ open_ended_answer_characters_limit: "" });
                        return;
                      }
                      const parsed = parseInt(String(value), 10);
                      onSettingChange({
                        open_ended_answer_characters_limit: Number.isFinite(parsed)
                          ? parsed
                          : "",
                      });
                    }}
                    min={0}
                    max={50000}
                    step={1}
                    disabled={isSaving}
                  />
                  <p className="quiz-modal-setting-help">
                    {__(
                      "Set the number of characters allowed for open-ended/essay answers. Leave empty to disable.",
                      "tutorpress"
                    )}
                  </p>
                </div>
              )}

              {showShortAnswerCharacterLimit && (
                <div className="quiz-modal-setting-group">
                  <NumberControl
                    label={__("Short Answer", "tutorpress")}
                    value={shortAnswerCharactersLimit}
                    onChange={(value) => {
                      if (value === undefined || value === "") {
                        onSettingChange({ short_answer_characters_limit: "" });
                        return;
                      }
                      const parsed = parseInt(String(value), 10);
                      onSettingChange({
                        short_answer_characters_limit: Number.isFinite(parsed)
                          ? parsed
                          : "",
                      });
                    }}
                    min={0}
                    max={10000}
                    step={1}
                    disabled={isSaving}
                  />
                  <p className="quiz-modal-setting-help">
                    {__(
                      "Set the number of characters allowed for short answers. Leave empty to disable.",
                      "tutorpress"
                    )}
                  </p>
                </div>
              )}
            </div>
          )}

          {showContentDripSettingsFrame && (
            <div className="quiz-modal-settings-frame">
              <h4>{__("Available after days", "tutorpress")}</h4>

              <div className="quiz-modal-setting-group">
                <NumberControl
                  label={__("Available after days", "tutorpress")}
                  value={afterXDaysOfEnroll}
                  onChange={(value) => onContentDripChange?.(parseInt(value as string) || 0)}
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
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
