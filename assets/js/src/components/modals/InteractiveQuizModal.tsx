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

import React, { useState, useEffect } from "react";
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
import type { H5PContent } from "../../types/h5p";
import type { QuizQuestion, QuizQuestionType } from "../../types/quiz";

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
  // Use the same quiz form hook as QuizModal for consistency
  const { formState, updateTitle, updateDescription, updateSettings, resetForm, isValid, isDirty, errors } =
    useQuizForm();

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
  const [isSaving, setIsSaving] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

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
  const handleQuestionSelect = (questionIndex: number) => {
    setSelectedQuestionIndex(questionIndex);
    setIsAddingQuestion(false);

    // Update selectedH5PContent when an H5P question is selected
    const selectedQuestion = questions[questionIndex];
    if (selectedQuestion?.question_type === "h5p") {
      // Try to find the H5P content in our stored data
      // For now, we'll need to maintain the selected H5P content state
      // In a full implementation, we might want to fetch H5P content details by ID
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
        <div className="quiz-modal-empty-state">
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
            <h4>{__("H5P Content Preview", "tutorpress")}</h4>
            <p className="quiz-modal-h5p-preview-description">
              {__("Content ID:", "tutorpress")} {h5pContentId}
            </p>
          </div>
          <div className="quiz-modal-h5p-preview-content">
            {/* Step 3.4: This will be replaced with actual H5P content rendering */}
            <div className="quiz-modal-h5p-placeholder">
              <p>
                <strong>{selectedQuestion.question_title}</strong>
              </p>
              <p>{__("H5P content preview will be implemented in Step 3.4", "tutorpress")}</p>
              <p>
                <em>
                  {__("Content Type:", "tutorpress")}{" "}
                  {selectedH5PContent?.content_type || __("Interactive Content", "tutorpress")}
                </em>
              </p>
            </div>
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
        <div className="quiz-modal-empty-state">
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
              <strong>{__("H5P Type:", "tutorpress")}</strong>
              <span>{selectedH5PContent?.content_type || __("Interactive Content", "tutorpress")}</span>
            </div>

            <div className="quiz-modal-h5p-meta-item">
              <strong>{__("Author:", "tutorpress")}</strong>
              <span>{selectedH5PContent?.user_name || __("Unknown", "tutorpress")}</span>
            </div>

            <div className="quiz-modal-h5p-meta-item">
              <strong>{__("Last Updated:", "tutorpress")}</strong>
              <span>{selectedH5PContent?.updated_at || __("Unknown", "tutorpress")}</span>
            </div>

            <div className="quiz-modal-h5p-actions">
              <Button
                variant="primary"
                href={`${adminUrl}admin.php?page=h5p_new&id=${h5pContentId}`}
                target="_blank"
                className="quiz-modal-h5p-edit-button"
              >
                {__("Edit", "tutorpress")}
              </Button>
            </div>
          </div>
        </div>
      );
    }

    return <div>{__("Unsupported question type for Interactive Quiz.", "tutorpress")}</div>;
  };

  // Handle save (placeholder - will use existing quiz save logic)
  const handleSave = async () => {
    if (!isValid) {
      createNotice("error", __("Please fix the form errors before saving.", "tutorpress"), {
        isDismissible: true,
      });
      return;
    }

    setIsSaving(true);
    setSaveError(null);

    try {
      // TODO: Implement actual save logic using existing quiz endpoints
      // For now, just simulate a save operation
      await new Promise((resolve) => setTimeout(resolve, 1000));

      setSaveSuccess(true);
      createNotice("success", __("Interactive Quiz saved successfully!", "tutorpress"), {
        isDismissible: true,
      });

      // Close modal after brief success indication
      setTimeout(() => {
        onClose();
        resetForm();
        setSaveSuccess(false);
      }, 1500);
    } catch (error) {
      setSaveError(error instanceof Error ? error.message : __("Failed to save Interactive Quiz.", "tutorpress"));
      createNotice("error", __("Failed to save Interactive Quiz.", "tutorpress"), {
        isDismissible: true,
      });
    } finally {
      setIsSaving(false);
    }
  };

  // Handle close (same logic as QuizModal)
  const handleClose = () => {
    if (isDirty) {
      if (confirm(__("You have unsaved changes. Are you sure you want to close?", "tutorpress"))) {
        resetForm();
        onClose();
      }
    } else {
      onClose();
    }
  };

  // Handle retry (same as QuizModal)
  const handleRetry = () => {
    if (quizId) {
      // TODO: Load existing Interactive Quiz data
      setLoadError(null);
    }
  };

  // Handle cancel add question (same as QuizModal)
  const handleCancelAddQuestion = () => {
    setIsAddingQuestion(false);
    setSelectedQuestionType(null);
  };

  // H5P Content Selection handlers
  const handleH5PContentSelect = (content: H5PContent) => {
    setSelectedH5PContent(content);

    // Create a new H5P question when content is selected
    const tempQuestionId = -(Date.now() + Math.floor(Math.random() * 1000));

    const newH5PQuestion: QuizQuestion = {
      question_id: tempQuestionId,
      question_title: content.title || __("Interactive Content", "tutorpress"),
      question_description: content.id.toString(), // Store H5P content ID in description (Tutor LMS format)
      question_mark: 1,
      answer_explanation: "",
      question_order: questions.length + 1,
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

    // Add to questions array
    const updatedQuestions = [...questions, newH5PQuestion];
    setQuestions(updatedQuestions);

    // Select the new question
    setSelectedQuestionIndex(updatedQuestions.length - 1);

    // Close H5P modal
    setIsH5PModalOpen(false);

    // Show success notice
    createNotice("success", __("H5P content added to Interactive Quiz!", "tutorpress"), {
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
      isValid={isValid}
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
                  // Override 2: Pass only Interactive Quiz settings (4 fields)
                  // This will trigger Interactive Quiz mode in SettingsTab
                  attemptsAllowed={formState.settings.attempts_allowed}
                  passingGrade={formState.settings.passing_grade}
                  quizAutoStart={formState.settings.quiz_auto_start}
                  questionsOrder={formState.settings.questions_order}
                  // UI state
                  isSaving={isSaving}
                  saveSuccess={saveSuccess}
                  saveError={saveError}
                  // Handlers
                  onSettingChange={updateSettings}
                  onSaveErrorDismiss={() => setSaveError(null)}
                  // Validation errors
                  errors={errors}

                  // Omit time limit and content drip handlers to trigger Interactive Quiz mode
                  // onTimeChange and onContentDripChange are NOT passed
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
        onClose={handleH5PModalClose}
        onContentSelect={handleH5PContentSelect}
        selectedContent={selectedH5PContent}
        title={__("Select H5P Content for Interactive Quiz", "tutorpress")}
      />

      {/* H5P-specific styling for proper spacing */}
      <style>{`
        .quiz-modal-h5p-meta-item {
          margin-bottom: 15px;
          display: flex;
          justify-content: space-between;
          align-items: center;
        }

        .quiz-modal-h5p-meta-item strong {
          margin-right: 10px;
          min-width: 120px;
        }

        .quiz-modal-h5p-actions {
          margin-top: 30px;
          padding-top: 20px;
          border-top: 1px solid #e0e0e0;
        }

        .quiz-modal-h5p-edit-button {
          width: 100%;
          text-align: center !important;
          justify-content: flex-start !important;
          background-color: #fff !important;
          border: 1px solid #007cba !important;
          color: #007cba !important;
          padding: 10px 15px !important;
          border-radius: 4px !important;
          font-weight: normal !important;
          min-height: auto !important;
          height: auto !important;
        }

        .quiz-modal-h5p-edit-button:hover {
          background: #007cba !important;
          color: #fff !important;
        }
      `}</style>
    </BaseModalLayout>
  );
};
