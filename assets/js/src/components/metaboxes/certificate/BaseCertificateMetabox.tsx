/**
 * Base Certificate Metabox Component
 *
 * Reusable, presentational certificate selection UI that works for any post type.
 * Pure props-based component with no store dependencies - parent handles all data and actions.
 *
 * Architecture:
 * - Presentational component (no store hooks, no side effects)
 * - Configuration-driven (all behavior controlled via props)
 * - Type-safe (comprehensive TypeScript interface)
 * - Composable (uses CertificateCard and CertificatePreviewModal)
 * - Extensible (additionalContent injection point)
 *
 * This component is designed to be reusable across different post types (courses, bundles, etc.)
 * by having all post-type-specific logic injected via props.
 *
 * @package TutorPress
 * @subpackage Components/Metaboxes/Certificate
 * @since 1.0.0
 */

import React from "react";
import { TabPanel, Spinner, Flex, FlexBlock } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

// Components
import { CertificateCard } from "./CertificateCard";
import CertificatePreviewModal from "../../modals/certificate/CertificatePreviewModal";

// Types
import type { CertificateTemplate, CertificateFilters, CertificatePreviewState } from "../../../types/certificate";

// ============================================================================
// Component Props Interface
// ============================================================================

export interface BaseCertificateMetaboxProps {
  // ============================================================================
  // Post Context
  // ============================================================================
  /** Post ID (course ID, bundle ID, etc.) */
  postId: number;

  /** Post type label for display text (e.g., "course", "bundle") */
  postTypeLabel: string;

  // ============================================================================
  // Template Data
  // ============================================================================
  /** All available certificate templates */
  templates: CertificateTemplate[] | null;

  /** Filtered templates based on current filters */
  filteredTemplates: CertificateTemplate[] | null;

  /** Currently selected template key */
  selectedTemplate: string | null;

  // ============================================================================
  // UI State
  // ============================================================================
  /** Current filter state (orientation, type) */
  filters: CertificateFilters;

  /** Whether templates are loading */
  isLoading: boolean;

  /** Whether there's a template loading error */
  hasError: boolean;

  /** Template loading error message */
  error: string | null;

  /** Whether selection is saving */
  isSelectionSaving: boolean;

  /** Preview modal state */
  previewModal: CertificatePreviewState;

  // ============================================================================
  // User Actions (Parent Handlers)
  // ============================================================================
  /** Called when user selects a template */
  onTemplateSelect: (template: CertificateTemplate) => void;

  /** Called when user clicks preview button */
  onTemplatePreview: (template: CertificateTemplate) => void;

  /** Called when user changes orientation filter */
  onOrientationChange: (orientation: "portrait" | "landscape") => void;

  /** Called when user switches tabs */
  onTabChange: (tab: "templates" | "custom_templates") => void;

  /** Called when user closes preview modal */
  onPreviewClose: () => void;

  /** Called when user navigates in preview modal */
  onPreviewNavigate: (direction: "prev" | "next") => void;

  // ============================================================================
  // Customization
  // ============================================================================
  /** Custom description text (defaults to "Select a certificate below for the {postTypeLabel}") */
  description?: string;

  /** Additional content to inject after certificate grid (e.g., bundle toggle) */
  additionalContent?: React.ReactNode;

  /** Whether to show certificate builder link */
  showCertificateBuilderLink?: boolean;

  /** Custom CSS class */
  className?: string;
}

// ============================================================================
// Component Implementation
// ============================================================================

/**
 * BaseCertificateMetabox Component
 *
 * Pure presentational component for certificate selection UI.
 * All interactions are handled via callback props passed from parent.
 */
export const BaseCertificateMetabox: React.FC<BaseCertificateMetaboxProps> = ({
  postId,
  postTypeLabel,
  templates,
  filteredTemplates,
  selectedTemplate,
  filters,
  isLoading,
  hasError,
  error,
  isSelectionSaving,
  previewModal,
  onTemplateSelect,
  onTemplatePreview,
  onOrientationChange,
  onTabChange,
  onPreviewClose,
  onPreviewNavigate,
  description,
  additionalContent,
  showCertificateBuilderLink = false,
  className = "tutorpress-certificate",
}): JSX.Element | null => {
  // ============================================================================
  // Helper: Check if template is selected
  // ============================================================================

  const isTemplateSelected = (template: CertificateTemplate): boolean => {
    return selectedTemplate === template.key;
  };

  // ============================================================================
  // Render: Loading State
  // ============================================================================

  if (isLoading) {
    return (
      <div className={className}>
        <Flex direction="column" align="center" gap={2} style={{ padding: "var(--space-xl)" }}>
          <Spinner />
          <div>{__("Loading certificate templates...", "tutorpress")}</div>
        </Flex>
      </div>
    );
  }

  // ============================================================================
  // Render: Error State
  // ============================================================================

  if (hasError) {
    return (
      <div className={className}>
        <div className={`${className}__error`}>
          <p>{__("Error loading certificate templates:", "tutorpress")}</p>
          <p>{error || __("Unknown error occurred", "tutorpress")}</p>
        </div>
      </div>
    );
  }

  // ============================================================================
  // Render: Main Content
  // ============================================================================

  const defaultDescription = __(`Select a certificate below for the ${postTypeLabel}`, "tutorpress");
  const displayDescription = description || defaultDescription;

  return (
    <div className={className}>
      {/* Metabox Header */}
      <div className={`${className}__header`}>
        <p className={`${className}__description`}>
          {displayDescription}
          {showCertificateBuilderLink && (
            <>
              {__(" or ", "tutorpress")}
              <a
                href="?action=tutor_certificate_builder"
                target="_blank"
                rel="noopener noreferrer"
                style={{ marginLeft: 2 }}
              >
                {__("create a new certificate here", "tutorpress")}
              </a>
              {__(".", "tutorpress")}
            </>
          )}
        </p>
      </div>

      {/* Template Tabs with Orientation Filters */}
      <div className={`${className}__tabs-container`}>
        {/* Orientation Filters - positioned on right side of tabs */}
        <div className={`${className}__orientation-filters`}>
          <div className={`${className}__filter-group`}>
            <button
              type="button"
              className={`${className}__filter-button ${filters.orientation === "portrait" ? "is-active" : ""}`}
              onClick={() => onOrientationChange("portrait")}
            >
              {__("Portrait", "tutorpress")}
            </button>
            <button
              type="button"
              className={`${className}__filter-button ${filters.orientation === "landscape" ? "is-active" : ""}`}
              onClick={() => onOrientationChange("landscape")}
            >
              {__("Landscape", "tutorpress")}
            </button>
          </div>
        </div>

        <TabPanel
          className={`${className}__tabs`}
          activeClass="is-active"
          initialTabName="templates"
          onSelect={(tab) => onTabChange(tab as "templates" | "custom_templates")}
          tabs={[
            {
              name: "templates",
              title: __("Templates", "tutorpress"),
              className: `${className}__tab`,
            },
            {
              name: "custom_templates",
              title: __("Custom Templates", "tutorpress"),
              className: `${className}__tab`,
            },
          ]}
        >
          {(tab) => (
            <div className={`${className}__tab-content`}>
              {/* Template Grid */}
              <div className={`${className}__grid`}>
                {filteredTemplates && filteredTemplates.length > 0 ? (
                  <div className={`${className}__grid-content`}>
                    <p>
                      {__("Found", "tutorpress")} {filteredTemplates.length} {__("templates", "tutorpress")}
                    </p>
                    {/* Certificate Template Cards */}
                    <div className={`${className}__cards`}>
                      {filteredTemplates.map((template: CertificateTemplate) => (
                        <CertificateCard
                          key={template.key}
                          template={template}
                          isSelected={isTemplateSelected(template)}
                          onSelect={onTemplateSelect}
                          onPreview={onTemplatePreview}
                          disabled={isSelectionSaving}
                          isLoading={isSelectionSaving && selectedTemplate === template.key}
                        />
                      ))}
                    </div>
                  </div>
                ) : (
                  <div className={`${className}__empty`}>
                    <p>{__("No templates found for the selected criteria.", "tutorpress")}</p>
                  </div>
                )}
              </div>

              {/* Selection Status */}
              {selectedTemplate && (
                <div className={`${className}__selection-status`}>
                  <p>
                    {isSelectionSaving
                      ? __("Saving selection...", "tutorpress")
                      : (() => {
                          const selected = templates?.find((t: CertificateTemplate) => t.key === selectedTemplate);
                          const templateName = selected?.name || selectedTemplate;
                          return __("Selected: ", "tutorpress") + templateName;
                        })()}
                  </p>
                </div>
              )}

              {/* Loading state for selection */}
              {isSelectionSaving && (
                <div className={`${className}__selection-loading`}>
                  <Spinner />
                  <span>{__("Saving selection...", "tutorpress")}</span>
                </div>
              )}
            </div>
          )}
        </TabPanel>
      </div>

      {/* Additional Content Injection Point */}
      {additionalContent}

      {/* Preview Modal */}
      <CertificatePreviewModal
        isOpen={previewModal.isOpen}
        template={previewModal.template}
        onClose={onPreviewClose}
        onSelect={onTemplateSelect}
        onNavigate={onPreviewNavigate}
        canNavigate={
          filteredTemplates ? filteredTemplates.filter((t: CertificateTemplate) => t.key !== "none").length > 1 : false
        }
      />
    </div>
  );
};

export default BaseCertificateMetabox;
