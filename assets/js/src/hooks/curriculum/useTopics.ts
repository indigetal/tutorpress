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
} from "../../types/curriculum";
import { getTopics } from "../../api/topics";
import { __ } from "@wordpress/i18n";

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

  // State setters
  setTopics: React.Dispatch<React.SetStateAction<Topic[]>>;
  setOperationState: React.Dispatch<React.SetStateAction<TopicOperationState>>;
  setTopicCreationState: React.Dispatch<React.SetStateAction<TopicCreationState>>;
  setEditState: React.Dispatch<React.SetStateAction<TopicEditState>>;
  setReorderState: React.Dispatch<React.SetStateAction<ReorderOperationState>>;

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
  // Return Values
  // =============================
  return {
    // State
    topics,
    operationState,
    topicCreationState,
    editState,
    reorderState,

    // State setters
    setTopics,
    setOperationState,
    setTopicCreationState,
    setEditState,
    setReorderState,

    // Computed
    isLoading: operationState.status === "loading",
    error: operationState.status === "error" ? operationState.error : null,
  };
}
