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

// Generate time options (15 min intervals, 12-hour format)
const generateTimeOptions = () => {
  const options = [];
  for (let hour = 0; hour < 24; hour++) {
    for (let minute = 0; minute < 60; minute += 15) {
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

  // Update course access settings using the API wrapper pattern
  const updateCourseSetting = (key: keyof CourseSettings, value: any) => {
    console.log(`TutorPress Debug - Updating ${key}:`, value);

    // Handle special cases for field relationships
    const updates: Partial<CourseSettings> = { [key]: value };

    // Add legacy field values for Tutor LMS compatibility
    if (key === "maximum_students") {
      // Handle unlimited students case (null or 0 means unlimited)
      const processedValue = value === "" || value === "0" || value === 0 ? null : parseInt(value);
      updates.maximum_students = processedValue;
    } else if (key === "course_enrollment_period") {
      // When disabling enrollment period, clear the dates
      if (value === "no") {
        updates.enrollment_starts_at = "";
        updates.enrollment_ends_at = "";
      }
    }

    updateSettings(updates);
  };

  const timeOptions = generateTimeOptions();

  // Get current dates for date pickers
  const startDate = parseGMTString(settings.enrollment_starts_at) || new Date();
  const endDate = parseGMTString(settings.enrollment_ends_at) || new Date();

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
            onChange={(value) => {
              console.log("TutorPress Debug - Maximum Students Input:", value);
              // Empty string or "0" should be stored as null (unlimited)
              const newValue = value === "" || value === "0" ? null : parseInt(value);
              console.log("TutorPress Debug - Maximum Students Processed:", newValue);
              updateCourseSetting("maximum_students", newValue);
            }}
            help={__(
              "Maximum number of students who can enroll in this course. Leave empty or enter 0 for unlimited.",
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
              console.log("TutorPress Debug - Pause Enrollment Input:", checked);
              const newValue = checked ? "yes" : "no";
              console.log("TutorPress Debug - Pause Enrollment Processed:", newValue);
              updateCourseSetting("pause_enrollment", newValue);
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
            onChange={(checked) => updateCourseSetting("course_enrollment_period", checked ? "yes" : "no")}
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
                          updateCourseSetting("enrollment_starts_at", newDate);
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
                  options={timeOptions}
                  onChange={(value) => {
                    const newDate = combineDateTime(startDate, value);
                    updateCourseSetting("enrollment_starts_at", newDate);
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
                          const newDate = combineDateTime(new Date(date), displayTime(settings.enrollment_ends_at));
                          updateCourseSetting("enrollment_ends_at", newDate);
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
                  options={timeOptions}
                  onChange={(value) => {
                    const newDate = combineDateTime(endDate, value);
                    updateCourseSetting("enrollment_ends_at", newDate);
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
