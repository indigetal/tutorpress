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
  isValidTopic,
} from "../../types/curriculum";
import { getTopics } from "../../api/topics";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

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
  isAddingTopic: boolean;

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
  const [isAddingTopic, setIsAddingTopic] = useState(false);

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
  // Return Values
  // =============================
  return {
    // State
    topics,
    operationState,
    topicCreationState,
    editState,
    reorderState,
    isAddingTopic,

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

    // Computed
    isLoading: operationState.status === "loading",
    error: operationState.status === "error" ? operationState.error : null,
  };
}
