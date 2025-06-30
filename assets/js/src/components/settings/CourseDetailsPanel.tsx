import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, TextControl, SelectControl, ToggleControl, Notice, Spinner } from "@wordpress/components";

// Import course settings types
import type { CourseSettings, CourseDifficultyLevel } from "../../types/courses";
import { courseDifficultyLevels } from "../../types/courses";

const CourseDetailsPanel: React.FC = () => {
  // Get settings from our store and Gutenberg store
  const { postType, settings, error, isLoading, isSaving } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      settings: select("tutorpress/course-settings").getSettings(),
      error: select("tutorpress/course-settings").getError(),
      isLoading: select("tutorpress/course-settings").getFetchState().isLoading,
      isSaving: select("core/editor").isSavingPost() && !select("core/editor").isAutosavingPost(),
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

  // Calculate total duration display
  const totalDuration = settings.course_duration;
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

      {isSaving && (
        <PanelRow>
          <div style={{ width: "100%", display: "flex", alignItems: "center", gap: "8px", padding: "8px 0" }}>
            <Spinner />
            <span>{__("Saving...", "tutorpress")}</span>
          </div>
        </PanelRow>
      )}

      {/* Difficulty Level */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <SelectControl
            label={__("Difficulty Level", "tutorpress")}
            value={settings.course_level}
            options={courseDifficultyLevels}
            onChange={(value: CourseDifficultyLevel) => updateSettings({ course_level: value })}
            help={__("Set the difficulty level that best describes this course", "tutorpress")}
            disabled={isSaving}
          />
        </div>
      </PanelRow>

      {/* Public Course Toggle */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <ToggleControl
            label={__("Public Course", "tutorpress")}
            help={
              settings.is_public_course
                ? __("This course is visible to all users", "tutorpress")
                : __("This course requires enrollment to view", "tutorpress")
            }
            checked={settings.is_public_course}
            onChange={(enabled) => updateSettings({ is_public_course: enabled })}
            disabled={isSaving}
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
              settings.enable_qna
                ? __("Students can ask questions and get answers", "tutorpress")
                : __("Q&A is disabled for this course", "tutorpress")
            }
            checked={settings.enable_qna}
            onChange={(enabled) => updateSettings({ enable_qna: enabled })}
            disabled={isSaving}
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
                value={settings.course_duration.hours.toString()}
                onChange={(value) =>
                  updateSettings({
                    course_duration: {
                      ...settings.course_duration,
                      hours: parseInt(value) || 0,
                    },
                  })
                }
                disabled={isSaving}
              />
            </div>

            <div style={{ flex: 1 }}>
              <div style={{ fontSize: "12px", fontWeight: 500 }}>{__("Minutes", "tutorpress")}</div>
              <TextControl
                type="number"
                min="0"
                max="59"
                value={settings.course_duration.minutes.toString()}
                onChange={(value) =>
                  updateSettings({
                    course_duration: {
                      ...settings.course_duration,
                      minutes: Math.min(59, parseInt(value) || 0),
                    },
                  })
                }
                disabled={isSaving}
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
