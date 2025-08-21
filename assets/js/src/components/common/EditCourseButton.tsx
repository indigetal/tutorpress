import React, { useEffect, useRef, useCallback } from "react";
import { __ } from "@wordpress/i18n";
import { Button, Spinner } from "@wordpress/components";
import { external } from "@wordpress/icons";
import { useSelect } from "@wordpress/data";
import { useCourseId } from "../../hooks/curriculum/useCourseId";
import { createRoot, type Root } from "react-dom/client";

const BUTTON_CONTAINER_ID = "tutorpress-edit-course-button-container";

interface ButtonContentProps {
  isButtonDisabled: boolean;
  handleEditCourse: () => void;
  getButtonText: () => string;
  courseId: number | null | undefined;
  curriculumBuilderExists: boolean;
}

// Extracted inner component to receive props
const ButtonContent: React.FC<ButtonContentProps> = React.memo(
  ({ isButtonDisabled, handleEditCourse, getButtonText, courseId, curriculumBuilderExists }) => {
    // console.log("ButtonContent Props Debug:", { isButtonDisabled, courseId }); // Keep this for inner component debug

    return (
      <div className="tutorpress-edit-course-button">
        <Button
          variant="secondary"
          icon={external}
          onClick={handleEditCourse}
          className="tutorpress-edit-course-btn"
          disabled={isButtonDisabled}
          title={
            !curriculumBuilderExists && courseId === null
              ? __("Curriculum metabox not available or course ID not found", "tutorpress")
              : undefined
          }
        >
          {courseId === undefined ? <Spinner /> : getButtonText()}
        </Button>
      </div>
    );
  }
);

/**
 * Edit Course Button Component - Phase 3: DOM Injection
 *
 * This component's primary responsibility is to render the "Edit Course" button.
 * Its DOM injection logic ensures it appears in the Gutenberg header toolbar.
 */
const EditCourseButton: React.FC = () => {
  // Use a ref to store the React root and DOM container for persistent management
  const reactRootRef = useRef<Root | null>(null);
  const domContainerRef = useRef<HTMLDivElement | null>(null);

  // Get current post type (stable across renders typically)
  const postType = useSelect((select: any) => select("core/editor").getCurrentPostType(), []);

  // Get course ID (can change from undefined -> null/number)
  const courseId = useCourseId();

  // Check if the required DOM element exists for course ID fetching
  const curriculumBuilderExists = !!document.getElementById("tutorpress-curriculum-builder");

  // Determine if the button should be disabled
  const isButtonDisabled = courseId === null || !curriculumBuilderExists;

  const handleEditCourse = useCallback(() => {
    if (courseId) {
      const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
      const url = new URL("post.php", adminUrl);
      url.searchParams.append("post", courseId.toString());
      url.searchParams.append("action", "edit");

      console.log("EditCourseButton: Navigating to course editor:", url.toString());
      window.location.href = url.toString();
    } else {
      console.warn("EditCourseButton: No course ID available for navigation");
    }
  }, [courseId]);

  const getButtonText = useCallback(() => {
    return __("Edit Course", "tutorpress");
  }, []);

  // Effect for initial DOM injection and cleanup (runs only once on mount/unmount)
  // or when the toolbar truly needs to be recreated.
  useEffect(() => {
    // Return early if not on a relevant post type, ensuring no injection happens
    if (postType !== "lesson" && postType !== "tutor_assignments") {
      // If we somehow were on a relevant type and then switched away, clean up
      if (reactRootRef.current) {
        reactRootRef.current.unmount();
        reactRootRef.current = null;
        domContainerRef.current?.remove();
        domContainerRef.current = null;
      }
      return;
    }

    const toolbar = document.querySelector(".edit-post-header-toolbar");
    if (!toolbar) {
      console.warn("TutorPress: Could not find Gutenberg header toolbar during initial mount.");
      return;
    }

    let container = document.getElementById(BUTTON_CONTAINER_ID) as HTMLDivElement | null;
    let root = reactRootRef.current;

    // Create container and root only if they don't exist
    if (!container) {
      container = document.createElement("div");
      container.id = BUTTON_CONTAINER_ID;
      container.style.display = "inline-block";
      container.style.marginRight = "8px";

      const toolbarChildren = Array.from(toolbar.children);
      if (toolbarChildren.length >= 5) {
        toolbar.insertBefore(container, toolbarChildren[5] || null);
      } else {
        toolbar.appendChild(container);
      }

      domContainerRef.current = container;
      root = createRoot(container);
      reactRootRef.current = root;
    } else if (!root) {
      // Re-create root if container exists but root was somehow lost
      root = createRoot(container);
      reactRootRef.current = root;
      console.log("TutorPress: Re-created React root for existing container.");
    }

    // --- MutationObserver for toolbar changes ---
    const observer = new MutationObserver((mutations) => {
      const toolbarRecreated = mutations.some((mutation) =>
        Array.from(mutation.addedNodes).some(
          (node) =>
            node.nodeType === Node.ELEMENT_NODE && (node as Element).classList?.contains("edit-post-header-toolbar")
        )
      );

      const ourContainerRemoved = mutations.some((mutation) =>
        Array.from(mutation.removedNodes).some(
          (node) => node.nodeType === Node.ELEMENT_NODE && (node as Element).id === BUTTON_CONTAINER_ID
        )
      );

      // If the toolbar was removed/re-added, or our container was removed,
      // we need to perform a full cleanup and let the parent component's
      // next render re-trigger this effect (if postType is still valid).
      if (toolbarRecreated || ourContainerRemoved) {
        console.log("TutorPress: Toolbar or button container detected change. Initiating full cleanup.");
        observer.disconnect(); // Disconnect old observer
        // Perform cleanup directly here, then rely on React's lifecycle
        // The cleanup below will run due to the return function.
        // We ensure a full re-init on the next relevant render.
      }
    });

    if (toolbar.parentNode) {
      observer.observe(toolbar.parentNode, { childList: true, subtree: true });
    } else {
      observer.observe(document.body, { childList: true, subtree: true });
    }

    // Cleanup function for when component unmounts or postType changes to non-relevant
    return () => {
      observer.disconnect();
      if (reactRootRef.current) {
        reactRootRef.current.unmount();
        reactRootRef.current = null;
      }
      if (domContainerRef.current) {
        domContainerRef.current.remove();
        domContainerRef.current = null;
      }
      console.log("TutorPress: EditCourseButton cleanup performed.");
    };
  }, [postType]); // **Crucial**: This effect runs only when postType changes.

  // Effect to update the ButtonContent within the already mounted React root.
  // This effect runs whenever any of its dependencies (props for ButtonContent) change.
  useEffect(() => {
    // Only attempt to render if the initial DOM injection has successfully happened
    // and we have a valid React root.
    if (
      (postType === "lesson" || postType === "tutor_assignments") &&
      reactRootRef.current &&
      domContainerRef.current
    ) {
      reactRootRef.current.render(
        <ButtonContent
          isButtonDisabled={isButtonDisabled}
          handleEditCourse={handleEditCourse}
          getButtonText={getButtonText}
          courseId={courseId}
          curriculumBuilderExists={curriculumBuilderExists}
        />
      );
    }
  }, [
    postType, // If postType changes to/from relevant, the first effect handles cleanup/setup
    isButtonDisabled,
    handleEditCourse,
    getButtonText,
    courseId,
    curriculumBuilderExists,
  ]);

  // Return null because this component doesn't render anything directly.
  return null;
};

export default EditCourseButton;
