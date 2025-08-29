/**
 * Bundle Instructors Panel Component
 *
 * Display-only instructor panel that shows aggregated instructors from bundle courses.
 * Auto-updates when bundle courses change via custom events.
 *
 * @package TutorPress
 * @since 0.1.0
 */

import React, { useEffect, useState, useCallback } from "react";
import { PluginDocumentSettingPanel } from "@wordpress/edit-post";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import { Spinner, Notice } from "@wordpress/components";
import PromoPanel from "../common/PromoPanel";

// Import types
import type { BundleInstructor } from "../../types/bundle";

/**
 * Bundle Instructors Panel Component
 *
 * Features:
 * - Dynamic instructor list display from bundle courses
 * - Auto-update when bundle courses change
 * - Display-only (no editing functionality)
 * - Handles loading states and error conditions
 * - Shows unique instructors only (no duplicates)
 */
const BundleInstructorsPanel: React.FC = () => {
  // Local state for instructor data
  const [instructors, setInstructors] = useState<BundleInstructor[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [totalInstructors, setTotalInstructors] = useState(0);
  const [totalCourses, setTotalCourses] = useState(0);

  // Get bundle data from our store and Gutenberg store
  const { postType, postId, bundleCourseIds } = useSelect(
    (select: any) => ({
      postType: select("core/editor").getCurrentPostType(),
      postId: select("core/editor").getCurrentPostId(),
      bundleCourseIds: select("core/editor").getEditedPostAttribute("meta")?.["bundle-course-ids"] || "",
    }),
    []
  );

  // Get dispatch actions
  const { getBundleInstructors } = useDispatch("tutorpress/course-bundles");

  /**
   * Fetch instructors from bundle courses
   */
  const fetchInstructors = useCallback(async () => {
    if (!postId || postType !== "course-bundle") return;

    setIsLoading(true);
    setError(null);

    try {
      const response = await getBundleInstructors(postId);

      if (response && response.success) {
        setInstructors(response.data || []);
        setTotalInstructors(response.total_instructors || 0);
        setTotalCourses(response.total_courses || 0);
      } else {
        setError(__("Failed to load instructors.", "tutorpress"));
        setInstructors([]);
        setTotalInstructors(0);
        setTotalCourses(0);
      }
    } catch (error) {
      console.error("Error fetching bundle instructors:", error);
      setError(__("Error loading instructors. Please try again.", "tutorpress"));
      setInstructors([]);
      setTotalInstructors(0);
      setTotalCourses(0);
    } finally {
      setIsLoading(false);
    }
  }, [postId, postType, getBundleInstructors]);

  // Load instructors when component mounts
  useEffect(() => {
    if (postType === "course-bundle" && postId) {
      fetchInstructors();
    }
  }, [postType, postId, fetchInstructors]);

  // Listen for course changes via custom events (like BundlePricingPanel)
  useEffect(() => {
    const handleCourseChange = async (event: Event) => {
      const customEvent = event as CustomEvent;
      // Only respond to events for this bundle
      if (customEvent.detail?.bundleId !== postId) return;

      if (!postId || postType !== "course-bundle") return;

      console.log("Bundle courses updated, refreshing instructors...");
      await fetchInstructors();
    };

    // Listen for course changes from the Courses Metabox
    window.addEventListener("tutorpress-bundle-courses-updated", handleCourseChange);

    return () => {
      window.removeEventListener("tutorpress-bundle-courses-updated", handleCourseChange);
    };
  }, [postId, postType, fetchInstructors]);

  // Only show for course-bundle post type
  if (postType !== "course-bundle") {
    return null;
  }

  // Check Freemius premium access (fail-closed)
  const canUsePremium = window.tutorpress_fs?.canUsePremium ?? false;

  // Show promo content if user doesn't have premium access
  if (!canUsePremium) {
    return (
      <PluginDocumentSettingPanel
        name={"tutorpress-bundle-instructors"}
        title={__("Bundle Instructors", "tutorpress")}
        className={"tutorpress-bundle-instructors-panel"}
      >
        <PromoPanel />
      </PluginDocumentSettingPanel>
    );
  }

  // Show loading state
  if (isLoading) {
    return (
      <PluginDocumentSettingPanel
        name="tutorpress-bundle-instructors"
        title={__("Bundle Instructors", "tutorpress")}
        className="tutorpress-bundle-instructors-panel"
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
        name="tutorpress-bundle-instructors"
        title={__("Bundle Instructors", "tutorpress")}
        className="tutorpress-bundle-instructors-panel"
      >
        <Notice status="error" isDismissible={false}>
          {error}
        </Notice>
      </PluginDocumentSettingPanel>
    );
  }

  return (
    <PluginDocumentSettingPanel
      name="tutorpress-bundle-instructors"
      title={__("Bundle Instructors", "tutorpress")}
      className="tutorpress-bundle-instructors-panel"
    >
      <div className="tutorpress-instructors-panel">
        {/* Instructors List */}
        {instructors.length > 0 ? (
          <div className="tutorpress-saved-files-list">
            {instructors.map((instructor: BundleInstructor) => (
              <div key={instructor.id} className="tutorpress-saved-file-item">
                <div className="tutorpress-instructor-info">
                  <div className="tutorpress-instructor-avatar">
                    {instructor.avatar_url ? (
                      <img
                        src={instructor.avatar_url}
                        alt={instructor.display_name}
                        className="tutorpress-instructor-avatar-img"
                      />
                    ) : (
                      <div className="tutorpress-instructor-avatar-placeholder">
                        {instructor.display_name.charAt(0).toUpperCase()}
                      </div>
                    )}
                  </div>
                  <div className="tutorpress-instructor-details">
                    <div className="tutorpress-instructor-name">{instructor.display_name}</div>
                    <div className="tutorpress-instructor-email">{instructor.user_email}</div>
                    {instructor.designation && (
                      <div className="tutorpress-instructor-designation">{instructor.designation}</div>
                    )}
                  </div>
                </div>
                {/* No delete button - display only */}
              </div>
            ))}
          </div>
        ) : (
          <div className="tutorpress-instructors-empty">
            <p>{__("No instructors found in this bundle's courses.", "tutorpress")}</p>
            <p className="description">
              {__("Instructors will appear here when courses are added to this bundle.", "tutorpress")}
            </p>
          </div>
        )}

        {/* Help Text */}
        <div className="tutorpress-help-text">
          <p className="description">
            {__(
              "Instructors are automatically determined from the courses included in this bundle. The list updates automatically when courses are added or removed.",
              "tutorpress"
            )}
          </p>
        </div>
      </div>
    </PluginDocumentSettingPanel>
  );
};

export default BundleInstructorsPanel;
