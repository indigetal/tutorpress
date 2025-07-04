import React, { useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, Button, TextareaControl } from "@wordpress/components";

// Import course settings types
import type { CourseSettings } from "../../types/courses";
import { isCourseAttachmentsEnabled } from "../../utils/addonChecker";
import VideoIntroSection from "./VideoIntroSection";

const CourseMediaPanel: React.FC = () => {
  // Get settings from our store and Gutenberg store
  const { postType, settings, error, isLoading, attachmentsMetadata, attachmentsLoading } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      settings: select("tutorpress/course-settings").getSettings(),
      error: select("tutorpress/course-settings").getError(),
      isLoading: select("tutorpress/course-settings").getFetchState().isLoading,
      attachmentsMetadata: select("tutorpress/course-settings").getAttachmentsMetadata(),
      attachmentsLoading: select("tutorpress/course-settings").getAttachmentsLoading(),
    }),
    []
  );

  // Get dispatch actions
  const { updateSettings, fetchAttachmentsMetadata } = useDispatch("tutorpress/course-settings");

  // Fetch attachment metadata when attachments change
  useEffect(() => {
    const attachmentIds = settings?.attachments || [];
    if (attachmentIds.length > 0) {
      fetchAttachmentsMetadata(attachmentIds);
    }
  }, [settings?.attachments, fetchAttachmentsMetadata]);

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Course Attachments functions (following Exercise Files pattern)
  const openCourseAttachmentsLibrary = () => {
    const currentAttachments = settings?.attachments || [];

    const mediaFrame = (window as any).wp.media({
      title: __("Select Course Attachments", "tutorpress"),
      button: {
        text: __("Add Attachments", "tutorpress"),
      },
      multiple: true,
    });

    mediaFrame.on("select", () => {
      const newAttachments = mediaFrame.state().get("selection").toJSON();
      const newAttachmentIds = newAttachments.map((attachment: any) => attachment.id);

      // Combine existing attachments with new ones, avoiding duplicates
      const allAttachmentIds = [...new Set([...currentAttachments, ...newAttachmentIds])];
      updateSettings({ attachments: allAttachmentIds });
    });

    mediaFrame.open();
  };

  const removeCourseAttachment = (attachmentId: number) => {
    const currentAttachments = settings?.attachments || [];
    const updatedAttachments = currentAttachments.filter((id: number) => id !== attachmentId);
    updateSettings({ attachments: updatedAttachments });
  };

  const attachmentCount = settings?.attachments?.length || 0;

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

      {/* Video Intro Section */}
      <VideoIntroSection />

      {/* Course Attachments Section - Only show if addon is available */}
      {isCourseAttachmentsEnabled() && (
        <PanelRow>
          <div style={{ width: "100%" }}>
            <div style={{ marginBottom: "8px", fontWeight: 600 }}>{__("Attachments", "tutorpress")}</div>

            <Button
              variant="secondary"
              onClick={openCourseAttachmentsLibrary}
              style={{ width: "100%", marginBottom: "8px" }}
            >
              {attachmentCount > 0
                ? __("Attachments", "tutorpress") + " (" + attachmentCount + " " + __("selected", "tutorpress") + ")"
                : __("Add Attachment", "tutorpress")}
            </Button>

            <p
              style={{
                fontSize: "12px",
                color: "#757575",
                margin: "0 0 8px 0",
              }}
            >
              {__("Add files that students can download to access course materials and resources.", "tutorpress")}
            </p>

            {/* Display selected files */}
            {attachmentCount > 0 && (
              <div className="tutorpress-saved-files-list">
                {settings?.attachments?.map((attachmentId: number) => {
                  // Find attachment metadata
                  const attachment = attachmentsMetadata.find((meta: any) => meta.id === attachmentId);
                  const displayName = attachment ? attachment.filename : `File ID: ${attachmentId}`;

                  return (
                    <div key={attachmentId} className="tutorpress-saved-file-item">
                      <span className="file-name" title={displayName}>
                        {attachmentsLoading ? <Spinner /> : null}
                        {displayName}
                      </span>
                      <Button
                        variant="tertiary"
                        onClick={() => removeCourseAttachment(attachmentId)}
                        className="delete-button"
                        aria-label={__("Remove attachment", "tutorpress")}
                      >
                        ×
                      </Button>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </PanelRow>
      )}

      {/* Materials Included Section */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <TextareaControl
            label={__("Materials Included", "tutorpress")}
            value={settings?.course_material_includes || ""}
            onChange={(value) => updateSettings({ course_material_includes: value })}
            placeholder={__(
              "A list of assets you will be providing for the students in this course (one per line)",
              "tutorpress"
            )}
            help={__("List each material or resource on a separate line for better readability.", "tutorpress")}
            rows={4}
          />
        </div>
      </PanelRow>
    </PluginDocumentSettingPanel>
  );
};

export default CourseMediaPanel;
