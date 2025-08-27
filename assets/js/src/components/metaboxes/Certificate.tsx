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
import React, { useEffect, useState } from "react";
import { TabPanel, Spinner, Flex, FlexBlock } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";

// Components
import { CertificateCard } from "./certificate/CertificateCard";
import CertificatePreviewModal from "../modals/certificate/CertificatePreviewModal";

// Types
import type { CertificateTemplate, CertificateFilters } from "../../types/certificate";
import { isCertificateBuilderEnabled } from "../../utils/addonChecker";

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
const Certificate: React.FC = (): JSX.Element | null => {
  // Early return if Certificate addon is not enabled
  if (!(window.tutorpressAddons?.certificate ?? false)) {
    return null;
  }

  // Get course ID from URL parameters (following Curriculum pattern)
  const urlParams = new URLSearchParams(window.location.search);
  const courseId = parseInt(urlParams.get("post") || "0", 10);

  // Certificate store selectors
  const {
    templates,
    filteredTemplates,
    filters,
    isLoading,
    hasError,
    error,
    selection,
    isSelectionLoading,
    isSelectionSaving,
    hasSelectionError,
    selectionError,
    previewModal,
  } = useSelect((select) => {
    const certificateStore = select(CERTIFICATE_STORE) as any;
    return {
      templates: certificateStore.getCertificateTemplates(),
      filteredTemplates: certificateStore.getFilteredCertificateTemplates(),
      filters: certificateStore.getCertificateFilters(),
      isLoading: certificateStore.isCertificateTemplatesLoading(),
      hasError: certificateStore.hasCertificateTemplatesError(),
      error: certificateStore.getCertificateTemplatesError(),
      selection: certificateStore.getCertificateSelection(),
      isSelectionLoading: certificateStore.isCertificateSelectionLoading(),
      isSelectionSaving: certificateStore.isCertificateSelectionSaving(),
      hasSelectionError: certificateStore.hasCertificateSelectionError(),
      selectionError: certificateStore.getCertificateSelectionError(),
      previewModal: certificateStore.getCertificatePreview(),
    };
  }, []);

  // Certificate store actions
  const {
    getCertificateTemplates,
    setCertificateFilters,
    getCertificateSelection,
    saveCertificateSelection,
    openCertificatePreview,
    closeCertificatePreview,
  } = useDispatch(CERTIFICATE_STORE) as any;

  // Load data on mount and ensure proper filter initialization
  useEffect(() => {
    // Load templates
    getCertificateTemplates();

    // Load current selection if we have a course ID
    if (courseId > 0) {
      getCertificateSelection(courseId);
    }

    // Force proper filter initialization on component mount
    // This ensures portrait orientation regardless of store state
    setCertificateFilters({
      orientation: "portrait",
      type: "templates",
      include_none: true,
    });
  }, [courseId, getCertificateTemplates, getCertificateSelection, setCertificateFilters]);

  // Additional safety net for orientation reset
  useEffect(() => {
    if (filters.orientation === "all") {
      setCertificateFilters({
        ...filters,
        orientation: "portrait",
      });
    }
  }, [filters.orientation, setCertificateFilters]);

  // Handle orientation filter change
  const handleOrientationFilter = (orientation: "portrait" | "landscape") => {
    setCertificateFilters({
      ...filters,
      orientation,
    });
  };

  // Handle template type change (tabs)
  const handleTabChange = (tabName: string) => {
    setCertificateFilters({
      ...filters,
      type: tabName as "templates" | "custom_templates",
    });
  };

  // Handle template selection
  const handleTemplateSelect = (template: CertificateTemplate) => {
    if (courseId > 0) {
      saveCertificateSelection(courseId, template.key);
    }
  };

  // Handle template preview
  const handleTemplatePreview = (template: CertificateTemplate) => {
    openCertificatePreview(template);
  };

  // Check if template is selected
  const isTemplateSelected = (template: CertificateTemplate): boolean => {
    return selection?.selectedTemplate === template.key;
  };

  // Preview modal navigation - exclude "None" templates from navigation
  const handlePreviewNavigation = (direction: "prev" | "next") => {
    if (!previewModal.template) return;

    // Filter out "None" templates from navigation
    const previewableTemplates = filteredTemplates.filter((t: CertificateTemplate) => t.key !== "none");
    const currentIndex = previewableTemplates.findIndex(
      (t: CertificateTemplate) => t.key === previewModal.template?.key
    );

    if (currentIndex === -1) return; // Current template not found in previewable templates

    let newIndex;
    if (direction === "prev") {
      newIndex = currentIndex > 0 ? currentIndex - 1 : previewableTemplates.length - 1;
    } else {
      newIndex = currentIndex < previewableTemplates.length - 1 ? currentIndex + 1 : 0;
    }

    const newTemplate = previewableTemplates[newIndex];
    if (newTemplate) {
      openCertificatePreview(newTemplate);
    }
  };

  // =============================
  // Render Methods
  // =============================

  // Render loading state
  if (isLoading) {
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
  if (hasError) {
    return (
      <div className="tutorpress-certificate">
        <div className="tutorpress-certificate__error">
          <p>{__("Error loading certificate templates:", "tutorpress")}</p>
          <p>{error?.message || __("Unknown error occurred", "tutorpress")}</p>
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
          {__("Select a certificate below for the course", "tutorpress")}
          {isCertificateBuilderEnabled() && (
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
      <div className="tutorpress-certificate__tabs-container">
        {/* Orientation Filters - positioned on right side of tabs */}
        <div className="tutorpress-certificate__orientation-filters">
          <div className="tutorpress-certificate__filter-group">
            <button
              type="button"
              className={`tutorpress-certificate__filter-button ${filters.orientation === "portrait" ? "is-active" : ""}`}
              onClick={() => handleOrientationFilter("portrait")}
            >
              {__("Portrait", "tutorpress")}
            </button>
            <button
              type="button"
              className={`tutorpress-certificate__filter-button ${
                filters.orientation === "landscape" ? "is-active" : ""
              }`}
              onClick={() => handleOrientationFilter("landscape")}
            >
              {__("Landscape", "tutorpress")}
            </button>
          </div>
        </div>

        <TabPanel
          className="tutorpress-certificate__tabs"
          activeClass="is-active"
          initialTabName="templates"
          onSelect={handleTabChange}
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
                    {/* Certificate Template Cards */}
                    <div className="tutorpress-certificate__cards">
                      {filteredTemplates.map((template: CertificateTemplate) => (
                        <CertificateCard
                          key={template.key}
                          template={template}
                          isSelected={isTemplateSelected(template)}
                          onSelect={handleTemplateSelect}
                          onPreview={handleTemplatePreview}
                          disabled={isSelectionSaving}
                          isLoading={isSelectionSaving && selection?.selectedTemplate === template.key}
                        />
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
                    {isSelectionSaving
                      ? __("Saving selection...", "tutorpress")
                      : selection.isDirty
                        ? __("Changes not saved.", "tutorpress")
                        : (() => {
                            const selectedTemplate = templates?.find(
                              (t: CertificateTemplate) => t.key === selection.selectedTemplate
                            );
                            const templateName = selectedTemplate?.name || selection.selectedTemplate;
                            return __("Selected: ", "tutorpress") + templateName;
                          })()}
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

      {/* Preview Modal */}
      <CertificatePreviewModal
        isOpen={previewModal.isOpen}
        template={previewModal.template}
        onClose={closeCertificatePreview}
        onSelect={handleTemplateSelect}
        onNavigate={handlePreviewNavigation}
        canNavigate={filteredTemplates.filter((t: CertificateTemplate) => t.key !== "none").length > 1}
      />
    </div>
  );
};

export default Certificate;
