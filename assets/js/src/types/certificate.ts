/**
 * Certificate Types for TutorPress
 *
 * Type definitions for certificate template management and selection.
 * Follows TutorPress established typing patterns.
 *
 * @package TutorPress
 * @since 0.1.0
 */

// ============================================================================
// CORE CERTIFICATE INTERFACES
// ============================================================================

/**
 * Certificate Template from Tutor LMS
 */
export interface CertificateTemplate {
  /** Template key (e.g., 'default', 'template_1', 'none') */
  key: string;
  /** Template slug (same as key for compatibility) */
  slug: string;
  /** Display name of the template */
  name: string;
  /** Template orientation: landscape or portrait */
  orientation: "landscape" | "portrait";
  /** Whether this is a default template */
  is_default: boolean;
  /** Local template path */
  path: string;
  /** Local template URL */
  url: string;
  /** Preview image URL */
  preview_src: string;
  /** Background image URL */
  background_src: string;
}

/**
 * Certificate Selection for a Course
 */
export interface CertificateSelection {
  /** Course ID */
  course_id: number;
  /** Selected template key */
  template_key: string;
  /** Meta key used in WordPress */
  meta_key: string;
}

/**
 * Certificate Template Filters
 */
export interface CertificateFilters {
  /** Filter by orientation */
  orientation?: "landscape" | "portrait" | "all";
  /** Filter by template type */
  type?: "templates" | "custom_templates" | "all";
  /** Include none/off options */
  include_none?: boolean;
}

// ============================================================================
// OPERATION STATE INTERFACES
// ============================================================================

/**
 * Base Operation State
 */
export interface CertificateOperationState {
  status: "idle" | "loading" | "saving" | "error" | "success";
  error?: CertificateError;
}

/**
 * Template Loading State
 */
export interface CertificateTemplateState extends CertificateOperationState {
  /** Available templates */
  templates: CertificateTemplate[];
  /** Applied filters */
  filters: CertificateFilters;
  /** Templates filtered by current criteria */
  filteredTemplates: CertificateTemplate[];
}

/**
 * Selection State for Current Course
 */
export interface CertificateSelectionState extends CertificateOperationState {
  /** Current course ID */
  courseId: number | null;
  /** Currently selected template */
  selectedTemplate: string | null;
  /** Whether selection has been modified */
  isDirty: boolean;
}

/**
 * Preview Modal State
 */
export interface CertificatePreviewState {
  /** Whether preview modal is open */
  isOpen: boolean;
  /** Template being previewed */
  template: CertificateTemplate | null;
  /** Loading state for preview */
  isLoading: boolean;
}

// ============================================================================
// ERROR HANDLING
// ============================================================================

/**
 * Certificate Error Codes
 */
export type CertificateErrorCode =
  | "ADDON_DISABLED"
  | "TEMPLATES_FETCH_FAILED"
  | "TEMPLATE_NOT_FOUND"
  | "SELECTION_SAVE_FAILED"
  | "SELECTION_FETCH_FAILED"
  | "PERMISSION_DENIED"
  | "INVALID_COURSE_ID"
  | "INVALID_TEMPLATE_KEY"
  | "NETWORK_ERROR"
  | "UNKNOWN_ERROR";

/**
 * Certificate Error Interface
 */
export interface CertificateError {
  /** Error code for programmatic handling */
  code: CertificateErrorCode;
  /** Human-readable error message */
  message: string;
  /** Additional error details */
  details?: Record<string, any>;
  /** Timestamp of error */
  timestamp?: Date;
}

// ============================================================================
// API RESPONSE INTERFACES
// ============================================================================

/**
 * Templates API Response
 */
export interface CertificateTemplatesResponse {
  success: boolean;
  message: string;
  data: CertificateTemplate[];
}

/**
 * Selection API Response
 */
export interface CertificateSelectionResponse {
  success: boolean;
  message: string;
  data: CertificateSelection;
}

/**
 * Save Selection API Response
 */
export interface CertificateSaveResponse {
  success: boolean;
  message: string;
  data: CertificateSelection;
}

// ============================================================================
// COMPONENT PROPS INTERFACES
// ============================================================================

/**
 * Template Card Props
 */
export interface CertificateTemplateCardProps {
  /** Template data */
  template: CertificateTemplate;
  /** Whether this template is selected */
  isSelected: boolean;
  /** Selection handler */
  onSelect: (template: CertificateTemplate) => void;
  /** Preview handler */
  onPreview: (template: CertificateTemplate) => void;
  /** Whether the card is disabled */
  disabled?: boolean;
}

/**
 * Template Grid Props
 */
export interface CertificateTemplateGridProps {
  /** Templates to display */
  templates: CertificateTemplate[];
  /** Currently selected template key */
  selectedTemplate: string | null;
  /** Template selection handler */
  onSelectTemplate: (templateKey: string) => void;
  /** Template preview handler */
  onPreviewTemplate: (template: CertificateTemplate) => void;
  /** Loading state */
  isLoading?: boolean;
  /** Error state */
  error?: CertificateError | null;
}

/**
 * Certificate Filters Props
 */
export interface CertificateFiltersProps {
  /** Current filters */
  filters: CertificateFilters;
  /** Filter change handler */
  onFiltersChange: (filters: CertificateFilters) => void;
  /** Available template counts for each filter */
  templateCounts?: {
    landscape: number;
    portrait: number;
    templates: number;
    custom: number;
  };
}

/**
 * Preview Modal Props
 */
export interface CertificatePreviewModalProps {
  /** Whether modal is open */
  isOpen: boolean;
  /** Template being previewed */
  template: CertificateTemplate | null;
  /** Close handler */
  onClose: () => void;
  /** Select template from preview */
  onSelect?: (template: CertificateTemplate) => void;
}

// ============================================================================
// UTILITY TYPES
// ============================================================================

/**
 * Certificate Template Grouped by Orientation
 */
export interface CertificateTemplateGroups {
  landscape: CertificateTemplate[];
  portrait: CertificateTemplate[];
}

/**
 * Certificate Tab Types
 */
export type CertificateTab = "templates" | "custom";

/**
 * Certificate Action Status
 */
export type CertificateActionStatus = "idle" | "pending" | "fulfilled" | "rejected";

/**
 * Helper type for API responses
 */
export type CertificateApiResponse<T = any> = {
  success: boolean;
  message: string;
  data: T;
};
