/**
 * Content Drip Settings Component
 *
 * Provides UI controls for managing content drip settings within the
 * Additional Content metabox. Includes enable/disable checkbox and
 * content drip type selection with radio buttons.
 *
 * Features:
 * - Enable/disable content drip checkbox
 * - Content drip type radio button group
 * - Conditional rendering and disabled states
 * - Descriptive help text for each option
 * - Integration with Additional Content store
 *
 * @package TutorPress
 * @subpackage Components/Metaboxes/AdditionalContent
 * @since 1.0.0
 */
import React from "react";
import { CheckboxControl, RadioControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

// Types
import type {
  ContentDripSettingsProps,
  ContentDripSettings as ContentDripSettingsType,
} from "../../../types/additional-content";

// Content drip type options
const CONTENT_DRIP_TYPE_OPTIONS = [
  {
    label: __("Schedule course contents by date", "tutorpress"),
    value: "unlock_by_date",
    description: __("Content becomes available on specific dates you set for each lesson.", "tutorpress"),
  },
  {
    label: __("Content available after X days from enrollment", "tutorpress"),
    value: "specific_days",
    description: __("Content unlocks after a specified number of days from when the student enrolled.", "tutorpress"),
  },
  {
    label: __("Course content available sequentially", "tutorpress"),
    value: "unlock_sequentially",
    description: __("Students must complete lessons in order before accessing the next one.", "tutorpress"),
  },
  {
    label: __("Course content unlocked after finishing prerequisites", "tutorpress"),
    value: "after_finishing_prerequisites",
    description: __("Content unlocks only after students complete prerequisite courses or lessons.", "tutorpress"),
  },
];

// ============================================================================
// Content Drip Settings Component
// ============================================================================

/**
 * Content Drip Settings component for managing content release timing.
 *
 * Props:
 * - enabled: Whether content drip is currently enabled
 * - type: Current content drip type selection
 * - onEnabledChange: Callback for enable/disable changes
 * - onTypeChange: Callback for type selection changes
 * - isDisabled: Whether controls should be disabled (e.g., during saving)
 * - showDescription: Whether to show descriptive text for options
 */
export const ContentDripSettings: React.FC<ContentDripSettingsProps> = ({
  enabled,
  type,
  onEnabledChange,
  onTypeChange,
  isDisabled = false,
  showDescription = true,
}) => {
  return (
    <div className="tutorpress-content-drip-settings">
      {/* Enable/Disable Content Drip */}
      <div className="tutorpress-content-drip-settings__enable">
        <CheckboxControl
          label={__("Enable Content Drip", "tutorpress")}
          help={__("Control when course content becomes available to students.", "tutorpress")}
          checked={enabled}
          onChange={onEnabledChange}
          disabled={isDisabled}
        />
      </div>

      {/* Content Drip Type Selection (only when enabled) */}
      {enabled && (
        <div className="tutorpress-content-drip-settings__type">
          <RadioControl
            label={__("Content Drip Type", "tutorpress")}
            help={__("Choose how you want to control the release of course content.", "tutorpress")}
            selected={type}
            options={CONTENT_DRIP_TYPE_OPTIONS.map((option) => ({
              label: option.label,
              value: option.value,
            }))}
            onChange={(value: string) => onTypeChange(value as ContentDripSettingsType["type"])}
            disabled={isDisabled}
          />

          {/* Option descriptions (when enabled) */}
          {showDescription && (
            <div className="tutorpress-content-drip-settings__descriptions">
              {CONTENT_DRIP_TYPE_OPTIONS.map((option) => (
                <div
                  key={option.value}
                  className={`tutorpress-content-drip-settings__description ${
                    type === option.value ? "is-selected" : ""
                  }`}
                >
                  {type === option.value && (
                    <p className="tutorpress-content-drip-settings__description-text">
                      <strong>{option.label}:</strong> {option.description}
                    </p>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
};
