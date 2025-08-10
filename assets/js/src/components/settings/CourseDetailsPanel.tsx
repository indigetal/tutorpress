import React, { useEffect, useRef, useState } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { useEntityProp } from "@wordpress/core-data";
import { PanelRow, TextControl, SelectControl, ToggleControl, Notice, Spinner } from "@wordpress/components";

// Import course settings types
import type { CourseSettings, CourseDifficultyLevel } from "../../types/courses";
import { courseDifficultyLevels } from "../../types/courses";

const CourseDetailsPanel: React.FC = () => {
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

  // Bind Gutenberg composite course_settings for incremental migration
  // Bind directly to the courses post type to avoid transient undefined postType during first render
  const [courseSettings, setCourseSettings] = useEntityProp("postType", "courses", "course_settings");
  const cs = (courseSettings as Partial<CourseSettings> | undefined) || undefined;
  const enableQna = cs?.enable_qna ?? settings.enable_qna;

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Calculate total duration display (entity-prop only for course_duration)
  const totalDuration = cs?.course_duration ?? { hours: 0, minutes: 0 };
  // Local UI buffers to allow empty/partial input without snapping to 0
  const [hoursInput, setHoursInput] = useState<string>(String(totalDuration.hours));
  const [minutesInput, setMinutesInput] = useState<string>(String(totalDuration.minutes));

  // Initialize local inputs once from entity prop (avoid fighting user typing)
  const initializedRef = useRef(false);
  useEffect(() => {
    if (!initializedRef.current) {
      setHoursInput(String(totalDuration.hours));
      setMinutesInput(String(totalDuration.minutes));
      initializedRef.current = true;
    }
  }, [totalDuration.hours, totalDuration.minutes]);

  // Show loading state while fetching settings
  if (isLoading) {
    return (
      <PluginDocumentSettingPanel
        name="course-details-settings"
        title={__("Course Details", "tutorpress")}
        className="tutorpress-course-details-panel"
      >
        <PanelRow>
          <div style={{ width: "100%", textAlign: "center", padding: "20px 0" }}>
            <Spinner />
          </div>
        </PanelRow>
      </PluginDocumentSettingPanel>
    );
  }
  const hasDuration = totalDuration.hours > 0 || totalDuration.minutes > 0;
  const durationText = hasDuration
    ? `${totalDuration.hours}h ${totalDuration.minutes}m`
    : __("No duration set", "tutorpress");

  return (
    <PluginDocumentSettingPanel
      name="course-details-settings"
      title={__("Course Details", "tutorpress")}
      className="tutorpress-course-details-panel"
    >
      {error && (
        <PanelRow>
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        </PanelRow>
      )}

      {/* Difficulty Level */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <SelectControl
            label={__("Difficulty Level", "tutorpress")}
            value={cs?.course_level ?? settings.course_level}
            options={courseDifficultyLevels}
            onChange={(value: CourseDifficultyLevel) => {
              setCourseSettings((prev: any) => ({ ...(prev || {}), course_level: value }));
              updateSettings({ course_level: value });
            }}
            help={__("Set the difficulty level that best describes this course", "tutorpress")}
          />
        </div>
      </PanelRow>

      {/* Public Course Toggle */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <ToggleControl
            label={__("Public Course", "tutorpress")}
            help={
              ((courseSettings as any)?.is_public_course ?? settings.is_public_course)
                ? __("This course is visible to all users", "tutorpress")
                : __("This course requires enrollment to view", "tutorpress")
            }
            checked={!!(cs?.is_public_course ?? settings.is_public_course)}
            onChange={(enabled) => {
              setCourseSettings((prev: any) => ({ ...(prev || {}), is_public_course: !!enabled }));
              updateSettings({ is_public_course: !!enabled });
            }}
          />

          <p
            style={{
              fontSize: "12px",
              color: "#757575",
              margin: "4px 0 0 0",
            }}
          >
            {__("Public courses can be viewed by anyone without enrollment", "tutorpress")}
          </p>
        </div>
      </PanelRow>

      {/* Q&A Toggle */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <ToggleControl
            label={__("Q&A", "tutorpress")}
            help={
              enableQna
                ? __("Students can ask questions and get answers", "tutorpress")
                : __("Q&A is disabled for this course", "tutorpress")
            }
            checked={!!enableQna}
            onChange={(enabled) => {
              setCourseSettings((prev: any) => ({ ...(prev || {}), enable_qna: !!enabled }));
              updateSettings({ enable_qna: !!enabled });
            }}
          />

          <p
            style={{
              fontSize: "12px",
              color: "#757575",
              margin: "4px 0 0 0",
            }}
          >
            {__("Enable Q&A to allow students to ask questions about the course", "tutorpress")}
          </p>
        </div>
      </PanelRow>

      {/* Course Duration */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <div style={{ marginBottom: "8px", fontWeight: 600 }}>{__("Total Course Duration", "tutorpress")}</div>

          <div
            style={{
              display: "flex",
              gap: "8px",
              alignItems: "flex-end",
            }}
          >
            <div style={{ flex: 1 }}>
              <div style={{ fontSize: "12px", fontWeight: 500 }}>{__("Hours", "tutorpress")}</div>
              <TextControl
                type="number"
                min="0"
                value={hoursInput}
                onChange={(value) => {
                  setHoursInput(value);
                }}
                onBlur={() => {
                  const hours = Math.max(0, parseInt(hoursInput || "0", 10) || 0);
                  const minutes = Math.min(
                    59,
                    Math.max(0, parseInt(minutesInput || String(totalDuration.minutes), 10) || 0)
                  );
                  setHoursInput(String(hours));
                  setCourseSettings((prev: Partial<CourseSettings> | undefined) => ({
                    ...(prev || {}),
                    course_duration: {
                      hours,
                      minutes,
                    },
                  }));
                }}
              />
            </div>

            <div style={{ flex: 1 }}>
              <div style={{ fontSize: "12px", fontWeight: 500 }}>{__("Minutes", "tutorpress")}</div>
              <TextControl
                type="number"
                min="0"
                max="59"
                value={minutesInput}
                onChange={(value) => {
                  setMinutesInput(value);
                }}
                onBlur={() => {
                  const hours = Math.max(0, parseInt(hoursInput || String(totalDuration.hours), 10) || 0);
                  const minutes = Math.min(59, Math.max(0, parseInt(minutesInput || "0", 10) || 0));
                  setMinutesInput(String(minutes));
                  setCourseSettings((prev: Partial<CourseSettings> | undefined) => ({
                    ...(prev || {}),
                    course_duration: {
                      hours,
                      minutes,
                    },
                  }));
                }}
              />
            </div>
          </div>

          <p
            style={{
              fontSize: "12px",
              color: "#757575",
              margin: "4px 0 0 0",
            }}
          >
            {__("Set the total time required to complete this course", "tutorpress")}
          </p>
        </div>
      </PanelRow>
    </PluginDocumentSettingPanel>
  );
};

export default CourseDetailsPanel;
