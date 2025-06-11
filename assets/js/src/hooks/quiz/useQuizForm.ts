/**
 * Quiz Form Management Hook
 *
 * @description Custom React hook for managing quiz form state, validation, and data transformation.
 *              Handles all quiz-level settings including title, description, time limits, grading,
 *              and integration with WordPress/Tutor LMS. Extracted from QuizModal during Phase 1
 *              refactoring to improve code organization and reusability.
 *
 * @features
 * - Form state management with validation
 * - WordPress Course Preview addon integration
 * - Time limit configuration with multiple units
 * - Passing grade and question limit settings
 * - Content drip functionality
 * - Form data transformation for API submission
 *
 * @usage
 * const { formState, updateTitle, validateEntireForm } = useQuizForm(initialData);
 *
 * @package TutorPress
 * @subpackage Quiz/Hooks
 * @since 1.0.0
 */

import { useState, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import type { QuizForm, QuizSettings, QuizQuestion, TimeUnit, FeedbackMode } from "../../types/quiz";
import { getDefaultQuizSettings } from "../../types/quiz";

/**
 * Quiz form validation errors
 */
export interface QuizFormErrors {
  title?: string;
  description?: string;
  timeLimit?: string;
  passingGrade?: string;
  maxQuestions?: string;
  availableAfterDays?: string;
  attemptsAllowed?: string;
}

/**
 * Quiz form state
 */
export interface QuizFormState {
  title: string;
  description: string;
  settings: QuizSettings;
  errors: QuizFormErrors;
  isValid: boolean;
  isDirty: boolean;
}

/**
 * Course Preview addon availability
 */
export interface CoursePreviewAddon {
  available: boolean;
  checked: boolean;
}

/**
 * Return type for useQuizForm hook
 */
export interface UseQuizFormReturn {
  formState: QuizFormState;
  coursePreviewAddon: CoursePreviewAddon;
  updateTitle: (title: string) => void;
  updateDescription: (description: string) => void;
  updateSettings: (settings: Partial<QuizSettings>) => void;
  updateTimeLimit: (value: number, type: TimeUnit) => void;
  updateContentDrip: (days: number) => void;
  resetForm: () => void;
  resetToDefaults: () => void;
  validateEntireForm: () => boolean;
  checkCoursePreviewAddon: () => Promise<boolean>;
  getFormData: (questions: QuizQuestion[]) => QuizForm;
  isValid: boolean;
  isDirty: boolean;
  errors: QuizFormErrors;
  // Initialization functions (no dirty state marking)
  initializeWithData: (data: Partial<QuizForm>) => void;
}

/**
 * Custom hook for managing quiz form state
 */
export const useQuizForm = (initialData?: Partial<QuizForm>): UseQuizFormReturn => {
  // Initialize form state
  const [formState, setFormState] = useState<QuizFormState>(() => ({
    title: initialData?.post_title || "",
    description: initialData?.post_content || "",
    settings: initialData?.quiz_option || getDefaultQuizSettings(),
    errors: {},
    isValid: true,
    isDirty: false,
  }));

  // Course Preview addon state
  const [coursePreviewAddon, setCoursePreviewAddon] = useState<CoursePreviewAddon>({
    available: false,
    checked: false,
  });

  /**
   * Check if Course Preview addon is available
   */
  const checkCoursePreviewAddon = useCallback(async () => {
    if (coursePreviewAddon.checked) {
      return coursePreviewAddon.available;
    }

    try {
      // Check via REST API or global variable
      const tutorObject = (window as any).tutorObject || (window as any)._tutorobject;
      const isAvailable = tutorObject?.coursePreviewAddon || false;

      setCoursePreviewAddon({
        available: isAvailable,
        checked: true,
      });

      return isAvailable;
    } catch (error) {
      setCoursePreviewAddon({
        available: false,
        checked: true,
      });
      return false;
    }
  }, [coursePreviewAddon]);

  /**
   * Validate form fields
   */
  const validateForm = useCallback(
    (state: QuizFormState): QuizFormErrors => {
      const errors: QuizFormErrors = {};

      // Title validation
      if (!state.title.trim()) {
        errors.title = __("Quiz title is required", "tutorpress");
      } else if (state.title.trim().length < 3) {
        errors.title = __("Quiz title must be at least 3 characters", "tutorpress");
      }

      // Time limit validation
      if (state.settings.time_limit.time_value < 0) {
        errors.timeLimit = __("Time limit cannot be negative", "tutorpress");
      }

      // Passing grade validation
      if (state.settings.passing_grade < 0 || state.settings.passing_grade > 100) {
        errors.passingGrade = __("Passing grade must be between 0 and 100", "tutorpress");
      }

      // Max questions validation
      if (state.settings.max_questions_for_answer < 0) {
        errors.maxQuestions = __("Max questions cannot be negative", "tutorpress");
      }

      // Available after days validation (if Course Preview addon is available)
      if (coursePreviewAddon.available && state.settings.content_drip_settings.after_xdays_of_enroll < 0) {
        errors.availableAfterDays = __("Available after days cannot be negative", "tutorpress");
      }

      // Attempts allowed validation (only validate when feedback mode is "retry")
      if (state.settings.feedback_mode === "retry") {
        if (state.settings.attempts_allowed < 0 || state.settings.attempts_allowed > 20) {
          errors.attemptsAllowed = __("Allowed attempts must be between 0 and 20", "tutorpress");
        }
      }

      return errors;
    },
    [coursePreviewAddon.available]
  );

  /**
   * Update form field
   */
  const updateField = useCallback(
    (field: keyof QuizFormState, value: any) => {
      setFormState((prevState) => {
        const newState = {
          ...prevState,
          [field]: value,
          isDirty: true,
        };

        // Validate and update errors
        const errors = validateForm(newState);
        newState.errors = errors;
        newState.isValid = Object.keys(errors).length === 0;

        return newState;
      });
    },
    [validateForm]
  );

  /**
   * Update quiz title
   */
  const updateTitle = useCallback(
    (title: string) => {
      updateField("title", title);
    },
    [updateField]
  );

  /**
   * Update quiz description
   */
  const updateDescription = useCallback(
    (description: string) => {
      updateField("description", description);
    },
    [updateField]
  );

  /**
   * Convert Tutor LMS integer booleans to actual booleans
   */
  const convertTutorBooleans = useCallback((settings: any): any => {
    const booleanFields = [
      "hide_quiz_time_display",
      "quiz_auto_start",
      "hide_question_number_overview",
      "pass_is_required",
    ];

    const converted = { ...settings };

    booleanFields.forEach((field) => {
      if (field in converted) {
        // Convert integer (0/1) or string ("0"/"1") to boolean
        converted[field] = converted[field] === 1 || converted[field] === "1" || converted[field] === true;
      }
    });

    return converted;
  }, []);

  /**
   * Update quiz settings
   */
  const updateSettings = useCallback(
    (settings: Partial<QuizSettings>) => {
      // Convert Tutor LMS integer booleans to actual booleans
      const convertedSettings = convertTutorBooleans(settings);

      setFormState((prevState) => {
        const newState = {
          ...prevState,
          settings: {
            ...prevState.settings,
            ...convertedSettings,
          },
          isDirty: true,
        };

        // Validate and update errors
        const errors = validateForm(newState);
        newState.errors = errors;
        newState.isValid = Object.keys(errors).length === 0;

        return newState;
      });
    },
    [validateForm, convertTutorBooleans]
  );

  /**
   * Update time limit
   */
  const updateTimeLimit = useCallback(
    (timeValue: number, timeType: string) => {
      updateSettings({
        time_limit: {
          time_value: timeValue,
          time_type: timeType as any,
        },
      });
    },
    [updateSettings]
  );

  /**
   * Update content drip settings
   */
  const updateContentDrip = useCallback(
    (afterDays: number) => {
      updateSettings({
        content_drip_settings: {
          ...formState.settings.content_drip_settings,
          after_xdays_of_enroll: afterDays,
        },
      });
    },
    [updateSettings, formState.settings.content_drip_settings]
  );

  /**
   * Reset form to initial state or defaults for new quiz
   */
  const resetForm = useCallback(() => {
    setFormState({
      title: initialData?.post_title || "",
      description: initialData?.post_content || "",
      settings: initialData?.quiz_option || getDefaultQuizSettings(),
      errors: {},
      isValid: true,
      isDirty: false,
    });
  }, [initialData]);

  /**
   * Reset form to completely clean defaults (for new quiz)
   */
  const resetToDefaults = useCallback(() => {
    setFormState({
      title: "",
      description: "",
      settings: getDefaultQuizSettings(),
      errors: {},
      isValid: true,
      isDirty: false,
    });
  }, []);

  /**
   * Convert booleans back to integers for Tutor LMS compatibility
   */
  const convertBooleansToIntegers = useCallback((settings: any): any => {
    const booleanFields = [
      "hide_quiz_time_display",
      "quiz_auto_start",
      "hide_question_number_overview",
      "pass_is_required",
    ];

    const converted = { ...settings };

    booleanFields.forEach((field) => {
      if (field in converted && typeof converted[field] === "boolean") {
        // Convert boolean to integer (0/1) for Tutor LMS
        converted[field] = converted[field] ? 1 : 0;
      }
    });

    return converted;
  }, []);

  /**
   * Get form data for saving
   */
  const getFormData = useCallback(
    (currentQuestions?: QuizQuestion[]): QuizForm => {
      // Convert booleans back to integers for Tutor LMS compatibility
      const tutorCompatibleSettings = convertBooleansToIntegers(formState.settings);

      return {
        ID: initialData?.ID,
        post_title: formState.title.trim(),
        post_content: formState.description.trim(),
        quiz_option: tutorCompatibleSettings,
        questions: currentQuestions || initialData?.questions || [],
        deleted_question_ids: initialData?.deleted_question_ids || [],
        deleted_answer_ids: initialData?.deleted_answer_ids || [],
        menu_order: initialData?.menu_order || 0,
      };
    },
    [formState, initialData, convertBooleansToIntegers]
  );

  /**
   * Validate entire form
   */
  const validateEntireForm = useCallback((): boolean => {
    const errors = validateForm(formState);
    setFormState((prevState) => ({
      ...prevState,
      errors,
      isValid: Object.keys(errors).length === 0,
    }));
    return Object.keys(errors).length === 0;
  }, [formState, validateForm]);

  /**
   * Initialize form with data without marking as dirty
   * Use this for loading existing quiz data - prevents "unsaved changes" warning
   */
  const initializeWithData = useCallback(
    (data: Partial<QuizForm>) => {
      const convertedSettings = data.quiz_option ? convertTutorBooleans(data.quiz_option) : getDefaultQuizSettings();

      setFormState((prevState) => {
        const newState = {
          ...prevState,
          title: data.post_title || "",
          description: data.post_content || "",
          settings: convertedSettings,
          isDirty: false, // Key: Keep isDirty as false for initialization
        };

        // Validate but don't mark as dirty
        const errors = validateForm(newState);
        newState.errors = errors;
        newState.isValid = Object.keys(errors).length === 0;

        return newState;
      });
    },
    [validateForm, convertTutorBooleans]
  );

  return {
    // State
    formState,
    coursePreviewAddon,

    // Actions
    updateTitle,
    updateDescription,
    updateSettings,
    updateTimeLimit,
    updateContentDrip,
    resetForm,
    resetToDefaults,
    validateEntireForm,
    checkCoursePreviewAddon,
    getFormData,

    // Initialization (no dirty state)
    initializeWithData,

    // Computed
    isValid: formState.isValid,
    isDirty: formState.isDirty,
    errors: formState.errors,
  };
};
