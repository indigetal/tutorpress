/**
 * Bundle Course Selection Metabox Component
 *
 * Skeleton component for bundle course selection functionality.
 * Will be expanded in Setting 1: Course List Selection.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React from "react";
import { __ } from "@wordpress/i18n";
import { Button, Notice } from "@wordpress/components";
import { plus } from "@wordpress/icons";

// Import types (will be expanded as needed)
import type { Bundle } from "../../../types/bundle";

/**
 * Bundle Course Selection Metabox Component
 *
 * Features (to be implemented):
 * - Course list display
 * - Course search and selection
 * - Course addition/removal
 * - Course reordering
 */
const CourseSelection: React.FC = () => {
  // Placeholder state (will be expanded)
  const [isLoading, setIsLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);

  // Placeholder handlers (will be expanded)
  const handleAddCourse = () => {
    // TODO: Implement course addition
    console.log("Add course functionality - to be implemented");
  };

  const handleRemoveCourse = (courseId: number) => {
    // TODO: Implement course removal
    console.log("Remove course functionality - to be implemented", courseId);
  };

  const handleReorderCourses = (courseIds: number[]) => {
    // TODO: Implement course reordering
    console.log("Reorder courses functionality - to be implemented", courseIds);
  };

  return (
    <div className="tutorpress-bundle-course-selection">
      <div className="tutorpress-metabox-header">
        <h3>{__("Bundle Courses", "tutorpress")}</h3>
        <p className="description">{__("Select courses to include in this bundle.", "tutorpress")}</p>
      </div>

      <div className="tutorpress-metabox-content">
        {/* Error display */}
        {error && (
          <Notice status="error" isDismissible={false}>
            {error}
          </Notice>
        )}

        {/* Loading state */}
        {isLoading && <div className="tutorpress-loading">{__("Loading courses...", "tutorpress")}</div>}

        {/* Course list placeholder */}
        <div className="tutorpress-course-list">
          <div className="tutorpress-course-list-placeholder">
            <p>{__("No courses selected yet.", "tutorpress")}</p>
            <p className="description">{__("Click the button below to add courses to this bundle.", "tutorpress")}</p>
          </div>
        </div>

        {/* Add course button */}
        <div className="tutorpress-metabox-actions">
          <Button icon={plus} variant="primary" onClick={handleAddCourse} disabled={isLoading}>
            {__("Add Course", "tutorpress")}
          </Button>
        </div>
      </div>
    </div>
  );
};

export default CourseSelection;
