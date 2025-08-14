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

// Hooks
import { useAdditionalContentData, useContentDripSettings } from "../../hooks/common";

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

  // Step E: Use new separate hooks for additional content data and content drip settings
  const { additionalContentData, ready: textDataReady } = useAdditionalContentData();
  const { contentDripSettings, ready: dripSettingsReady } = useContentDripSettings();

  // Check if both data sources are ready
  const entityReady = textDataReady && dripSettingsReady;

  // Additional Content store selectors (for writes and other state)
  const { isLoading, isSaving, isDirty, hasError, error, isContentDripAddonAvailable, editorIsSaving } = useSelect(
    (select) => {
      const additionalContentStore = select(ADDITIONAL_CONTENT_STORE) as any;
      const coreEditor = select("core/editor") as any;
      return {
        isLoading: additionalContentStore.isLoading(),
        isSaving: additionalContentStore.isSaving(),
        isDirty: additionalContentStore.hasUnsavedChanges(),
        hasError: additionalContentStore.hasError(),
        error: additionalContentStore.getError(),
        isContentDripAddonAvailable: additionalContentStore.isContentDripAddonAvailable(),
        editorIsSaving: coreEditor?.isSavingPost?.() || false,
      };
    },
    []
  );

  // Extract data from new hooks
  const data = additionalContentData || {
    what_will_learn: "",
    target_audience: "",
    requirements: "",
  };

  // Extract content drip settings from new hook
  const contentDrip = contentDripSettings || {
    enabled: false,
    type: "unlock_by_date",
  };

  // Additional Content store actions (for writes and other operations)
  const {
    saveAdditionalContent,
    updateWhatWillILearn,
    updateTargetAudience,
    updateRequirements,
    updateContentDripEnabled,
    updateContentDripType,
    clearError,
  } = useDispatch(ADDITIONAL_CONTENT_STORE) as any;

  // Step D: No need to fetch data since we're reading from entity prop
  // The useEntityProp hook automatically loads the data from the REST API

  // Integrate with Gutenberg's dirty state system and update hidden form fields
  useEffect(() => {
    if (isDirty && (window as any).wp?.data) {
      // Mark the post as having unsaved changes so Gutenberg shows the save prompt
      const { editPost } = (window as any).wp.data.dispatch("core/editor");
      if (editPost) {
        // Trigger a meta update to mark the post as dirty
        editPost({ meta: { _tutorpress_additional_content_dirty: Date.now() } });
      }
    }

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

  // Render loading state (show if store is loading OR entity prop is not ready)
  if (isLoading || !entityReady) {
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
