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
    // Instead of showing question type dropdown, open H5P Content Selection Modal
    createNotice("info", __("H5P Content Selection will be implemented in Step 3.2", "tutorpress"), {
      id: "h5p-content-selection",
      isDismissible: true,
    });
  };

  // Question management handlers (same as QuizModal)
  const handleQuestionSelect = (questionIndex: number) => {
    setSelectedQuestionIndex(questionIndex);
    setIsAddingQuestion(false);
  };

  const handleQuestionTypeSelect = (questionType: QuizQuestionType) => {
    // Not used in Interactive Quiz - H5P content is selected instead
  };

  const handleDeleteQuestion = (questionIndex: number) => {
    const updatedQuestions = questions.filter((_, index) => index !== questionIndex);
    setQuestions(updatedQuestions);
    setSelectedQuestionIndex(null);
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
    return <div>{__("H5P question form will be implemented in Step 3.2", "tutorpress")}</div>;
  };

  const renderQuestionSettings = (): JSX.Element => {
    return <div>{__("H5P question settings will be implemented in Step 3.2", "tutorpress")}</div>;
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
    </BaseModalLayout>
  );
};
