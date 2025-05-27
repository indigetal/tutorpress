import { createReduxStore, register, select } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import { __ } from "@wordpress/i18n";
import {
  Topic,
  TopicOperationState,
  TopicEditState,
  TopicCreationState,
  ReorderOperationState,
  TopicDeletionState,
  TopicDuplicationState,
  LessonDuplicationState,
  CurriculumError,
  OperationResult,
  TopicActiveOperation,
  CurriculumErrorCode,
} from "../../types/curriculum";
import type { Lesson } from "../../types/lessons";
import { apiService } from "../../api/service";
import {
  getTopics as fetchTopics,
  reorderTopics,
  duplicateTopic,
  createTopic as apiCreateTopic,
  updateTopic,
  deleteTopic as apiDeleteTopic,
} from "../../api/topics";
import {
  createLesson as apiCreateLesson,
  updateLesson as apiUpdateLesson,
  deleteLesson as apiDeleteLesson,
  duplicateLesson,
} from "../../api/lessons";
import { TopicRequest } from "../../types/api";
import apiFetch from "@wordpress/api-fetch";

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
  lessonDuplicationState: LessonDuplicationState;
  lessonState: {
    status: "idle" | "loading" | "error" | "success";
    error?: CurriculumError;
    activeLessonId?: number;
  };
  isAddingTopic: boolean;
  activeOperation: { type: string; topicId?: number };
  fetchState: {
    isLoading: boolean;
    error: Error | null;
    lastFetchedCourseId: number | null;
  };
  courseId: number | null;
}

// Initial state
const DEFAULT_STATE: CurriculumState = {
  topics: [],
  operationState: { status: "idle" },
  topicCreationState: { status: "idle" },
  editState: { isEditing: false, topicId: null },
  deletionState: { status: "idle" },
  duplicationState: { status: "idle" },
  lessonDuplicationState: { status: "idle" },
  reorderState: { status: "idle" },
  lessonState: { status: "idle" },
  isAddingTopic: false,
  activeOperation: { type: "none" },
  fetchState: {
    isLoading: false,
    error: null,
    lastFetchedCourseId: null,
  },
  courseId: null,
};

// Action types
export type CurriculumAction =
  | { type: "SET_TOPICS"; payload: Topic[] | ((topics: Topic[]) => Topic[]) }
  | { type: "SET_OPERATION_STATE"; payload: TopicOperationState }
  | { type: "SET_EDIT_STATE"; payload: TopicEditState }
  | { type: "SET_TOPIC_CREATION_STATE"; payload: TopicCreationState }
  | { type: "SET_REORDER_STATE"; payload: ReorderOperationState }
  | { type: "SET_DELETION_STATE"; payload: TopicDeletionState }
  | { type: "SET_DUPLICATION_STATE"; payload: TopicDuplicationState }
  | { type: "SET_LESSON_DUPLICATION_STATE"; payload: LessonDuplicationState }
  | { type: "SET_IS_ADDING_TOPIC"; payload: boolean }
  | { type: "SET_ACTIVE_OPERATION"; payload: TopicActiveOperation }
  | { type: "FETCH_TOPICS_START"; payload: { courseId: number } }
  | { type: "FETCH_TOPICS_SUCCESS"; payload: { topics: Topic[] } }
  | { type: "FETCH_TOPICS_ERROR"; payload: { error: CurriculumError } }
  | { type: "SET_COURSE_ID"; payload: number | null }
  | { type: "FETCH_COURSE_ID_START"; payload: { lessonId: number } }
  | { type: "FETCH_COURSE_ID_SUCCESS"; payload: { courseId: number } }
  | { type: "FETCH_COURSE_ID_ERROR"; payload: { error: CurriculumError } }
  | { type: "CREATE_LESSON_START"; payload: { topicId: number } }
  | { type: "CREATE_LESSON_SUCCESS"; payload: { lesson: Lesson } }
  | { type: "CREATE_LESSON_ERROR"; payload: { error: CurriculumError } }
  | { type: "UPDATE_LESSON_START"; payload: { lessonId: number } }
  | { type: "UPDATE_LESSON_SUCCESS"; payload: { lesson: Lesson } }
  | { type: "UPDATE_LESSON_ERROR"; payload: { error: CurriculumError } }
  | { type: "DELETE_LESSON_START"; payload: { lessonId: number } }
  | { type: "DELETE_LESSON_SUCCESS"; payload: { lessonId: number } }
  | { type: "DELETE_LESSON_ERROR"; payload: { error: CurriculumError } }
  | { type: "DELETE_TOPIC_START"; payload: { topicId: number } }
  | { type: "DELETE_TOPIC_SUCCESS"; payload: { topicId: number } }
  | { type: "DELETE_TOPIC_ERROR"; payload: { error: CurriculumError } }
  | { type: "DUPLICATE_LESSON_START"; payload: { lessonId: number } }
  | { type: "DUPLICATE_LESSON_SUCCESS"; payload: { lesson: Lesson; sourceLessonId: number } }
  | { type: "DUPLICATE_LESSON_ERROR"; payload: { error: CurriculumError; lessonId: number } }
  | { type: "CREATE_LESSON"; payload: { title: string; content: string; topic_id: number } }
  | {
      type: "UPDATE_LESSON";
      payload: { lessonId: number; data: Partial<{ title: string; content: string; topic_id: number }> };
    }
  | { type: "DELETE_LESSON"; payload: { lessonId: number } }
  | { type: "DELETE_TOPIC"; payload: { topicId: number; courseId: number } }
  | { type: "DUPLICATE_LESSON"; payload: { lessonId: number; topicId: number } }
  | { type: "SET_LESSON_STATE"; payload: CurriculumState["lessonState"] }
  | { type: "REFRESH_TOPICS_AFTER_LESSON_SAVE"; payload: { courseId: number } };

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
  setReorderState(state: ReorderOperationState) {
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
  setLessonDuplicationState(state: LessonDuplicationState) {
    return {
      type: "SET_LESSON_DUPLICATION_STATE",
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
  setCourseId(courseId: number | null) {
    return {
      type: "SET_COURSE_ID",
      payload: courseId,
    };
  },
  fetchCourseId(id: number) {
    return async ({ dispatch }: { dispatch: (action: CurriculumAction) => void }) => {
      dispatch({ type: "FETCH_COURSE_ID_START", payload: { lessonId: id } });
      try {
        // Get the URL parameters to check if this is a new lesson with topic_id
        const urlParams = new URLSearchParams(window.location.search);
        const isNewLesson = urlParams.has("topic_id");

        // If this is a new lesson, use the topic_id directly to get the course ID
        // Otherwise, use the lesson ID to get the parent info
        const path = isNewLesson
          ? `/tutorpress/v1/topics/${id}/parent-info`
          : `/tutorpress/v1/lessons/${id}/parent-info`;

        const response = await apiFetch<ParentInfoResponse>({
          path,
          method: "GET",
        });

        if (!response.success || !response.data?.course_id) {
          throw new Error(response.message || __("Failed to get course ID", "tutorpress"));
        }

        dispatch({ type: "FETCH_COURSE_ID_SUCCESS", payload: { courseId: response.data.course_id } });
      } catch (error) {
        console.error("Error fetching course ID:", error);
        dispatch({
          type: "FETCH_COURSE_ID_ERROR",
          payload: {
            error: {
              code: CurriculumErrorCode.FETCH_FAILED,
              message: error instanceof Error ? error.message : __("Failed to fetch course ID", "tutorpress"),
              context: {
                action: "fetchCourseId",
                details: `Failed to fetch course ID for ID ${id}`,
              },
            },
          },
        });
      }
    };
  },
  createLesson(data: { title: string; content: string; topic_id: number }) {
    return {
      type: "CREATE_LESSON",
      data,
    };
  },
  updateLesson(lessonId: number, data: Partial<{ title: string; content: string; topic_id: number }>) {
    return {
      type: "UPDATE_LESSON",
      lessonId,
      data,
    };
  },
  deleteLesson(lessonId: number) {
    return {
      type: "DELETE_LESSON",
      payload: { lessonId },
    };
  },
  deleteTopic(topicId: number, courseId: number) {
    return {
      type: "DELETE_TOPIC",
      payload: { topicId, courseId },
    };
  },
  duplicateLesson(lessonId: number, topicId: number) {
    return {
      type: "DUPLICATE_LESSON",
      payload: { lessonId, topicId },
    };
  },
  setLessonState(state: CurriculumState["lessonState"]) {
    return {
      type: "SET_LESSON_STATE",
      payload: state,
    };
  },
  refreshTopicsAfterLessonSave(courseId: number) {
    return {
      type: "REFRESH_TOPICS_AFTER_LESSON_SAVE",
      payload: { courseId },
    };
  },
  *updateTopic(topicId: number, data: Partial<TopicRequest>): Generator<unknown, void, unknown> {
    try {
      yield actions.setEditState({
        isEditing: true,
        topicId,
      });

      // Update the topic
      yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics/${topicId}`,
          method: "PATCH",
          data,
        },
      };

      // Fetch updated topics
      const topicsResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${data.course_id || 0}`,
          method: "GET",
        },
      };

      if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
        throw new Error("Invalid topics response");
      }

      const topics = topicsResponse as { data: Topic[] };

      // Transform topics to preserve UI state - set to collapsed to avoid toggle issues
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      yield actions.setTopics(transformedTopics);
      yield actions.setEditState({
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

      yield actions.setEditState({
        isEditing: false,
        topicId: null,
      });
      throw curriculumError;
    }
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
  getLessonDuplicationState(state: CurriculumState) {
    return state.lessonDuplicationState;
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
  getCourseId(state: CurriculumState) {
    return state.courseId;
  },
  getLessonState(state: CurriculumState) {
    return state.lessonState;
  },
  getActiveLessonId(state: CurriculumState) {
    return state.lessonState.activeLessonId;
  },
  isLessonLoading(state: CurriculumState) {
    return state.lessonState.status === "loading";
  },
  hasLessonError(state: CurriculumState) {
    return state.lessonState.status === "error";
  },
  getLessonError(state: CurriculumState) {
    return state.lessonState.error;
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

    case "SET_COURSE_ID":
      return {
        ...state,
        courseId: action.payload,
      };
    case "FETCH_COURSE_ID_START":
      return {
        ...state,
        operationState: { status: "loading" },
      };
    case "FETCH_COURSE_ID_SUCCESS":
      return {
        ...state,
        courseId: action.payload.courseId,
        operationState: { status: "idle" },
      };
    case "FETCH_COURSE_ID_ERROR":
      return {
        ...state,
        operationState: { status: "error", error: action.payload.error },
      };
    case "CREATE_LESSON_START":
      return {
        ...state,
        lessonState: { status: "loading" },
      };
    case "CREATE_LESSON_SUCCESS":
      return {
        ...state,
        lessonState: {
          status: "success",
          activeLessonId: action.payload.lesson.id,
        },
      };
    case "CREATE_LESSON_ERROR":
      return {
        ...state,
        lessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "UPDATE_LESSON_START":
      return {
        ...state,
        lessonState: {
          status: "loading",
          activeLessonId: action.payload.lessonId,
        },
      };
    case "UPDATE_LESSON_SUCCESS":
      return {
        ...state,
        lessonState: {
          status: "success",
          activeLessonId: action.payload.lesson.id,
        },
      };
    case "UPDATE_LESSON_ERROR":
      return {
        ...state,
        lessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "DELETE_LESSON_START":
      return {
        ...state,
        lessonState: {
          status: "loading",
          activeLessonId: action.payload.lessonId,
        },
      };
    case "DELETE_LESSON_SUCCESS":
      return {
        ...state,
        lessonState: { status: "success" },
      };
    case "DELETE_LESSON_ERROR":
      return {
        ...state,
        lessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "DELETE_TOPIC_START":
      return {
        ...state,
        deletionState: {
          status: "deleting",
          topicId: action.payload.topicId,
        },
      };
    case "DELETE_TOPIC_SUCCESS":
      return {
        ...state,
        deletionState: {
          status: "success",
          topicId: action.payload.topicId,
        },
      };
    case "DELETE_TOPIC_ERROR":
      return {
        ...state,
        deletionState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "SET_LESSON_DUPLICATION_STATE": {
      const newState = handleStateUpdate(state.lessonDuplicationState, action.payload);
      return {
        ...state,
        lessonDuplicationState: newState,
      };
    }
    case "DUPLICATE_LESSON_START":
      return {
        ...state,
        lessonDuplicationState: {
          status: "duplicating",
          sourceLessonId: action.payload.lessonId,
        },
      };
    case "DUPLICATE_LESSON_SUCCESS":
      return {
        ...state,
        lessonDuplicationState: {
          status: "success",
          sourceLessonId: action.payload.sourceLessonId,
          duplicatedLessonId: action.payload.lesson.id,
        },
      };
    case "DUPLICATE_LESSON_ERROR":
      return {
        ...state,
        lessonDuplicationState: {
          status: "error",
          error: action.payload.error,
          sourceLessonId: action.payload.lessonId,
        },
      };
    case "SET_LESSON_STATE":
      return {
        ...state,
        lessonState: action.payload,
      };
    case "REFRESH_TOPICS_AFTER_LESSON_SAVE":
      return {
        ...state,
        operationState: { status: "loading" },
      };
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

interface ParentInfoResponse {
  success: boolean;
  message: string;
  data: {
    course_id: number;
    topic_id: number;
  };
}

// Generator functions
const resolvers = {
  *fetchTopics(courseId: number): Generator<unknown, void, unknown> {
    yield actions.setOperationState({ status: "loading" });
    try {
      const response = (yield apiFetch({
        path: `/tutorpress/v1/topics?course_id=${courseId}`,
      })) as TopicsResponse;

      const topics = response.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
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

  *updateTopic(topicId: number, data: Partial<TopicRequest>): Generator<unknown, void, unknown> {
    try {
      yield actions.setEditState({
        isEditing: true,
        topicId,
      });

      // Update the topic
      yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics/${topicId}`,
          method: "PATCH",
          data,
        },
      };

      // Fetch updated topics
      const topicsResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${data.course_id || 0}`,
          method: "GET",
        },
      };

      if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
        throw new Error("Invalid topics response");
      }

      const topics = topicsResponse as { data: Topic[] };

      // Transform topics to preserve UI state - set to collapsed to avoid toggle issues
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      yield actions.setTopics(transformedTopics);
      yield actions.setEditState({
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

      yield actions.setEditState({
        isEditing: false,
        topicId: null,
      });
      throw curriculumError;
    }
  },

  *deleteTopic(topicId: number, courseId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "DELETE_TOPIC_START",
        payload: { topicId },
      };

      // Delete the topic
      yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics/${topicId}`,
          method: "DELETE",
        },
      };

      yield {
        type: "DELETE_TOPIC_SUCCESS",
        payload: { topicId },
      };

      // Fetch updated topics
      const topicsResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
          method: "GET",
        },
      };

      if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
        throw new Error("Invalid topics response");
      }

      const topics = topicsResponse as { data: Topic[] };

      // Transform topics to set all to collapsed after deletion (user preference)
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      yield {
        type: "SET_TOPICS",
        payload: transformedTopics,
      };
    } catch (error) {
      yield {
        type: "DELETE_TOPIC_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to delete topic", "tutorpress"),
            context: {
              action: "deleteTopic",
              details: `Failed to delete topic ${topicId}`,
            },
          },
        },
      };
    }
  },

  *createLesson(data: { title: string; content: string; topic_id: number }): Generator<unknown, void, unknown> {
    try {
      // Start lesson creation
      yield {
        type: "CREATE_LESSON_START",
        payload: { topicId: data.topic_id },
      };

      // Create the lesson
      const lessonResponse = yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/lessons",
          method: "POST",
          data,
        },
      };

      if (!lessonResponse || typeof lessonResponse !== "object") {
        throw new Error("Invalid lesson response");
      }

      const lesson = lessonResponse as Lesson;

      // Get parent info
      const parentInfoResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/lessons/${lesson.id}/parent-info`,
          method: "GET",
        },
      };

      if (!parentInfoResponse || typeof parentInfoResponse !== "object" || !("data" in parentInfoResponse)) {
        throw new Error("Invalid parent info response");
      }

      const parentInfo = parentInfoResponse as { data: { course_id: number } };

      // Update store with new lesson
      yield {
        type: "CREATE_LESSON_SUCCESS",
        payload: { lesson },
      };

      // Fetch updated topics
      const topicsResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${parentInfo.data.course_id}`,
          method: "GET",
        },
      };

      if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
        throw new Error("Invalid topics response");
      }

      const topics = topicsResponse as { data: Topic[] };

      // Transform topics to preserve UI state - set to collapsed to avoid toggle issues
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      // Update topics in store
      yield {
        type: "SET_TOPICS",
        payload: transformedTopics,
      };
    } catch (error) {
      yield {
        type: "CREATE_LESSON_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.CREATION_FAILED,
            message: error instanceof Error ? error.message : "Failed to create lesson",
            context: {
              action: "createLesson",
              topicId: data.topic_id,
            },
          },
        },
      };
    }
  },

  *updateLesson(
    lessonId: number,
    data: Partial<{ title: string; content: string; topic_id: number }>
  ): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "UPDATE_LESSON_START",
        payload: { lessonId },
      };

      // Update the lesson
      const lessonResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/lessons/${lessonId}`,
          method: "PATCH",
          data,
        },
      };

      if (!lessonResponse || typeof lessonResponse !== "object") {
        throw new Error("Invalid lesson response");
      }

      const lesson = lessonResponse as Lesson;

      yield {
        type: "UPDATE_LESSON_SUCCESS",
        payload: { lesson },
      };

      // If topic_id changed, we need to refresh topics
      if (data.topic_id) {
        // Get parent info to get the course ID
        const parentInfoResponse = yield {
          type: "API_FETCH",
          request: {
            path: `/tutorpress/v1/lessons/${lesson.id}/parent-info`,
            method: "GET",
          },
        };

        if (!parentInfoResponse || typeof parentInfoResponse !== "object" || !("data" in parentInfoResponse)) {
          throw new Error("Invalid parent info response");
        }

        const parentInfo = parentInfoResponse as { data: { course_id: number } };

        // Fetch updated topics
        const topicsResponse = yield {
          type: "API_FETCH",
          request: {
            path: `/tutorpress/v1/topics?course_id=${parentInfo.data.course_id}`,
            method: "GET",
          },
        };

        if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
          throw new Error("Invalid topics response");
        }

        const topics = topicsResponse as { data: Topic[] };

        // Transform topics to preserve UI state - set to collapsed to avoid toggle issues
        const transformedTopics = topics.data.map((topic) => ({
          ...topic,
          isCollapsed: true,
          contents: topic.contents || [],
        }));

        yield {
          type: "SET_TOPICS",
          payload: transformedTopics,
        };
      }
    } catch (error) {
      yield {
        type: "UPDATE_LESSON_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.EDIT_FAILED,
            message: error instanceof Error ? error.message : __("Failed to update lesson", "tutorpress"),
            context: {
              action: "updateLesson",
              details: `Failed to update lesson ${lessonId}`,
            },
          },
        },
      };
    }
  },

  *deleteLesson(lessonId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "DELETE_LESSON_START",
        payload: { lessonId },
      };

      // Get parent info before deleting to ensure we can refresh topics after
      const parentInfoResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/lessons/${lessonId}/parent-info`,
          method: "GET",
        },
      };

      if (!parentInfoResponse || typeof parentInfoResponse !== "object" || !("data" in parentInfoResponse)) {
        throw new Error("Invalid parent info response");
      }

      const parentInfo = parentInfoResponse as { data: { course_id: number } };

      // Delete the lesson
      yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/lessons/${lessonId}`,
          method: "DELETE",
        },
      };

      yield {
        type: "DELETE_LESSON_SUCCESS",
        payload: { lessonId },
      };

      // Fetch updated topics
      const topicsResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${parentInfo.data.course_id}`,
          method: "GET",
        },
      };

      if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
        throw new Error("Invalid topics response");
      }

      const topics = topicsResponse as { data: Topic[] };

      // Transform topics to preserve UI state - set to collapsed to avoid toggle issues
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      yield {
        type: "SET_TOPICS",
        payload: transformedTopics,
      };
    } catch (error) {
      yield {
        type: "DELETE_LESSON_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to delete lesson", "tutorpress"),
            context: {
              action: "deleteLesson",
              details: `Failed to delete lesson ${lessonId}`,
            },
          },
        },
      };
    }
  },

  *duplicateLesson(lessonId: number, topicId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "DUPLICATE_LESSON_START",
        payload: { lessonId },
      };

      // Duplicate the lesson
      const lessonResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/lessons/${lessonId}/duplicate`,
          method: "POST",
          data: { topic_id: topicId },
        },
      };

      if (!lessonResponse || typeof lessonResponse !== "object" || !("data" in lessonResponse)) {
        throw new Error("Invalid lesson response");
      }

      const lesson = (lessonResponse as { data: Lesson }).data;

      yield {
        type: "DUPLICATE_LESSON_SUCCESS",
        payload: { lesson, sourceLessonId: lessonId },
      };

      // Get parent info to refresh topics
      const parentInfoResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/lessons/${lesson.id}/parent-info`,
          method: "GET",
        },
      };

      if (!parentInfoResponse || typeof parentInfoResponse !== "object" || !("data" in parentInfoResponse)) {
        throw new Error("Invalid parent info response");
      }

      const parentInfo = parentInfoResponse as { data: { course_id: number } };

      // Fetch updated topics
      const topicsResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${parentInfo.data.course_id}`,
          method: "GET",
        },
      };

      if (!topicsResponse || typeof topicsResponse !== "object" || !("data" in topicsResponse)) {
        throw new Error("Invalid topics response");
      }

      const topics = topicsResponse as { data: Topic[] };

      // Transform topics to preserve UI state - set to collapsed to avoid toggle issues
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      // Update topics in store
      yield {
        type: "SET_TOPICS",
        payload: transformedTopics,
      };
    } catch (error) {
      yield {
        type: "DUPLICATE_LESSON_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.DUPLICATE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to duplicate lesson", "tutorpress"),
            context: {
              action: "duplicateLesson",
              details: `Failed to duplicate lesson ${lessonId}`,
            },
          },
          lessonId,
        },
      };
    }
  },

  *refreshTopicsAfterLessonSave({ courseId }: { courseId: number }): Generator<unknown, void, unknown> {
    if (process.env.NODE_ENV === "development") {
      console.log("Resolver: Refreshing topics after lesson save for course:", courseId);
    }

    try {
      yield actions.setOperationState({ status: "loading" });

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
          method: "GET",
        },
      }) as { data: Topic[] };

      if (!response || !response.data) {
        throw new Error("Invalid response format");
      }

      const topics = response.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
      }));

      yield actions.setTopics(topics);
      yield actions.setOperationState({ status: "success", data: topics });

      if (process.env.NODE_ENV === "development") {
        console.log("Resolver: Successfully refreshed topics after lesson save");
      }
    } catch (error) {
      console.error("Error refreshing topics after lesson save:", error);
      yield actions.setOperationState({
        status: "error",
        error: {
          code: CurriculumErrorCode.FETCH_FAILED,
          message: error instanceof Error ? error.message : "Failed to refresh topics after lesson save",
          context: { action: "refreshTopicsAfterLessonSave" },
        },
      });
    }
  },
};

// Create and register the store
const curriculumStore = createReduxStore("tutorpress/curriculum", {
  reducer,
  actions: {
    ...actions,
    ...resolvers,
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
  setLessonDuplicationState,
  setIsAddingTopic,
  setActiveOperation,
  deleteTopic,
  refreshTopicsAfterLessonSave,
} = actions;

export const {
  getTopics,
  getOperationState,
  getEditState,
  getTopicCreationState,
  getReorderState,
  getDeletionState,
  getDuplicationState,
  getLessonDuplicationState,
  getIsAddingTopic,
  getActiveOperation,
  getLessonState,
  getActiveLessonId,
  isLessonLoading,
  hasLessonError,
  getLessonError,
} = selectors;
