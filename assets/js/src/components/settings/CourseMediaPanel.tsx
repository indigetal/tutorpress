import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner } from "@wordpress/components";

// Import course settings types
import type { CourseSettings } from "../../types/courses";

const CourseMediaPanel: React.FC = () => {
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
        name="course-media-settings"
        title={__("Course Media", "tutorpress")}
        className="tutorpress-course-media-panel"
      >
        <PanelRow>
          <div style={{ width: "100%", textAlign: "center", padding: "20px 0" }}>
            <Spinner />
          </div>
        </PanelRow>
      </PluginDocumentSettingPanel>
    );
  }

  return (
    <PluginDocumentSettingPanel
      name="course-media-settings"
      title={__("Course Media", "tutorpress")}
      className="tutorpress-course-media-panel"
    >
      {error && (
        <PanelRow>
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        </PanelRow>
      )}

      {/* Placeholder content - we'll add sections incrementally */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <p>{__("Course Media settings will be added here.", "tutorpress")}</p>
        </div>
      </PanelRow>
    </PluginDocumentSettingPanel>
  );
};

export default CourseMediaPanel;
