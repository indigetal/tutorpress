/**
 * Hook for managing topics in a course curriculum
 *
 * Orchestrates topic operations and coordinates with the curriculum store.
 * The store handles all state management, while this hook provides the
 * operations interface for components.
 */
import { useCallback, useEffect, useRef } from "react";
import {
  Topic,
  TopicOperationState,
  TopicEditState,
  TopicCreationState,
  ReorderOperationState,
  TopicDeletionState,
  TopicDuplicationState,
  CurriculumError,
  OperationResult,
  TopicActiveOperation,
  isValidTopic,
  TopicFormData,
  CurriculumErrorCode,
  createOperationError,
} from "../../types/curriculum";
import type { CurriculumSnapshot } from "./useSnapshot";
import { useSnapshot } from "./useSnapshot";
import { getTopics, duplicateTopic, updateTopic, createTopic } from "../../api/topics";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { useDispatch, useSelect } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";
import { store as editorStore } from "@wordpress/editor";
import { useCurriculumError } from "./useCurriculumError";
import {
  setTopics,
  setOperationState,
  setEditState,
  setTopicCreationState,
  setDeletionState,
  setDuplicationState,
  setReorderState,
  setIsAddingTopic,
  setActiveOperation,
  deleteTopic,
  refreshTopicsAfterLessonSave,
  refreshTopicsAfterAssignmentSave,
} from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";
import { useStatePersistence } from "./useStatePersistence";
import { addFilter, removeFilter } from "@wordpress/hooks";
import { CoreEditorSelectors, isCoreEditorSelectors } from "../../types/wordpress";

// ============================================================================
// Error Handling Types
// ============================================================================

/**
 * Database error interface
 */
interface DbError {
  code: string;
  message: string;
}

/**
 * Type guard for database errors
 */
const isDbError = (data: unknown): data is DbError => {
  return typeof data === "object" && data !== null && "code" in data && "message" in data;
};

// Editor store types
interface EditorSelectors {
  isAutosavingPost: () => boolean;
  isPublishingPost: () => boolean;
  isSavingPost: () => boolean;
  getCurrentPostId: () => number;
  getCurrentPost: () => { status: string } | null;
  getEditedPostAttribute: (attr: string) => any;
}

// Type guard for editor store
const isEditorStore = (editor: unknown): editor is EditorSelectors => {
  if (!editor || typeof editor !== "object") return false;

  const e = editor as Record<string, unknown>;
  return (
    typeof e.isAutosavingPost === "function" &&
    typeof e.isPublishingPost === "function" &&
    typeof e.isSavingPost === "function" &&
    typeof e.getCurrentPostId === "function" &&
    typeof e.getCurrentPost === "function" &&
    typeof e.getEditedPostAttribute === "function"
  );
};

export interface UseTopicsOptions {
  courseId?: number;
  isLesson?: boolean;
  isAssignment?: boolean;
}

export interface UseTopicsReturn {
  // State from store
  topics: Topic[];
  operationState: TopicOperationState;
  topicCreationState: TopicCreationState;
  editState: TopicEditState;
  reorderState: ReorderOperationState;
  deletionState: TopicDeletionState;
  duplicationState: TopicDuplicationState;
  isAddingTopic: boolean;
  snapshot: CurriculumSnapshot | null;
  activeOperation: TopicActiveOperation;

  // Topic UI Operations
  handleTopicToggle: (topicId: number) => void;
  handleAddTopicClick: () => void;

  // Topic Edit Operations
  handleTopicEdit: (topicId: number) => void;
  handleTopicEditCancel: () => void;
  handleTopicEditSave: (topicId: number, data: TopicFormData) => Promise<void>;

  // Topic Creation Operations
  handleTopicFormSave: (data: TopicFormData) => Promise<void>;
  handleTopicFormCancel: () => void;

  // Topic Deletion Operations
  handleTopicDelete: (topicId: number) => Promise<void>;

  // Topic Duplication Operations
  handleTopicDuplicate: (topicId: number) => Promise<void>;

  // Snapshot Operations
  createSnapshot: (operation: CurriculumSnapshot["operation"]) => void;
  restoreFromSnapshot: () => boolean;

  // Computed
  isLoading: boolean;
  error: CurriculumError | null;
}

/**
 * Hook for managing topics in a course curriculum
 *
 * @param options Configuration options for the hook
 * @returns Topic state and operations
 */
export function useTopics({ courseId, isLesson = false, isAssignment = false }: UseTopicsOptions): UseTopicsReturn {
  const { createNotice } = useDispatch(noticesStore);
  const { dispatch } = useDispatch("tutorpress/curriculum");

  // Add debug logging for initialization
  if (process.env.NODE_ENV === "development") {
    console.log("useTopics hook executed with:", { courseId, isLesson, isAssignment });
  }

  const {
    topics,
    operationState,
    editState,
    topicCreationState,
    deletionState,
    duplicationState,
    reorderState,
    isAddingTopic,
    activeOperation,
  } = useSelect(
    (select) => ({
      topics: select(curriculumStore).getTopics(),
      operationState: select(curriculumStore).getOperationState(),
      editState: select(curriculumStore).getEditState(),
      topicCreationState: select(curriculumStore).getTopicCreationState(),
      deletionState: select(curriculumStore).getDeletionState(),
      duplicationState: select(curriculumStore).getDuplicationState(),
      reorderState: select(curriculumStore).getReorderState(),
      isAddingTopic: select(curriculumStore).getIsAddingTopic(),
      activeOperation: select(curriculumStore).getActiveOperation(),
    }),
    []
  );

  // Add editor store selectors
  const { currentPostStatus, currentPostId } = useSelect(
    (select) => {
      if (!isLesson && !isAssignment) {
        if (process.env.NODE_ENV === "development") {
          console.log("useSelect (editor store): Not in lesson/assignment context.");
        }
        return {
          currentPostStatus: null,
          currentPostId: 0,
        };
      }

      try {
        // Explicitly type the editor store selection
        const editor = select(editorStore) as unknown;
        if (process.env.NODE_ENV === "development") {
          console.log("useSelect (editor store): Attempting to select editor store.", editor);
        }

        if (isCoreEditorSelectors(editor)) {
          const post = editor.getCurrentPost();
          const postId = editor.getCurrentPostId();
          if (process.env.NODE_ENV === "development") {
            console.log("useSelect (editor store): Got editor state.", {
              postId,
              postStatus: post?.status,
              isLesson,
              isAssignment,
            });
          }
          return {
            currentPostStatus: post?.status || null,
            currentPostId: postId || 0,
          };
        } else {
          if (process.env.NODE_ENV === "development") {
            console.warn("useSelect (editor store): Editor store not in expected state.");
          }
        }
      } catch (e) {
        if (process.env.NODE_ENV === "development") {
          console.warn("useSelect (editor store): Error accessing editor store:", e);
        }
      }

      // Fallback to URL params if editor store not available
      const urlParams = new URLSearchParams(window.location.search);
      const postIdFromUrl = parseInt(urlParams.get("post") || "0", 10);

      if (process.env.NODE_ENV === "development") {
        console.log("useSelect (editor store): Falling back to URL post ID:", postIdFromUrl);
      }

      return {
        currentPostStatus: null,
        currentPostId: postIdFromUrl,
      };
    },
    [isLesson, isAssignment]
  );

  // Add refs for tracking previous state
  const prevPostId = useRef(currentPostId);
  const prevPostStatus = useRef(currentPostStatus);

  const {
    setTopics,
    setOperationState,
    setEditState,
    setTopicCreationState,
    setDeletionState,
    setDuplicationState,
    setReorderState,
    setIsAddingTopic,
    setActiveOperation,
    deleteTopic,
    refreshTopicsAfterLessonSave,
    refreshTopicsAfterAssignmentSave,
  } = useDispatch(curriculumStore);

  // Use the error handling hook
  const { validateApiResponse, createCurriculumError, handleRetry } = useCurriculumError({
    reorderState,
    deletionState,
    duplicationState,
    topics,
    handleReorderTopics: async (orderedTopics: Topic[]): Promise<OperationResult<void>> => {
      if (!courseId) {
        const error = createOperationError(
          CurriculumErrorCode.VALIDATION_ERROR,
          __("Course ID not available for reordering.", "tutorpress"),
          { type: "reorder" }
        );
        throw error;
      }
      // Return the expected OperationResult<void> type
      return { success: true, data: undefined };
    },
    handleTopicDelete: async () => {}, // Placeholder
    handleTopicDuplicate: async () => {}, // Placeholder
  });

  // Use the snapshot hook
  const { snapshot, createSnapshot, restoreFromSnapshot, clearSnapshot } = useSnapshot({
    topics,
    setTopics,
  });

  // Use state persistence hook at the top level
  useStatePersistence(courseId ?? 0, topics, setTopics);

  // Helper for operation success cleanup
  const handleOperationSuccess = useCallback(() => {
    clearSnapshot();
    setActiveOperation({ type: "none" });
  }, [clearSnapshot, setActiveOperation]);

  // Helper for operation error cleanup
  const handleOperationError = useCallback(
    (error: Error) => {
      if (snapshot) {
        restoreFromSnapshot();
      }
      clearSnapshot();
      setActiveOperation({ type: "none" });
      return error;
    },
    [snapshot, restoreFromSnapshot, clearSnapshot, setActiveOperation]
  );

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      clearSnapshot();
      setActiveOperation({ type: "none" });
    };
  }, [clearSnapshot, setActiveOperation]);

  // Fetch topics on mount and when courseId changes
  useEffect(() => {
    if (courseId && courseId > 0 && operationState.status !== "loading" && (!topics || topics.length === 0)) {
      if (process.env.NODE_ENV === "development") {
        console.log("Initial fetch: Using store's refreshTopicsAfterLessonSave for courseId:", courseId);
      }

      // Use the store's refreshTopicsAfterLessonSave action
      refreshTopicsAfterLessonSave({ courseId });
    }
  }, [courseId, operationState.status, topics, refreshTopicsAfterLessonSave]);

  // Add a useEffect to track state changes
  useEffect(() => {
    console.log("Hook: Topics updated:", topics);
  }, [topics]);

  // Add a useEffect to track operation state changes
  useEffect(() => {
    console.log("Hook: Operation state updated:", operationState);
  }, [operationState]);

  // Add refreshTopics function
  const refreshTopics = useCallback(async () => {
    if (!courseId) {
      console.warn("Cannot refresh topics: courseId is not available");
      return;
    }

    try {
      setOperationState({ status: "loading" });
      const response = await apiFetch({
        path: `/tutorpress/v1/topics?course_id=${courseId}`,
      });

      // Validate and transform the response
      const topicsWithCollapsed = validateApiResponse(response);

      // Update topics and operation state atomically
      setTopics(topicsWithCollapsed);
      setOperationState({
        status: "success",
        data: topicsWithCollapsed,
      });
    } catch (err) {
      const error = createCurriculumError(err, { action: "refresh_topics" });
      console.error("Error refreshing topics:", error);

      // Show error notice
      createNotice("error", error.message, {
        type: "snackbar",
        isDismissible: true,
      });

      // Update operation state to error
      setOperationState({
        status: "error",
        error,
      });
    }
  }, [courseId, setTopics, setOperationState, createNotice, validateApiResponse, createCurriculumError]);

  // Add effect for monitoring post status changes
  useEffect(() => {
    if ((!isLesson && !isAssignment) || !currentPostId) {
      if (process.env.NODE_ENV === "development") {
        console.log("Post status monitoring: Not in lesson/assignment context or no post ID");
      }
      return;
    }

    if (process.env.NODE_ENV === "development") {
      console.log("Post status monitoring: Current state", {
        currentPostId,
        currentPostStatus,
        prevPostId: prevPostId.current,
        prevPostStatus: prevPostStatus.current,
        isLesson,
        isAssignment,
      });
    }

    // Check if this is a new publish (status change to 'publish')
    if (
      currentPostStatus === "publish" &&
      prevPostStatus.current !== "publish" &&
      currentPostId > 0 &&
      (!prevPostId.current || prevPostId.current === currentPostId)
    ) {
      if (process.env.NODE_ENV === "development") {
        console.log("Post status monitoring: Detected initial publish", {
          postId: currentPostId,
          prevStatus: prevPostStatus.current,
          currentStatus: currentPostStatus,
          isLesson,
          isAssignment,
        });
      }

      // Trigger curriculum refresh using store action
      if (courseId) {
        if (isLesson) {
          refreshTopicsAfterLessonSave({ courseId });
        } else if (isAssignment) {
          refreshTopicsAfterAssignmentSave({ courseId });
        }
      } else {
        console.warn("Cannot refresh topics: courseId is not available");
      }
    }

    // Update refs for next check
    prevPostId.current = currentPostId;
    prevPostStatus.current = currentPostStatus;
  }, [
    isLesson,
    isAssignment,
    currentPostId,
    currentPostStatus,
    courseId,
    refreshTopicsAfterLessonSave,
    refreshTopicsAfterAssignmentSave,
  ]);

  // Add effect for debug logging state changes
  useEffect(() => {
    if (process.env.NODE_ENV === "development") {
      console.log("useTopics state update:", {
        isLesson,
        isAssignment,
        currentPostId,
        currentPostStatus,
        prevPostId: prevPostId.current,
        prevPostStatus: prevPostStatus.current,
        courseId,
      });
    }
  }, [isLesson, isAssignment, currentPostId, currentPostStatus, courseId]);

  // Add editor.didPostSaving filter for existing lesson and assignment updates
  useEffect(() => {
    if ((!isLesson && !isAssignment) || !courseId) {
      if (process.env.NODE_ENV === "development") {
        console.log("editor.didPostSaving filter: Not setting up - not in lesson/assignment context or no courseId");
      }
      return;
    }

    if (process.env.NODE_ENV === "development") {
      console.log("editor.didPostSaving filter: Setting up filter for lesson/assignment context", {
        courseId,
        isLesson,
        isAssignment,
      });
    }

    const handlePostSaving = (isComplete: boolean, options: { isAutosave?: boolean } = {}) => {
      if (process.env.NODE_ENV === "development") {
        console.log("editor.didPostSaving filter: Filter triggered", {
          isComplete,
          isAutosave: options.isAutosave,
          courseId,
          isLesson,
          isAssignment,
        });
      }

      // Only proceed if save is complete and not an autosave
      if (!isComplete || options.isAutosave) {
        if (process.env.NODE_ENV === "development") {
          console.log("editor.didPostSaving filter: Skipping - not complete or is autosave");
        }
        return;
      }

      // Get current courseId from store state
      const currentCourseId = courseId;
      if (!currentCourseId) {
        if (process.env.NODE_ENV === "development") {
          console.warn("editor.didPostSaving filter: No courseId available for refresh");
        }
        return;
      }

      if (process.env.NODE_ENV === "development") {
        console.log("editor.didPostSaving filter: Dispatching refresh action", {
          courseId: currentCourseId,
          isLesson,
          isAssignment,
        });
      }

      // Dispatch the appropriate refresh action
      if (isLesson) {
        refreshTopicsAfterLessonSave({ courseId: currentCourseId });
      } else if (isAssignment) {
        refreshTopicsAfterAssignmentSave({ courseId: currentCourseId });
      }
    };

    // Add the filter
    addFilter("editor.didPostSaving", "tutorpress/curriculum-refresh", handlePostSaving);

    if (process.env.NODE_ENV === "development") {
      console.log("editor.didPostSaving filter: Filter registered successfully");
    }

    // Cleanup function to remove the filter
    return () => {
      if (process.env.NODE_ENV === "development") {
        console.log("editor.didPostSaving filter: Removing filter");
      }
      removeFilter("editor.didPostSaving", "tutorpress/curriculum-refresh");
    };
  }, [isLesson, isAssignment, courseId, refreshTopicsAfterLessonSave, refreshTopicsAfterAssignmentSave]);

  /** Handle topic toggle (collapse/expand) */
  const handleTopicToggle = useCallback((topicId: number) => {
    setTopics((currentTopics: Topic[]) =>
      currentTopics.map((topic: Topic) =>
        topic.id === topicId ? { ...topic, isCollapsed: !topic.isCollapsed } : topic
      )
    );
  }, []);

  /** Handle starting topic addition */
  const handleAddTopicClick = useCallback(() => {
    setTopics((currentTopics: Topic[]) =>
      currentTopics.map((topic: Topic) => ({
        ...topic,
        isCollapsed: true,
      }))
    );
    setIsAddingTopic(true);
  }, []);

  /** Handle topic edit start */
  const handleTopicEdit = useCallback(
    (topicId: number) => {
      if (activeOperation.type !== "none") {
        createNotice("error", "Another operation is in progress. Please wait.");
        return;
      }
      setEditState({ isEditing: true, topicId });
    },
    [activeOperation, createNotice]
  );

  /** Handle topic edit cancel */
  const handleTopicEditCancel = useCallback(() => {
    // Create snapshot before canceling
    createSnapshot("edit");

    try {
      // Reset edit state
      setEditState({ isEditing: false, topicId: null });

      // Restore from snapshot to ensure clean state
      restoreFromSnapshot();
    } catch (error) {
      console.error("Error canceling topic edit:", error);

      // Show error notice
      createNotice("error", __("Failed to cancel topic edit", "tutorpress"), {
        type: "snackbar",
      });
    } finally {
      // Clear snapshot
      clearSnapshot();
    }
  }, [createNotice, createSnapshot, restoreFromSnapshot, clearSnapshot]);

  /** Handle topic edit save */
  const handleTopicEditSave = useCallback(
    async (topicId: number, data: TopicFormData) => {
      // Validate input
      if (!data.title.trim()) {
        createNotice("error", __("Topic title cannot be empty", "tutorpress"), {
          type: "snackbar",
        });
        return;
      }

      if (!courseId) {
        const error = createOperationError(
          CurriculumErrorCode.VALIDATION_ERROR,
          __("Course ID not available to update topic.", "tutorpress"),
          { type: "edit", topicId }
        );
        throw error;
      }

      // Create snapshot before edit
      createSnapshot("edit");

      try {
        // Update topic using store action
        await updateTopic(topicId, {
          title: data.title.trim(),
          content: data.summary.trim() || " ", // Ensure content is never null
          course_id: courseId,
        });

        // Update local state atomically
        setTopics((currentTopics) =>
          currentTopics.map((topic) => {
            if (topic.id === topicId) {
              return {
                ...topic,
                title: data.title.trim(),
                content: data.summary.trim() || " ",
              };
            }
            return topic;
          })
        );

        // Reset edit state
        setEditState({ isEditing: false, topicId: null });

        // Show success notice
        createNotice("success", __("Topic updated successfully", "tutorpress"), {
          type: "snackbar",
        });
      } catch (error) {
        console.error("Error updating topic:", error);

        // Restore from snapshot on error
        restoreFromSnapshot();

        // Show error notice
        createNotice("error", error instanceof Error ? error.message : __("Failed to update topic", "tutorpress"), {
          type: "snackbar",
        });

        // Reset edit state
        setEditState({ isEditing: false, topicId: null });

        throw error;
      } finally {
        // Clear snapshot
        clearSnapshot();
      }
    },
    [courseId, createNotice, createSnapshot, restoreFromSnapshot, clearSnapshot]
  );

  /** Handle topic form save */
  const handleTopicFormSave = useCallback(
    async (data: TopicFormData) => {
      if (!courseId) {
        const error = createOperationError(
          CurriculumErrorCode.VALIDATION_ERROR,
          __("Course ID not available to create topic.", "tutorpress"),
          { type: "create" }
        );
        throw error;
      }

      try {
        createSnapshot("edit");

        // Create topic using store action
        const newTopic = await createTopic({
          title: data.title,
          content: data.summary,
          course_id: courseId,
          // menu_order will be calculated by the backend
        });

        // Update local state with new topic
        setTopics((currentTopics) => [...currentTopics, newTopic]);

        // Reset form state
        setTopicCreationState({ status: "idle" });
        setIsAddingTopic(false);

        // Cleanup and show success notice
        handleOperationSuccess();
        createNotice("success", __("Topic created successfully", "tutorpress"), {
          type: "snackbar",
        });
      } catch (error) {
        // Handle error and cleanup
        handleOperationError(error as Error);
        createNotice("error", __("Failed to create topic", "tutorpress"), {
          type: "snackbar",
        });
      }
    },
    [courseId, createNotice, createSnapshot, restoreFromSnapshot, clearSnapshot]
  );

  /** Handle topic form cancel */
  const handleTopicFormCancel = () => {
    setTopicCreationState({ status: "idle" });
    setIsAddingTopic(false);
  };

  /** Handle topic deletion */
  const handleTopicDelete = useCallback(
    async (topicId: number) => {
      if (!courseId) {
        const errorMessage = __("Course ID not available to delete topic.", "tutorpress");
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
        return;
      }

      if (activeOperation.type !== "none") {
        createNotice("error", "Another operation is in progress. Please wait.");
        return;
      }

      try {
        createSnapshot("delete");

        // Use the store resolver for topic deletion (uses API_FETCH control type)
        await deleteTopic(topicId, courseId);

        // Cleanup and show success notice
        handleOperationSuccess();
        createNotice("success", __("Topic deleted successfully", "tutorpress"), {
          type: "snackbar",
        });
      } catch (err) {
        console.error("Error deleting topic:", err);

        const error: CurriculumError = {
          code: CurriculumErrorCode.SERVER_ERROR,
          message: err instanceof Error ? err.message : __("Failed to delete topic", "tutorpress"),
          context: {
            action: "delete_topic",
            topicId,
          },
        };

        setDeletionState({
          status: "error",
          error,
          topicId,
        });

        // Handle error and cleanup
        handleOperationError(new Error(error.message));
        createNotice("error", error.message, {
          type: "snackbar",
        });
      }
    },
    [courseId, activeOperation, createNotice, handleOperationSuccess, handleOperationError, createSnapshot, deleteTopic]
  );

  /** Handle topic duplication */
  const handleTopicDuplicate = useCallback(
    async (topicId: number) => {
      if (!courseId) {
        const error = createOperationError(
          CurriculumErrorCode.VALIDATION_ERROR,
          __("Course ID not available to duplicate topic.", "tutorpress"),
          { type: "duplicate", topicId }
        );
        throw error;
      }

      try {
        // Create snapshot before duplication
        createSnapshot("duplicate");

        // Set duplication state to loading
        setDuplicationState({
          status: "duplicating",
          sourceTopicId: topicId,
        });

        // Duplicate topic
        const duplicatedTopic = await duplicateTopic(topicId, courseId);

        // Update state with the new topic
        setTopics((currentTopics) => [...currentTopics, duplicatedTopic]);
        setDuplicationState({
          status: "success",
          sourceTopicId: topicId,
          duplicatedTopicId: duplicatedTopic.id,
        });

        // Cleanup and show success notice
        handleOperationSuccess();
        createNotice("success", __("Topic duplicated successfully.", "tutorpress"), {
          type: "snackbar",
        });
      } catch (error) {
        // Handle error
        const errorMessage = error instanceof Error ? error.message : __("Failed to duplicate topic.", "tutorpress");
        setDuplicationState({
          status: "error",
          error: createOperationError(
            CurriculumErrorCode.DUPLICATE_FAILED,
            errorMessage,
            { type: "duplicate", topicId },
            {
              action: "duplicateTopic",
              topicId,
            }
          ),
          sourceTopicId: topicId,
        });

        // Handle error and cleanup
        handleOperationError(error instanceof Error ? error : new Error(errorMessage));
        createNotice("error", errorMessage, {
          type: "snackbar",
        });
      }
    },
    [courseId, createNotice, createSnapshot, restoreFromSnapshot, clearSnapshot]
  );

  return {
    // State
    topics,
    operationState,
    topicCreationState,
    editState,
    reorderState,
    deletionState,
    duplicationState,
    isAddingTopic,
    snapshot: null,
    activeOperation: activeOperation as TopicActiveOperation,

    // Topic UI Operations
    handleTopicToggle,
    handleAddTopicClick,

    // Topic Edit Operations
    handleTopicEdit,
    handleTopicEditCancel,
    handleTopicEditSave,

    // Topic Creation Operations
    handleTopicFormSave,
    handleTopicFormCancel,

    // Topic Deletion Operations
    handleTopicDelete,

    // Topic Duplication Operations
    handleTopicDuplicate,

    // Snapshot Operations
    createSnapshot,
    restoreFromSnapshot,

    // Computed
    isLoading: operationState.status === "loading",
    error: operationState.status === "error" ? operationState.error : null,
  };
}
