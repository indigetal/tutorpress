/**
 * Hook for managing topics in a course curriculum
 *
 * Handles topic state management and operations like fetching, creating,
 * editing, deleting, and reordering topics.
 */
import { useState, useCallback, useEffect } from "react";
import {
  Topic,
  TopicFormData,
  TopicEditState,
  TopicCreationState,
  TopicOperationState,
  ReorderOperationState,
  CurriculumError,
  CurriculumErrorCode,
  OperationResult,
  CurriculumSnapshot,
  isValidTopic,
} from "../../types/curriculum";
import { getTopics, duplicateTopic } from "../../api/topics";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { store as noticesStore } from "@wordpress/notices";
import { useDispatch } from "@wordpress/data";

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

// New interface for deletion state
interface TopicDeletionState {
  status: "idle" | "deleting" | "error" | "success";
  error?: CurriculumError;
  topicId?: number;
}

// New interface for duplication state
interface TopicDuplicationState {
  status: "idle" | "duplicating" | "error" | "success";
  error?: CurriculumError;
  sourceTopicId?: number;
  duplicatedTopicId?: number;
}

export interface UseTopicsOptions {
  courseId: number;
}

export interface UseTopicsReturn {
  // State
  topics: Topic[];
  operationState: TopicOperationState;
  topicCreationState: TopicCreationState;
  editState: TopicEditState;
  reorderState: ReorderOperationState;
  deletionState: TopicDeletionState;
  duplicationState: TopicDuplicationState;
  isAddingTopic: boolean;
  snapshot: CurriculumSnapshot | null;

  // State setters
  setTopics: React.Dispatch<React.SetStateAction<Topic[]>>;
  setOperationState: React.Dispatch<React.SetStateAction<TopicOperationState>>;
  setTopicCreationState: React.Dispatch<React.SetStateAction<TopicCreationState>>;
  setEditState: React.Dispatch<React.SetStateAction<TopicEditState>>;
  setReorderState: React.Dispatch<React.SetStateAction<ReorderOperationState>>;
  setIsAddingTopic: React.Dispatch<React.SetStateAction<boolean>>;

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
  // =============================
  // State
  // =============================
  const [topics, setTopics] = useState<Topic[]>([]);
  const [operationState, setOperationState] = useState<TopicOperationState>({ status: "idle" });
  const [topicCreationState, setTopicCreationState] = useState<TopicCreationState>({ status: "idle" });
  const [editState, setEditState] = useState<TopicEditState>({ isEditing: false, topicId: null });
  const [reorderState, setReorderState] = useState<ReorderOperationState>({ status: "idle" });
  const [deletionState, setDeletionState] = useState<TopicDeletionState>({ status: "idle" });
  const [duplicationState, setDuplicationState] = useState<TopicDuplicationState>({ status: "idle" });
  const [isAddingTopic, setIsAddingTopic] = useState(false);
  const [snapshot, setSnapshot] = useState<CurriculumSnapshot | null>(null);

  // WordPress notices
  const { createNotice } = useDispatch(noticesStore);

  // =============================
  // Snapshot Management
  // =============================

  /** Create a snapshot of current state */
  const createSnapshot = useCallback(
    (operation: CurriculumSnapshot["operation"]) => {
      setSnapshot({
        topics: [...topics],
        timestamp: Date.now(),
        operation,
      });
    },
    [topics]
  );

  /** Restore from snapshot */
  const restoreFromSnapshot = useCallback(() => {
    if (snapshot) {
      setTopics(snapshot.topics);
      setSnapshot(null);
      return true;
    }
    return false;
  }, [snapshot]);

  // =============================
  // Effects
  // =============================

  // Fetch topics on mount and when courseId changes
  useEffect(() => {
    const fetchTopics = async () => {
      setOperationState({ status: "loading" });
      try {
        const fetchedTopics = await getTopics(courseId);
        setTopics(fetchedTopics.map((topic) => ({ ...topic, isCollapsed: true })));
        setOperationState({ status: "success", data: fetchedTopics });
      } catch (err) {
        console.error("Error fetching topics:", err);
        setOperationState({
          status: "error",
          error: {
            code: CurriculumErrorCode.FETCH_FAILED,
            message: err instanceof Error ? err.message : __("Failed to load topics", "tutorpress"),
            context: { action: "fetch_topics" },
          },
        });
      }
    };

    fetchTopics();
  }, [courseId]);

  // =============================
  // Topic UI Operations
  // =============================

  /** Handle topic toggle (collapse/expand) */
  const handleTopicToggle = useCallback((topicId: number) => {
    setTopics((currentTopics) =>
      currentTopics.map((topic) => (topic.id === topicId ? { ...topic, isCollapsed: !topic.isCollapsed } : topic))
    );
  }, []);

  /** Handle starting topic addition */
  const handleAddTopicClick = useCallback(() => {
    // Close all topics when adding a new one
    setTopics((currentTopics) =>
      currentTopics.map((topic) => ({
        ...topic,
        isCollapsed: true,
      }))
    );
    setIsAddingTopic(true);
  }, []);

  // =============================
  // Topic Edit Operations
  // =============================

  /** Handle topic edit start */
  const handleTopicEdit = useCallback((topicId: number) => {
    setEditState({ isEditing: true, topicId });
  }, []);

  /** Handle topic edit cancel */
  const handleTopicEditCancel = useCallback(() => {
    setEditState({ isEditing: false, topicId: null });
  }, []);

  /** Handle topic edit save */
  const handleTopicEditSave = useCallback(async (topicId: number, data: TopicFormData) => {
    try {
      const response = await apiFetch<{ success: boolean; data: Topic; message?: string }>({
        path: `/tutorpress/v1/topics/${topicId}`,
        method: "PATCH",
        data: {
          title: data.title.trim(),
          content: data.summary.trim() || " ", // Ensure content is never null
        },
      });

      if (!response.success || !isValidTopic(response.data)) {
        throw {
          code: CurriculumErrorCode.CREATION_FAILED,
          message: response.message || __("Failed to update topic", "tutorpress"),
        };
      }

      // Update topic in list with response data and ensure it's collapsed
      setTopics((currentTopics) =>
        currentTopics.map((topic) =>
          topic.id === topicId ? { ...topic, title: response.data.title, content: response.data.content } : topic
        )
      );
      setEditState({ isEditing: false, topicId: null });
    } catch (err) {
      console.error("Error updating topic:", err);
      throw err;
    }
  }, []);

  // =============================
  // Topic Creation Operations
  // =============================

  /** Create a new topic */
  const createTopic = useCallback(
    async (data: TopicFormData): Promise<OperationResult<Topic>> => {
      try {
        // Validate required fields
        if (!data.title.trim()) {
          return {
            success: false,
            error: {
              code: CurriculumErrorCode.VALIDATION_ERROR,
              message: __("Topic title is required", "tutorpress"),
              context: { action: "create_topic" },
            },
          };
        }

        // Calculate the new topic's menu_order (always at the end)
        const lastTopic =
          topics.length > 0
            ? topics.reduce((max, topic) => (topic.menu_order > max.menu_order ? topic : max), topics[0])
            : null;
        const newMenuOrder = lastTopic ? lastTopic.menu_order + 1 : 0;

        const response = await apiFetch<{ success: boolean; data: Topic; message?: string }>({
          path: `/tutorpress/v1/topics`,
          method: "POST",
          data: {
            course_id: courseId,
            title: data.title.trim(),
            content: data.summary.trim() || " ", // Ensure content is never null
            menu_order: newMenuOrder,
          },
        });

        if (!response.success || !isValidTopic(response.data)) {
          // Handle specific database errors
          if (isDbError(response.data) && response.data.code === "db_insert_error") {
            return {
              success: false,
              error: {
                code: CurriculumErrorCode.SERVER_ERROR,
                message: __("Database error while creating topic. Please try again.", "tutorpress"),
                context: {
                  action: "create_topic",
                  details: response.data.message,
                },
              },
            };
          }

          return {
            success: false,
            error: {
              code: CurriculumErrorCode.CREATION_FAILED,
              message: response.message || __("Failed to create topic", "tutorpress"),
              context: { action: "create_topic" },
            },
          };
        }

        return { success: true, data: response.data };
      } catch (error: unknown) {
        console.error("Error creating topic:", error);

        // Handle known error types
        if (error && typeof error === "object" && "code" in error && "message" in error) {
          return {
            success: false,
            error: {
              code: (error as { code: CurriculumErrorCode }).code,
              message: (error as { message: string }).message,
              context: { action: "create_topic" },
            },
          };
        }

        // Default error for unknown types
        return {
          success: false,
          error: {
            code: CurriculumErrorCode.CREATION_FAILED,
            message: error instanceof Error ? error.message : __("Failed to create topic", "tutorpress"),
            context: { action: "create_topic" },
          },
        };
      }
    },
    [courseId, topics]
  );

  /** Handle topic form save */
  const handleTopicFormSave = useCallback(
    async (data: TopicFormData) => {
      setTopicCreationState({ status: "creating" });

      const result = await createTopic(data);

      if (result.success && result.data) {
        // Ensure we're working with a valid Topic
        const newTopic: Topic = result.data;
        setTopics((currentTopics) => [...currentTopics, newTopic]);
        setTopicCreationState({ status: "success", data: newTopic });
        setIsAddingTopic(false); // Close the form after successful creation
      } else {
        setTopicCreationState({ status: "error", error: result.error! });
        // Keep form open to allow retry
      }
    },
    [createTopic]
  );

  /** Handle topic form cancel */
  const handleTopicFormCancel = useCallback(() => {
    setTopicCreationState({ status: "idle" });
    setIsAddingTopic(false); // Close the form when cancelled
  }, []);

  // =============================
  // Topic Deletion Operations
  // =============================

  /** Handle topic deletion */
  const handleTopicDelete = useCallback(
    async (topicId: number) => {
      if (!window.confirm(__("Are you sure you want to delete this topic?", "tutorpress"))) {
        return;
      }

      setDeletionState({ status: "deleting", topicId });

      try {
        // Create snapshot before updating state
        createSnapshot("delete");

        // Optimistically update UI
        setTopics((currentTopics) => currentTopics.filter((t) => t.id !== topicId));

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

        // Clear snapshot and set success state
        setSnapshot(null);
        setDeletionState({ status: "success" });
      } catch (err) {
        console.error("Error deleting topic:", err);

        // Restore previous state
        restoreFromSnapshot();

        // Set error state
        const error: CurriculumError = {
          code: CurriculumErrorCode.SERVER_ERROR,
          message: err instanceof Error ? err.message : __("Failed to delete topic", "tutorpress"),
          context: { action: "delete_topic" },
        };

        setDeletionState({
          status: "error",
          error,
          topicId,
        });
      }
    },
    [createSnapshot, restoreFromSnapshot]
  );

  // =============================
  // Topic Duplication Operations
  // =============================

  /** Handle topic duplication */
  const handleTopicDuplicate = useCallback(
    async (topicId: number) => {
      setDuplicationState({ status: "duplicating", sourceTopicId: topicId });

      try {
        // Create snapshot before duplication
        createSnapshot("duplicate");

        const duplicatedTopic = await duplicateTopic(courseId, topicId);

        // Validate the response
        if (!duplicatedTopic || !isValidTopic(duplicatedTopic)) {
          throw new Error(__("Invalid response from server", "tutorpress"));
        }

        // Add the duplicated topic to the end of the list
        setTopics((prevTopics) => [...prevTopics, { ...duplicatedTopic, isCollapsed: true }]);

        // Update state on success
        setDuplicationState({
          status: "success",
          sourceTopicId: topicId,
          duplicatedTopicId: duplicatedTopic.id,
        });

        // Show success notice
        createNotice("success", __("Topic duplicated successfully", "tutorpress"), { type: "snackbar" });
      } catch (error) {
        console.error("Error duplicating topic:", error);

        // Restore previous state
        restoreFromSnapshot();

        // Set error state
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

        // Show error notice
        createNotice("error", errorMessage, { type: "snackbar" });
      }
    },
    [courseId, createSnapshot, restoreFromSnapshot, createNotice]
  );

  // =============================
  // Return Values
  // =============================
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
    snapshot,

    // State setters
    setTopics,
    setOperationState,
    setTopicCreationState,
    setEditState,
    setReorderState,
    setIsAddingTopic,

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
