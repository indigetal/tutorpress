/**
 * Interactive Quiz Modal Component
 *
 * @description Modal for creating and editing Interactive Quiz (H5P) content within the course curriculum.
 *              Uses the IDENTICAL structure to QuizModal with H5P-specific overrides:
 *              1. Replaces question management with H5P content selection in question-details tab
 *              2. Limits settings to 4 Interactive Quiz specific fields only
 *              3. Maintains identical UI/UX to Quiz Modal for consistency
 *
 * @package TutorPress
 * @subpackage Components/Modals
 * @since 1.4.0
 */

import React, { useState, useEffect, useMemo } from "react";
import { TabPanel, Button, TextControl, TextareaControl, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { useQuizForm } from "../../hooks/quiz/useQuizForm";
import { curriculumStore } from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";
import { BaseModalLayout, BaseModalHeader } from "../common";
import { SettingsTab } from "./quiz/SettingsTab";
import { QuestionDetailsTab } from "./quiz/QuestionDetailsTab";
import { H5PContentSelectionModal } from "./interactive-quiz/H5PContentSelectionModal";
import { H5PContentPreview } from "../h5p/H5PContentPreview";
import type { H5PContent } from "../../types/h5p";
import type { QuizQuestion, QuizQuestionType, QuizDetails, QuizQuestionOption } from "../../types/quiz";

interface InteractiveQuizModalProps {
  isOpen: boolean;
  onClose: () => void;
  topicId?: number;
  courseId?: number;
  quizId?: number; // For editing existing Interactive Quiz
}

export const InteractiveQuizModal: React.FC<InteractiveQuizModalProps> = ({
  isOpen,
  onClose,
  topicId,
  courseId,
  quizId,
}) => {
  // Quiz data state for loading existing Interactive Quizzes
  const [quizData, setQuizData] = useState<QuizDetails | null>(null);

  // Use the same quiz form hook as QuizModal for consistency
  const {
    formState,
    updateTitle,
    updateDescription,
    updateSettings,
    updateTimeLimit,
    updateContentDrip,
    resetForm,
    resetToDefaults,
    initializeWithData,
    isValid,
    isDirty,
    errors,
  } = useQuizForm();

  // Question management state (identical to QuizModal)
  const [questions, setQuestions] = useState<QuizQuestion[]>([]);
  const [selectedQuestionIndex, setSelectedQuestionIndex] = useState<number | null>(null);
  const [isAddingQuestion, setIsAddingQuestion] = useState(false);
  const [selectedQuestionType, setSelectedQuestionType] = useState<QuizQuestionType | null>(null);
  const [questionTypes] = useState([]); // Empty for Interactive Quiz - no question types needed
  const [loadingQuestionTypes] = useState(false);

  // H5P Content Selection Modal state
  const [isH5PModalOpen, setIsH5PModalOpen] = useState(false);
  const [selectedH5PContent, setSelectedH5PContent] = useState<H5PContent | null>(null);

  // Active tab state (same as QuizModal)
  const [activeTab, setActiveTab] = useState("question-details");

  // Store state and dispatch
  const { createNotice } = useDispatch(noticesStore);
  const { saveQuiz, getQuizDetails } = useDispatch(curriculumStore) as any;
  const [isSaving, setIsSaving] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Add state for "All Settings" toggle
  const [showAllSettings, setShowAllSettings] = useState(false);

  // Custom validity check that includes H5P question validation
  const isInteractiveQuizValid = useMemo(() => {
    const hasH5PQuestions = questions.filter((q) => q.question_type === "h5p").length > 0;
    return isValid && hasH5PQuestions;
  }, [isValid, questions]);

  // Load existing quiz data function (same as QuizModal)
  const loadExistingQuizData = async (id: number) => {
    setIsLoading(true);
    setLoadError(null);

    try {
      // Use the curriculum store to get quiz details (same as QuizModal)
      await getQuizDetails(id);

      // Use WordPress apiFetch with relative path as fallback (same as QuizModal)
      const response = (await (window as any).wp.apiFetch({
        path: `/tutorpress/v1/quizzes/${id}`,
        method: "GET",
      })) as any;

      if (!response.success || !response.data) {
        throw new Error(response.message || __("Failed to load Interactive Quiz data", "tutorpress"));
      }

      const data = response.data;

      // Set quiz data for form initialization
      setQuizData(data);

      // Initialize form with loaded data (clean approach - no dirty state marking)
      initializeWithData(data);

      // Load questions and convert them to proper format
      if (data.questions && Array.isArray(data.questions)) {
        const loadedQuestions: QuizQuestion[] = data.questions.map((question: any, index: number) => ({
          ...question,
          _data_status: "no_change" as const,
          question_answers:
            question.question_answers?.map((answer: any) => ({
              ...answer,
              _data_status: "no_change" as const,
            })) || [],
        }));

        setQuestions(loadedQuestions);

        // Select first H5P question if available
        if (loadedQuestions.length > 0) {
          setSelectedQuestionIndex(0);

          // If it's an H5P question, load the actual H5P content details
          const firstQuestion = loadedQuestions[0];
          if (firstQuestion.question_type === "h5p") {
            const h5pContentId = parseInt(firstQuestion.question_description);
            if (!isNaN(h5pContentId)) {
              try {
                // Fetch H5P content metadata from the API (include course_id for collaborative access)
                const apiPath = courseId
                  ? `/tutorpress/v1/h5p/contents?per_page=100&course_id=${courseId}`
                  : `/tutorpress/v1/h5p/contents?per_page=100`;
                const h5pResponse = (await (window as any).wp.apiFetch({
                  path: apiPath,
                  method: "GET",
                })) as any;

                if (h5pResponse?.items?.length > 0) {
                  // Find the specific content by ID
                  const h5pContent = h5pResponse.items.find((item: any) => item.id === h5pContentId);
                  if (h5pContent) {
                    setSelectedH5PContent({
                      id: h5pContent.id,
                      title: h5pContent.title || firstQuestion.question_title,
                      content_type: h5pContent.content_type || "h5p",
                      user_id: h5pContent.user_id || 0,
                      user_name: h5pContent.user_name || __("Unknown", "tutorpress"),
                      updated_at: h5pContent.updated_at || __("Unknown", "tutorpress"),
                    });
                  } else {
                    // Content not found in user's content list
                    setSelectedH5PContent({
                      id: h5pContentId,
                      title: firstQuestion.question_title,
                      content_type: "h5p",
                      user_id: 0,
                      user_name: __("Unknown", "tutorpress"),
                      updated_at: __("Unknown", "tutorpress"),
                    });
                  }
                } else {
                  // No H5P content found for this user
                  setSelectedH5PContent({
                    id: h5pContentId,
                    title: firstQuestion.question_title,
                    content_type: "h5p",
                    user_id: 0,
                    user_name: __("Unknown", "tutorpress"),
                    updated_at: __("Unknown", "tutorpress"),
                  });
                }
              } catch (h5pError) {
                console.error("TutorPress: Failed to load H5P content metadata:", h5pError);
                // Fallback to basic H5P content object
                setSelectedH5PContent({
                  id: h5pContentId,
                  title: firstQuestion.question_title,
                  content_type: "h5p",
                  user_id: 0,
                  user_name: __("Unknown", "tutorpress"),
                  updated_at: __("Unknown", "tutorpress"),
                });
              }
            }
          }
        }
      }
    } catch (error) {
      console.error("Error loading Interactive Quiz data:", error);
      const errorMessage = error instanceof Error ? error.message : "Unknown error occurred";
      setLoadError(__("Failed to load Interactive Quiz data: ", "tutorpress") + errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  // useEffect to load existing quiz data when modal opens
  useEffect(() => {
    if (isOpen && quizId) {
      loadExistingQuizData(quizId);
    } else if (isOpen && !quizId) {
      // Reset for new Interactive Quiz (same as QuizModal)
      setQuizData(null);
      setLoadError(null);
      // Reset form to clean defaults for new quiz
      resetToDefaults();
      // Reset questions state for new quiz
      setQuestions([]);
      setSelectedQuestionIndex(null);
      setSelectedH5PContent(null);
    }
  }, [isOpen, quizId, resetToDefaults]);

  // Tab configuration (identical to QuizModal)
  const tabs = [
    {
      name: "question-details",
      title: __("Question Details", "tutorpress"), // Keep original name
      className: "quiz-modal-question-details-tab",
    },
    {
      name: "settings",
      title: __("Settings", "tutorpress"),
      className: "quiz-modal-settings-tab",
    },
  ];

  // Override 1: Handle Add Question - Open H5P Content Selection instead of dropdown
  const handleAddQuestion = () => {
    // Open H5P Content Selection Modal instead of showing question type dropdown
    setIsH5PModalOpen(true);
  };

  // Question management handlers (same as QuizModal)
  const handleQuestionSelect = async (questionIndex: number) => {
    setSelectedQuestionIndex(questionIndex);
    setIsAddingQuestion(false);

    // Update selectedH5PContent when an H5P question is selected
    const selectedQuestion = questions[questionIndex];
    if (selectedQuestion?.question_type === "h5p") {
      const h5pContentId = parseInt(selectedQuestion.question_description);
      if (!isNaN(h5pContentId)) {
        try {
          // Fetch H5P content metadata from the API (include course_id for collaborative access)
          const apiPath = courseId
            ? `/tutorpress/v1/h5p/contents?per_page=100&course_id=${courseId}`
            : `/tutorpress/v1/h5p/contents?per_page=100`;
          const h5pResponse = (await (window as any).wp.apiFetch({
            path: apiPath,
            method: "GET",
          })) as any;

          if (h5pResponse?.items?.length > 0) {
            // Find the specific content by ID
            const h5pContent = h5pResponse.items.find((item: any) => item.id === h5pContentId);
            if (h5pContent) {
              setSelectedH5PContent({
                id: h5pContent.id,
                title: h5pContent.title || selectedQuestion.question_title,
                content_type: h5pContent.content_type || "h5p",
                user_id: h5pContent.user_id || 0,
                user_name: h5pContent.user_name || __("Unknown", "tutorpress"),
                updated_at: h5pContent.updated_at || __("Unknown", "tutorpress"),
              });
            } else {
              // Content not found in user's content list
              setSelectedH5PContent({
                id: h5pContentId,
                title: selectedQuestion.question_title,
                content_type: "h5p",
                user_id: 0,
                user_name: __("Unknown", "tutorpress"),
                updated_at: __("Unknown", "tutorpress"),
              });
            }
          }
        } catch (h5pError) {
          console.error("TutorPress: Failed to load H5P content metadata on question select:", h5pError);
          // Fallback to basic H5P content object
          setSelectedH5PContent({
            id: h5pContentId,
            title: selectedQuestion.question_title,
            content_type: "h5p",
            user_id: 0,
            user_name: __("Unknown", "tutorpress"),
            updated_at: __("Unknown", "tutorpress"),
          });
        }
      }
    }
  };

  const handleQuestionTypeSelect = (questionType: QuizQuestionType) => {
    // Not used in Interactive Quiz - H5P content is selected instead
  };

  const handleDeleteQuestion = (questionIndex: number) => {
    const questionToDelete = questions[questionIndex];
    const updatedQuestions = questions.filter((_, index) => index !== questionIndex);
    setQuestions(updatedQuestions);
    setSelectedQuestionIndex(null);

    // Clear selectedH5PContent if we're deleting the H5P question
    if (questionToDelete?.question_type === "h5p") {
      setSelectedH5PContent(null);
    }
  };

  const handleQuestionReorder = (items: Array<{ id: number; [key: string]: any }>) => {
    // Reorder questions based on new order
    const reorderedQuestions = items.map((item, index) => ({
      ...questions.find((q) => q.question_id === item.id)!,
      question_order: index + 1,
    }));
    setQuestions(reorderedQuestions);
  };

  const getQuestionTypeDisplayName = (questionType: QuizQuestionType): string => {
    return questionType === "h5p" ? __("Interactive Content", "tutorpress") : questionType;
  };

  const renderQuestionForm = (): JSX.Element => {
    // If no question is selected, show instructions
    if (selectedQuestionIndex === null || questions.length === 0) {
      return (
        <div className="quiz-modal-empty-state tpress-empty-state-container">
          <div className="quiz-modal-empty-content">
            <h4>{__("No Interactive Content Selected", "tutorpress")}</h4>
            <p>{__("Click 'Add Question' to select H5P content for this Interactive Quiz.", "tutorpress")}</p>
          </div>
        </div>
      );
    }

    const selectedQuestion = questions[selectedQuestionIndex];
    if (selectedQuestion?.question_type === "h5p") {
      const h5pContentId = parseInt(selectedQuestion.question_description);

      return (
        <div className="quiz-modal-h5p-preview">
          <div className="quiz-modal-h5p-preview-header">
            <h4 className="quiz-modal-h5p-content-title">{selectedQuestion.question_title}</h4>
          </div>
          <div className="quiz-modal-h5p-preview-content">
            <H5PContentPreview
              contentId={h5pContentId}
              className="quiz-modal-h5p-content"
              showHeader={false}
              courseId={courseId}
            />
          </div>
        </div>
      );
    }

    return <div>{__("Unsupported question type for Interactive Quiz.", "tutorpress")}</div>;
  };

  const renderQuestionSettings = (): JSX.Element => {
    // If no question is selected, show instructions
    if (selectedQuestionIndex === null || questions.length === 0) {
      return (
        <div className="quiz-modal-empty-state tpress-empty-state-container">
          <p>{__("Select H5P content to view settings.", "tutorpress")}</p>
        </div>
      );
    }

    const selectedQuestion = questions[selectedQuestionIndex];

    if (selectedQuestion?.question_type === "h5p" && selectedH5PContent) {
      const h5pContentId = selectedQuestion.question_description;
      const adminUrl = (window as any).tutorPressCurriculum?.adminUrl || "";

      return (
        <div className="quiz-modal-h5p-settings">
          <div className="quiz-modal-h5p-meta">
            <div className="quiz-modal-h5p-meta-item">
              <strong>{__("Author:", "tutorpress")}</strong>
              <span>{selectedH5PContent?.user_name || __("Unknown", "tutorpress")}</span>
            </div>

            <div className="quiz-modal-h5p-meta-item">
              <strong>{__("Last Updated:", "tutorpress")}</strong>
              <span>{selectedH5PContent?.updated_at || __("Unknown", "tutorpress")}</span>
            </div>
          </div>
        </div>
      );
    }

    return <div>{__("Unsupported question type for Interactive Quiz.", "tutorpress")}</div>;
  };

  // Handle save (using WordPress data store like QuizModal)
  const handleSave = async () => {
    if (!isValid) {
      createNotice("error", __("Please fix the form errors before saving.", "tutorpress"), {
        isDismissible: true,
      });
      return;
    }

    // Validate that at least one H5P question exists
    const h5pQuestions = questions.filter((q) => q.question_type === "h5p");
    if (h5pQuestions.length === 0) {
      createNotice("error", __("Please add a question.", "tutorpress"), {
        isDismissible: true,
      });
      return;
    }

    if (!courseId || !topicId) {
      setSaveError(__("Course ID and Topic ID are required to save the Interactive Quiz.", "tutorpress"));
      return;
    }

    setIsSaving(true);
    setSaveError(null);
    setSaveSuccess(false);

    try {
      // Build form data in the same format as QuizModal
      const formData: any = {
        post_title: formState.title,
        post_content: formState.description,
        quiz_option: {
          // Include all default quiz settings for Tutor LMS frontend course builder compatibility
          ...formState.settings,
          // Override with Interactive Quiz identifier
          quiz_type: "tutor_h5p_quiz",
        },
        questions: questions,
      };

      // Add quiz ID for updates
      if (quizId) {
        formData.ID = quizId;
      }

      // Use the curriculum store saveQuiz action (same as QuizModal)
      await saveQuiz(formData, courseId, topicId);

      setSaveSuccess(true);

      if (quizId) {
        createNotice("success", __("Interactive Quiz updated successfully.", "tutorpress"), {
          type: "snackbar",
        });
      } else {
        createNotice("success", __("Interactive Quiz created successfully.", "tutorpress"), {
          type: "snackbar",
        });
      }

      // Close modal after successful save (following Quiz Modal pattern)
      setTimeout(() => {
        handleClose();
      }, 1000);
    } catch (error) {
      console.error("Error saving Interactive Quiz:", error);

      let errorMessage = __("Failed to save Interactive Quiz. Please try again.", "tutorpress");

      if (error instanceof Error) {
        errorMessage = error.message;
      } else if (typeof error === "string") {
        errorMessage = error;
      }

      setSaveError(errorMessage);

      createNotice("error", errorMessage, {
        type: "snackbar",
      });
    } finally {
      setIsSaving(false);
    }
  };

  // Handle close (now handled by BaseModalLayout and BaseModalHeader)
  const handleClose = () => {
    // Reset form and state
    resetForm();
    setQuizData(null);
    setQuestions([]);
    setSelectedQuestionIndex(null);
    setIsAddingQuestion(false);
    setSelectedQuestionType(null);
    setSelectedH5PContent(null);
    setIsH5PModalOpen(false);
    setLoadError(null);
    setSaveError(null);
    setSaveSuccess(false);
    setIsLoading(false);
    onClose();
  };

  // Handle retry (same as QuizModal)
  const handleRetry = () => {
    if (quizId) {
      loadExistingQuizData(quizId);
    }
  };

  // Handle cancel add question (same as QuizModal)
  const handleCancelAddQuestion = () => {
    setIsAddingQuestion(false);
    setSelectedQuestionType(null);
  };

  // H5P Content Selection handlers
  const handleH5PContentSelect = (contentArray: H5PContent[]) => {
    if (contentArray.length === 0) return;

    // Create H5P questions for each selected content
    const newH5PQuestions: QuizQuestion[] = contentArray.map((content, index) => {
      const tempQuestionId = -(Date.now() + Math.floor(Math.random() * 1000) + index);

      return {
        question_id: tempQuestionId,
        question_title: content.title || __("Interactive Content", "tutorpress"),
        question_description: content.id.toString(), // Store H5P content ID in description (Tutor LMS format)
        question_mark: 1,
        answer_explanation: "",
        question_order: questions.length + index + 1,
        question_type: "h5p" as QuizQuestionType,
        question_settings: {
          question_type: "h5p" as QuizQuestionType,
          answer_required: true,
          randomize_question: false,
          question_mark: 1,
          show_question_mark: true,
          has_multiple_correct_answer: false,
          is_image_matching: false,
        },
        question_answers: [], // H5P content doesn't use traditional answers
        _data_status: "new",
      };
    });

    // Add to questions array
    const updatedQuestions = [...questions, ...newH5PQuestions];
    setQuestions(updatedQuestions);

    // Select the first new question
    setSelectedQuestionIndex(questions.length);

    // Set the first selected content as current
    setSelectedH5PContent(contentArray[0]);

    // Close H5P modal
    setIsH5PModalOpen(false);

    // Show success notice
    const message =
      contentArray.length === 1
        ? __("H5P content added to Interactive Quiz!", "tutorpress")
        : __("%d H5P items added to Interactive Quiz!", "tutorpress").replace("%d", contentArray.length.toString());

    createNotice("success", message, {
      isDismissible: true,
    });
  };

  const handleH5PModalClose = () => {
    setIsH5PModalOpen(false);
  };

  // Modal header (identical to QuizModal)
  const modalHeader = (
    <BaseModalHeader
      title={quizId ? __("Edit Interactive Quiz", "tutorpress") : __("Create Interactive Quiz", "tutorpress")}
      isValid={isInteractiveQuizValid}
      isDirty={isDirty}
      isSaving={isSaving}
      saveSuccess={saveSuccess}
      primaryButtonText={
        quizId ? __("Update Interactive Quiz", "tutorpress") : __("Save Interactive Quiz", "tutorpress")
      }
      savingButtonText={quizId ? __("Updating...", "tutorpress") : __("Saving...", "tutorpress")}
      successButtonText={__("Saved!", "tutorpress")}
      onSave={handleSave}
      onClose={handleClose}
      className="quiz-modal"
    />
  );

  return (
    <BaseModalLayout
      isOpen={isOpen}
      onClose={handleClose}
      isDirty={isDirty}
      className="quiz-modal"
      isLoading={isLoading}
      loadingMessage={__("Loading Interactive Quiz data...", "tutorpress")}
      loadError={loadError}
      onRetry={handleRetry}
      header={modalHeader}
    >
      <TabPanel
        className="quiz-modal-tabs"
        activeClass="is-active"
        tabs={tabs}
        onSelect={(tabName) => setActiveTab(tabName)}
      >
        {(tab) => {
          switch (tab.name) {
            case "question-details":
              return (
                <QuestionDetailsTab
                  // Form state (mapped to QuestionDetailsTab props)
                  formTitle={formState.title}
                  formDescription={formState.description}
                  topicId={topicId}
                  // Question management state (same as QuizModal)
                  questions={questions}
                  selectedQuestionIndex={selectedQuestionIndex}
                  isAddingQuestion={isAddingQuestion}
                  selectedQuestionType={selectedQuestionType}
                  questionTypes={questionTypes}
                  loadingQuestionTypes={loadingQuestionTypes}
                  // UI state
                  isSaving={isSaving}
                  saveSuccess={saveSuccess}
                  saveError={saveError}
                  // Handlers (mapped to QuestionDetailsTab props)
                  onTitleChange={updateTitle}
                  onDescriptionChange={updateDescription}
                  onSaveErrorDismiss={() => setSaveError(null)}
                  // Override 1: Custom handlers - handleAddQuestion opens H5P Content Selection
                  onAddQuestion={handleAddQuestion}
                  onQuestionSelect={handleQuestionSelect}
                  onQuestionTypeSelect={handleQuestionTypeSelect}
                  onDeleteQuestion={handleDeleteQuestion}
                  onQuestionReorder={handleQuestionReorder}
                  onCancelAddQuestion={handleCancelAddQuestion}
                  getQuestionTypeDisplayName={getQuestionTypeDisplayName}
                  // Question rendering (placeholder for Step 3.2)
                  renderQuestionForm={renderQuestionForm}
                  renderQuestionSettings={renderQuestionSettings}
                />
              );
            case "settings":
              return (
                <SettingsTab
                  // Core settings (always passed)
                  attemptsAllowed={formState.settings.attempts_allowed}
                  passingGrade={formState.settings.passing_grade}
                  quizAutoStart={formState.settings.quiz_auto_start}
                  questionsOrder={formState.settings.questions_order}
                  // All settings with defaults (for Tutor LMS compatibility)
                  timeValue={formState.settings.time_limit.time_value}
                  timeType={formState.settings.time_limit.time_type}
                  hideQuizTimeDisplay={formState.settings.hide_quiz_time_display}
                  feedbackMode={formState.settings.feedback_mode}
                  maxQuestionsForAnswer={formState.settings.max_questions_for_answer}
                  afterXDaysOfEnroll={formState.settings.content_drip_settings.after_xdays_of_enroll}
                  questionLayoutView={formState.settings.question_layout_view}
                  hideQuestionNumberOverview={formState.settings.hide_question_number_overview}
                  shortAnswerCharactersLimit={formState.settings.short_answer_characters_limit}
                  openEndedAnswerCharactersLimit={formState.settings.open_ended_answer_characters_limit}
                  // Addon state
                  coursePreviewAddonAvailable={false} // TODO: Implement if needed
                  // UI state
                  isSaving={isSaving}
                  saveSuccess={saveSuccess}
                  saveError={saveError}
                  // All Settings toggle state
                  showAllSettings={showAllSettings}
                  onShowAllSettingsChange={setShowAllSettings}
                  // Handlers (always passed for full functionality)
                  onSettingChange={updateSettings}
                  onTimeChange={updateTimeLimit}
                  onContentDripChange={updateContentDrip}
                  onSaveErrorDismiss={() => setSaveError(null)}
                  // Validation errors
                  errors={errors}
                />
              );
            default:
              return null;
          }
        }}
      </TabPanel>

      {/* H5P Content Selection Modal */}
      <H5PContentSelectionModal
        isOpen={isH5PModalOpen}
        onClose={() => setIsH5PModalOpen(false)}
        onContentSelect={handleH5PContentSelect}
        selectedContent={[]} // Always start with empty selection for adding new content
        title={__("Select H5P Content for Interactive Quiz", "tutorpress")}
        excludeContentIds={questions
          .filter((q) => q.question_type === "h5p")
          .map((q) => parseInt(q.question_description))
          .filter((id) => !isNaN(id))}
        courseId={courseId}
      />
    </BaseModalLayout>
  );
};
