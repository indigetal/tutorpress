/**
 * Certificate Metabox Component
 *
 * Implements the certificate template selection UI for course management.
 * Uses WordPress Data store for state management and follows established
 * TutorPress component patterns.
 *
 * State Management:
 * - Certificate templates and selection managed through certificate store
 * - Loading and error states handled through established patterns
 * - Integration with course meta for persistence
 *
 * @package TutorPress
 * @subpackage Components/Metaboxes
 * @since 1.0.0
 */
import React, { useEffect } from "react";
import { TabPanel, Spinner, Flex, FlexBlock } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";

// Types
import type { CertificateTemplate, CertificateFilters } from "../../types/certificate";

// Store constant
const CERTIFICATE_STORE = "tutorpress/certificate";

// ============================================================================
// Certificate Metabox Component
// ============================================================================

/**
 * Main Certificate component for managing course certificate template selection.
 *
 * Features:
 * - Template/Custom Template tabs using WordPress TabPanel
 * - Landscape/Portrait orientation filtering
 * - Template grid with selection functionality
 * - Integration with WordPress Data store
 * - Loading and error states
 *
 * State Management:
 * - Uses certificate store for global state (templates, selection, etc.)
 * - Follows established TutorPress data flow patterns
 */
const Certificate: React.FC = (): JSX.Element => {
  // Get course ID from URL parameters (following Curriculum pattern)
  const urlParams = new URLSearchParams(window.location.search);
  const courseId = Number(urlParams.get("post"));

  // Certificate store selectors
  const {
    templates,
    filteredTemplates,
    filters,
    selection,
    isTemplatesLoading,
    hasTemplatesError,
    templatesError,
    isSelectionLoading,
    hasSelectionError,
    selectionError,
  } = useSelect((select) => {
    const certificateStore = select(CERTIFICATE_STORE) as any;
    return {
      templates: certificateStore.getCertificateTemplates(),
      filteredTemplates: certificateStore.getFilteredCertificateTemplates(),
      filters: certificateStore.getCertificateFilters(),
      selection: certificateStore.getCertificateSelection(),
      isTemplatesLoading: certificateStore.isCertificateTemplatesLoading(),
      hasTemplatesError: certificateStore.hasCertificateTemplatesError(),
      templatesError: certificateStore.getCertificateTemplatesError(),
      isSelectionLoading: certificateStore.isCertificateSelectionLoading(),
      hasSelectionError: certificateStore.hasCertificateSelectionError(),
      selectionError: certificateStore.getCertificateSelectionError(),
    };
  }, []);

  // Certificate store actions
  const { getCertificateTemplates, getCertificateSelection, setCertificateFilters } = useDispatch(
    CERTIFICATE_STORE
  ) as any;

  // Load data on mount
  useEffect(() => {
    // Load templates
    getCertificateTemplates();

    // Load current selection if we have a course ID
    if (courseId > 0) {
      getCertificateSelection(courseId);
    }
  }, [courseId, getCertificateTemplates, getCertificateSelection]);

  // Handle orientation filter change
  const handleOrientationChange = (orientation: "all" | "landscape" | "portrait") => {
    setCertificateFilters({
      ...filters,
      orientation,
    });
  };

  // Handle template type change (tabs)
  const handleTemplateTypeChange = (templateType: "templates" | "custom_templates") => {
    setCertificateFilters({
      ...filters,
      type: templateType,
    });
  };

  // =============================
  // Render Methods
  // =============================

  // Render loading state
  if (isTemplatesLoading) {
    return (
      <div className="tutorpress-certificate">
        <Flex direction="column" align="center" gap={2} style={{ padding: "var(--space-xl)" }}>
          <Spinner />
          <div>{__("Loading certificate templates...", "tutorpress")}</div>
        </Flex>
      </div>
    );
  }

  // Render error state
  if (hasTemplatesError) {
    return (
      <div className="tutorpress-certificate">
        <div className="tutorpress-certificate__error">
          <p>{__("Error loading certificate templates:", "tutorpress")}</p>
          <p>{templatesError?.message || __("Unknown error occurred", "tutorpress")}</p>
        </div>
      </div>
    );
  }

  // Render main content
  return (
    <div className="tutorpress-certificate">
      {/* Metabox Header - Title removed as it's redundant with WordPress metabox title */}
      <div className="tutorpress-certificate__header">
        <p className="tutorpress-certificate__description">
          {__("Select a certificate to award your learners", "tutorpress")}
        </p>
      </div>

      {/* Orientation Filters */}
      <div className="tutorpress-certificate__orientation-filters">
        <div className="tutorpress-certificate__filter-group">
          <button
            type="button"
            className={`tutorpress-certificate__filter-button ${
              filters.orientation === "landscape" ? "is-active" : ""
            }`}
            onClick={() => handleOrientationChange("landscape")}
          >
            {__("Landscape", "tutorpress")}
          </button>
          <button
            type="button"
            className={`tutorpress-certificate__filter-button ${filters.orientation === "portrait" ? "is-active" : ""}`}
            onClick={() => handleOrientationChange("portrait")}
          >
            {__("Portrait", "tutorpress")}
          </button>
        </div>
      </div>

      {/* Template Tabs */}
      <TabPanel
        className="tutorpress-certificate__tabs"
        activeClass="is-active"
        onSelect={(tabName) => handleTemplateTypeChange(tabName as "templates" | "custom_templates")}
        tabs={[
          {
            name: "templates",
            title: __("Templates", "tutorpress"),
            className: "tutorpress-certificate__tab",
          },
          {
            name: "custom_templates",
            title: __("Custom Templates", "tutorpress"),
            className: "tutorpress-certificate__tab",
          },
        ]}
      >
        {(tab) => (
          <div className="tutorpress-certificate__tab-content">
            {/* Template Grid Placeholder */}
            <div className="tutorpress-certificate__grid">
              {filteredTemplates && filteredTemplates.length > 0 ? (
                <div className="tutorpress-certificate__grid-content">
                  <p>
                    {__("Found", "tutorpress")} {filteredTemplates.length} {__("templates", "tutorpress")}
                  </p>
                  {/* Template cards will be implemented in Step 4 */}
                  <div className="tutorpress-certificate__cards">
                    {filteredTemplates.slice(0, 3).map((template: CertificateTemplate) => (
                      <div key={template.key} className="tutorpress-certificate__card-placeholder">
                        <div className="tutorpress-certificate__card-image">
                          <img
                            src={template.preview_src || template.background_src}
                            alt={template.name}
                            style={{ width: "100%", height: "auto" }}
                          />
                        </div>
                        <div className="tutorpress-certificate__card-title">{template.name}</div>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="tutorpress-certificate__empty">
                  <p>{__("No templates found for the selected criteria.", "tutorpress")}</p>
                </div>
              )}
            </div>

            {/* Selection Status */}
            {selection?.selectedTemplate && (
              <div className="tutorpress-certificate__selection-status">
                <p>
                  {__("Selected template:", "tutorpress")} <strong>{selection.selectedTemplate}</strong>
                  {selection.isDirty && (
                    <span className="tutorpress-certificate__unsaved"> ({__("unsaved", "tutorpress")})</span>
                  )}
                </p>
              </div>
            )}

            {/* Loading/Error states for selection */}
            {isSelectionLoading && (
              <div className="tutorpress-certificate__selection-loading">
                <Spinner />
                <span>{__("Saving selection...", "tutorpress")}</span>
              </div>
            )}

            {hasSelectionError && (
              <div className="tutorpress-certificate__selection-error">
                <p>{__("Error saving selection:", "tutorpress")}</p>
                <p>{selectionError?.message || __("Unknown error occurred", "tutorpress")}</p>
              </div>
            )}
          </div>
        )}
      </TabPanel>
    </div>
  );
};

export default Certificate;
