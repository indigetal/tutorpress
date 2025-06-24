import React, { useState, useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, TextControl, SelectControl, ToggleControl, Notice, Spinner } from "@wordpress/components";

// Import course settings types
import type { CourseSettings, CourseDifficultyLevel } from "../../types/courses";
import { defaultCourseSettings, courseDifficultyLevels } from "../../types/courses";

const CourseDetailsPanel: React.FC = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string>("");

  const { postType, courseSettings, isSaving, postId } = useSelect((select: any) => {
    const { getCurrentPostType } = select("core/editor");
    const { getEditedPostAttribute } = select("core/editor");
    const { isSavingPost } = select("core/editor");
    const { getCurrentPostId } = select("core/editor");

    return {
      postType: getCurrentPostType(),
      courseSettings: getEditedPostAttribute("course_settings") || defaultCourseSettings,
      isSaving: isSavingPost(),
      postId: getCurrentPostId(),
    };
  }, []);

  const { editPost } = useDispatch("core/editor");

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  const updateSetting = (key: string, value: any) => {
    const newSettings = { ...courseSettings };

    if (key.includes(".")) {
      const keys = key.split(".");
      let current = newSettings;

      for (let i = 0; i < keys.length - 1; i++) {
        current[keys[i]] = { ...current[keys[i]] };
        current = current[keys[i]];
      }

      current[keys[keys.length - 1]] = value;
    } else {
      newSettings[key] = value;
    }

    editPost({ course_settings: newSettings });
  };

  // Calculate total duration display
  const totalDuration = courseSettings.course_duration;
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

      {/* Difficulty Level */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <SelectControl
            label={__("Difficulty Level", "tutorpress")}
            value={courseSettings.course_level}
            options={courseDifficultyLevels}
            onChange={(value: CourseDifficultyLevel) => updateSetting("course_level", value)}
            disabled={isSaving || isLoading}
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
              courseSettings.is_public_course
                ? __("This course is visible to all users", "tutorpress")
                : __("This course requires enrollment to view", "tutorpress")
            }
            checked={courseSettings.is_public_course}
            onChange={(enabled) => updateSetting("is_public_course", enabled)}
            disabled={isSaving || isLoading}
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
              courseSettings.enable_qna
                ? __("Students can ask questions and get answers", "tutorpress")
                : __("Q&A is disabled for this course", "tutorpress")
            }
            checked={courseSettings.enable_qna}
            onChange={(enabled) => updateSetting("enable_qna", enabled)}
            disabled={isSaving || isLoading}
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
                value={courseSettings.course_duration.hours.toString()}
                onChange={(value) => updateSetting("course_duration.hours", parseInt(value) || 0)}
                disabled={isSaving || isLoading}
              />
            </div>

            <div style={{ flex: 1 }}>
              <div style={{ fontSize: "12px", fontWeight: 500 }}>{__("Minutes", "tutorpress")}</div>
              <TextControl
                type="number"
                min="0"
                max="59"
                value={courseSettings.course_duration.minutes.toString()}
                onChange={(value) => updateSetting("course_duration.minutes", Math.min(59, parseInt(value) || 0))}
                disabled={isSaving || isLoading}
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
            {__("Current duration: ", "tutorpress") + durationText}
          </p>
        </div>
      </PanelRow>
    </PluginDocumentSettingPanel>
  );
};

export default CourseDetailsPanel;
