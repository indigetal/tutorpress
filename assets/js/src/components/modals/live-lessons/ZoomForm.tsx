/**
 * Zoom Form Component
 *
 * @description Form component for Zoom Live Lesson creation and editing.
 *              Uses WordPress components for consistent UI/UX and follows the
 *              field patterns from Tutor LMS Zoom addon research.
 *
 * @package TutorPress
 * @subpackage Components/Modals/LiveLessons
 * @since 1.5.2
 */

import React, { useState } from "react";
import {
  TextControl,
  TextareaControl,
  DatePicker,
  SelectControl,
  Popover,
  Button,
  __experimentalNumberControl as NumberControl,
  __experimentalHStack as HStack,
  FlexItem,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { calendar } from "@wordpress/icons";
import type { ZoomFormData } from "../../../types/liveLessons";

interface ZoomFormProps {
  formData: ZoomFormData;
  onChange: (data: ZoomFormData) => void;
  disabled?: boolean;
}

export const ZoomForm: React.FC<ZoomFormProps> = ({ formData, onChange, disabled = false }) => {
  // Date picker popover state
  const [datePickerOpen, setDatePickerOpen] = useState(false);

  /**
   * Handle form field updates
   */
  const updateField = (field: keyof ZoomFormData, value: any) => {
    onChange({
      ...formData,
      [field]: value,
    });
  };

  /**
   * Generate time options for select controls
   */
  const generateTimeOptions = () => {
    const options = [];
    for (let hour = 0; hour < 24; hour++) {
      for (let minute = 0; minute < 60; minute += 15) {
        const time24 = `${hour.toString().padStart(2, "0")}:${minute.toString().padStart(2, "0")}`;
        const hour12 = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
        const period = hour < 12 ? "AM" : "PM";
        const time12 = `${hour12}:${minute.toString().padStart(2, "0")} ${period}`;

        options.push({
          label: time12,
          value: time12,
        });
      }
    }
    return options;
  };

  /**
   * Generate timezone options
   */
  const generateTimezoneOptions = () => {
    // Common timezones - in a real implementation, this would be more comprehensive
    return [
      { label: __("UTC", "tutorpress"), value: "UTC" },
      { label: __("Eastern Time (ET)", "tutorpress"), value: "America/New_York" },
      { label: __("Central Time (CT)", "tutorpress"), value: "America/Chicago" },
      { label: __("Mountain Time (MT)", "tutorpress"), value: "America/Denver" },
      { label: __("Pacific Time (PT)", "tutorpress"), value: "America/Los_Angeles" },
      { label: __("London (GMT)", "tutorpress"), value: "Europe/London" },
      { label: __("Paris (CET)", "tutorpress"), value: "Europe/Paris" },
      { label: __("Tokyo (JST)", "tutorpress"), value: "Asia/Tokyo" },
      { label: __("Sydney (AEST)", "tutorpress"), value: "Australia/Sydney" },
    ];
  };

  /**
   * Generate duration unit options
   */
  const getDurationUnitOptions = () => [
    { label: __("Minutes", "tutorpress"), value: "minutes" },
    { label: __("Hours", "tutorpress"), value: "hours" },
  ];

  /**
   * Generate auto recording options
   */
  const getAutoRecordingOptions = () => [
    { label: __("None", "tutorpress"), value: "none" },
    { label: __("Local Recording", "tutorpress"), value: "local" },
    { label: __("Cloud Recording", "tutorpress"), value: "cloud" },
  ];

  /**
   * Generate host options
   */
  const getHostOptions = () => [
    { label: __("Default Host", "tutorpress"), value: "default" },
    { label: __("Alternative Host", "tutorpress"), value: "alternative" },
  ];

  const timeOptions = generateTimeOptions();
  const timezoneOptions = generateTimezoneOptions();
  const durationUnitOptions = getDurationUnitOptions();
  const autoRecordingOptions = getAutoRecordingOptions();
  const hostOptions = getHostOptions();

  return (
    <div className="tutorpress-zoom-form">
      {/* Title Field */}
      <TextControl
        label={__("Meeting Title", "tutorpress")}
        value={formData.title}
        onChange={(value) => updateField("title", value)}
        placeholder={__("Enter meeting title", "tutorpress")}
        disabled={disabled}
        required
      />

      {/* Summary Field */}
      <TextareaControl
        label={__("Meeting Summary", "tutorpress")}
        value={formData.summary}
        onChange={(value) => updateField("summary", value)}
        placeholder={__("Enter meeting description or agenda", "tutorpress")}
        rows={4}
        disabled={disabled}
        required
      />

      {/* Date and Time */}
      <div className="tutorpress-datetime-section">
        <h4>{__("Meeting Date", "tutorpress")}</h4>

        {/* Date - Full Width */}
        <div className="tutorpress-date-picker-wrapper">
          <Button
            variant="secondary"
            icon={calendar}
            onClick={() => setDatePickerOpen(!datePickerOpen)}
            disabled={disabled}
          >
            {formData.date.toLocaleDateString()}
          </Button>

          {datePickerOpen && (
            <Popover position="bottom left" onClose={() => setDatePickerOpen(false)}>
              <DatePicker
                currentDate={formData.date.toISOString()}
                onChange={(date) => {
                  updateField("date", new Date(date));
                  setDatePickerOpen(false);
                }}
              />
            </Popover>
          )}
        </div>

        {/* Time - Full Width */}
        <SelectControl
          label={__("Start Time", "tutorpress")}
          value={formData.time}
          options={timeOptions}
          onChange={(value) => updateField("time", value)}
          disabled={disabled}
        />
      </div>

      {/* Duration */}
      <div className="tutorpress-duration-section">
        <h4>{__("Meeting Duration", "tutorpress")}</h4>

        <HStack spacing={3}>
          <FlexItem>
            <NumberControl
              value={formData.duration}
              onChange={(value) => updateField("duration", parseInt(value as string) || 40)}
              min={1}
              max={480} // 8 hours max
              disabled={disabled}
            />
          </FlexItem>

          <FlexItem>
            <SelectControl
              value={formData.durationUnit}
              options={durationUnitOptions}
              onChange={(value) => updateField("durationUnit", value)}
              disabled={disabled}
            />
          </FlexItem>
        </HStack>
      </div>

      {/* Timezone */}
      <SelectControl
        label={__("Timezone", "tutorpress")}
        value={formData.timezone}
        options={timezoneOptions}
        onChange={(value) => updateField("timezone", value)}
        disabled={disabled}
        help={__("Select the timezone for this meeting", "tutorpress")}
      />

      {/* Auto Recording */}
      <SelectControl
        label={__("Auto Recording", "tutorpress")}
        value={formData.autoRecording}
        options={autoRecordingOptions}
        onChange={(value) => updateField("autoRecording", value)}
        disabled={disabled}
        help={__("Choose whether to automatically record this meeting", "tutorpress")}
      />

      {/* Meeting Password */}
      <TextControl
        label={__("Meeting Password", "tutorpress")}
        value={formData.password}
        onChange={(value) => updateField("password", value)}
        placeholder={__("Optional meeting password", "tutorpress")}
        disabled={disabled}
        help={__("Leave empty for no password protection", "tutorpress")}
      />

      {/* Host Selection */}
      <SelectControl
        label={__("Meeting Host", "tutorpress")}
        value={formData.host}
        options={hostOptions}
        onChange={(value) => updateField("host", value)}
        disabled={disabled}
        help={__("Select who will host this meeting", "tutorpress")}
      />

      {/* Meeting Instructions */}
      <div className="tutorpress-form-notice">
        <p>
          <strong>{__("Note:", "tutorpress")}</strong>{" "}
          {__(
            "The Zoom meeting link will be generated automatically when you save this lesson. Students will be able to join the meeting from the course page.",
            "tutorpress"
          )}
        </p>
      </div>
    </div>
  );
};
