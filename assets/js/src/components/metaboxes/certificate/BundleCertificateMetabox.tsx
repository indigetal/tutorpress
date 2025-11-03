/**
 * Bundle Certificate Metabox Component
 *
 * Bundle-specific wrapper for BaseCertificateMetabox that handles:
 * - Bundle store integration (selectors/actions)
 * - Bundle certificate selection and toggle logic
 * - Bundle-specific save logic (includes allow_individual_certificates)
 * - Bundle toggle UI via additionalContent prop
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
import { ToggleControl } from "@wordpress/components";
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
// Bundle Certificate Toggle Component (Inline)
// ============================================================================

/**
 * Bundle Certificate Toggle Component
 *
 * Allows instructors to control whether individual courses within a bundle
 * can award their own certificates, or if only the bundle completion
 * certificate should be awarded.
 */
interface BundleCertificateToggleProps {
  value: "0" | "1";
  onChange: (value: "0" | "1") => void;
  disabled?: boolean;
  help?: string;
}

const BundleCertificateToggle: React.FC<BundleCertificateToggleProps> = ({
  value,
  onChange,
  disabled = false,
  help,
}) => {
  const isChecked = value === "1";

  const handleChange = (checked: boolean) => {
    onChange(checked ? "1" : "0");
  };

  const defaultHelp = isChecked
    ? __("Individual courses in this bundle can award their own certificates upon completion.", "tutorpress")
    : __(
        "Only the bundle completion certificate will be awarded. Individual courses will not issue certificates.",
        "tutorpress"
      );

  return (
    <ToggleControl
      label={__("Allow Individual Course Certificates", "tutorpress")}
      help={help || defaultHelp}
      checked={isChecked}
      onChange={handleChange}
      disabled={disabled}
    />
  );
};

// ============================================================================
// Bundle Certificate Metabox Component
// ============================================================================

/**
 * BundleCertificateMetabox Component
 *
 * Serves as the wrapper that:
 * 1. Extracts bundleId from URL parameters
 * 2. Connects to certificate store (bundle-specific selectors/actions)
 * 3. Manages bundle-specific logic (save handler, toggle logic)
 * 4. Renders BaseCertificateMetabox with all required props
 * 5. Injects bundle toggle via additionalContent prop
 */
export const BundleCertificateMetabox: React.FC = (): JSX.Element | null => {
  // ============================================================================
  // Early Exit: Check if Certificate addon is enabled
  // ============================================================================

  if (!(window.tutorpressAddons?.certificate ?? false)) {
    return null;
  }

  // ============================================================================
  // Extract Bundle ID from URL
  // ============================================================================

  const urlParams = new URLSearchParams(window.location.search);
  const bundleId = parseInt(urlParams.get("post") || "0", 10);

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
    bundleSelection,
    isSelectionLoading,
    isSelectionSaving,
    hasSelectionError,
    selectionError,
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

      // Bundle-specific selection data and states
      bundleSelection: certificateStore.getBundleCertificateSelection(),
      isSelectionLoading: certificateStore.isBundleCertificateLoading(),
      isSelectionSaving: certificateStore.isBundleCertificateSaving(),
      hasSelectionError: certificateStore.hasBundleCertificateError(),
      selectionError: certificateStore.getBundleCertificateError(),

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
    getBundleCertificateSelection,
    saveBundleCertificate,
    setBundleCertificateToggle,
    openCertificatePreview,
    closeCertificatePreview,
  } = useDispatch(CERTIFICATE_STORE) as any;

  // ============================================================================
  // Data Loading on Mount
  // ============================================================================

  useEffect(() => {
    // Load available certificate templates
    getCertificateTemplates();

    // Load current bundle certificate selection
    if (bundleId > 0) {
      getBundleCertificateSelection(bundleId);
    }

    // Initialize filter state (default to portrait orientation, templates tab)
    setCertificateFilters({
      orientation: "portrait",
      type: "templates",
      include_none: true,
    });
  }, [bundleId, getCertificateTemplates, getBundleCertificateSelection, setCertificateFilters]);

  // ============================================================================
  // Event Handlers
  // ============================================================================

  /**
   * Handle template selection
   * Saves the selected template along with current toggle value
   */
  const handleTemplateSelect = (template: CertificateTemplate) => {
    if (bundleId > 0 && bundleSelection) {
      saveBundleCertificate({
        bundle_id: bundleId,
        template_key: template.key,
        allow_individual_certificates: bundleSelection.allowIndividualCertificates || "1",
      });
    }
  };

  /**
   * Handle preview modal navigation
   * Excludes "none" template from navigation
   */
  const handlePreviewNavigation = (direction: "prev" | "next") => {
    if (!previewModal.template || !filteredTemplates) return;

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

  // ============================================================================
  // Bundle Toggle Content (Injected via additionalContent prop)
  // ============================================================================

  /**
   * Render bundle certificate toggle
   * Only visible when a template is selected (not "none")
   * Allows instructors to control individual course certificates
   */
  const bundleToggleContent =
    bundleSelection?.selectedTemplate && bundleSelection.selectedTemplate !== "none" ? (
      <div className="tutorpress-certificate__toggle-section">
        <h3>{__("Individual Course Certificates", "tutorpress")}</h3>
        <BundleCertificateToggle
          value={bundleSelection.allowIndividualCertificates}
          onChange={(value) => {
            if (bundleId > 0 && bundleSelection) {
              // Update toggle state in store
              setBundleCertificateToggle(value);

              // Save updated toggle state with current template selection
              saveBundleCertificate({
                bundle_id: bundleId,
                template_key: bundleSelection.selectedTemplate || "none",
                allow_individual_certificates: value,
              });
            }
          }}
          disabled={isSelectionSaving}
        />
      </div>
    ) : null;

  // ============================================================================
  // Render Base Component with Props
  // ============================================================================

  return (
    <BaseCertificateMetabox
      // Post context
      postId={bundleId}
      postTypeLabel="bundle"
      // Template data
      templates={templates}
      filteredTemplates={filteredTemplates}
      filters={filters}
      selectedTemplate={bundleSelection?.selectedTemplate || null}
      // UI states
      isLoading={isLoading}
      hasError={hasError}
      error={error}
      isSelectionSaving={isSelectionSaving}
      previewModal={previewModal}
      // Event handlers
      onTemplateSelect={handleTemplateSelect}
      onTemplatePreview={openCertificatePreview}
      onOrientationChange={(orientation) => setCertificateFilters({ ...filters, orientation })}
      onTabChange={(tab) => setCertificateFilters({ ...filters, type: tab })}
      onPreviewClose={closeCertificatePreview}
      onPreviewNavigate={handlePreviewNavigation}
      // Customization
      additionalContent={bundleToggleContent}
      showCertificateBuilderLink={isCertificateBuilderEnabled()}
    />
  );
};

export default BundleCertificateMetabox;
