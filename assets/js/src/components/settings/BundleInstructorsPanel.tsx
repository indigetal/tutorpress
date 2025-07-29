/**
 * Bundle Instructors Panel Component
 *
 * Skeleton component for bundle instructors settings.
 * Will be expanded in Setting 4: Dynamic Instructors.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/editor";
import { __ } from "@wordpress/i18n";
import { PanelRow, Notice, Button } from "@wordpress/components";
import { plus } from "@wordpress/icons";

// Import types (will be expanded as needed)
import type { Bundle } from "../../types/bundle";

/**
 * Bundle Instructors Panel Component
 *
 * Features (to be implemented):
 * - Dynamic instructor list display
 * - Instructor search and selection
 * - Instructor addition/removal
 * - Instructor reordering
 * - Auto-population from bundle courses
 */
const BundleInstructorsPanel: React.FC = () => {
  // Placeholder state (will be expanded)
  const [instructors, setInstructors] = React.useState<any[]>([]);
  const [isLoading, setIsLoading] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [isSearching, setIsSearching] = React.useState(false);

  // Placeholder handlers (will be expanded)
  const handleAddInstructor = () => {
    // TODO: Implement instructor addition
    console.log("Add instructor functionality - to be implemented");
  };

  const handleRemoveInstructor = (instructorId: number) => {
    // TODO: Implement instructor removal
    console.log("Remove instructor functionality - to be implemented", instructorId);
  };

  const handleReorderInstructors = (instructorIds: number[]) => {
    // TODO: Implement instructor reordering
    console.log("Reorder instructors functionality - to be implemented", instructorIds);
  };

  const handleLoadInstructorsFromCourses = async () => {
    // TODO: Implement auto-loading instructors from bundle courses
    setIsLoading(true);
    console.log("Load instructors from courses functionality - to be implemented");

    // Simulate async operation
    setTimeout(() => {
      setIsLoading(false);
    }, 1000);
  };

  return (
    <PluginDocumentSettingPanel
      name="tutorpress-bundle-instructors"
      title={__("Bundle Instructors", "tutorpress")}
      className="tutorpress-bundle-instructors-panel"
    >
      {/* Error display */}
      {error && (
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      )}

      {/* Loading state */}
      {isLoading && <div className="tutorpress-loading">{__("Loading instructors...", "tutorpress")}</div>}

      {/* Instructor list placeholder */}
      <PanelRow>
        <div className="tutorpress-instructor-list">
          <div className="tutorpress-instructor-list-placeholder">
            <p>{__("No instructors added yet.", "tutorpress")}</p>
            <p className="description">
              {__("Instructors will be automatically populated from the courses in this bundle.", "tutorpress")}
            </p>
          </div>
        </div>
      </PanelRow>

      {/* Action buttons */}
      <PanelRow>
        <div className="tutorpress-panel-actions">
          <Button icon={plus} variant="secondary" onClick={handleAddInstructor} disabled={isLoading}>
            {__("Add Instructor", "tutorpress")}
          </Button>

          <Button variant="secondary" onClick={handleLoadInstructorsFromCourses} disabled={isLoading}>
            {__("Load from Courses", "tutorpress")}
          </Button>
        </div>
      </PanelRow>

      {/* Help text */}
      <PanelRow>
        <div className="tutorpress-help-text">
          <p className="description">
            {__(
              "Instructors are automatically determined from the courses included in this bundle. You can manually add or remove instructors as needed.",
              "tutorpress"
            )}
          </p>
        </div>
      </PanelRow>
    </PluginDocumentSettingPanel>
  );
};

export default BundleInstructorsPanel;
