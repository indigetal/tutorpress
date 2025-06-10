/**
 * H5P Content Table Component
 *
 * Displays H5P content in a table format with selection and pagination.
 *
 * @package TutorPress
 * @since 1.4.0
 */

import React from "react";
import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

// Import types
import type { H5PContent } from "../../types";

/**
 * Pagination information
 */
interface PaginationInfo {
  total: number;
  total_pages: number;
  current_page: number;
  per_page: number;
}

/**
 * H5P Content Table Props
 */
interface H5PContentTableProps {
  /** Array of H5P content items */
  contents: H5PContent[];

  /** Currently selected content */
  selectedContent?: H5PContent | null;

  /** Function called when content is selected */
  onContentSelect: (content: H5PContent) => void;

  /** Pagination information */
  pagination?: PaginationInfo | null;

  /** Function called when page changes */
  onPageChange?: (page: number) => void;

  /** Whether data is loading */
  isLoading?: boolean;
}

/**
 * H5P Content Table Component
 */
export const H5PContentTable: React.FC<H5PContentTableProps> = ({
  contents,
  selectedContent,
  onContentSelect,
  pagination,
  onPageChange,
  isLoading = false,
}) => {
  // Format date for display
  const formatDate = (dateString: string) => {
    try {
      return new Date(dateString).toLocaleDateString();
    } catch {
      return dateString;
    }
  };

  return (
    <div className="tutorpress-h5p-content-table">
      <div className="tutorpress-h5p-table-wrapper">
        <table className="wp-list-table widefat fixed striped">
          <thead>
            <tr>
              <th className="column-title">{__("Title", "tutorpress")}</th>
              <th className="column-type">{__("Type", "tutorpress")}</th>
              <th className="column-author">{__("Author", "tutorpress")}</th>
              <th className="column-date">{__("Last Modified", "tutorpress")}</th>
              <th className="column-actions">{__("Actions", "tutorpress")}</th>
            </tr>
          </thead>
          <tbody>
            {contents.length === 0 ? (
              <tr>
                <td colSpan={5} className="no-items">
                  {isLoading ? __("Loading...", "tutorpress") : __("No H5P content found.", "tutorpress")}
                </td>
              </tr>
            ) : (
              contents.map((content) => (
                <tr key={content.id} className={selectedContent?.id === content.id ? "selected" : ""}>
                  <td className="column-title">
                    <strong>{content.title}</strong>
                  </td>
                  <td className="column-type">
                    <span className="h5p-type-badge">{content.library || content.content_type}</span>
                  </td>
                  <td className="column-author">{content.user_name}</td>
                  <td className="column-date">{formatDate(content.updated_at)}</td>
                  <td className="column-actions">
                    <Button
                      variant={selectedContent?.id === content.id ? "primary" : "secondary"}
                      onClick={() => onContentSelect(content)}
                      size="small"
                    >
                      {selectedContent?.id === content.id ? __("Selected", "tutorpress") : __("Select", "tutorpress")}
                    </Button>
                  </td>
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {pagination && pagination.total_pages > 1 && (
        <div className="tutorpress-h5p-pagination">
          <div className="pagination-info">
            {__("Showing %1$d of %2$d items", "tutorpress")
              .replace("%1$d", contents.length.toString())
              .replace("%2$d", pagination.total.toString())}
          </div>

          <div className="pagination-controls">
            <Button
              variant="tertiary"
              disabled={pagination.current_page <= 1 || isLoading}
              onClick={() => onPageChange?.(pagination.current_page - 1)}
            >
              {__("Previous", "tutorpress")}
            </Button>

            <span className="page-numbers">
              {__("Page %1$d of %2$d", "tutorpress")
                .replace("%1$d", pagination.current_page.toString())
                .replace("%2$d", pagination.total_pages.toString())}
            </span>

            <Button
              variant="tertiary"
              disabled={pagination.current_page >= pagination.total_pages || isLoading}
              onClick={() => onPageChange?.(pagination.current_page + 1)}
            >
              {__("Next", "tutorpress")}
            </Button>
          </div>
        </div>
      )}

      <style>{`
        .tutorpress-h5p-content-table {
          width: 100%;
        }

        .tutorpress-h5p-table-wrapper {
          overflow-x: auto;
          border: 1px solid #c3c4c7;
          border-radius: 4px;
        }

        .tutorpress-h5p-content-table table {
          margin: 0;
          border: none;
        }

        .tutorpress-h5p-content-table thead th {
          background: #f6f7f7;
          border-bottom: 1px solid #c3c4c7;
          font-weight: 600;
          padding: 12px;
        }

        .tutorpress-h5p-content-table tbody td {
          padding: 12px;
          border-bottom: 1px solid #dcdcde;
          vertical-align: middle;
        }

        .tutorpress-h5p-content-table tbody tr:hover {
          background-color: #f6f7f7;
        }

        .tutorpress-h5p-content-table tbody tr.selected {
          background-color: #e7f3ff;
        }

        .tutorpress-h5p-content-table .no-items {
          text-align: center;
          padding: 40px 12px;
          color: #757575;
          font-style: italic;
        }

        .h5p-type-badge {
          display: inline-block;
          background: #f0f0f1;
          color: #1e1e1e;
          padding: 4px 8px;
          border-radius: 12px;
          font-size: 12px;
          font-weight: 500;
        }

        .tutorpress-h5p-pagination {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-top: 20px;
          padding: 15px 0;
          border-top: 1px solid #dcdcde;
        }

        .pagination-info {
          color: #757575;
          font-size: 14px;
        }

        .pagination-controls {
          display: flex;
          align-items: center;
          gap: 15px;
        }

        .page-numbers {
          font-size: 14px;
          color: #1e1e1e;
        }

        /* Column widths */
        .column-title { width: 25%; }
        .column-type { width: 15%; }
        .column-author { width: 15%; }
        .column-description { width: 30%; }
        .column-date { width: 10%; }
        .column-actions { width: 5%; }

        @media (max-width: 768px) {
          .tutorpress-h5p-pagination {
            flex-direction: column;
            gap: 10px;
            text-align: center;
          }

          .pagination-controls {
            justify-content: center;
          }

          /* Hide some columns on mobile */
          .column-description,
          .column-date {
            display: none;
          }

          .column-title { width: 40%; }
          .column-type { width: 25%; }
          .column-author { width: 20%; }
          .column-actions { width: 15%; }
        }
      `}</style>
    </div>
  );
};

export default H5PContentTable;
