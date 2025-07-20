import React from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { Button } from "@wordpress/components";
import { external } from "@wordpress/icons";
import { useSelect } from "@wordpress/data";

interface EditCourseButtonProps {
  className?: string;
}

/**
 * Edit Course Button Component - Phase 1 Scaffolding
 *
 * Basic button component that appears in the Document Settings sidebar
 * for lessons and assignments. This follows TutorPress's established pattern
 * using PluginDocumentSettingPanel.
 *
 * Phase 1: Basic structure with hardcoded functionality for testing
 * Future phases will add: course ID integration, DOM injection, navigation
 */
const EditCourseButton: React.FC<EditCourseButtonProps> = ({ className = "" }) => {
  // Get current post type to determine if we should show the button
  const postType = useSelect((select: any) => select("core/editor").getCurrentPostType(), []);

  // Only show for lessons and assignments
  if (postType !== "lesson" && postType !== "tutor_assignments") {
    return null;
  }

  // Phase 1: Hardcoded functionality for testing
  const handleEditCourse = () => {
    // Phase 1: Simple alert for testing
    // Future phases will implement proper navigation
    alert("Edit Course button clicked! (Phase 1 - Navigation not implemented yet)");
  };

  return (
    <PluginDocumentSettingPanel
      name="edit-course-button"
      title={__("Course Actions", "tutorpress")}
      className={`tutorpress-edit-course-panel ${className}`}
    >
      <div className="tutorpress-edit-course-button">
        <Button variant="secondary" icon={external} onClick={handleEditCourse} className="tutorpress-edit-course-btn">
          {__("Edit Course", "tutorpress")}
        </Button>
      </div>
    </PluginDocumentSettingPanel>
  );
};

export default EditCourseButton;
