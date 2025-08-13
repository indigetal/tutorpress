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
import { useSelect } from "@wordpress/data";
import { useCourseSettings } from "../../hooks/common";
import { PanelRow, Notice, Spinner, Button, TextControl, TextareaControl, SelectControl } from "@wordpress/components";

// Import course settings types
import type { CourseSettings } from "../../types/courses";
import { useVideoDetection } from "../../hooks/useVideoDetection";
import VideoThumbnail from "../common/VideoThumbnail";

const VideoIntroSection: React.FC = () => {
  // Editor saving state
  const { isSaving } = useSelect(
    (select: any) => ({
      isSaving: select("core/editor").isSavingPost(),
    }),
    []
  );

  // Shared course settings hook (parent panel gates readiness)
  const { courseSettings, safeSet } = useCourseSettings();

  // Strongly-typed helpers
  const emptyIntro: CourseSettings["intro_video"] = {
    source: "",
    source_video_id: 0,
    source_external_url: "",
    source_youtube: "",
    source_vimeo: "",
    source_embedded: "",
    source_shortcode: "",
    poster: "",
  };
  const courseSettingsTyped = (courseSettings as Partial<CourseSettings> | undefined) || undefined;
  const intro: CourseSettings["intro_video"] =
    (courseSettingsTyped?.intro_video as CourseSettings["intro_video"] | undefined) || emptyIntro;

  // Video detection hook (without duration detection as requested)
  const { isDetecting, error: videoError, isSourceSupported } = useVideoDetection();

  // Local state for video meta errors
  const [videoMetaError, setVideoMetaError] = useState<string>("");
  const [isLoadingVideoMeta, setIsLoadingVideoMeta] = useState<boolean>(false);

  // Deep-merge helper for intro_video; build nested object and pass via shallow safeSet
  const applyIntro = useCallback(
    (updates: Partial<CourseSettings["intro_video"]>) => {
      const nextIntro = {
        ...((courseSettingsTyped?.intro_video as CourseSettings["intro_video"]) || emptyIntro),
        ...updates,
      };
      safeSet({ intro_video: nextIntro });
    },
    [safeSet, courseSettingsTyped]
  );

  // Reset by source and clear helpers
  const resetForSource = useCallback(
    (source: "" | "html5" | "youtube" | "vimeo" | "external_url" | "embedded" | "shortcode") => {
      setVideoMetaError("");
      applyIntro({
        source,
        source_video_id: 0,
        source_external_url: "",
        source_youtube: "",
        source_vimeo: "",
        source_embedded: "",
        source_shortcode: "",
        poster: "",
      });
    },
    [applyIntro]
  );

  const clearVideo = useCallback(() => {
    resetForSource("");
  }, [resetForSource]);

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
      applyIntro({ source: "html5", source_video_id: attachment.id });
    });

    mediaFrame.open();
  }, [applyIntro]);

  // Video source options (matching Tutor LMS frontend course builder)
  const videoSourceOptions = [
    { label: __("No Video", "tutorpress"), value: "" },
    { label: __("Upload Video", "tutorpress"), value: "html5" },
    {
      label: __("YouTube", "tutorpress"),
      value: "youtube",
    },
    { label: __("Vimeo", "tutorpress"), value: "vimeo" },
    { label: __("External URL", "tutorpress"), value: "external_url" },
    // Commented out to match Tutor LMS frontend course builder
    // { label: __("Embedded Code", "tutorpress"), value: "embedded" },
    // { label: __("Shortcode", "tutorpress"), value: "shortcode" },
  ];

  // Check if video is set
  const hasVideo =
    (intro?.source || "") !== "" &&
    (intro?.source_video_id > 0 ||
      intro?.source_external_url ||
      intro?.source_youtube ||
      intro?.source_vimeo ||
      intro?.source_embedded ||
      intro?.source_shortcode);

  // Show video detection loading state
  const showVideoDetectionLoading = isDetecting || isLoadingVideoMeta;

  return (
    <PanelRow data-testid="video-intro-section">
      <div style={{ width: "100%" }}>
        <div style={{ marginBottom: "8px", fontWeight: 600 }}>{__("Intro Video", "tutorpress")}</div>

        <SelectControl
          value={intro?.source || ""}
          options={videoSourceOptions}
          onChange={(value) => {
            if (value === "") {
              clearVideo();
            } else {
              resetForSource(value as "" | "html5" | "youtube" | "vimeo" | "external_url" | "embedded" | "shortcode");
            }
          }}
          disabled={isSaving}
          style={{ marginBottom: "8px" }}
        />

        {/* Upload Video */}
        {intro?.source === "html5" && (
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
              {intro?.source_video_id > 0 ? __("Change Video", "tutorpress") : __("Select Video", "tutorpress")}
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

            {/* Video Thumbnail */}
            <VideoThumbnail
              key={`video-${intro?.source}-${intro?.source_video_id}-${intro?.source_youtube}-${intro?.source_vimeo}`}
              videoData={intro}
            />
          </div>
        )}

        {/* YouTube */}
        {intro?.source === "youtube" && (
          <div>
            <TextControl
              label={__("YouTube URL or Video ID", "tutorpress")}
              placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ"
              value={intro?.source_youtube || ""}
              onChange={(value) => applyIntro({ source_youtube: value })}
              disabled={isSaving}
              help={__("Enter the full YouTube URL or just the video ID", "tutorpress")}
            />
            {showVideoDetectionLoading && (
              <div style={{ textAlign: "center", padding: "8px" }}>
                <Spinner />
                <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>{__("Processing video…", "tutorpress")}</p>
              </div>
            )}

            {/* Video Thumbnail */}
            <VideoThumbnail
              key={`video-${intro?.source}-${intro?.source_video_id}-${intro?.source_youtube}-${intro?.source_vimeo}`}
              videoData={intro}
            />
          </div>
        )}

        {/* Vimeo */}
        {intro?.source === "vimeo" && (
          <div>
            <TextControl
              label={__("Vimeo URL or Video ID", "tutorpress")}
              placeholder="https://vimeo.com/123456789"
              value={intro?.source_vimeo || ""}
              onChange={(value) => applyIntro({ source_vimeo: value })}
              disabled={isSaving}
              help={__("Enter the full Vimeo URL or just the video ID", "tutorpress")}
            />
            {showVideoDetectionLoading && (
              <div style={{ textAlign: "center", padding: "8px" }}>
                <Spinner />
                <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>{__("Processing video…", "tutorpress")}</p>
              </div>
            )}

            {/* Video Thumbnail */}
            <VideoThumbnail
              key={`video-${intro?.source}-${intro?.source_video_id}-${intro?.source_youtube}-${intro?.source_vimeo}`}
              videoData={intro}
            />
          </div>
        )}

        {/* External URL */}
        {intro?.source === "external_url" && (
          <div>
            <TextControl
              label={__("External Video URL", "tutorpress")}
              placeholder="https://example.com/video.mp4"
              value={intro?.source_external_url || ""}
              onChange={(value) => applyIntro({ source_external_url: value })}
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

        {/* Embedded Code - Commented out to match Tutor LMS frontend course builder */}
        {/* {settings?.intro_video?.source === "embedded" && (
            <TextareaControl
            label={__("Embedded Video Code", "tutorpress")}
            placeholder="<iframe src=...></iframe>"
              value={intro?.source_embedded || ""}
            onChange={(value) => updateSetting("intro_video.source_embedded", value)}
            disabled={isSaving}
            help={__("Paste the embed code (iframe, video tag, etc.)", "tutorpress")}
            rows={4}
          />
        )} */}

        {/* Shortcode - Commented out to match Tutor LMS frontend course builder */}
        {/* {settings?.intro_video?.source === "shortcode" && (
            <TextControl
            label={__("Video Shortcode", "tutorpress")}
            placeholder="[video src='...']"
              value={intro?.source_shortcode || ""}
            onChange={(value) => updateSetting("intro_video.source_shortcode", value)}
            disabled={isSaving}
            help={__("Enter a WordPress video shortcode", "tutorpress")}
          />
        )} */}

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
