/**
 * Video Thumbnail Component for TutorPress
 *
 * Displays thumbnails for video sources: WordPress Uploads, YouTube, and Vimeo.
 * Handles thumbnail fetching, loading states, and error handling.
 *
 * @package TutorPress
 * @since 1.0.0
 */

import React, { useState, useEffect } from "react";
import { __ } from "@wordpress/i18n";
import { Spinner, Notice } from "@wordpress/components";

interface VideoThumbnailProps {
  videoData: {
    source: string;
    source_video_id?: number;
    source_youtube?: string;
    source_vimeo?: string;
  };
  className?: string;
  maxWidth?: number;
}

const VideoThumbnail: React.FC<VideoThumbnailProps> = ({ videoData, className = "", maxWidth }) => {
  const [thumbnail, setThumbnail] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string>("");
  const [filename, setFilename] = useState<string>("");

  // Extract YouTube video ID from URL or ID
  const extractYouTubeId = (url: string): string | null => {
    if (!url) return null;

    // Handle direct video ID (11 characters)
    if (/^[a-zA-Z0-9_-]{11}$/.test(url)) {
      return url;
    }

    // Handle various YouTube URL formats
    const patterns = [
      /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
      /youtube\.com\/v\/([a-zA-Z0-9_-]{11})/,
    ];

    for (const pattern of patterns) {
      const match = url.match(pattern);
      if (match) return match[1];
    }

    return null;
  };

  // Extract Vimeo video ID from URL or ID
  const extractVimeoId = (url: string): string | null => {
    if (!url) return null;

    // Handle direct video ID (numbers only)
    if (/^\d+$/.test(url)) {
      return url;
    }

    // Handle Vimeo URL formats
    const patterns = [
      /vimeo\.com\/(\d+)/,
      /vimeo\.com\/groups\/[^\/]+\/videos\/(\d+)/,
      /vimeo\.com\/channels\/[^\/]+\/(\d+)/,
    ];

    for (const pattern of patterns) {
      const match = url.match(pattern);
      if (match) return match[1];
    }

    return null;
  };

  // Fetch WordPress media thumbnail using WordPress REST API with proper authentication
  const fetchWordPressThumbnail = async (attachmentId: number) => {
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

      if (!response.ok) throw new Error("Failed to fetch media data");

      const data = await response.json();

      // For video files, check for poster image first, then fallback to source
      let thumbnailUrl = null;

      if (data.mime_type?.startsWith("video/")) {
        // For videos, check for poster image or use source as fallback
        thumbnailUrl =
          data.media_details?.poster ||
          data.media_details?.sizes?.thumbnail?.source_url ||
          data.media_details?.sizes?.medium?.source_url ||
          data.source_url;
      } else {
        // For images, use standard thumbnail sizes
        thumbnailUrl =
          data.media_details?.sizes?.thumbnail?.source_url ||
          data.media_details?.sizes?.medium?.source_url ||
          data.source_url;
      }

      if (thumbnailUrl) {
        setThumbnail(thumbnailUrl);
        // Store the filename for display
        setFilename(data.title?.rendered || data.media_details?.file?.split("/").pop() || "");
      } else {
        setError(__("No thumbnail available for this video", "tutorpress"));
      }
    } catch (err) {
      setError(__("Failed to load video thumbnail", "tutorpress"));
    }
  };

  // Fetch Vimeo thumbnail without hitting Vimeo oEmbed (avoids CORS). Use public proxy.
  const fetchVimeoThumbnail = async (vimeoId: string) => {
    const proxyThumb = `https://vumbnail.com/${vimeoId}.jpg`;
    setThumbnail(proxyThumb);
  };

  useEffect(() => {
    // Reset state when video data changes
    setThumbnail("");
    setError("");
    setFilename("");

    if (!videoData?.source) {
      setLoading(false);
      return;
    }

    setLoading(true);

    const processVideo = async () => {
      try {
        switch (videoData.source) {
          case "html5":
            if (videoData.source_video_id) {
              await fetchWordPressThumbnail(videoData.source_video_id);
            } else {
              setError(__("No video selected", "tutorpress"));
            }
            break;

          case "youtube":
            if (videoData.source_youtube) {
              const videoId = extractYouTubeId(videoData.source_youtube);
              if (videoId) {
                // YouTube provides thumbnails via direct URL (mqdefault = medium quality)
                setThumbnail(`https://img.youtube.com/vi/${videoId}/mqdefault.jpg`);
              } else {
                setError(__("Invalid YouTube URL", "tutorpress"));
              }
            } else {
              setError(__("No YouTube URL provided", "tutorpress"));
            }
            break;

          case "vimeo":
            if (videoData.source_vimeo) {
              const videoId = extractVimeoId(videoData.source_vimeo);
              if (videoId) {
                await fetchVimeoThumbnail(videoId);
              } else {
                setError(__("Invalid Vimeo URL", "tutorpress"));
              }
            } else {
              setError(__("No Vimeo URL provided", "tutorpress"));
            }
            break;

          default:
            setError(__("Unsupported video source for thumbnails", "tutorpress"));
            break;
        }
      } finally {
        setLoading(false);
      }
    };

    processVideo();
  }, [videoData?.source, videoData?.source_video_id, videoData?.source_youtube, videoData?.source_vimeo]);

  // Don't render anything if no video source
  if (!videoData?.source) {
    return null;
  }

  return (
    <div className={`tutorpress-video-thumbnail ${className}`} data-testid="video-thumbnail">
      {loading && (
        <div style={{ textAlign: "center", padding: "8px" }}>
          <Spinner />
          <p style={{ fontSize: "12px", margin: "4px 0 0 0" }}>{__("Loading thumbnail...", "tutorpress")}</p>
        </div>
      )}

      {error && (
        <Notice status="warning" isDismissible={false}>
          {error}
        </Notice>
      )}

      {thumbnail && !loading && (
        <div style={{ marginTop: "8px", width: "100%" }}>
          {videoData?.source === "html5" && thumbnail.endsWith(".mp4") ? (
            // For video files, show a video player placeholder
            <div
              style={{
                width: "100%",
                height: "120px",
                backgroundColor: "#f0f0f1",
                border: "1px solid #dcdcde",
                borderRadius: "4px",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
                position: "relative",
              }}
            >
              <div style={{ textAlign: "center" }}>
                <div style={{ fontSize: "16px", marginBottom: "4px", color: "#757575" }}>▶</div>
                <div style={{ fontSize: "12px", color: "#757575" }}>{__("Video File", "tutorpress")}</div>
                <div style={{ fontSize: "10px", color: "#757575", marginTop: "2px" }}>
                  {filename || (videoData.source_video_id ? `ID: ${videoData.source_video_id}` : "")}
                </div>
              </div>
            </div>
          ) : (
            // For images or other files, show the actual thumbnail
            <img
              src={thumbnail}
              alt={__("Video thumbnail", "tutorpress")}
              style={{
                width: "100%",
                height: "auto",
                borderRadius: "4px",
                border: "1px solid #dcdcde",
              }}
              onError={() => setError(__("Failed to load thumbnail image", "tutorpress"))}
            />
          )}
        </div>
      )}
    </div>
  );
};

export default VideoThumbnail;
