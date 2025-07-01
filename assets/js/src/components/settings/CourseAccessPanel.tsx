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
import { AddonChecker } from "../../utils/addonChecker";

// Import course settings types
import type { CourseSettings } from "../../types/courses";

// Import our reusable datetime validation utilities
import {
  convertToGMT,
  parseGMTString,
  displayDate,
  displayTime,
  combineDateTime,
  generateTimeOptions,
  filterEndTimeOptions,
  validateAndCorrectDateTime,
} from "../../utils/datetime-validation";

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

  // Generate time options with 30-minute intervals (standardized across TutorPress)
  const timeOptions = generateTimeOptions(30);

  // Get current dates for date pickers (default to current date)
  const currentDate = new Date();
  const startDate = parseGMTString(settings.enrollment_starts_at) || currentDate;
  const endDate = parseGMTString(settings.enrollment_ends_at) || currentDate;

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
                          const newStartDate = new Date(date);
                          const newDate = combineDateTime(newStartDate, displayTime(settings.enrollment_starts_at));

                          // Use comprehensive datetime validation utility
                          const currentEndDate = parseGMTString(settings.enrollment_ends_at) || newStartDate;
                          const validation = validateAndCorrectDateTime(
                            newStartDate,
                            displayTime(settings.enrollment_starts_at),
                            currentEndDate,
                            displayTime(settings.enrollment_ends_at),
                            { dateStrategy: "adjust-end" }
                          );

                          if (!validation.isValid && validation.correctedEndDate) {
                            const correctedEndDate = combineDateTime(
                              validation.correctedEndDate,
                              displayTime(settings.enrollment_ends_at)
                            );
                            updateSettings({
                              enrollment_starts_at: newDate,
                              enrollment_ends_at: correctedEndDate,
                            });
                          } else {
                            updateSettings({ enrollment_starts_at: newDate });
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
                  options={timeOptions}
                  onChange={(value) => {
                    const newStartDate = combineDateTime(startDate, value);
                    updateSettings({ enrollment_starts_at: newStartDate });

                    // Auto-correct end time if it becomes invalid using our validation utility
                    if (settings.enrollment_ends_at) {
                      const startDateTimeParsed = parseGMTString(settings.enrollment_starts_at);
                      const endDateTimeParsed = parseGMTString(settings.enrollment_ends_at);

                      if (startDateTimeParsed && endDateTimeParsed) {
                        const validationResult = validateAndCorrectDateTime(
                          startDateTimeParsed,
                          value,
                          endDateTimeParsed,
                          displayTime(settings.enrollment_ends_at)
                        );

                        if (!validationResult.isValid && validationResult.correctedEndTime) {
                          const correctedEndDate = combineDateTime(endDate, validationResult.correctedEndTime);
                          updateSettings({
                            enrollment_starts_at: newStartDate,
                            enrollment_ends_at: correctedEndDate,
                          });
                        }
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

                          // Use our validation utility to ensure valid time range
                          const startDateTimeForDate = parseGMTString(settings.enrollment_starts_at);
                          let finalEndDate = newDate;

                          if (startDateTimeForDate) {
                            const validationResult = validateAndCorrectDateTime(
                              startDateTimeForDate,
                              displayTime(settings.enrollment_starts_at),
                              selectedDate,
                              displayTime(settings.enrollment_ends_at)
                            );

                            finalEndDate = validationResult.isValid
                              ? newDate
                              : combineDateTime(
                                  selectedDate,
                                  validationResult.correctedEndTime || displayTime(settings.enrollment_ends_at)
                                );
                          }

                          updateSettings({ enrollment_ends_at: finalEndDate });
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
                  options={(() => {
                    const startDateTime = parseGMTString(settings.enrollment_starts_at);
                    return startDateTime
                      ? filterEndTimeOptions(
                          timeOptions,
                          startDateTime,
                          displayTime(settings.enrollment_starts_at),
                          endDate
                        )
                      : timeOptions;
                  })()}
                  onChange={(value) => {
                    const newEndDate = combineDateTime(endDate, value);

                    // Validate and auto-correct if needed using our validation utility
                    const startDateTimeForValidation = parseGMTString(settings.enrollment_starts_at);
                    let finalEndDate = newEndDate;

                    if (startDateTimeForValidation) {
                      const validationResult = validateAndCorrectDateTime(
                        startDateTimeForValidation,
                        displayTime(settings.enrollment_starts_at),
                        endDate,
                        value
                      );

                      finalEndDate = validationResult.isValid
                        ? newEndDate
                        : combineDateTime(endDate, validationResult.correctedEndTime || value);
                    }

                    updateSettings({ enrollment_ends_at: finalEndDate });
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
