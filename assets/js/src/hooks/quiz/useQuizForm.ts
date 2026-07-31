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
import type {
  QuizCapabilities,
  QuizContentType,
  QuizEffectiveSettings,
  QuizForm,
  QuizQuestion,
  QuizSettings,
  QuizSettingsContract,
  QuizSettingsDirtyGroup,
  QuizSettingsLoadInput,
  QuizSettingsSaveBlockedResult,
  RawQuizSettings,
  TimeUnit,
} from "../../types/quiz";
import { getDefaultQuizSettings } from "../../types/quiz";
import {
  convertQuizSettingsFormModelToPayload,
  convertRawQuizSettingsToFormModel,
  createNewQuizSettingsFormModel,
  getQuizSettingsContract,
} from "../../utils/quizSettingsContract";
import { isH5pEnabled, isH5pPluginActive } from "../../utils/addonChecker";

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
  rawSettings: RawQuizSettings;
  effectiveSettings: QuizEffectiveSettings | null;
  dirtySettingsGroups: ReadonlySet<QuizSettingsDirtyGroup>;
  settingsContract: QuizSettingsContract;
  contentType: QuizContentType;
  errors: QuizFormErrors;
  isValid: boolean;
  isDirty: boolean;
}

export interface UseQuizFormOptions {
  capabilities?: QuizCapabilities;
  contentType: QuizContentType;
  initialData?: Partial<QuizForm>;
  contentDripAvailable?: boolean;
  hasProContentDripSettings?: boolean;
  proContentDripSettings?: QuizSettingsLoadInput["proContentDripSettings"];
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
  getFormData: (questions: QuizQuestion[], isNewQuiz: boolean) => QuizFormDataResult;
  isValid: boolean;
  isDirty: boolean;
  errors: QuizFormErrors;
  // Initialization functions (no dirty state marking)
  initializeWithData: (data: Partial<QuizForm>) => void;
}

export type QuizFormDataResult =
  | {
      status: "ready";
      formData: QuizForm;
    }
  | QuizSettingsSaveBlockedResult;

/**
 * Custom hook for managing quiz form state
 */
export const createInitialQuizFormState = ({
  capabilities,
  contentType,
  initialData,
  contentDripAvailable = false,
  hasProContentDripSettings = false,
  proContentDripSettings,
}: UseQuizFormOptions): QuizFormState => {
  const settingsContract = getQuizSettingsContract(capabilities);

  if (initialData?.quiz_option) {
    const loadedModel = convertRawQuizSettingsToFormModel({
      contract: settingsContract,
      contentType,
      rawSettings: initialData.quiz_option as unknown as RawQuizSettings,
      contentDripAvailable,
      hasProContentDripSettings,
      proContentDripSettings,
    });

    return {
      title: initialData.post_title || "",
      description: initialData.post_content || "",
      settings: initialData.quiz_option,
      rawSettings: loadedModel.rawSettings,
      effectiveSettings: loadedModel.effectiveSettings,
      dirtySettingsGroups: loadedModel.dirtyGroups,
      settingsContract,
      contentType,
      errors: {},
      isValid: true,
      isDirty: false,
    };
  }

  const model = createNewQuizSettingsFormModel(capabilities, contentType);
  const effective = model?.effectiveSettings;
  const settings: QuizSettings = effective
    ? toFormQuizSettings(effective)
    : getDefaultQuizSettings();

  return {
    title: initialData?.post_title || "",
    description: initialData?.post_content || "",
    settings,
    rawSettings: model ? { ...model.rawSettings } : {},
    effectiveSettings: effective ? { ...effective } : null,
    dirtySettingsGroups: new Set<QuizSettingsDirtyGroup>(),
    settingsContract,
    contentType,
    errors: {},
    isValid: true,
    isDirty: false,
  };
};

const toFormQuizSettings = (effective: QuizEffectiveSettings): QuizSettings => ({
  time_limit: { ...effective.time_limit },
  hide_quiz_time_display: effective.hide_quiz_time_display,
  feedback_mode: effective.feedback_mode,
  limit_attempts_allowed: effective.limit_attempts_allowed,
  attempts_allowed: effective.attempts_allowed,
  pass_is_required: effective.pass_is_required,
  passing_grade: effective.passing_grade,
  limit_questions_to_answer: effective.limit_questions_to_answer,
  max_questions_for_answer: effective.max_questions_for_answer,
  quiz_auto_start: effective.quiz_auto_start,
  question_layout_view: effective.question_layout_view,
  questions_order: effective.questions_order,
  hide_question_number_overview: effective.hide_question_number_overview,
  short_answer_characters_limit:
    typeof effective.short_answer_characters_limit === "number"
      ? effective.short_answer_characters_limit
      : 0,
  open_ended_answer_characters_limit:
    typeof effective.open_ended_answer_characters_limit === "number"
      ? effective.open_ended_answer_characters_limit
      : 0,
  content_drip_settings: { ...effective.content_drip_settings },
});

const getDirtyGroupsForSettings = (
  settings: Partial<QuizSettings>,
  contract: QuizSettingsContract
): QuizSettingsDirtyGroup[] => {
  const groups = new Set<QuizSettingsDirtyGroup>();

  Object.keys(settings).forEach((key) => {
    switch (key as keyof QuizSettings) {
      case "time_limit":
        groups.add("time_limit");
        break;
      case "hide_quiz_time_display":
        groups.add("hide_countdown");
        break;
      case "feedback_mode":
        groups.add("legacy_feedback");
        break;
      case "limit_attempts_allowed":
      case "attempts_allowed":
        groups.add(contract === "v4" ? "attempts" : "legacy_feedback");
        break;
      case "pass_is_required":
        groups.add("pass_required");
        break;
      case "passing_grade":
        groups.add("passing_grade");
        break;
      case "limit_questions_to_answer":
      case "max_questions_for_answer":
        groups.add("question_limit");
        break;
      case "quiz_auto_start":
        groups.add("auto_start");
        break;
      case "question_layout_view":
        groups.add("layout");
        break;
      case "questions_order":
        groups.add("question_order");
        break;
      case "hide_question_number_overview":
        groups.add("hide_question_number");
        break;
      case "short_answer_characters_limit":
        groups.add("short_answer_character_limit");
        break;
      case "open_ended_answer_characters_limit":
        groups.add("open_ended_character_limit");
        break;
      case "content_drip_settings":
        groups.add("drip_available_after_days");
        break;
    }
  });

  return [...groups];
};

const applySettingsToEffective = (
  effectiveSettings: QuizEffectiveSettings | null,
  settings: Partial<QuizSettings>
): QuizEffectiveSettings | null => {
  if (!effectiveSettings) {
    return null;
  }

  const next = {
    ...effectiveSettings,
    time_limit: { ...effectiveSettings.time_limit },
    content_drip_settings: { ...effectiveSettings.content_drip_settings },
  };

  if (settings.time_limit) {
    next.time_limit = { ...settings.time_limit };
    next.enable_time_limit = settings.time_limit.time_value > 0;
  }
  if (settings.hide_quiz_time_display !== undefined) {
    next.hide_quiz_time_display = settings.hide_quiz_time_display;
  }
  if (settings.feedback_mode !== undefined) {
    next.feedback_mode = settings.feedback_mode;
    next.limit_attempts_allowed = settings.feedback_mode === "retry";
    next.enable_answer_reveal = settings.feedback_mode === "reveal";
  }
  if (settings.limit_attempts_allowed !== undefined) {
    next.limit_attempts_allowed = settings.limit_attempts_allowed;
  }
  if (settings.attempts_allowed !== undefined) {
    next.attempts_allowed = settings.attempts_allowed;
  }
  if (settings.pass_is_required !== undefined) {
    next.pass_is_required = settings.pass_is_required;
  }
  if (settings.passing_grade !== undefined) {
    next.passing_grade = settings.passing_grade;
  }
  if (settings.limit_questions_to_answer !== undefined) {
    next.limit_questions_to_answer = settings.limit_questions_to_answer;
    if (settings.limit_questions_to_answer && next.max_questions_for_answer <= 0) {
      next.max_questions_for_answer = 10;
    }
  }
  if (settings.max_questions_for_answer !== undefined) {
    next.max_questions_for_answer =
      settings.max_questions_for_answer > 0 ? settings.max_questions_for_answer : 10;
    if (settings.limit_questions_to_answer === undefined) {
      next.limit_questions_to_answer = settings.max_questions_for_answer > 0;
    }
  }
  if (settings.quiz_auto_start !== undefined) {
    next.quiz_auto_start = settings.quiz_auto_start;
  }
  if (settings.question_layout_view !== undefined) {
    next.question_layout_view =
      settings.question_layout_view === "question_below_each_other"
        ? "question_below_each_other"
        : "single_question";
    next.enable_pagination = settings.question_layout_view === "question_pagination";
  }
  if (settings.questions_order !== undefined) {
    next.questions_order = settings.questions_order;
  }
  if (settings.hide_question_number_overview !== undefined) {
    next.hide_question_number_overview = settings.hide_question_number_overview;
  }
  if (settings.short_answer_characters_limit !== undefined) {
    next.short_answer_characters_limit = settings.short_answer_characters_limit;
  }
  if (settings.open_ended_answer_characters_limit !== undefined) {
    next.open_ended_answer_characters_limit = settings.open_ended_answer_characters_limit;
  }
  if (settings.content_drip_settings) {
    next.content_drip_settings = {
      ...next.content_drip_settings,
      ...settings.content_drip_settings,
    };
  }

  return next;
};

export const useQuizForm = (options: UseQuizFormOptions): UseQuizFormReturn => {
  const {
    capabilities,
    contentType,
    initialData,
    contentDripAvailable = false,
    hasProContentDripSettings = false,
    proContentDripSettings,
  } = options;
  // Initialize form state
  const [formState, setFormState] = useState<QuizFormState>(() => createInitialQuizFormState(options));

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
      const timeValue = state.settings.time_limit?.time_value;
      if (timeValue !== undefined && timeValue < 0) {
        errors.timeLimit = __("Time limit cannot be negative", "tutorpress");
      }

      // Passing grade validation
      if (state.settings.passing_grade < 0 || state.settings.passing_grade > 100) {
        errors.passingGrade = __("Passing grade must be between 0 and 100", "tutorpress");
      }

      // Max questions: require a positive companion when the limit toggle is on
      if (state.settings.limit_questions_to_answer) {
        if (state.settings.max_questions_for_answer <= 0) {
          errors.maxQuestions = __("Maximum questions must be greater than 0", "tutorpress");
        }
      } else if (state.settings.max_questions_for_answer < 0) {
        errors.maxQuestions = __("Max questions cannot be negative", "tutorpress");
      }

      // Available after days validation (if Course Preview addon is available)
      const availableAfterDays = state.settings.content_drip_settings?.after_xdays_of_enroll;
      if (
        coursePreviewAddon.available &&
        availableAfterDays !== undefined &&
        availableAfterDays < 0
      ) {
        errors.availableAfterDays = __("Available after days cannot be negative", "tutorpress");
      }

      // Attempts: V4 validates when limit is on; legacy validates only for Retry
      const attemptsLimited =
        state.settingsContract === "v4"
          ? state.settings.limit_attempts_allowed
          : state.settings.feedback_mode === "retry";
      if (attemptsLimited) {
        if (state.settingsContract === "v4") {
          if (state.settings.attempts_allowed < 0) {
            errors.attemptsAllowed = __("Allowed attempts cannot be negative", "tutorpress");
          }
        } else if (state.settings.attempts_allowed < 0 || state.settings.attempts_allowed > 20) {
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
      "limit_attempts_allowed",
      "limit_questions_to_answer",
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
        const nextEffective = applySettingsToEffective(
          prevState.effectiveSettings,
          convertedSettings
        );
        const dirtyGroups = getDirtyGroupsForSettings(
          convertedSettings,
          prevState.settingsContract
        );
        const mergedSettings: QuizSettings = {
          ...prevState.settings,
          ...convertedSettings,
        };

        // Keep Quiz scope toggles/companions aligned after derived effective updates
        if (nextEffective) {
          mergedSettings.limit_attempts_allowed = nextEffective.limit_attempts_allowed;
          mergedSettings.limit_questions_to_answer = nextEffective.limit_questions_to_answer;
          mergedSettings.attempts_allowed = nextEffective.attempts_allowed;
          mergedSettings.max_questions_for_answer = nextEffective.max_questions_for_answer;
          mergedSettings.feedback_mode = nextEffective.feedback_mode;
        }

        const newState = {
          ...prevState,
          settings: mergedSettings,
          effectiveSettings: nextEffective,
          dirtySettingsGroups: new Set([
            ...prevState.dirtySettingsGroups,
            ...dirtyGroups,
          ]),
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
    setFormState(
      createInitialQuizFormState({
        capabilities,
        contentType,
        initialData,
        contentDripAvailable,
        hasProContentDripSettings,
        proContentDripSettings,
      })
    );
  }, [
    capabilities,
    contentType,
    initialData,
    contentDripAvailable,
    hasProContentDripSettings,
    proContentDripSettings,
  ]);

  /**
   * Reset form to completely clean defaults (for new quiz)
   */
  const resetToDefaults = useCallback(() => {
    setFormState(createInitialQuizFormState({ capabilities, contentType }));
  }, [capabilities, contentType]);

  /**
   * Get form data for saving
   */
  const getFormData = useCallback(
    (currentQuestions: QuizQuestion[], isNewQuiz: boolean): QuizFormDataResult => {
      const settingsResult = convertQuizSettingsFormModelToPayload({
        contract: formState.settingsContract,
        contentType: formState.contentType,
        rawSettings: formState.rawSettings,
        effectiveSettings: formState.effectiveSettings,
        dirtyGroups: formState.dirtySettingsGroups,
        isNewQuiz,
        h5pRuntimeAvailable: isH5pEnabled() && isH5pPluginActive(),
      });

      if (settingsResult.status === "blocked") {
        return settingsResult;
      }

      return {
        status: "ready",
        formData: {
          ID: initialData?.ID,
          post_title: formState.title.trim(),
          post_content: formState.description.trim(),
          quiz_option: settingsResult.settings as unknown as QuizSettings,
          questions: currentQuestions,
          deleted_question_ids: initialData?.deleted_question_ids || [],
          deleted_answer_ids: initialData?.deleted_answer_ids || [],
          menu_order: initialData?.menu_order || 0,
        },
      };
    },
    [formState, initialData]
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
      const loadedModel = data.quiz_option
        ? convertRawQuizSettingsToFormModel({
            contract: getQuizSettingsContract(capabilities),
            contentType,
            rawSettings: data.quiz_option as unknown as RawQuizSettings,
            contentDripAvailable,
            hasProContentDripSettings,
            proContentDripSettings,
          })
        : null;

      setFormState((prevState) => {
        // Preserve loaded form fields for non-Quiz-scope groups (layout, drip, timing,
        // etc.). Overlay only Step 6 Quiz-scope effective values so companions/toggles
        // are correct without rewriting out-of-scope presentation.
        const effective = loadedModel?.effectiveSettings;
        const settings: QuizSettings = {
          ...getDefaultQuizSettings(),
          ...convertedSettings,
          limit_attempts_allowed:
            effective?.limit_attempts_allowed ??
            convertedSettings.limit_attempts_allowed ??
            convertedSettings.feedback_mode === "retry",
          attempts_allowed:
            effective?.attempts_allowed ?? convertedSettings.attempts_allowed,
          feedback_mode: effective?.feedback_mode ?? convertedSettings.feedback_mode,
          pass_is_required:
            effective?.pass_is_required ?? convertedSettings.pass_is_required,
          passing_grade: effective?.passing_grade ?? convertedSettings.passing_grade,
          questions_order:
            effective?.questions_order ?? convertedSettings.questions_order,
          limit_questions_to_answer:
            effective?.limit_questions_to_answer ??
            convertedSettings.limit_questions_to_answer ??
            Number(convertedSettings.max_questions_for_answer) > 0,
          max_questions_for_answer:
            effective?.max_questions_for_answer ??
            convertedSettings.max_questions_for_answer,
        };

        const newState = {
          ...prevState,
          title: data.post_title || "",
          description: data.post_content || "",
          settings,
          rawSettings: loadedModel?.rawSettings ?? {},
          effectiveSettings: loadedModel?.effectiveSettings ?? null,
          dirtySettingsGroups: new Set<QuizSettingsDirtyGroup>(),
          isDirty: false, // Key: Keep isDirty as false for initialization
        };

        // Validate but don't mark as dirty
        const errors = validateForm(newState);
        newState.errors = errors;
        newState.isValid = Object.keys(errors).length === 0;

        return newState;
      });
    },
    [
      validateForm,
      convertTutorBooleans,
      capabilities,
      contentType,
      contentDripAvailable,
      hasProContentDripSettings,
      proContentDripSettings,
    ]
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
