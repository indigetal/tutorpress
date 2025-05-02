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
  TopicFormData,
  TopicEditState,
  TopicCreationState,
  TopicOperationState,
  ReorderOperationState,
  CurriculumError,
  CurriculumErrorCode,
  OperationResult,
  isValidTopic,
} from "../../types/curriculum";
import type { CurriculumSnapshot } from "./useSnapshot";
import { useSnapshot } from "./useSnapshot";
import { getTopics, duplicateTopic } from "../../api/topics";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import { store as noticesStore } from "@wordpress/notices";
import { useDispatch, useSelect } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";
import { useCurriculumError } from "./useCurriculumError";

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
  // Get all state in a single useSelect call
  const {
    topics,
    operationState,
    editState,
    topicCreationState,
    reorderState,
    deletionState,
    duplicationState,
    isAddingTopic,
  } = useSelect(
    (select) => {
      const store = select(curriculumStore);
      return {
        topics: store.getTopics(),
        operationState: store.getOperationState(),
        editState: store.getEditState(),
        topicCreationState: store.getTopicCreationState(),
        reorderState: store.getReorderState(),
        deletionState: store.getDeletionState(),
        duplicationState: store.getDuplicationState(),
        isAddingTopic: store.getIsAddingTopic(),
      };
    },
    [] // Empty dependency array since we're using the store directly
  );

  // Get store actions
  const {
    setTopics,
    setOperationState,
    setEditState,
    setTopicCreationState,
    setReorderState,
    setDeletionState,
    setDuplicationState,
    setIsAddingTopic,
    fetchTopics,
  } = useDispatch(curriculumStore);

  // WordPress notices
  const { createNotice } = useDispatch(noticesStore);

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
          code: CurriculumErrorCode.SERVER_ERROR,
          message: response.message || __("Failed to update topic", "tutorpress"),
        };
      }

      setTopics((currentTopics) =>
        currentTopics.map((topic) => {
          if (topic.id === topicId) {
            return {
              id: response.data.id,
              title: response.data.title,
              content: response.data.content,
              isCollapsed: topic.isCollapsed,
              menu_order: topic.menu_order ?? 0,
              contents: topic.contents ?? [],
            } as Topic;
          }
          return topic;
        })
      );
      setEditState({ isEditing: false, topicId: null });
    } catch (err) {
      console.error("Error updating topic:", err);
      throw err;
    }
  }, []);

  /** Create a new topic */
  const createTopic = useCallback(
    async (data: TopicFormData): Promise<OperationResult<Topic>> => {
      try {
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

        const lastTopic = topics.length > 0 ? topics[topics.length - 1] : null;
        const newMenuOrder = lastTopic ? lastTopic.menu_order + 1 : 0;

        const response = await apiFetch<{ success: boolean; data: Topic; message?: string }>({
          path: `/tutorpress/v1/topics`,
          method: "POST",
          data: {
            course_id: courseId,
            title: data.title.trim(),
            content: data.summary.trim() || " ",
            menu_order: newMenuOrder,
          },
        });

        if (!response.success || !isValidTopic(response.data)) {
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
              code: CurriculumErrorCode.SERVER_ERROR,
              message: response.message || __("Failed to create topic", "tutorpress"),
              context: { action: "create_topic" },
            },
          };
        }

        return { success: true, data: response.data };
      } catch (error: unknown) {
        console.error("Error creating topic:", error);

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

        return {
          success: false,
          error: {
            code: CurriculumErrorCode.SERVER_ERROR,
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
        const newTopic: Topic = {
          id: result.data.id,
          title: result.data.title,
          content: result.data.content || "",
          menu_order: result.data.menu_order || 0,
          contents: result.data.contents || [],
          isCollapsed: true,
        };

        setTopics((currentTopics) => [...currentTopics, newTopic]);
        setTopicCreationState({ status: "success", data: result.data });
        setIsAddingTopic(false);
      } else {
        setTopicCreationState({ status: "error", error: result.error! });
      }
    },
    [createTopic]
  );

  /** Handle topic form cancel */
  const handleTopicFormCancel = useCallback(() => {
    setTopicCreationState({ status: "idle" });
    setIsAddingTopic(false);
  }, []);

  /** Handle topic deletion */
  const handleTopicDelete = useCallback(
    async (topicId: number) => {
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
      }
    },
    [clearSnapshot]
  );

  /** Handle topic duplication */
  const handleTopicDuplicate = useCallback(
    async (topicId: number) => {
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

        createNotice("error", errorMessage, { type: "snackbar" });
      }
    },
    [courseId, createNotice]
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
    snapshot,

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
