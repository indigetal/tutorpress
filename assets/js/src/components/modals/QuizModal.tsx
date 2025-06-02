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
  Spinner,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { useQuizForm } from "../../hooks/useQuizForm";
import { curriculumStore } from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";
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
  const [isLoading, setIsLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [quizData, setQuizData] = useState<any>(null);

  // Initialize quiz form hook with loaded data
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
  } = useQuizForm(quizData);

  // Get quiz duplication state from curriculum store
  const quizDuplicationState = useSelect((select) => {
    return (select(curriculumStore) as any).getQuizDuplicationState();
  }, []);

  const { setQuizDuplicationState, setTopics } = useDispatch(curriculumStore) as any;
  const { createNotice } = useDispatch(noticesStore);

  /**
   * Load existing quiz data when editing
   */
  const loadExistingQuizData = async (id: number) => {
    setIsLoading(true);
    setLoadError(null);

    try {
      console.log("Loading quiz data for ID:", id);

      // Use our REST API to get quiz details
      const response = (await window.wp.apiFetch({
        path: `/tutorpress/v1/quizzes/${id}`,
        method: "GET",
      })) as any;

      console.log("Loaded quiz data:", response);

      if (response.success && response.data) {
        const quizData = response.data;
        setQuizData(quizData);

        // Manually update form fields with loaded data
        updateTitle(quizData.post_title || "");
        updateDescription(quizData.post_content || "");
        if (quizData.quiz_option) {
          updateSettings(quizData.quiz_option);
        }

        return quizData;
      } else {
        throw new Error(response.message || __("Failed to load quiz data", "tutorpress"));
      }
    } catch (error) {
      console.error("Error loading quiz data:", error);
      const errorMessage = error instanceof Error ? error.message : __("Failed to load quiz data", "tutorpress");
      setLoadError(errorMessage);
      return null;
    } finally {
      setIsLoading(false);
    }
  };

  // Load quiz data when modal opens with quizId
  useEffect(() => {
    if (isOpen && quizId) {
      loadExistingQuizData(quizId);
    } else if (isOpen && !quizId) {
      // Reset for new quiz
      setQuizData(null);
      setLoadError(null);
    }
  }, [isOpen, quizId]);

  // Check Course Preview addon availability on mount
  useEffect(() => {
    if (isOpen) {
      checkCoursePreviewAddon();
      setSaveError(null);
      setSaveSuccess(false);
    }
  }, [isOpen, checkCoursePreviewAddon]);

  /**
   * Update local topics state after quiz creation (following topics pattern)
   */
  const updateTopicsAfterQuizCreation = (newQuiz: any, targetTopicId: number) => {
    setTopics((currentTopics: any[]) =>
      currentTopics.map((topic) => {
        if (topic.id === targetTopicId) {
          return {
            ...topic,
            contents: [
              ...(topic.contents || []),
              {
                id: newQuiz.id || newQuiz.ID,
                title: newQuiz.title || newQuiz.post_title,
                type: "tutor_quiz",
                menu_order: newQuiz.menu_order || 0,
                status: newQuiz.status || "draft",
              },
            ],
          };
        }
        return topic;
      })
    );
  };

  /**
   * Update local topics state after quiz update (following topics pattern)
   */
  const updateTopicsAfterQuizUpdate = (updatedQuiz: any) => {
    setTopics((currentTopics: any[]) =>
      currentTopics.map((topic) => ({
        ...topic,
        contents: (topic.contents || []).map((item: any) => {
          if (item.type === "tutor_quiz" && item.id === (updatedQuiz.id || updatedQuiz.ID)) {
            return {
              ...item,
              title: updatedQuiz.title || updatedQuiz.post_title,
              status: updatedQuiz.status || item.status,
            };
          }
          return item;
        }),
      }))
    );
  };

  const handleClose = () => {
    // Reset any quiz state if needed
    setQuizDuplicationState({ status: "idle" });
    resetForm();
    setQuizData(null);
    setLoadError(null);
    setSaveError(null);
    setSaveSuccess(false);
    onClose();
  };

  const handleSave = async () => {
    if (!validateEntireForm()) {
      setSaveError(__("Please fix the form errors before saving.", "tutorpress"));
      return;
    }

    if (!courseId || !topicId) {
      setSaveError(__("Course ID and Topic ID are required to save the quiz.", "tutorpress"));
      return;
    }

    setIsSaving(true);
    setSaveError(null);
    setSaveSuccess(false);

    try {
      const formData = getFormData();

      // Get QuizService from global window object
      const quizService = (window as any).tutorpress?.quiz?.service;
      if (!quizService) {
        throw new Error(__("Quiz service not available. Please refresh the page and try again.", "tutorpress"));
      }

      console.log("Saving quiz with data:", formData);
      console.log("Course ID:", courseId, "Topic ID:", topicId, "Quiz ID:", quizId);

      let result;
      if (quizId) {
        // Update existing quiz - use the same saveQuiz method but include quiz ID
        console.log("Updating existing quiz:", quizId);
        const formDataWithId = {
          ...formData,
          ID: quizId, // Add the quiz ID to make it an update operation
        };
        result = await quizService.saveQuiz(formDataWithId, courseId, topicId);
      } else {
        // Create new quiz
        console.log("Creating new quiz");
        result = await quizService.saveQuiz(formData, courseId, topicId);
      }

      console.log("Quiz save result:", result);

      if (result.success && result.data) {
        // Show success message briefly
        setSaveSuccess(true);

        if (quizId) {
          // Update existing quiz in local state
          updateTopicsAfterQuizUpdate(result.data);

          // Show success notice
          createNotice("success", __("Quiz updated successfully.", "tutorpress"), {
            type: "snackbar",
          });
        } else {
          // Add new quiz to local state
          updateTopicsAfterQuizCreation(result.data, topicId);

          // Show success notice
          createNotice("success", __("Quiz created successfully.", "tutorpress"), {
            type: "snackbar",
          });
        }

        // Close modal after successful save (following topics pattern)
        setTimeout(() => {
          handleClose();
        }, 1000);
      } else {
        throw new Error(result.error?.message || __("Failed to save quiz", "tutorpress"));
      }
    } catch (error) {
      console.error("Error saving quiz:", error);

      let errorMessage = __("Failed to save quiz. Please try again.", "tutorpress");

      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (typeof error === "string") {
        errorMessage = error;
      }

      setSaveError(errorMessage);

      // Show error notice (following topics pattern)
      createNotice("error", errorMessage, {
        type: "snackbar",
      });
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
        {/* Success/Error Messages */}
        {saveSuccess && (
          <Notice status="success" isDismissible={false}>
            {__("Quiz saved successfully! Updating curriculum...", "tutorpress")}
          </Notice>
        )}

        {saveError && (
          <Notice status="error" isDismissible={true} onRemove={() => setSaveError(null)}>
            {saveError}
          </Notice>
        )}

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
                disabled={isSaving}
              />

              <TextareaControl
                label={__("Quiz Description", "tutorpress")}
                value={formState.description}
                onChange={updateDescription}
                placeholder={__("Enter quiz description...", "tutorpress")}
                rows={3}
                disabled={isSaving}
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
                  disabled={!formState.title.trim() || isSaving}
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
        {/* Success/Error Messages */}
        {saveSuccess && (
          <Notice status="success" isDismissible={false}>
            {__("Quiz saved successfully! Updating curriculum...", "tutorpress")}
          </Notice>
        )}

        {saveError && (
          <Notice status="error" isDismissible={true} onRemove={() => setSaveError(null)}>
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
                    value={formState.settings.time_limit.time_value}
                    onChange={(value) =>
                      updateTimeLimit(parseInt(value as string) || 0, formState.settings.time_limit.time_type)
                    }
                    min={0}
                    step={1}
                    style={{ width: "100px", flexShrink: 0 }}
                    disabled={isSaving}
                  />
                  <SelectControl
                    value={formState.settings.time_limit.time_type}
                    options={timeUnitOptions}
                    onChange={(value) => updateTimeLimit(formState.settings.time_limit.time_value, value as TimeUnit)}
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
                  checked={formState.settings.hide_quiz_time_display}
                  onChange={(checked) => updateSettings({ hide_quiz_time_display: checked })}
                  disabled={isSaving}
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
                  disabled={isSaving}
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
                  value={formState.settings.max_questions_for_answer}
                  onChange={(value) =>
                    updateSettings({
                      max_questions_for_answer: parseInt(value as string) || 0,
                    })
                  }
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
              {coursePreviewAddon.available && (
                <div className="quiz-modal-setting-group">
                  <NumberControl
                    label={__("Available after days", "tutorpress")}
                    value={formState.settings.content_drip_settings.after_xdays_of_enroll}
                    onChange={(value) => updateContentDrip(parseInt(value as string) || 0)}
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
        {/* Loading state when editing quiz */}
        {isLoading && (
          <div className="quiz-modal-loading" style={{ padding: "40px", textAlign: "center" }}>
            <Spinner style={{ margin: "0 auto 16px" }} />
            <p>{__("Loading quiz data...", "tutorpress")}</p>
          </div>
        )}

        {/* Error state when loading quiz fails */}
        {loadError && (
          <div className="quiz-modal-error" style={{ padding: "20px" }}>
            <Notice status="error" isDismissible={false}>
              <strong>{__("Error loading quiz:", "tutorpress")}</strong> {loadError}
            </Notice>
            <div style={{ marginTop: "16px", textAlign: "center" }}>
              <Button variant="primary" onClick={() => quizId && loadExistingQuizData(quizId)}>
                {__("Try Again", "tutorpress")}
              </Button>
              <Button variant="secondary" onClick={handleClose} style={{ marginLeft: "8px" }}>
                {__("Cancel", "tutorpress")}
              </Button>
            </div>
          </div>
        )}

        {/* Main content - only show when not loading and no error */}
        {!isLoading && !loadError && (
          <>
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
              <Button
                variant="primary"
                onClick={handleSave}
                disabled={!isValid || isSaving || saveSuccess}
                isBusy={isSaving}
              >
                {isSaving
                  ? quizId
                    ? __("Updating...", "tutorpress")
                    : __("Saving...", "tutorpress")
                  : saveSuccess
                  ? __("Saved!", "tutorpress")
                  : quizId
                  ? __("Update Quiz", "tutorpress")
                  : __("Save Quiz", "tutorpress")}
              </Button>
            </div>
          </>
        )}
      </div>
    </Modal>
  );
};
