import React, { useState, useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import {
  PanelRow,
  TextControl,
  SelectControl,
  ToggleControl,
  Notice,
  Spinner,
  Button,
  BaseControl,
  DateTimePicker,
  __experimentalNumberControl as NumberControl,
} from "@wordpress/components";
import { store as editorStore } from "@wordpress/editor";
import { AddonChecker } from "../../utils/addonChecker";

// Import types and utilities
import type { CourseSettings } from "../../types/courses";
import { defaultCourseSettings } from "../../types/courses";
import { isPrerequisitesEnabled } from "../../utils/addonChecker";

interface Course {
  id: number;
  title: string;
  permalink: string;
  featured_image?: string;
  author: string;
  date_created: string;
}

interface EnrollmentSettings {
  maximum_students: number;
  course_enrollment_period: "yes" | "no";
  enrollment_starts_at: string;
  enrollment_ends_at: string;
  pause_enrollment: "yes" | "no";
}

interface CourseOption {
  value: string;
  label: string;
}

export const CourseAccessPanel: React.FC = () => {
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string>("");
  const [availableCourses, setAvailableCourses] = useState<Course[]>([]);
  const [coursesLoading, setCoursesLoading] = useState(false);
  const [courseSettings, setCourseSettings] = useState({
    course_prerequisites: [] as number[],
    maximum_students: 0,
    course_enrollment_period: "no" as "yes" | "no",
    enrollment_starts_at: "",
    enrollment_ends_at: "",
    pause_enrollment: "no" as "yes" | "no",
  });

  // Get current post data
  const { courseId, postType } = useSelect((select: any) => {
    const { getCurrentPostType } = select("core/editor");
    const { getCurrentPostId } = select("core/editor");

    return {
      courseId: getCurrentPostId(),
      postType: getCurrentPostType(),
    };
  }, []);

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Addon availability
  const [addonsAvailable, setAddonsAvailable] = useState({
    prerequisites: false,
    enrollments: false,
  });

  // Load addon availability
  useEffect(() => {
    const loadAddonStatus = () => {
      try {
        const prerequisites = AddonChecker.isPrerequisitesEnabled();
        const enrollments = AddonChecker.isEnrollmentsEnabled();

        console.log("CourseAccessPanel - Addon Status Loaded:", {
          prerequisites,
          enrollments,
        });

        setAddonsAvailable({
          prerequisites,
          enrollments,
        });
      } catch (error) {
        console.error("Error checking addon status:", error);
        // Set defaults if addon checker fails
        setAddonsAvailable({
          prerequisites: false,
          enrollments: false,
        });
      }
    };

    loadAddonStatus();
  }, []);

  // Load course settings from REST API
  useEffect(() => {
    if (!courseId) return;

    const loadCourseSettings = async () => {
      setIsLoading(true);
      try {
        const baseUrl = window.location.origin + window.location.pathname.split("/wp-admin")[0];
        const response = await fetch(`${baseUrl}/wp-json/tutorpress/v1/courses/${courseId}/settings`, {
          headers: {
            "X-WP-Nonce": (window as any).wpApiSettings?.nonce || "",
          },
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        if (data.success) {
          const settings = data.data;
          setCourseSettings({
            course_prerequisites: settings.course_prerequisites || [],
            maximum_students: settings.maximum_students || 0,
            course_enrollment_period: settings.course_enrollment_period || "no",
            enrollment_starts_at: settings.enrollment_starts_at || "",
            enrollment_ends_at: settings.enrollment_ends_at || "",
            pause_enrollment: settings.pause_enrollment || "no",
          });
        } else {
          setError(__("Failed to load course settings", "tutorpress"));
        }
      } catch (err) {
        console.error("Error loading course settings:", err);
        setError(__("Failed to load course settings", "tutorpress"));
      } finally {
        setIsLoading(false);
      }
    };

    loadCourseSettings();
  }, [courseId]);

  // Debug: Log render state
  useEffect(() => {
    console.log("CourseAccessPanel - Render State:", {
      isLoading,
      addonsAvailable,
      courseSettings,
      courseId,
    });
  }, [isLoading, addonsAvailable, courseSettings, courseId]);

  // Load available courses for prerequisites
  useEffect(() => {
    if (!addonsAvailable.prerequisites) return;

    const loadCourses = async () => {
      setCoursesLoading(true);
      try {
        const baseUrl = window.location.origin + window.location.pathname.split("/wp-admin")[0];
        const params = new URLSearchParams({
          exclude: courseId.toString(),
          per_page: "50",
        });

        const response = await fetch(`${baseUrl}/wp-json/tutorpress/v1/courses/for-prerequisites?${params}`, {
          headers: {
            "X-WP-Nonce": (window as any).wpApiSettings?.nonce || "",
          },
        });

        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        if (data.success) {
          setAvailableCourses(data.data || []);
        } else {
          setError(__("Failed to load available courses", "tutorpress"));
        }
      } catch (err) {
        console.error("Error loading courses:", err);
        setError(__("Failed to load available courses", "tutorpress"));
      } finally {
        setCoursesLoading(false);
      }
    };

    loadCourses();
  }, [addonsAvailable.prerequisites, courseId]);

  // Save course settings via REST API
  const saveCourseSettings = async (newSettings: Partial<typeof courseSettings>) => {
    setIsSaving(true);
    try {
      const baseUrl = window.location.origin + window.location.pathname.split("/wp-admin")[0];
      const response = await fetch(`${baseUrl}/wp-json/tutorpress/v1/courses/${courseId}/settings`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": (window as any).wpApiSettings?.nonce || "",
        },
        body: JSON.stringify(newSettings),
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      if (data.success) {
        // Update local state with new settings
        setCourseSettings((prev) => ({ ...prev, ...newSettings }));
        return true;
      } else {
        setError(__("Failed to save course settings", "tutorpress"));
        return false;
      }
    } catch (err) {
      console.error("Error saving course settings:", err);
      setError(__("Failed to save course settings", "tutorpress"));
      return false;
    } finally {
      setIsSaving(false);
    }
  };

  // Update a single setting
  const updateSetting = async (key: keyof typeof courseSettings, value: any) => {
    await saveCourseSettings({ [key]: value });
  };

  // Handle prerequisite selection
  const handlePrerequisiteChange = async (courseId: string) => {
    const numericId = parseInt(courseId);
    if (isNaN(numericId) || !courseId) return;

    const currentPrereqs = courseSettings.course_prerequisites || [];
    if (!currentPrereqs.includes(numericId)) {
      const newPrereqs = [...currentPrereqs, numericId];
      await updateSetting("course_prerequisites", newPrereqs);
    }
  };

  // Remove prerequisite
  const removePrerequisite = async (courseId: number) => {
    const currentPrereqs = courseSettings.course_prerequisites || [];
    const updatedPrereqs = currentPrereqs.filter((id: number) => id !== courseId);
    await updateSetting("course_prerequisites", updatedPrereqs);
  };

  // Convert course list to select options
  const courseOptions: CourseOption[] = [
    {
      value: "",
      label: coursesLoading ? __("Loading courses...", "tutorpress") : __("Select a course...", "tutorpress"),
    },
    ...availableCourses
      .filter((course) => !courseSettings.course_prerequisites.includes(course.id))
      .map((course) => ({
        value: course.id.toString(),
        label: course.title,
      })),
  ];

  // Get selected prerequisites with course details
  const selectedPrerequisitesWithDetails = courseSettings.course_prerequisites
    .map((id: number) => availableCourses.find((course) => course.id === id))
    .filter((course): course is Course => course !== undefined);

  return (
    <PluginDocumentSettingPanel
      name="course-access-enrollment"
      title={__("Course Access & Enrollment", "tutorpress")}
      className="tutorpress-course-access-panel"
    >
      {/* Error Display */}
      {error && (
        <Notice status="error" isDismissible={true} onRemove={() => setError("")}>
          {error}
        </Notice>
      )}

      {/* Loading State */}
      {isLoading && (
        <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "16px" }}>
          <Spinner />
          <span>{__("Loading course settings...", "tutorpress")}</span>
        </div>
      )}

      {!isLoading && (
        <>
          {/* Prerequisites Section */}
          {addonsAvailable.prerequisites && (
            <div className="tutorpress-settings-section">
              <h4>{__("Prerequisites", "tutorpress")}</h4>
              <p className="description">
                {__("Select courses that students must complete before enrolling in this course.", "tutorpress")}
              </p>

              {/* Add Prerequisites */}
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
                <div style={{ marginTop: "8px" }}>
                  <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
                    {__("Selected Prerequisites:", "tutorpress")}
                  </div>
                  {selectedPrerequisitesWithDetails.map((course) => (
                    <div
                      key={course.id}
                      style={{
                        display: "flex",
                        justifyContent: "space-between",
                        alignItems: "center",
                        padding: "4px 8px",
                        backgroundColor: "#f0f0f0",
                        borderRadius: "4px",
                        marginBottom: "4px",
                        fontSize: "12px",
                      }}
                    >
                      <span>{course.title}</span>
                      <Button isSmall isDestructive onClick={() => removePrerequisite(course.id)}>
                        {__("Remove", "tutorpress")}
                      </Button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {/* Maximum Students - Always Available */}
          <PanelRow>
            <TextControl
              label={__("Maximum Students", "tutorpress")}
              type="number"
              min="0"
              value={(courseSettings.maximum_students || 0).toString()}
              onChange={(value) => updateSetting("maximum_students", parseInt(value) || 0)}
              disabled={isSaving}
              help={__("Set 0 for unlimited enrollment", "tutorpress")}
              __next40pxDefaultSize
              __nextHasNoMarginBottom
            />
          </PanelRow>

          {/* TODO: Schedule Fields - Core Feature */}
          {/* Research needed: toggle + date/time + "Show coming soon" checkbox */}
          {/* Not associated with Prerequisites or Enrollments addons */}

          {/* Enrollment Settings Section (Addon-dependent) */}
          {addonsAvailable.enrollments && (
            <div className="tutorpress-settings-section">
              <h4>{__("Enrollment Settings", "tutorpress")}</h4>

              {/* Pause Enrollment - Enrollments Addon Feature */}
              <ToggleControl
                label={__("Pause Enrollment", "tutorpress")}
                help={
                  courseSettings.pause_enrollment === "yes"
                    ? __("New enrollments are currently paused", "tutorpress")
                    : __("Students can enroll in this course", "tutorpress")
                }
                checked={courseSettings.pause_enrollment === "yes"}
                onChange={(checked) => updateSetting("pause_enrollment", checked ? "yes" : "no")}
                disabled={isSaving}
                __nextHasNoMarginBottom
              />

              {/* Enrollment Period - Enrollments Addon Feature */}
              <ToggleControl
                label={__("Set Enrollment Period", "tutorpress")}
                help={__("Restrict enrollment to specific dates.", "tutorpress")}
                checked={courseSettings.course_enrollment_period === "yes"}
                onChange={(checked) => updateSetting("course_enrollment_period", checked ? "yes" : "no")}
                __nextHasNoMarginBottom
              />

              {/* Enrollment Start/End Dates - Enrollments Addon Feature */}
              {courseSettings.course_enrollment_period === "yes" && (
                <div style={{ marginTop: "12px", paddingLeft: "8px" }}>
                  <BaseControl label={__("Enrollment Start Date", "tutorpress")} __nextHasNoMarginBottom>
                    <DateTimePicker
                      currentDate={courseSettings.enrollment_starts_at}
                      onChange={(date) => updateSetting("enrollment_starts_at", date || "")}
                      is12Hour
                    />
                  </BaseControl>

                  <div style={{ marginTop: "12px" }}>
                    <BaseControl label={__("Enrollment End Date", "tutorpress")} __nextHasNoMarginBottom>
                      <DateTimePicker
                        currentDate={courseSettings.enrollment_ends_at}
                        onChange={(date) => updateSetting("enrollment_ends_at", date || "")}
                        is12Hour
                      />
                    </BaseControl>
                  </div>
                </div>
              )}
            </div>
          )}
        </>
      )}
    </PluginDocumentSettingPanel>
  );
};

export default CourseAccessPanel;
