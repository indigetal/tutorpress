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

import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { useDispatch, useSelect } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";
import { useCurriculumError } from "./useCurriculumError";
import { store as noticesStore } from "@wordpress/notices";
import { useStatePersistence } from "./useStatePersistence";
import { addAction, removeAction } from "@wordpress/hooks";

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

/**
 * Type guard for error state
 */
const isErrorState = (state: TopicOperationState): state is { status: "error"; error: CurriculumError } => {
  return state.status === "error";
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
    duplicateTopic,
    updateTopic,
    createTopic,
    fetchTopics,
  } = useDispatch(curriculumStore);

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
    fetchState,
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
      fetchState: select(curriculumStore).getFetchState(),
    }),
    []
  );

  // Track previous courseId to prevent unnecessary fetches
  const prevCourseId = useRef<number | undefined>(courseId);

  // Fetch topics when courseId changes
  useEffect(() => {
    if (courseId && courseId !== prevCourseId.current) {
      fetchTopics(courseId);
      prevCourseId.current = courseId;
    }
  }, [courseId, fetchTopics]);

  // Computed loading state
  const isLoading = operationState.status === "loading" || fetchState.isLoading;

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

  // Refresh curriculum after a successful Lesson or Assignment entity save.
  useEffect(() => {
    if (!isLesson && !isAssignment) {
      return;
    }

    const handleSavePost = (
      _post: { id: number; type: string },
      options: { isAutosave?: boolean } = {}
    ) => {
      if (options.isAutosave || !courseId) {
        return;
      }

      void fetchTopics(courseId);
    };

    addAction("editor.savePost", "tutorpress/curriculum-refresh", handleSavePost);

    return () => {
      removeAction("editor.savePost", "tutorpress/curriculum-refresh");
    };
  }, [isLesson, isAssignment, courseId, fetchTopics]);

  /** Handle topic toggle (collapse/expand) */
  const handleTopicToggle = useCallback(
    (topicId: number) => {
      setTopics((currentTopics: Topic[]) =>
        currentTopics.map((topic: Topic) =>
          topic.id === topicId ? { ...topic, isCollapsed: !topic.isCollapsed } : topic
        )
      );
    },
    [setTopics]
  );

  /** Handle starting topic addition */
  const handleAddTopicClick = useCallback(() => {
    setTopics((currentTopics: Topic[]) =>
      currentTopics.map((topic: Topic) => ({
        ...topic,
        isCollapsed: true,
      }))
    );
    setIsAddingTopic(true);
  }, [setTopics, setIsAddingTopic]);

  /** Handle topic edit start */
  const handleTopicEdit = useCallback(
    (topicId: number) => {
      if (activeOperation.type !== "none") {
        createNotice("error", "Another operation is in progress. Please wait.");
        return;
      }
      setEditState({ isEditing: true, topicId });
    },
    [activeOperation, createNotice, setEditState]
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
  }, [createNotice, createSnapshot, restoreFromSnapshot, clearSnapshot, setEditState]);

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
    [courseId, createNotice, createSnapshot, restoreFromSnapshot, clearSnapshot, setTopics, setEditState]
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

        // Create topic using store action (handles state updates internally)
        await createTopic({
          title: data.title,
          content: data.summary,
          course_id: courseId,
          // menu_order will be calculated by the backend
        });

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
    [
      courseId,
      createNotice,
      createSnapshot,
      restoreFromSnapshot,
      clearSnapshot,
      setTopics,
      setTopicCreationState,
      setIsAddingTopic,
      handleOperationSuccess,
      handleOperationError,
    ]
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

        // Set deletion state to indicate deletion is in progress
        setDeletionState({
          status: "deleting",
          topicId,
        });

        // Use the store resolver for topic deletion (uses API_FETCH control type)
        await deleteTopic(topicId, courseId);

        // Reset deletion state to idle (the resolver handles topics refresh)
        setDeletionState({ status: "idle" });

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
    [
      courseId,
      activeOperation,
      createNotice,
      handleOperationSuccess,
      handleOperationError,
      createSnapshot,
      setDeletionState,
      deleteTopic,
    ]
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

        // Use the store's duplicateTopic function (uses API_FETCH control type)
        await duplicateTopic(topicId, courseId);

        // The store function handles updating the topics list and duplication state
        // No need to manually update state here

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
    [
      courseId,
      createNotice,
      createSnapshot,
      restoreFromSnapshot,
      clearSnapshot,
      setTopics,
      setDuplicationState,
      handleOperationSuccess,
      handleOperationError,
    ]
  );

  // Computed values
  const hasError = operationState.status === "error" || fetchState.error;
  const shouldShowEmpty = !isLoading && !hasError && topics.length === 0;

  // Get error from either operation state or fetch state
  const error = (() => {
    if (isErrorState(operationState)) {
      return operationState.error;
    }
    if (fetchState.error) {
      return createOperationError(CurriculumErrorCode.FETCH_FAILED, fetchState.error.message, { type: "none" });
    }
    return null;
  })();

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
    isLoading,
    error,
  };
}
