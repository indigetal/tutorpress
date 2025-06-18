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
        return {
          currentPostStatus: null,
          currentPostId: 0,
        };
      }

      try {
        // Explicitly type the editor store selection
        const editor = select(editorStore) as unknown;

        if (!isEditorStore(editor)) {
          return {
            currentPostStatus: null,
            currentPostId: 0,
          };
        }

        // Get editor state
        const currentPost = editor.getCurrentPost();
        const currentPostId = editor.getCurrentPostId();

        return {
          currentPostStatus: currentPost?.status || null,
          currentPostId: currentPostId || 0,
        };
      } catch (error) {
        // Fallback to URL post ID if editor store fails
        const urlParams = new URLSearchParams(window.location.search);
        const postIdFromUrl = urlParams.get("post");

        return {
          currentPostStatus: null,
          currentPostId: postIdFromUrl ? parseInt(postIdFromUrl, 10) : 0,
        };
      }
    },
    [isLesson, isAssignment]
  );

  // Add refs for tracking previous state
  const prevPostIdRef = useRef(currentPostId);
  const prevPostStatusRef = useRef(currentPostStatus);

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

  // Add a useEffect to track topics changes
  useEffect(() => {
    // Topics updated
  }, [topics]);

  // Add a useEffect to track operation state changes
  useEffect(() => {
    // Operation state updated
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

  // Fetch topics on mount and when courseId changes
  useEffect(() => {
    if (courseId && courseId > 0 && operationState.status !== "loading" && (!topics || topics.length === 0)) {
      refreshTopics();
    }
  }, [courseId, operationState.status, topics, refreshTopics]);

  // Monitor post status changes for automatic refresh
  useEffect(() => {
    if ((!isLesson && !isAssignment) || !currentPostId) {
      return;
    }

    const prevPostId = prevPostIdRef.current;
    const prevStatus = prevPostStatusRef.current;

    // Check if this is an initial publish (status changed from draft/auto-draft to publish)
    const isInitialPublish =
      currentPostStatus === "publish" &&
      (prevStatus === "draft" || prevStatus === "auto-draft" || prevStatus === null) &&
      currentPostId === prevPostId;

    if (isInitialPublish && courseId) {
      // Trigger curriculum refresh
      refreshTopics();
    }

    // Update refs for next check
    prevPostIdRef.current = currentPostId;
    prevPostStatusRef.current = currentPostStatus;
  }, [isLesson, isAssignment, currentPostId, currentPostStatus, courseId, refreshTopics]);

  // Add editor.didPostSaving filter for existing lesson and assignment updates
  useEffect(() => {
    if ((!isLesson && !isAssignment) || !courseId) {
      return;
    }

    const handlePostSaving = (isComplete: boolean, options: { isAutosave?: boolean } = {}) => {
      // Only proceed if save is complete and not an autosave
      if (!isComplete || options.isAutosave) {
        return;
      }

      // Get current courseId from store state
      const currentCourseId = courseId;
      if (!currentCourseId) {
        return;
      }

      // Refresh topics
      refreshTopics();
    };

    // Add the filter
    addFilter("editor.didPostSaving", "tutorpress/curriculum-refresh", handlePostSaving);

    // Cleanup function to remove the filter
    return () => {
      removeFilter("editor.didPostSaving", "tutorpress/curriculum-refresh");
    };
  }, [isLesson, isAssignment, courseId, refreshTopics]);

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
