import React, { useState, useEffect } from "react";
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
  BaseControl,
} from "@wordpress/components";
import { calendar } from "@wordpress/icons";
import { AddonChecker } from "../../utils/addonChecker";

// Import course settings types
import type { CourseSettings } from "../../types/courses";

// Import our reusable datetime validation utilities
import {
  parseGMTString,
  displayDate,
  displayTime,
  combineDateTime,
  generateTimeOptions,
  filterEndTimeOptions,
  validateAndCorrectDateTime,
} from "../../utils/datetime-validation";

// Types for prerequisites functionality
interface Course {
  id: number;
  title: string;
  permalink: string;
  featured_image?: string;
  author: string;
  date_created: string;
  // Enhanced fields from new search endpoint (optional for backward compatibility)
  price?: string;
  duration?: string;
  lesson_count?: number;
  quiz_count?: number;
  resource_count?: number;
}

interface CourseOption {
  value: string;
  label: string;
}

const CourseAccessPanel: React.FC = () => {
  // State for date picker popovers
  const [startDatePickerOpen, setStartDatePickerOpen] = useState(false);
  const [endDatePickerOpen, setEndDatePickerOpen] = useState(false);

  // Get settings from our store and Gutenberg store
  const { postType, settings, error, isLoading, courseId, availableCourses, coursesLoading, coursesError } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      settings: select("tutorpress/course-settings").getSettings(),
      error: select("tutorpress/course-settings").getError(),
      isLoading: select("tutorpress/course-settings").getFetchState().isLoading,
      courseId: select("core/editor").getCurrentPostId(),
      availableCourses: select("tutorpress/course-settings").getAvailableCourses(),
      coursesLoading: select("tutorpress/course-settings").getCourseSelectionLoading(),
      coursesError: select("tutorpress/course-settings").getCourseSelectionError(),
    }),
    []
  );

  // Get dispatch actions
  const { updateSettings, fetchAvailableCourses } = useDispatch("tutorpress/course-settings");

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Check if prerequisites addon is enabled
  const isPrerequisitesEnabled = AddonChecker.isPrerequisitesEnabled();

  // Load available courses for prerequisites when addon is enabled
  useEffect(() => {
    if (isPrerequisitesEnabled && courseId) {
      fetchAvailableCourses();
    }
  }, [isPrerequisitesEnabled, courseId, fetchAvailableCourses]);

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

  // Prerequisites functionality
  const handlePrerequisiteChange = async (courseId: string) => {
    const numericId = parseInt(courseId);
    if (isNaN(numericId) || !courseId) return;

    const currentPrereqs = settings.course_prerequisites || [];
    if (!currentPrereqs.includes(numericId)) {
      const newPrereqs = [...currentPrereqs, numericId];
      updateSettings({ course_prerequisites: newPrereqs });
    }
  };

  const removePrerequisite = async (courseId: number) => {
    const currentPrereqs = settings.course_prerequisites || [];
    const updatedPrereqs = currentPrereqs.filter((id: number) => id !== courseId);
    updateSettings({ course_prerequisites: updatedPrereqs });
  };

  // Convert course list to select options
  const courseOptions: CourseOption[] = [
    {
      value: "",
      label: coursesLoading ? __("Loading courses...", "tutorpress") : __("Select a course...", "tutorpress"),
    },
    ...availableCourses
      .filter((course: Course) => !(settings.course_prerequisites || []).includes(course.id))
      .map((course: Course) => ({
        value: course.id.toString(),
        label: course.title,
      })),
  ];

  // Get selected prerequisites with course details
  const selectedPrerequisitesWithDetails = (settings.course_prerequisites || [])
    .map((id: number) => availableCourses.find((course: Course) => course.id === id))
    .filter((course: Course | undefined): course is Course => course !== undefined);

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

      {/* Prerequisites Section - Only show if addon is enabled */}
      {isPrerequisitesEnabled && (
        <div className="tutorpress-settings-section" style={{ marginBottom: "16px" }}>
          <BaseControl
            label={__("Prerequisites", "tutorpress")}
            help={__("Select courses that students must complete before enrolling in this course.", "tutorpress")}
          >
            {coursesError && (
              <Notice status="error" isDismissible={false}>
                {coursesError}
              </Notice>
            )}

            {/* Add Prerequisites Dropdown */}
            <SelectControl
              label={__("Add Prerequisite Course", "tutorpress")}
              value=""
              options={courseOptions}
              onChange={handlePrerequisiteChange}
              disabled={coursesLoading}
              __next40pxDefaultSize
            />

            {/* Selected Prerequisites Display */}
            {selectedPrerequisitesWithDetails.length > 0 && (
              <div className="tutorpress-saved-files-list">
                <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
                  {__("Selected Prerequisites:", "tutorpress")}
                </div>
                {selectedPrerequisitesWithDetails.map((course: Course) => (
                  <div key={course.id} className="tutorpress-saved-file-item">
                    <span className="file-name">{course.title}</span>
                    <Button
                      variant="tertiary"
                      onClick={() => removePrerequisite(course.id)}
                      className="delete-button"
                      aria-label={__("Remove prerequisite", "tutorpress")}
                    >
                      ×
                    </Button>
                  </div>
                ))}
              </div>
            )}
          </BaseControl>
        </div>
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

                          // Auto-correct end date if start date is later (simple behavior)
                          const currentEndDate = parseGMTString(settings.enrollment_ends_at) || newStartDate;
                          const validation = validateAndCorrectDateTime(
                            newStartDate,
                            displayTime(settings.enrollment_starts_at),
                            currentEndDate,
                            displayTime(settings.enrollment_ends_at)
                          );

                          // Always update start date, and auto-correct end date if needed
                          const updates: any = { enrollment_starts_at: newDate };

                          if (validation.correctedEndDate) {
                            updates.enrollment_ends_at = combineDateTime(
                              validation.correctedEndDate,
                              displayTime(settings.enrollment_ends_at)
                            );
                          }
                          if (validation.correctedEndTime) {
                            const endDateToUse = validation.correctedEndDate || currentEndDate;
                            updates.enrollment_ends_at = combineDateTime(endDateToUse, validation.correctedEndTime);
                          }

                          updateSettings(updates);

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

                        if (validationResult.correctedEndTime) {
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
                          const selectedDate = new Date(date);
                          const newDate = combineDateTime(selectedDate, displayTime(settings.enrollment_ends_at));

                          // Auto-correct start date if end date is earlier (match Google Meet behavior)
                          const startDateTime = parseGMTString(settings.enrollment_starts_at);
                          const updates: any = { enrollment_ends_at: newDate };

                          if (startDateTime) {
                            const validationResult = validateAndCorrectDateTime(
                              startDateTime,
                              displayTime(settings.enrollment_starts_at),
                              selectedDate,
                              displayTime(settings.enrollment_ends_at)
                            );

                            if (validationResult.correctedEndTime) {
                              updates.enrollment_ends_at = combineDateTime(
                                selectedDate,
                                validationResult.correctedEndTime
                              );
                            }

                            // If end date is before start date, auto-correct start date backward
                            const startDateOnly = new Date(
                              startDateTime.getFullYear(),
                              startDateTime.getMonth(),
                              startDateTime.getDate()
                            );
                            const endDateOnly = new Date(
                              selectedDate.getFullYear(),
                              selectedDate.getMonth(),
                              selectedDate.getDate()
                            );

                            if (endDateOnly < startDateOnly) {
                              updates.enrollment_starts_at = combineDateTime(
                                selectedDate,
                                displayTime(settings.enrollment_starts_at)
                              );
                            }
                          }

                          updateSettings(updates);
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

                      finalEndDate = validationResult.correctedEndTime
                        ? combineDateTime(endDate, validationResult.correctedEndTime)
                        : newEndDate;
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
