/**
 * Additional Content Metabox Component
 *
 * Implements the additional course content fields UI for course management.
 * Uses WordPress Data store for state management and follows established
 * TutorPress component patterns.
 *
 * Features:
 * - What Will I Learn textarea field
 * - Target Audience textarea field
 * - Requirements/Instructions textarea field
 * - Conditional Content Drip settings based on addon availability
 * - Integration with Gutenberg's save system
 * - Loading and error states
 * - Integration with course meta for persistence
 *
 * State Management:
 * - Additional content fields managed through additional-content store
 * - Loading and error states handled through established patterns
 * - Integration with course meta for persistence
 *
 * @package TutorPress
 * @subpackage Components/Metaboxes
 * @since 1.0.0
 */
import React, { useEffect, useCallback, useRef } from "react";
import { TextareaControl, Spinner, Flex, FlexBlock, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";

// Components
import { ContentDripSettings } from "./additional-content/ContentDripSettings";

// Types
import type {
  AdditionalContentData,
  ContentDripSettings as ContentDripSettingsType,
} from "../../types/additional-content";

// Store constant
const ADDITIONAL_CONTENT_STORE = "tutorpress/additional-content";

// ============================================================================
// Additional Content Metabox Component
// ============================================================================

/**
 * Main Additional Content component for managing course additional content fields.
 *
 * Features:
 * - Three main textarea fields for course additional information
 * - Conditional Content Drip settings section
 * - Integration with Gutenberg's native save system
 * - Loading and error states with proper feedback
 * - Integration with WordPress Data store
 *
 * State Management:
 * - Uses additional-content store for global state (data, content drip, etc.)
 * - Follows established TutorPress data flow patterns
 */
const AdditionalContent: React.FC = (): JSX.Element => {
  // Get course ID from data attribute (following established TutorPress pattern)
  const container = document.getElementById("tutorpress-additional-content-root");
  const courseId = container ? parseInt(container.getAttribute("data-post-id") || "0", 10) : 0;

  // Additional Content store selectors
  const {
    data,
    contentDrip,
    isLoading,
    isSaving,
    isDirty,
    hasError,
    error,
    isContentDripAddonAvailable,
    editorIsSaving,
  } = useSelect((select) => {
    const additionalContentStore = select(ADDITIONAL_CONTENT_STORE) as any;
    const coreEditor = select("core/editor") as any;
    return {
      data: additionalContentStore.getAdditionalContentData(),
      contentDrip: additionalContentStore.getContentDripSettings(),
      isLoading: additionalContentStore.isLoading(),
      isSaving: additionalContentStore.isSaving(),
      isDirty: additionalContentStore.hasUnsavedChanges(),
      hasError: additionalContentStore.hasError(),
      error: additionalContentStore.getError(),
      isContentDripAddonAvailable: additionalContentStore.isContentDripAddonAvailable(),
      editorIsSaving: coreEditor?.isSavingPost?.() || false,
    };
  }, []);

  // Additional Content store actions
  const {
    fetchAdditionalContent,
    saveAdditionalContent,
    updateWhatWillILearn,
    updateTargetAudience,
    updateRequirements,
    updateContentDripEnabled,
    updateContentDripType,
    clearError,
  } = useDispatch(ADDITIONAL_CONTENT_STORE) as any;

  // Load data on mount
  useEffect(() => {
    if (courseId > 0) {
      fetchAdditionalContent(courseId);
    }
  }, [courseId, fetchAdditionalContent]);

  // Update hidden form fields so they're available when the post is saved
  useEffect(() => {
    // Update hidden form fields so they're available when the post is saved
    updateHiddenFormFields();
  }, [isDirty, data, contentDrip]);

  // Persist via REST when the editor initiates a save
  const prevEditorIsSaving = useRef<boolean>(false);
  useEffect(() => {
    if (courseId > 0 && isDirty && !prevEditorIsSaving.current && editorIsSaving) {
      // Fire-and-forget; REST controller mirrors to Tutor LMS meta
      (saveAdditionalContent as any)(courseId, data, contentDrip);
    }
    prevEditorIsSaving.current = editorIsSaving;
  }, [courseId, editorIsSaving, isDirty, data, contentDrip, saveAdditionalContent]);

  // Update hidden form fields for WordPress save_post hook
  const updateHiddenFormFields = useCallback(() => {
    const container = document.getElementById("tutorpress-additional-content-root");
    if (!container) return;

    // Update or create hidden form fields
    const fields = [
      { name: "tutorpress_what_will_learn", value: data.what_will_learn },
      { name: "tutorpress_target_audience", value: data.target_audience },
      { name: "tutorpress_requirements", value: data.requirements },
      { name: "tutorpress_content_drip_enabled", value: contentDrip.enabled ? "1" : "0" },
      // Only send content drip type if content drip is enabled
      { name: "tutorpress_content_drip_type", value: contentDrip.enabled ? contentDrip.type : "" },
    ];

    fields.forEach(({ name, value }) => {
      let field = document.querySelector(`input[name="${name}"]`) as HTMLInputElement;
      if (!field) {
        field = document.createElement("input");
        field.type = "hidden";
        field.name = name;
        container.appendChild(field);
      }
      field.value = value || "";
    });
  }, [data, contentDrip]);

  // Handle field changes
  const handleWhatWillLearnChange = (value: string) => {
    updateWhatWillILearn(value);
  };

  const handleTargetAudienceChange = (value: string) => {
    updateTargetAudience(value);
  };

  const handleRequirementsChange = (value: string) => {
    updateRequirements(value);
  };

  const handleContentDripEnabledChange = (enabled: boolean) => {
    updateContentDripEnabled(enabled);
  };

  const handleContentDripTypeChange = (type: ContentDripSettingsType["type"]) => {
    updateContentDripType(type);
  };

  // Handle error dismissal
  const handleErrorDismiss = () => {
    clearError();
  };

  // =============================
  // Render Methods
  // =============================

  // Render loading state
  if (isLoading) {
    return (
      <div className="tutorpress-additional-content">
        <Flex direction="column" align="center" gap={2} style={{ padding: "var(--space-xl)" }}>
          <Spinner />
          <div>{__("Loading additional content...", "tutorpress")}</div>
        </Flex>
      </div>
    );
  }

  // Render error state
  if (hasError && error) {
    return (
      <div className="tutorpress-additional-content">
        <Notice status="error" onRemove={handleErrorDismiss} isDismissible={true}>
          {error}
        </Notice>
      </div>
    );
  }

  return (
    <div className="tutorpress-additional-content">
      {/* Main content fields */}
      <div className="tutorpress-additional-content__fields">
        {/* What Will I Learn Field */}
        <div className="tutorpress-additional-content__field">
          <TextareaControl
            label={__("What Will I Learn", "tutorpress")}
            value={data.what_will_learn}
            onChange={handleWhatWillLearnChange}
            placeholder={__("Define key takeaways from this course (list one benefit per line)", "tutorpress")}
            rows={4}
          />
        </div>

        {/* Target Audience Field */}
        <div className="tutorpress-additional-content__field">
          <TextareaControl
            label={__("Target Audience", "tutorpress")}
            value={data.target_audience}
            onChange={handleTargetAudienceChange}
            placeholder={__(
              "Specify the target audience that will benefit from the course. (One Line Per target audience)",
              "tutorpress"
            )}
            rows={4}
          />
        </div>

        {/* Requirements/Instructions Field */}
        <div className="tutorpress-additional-content__field">
          <TextareaControl
            label={__("Requirements/Instructions", "tutorpress")}
            value={data.requirements}
            onChange={handleRequirementsChange}
            placeholder={__(
              "Additional requirements or special instructions for the students (One Per Line)",
              "tutorpress"
            )}
            rows={4}
          />
        </div>
      </div>

      {/* Content Drip Settings (always show when addon is available) */}
      {isContentDripAddonAvailable && (
        <div className="tutorpress-additional-content__content-drip">
          <h3 className="tutorpress-additional-content__section-title">{__("Content Drip Settings", "tutorpress")}</h3>
          <p className="tutorpress-additional-content__section-description">
            {__("You can schedule your course content using one of the following Content Drip options.", "tutorpress")}
          </p>

          <ContentDripSettings
            enabled={contentDrip.enabled}
            type={contentDrip.type}
            onEnabledChange={handleContentDripEnabledChange}
            onTypeChange={handleContentDripTypeChange}
            isDisabled={false}
            showDescription={true}
          />
        </div>
      )}
    </div>
  );
};

export default AdditionalContent;
