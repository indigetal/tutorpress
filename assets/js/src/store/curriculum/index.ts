import { createReduxStore, register } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import {
  Topic,
  TopicOperationState,
  TopicEditState,
  TopicCreationState,
  ReorderOperationState,
  CurriculumError,
  CurriculumErrorCode,
} from "../../types/curriculum";

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
const reducer = (state = DEFAULT_STATE, action: any) => {
  switch (action.type) {
    case "SET_TOPICS":
      return {
        ...state,
        topics: handleStateUpdate(state.topics, action.topics),
      };
    case "SET_OPERATION_STATE":
      return {
        ...state,
        operationState: handleStateUpdate(state.operationState, action.state),
      };
    case "SET_EDIT_STATE":
      return {
        ...state,
        editState: handleStateUpdate(state.editState, action.state),
      };
    case "SET_TOPIC_CREATION_STATE":
      return {
        ...state,
        topicCreationState: handleStateUpdate(state.topicCreationState, action.state),
      };
    case "SET_REORDER_STATE":
      return {
        ...state,
        reorderState: handleStateUpdate(state.reorderState, action.state),
      };
    case "SET_DELETION_STATE":
      return {
        ...state,
        deletionState: handleStateUpdate(state.deletionState, action.state),
      };
    case "SET_DUPLICATION_STATE":
      return {
        ...state,
        duplicationState: handleStateUpdate(state.duplicationState, action.state),
      };
    case "SET_IS_ADDING_TOPIC":
      return {
        ...state,
        isAddingTopic: handleStateUpdate(state.isAddingTopic, action.isAdding),
      };
    default:
      return state;
  }
};

// Create and register the store
const store = createReduxStore("tutorpress/curriculum", {
  reducer,
  actions,
  selectors,
});

register(store);

export { store as curriculumStore };
