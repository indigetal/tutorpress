/**
 * Video Detection Hook for TutorPress
 *
 * Reusable React hook for video duration auto-detection.
 * Can be used by both Course and Lesson settings components.
 */

import { useState, useCallback, useRef } from "react";
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
  const debounceTimerRef = useRef<number | null>(null);
  const lastRequestIdRef = useRef<number>(0);

  const detectDuration = useCallback(async (source: VideoSource, url: string): Promise<VideoDuration | null> => {
    // Debounce rapid calls (300ms)
    if (debounceTimerRef.current) {
      window.clearTimeout(debounceTimerRef.current);
    }

    return new Promise<VideoDuration | null>((resolve) => {
      debounceTimerRef.current = window.setTimeout(async () => {
        if (!url.trim()) {
          setError("Video URL is required");
          resolve(null);
          return;
        }

        if (source === "youtube" && !isYouTubeApiAvailable()) {
          setError("YouTube API key not configured in Tutor LMS settings");
          resolve(null);
          return;
        }

        const requestId = ++lastRequestIdRef.current;
        setIsDetecting(true);
        setError(null);
        setDuration(null);

        try {
          const detectedDuration = await detectVideoDuration(source, url);

          // Stale-response guard
          if (requestId !== lastRequestIdRef.current) {
            resolve(null);
            return;
          }

          if (detectedDuration) {
            setDuration(detectedDuration);
            resolve(detectedDuration);
          } else {
            setError(`Could not detect duration for ${source} video`);
            resolve(null);
          }
        } catch (err) {
          const errorMessage = err instanceof Error ? err.message : "Unknown error occurred";
          setError(`Failed to detect video duration: ${errorMessage}`);
          resolve(null);
        } finally {
          // Ensure we only stop detecting for the latest request
          if (requestId === lastRequestIdRef.current) {
            setIsDetecting(false);
          }
        }
      }, 300);
    });
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
