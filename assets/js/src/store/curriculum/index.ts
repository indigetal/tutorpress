import { createReduxStore, register } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { __ } from "@wordpress/i18n";
import {
  Topic,
  TopicOperationState,
  TopicEditState,
  TopicCreationState,
  ReorderOperationState,
  CurriculumError,
  CurriculumErrorCode,
  TopicDeletionState,
  TopicDuplicationState,
} from "../../types/curriculum";
import { apiService } from "../../api/service";
import { getTopics, reorderTopics, duplicateTopic, createTopic, updateTopic, deleteTopic } from "../../api/topics";
import { TopicRequest } from "../../types/api";

// Define the store's state interface
interface CurriculumState {
  topics: Topic[];
  operationState: TopicOperationState;
  editState: TopicEditState;
  topicCreationState: TopicCreationState;
  reorderState: ReorderOperationState;
  deletionState: {
    status: "idle" | "deleting" | "error" | "success";
    error?: CurriculumError;
    topicId?: number;
  };
  duplicationState: {
    status: "idle" | "duplicating" | "error" | "success";
    error?: CurriculumError;
    sourceTopicId?: number;
    duplicatedTopicId?: number;
  };
  isAddingTopic: boolean;
}

// Initial state
const DEFAULT_STATE: CurriculumState = {
  topics: [],
  operationState: { status: "idle" },
  editState: { isEditing: false, topicId: null },
  topicCreationState: { status: "idle" },
  reorderState: { status: "idle" },
  deletionState: { status: "idle" },
  duplicationState: { status: "idle" },
  isAddingTopic: false,
};

// Action types
type CurriculumAction =
  | { type: "SET_TOPICS"; topics: Topic[] | ((currentTopics: Topic[]) => Topic[]) }
  | {
      type: "SET_OPERATION_STATE";
      state: TopicOperationState | ((currentState: TopicOperationState) => TopicOperationState);
    }
  | { type: "SET_EDIT_STATE"; state: TopicEditState | ((currentState: TopicEditState) => TopicEditState) }
  | {
      type: "SET_TOPIC_CREATION_STATE";
      state: TopicCreationState | ((currentState: TopicCreationState) => TopicCreationState);
    }
  | {
      type: "SET_REORDER_STATE";
      state: ReorderOperationState | ((currentState: ReorderOperationState) => ReorderOperationState);
    }
  | {
      type: "SET_DELETION_STATE";
      state: TopicDeletionState | ((currentState: TopicDeletionState) => TopicDeletionState);
    }
  | {
      type: "SET_DUPLICATION_STATE";
      state: TopicDuplicationState | ((currentState: TopicDuplicationState) => TopicDuplicationState);
    }
  | { type: "SET_IS_ADDING_TOPIC"; isAdding: boolean | ((currentState: boolean) => boolean) };

// Action creators
const actions = {
  setTopics(topics: Topic[] | ((currentTopics: Topic[]) => Topic[])) {
    return {
      type: "SET_TOPICS",
      topics,
    };
  },
  setOperationState(state: TopicOperationState | ((currentState: TopicOperationState) => TopicOperationState)) {
    return {
      type: "SET_OPERATION_STATE",
      state,
    };
  },
  setEditState(state: TopicEditState | ((currentState: TopicEditState) => TopicEditState)) {
    return {
      type: "SET_EDIT_STATE",
      state,
    };
  },
  setTopicCreationState(state: TopicCreationState | ((currentState: TopicCreationState) => TopicCreationState)) {
    return {
      type: "SET_TOPIC_CREATION_STATE",
      state,
    };
  },
  setReorderState(state: ReorderOperationState | ((currentState: ReorderOperationState) => ReorderOperationState)) {
    return {
      type: "SET_REORDER_STATE",
      state,
    };
  },
  setDeletionState(
    state:
      | CurriculumState["deletionState"]
      | ((currentState: CurriculumState["deletionState"]) => CurriculumState["deletionState"])
  ) {
    return {
      type: "SET_DELETION_STATE",
      state,
    };
  },
  setDuplicationState(
    state:
      | CurriculumState["duplicationState"]
      | ((currentState: CurriculumState["duplicationState"]) => CurriculumState["duplicationState"])
  ) {
    return {
      type: "SET_DUPLICATION_STATE",
      state,
    };
  },
  setIsAddingTopic(isAdding: boolean | ((currentState: boolean) => boolean)) {
    return {
      type: "SET_IS_ADDING_TOPIC",
      isAdding,
    };
  },
};

// Selectors
const selectors = {
  getTopics(state: CurriculumState) {
    return state.topics;
  },
  getOperationState(state: CurriculumState) {
    return state.operationState;
  },
  getEditState(state: CurriculumState) {
    return state.editState;
  },
  getTopicCreationState(state: CurriculumState) {
    return state.topicCreationState;
  },
  getReorderState(state: CurriculumState) {
    return state.reorderState;
  },
  getDeletionState(state: CurriculumState) {
    return state.deletionState;
  },
  getDuplicationState(state: CurriculumState) {
    return state.duplicationState;
  },
  getIsAddingTopic(state: CurriculumState) {
    return state.isAddingTopic;
  },
};

// Helper function to handle state updates
const handleStateUpdate = <T>(currentState: T, newState: T | ((state: T) => T)): T => {
  return typeof newState === "function" ? (newState as (state: T) => T)(currentState) : newState;
};

// Reducer
const reducer = (state = DEFAULT_STATE, action: CurriculumAction): CurriculumState => {
  switch (action.type) {
    case "SET_TOPICS": {
      const newTopics = handleStateUpdate(state.topics, action.topics);
      return {
        ...state,
        topics: newTopics,
        // Reset operation state if topics are updated successfully
        operationState: { status: "idle" },
      };
    }

    case "SET_OPERATION_STATE": {
      const newState = handleStateUpdate(state.operationState, action.state);
      return {
        ...state,
        operationState: newState,
      };
    }

    case "SET_EDIT_STATE": {
      const newState = handleStateUpdate(state.editState, action.state);
      return {
        ...state,
        editState: newState,
      };
    }

    case "SET_TOPIC_CREATION_STATE": {
      const newState = handleStateUpdate(state.topicCreationState, action.state);
      return {
        ...state,
        topicCreationState: newState,
        // Reset adding state if creation is complete
        isAddingTopic: newState.status === "idle" ? false : state.isAddingTopic,
      };
    }

    case "SET_REORDER_STATE": {
      const newState = handleStateUpdate(state.reorderState, action.state);
      return {
        ...state,
        reorderState: newState,
      };
    }

    case "SET_DELETION_STATE": {
      const newState = handleStateUpdate(state.deletionState, action.state);
      return {
        ...state,
        deletionState: newState,
        // Remove topic from state if deletion was successful
        topics:
          newState.status === "success" && newState.topicId
            ? state.topics.filter((topic) => topic.id !== newState.topicId)
            : state.topics,
      };
    }

    case "SET_DUPLICATION_STATE": {
      const newState = handleStateUpdate(state.duplicationState, action.state);
      return {
        ...state,
        duplicationState: newState,
      };
    }

    case "SET_IS_ADDING_TOPIC": {
      const newState = handleStateUpdate(state.isAddingTopic, action.isAdding);
      return {
        ...state,
        isAddingTopic: newState,
      };
    }

    default:
      return state;
  }
};

// Async action creators
const asyncActions = {
  async fetchTopics(courseId: number) {
    try {
      actions.setOperationState({ status: "loading" });

      const response = await getTopics(courseId);

      actions.setTopics(response);
      actions.setOperationState({
        status: "success",
        data: response,
      });
    } catch (error) {
      const curriculumError: CurriculumError = {
        code: CurriculumErrorCode.FETCH_FAILED,
        message: error instanceof Error ? error.message : "Failed to fetch topics",
        context: {
          action: "fetchTopics",
          details: error instanceof Error ? error.stack : undefined,
        },
      };

      actions.setOperationState({
        status: "error",
        error: curriculumError,
      });
      throw curriculumError;
    }
  },

  async reorderTopics(courseId: number, topicIds: number[]) {
    try {
      actions.setReorderState({ status: "reordering" });

      await reorderTopics(courseId, topicIds);

      // Refresh topics after reordering
      const updatedTopics = await getTopics(courseId);
      actions.setTopics(updatedTopics);
      actions.setReorderState({ status: "success" });
    } catch (error) {
      const curriculumError: CurriculumError = {
        code: CurriculumErrorCode.REORDER_FAILED,
        message: error instanceof Error ? error.message : "Failed to reorder topics",
        context: {
          action: "reorderTopics",
          details: error instanceof Error ? error.stack : undefined,
        },
      };

      actions.setReorderState({
        status: "error",
        error: curriculumError,
      });
      throw curriculumError;
    }
  },

  async duplicateTopic(topicId: number, courseId: number) {
    try {
      actions.setDuplicationState({
        status: "duplicating",
        sourceTopicId: topicId,
      });

      const newTopic = await duplicateTopic(topicId, courseId);

      // Refresh topics after duplication
      const updatedTopics = await getTopics(courseId);
      actions.setTopics(updatedTopics);
      actions.setDuplicationState({
        status: "success",
        sourceTopicId: topicId,
        duplicatedTopicId: newTopic.id,
      });
    } catch (error) {
      const curriculumError: CurriculumError = {
        code: CurriculumErrorCode.CREATION_FAILED,
        message: error instanceof Error ? error.message : "Failed to duplicate topic",
        context: {
          action: "duplicateTopic",
          topicId,
          details: error instanceof Error ? error.stack : undefined,
        },
      };

      actions.setDuplicationState({
        status: "error",
        error: curriculumError,
        sourceTopicId: topicId,
      });
      throw curriculumError;
    }
  },

  async createTopic(data: TopicRequest) {
    try {
      actions.setTopicCreationState({ status: "creating" });

      const newTopic = await createTopic(data);

      // Refresh topics after creation
      const updatedTopics = await getTopics(data.course_id);
      actions.setTopics(updatedTopics);
      actions.setTopicCreationState({
        status: "success",
        data: newTopic,
      });
    } catch (error) {
      const curriculumError: CurriculumError = {
        code: CurriculumErrorCode.CREATION_FAILED,
        message: error instanceof Error ? error.message : "Failed to create topic",
        context: {
          action: "createTopic",
          details: error instanceof Error ? error.stack : undefined,
        },
      };

      actions.setTopicCreationState({
        status: "error",
        error: curriculumError,
      });
      throw curriculumError;
    }
  },

  async updateTopic(topicId: number, data: Partial<TopicRequest>) {
    try {
      actions.setEditState({
        isEditing: true,
        topicId,
      });

      const updatedTopic = await updateTopic(topicId, data);

      // Refresh topics after update
      const updatedTopics = await getTopics(data.course_id || 0);
      actions.setTopics(updatedTopics);
      actions.setEditState({
        isEditing: false,
        topicId: null,
      });
    } catch (error) {
      const curriculumError: CurriculumError = {
        code: CurriculumErrorCode.VALIDATION_ERROR,
        message: error instanceof Error ? error.message : "Failed to update topic",
        context: {
          action: "updateTopic",
          topicId,
          details: error instanceof Error ? error.stack : undefined,
        },
      };

      actions.setEditState({
        isEditing: false,
        topicId: null,
      });
      throw curriculumError;
    }
  },

  async deleteTopic(topicId: number, courseId: number) {
    try {
      actions.setDeletionState({
        status: "deleting",
        topicId,
      });

      await deleteTopic(topicId);

      // Refresh topics after deletion
      const updatedTopics = await getTopics(courseId);
      actions.setTopics(updatedTopics);
      actions.setDeletionState({
        status: "success",
        topicId,
      });
    } catch (error) {
      const curriculumError: CurriculumError = {
        code: CurriculumErrorCode.SERVER_ERROR,
        message: error instanceof Error ? error.message : "Failed to delete topic",
        context: {
          action: "deleteTopic",
          topicId,
          details: error instanceof Error ? error.stack : undefined,
        },
      };

      actions.setDeletionState({
        status: "error",
        error: curriculumError,
        topicId,
      });
      throw curriculumError;
    }
  },
};

// Create and register the store
const store = createReduxStore("tutorpress/curriculum", {
  reducer,
  actions: {
    ...actions,
    ...asyncActions,
  },
  selectors,
  controls,
});

register(store);

export { store as curriculumStore };
