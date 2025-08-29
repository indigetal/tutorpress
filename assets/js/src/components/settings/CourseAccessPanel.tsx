import React, { useState, useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
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
import { useCourseSettings } from "../../hooks/common";
import PromoPanel from "../common/PromoPanel";

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

  // Get settings from stores
  const { postType, courseId, availableCourses, coursesLoading, coursesError } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      courseId: select("core/editor").getCurrentPostId(),
      availableCourses: select("tutorpress/prerequisites").getAvailableCourses(),
      coursesLoading: select("tutorpress/prerequisites").getCourseSelectionLoading(),
      coursesError: select("tutorpress/prerequisites").getCourseSelectionError(),
    }),
    []
  );

  // Get dispatch actions
  const { fetchAvailableCourses } = useDispatch("tutorpress/prerequisites");

  // Shared course settings hook
  const { courseSettings, setCourseSettings, ready, safeSet } = useCourseSettings();
  const cs = (courseSettings as Partial<CourseSettings> | undefined) || undefined;

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Check if prerequisites feature is available
  const isPrerequisitesEnabled = window.tutorpressAddons?.prerequisites ?? false;

  // Load available courses for prerequisites when addon is enabled
  useEffect(() => {
    if (isPrerequisitesEnabled && courseId) {
      fetchAvailableCourses();
    }
  }, [isPrerequisitesEnabled, courseId, fetchAvailableCourses]);

  if (!ready) {
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

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name="course-access-panel"
        title={__("Course Access & Enrollment", "tutorpress")}
        className="tutorpress-course-access-panel"
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  // Helper function for maximum students processing
  const processMaximumStudents = (value: string | number | null): number | null => {
    if (value === "" || value === null) return null; // allow blank (unlimited)
    const n = parseInt(value.toString(), 10);
    if (Number.isNaN(n)) return null;
    return Math.max(0, n); // allow 0 (unlimited) or positive ints
  };

  // Generate time options with 30-minute intervals (standardized across TutorPress)
  const timeOptions = generateTimeOptions(30);

  // Get current dates for date pickers (default to current date)
  const currentDate = new Date();
  const startAt = (cs?.enrollment_starts_at ?? "") as string;
  const endAt = (cs?.enrollment_ends_at ?? "") as string;
  const startDate = parseGMTString(startAt) || currentDate;
  const endDate = parseGMTString(endAt) || currentDate;

  // Local buffer for Maximum Students (allows blank input and smooth typing)
  const [maxStudentsInput, setMaxStudentsInput] = useState<string>(
    cs && Object.prototype.hasOwnProperty.call(cs, "maximum_students")
      ? cs.maximum_students == null
        ? ""
        : String(cs.maximum_students)
      : ""
  );
  useEffect(() => {
    // Sync buffer when entity changes (e.g., after save or external changes)
    const next =
      cs && Object.prototype.hasOwnProperty.call(cs, "maximum_students")
        ? cs.maximum_students == null
          ? ""
          : String(cs.maximum_students)
        : "";
    setMaxStudentsInput(next);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cs?.maximum_students]);

  // Prerequisites functionality
  const handlePrerequisiteChange = async (courseId: string) => {
    const numericId = parseInt(courseId);
    if (isNaN(numericId) || !courseId) return;

    const currentPrereqs: number[] = (cs?.course_prerequisites ?? []) as number[];
    if (!currentPrereqs.includes(numericId)) {
      const newPrereqs = [...currentPrereqs, numericId];
      safeSet({ course_prerequisites: newPrereqs });
    }
  };

  const removePrerequisite = async (courseId: number) => {
    const currentPrereqs: number[] = (cs?.course_prerequisites ?? []) as number[];
    const updatedPrereqs = currentPrereqs.filter((id: number) => id !== courseId);
    safeSet({ course_prerequisites: updatedPrereqs });
  };

  // Convert course list to select options
  const selectedPrereqIds: number[] = (cs?.course_prerequisites ?? []) as number[];
  const courseOptions: CourseOption[] = [
    {
      value: "",
      label: coursesLoading ? __("Loading courses...", "tutorpress") : __("Select a course...", "tutorpress"),
    },
    ...availableCourses
      .filter((course: Course) => !selectedPrereqIds.includes(course.id))
      .map((course: Course) => ({
        value: course.id.toString(),
        label: course.title,
      })),
  ];

  // Get selected prerequisites with course details
  const selectedPrerequisitesWithDetails = (selectedPrereqIds || [])
    .map((id: number) => availableCourses.find((course: Course) => course.id === id))
    .filter((course: Course | undefined): course is Course => course !== undefined);

  // Enrollment period enabled state (prefer entity prop with store fallback during transition)
  const enrollmentPeriodEnabled = (cs?.course_enrollment_period ?? "no") === "yes";

  return (
    <PluginDocumentSettingPanel
      name="course-access-panel"
      title={__("Course Access & Enrollment", "tutorpress")}
      className="tutorpress-course-access-panel"
    >
      {/* No legacy error state; entity-only */}

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
            value={maxStudentsInput}
            placeholder="0"
            min={0 as any}
            onChange={(value) => {
              setMaxStudentsInput(value);
            }}
            onBlur={() => {
              const newValue = processMaximumStudents(maxStudentsInput);
              safeSet({ maximum_students: newValue });
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
            checked={((courseSettings as any)?.pause_enrollment ?? "no") === "yes"}
            onChange={(checked: boolean) => {
              const value = checked ? "yes" : "no";
              safeSet({ pause_enrollment: value as any });
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
            checked={enrollmentPeriodEnabled}
            onChange={(checked) => {
              if (checked) {
                // Enable enrollment period
                safeSet({ course_enrollment_period: "yes" as any });
              } else {
                // Disable and clear dates immediately in editor state
                const cleared = {
                  course_enrollment_period: "no" as const,
                  enrollment_starts_at: "",
                  enrollment_ends_at: "",
                } satisfies Partial<CourseSettings>;
                safeSet(cleared);
              }
            }}
            help={__("Set a specific time period when students can enroll in this course.", "tutorpress")}
          />
        </div>
      </PanelRow>

      {enrollmentPeriodEnabled && (
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
                    {displayDate(startAt)}
                  </Button>

                  {startDatePickerOpen && (
                    <Popover position="bottom left" onClose={() => setStartDatePickerOpen(false)}>
                      <DatePicker
                        currentDate={startDate}
                        onChange={(date) => {
                          const newStartDate = new Date(date);
                          const newDate = combineDateTime(newStartDate, displayTime(startAt));

                          // Auto-correct end date if start date is later (simple behavior)
                          const currentEndDate = parseGMTString(endAt) || newStartDate;
                          const validation = validateAndCorrectDateTime(
                            newStartDate,
                            displayTime(startAt),
                            currentEndDate,
                            displayTime(endAt)
                          );

                          // Always update start date, and auto-correct end date if needed
                          const updates: any = { enrollment_starts_at: newDate };

                          if (validation.correctedEndDate) {
                            updates.enrollment_ends_at = combineDateTime(
                              validation.correctedEndDate,
                              displayTime(endAt)
                            );
                          }
                          if (validation.correctedEndTime) {
                            const endDateToUse = validation.correctedEndDate || currentEndDate;
                            updates.enrollment_ends_at = combineDateTime(endDateToUse, validation.correctedEndTime);
                          }

                          safeSet(updates);

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
                  value={displayTime(startAt)}
                  options={timeOptions}
                  onChange={(value) => {
                    const newStartDate = combineDateTime(startDate, value);
                    safeSet({ enrollment_starts_at: newStartDate });

                    // Auto-correct end time if it becomes invalid using our validation utility
                    if (endAt) {
                      const startDateTimeParsed = parseGMTString(startAt);
                      const endDateTimeParsed = parseGMTString(endAt);

                      if (startDateTimeParsed && endDateTimeParsed) {
                        const validationResult = validateAndCorrectDateTime(
                          startDateTimeParsed,
                          value,
                          endDateTimeParsed,
                          displayTime(endAt)
                        );

                        if (validationResult.correctedEndTime) {
                          const correctedEndDate = combineDateTime(endDate, validationResult.correctedEndTime);
                          safeSet({ enrollment_ends_at: correctedEndDate });
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
                    {displayDate(endAt)}
                  </Button>

                  {endDatePickerOpen && (
                    <Popover position="bottom left" onClose={() => setEndDatePickerOpen(false)}>
                      <DatePicker
                        currentDate={endDate}
                        onChange={(date) => {
                          const selectedDate = new Date(date);
                          const newDate = combineDateTime(selectedDate, displayTime(endAt));

                          // Auto-correct start date if end date is earlier (match Google Meet behavior)
                          const startDateTime = parseGMTString(startAt);
                          const updates: any = { enrollment_ends_at: newDate };

                          if (startDateTime) {
                            const validationResult = validateAndCorrectDateTime(
                              startDateTime,
                              displayTime(startAt),
                              selectedDate,
                              displayTime(endAt)
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
                              updates.enrollment_starts_at = combineDateTime(selectedDate, displayTime(startAt));
                            }
                          }

                          safeSet(updates);
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
                  value={displayTime(endAt)}
                  options={(() => {
                    const startDateTime = parseGMTString(startAt);
                    return startDateTime
                      ? filterEndTimeOptions(timeOptions, startDateTime, displayTime(startAt), endDate)
                      : timeOptions;
                  })()}
                  onChange={(value) => {
                    const newEndDate = combineDateTime(endDate, value);

                    // Validate and auto-correct if needed using our validation utility
                    const startDateTimeForValidation = parseGMTString(startAt);
                    let finalEndDate = newEndDate;

                    if (startDateTimeForValidation) {
                      const validationResult = validateAndCorrectDateTime(
                        startDateTimeForValidation,
                        displayTime(startAt),
                        endDate,
                        value
                      );

                      finalEndDate = validationResult.correctedEndTime
                        ? combineDateTime(endDate, validationResult.correctedEndTime)
                        : newEndDate;
                    }

                    safeSet({ enrollment_ends_at: finalEndDate });
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
