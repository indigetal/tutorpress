import React, { useState, useEffect } from "react";
import {
  Modal,
  TabPanel,
  Button,
  TextControl,
  TextareaControl,
  SelectControl,
  ToggleControl,
  __experimentalNumberControl as NumberControl,
  __experimentalHStack as HStack,
  Notice,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { useQuizForm } from "../../hooks/useQuizForm";
import type { TimeUnit, FeedbackMode } from "../../types/quiz";

interface QuizModalProps {
  isOpen: boolean;
  onClose: () => void;
  topicId?: number;
  courseId?: number;
  quizId?: number; // For editing existing quiz
}

export const QuizModal: React.FC<QuizModalProps> = ({ isOpen, onClose, topicId, courseId, quizId }) => {
  const [activeTab, setActiveTab] = useState("question-details");
  const [isSaving, setIsSaving] = useState(false);

  // Initialize quiz form hook
  const {
    formState,
    coursePreviewAddon,
    updateTitle,
    updateDescription,
    updateSettings,
    updateTimeLimit,
    updateContentDrip,
    resetForm,
    validateEntireForm,
    checkCoursePreviewAddon,
    getFormData,
    isValid,
    isDirty,
    errors,
  } = useQuizForm();

  // Get quiz duplication state from curriculum store
  const quizDuplicationState = useSelect((select) => {
    return (select("tutorpress/curriculum") as any).getQuizDuplicationState();
  }, []);

  const { setQuizDuplicationState } = useDispatch("tutorpress/curriculum") as any;

  // Check Course Preview addon availability on mount
  useEffect(() => {
    if (isOpen) {
      checkCoursePreviewAddon();
    }
  }, [isOpen, checkCoursePreviewAddon]);

  const handleClose = () => {
    // Reset any quiz state if needed
    setQuizDuplicationState({ status: "idle" });
    resetForm();
    onClose();
  };

  const handleSave = async () => {
    if (!validateEntireForm()) {
      return;
    }

    setIsSaving(true);
    try {
      const formData = getFormData();

      // TODO: Implement actual save using QuizService
      console.log("Saving quiz:", formData);

      // Simulate API call
      await new Promise((resolve) => setTimeout(resolve, 1000));

      // Close modal on success
      handleClose();
    } catch (error) {
      console.error("Error saving quiz:", error);
    } finally {
      setIsSaving(false);
    }
  };

  const tabs = [
    {
      name: "question-details",
      title: __("Question Details", "tutorpress"),
      className: "quiz-modal-tab-question-details",
    },
    {
      name: "settings",
      title: __("Settings", "tutorpress"),
      className: "quiz-modal-tab-settings",
    },
  ];

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

  const renderQuestionDetailsTab = () => {
    return (
      <div className="quiz-modal-question-details">
        <div className="quiz-modal-three-column-layout">
          {/* Left Column: Quiz name, Question dropdown, Questions list */}
          <div className="quiz-modal-left-column">
            <div className="quiz-modal-quiz-info">
              <TextControl
                label={__("Quiz Title", "tutorpress")}
                value={formState.title}
                onChange={updateTitle}
                placeholder={__("Enter quiz title...", "tutorpress")}
                help={errors.title}
                className={errors.title ? "has-error" : ""}
              />

              <TextareaControl
                label={__("Quiz Description", "tutorpress")}
                value={formState.description}
                onChange={updateDescription}
                placeholder={__("Enter quiz description...", "tutorpress")}
                rows={3}
              />

              {topicId && <p className="quiz-modal-topic-context">{__("Topic ID: ", "tutorpress") + topicId}</p>}
            </div>

            <div className="quiz-modal-questions-section">
              <div className="quiz-modal-questions-header">
                <h4>{__("Questions", "tutorpress")}</h4>
                <Button
                  variant="primary"
                  className="quiz-modal-add-question-btn"
                  onClick={() => {
                    console.log("Add question clicked");
                  }}
                  disabled={!formState.title.trim()}
                >
                  +
                </Button>
              </div>

              <div className="quiz-modal-questions-list">
                {!formState.title.trim() ? (
                  <p className="quiz-modal-no-questions">{__("Enter a quiz title to add questions.", "tutorpress")}</p>
                ) : (
                  <p className="quiz-modal-no-questions">{__("No questions added yet.", "tutorpress")}</p>
                )}
              </div>
            </div>
          </div>

          {/* Center Column: Contextual question form */}
          <div className="quiz-modal-center-column">
            <div className="quiz-modal-question-form">
              <div className="quiz-modal-empty-state">
                <p>{__("Create/Select a question to view details", "tutorpress")}</p>
              </div>
            </div>
          </div>

          {/* Right Column: Contextual question settings */}
          <div className="quiz-modal-right-column">
            <div className="quiz-modal-question-settings">
              <h4>{__("Question Type", "tutorpress")}</h4>
              <div className="quiz-modal-empty-state">
                <p>{__("Select a question to view settings", "tutorpress")}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  };

  const renderSettingsTab = () => {
    const selectedFeedbackMode = feedbackModeOptions.find(
      (option) => option.value === formState.settings.feedback_mode
    );

    return (
      <div className="quiz-modal-settings">
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
                    value={formState.settings.time_limit.time_value}
                    onChange={(value) =>
                      updateTimeLimit(parseInt(value as string) || 0, formState.settings.time_limit.time_type)
                    }
                    min={0}
                    step={1}
                    style={{ width: "100px", flexShrink: 0 }}
                  />
                  <SelectControl
                    value={formState.settings.time_limit.time_type}
                    options={timeUnitOptions}
                    onChange={(value) => updateTimeLimit(formState.settings.time_limit.time_value, value as TimeUnit)}
                    style={{ width: "100px", flexShrink: 0 }}
                    __nextHasNoMarginBottom
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
                  checked={formState.settings.hide_quiz_time_display}
                  onChange={(checked) => updateSettings({ hide_quiz_time_display: checked })}
                />
              </div>

              {/* Feedback Mode */}
              <div className="quiz-modal-setting-group">
                <SelectControl
                  label={__("Feedback Mode", "tutorpress")}
                  value={formState.settings.feedback_mode}
                  options={feedbackModeOptions.map((option) => ({
                    label: option.label,
                    value: option.value,
                  }))}
                  onChange={(value) => updateSettings({ feedback_mode: value as FeedbackMode })}
                />
                {selectedFeedbackMode && <p className="quiz-modal-setting-help">{selectedFeedbackMode.help}</p>}
              </div>

              {/* Passing Grade */}
              <div className="quiz-modal-setting-group">
                <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                  <NumberControl
                    label={__("Passing Grade", "tutorpress")}
                    value={formState.settings.passing_grade}
                    onChange={(value) =>
                      updateSettings({
                        passing_grade: parseInt(value as string) || 0,
                      })
                    }
                    min={0}
                    max={100}
                    step={1}
                    style={{ width: "120px" }}
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
                  value={formState.settings.max_questions_for_answer}
                  onChange={(value) =>
                    updateSettings({
                      max_questions_for_answer: parseInt(value as string) || 0,
                    })
                  }
                  min={0}
                  step={1}
                  style={{ width: "120px" }}
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
              {coursePreviewAddon.available && (
                <div className="quiz-modal-setting-group">
                  <NumberControl
                    label={__("Available after days", "tutorpress")}
                    value={formState.settings.content_drip_settings.after_xdays_of_enroll}
                    onChange={(value) => updateContentDrip(parseInt(value as string) || 0)}
                    min={0}
                    step={1}
                    style={{ width: "120px" }}
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

  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={quizId ? __("Edit Quiz", "tutorpress") : __("Create Quiz", "tutorpress")}
      onRequestClose={handleClose}
      className="quiz-modal"
      size="large"
    >
      <div className="quiz-modal-content">
        <TabPanel
          className="quiz-modal-tabs"
          activeClass="is-active"
          tabs={tabs}
          onSelect={(tabName) => setActiveTab(tabName)}
        >
          {(tab) => {
            switch (tab.name) {
              case "question-details":
                return renderQuestionDetailsTab();
              case "settings":
                return renderSettingsTab();
              default:
                return null;
            }
          }}
        </TabPanel>

        {/* Modal Footer */}
        <div className="quiz-modal-footer">
          <Button variant="secondary" onClick={handleClose} disabled={isSaving}>
            {__("Cancel", "tutorpress")}
          </Button>
          <Button variant="primary" onClick={handleSave} disabled={!isValid || isSaving} isBusy={isSaving}>
            {isSaving ? __("Saving...", "tutorpress") : __("Save Quiz", "tutorpress")}
          </Button>
        </div>
      </div>
    </Modal>
  );
};
