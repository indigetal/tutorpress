/**
 * Generic Content Drip Panel Component
 *
 * Reusable component for content drip settings on both lessons and assignments.
 * Uses TypeScript generics and conditional rendering based on course content drip type.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import React, { useEffect, useState, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import {
  PanelRow,
  TextControl,
  Button,
  Notice,
  Spinner,
  Card,
  CardBody,
  FormTokenField,
  Flex,
  FlexItem,
  Icon,
} from "@wordpress/components";
import { info } from "@wordpress/icons";

// Import types
import type {
  ContentDripPanelProps,
  ContentDripItemSettings,
  ContentDripInfo,
  PrerequisitesByTopic,
  PrerequisiteItem,
} from "../../types/content-drip";

// Import hooks
import { useCourseId } from "../../hooks/curriculum/useCourseId";

/**
 * Generic Content Drip Panel Component
 *
 * @template T - Post type ("lesson" | "tutor_assignments")
 */
function ContentDripPanel<T extends "lesson" | "tutor_assignments">({
  postType,
  courseId,
  postId,
  settings,
  onSettingsChange,
  isDisabled = false,
  className = "",
}: ContentDripPanelProps<T>) {
  // Local state for form management - start with default values, will be updated when store loads
  const [localSettings, setLocalSettings] = useState<ContentDripItemSettings>({
    unlock_date: "",
    after_xdays_of_enroll: 0,
    prerequisites: [],
  });
  const [selectedPrerequisiteTokens, setSelectedPrerequisiteTokens] = useState<string[]>([]);
  const [hasInitialized, setHasInitialized] = useState(false);

  // Get content drip info and prerequisites from store, including course-level settings
  const {
    contentDripInfo,
    prerequisites,
    isLoadingSettings,
    isLoadingPrerequisites,
    error,
    saving,
    saveError,
    isContentDripEnabled,
    courseContentDripType,
  } = useSelect(
    (select: any) => {
      const store = select("tutorpress/additional-content");

      return {
        contentDripInfo: store.getContentDripInfoForPost(postId),
        prerequisites: store.getPrerequisitesForCourse(courseId),
        isLoadingSettings: store.isContentDripLoadingForPost(postId),
        isLoadingPrerequisites: store.isPrerequisitesLoadingForCourse(courseId),
        error: store.getContentDripErrorForPost(postId),
        saving: store.isContentDripSavingForPost(postId),
        saveError: store.getContentDripSaveErrorForPost(postId),
        // Use existing store selectors for course-level settings (no separate API call needed)
        isContentDripEnabled: store.isContentDripEnabled(),
        courseContentDripType: store.getContentDripType(),
      };
    },
    [postId, courseId]
  );

  // Get actions from store
  const { getContentDripSettings, getPrerequisites, updateContentDripSettings, fetchAdditionalContent } = useDispatch(
    "tutorpress/additional-content"
  );

  // Load course-level additional content (includes content drip settings)
  useEffect(() => {
    if (courseId) {
      fetchAdditionalContent(courseId);
    }
  }, [courseId, fetchAdditionalContent]);

  // Load post-level content drip settings
  useEffect(() => {
    if (postId) {
      getContentDripSettings(postId);
    }
  }, [postId, getContentDripSettings]);

  // Load prerequisites when content drip is enabled and we need to show the prerequisites field
  useEffect(() => {
    if (courseId && isContentDripEnabled && courseContentDripType === "after_finishing_prerequisites") {
      getPrerequisites(courseId);
    }
  }, [courseId, isContentDripEnabled, courseContentDripType, getPrerequisites]);

  // Update local settings when store settings change, or use passed settings as fallback
  useEffect(() => {
    if (contentDripInfo?.settings) {
      // Use store settings if available (loaded from API)
      setLocalSettings(contentDripInfo.settings);
      setHasInitialized(true);
    } else if (!hasInitialized && !isLoadingSettings) {
      // Use passed settings as fallback if store hasn't loaded anything yet
      setLocalSettings(settings);
      setHasInitialized(true);
    }
  }, [contentDripInfo?.settings, settings, hasInitialized, isLoadingSettings]);

  // Update prerequisite tokens when prerequisites or settings change
  useEffect(() => {
    if (prerequisites && localSettings.prerequisites) {
      const tokens = localSettings.prerequisites
        .map((id) => {
          // Find the prerequisite item across all topics
          for (const topic of prerequisites) {
            const item = topic.items.find((item: PrerequisiteItem) => item.id === id);
            if (item) {
              return `${item.title} (${item.type_label})`;
            }
          }
          return null;
        })
        .filter(Boolean) as string[];

      setSelectedPrerequisiteTokens(tokens);
    }
  }, [prerequisites, localSettings.prerequisites]);

  // Handle settings change with debouncing
  const handleSettingsChange = useCallback(
    (newSettings: Partial<ContentDripItemSettings>) => {
      const updatedSettings = { ...localSettings, ...newSettings };
      setLocalSettings(updatedSettings);

      // Debounce the actual save operation
      const timeoutId = setTimeout(() => {
        onSettingsChange(updatedSettings);
        updateContentDripSettings(postId, updatedSettings);
      }, 500);

      return () => clearTimeout(timeoutId);
    },
    [localSettings, onSettingsChange, postId, updateContentDripSettings]
  );

  // Handle date change
  const handleDateChange = useCallback(
    (date: string | null) => {
      handleSettingsChange({ unlock_date: date || "" });
    },
    [handleSettingsChange]
  );

  // Handle days change
  const handleDaysChange = useCallback(
    (value: string) => {
      const days = parseInt(value) || 0;
      handleSettingsChange({ after_xdays_of_enroll: days });
    },
    [handleSettingsChange]
  );

  // Handle prerequisite selection
  const handlePrerequisiteChange = useCallback(
    (tokens: (string | any)[]) => {
      if (!prerequisites) return;

      // Convert tokens to strings
      const stringTokens = tokens.map((token) => (typeof token === "string" ? token : token.value || ""));
      setSelectedPrerequisiteTokens(stringTokens);

      // Convert tokens back to IDs
      const selectedIds: number[] = [];

      stringTokens.forEach((token) => {
        for (const topic of prerequisites) {
          const item = topic.items.find((item: PrerequisiteItem) => `${item.title} (${item.type_label})` === token);
          if (item) {
            selectedIds.push(item.id);
            break;
          }
        }
      });

      handleSettingsChange({ prerequisites: selectedIds });
    },
    [prerequisites, handleSettingsChange]
  );

  // Get available prerequisite suggestions
  const getPrerequisiteSuggestions = useCallback(() => {
    if (!prerequisites) return [];

    const suggestions: string[] = [];
    prerequisites.forEach((topic: PrerequisitesByTopic) => {
      topic.items.forEach((item: PrerequisiteItem) => {
        // Don't include the current post as a prerequisite for itself
        if (item.id !== postId) {
          suggestions.push(`${item.title} (${item.type_label})`);
        }
      });
    });

    return suggestions;
  }, [prerequisites, postId]);

  // Don't render if content drip is not enabled at course level
  if (!isContentDripEnabled) {
    return null;
  }

  // Show loading state while fetching settings or waiting for initialization
  if (isLoadingSettings || !hasInitialized) {
    return (
      <Card className={`content-drip-panel ${className}`}>
        <CardBody>
          <div className="content-drip-panel__loading">
            <Spinner />
            <span className="content-drip-panel__loading-text">
              {__("Loading content drip settings...", "tutorpress")}
            </span>
          </div>
        </CardBody>
      </Card>
    );
  }

  // Show error state
  if (error) {
    return (
      <Card className={`content-drip-panel ${className}`}>
        <CardBody>
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        </CardBody>
      </Card>
    );
  }

  // Determine what fields to show based on course content drip type
  const showDateField = courseContentDripType === "unlock_by_date";
  const showDaysField = courseContentDripType === "specific_days";
  const showPrerequisitesField = courseContentDripType === "after_finishing_prerequisites";
  const isSequential = courseContentDripType === "unlock_sequentially";

  // For sequential content drip, show info message only
  if (isSequential) {
    return (
      <Card className={`content-drip-panel ${className}`}>
        <CardBody>
          <h3 className="content-drip-panel__title">{__("Content Drip", "tutorpress")}</h3>
          <div className="content-drip-panel__sequential-notice">
            <Flex align="flex-start" gap={2}>
              <FlexItem>
                <Icon icon={info} className="content-drip-panel__info-icon" size={16} />
              </FlexItem>
              <FlexItem>
                <p className="content-drip-panel__sequential-text">
                  {__(
                    "Course set to Sequential Content Drip. Content will be unlocked based on curriculum order.",
                    "tutorpress"
                  )}
                </p>
              </FlexItem>
            </Flex>
          </div>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card className={`content-drip-panel ${className}`}>
      <CardBody>
        <h3 className="content-drip-panel__title">{__("Content Drip", "tutorpress")}</h3>

        {saveError && (
          <Notice status="error" isDismissible={false}>
            {saveError}
          </Notice>
        )}

        {/* Date Picker Field */}
        {showDateField && (
          <PanelRow>
            <div className="content-drip-panel__field">
              <label className="content-drip-panel__label">{__("Unlock Date", "tutorpress")}</label>
              <div className="content-drip-panel__date-field">
                <TextControl
                  type="date"
                  value={localSettings.unlock_date ? localSettings.unlock_date.split("T")[0] : ""}
                  onChange={(value) => handleDateChange(value ? `${value}T00:00:00` : "")}
                  disabled={isDisabled || saving}
                />
              </div>
              <p className="content-drip-panel__help">
                {__(
                  "This content will be available from the given date. Leave empty to make it available immediately.",
                  "tutorpress"
                )}
              </p>
            </div>
          </PanelRow>
        )}

        {/* Days Input Field */}
        {showDaysField && (
          <PanelRow>
            <div className="content-drip-panel__field">
              <TextControl
                label={__("Available after days", "tutorpress")}
                type="number"
                min="0"
                value={localSettings.after_xdays_of_enroll?.toString() || "0"}
                onChange={handleDaysChange}
                disabled={isDisabled || saving}
                help={__(
                  "This content will be available after the given number of days from enrollment.",
                  "tutorpress"
                )}
              />
            </div>
          </PanelRow>
        )}

        {/* Prerequisites Multi-Select Field */}
        {showPrerequisitesField && (
          <PanelRow>
            <div className="content-drip-panel__field">
              <label className="content-drip-panel__label">{__("Prerequisites", "tutorpress")}</label>

              {isLoadingPrerequisites ? (
                <div className="content-drip-panel__loading">
                  <Spinner />
                  <span className="content-drip-panel__loading-text">
                    {__("Loading available prerequisites...", "tutorpress")}
                  </span>
                </div>
              ) : (
                <div
                  className="content-drip-panel__form-token-wrapper"
                  onFocus={(e) => {
                    // Prevent TinyMCE from interfering with this component
                    e.stopPropagation();
                  }}
                  onClick={(e) => {
                    // Prevent TinyMCE event bubbling
                    e.stopPropagation();
                  }}
                >
                  <FormTokenField
                    value={selectedPrerequisiteTokens}
                    suggestions={getPrerequisiteSuggestions()}
                    onChange={handlePrerequisiteChange}
                    placeholder={__("Search for content items...", "tutorpress")}
                    disabled={isDisabled || saving}
                    __experimentalExpandOnFocus
                    __experimentalShowHowTo={false}
                  />
                </div>
              )}

              <p className="content-drip-panel__help">
                {__("Select content items that must be completed before this item becomes available.", "tutorpress")}
              </p>
            </div>
          </PanelRow>
        )}

        {/* Saving indicator */}
        {saving && (
          <div className="content-drip-panel__saving">
            <Spinner />
            <span className="content-drip-panel__saving-text">
              {__("Saving content drip settings...", "tutorpress")}
            </span>
          </div>
        )}
      </CardBody>
    </Card>
  );
}

export default ContentDripPanel;
