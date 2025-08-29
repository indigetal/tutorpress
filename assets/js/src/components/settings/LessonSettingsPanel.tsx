import React, { useState, useEffect, useCallback } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect } from "@wordpress/data";
import {
  PanelRow,
  TextControl,
  SelectControl,
  Button,
  Notice,
  ToggleControl,
  Spinner,
  TextareaControl,
} from "@wordpress/components";

// Import shared video detection utilities
import { useVideoDetection } from "../../hooks/useVideoDetection";
import type { VideoSource } from "../../utils/videoDetection";

// Import Content Drip Panel
import ContentDripPanel from "./ContentDripPanel";
import { useCourseId } from "../../hooks/curriculum/useCourseId";
import { useLessonSettings } from "../../hooks/common";
import type { ContentDripItemSettings } from "../../types/content-drip";

// Import VideoThumbnail component
import VideoThumbnail from "../common/VideoThumbnail";
import PromoPanel from "../common/PromoPanel";

interface VideoSettings {
  source: "" | "html5" | "youtube" | "vimeo" | "external_url" | "embedded" | "shortcode";
  source_video_id: number;
  source_external_url: string;
  source_youtube: string;
  source_vimeo: string;
  source_embedded: string;
  source_shortcode: string;
  poster: string;
}

interface DurationSettings {
  hours: number;
  minutes: number;
  seconds: number;
}

interface LessonPreviewSettings {
  enabled: boolean;
  addon_available: boolean;
}

interface LessonSettings {
  video: VideoSettings;
  duration: DurationSettings;
  exercise_files: number[];
  lesson_preview: LessonPreviewSettings;
}

interface AttachmentDuration {
  hours: number;
  minutes: number;
  seconds: number;
}

interface AttachmentMetadata {
  duration: AttachmentDuration;
}

const LessonSettingsPanel: React.FC = () => {
  const [isLoadingVideoMeta, setIsLoadingVideoMeta] = useState(false);
  const [videoMetaError, setVideoMetaError] = useState<string>("");
  const [exerciseFilesMetadata, setExerciseFilesMetadata] = useState<any[]>([]);
  const [isLoadingExerciseFiles, setIsLoadingExerciseFiles] = useState(false);

  // Use shared video detection hook
  const { isDetecting, detectDuration, error, isSourceSupported } = useVideoDetection();

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

  const { lessonSettings: hookSettings, setLessonSettings, safeSet } = useLessonSettings();

  const defaultLessonSettings: LessonSettings & { content_drip?: any } = {
    video: {
      source: "",
      source_video_id: 0,
      source_external_url: "",
      source_youtube: "",
      source_vimeo: "",
      source_embedded: "",
      source_shortcode: "",
      poster: "",
    },
    duration: { hours: 0, minutes: 0, seconds: 0 },
    exercise_files: [],
    lesson_preview: { enabled: false, addon_available: false },
    content_drip: { unlock_date: "", after_xdays_of_enroll: 0, prerequisites: [] },
  };

  const lessonSettings: LessonSettings & { content_drip?: any } = (hookSettings as any) || defaultLessonSettings;

  // Fetch exercise files metadata when exercise files change
  useEffect(() => {
    const fetchExerciseFilesMetadata = async () => {
      const exerciseFileIds = lessonSettings.exercise_files || [];
      if (exerciseFileIds.length === 0) {
        setExerciseFilesMetadata([]);
        return;
      }

      setIsLoadingExerciseFiles(true);
      try {
        // Fetch metadata for each exercise file using WordPress REST API
        const metadataPromises = exerciseFileIds.map(async (attachmentId: number) => {
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
        setExerciseFilesMetadata(metadata.filter(Boolean));
      } catch (error) {
        console.error("Failed to fetch exercise files metadata:", error);
      } finally {
        setIsLoadingExerciseFiles(false);
      }
    };

    fetchExerciseFilesMetadata();
  }, [lessonSettings.exercise_files]);

  // Debug logging for lesson settings changes
  useEffect(() => {
    // Component received lesson settings - removed debug logging
  }, [lessonSettings]);

  // Only show for lesson post type
  if (postType !== "lesson") {
    return null;
  }

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name={"lesson-settings"}
        title={__("Lesson Settings", "tutorpress")}
        className={"lesson-settings-panel"}
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  // Removed legacy updateSetting helper; all writes use safeSet with per-section deep merges

  // Auto-detect video duration using shared utilities
  const autoDetectVideoDuration = useCallback(
    async (source: VideoSource, url: string) => {
      if (!url.trim()) return;

      setVideoMetaError("");

      try {
        const detectedDuration = await detectDuration(source, url);

        if (detectedDuration) {
          const current = ((window as any).wp?.data?.select("core/editor").getEditedPostAttribute("lesson_settings") ||
            {}) as LessonSettings;
          setLessonSettings({
            ...(current as any),
            duration: {
              hours: detectedDuration.hours,
              minutes: detectedDuration.minutes,
              seconds: detectedDuration.seconds,
            },
          } as any);
        }
      } catch (err) {
        setVideoMetaError(error || __("Could not auto-detect video duration", "tutorpress"));
      }
    },
    [detectDuration, error]
  );

  // Handle uploaded video duration detection
  const detectUploadedVideoDuration = useCallback(async (attachmentId: number) => {
    setIsLoadingVideoMeta(true);
    setVideoMetaError("");

    try {
      // Use WordPress REST API directly for attachment metadata
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

      const result = await response.json();

      // WordPress REST API returns media data directly, not wrapped in data property
      if (result.media_details?.length_formatted) {
        // WordPress doesn't provide duration in the format we need, so we'll set a default
        // or extract from length_formatted if possible
        const duration = {
          hours: 0,
          minutes: 0,
          seconds: 0,
        };

        // Get the CURRENT editor state instead of component state to avoid stale data
        const current = ((window as any).wp?.data?.select("core/editor").getEditedPostAttribute("lesson_settings") ||
          {}) as LessonSettings;
        setLessonSettings({
          ...(current as any),
          duration: {
            hours: duration.hours || 0,
            minutes: duration.minutes || 0,
            seconds: duration.seconds || 0,
          },
        } as any);
      } else {
        setVideoMetaError(__("Could not extract video duration", "tutorpress"));
      }
    } catch (error) {
      console.error("TutorPress Debug: Error fetching attachment metadata:", error);
      setVideoMetaError(__("Could not extract video duration", "tutorpress"));
    } finally {
      setIsLoadingVideoMeta(false);
    }
  }, []);

  const openVideoMediaLibrary = () => {
    const mediaFrame = (window as any).wp.media({
      title: __("Select Video", "tutorpress"),
      button: {
        text: __("Use This Video", "tutorpress"),
      },
      multiple: false,
      library: {
        type: ["video"],
      },
    });

    mediaFrame.on("select", async () => {
      const attachment = mediaFrame.state().get("selection").first().toJSON();
      // Set video source to upload and store attachment ID
      safeSet({ video: { ...lessonSettings.video, source: "html5", source_video_id: attachment.id } } as any);
      // Try to auto-detect video duration for uploaded videos
      await detectUploadedVideoDuration(attachment.id);
    });

    mediaFrame.open();
  };

  const openExerciseFilesLibrary = () => {
    const currentFiles = lessonSettings.exercise_files || [];

    const mediaFrame = (window as any).wp.media({
      title: __("Select Exercise Files", "tutorpress"),
      button: {
        text: __("Add Files", "tutorpress"),
      },
      multiple: true,
    });

    mediaFrame.on("select", () => {
      const newAttachments = mediaFrame.state().get("selection").toJSON();
      const newAttachmentIds = newAttachments.map((attachment: any) => attachment.id);

      // Combine existing files with new ones, avoiding duplicates
      const allAttachmentIds = [...new Set([...currentFiles, ...newAttachmentIds])];
      safeSet({ exercise_files: allAttachmentIds } as any);
    });

    mediaFrame.open();
  };

  const removeExerciseFile = (attachmentId: number) => {
    const currentFiles = lessonSettings.exercise_files || [];
    const updatedFiles = currentFiles.filter((id: number) => id !== attachmentId);
    safeSet({ exercise_files: updatedFiles } as any);
  };

  const clearVideo = () => {
    const nextVideo = {
      source: "",
      source_video_id: 0,
      source_external_url: "",
      source_youtube: "",
      source_vimeo: "",
      source_embedded: "",
      source_shortcode: "",
      poster: "",
    };
    setVideoMetaError("");
    safeSet({ video: nextVideo, duration: { hours: 0, minutes: 0, seconds: 0 } } as any);
  };

  // Handle content drip settings changes
  // Note: ContentDripPanel handles its own saving through TutorPress content drip endpoint
  const handleContentDripChange = useCallback((newSettings: ContentDripItemSettings) => {
    // No-op: ContentDripPanel manages its own state and saving
    // This prevents content drip settings from being included in lesson_settings
    // which would cause 500 errors in WordPress core lesson endpoint
  }, []);

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

  const exerciseFileCount = lessonSettings.exercise_files?.length || 0;
  const hasVideo =
    lessonSettings.video.source !== "" &&
    (lessonSettings.video.source_video_id > 0 ||
      lessonSettings.video.source_external_url ||
      lessonSettings.video.source_youtube ||
      lessonSettings.video.source_vimeo ||
      lessonSettings.video.source_embedded ||
      lessonSettings.video.source_shortcode);
  const hasDuration =
    lessonSettings.duration.hours > 0 || lessonSettings.duration.minutes > 0 || lessonSettings.duration.seconds > 0;

  // Show video detection loading state
  const showVideoDetectionLoading = isDetecting || isLoadingVideoMeta;

  return (
    <PluginDocumentSettingPanel
      name="lesson-settings"
      title={__("Lesson Settings", "tutorpress")}
      className="lesson-settings-panel"
    >
      {/* Video Section */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <div style={{ marginBottom: "8px", fontWeight: 600 }}>{__("Video", "tutorpress")}</div>

          <SelectControl
            value={lessonSettings.video.source}
            options={videoSourceOptions}
            onChange={(value) => {
              if (value === "") {
                clearVideo();
              } else {
                const nextVideo = {
                  source: value as VideoSettings["source"],
                  source_video_id: 0,
                  source_external_url: "",
                  source_youtube: "",
                  source_vimeo: "",
                  source_embedded: "",
                  source_shortcode: "",
                  poster: "",
                };
                safeSet({ video: nextVideo } as any);
              }
            }}
            disabled={isSaving}
            style={{ marginBottom: "8px" }}
          />

          {/* Upload Video */}
          {lessonSettings.video.source === "html5" && (
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
                {lessonSettings.video.source_video_id > 0
                  ? __("Change Video (ID: ", "tutorpress") + lessonSettings.video.source_video_id + ")"
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
                    {__("Extracting video duration…", "tutorpress")}
                  </p>
                </div>
              )}

              {/* Video Thumbnail */}
              <VideoThumbnail
                key={`video-${lessonSettings.video.source}-${lessonSettings.video.source_video_id}-${lessonSettings.video.source_youtube}-${lessonSettings.video.source_vimeo}`}
                videoData={lessonSettings.video}
              />
            </div>
          )}

          {/* YouTube */}
          {lessonSettings.video.source === "youtube" && (
            <div>
              <TextControl
                label={__("YouTube URL or Video ID", "tutorpress")}
                placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ"
                value={lessonSettings.video.source_youtube}
                onChange={(value) => {
                  safeSet({ video: { ...lessonSettings.video, source_youtube: value } } as any);
                  if (value && isSourceSupported("youtube")) {
                    autoDetectVideoDuration("youtube", value);
                  }
                }}
                disabled={isSaving}
                help={
                  isSourceSupported("youtube")
                    ? __("Enter the full YouTube URL or just the video ID", "tutorpress")
                    : __("YouTube API key not configured - duration auto-detection disabled", "tutorpress")
                }
              />
              {showVideoDetectionLoading && (
                <div style={{ textAlign: "center", padding: "8px" }}>
                  <Spinner />
                  <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>
                    {__("Detecting video duration…", "tutorpress")}
                  </p>
                </div>
              )}

              {/* Video Thumbnail */}
              <VideoThumbnail
                key={`video-${lessonSettings.video.source}-${lessonSettings.video.source_video_id}-${lessonSettings.video.source_youtube}-${lessonSettings.video.source_vimeo}`}
                videoData={lessonSettings.video}
              />
            </div>
          )}

          {/* Vimeo */}
          {lessonSettings.video.source === "vimeo" && (
            <div>
              <TextControl
                label={__("Vimeo URL or Video ID", "tutorpress")}
                placeholder="https://vimeo.com/123456789"
                value={lessonSettings.video.source_vimeo}
                onChange={(value) => {
                  safeSet({ video: { ...lessonSettings.video, source_vimeo: value } } as any);
                  if (value) {
                    // Debounce auto-detection to avoid interfering with typing
                    setTimeout(() => {
                      autoDetectVideoDuration("vimeo", value);
                    }, 1000);
                  }
                }}
                disabled={isSaving}
                help={__("Enter the full Vimeo URL or just the video ID", "tutorpress")}
              />
              {showVideoDetectionLoading && (
                <div style={{ textAlign: "center", padding: "8px" }}>
                  <Spinner />
                  <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>
                    {__("Detecting video duration…", "tutorpress")}
                  </p>
                </div>
              )}

              {/* Video Thumbnail */}
              <VideoThumbnail
                key={`video-${lessonSettings.video.source}-${lessonSettings.video.source_video_id}-${lessonSettings.video.source_youtube}-${lessonSettings.video.source_vimeo}`}
                videoData={lessonSettings.video}
              />
            </div>
          )}

          {/* External URL */}
          {lessonSettings.video.source === "external_url" && (
            <div>
              <TextControl
                label={__("External Video URL", "tutorpress")}
                placeholder="https://example.com/video.mp4"
                value={lessonSettings.video.source_external_url}
                onChange={(value) => {
                  safeSet({ video: { ...lessonSettings.video, source_external_url: value } } as any);
                  if (value) {
                    autoDetectVideoDuration("external_url", value);
                  }
                }}
                disabled={isSaving}
                help={__("Enter a direct link to the video file (MP4, WebM, etc.)", "tutorpress")}
              />
              {showVideoDetectionLoading && (
                <div style={{ textAlign: "center", padding: "8px" }}>
                  <Spinner />
                  <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>
                    {__("Detecting video duration…", "tutorpress")}
                  </p>
                </div>
              )}
            </div>
          )}

          {/* Embedded Code */}
          {lessonSettings.video.source === "embedded" && (
            <TextareaControl
              label={__("Embedded Video Code", "tutorpress")}
              placeholder="<iframe src=...></iframe>"
              value={lessonSettings.video.source_embedded}
              onChange={(value) => safeSet({ video: { ...lessonSettings.video, source_embedded: value } } as any)}
              disabled={isSaving}
              help={__("Paste the embed code (iframe, video tag, etc.)", "tutorpress")}
              rows={4}
            />
          )}

          {/* Shortcode */}
          {lessonSettings.video.source === "shortcode" && (
            <TextControl
              label={__("Video Shortcode", "tutorpress")}
              placeholder="[video src='...']"
              value={lessonSettings.video.source_shortcode}
              onChange={(value) => safeSet({ video: { ...lessonSettings.video, source_shortcode: value } } as any)}
              disabled={isSaving}
              help={__("Enter a WordPress video shortcode", "tutorpress")}
            />
          )}

          {/* Video Meta Error */}
          {(videoMetaError || error) && (
            <Notice status="warning" isDismissible={false}>
              {videoMetaError || error}
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
        </div>
      </PanelRow>

      {/* Video Duration Section - Only show when video source is selected */}
      {lessonSettings.video.source !== "" && (
        <PanelRow>
          <div style={{ width: "100%" }}>
            <div style={{ marginBottom: "8px", fontWeight: 600 }}>{__("Video Duration", "tutorpress")}</div>

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
                  value={lessonSettings.duration.hours.toString()}
                  onChange={(value) => {
                    const hours = parseInt(value) || 0;
                    safeSet({
                      duration: {
                        hours,
                        minutes: lessonSettings.duration.minutes,
                        seconds: lessonSettings.duration.seconds,
                      },
                    } as any);
                  }}
                  disabled={isSaving}
                />
              </div>

              <div style={{ flex: 1 }}>
                <div style={{ fontSize: "12px", fontWeight: 500 }}>{__("Minutes", "tutorpress")}</div>
                <TextControl
                  type="number"
                  min="0"
                  max="59"
                  value={lessonSettings.duration.minutes.toString()}
                  onChange={(value) => {
                    const minutes = Math.min(59, parseInt(value) || 0);
                    safeSet({
                      duration: {
                        hours: lessonSettings.duration.hours,
                        minutes,
                        seconds: lessonSettings.duration.seconds,
                      },
                    } as any);
                  }}
                  disabled={isSaving}
                />
              </div>

              <div style={{ flex: 1 }}>
                <div style={{ fontSize: "12px", fontWeight: 500 }}>{__("Seconds", "tutorpress")}</div>
                <TextControl
                  type="number"
                  min="0"
                  max="59"
                  value={lessonSettings.duration.seconds.toString()}
                  onChange={(value) => {
                    const seconds = Math.min(59, parseInt(value) || 0);
                    safeSet({
                      duration: {
                        hours: lessonSettings.duration.hours,
                        minutes: lessonSettings.duration.minutes,
                        seconds,
                      },
                    } as any);
                  }}
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
              {hasDuration
                ? __("Video duration is set and will be tracked for student progress", "tutorpress")
                : __("Duration will be auto-detected for supported video sources", "tutorpress")}
            </p>
          </div>
        </PanelRow>
      )}

      {/* Exercise Files Section */}
      <PanelRow>
        <div style={{ width: "100%" }}>
          <div style={{ marginBottom: "8px", fontWeight: 600 }}>{__("Exercise Files", "tutorpress")}</div>

          <Button
            variant="secondary"
            onClick={openExerciseFilesLibrary}
            disabled={isSaving}
            style={{ width: "100%", marginBottom: "8px" }}
          >
            {exerciseFileCount > 0
              ? __("Exercise Files", "tutorpress") + " (" + exerciseFileCount + " " + __("selected", "tutorpress") + ")"
              : __("Add Exercise Files", "tutorpress")}
          </Button>

          <p
            style={{
              fontSize: "12px",
              color: "#757575",
              margin: "0 0 8px 0",
            }}
          >
            {__(
              "Add files that students can download to complete exercises or assignments related to this lesson.",
              "tutorpress"
            )}
          </p>

          {/* Display selected files */}
          {exerciseFileCount > 0 && (
            <div className="tutorpress-saved-files-list">
              {lessonSettings.exercise_files?.map((attachmentId: number) => {
                // Find attachment metadata
                const attachment = exerciseFilesMetadata.find((meta: any) => meta.id === attachmentId);
                const displayName = attachment ? attachment.filename : `File ID: ${attachmentId}`;

                return (
                  <div key={attachmentId} className="tutorpress-saved-file-item">
                    <span className="file-name" title={displayName}>
                      {isLoadingExerciseFiles ? <Spinner /> : null}
                      {displayName}
                    </span>
                    <Button
                      variant="tertiary"
                      onClick={() => removeExerciseFile(attachmentId)}
                      className="delete-button"
                      disabled={isSaving}
                      aria-label={__("Remove exercise file", "tutorpress")}
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

      {/* Lesson Preview Section - Only show if addon is available */}
      {lessonSettings.lesson_preview.addon_available && (
        <PanelRow>
          <div style={{ width: "100%" }}>
            <ToggleControl
              label={__("Lesson Preview", "tutorpress")}
              help={
                lessonSettings.lesson_preview.enabled
                  ? __("This lesson can be viewed by guests without enrolling in the course", "tutorpress")
                  : __("This lesson requires course enrollment to view", "tutorpress")
              }
              checked={lessonSettings.lesson_preview.enabled}
              onChange={(enabled) => safeSet({ lesson_preview: { ...lessonSettings.lesson_preview, enabled } } as any)}
              disabled={isSaving}
            />

            <p
              style={{
                fontSize: "12px",
                color: "#757575",
                margin: "4px 0 0 0",
              }}
            >
              {__("If checked, any user/guest can view this lesson without enrolling in the course", "tutorpress")}
            </p>
          </div>
        </PanelRow>
      )}

      {/* Content Drip Section - Only show when course ID is available */}
      {courseId && postId && (
        <ContentDripPanel
          postType="lesson"
          courseId={courseId}
          postId={postId}
          settings={lessonSettings.content_drip || { unlock_date: "", after_xdays_of_enroll: 0, prerequisites: [] }}
          onSettingsChange={handleContentDripChange}
          isDisabled={isSaving}
        />
      )}
    </PluginDocumentSettingPanel>
  );
};

export default LessonSettingsPanel;
