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
}) => {
  // Local state for search and filters
  const [searchTerm, setSearchTerm] = useState("");
  const [contentTypeFilter, setContentTypeFilter] = useState("");

  // Local state for multiple selections
  const [localSelectedContent, setLocalSelectedContent] = useState<H5PContent[]>([]);

  // Get H5P data from store
  const { contents, pagination, searchParams, isLoading, hasError, error } = useSelect((select) => {
    const store = select("tutorpress/curriculum") as any;
    return {
      contents: store.getH5PContents(),
      pagination: store.getH5PPagination(),
      searchParams: store.getH5PSearchParams(),
      isLoading: store.isH5PContentLoading(),
      hasError: store.hasH5PContentError(),
      error: store.getH5PContentError(),
    };
  }, []);

  // Get dispatch functions
  const { fetchH5PContents, setH5PSearchParams, setH5PSelectedContent } = useDispatch("tutorpress/curriculum") as any;

  // Reset selection state and fetch content when modal opens
  useEffect(() => {
    if (isOpen) {
      // Always reset selection state when modal opens
      setLocalSelectedContent([]);

      // Fetch content if needed
      if (contents.length === 0) {
        fetchH5PContents({});
      }
    }
  }, [isOpen, contents.length, fetchH5PContents]);

  // Handle search term changes with debouncing
  useEffect(() => {
    const timeoutId = setTimeout(() => {
      const newSearchParams: H5PContentSearchParams = {
        search: searchTerm,
        contentType: contentTypeFilter,
        per_page: 20,
        page: 1,
      };

      setH5PSearchParams(newSearchParams);
      fetchH5PContents(newSearchParams);
    }, 500);

    return () => clearTimeout(timeoutId);
  }, [searchTerm, contentTypeFilter, setH5PSearchParams, fetchH5PContents]);

  // Handle pagination
  const handlePageChange = useCallback(
    (newPage: number) => {
      const newSearchParams: H5PContentSearchParams = {
        ...searchParams,
        page: newPage,
      };

      setH5PSearchParams(newSearchParams);
      fetchH5PContents(newSearchParams);
    },
    [searchParams, setH5PSearchParams, fetchH5PContents]
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
            <div className="tutorpress-h5p-loading-state">
              <Spinner />
              <p>{__("Loading H5P content...", "tutorpress")}</p>
            </div>
          )}

          {/* Error State */}
          {hasError && !isLoading && (
            <div className="tutorpress-h5p-error-state">
              <div className="tutor-alert tutor-alert-warning">
                <p>{error?.message || __("Failed to load H5P content.", "tutorpress")}</p>
                <Button variant="secondary" onClick={handleRetry} className="tutor-btn tutor-btn-outline-primary">
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
            />
          )}

          {/* Empty State */}
          {!hasError && !isLoading && contents.length === 0 && (
            <div className="tutorpress-h5p-empty-state">
              <div className="tutor-empty-state">
                <div className="tutor-empty-state-icon">
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

      {/* CSS styles replicating Tutor LMS modal patterns */}
      <style>{`
        .tutorpress-h5p-selection-modal {
          --wp-admin-theme-color: #0073aa;
          --wp-admin-theme-color-darker-10: #005a87;
        }

        .tutorpress-h5p-selection-modal .components-modal__content {
          max-width: 900px;
          width: 90vw;
          max-height: 80vh;
          padding: 0;
          overflow: hidden;
        }

        .tutorpress-h5p-modal-content {
          display: flex;
          flex-direction: column;
          height: 100%;
        }

        .tutorpress-h5p-modal-body {
          flex: 1;
          padding: 25px;
          overflow-y: auto;
        }

        .tutorpress-h5p-modal-footer {
          padding: 20px 25px;
          border-top: 1px solid #dcdcde;
          background: #f6f7f7;
        }

        /* Loading State */
        .tutorpress-h5p-loading-state {
          display: flex;
          flex-direction: column;
          align-items: center;
          justify-content: center;
          padding: 60px 20px;
          text-align: center;
        }

        .tutorpress-h5p-loading-state .components-spinner {
          margin-bottom: 15px;
        }

        .tutorpress-h5p-loading-state p {
          color: #757575;
          margin: 0;
        }

        /* Error State */
        .tutorpress-h5p-error-state {
          margin: 20px 0;
        }

        .tutor-alert {
          padding: 15px;
          border-radius: 4px;
          border-left: 4px solid #ffb900;
          background: #fff8e1;
        }

        .tutor-alert-warning {
          border-left-color: #ffb900;
        }

        .tutor-alert p {
          margin: 0 0 10px;
          color: #1e1e1e;
        }

        .tutor-btn {
          display: inline-flex;
          align-items: center;
          padding: 8px 16px;
          border-radius: 4px;
          text-decoration: none;
          font-size: 14px;
          font-weight: 500;
          border: 1px solid;
          cursor: pointer;
          transition: all 0.2s ease;
        }

        .tutor-btn-outline-primary {
          color: var(--wp-admin-theme-color);
          border-color: var(--wp-admin-theme-color);
          background: transparent;
        }

        .tutor-btn-outline-primary:hover {
          color: white;
          background: var(--wp-admin-theme-color);
        }

        /* Empty State */
        .tutorpress-h5p-empty-state {
          padding: 60px 20px;
        }

        .tutor-empty-state {
          text-align: center;
          max-width: 400px;
          margin: 0 auto;
        }

        .tutor-empty-state-icon {
          font-size: 48px;
          color: #c3c4c7;
          margin-bottom: 20px;
        }

        .tutor-empty-state h3 {
          margin: 0 0 10px;
          color: #1e1e1e;
          font-size: 20px;
          font-weight: 600;
        }

        .tutor-empty-state p {
          margin: 0;
          color: #757575;
          line-height: 1.5;
        }

        /* Responsive design */
        @media (max-width: 768px) {
          .tutorpress-h5p-selection-modal .components-modal__content {
            width: 95vw;
            max-height: 90vh;
          }

          .tutorpress-h5p-modal-header,
          .tutorpress-h5p-modal-body {
            padding: 15px 20px;
          }

          .tutorpress-h5p-modal-title {
            font-size: 16px;
          }
        }
      `}</style>
    </Modal>
  );
};

export default H5PContentSelectionModal;
