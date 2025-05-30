/**
 * Video Detection Hook for TutorPress
 *
 * Reusable React hook for video duration auto-detection.
 * Can be used by both Course and Lesson settings components.
 */

import { useState, useCallback } from "react";
import {
  detectVideoDuration,
  type VideoDuration,
  type VideoSource,
  isYouTubeApiAvailable,
} from "../utils/videoDetection";

interface UseVideoDetectionReturn {
  /** Whether detection is currently in progress */
  isDetecting: boolean;
  /** Detected video duration */
  duration: VideoDuration | null;
  /** Error message if detection failed */
  error: string | null;
  /** Manually trigger video duration detection */
  detectDuration: (source: VideoSource, url: string) => Promise<VideoDuration | null>;
  /** Clear current detection state */
  clearDetection: () => void;
  /** Check if a video source is supported */
  isSourceSupported: (source: VideoSource) => boolean;
}

/**
 * Hook for video duration auto-detection
 */
export function useVideoDetection(): UseVideoDetectionReturn {
  const [isDetecting, setIsDetecting] = useState(false);
  const [duration, setDuration] = useState<VideoDuration | null>(null);
  const [error, setError] = useState<string | null>(null);

  const detectDuration = useCallback(async (source: VideoSource, url: string): Promise<VideoDuration | null> => {
    if (!url.trim()) {
      setError("Video URL is required");
      return null;
    }

    // Check if YouTube source is supported
    if (source === "youtube" && !isYouTubeApiAvailable()) {
      setError("YouTube API key not configured in Tutor LMS settings");
      return null;
    }

    setIsDetecting(true);
    setError(null);
    setDuration(null);

    try {
      const detectedDuration = await detectVideoDuration(source, url);

      if (detectedDuration) {
        setDuration(detectedDuration);
        return detectedDuration;
      } else {
        setError(`Could not detect duration for ${source} video`);
        return null;
      }
    } catch (err) {
      const errorMessage = err instanceof Error ? err.message : "Unknown error occurred";
      setError(`Failed to detect video duration: ${errorMessage}`);
      return null;
    } finally {
      setIsDetecting(false);
    }
  }, []);

  const clearDetection = useCallback(() => {
    setDuration(null);
    setError(null);
    setIsDetecting(false);
  }, []);

  const isSourceSupported = useCallback((source: VideoSource): boolean => {
    if (source === "youtube") {
      return isYouTubeApiAvailable();
    }
    return ["vimeo", "html5", "external_url"].includes(source);
  }, []);

  return {
    isDetecting,
    duration,
    error,
    detectDuration,
    clearDetection,
    isSourceSupported,
  };
}
