/**
 * H5P Content Selection Modal
 *
 * Simple modal for selecting H5P content within Interactive Quiz Modal.
 * Uses WordPress Modal directly and replicates Tutor LMS UI patterns.
 *
 * @package TutorPress
 * @since 1.4.0
 */

import React, { useState, useEffect, useCallback } from "react";
import { useSelect, useDispatch } from "@wordpress/data";
import { Button, Modal, Spinner, Flex } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { close } from "@wordpress/icons";

// Import types
import type { H5PContent, H5PContentSearchParams } from "../../../types";

// Import generic H5P components
import { H5PContentTable } from "../../h5p/H5PContentTable";
import { H5PContentSearch } from "../../h5p/H5PContentSearch";

/**
 * H5P Content Selection Modal Props
 */
interface H5PContentSelectionModalProps {
  /** Whether the modal is open */
  isOpen: boolean;

  /** Function to close the modal */
  onClose: () => void;

  /** Function called when content is selected */
  onContentSelect: (content: H5PContent[]) => void;

  /** Currently selected content (for highlighting) */
  selectedContent?: H5PContent[];

  /** Modal title */
  title?: string;

  /** Array of H5P content IDs that should be excluded from the table */
  excludeContentIds?: number[];

  /** Course ID for collaborative instructor access */
  courseId?: number;
}

/**
 * H5P Content Selection Modal Component
 *
 * Simple selection modal that replicates Tutor LMS UI patterns.
 */
export const H5PContentSelectionModal: React.FC<H5PContentSelectionModalProps> = ({
  isOpen,
  onClose,
  onContentSelect,
  selectedContent = [],
  title = __("Select H5P Content", "tutorpress"),
  excludeContentIds = [],
  courseId,
}) => {
  // Local state for search and filters
  const [searchTerm, setSearchTerm] = useState("");
  const [contentTypeFilter, setContentTypeFilter] = useState("");

  // Local state for multiple selections
  const [localSelectedContent, setLocalSelectedContent] = useState<H5PContent[]>([]);

  // Get H5P data from store
  const { contents, pagination, searchParams, isLoading, hasError, error, currentUserId } = useSelect((select) => {
    const h5pStore = select("tutorpress/h5p") as any;
    const coreStore = select("core") as any;
    return {
      contents: h5pStore.getH5PContents(),
      pagination: h5pStore.getH5PPagination(),
      searchParams: h5pStore.getH5PSearchParams(),
      isLoading: h5pStore.isH5PContentLoading(),
      hasError: h5pStore.hasH5PContentError(),
      error: h5pStore.getH5PContentError(),
      currentUserId: coreStore.getCurrentUser()?.id,
    };
  }, []);

  // Get dispatch functions
  const { fetchH5PContents, setH5PSearchParams, setH5PSelectedContent } = useDispatch("tutorpress/h5p") as any;

  // Reset selection state and fetch content when modal opens
  useEffect(() => {
    if (isOpen) {
      // Always reset selection state when modal opens
      setLocalSelectedContent([]);

      // Always fetch content when modal opens (don't rely on cache)
      // This ensures we get the correct content for the current course context
      fetchH5PContents({
        course_id: courseId,
      });
    }
  }, [isOpen, courseId, fetchH5PContents]);

  // Handle search term changes with debouncing
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      const newSearchParams: H5PContentSearchParams = {
        search: searchTerm,
        contentType: contentTypeFilter,
        course_id: courseId,
        per_page: 20,
        page: 1,
      };

      setH5PSearchParams(newSearchParams);
      fetchH5PContents(newSearchParams);
    }, 500);

    return () => clearTimeout(timeoutId);
  }, [searchTerm, contentTypeFilter, courseId, setH5PSearchParams, fetchH5PContents]);

  // Handle pagination
  const handlePageChange = useCallback(
    (newPage: number) => {
      const newSearchParams: H5PContentSearchParams = {
        ...searchParams,
        course_id: courseId,
        page: newPage,
      };

      setH5PSearchParams(newSearchParams);
      fetchH5PContents(newSearchParams);
    },
    [searchParams, courseId, setH5PSearchParams, fetchH5PContents]
  );

  // Handle content selection (toggle for multi-select)
  const handleContentSelect = useCallback((content: H5PContent) => {
    setLocalSelectedContent((prev) => {
      const isSelected = prev.some((selected) => selected.id === content.id);
      if (isSelected) {
        // Remove from selection
        return prev.filter((selected) => selected.id !== content.id);
      } else {
        // Add to selection
        return [...prev, content];
      }
    });
  }, []);

  // Handle adding selected content
  const handleAdd = useCallback(() => {
    if (localSelectedContent.length > 0) {
      onContentSelect(localSelectedContent);
      onClose();
    }
  }, [localSelectedContent, onContentSelect, onClose]);

  // Handle cancel
  const handleCancel = useCallback(() => {
    setLocalSelectedContent([]); // Always reset to empty on cancel
    onClose();
  }, [onClose]);

  // Handle retry on error
  const handleRetry = useCallback(() => {
    fetchH5PContents(searchParams);
  }, [fetchH5PContents, searchParams]);

  // Don't render if not open
  if (!isOpen) {
    return null;
  }

  return (
    <Modal
      title={title}
      onRequestClose={onClose}
      className="tutorpress-h5p-selection-modal"
      shouldCloseOnClickOutside={false}
      __experimentalHideHeader={false}
    >
      <div className="tutorpress-h5p-modal-content">
        {/* Search and Filter Controls */}
        <div className="tutorpress-h5p-modal-body">
          <H5PContentSearch
            searchTerm={searchTerm}
            onSearchChange={setSearchTerm}
            contentTypeFilter={contentTypeFilter}
            onContentTypeChange={setContentTypeFilter}
            isLoading={isLoading}
          />

          {/* Loading State */}
          {isLoading && (
            <div className="tutorpress-h5p-loading-state tpress-loading-state-centered">
              <Spinner />
              <p>{__("Loading H5P content...", "tutorpress")}</p>
            </div>
          )}

          {/* Error State */}
          {hasError && !isLoading && (
            <div className="tutorpress-h5p-error-state tpress-error-state-alert">
              <div className="tutor-alert">
                <p>{error?.message || __("Failed to load H5P content.", "tutorpress")}</p>
                <Button
                  variant="secondary"
                  onClick={handleRetry}
                  className="tutor-btn tutor-btn-outline-primary tpress-error-retry-btn"
                >
                  {__("Retry", "tutorpress")}
                </Button>
              </div>
            </div>
          )}

          {/* Content Table */}
          {!hasError && !isLoading && (
            <H5PContentTable
              contents={contents.filter((content: H5PContent) => !excludeContentIds.includes(content.id))}
              selectedContent={localSelectedContent}
              onContentSelect={handleContentSelect}
              pagination={pagination}
              onPageChange={handlePageChange}
              isLoading={isLoading}
              currentUserId={currentUserId}
            />
          )}

          {/* Empty State */}
          {!hasError && !isLoading && contents.length === 0 && (
            <div className="tutorpress-h5p-empty-state tpress-empty-state-page">
              <div className="tutor-empty-state">
                <div className="tutor-empty-state-icon tpress-empty-state-icon">
                  <i className="tutor-icon-h5p"></i>
                </div>
                <h3>{__("No H5P Content Found", "tutorpress")}</h3>
                <p>{__("Try adjusting your search terms or create new H5P content.", "tutorpress")}</p>
              </div>
            </div>
          )}
        </div>

        {/* Modal Footer with Cancel and Add buttons */}
        <div className="tutorpress-h5p-modal-footer">
          <Flex justify="flex-end" gap={3}>
            <Button variant="secondary" onClick={handleCancel}>
              {__("Cancel", "tutorpress")}
            </Button>
            <Button variant="primary" onClick={handleAdd} disabled={localSelectedContent.length === 0}>
              {__("Add Selected", "tutorpress")} ({localSelectedContent.length})
            </Button>
          </Flex>
        </div>
      </div>
    </Modal>
  );
};

export default H5PContentSelectionModal;
