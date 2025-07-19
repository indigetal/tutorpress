/**
 * Hook for managing assignments in a course curriculum
 *
 * Orchestrates assignment operations and coordinates with the curriculum store.
 * The store handles all state management, while this hook provides the
 * operations interface for components.
 */
import { useCallback } from "react";
import { AssignmentDuplicationState, CurriculumError, CurriculumErrorCode } from "../../types/curriculum";
import type { Assignment } from "../../types/assignments";
import { __ } from "@wordpress/i18n";
import { useDispatch, useSelect } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";

export interface UseAssignmentsOptions {
  courseId?: number;
  topicId?: number;
}

export interface UseAssignmentsReturn {
  // State
  assignmentDuplicationState: AssignmentDuplicationState;

  // Assignment Operations
  handleAssignmentEdit: (assignmentId: number) => void;
  handleAssignmentDuplicate: (assignmentId: number, topicId: number) => Promise<void>;
  handleAssignmentDelete: (assignmentId: number) => Promise<void>;

  // Computed
  isAssignmentDuplicating: boolean;
  assignmentDuplicationError: CurriculumError | null;
}

/**
 * Hook for managing assignment operations
 */
export function useAssignments({ courseId, topicId }: UseAssignmentsOptions): UseAssignmentsReturn {
  const { createNotice } = useDispatch(noticesStore);
  const { deleteAssignment, duplicateAssignment } = useDispatch("tutorpress/curriculum");

  // Get assignment duplication state from store
  const assignmentDuplicationState = useSelect((select) => {
    const curriculumSelectors = select("tutorpress/curriculum") as {
      getAssignmentDuplicationState: () => AssignmentDuplicationState;
    };
    return curriculumSelectors.getAssignmentDuplicationState();
  }, []);

  /** Handle assignment edit - redirect to assignment editor */
  const handleAssignmentEdit = useCallback((assignmentId: number) => {
    const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
    const url = new URL("post.php", adminUrl);
    url.searchParams.append("post", assignmentId.toString());
    url.searchParams.append("action", "edit");
    window.location.href = url.toString();
  }, []);

  /** Handle assignment duplication with redirect to editor */
  const handleAssignmentDuplicate = useCallback(
    async (assignmentId: number, targetTopicId: number) => {
      if (!courseId) {
        const errorMessage = __("Course ID not available to duplicate assignment.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
        return;
      }

      try {
        // Call the store function to duplicate the assignment (returns assignment data)
        const duplicatedAssignment = await duplicateAssignment(assignmentId, targetTopicId, courseId);

        // Show success notice
        createNotice("success", __("Assignment duplicated successfully. Redirecting to editor...", "tutorpress"), {
          type: "snackbar",
        });

        // Redirect to the duplicate assignment editor immediately
        const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
        const url = new URL("post.php", adminUrl);
        url.searchParams.append("post", duplicatedAssignment.id.toString());
        url.searchParams.append("action", "edit");

        // Redirect immediately - no delay needed
        window.location.href = url.toString();
      } catch (error) {
        // Handle error
        const errorMessage =
          error instanceof Error ? error.message : __("Failed to duplicate assignment.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
      }
    },
    [courseId, createNotice, duplicateAssignment]
  );

  /** Handle assignment deletion */
  const handleAssignmentDelete = useCallback(
    async (assignmentId: number) => {
      if (!courseId) {
        const errorMessage = __("Course ID not available to delete assignment.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
        return;
      }

      try {
        // Use the store resolver for assignment deletion (uses API_FETCH control type)
        await deleteAssignment(assignmentId);

        // Show success notice
        createNotice("success", __("Assignment deleted successfully.", "tutorpress"), {
          type: "snackbar",
        });
      } catch (error) {
        // Handle error
        const errorMessage = error instanceof Error ? error.message : __("Failed to delete assignment.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
      }
    },
    [courseId, createNotice, deleteAssignment]
  );

  return {
    // State
    assignmentDuplicationState,

    // Assignment Operations
    handleAssignmentEdit,
    handleAssignmentDuplicate,
    handleAssignmentDelete,

    // Computed
    isAssignmentDuplicating: assignmentDuplicationState.status === "duplicating",
    assignmentDuplicationError:
      assignmentDuplicationState.status === "error" ? assignmentDuplicationState.error || null : null,
  };
}
