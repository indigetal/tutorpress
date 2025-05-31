/**
 * Video Detection Utilities for TutorPress
 *
 * Shared utilities for video duration auto-detection across Course and Lesson settings.
 * Uses the same approach as Tutor LMS for consistency.
 */

import { __ } from "@wordpress/i18n";

/**
 * Video duration in hours, minutes, seconds format
 */
export interface VideoDuration {
  hours: number;
  minutes: number;
  seconds: number;
}

/**
 * Video source types supported by TutorPress
 */
export type VideoSource = "youtube" | "vimeo" | "html5" | "external_url";

/**
 * Video validation patterns (same as Tutor LMS)
 */
const VideoRegex = {
  YOUTUBE: /(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/,
  VIMEO: /vimeo\.com\/(?:channels\/[A-z]+\/|groups\/[A-z]+\/videos\/|)(\d+)/,
  EXTERNAL_URL: /^https?:\/\/.+\.(mp4|webm|ogg)$/i,
};

/**
 * Get Vimeo video duration using Vimeo API v2
 * Uses the same approach as Tutor LMS
 */
export async function getVimeoVideoDuration(videoUrl: string): Promise<number | null> {
  const regExp = /^.*(vimeo\.com\/)((channels\/[A-z]+\/)|(groups\/[A-z]+\/videos\/))?([0-9]+)/;
  const match = videoUrl.match(regExp);
  const videoId = match ? match[5] : null;

  if (!videoId) {
    return null;
  }

  const jsonUrl = `https://vimeo.com/api/v2/video/${videoId}.xml`;

  try {
    const response = await fetch(jsonUrl);
    const textData = await response.text();

    // Parse XML manually since we're dealing with XML response
    const parser = new DOMParser();
    const xmlDoc = parser.parseFromString(textData, "text/xml");

    // Extract duration from XML
    const durationElement = xmlDoc.querySelector("duration");
    if (durationElement) {
      const duration = parseInt(durationElement.textContent || "0", 10);
      return duration; // in seconds
    }
  } catch (error) {
    // Silently handle errors
  }

  return null;
}

/**
 * Get external/HTML5 video duration using browser HTML5 video element
 * Uses the same approach as Tutor LMS
 */
export async function getExternalVideoDuration(videoUrl: string): Promise<number | null> {
  return new Promise((resolve) => {
    const video = document.createElement("video");
    video.src = videoUrl;
    video.preload = "metadata";
    video.crossOrigin = "anonymous";

    video.onloadedmetadata = () => {
      resolve(video.duration);
      video.remove();
    };

    video.onerror = () => {
      resolve(null);
      video.remove();
    };

    // Timeout after 10 seconds
    setTimeout(() => {
      resolve(null);
      video.remove();
    }, 10000);
  });
}

/**
 * Get YouTube video duration using Tutor LMS's existing AJAX endpoint
 */
export async function getYouTubeVideoDuration(videoId: string): Promise<string | null> {
  try {
    const formData = new FormData();
    formData.append("action", "tutor_youtube_video_duration");
    formData.append("video_id", videoId);
    formData.append("_wpnonce", (window as any).tutorpressData?.nonce || "");

    const response = await fetch((window as any).ajaxurl || "/wp-admin/admin-ajax.php", {
      method: "POST",
      body: formData,
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    if (data.success && data.data?.duration) {
      return data.data.duration; // ISO 8601 format
    }

    return null;
  } catch (error) {
    return null;
  }
}

/**
 * Convert YouTube's ISO 8601 duration to seconds
 * Same logic as Tutor LMS
 */
export function convertYouTubeDurationToSeconds(duration: string): number {
  const matches = duration.match(/PT(\d+H)?(\d+M)?(\d+S)?/);

  if (!matches) {
    return 0;
  }

  const hours = matches[1] ? Number(matches[1].replace("H", "")) : 0;
  const minutes = matches[2] ? Number(matches[2].replace("M", "")) : 0;
  const seconds = matches[3] ? Number(matches[3].replace("S", "")) : 0;

  return hours * 3600 + minutes * 60 + seconds;
}

/**
 * Convert seconds to hours, minutes, seconds format
 * Same logic as Tutor LMS
 */
export function convertSecondsToHMS(seconds: number): VideoDuration {
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const sec = seconds % 60;

  return { hours, minutes, seconds: sec };
}

/**
 * Convert hours, minutes, seconds to total seconds
 */
export function convertHMSToSeconds(duration: VideoDuration): number {
  return duration.hours * 3600 + duration.minutes * 60 + duration.seconds;
}

/**
 * Extract video ID from YouTube URL
 */
export function extractYouTubeVideoId(url: string): string | null {
  const match = url.match(VideoRegex.YOUTUBE);
  return match && match[1].length === 11 ? match[1] : null;
}

/**
 * Extract video ID from Vimeo URL
 */
export function extractVimeoVideoId(url: string): string | null {
  const match = url.match(VideoRegex.VIMEO);
  return match?.[1] || null;
}

/**
 * Validate video source and URL
 */
export function validateVideoSource(source: VideoSource, url: string): boolean {
  if (!url) return false;

  switch (source) {
    case "youtube":
      return VideoRegex.YOUTUBE.test(url);
    case "vimeo":
      return VideoRegex.VIMEO.test(url);
    case "html5":
    case "external_url":
      return VideoRegex.EXTERNAL_URL.test(url) || url.startsWith("http");
    default:
      return false;
  }
}

/**
 * Auto-detect video duration based on source type
 * Main function that handles all video sources
 */
export async function detectVideoDuration(source: VideoSource, url: string): Promise<VideoDuration | null> {
  if (!validateVideoSource(source, url)) {
    return null;
  }

  try {
    let durationSeconds = 0;

    switch (source) {
      case "vimeo":
        durationSeconds = (await getVimeoVideoDuration(url)) ?? 0;
        break;

      case "html5":
      case "external_url":
        durationSeconds = (await getExternalVideoDuration(url)) ?? 0;
        break;

      case "youtube": {
        const videoId = extractYouTubeVideoId(url);
        if (videoId) {
          const isoDuration = await getYouTubeVideoDuration(videoId);
          if (isoDuration) {
            durationSeconds = convertYouTubeDurationToSeconds(isoDuration);
          }
        }
        break;
      }
    }

    if (durationSeconds > 0) {
      return convertSecondsToHMS(Math.floor(durationSeconds));
    }

    return null;
  } catch (error) {
    return null;
  }
}

/**
 * Check if YouTube API is available (same check as Tutor LMS)
 */
export function isYouTubeApiAvailable(): boolean {
  return (window as any).tutorConfig?.settings?.youtube_api_key_exist === true;
}

/**
 * Get supported video sources
 */
export function getSupportedVideoSources(): Record<VideoSource, string> {
  return {
    youtube: __("YouTube", "tutorpress"),
    vimeo: __("Vimeo", "tutorpress"),
    html5: __("Uploaded Video", "tutorpress"),
    external_url: __("External URL", "tutorpress"),
  };
}
