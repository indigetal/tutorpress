import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, TextControl, SelectControl, Button, Notice } from "@wordpress/components";

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
  content_drip?: {
    enabled: boolean;
    type: string;
    available_after_days: number;
    show_days_field: boolean;
  };
}

const AssignmentSettingsPanel: React.FC = () => {
  const { postType, assignmentSettings, isSaving } = useSelect((select: any) => {
    const { getCurrentPostType } = select("core/editor");
    const { getEditedPostAttribute } = select("core/editor");
    const { isSavingPost } = select("core/editor");

    return {
      postType: getCurrentPostType(),
      assignmentSettings: getEditedPostAttribute("assignment_settings") || {
        time_duration: { value: 0, unit: "hours" },
        total_points: 100,
        pass_points: 60,
        file_upload_limit: 1,
        file_size_limit: 2,
        attachments_enabled: true,
        instructor_attachments: [],
        content_drip: {
          enabled: false,
          type: "unlock_by_date",
          available_after_days: 0,
          show_days_field: false,
        },
      },
      isSaving: isSavingPost(),
    };
  }, []);

  const { editPost } = useDispatch("core/editor");

  // Only show for assignment post type
  if (postType !== "tutor_assignments") {
    return null;
  }

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

    editPost({ assignment_settings: newSettings });
  };

  const openMediaLibrary = () => {
    // Open WordPress Media Library
    const mediaFrame = (window as any).wp.media({
      title: __("Select Assignment Attachments", "tutorpress"),
      button: {
        text: __("Add Attachments", "tutorpress"),
      },
      multiple: true,
    });

    mediaFrame.on("select", () => {
      const attachments = mediaFrame.state().get("selection").toJSON();
      const attachmentIds = attachments.map((attachment: any) => attachment.id);
      updateSetting("instructor_attachments", attachmentIds);
    });

    mediaFrame.open();
  };

  const removeAttachment = (attachmentId: number) => {
    const currentAttachments = assignmentSettings.instructor_attachments || [];
    const updatedAttachments = currentAttachments.filter((id: number) => id !== attachmentId);
    updateSetting("instructor_attachments", updatedAttachments);
  };

  const timeUnitOptions = [
    { label: __("Hours", "tutorpress"), value: "hours" },
    { label: __("Days", "tutorpress"), value: "days" },
    { label: __("Weeks", "tutorpress"), value: "weeks" },
  ];

  // Validation warnings
  const warnings = [];
  if (assignmentSettings.pass_points > assignmentSettings.total_points) {
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
            <div style={{ marginTop: "8px" }}>
              {assignmentSettings.instructor_attachments?.map((attachmentId: number) => (
                <div
                  key={attachmentId}
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
                  <span>{__(`Attachment ID: ${attachmentId}`, "tutorpress")}</span>
                  <Button
                    variant="link"
                    onClick={() => removeAttachment(attachmentId)}
                    style={{ color: "#d63638", fontSize: "12px", padding: "0" }}
                    disabled={isSaving}
                  >
                    {__("Remove", "tutorpress")}
                  </Button>
                </div>
              ))}
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
              value={assignmentSettings.time_duration.value.toString()}
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
          min="1"
          value={assignmentSettings.total_points.toString()}
          onChange={(value) => updateSetting("total_points", Math.max(1, parseInt(value) || 1))}
          disabled={isSaving}
        />
      </PanelRow>

      <PanelRow>
        <TextControl
          label={__("Minimum Pass Points", "tutorpress")}
          type="number"
          min="0"
          max={assignmentSettings.total_points}
          value={assignmentSettings.pass_points.toString()}
          onChange={(value) => {
            const passPoints = parseInt(value) || 0;
            const maxPoints = Math.min(passPoints, assignmentSettings.total_points);
            updateSetting("pass_points", maxPoints);
          }}
          disabled={isSaving}
        />
      </PanelRow>

      <PanelRow>
        <TextControl
          label={__("File Upload Limit", "tutorpress")}
          help={__(
            "Define the number of files that a student can upload in this assignment. Input 0 to disable the option to upload.",
            "tutorpress"
          )}
          type="number"
          min="0"
          value={assignmentSettings.file_upload_limit.toString()}
          onChange={(value) => updateSetting("file_upload_limit", parseInt(value) || 0)}
          disabled={isSaving}
        />
      </PanelRow>

      <PanelRow>
        <TextControl
          label={__("Maximum File Size Limit (MB)", "tutorpress")}
          type="number"
          min="1"
          value={assignmentSettings.file_size_limit.toString()}
          onChange={(value) => updateSetting("file_size_limit", Math.max(1, parseInt(value) || 1))}
          disabled={isSaving}
        />
      </PanelRow>

      {/* Content Drip Settings - Only show if enabled and type is specific_days */}
      {assignmentSettings.content_drip?.show_days_field && (
        <PanelRow>
          <div style={{ width: "100%" }}>
            <TextControl
              label={__("Available After Days", "tutorpress")}
              help={__(
                "This assignment will be available after the given number of days from enrollment.",
                "tutorpress"
              )}
              type="number"
              min="0"
              value={assignmentSettings.content_drip.available_after_days.toString()}
              onChange={(value) => updateSetting("content_drip.available_after_days", parseInt(value) || 0)}
              disabled={isSaving}
            />
            <p style={{ fontSize: "12px", color: "#757575", margin: "4px 0 0 0" }}>
              {assignmentSettings.content_drip.available_after_days === 0
                ? __("Available immediately upon enrollment", "tutorpress")
                : __(
                    `Available ${assignmentSettings.content_drip.available_after_days} days after enrollment`,
                    "tutorpress"
                  )}
            </p>
          </div>
        </PanelRow>
      )}

      {/* Content Drip Info Notice */}
      {assignmentSettings.content_drip?.enabled && !assignmentSettings.content_drip.show_days_field && (
        <Notice status="info" isDismissible={false}>
          <p>
            {assignmentSettings.content_drip.type === "unlock_by_date" &&
              __(
                "Content Drip is enabled for this course using date-based scheduling. Individual assignment timing is controlled at the course level.",
                "tutorpress"
              )}
            {assignmentSettings.content_drip.type === "unlock_sequentially" &&
              __(
                "Content Drip is enabled for this course using sequential unlocking. Assignments will be available based on completion of previous content.",
                "tutorpress"
              )}
            {assignmentSettings.content_drip.type === "after_finishing_prerequisites" &&
              __(
                "Content Drip is enabled for this course using prerequisite-based unlocking. Assignment availability is controlled by course prerequisites.",
                "tutorpress"
              )}
          </p>
        </Notice>
      )}
    </PluginDocumentSettingPanel>
  );
};

export default AssignmentSettingsPanel;
