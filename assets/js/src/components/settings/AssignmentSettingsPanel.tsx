import React, { useCallback, useState, useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { useEntityProp } from "@wordpress/core-data";
import { PanelRow, TextControl, SelectControl, Button, Notice, Spinner } from "@wordpress/components";

// Import Content Drip Panel
import ContentDripPanel from "./ContentDripPanel";
import { useCourseId } from "../../hooks/curriculum/useCourseId";
import type { ContentDripItemSettings } from "../../types/content-drip";
import PromoPanel from "../common/PromoPanel";

interface AssignmentSettings {
  time_duration: {
    value: number;
    unit: string;
  };
  total_points: number;
  pass_points: number;
  file_upload_limit: number;
  file_size_limit: number;
  attachments_enabled: boolean;
  instructor_attachments?: number[]; // Array of attachment IDs
}

const AssignmentSettingsPanel: React.FC = () => {
  const [attachmentsMetadata, setAttachmentsMetadata] = useState<any[]>([]);
  const [isLoadingAttachments, setIsLoadingAttachments] = useState(false);

  // Get course ID for content drip context
  const courseId = useCourseId();

  const { postType, isSaving, postId } = useSelect((select: any) => {
    const { getCurrentPostType } = select("core/editor");
    const { isSavingPost } = select("core/editor");
    const { getCurrentPostId } = select("core/editor");

    return {
      postType: getCurrentPostType(),
      isSaving: isSavingPost(),
      postId: getCurrentPostId(),
    };
  }, []);

  const { editPost } = useDispatch("core/editor");

  // Only show for assignment post type
  if (postType !== "tutor_assignments") {
    return null;
  }

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name={"assignment-settings"}
        title={__("Assignment Settings", "tutorpress")}
        className={"assignment-settings-panel"}
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  // Use composite assignment_settings field (following Course/Lesson patterns)
  const [assignmentSettings, setAssignmentSettings] = useEntityProp(
    "postType",
    "tutor_assignments",
    "assignment_settings"
  );

  // Fetch attachments metadata when attachments change
  useEffect(() => {
    const fetchAttachmentsMetadata = async () => {
      const attachmentIds = assignmentSettings.instructor_attachments || [];
      if (attachmentIds.length === 0) {
        setAttachmentsMetadata([]);
        return;
      }

      setIsLoadingAttachments(true);
      try {
        // Fetch metadata for each attachment using WordPress REST API
        const metadataPromises = attachmentIds.map(async (attachmentId: number) => {
          try {
            // Use WordPress REST API with proper authentication headers
            const apiBase = (window as any).wpApiSettings?.root || "/wp-json/";
            const nonce = (window as any).wpApiSettings?.nonce || "";

            const response = await fetch(`${apiBase}wp/v2/media/${attachmentId}`, {
              headers: {
                "X-WP-Nonce": nonce,
                "Content-Type": "application/json",
              },
            });

            if (!response.ok) {
              throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();

            // Extract filename with extension
            let filename =
              data.title?.rendered || data.media_details?.file?.split("/").pop() || `File ID: ${attachmentId}`;

            // Add file extension if we have mime_type but no extension in filename
            if (data.mime_type && !filename.includes(".")) {
              const extension = data.mime_type.split("/")[1];
              if (extension) {
                filename = `${filename}.${extension}`;
              }
            }

            return {
              id: attachmentId,
              filename: filename,
              url: data.source_url,
              mime_type: data.mime_type,
            };
          } catch (error) {
            console.error(`Failed to fetch metadata for attachment ${attachmentId}:`, error);
          }
          return {
            id: attachmentId,
            filename: `File ID: ${attachmentId}`,
            url: "",
            mime_type: "",
          };
        });

        const metadata = await Promise.all(metadataPromises);
        setAttachmentsMetadata(metadata.filter(Boolean));
      } catch (error) {
        console.error("Failed to fetch attachments metadata:", error);
      } finally {
        setIsLoadingAttachments(false);
      }
    };

    fetchAttachmentsMetadata();
  }, [assignmentSettings.instructor_attachments]);

  const updateSetting = (key: string, value: any) => {
    const newSettings = { ...assignmentSettings };

    if (key.includes(".")) {
      const [parentKey, childKey] = key.split(".");
      newSettings[parentKey] = {
        ...newSettings[parentKey],
        [childKey]: value,
      };
    } else {
      newSettings[key] = value;
    }

    setAssignmentSettings(newSettings);
  };

  const openMediaLibrary = () => {
    const currentAttachments = assignmentSettings.instructor_attachments || [];

    // Open WordPress Media Library
    const mediaFrame = (window as any).wp.media({
      title: __("Select Assignment Attachments", "tutorpress"),
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
      updateSetting("instructor_attachments", allAttachmentIds);
    });

    mediaFrame.open();
  };

  const removeAttachment = (attachmentId: number) => {
    const currentAttachments = assignmentSettings.instructor_attachments || [];
    const updatedAttachments = currentAttachments.filter((id: number) => id !== attachmentId);
    updateSetting("instructor_attachments", updatedAttachments);
  };

  // Handle content drip settings changes
  // Note: ContentDripPanel handles its own saving through TutorPress content drip endpoint
  const handleContentDripChange = useCallback((newSettings: ContentDripItemSettings) => {
    // No-op: ContentDripPanel manages its own state and saving
    // This prevents content drip settings from being included in assignment_settings
    // which would cause 500 errors in WordPress core assignment endpoint
  }, []);

  const timeUnitOptions = [
    { label: __("Hours", "tutorpress"), value: "hours" },
    { label: __("Days", "tutorpress"), value: "days" },
    { label: __("Weeks", "tutorpress"), value: "weeks" },
  ];

  // Validation warnings
  const warnings = [];
  if (assignmentSettings.total_points > 0 && assignmentSettings.pass_points > assignmentSettings.total_points) {
    warnings.push(__("Passing points cannot exceed total points.", "tutorpress"));
  }

  const attachmentCount = assignmentSettings.instructor_attachments?.length || 0;

  return (
    <PluginDocumentSettingPanel
      name="assignment-settings"
      title={__("Assignment Settings", "tutorpress")}
      className="assignment-settings-panel"
    >
      {warnings.length > 0 && (
        <Notice status="warning" isDismissible={false}>
          {warnings.map((warning, index) => (
            <p key={index}>{warning}</p>
          ))}
        </Notice>
      )}

      <PanelRow>
        <div style={{ width: "100%" }}>
          <Button
            variant="secondary"
            onClick={openMediaLibrary}
            disabled={isSaving}
            style={{ width: "100%", marginBottom: "8px" }}
          >
            {attachmentCount > 0
              ? __(`Upload Attachments (${attachmentCount} selected)`, "tutorpress")
              : __("Upload Attachments", "tutorpress")}
          </Button>
          <p style={{ fontSize: "12px", color: "#757575", margin: "0 0 8px 0" }}>
            {__("Add files that students can download with this assignment.", "tutorpress")}
          </p>

          {/* Display selected attachments */}
          {attachmentCount > 0 && (
            <div className="tutorpress-saved-files-list">
              {assignmentSettings.instructor_attachments?.map((attachmentId: number) => {
                // Find attachment metadata
                const attachment = attachmentsMetadata.find((meta: any) => meta.id === attachmentId);
                const displayName = attachment ? attachment.filename : `File ID: ${attachmentId}`;

                return (
                  <div key={attachmentId} className="tutorpress-saved-file-item">
                    <span className="file-name" title={displayName}>
                      {isLoadingAttachments ? <Spinner /> : null}
                      {displayName}
                    </span>
                    <Button
                      variant="tertiary"
                      onClick={() => removeAttachment(attachmentId)}
                      className="delete-button"
                      disabled={isSaving}
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

      <PanelRow>
        <div style={{ width: "100%" }}>
          <label style={{ display: "block", marginBottom: "8px", fontWeight: 600 }}>
            {__("Time Limit", "tutorpress")}
          </label>
          <div style={{ display: "flex", gap: "8px" }}>
            <TextControl
              type="number"
              min="0"
              value={assignmentSettings.time_duration.value?.toString() || "0"}
              onChange={(value) => updateSetting("time_duration.value", parseInt(value) || 0)}
              disabled={isSaving}
              style={{ flex: 1 }}
            />
            <SelectControl
              value={assignmentSettings.time_duration.unit}
              options={timeUnitOptions}
              onChange={(value) => updateSetting("time_duration.unit", value)}
              disabled={isSaving}
              style={{ flex: 1 }}
            />
          </div>
          <p style={{ fontSize: "12px", color: "#757575", margin: "4px 0 0 0" }}>
            {assignmentSettings.time_duration.value === 0
              ? __("No time limit", "tutorpress")
              : __("Students have this amount of time to complete the assignment after enrollment", "tutorpress")}
          </p>
        </div>
      </PanelRow>

      <PanelRow>
        <TextControl
          label={__("Total Points", "tutorpress")}
          type="number"
          min="0"
          value={assignmentSettings.total_points?.toString() || "0"}
          onChange={(value) => {
            const newSettings = { ...assignmentSettings };
            newSettings.total_points = Math.max(0, parseInt(value) || 0);
            setAssignmentSettings(newSettings);
          }}
          disabled={isSaving}
          help={__("Set to 0 for no points assignment", "tutorpress")}
        />
      </PanelRow>

      <PanelRow>
        <TextControl
          label={__("Minimum Pass Points", "tutorpress")}
          type="number"
          min="0"
          max={assignmentSettings.total_points > 0 ? assignmentSettings.total_points : undefined}
          value={assignmentSettings.pass_points?.toString() || "0"}
          onChange={(value) => {
            const passPoints = parseInt(value) || 0;
            // If total_points is 0, allow any pass_points value (including 0)
            const maxPoints =
              assignmentSettings.total_points === 0
                ? passPoints
                : Math.min(passPoints, assignmentSettings.total_points);
            updateSetting("pass_points", maxPoints);
          }}
          disabled={isSaving}
          help={__("Set to 0 for no minimum pass requirement", "tutorpress")}
        />
      </PanelRow>

      <PanelRow>
        <TextControl
          label={__("File Upload Limit", "tutorpress")}
          help={__("Set to 0 to disable file uploads for this assignment", "tutorpress")}
          type="number"
          min="0"
          value={assignmentSettings.file_upload_limit?.toString() || "0"}
          onChange={(value) => updateSetting("file_upload_limit", parseInt(value) || 0)}
          disabled={isSaving}
        />
      </PanelRow>

      <PanelRow>
        <TextControl
          label={__("Maximum File Size Limit (MB)", "tutorpress")}
          type="number"
          min="1"
          value={assignmentSettings.file_size_limit?.toString() || "1"}
          onChange={(value) => updateSetting("file_size_limit", Math.max(1, parseInt(value) || 1))}
          disabled={isSaving}
        />
      </PanelRow>

      {/* Content Drip Section - Only show when course ID is available */}
      {courseId && postId && (
        <ContentDripPanel
          postType="tutor_assignments"
          courseId={courseId}
          postId={postId}
          settings={assignmentSettings.content_drip || { unlock_date: "", after_xdays_of_enroll: 0, prerequisites: [] }}
          onSettingsChange={handleContentDripChange}
          isDisabled={isSaving}
        />
      )}
    </PluginDocumentSettingPanel>
  );
};

export default AssignmentSettingsPanel;
