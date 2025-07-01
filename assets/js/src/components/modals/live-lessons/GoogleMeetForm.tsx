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
  validateAndCorrectMeetingTime,
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
                      updateField("startDate", newStartDate);

                      // Auto-correct end date if it becomes before start date
                      if (formData.endDate < newStartDate) {
                        updateField("endDate", newStartDate);
                      }

                      // Auto-correct end time if it becomes invalid using our validation utility
                      const validationResult = validateAndCorrectMeetingTime(
                        newStartDate,
                        formData.startTime,
                        formData.endDate,
                        formData.endTime
                      );

                      if (!validationResult.isValid && validationResult.correctedEndTime) {
                        updateField("endTime", validationResult.correctedEndTime);
                      }

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
                updateField("startTime", value);

                // Auto-correct end time if it becomes invalid using our validation utility
                const validationResult = validateAndCorrectMeetingTime(
                  formData.startDate,
                  value,
                  formData.endDate,
                  formData.endTime
                );

                if (!validationResult.isValid && validationResult.correctedEndTime) {
                  updateField("endTime", validationResult.correctedEndTime);
                }
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

                      // Ensure end date is not before start date
                      if (newEndDate < formData.startDate) {
                        // Don't allow selecting date before start date
                        return;
                      }

                      updateField("endDate", newEndDate);

                      // Validate and auto-correct end time using our validation utility
                      const validationResult = validateAndCorrectMeetingTime(
                        formData.startDate,
                        formData.startTime,
                        newEndDate,
                        formData.endTime
                      );

                      if (!validationResult.isValid && validationResult.correctedEndTime) {
                        updateField("endTime", validationResult.correctedEndTime);
                      }

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
                const validationResult = validateAndCorrectMeetingTime(
                  formData.startDate,
                  formData.startTime,
                  formData.endDate,
                  value
                );

                if (!validationResult.isValid && validationResult.correctedEndTime) {
                  // Auto-correct invalid end time
                  updateField("endTime", validationResult.correctedEndTime);
                } else {
                  updateField("endTime", value);
                }
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
