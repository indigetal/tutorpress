import React, { useEffect } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { useSelect, useDispatch } from "@wordpress/data";
import { Spinner, Notice } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

// Import course settings types
import type { CourseInstructors } from "../../types/courses";

const CourseInstructorsPanel: React.FC = () => {
  // Get instructor data from our store
  const { postType, instructors, error, isLoading } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      instructors: select("tutorpress/course-settings").getInstructors(),
      error: select("tutorpress/course-settings").getInstructorsError(),
      isLoading: select("tutorpress/course-settings").getInstructorsLoading(),
    }),
    []
  );

  // Get dispatch actions
  const { getCourseInstructors } = useDispatch("tutorpress/course-settings");

  // Load instructors when component mounts
  useEffect(() => {
    if (postType === "courses") {
      getCourseInstructors();
    }
  }, [postType, getCourseInstructors]);

  // Only show for course post type
  if (postType !== "courses") {
    return null;
  }

  // Show loading state while fetching instructors
  if (isLoading) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-course-instructors"
        title={__("Course Instructors", "tutorpress")}
        className="tutorpress-settings-section"
      >
        <div className="tutorpress-settings-loading">
          <Spinner />
          <div className="tutorpress-settings-loading-text">{__("Loading instructors...", "tutorpress")}</div>
        </div>
      </PluginDocumentSettingPanel>
    );
  }

  // Show error state if there's an error
  if (error) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-course-instructors"
        title={__("Course Instructors", "tutorpress")}
        className="tutorpress-settings-section"
      >
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      </PluginDocumentSettingPanel>
    );
  }

  // Show message if no instructor data is available
  if (!instructors) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-course-instructors"
        title={__("Course Instructors", "tutorpress")}
        className="tutorpress-settings-section"
      >
        <div className="tutorpress-instructors-empty">
          <p>{__("No instructors assigned to this course.", "tutorpress")}</p>
        </div>
      </PluginDocumentSettingPanel>
    );
  }

  return (
    <PluginDocumentSettingPanel
      name="tutorpress-course-instructors"
      title={__("Course Instructors", "tutorpress")}
      className="tutorpress-settings-section"
    >
      <div className="tutorpress-instructors-panel">
        {instructors.author && (
          <div className="tutorpress-saved-files-list">
            <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
              {__("Author:", "tutorpress")}
            </div>
            <div className="tutorpress-saved-file-item">
              <div className="tutorpress-instructor-info">
                <div className="tutorpress-instructor-avatar">
                  {instructors.author.avatar ? (
                    <img
                      src={instructors.author.avatar}
                      alt={instructors.author.name}
                      className="tutorpress-instructor-avatar-img"
                    />
                  ) : (
                    <div className="tutorpress-instructor-avatar-placeholder">
                      {instructors.author.name.charAt(0).toUpperCase()}
                    </div>
                  )}
                </div>
                <div className="tutorpress-instructor-details">
                  <div className="tutorpress-instructor-name">{instructors.author.name}</div>
                  <div className="tutorpress-instructor-email">{instructors.author.email}</div>
                </div>
              </div>
            </div>
          </div>
        )}

        {instructors.co_instructors && instructors.co_instructors.length > 0 && (
          <div className="tutorpress-saved-files-list">
            <div style={{ fontSize: "12px", fontWeight: "500", marginBottom: "4px" }}>
              {__("Co-Instructors:", "tutorpress")} ({instructors.co_instructors.length})
            </div>
            {instructors.co_instructors.map((instructor: any) => (
              <div key={instructor.id} className="tutorpress-saved-file-item">
                <div className="tutorpress-instructor-info">
                  <div className="tutorpress-instructor-avatar">
                    {instructor.avatar ? (
                      <img src={instructor.avatar} alt={instructor.name} className="tutorpress-instructor-avatar-img" />
                    ) : (
                      <div className="tutorpress-instructor-avatar-placeholder">
                        {instructor.name.charAt(0).toUpperCase()}
                      </div>
                    )}
                  </div>
                  <div className="tutorpress-instructor-details">
                    <div className="tutorpress-instructor-name">{instructor.name}</div>
                    <div className="tutorpress-instructor-email">{instructor.email}</div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {!instructors.author && (!instructors.co_instructors || instructors.co_instructors.length === 0) && (
          <div className="tutorpress-instructors-empty">
            <p>{__("No instructors assigned to this course.", "tutorpress")}</p>
          </div>
        )}
      </div>
    </PluginDocumentSettingPanel>
  );
};

export default CourseInstructorsPanel;
