import React, { useState, useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import {
  PanelRow,
  TextControl,
  SelectControl,
  ToggleControl,
  CheckboxControl,
  Notice,
  Spinner,
  Button,
  BaseControl,
  DateTimePicker,
} from "@wordpress/components";
import { store as editorStore } from "@wordpress/editor";
import apiFetch from "@wordpress/api-fetch";
import { AddonChecker } from "../../utils/addonChecker";

// Import types and utilities
import type { CourseSettings } from "../../types/courses";
import { defaultCourseSettings } from "../../types/courses";

interface Course {
  id: number;
  title: { rendered: string };
}

const CourseAccessPanel: React.FC = () => {
  const { postType } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
    }),
    []
  );

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  const [isLoadingCourses, setIsLoadingCourses] = useState(false);
  const [availableCourses, setAvailableCourses] = useState<Course[]>([]);
  const [courseFetchError, setCourseFetchError] = useState<string | null>(null);

  // Get course settings from Gutenberg's data store
  const courseSettings = useSelect((select) => {
    const editorSelect = select(editorStore) as any;
    const settings = editorSelect.getEditedPostAttribute("course_settings") || {};
    return {
      ...defaultCourseSettings,
      ...settings,
    };
  }, []);

  const { editPost } = useDispatch(editorStore);

  // Update course settings - same pattern as CourseDetailsPanel
  const updateCourseSetting = (key: keyof CourseSettings, value: any) => {
    const newSettings = { ...courseSettings };
    newSettings[key] = value;
    editPost({ course_settings: newSettings });
  };

  // Fetch available courses for prerequisites
  useEffect(() => {
    if (!AddonChecker.isPrerequisitesEnabled()) return;

    setIsLoadingCourses(true);
    setCourseFetchError(null);

    apiFetch({
      path: "/wp/v2/courses?per_page=100&status=publish",
    })
      .then((response: any) => {
        // Ensure response is an array and handle different response formats
        let courses: Course[] = [];
        if (Array.isArray(response)) {
          courses = response;
        } else if (response && Array.isArray(response.data)) {
          courses = response.data;
        } else if (response && response.success && Array.isArray(response.data)) {
          courses = response.data;
        }

        setAvailableCourses(courses);
      })
      .catch((error) => {
        console.error("Error fetching courses:", error);
        setCourseFetchError(__("Failed to load courses. Please refresh the page and try again.", "tutorpress"));
        setAvailableCourses([]); // Ensure it's always an array
      })
      .finally(() => {
        setIsLoadingCourses(false);
      });
  }, []);

  // Get selected prerequisites with course details - with safety checks
  const selectedPrerequisitesWithDetails = (courseSettings.course_prerequisites || [])
    .map((id: number) => {
      // Ensure availableCourses is an array before calling find
      if (!Array.isArray(availableCourses)) {
        return undefined;
      }
      return availableCourses.find((course: Course) => course.id === id);
    })
    .filter((course: Course | undefined): course is Course => course !== undefined);

  // Get available courses for the dropdown (excluding already selected) - with safety checks
  const availableCoursesForDropdown = Array.isArray(availableCourses)
    ? availableCourses
        .filter((course: Course) => !(courseSettings.course_prerequisites || []).includes(course.id))
        .map((course: Course) => ({
          label: course.title.rendered,
          value: course.id.toString(),
        }))
    : [];

  const handlePrerequisiteAdd = (courseId: number) => {
    const currentPrerequisites = courseSettings.course_prerequisites || [];
    updateCourseSetting("course_prerequisites", [...currentPrerequisites, courseId]);
  };

  const handlePrerequisiteRemove = (courseId: number) => {
    const currentPrerequisites = courseSettings.course_prerequisites || [];
    updateCourseSetting(
      "course_prerequisites",
      currentPrerequisites.filter((id: number) => id !== courseId)
    );
  };

  return (
    <PluginDocumentSettingPanel
      name="course-access-panel"
      title={__("Course Access & Enrollment", "tutorpress")}
      className="tutorpress-course-access-panel"
    >
      {/* Prerequisites Section */}
      {AddonChecker.isPrerequisitesEnabled() && (
        <>
          <PanelRow>
            <BaseControl
              label={__("Prerequisites", "tutorpress")}
              help={__("Students must complete these courses before enrolling.", "tutorpress")}
            >
              {isLoadingCourses ? (
                <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
                  <Spinner />
                  <span>{__("Loading courses...", "tutorpress")}</span>
                </div>
              ) : courseFetchError ? (
                <Notice status="error" isDismissible={false}>
                  <p>{courseFetchError}</p>
                </Notice>
              ) : (
                <>
                  {/* Selected Prerequisites */}
                  {selectedPrerequisitesWithDetails.length > 0 && (
                    <div style={{ marginBottom: "12px" }}>
                      <strong>{__("Selected Prerequisites:", "tutorpress")}</strong>
                      <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
                        {selectedPrerequisitesWithDetails.map((course: Course) => (
                          <li
                            key={course.id}
                            style={{
                              display: "flex",
                              justifyContent: "space-between",
                              alignItems: "center",
                              margin: "4px 0",
                            }}
                          >
                            <span>{course.title.rendered}</span>
                            <Button isSmall isDestructive onClick={() => handlePrerequisiteRemove(course.id)}>
                              {__("Remove", "tutorpress")}
                            </Button>
                          </li>
                        ))}
                      </ul>
                    </div>
                  )}

                  {/* Add New Prerequisite */}
                  {availableCoursesForDropdown.length > 0 ? (
                    <SelectControl
                      label={__("Add Prerequisite Course", "tutorpress")}
                      value=""
                      options={[
                        { label: __("Select a course...", "tutorpress"), value: "" },
                        ...availableCoursesForDropdown,
                      ]}
                      onChange={(value) => {
                        if (value) {
                          handlePrerequisiteAdd(parseInt(value, 10));
                        }
                      }}
                    />
                  ) : (
                    <p style={{ fontStyle: "italic", color: "#666" }}>
                      {selectedPrerequisitesWithDetails.length > 0
                        ? __("All available courses have been selected as prerequisites.", "tutorpress")
                        : __("No courses available for prerequisites.", "tutorpress")}
                    </p>
                  )}
                </>
              )}
            </BaseControl>
          </PanelRow>
        </>
      )}

      {/* Maximum Students - Always visible (core Tutor Pro feature) */}
      <PanelRow>
        <TextControl
          label={__("Maximum Students", "tutorpress")}
          help={__("Set the maximum number of students who can enroll. Leave empty for unlimited.", "tutorpress")}
          value={courseSettings.maximum_students || ""}
          onChange={(value) => updateCourseSetting("maximum_students", parseInt(value, 10) || 0)}
          type="number"
          min="0"
        />
      </PanelRow>

      {/* Enrollment Period Section - Only show if Enrollments addon is enabled */}
      {AddonChecker.isEnrollmentsEnabled() && (
        <>
          <PanelRow>
            <ToggleControl
              label={__("Set Enrollment Period", "tutorpress")}
              help={__("Enable to set specific start and end dates for course enrollment.", "tutorpress")}
              checked={courseSettings.course_enrollment_period === "yes"}
              onChange={(checked) => updateCourseSetting("course_enrollment_period", checked ? "yes" : "no")}
            />
          </PanelRow>

          {courseSettings.course_enrollment_period === "yes" && (
            <>
              <PanelRow>
                <BaseControl label={__("Enrollment Start Date & Time", "tutorpress")}>
                  <DateTimePicker
                    currentDate={courseSettings.enrollment_starts_at || null}
                    onChange={(date) => updateCourseSetting("enrollment_starts_at", date)}
                    is12Hour={true}
                  />
                </BaseControl>
              </PanelRow>

              <PanelRow>
                <BaseControl label={__("Enrollment End Date & Time", "tutorpress")}>
                  <DateTimePicker
                    currentDate={courseSettings.enrollment_ends_at || null}
                    onChange={(date) => updateCourseSetting("enrollment_ends_at", date)}
                    is12Hour={true}
                  />
                </BaseControl>
              </PanelRow>
            </>
          )}

          <PanelRow>
            <CheckboxControl
              label={__("Pause Enrollment", "tutorpress")}
              help={__("Temporarily disable new enrollments for this course.", "tutorpress")}
              checked={courseSettings.pause_enrollment === "yes"}
              onChange={(checked) => updateCourseSetting("pause_enrollment", checked ? "yes" : "no")}
            />
          </PanelRow>
        </>
      )}

      {/* Show notice when addons are disabled */}
      {!AddonChecker.isPrerequisitesEnabled() && !AddonChecker.isEnrollmentsEnabled() && (
        <Notice status="info" isDismissible={false}>
          <p>
            {__("Additional enrollment features are available when Tutor LMS Pro addons are enabled:", "tutorpress")}
          </p>
          <ul style={{ marginLeft: "20px" }}>
            <li>{__("Prerequisites addon: Course prerequisites", "tutorpress")}</li>
            <li>{__("Enrollments addon: Enrollment periods and controls", "tutorpress")}</li>
          </ul>
        </Notice>
      )}
    </PluginDocumentSettingPanel>
  );
};

export default CourseAccessPanel;
