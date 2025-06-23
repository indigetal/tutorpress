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
  DateTimePicker,
  Button,
  Notice,
  Spinner,
  Card,
  CardBody,
  FormTokenField,
  SelectControl,
} from "@wordpress/components";

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

  // Get content drip info and prerequisites from store
  const { contentDripInfo, prerequisites, isLoadingSettings, isLoadingPrerequisites, error, saving, saveError } =
    useSelect(
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
        };
      },
      [postId, courseId]
    );

  // Local state for course content drip settings
  const [courseContentDripSettings, setCourseContentDripSettings] = useState<{
    enabled: boolean;
    type: string;
  } | null>(null);

  // Get actions from store
  const { getContentDripSettings, getPrerequisites, updateContentDripSettings, getCourseContentDripSettings } =
    useDispatch("tutorpress/additional-content");

  // Check if content drip is enabled at course level
  const isContentDripEnabled = courseContentDripSettings?.enabled === true;

  // Get course content drip type
  const courseContentDripType = courseContentDripSettings?.type || null;

  // Load lightweight course content drip settings on mount
  useEffect(() => {
    if (courseId) {
      // Use lightweight endpoint to get only course content drip settings
      getCourseContentDripSettings(courseId)
        .then((settings: any) => {
          setCourseContentDripSettings(settings);
        })
        .catch((error: any) => {
          console.error("Failed to load course content drip settings:", error);
          setCourseContentDripSettings({ enabled: false, type: "unlock_by_date" });
        });
    }
  }, [courseId, getCourseContentDripSettings]);

  // Load post-level content drip settings
  useEffect(() => {
    if (postId) {
      getContentDripSettings(postId);
    }
  }, [postId, getContentDripSettings]);

  // Load prerequisites when content drip is enabled and we need to show the prerequisites field
  useEffect(() => {
    if (
      courseId &&
      courseContentDripSettings?.enabled &&
      courseContentDripSettings?.type === "after_finishing_prerequisites"
    ) {
      getPrerequisites(courseId);
    }
  }, [courseId, courseContentDripSettings?.enabled, courseContentDripSettings?.type, getPrerequisites]);

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
          <Notice status="info" isDismissible={false}>
            <p>
              <strong>{__("Sequential Content Drip", "tutorpress")}</strong>
            </p>
            <p>
              {__(
                "This content will be unlocked automatically based on the curriculum order. No additional settings are needed.",
                "tutorpress"
              )}
            </p>
          </Notice>
        </CardBody>
      </Card>
    );
  }

  return (
    <Card className={`content-drip-panel ${className}`}>
      <CardBody>
        <h3 className="content-drip-panel__title">{__("Content Drip Settings", "tutorpress")}</h3>

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
              <DateTimePicker
                currentDate={localSettings.unlock_date || ""}
                onChange={handleDateChange}
                is12Hour={true}
              />
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
                {__(
                  "Select content items that must be completed before this item becomes available. Enhanced multi-select interface for better UX.",
                  "tutorpress"
                )}
              </p>

              {/* Show selected prerequisites grouped by topic */}
              {prerequisites && localSettings.prerequisites && localSettings.prerequisites.length > 0 && (
                <div className="content-drip-panel__prerequisites">
                  <strong className="content-drip-panel__prerequisites-label">
                    {__("Selected Prerequisites:", "tutorpress")}
                  </strong>
                  {prerequisites.map((topic: PrerequisitesByTopic) => {
                    const topicPrerequisites = topic.items.filter((item: PrerequisiteItem) =>
                      localSettings.prerequisites?.includes(item.id)
                    );

                    if (topicPrerequisites.length === 0) return null;

                    return (
                      <div key={topic.topic_id} className="content-drip-panel__topic">
                        <div className="content-drip-panel__topic-title">{topic.topic_title}</div>
                        {topicPrerequisites.map((item: PrerequisiteItem) => (
                          <div key={item.id} className="content-drip-panel__prerequisite-item">
                            {item.title} ({item.type_label})
                          </div>
                        ))}
                      </div>
                    );
                  })}
                </div>
              )}
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
