/**
 * Generic Modal Form State Management Hook
 *
 * @description Generic React hook for managing modal form state, validation, and data transformation.
 *              Extracted from useQuizForm to provide reusable form state patterns for all modal types.
 *              Supports title/description management, settings validation, dirty state tracking,
 *              and error handling with customizable validation rules.
 *
 * @features
 * - Generic form state management with validation
 * - Customizable validation rules and error messages
 * - Dirty state tracking for unsaved changes
 * - Title and description field management
 * - Settings object management with type safety
 * - Form reset and default value handling
 * - Data transformation utilities
 *
 * @usage
 * const { formState, updateTitle, validateForm } = useModalFormState({
 *   initialData: { title: "Quiz", description: "Description" },
 *   defaultSettings: getDefaultQuizSettings(),
 *   validationRules: customValidationRules,
 * });
 *
 * @package TutorPress
 * @subpackage Common/Hooks
 * @since 1.0.0
 */

import { useState, useCallback } from "react";
import { __ } from "@wordpress/i18n";

/**
 * Generic form validation errors
 */
export interface ModalFormErrors {
  title?: string;
  description?: string;
  [key: string]: string | undefined;
}

/**
 * Generic form state structure
 */
export interface ModalFormState<TSettings = Record<string, any>> {
  title: string;
  description: string;
  settings: TSettings;
  errors: ModalFormErrors;
  isValid: boolean;
  isDirty: boolean;
}

/**
 * Validation rule function type
 */
export type ValidationRule<TSettings = Record<string, any>> = (state: ModalFormState<TSettings>) => ModalFormErrors;

/**
 * Initial data structure for modal forms
 */
export interface ModalFormInitialData<TSettings = Record<string, any>> {
  ID?: number;
  post_title?: string;
  post_content?: string;
  settings?: TSettings;
  [key: string]: any;
}

/**
 * Configuration options for useModalFormState
 */
export interface UseModalFormStateOptions<TSettings = Record<string, any>> {
  initialData?: ModalFormInitialData<TSettings>;
  defaultSettings: TSettings;
  validationRules?: ValidationRule<TSettings>[];
  customValidators?: Record<string, (value: any, state: ModalFormState<TSettings>) => string | undefined>;
}

/**
 * Return type for useModalFormState hook
 */
export interface UseModalFormStateReturn<TSettings = Record<string, any>> {
  formState: ModalFormState<TSettings>;
  updateTitle: (title: string) => void;
  updateDescription: (description: string) => void;
  updateSettings: (settings: Partial<TSettings>) => void;
  updateField: (field: keyof ModalFormState<TSettings>, value: any) => void;
  resetForm: () => void;
  resetToDefaults: () => void;
  validateForm: () => boolean;
  getFormData: () => ModalFormInitialData<TSettings>;
  isValid: boolean;
  isDirty: boolean;
  errors: ModalFormErrors;
}

/**
 * Default validation rules for common form fields
 */
export const createDefaultValidationRules = <TSettings = Record<string, any>>(): ValidationRule<TSettings>[] => [
  // Title validation
  (state: ModalFormState<TSettings>): ModalFormErrors => {
    const errors: ModalFormErrors = {};

    if (!state.title.trim()) {
      errors.title = __("Title is required", "tutorpress");
    } else if (state.title.trim().length < 3) {
      errors.title = __("Title must be at least 3 characters", "tutorpress");
    }

    return errors;
  },
];

/**
 * Utility function to merge validation errors
 */
export const mergeValidationErrors = (errorArrays: ModalFormErrors[]): ModalFormErrors => {
  return errorArrays.reduce((merged, errors) => ({ ...merged, ...errors }), {});
};

/**
 * Generic hook for managing modal form state
 */
export const useModalFormState = <TSettings = Record<string, any>>({
  initialData,
  defaultSettings,
  validationRules = [],
  customValidators = {},
}: UseModalFormStateOptions<TSettings>): UseModalFormStateReturn<TSettings> => {
  // Initialize form state
  const [formState, setFormState] = useState<ModalFormState<TSettings>>(() => ({
    title: initialData?.post_title || "",
    description: initialData?.post_content || "",
    settings: initialData?.settings || defaultSettings,
    errors: {},
    isValid: true,
    isDirty: false,
  }));

  /**
   * Validate form using all validation rules
   */
  const validateFormState = useCallback(
    (state: ModalFormState<TSettings>): ModalFormErrors => {
      // Apply default validation rules
      const defaultRules = createDefaultValidationRules<TSettings>();
      const allRules = [...defaultRules, ...validationRules];

      // Collect errors from all validation rules
      const errorArrays = allRules.map((rule) => rule(state));
      let mergedErrors = mergeValidationErrors(errorArrays);

      // Apply custom validators
      Object.entries(customValidators).forEach(([field, validator]) => {
        const fieldValue = field.includes(".")
          ? field.split(".").reduce((obj, key) => obj?.[key], state as any)
          : (state as any)[field];

        const error = validator(fieldValue, state);
        if (error) {
          mergedErrors[field] = error;
        }
      });

      return mergedErrors;
    },
    [validationRules, customValidators]
  );

  /**
   * Update any form field with validation
   */
  const updateField = useCallback(
    (field: keyof ModalFormState<TSettings>, value: any) => {
      setFormState((prevState) => {
        const newState = {
          ...prevState,
          [field]: value,
          isDirty: true,
        };

        // Validate and update errors
        const errors = validateFormState(newState);
        newState.errors = errors;
        newState.isValid = Object.keys(errors).length === 0;

        return newState;
      });
    },
    [validateFormState]
  );

  /**
   * Update title field
   */
  const updateTitle = useCallback(
    (title: string) => {
      updateField("title", title);
    },
    [updateField]
  );

  /**
   * Update description field
   */
  const updateDescription = useCallback(
    (description: string) => {
      updateField("description", description);
    },
    [updateField]
  );

  /**
   * Update settings with partial update support
   */
  const updateSettings = useCallback(
    (settingsUpdate: Partial<TSettings>) => {
      setFormState((prevState) => {
        const newState = {
          ...prevState,
          settings: {
            ...prevState.settings,
            ...settingsUpdate,
          },
          isDirty: true,
        };

        // Validate and update errors
        const errors = validateFormState(newState);
        newState.errors = errors;
        newState.isValid = Object.keys(errors).length === 0;

        return newState;
      });
    },
    [validateFormState]
  );

  /**
   * Reset form to initial state
   */
  const resetForm = useCallback(() => {
    setFormState({
      title: initialData?.post_title || "",
      description: initialData?.post_content || "",
      settings: initialData?.settings || defaultSettings,
      errors: {},
      isValid: true,
      isDirty: false,
    });
  }, [initialData, defaultSettings]);

  /**
   * Reset form to clean defaults (for new items)
   */
  const resetToDefaults = useCallback(() => {
    setFormState({
      title: "",
      description: "",
      settings: defaultSettings,
      errors: {},
      isValid: true,
      isDirty: false,
    });
  }, [defaultSettings]);

  /**
   * Validate entire form and update state
   */
  const validateForm = useCallback((): boolean => {
    const errors = validateFormState(formState);
    setFormState((prevState) => ({
      ...prevState,
      errors,
      isValid: Object.keys(errors).length === 0,
    }));
    return Object.keys(errors).length === 0;
  }, [formState, validateFormState]);

  /**
   * Get form data for saving
   */
  const getFormData = useCallback((): ModalFormInitialData<TSettings> => {
    return {
      ID: initialData?.ID,
      post_title: formState.title.trim(),
      post_content: formState.description.trim(),
      settings: formState.settings,
      ...initialData, // Include any additional fields from initial data
    };
  }, [formState, initialData]);

  return {
    // State
    formState,

    // Actions
    updateTitle,
    updateDescription,
    updateSettings,
    updateField,
    resetForm,
    resetToDefaults,
    validateForm,
    getFormData,

    // Computed
    isValid: formState.isValid,
    isDirty: formState.isDirty,
    errors: formState.errors,
  };
};

/**
 * Utility function to create validation rules for numeric fields
 */
export const createNumericValidationRule = <TSettings = Record<string, any>>(
  fieldPath: string,
  min?: number,
  max?: number,
  fieldName?: string
): ValidationRule<TSettings> => {
  return (state: ModalFormState<TSettings>): ModalFormErrors => {
    const errors: ModalFormErrors = {};

    // Navigate to nested field value
    const value = fieldPath.split(".").reduce((obj, key) => obj?.[key], state as any);

    if (typeof value === "number") {
      if (min !== undefined && value < min) {
        errors[fieldPath] = __(`${fieldName || fieldPath} cannot be less than ${min}`, "tutorpress");
      }
      if (max !== undefined && value > max) {
        errors[fieldPath] = __(`${fieldName || fieldPath} cannot be greater than ${max}`, "tutorpress");
      }
    }

    return errors;
  };
};

/**
 * Utility function to create validation rules for required fields
 */
export const createRequiredFieldValidationRule = <TSettings = Record<string, any>>(
  fieldPath: string,
  fieldName?: string
): ValidationRule<TSettings> => {
  return (state: ModalFormState<TSettings>): ModalFormErrors => {
    const errors: ModalFormErrors = {};

    // Navigate to nested field value
    const value = fieldPath.split(".").reduce((obj, key) => obj?.[key], state as any);

    if (!value || (typeof value === "string" && !value.trim())) {
      errors[fieldPath] = __(`${fieldName || fieldPath} is required`, "tutorpress");
    }

    return errors;
  };
};
