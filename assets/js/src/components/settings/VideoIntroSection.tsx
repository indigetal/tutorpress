/**
 * Video Intro Section Component for TutorPress
 *
 * Replicates the Video section from LessonSettingsPanel for course intro video.
 * Handles all 6 video source types: HTML5, YouTube, Vimeo, External URL, Embedded, Shortcode.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import React, { useState, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { PanelRow, Notice, Spinner, Button, TextControl, TextareaControl, SelectControl } from "@wordpress/components";

// Import course settings types
import type { CourseSettings } from "../../types/courses";
import { useVideoDetection } from "../../hooks/useVideoDetection";

const VideoIntroSection: React.FC = () => {
  // Get settings from our store
  const { settings, isSaving } = useSelect(
    (select: any) => ({
      settings: select("tutorpress/course-settings").getSettings(),
      isSaving: select("core/editor").isSavingPost(),
    }),
    []
  );

  // Get dispatch actions
  const { updateSettings } = useDispatch("tutorpress/course-settings");

  // Video detection hook (without duration detection as requested)
  const { isDetecting, error: videoError, isSourceSupported } = useVideoDetection();

  // Local state for video meta errors
  const [videoMetaError, setVideoMetaError] = useState<string>("");
  const [isLoadingVideoMeta, setIsLoadingVideoMeta] = useState<boolean>(false);

  // Update a specific setting
  const updateSetting = useCallback(
    (key: string, value: any) => {
      const newSettings = { ...settings };
      const keys = key.split(".");
      let current = newSettings;

      for (let i = 0; i < keys.length - 1; i++) {
        current = current[keys[i]];
      }

      current[keys[keys.length - 1]] = value;
      updateSettings(newSettings);
    },
    [settings, updateSettings]
  );

  // Clear video completely
  const clearVideo = useCallback(() => {
    const newSettings = {
      ...settings,
      intro_video: {
        source: "",
        source_video_id: 0,
        source_external_url: "",
        source_youtube: "",
        source_vimeo: "",
        source_embedded: "",
        source_shortcode: "",
        poster: "",
      },
    };

    setVideoMetaError("");
    updateSettings(newSettings);
  }, [settings, updateSettings]);

  // Open WordPress media library for video selection
  const openVideoMediaLibrary = useCallback(() => {
    const mediaFrame = (window as any).wp.media({
      title: __("Select Intro Video", "tutorpress"),
      button: {
        text: __("Use This Video", "tutorpress"),
      },
      multiple: false,
      library: {
        type: ["video"],
      },
    });

    mediaFrame.on("select", () => {
      const attachment = mediaFrame.state().get("selection").first().toJSON();

      // Set video source to upload and store attachment ID
      updateSetting("intro_video.source", "html5");
      updateSetting("intro_video.source_video_id", attachment.id);
    });

    mediaFrame.open();
  }, [updateSetting]);

  // Video source options
  const videoSourceOptions = [
    { label: __("No Video", "tutorpress"), value: "" },
    { label: __("Upload Video", "tutorpress"), value: "html5" },
    {
      label: __("YouTube", "tutorpress"),
      value: "youtube",
    },
    { label: __("Vimeo", "tutorpress"), value: "vimeo" },
    { label: __("External URL", "tutorpress"), value: "external_url" },
    { label: __("Embedded Code", "tutorpress"), value: "embedded" },
    { label: __("Shortcode", "tutorpress"), value: "shortcode" },
  ];

  // Check if video is set
  const hasVideo =
    settings?.intro_video?.source !== "" &&
    (settings?.intro_video?.source_video_id > 0 ||
      settings?.intro_video?.source_external_url ||
      settings?.intro_video?.source_youtube ||
      settings?.intro_video?.source_vimeo ||
      settings?.intro_video?.source_embedded ||
      settings?.intro_video?.source_shortcode);

  // Show video detection loading state
  const showVideoDetectionLoading = isDetecting || isLoadingVideoMeta;

  return (
    <PanelRow>
      <div style={{ width: "100%" }}>
        <div style={{ marginBottom: "8px", fontWeight: 600 }}>{__("Intro Video", "tutorpress")}</div>

        <SelectControl
          value={settings?.intro_video?.source || ""}
          options={videoSourceOptions}
          onChange={(value) => {
            if (value === "") {
              // Clear video completely
              clearVideo();
            } else {
              // Clear other video source fields when changing source type
              const newSettings = {
                ...settings,
                intro_video: {
                  source: value as any,
                  source_video_id: 0,
                  source_external_url: "",
                  source_youtube: "",
                  source_vimeo: "",
                  source_embedded: "",
                  source_shortcode: "",
                  poster: "",
                },
              };
              updateSettings(newSettings);
            }
          }}
          disabled={isSaving}
          style={{ marginBottom: "8px" }}
        />

        {/* Upload Video */}
        {settings?.intro_video?.source === "html5" && (
          <div style={{ marginTop: "8px" }}>
            <Button
              variant="secondary"
              onClick={openVideoMediaLibrary}
              disabled={isSaving}
              style={{
                width: "100%",
                marginBottom: "8px",
              }}
            >
              {settings?.intro_video?.source_video_id > 0
                ? __("Change Video (ID: ", "tutorpress") + settings.intro_video.source_video_id + ")"
                : __("Select Video", "tutorpress")}
            </Button>
            {showVideoDetectionLoading && (
              <div
                style={{
                  textAlign: "center",
                  padding: "8px",
                }}
              >
                <Spinner />
                <p
                  style={{
                    fontSize: "12px",
                    margin: "4px 0 0 0",
                  }}
                >
                  {__("Processing video…", "tutorpress")}
                </p>
              </div>
            )}
          </div>
        )}

        {/* YouTube */}
        {settings?.intro_video?.source === "youtube" && (
          <div>
            <TextControl
              label={__("YouTube URL or Video ID", "tutorpress")}
              placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ"
              value={settings?.intro_video?.source_youtube || ""}
              onChange={(value) => updateSetting("intro_video.source_youtube", value)}
              disabled={isSaving}
              help={__("Enter the full YouTube URL or just the video ID", "tutorpress")}
            />
            {showVideoDetectionLoading && (
              <div style={{ textAlign: "center", padding: "8px" }}>
                <Spinner />
                <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>{__("Processing video…", "tutorpress")}</p>
              </div>
            )}
          </div>
        )}

        {/* Vimeo */}
        {settings?.intro_video?.source === "vimeo" && (
          <div>
            <TextControl
              label={__("Vimeo URL or Video ID", "tutorpress")}
              placeholder="https://vimeo.com/123456789"
              value={settings?.intro_video?.source_vimeo || ""}
              onChange={(value) => updateSetting("intro_video.source_vimeo", value)}
              disabled={isSaving}
              help={__("Enter the full Vimeo URL or just the video ID", "tutorpress")}
            />
            {showVideoDetectionLoading && (
              <div style={{ textAlign: "center", padding: "8px" }}>
                <Spinner />
                <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>{__("Processing video…", "tutorpress")}</p>
              </div>
            )}
          </div>
        )}

        {/* External URL */}
        {settings?.intro_video?.source === "external_url" && (
          <div>
            <TextControl
              label={__("External Video URL", "tutorpress")}
              placeholder="https://example.com/video.mp4"
              value={settings?.intro_video?.source_external_url || ""}
              onChange={(value) => updateSetting("intro_video.source_external_url", value)}
              disabled={isSaving}
              help={__("Enter a direct link to the video file (MP4, WebM, etc.)", "tutorpress")}
            />
            {showVideoDetectionLoading && (
              <div style={{ textAlign: "center", padding: "8px" }}>
                <Spinner />
                <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>{__("Processing video…", "tutorpress")}</p>
              </div>
            )}
          </div>
        )}

        {/* Embedded Code */}
        {settings?.intro_video?.source === "embedded" && (
          <TextareaControl
            label={__("Embedded Video Code", "tutorpress")}
            placeholder="<iframe src=...></iframe>"
            value={settings?.intro_video?.source_embedded || ""}
            onChange={(value) => updateSetting("intro_video.source_embedded", value)}
            disabled={isSaving}
            help={__("Paste the embed code (iframe, video tag, etc.)", "tutorpress")}
            rows={4}
          />
        )}

        {/* Shortcode */}
        {settings?.intro_video?.source === "shortcode" && (
          <TextControl
            label={__("Video Shortcode", "tutorpress")}
            placeholder="[video src='...']"
            value={settings?.intro_video?.source_shortcode || ""}
            onChange={(value) => updateSetting("intro_video.source_shortcode", value)}
            disabled={isSaving}
            help={__("Enter a WordPress video shortcode", "tutorpress")}
          />
        )}

        {/* Video Meta Error */}
        {(videoMetaError || videoError) && (
          <Notice status="warning" isDismissible={false}>
            {videoMetaError || videoError}
          </Notice>
        )}

        {hasVideo && (
          <Button
            variant="link"
            onClick={clearVideo}
            disabled={isSaving}
            style={{
              color: "#d63638",
              fontSize: "12px",
              marginTop: "8px",
            }}
          >
            {__("Remove Video", "tutorpress")}
          </Button>
        )}

        <p
          style={{
            fontSize: "12px",
            color: "#757575",
            margin: "8px 0 0 0",
          }}
        >
          {__("Add an introductory video that students will see before enrolling in the course.", "tutorpress")}
        </p>
      </div>
    </PanelRow>
  );
};

export default VideoIntroSection;
