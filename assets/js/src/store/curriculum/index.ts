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
  AssignmentDuplicationState,
  QuizDuplicationState,
  CurriculumError,
  OperationResult,
  TopicActiveOperation,
  CurriculumErrorCode,
  BaseContentItem,
} from "../../types/curriculum";
import type { Lesson } from "../../types/lessons";
import type { Assignment } from "../../types/assignments";
import { QuizForm } from "../../types/quiz";

import { TopicRequest, APIResponse, TopicResponse } from "../../types/api";
import apiFetch from "@wordpress/api-fetch";
import { LiveLesson, LiveLessonFormData, LiveLessonListResponse, LiveLessonApiResponse } from "../../types/liveLessons";
import {
  createRemoveContentPayload,
  createRemoveMultiTypeContentPayload,
  createDuplicateContentPayload,
  createSaveContentPayload,
  createUpdateContentPayload,
  resolveContentType,
  deleteResolvers,
  duplicateResolvers,
  saveUpdateResolvers,
  createOperationWrapper,
  OPERATION_CONFIGS,
  namedQuizSelectors,
  namedLessonSelectors,
  namedLiveLessonSelectors,
  reorderTopicContent,
  contentReorderSelectors,
  ContentReorderOperationState,
  ContentOrder,
} from "./utils";
import { createCurriculumError } from "../../utils/errors";

// Define the store's state interface
export interface CurriculumState {
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
  assignmentDuplicationState: AssignmentDuplicationState;
  quizDuplicationState: QuizDuplicationState;
  quizState: {
    status: "idle" | "saving" | "loading" | "deleting" | "duplicating" | "error" | "success";
    error?: CurriculumError;
    activeQuizId?: number;
    lastSavedQuizId?: number;
  };
  lessonState: {
    status: "idle" | "loading" | "error" | "success";
    error?: CurriculumError;
    activeLessonId?: number;
  };
  assignmentState: {
    status: "idle" | "loading" | "error" | "success";
    error?: CurriculumError;
    activeAssignmentId?: number;
  };
  liveLessonState: {
    status: "idle" | "saving" | "loading" | "deleting" | "duplicating" | "error" | "success";
    error?: CurriculumError;
    activeLiveLessonId?: number;
    lastSavedLiveLessonId?: number;
  };
  liveLessonDuplicationState: {
    status: "idle" | "duplicating" | "error" | "success";
    error?: CurriculumError;
    sourceLiveLessonId?: number;
    duplicatedLiveLessonId?: number;
  };
  contentReorderState: ContentReorderOperationState;
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
  assignmentDuplicationState: { status: "idle" },
  quizDuplicationState: { status: "idle" },
  reorderState: { status: "idle" },
  lessonState: { status: "idle" },
  assignmentState: { status: "idle" },
  liveLessonState: { status: "idle" },
  liveLessonDuplicationState: { status: "idle" },
  contentReorderState: { status: "idle" },
  isAddingTopic: false,
  activeOperation: { type: "none" },
  fetchState: {
    isLoading: false,
    error: null,
    lastFetchedCourseId: null,
  },
  courseId: null,
  quizState: { status: "idle" },
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
  | { type: "SET_ASSIGNMENT_DUPLICATION_STATE"; payload: AssignmentDuplicationState }
  | { type: "SET_QUIZ_DUPLICATION_STATE"; payload: QuizDuplicationState }
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
  | { type: "DELETE_ASSIGNMENT_START"; payload: { assignmentId: number } }
  | { type: "DELETE_ASSIGNMENT_SUCCESS"; payload: { assignmentId: number } }
  | { type: "DELETE_ASSIGNMENT_ERROR"; payload: { error: CurriculumError } }
  | { type: "CREATE_ASSIGNMENT_START"; payload: { topicId: number } }
  | { type: "CREATE_ASSIGNMENT_SUCCESS"; payload: { assignment: Assignment } }
  | { type: "CREATE_ASSIGNMENT_ERROR"; payload: { error: CurriculumError } }
  | { type: "UPDATE_ASSIGNMENT_START"; payload: { assignmentId: number } }
  | { type: "UPDATE_ASSIGNMENT_SUCCESS"; payload: { assignment: Assignment } }
  | { type: "UPDATE_ASSIGNMENT_ERROR"; payload: { error: CurriculumError } }
  | { type: "DUPLICATE_ASSIGNMENT_START"; payload: { assignmentId: number; topicId: number; courseId: number } }
  | {
      type: "DUPLICATE_ASSIGNMENT_SUCCESS";
      payload: { assignment: Assignment; sourceAssignmentId: number; courseId: number };
    }
  | { type: "DUPLICATE_ASSIGNMENT_ERROR"; payload: { error: CurriculumError; assignmentId: number } }
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
  | { type: "DELETE_ASSIGNMENT"; payload: { assignmentId: number } }
  | { type: "CREATE_ASSIGNMENT"; payload: { title: string; content: string; topic_id: number } }
  | {
      type: "UPDATE_ASSIGNMENT";
      payload: { assignmentId: number; data: Partial<{ title: string; content: string; topic_id: number }> };
    }
  | { type: "DELETE_TOPIC"; payload: { topicId: number; courseId: number } }
  | { type: "DUPLICATE_LESSON"; payload: { lessonId: number; topicId: number } }
  | { type: "DUPLICATE_ASSIGNMENT"; payload: { assignmentId: number; topicId: number; courseId: number } }
  | { type: "SET_LESSON_STATE"; payload: CurriculumState["lessonState"] }
  | { type: "SAVE_QUIZ_START"; payload: { quizData: any; courseId: number; topicId: number } }
  | { type: "SAVE_QUIZ_SUCCESS"; payload: { quiz: any; courseId: number } }
  | { type: "SAVE_QUIZ_ERROR"; payload: { error: CurriculumError } }
  | { type: "GET_QUIZ_START"; payload: { quizId: number } }
  | { type: "GET_QUIZ_SUCCESS"; payload: { quiz: any } }
  | { type: "GET_QUIZ_ERROR"; payload: { error: CurriculumError } }
  | { type: "DELETE_QUIZ_START"; payload: { quizId: number } }
  | { type: "DELETE_QUIZ_SUCCESS"; payload: { quizId: number; courseId: number } }
  | { type: "DELETE_QUIZ_ERROR"; payload: { error: CurriculumError } }
  | { type: "DUPLICATE_QUIZ_START"; payload: { quizId: number; topicId: number; courseId: number } }
  | { type: "DUPLICATE_QUIZ_SUCCESS"; payload: { quiz: any; sourceQuizId: number; courseId: number } }
  | { type: "DUPLICATE_QUIZ_ERROR"; payload: { error: CurriculumError; quizId: number } }
  | { type: "SET_QUIZ_STATE"; payload: CurriculumState["quizState"] }
  // Live Lessons Actions
  | {
      type: "SAVE_LIVE_LESSON_START";
      payload: { liveLessonData: LiveLessonFormData; courseId: number; topicId: number };
    }
  | { type: "SAVE_LIVE_LESSON_SUCCESS"; payload: { liveLesson: LiveLesson; courseId: number } }
  | { type: "SAVE_LIVE_LESSON_ERROR"; payload: { error: CurriculumError } }
  | { type: "GET_LIVE_LESSON_START"; payload: { liveLessonId: number } }
  | { type: "GET_LIVE_LESSON_SUCCESS"; payload: { liveLesson: LiveLesson } }
  | { type: "GET_LIVE_LESSON_ERROR"; payload: { error: CurriculumError } }
  | { type: "UPDATE_LIVE_LESSON_START"; payload: { liveLessonId: number; data: Partial<LiveLessonFormData> } }
  | { type: "UPDATE_LIVE_LESSON_SUCCESS"; payload: { liveLesson: LiveLesson; courseId: number } }
  | { type: "UPDATE_LIVE_LESSON_ERROR"; payload: { error: CurriculumError } }
  | { type: "DELETE_LIVE_LESSON_START"; payload: { liveLessonId: number } }
  | { type: "DELETE_LIVE_LESSON_SUCCESS"; payload: { liveLessonId: number; courseId: number } }
  | { type: "DELETE_LIVE_LESSON_ERROR"; payload: { error: CurriculumError } }
  | { type: "DUPLICATE_LIVE_LESSON_START"; payload: { liveLessonId: number; topicId: number; courseId: number } }
  | {
      type: "DUPLICATE_LIVE_LESSON_SUCCESS";
      payload: { liveLesson: LiveLesson; sourceLiveLessonId: number; courseId: number };
    }
  | { type: "DUPLICATE_LIVE_LESSON_ERROR"; payload: { error: CurriculumError; liveLessonId: number } }
  | { type: "SET_LIVE_LESSON_STATE"; payload: CurriculumState["liveLessonState"] }
  | { type: "SET_LIVE_LESSON_DUPLICATION_STATE"; payload: CurriculumState["liveLessonDuplicationState"] }
  | { type: "SET_CONTENT_REORDER_STATE"; payload: ContentReorderOperationState }
  | { type: "REORDER_TOPIC_CONTENT"; payload: { topicId: number; contentOrders: ContentOrder[] } };

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
  setAssignmentDuplicationState(state: AssignmentDuplicationState) {
    return {
      type: "SET_ASSIGNMENT_DUPLICATION_STATE",
      payload: state,
    };
  },
  setQuizDuplicationState(state: QuizDuplicationState) {
    return {
      type: "SET_QUIZ_DUPLICATION_STATE",
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
        // Get the root element data attributes
        const rootElement = document.getElementById("tutorpress-curriculum-builder");
        const postType = rootElement?.dataset.postType || "";
        const isLesson = postType === "lesson";
        const isAssignment = postType === "tutor_assignments";

        // Get the URL parameters to check if this is a new lesson/assignment with topic_id
        const urlParams = new URLSearchParams(window.location.search);
        const isNewContent = urlParams.has("topic_id");

        // Determine the correct API endpoint based on context
        let path: string;
        if (isNewContent) {
          // For new content (lesson or assignment), use the topic endpoint
          path = `/tutorpress/v1/topics/${id}/parent-info`;
        } else {
          // For existing content, use the appropriate endpoint based on type
          if (isAssignment) {
            path = `/tutorpress/v1/assignments/${id}/parent-info`;
          } else {
            path = `/tutorpress/v1/lessons/${id}/parent-info`;
          }
        }

        const response = await apiFetch<ParentInfoResponse>({
          path,
          method: "GET",
        });

        if (!response.success || !response.data?.course_id) {
          throw new Error(response.message || __("Failed to get course ID", "tutorpress"));
        }

        dispatch({ type: "FETCH_COURSE_ID_SUCCESS", payload: { courseId: response.data.course_id } });
      } catch (error) {
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
  saveQuiz(quizData: QuizForm, courseId: number, topicId: number) {
    return {
      type: "SAVE_QUIZ",
      payload: { quizData, courseId, topicId },
    };
  },
  getQuizDetails(quizId: number) {
    return {
      type: "GET_QUIZ",
      payload: { quizId },
    };
  },
  deleteQuiz(quizId: number) {
    return {
      type: "DELETE_QUIZ",
      payload: { quizId },
    };
  },
  duplicateQuiz(quizId: number, topicId: number, courseId: number) {
    return {
      type: "DUPLICATE_QUIZ",
      payload: { quizId, topicId, courseId },
    };
  },
  setQuizState(state: CurriculumState["quizState"]) {
    return {
      type: "SET_QUIZ_STATE",
      payload: state,
    };
  },

  // Live Lessons Actions
  saveLiveLesson(liveLessonData: LiveLessonFormData, courseId: number, topicId: number) {
    return {
      type: "SAVE_LIVE_LESSON",
      payload: { liveLessonData, courseId, topicId },
    };
  },

  getLiveLessonDetails(liveLessonId: number) {
    return {
      type: "GET_LIVE_LESSON",
      payload: { liveLessonId },
    };
  },

  updateLiveLesson(liveLessonId: number, data: Partial<LiveLessonFormData>) {
    return {
      type: "UPDATE_LIVE_LESSON",
      payload: { liveLessonId, data },
    };
  },

  deleteLiveLesson(liveLessonId: number) {
    return {
      type: "DELETE_LIVE_LESSON",
      payload: { liveLessonId },
    };
  },

  duplicateLiveLesson(liveLessonId: number, topicId: number, courseId: number) {
    return {
      type: "DUPLICATE_LIVE_LESSON",
      payload: { liveLessonId, topicId, courseId },
    };
  },

  setLiveLessonState(state: CurriculumState["liveLessonState"]) {
    return {
      type: "SET_LIVE_LESSON_STATE",
      payload: state,
    };
  },

  setLiveLessonDuplicationState(state: CurriculumState["liveLessonDuplicationState"]) {
    return {
      type: "SET_LIVE_LESSON_DUPLICATION_STATE",
      payload: state,
    };
  },
  setContentReorderState(state: ContentReorderOperationState) {
    return {
      type: "SET_CONTENT_REORDER_STATE",
      payload: state,
    };
  },
  reorderTopicContent(topicId: number, contentOrders: ContentOrder[]) {
    return {
      type: "REORDER_TOPIC_CONTENT",
      payload: { topicId, contentOrders },
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
  deleteAssignment(assignmentId: number) {
    return {
      type: "DELETE_ASSIGNMENT",
      payload: { assignmentId },
    };
  },
  createAssignment(data: { title: string; content: string; topic_id: number }) {
    return {
      type: "CREATE_ASSIGNMENT",
      payload: data,
    };
  },
  updateAssignment(assignmentId: number, data: Partial<{ title: string; content: string; topic_id: number }>) {
    return {
      type: "UPDATE_ASSIGNMENT",
      payload: { assignmentId, data },
    };
  },
  duplicateAssignment(assignmentId: number, topicId: number, courseId: number) {
    return {
      type: "DUPLICATE_ASSIGNMENT",
      payload: { assignmentId, topicId, courseId },
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
  getLessonDuplicationState(state: CurriculumState) {
    return state.lessonDuplicationState;
  },
  getAssignmentDuplicationState(state: CurriculumState) {
    return state.assignmentDuplicationState;
  },
  getQuizDuplicationState(state: CurriculumState) {
    return state.quizDuplicationState;
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

  // Lesson selectors - Factory-generated
  getLessonState: namedLessonSelectors.getLessonState,
  getActiveLessonId: namedLessonSelectors.getActiveLessonId,
  isLessonLoading: namedLessonSelectors.isLessonLoading,
  hasLessonError: namedLessonSelectors.hasLessonError,
  getLessonError: namedLessonSelectors.getLessonError,

  // Assignment selectors
  getAssignmentState(state: CurriculumState) {
    return state.assignmentState;
  },
  getActiveAssignmentId(state: CurriculumState) {
    return state.assignmentState.activeAssignmentId;
  },
  isAssignmentLoading(state: CurriculumState) {
    return state.assignmentState.status === "loading";
  },
  hasAssignmentError(state: CurriculumState) {
    return state.assignmentState.status === "error" && !!state.assignmentState.error;
  },
  getAssignmentError(state: CurriculumState) {
    return state.assignmentState.status === "error" ? state.assignmentState.error : null;
  },

  // Quiz selectors - Factory-generated
  getQuizState: namedQuizSelectors.getQuizState,
  getActiveQuizId: namedQuizSelectors.getActiveQuizId,
  getLastSavedQuizId: namedQuizSelectors.getLastSavedQuizId,
  isQuizLoading: namedQuizSelectors.isQuizLoading,
  isQuizSaving: namedQuizSelectors.isQuizSaving,
  isQuizDeleting: namedQuizSelectors.isQuizDeleting,
  isQuizDuplicating: namedQuizSelectors.isQuizDuplicating,
  hasQuizError: namedQuizSelectors.hasQuizError,
  getQuizError: namedQuizSelectors.getQuizError,

  // Live Lesson selectors - Factory-generated
  getLiveLessonState: namedLiveLessonSelectors.getLiveLessonState,
  getActiveLiveLessonId: namedLiveLessonSelectors.getActiveLiveLessonId,
  getLastSavedLiveLessonId: namedLiveLessonSelectors.getLastSavedLiveLessonId,
  isLiveLessonLoading: namedLiveLessonSelectors.isLiveLessonLoading,
  isLiveLessonSaving: namedLiveLessonSelectors.isLiveLessonSaving,
  isLiveLessonDeleting: namedLiveLessonSelectors.isLiveLessonDeleting,
  isLiveLessonDuplicating: namedLiveLessonSelectors.isLiveLessonDuplicating,
  hasLiveLessonError: namedLiveLessonSelectors.hasLiveLessonError,
  getLiveLessonError: namedLiveLessonSelectors.getLiveLessonError,

  // Custom live lesson selector not covered by factory
  getLiveLessonDuplicationState(state: CurriculumState) {
    return state.liveLessonDuplicationState;
  },
  getContentReorderState(state: CurriculumState) {
    return state.contentReorderState;
  },
  isContentReordering(state: CurriculumState) {
    return state.contentReorderState.status === "reordering";
  },
  hasContentReorderError(state: CurriculumState) {
    return state.contentReorderState.status === "error" && !!state.contentReorderState.error;
  },
  getContentReorderError(state: CurriculumState) {
    return state.contentReorderState.status === "error" ? state.contentReorderState.error : null;
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
      const newTopics = handleStateUpdate(state.topics, action.payload);
      return {
        ...state,
        topics: newTopics,
      };
    }

    case "SET_OPERATION_STATE": {
      const newState = handleStateUpdate(state.operationState, action.payload);
      return {
        ...state,
        operationState: newState,
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

    case "SET_LESSON_DUPLICATION_STATE": {
      const newState = handleStateUpdate(state.lessonDuplicationState, action.payload);
      return {
        ...state,
        lessonDuplicationState: newState,
      };
    }

    case "SET_ASSIGNMENT_DUPLICATION_STATE": {
      const newState = handleStateUpdate(state.assignmentDuplicationState, action.payload);
      return {
        ...state,
        assignmentDuplicationState: newState,
      };
    }

    case "SET_QUIZ_DUPLICATION_STATE": {
      const newState = handleStateUpdate(state.quizDuplicationState, action.payload);
      return {
        ...state,
        quizDuplicationState: newState,
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
    case "DELETE_ASSIGNMENT_START":
      return {
        ...state,
        lessonState: {
          status: "loading",
          activeLessonId: action.payload.assignmentId,
        },
      };
    case "DELETE_ASSIGNMENT_SUCCESS":
      return {
        ...state,
        lessonState: {
          status: "success",
          activeLessonId: action.payload.assignmentId,
        },
      };
    case "DELETE_ASSIGNMENT_ERROR":
      return {
        ...state,
        lessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "CREATE_ASSIGNMENT_START":
      return {
        ...state,
        assignmentState: {
          status: "loading",
          activeAssignmentId: action.payload.topicId,
        },
      };
    case "CREATE_ASSIGNMENT_SUCCESS":
      return {
        ...state,
        assignmentState: {
          status: "success",
          activeAssignmentId: action.payload.assignment.id,
        },
      };
    case "CREATE_ASSIGNMENT_ERROR":
      return {
        ...state,
        assignmentState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "CREATE_ASSIGNMENT":
      return {
        ...state,
        assignmentState: {
          status: "loading",
          activeAssignmentId: undefined,
        },
      };
    case "UPDATE_ASSIGNMENT":
      return {
        ...state,
        assignmentState: {
          status: "loading",
          activeAssignmentId: action.payload.assignmentId,
        },
      };
    case "DUPLICATE_ASSIGNMENT":
      return {
        ...state,
        assignmentDuplicationState: {
          status: "duplicating",
          sourceAssignmentId: action.payload.assignmentId,
        },
      };
    case "DELETE_TOPIC":
      return {
        ...state,
        activeOperation: { type: "delete", topicId: action.payload.topicId },
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
        activeOperation: { type: "none" },
      };
    case "DELETE_TOPIC_ERROR":
      return {
        ...state,
        deletionState: {
          status: "error",
          error: action.payload.error,
        },
        activeOperation: { type: "none" },
      };
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

    case "SAVE_QUIZ_START":
      return {
        ...state,
        quizState: { status: "saving" },
      };
    case "SAVE_QUIZ_SUCCESS":
      return {
        ...state,
        quizState: {
          status: "success",
          lastSavedQuizId: action.payload.quiz.id,
        },
      };
    case "SAVE_QUIZ_ERROR":
      return {
        ...state,
        quizState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "GET_QUIZ_START":
      return {
        ...state,
        quizState: { status: "loading" },
      };
    case "GET_QUIZ_SUCCESS":
      return {
        ...state,
        quizState: {
          status: "success",
          activeQuizId: action.payload.quiz.id,
        },
      };
    case "GET_QUIZ_ERROR":
      return {
        ...state,
        quizState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "DELETE_QUIZ_START":
      return {
        ...state,
        quizState: {
          status: "deleting",
          activeQuizId: action.payload.quizId,
        },
      };
    case "DELETE_QUIZ_SUCCESS":
      return {
        ...state,
        quizState: {
          status: "success",
          activeQuizId: undefined,
        },
      };
    case "DELETE_QUIZ_ERROR":
      return {
        ...state,
        quizState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "DUPLICATE_QUIZ_START":
      return {
        ...state,
        quizDuplicationState: {
          status: "duplicating",
          sourceQuizId: action.payload.quizId,
        },
      };
    case "DUPLICATE_QUIZ_SUCCESS":
      return {
        ...state,
        quizDuplicationState: {
          status: "success",
          sourceQuizId: action.payload.sourceQuizId,
          duplicatedQuizId: action.payload.quiz.id,
        },
      };
    case "DUPLICATE_QUIZ_ERROR":
      return {
        ...state,
        quizDuplicationState: {
          status: "error",
          error: action.payload.error,
          sourceQuizId: action.payload.quizId,
        },
      };
    case "SET_QUIZ_STATE":
      return {
        ...state,
        quizState: action.payload,
      };

    // Live Lessons Reducer Cases
    case "SAVE_LIVE_LESSON_START":
      return {
        ...state,
        liveLessonState: { status: "saving" },
      };
    case "SAVE_LIVE_LESSON_SUCCESS":
      return {
        ...state,
        liveLessonState: {
          status: "success",
          lastSavedLiveLessonId: action.payload.liveLesson.id,
        },
      };
    case "SAVE_LIVE_LESSON_ERROR":
      return {
        ...state,
        liveLessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "GET_LIVE_LESSON_START":
      return {
        ...state,
        liveLessonState: { status: "loading" },
      };
    case "GET_LIVE_LESSON_SUCCESS":
      return {
        ...state,
        liveLessonState: {
          status: "success",
          activeLiveLessonId: action.payload.liveLesson.id,
        },
      };
    case "GET_LIVE_LESSON_ERROR":
      return {
        ...state,
        liveLessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "UPDATE_LIVE_LESSON_START":
      return {
        ...state,
        liveLessonState: {
          status: "saving",
          activeLiveLessonId: action.payload.liveLessonId,
        },
      };
    case "UPDATE_LIVE_LESSON_SUCCESS":
      return {
        ...state,
        liveLessonState: {
          status: "success",
          lastSavedLiveLessonId: action.payload.liveLesson.id,
        },
      };
    case "UPDATE_LIVE_LESSON_ERROR":
      return {
        ...state,
        liveLessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "DELETE_LIVE_LESSON_START":
      return {
        ...state,
        liveLessonState: {
          status: "deleting",
          activeLiveLessonId: action.payload.liveLessonId,
        },
      };
    case "DELETE_LIVE_LESSON_SUCCESS":
      return {
        ...state,
        liveLessonState: { status: "success" },
      };
    case "DELETE_LIVE_LESSON_ERROR":
      return {
        ...state,
        liveLessonState: {
          status: "error",
          error: action.payload.error,
        },
      };
    case "DUPLICATE_LIVE_LESSON_START":
      return {
        ...state,
        liveLessonDuplicationState: {
          status: "duplicating",
          sourceLiveLessonId: action.payload.liveLessonId,
        },
      };
    case "DUPLICATE_LIVE_LESSON_SUCCESS":
      return {
        ...state,
        liveLessonDuplicationState: {
          status: "success",
          sourceLiveLessonId: action.payload.sourceLiveLessonId,
          duplicatedLiveLessonId: action.payload.liveLesson.id,
        },
      };
    case "DUPLICATE_LIVE_LESSON_ERROR":
      return {
        ...state,
        liveLessonDuplicationState: {
          status: "error",
          error: action.payload.error,
          sourceLiveLessonId: action.payload.liveLessonId,
        },
      };
    case "SET_LIVE_LESSON_STATE":
      return {
        ...state,
        liveLessonState: action.payload,
      };
    case "SET_LIVE_LESSON_DUPLICATION_STATE":
      return {
        ...state,
        liveLessonDuplicationState: action.payload,
      };

    case "SET_CONTENT_REORDER_STATE":
      return {
        ...state,
        contentReorderState: action.payload,
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
    status: string;
    contents: Array<{
      id: number;
      title: string;
      type: string;
      menu_order: number;
      status: string;
    }>;
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
      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
        },
      }) as { success: boolean; message: string; data: Array<Topic> };

      if (!response.success) {
        throw new Error(response.message || "Failed to fetch topics");
      }

      // Transform topics to include isCollapsed and ensure contents array exists
      const topics = response.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents:
          topic.contents?.map((content, index) => ({
            ...content,
            topic_id: topic.id,
            order: index,
          })) || [],
      }));

      yield actions.setTopics(topics);
      yield actions.setOperationState({ status: "idle" });
    } catch (error) {
      console.error("Error fetching topics:", error);
      yield actions.setOperationState({
        status: "error",
        error: {
          code: CurriculumErrorCode.SERVER_ERROR,
          message: error instanceof Error ? error.message : "Failed to fetch topics",
          context: {
            action: "fetchTopics",
            details: `Failed to fetch topics for course ${courseId}`,
          },
        },
      });
      // Set topics to empty array on error to ensure consistent state
      yield actions.setTopics([]);
    }
  },

  *reorderTopics(courseId: number, topicIds: number[]): Generator<unknown, void, unknown> {
    try {
      yield actions.setReorderState({ status: "reordering" });

      // Reorder topics using API_FETCH
      const reorderResponse = yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/topics/reorder",
          method: "POST",
          data: {
            course_id: courseId,
            topic_orders: topicIds.map((id, index) => ({
              id,
              order: index,
            })),
          },
        },
      };

      if (!reorderResponse || typeof reorderResponse !== "object" || !("success" in reorderResponse)) {
        throw new Error("Invalid reorder response");
      }

      const reorderResult = reorderResponse as { success: boolean; message?: string };
      if (!reorderResult.success) {
        const errorMessage = reorderResult.message || "Failed to reorder topics";
        throw new Error(errorMessage);
      }

      // Fetch updated topics using API_FETCH
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

      // Transform topics to preserve UI state
      const transformedTopics = topics.data.map((topic) => ({
        ...topic,
        isCollapsed: true,
        contents: topic.contents || [],
      }));

      yield actions.setTopics(transformedTopics);
      yield actions.setReorderState({ status: "success" });
    } catch (error) {
      const curriculumError = createCurriculumError(
        error,
        CurriculumErrorCode.REORDER_FAILED,
        "reorderTopics",
        "Failed to reorder topics",
        { courseId }
      );

      yield actions.setReorderState({
        status: "error",
        error: curriculumError,
      });
      throw curriculumError;
    }
  },

  *duplicateTopic(topicId: number, courseId: number): Generator<unknown, void, unknown> {
    try {
      yield actions.setDuplicationState({
        status: "duplicating",
        sourceTopicId: topicId,
      });

      // Duplicate topic using API_FETCH
      const duplicateResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/topics/${topicId}/duplicate`,
          method: "POST",
          data: {
            course_id: courseId,
          },
        },
      };

      if (!duplicateResponse || typeof duplicateResponse !== "object" || !("success" in duplicateResponse)) {
        throw new Error("Invalid duplicate response");
      }

      const duplicateResult = duplicateResponse as { success: boolean; message?: string; data?: TopicResponse };
      if (!duplicateResult.success || !duplicateResult.data) {
        const errorMessage = duplicateResult.message || "Failed to duplicate topic";
        throw new Error(errorMessage);
      }

      const topicData = duplicateResult.data; // Now TypeScript knows this is defined

      // Transform the duplicated topic to match the frontend Topic interface
      const newTopic: Topic = {
        id: topicData.id,
        title: topicData.title,
        content: topicData.content || "",
        menu_order: topicData.menu_order || 0,
        isCollapsed: true,
        contents: (topicData.contents || []).map((item: BaseContentItem) => ({
          ...item,
          topic_id: topicData.id,
          order: 0,
        })),
      };

      // Add the new topic to the existing topics list (efficient single-API-call approach)
      yield actions.setTopics((currentTopics) => [...currentTopics, newTopic]);
      yield actions.setDuplicationState({
        status: "success",
        sourceTopicId: topicId,
        duplicatedTopicId: newTopic.id,
      });
    } catch (error) {
      const curriculumError = createCurriculumError(
        error,
        CurriculumErrorCode.DUPLICATE_FAILED,
        "duplicateTopic",
        "Failed to duplicate topic",
        { topicId, courseId }
      );

      yield actions.setDuplicationState({
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
      const curriculumError = createCurriculumError(
        error,
        CurriculumErrorCode.CREATION_FAILED,
        "createTopic",
        "Failed to create topic",
        { courseId: data.course_id }
      );

      yield actions.setTopicCreationState({
        status: "error",
        error: curriculumError,
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
      const curriculumError = createCurriculumError(
        error,
        CurriculumErrorCode.VALIDATION_ERROR,
        "updateTopic",
        "Failed to update topic",
        { topicId, courseId: data.course_id }
      );

      yield actions.setEditState({
        isEditing: false,
        topicId: null,
      });
      throw curriculumError;
    }
  },

  deleteTopic: deleteResolvers.topic,

  // Wrapped createLesson with automatic START/SUCCESS/ERROR dispatching
  createLesson: createOperationWrapper(
    OPERATION_CONFIGS.createLesson,
    function* (data: {
      title: string;
      content: string;
      topic_id: number;
    }): Generator<unknown, { lesson: Lesson }, unknown> {
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

      return { lesson };
    }
  ),

  // Wrapped updateLesson with automatic START/SUCCESS/ERROR dispatching
  updateLesson: createOperationWrapper(
    OPERATION_CONFIGS.updateLesson,
    function* (
      lessonId: number,
      data: Partial<{ title: string; content: string; topic_id: number }>
    ): Generator<unknown, { lesson: Lesson }, unknown> {
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

      return { lesson };
    }
  ),

  deleteLesson: deleteResolvers.lesson,

  deleteAssignment: deleteResolvers.assignment,

  *createAssignment(data: {
    title: string;
    content: string;
    topic_id: number;
  }): Generator<unknown, Assignment, unknown> {
    try {
      yield {
        type: "CREATE_ASSIGNMENT_START",
        payload: { topicId: data.topic_id },
      };

      // Create the assignment
      const response = yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/assignments",
          method: "POST",
          data,
        },
      };

      if (!response || typeof response !== "object" || !("data" in response)) {
        throw new Error("Invalid assignment creation response");
      }

      const assignment = response.data as Assignment;

      yield {
        type: "CREATE_ASSIGNMENT_SUCCESS",
        payload: { assignment },
      };

      // Update topics directly to add the new assignment (preserves toggle states)
      yield {
        type: "SET_TOPICS",
        payload: (currentTopics: Topic[]) => {
          return currentTopics.map((topic) => {
            if (topic.id === data.topic_id) {
              return {
                ...topic,
                contents: [
                  ...(topic.contents || []),
                  {
                    id: assignment.id,
                    title: assignment.title,
                    type: "tutor_assignments",
                    topic_id: data.topic_id,
                    order: (topic.contents?.length || 0) + 1,
                    menu_order: (topic.contents?.length || 0) + 1,
                    status: "publish",
                  },
                ],
              };
            }
            return topic;
          });
        },
      };

      return assignment;
    } catch (error) {
      yield {
        type: "CREATE_ASSIGNMENT_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.SAVE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to create assignment", "tutorpress"),
            context: {
              action: "createAssignment",
              details: `Failed to create assignment for topic ${data.topic_id}`,
            },
          },
        },
      };
      throw error;
    }
  },

  // Wrapped updateAssignment with automatic START/SUCCESS/ERROR dispatching
  updateAssignment: createOperationWrapper(
    OPERATION_CONFIGS.updateAssignment,
    function* (
      assignmentId: number,
      data: Partial<{ title: string; content: string; topic_id: number }>
    ): Generator<unknown, { assignment: Assignment }, unknown> {
      // Update the assignment
      const assignmentResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/assignments/${assignmentId}`,
          method: "PATCH",
          data,
        },
      };

      if (!assignmentResponse || typeof assignmentResponse !== "object" || !("data" in assignmentResponse)) {
        throw new Error("Invalid assignment response");
      }

      const assignment = assignmentResponse.data as Assignment;

      // If topic_id changed, we need to refresh topics
      if (data.topic_id) {
        // Get parent info to get the course ID
        const parentInfoResponse = yield {
          type: "API_FETCH",
          request: {
            path: `/tutorpress/v1/assignments/${assignment.id}/parent-info`,
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
      } else {
        // Update topics directly to reflect the updated assignment (preserves toggle states)
        yield {
          type: "SET_TOPICS",
          payload: (currentTopics: Topic[]) => {
            return currentTopics.map((topic) => {
              const assignmentIndex = topic.contents?.findIndex((item) => item.id === assignmentId) ?? -1;
              if (assignmentIndex >= 0) {
                const updatedContents = [...(topic.contents || [])];
                updatedContents[assignmentIndex] = {
                  ...updatedContents[assignmentIndex],
                  title: assignment.title,
                };
                return {
                  ...topic,
                  contents: updatedContents,
                };
              }
              return topic;
            });
          },
        };
      }

      return { assignment };
    }
  ),

  *duplicateLesson(lessonId: number, topicId: number): Generator<unknown, Lesson, unknown> {
    const duplicatedLesson = yield* duplicateResolvers.lesson(lessonId, topicId);
    return duplicatedLesson;
  },

  *duplicateAssignment(
    assignmentId: number,
    topicId: number,
    courseId: number
  ): Generator<unknown, Assignment, unknown> {
    const duplicatedAssignment = yield* duplicateResolvers.assignment(assignmentId, topicId, courseId);
    return duplicatedAssignment;
  },

  *saveQuiz(quizData: QuizForm, courseId: number, topicId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "SAVE_QUIZ_START",
        payload: { quizData, courseId, topicId },
      };

      // Sanitize quiz data similar to the original quiz service
      const sanitizedQuizData = {
        ...quizData,
        questions: quizData.questions.map((question) => {
          const { question_settings, ...questionBase } = question;

          const serializedSettings = {
            question_type: question.question_type,
            answer_required: question_settings.answer_required ? 1 : 0,
            randomize_question: question_settings.randomize_question ? 1 : 0,
            question_mark: question.question_mark,
            show_question_mark: question_settings.show_question_mark ? 1 : 0,
            has_multiple_correct_answer: question_settings.has_multiple_correct_answer ? 1 : 0,
            is_image_matching: question_settings.is_image_matching ? 1 : 0,
          };

          return {
            ...questionBase,
            question_settings: serializedSettings,
            answer_required: question_settings.answer_required ? 1 : 0,
            randomize_question: question_settings.randomize_question ? 1 : 0,
            show_question_mark: question_settings.show_question_mark ? 1 : 0,
            question_answers: question.question_answers.map((answer) => ({
              ...answer,
            })),
          };
        }),
      };

      // Get Tutor LMS nonce
      const tutorObject = (window as any)._tutorobject;
      const ajaxUrl = tutorObject?.ajaxurl || "/wp-admin/admin-ajax.php";
      const nonce = tutorObject?._tutor_nonce || "";

      // Prepare FormData for Tutor LMS AJAX endpoint
      const formData = new FormData();
      formData.append("action", "tutor_quiz_builder_save");
      formData.append("_tutor_nonce", nonce);
      formData.append("payload", JSON.stringify(sanitizedQuizData));
      formData.append("course_id", courseId.toString());
      formData.append("topic_id", topicId.toString());

      // Add deleted IDs if they exist
      if (sanitizedQuizData.deleted_question_ids && sanitizedQuizData.deleted_question_ids.length > 0) {
        sanitizedQuizData.deleted_question_ids.forEach((id: number, index: number) => {
          formData.append(`deleted_question_ids[${index}]`, id.toString());
        });
      }

      if (sanitizedQuizData.deleted_answer_ids && sanitizedQuizData.deleted_answer_ids.length > 0) {
        sanitizedQuizData.deleted_answer_ids.forEach((id: number, index: number) => {
          formData.append(`deleted_answer_ids[${index}]`, id.toString());
        });
      }

      // Use API_FETCH control to call Tutor LMS AJAX endpoint
      const response = yield {
        type: "API_FETCH",
        request: {
          url: ajaxUrl,
          method: "POST",
          body: formData,
        },
      };

      console.log("Tutor LMS AJAX Response:", response);

      // Parse Tutor LMS AJAX response which is typically a JSON string
      let parsedResponse;
      try {
        if (typeof response === "string") {
          parsedResponse = JSON.parse(response);
        } else {
          parsedResponse = response;
        }
      } catch (parseError) {
        console.error("Failed to parse Tutor LMS response:", response);
        throw new Error("Invalid response format from Tutor LMS");
      }

      // Check if the response indicates success
      if (!parsedResponse || (!parsedResponse.success && !parsedResponse.data)) {
        throw new Error(parsedResponse?.message || "Failed to save quiz");
      }

      const savedQuiz = parsedResponse.data || parsedResponse;

      yield {
        type: "SAVE_QUIZ_SUCCESS",
        payload: { quiz: savedQuiz, courseId },
      };

      // Update topics directly to add/update the quiz - preserves toggle states
      yield {
        type: "SET_TOPICS",
        payload: (currentTopics: Topic[]) => {
          return currentTopics.map((topic) => {
            if (topic.id === topicId) {
              // Determine correct content type based on quiz_type in quiz_option
              const quizType = (sanitizedQuizData.quiz_option as any)?.quiz_type;
              const isInteractiveQuiz = quizType === "tutor_h5p_quiz";
              const contentType = isInteractiveQuiz ? "interactive_quiz" : "tutor_quiz";

              // Check if quiz already exists (editing) or is new
              const existingQuizIndex = topic.contents?.findIndex((item) => item.id === savedQuiz.ID) ?? -1;

              if (existingQuizIndex >= 0) {
                // Update existing quiz
                const updatedContents = [...(topic.contents || [])];
                updatedContents[existingQuizIndex] = {
                  ...updatedContents[existingQuizIndex],
                  title: savedQuiz.post_title || sanitizedQuizData.post_title,
                };

                return {
                  ...topic,
                  contents: updatedContents,
                };
              } else {
                // Add new quiz
                return {
                  ...topic,
                  contents: [
                    ...(topic.contents || []),
                    {
                      id: savedQuiz.ID,
                      title: savedQuiz.post_title || sanitizedQuizData.post_title,
                      type: contentType,
                      topic_id: topicId,
                      order: (topic.contents?.length || 0) + 1,
                      menu_order: (topic.contents?.length || 0) + 1,
                      status: "publish",
                    },
                  ],
                };
              }
            }
            return topic;
          });
        },
      };
    } catch (error) {
      console.error("Quiz save error:", error);
      yield {
        type: "SAVE_QUIZ_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.CREATE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to save quiz", "tutorpress"),
            context: {
              action: "saveQuiz",
              details: "Failed to save quiz to Tutor LMS",
            },
          },
        },
      };
    }
  },

  *getQuizDetails(quizId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "GET_QUIZ_START",
        payload: { quizId },
      };

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/quizzes/${quizId}`,
          method: "GET",
        },
      };

      if (!response || typeof response !== "object" || !("data" in response)) {
        throw new Error("Invalid quiz details response");
      }

      const quiz = (response as { data: any }).data;

      yield {
        type: "GET_QUIZ_SUCCESS",
        payload: { quiz },
      };
    } catch (error) {
      yield {
        type: "GET_QUIZ_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.FETCH_FAILED,
            message: error instanceof Error ? error.message : __("Failed to get quiz details", "tutorpress"),
            context: {
              action: "getQuizDetails",
              details: `Failed to get quiz ${quizId}`,
            },
          },
        },
      };
    }
  },

  deleteQuiz: deleteResolvers.quiz,

  duplicateQuiz: duplicateResolvers.quiz,

  // Live Lessons Resolvers using API_FETCH control pattern
  // saveLiveLesson: Uses factory-generated resolver (see end of resolvers object)

  *getLiveLessonDetails(liveLessonId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "GET_LIVE_LESSON_START",
        payload: { liveLessonId },
      };

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/live-lessons/${liveLessonId}`,
          method: "GET",
        },
      };

      if (!response || typeof response !== "object" || !("data" in response)) {
        throw new Error("Invalid live lesson details response");
      }

      const liveLesson = (response as LiveLessonApiResponse).data as LiveLesson;

      yield {
        type: "GET_LIVE_LESSON_SUCCESS",
        payload: { liveLesson },
      };
    } catch (error) {
      yield {
        type: "GET_LIVE_LESSON_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.FETCH_FAILED,
            message: error instanceof Error ? error.message : __("Failed to fetch live lesson details", "tutorpress"),
            context: {
              action: "getLiveLessonDetails",
              details: `Failed to fetch live lesson ${liveLessonId}`,
            },
          },
        },
      };
    }
  },

  // updateLiveLesson: Uses factory-generated resolver (see end of resolvers object)

  deleteLiveLesson: deleteResolvers.liveLesson,

  duplicateLiveLesson: duplicateResolvers.liveLesson,

  saveLiveLesson: saveUpdateResolvers.saveLiveLesson,

  updateLiveLesson: saveUpdateResolvers.updateLiveLesson,

  reorderTopicContent,
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

export { curriculumStore };

// Export actions
export const {
  setTopics,
  setOperationState,
  setEditState,
  setTopicCreationState,
  setReorderState,
  setDeletionState,
  setDuplicationState,
  setLessonDuplicationState,
  setAssignmentDuplicationState,
  setIsAddingTopic,
  setActiveOperation,
  deleteTopic,
  saveQuiz,
  getQuizDetails,
  deleteQuiz,
  duplicateQuiz,
  setQuizState,
} = actions;

// Export selectors - Updated to use factory-generated names
export const {
  getTopics,
  getOperationState,
  getEditState,
  getTopicCreationState,
  getReorderState,
  getDeletionState,
  getDuplicationState,
  getLessonDuplicationState,
  getAssignmentDuplicationState,
  getQuizDuplicationState,
  getIsAddingTopic,
  getActiveOperation,
  // Factory-generated lesson selectors
  getLessonState,
  getActiveLessonId,
  isLessonLoading,
  hasLessonError,
  getLessonError,
  // Factory-generated quiz selectors
  getQuizState,
  getActiveQuizId,
  getLastSavedQuizId,
  isQuizLoading,
  isQuizSaving,
  isQuizDeleting,
  isQuizDuplicating,
  hasQuizError,
  getQuizError,
  // Factory-generated live lesson selectors
  getLiveLessonState,
  getActiveLiveLessonId,
  getLastSavedLiveLessonId,
  isLiveLessonLoading,
  isLiveLessonSaving,
  isLiveLessonDeleting,
  isLiveLessonDuplicating,
  hasLiveLessonError,
  getLiveLessonError,
  // Custom selectors not covered by factory
  getLiveLessonDuplicationState,
  // Content reorder selectors
  getContentReorderState,
  isContentReordering,
  hasContentReorderError,
  getContentReorderError,
} = selectors;
