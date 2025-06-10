/**
 * H5P Content Search Component
 *
 * Provides search and filtering controls for H5P content selection.
 *
 * @package TutorPress
 * @since 1.4.0
 */

import React from "react";
import { SearchControl, SelectControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

/**
 * H5P Content Search Props
 */
interface H5PContentSearchProps {
  /** Current search term */
  searchTerm: string;

  /** Function called when search term changes */
  onSearchChange: (value: string) => void;

  /** Current content type filter */
  contentTypeFilter: string;

  /** Function called when content type filter changes */
  onContentTypeChange: (value: string) => void;

  /** Whether a search operation is in progress */
  isLoading?: boolean;
}

/**
 * H5P Content Search Component
 */
export const H5PContentSearch: React.FC<H5PContentSearchProps> = ({
  searchTerm,
  onSearchChange,
  contentTypeFilter,
  onContentTypeChange,
  isLoading = false,
}) => {
  // Content type options based on quiz-compatible H5P types
  const contentTypeOptions = [
    { label: __("All Content Types", "tutorpress"), value: "" },
    { label: __("Interactive Video", "tutorpress"), value: "H5P.InteractiveVideo" },
    { label: __("Course Presentation", "tutorpress"), value: "H5P.CoursePresentation" },
    { label: __("Question Set", "tutorpress"), value: "H5P.QuestionSet" },
    { label: __("Single Choice Set", "tutorpress"), value: "H5P.SingleChoiceSet" },
    { label: __("Multiple Choice", "tutorpress"), value: "H5P.MultiChoice" },
    { label: __("True/False", "tutorpress"), value: "H5P.TrueFalse" },
    { label: __("Fill in the Blanks", "tutorpress"), value: "H5P.FillInTheBlanks" },
    { label: __("Drag and Drop", "tutorpress"), value: "H5P.DragQuestion" },
    { label: __("Mark the Words", "tutorpress"), value: "H5P.MarkTheWords" },
    { label: __("Drag Text", "tutorpress"), value: "H5P.DragText" },
    { label: __("Accordion", "tutorpress"), value: "H5P.Accordion" },
    { label: __("Image Hotspots", "tutorpress"), value: "H5P.ImageHotspots" },
  ];

  return (
    <div className="tutorpress-h5p-content-search">
      <div className="tutorpress-h5p-search-controls">
        <div className="tutorpress-h5p-search-field">
          <SearchControl
            label={__("Search H5P Content", "tutorpress")}
            value={searchTerm}
            onChange={onSearchChange}
            placeholder={__("Search by title or description...", "tutorpress")}
          />
        </div>

        <div className="tutorpress-h5p-filter-field">
          <SelectControl
            label={__("Content Type", "tutorpress")}
            value={contentTypeFilter}
            options={contentTypeOptions}
            onChange={onContentTypeChange}
            disabled={isLoading}
          />
        </div>
      </div>

      <style>{`
        .tutorpress-h5p-content-search {
          margin-bottom: 20px;
          padding-bottom: 20px;
          border-bottom: 1px solid #ddd;
        }

        .tutorpress-h5p-search-controls {
          display: grid;
          grid-template-columns: 2fr 1fr;
          gap: 15px;
          align-items: end;
        }

        .tutorpress-h5p-search-field,
        .tutorpress-h5p-filter-field {
          display: flex;
          flex-direction: column;
        }

        .tutorpress-h5p-search-field .components-search-control,
        .tutorpress-h5p-filter-field .components-base-control {
          margin-bottom: 0;
        }

        @media (max-width: 768px) {
          .tutorpress-h5p-search-controls {
            grid-template-columns: 1fr;
            gap: 20px;
          }
        }
      `}</style>
    </div>
  );
};

export default H5PContentSearch;
