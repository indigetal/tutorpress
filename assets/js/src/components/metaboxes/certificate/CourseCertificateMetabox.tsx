/**
 * Course Certificate Metabox Component
 *
 * Course-specific wrapper for BaseCertificateMetabox that handles:
 * - Course store integration (selectors/actions)
 * - Course certificate selection logic
 * - Portrait orientation safety net (courses always use portrait)
 *
 * This component manages all store interactions and passes data/actions to
 * the reusable BaseCertificateMetabox component via props.
 *
 * @package TutorPress
 * @subpackage Components/Metaboxes/Certificate
 * @since 1.0.0
 */

import React, { useEffect } from "react";
import { useSelect, useDispatch } from "@wordpress/data";
import { __ } from "@wordpress/i18n";

// Components
import { BaseCertificateMetabox } from "./BaseCertificateMetabox";

// Types
import type { CertificateTemplate } from "../../../types/certificate";

// Utils
import { isCertificateBuilderEnabled } from "../../../utils/addonChecker";

// Store constant
const CERTIFICATE_STORE = "tutorpress/certificate";

// ============================================================================
// Course Certificate Metabox Component
// ============================================================================

/**
 * CourseCertificateMetabox Component
 *
 * Serves as the wrapper that:
 * 1. Extracts courseId from URL parameters
 * 2. Connects to certificate store (course-specific selectors/actions)
 * 3. Manages course-specific logic (portrait orientation safety net)
 * 4. Renders BaseCertificateMetabox with all required props
 */
export const CourseCertificateMetabox: React.FC = (): JSX.Element | null => {
  // ============================================================================
  // Early Exit: Check if Certificate addon is enabled
  // ============================================================================

  if (!(window.tutorpressAddons?.certificate ?? false)) {
    return null;
  }

  // ============================================================================
  // Extract Course ID from URL
  // ============================================================================

  const urlParams = new URLSearchParams(window.location.search);
  const courseId = parseInt(urlParams.get("post") || "0", 10);

  // ============================================================================
  // Store Integration: Selectors
  // ============================================================================

  const {
    templates,
    filteredTemplates,
    filters,
    isLoading,
    hasError,
    error,
    selection,
    isSelectionSaving,
    previewModal,
  } = useSelect((select) => {
    const certificateStore = select(CERTIFICATE_STORE) as any;
    return {
      // Template data
      templates: certificateStore.getCertificateTemplates(),
      filteredTemplates: certificateStore.getFilteredCertificateTemplates(),
      filters: certificateStore.getCertificateFilters(),

      // Template loading states
      isLoading: certificateStore.isCertificateTemplatesLoading(),
      hasError: certificateStore.hasCertificateTemplatesError(),
      error: certificateStore.getCertificateTemplatesError(),

      // Course-specific selection data and states
      selection: certificateStore.getCertificateSelection(),
      isSelectionSaving: certificateStore.isCertificateSelectionSaving(),

      // Preview modal state
      previewModal: certificateStore.getCertificatePreview(),
    };
  }, []);

  // ============================================================================
  // Store Integration: Actions
  // ============================================================================

  const {
    getCertificateTemplates,
    setCertificateFilters,
    getCertificateSelection,
    saveCertificateSelection,
    openCertificatePreview,
    closeCertificatePreview,
  } = useDispatch(CERTIFICATE_STORE) as any;

  // ============================================================================
  // Data Loading on Mount
  // ============================================================================

  useEffect(() => {
    // Load available certificate templates
    getCertificateTemplates();

    // Load current course certificate selection
    if (courseId > 0) {
      getCertificateSelection(courseId);
    }

    // Initialize filter state
    // Force portrait orientation for courses (not landscape)
    setCertificateFilters({
      orientation: "portrait",
      type: "templates",
      include_none: true,
    });
  }, [courseId, getCertificateTemplates, getCertificateSelection, setCertificateFilters]);

  // ============================================================================
  // Portrait Orientation Safety Net
  // ============================================================================

  /**
   * Ensure portrait orientation is maintained for courses
   * This is a safety net to prevent the orientation from being changed to "all" or "landscape"
   */
  useEffect(() => {
    if (filters.orientation === "all") {
      setCertificateFilters({
        ...filters,
        orientation: "portrait",
      });
    }
  }, [filters.orientation, setCertificateFilters]);

  // ============================================================================
  // Event Handlers
  // ============================================================================

  /**
   * Handle template selection
   * Saves the selected template to the course
   */
  const handleTemplateSelect = (template: CertificateTemplate) => {
    if (courseId > 0) {
      saveCertificateSelection(courseId, template.key);
    }
  };

  /**
   * Handle preview modal navigation
   * Excludes "none" template from navigation
   */
  const handlePreviewNavigation = (direction: "prev" | "next") => {
    if (!previewModal.template || !filteredTemplates) return;

    // Filter out "None" templates from navigation
    const previewableTemplates = filteredTemplates.filter(
      (t: CertificateTemplate) => t.key !== "none"
    );
    const currentIndex = previewableTemplates.findIndex(
      (t: CertificateTemplate) => t.key === previewModal.template?.key
    );

    if (currentIndex === -1) return; // Current template not found in previewable templates

    let newIndex;
    if (direction === "prev") {
      newIndex = currentIndex > 0 ? currentIndex - 1 : previewableTemplates.length - 1;
    } else {
      newIndex =
        currentIndex < previewableTemplates.length - 1 ? currentIndex + 1 : 0;
    }

    const newTemplate = previewableTemplates[newIndex];
    if (newTemplate) {
      openCertificatePreview(newTemplate);
    }
  };

  // ============================================================================
  // Render Base Component with Props
  // ============================================================================

  return (
    <BaseCertificateMetabox
      // Post context
      postId={courseId}
      postTypeLabel="course"
      // Template data
      templates={templates}
      filteredTemplates={filteredTemplates}
      filters={filters}
      selectedTemplate={selection?.selectedTemplate || null}
      // UI states
      isLoading={isLoading}
      hasError={hasError}
      error={error}
      isSelectionSaving={isSelectionSaving}
      previewModal={previewModal}
      // Event handlers
      onTemplateSelect={handleTemplateSelect}
      onTemplatePreview={openCertificatePreview}
      onOrientationChange={(orientation) =>
        setCertificateFilters({ ...filters, orientation })
      }
      onTabChange={(tab) => setCertificateFilters({ ...filters, type: tab })}
      onPreviewClose={closeCertificatePreview}
      onPreviewNavigate={handlePreviewNavigation}
      // Customization
      showCertificateBuilderLink={true}
      description="Select a certificate below for the course"
    />
  );
};

export default CourseCertificateMetabox;
