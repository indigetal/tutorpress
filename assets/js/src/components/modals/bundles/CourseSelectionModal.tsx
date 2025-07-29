/**
 * Bundle Course Selection Modal Component
 *
 * Skeleton component for course selection modal functionality.
 * Will be expanded in Setting 1: Course List Selection.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React from "react";
import { __ } from "@wordpress/i18n";
import { Modal, Button, TextControl, Notice, Spinner } from "@wordpress/components";
import { search, plus } from "@wordpress/icons";

// Import types (will be expanded as needed)
import type { Bundle, BundleCourse } from "../../../types/bundle";

/**
 * Bundle Course Selection Modal Component
 *
 * Features (to be implemented):
 * - Course search functionality
 * - Course list display
 * - Course selection/deselection
 * - Course preview
 * - Bulk selection
 */
interface CourseSelectionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSave: (selectedCourseIds: number[]) => void;
  currentCourseIds?: number[];
}

const CourseSelectionModal: React.FC<CourseSelectionModalProps> = ({
  isOpen,
  onClose,
  onSave,
  currentCourseIds = [],
}) => {
  // Placeholder state (will be expanded)
  const [searchQuery, setSearchQuery] = React.useState("");
  const [selectedCourseIds, setSelectedCourseIds] = React.useState<number[]>(currentCourseIds);
  const [availableCourses, setAvailableCourses] = React.useState<any[]>([]);
  const [isLoading, setIsLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  // Placeholder handlers (will be expanded)
  const handleSearchChange = (value: string) => {
    // TODO: Implement search functionality
    setSearchQuery(value);
    console.log("Search query changed - to be implemented", value);
  };

  const handleCourseToggle = (courseId: number) => {
    // TODO: Implement course selection toggle
    console.log("Course toggle functionality - to be implemented", courseId);

    setSelectedCourseIds((prev) => {
      if (prev.includes(courseId)) {
        return prev.filter((id) => id !== courseId);
      } else {
        return [...prev, courseId];
      }
    });
  };

  const handleSelectAll = () => {
    // TODO: Implement select all functionality
    console.log("Select all functionality - to be implemented");
  };

  const handleDeselectAll = () => {
    // TODO: Implement deselect all functionality
    console.log("Deselect all functionality - to be implemented");
    setSelectedCourseIds([]);
  };

  const handleSave = () => {
    // TODO: Implement save functionality
    console.log("Save functionality - to be implemented", selectedCourseIds);
    onSave(selectedCourseIds);
    onClose();
  };

  const handleLoadCourses = async () => {
    // TODO: Implement course loading
    setIsLoading(true);
    console.log("Load courses functionality - to be implemented");

    // Simulate async operation
    setTimeout(() => {
      setIsLoading(false);
    }, 1000);
  };

  // Load courses when modal opens
  React.useEffect(() => {
    if (isOpen) {
      handleLoadCourses();
    }
  }, [isOpen]);

  return (
    <Modal
      title={__("Select Courses for Bundle", "tutorpress")}
      isFullScreen={false}
      onRequestClose={onClose}
      className="tutorpress-course-selection-modal"
    >
      <div className="tutorpress-modal-content">
        {/* Error display */}
        {error && (
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        )}

        {/* Search input */}
        <div className="tutorpress-search-section">
          <TextControl
            value={searchQuery}
            onChange={handleSearchChange}
            placeholder={__("Search courses...", "tutorpress")}
            disabled={isLoading}
          />
        </div>

        {/* Loading state */}
        {isLoading && (
          <div className="tutorpress-loading">
            <Spinner />
            {__("Loading courses...", "tutorpress")}
          </div>
        )}

        {/* Course list placeholder */}
        <div className="tutorpress-course-list">
          <div className="tutorpress-course-list-placeholder">
            <p>{__("No courses available.", "tutorpress")}</p>
            <p className="description">{__("Search for courses to add to this bundle.", "tutorpress")}</p>
          </div>
        </div>

        {/* Selection summary */}
        <div className="tutorpress-selection-summary">
          <p>
            {__("Selected:", "tutorpress")} {selectedCourseIds.length} {__("courses", "tutorpress")}
          </p>
        </div>

        {/* Action buttons */}
        <div className="tutorpress-modal-actions">
          <div className="tutorpress-modal-actions-left">
            <Button variant="secondary" onClick={handleSelectAll} disabled={isLoading}>
              {__("Select All", "tutorpress")}
            </Button>

            <Button variant="secondary" onClick={handleDeselectAll} disabled={isLoading}>
              {__("Deselect All", "tutorpress")}
            </Button>
          </div>

          <div className="tutorpress-modal-actions-right">
            <Button variant="secondary" onClick={onClose} disabled={isLoading}>
              {__("Cancel", "tutorpress")}
            </Button>

            <Button icon={plus} variant="primary" onClick={handleSave} disabled={isLoading}>
              {__("Add Selected Courses", "tutorpress")}
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  );
};

export default CourseSelectionModal;
