/**
 * Course Selection Modal Component
 *
 * @description Modal for selecting courses to add to a bundle. Follows the established modal patterns:
 *              1. Uses WordPress Modal component directly
 *              2. Course search and selection with pagination
 *              3. Integrates with WordPress Data Store following API_FETCH pattern
 *              4. Maintains consistent UI/UX with other TutorPress modals
 *
 * @package TutorPress
 * @subpackage Components/Modals/Bundles
 * @since 1.0.0
 */

import React, { useState, useEffect } from "react";
import { Modal, Button, Notice, Spinner, CheckboxControl, TextControl } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelect } from "@wordpress/data";
import { store as noticesStore } from "@wordpress/notices";
import type { AvailableCourse } from "../../../types/bundle";

interface CourseSelectionModalProps {
  isOpen: boolean;
  onClose: () => void;
  onAddCourses: (courseIds: number[]) => void;
  bundleId?: number;
  excludeCourseIds?: number[];
}

const COURSE_BUNDLES_STORE = "tutorpress/course-bundles";

export const CourseSelectionModal: React.FC<CourseSelectionModalProps> = ({
  isOpen,
  onClose,
  onAddCourses,
  bundleId,
  excludeCourseIds = [],
}) => {
  // State management
  const [searchTerm, setSearchTerm] = useState("");
  const [selectedCourses, setSelectedCourses] = useState<number[]>([]);
  const [selectAll, setSelectAll] = useState(false);
  const [currentPage, setCurrentPage] = useState(1);
  const [isLoading, setIsLoading] = useState(false);
  const [availableCourses, setAvailableCourses] = useState<AvailableCourse[]>([]);

  // Store dispatch
  const { fetchAvailableCourses } = useDispatch(COURSE_BUNDLES_STORE);

  // Load available courses when modal opens
  useEffect(() => {
    if (isOpen) {
      loadAvailableCourses();
    }
  }, [isOpen, currentPage, searchTerm]);

  // Reset state when modal opens
  useEffect(() => {
    if (isOpen) {
      setSelectedCourses([]);
      setSelectAll(false);
      setCurrentPage(1);
      setSearchTerm("");
    }
  }, [isOpen]);

  /**
   * Load available courses
   */
  const loadAvailableCourses = async () => {
    setIsLoading(true);
    try {
      const excludeIds = [...excludeCourseIds];
      if (bundleId) {
        // Add current bundle courses to exclude list
        // This would need to be implemented based on current bundle courses
      }

      const response = await fetchAvailableCourses({
        search: searchTerm || undefined,
        per_page: 20,
        page: currentPage,
        exclude: excludeIds.length > 0 ? excludeIds.join(",") : undefined,
      });

      if (response && response.data) {
        setAvailableCourses(response.data);
      }
    } catch (error) {
      console.error("Failed to load available courses:", error);
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * Handle search input change
   */
  const handleSearchChange = (value: string) => {
    setSearchTerm(value);
    setCurrentPage(1); // Reset to first page when searching
  };

  /**
   * Handle individual course selection
   */
  const handleCourseSelection = (courseId: number, checked: boolean) => {
    if (checked) {
      setSelectedCourses([...selectedCourses, courseId]);
    } else {
      setSelectedCourses(selectedCourses.filter((id) => id !== courseId));
    }
  };

  /**
   * Handle select all toggle
   */
  const handleSelectAll = (checked: boolean) => {
    setSelectAll(checked);
    if (checked) {
      const allCourseIds = availableCourses.map((course: AvailableCourse) => course.id);
      setSelectedCourses(allCourseIds);
    } else {
      setSelectedCourses([]);
    }
  };

  /**
   * Handle pagination
   */
  const handlePageChange = (page: number) => {
    setCurrentPage(page);
  };

  /**
   * Handle add courses
   */
  const handleAddCourses = () => {
    if (selectedCourses.length > 0) {
      onAddCourses(selectedCourses);
      onClose();
    }
  };

  /**
   * Handle modal close
   */
  const handleClose = () => {
    setSelectedCourses([]);
    setSelectAll(false);
    setCurrentPage(1);
    setSearchTerm("");
    onClose();
  };

  if (!isOpen) return null;

  return (
    <Modal
      title={__("Select Courses", "tutorpress")}
      onRequestClose={handleClose}
      className="tutorpress-course-selection-modal"
      size="large"
    >
      <div className="tutorpress-modal-content">
        {/* Search Field */}
        <div className="tutorpress-search-section">
          <TextControl
            value={searchTerm}
            onChange={handleSearchChange}
            placeholder={__("Search for courses...", "tutorpress")}
          />
        </div>

        {/* Loading State */}
        {isLoading && (
          <div className="tutorpress-modal-loading tpress-loading-state-centered">
            <Spinner />
            <p>{__("Loading courses...", "tutorpress")}</p>
          </div>
        )}

        {/* Course List */}
        {!isLoading && (
          <div className="tutorpress-course-content">
            {/* Table Headers */}
            <div className="tutorpress-course-table-header">
              <div className="tutorpress-course-table-row">
                <div className="tutorpress-course-checkbox">
                  <CheckboxControl checked={selectAll} onChange={handleSelectAll} label={__("Name", "tutorpress")} />
                </div>
                <div className="tutorpress-course-price-header">{__("Price", "tutorpress")}</div>
              </div>
            </div>

            {/* Course List */}
            <div className="tutorpress-course-list">
              {availableCourses.length === 0 ? (
                <div className="tutorpress-no-courses">
                  <p>{__("No courses found.", "tutorpress")}</p>
                </div>
              ) : (
                availableCourses.map((course: AvailableCourse) => (
                  <div key={course.id} className="tutorpress-course-table-row">
                    <div className="tutorpress-course-info">
                      <CheckboxControl
                        checked={selectedCourses.includes(course.id)}
                        onChange={(checked) => handleCourseSelection(course.id, checked)}
                      />
                      <div className="tutorpress-course-thumbnail">
                        {course.featured_image ? (
                          <img src={course.featured_image} alt={course.title} width="40" height="40" />
                        ) : (
                          <div className="tutorpress-course-thumbnail-placeholder">
                            {course.title.charAt(0).toUpperCase()}
                          </div>
                        )}
                      </div>
                      <div className="tutorpress-course-title">{course.title}</div>
                    </div>
                    <div className="tutorpress-course-price" 
                         dangerouslySetInnerHTML={{ __html: course.price || __("Free", "tutorpress") }}
                    />
                  </div>
                ))
              )}
            </div>

            {/* Pagination */}
            {availableCourses.length > 0 && (
              <div className="tutorpress-pagination">
                <Button
                  variant="secondary"
                  onClick={() => handlePageChange(currentPage - 1)}
                  disabled={currentPage === 1}
                >
                  {__("Previous", "tutorpress")}
                </Button>
                <span className="tutorpress-page-info">
                  {__("Page", "tutorpress")} {currentPage}
                </span>
                <Button
                  variant="secondary"
                  onClick={() => handlePageChange(currentPage + 1)}
                  disabled={availableCourses.length < 20} // Assuming 20 per page
                >
                  {__("Next", "tutorpress")}
                </Button>
              </div>
            )}
          </div>
        )}

        {/* Modal Actions */}
        <div className="tutorpress-modal-actions tpress-button-group tpress-button-group-end">
          <Button variant="tertiary" onClick={handleClose}>
            {__("Cancel", "tutorpress")}
          </Button>
          <Button variant="primary" onClick={handleAddCourses} disabled={selectedCourses.length === 0 || isLoading}>
            {__("Add", "tutorpress")} {selectedCourses.length > 0 && `(${selectedCourses.length})`}
          </Button>
        </div>
      </div>
    </Modal>
  );
};
