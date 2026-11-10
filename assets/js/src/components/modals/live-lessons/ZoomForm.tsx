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

import React, { useState, useEffect } from "react";
import {
  TextControl,
  TextareaControl,
  DatePicker,
  SelectControl,
  CheckboxControl,
  Popover,
  Button,
  __experimentalNumberControl as NumberControl,
  __experimentalHStack as HStack,
  FlexItem,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { calendar, seen, unseen } from "@wordpress/icons";
import { useSelect } from "@wordpress/data";
import type { ZoomFormData } from "../../../types/liveLessons";
import { generateTimezoneOptions } from "./index";
import {
  normalizeZoomUsersBody,
  normalizeZoomUsersError,
  selectedZoomHost,
  zoomUsersRequest,
  type ZoomHostRow,
} from "../../../utils/liveLessonZoomUsers";

const dayPrefix = /^(\d{4}-\d{2}-\d{2})/;

function calendarDay(value: string | null): string | null {
  return dayPrefix.exec(value ?? "")?.[1] ?? null;
}

function dateToken(day: string): string | undefined {
  return calendarDay(day) === day ? `${day}T00:00:00.000Z` : undefined;
}

interface ZoomFormProps {
  formData: ZoomFormData;
  onChange: (data: ZoomFormData) => void;
  disabled?: boolean;
}

export const ZoomForm: React.FC<ZoomFormProps> = ({ formData, onChange, disabled = false }) => {
  // Date picker popover state
  const [datePickerOpen, setDatePickerOpen] = useState(false);

  // Password visibility toggle state
  const [passwordVisible, setPasswordVisible] = useState(false);

  // Zoom users state
  const [zoomUsers, setZoomUsers] = useState<ZoomHostRow[]>([]);
  const [loadingUsers, setLoadingUsers] = useState(true);
  const [usersError, setUsersError] = useState<string | null>(null);

  // Get course ID from curriculum store
  const courseId = useSelect((select: any) => {
    return select("tutorpress/curriculum").getCourseId();
  }, []);

  /**
   * Fetch Zoom users on component mount
   */
  useEffect(() => {
    const request = zoomUsersRequest(courseId, window.tutorPressCurriculum?.liveLessonSyncEnabled);
    if (request === null) {
      setZoomUsers([]);
      setUsersError(null);
      setLoadingUsers(false);
      return;
    }

    const host = formData.host;
    const fetchZoomUsers = async () => {
      setLoadingUsers(true);
      setUsersError(null);
      try {
        const response = await (window as any).wp.apiFetch({
          path: request.path,
          method: "GET",
        });
        const result = normalizeZoomUsersBody(response);
        setZoomUsers(result.hosts);
        setUsersError(result.message);
        if (result.message === null) {
          const nextHost = selectedZoomHost(host, result.hosts);
          if (nextHost !== host) {
            updateField("host", nextHost);
          }
        }
      } catch (error) {
        setUsersError(normalizeZoomUsersError(error).message);
      } finally {
        setLoadingUsers(false);
      }
    };

    fetchZoomUsers();
  }, [courseId]);

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
   * Generate host options from Zoom users (matches Tutor LMS format exactly)
   */
  const getHostOptions = () => {
    if (loadingUsers) {
      return [{ label: __("Loading Zoom users...", "tutorpress"), value: "" }];
    }

    if (usersError || zoomUsers.length === 0) {
      return [{ label: __("No Zoom users available", "tutorpress"), value: "" }];
    }

    return zoomUsers.map((user) => ({
      label: `${user.label} (${user.email})`,
      value: user.id,
    }));
  };

  const timeOptions = generateTimeOptions();
  const timezoneOptions = generateTimezoneOptions();
  const durationUnitOptions = getDurationUnitOptions();
  const autoRecordingOptions = getAutoRecordingOptions();
  const hostOptions = getHostOptions();

  return (
    <div className="tutorpress-zoom-form">
      {/* Title Field */}
      <TextControl
        label={__("Meeting Title", "tutorpress") + " *"}
        value={formData.title}
        onChange={(value: string) => updateField("title", value)}
        placeholder={__("Enter meeting title", "tutorpress")}
        disabled={disabled}
        required
      />

      {/* Summary Field */}
      <TextareaControl
        label={__("Meeting Summary", "tutorpress") + " *"}
        value={formData.summary}
        onChange={(value: string) => updateField("summary", value)}
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
            {formData.date}
          </Button>

          {datePickerOpen && (
            <Popover position="bottom left" onClose={() => setDatePickerOpen(false)}>
              <DatePicker
                currentDate={dateToken(formData.date)}
                onChange={(date) => {
                  const day = calendarDay(typeof date === "string" ? date : null);
                  if (!day) {
                    setDatePickerOpen(false);
                    return;
                  }
                  updateField("date", day);
                  setDatePickerOpen(false);
                }}
              />
            </Popover>
          )}
        </div>

        {/* Time - Full Width */}
        <SelectControl
          label={__("Start Time", "tutorpress") + " *"}
          value={formData.time}
          options={timeOptions}
          onChange={(value: string) => updateField("time", value)}
          disabled={disabled}
          required
        />
      </div>

      {/* Duration */}
      <div className="tutorpress-duration-section">
        <h4>{__("Meeting Duration", "tutorpress")}</h4>

        <HStack spacing={3}>
          <FlexItem>
            <NumberControl
              value={formData.duration}
              onChange={(value: string | undefined) => updateField("duration", parseInt(value || "40") || 40)}
              min={1}
              max={formData.durationUnit === "hours" ? 8 : 480}
              disabled={disabled}
            />
          </FlexItem>

          <FlexItem>
            <SelectControl
              value={formData.durationUnit}
              options={durationUnitOptions}
              onChange={(value: string) => updateField("durationUnit", value)}
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
        onChange={(value: string) => updateField("timezone", value)}
        disabled={disabled}
        help={__("Select the timezone for this meeting", "tutorpress")}
        required
      />

      {/* Auto Recording */}
      <SelectControl
        label={__("Auto Recording", "tutorpress")}
        value={formData.autoRecording}
        options={autoRecordingOptions}
        onChange={(value: string) => updateField("autoRecording", value)}
        disabled={disabled}
        help={__("Choose whether to automatically record this meeting", "tutorpress")}
      />

      <CheckboxControl
        label={__("Waiting Room", "tutorpress")}
        checked={formData.waitingRoom}
        onChange={(checked) => updateField("waitingRoom", checked)}
        disabled={disabled}
        help={__("Participants wait until the host admits them", "tutorpress")}
      />

      {/* Meeting Password with Eye Toggle */}
      <div className="tutorpress-password-field">
        <label className="components-base-control__label" htmlFor="zoom-password-input">
          {__("Meeting Password", "tutorpress")}
        </label>
        <div className="tutorpress-password-input-wrapper">
          <input
            id="zoom-password-input"
            type={passwordVisible ? "text" : "password"}
            value={formData.password}
            onChange={(e) => updateField("password", e.target.value)}
            placeholder={__("Optional meeting password", "tutorpress")}
            disabled={disabled}
            className="components-text-control__input"
            autoComplete="new-password"
          />
          <Button
            icon={passwordVisible ? unseen : seen}
            onClick={() => setPasswordVisible(!passwordVisible)}
            disabled={disabled}
            className="tutorpress-password-toggle"
            isSmall
            aria-label={passwordVisible ? __("Hide password", "tutorpress") : __("Show password", "tutorpress")}
          />
        </div>
        <p className="components-base-control__help">{__("Leave empty for no password protection", "tutorpress")}</p>
      </div>

      {/* Host Selection */}
      <SelectControl
        label={__("Meeting Host", "tutorpress") + " *"}
        value={formData.host}
        options={hostOptions}
        onChange={(value: string) => updateField("host", value)}
        disabled={disabled || loadingUsers}
        help={
          usersError
            ? usersError
            : loadingUsers
              ? __("Loading available Zoom hosts...", "tutorpress")
              : __(
                  "Select the Zoom user who will host this meeting. This must be a valid Zoom user from your Zoom account.",
                  "tutorpress"
                )
        }
        required
      />

      {/* Show error state if Zoom users failed to load */}
      {usersError && (
        <div className="tutorpress-form-error tpress-error-state-inline">
          <p>
            <strong>{__("Zoom Configuration Error:", "tutorpress")}</strong> {usersError}
          </p>
          <p style={{ color: "#646970", fontSize: "12px", margin: "4px 0 0 0" }}>
            {__("Please ensure your Zoom API credentials are configured in Tutor LMS settings.", "tutorpress")}
          </p>
        </div>
      )}

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
