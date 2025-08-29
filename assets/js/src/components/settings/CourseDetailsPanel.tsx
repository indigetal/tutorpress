import React, { useEffect, useRef, useState } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect } from "@wordpress/data";
// useEntityProp replaced by shared hook
import { PanelRow, TextControl, SelectControl, ToggleControl, Notice, Spinner } from "@wordpress/components";

// Import course settings types
import type { CourseSettings, CourseDifficultyLevel } from "../../types/courses";
import { courseDifficultyLevels } from "../../types/courses";
import { useCourseSettings } from "../../hooks/common";
import PromoPanel from "../common/PromoPanel";

const CourseDetailsPanel: React.FC = () => {
  // Get current post type
  const { postType } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
    }),
    []
  );

  // Shared hook for course settings
  const { courseSettings, setCourseSettings, ready, safeSet } = useCourseSettings();
  const cs = (courseSettings as Partial<CourseSettings> | undefined) || undefined;
  const enableQna = cs?.enable_qna ?? false;

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

  const entityReady = !!ready;

  // Show loading state while entity not ready
  if (!entityReady) {
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

  // Check Freemius premium access
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false; // Default to false (fail closed) if Freemius data not available

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name="course-details-settings"
        title={__("Course Details", "tutorpress")}
        className="tutorpress-course-details-panel"
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  const hasDuration = totalDuration.hours > 0 || totalDuration.minutes > 0;
  const durationText = hasDuration
    ? `${totalDuration.hours}h ${totalDuration.minutes}m`
    : __("No duration set", "tutorpress");

  // safeSet provided by shared hook; shallow merge at top-level

  return (
    <PluginDocumentSettingPanel
      name="course-details-settings"
      title={__("Course Details", "tutorpress")}
      className="tutorpress-course-details-panel"
    >
      {/* No legacy error state; entity-only */}

      {/* Difficulty Level */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <SelectControl
            label={__("Difficulty Level", "tutorpress")}
            value={cs?.course_level ?? "all_levels"}
            options={courseDifficultyLevels}
            onChange={(value: CourseDifficultyLevel) => {
              safeSet({ course_level: value });
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
              (cs?.is_public_course ?? false)
                ? __("This course is visible to all users", "tutorpress")
                : __("This course requires enrollment to view", "tutorpress")
            }
            checked={!!(cs?.is_public_course ?? false)}
            onChange={(enabled) => {
              // If enabling public course and course is currently paid, show warning but allow the change
              // The CoursePricingPanel will auto-reset to free when this changes
              safeSet({ is_public_course: !!enabled });
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

          {/* Show notice when public course is enabled and pricing model is paid */}
          {(cs?.is_public_course ?? false) && cs?.pricing_model === "paid" && (
            <div style={{ marginTop: "8px" }}>
              <Notice status="info" isDismissible={false}>
                {__(
                  "This course will be automatically set to free pricing since public courses cannot be paid.",
                  "tutorpress"
                )}
              </Notice>
            </div>
          )}
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
            onChange={(enabled) => safeSet({ enable_qna: !!enabled })}
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
                  const nextDuration = {
                    ...(cs?.course_duration || {}),
                    hours,
                    minutes,
                  } as CourseSettings["course_duration"];
                  safeSet({ course_duration: nextDuration } as Partial<CourseSettings>);
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
                  const nextDuration = {
                    ...(cs?.course_duration || {}),
                    hours,
                    minutes,
                  } as CourseSettings["course_duration"];
                  safeSet({ course_duration: nextDuration } as Partial<CourseSettings>);
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
