import React, { useState, useEffect, useRef } from "react";
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
  QuizQuestionOption,
  DataStatus,
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
    // Reset editor visibility when selecting a new question
    setShowDescriptionEditor(false);
    setShowExplanationEditor(false);
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
    const currentQuestion = updatedQuestions[questionIndex];

    const preservedStatus = currentQuestion._data_status === "new" ? "new" : "update";

    updatedQuestions[questionIndex] = {
      ...currentQuestion,
      [field]: value,
      _data_status: preservedStatus,
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
        return renderTrueFalseContent(question);
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
   * Render True/False question content - Step 3.5
   */
  const renderTrueFalseContent = (question: QuizQuestion): JSX.Element => {
    // Ensure we have True/False answer options
    const trueFalseAnswers = ensureTrueFalseAnswers(question);
    const trueAnswer = trueFalseAnswers.find((answer: QuizQuestionOption) => answer.answer_title === "True");
    const falseAnswer = trueFalseAnswers.find((answer: QuizQuestionOption) => answer.answer_title === "False");
    const correctAnswerId = trueFalseAnswers.find((answer: QuizQuestionOption) => answer.is_correct === "1")?.answer_id;

    return (
      <div className="quiz-modal-true-false-content">
        <div className="quiz-modal-true-false-options">
          <div
            className={`quiz-modal-true-false-option ${correctAnswerId === trueAnswer?.answer_id ? "is-correct" : ""}`}
            onClick={() => handleTrueFalseCorrectAnswer(question, trueAnswer?.answer_id || 0)}
          >
            {correctAnswerId === trueAnswer?.answer_id && <span className="quiz-modal-correct-indicator">✓</span>}
            <span className="quiz-modal-answer-text">{__("True", "tutorpress")}</span>
          </div>

          <div
            className={`quiz-modal-true-false-option ${correctAnswerId === falseAnswer?.answer_id ? "is-correct" : ""}`}
            onClick={() => handleTrueFalseCorrectAnswer(question, falseAnswer?.answer_id || 0)}
          >
            {correctAnswerId === falseAnswer?.answer_id && <span className="quiz-modal-correct-indicator">✓</span>}
            <span className="quiz-modal-answer-text">{__("False", "tutorpress")}</span>
          </div>
        </div>

        {!correctAnswerId && (
          <div className="quiz-modal-validation-error">
            <p>{__("Please select the correct answer (True or False)", "tutorpress")}</p>
          </div>
        )}
      </div>
    );
  };

  /**
   * Ensure True/False answers exist for question - Step 3.5
   */
  const ensureTrueFalseAnswers = (question: QuizQuestion): QuizQuestionOption[] => {
    let answers = [...question.question_answers];

    // Check if True answer exists
    let trueAnswer = answers.find((answer: QuizQuestionOption) => answer.answer_title === "True");
    if (!trueAnswer) {
      trueAnswer = {
        answer_id: -(Date.now() + Math.floor(Math.random() * 1000) + 1),
        belongs_question_id: question.question_id,
        belongs_question_type: question.question_type,
        answer_title: "True",
        is_correct: "0",
        image_id: 0,
        image_url: "",
        answer_two_gap_match: "",
        answer_view_format: "",
        answer_order: 1,
        _data_status: "new",
      };
      answers.push(trueAnswer);
    }

    // Check if False answer exists
    let falseAnswer = answers.find((answer: QuizQuestionOption) => answer.answer_title === "False");
    if (!falseAnswer) {
      falseAnswer = {
        answer_id: -(Date.now() + Math.floor(Math.random() * 1000) + 2),
        belongs_question_id: question.question_id,
        belongs_question_type: question.question_type,
        answer_title: "False",
        is_correct: "0",
        image_id: 0,
        image_url: "",
        answer_two_gap_match: "",
        answer_view_format: "",
        answer_order: 2,
        _data_status: "new",
      };
      answers.push(falseAnswer);
    }

    // Update question if answers were added
    if (answers.length !== question.question_answers.length) {
      const questionIndex = questions.findIndex((q) => q.question_id === question.question_id);
      if (questionIndex !== -1) {
        const updatedQuestions = [...questions];
        const currentQuestion = updatedQuestions[questionIndex];

        const preservedStatus = currentQuestion._data_status === "new" ? "new" : "update";

        updatedQuestions[questionIndex] = {
          ...currentQuestion,
          question_answers: answers,
          _data_status: preservedStatus,
        };

        setQuestions(updatedQuestions);
      }
    }

    return answers;
  };

  /**
   * Handle True/False correct answer selection - Step 3.5
   */
  const handleTrueFalseCorrectAnswer = (question: QuizQuestion, selectedAnswerId: number) => {
    const questionIndex = questions.findIndex((q) => q.question_id === question.question_id);
    if (questionIndex === -1) return;

    const updatedQuestions = [...questions];
    const currentQuestion = updatedQuestions[questionIndex];

    const updatedAnswers = currentQuestion.question_answers.map((answer: QuizQuestionOption) => ({
      ...answer,
      is_correct: (answer.answer_id === selectedAnswerId ? "1" : "0") as "0" | "1",
      _data_status: (answer._data_status === "new" ? "new" : "update") as DataStatus,
    }));

    const preservedStatus = currentQuestion._data_status === "new" ? "new" : "update";

    updatedQuestions[questionIndex] = {
      ...currentQuestion,
      question_answers: updatedAnswers,
      _data_status: preservedStatus,
    };

    setQuestions(updatedQuestions);
    console.log(`Set correct answer for True/False question ${question.question_id}:`, selectedAnswerId);
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
    updatedQuestions[questionIndex] = {
      ...currentQuestion,
      question_settings: {
        ...currentQuestion.question_settings,
        [setting]: value,
      },
      _data_status: currentQuestion._data_status === "new" ? "new" : "update",
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
          <ToggleControl
            label={__("Multiple Correct Answers", "tutorpress")}
            checked={question.question_settings.has_multiple_correct_answer}
            onChange={(checked) =>
              handleQuestionSettingUpdate(questions.indexOf(question), "has_multiple_correct_answer", checked)
            }
            disabled={isSaving}
          />
        );
      case "image_matching":
      case "image_answering":
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
      case "matching":
      case "ordering":
      case "h5p":
      default:
        // Return empty fragment for question types without additional settings
        return <></>;
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

      // Use the curriculum store to get quiz details
      await getQuizDetails(id);

      // The quiz data will be available through store selectors after successful load
      // For now, we'll use a direct API call as a fallback until the store selectors are properly integrated
      const response = (await (window as any).wp.apiFetch({
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
          console.log("Loaded", questionsWithStatus.length, "questions for quiz", id);
          console.log("Loaded questions with _data_status:", questionsWithStatus);
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
      // Reset questions state for new quiz - Step 3.2
      setQuestions([]);
      setSelectedQuestionIndex(null);
      setEditingQuestionId(null);
      setIsAddingQuestion(false);
      setSelectedQuestionType(null);
      setDeletedQuestionIds([]);
      setDeletedAnswerIds([]);
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
        setDeletedQuestionIds([]);
        setDeletedAnswerIds([]);
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
    setDeletedQuestionIds([]);
    setDeletedAnswerIds([]);
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

      // Add deleted IDs to form data
      formData.deleted_question_ids = deletedQuestionIds;
      formData.deleted_answer_ids = deletedAnswerIds;

      console.log("Saving quiz with", questions.length, "questions");

      // Add quiz ID for updates
      if (quizId) {
        console.log("Updating existing quiz:", quizId);
        formData.ID = quizId; // Add the quiz ID to make it an update operation
      } else {
        console.log("Creating new quiz");
      }

      console.log("Saving quiz with data:", formData);
      console.log("Course ID:", courseId, "Topic ID:", topicId, "Quiz ID:", quizId);

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
                          onChange={(value) =>
                            handleQuestionFieldUpdate(selectedQuestionIndex, "question_description", value)
                          }
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
                          onChange={(value) =>
                            handleQuestionFieldUpdate(selectedQuestionIndex, "answer_explanation", value)
                          }
                          editorId="answer_explanation"
                          onCancel={() => setShowExplanationEditor(false)}
                          onOk={() => setShowExplanationEditor(false)}
                        />
                      </div>
                    )}
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
                  {/* Question Type Display */}
                  <div className="quiz-modal-question-type-display">
                    <label>
                      {__("Question Type: ", "tutorpress")}
                      <span className="quiz-modal-question-type-value">
                        {getQuestionTypeDisplayName(questions[selectedQuestionIndex].question_type)}
                      </span>
                    </label>
                  </div>

                  {/* Answer Required */}
                  <ToggleControl
                    label={__("Answer Required", "tutorpress")}
                    checked={questions[selectedQuestionIndex].question_settings.answer_required}
                    onChange={(checked) =>
                      handleQuestionSettingUpdate(selectedQuestionIndex, "answer_required", checked)
                    }
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
                    onChange={(checked) =>
                      handleQuestionSettingUpdate(selectedQuestionIndex, "show_question_mark", checked)
                    }
                    disabled={isSaving}
                  />

                  {/* Type-Specific Settings (only for certain types) */}
                  {renderQuestionTypeSettings(questions[selectedQuestionIndex])}
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
