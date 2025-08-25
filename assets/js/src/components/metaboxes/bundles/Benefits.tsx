/**
 * Bundle Benefits Metabox Component
 *
 * Manages the "What Will I Learn?" field for course bundles.
 * Uses the dedicated course-bundles store following the exact pattern
 * from Course Additional Content metabox.
 *
 * @package TutorPress
 * @subpackage Components/Metaboxes/Bundles
 * @since 1.0.0
 */
import React, { useEffect, useCallback, useRef } from "react";
import { TextareaControl, Spinner, Flex, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";

// Store constant
const COURSE_BUNDLES_STORE = "tutorpress/course-bundles";

/**
 * Bundle Benefits component
 */
const Benefits: React.FC = (): JSX.Element => {
  // Get bundle ID from data attribute (following established TutorPress pattern)
  const container = document.getElementById("tutorpress-bundle-benefits-root");
  const bundleId = container ? parseInt(container.getAttribute("data-post-id") || "0", 10) : 0;

  // Bundle Benefits store selectors
  const { data, isLoading, isSaving, isDirty, hasError, error, editorIsSaving } = useSelect((select) => {
    const bundleStore = select(COURSE_BUNDLES_STORE) as any;
    const coreEditor = select("core/editor") as any;
    return {
      data: bundleStore.getBundleBenefitsData(),
      isLoading: bundleStore.getBundleBenefitsLoading(),
      isSaving: bundleStore.getBundleBenefitsSaving(),
      isDirty: bundleStore.hasBundleBenefitsUnsavedChanges(),
      hasError: bundleStore.getBundleBenefitsError() !== null,
      error: bundleStore.getBundleBenefitsError(),
      editorIsSaving: coreEditor?.isSavingPost?.() || false,
    };
  }, []);

  // Bundle Benefits store actions
  const { fetchBundleBenefits, saveBundleBenefits, updateBundleBenefits } = useDispatch(COURSE_BUNDLES_STORE) as any;

  // Load data on mount
  useEffect(() => {
    if (bundleId > 0) {
      fetchBundleBenefits(bundleId);
    }
  }, [bundleId, fetchBundleBenefits]);

  // Update hidden form fields so they're available when the post is saved
  useEffect(() => {
    updateHiddenFormFields();
  }, [isDirty, data]);

  // Persist via REST when the editor initiates a save
  const prevEditorIsSaving = useRef<boolean>(false);
  useEffect(() => {
    if (bundleId > 0 && isDirty && !prevEditorIsSaving.current && editorIsSaving) {
      // Fire-and-forget; REST controller mirrors to Tutor LMS meta
      saveBundleBenefits(bundleId, data);
    }
    prevEditorIsSaving.current = editorIsSaving;
  }, [bundleId, editorIsSaving, isDirty, data, saveBundleBenefits]);

  // Update hidden form fields for WordPress save_post hook (matches Course Additional Content pattern)
  const updateHiddenFormFields = useCallback(() => {
    const container = document.getElementById("tutorpress-bundle-benefits-root");
    if (!container) return;

    // Update or create hidden form field
    const fieldName = "tutorpress_bundle_benefits";
    let field = document.querySelector(`input[name="${fieldName}"]`) as HTMLInputElement;
    if (!field) {
      field = document.createElement("input");
      field.type = "hidden";
      field.name = fieldName;
      container.appendChild(field);
    }
    field.value = data?.benefits || "";
  }, [data]);

  // Handle benefits change
  const handleBenefitsChange = (value: string) => {
    updateBundleBenefits(value);
  };

  // Handle error dismissal
  const handleErrorDismiss = () => {
    // Clear error through store action if available
    // For now, we'll rely on automatic error clearing on successful operations
  };

  // =============================
  // Render Methods
  // =============================

  // Render loading state
  if (isLoading) {
    return (
      <div className="tutorpress-bundle-benefits">
        <Flex direction="column" align="center" gap={2} style={{ padding: "var(--space-xl)" }}>
          <Spinner />
          <div>{__("Loading bundle benefits...", "tutorpress")}</div>
        </Flex>
      </div>
    );
  }

  // Render error state
  if (hasError && error) {
    return (
      <div className="tutorpress-bundle-benefits">
        <Notice status="error" onRemove={handleErrorDismiss} isDismissible={true}>
          {error}
        </Notice>
      </div>
    );
  }

  return (
    <div className="tutorpress-bundle-benefits">
      {/* What Will I Learn Field */}
      <div className="tutorpress-bundle-benefits__field">
        <TextareaControl
          label={__("What Will I Learn?", "tutorpress")}
          value={data?.benefits || ""}
          onChange={handleBenefitsChange}
          placeholder={__("Define key takeaways from this bundle (list one benefit per line)", "tutorpress")}
          rows={4}
        />
      </div>
    </div>
  );
};

export default Benefits;
