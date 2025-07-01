/**
 * Google Meet Form Component
 *
 * @description Form component for Google Meet Live Lesson creation and editing.
 *              Enhanced with reusable datetime validation utilities for consistent UX.
 *              Features 30-minute intervals and auto-correction validation.
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
import { generateTimezoneOptions } from "./index";

// Import our reusable datetime validation utilities
import {
  generateTimeOptions,
  filterEndTimeOptions,
  validateAndCorrectDateTime,
} from "../../../utils/datetime-validation";

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

  // Generate time options with 30-minute intervals (standardized across TutorPress)
  const timeOptions = generateTimeOptions(30);
  const timezoneOptions = generateTimezoneOptions();

  return (
    <div className="tutorpress-google-meet-form">
      {/* Title Field */}
      <TextControl
        label={__("Meeting Title", "tutorpress") + " *"}
        value={formData.title}
        onChange={(value) => updateField("title", value)}
        placeholder={__("Enter meeting title", "tutorpress")}
        disabled={disabled}
        required
      />

      {/* Summary Field */}
      <TextareaControl
        label={__("Meeting Summary", "tutorpress") + " *"}
        value={formData.summary}
        onChange={(value) => updateField("summary", value)}
        placeholder={__("Enter meeting description or agenda", "tutorpress")}
        rows={4}
        disabled={disabled}
        required
      />

      {/* Meeting Start */}
      <div className="tutorpress-datetime-section">
        <h4>{__("Meeting Start", "tutorpress")}</h4>

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
                      const newStartDate = new Date(date);

                      // Auto-correct end date and time using simplified validation utility
                      const validationResult = validateAndCorrectDateTime(
                        newStartDate,
                        formData.startTime,
                        formData.endDate,
                        formData.endTime
                      );

                      // Build updates object to avoid race conditions
                      const updates: Partial<GoogleMeetFormData> = {
                        startDate: newStartDate,
                        ...(validationResult.correctedEndDate && { endDate: validationResult.correctedEndDate }),
                        ...(validationResult.correctedEndTime && { endTime: validationResult.correctedEndTime }),
                      };

                      // Apply all updates at once
                      onChange({ ...formData, ...updates });

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
              value={formData.startTime}
              options={timeOptions}
              onChange={(value) => {
                // Auto-correct end date and time using simplified validation
                const validationResult = validateAndCorrectDateTime(
                  formData.startDate,
                  value,
                  formData.endDate,
                  formData.endTime
                );

                // Build updates object to avoid race conditions
                const updates: Partial<GoogleMeetFormData> = {
                  startTime: value,
                  ...(validationResult.correctedEndDate && { endDate: validationResult.correctedEndDate }),
                  ...(validationResult.correctedEndTime && { endTime: validationResult.correctedEndTime }),
                };

                // Apply all updates at once
                onChange({ ...formData, ...updates });
              }}
              disabled={disabled}
            />
          </FlexItem>
        </HStack>
      </div>

      {/* Meeting End */}
      <div className="tutorpress-datetime-section">
        <h4>{__("Meeting End", "tutorpress")}</h4>

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
                      const newEndDate = new Date(date);

                      // Auto-correct start date if end date is earlier (match Course Access Panel behavior)
                      const validationResult = validateAndCorrectDateTime(
                        formData.startDate,
                        formData.startTime,
                        newEndDate,
                        formData.endTime
                      );

                      // Build updates object to avoid race conditions
                      const updates: Partial<GoogleMeetFormData> = {
                        endDate: newEndDate,
                        ...(validationResult.correctedEndTime && { endTime: validationResult.correctedEndTime }),
                      };

                      // If end date is before start date, auto-correct by moving start date back
                      const startDateOnly = new Date(
                        formData.startDate.getFullYear(),
                        formData.startDate.getMonth(),
                        formData.startDate.getDate()
                      );
                      const endDateOnly = new Date(
                        newEndDate.getFullYear(),
                        newEndDate.getMonth(),
                        newEndDate.getDate()
                      );

                      if (endDateOnly < startDateOnly) {
                        updates.startDate = new Date(newEndDate);
                      }

                      // Apply all updates at once
                      onChange({ ...formData, ...updates });

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
              value={formData.endTime}
              options={filterEndTimeOptions(timeOptions, formData.startDate, formData.startTime, formData.endDate)}
              onChange={(value) => {
                const validationResult = validateAndCorrectDateTime(
                  formData.startDate,
                  formData.startTime,
                  formData.endDate,
                  value
                );

                // Use corrected time if validation suggests it, otherwise use selected value
                const finalEndTime = validationResult.correctedEndTime || value;
                updateField("endTime", finalEndTime);
              }}
              disabled={disabled}
            />
          </FlexItem>
        </HStack>
      </div>

      {/* Timezone */}
      <SelectControl
        label={__("Timezone", "tutorpress") + " *"}
        value={formData.timezone}
        options={timezoneOptions}
        onChange={(value) => updateField("timezone", value)}
        disabled={disabled}
        help={__("Select the timezone for this meeting", "tutorpress")}
        required
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
