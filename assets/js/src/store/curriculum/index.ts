import { createReduxStore, register, select } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { __ } from "@wordpress/i18n";
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
  CurriculumErrorCode,
} from "../../types/curriculum";
import { apiService } from "../../api/service";
import {
  getTopics as fetchTopics,
  reorderTopics,
  duplicateTopic,
  createTopic as apiCreateTopic,
  updateTopic,
  deleteTopic,
} from "../../api/topics";
import { TopicRequest } from "../../types/api";
import apiFetch from "@wordpress/api-fetch";

// Define the store's state interface
interface CurriculumState {
  topics: Topic[];
  operationState: TopicOperationState;
  editState: TopicEditState;
  topicCreationState: TopicCreationState;
  reorderState: TopicReorderState;
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
  activeOperation: { type: string; topicId?: number };
  fetchState: {
    isLoading: boolean;
    error: Error | null;
    lastFetchedCourseId: number | null;
  };
}

// Initial state
const DEFAULT_STATE: CurriculumState = {
  topics: [],
  operationState: { status: "idle" },
  topicCreationState: { status: "idle" },
  editState: { isEditing: false, topicId: null },
  deletionState: { status: "idle" },
  duplicationState: { status: "idle" },
  reorderState: { status: "idle" },
  isAddingTopic: false,
  activeOperation: { type: "none" },
  fetchState: {
    isLoading: false,
    error: null,
    lastFetchedCourseId: null,
  },
};

// Action types
export type CurriculumAction =
  | { type: "SET_TOPICS"; payload: Topic[] | ((topics: Topic[]) => Topic[]) }
  | { type: "SET_OPERATION_STATE"; payload: TopicOperationState }
  | { type: "SET_EDIT_STATE"; payload: TopicEditState }
  | { type: "SET_TOPIC_CREATION_STATE"; payload: TopicCreationState }
  | { type: "SET_REORDER_STATE"; payload: TopicReorderState }
  | { type: "SET_DELETION_STATE"; payload: TopicDeletionState }
  | { type: "SET_DUPLICATION_STATE"; payload: TopicDuplicationState }
  | { type: "SET_IS_ADDING_TOPIC"; payload: boolean }
  | { type: "SET_ACTIVE_OPERATION"; payload: TopicActiveOperation }
  | { type: "FETCH_TOPICS_START"; payload: { courseId: number } }
  | { type: "FETCH_TOPICS_SUCCESS"; payload: { topics: Topic[] } }
  | { type: "FETCH_TOPICS_ERROR"; payload: { error: CurriculumError } };

// Action creators
export const actions = {
  setTopics(topics: Topic[] | ((currentTopics: Topic[]) => Topic[])) {
    return {
      type: "SET_TOPICS",
      payload: topics,
    };
  },
  setOperationState(state: TopicOperationState) {
    return {
      type: "SET_OPERATION_STATE",
      payload: state,
    };
  },
  setEditState(state: TopicEditState) {
    return {
      type: "SET_EDIT_STATE",
      payload: state,
    };
  },
  setTopicCreationState(state: TopicCreationState) {
    return {
      type: "SET_TOPIC_CREATION_STATE",
      payload: state,
    };
  },
  setReorderState(state: TopicReorderState) {
    return {
      type: "SET_REORDER_STATE",
      payload: state,
    };
  },
  setDeletionState(state: TopicDeletionState) {
    return {
      type: "SET_DELETION_STATE",
      payload: state,
    };
  },
  setDuplicationState(state: TopicDuplicationState) {
    return {
      type: "SET_DUPLICATION_STATE",
      payload: state,
    };
  },
  setIsAddingTopic(isAdding: boolean) {
    return {
      type: "SET_IS_ADDING_TOPIC",
      payload: isAdding,
    };
  },
  setActiveOperation(payload: TopicActiveOperation) {
    return {
      type: "SET_ACTIVE_OPERATION",
      payload,
    };
  },
  createTopic(data: TopicRequest) {
    return {
      type: "CREATE_TOPIC",
      data,
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
  getActiveOperation(state: CurriculumState) {
    return state.activeOperation;
  },
  getFetchState(state: CurriculumState) {
    return state.fetchState;
  },
  getTopicById(state: CurriculumState, topicId: number) {
    return state.topics.find((topic) => topic.id === topicId);
  },
  getActiveTopic(state: CurriculumState) {
    const { activeOperation } = state;
    if (activeOperation.type === "none" || !activeOperation.topicId) {
      return null;
    }
    return state.topics.find((topic) => topic.id === activeOperation.topicId);
  },
  getTopicsCount(state: CurriculumState) {
    return state.topics.length;
  },
  getTopicsWithContent(state: CurriculumState) {
    return state.topics.filter((topic) => topic.content && topic.content.trim() !== "");
  },
};

// Helper function to handle state updates
const handleStateUpdate = <T>(currentState: T, newState: T | ((state: T) => T)): T => {
  return typeof newState === "function" ? (newState as (state: T) => T)(currentState) : newState;
};

// Reducer
const reducer = (state = DEFAULT_STATE, action: CurriculumAction): CurriculumState => {
  if (process.env.NODE_ENV === "development") {
    console.log("Reducer: Processing action:", action.type);
  }

  switch (action.type) {
    case "FETCH_TOPICS_START":
      return {
        ...state,
        operationState: { status: "loading" },
      };
    case "SET_TOPICS": {
      const newTopics = handleStateUpdate(state.topics, action.payload);
      if (process.env.NODE_ENV === "development") {
        console.log("Reducer: Setting topics:", newTopics);
      }
      return {
        ...state,
        topics: newTopics,
        operationState: state.operationState,
      };
    }

    case "SET_OPERATION_STATE": {
      const newState = handleStateUpdate(state.operationState, action.payload);
      if (process.env.NODE_ENV === "development") {
        console.log("Reducer: Setting operation state:", newState);
      }
      return {
        ...state,
        operationState: newState,
      };
    }

    case "SET_ACTIVE_OPERATION": {
      if (state.activeOperation.type !== "none" && action.payload.type !== "none") {
        if (process.env.NODE_ENV === "development") {
          console.warn(
            `Attempting to set active operation to ${action.payload.type} while ${state.activeOperation.type} is in progress`
          );
        }
        return state;
      }

      return {
        ...state,
        activeOperation: action.payload,
      };
    }

    case "SET_EDIT_STATE": {
      const newState = handleStateUpdate(state.editState, action.payload);

      if (!state.editState.isEditing && newState.isEditing && newState.topicId) {
        return {
          ...state,
          editState: newState,
          activeOperation: { type: "edit", topicId: newState.topicId },
        };
      }

      if (state.editState.isEditing && !newState.isEditing) {
        return {
          ...state,
          editState: newState,
          activeOperation: { type: "none" },
        };
      }

      return {
        ...state,
        editState: newState,
      };
    }

    case "SET_TOPIC_CREATION_STATE": {
      const newState = handleStateUpdate(state.topicCreationState, action.payload);
      if (process.env.NODE_ENV === "development") {
        console.log("Reducer: Setting topic creation state:", newState);
      }

      if (newState.status === "creating") {
        return {
          ...state,
          topicCreationState: newState,
          activeOperation: { type: "create" },
        };
      }

      if (
        state.topicCreationState.status === "creating" &&
        (newState.status === "success" || newState.status === "error")
      ) {
        return {
          ...state,
          topicCreationState: newState,
          activeOperation: { type: "none" },
        };
      }

      return {
        ...state,
        topicCreationState: newState,
      };
    }

    case "SET_REORDER_STATE": {
      const newState = handleStateUpdate(state.reorderState, action.payload);

      if (newState.status === "reordering") {
        return {
          ...state,
          reorderState: newState,
          activeOperation: { type: "reorder" },
        };
      }

      if (
        state.reorderState.status === "reordering" &&
        (newState.status === "success" || newState.status === "error")
      ) {
        return {
          ...state,
          reorderState: newState,
          activeOperation: { type: "none" },
        };
      }

      return {
        ...state,
        reorderState: newState,
      };
    }

    case "SET_DELETION_STATE": {
      const newState = handleStateUpdate(state.deletionState, action.payload);

      if (newState.status === "deleting" && newState.topicId) {
        return {
          ...state,
          deletionState: newState,
          activeOperation: { type: "delete", topicId: newState.topicId },
        };
      }

      if (state.deletionState.status === "deleting" && (newState.status === "success" || newState.status === "error")) {
        return {
          ...state,
          deletionState: newState,
          activeOperation: { type: "none" },
        };
      }

      return {
        ...state,
        deletionState: newState,
      };
    }

    case "SET_DUPLICATION_STATE": {
      const newState = handleStateUpdate(state.duplicationState, action.payload);

      if (newState.status === "duplicating" && newState.sourceTopicId) {
        return {
          ...state,
          duplicationState: newState,
          activeOperation: { type: "duplicate", topicId: newState.sourceTopicId },
        };
      }

      if (
        state.duplicationState.status === "duplicating" &&
        (newState.status === "success" || newState.status === "error")
      ) {
        return {
          ...state,
          duplicationState: newState,
          activeOperation: { type: "none" },
        };
      }

      return {
        ...state,
        duplicationState: newState,
      };
    }

    case "SET_IS_ADDING_TOPIC": {
      const newState = handleStateUpdate(state.isAddingTopic, action.payload);
      return {
        ...state,
        isAddingTopic: newState,
      };
    }

    case "FETCH_TOPICS_SUCCESS": {
      const newTopics = handleStateUpdate(state.topics, action.payload.topics);
      return {
        ...state,
        topics: newTopics,
        operationState: { status: "success", data: newTopics },
      };
    }

    case "FETCH_TOPICS_ERROR": {
      return {
        ...state,
        operationState: {
          status: "error",
          error: action.payload.error,
        },
      };
    }

    default:
      return state;
  }
};

// Define API response types
interface TopicsResponse {
  success: boolean;
  message?: string;
  data: Array<{
    id: number;
    title: string;
    content: string;
    menu_order: number;
  }>;
}

interface CreateTopicResponse {
  success: boolean;
  message?: string;
  data: {
    id: number;
    title: string;
    content: string;
    menu_order: number;
  };
}

// Async action creators
const asyncActions = {
  *fetchTopics(courseId: number): Generator<unknown, void, unknown> {
    yield actions.setOperationState({ status: "loading" });
    try {
      const response = (yield apiFetch({
        path: `/tutorpress/v1/topics?course_id=${courseId}`,
      })) as TopicsResponse;

      const topics = response.data.map((topic) => ({
        ...topic,
        isCollapsed: false,
        contents: [],
      }));
      yield actions.setTopics(topics);
      yield actions.setOperationState({ status: "success", data: topics });
    } catch (error) {
      yield actions.setOperationState({
        status: "error",
        error: {
          code: CurriculumErrorCode.SERVER_ERROR,
          message: error instanceof Error ? error.message : "Failed to fetch topics",
          context: { action: "fetchTopics" },
        },
      });
    }
  },

  async reorderTopics(courseId: number, topicIds: number[]) {
    try {
      actions.setReorderState({ status: "reordering" });

      await reorderTopics(courseId, topicIds);

      const updatedTopics = await fetchTopics(courseId);
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

      const updatedTopics = await fetchTopics(courseId);
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

  *createTopic(data: TopicRequest): Generator<unknown, Topic, unknown> {
    try {
      yield actions.setTopicCreationState({ status: "creating" });

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/topics",
          method: "POST",
          data: {
            course_id: data.course_id,
            title: data.title,
            content: data.content || " ",
            menu_order: data.menu_order || 0,
          },
        },
      }) as CreateTopicResponse;

      if (!response || !response.success || !response.data) {
        const errorMessage = response?.message || "Failed to create topic";
        throw new Error(errorMessage);
      }

      const newTopic: Topic = {
        id: response.data.id,
        title: response.data.title,
        content: response.data.content || " ",
        menu_order: response.data.menu_order,
        isCollapsed: false,
        contents: [],
      };

      yield actions.setTopics((currentTopics) => [...currentTopics, newTopic]);

      yield actions.setTopicCreationState({
        status: "success",
        data: newTopic,
      });

      yield actions.setIsAddingTopic(false);

      return newTopic;
    } catch (error) {
      yield actions.setTopicCreationState({
        status: "error",
        error: {
          code: CurriculumErrorCode.CREATION_FAILED,
          message: error instanceof Error ? error.message : "Failed to create topic",
          context: {
            action: "createTopic",
            details: error instanceof Error ? error.stack : JSON.stringify(error),
          },
        },
      });

      throw error;
    }
  },

  async updateTopic(topicId: number, data: Partial<TopicRequest>) {
    try {
      actions.setEditState({
        isEditing: true,
        topicId,
      });

      const updatedTopic = await updateTopic(topicId, data);

      const updatedTopics = await fetchTopics(data.course_id || 0);
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

      const updatedTopics = await fetchTopics(courseId);
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
} as const;

// Create and register the store
const curriculumStore = createReduxStore("tutorpress/curriculum", {
  reducer,
  actions: {
    ...actions,
    ...asyncActions,
  },
  selectors,
  controls,
});

register(curriculumStore);

const verifyStoreRegistration = () => {
  try {
    const store = select("tutorpress/curriculum");
    if (!store) {
      console.error("Store not found:", store);
      return false;
    }
    console.log("Store successfully registered:", curriculumStore);
    return true;
  } catch (error) {
    console.error("Error verifying store registration:", error);
    return false;
  }
};

// Verify registration
if (!verifyStoreRegistration()) {
  console.error("Failed to register curriculum store!");
}

// Log the store registration
console.log("Store registered:", curriculumStore);

export { curriculumStore };
export const {
  setTopics,
  setOperationState,
  setEditState,
  setTopicCreationState,
  setReorderState,
  setDeletionState,
  setDuplicationState,
  setIsAddingTopic,
  setActiveOperation,
} = actions;

export const {
  getTopics,
  getOperationState,
  getEditState,
  getTopicCreationState,
  getReorderState,
  getDeletionState,
  getDuplicationState,
  getIsAddingTopic,
  getActiveOperation,
} = selectors;
