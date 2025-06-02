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
  Icon,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { useQuizForm } from "../../hooks/useQuizForm";
import { curriculumStore } from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";
import type {
  TimeUnit,
  FeedbackMode,
  QuizQuestionType,
  QuizQuestion,
  QuizDetails,
  QuizForm,
  QuizQuestionSettings,
  getDefaultQuestionSettings,
} from "../../types/quiz";

interface QuizModalProps {
  isOpen: boolean;
  onClose: () => void;
  topicId?: number;
  courseId?: number;
  quizId?: number; // For editing existing quiz
}

interface QuestionTypeOption {
  label: string;
  value: QuizQuestionType;
  is_pro: boolean;
}

export const QuizModal: React.FC<QuizModalProps> = ({ isOpen, onClose, topicId, courseId, quizId }) => {
  const [activeTab, setActiveTab] = useState("question-details");
  const [isSaving, setIsSaving] = useState(false);
  const [isLoading, setIsLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [quizData, setQuizData] = useState<any>(null);

  // Question management state
  const [isAddingQuestion, setIsAddingQuestion] = useState(false);
  const [selectedQuestionType, setSelectedQuestionType] = useState<QuizQuestionType | null>(null);
  const [questionTypes, setQuestionTypes] = useState<QuestionTypeOption[]>([]);
  const [loadingQuestionTypes, setLoadingQuestionTypes] = useState(false);

  // Question list state - Step 3.2
  const [questions, setQuestions] = useState<QuizQuestion[]>([]);
  const [selectedQuestionIndex, setSelectedQuestionIndex] = useState<number | null>(null);
  const [editingQuestionId, setEditingQuestionId] = useState<number | null>(null);

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
   * Load question types from Tutor LMS
   */
  const loadQuestionTypes = async () => {
    setLoadingQuestionTypes(true);
    try {
      // Check for Tutor LMS question types in multiple ways
      let questionTypesData = null;

      // Method 1: Try window.tutor_utils (if exposed globally)
      if ((window as any).tutor_utils && typeof (window as any).tutor_utils.get_question_types === "function") {
        questionTypesData = (window as any).tutor_utils.get_question_types();
        console.log("Loaded question types from window.tutor_utils:", questionTypesData);
      }
      // Method 2: Try window._tutorobject (common Tutor LMS global)
      else if ((window as any)._tutorobject && (window as any)._tutorobject.question_types) {
        questionTypesData = (window as any)._tutorobject.question_types;
        console.log("Loaded question types from _tutorobject:", questionTypesData);
      }
      // Method 3: Try REST API endpoint for question types
      else {
        try {
          const response = await window.wp.apiFetch({
            path: "/tutor/v1/question-types",
            method: "GET",
          });
          if (response && response.data) {
            questionTypesData = response.data;
            console.log("Loaded question types from REST API:", questionTypesData);
          }
        } catch (apiError) {
          console.log("REST API for question types not available:", apiError);
        }
      }

      if (questionTypesData && typeof questionTypesData === "object") {
        // Convert to our option format
        const options: QuestionTypeOption[] = Object.entries(questionTypesData).map(
          ([value, config]: [string, any]) => ({
            label: config.name || value.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase()),
            value: value as QuizQuestionType,
            is_pro: config.is_pro || false,
          })
        );

        setQuestionTypes(options);
        console.log("Successfully loaded question types:", options);
      } else {
        // Fallback to static question types based on Tutor LMS core
        console.warn("Using fallback question types - Tutor LMS question types not available");
        const fallbackTypes: QuestionTypeOption[] = [
          { label: __("True/False", "tutorpress"), value: "true_false", is_pro: false },
          { label: __("Single Choice", "tutorpress"), value: "single_choice", is_pro: false },
          { label: __("Multiple Choice", "tutorpress"), value: "multiple_choice", is_pro: false },
          { label: __("Open Ended", "tutorpress"), value: "open_ended", is_pro: false },
          { label: __("Fill In The Blanks", "tutorpress"), value: "fill_in_the_blank", is_pro: false },
          { label: __("Short Answer", "tutorpress"), value: "short_answer", is_pro: true },
          { label: __("Matching", "tutorpress"), value: "matching", is_pro: true },
          { label: __("Image Matching", "tutorpress"), value: "image_matching", is_pro: true },
          { label: __("Image Answering", "tutorpress"), value: "image_answering", is_pro: true },
          { label: __("Ordering", "tutorpress"), value: "ordering", is_pro: true },
        ];
        setQuestionTypes(fallbackTypes);
      }
    } catch (error) {
      console.error("Error loading question types:", error);
      // Set empty array on error, but provide basic fallback
      const basicTypes: QuestionTypeOption[] = [
        { label: __("True/False", "tutorpress"), value: "true_false", is_pro: false },
        { label: __("Multiple Choice", "tutorpress"), value: "multiple_choice", is_pro: false },
      ];
      setQuestionTypes(basicTypes);
    } finally {
      setLoadingQuestionTypes(false);
    }
  };

  /**
   * Handle add question button click - Step 3.2 - Toggle dropdown
   */
  const handleAddQuestion = () => {
    console.log("Toggling add question dropdown");
    setIsAddingQuestion(!isAddingQuestion);
    if (isAddingQuestion) {
      // Closing dropdown - reset state
      setSelectedQuestionType(null);
      setEditingQuestionId(null);
    }
  };

  /**
   * Handle question type selection
   */
  const handleQuestionTypeSelect = (questionType: QuizQuestionType) => {
    setSelectedQuestionType(questionType);
    console.log("Selected question type:", questionType);

    // Immediately create a new question after type selection - Step 3.2
    handleCreateNewQuestion(questionType);
  };

  /**
   * Handle question selection from list - Step 3.2
   */
  const handleQuestionSelect = (questionIndex: number) => {
    setSelectedQuestionIndex(questionIndex);
    setEditingQuestionId(questions[questionIndex]?.question_id || null);
    setIsAddingQuestion(false); // Exit add mode when selecting existing question
    console.log("Selected question:", questions[questionIndex]);
  };

  /**
   * Handle creating new question after type selection - Step 3.2
   */
  const handleCreateNewQuestion = (questionType?: QuizQuestionType) => {
    const typeToUse = questionType || selectedQuestionType;
    if (!typeToUse || !formState.title.trim()) {
      return;
    }

    // Create a new question object
    const newQuestion: QuizQuestion = {
      question_id: Date.now(), // Temporary ID for new questions
      question_title: "",
      question_description: "",
      question_mark: 1,
      answer_explanation: "",
      question_order: questions.length + 1,
      question_type: typeToUse,
      question_settings: {
        question_type: typeToUse,
        answer_required: true,
        randomize_question: false,
        question_mark: 1,
        show_question_mark: true,
        has_multiple_correct_answer: typeToUse === "multiple_choice",
        is_image_matching: typeToUse.includes("image"),
      },
      question_answers: [],
      _data_status: "new",
    };

    // Add to questions array
    const updatedQuestions = [...questions, newQuestion];
    setQuestions(updatedQuestions);

    // Select the new question
    setSelectedQuestionIndex(updatedQuestions.length - 1);
    setEditingQuestionId(newQuestion.question_id);

    // Reset add question state
    setIsAddingQuestion(false);
    setSelectedQuestionType(null);

    console.log("Created new question:", newQuestion);
  };

  /**
   * Handle deleting a question - Step 3.2
   */
  const handleDeleteQuestion = (questionIndex: number) => {
    if (questionIndex < 0 || questionIndex >= questions.length) {
      return;
    }

    const questionToDelete = questions[questionIndex];
    const updatedQuestions = questions.filter((_, index) => index !== questionIndex);

    // Update question orders
    const reorderedQuestions = updatedQuestions.map((question, index) => ({
      ...question,
      question_order: index + 1,
    }));

    setQuestions(reorderedQuestions);

    // Adjust selection
    if (selectedQuestionIndex === questionIndex) {
      setSelectedQuestionIndex(null);
      setEditingQuestionId(null);
    } else if (selectedQuestionIndex !== null && selectedQuestionIndex > questionIndex) {
      setSelectedQuestionIndex(selectedQuestionIndex - 1);
    }

    console.log("Deleted question:", questionToDelete);
  };

  /**
   * Get question type display name for badges - Step 3.2
   */
  const getQuestionTypeDisplayName = (questionType: QuizQuestionType): string => {
    const typeOption = questionTypes.find((type) => type.value === questionType);
    if (typeOption) {
      return typeOption.label;
    }

    // Fallback display names
    const displayNames: Record<QuizQuestionType, string> = {
      true_false: __("True/False", "tutorpress"),
      single_choice: __("Single Choice", "tutorpress"),
      multiple_choice: __("Multiple Choice", "tutorpress"),
      open_ended: __("Open Ended", "tutorpress"),
      fill_in_the_blank: __("Fill in the Blanks", "tutorpress"),
      short_answer: __("Short Answer", "tutorpress"),
      matching: __("Matching", "tutorpress"),
      image_matching: __("Image Matching", "tutorpress"),
      image_answering: __("Image Answering", "tutorpress"),
      ordering: __("Ordering", "tutorpress"),
      h5p: __("H5P", "tutorpress"),
    };

    return displayNames[questionType] || questionType.replace(/_/g, " ");
  };

  /**
   * Handle question field updates - Step 3.3
   */
  const handleQuestionFieldUpdate = (questionIndex: number, field: keyof QuizQuestion, value: any) => {
    if (questionIndex < 0 || questionIndex >= questions.length) {
      return;
    }

    const updatedQuestions = [...questions];
    updatedQuestions[questionIndex] = {
      ...updatedQuestions[questionIndex],
      [field]: value,
      _data_status: "update", // Mark as updated for Tutor LMS
    };

    setQuestions(updatedQuestions);
    console.log(`Updated question ${questionIndex} field ${field}:`, value);
  };

  /**
   * Render question type-specific content - Step 3.3
   */
  const renderQuestionTypeContent = (question: QuizQuestion): JSX.Element => {
    // This will be expanded in Steps 3.5-3.9 for each question type
    switch (question.question_type) {
      case "true_false":
        return (
          <div className="quiz-modal-question-placeholder">
            <p>{__("True/False answer options will be implemented in Step 3.5", "tutorpress")}</p>
          </div>
        );
      case "single_choice":
        return (
          <div className="quiz-modal-question-placeholder">
            <p>{__("Single Choice answer options will be implemented in Step 3.7", "tutorpress")}</p>
          </div>
        );
      case "multiple_choice":
        return (
          <div className="quiz-modal-question-placeholder">
            <p>{__("Multiple Choice answer options will be implemented in Step 3.6", "tutorpress")}</p>
          </div>
        );
      case "fill_in_the_blank":
        return (
          <div className="quiz-modal-question-placeholder">
            <p>{__("Fill in the Blanks will be implemented in Step 3.8", "tutorpress")}</p>
          </div>
        );
      case "open_ended":
      case "short_answer":
      case "matching":
      case "image_matching":
      case "image_answering":
      case "ordering":
      case "h5p":
        return (
          <div className="quiz-modal-question-placeholder">
            <p>{__("This question type will be implemented in Step 3.9", "tutorpress")}</p>
          </div>
        );
      default:
        return (
          <div className="quiz-modal-question-placeholder">
            <p>{__("Unknown question type", "tutorpress")}</p>
          </div>
        );
    }
  };

  /**
   * Handle question setting updates - Step 3.3
   */
  const handleQuestionSettingUpdate = (questionIndex: number, setting: keyof QuizQuestionSettings, value: any) => {
    if (questionIndex < 0 || questionIndex >= questions.length) {
      return;
    }

    const updatedQuestions = [...questions];
    updatedQuestions[questionIndex] = {
      ...updatedQuestions[questionIndex],
      question_settings: {
        ...updatedQuestions[questionIndex].question_settings,
        [setting]: value,
      },
      _data_status: "update", // Mark as updated for Tutor LMS
    };

    setQuestions(updatedQuestions);
    console.log(`Updated question ${questionIndex} setting ${String(setting)}:`, value);
  };

  /**
   * Render question type-specific settings - Step 3.3
   */
  const renderQuestionTypeSettings = (question: QuizQuestion): JSX.Element => {
    // This will be expanded in Steps 3.5-3.9 for each question type
    switch (question.question_type) {
      case "multiple_choice":
        return (
          <div className="quiz-modal-settings-placeholder">
            <ToggleControl
              label={__("Multiple Correct Answers", "tutorpress")}
              checked={question.question_settings.has_multiple_correct_answer}
              onChange={(checked) =>
                handleQuestionSettingUpdate(questions.indexOf(question), "has_multiple_correct_answer", checked)
              }
              help={__("Allow students to select multiple correct answers", "tutorpress")}
              disabled={isSaving}
            />
          </div>
        );
      case "image_matching":
      case "image_answering":
        return (
          <div className="quiz-modal-settings-placeholder">
            <ToggleControl
              label={__("Image Matching", "tutorpress")}
              checked={question.question_settings.is_image_matching}
              onChange={(checked) =>
                handleQuestionSettingUpdate(questions.indexOf(question), "is_image_matching", checked)
              }
              help={__("Enable image-based matching for this question", "tutorpress")}
              disabled={isSaving}
            />
          </div>
        );
      case "true_false":
      case "single_choice":
      case "fill_in_the_blank":
      case "open_ended":
      case "short_answer":
      case "matching":
      case "ordering":
      case "h5p":
        return (
          <div className="quiz-modal-settings-placeholder">
            <p>{__("No additional settings for this question type", "tutorpress")}</p>
          </div>
        );
      default:
        return (
          <div className="quiz-modal-settings-placeholder">
            <p>{__("Unknown question type", "tutorpress")}</p>
          </div>
        );
    }
  };

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

        // Load questions data - Step 3.2
        if (quizData.questions && Array.isArray(quizData.questions)) {
          const sortedQuestions = quizData.questions.sort(
            (a: QuizQuestion, b: QuizQuestion) => a.question_order - b.question_order
          );
          setQuestions(sortedQuestions);
          console.log("Loaded questions:", sortedQuestions);
        } else {
          setQuestions([]);
        }

        // Reset question selection state
        setSelectedQuestionIndex(null);
        setEditingQuestionId(null);
        setIsAddingQuestion(false);
        setSelectedQuestionType(null);

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
      // Reset questions state for new quiz - Step 3.2
      setQuestions([]);
      setSelectedQuestionIndex(null);
      setEditingQuestionId(null);
      setIsAddingQuestion(false);
      setSelectedQuestionType(null);
    }
  }, [isOpen, quizId]);

  // Load question types when modal opens
  useEffect(() => {
    if (isOpen) {
      loadQuestionTypes();
    }
  }, [isOpen]);

  // Check Course Preview addon availability on mount
  useEffect(() => {
    if (isOpen) {
      checkCoursePreviewAddon();
      setSaveError(null);
      setSaveSuccess(false);
      // Reset question state when modal opens
      if (!quizId) {
        setIsAddingQuestion(false);
        setSelectedQuestionType(null);
        setSelectedQuestionIndex(null);
        setEditingQuestionId(null);
      }
    }
  }, [isOpen, checkCoursePreviewAddon, quizId]);

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
    // Reset questions state - Step 3.2
    setQuestions([]);
    setSelectedQuestionIndex(null);
    setEditingQuestionId(null);
    setIsAddingQuestion(false);
    setSelectedQuestionType(null);
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
      const formData = getFormData(questions);

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
                  onClick={handleAddQuestion}
                  disabled={!formState.title.trim() || isSaving}
                >
                  +
                </Button>
              </div>

              {/* Question Type Dropdown - Show when adding question - Tutor LMS Style */}
              {isAddingQuestion && (
                <div className="quiz-modal-question-type-section">
                  <SelectControl
                    label={__("Question Type", "tutorpress")}
                    value={selectedQuestionType || ""}
                    options={[
                      { label: __("Select Question Type", "tutorpress"), value: "" },
                      ...questionTypes.map((type) => ({
                        label: type.is_pro ? `${type.label} ${__("(Pro)", "tutorpress")}` : type.label,
                        value: type.value,
                      })),
                    ]}
                    onChange={(value) => {
                      if (value) {
                        handleQuestionTypeSelect(value as QuizQuestionType);
                      } else {
                        setSelectedQuestionType(null);
                      }
                    }}
                    disabled={loadingQuestionTypes || isSaving}
                    className="quiz-modal-question-type-select"
                  />

                  {loadingQuestionTypes && (
                    <div className="quiz-modal-loading-question-types">
                      <Spinner style={{ margin: "0 8px 0 0" }} />
                      <span>{__("Loading question types...", "tutorpress")}</span>
                    </div>
                  )}

                  <div className="quiz-modal-question-type-actions">
                    <Button
                      variant="secondary"
                      isSmall
                      onClick={() => {
                        setIsAddingQuestion(false);
                        setSelectedQuestionType(null);
                      }}
                    >
                      {__("Cancel", "tutorpress")}
                    </Button>
                  </div>
                </div>
              )}

              {/* Questions List - Always visible, dropdown overlays when needed */}
              <div className="quiz-modal-questions-list">
                {!formState.title.trim() ? (
                  <div className="quiz-modal-no-questions">
                    <p>{__("Enter a quiz title to add questions.", "tutorpress")}</p>
                  </div>
                ) : questions.length === 0 && !isAddingQuestion ? (
                  <div className="quiz-modal-no-questions">
                    <p>{__("No questions added yet. Click + to add your first question.", "tutorpress")}</p>
                  </div>
                ) : (
                  questions.map((question, index) => (
                    <div
                      key={question.question_id}
                      className={`tutorpress-content-item quiz-modal-question-item ${
                        selectedQuestionIndex === index ? "is-selected" : ""
                      }`}
                      onClick={() => handleQuestionSelect(index)}
                    >
                      <div className="tutorpress-content-item-icon">
                        <span className="quiz-modal-question-number item-icon">{index + 1}</span>
                        <Icon icon="menu" className="drag-icon" />
                      </div>
                      <div className="quiz-modal-question-content">
                        <div className="quiz-modal-question-title">
                          {question.question_title || `${__("Question", "tutorpress")} ${index + 1}`}
                        </div>
                        <div className="quiz-modal-question-type-badge">
                          {getQuestionTypeDisplayName(question.question_type)}
                        </div>
                      </div>
                      <div className="tutorpress-content-item-actions">
                        <Button
                          icon="admin-page"
                          label={__("Duplicate Question", "tutorpress")}
                          isSmall
                          variant="tertiary"
                          onClick={(e: React.MouseEvent<HTMLButtonElement>) => {
                            e.stopPropagation();
                            // TODO: Implement duplication in future step
                            console.log("Duplicate question:", index);
                          }}
                        />
                        <Button
                          icon="trash"
                          label={__("Delete Question", "tutorpress")}
                          isSmall
                          variant="tertiary"
                          onClick={(e: React.MouseEvent<HTMLButtonElement>) => {
                            e.stopPropagation();
                            handleDeleteQuestion(index);
                          }}
                        />
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          </div>

          {/* Center Column: Contextual question form */}
          <div className="quiz-modal-center-column">
            <div className="quiz-modal-question-form">
              {selectedQuestionIndex !== null && questions[selectedQuestionIndex] ? (
                <div className="quiz-modal-question-form-content">
                  <h4>
                    {__("Question", "tutorpress")} {selectedQuestionIndex + 1}:{" "}
                    {getQuestionTypeDisplayName(questions[selectedQuestionIndex].question_type)}
                  </h4>

                  {/* Core Question Fields */}
                  <div className="quiz-modal-question-core-fields">
                    <TextControl
                      label={__("Question Title", "tutorpress")}
                      value={questions[selectedQuestionIndex].question_title}
                      onChange={(value) => handleQuestionFieldUpdate(selectedQuestionIndex, "question_title", value)}
                      placeholder={__("Enter your question...", "tutorpress")}
                      help={__("The main question text that students will see", "tutorpress")}
                      disabled={isSaving}
                    />

                    <TextareaControl
                      label={__("Question Description", "tutorpress")}
                      value={questions[selectedQuestionIndex].question_description}
                      onChange={(value) =>
                        handleQuestionFieldUpdate(selectedQuestionIndex, "question_description", value)
                      }
                      placeholder={__("Optional additional context or instructions...", "tutorpress")}
                      help={__("Additional context, instructions, or media for this question", "tutorpress")}
                      rows={3}
                      disabled={isSaving}
                    />

                    <NumberControl
                      label={__("Question Marks", "tutorpress")}
                      value={questions[selectedQuestionIndex].question_mark}
                      onChange={(value) =>
                        handleQuestionFieldUpdate(
                          selectedQuestionIndex,
                          "question_mark",
                          parseInt(value as string) || 1
                        )
                      }
                      min={1}
                      max={100}
                      step={1}
                      help={__("Points awarded for correct answer", "tutorpress")}
                      disabled={isSaving}
                    />
                  </div>

                  {/* Question Type-Specific Content Area */}
                  <div className="quiz-modal-question-type-content">
                    <h5>{__("Answer Options", "tutorpress")}</h5>
                    {renderQuestionTypeContent(questions[selectedQuestionIndex])}
                  </div>

                  {/* Answer Explanation */}
                  <div className="quiz-modal-question-explanation">
                    <TextareaControl
                      label={__("Answer Explanation", "tutorpress")}
                      value={questions[selectedQuestionIndex].answer_explanation}
                      onChange={(value) =>
                        handleQuestionFieldUpdate(selectedQuestionIndex, "answer_explanation", value)
                      }
                      placeholder={__("Explain why this is the correct answer...", "tutorpress")}
                      help={__("Optional explanation shown to students after they answer", "tutorpress")}
                      rows={3}
                      disabled={isSaving}
                    />
                  </div>
                </div>
              ) : (
                <div className="quiz-modal-empty-state">
                  <p>{__("Create or select a question to view details", "tutorpress")}</p>
                </div>
              )}
            </div>
          </div>

          {/* Right Column: Contextual question settings */}
          <div className="quiz-modal-right-column">
            <div className="quiz-modal-question-settings">
              <h4>{__("Question Settings", "tutorpress")}</h4>
              {selectedQuestionIndex !== null && questions[selectedQuestionIndex] ? (
                <div className="quiz-modal-question-settings-content">
                  {/* Universal Question Settings */}
                  <div className="quiz-modal-universal-settings">
                    <h5>{__("General Settings", "tutorpress")}</h5>

                    <ToggleControl
                      label={__("Answer Required", "tutorpress")}
                      checked={questions[selectedQuestionIndex].question_settings.answer_required}
                      onChange={(checked) =>
                        handleQuestionSettingUpdate(selectedQuestionIndex, "answer_required", checked)
                      }
                      help={__("Students must answer this question to proceed", "tutorpress")}
                      disabled={isSaving}
                    />

                    <ToggleControl
                      label={__("Show Question Mark", "tutorpress")}
                      checked={questions[selectedQuestionIndex].question_settings.show_question_mark}
                      onChange={(checked) =>
                        handleQuestionSettingUpdate(selectedQuestionIndex, "show_question_mark", checked)
                      }
                      help={__("Display the point value to students", "tutorpress")}
                      disabled={isSaving}
                    />

                    <ToggleControl
                      label={__("Randomize Question", "tutorpress")}
                      checked={questions[selectedQuestionIndex].question_settings.randomize_question}
                      onChange={(checked) =>
                        handleQuestionSettingUpdate(selectedQuestionIndex, "randomize_question", checked)
                      }
                      help={__("Randomize answer options for this question", "tutorpress")}
                      disabled={isSaving}
                    />
                  </div>

                  {/* Question Type-Specific Settings */}
                  <div className="quiz-modal-type-specific-settings">
                    <h5>{__("Type-Specific Settings", "tutorpress")}</h5>
                    {renderQuestionTypeSettings(questions[selectedQuestionIndex])}
                  </div>
                </div>
              ) : (
                <div className="quiz-modal-empty-state">
                  <p>{__("Select a question to view settings", "tutorpress")}</p>
                </div>
              )}
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
