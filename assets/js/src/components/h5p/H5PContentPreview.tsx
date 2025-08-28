/**
 * H5P Content Preview Component
 *
 * @description Fetches and displays H5P content preview HTML for use in Interactive Quiz Modal.
 *              Uses the TutorPress H5P REST API to get rendered content and displays it safely.
 *
 * @package TutorPress
 * @subpackage Components/H5P
 * @since 1.4.0
 */

import React, { useState, useEffect } from "react";
import { Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

interface H5PContentPreviewProps {
  contentId: number;
  className?: string;
  showHeader?: boolean;
  courseId?: number;
}

interface H5PPreviewData {
  html: string;
  metadata: {
    id: number;
    title: string;
    library: string;
    content_type: string;
    description?: string;
  };
  content_id: number;
}

interface H5PPreviewResponse {
  success: boolean;
  data: H5PPreviewData;
}

export const H5PContentPreview: React.FC<H5PContentPreviewProps> = ({
  contentId,
  className = "",
  showHeader = true,
  courseId,
}) => {
  const [previewData, setPreviewData] = useState<H5PPreviewData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!contentId) return;

    setIsLoading(true);
    setError(null);

    const queryParams = courseId ? `?course_id=${courseId}` : '';
    apiFetch<H5PPreviewResponse>({
      path: `/tutorpress/v1/h5p/preview/${contentId}${queryParams}`,
      method: "GET",
    })
      .then((response) => {
        if (response.success && response.data) {
          setPreviewData(response.data);
        } else {
          throw new Error("Invalid response from H5P preview endpoint");
        }
      })
      .catch((error) => {
        console.error("H5P Preview Error:", error);
        setError(error.message || "Failed to load H5P content preview");
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [contentId, courseId]);

  if (isLoading) {
    return (
      <div className="h5p-content-preview h5p-content-preview--loading">
        <Spinner />
        <p>{__("Loading H5P content preview...", "tutorpress")}</p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="h5p-content-preview h5p-content-preview--error">
        <div className="h5p-content-preview__error">
          <p>
            {__("Error loading H5P content:", "tutorpress")} {error}
          </p>
        </div>
      </div>
    );
  }

  if (!previewData) {
    return (
      <div className="h5p-content-preview h5p-content-preview--empty">
        <p>{__("No H5P content preview available", "tutorpress")}</p>
      </div>
    );
  }

  return (
    <div className={`h5p-content-preview ${className || ""}`}>
      {showHeader && (
        <div className="h5p-content-preview__header">
          <h3 className="h5p-content-preview__title">{__("H5P Interactive Content Preview", "tutorpress")}</h3>
        </div>
      )}
      <div className="h5p-content-preview__content" dangerouslySetInnerHTML={{ __html: previewData.html }} />
    </div>
  );
};
