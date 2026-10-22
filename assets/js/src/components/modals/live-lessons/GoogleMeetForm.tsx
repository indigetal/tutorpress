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
import { generateTimeOptions } from "../../../utils/datetime-validation";

const dayPrefix = /^(\d{4}-\d{2}-\d{2})/;

function calendarDay(value: string | null): string | null {
  return dayPrefix.exec(value ?? "")?.[1] ?? null;
}

function dateToken(day: string): string | undefined {
  return calendarDay(day) === day ? `${day}T00:00:00.000Z` : undefined;
}

function sameCalendarDay(startDate: string, endDate: string): boolean {
  return startDate !== "" && startDate === endDate;
}

function laterEndTime(options: Array<{ value: string }>, startTime: string, endTime: string): string | undefined {
  const startIndex = options.findIndex((option) => option.value === startTime);
  const endIndex = options.findIndex((option) => option.value === endTime);
  return startIndex >= 0 && endIndex >= 0 && endIndex <= startIndex ? options[startIndex + 1]?.value : undefined;
}

function endTimeChoices<T extends { value: string }>(options: T[], startDate: string, startTime: string, endDate: string): T[] {
  if (!sameCalendarDay(startDate, endDate)) {
    return options;
  }
  const startIndex = options.findIndex((option) => option.value === startTime);
  return startIndex >= 0 ? options.slice(startIndex + 1) : options;
}

function applyGoogleSchedule(
  current: GoogleMeetFormData,
  patch: Partial<Pick<GoogleMeetFormData, "startDate" | "startTime" | "endDate" | "endTime">>,
  options: Array<{ value: string }>
): GoogleMeetFormData {
  const startDate = patch.startDate ?? current.startDate;
  const startTime = patch.startTime ?? current.startTime;
  const endDate = patch.endDate ?? current.endDate;
  const endTime = patch.endTime ?? current.endTime;
  const corrected = sameCalendarDay(startDate, endDate) ? laterEndTime(options, startTime, endTime) : undefined;
  return { ...current, startDate, startTime, endDate, endTime: corrected ?? endTime };
}

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
                {formData.startDate}
              </Button>

              {startDatePickerOpen && (
                <Popover position="bottom left" onClose={() => setStartDatePickerOpen(false)}>
                  <DatePicker
                    currentDate={dateToken(formData.startDate)}
                    onChange={(date) => {
                      const startDate = calendarDay(typeof date === "string" ? date : null);
                      if (!startDate) {
                        setStartDatePickerOpen(false);
                        return;
                      }
                      const endDate =
                        formData.endDate !== "" && startDate > formData.endDate ? startDate : formData.endDate;
                      onChange(applyGoogleSchedule(formData, { startDate, endDate }, timeOptions));
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
              onChange={(value) => onChange(applyGoogleSchedule(formData, { startTime: value }, timeOptions))}
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
                {formData.endDate}
              </Button>

              {endDatePickerOpen && (
                <Popover position="bottom left" onClose={() => setEndDatePickerOpen(false)}>
                  <DatePicker
                    currentDate={dateToken(formData.endDate)}
                    onChange={(date) => {
                      const endDate = calendarDay(typeof date === "string" ? date : null);
                      if (!endDate) {
                        setEndDatePickerOpen(false);
                        return;
                      }
                      const startDate =
                        formData.startDate !== "" && endDate < formData.startDate ? endDate : formData.startDate;
                      onChange(applyGoogleSchedule(formData, { startDate, endDate }, timeOptions));
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
              options={endTimeChoices(timeOptions, formData.startDate, formData.startTime, formData.endDate)}
              onChange={(value) => onChange(applyGoogleSchedule(formData, { endTime: value }, timeOptions))}
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
