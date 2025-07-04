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

const VideoThumbnail: React.FC<VideoThumbnailProps> = ({ videoData, className = "", maxWidth = 200 }) => {
  const [thumbnail, setThumbnail] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);
  const [error, setError] = useState<string>("");

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

  // Fetch WordPress media thumbnail
  const fetchWordPressThumbnail = async (attachmentId: number) => {
    try {
      const response = await fetch(`/wp-json/wp/v2/media/${attachmentId}`);
      if (!response.ok) throw new Error("Failed to fetch media data");

      const data = await response.json();

      // Use thumbnail or medium size if available, fallback to source
      const thumbnailUrl =
        data.media_details?.sizes?.thumbnail?.source_url ||
        data.media_details?.sizes?.medium?.source_url ||
        data.source_url;

      if (thumbnailUrl) {
        setThumbnail(thumbnailUrl);
      } else {
        setError(__("No thumbnail available for this video", "tutorpress"));
      }
    } catch (err) {
      setError(__("Failed to load video thumbnail", "tutorpress"));
    }
  };

  // Fetch Vimeo thumbnail using oEmbed API
  const fetchVimeoThumbnail = async (vimeoId: string) => {
    try {
      const oembedUrl = `https://vimeo.com/api/oembed.json?url=https://vimeo.com/${vimeoId}`;
      const response = await fetch(oembedUrl);

      if (!response.ok) throw new Error("Failed to fetch Vimeo data");

      const data = await response.json();

      if (data.thumbnail_url) {
        setThumbnail(data.thumbnail_url);
      } else {
        setError(__("No thumbnail available for this Vimeo video", "tutorpress"));
      }
    } catch (err) {
      setError(__("Failed to load Vimeo thumbnail", "tutorpress"));
    }
  };

  useEffect(() => {
    if (!videoData?.source) {
      setThumbnail("");
      setError("");
      return;
    }

    setLoading(true);
    setError("");

    switch (videoData.source) {
      case "html5":
        if (videoData.source_video_id) {
          fetchWordPressThumbnail(videoData.source_video_id);
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
            fetchVimeoThumbnail(videoId);
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

    setLoading(false);
  }, [videoData]);

  // Don't render anything if no video source
  if (!videoData?.source) {
    return null;
  }

  return (
    <div className={`tutorpress-video-thumbnail ${className}`}>
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
        <div style={{ marginTop: "8px" }}>
          <img
            src={thumbnail}
            alt={__("Video thumbnail", "tutorpress")}
            style={{
              width: "100%",
              maxWidth: `${maxWidth}px`,
              height: "auto",
              borderRadius: "4px",
              border: "1px solid #dcdcde",
            }}
            onError={() => setError(__("Failed to load thumbnail image", "tutorpress"))}
          />
        </div>
      )}
    </div>
  );
};

export default VideoThumbnail;
