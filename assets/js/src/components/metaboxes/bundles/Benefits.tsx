/**
 * Bundle Benefits Metabox Component
 *
 * Implements the "What Will I Learn" field for course bundles in Gutenberg.
 * Uses the exact same pattern as Additional Content metabox for consistency.
 *
 * Features:
 * - What Will I Learn textarea field
 * - Integration with Gutenberg's save system
 * - Loading and error states
 * - Integration with bundle meta for persistence
 *
 * State Management:
 * - Uses course-bundles store for global state (following Additional Content pattern)
 * - Loading and error states handled through established patterns
 * - Integration with bundle meta for persistence
 *
 * @package TutorPress
 * @subpackage Components/Metaboxes/Bundles
 * @since 1.0.0
 */
import React, { useEffect, useCallback } from "react";
import { TextareaControl, Spinner, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";

// Store constant
const COURSE_BUNDLES_STORE = "tutorpress/course-bundles";

// ============================================================================
// Bundle Benefits Metabox Component
// ============================================================================

/**
 * Main Bundle Benefits component for managing bundle benefits field.
 *
 * Features:
 * - What Will I Learn textarea field for bundle benefits
 * - Integration with Gutenberg's native save system
 * - Loading and error states with proper feedback
 * - Integration with WordPress Data Store
 *
 * State Management:
 * - Uses course-bundles store for global state (following Additional Content pattern)
 * - Follows established TutorPress data flow patterns
 */
const Benefits: React.FC = (): JSX.Element => {
  // Get bundle ID from data attribute (following established TutorPress pattern)
  const container = document.getElementById("tutorpress-bundle-benefits-root");
  const postId = container ? parseInt(container.getAttribute("data-post-id") || "0", 10) : 0;

  // Course Bundles store selectors (following Additional Content pattern)
  const { data, isLoading, isSaving, isDirty, hasError, error } = useSelect((select) => {
    const courseBundlesStore = select(COURSE_BUNDLES_STORE) as any;
    return {
      data: courseBundlesStore.getBundleBenefitsData(),
      isLoading: courseBundlesStore.getBundleBenefitsLoading(),
      isSaving: courseBundlesStore.getBundleBenefitsSaving(),
      isDirty: courseBundlesStore.hasBundleBenefitsUnsavedChanges(),
      hasError: courseBundlesStore.getBundleBenefitsError() !== null,
      error: courseBundlesStore.getBundleBenefitsError(),
    };
  }, []);

  // Course Bundles store actions (following Additional Content pattern)
  const { fetchBundleBenefits, updateBundleBenefits, clearError } = useDispatch(COURSE_BUNDLES_STORE) as any;

  // Load data on mount (following Additional Content pattern)
  useEffect(() => {
    if (postId > 0) {
      fetchBundleBenefits(postId);
    }
  }, [postId, fetchBundleBenefits]);

  // Integrate with Gutenberg's dirty state system and update hidden form fields (following Additional Content pattern)
  useEffect(() => {
    if (isDirty && (window as any).wp?.data) {
      // Mark the post as having unsaved changes so Gutenberg shows the save prompt
      const { editPost } = (window as any).wp.data.dispatch("core/editor");
      if (editPost) {
        // Trigger a meta update to mark the post as dirty
        editPost({ meta: { _tutorpress_bundle_benefits_dirty: Date.now() } });
      }
    }

    // Update hidden form fields so they're available when the post is saved
    updateHiddenFormFields();
  }, [isDirty, data]);

  // Update hidden form fields for WordPress save_post hook (following Additional Content pattern)
  const updateHiddenFormFields = useCallback(() => {
    const container = document.getElementById("tutorpress-bundle-benefits-root");
    if (!container) return;

    // Update or create hidden form field
    let field = document.querySelector(`input[name="tutorpress_bundle_benefits"]`) as HTMLInputElement;
    if (!field) {
      field = document.createElement("input");
      field.type = "hidden";
      field.name = "tutorpress_bundle_benefits";
      container.appendChild(field);
    }
    field.value = data?.benefits || "";
  }, [data]);

  // Handle benefits change (following Additional Content pattern)
  const handleBenefitsChange = (value: string) => {
    updateBundleBenefits(value);
  };

  // Handle error dismissal (following Additional Content pattern)
  const handleErrorDismiss = () => {
    clearError();
  };

  // =============================
  // Render Methods
  // =============================

  // Render loading state
  if (isLoading) {
    return (
      <div className="tutorpress-bundle-benefits">
        <div style={{ textAlign: "center", padding: "var(--space-xl)" }}>
          <Spinner />
          <div>{__("Loading bundle benefits...", "tutorpress")}</div>
        </div>
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
