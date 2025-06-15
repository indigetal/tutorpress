/**
 * Google Meet Form Component
 *
 * @description Form component for Google Meet Live Lesson creation and editing.
 *              Uses WordPress components for consistent UI/UX and follows the
 *              field patterns from Tutor LMS Google Meet addon research.
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
  CheckboxControl,
  Popover,
  Button,
  Flex,
  FlexItem,
  __experimentalHStack as HStack,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { calendar } from "@wordpress/icons";
import type { GoogleMeetFormData } from "../../../types/liveLessons";

interface GoogleMeetFormProps {
  formData: GoogleMeetFormData;
  onChange: (data: GoogleMeetFormData) => void;
  disabled?: boolean;
}

export const GoogleMeetForm: React.FC<GoogleMeetFormProps> = ({ formData, onChange, disabled = false }) => {
  // Date picker popover state
  const [startDatePickerOpen, setStartDatePickerOpen] = useState(false);
  const [endDatePickerOpen, setEndDatePickerOpen] = useState(false);

  /**
   * Handle form field updates
   */
  const updateField = (field: keyof GoogleMeetFormData, value: any) => {
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

  const timeOptions = generateTimeOptions();
  const timezoneOptions = generateTimezoneOptions();

  return (
    <div className="tutorpress-google-meet-form">
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

      {/* Start Date and Time */}
      <div className="tutorpress-datetime-section">
        <h4>{__("Start Date & Time", "tutorpress")}</h4>

        <HStack spacing={3}>
          {/* Start Date */}
          <FlexItem>
            <div className="tutorpress-date-picker-wrapper">
              <Button
                variant="secondary"
                icon={calendar}
                onClick={() => setStartDatePickerOpen(!startDatePickerOpen)}
                disabled={disabled}
              >
                {formData.startDate.toLocaleDateString()}
              </Button>

              {startDatePickerOpen && (
                <Popover position="bottom left" onClose={() => setStartDatePickerOpen(false)}>
                  <DatePicker
                    currentDate={formData.startDate.toISOString()}
                    onChange={(date) => {
                      updateField("startDate", new Date(date));
                      setStartDatePickerOpen(false);
                    }}
                  />
                </Popover>
              )}
            </div>
          </FlexItem>

          {/* Start Time */}
          <FlexItem>
            <SelectControl
              label={__("Start Time", "tutorpress")}
              value={formData.startTime}
              options={timeOptions}
              onChange={(value) => updateField("startTime", value)}
              disabled={disabled}
            />
          </FlexItem>
        </HStack>
      </div>

      {/* End Date and Time */}
      <div className="tutorpress-datetime-section">
        <h4>{__("End Date & Time", "tutorpress")}</h4>

        <HStack spacing={3}>
          {/* End Date */}
          <FlexItem>
            <div className="tutorpress-date-picker-wrapper">
              <Button
                variant="secondary"
                icon={calendar}
                onClick={() => setEndDatePickerOpen(!endDatePickerOpen)}
                disabled={disabled}
              >
                {formData.endDate.toLocaleDateString()}
              </Button>

              {endDatePickerOpen && (
                <Popover position="bottom left" onClose={() => setEndDatePickerOpen(false)}>
                  <DatePicker
                    currentDate={formData.endDate.toISOString()}
                    onChange={(date) => {
                      updateField("endDate", new Date(date));
                      setEndDatePickerOpen(false);
                    }}
                  />
                </Popover>
              )}
            </div>
          </FlexItem>

          {/* End Time */}
          <FlexItem>
            <SelectControl
              label={__("End Time", "tutorpress")}
              value={formData.endTime}
              options={timeOptions}
              onChange={(value) => updateField("endTime", value)}
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

      {/* Add Enrolled Students */}
      <CheckboxControl
        label={__("Add enrolled students to meeting", "tutorpress")}
        checked={formData.addEnrolledStudents}
        onChange={(checked) => updateField("addEnrolledStudents", checked)}
        disabled={disabled}
        help={__("Automatically invite all enrolled students to this Google Meet session", "tutorpress")}
      />

      {/* Meeting Instructions */}
      <div className="tutorpress-form-notice">
        <p>
          <strong>{__("Note:", "tutorpress")}</strong>{" "}
          {__(
            "The Google Meet link will be generated automatically when you save this lesson. Students will be able to join the meeting from the course page.",
            "tutorpress"
          )}
        </p>
      </div>
    </div>
  );
};
