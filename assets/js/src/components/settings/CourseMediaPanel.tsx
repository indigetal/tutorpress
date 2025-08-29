import React, { useEffect, useRef, useState } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, Button, TextareaControl } from "@wordpress/components";

// Import course settings types
import type { CourseSettings } from "../../types/courses";
import { isCourseAttachmentsEnabled } from "../../utils/addonChecker";
import VideoIntroSection from "./VideoIntroSection";
import { useCourseSettings } from "../../hooks/common";
import PromoPanel from "../common/PromoPanel";

const CourseMediaPanel: React.FC = () => {
  // Shared hook for course settings
  const { courseSettings, ready, safeSet } = useCourseSettings();
  const cs = (courseSettings as Partial<CourseSettings> | undefined) || undefined;
  const attachmentIds = (cs?.attachments || []) as number[];

  // Read editor post type and attachments metadata via store
  const { postType, attachmentsMetadata, attachmentsLoading } = useSelect(
    (select: any) => {
      const metaStore = select("tutorpress/attachments-meta");
      return {
        postType: select("core/editor").getCurrentPostType(),
        attachmentsMetadata: metaStore.getAttachmentsMetadata(attachmentIds),
        attachmentsLoading: metaStore.getAttachmentsLoading(),
      };
    },
    [attachmentIds.join(",")]
  );

  // No legacy course-settings dispatches remain
  const { fetchAttachmentsMetadata } = useDispatch("tutorpress/attachments-meta");

  // Removed legacy hydration on mount; rely on entity-prop/REST lifecycle

  // UI ids with write-through to entity; guard against flicker while entity catches up
  const [uiIds, setUiIds] = useState<number[]>(attachmentIds || []);
  const lastWriteRef = useRef<number[] | null>(null);
  useEffect(() => {
    const entityStr = (attachmentIds || []).join(",");
    const lastStr = (lastWriteRef.current || []).join(",");
    const uiStr = (uiIds || []).join(",");
    if (lastWriteRef.current) {
      if (entityStr === lastStr && uiStr !== entityStr) {
        setUiIds(attachmentIds || []);
        lastWriteRef.current = null;
      }
    } else if (uiStr !== entityStr) {
      setUiIds(attachmentIds || []);
    }
  }, [attachmentIds.join(",")]);

  const optimisticMetaById: Record<number, { id: number; filename: string }> = {};

  // Fetch attachment metadata when attachments change
  useEffect(() => {
    if (uiIds.length > 0) {
      fetchAttachmentsMetadata(uiIds);
    }
  }, [uiIds.join(","), fetchAttachmentsMetadata]);

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Show loading while entity not ready
  if (!ready) {
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

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name="course-media-settings"
        title={__("Course Media", "tutorpress")}
        className="tutorpress-course-media-panel"
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  // Course Attachments functions (following Exercise Files pattern)
  const openCourseAttachmentsLibrary = () => {
    const currentAttachments = uiIds || [];

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
      // Write via entity prop; show optimistic ids and fetch metadata
      setUiIds(allAttachmentIds);
      lastWriteRef.current = allAttachmentIds;
      safeSet({ attachments: allAttachmentIds as any });
      // Ensure fetch sees updated ids on next tick
      setTimeout(() => fetchAttachmentsMetadata(allAttachmentIds), 0);
    });

    mediaFrame.open();
  };

  const removeCourseAttachment = (attachmentId: number) => {
    const currentAttachments = uiIds || [];
    const updatedAttachments = currentAttachments.filter((id: number) => id !== attachmentId);
    // Write via entity prop and schedule metadata refresh (remaining ids)
    setUiIds(updatedAttachments);
    lastWriteRef.current = updatedAttachments;
    safeSet({ attachments: updatedAttachments as any });
    setTimeout(() => fetchAttachmentsMetadata(updatedAttachments), 0);
  };

  const attachmentCount = uiIds?.length || 0;

  // Duplicate guard removed

  return (
    <PluginDocumentSettingPanel
      name="course-media-settings"
      title={__("Course Media", "tutorpress")}
      className="tutorpress-course-media-panel"
    >
      {/* No legacy error state; entity-only */}

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
                {uiIds?.map((attachmentId: number) => {
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
            value={cs?.course_material_includes ?? ""}
            onChange={(value) => safeSet({ course_material_includes: value })}
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
