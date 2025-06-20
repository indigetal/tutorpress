import React, { useState, useEffect, useRef } from "react";
import {
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
import { useQuizForm } from "../../hooks/quiz/useQuizForm";
import { curriculumStore } from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";
import { getQuestionComponent, hasQuestionComponent } from "./quiz/questions";
import { useQuestionValidation } from "../../hooks/quiz";
import { BaseModalLayout, BaseModalHeader } from "../common";
import type {
  TimeUnit,
  FeedbackMode,
  QuizQuestionType,
  QuizQuestion,
  QuizDetails,
  QuizForm,
  QuizQuestionSettings,
  getDefaultQuestionSettings,
  QuizQuestionOption,
  DataStatus,
} from "../../types/quiz";
import { QuestionDetailsTab } from "./quiz/QuestionDetailsTab";
import { SettingsTab } from "./quiz/SettingsTab";

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

// Add TinyMCE Editor Component
interface TinyMCEEditorProps {
  value: string;
  onChange: (value: string) => void;
  placeholder?: string;
  editorId: string;
  onCancel: () => void;
  onOk: () => void;
}

const TinyMCEEditor: React.FC<TinyMCEEditorProps> = ({ value, onChange, placeholder, editorId, onCancel, onOk }) => {
  const editorRef = useRef<HTMLTextAreaElement>(null);
  const [isInitialized, setIsInitialized] = useState(false);

  useEffect(() => {
    if (!editorRef.current || isInitialized) return;

    // Initialize TinyMCE editor using WordPress wp.editor
    const wpEditor = (window as any).wp?.editor;
    if (wpEditor) {
      // Force removal of any existing editor first
      try {
        wpEditor.remove(editorId);
      } catch (e) {
        // Ignore if editor doesn't exist
      }

      wpEditor.initialize(editorId, {
        tinymce: {
          wpautop: true,
          plugins:
            "charmap colorpicker hr lists paste tabfocus textcolor fullscreen wordpress wpautoresize wpeditimage wpemoji wpgallery wplink wptextpattern",
          toolbar1:
            "formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv",
          toolbar2: "strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help",
          // Critical WordPress editor settings
          wp_skip_init: false,
          add_unload_trigger: false,
          browser_spellcheck: true,
          keep_styles: false,
          end_container_on_empty_block: true,
          wpeditimage_disable_captions: false,
          wpeditimage_html5_captions: true,
          theme: "modern",
          skin: "lightgray",
          // Force height and visual appearance
          height: 200,
          resize: false,
          menubar: false,
          statusbar: false,
          // Content settings
          forced_root_block: "p",
          force_br_newlines: false,
          force_p_newlines: false,
          remove_trailing_brs: true,
          formats: {
            alignleft: [
              { selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: { textAlign: "left" } },
              { selector: "img,table,dl.wp-caption", classes: "alignleft" },
            ],
            aligncenter: [
              { selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: { textAlign: "center" } },
              { selector: "img,table,dl.wp-caption", classes: "aligncenter" },
            ],
            alignright: [
              { selector: "p,h1,h2,h3,h4,h5,h6,td,th,div,ul,ol,li", styles: { textAlign: "right" } },
              { selector: "img,table,dl.wp-caption", classes: "alignright" },
            ],
            strikethrough: { inline: "del" },
          },
          setup: (editor: any) => {
            // Set initial content when editor is ready
            editor.on("init", () => {
              editor.setContent(value || "");

              // Force Visual mode after initialization with a longer delay
              setTimeout(() => {
                forceVisualMode(editorId);
              }, 200);
            });

            // Handle content changes
            editor.on("change keyup paste input SetContent", () => {
              const content = editor.getContent();
              onChange(content);
            });

            // Handle undo/redo events
            editor.on("Undo Redo", () => {
              const content = editor.getContent();
              onChange(content);
            });

            // Handle editor focus to ensure Visual mode
            editor.on("focus", () => {
              setTimeout(() => {
                forceVisualMode(editorId);
              }, 50);
            });
          },
        },
        quicktags: {
          buttons: "strong,em,link,block,del,ins,img,ul,ol,li,code,more,close",
        },
        mediaButtons: true,
      });

      // Set up tab click handlers after initialization
      setTimeout(() => {
        setupTabHandlers(editorId);
      }, 300);

      setIsInitialized(true);
    }

    return () => {
      // Cleanup editor on unmount
      const wpEditor = (window as any).wp?.editor;
      if (wpEditor && isInitialized) {
        try {
          wpEditor.remove(editorId);
        } catch (e) {
          // Ignore cleanup errors
        }
      }
    };
  }, [editorId, isInitialized]);

  // Function to force Visual mode
  const forceVisualMode = (editorId: string) => {
    const editorWrap = document.querySelector(`#wp-${editorId}-wrap`);
    const textTab = document.querySelector(`#${editorId}-html`);
    const visualTab = document.querySelector(`#${editorId}-tmce`);
    const textarea = document.querySelector(`#${editorId}`) as HTMLTextAreaElement;

    if (editorWrap && textTab && visualTab) {
      // Force Visual tab to be active
      textTab.classList.remove("active");
      visualTab.classList.add("active");

      // Force container to show Visual mode
      editorWrap.classList.remove("html-active");
      editorWrap.classList.add("tmce-active");

      // Hide textarea, show TinyMCE
      if (textarea) {
        textarea.style.display = "none";
      }

      const mceContainer = editorWrap.querySelector(".mce-tinymce");
      if (mceContainer) {
        (mceContainer as HTMLElement).style.display = "block";
      }
    }
  };

  // Function to set up tab click handlers
  const setupTabHandlers = (editorId: string) => {
    const textTab = document.querySelector(`#${editorId}-html`) as HTMLElement;
    const visualTab = document.querySelector(`#${editorId}-tmce`) as HTMLElement;

    if (textTab && visualTab) {
      // Allow Visual tab to be clicked but ensure it stays in Visual mode
      visualTab.onclick = (e) => {
        // Don't prevent default - allow normal tab switching behavior
        setTimeout(() => {
          forceVisualMode(editorId);
        }, 10);
      };

      // Prevent Text/Code tab from working - redirect to Visual mode
      textTab.onclick = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setTimeout(() => {
          forceVisualMode(editorId);
        }, 10);
        return false;
      };
    }
  };

  // Update content when value prop changes externally
  useEffect(() => {
    if (isInitialized) {
      const tinymce = (window as any).tinymce;
      if (tinymce) {
        const editor = tinymce.get(editorId);
        if (editor && editor.getContent() !== value) {
          editor.setContent(value || "");
          // Force Visual mode after content update
          setTimeout(() => {
            forceVisualMode(editorId);
          }, 50);
        }
      }
    }
  }, [value, editorId, isInitialized]);

  return (
    <div className="quiz-modal-wp-editor">
      <div className="quiz-modal-tinymce-editor">
        <textarea
          ref={editorRef}
          id={editorId}
          name={editorId}
          defaultValue={value}
          placeholder={placeholder}
          style={{ width: "100%", height: "200px" }}
        />
      </div>
      <div className="quiz-modal-editor-actions">
        <Button variant="secondary" isSmall onClick={onCancel}>
          {__("Cancel", "tutorpress")}
        </Button>
        <Button variant="primary" isSmall onClick={onOk}>
          {__("OK", "tutorpress")}
        </Button>
      </div>
    </div>
  );
};

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
  const [deletedQuestionIds, setDeletedQuestionIds] = useState<number[]>([]);
  const [deletedAnswerIds, setDeletedAnswerIds] = useState<number[]>([]);

  // Editor visibility state
  const [showDescriptionEditor, setShowDescriptionEditor] = useState(false);
  const [showExplanationEditor, setShowExplanationEditor] = useState(false);

  // Validation state - Step 3.6.8
  const [showValidationErrors, setShowValidationErrors] = useState(false);

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
    resetToDefaults,
    initializeWithData,
    validateEntireForm,
    checkCoursePreviewAddon,
    getFormData,
    isValid,
    isDirty,
    errors,
  } = useQuizForm();

  // Get quiz duplication state from curriculum store
  const quizDuplicationState = useSelect((select) => {
    return (select(curriculumStore) as any).getQuizDuplicationState();
  }, []);

  const { setQuizDuplicationState, setTopics } = useDispatch(curriculumStore) as any;
  const { createNotice } = useDispatch(noticesStore);

  // Store state and dispatch
  const { saveQuiz, getQuizDetails, setQuizState } = useDispatch(curriculumStore) as any;
  const { isQuizSaving, hasQuizError, getQuizError, getLastSavedQuizId } = useSelect(
    (select) => ({
      isQuizSaving: select(curriculumStore).isQuizSaving(),
      hasQuizError: select(curriculumStore).hasQuizError(),
      getQuizError: select(curriculumStore).getQuizError(),
      getLastSavedQuizId: select(curriculumStore).getLastSavedQuizId(),
    }),
    []
  );

  // Use centralized validation hook
  const { validateAllQuestions: validateAllQuestionsHook } = useQuestionValidation();

  /**
   * Handle tracking deleted answer IDs from question components
   */
  const handleDeletedAnswerId = (answerId: number) => {
    if (answerId > 0) {
      setDeletedAnswerIds((prev) => [...prev, answerId]);
    }
  };

  /**
   * Load existing quiz data when editing
   */
  const loadExistingQuizData = async (id: number) => {
    setIsLoading(true);
    setLoadError(null);

    try {
      // Use the curriculum store to get quiz details
      await getQuizDetails(id);

      // The quiz data will be available through store selectors after successful load
      // For now, we'll use a direct API call as a fallback until the store selectors are properly integrated
      const response = (await (window as any).wp.apiFetch({
        path: `/tutorpress/v1/quizzes/${id}`,
        method: "GET",
      })) as any;

      if (response.success && response.data) {
        const quizData = response.data;
        setQuizData(quizData);

        // Initialize form with loaded data (clean approach - no dirty state marking)
        initializeWithData(quizData);

        // Load questions data - Step 3.2
        if (quizData.questions && Array.isArray(quizData.questions)) {
          const sortedQuestions = quizData.questions.sort(
            (a: QuizQuestion, b: QuizQuestion) => a.question_order - b.question_order
          );

          // Ensure all loaded questions have _data_status set
          const questionsWithStatus = sortedQuestions.map((question: QuizQuestion) => ({
            ...question,
            _data_status: question._data_status || "no_change",
            question_answers: question.question_answers.map((answer: QuizQuestionOption) => ({
              ...answer,
              _data_status: answer._data_status || "no_change",
            })),
          }));

          setQuestions(questionsWithStatus);
          console.log(`TutorPress: Loaded quiz ${id} with ${questionsWithStatus.length} questions`);
        } else {
          setQuestions([]);
        }

        // Reset question selection state
        setSelectedQuestionIndex(null);
        setEditingQuestionId(null);
        setIsAddingQuestion(false);
        setSelectedQuestionType(null);
        setDeletedQuestionIds([]);
        setDeletedAnswerIds([]);

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
      // Reset form to clean defaults for new quiz
      resetToDefaults();
      // Reset questions state for new quiz - Step 3.2
      setQuestions([]);
      setSelectedQuestionIndex(null);
      setEditingQuestionId(null);
      setIsAddingQuestion(false);
      setSelectedQuestionType(null);
      setDeletedQuestionIds([]);
      setDeletedAnswerIds([]);
    }
  }, [isOpen, quizId, resetToDefaults]);

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
        setDeletedQuestionIds([]);
        setDeletedAnswerIds([]);
      }
    }
  }, [isOpen, checkCoursePreviewAddon, quizId]);

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
    setDeletedQuestionIds([]);
    setDeletedAnswerIds([]);
    // Reset validation state - Step 3.6.8
    setShowValidationErrors(false);
    onClose();
  };

  const handleSave = async () => {
    if (!validateEntireForm()) {
      setSaveError(__("Please fix the form errors before saving.", "tutorpress"));
      return;
    }

    // Validate questions - Step 3.6.8
    const questionValidation = validateAllQuestions();
    if (!questionValidation.isValid) {
      const errorMessage = questionValidation.errors.join(" ");
      setSaveError(errorMessage);
      setShowValidationErrors(true); // Show validation errors in UI
      return;
    }

    // Verify Tutor LMS compatibility - Step 3.6.8
    if (!verifyTutorLMSCompatibility(questions)) {
      setSaveError(
        __("Data format is not compatible with Tutor LMS. Please check your question configuration.", "tutorpress")
      );
      setShowValidationErrors(true); // Show validation errors in UI
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

      // Add deleted IDs to form data
      formData.deleted_question_ids = deletedQuestionIds;
      formData.deleted_answer_ids = deletedAnswerIds;

      // Add quiz ID for updates
      if (quizId) {
        formData.ID = quizId; // Add the quiz ID to make it an update operation
        console.log(`TutorPress: Updating quiz ${quizId} with ${questions.length} questions`);
      } else {
        console.log(`TutorPress: Creating new quiz with ${questions.length} questions`);
      }

      // Use the curriculum store instead of direct quiz service
      await saveQuiz(formData, courseId, topicId);

      // The success/error handling is now done by the store state
      // Show success message briefly
      setSaveSuccess(true);

      if (quizId) {
        // Show success notice
        createNotice("success", __("Quiz updated successfully.", "tutorpress"), {
          type: "snackbar",
        });
      } else {
        // Show success notice
        createNotice("success", __("Quiz created successfully.", "tutorpress"), {
          type: "snackbar",
        });
      }

      // Close modal after successful save (following topics pattern)
      setTimeout(() => {
        handleClose();
      }, 1000);
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

  /**
   * Render the question form for the center column
   */
  const renderQuestionForm = (): JSX.Element => {
    if (selectedQuestionIndex !== null && questions[selectedQuestionIndex]) {
      return (
        <div className="quiz-modal-question-form-content">
          {/* Core Question Fields */}
          <div className="quiz-modal-question-core-fields">
            <TextControl
              label={__("Question Title", "tutorpress")}
              value={questions[selectedQuestionIndex].question_title}
              onChange={(value) => handleQuestionFieldUpdate(selectedQuestionIndex, "question_title", value)}
              placeholder={__("Enter your question...", "tutorpress")}
              disabled={isSaving}
            />

            <div className="quiz-modal-description-field">
              {!showDescriptionEditor && (
                <div
                  className="quiz-modal-description-label"
                  onClick={() => setShowDescriptionEditor(!showDescriptionEditor)}
                >
                  {questions[selectedQuestionIndex].question_description.trim() ? (
                    <div
                      className="quiz-modal-saved-content"
                      dangerouslySetInnerHTML={{
                        __html: questions[selectedQuestionIndex].question_description,
                      }}
                    />
                  ) : (
                    __("Description (optional)", "tutorpress")
                  )}
                </div>
              )}
              {showDescriptionEditor && (
                <TinyMCEEditor
                  value={questions[selectedQuestionIndex].question_description}
                  onChange={(value) => handleQuestionFieldUpdate(selectedQuestionIndex, "question_description", value)}
                  editorId="question_description"
                  onCancel={() => setShowDescriptionEditor(false)}
                  onOk={() => setShowDescriptionEditor(false)}
                />
              )}
            </div>
          </div>

          {/* Question Type-Specific Content Area */}
          <div className="quiz-modal-question-type-content">
            {renderQuestionTypeContent(questions[selectedQuestionIndex])}
          </div>

          {/* Answer Explanation */}
          <div className="quiz-modal-question-explanation">
            {!showExplanationEditor && (
              <div
                className="quiz-modal-explanation-label"
                onClick={() => setShowExplanationEditor(!showExplanationEditor)}
              >
                {questions[selectedQuestionIndex].answer_explanation.trim() ? (
                  <div>
                    <label>{__("Answer Explanation", "tutorpress")}</label>
                    <div
                      className="quiz-modal-saved-content"
                      dangerouslySetInnerHTML={{ __html: questions[selectedQuestionIndex].answer_explanation }}
                    />
                  </div>
                ) : (
                  __("Write answer explanation", "tutorpress")
                )}
              </div>
            )}
            {showExplanationEditor && (
              <div>
                <label>{__("Answer Explanation", "tutorpress")}</label>
                <TinyMCEEditor
                  value={questions[selectedQuestionIndex].answer_explanation}
                  onChange={(value) => handleQuestionFieldUpdate(selectedQuestionIndex, "answer_explanation", value)}
                  editorId="answer_explanation"
                  onCancel={() => setShowExplanationEditor(false)}
                  onOk={() => setShowExplanationEditor(false)}
                />
              </div>
            )}
          </div>
        </div>
      );
    } else {
      return (
        <div className="quiz-modal-empty-state tpress-empty-state-container">
          <p>{__("Create or select a question to view details", "tutorpress")}</p>
        </div>
      );
    }
  };

  /**
   * Render the question settings for the right column
   */
  const renderQuestionSettings = (): JSX.Element => {
    if (selectedQuestionIndex !== null && questions[selectedQuestionIndex]) {
      return (
        <div className="quiz-modal-question-settings-content">
          {/* Question Type Display */}
          <div className="quiz-modal-question-type-display">
            <label>
              {__("Question Type: ", "tutorpress")}
              <span className="quiz-modal-question-type-value">
                {getQuestionTypeDisplayName(questions[selectedQuestionIndex].question_type)}
              </span>
            </label>
          </div>

          {/* Type-Specific Settings First (Multiple Correct Answer, etc.) */}
          {renderQuestionTypeSettings(questions[selectedQuestionIndex])}

          {/* Answer Required */}
          <ToggleControl
            label={__("Answer Required", "tutorpress")}
            checked={questions[selectedQuestionIndex].question_settings.answer_required}
            onChange={(checked) => handleQuestionSettingUpdate(selectedQuestionIndex, "answer_required", checked)}
            disabled={isSaving}
          />

          {/* Points For This Question */}
          <NumberControl
            label={__("Points For This Question", "tutorpress")}
            value={questions[selectedQuestionIndex].question_mark}
            onChange={(value) =>
              handleQuestionFieldUpdate(selectedQuestionIndex, "question_mark", parseInt(value as string) || 1)
            }
            min={1}
            max={100}
            step={1}
            type="number"
            disabled={isSaving}
          />

          {/* Display Points */}
          <ToggleControl
            label={__("Display Points", "tutorpress")}
            checked={questions[selectedQuestionIndex].question_settings.show_question_mark}
            onChange={(checked) => handleQuestionSettingUpdate(selectedQuestionIndex, "show_question_mark", checked)}
            disabled={isSaving}
          />
        </div>
      );
    } else {
      return (
        <div className="quiz-modal-empty-state tpress-empty-state-container">
          <p>{__("Select a question to view settings", "tutorpress")}</p>
        </div>
      );
    }
  };

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
        // Convert to our option format and filter out single_choice and image_matching
        const options: QuestionTypeOption[] = Object.entries(questionTypesData)
          .filter(([value]) => value !== "single_choice" && value !== "image_matching")
          .map(([value, config]: [string, any]) => ({
            label: config.name || value.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase()),
            value: value as QuizQuestionType,
            is_pro: config.is_pro || false,
          }))
          // Sort according to the specified order
          .sort((a, b) => {
            const order = [
              "true_false",
              "multiple_choice",
              "open_ended",
              "fill_in_the_blank",
              "short_answer",
              "matching",
              "image_answering",
              "ordering",
            ];
            const aIndex = order.indexOf(a.value);
            const bIndex = order.indexOf(b.value);
            return (aIndex === -1 ? 999 : aIndex) - (bIndex === -1 ? 999 : bIndex);
          });

        setQuestionTypes(options);
        console.log("Successfully loaded question types:", options);
      } else {
        // Fallback to static question types based on correct Tutor LMS order
        // Excludes single_choice and image_matching from dropdown as requested
        console.warn("Using fallback question types - Tutor LMS question types not available");
        const fallbackTypes: QuestionTypeOption[] = [
          { label: __("True/False", "tutorpress"), value: "true_false", is_pro: false },
          { label: __("Multiple Choice", "tutorpress"), value: "multiple_choice", is_pro: false },
          { label: __("Open Ended/Essay", "tutorpress"), value: "open_ended", is_pro: false },
          { label: __("Fill In The Blanks", "tutorpress"), value: "fill_in_the_blank", is_pro: false },
          { label: __("Short Answer", "tutorpress"), value: "short_answer", is_pro: true },
          { label: __("Matching", "tutorpress"), value: "matching", is_pro: true },
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
    // Reset editor visibility when selecting a new question
    setShowDescriptionEditor(false);
    setShowExplanationEditor(false);
  };

  /**
   * Handle creating new question after type selection - Step 3.2
   */
  const handleCreateNewQuestion = (questionType?: QuizQuestionType) => {
    const typeToUse = questionType || selectedQuestionType;
    if (!typeToUse || !formState.title.trim()) {
      return;
    }

    // Generate unique temporary ID (negative numbers to distinguish from real IDs)
    const tempQuestionId = -(Date.now() + Math.floor(Math.random() * 1000));

    // Create a new question object
    const newQuestion: QuizQuestion = {
      question_id: tempQuestionId,
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

    // Reset editor visibility for new question
    setShowDescriptionEditor(false);
    setShowExplanationEditor(false);

    // Reset validation state for new question - Step 3.6.8
    setShowValidationErrors(false);
  };

  /**
   * Handle deleting a question - Step 3.2
   */
  const handleDeleteQuestion = (questionIndex: number) => {
    if (questionIndex < 0 || questionIndex >= questions.length) {
      return;
    }

    const questionToDelete = questions[questionIndex];

    // Track deleted IDs for existing questions (those with real database IDs)
    if (questionToDelete.question_id > 0) {
      setDeletedQuestionIds((prev) => [...prev, questionToDelete.question_id]);

      // Track deleted answer IDs
      const answerIdsToDelete = questionToDelete.question_answers
        .filter((answer) => answer.answer_id > 0)
        .map((answer) => answer.answer_id);

      if (answerIdsToDelete.length > 0) {
        setDeletedAnswerIds((prev) => [...prev, ...answerIdsToDelete]);
      }
    }

    const updatedQuestions = questions.filter((_, index) => index !== questionIndex);

    // Update question orders
    const reorderedQuestions = updatedQuestions.map((question, index) => ({
      ...question,
      question_order: index + 1,
      _data_status: question._data_status === "new" ? ("new" as DataStatus) : ("update" as DataStatus),
    }));

    setQuestions(reorderedQuestions);

    // Adjust selection
    if (selectedQuestionIndex === questionIndex) {
      setSelectedQuestionIndex(null);
      setEditingQuestionId(null);
    } else if (selectedQuestionIndex !== null && selectedQuestionIndex > questionIndex) {
      setSelectedQuestionIndex(selectedQuestionIndex - 1);
    }
  };

  /**
   * Handle reordering questions - Step 9
   */
  const handleQuestionReorder = (items: Array<{ id: number; [key: string]: any }>) => {
    // Update question orders based on new positions
    const updatedQuestions = items
      .map((item, index) => {
        const question = questions.find((q) => q.question_id === item.id);
        if (!question) return null;

        return {
          ...question,
          question_order: index + 1,
          _data_status: question._data_status === "new" ? ("new" as DataStatus) : ("update" as DataStatus),
        };
      })
      .filter(Boolean) as QuizQuestion[];

    setQuestions(updatedQuestions);

    // Update selected index if needed
    if (selectedQuestionIndex !== null) {
      const selectedQuestion = questions[selectedQuestionIndex];
      if (selectedQuestion) {
        const newIndex = updatedQuestions.findIndex((q) => q.question_id === selectedQuestion.question_id);
        if (newIndex !== -1) {
          setSelectedQuestionIndex(newIndex);
        }
      }
    }
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
    const currentQuestion = updatedQuestions[questionIndex];

    const preservedStatus = currentQuestion._data_status === "new" ? "new" : "update";

    updatedQuestions[questionIndex] = {
      ...currentQuestion,
      [field]: value,
      _data_status: preservedStatus,
    };

    setQuestions(updatedQuestions);
  };

  /**
   * Render question type-specific content - Step 3.3
   */
  const renderQuestionTypeContent = (question: QuizQuestion): JSX.Element => {
    const questionIndex = questions.findIndex((q) => q.question_id === question.question_id);

    // Use the question component registry
    const QuestionComponent = getQuestionComponent(question.question_type);

    if (QuestionComponent) {
      return (
        <QuestionComponent
          question={question}
          questionIndex={questionIndex}
          onQuestionUpdate={handleQuestionFieldUpdate}
          showValidationErrors={showValidationErrors}
          isSaving={isSaving}
          onDeletedAnswerId={handleDeletedAnswerId}
        />
      );
    }

    // Fallback for question types not yet implemented
    switch (question.question_type) {
      case "open_ended":
      case "short_answer":
      case "matching":
      case "image_matching":
      case "image_answering":
      case "ordering":
      case "h5p":
        return (
          <div className="quiz-modal-question-placeholder">
            <p>{__("This question type will be implemented in future steps", "tutorpress")}</p>
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
    const currentQuestion = updatedQuestions[questionIndex];

    // Handle "Multiple Correct Answers" toggle transition
    if (setting === "has_multiple_correct_answer") {
      const isChangingToSingle = currentQuestion.question_settings.has_multiple_correct_answer && !value;

      if (isChangingToSingle) {
        // When switching from multiple to single mode, clear all correct answers
        const correctAnswers = currentQuestion.question_answers.filter((answer) => answer.is_correct === "1");

        if (correctAnswers.length > 0) {
          // Clear all correct answers to match Tutor LMS behavior
          const updatedAnswers = currentQuestion.question_answers.map((answer: QuizQuestionOption) => ({
            ...answer,
            is_correct: "0" as "0" | "1",
            _data_status: (answer._data_status === "new" ? "new" : "update") as DataStatus,
          }));

          updatedQuestions[questionIndex] = {
            ...currentQuestion,
            question_answers: updatedAnswers,
            question_settings: {
              ...currentQuestion.question_settings,
              [setting]: value,
            },
            _data_status: currentQuestion._data_status === "new" ? "new" : "update",
          };

          setQuestions(updatedQuestions);
          return;
        }
      }
    }

    // Standard setting update for all other cases
    updatedQuestions[questionIndex] = {
      ...currentQuestion,
      question_settings: {
        ...currentQuestion.question_settings,
        [setting]: value,
      },
      _data_status: currentQuestion._data_status === "new" ? "new" : "update",
    };

    setQuestions(updatedQuestions);
  };

  /**
   * Validate all questions before save - Step 3.6.8
   */
  const validateAllQuestions = (): { isValid: boolean; errors: string[] } => {
    const result = validateAllQuestionsHook(questions);
    return {
      isValid: result.isValid,
      errors: result.errors,
    };
  };

  /**
   * Verify data format compatibility with Tutor LMS - Step 3.6.8
   */
  const verifyTutorLMSCompatibility = (questions: QuizQuestion[]): boolean => {
    try {
      questions.forEach((question) => {
        // Verify question structure
        if (!question.question_type || !question.question_title) {
          throw new Error("Invalid question structure");
        }

        // Verify answer structure for multiple choice
        if (question.question_type === "multiple_choice") {
          question.question_answers.forEach((answer) => {
            if (!answer.answer_title || !["0", "1"].includes(answer.is_correct)) {
              throw new Error("Invalid answer structure for multiple choice");
            }
            if (typeof answer.answer_order !== "number") {
              throw new Error("Invalid answer order");
            }
          });

          // Verify settings structure
          if (typeof question.question_settings.has_multiple_correct_answer !== "boolean") {
            throw new Error("Invalid multiple correct answer setting");
          }
        }
      });

      console.log("Data format verification passed - compatible with Tutor LMS");
      return true;
    } catch (error) {
      console.error("Data format verification failed:", error);
      return false;
    }
  };

  /**
   * Render question type-specific settings - Step 3.3
   */
  const renderQuestionTypeSettings = (question: QuizQuestion): JSX.Element => {
    // This will be expanded in Steps 3.5-3.9 for each question type
    switch (question.question_type) {
      case "multiple_choice":
        return (
          <ToggleControl
            label={__("Multiple Correct Answers", "tutorpress")}
            checked={question.question_settings.has_multiple_correct_answer}
            onChange={(checked) =>
              handleQuestionSettingUpdate(questions.indexOf(question), "has_multiple_correct_answer", checked)
            }
            disabled={isSaving}
          />
        );
      case "matching":
        return (
          <ToggleControl
            label={__("Image Matching", "tutorpress")}
            checked={question.question_settings.is_image_matching}
            onChange={(checked) =>
              handleQuestionSettingUpdate(questions.indexOf(question), "is_image_matching", checked)
            }
            disabled={isSaving}
          />
        );
      case "true_false":
      case "single_choice":
      case "fill_in_the_blank":
      case "open_ended":
      case "short_answer":
      case "image_matching":
      case "ordering":
      case "image_answering": // Image Answering questions have no additional settings
      case "h5p":
      default:
        // Return empty fragment for question types without additional settings
        return <></>;
    }
  };

  const modalHeader = (
    <BaseModalHeader
      title={quizId ? __("Edit Quiz", "tutorpress") : __("Create Quiz", "tutorpress")}
      isValid={isValid}
      isDirty={isDirty}
      isSaving={isSaving}
      saveSuccess={saveSuccess}
      primaryButtonText={quizId ? __("Update Quiz", "tutorpress") : __("Save Quiz", "tutorpress")}
      savingButtonText={quizId ? __("Updating...", "tutorpress") : __("Saving...", "tutorpress")}
      successButtonText={__("Saved!", "tutorpress")}
      onSave={handleSave}
      onClose={handleClose}
      className="quiz-modal"
    />
  );

  const handleRetry = () => {
    if (quizId) {
      loadExistingQuizData(quizId);
    }
  };

  return (
    <BaseModalLayout
      isOpen={isOpen}
      onClose={handleClose}
      isDirty={isDirty}
      className="quiz-modal"
      isLoading={isLoading}
      loadingMessage={__("Loading quiz data...", "tutorpress")}
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
                  formTitle={formState.title}
                  formDescription={formState.description}
                  topicId={topicId}
                  questions={questions}
                  selectedQuestionIndex={selectedQuestionIndex}
                  isAddingQuestion={isAddingQuestion}
                  selectedQuestionType={selectedQuestionType}
                  questionTypes={questionTypes}
                  loadingQuestionTypes={loadingQuestionTypes}
                  isSaving={isSaving}
                  saveSuccess={saveSuccess}
                  saveError={saveError}
                  onTitleChange={updateTitle}
                  onDescriptionChange={updateDescription}
                  onAddQuestion={handleAddQuestion}
                  onQuestionSelect={handleQuestionSelect}
                  onQuestionTypeSelect={handleQuestionTypeSelect}
                  onDeleteQuestion={handleDeleteQuestion}
                  onQuestionReorder={handleQuestionReorder}
                  onCancelAddQuestion={() => setIsAddingQuestion(false)}
                  onSaveErrorDismiss={() => setSaveError(null)}
                  getQuestionTypeDisplayName={getQuestionTypeDisplayName}
                  renderQuestionForm={() => renderQuestionForm()}
                  renderQuestionSettings={() => renderQuestionSettings()}
                />
              );
            case "settings":
              return (
                <SettingsTab
                  timeValue={formState.settings.time_limit.time_value}
                  timeType={formState.settings.time_limit.time_type}
                  hideQuizTimeDisplay={formState.settings.hide_quiz_time_display}
                  feedbackMode={formState.settings.feedback_mode}
                  passingGrade={formState.settings.passing_grade}
                  maxQuestionsForAnswer={formState.settings.max_questions_for_answer}
                  afterXDaysOfEnroll={formState.settings.content_drip_settings.after_xdays_of_enroll}
                  quizAutoStart={formState.settings.quiz_auto_start}
                  questionLayoutView={formState.settings.question_layout_view}
                  questionsOrder={formState.settings.questions_order}
                  hideQuestionNumberOverview={formState.settings.hide_question_number_overview}
                  shortAnswerCharactersLimit={formState.settings.short_answer_characters_limit}
                  openEndedAnswerCharactersLimit={formState.settings.open_ended_answer_characters_limit}
                  attemptsAllowed={formState.settings.attempts_allowed}
                  coursePreviewAddonAvailable={coursePreviewAddon.available}
                  isSaving={isSaving}
                  saveSuccess={saveSuccess}
                  saveError={saveError}
                  onTimeChange={updateTimeLimit}
                  onSettingChange={updateSettings}
                  onContentDripChange={updateContentDrip}
                  onSaveErrorDismiss={() => setSaveError(null)}
                  errors={errors}
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
