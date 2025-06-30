import React, { useState } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import {
  PanelRow,
  TextControl,
  ToggleControl,
  CheckboxControl,
  Button,
  Popover,
  SelectControl,
  __experimentalHStack as HStack,
  FlexItem,
  DatePicker,
  Notice,
  Spinner,
} from "@wordpress/components";
import { calendar } from "@wordpress/icons";
import { format } from "date-fns";
import { AddonChecker } from "../../utils/addonChecker";

// Import course settings types
import type { CourseSettings } from "../../types/courses";

// Convert local time to GMT for storage
const convertToGMT = (localDate: Date): string => {
  // Get timezone offset in minutes
  const offsetInMinutes = localDate.getTimezoneOffset();

  // Add offset to convert local to GMT
  const gmtDate = new Date(localDate.getTime() + offsetInMinutes * 60 * 1000);

  // Format as 'yyyy-MM-dd HH:mm:ss'
  return format(gmtDate, "yyyy-MM-dd HH:mm:ss");
};

// Parse GMT string to local Date for display
const parseGMTString = (gmtString: string | null | undefined): Date | null => {
  if (!gmtString) return null;

  try {
    // Parse GMT string
    const [datePart, timePart] = gmtString.split(" ");
    if (!datePart || !timePart) return null;

    const [year, month, day] = datePart.split("-").map(Number);
    const [hours, minutes] = timePart.split(":").map(Number);

    // Create Date in GMT then convert to local
    const date = new Date();
    date.setUTCFullYear(year, month - 1, day);
    date.setUTCHours(hours, minutes, 0, 0);
    return date;
  } catch (e) {
    console.error("Error parsing GMT date:", e);
    return null;
  }
};

// Display functions use local time
const displayDate = (gmtString: string | null | undefined): string => {
  const date = parseGMTString(gmtString);
  return date ? date.toLocaleDateString() : new Date().toLocaleDateString();
};

const displayTime = (gmtString: string | null | undefined): string => {
  const date = parseGMTString(gmtString);
  return date
    ? date.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      })
    : "09:00 AM";
};

// Combine local date and time, then convert to GMT for storage
const combineDateTime = (localDate: Date, localTimeStr: string): string => {
  // Parse time string (12-hour format)
  const match = localTimeStr.match(/(\d+):(\d+)\s*(AM|PM)/i);
  if (!match) return convertToGMT(localDate);

  let [_, hours, minutes, period] = match;
  let hour = parseInt(hours);

  // Convert to 24-hour
  if (period.toUpperCase() === "PM" && hour < 12) hour += 12;
  if (period.toUpperCase() === "AM" && hour === 12) hour = 0;

  // Create new date with local time
  const combinedLocalDate = new Date(localDate);
  combinedLocalDate.setHours(hour, parseInt(minutes), 0, 0);

  // Convert local to GMT for storage
  return convertToGMT(combinedLocalDate);
};

// Generate time options (30 min intervals, 12-hour format to match Tutor LMS)
const generateTimeOptions = () => {
  const options = [];
  for (let hour = 0; hour < 24; hour++) {
    for (let minute = 0; minute < 60; minute += 30) {
      const date = new Date();
      date.setHours(hour, minute);
      const time12 = date.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      });

      options.push({
        label: time12,
        value: time12,
      });
    }
  }
  return options;
};

const CourseAccessPanel: React.FC = () => {
  // State for date picker popovers
  const [startDatePickerOpen, setStartDatePickerOpen] = useState(false);
  const [endDatePickerOpen, setEndDatePickerOpen] = useState(false);

  // Get settings from our store and Gutenberg store
  const { postType, settings, error, isLoading } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      settings: select("tutorpress/course-settings").getSettings(),
      error: select("tutorpress/course-settings").getError(),
      isLoading: select("tutorpress/course-settings").getFetchState().isLoading,
    }),
    []
  );

  // Get dispatch actions
  const { updateSettings } = useDispatch("tutorpress/course-settings");

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Show loading state while fetching settings
  if (isLoading) {
    return (
      <PluginDocumentSettingPanel
        name="course-access-panel"
        title={__("Course Access & Enrollment", "tutorpress")}
        className="tutorpress-course-access-panel"
      >
        <PanelRow>
          <div style={{ width: "100%", textAlign: "center", padding: "20px 0" }}>
            <Spinner />
          </div>
        </PanelRow>
      </PluginDocumentSettingPanel>
    );
  }

  // Helper function for maximum students processing
  const processMaximumStudents = (value: string | number | null): number | null => {
    return value === "" || value === "0" || value === 0 ? null : parseInt(value?.toString() || "0") || null;
  };

  const timeOptions = generateTimeOptions();

  // Get current dates for date pickers (default to current date)
  const currentDate = new Date();
  const startDate = parseGMTString(settings.enrollment_starts_at) || currentDate;
  const endDate = parseGMTString(settings.enrollment_ends_at) || currentDate;

  // Helper function to get filtered time options based on constraints
  const getFilteredTimeOptions = (isEndTime: boolean) => {
    const allOptions = timeOptions;

    if (!isEndTime) {
      return allOptions; // Start time has no constraints
    }

    // For end time, filter out times that are before start time on the same date
    const startDateTime = parseGMTString(settings.enrollment_starts_at);
    const endDateTime = parseGMTString(settings.enrollment_ends_at);

    if (!startDateTime) {
      return allOptions; // No start date set, show all options
    }

    // If we have a start date, check if we're on the same date
    const currentEndDate = endDateTime || startDateTime; // Use start date if no end date

    if (startDateTime.toDateString() === currentEndDate.toDateString()) {
      const startTimeStr = displayTime(settings.enrollment_starts_at);
      const startTimeIndex = allOptions.findIndex((option) => option.value === startTimeStr);

      if (startTimeIndex >= 0) {
        // Return options after start time (at least 30 minutes later)
        return allOptions.slice(startTimeIndex + 1);
      }
    }

    return allOptions;
  };

  // Helper function to validate and auto-correct end time
  const validateAndCorrectEndTime = (newEndTime: string, currentStartTime: string) => {
    const startDateTime = parseGMTString(currentStartTime);
    const endDateTime = parseGMTString(newEndTime);

    if (!startDateTime || !endDateTime) {
      return newEndTime; // Can't validate without both dates
    }

    // If end is before start, auto-correct to be 30 minutes after start
    if (endDateTime <= startDateTime) {
      const correctedEnd = new Date(startDateTime);
      correctedEnd.setMinutes(correctedEnd.getMinutes() + 30);
      return convertToGMT(correctedEnd);
    }

    return newEndTime;
  };

  return (
    <PluginDocumentSettingPanel
      name="course-access-panel"
      title={__("Course Access & Enrollment", "tutorpress")}
      className="tutorpress-course-access-panel"
    >
      {error && (
        <PanelRow>
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        </PanelRow>
      )}

      {/* Maximum Students */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <TextControl
            type="number"
            label={__("Maximum Students", "tutorpress")}
            value={settings.maximum_students?.toString() || ""}
            placeholder="0"
            onChange={(value) => {
              const newValue = processMaximumStudents(value);
              updateSettings({ maximum_students: newValue });
            }}
            help={__(
              "Maximum number of students who can enroll in this course. Set to 0 for unlimited students.",
              "tutorpress"
            )}
          />
        </div>
      </PanelRow>

      {/* Pause Enrollment */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <CheckboxControl
            label={__("Pause Enrollment", "tutorpress")}
            checked={settings.pause_enrollment === "yes"}
            onChange={(checked: boolean) => {
              updateSettings({ pause_enrollment: checked ? "yes" : "no" });
            }}
            help={__("Temporarily stop new enrollments for this course.", "tutorpress")}
          />
        </div>
      </PanelRow>

      {/* Course Enrollment Period */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <ToggleControl
            label={__("Course Enrollment Period", "tutorpress")}
            checked={settings.course_enrollment_period === "yes"}
            onChange={(checked) => {
              const updates: Partial<CourseSettings> = { course_enrollment_period: checked ? "yes" : "no" };
              // When disabling enrollment period, clear the dates
              if (!checked) {
                updates.enrollment_starts_at = "";
                updates.enrollment_ends_at = "";
              }
              updateSettings(updates);
            }}
            help={__("Set a specific time period when students can enroll in this course.", "tutorpress")}
          />
        </div>
      </PanelRow>

      {settings.course_enrollment_period === "yes" && (
        <>
          {/* Start Date/Time */}
          <div className="tutorpress-datetime-section">
            <h4>{__("Enrollment Start", "tutorpress")}</h4>
            <HStack spacing={3}>
              {/* Start Date */}
              <FlexItem>
                <div className="tutorpress-date-picker-wrapper">
                  <Button
                    variant="secondary"
                    icon={calendar}
                    onClick={() => setStartDatePickerOpen(!startDatePickerOpen)}
                  >
                    {displayDate(settings.enrollment_starts_at)}
                  </Button>

                  {startDatePickerOpen && (
                    <Popover position="bottom left" onClose={() => setStartDatePickerOpen(false)}>
                      <DatePicker
                        currentDate={startDate}
                        onChange={(date) => {
                          const newDate = combineDateTime(new Date(date), displayTime(settings.enrollment_starts_at));
                          updateSettings({ enrollment_starts_at: newDate });

                          // If end date is before new start date, update end date to match
                          const currentEndDate = parseGMTString(settings.enrollment_ends_at);
                          if (currentEndDate && new Date(date) > currentEndDate) {
                            const newEndDate = combineDateTime(
                              new Date(date),
                              displayTime(settings.enrollment_ends_at)
                            );
                            updateSettings({
                              enrollment_starts_at: newDate,
                              enrollment_ends_at: newEndDate,
                            });
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
                  value={displayTime(settings.enrollment_starts_at)}
                  options={getFilteredTimeOptions(false)}
                  onChange={(value) => {
                    const newStartDate = combineDateTime(startDate, value);
                    updateSettings({ enrollment_starts_at: newStartDate });

                    // Auto-correct end time if it becomes invalid
                    if (settings.enrollment_ends_at) {
                      const correctedEndDate = validateAndCorrectEndTime(settings.enrollment_ends_at, newStartDate);
                      if (correctedEndDate !== settings.enrollment_ends_at) {
                        updateSettings({
                          enrollment_starts_at: newStartDate,
                          enrollment_ends_at: correctedEndDate,
                        });
                      }
                    }
                  }}
                />
              </FlexItem>
            </HStack>
          </div>

          {/* End Date/Time */}
          <div className="tutorpress-datetime-section">
            <h4>{__("Enrollment End", "tutorpress")}</h4>
            <HStack spacing={3}>
              {/* End Date */}
              <FlexItem>
                <div className="tutorpress-date-picker-wrapper">
                  <Button variant="secondary" icon={calendar} onClick={() => setEndDatePickerOpen(!endDatePickerOpen)}>
                    {displayDate(settings.enrollment_ends_at)}
                  </Button>

                  {endDatePickerOpen && (
                    <Popover position="bottom left" onClose={() => setEndDatePickerOpen(false)}>
                      <DatePicker
                        currentDate={endDate}
                        onChange={(date) => {
                          // Ensure end date is not before start date
                          const startDateTime = parseGMTString(settings.enrollment_starts_at);
                          const selectedDate = new Date(date);

                          if (startDateTime) {
                            // Compare just the date part (not time)
                            const startDateOnly = new Date(
                              startDateTime.getFullYear(),
                              startDateTime.getMonth(),
                              startDateTime.getDate()
                            );
                            const selectedDateOnly = new Date(
                              selectedDate.getFullYear(),
                              selectedDate.getMonth(),
                              selectedDate.getDate()
                            );

                            if (selectedDateOnly < startDateOnly) {
                              // If selected date is before start date, don't allow it
                              return;
                            }
                          }

                          const newDate = combineDateTime(selectedDate, displayTime(settings.enrollment_ends_at));
                          const correctedEndDate = validateAndCorrectEndTime(newDate, settings.enrollment_starts_at);

                          updateSettings({ enrollment_ends_at: correctedEndDate });
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
                  value={displayTime(settings.enrollment_ends_at)}
                  options={getFilteredTimeOptions(true)}
                  onChange={(value) => {
                    const newEndDate = combineDateTime(endDate, value);

                    // Validate and auto-correct if needed
                    const correctedEndDate = validateAndCorrectEndTime(newEndDate, settings.enrollment_starts_at);

                    updateSettings({ enrollment_ends_at: correctedEndDate });
                  }}
                />
              </FlexItem>
            </HStack>
          </div>
        </>
      )}
    </PluginDocumentSettingPanel>
  );
};

export default CourseAccessPanel;
