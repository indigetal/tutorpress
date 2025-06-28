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
} from "@wordpress/components";
import { calendar } from "@wordpress/icons";
import { format } from "date-fns";
import { AddonChecker } from "../../utils/addonChecker";

interface CourseSettings {
  maximum_students?: number | null;
  pause_enrollment?: "yes" | "no";
  course_enrollment_period?: "yes" | "no";
  enrollment_starts_at?: string | null;
  enrollment_ends_at?: string | null;
}

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
const parseGMTString = (gmtString: string | null | undefined): Date => {
  if (!gmtString) return new Date();

  try {
    // Parse GMT string
    const [datePart, timePart] = gmtString.split(" ");
    if (!datePart || !timePart) return new Date();

    const [year, month, day] = datePart.split("-").map(Number);
    const [hours, minutes] = timePart.split(":").map(Number);

    // Create Date in GMT then convert to local
    const date = new Date();
    date.setUTCFullYear(year, month - 1, day);
    date.setUTCHours(hours, minutes, 0, 0);
    return date;
  } catch (e) {
    console.error("Error parsing GMT date:", e);
    return new Date();
  }
};

// Display functions use local time
const displayDate = (gmtString: string | null | undefined): string => {
  return parseGMTString(gmtString).toLocaleDateString();
};

const displayTime = (gmtString: string | null | undefined): string => {
  return parseGMTString(gmtString).toLocaleTimeString("en-US", {
    hour: "numeric",
    minute: "2-digit",
    hour12: true,
  });
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

  // Get course settings from store
  const { courseSettings } = useSelect(
    (select: any) => ({
      courseSettings: (select("core/editor").getEditedPostAttribute("course_settings") || {}) as CourseSettings,
    }),
    []
  );

  const { editPost } = useDispatch("core/editor");

  // Update course settings in _tutor_course_settings
  const updateCourseSetting = (key: keyof CourseSettings, value: any) => {
    const newSettings = { ...courseSettings };
    newSettings[key] = value;
    editPost({ course_settings: newSettings });
  };

  const timeOptions = generateTimeOptions();

  return (
    <PluginDocumentSettingPanel name="course-access-panel" title={__("Course Access & Enrollment", "tutorpress")}>
      {/* Maximum Students */}
      <PanelRow>
        <TextControl
          type="number"
          label={__("Maximum Students", "tutorpress")}
          value={courseSettings.maximum_students?.toString() || ""}
          onChange={(value) => updateCourseSetting("maximum_students", value ? parseInt(value) : null)}
          help={__(
            "Maximum number of students who can enroll in this course. Leave empty for unlimited.",
            "tutorpress"
          )}
        />
      </PanelRow>

      {/* Pause Enrollment */}
      <PanelRow>
        <CheckboxControl
          label={__("Pause Enrollment", "tutorpress")}
          checked={courseSettings.pause_enrollment === "yes"}
          onChange={(checked: boolean) => updateCourseSetting("pause_enrollment", checked ? "yes" : "no")}
          help={__("Temporarily stop new enrollments for this course.", "tutorpress")}
        />
      </PanelRow>

      {/* Course Enrollment Period */}
      <PanelRow>
        <ToggleControl
          label={__("Course Enrollment Period", "tutorpress")}
          checked={courseSettings.course_enrollment_period === "yes"}
          onChange={(checked) => updateCourseSetting("course_enrollment_period", checked ? "yes" : "no")}
          help={__("Set a specific time period when students can enroll in this course.", "tutorpress")}
        />
      </PanelRow>

      {courseSettings.course_enrollment_period === "yes" && (
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
                    {displayDate(courseSettings.enrollment_starts_at)}
                  </Button>

                  {startDatePickerOpen && (
                    <Popover position="bottom left" onClose={() => setStartDatePickerOpen(false)}>
                      <DatePicker
                        currentDate={courseSettings.enrollment_starts_at || new Date().toISOString()}
                        onChange={(date) => {
                          const newDate = combineDateTime(
                            new Date(date),
                            courseSettings.enrollment_starts_at
                              ? displayTime(courseSettings.enrollment_starts_at)
                              : "09:00 AM"
                          );
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
                  value={displayTime(courseSettings.enrollment_starts_at)}
                  options={timeOptions}
                  onChange={(value) => {
                    const newDate = combineDateTime(new Date(courseSettings.enrollment_starts_at || new Date()), value);
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
                    {displayDate(courseSettings.enrollment_ends_at)}
                  </Button>

                  {endDatePickerOpen && (
                    <Popover position="bottom left" onClose={() => setEndDatePickerOpen(false)}>
                      <DatePicker
                        currentDate={courseSettings.enrollment_ends_at || new Date().toISOString()}
                        onChange={(date) => {
                          const newDate = combineDateTime(
                            new Date(date),
                            courseSettings.enrollment_ends_at
                              ? displayTime(courseSettings.enrollment_ends_at)
                              : "09:00 AM"
                          );
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
                  value={displayTime(courseSettings.enrollment_ends_at)}
                  options={timeOptions}
                  onChange={(value) => {
                    const newDate = combineDateTime(new Date(courseSettings.enrollment_ends_at || new Date()), value);
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
