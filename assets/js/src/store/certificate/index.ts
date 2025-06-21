/**
 * Certificate Store for TutorPress
 *
 * Dedicated store for certificate template operations, following the H5P store pattern
 * for better organization and maintainability.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { __ } from "@wordpress/i18n";
import {
  CertificateTemplate,
  CertificateSelection,
  CertificateFilters,
  CertificateTemplateState,
  CertificateSelectionState,
  CertificatePreviewState,
  CertificateError,
  CertificateErrorCode,
  CertificateTemplatesResponse,
  CertificateSelectionResponse,
  CertificateSaveResponse,
} from "../../types/certificate";

// ============================================================================
// STATE INTERFACE
// ============================================================================

/**
 * Certificate Store State Interface
 */
interface CertificateState {
  /** Template loading, filtering, and management */
  templates: CertificateTemplateState;
  /** Current course certificate selection */
  selection: CertificateSelectionState;
  /** Preview modal state */
  preview: CertificatePreviewState;
}

// ============================================================================
// INITIAL STATE
// ============================================================================

const DEFAULT_STATE: CertificateState = {
  templates: {
    templates: [],
    filters: {
      orientation: "portrait",
      type: "templates",
      include_none: true,
    },
    filteredTemplates: [],
    status: "idle",
  },
  selection: {
    courseId: null,
    selectedTemplate: null,
    isDirty: false,
    status: "idle",
  },
  preview: {
    isOpen: false,
    template: null,
    isLoading: false,
  },
};

// ============================================================================
// ACTION TYPES
// ============================================================================

export type CertificateAction =
  // Template Actions
  | { type: "FETCH_CERTIFICATE_TEMPLATES" }
  | { type: "FETCH_CERTIFICATE_TEMPLATES_START" }
  | { type: "FETCH_CERTIFICATE_TEMPLATES_SUCCESS"; payload: { templates: CertificateTemplate[] } }
  | { type: "FETCH_CERTIFICATE_TEMPLATES_ERROR"; payload: { error: CertificateError } }
  | { type: "SET_CERTIFICATE_FILTERS"; payload: { filters: CertificateFilters } }
  | { type: "FILTER_CERTIFICATE_TEMPLATES"; payload: { filteredTemplates: CertificateTemplate[] } }
  // Selection Actions
  | { type: "FETCH_CERTIFICATE_SELECTION"; payload: { courseId: number } }
  | { type: "FETCH_CERTIFICATE_SELECTION_START"; payload: { courseId: number } }
  | { type: "FETCH_CERTIFICATE_SELECTION_SUCCESS"; payload: { selection: CertificateSelection } }
  | { type: "FETCH_CERTIFICATE_SELECTION_ERROR"; payload: { error: CertificateError } }
  | { type: "SAVE_CERTIFICATE_SELECTION"; payload: { courseId: number; templateKey: string } }
  | { type: "SAVE_CERTIFICATE_SELECTION_START"; payload: { courseId: number; templateKey: string } }
  | { type: "SAVE_CERTIFICATE_SELECTION_SUCCESS"; payload: { selection: CertificateSelection } }
  | { type: "SAVE_CERTIFICATE_SELECTION_ERROR"; payload: { error: CertificateError } }
  | { type: "SET_CERTIFICATE_COURSE_ID"; payload: { courseId: number | null } }
  | { type: "SET_CERTIFICATE_SELECTED_TEMPLATE"; payload: { templateKey: string | null } }
  | { type: "SET_CERTIFICATE_DIRTY_STATE"; payload: { isDirty: boolean } }
  // Preview Actions
  | { type: "OPEN_CERTIFICATE_PREVIEW"; payload: { template: CertificateTemplate } }
  | { type: "CLOSE_CERTIFICATE_PREVIEW" }
  | { type: "SET_CERTIFICATE_PREVIEW_LOADING"; payload: { isLoading: boolean } };

// ============================================================================
// REDUCER
// ============================================================================

const reducer = (state = DEFAULT_STATE, action: CertificateAction): CertificateState => {
  switch (action.type) {
    // Template Operations
    case "FETCH_CERTIFICATE_TEMPLATES_START":
      return {
        ...state,
        templates: {
          ...state.templates,
          status: "loading",
        },
      };

    case "FETCH_CERTIFICATE_TEMPLATES_SUCCESS":
      return {
        ...state,
        templates: {
          ...state.templates,
          templates: action.payload.templates,
          filteredTemplates: filterTemplates(action.payload.templates, state.templates.filters),
          status: "success",
        },
      };

    case "FETCH_CERTIFICATE_TEMPLATES_ERROR":
      return {
        ...state,
        templates: {
          ...state.templates,
          status: "error",
          error: action.payload.error,
        },
      };

    case "SET_CERTIFICATE_FILTERS":
      const newFilters = action.payload.filters;
      const filteredTemplates = filterTemplates(state.templates.templates, newFilters);
      return {
        ...state,
        templates: {
          ...state.templates,
          filters: newFilters,
          filteredTemplates,
        },
      };

    case "FILTER_CERTIFICATE_TEMPLATES":
      return {
        ...state,
        templates: {
          ...state.templates,
          filteredTemplates: action.payload.filteredTemplates,
        },
      };

    // Selection Operations
    case "FETCH_CERTIFICATE_SELECTION_START":
      return {
        ...state,
        selection: {
          ...state.selection,
          courseId: action.payload.courseId,
          status: "loading",
        },
      };

    case "FETCH_CERTIFICATE_SELECTION_SUCCESS":
      return {
        ...state,
        selection: {
          ...state.selection,
          selectedTemplate: action.payload.selection.template_key,
          isDirty: false,
          status: "success",
        },
      };

    case "FETCH_CERTIFICATE_SELECTION_ERROR":
      return {
        ...state,
        selection: {
          ...state.selection,
          status: "error",
          error: action.payload.error,
        },
      };

    case "SAVE_CERTIFICATE_SELECTION_START":
      return {
        ...state,
        selection: {
          ...state.selection,
          status: "saving",
        },
      };

    case "SAVE_CERTIFICATE_SELECTION_SUCCESS":
      return {
        ...state,
        selection: {
          ...state.selection,
          selectedTemplate: action.payload.selection.template_key,
          isDirty: false,
          status: "success",
        },
      };

    case "SAVE_CERTIFICATE_SELECTION_ERROR":
      return {
        ...state,
        selection: {
          ...state.selection,
          status: "error",
          error: action.payload.error,
        },
      };

    case "SET_CERTIFICATE_COURSE_ID":
      return {
        ...state,
        selection: {
          ...state.selection,
          courseId: action.payload.courseId,
        },
      };

    case "SET_CERTIFICATE_SELECTED_TEMPLATE":
      return {
        ...state,
        selection: {
          ...state.selection,
          selectedTemplate: action.payload.templateKey,
          isDirty: true,
        },
      };

    case "SET_CERTIFICATE_DIRTY_STATE":
      return {
        ...state,
        selection: {
          ...state.selection,
          isDirty: action.payload.isDirty,
        },
      };

    // Preview Operations
    case "OPEN_CERTIFICATE_PREVIEW":
      return {
        ...state,
        preview: {
          isOpen: true,
          template: action.payload.template,
          isLoading: false,
        },
      };

    case "CLOSE_CERTIFICATE_PREVIEW":
      return {
        ...state,
        preview: {
          isOpen: false,
          template: null,
          isLoading: false,
        },
      };

    case "SET_CERTIFICATE_PREVIEW_LOADING":
      return {
        ...state,
        preview: {
          ...state.preview,
          isLoading: action.payload.isLoading,
        },
      };

    default:
      return state;
  }
};

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Filter templates based on current filters
 */
const filterTemplates = (templates: CertificateTemplate[], filters: CertificateFilters): CertificateTemplate[] => {
  let filtered = [...templates];

  // Filter by type first (templates vs custom)
  if (filters.type && filters.type !== "all") {
    if (filters.type === "templates") {
      // Built-in templates - include default templates AND single none option
      // We only include "none" template (landscape), not "off" (portrait) to avoid duplication
      filtered = filtered.filter((template) => template.is_default === true || template.key === "none");
    } else if (filters.type === "custom_templates") {
      // Custom templates - templates added via tutor_certificate_templates filter
      // These have is_default !== true AND don't use the standard Tutor plugin path structure
      // Exclude none/off templates which are special system templates
      filtered = filtered.filter((template) => {
        // Exclude none/off system templates
        if (template.key === "none" || template.key === "off") {
          return false;
        }

        // Custom templates don't have is_default: true
        if (template.is_default === true) {
          return false;
        }

        // Additional check: custom templates typically have different path structures
        // Default templates have paths like "tutor-pro/addons/tutor-certificate/templates/template_1"
        // Custom templates from plugins will have different path structures
        if (template.path && template.path.includes("/tutor-certificate/templates/")) {
          return false;
        }

        return true;
      });
    }
  }

  // Filter by orientation (after type filtering)
  // Special handling: "none" template appears in Templates tab regardless of orientation
  if (filters.orientation && filters.orientation !== "all") {
    filtered = filtered.filter((template) => {
      // Always include "none" template in Templates tab regardless of orientation
      if (template.key === "none" && filters.type === "templates") {
        return true;
      }
      // For all other templates, filter by orientation
      return template.orientation === filters.orientation;
    });
  }

  return filtered;
};

/**
 * Create a certificate error
 */
const createCertificateError = (
  code: CertificateErrorCode,
  message: string,
  details?: Record<string, any>
): CertificateError => ({
  code,
  message,
  details,
  timestamp: new Date(),
});

// ============================================================================
// ACTIONS
// ============================================================================

const actions = {
  // Template Actions
  getCertificateTemplates() {
    return { type: "FETCH_CERTIFICATE_TEMPLATES" as const };
  },

  setCertificateFilters(filters: CertificateFilters) {
    return { type: "SET_CERTIFICATE_FILTERS" as const, payload: { filters } };
  },

  // Selection Actions
  getCertificateSelection(courseId: number) {
    return { type: "FETCH_CERTIFICATE_SELECTION" as const, payload: { courseId } };
  },

  saveCertificateSelection(courseId: number, templateKey: string) {
    return { type: "SAVE_CERTIFICATE_SELECTION" as const, payload: { courseId, templateKey } };
  },

  setCertificateCourseId(courseId: number | null) {
    return { type: "SET_CERTIFICATE_COURSE_ID" as const, payload: { courseId } };
  },

  setCertificateSelectedTemplate(templateKey: string | null) {
    return { type: "SET_CERTIFICATE_SELECTED_TEMPLATE" as const, payload: { templateKey } };
  },

  setCertificateDirtyState(isDirty: boolean) {
    return { type: "SET_CERTIFICATE_DIRTY_STATE" as const, payload: { isDirty } };
  },

  // Preview Actions
  openCertificatePreview(template: CertificateTemplate) {
    return { type: "OPEN_CERTIFICATE_PREVIEW" as const, payload: { template } };
  },

  closeCertificatePreview() {
    return { type: "CLOSE_CERTIFICATE_PREVIEW" as const };
  },

  setCertificatePreviewLoading(isLoading: boolean) {
    return { type: "SET_CERTIFICATE_PREVIEW_LOADING" as const, payload: { isLoading } };
  },
};

// ============================================================================
// SELECTORS
// ============================================================================

const selectors = {
  // Template Selectors
  getCertificateTemplates(state: CertificateState) {
    return state.templates.templates;
  },

  getCertificateFilters(state: CertificateState) {
    return state.templates.filters;
  },

  getFilteredCertificateTemplates(state: CertificateState) {
    return state.templates.filteredTemplates;
  },

  getCertificateTemplateOperationState(state: CertificateState) {
    return { status: state.templates.status, error: state.templates.error };
  },

  isCertificateTemplatesLoading(state: CertificateState) {
    return state.templates.status === "loading";
  },

  hasCertificateTemplatesError(state: CertificateState) {
    return state.templates.status === "error";
  },

  getCertificateTemplatesError(state: CertificateState) {
    return state.templates.error || null;
  },

  getCertificateTemplateByKey(state: CertificateState, templateKey: string) {
    return state.templates.templates.find((template) => template.key === templateKey) || null;
  },

  // Selection Selectors
  getCertificateSelection(state: CertificateState) {
    return {
      courseId: state.selection.courseId,
      selectedTemplate: state.selection.selectedTemplate,
      isDirty: state.selection.isDirty,
    };
  },

  getCertificateSelectionOperationState(state: CertificateState) {
    return { status: state.selection.status, error: state.selection.error };
  },

  isCertificateSelectionLoading(state: CertificateState) {
    return state.selection.status === "loading";
  },

  isCertificateSelectionSaving(state: CertificateState) {
    return state.selection.status === "saving";
  },

  hasCertificateSelectionError(state: CertificateState) {
    return state.selection.status === "error";
  },

  getCertificateSelectionError(state: CertificateState) {
    return state.selection.error || null;
  },

  isCertificateSelectionDirty(state: CertificateState) {
    return state.selection.isDirty;
  },

  // Preview Selectors
  getCertificatePreview(state: CertificateState) {
    return state.preview;
  },

  isCertificatePreviewOpen(state: CertificateState) {
    return state.preview.isOpen;
  },

  getCertificatePreviewTemplate(state: CertificateState) {
    return state.preview.template;
  },

  isCertificatePreviewLoading(state: CertificateState) {
    return state.preview.isLoading;
  },
};

// ============================================================================
// RESOLVERS (API CALLS)
// ============================================================================

const resolvers = {
  *getCertificateTemplates(): Generator<unknown, void, unknown> {
    yield { type: "FETCH_CERTIFICATE_TEMPLATES_START" };

    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/certificate/templates?include_none=true",
          method: "GET",
        },
      };

      const typedResponse = response as any;
      if (typedResponse.success) {
        yield {
          type: "FETCH_CERTIFICATE_TEMPLATES_SUCCESS",
          payload: { templates: typedResponse.data },
        };
      } else {
        yield {
          type: "FETCH_CERTIFICATE_TEMPLATES_ERROR",
          payload: {
            error: createCertificateError(
              "TEMPLATES_FETCH_FAILED",
              typedResponse.message || __("Failed to fetch certificate templates.", "tutorpress")
            ),
          },
        };
      }
    } catch (error: any) {
      yield {
        type: "FETCH_CERTIFICATE_TEMPLATES_ERROR",
        payload: {
          error: createCertificateError(
            "NETWORK_ERROR",
            error.message || __("Network error while fetching templates.", "tutorpress"),
            { originalError: error }
          ),
        },
      };
    }
  },

  *getCertificateSelection(courseId: number): Generator<unknown, void, unknown> {
    yield { type: "FETCH_CERTIFICATE_SELECTION_START", payload: { courseId } };

    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/certificate/selection/${courseId}`,
          method: "GET",
        },
      };

      const typedResponse = response as any;
      if (typedResponse.success) {
        yield {
          type: "FETCH_CERTIFICATE_SELECTION_SUCCESS",
          payload: { selection: typedResponse.data },
        };
      } else {
        yield {
          type: "FETCH_CERTIFICATE_SELECTION_ERROR",
          payload: {
            error: createCertificateError(
              "SELECTION_FETCH_FAILED",
              typedResponse.message || __("Failed to fetch certificate selection.", "tutorpress")
            ),
          },
        };
      }
    } catch (error: any) {
      yield {
        type: "FETCH_CERTIFICATE_SELECTION_ERROR",
        payload: {
          error: createCertificateError(
            "NETWORK_ERROR",
            error.message || __("Network error while fetching selection.", "tutorpress"),
            { originalError: error }
          ),
        },
      };
    }
  },

  *saveCertificateSelection(courseId: number, templateKey: string): Generator<unknown, void, unknown> {
    yield { type: "SAVE_CERTIFICATE_SELECTION_START", payload: { courseId, templateKey } };

    try {
      const response = yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/certificate/save",
          method: "POST",
          data: {
            course_id: courseId,
            template_key: templateKey,
          },
        },
      };

      const typedResponse = response as any;
      if (typedResponse.success) {
        yield {
          type: "SAVE_CERTIFICATE_SELECTION_SUCCESS",
          payload: { selection: typedResponse.data },
        };
      } else {
        yield {
          type: "SAVE_CERTIFICATE_SELECTION_ERROR",
          payload: {
            error: createCertificateError(
              "SELECTION_SAVE_FAILED",
              typedResponse.message || __("Failed to save certificate selection.", "tutorpress")
            ),
          },
        };
      }
    } catch (error: any) {
      yield {
        type: "SAVE_CERTIFICATE_SELECTION_ERROR",
        payload: {
          error: createCertificateError(
            "NETWORK_ERROR",
            error.message || __("Network error while saving selection.", "tutorpress"),
            { originalError: error }
          ),
        },
      };
    }
  },
};

// ============================================================================
// STORE REGISTRATION
// ============================================================================

const storeConfig = {
  reducer,
  actions: {
    ...actions,
    ...resolvers,
  },
  selectors,
  controls,
};

// Create and register the store
const store = createReduxStore("tutorpress/certificate", storeConfig);
register(store);

// Export the store for external use
export default store;
export { selectors, actions };
export type { CertificateState };
