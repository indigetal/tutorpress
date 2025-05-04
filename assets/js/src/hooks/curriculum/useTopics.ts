/**
 * Hook for managing topics in a course curriculum
 *
 * Orchestrates topic operations and coordinates with the curriculum store.
 * The store handles all state management, while this hook provides the
 * operations interface for components.
 */
import { useCallback, useEffect } from "react";
import {
  Topic,
  TopicOperationState,
  TopicEditState,
  TopicCreationState,
  TopicReorderState,
  TopicDeletionState,
  TopicDuplicationState,
  CurriculumError,
  TopicOperationResult,
  TopicActiveOperation,
  isValidTopic,
  TopicFormData,
  CurriculumErrorCode,
} from "../../types/curriculum";
import type { CurriculumSnapshot } from "./useSnapshot";
import { useSnapshot } from "./useSnapshot";
import { getTopics, duplicateTopic, updateTopic, createTopic } from "../../api/topics";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { useDispatch, useSelect } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";
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
} from "../../store/curriculum";
import { store as noticesStore } from "@wordpress/notices";

// Type guard for database error response
interface DbError {
  code: string;
  message: string;
}

const isDbError = (data: unknown): data is DbError => {
  return (
    typeof data === "object" &&
    data !== null &&
    "code" in data &&
    typeof data.code === "string" &&
    "message" in data &&
    typeof data.message === "string"
  );
};

export interface UseTopicsOptions {
  courseId: number;
}

export interface UseTopicsReturn {
  // State from store
  topics: Topic[];
  operationState: TopicOperationState;
  topicCreationState: TopicCreationState;
  editState: TopicEditState;
  reorderState: TopicReorderState;
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
export function useTopics({ courseId }: UseTopicsOptions): UseTopicsReturn {
  const { createNotice } = useDispatch(noticesStore);
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
  } = useDispatch(curriculumStore);

  // Use the error handling hook
  const { validateApiResponse, createCurriculumError, handleRetry } = useCurriculumError({
    reorderState,
    deletionState,
    duplicationState,
    topics,
    handleReorderTopics: async () => ({ success: true }), // Placeholder
    handleTopicDelete: async () => {}, // Placeholder
    handleTopicDuplicate: async () => {}, // Placeholder
  });

  // Use the snapshot hook
  const { snapshot, createSnapshot, restoreFromSnapshot, clearSnapshot } = useSnapshot({
    topics,
    setTopics,
  });

  // Fetch topics on mount and when courseId changes
  useEffect(() => {
    const fetchTopicsData = async () => {
      try {
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
        const error = createCurriculumError(err, { action: "fetch_topics" });

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
    };

    // Only fetch if we're not already loading and we don't have topics
    if (operationState.status !== "loading" && (!topics || topics.length === 0)) {
      setOperationState({ status: "loading" });
      fetchTopicsData();
    }
  }, [courseId, topics?.length, operationState.status]);

  // Add a useEffect to track state changes
  useEffect(() => {
    console.log("Hook: Topics updated:", topics);
  }, [topics]);

  // Add a useEffect to track operation state changes
  useEffect(() => {
    console.log("Hook: Operation state updated:", operationState);
  }, [operationState]);

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
  const handleTopicFormSave = async (data: TopicFormData) => {
    try {
      // Create topic using store action
      const newTopic = await createTopic({
        title: data.title,
        content: data.summary,
        course_id: courseId,
        menu_order: topics.length, // Add to end of list
      });

      // Update local state with new topic
      setTopics((currentTopics) => [...currentTopics, newTopic]);

      // Reset form state
      setTopicCreationState({ status: "idle" });
      setIsAddingTopic(false);

      // Show success notice
      createNotice("success", __("Topic created successfully", "tutorpress"), {
        type: "snackbar",
      });
    } catch (error) {
      // Error is already handled by the store
      console.error("Failed to create topic:", error);

      // Show error notice
      createNotice("error", __("Failed to create topic", "tutorpress"), {
        type: "snackbar",
      });
    }
  };

  /** Handle topic form cancel */
  const handleTopicFormCancel = () => {
    setTopicCreationState({ status: "idle" });
    setIsAddingTopic(false);
  };

  /** Handle topic deletion */
  const handleTopicDelete = useCallback(
    async (topicId: number) => {
      if (activeOperation.type !== "none") {
        createNotice("error", "Another operation is in progress. Please wait.");
        return;
      }
      setDeletionState({ status: "deleting", topicId });

      try {
        const response = await apiFetch<{ success: boolean; message?: string }>({
          path: `/tutorpress/v1/topics/${topicId}`,
          method: "DELETE",
        });

        if (!response.success) {
          throw {
            code: CurriculumErrorCode.SERVER_ERROR,
            message: response.message || __("Failed to delete topic", "tutorpress"),
          };
        }

        setTopics((currentTopics) => currentTopics.filter((topic) => topic.id !== topicId));
        clearSnapshot();
        setDeletionState({ status: "success" });
        setActiveOperation({ type: "none" });
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
        setActiveOperation({ type: "none" });
        createNotice("error", error instanceof Error ? error.message : __("Failed to delete topic", "tutorpress"), {
          type: "snackbar",
        });
      }
    },
    [clearSnapshot, activeOperation, createNotice]
  );

  /** Handle topic duplication */
  const handleTopicDuplicate = useCallback(
    async (topicId: number) => {
      if (activeOperation.type !== "none") {
        createNotice("error", "Another operation is in progress. Please wait.");
        return;
      }
      setDuplicationState({ status: "duplicating", sourceTopicId: topicId });

      try {
        const duplicatedTopic = await duplicateTopic(topicId, courseId);

        if (!duplicatedTopic) {
          throw {
            code: CurriculumErrorCode.SERVER_ERROR,
            message: __("Failed to duplicate topic", "tutorpress"),
          };
        }

        setTopics((prevTopics) => [...prevTopics, { ...duplicatedTopic, isCollapsed: true }]);
        setDuplicationState({
          status: "success",
          sourceTopicId: topicId,
          duplicatedTopicId: duplicatedTopic.id,
        });
        setActiveOperation({ type: "none" });
        createNotice("success", __("Topic duplicated successfully", "tutorpress"), {
          type: "snackbar",
        });
      } catch (error) {
        console.error("Error duplicating topic:", error);

        const errorMessage = error instanceof Error ? error.message : __("Failed to duplicate topic", "tutorpress");

        setDuplicationState({
          status: "error",
          sourceTopicId: topicId,
          error: {
            code: CurriculumErrorCode.SERVER_ERROR,
            message: errorMessage,
            context: { action: "duplicate_topic" },
          },
        });
        setActiveOperation({ type: "none" });
        createNotice("error", errorMessage, { type: "snackbar" });
      }
    },
    [courseId, createNotice, activeOperation]
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
