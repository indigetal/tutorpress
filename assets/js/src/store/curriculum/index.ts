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
} from "../../types/curriculum";
import type { Lesson } from "../../types/lessons";
import { QuizForm } from "../../types/quiz";
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
import { deleteAssignment as apiDeleteAssignment } from "../../api/assignments";
import { TopicRequest } from "../../types/api";
import apiFetch from "@wordpress/api-fetch";
import {
  H5PContentState,
  H5PStatementState,
  H5PValidationState,
  H5PResultsState,
  H5PContentSearchParams,
  H5PContent,
  H5PQuestionStatement,
  H5PQuestionValidation,
  H5PQuizResult,
  H5PContentResponse,
  H5PStatementSaveResponse,
  H5PValidationResponse,
  H5PQuizResultResponse,
  H5PError,
  H5PErrorCode,
} from "../../types/h5p";

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
  isAddingTopic: boolean;
  activeOperation: { type: string; topicId?: number };
  fetchState: {
    isLoading: boolean;
    error: Error | null;
    lastFetchedCourseId: number | null;
  };
  courseId: number | null;
  h5pContent: H5PContentState;
  h5pStatements: H5PStatementState;
  h5pValidation: H5PValidationState;
  h5pResults: H5PResultsState;
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
  isAddingTopic: false,
  activeOperation: { type: "none" },
  fetchState: {
    isLoading: false,
    error: null,
    lastFetchedCourseId: null,
  },
  courseId: null,
  quizState: { status: "idle" },
  h5pContent: {
    contents: [],
    selectedContent: null,
    searchParams: {},
    pagination: null,
    operationState: { status: "idle" },
  },
  h5pStatements: {
    statements: [],
    operationState: { status: "idle" },
  },
  h5pValidation: {
    validationResults: {},
    operationState: { status: "idle" },
  },
  h5pResults: {
    results: {},
    operationState: { status: "idle" },
  },
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
  | { type: "DELETE_TOPIC"; payload: { topicId: number; courseId: number } }
  | { type: "DUPLICATE_LESSON"; payload: { lessonId: number; topicId: number } }
  | { type: "SET_LESSON_STATE"; payload: CurriculumState["lessonState"] }
  | { type: "REFRESH_TOPICS_AFTER_LESSON_SAVE"; payload: { courseId: number } }
  | { type: "REFRESH_TOPICS_AFTER_ASSIGNMENT_SAVE"; payload: { courseId: number } }
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
  | { type: "REFRESH_TOPICS_AFTER_QUIZ_SAVE"; payload: { courseId: number } }
  // H5P Actions
  | { type: "FETCH_H5P_CONTENTS_START"; payload: { searchParams: H5PContentSearchParams } }
  | { type: "FETCH_H5P_CONTENTS_SUCCESS"; payload: { contents: H5PContent[]; pagination?: any } }
  | { type: "FETCH_H5P_CONTENTS_ERROR"; payload: { error: H5PError } }
  | { type: "SET_H5P_SELECTED_CONTENT"; payload: { content: H5PContent | null } }
  | { type: "SET_H5P_SEARCH_PARAMS"; payload: { searchParams: H5PContentSearchParams } }
  | { type: "SAVE_H5P_STATEMENT_START"; payload: { statement: H5PQuestionStatement } }
  | { type: "SAVE_H5P_STATEMENT_SUCCESS"; payload: { statement: any; statementId: number } }
  | { type: "SAVE_H5P_STATEMENT_ERROR"; payload: { error: H5PError } }
  | { type: "VALIDATE_H5P_ANSWERS_START"; payload: { validation: H5PQuestionValidation } }
  | { type: "VALIDATE_H5P_ANSWERS_SUCCESS"; payload: { results: Record<number, boolean> } }
  | { type: "VALIDATE_H5P_ANSWERS_ERROR"; payload: { error: H5PError } }
  | { type: "FETCH_H5P_RESULTS_START"; payload: { resultParams: H5PQuizResult } }
  | { type: "FETCH_H5P_RESULTS_SUCCESS"; payload: { results: H5PQuizResultResponse; resultKey: string } }
  | { type: "FETCH_H5P_RESULTS_ERROR"; payload: { error: H5PError } };

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
        // Get the URL parameters to check if this is a new lesson/assignment with topic_id
        const urlParams = new URLSearchParams(window.location.search);
        const isNewContent = urlParams.has("topic_id");

        // Get the context from the localized script data
        const isAssignment = (window as any).tutorPressCurriculum?.isAssignment;

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
  refreshTopicsAfterLessonSave(courseId: number) {
    return {
      type: "REFRESH_TOPICS_AFTER_LESSON_SAVE",
      payload: { courseId },
    };
  },
  refreshTopicsAfterAssignmentSave(courseId: number) {
    return {
      type: "REFRESH_TOPICS_AFTER_ASSIGNMENT_SAVE",
      payload: { courseId },
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
  refreshTopicsAfterQuizSave(courseId: number) {
    return {
      type: "REFRESH_TOPICS_AFTER_QUIZ_SAVE",
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
  deleteAssignment(assignmentId: number) {
    return {
      type: "DELETE_ASSIGNMENT",
      payload: { assignmentId },
    };
  },
  // H5P Action Creators
  fetchH5PContents(searchParams: H5PContentSearchParams) {
    return {
      type: "FETCH_H5P_CONTENTS",
      payload: { searchParams },
    };
  },
  setH5PSelectedContent(content: H5PContent | null) {
    return {
      type: "SET_H5P_SELECTED_CONTENT",
      payload: { content },
    };
  },
  setH5PSearchParams(searchParams: H5PContentSearchParams) {
    return {
      type: "SET_H5P_SEARCH_PARAMS",
      payload: { searchParams },
    };
  },
  saveH5PStatement(statement: H5PQuestionStatement) {
    return {
      type: "SAVE_H5P_STATEMENT",
      payload: { statement },
    };
  },
  validateH5PAnswers(validation: H5PQuestionValidation) {
    return {
      type: "VALIDATE_H5P_ANSWERS",
      payload: { validation },
    };
  },
  fetchH5PResults(resultParams: H5PQuizResult) {
    return {
      type: "FETCH_H5P_RESULTS",
      payload: { resultParams },
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
  getQuizState(state: CurriculumState) {
    return state.quizState;
  },
  getActiveQuizId(state: CurriculumState) {
    return state.quizState.activeQuizId;
  },
  getLastSavedQuizId(state: CurriculumState) {
    return state.quizState.lastSavedQuizId;
  },
  isQuizLoading(state: CurriculumState) {
    return state.quizState.status === "loading";
  },
  isQuizSaving(state: CurriculumState) {
    return state.quizState.status === "saving";
  },
  isQuizDeleting(state: CurriculumState) {
    return state.quizState.status === "deleting";
  },
  isQuizDuplicating(state: CurriculumState) {
    return state.quizState.status === "duplicating";
  },
  hasQuizError(state: CurriculumState) {
    return state.quizState.status === "error";
  },
  getQuizError(state: CurriculumState) {
    return state.quizState.error;
  },
  // H5P Selectors
  getH5PContents(state: CurriculumState) {
    return state.h5pContent.contents;
  },
  getH5PSelectedContent(state: CurriculumState) {
    return state.h5pContent.selectedContent;
  },
  getH5PSearchParams(state: CurriculumState) {
    return state.h5pContent.searchParams;
  },
  getH5PPagination(state: CurriculumState) {
    return state.h5pContent.pagination;
  },
  getH5PContentOperationState(state: CurriculumState) {
    return state.h5pContent.operationState;
  },
  isH5PContentLoading(state: CurriculumState) {
    return state.h5pContent.operationState.status === "loading";
  },
  hasH5PContentError(state: CurriculumState) {
    return state.h5pContent.operationState.status === "error";
  },
  getH5PContentError(state: CurriculumState) {
    return state.h5pContent.operationState.error;
  },
  getH5PStatements(state: CurriculumState) {
    return state.h5pStatements.statements;
  },
  getH5PStatementOperationState(state: CurriculumState) {
    return state.h5pStatements.operationState;
  },
  isH5PStatementSaving(state: CurriculumState) {
    return state.h5pStatements.operationState.status === "saving";
  },
  hasH5PStatementError(state: CurriculumState) {
    return state.h5pStatements.operationState.status === "error";
  },
  getH5PStatementError(state: CurriculumState) {
    return state.h5pStatements.operationState.error;
  },
  getH5PValidationResults(state: CurriculumState) {
    return state.h5pValidation.validationResults;
  },
  getH5PValidationOperationState(state: CurriculumState) {
    return state.h5pValidation.operationState;
  },
  isH5PValidating(state: CurriculumState) {
    return state.h5pValidation.operationState.status === "validating";
  },
  hasH5PValidationError(state: CurriculumState) {
    return state.h5pValidation.operationState.status === "error";
  },
  getH5PValidationError(state: CurriculumState) {
    return state.h5pValidation.operationState.error;
  },
  getH5PResults(state: CurriculumState) {
    return state.h5pResults.results;
  },
  getH5PResultsOperationState(state: CurriculumState) {
    return state.h5pResults.operationState;
  },
  isH5PResultsLoading(state: CurriculumState) {
    return state.h5pResults.operationState.status === "loading";
  },
  hasH5PResultsError(state: CurriculumState) {
    return state.h5pResults.operationState.status === "error";
  },
  getH5PResultsError(state: CurriculumState) {
    return state.h5pResults.operationState.error;
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
    case "REFRESH_TOPICS_AFTER_ASSIGNMENT_SAVE":
      return {
        ...state,
        operationState: { status: "loading" },
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
    case "REFRESH_TOPICS_AFTER_QUIZ_SAVE":
      return {
        ...state,
        operationState: { status: "loading" },
      };
    // H5P Reducer Cases
    case "FETCH_H5P_CONTENTS_START":
      return {
        ...state,
        h5pContent: {
          ...state.h5pContent,
          searchParams: action.payload.searchParams,
          operationState: { status: "loading" },
        },
      };
    case "FETCH_H5P_CONTENTS_SUCCESS":
      return {
        ...state,
        h5pContent: {
          ...state.h5pContent,
          contents: action.payload.contents,
          pagination: action.payload.pagination || null,
          operationState: { status: "success" },
        },
      };
    case "FETCH_H5P_CONTENTS_ERROR":
      return {
        ...state,
        h5pContent: {
          ...state.h5pContent,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
      };
    case "SET_H5P_SELECTED_CONTENT":
      return {
        ...state,
        h5pContent: {
          ...state.h5pContent,
          selectedContent: action.payload.content,
        },
      };
    case "SET_H5P_SEARCH_PARAMS":
      return {
        ...state,
        h5pContent: {
          ...state.h5pContent,
          searchParams: action.payload.searchParams,
        },
      };
    case "SAVE_H5P_STATEMENT_START":
      return {
        ...state,
        h5pStatements: {
          ...state.h5pStatements,
          operationState: { status: "saving" },
        },
      };
    case "SAVE_H5P_STATEMENT_SUCCESS":
      return {
        ...state,
        h5pStatements: {
          ...state.h5pStatements,
          statements: [...state.h5pStatements.statements, action.payload.statement],
          operationState: { status: "success" },
        },
      };
    case "SAVE_H5P_STATEMENT_ERROR":
      return {
        ...state,
        h5pStatements: {
          ...state.h5pStatements,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
      };
    case "VALIDATE_H5P_ANSWERS_START":
      return {
        ...state,
        h5pValidation: {
          ...state.h5pValidation,
          operationState: { status: "validating" },
        },
      };
    case "VALIDATE_H5P_ANSWERS_SUCCESS":
      return {
        ...state,
        h5pValidation: {
          ...state.h5pValidation,
          validationResults: action.payload.results,
          operationState: { status: "success" },
        },
      };
    case "VALIDATE_H5P_ANSWERS_ERROR":
      return {
        ...state,
        h5pValidation: {
          ...state.h5pValidation,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
      };
    case "FETCH_H5P_RESULTS_START":
      return {
        ...state,
        h5pResults: {
          ...state.h5pResults,
          operationState: { status: "loading" },
        },
      };
    case "FETCH_H5P_RESULTS_SUCCESS":
      return {
        ...state,
        h5pResults: {
          ...state.h5pResults,
          results: {
            ...state.h5pResults.results,
            [action.payload.resultKey]: action.payload.results,
          },
          operationState: { status: "success" },
        },
      };
    case "FETCH_H5P_RESULTS_ERROR":
      return {
        ...state,
        h5pResults: {
          ...state.h5pResults,
          operationState: {
            status: "error",
            error: action.payload.error,
          },
        },
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

  *deleteAssignment(assignmentId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "DELETE_ASSIGNMENT_START",
        payload: { assignmentId },
      };

      // Get parent info before deleting to ensure we can refresh topics after
      const parentInfoResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/assignments/${assignmentId}/parent-info`,
          method: "GET",
        },
      };

      if (!parentInfoResponse || typeof parentInfoResponse !== "object" || !("data" in parentInfoResponse)) {
        throw new Error("Invalid parent info response");
      }

      const parentInfo = parentInfoResponse as { data: { course_id: number } };

      // Delete the assignment
      yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/assignments/${assignmentId}`,
          method: "DELETE",
        },
      };

      yield {
        type: "DELETE_ASSIGNMENT_SUCCESS",
        payload: { assignmentId },
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
        type: "DELETE_ASSIGNMENT_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to delete assignment", "tutorpress"),
            context: {
              action: "deleteAssignment",
              details: `Failed to delete assignment ${assignmentId}`,
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
    } catch (error) {
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

  *refreshTopicsAfterAssignmentSave({ courseId }: { courseId: number }): Generator<unknown, void, unknown> {
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
    } catch (error) {
      yield actions.setOperationState({
        status: "error",
        error: {
          code: CurriculumErrorCode.FETCH_FAILED,
          message: error instanceof Error ? error.message : "Failed to refresh topics after assignment save",
          context: { action: "refreshTopicsAfterAssignmentSave" },
        },
      });
    }
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

      yield {
        type: "SAVE_QUIZ_SUCCESS",
        payload: { quiz: parsedResponse.data || parsedResponse, courseId },
      };

      // Refresh topics to show the new/updated quiz
      try {
        yield actions.setOperationState({ status: "loading" });

        const topicsResponse = (yield {
          type: "API_FETCH",
          request: {
            path: `/tutorpress/v1/topics?course_id=${courseId}`,
            method: "GET",
          },
        }) as { data: Topic[] };

        if (topicsResponse && topicsResponse.data) {
          const topics = topicsResponse.data.map((topic) => ({
            ...topic,
            isCollapsed: true,
          }));

          yield actions.setTopics(topics);
          yield actions.setOperationState({ status: "success", data: topics });
        }
      } catch (refreshError) {
        console.warn("Failed to refresh topics after quiz save:", refreshError);
        // Don't throw here as the quiz was saved successfully
      }
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

  *deleteQuiz(quizId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "DELETE_QUIZ_START",
        payload: { quizId },
      };

      // Get parent info first to refresh topics later
      const parentInfoResponse = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/quizzes/${quizId}/parent-info`,
          method: "GET",
        },
      };

      if (!parentInfoResponse || typeof parentInfoResponse !== "object" || !("data" in parentInfoResponse)) {
        throw new Error("Invalid parent info response");
      }

      const parentInfo = parentInfoResponse as { data: { course_id: number } };

      // Delete the quiz
      yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/quizzes/${quizId}`,
          method: "DELETE",
        },
      };

      yield {
        type: "DELETE_QUIZ_SUCCESS",
        payload: { quizId, courseId: parentInfo.data.course_id },
      };

      // Refresh topics to remove the deleted quiz
      try {
        yield actions.setOperationState({ status: "loading" });

        const topicsResponse = (yield {
          type: "API_FETCH",
          request: {
            path: `/tutorpress/v1/topics?course_id=${parentInfo.data.course_id}`,
            method: "GET",
          },
        }) as { data: Topic[] };

        if (topicsResponse && topicsResponse.data) {
          const topics = topicsResponse.data.map((topic) => ({
            ...topic,
            isCollapsed: true,
          }));

          yield actions.setTopics(topics);
          yield actions.setOperationState({ status: "success", data: topics });
        }
      } catch (refreshError) {
        console.warn("Failed to refresh topics after quiz delete:", refreshError);
      }
    } catch (error) {
      yield {
        type: "DELETE_QUIZ_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.DELETE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to delete quiz", "tutorpress"),
            context: {
              action: "deleteQuiz",
              details: `Failed to delete quiz ${quizId}`,
            },
          },
        },
      };
    }
  },

  *duplicateQuiz(quizId: number, topicId: number, courseId: number): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "DUPLICATE_QUIZ_START",
        payload: { quizId, topicId, courseId },
      };

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/quizzes/${quizId}/duplicate`,
          method: "POST",
          data: { topic_id: topicId, course_id: courseId },
        },
      };

      if (!response || typeof response !== "object" || !("data" in response)) {
        throw new Error("Invalid quiz duplication response");
      }

      const quiz = (response as { data: any }).data;

      yield {
        type: "DUPLICATE_QUIZ_SUCCESS",
        payload: { quiz, sourceQuizId: quizId, courseId },
      };

      // Refresh topics to show the duplicated quiz
      try {
        yield actions.setOperationState({ status: "loading" });

        const topicsResponse = (yield {
          type: "API_FETCH",
          request: {
            path: `/tutorpress/v1/topics?course_id=${courseId}`,
            method: "GET",
          },
        }) as { data: Topic[] };

        if (topicsResponse && topicsResponse.data) {
          const topics = topicsResponse.data.map((topic) => ({
            ...topic,
            isCollapsed: true,
          }));

          yield actions.setTopics(topics);
          yield actions.setOperationState({ status: "success", data: topics });
        }
      } catch (refreshError) {
        console.warn("Failed to refresh topics after quiz duplicate:", refreshError);
      }
    } catch (error) {
      yield {
        type: "DUPLICATE_QUIZ_ERROR",
        payload: {
          error: {
            code: CurriculumErrorCode.DUPLICATE_FAILED,
            message: error instanceof Error ? error.message : __("Failed to duplicate quiz", "tutorpress"),
            context: {
              action: "duplicateQuiz",
              details: `Failed to duplicate quiz ${quizId}`,
            },
          },
          quizId,
        },
      };
    }
  },

  *refreshTopicsAfterQuizSave({ courseId }: { courseId: number }): Generator<unknown, void, unknown> {
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
    } catch (error) {
      yield actions.setOperationState({
        status: "error",
        error: {
          code: CurriculumErrorCode.FETCH_FAILED,
          message: error instanceof Error ? error.message : "Failed to refresh topics after quiz save",
          context: { action: "refreshTopicsAfterQuizSave" },
        },
      });
    }
  },

  // H5P Resolvers using API_FETCH control pattern
  *fetchH5PContents(searchParams: H5PContentSearchParams): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "FETCH_H5P_CONTENTS_START",
        payload: { searchParams },
      };

      // Build query string from search parameters
      const queryParams = new URLSearchParams();
      if (searchParams.search) {
        queryParams.append("search", searchParams.search);
      }
      if (searchParams.contentType) {
        queryParams.append("content_type", searchParams.contentType);
      }
      if (searchParams.per_page) {
        queryParams.append("per_page", searchParams.per_page.toString());
      }
      if (searchParams.page) {
        queryParams.append("page", searchParams.page.toString());
      }
      if (searchParams.order) {
        queryParams.append("order", searchParams.order);
      }
      if (searchParams.orderby) {
        queryParams.append("orderby", searchParams.orderby);
      }

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/h5p/contents?${queryParams.toString()}`,
          method: "GET",
        },
      }) as H5PContentResponse;

      if (!response || !response.items) {
        throw new Error("Failed to fetch H5P contents");
      }

      yield {
        type: "FETCH_H5P_CONTENTS_SUCCESS",
        payload: {
          contents: response.items,
          pagination: {
            total: response.total,
            total_pages: response.total_pages,
            current_page: response.page,
            per_page: response.per_page,
          },
        },
      };
    } catch (error) {
      yield {
        type: "FETCH_H5P_CONTENTS_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.SERVER_ERROR,
            message: error instanceof Error ? error.message : "Failed to fetch H5P contents",
            context: { action: "fetchH5PContents", searchParams },
          },
        },
      };
    }
  },

  *saveH5PStatement(statement: H5PQuestionStatement): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "SAVE_H5P_STATEMENT_START",
        payload: { statement },
      };

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/h5p/statements",
          method: "POST",
          data: statement,
        },
      }) as H5PStatementSaveResponse;

      if (!response || !response.success) {
        throw new Error(response?.message || "Failed to save H5P statement");
      }

      yield {
        type: "SAVE_H5P_STATEMENT_SUCCESS",
        payload: {
          statement: statement,
          statementId: response.data?.statement_id || 0,
        },
      };
    } catch (error) {
      yield {
        type: "SAVE_H5P_STATEMENT_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.SERVER_ERROR,
            message: error instanceof Error ? error.message : "Failed to save H5P statement",
            context: { action: "saveH5PStatement", statement },
          },
        },
      };
    }
  },

  *validateH5PAnswers(validation: H5PQuestionValidation): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "VALIDATE_H5P_ANSWERS_START",
        payload: { validation },
      };

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: "/tutorpress/v1/h5p/validate",
          method: "POST",
          data: validation,
        },
      }) as H5PValidationResponse;

      if (!response || !response.success || !response.data) {
        throw new Error(response?.message || "Failed to validate H5P answers");
      }

      yield {
        type: "VALIDATE_H5P_ANSWERS_SUCCESS",
        payload: {
          results: response.data.validation_results,
        },
      };
    } catch (error) {
      yield {
        type: "VALIDATE_H5P_ANSWERS_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.VALIDATION_FAILED,
            message: error instanceof Error ? error.message : "Failed to validate H5P answers",
            context: { action: "validateH5PAnswers", validation },
          },
        },
      };
    }
  },

  *fetchH5PResults(resultParams: H5PQuizResult): Generator<unknown, void, unknown> {
    try {
      yield {
        type: "FETCH_H5P_RESULTS_START",
        payload: { resultParams },
      };

      // Build query string from result parameters
      const queryParams = new URLSearchParams({
        quiz_id: resultParams.quiz_id.toString(),
        user_id: resultParams.user_id.toString(),
        question_id: resultParams.question_id.toString(),
        content_id: resultParams.content_id.toString(),
        attempt_id: resultParams.attempt_id.toString(),
      });

      const response = (yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/h5p/results?${queryParams.toString()}`,
          method: "GET",
        },
      }) as H5PQuizResultResponse;

      if (!response || !response.success || !response.data) {
        throw new Error(response?.message || "Failed to fetch H5P results");
      }

      // Create a unique key for caching results
      const resultKey = `${resultParams.quiz_id}_${resultParams.user_id}_${resultParams.attempt_id}`;

      yield {
        type: "FETCH_H5P_RESULTS_SUCCESS",
        payload: {
          results: response,
          resultKey,
        },
      };
    } catch (error) {
      yield {
        type: "FETCH_H5P_RESULTS_ERROR",
        payload: {
          error: {
            code: H5PErrorCode.SERVER_ERROR,
            message: error instanceof Error ? error.message : "Failed to fetch H5P results",
            context: { action: "fetchH5PResults", resultParams },
          },
        },
      };
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
  refreshTopicsAfterLessonSave,
  refreshTopicsAfterAssignmentSave,
  saveQuiz,
  getQuizDetails,
  deleteQuiz,
  duplicateQuiz,
  setQuizState,
  refreshTopicsAfterQuizSave,
  // H5P Actions
  fetchH5PContents,
  setH5PSelectedContent,
  setH5PSearchParams,
  saveH5PStatement,
  validateH5PAnswers,
  fetchH5PResults,
} = actions;

// Export selectors
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
  getLessonState,
  getActiveLessonId,
  isLessonLoading,
  hasLessonError,
  getLessonError,
  getQuizState,
  getActiveQuizId,
  getLastSavedQuizId,
  isQuizLoading,
  isQuizSaving,
  isQuizDeleting,
  isQuizDuplicating,
  hasQuizError,
  getQuizError,
  // H5P Selectors
  getH5PContents,
  getH5PSelectedContent,
  getH5PSearchParams,
  getH5PPagination,
  getH5PContentOperationState,
  isH5PContentLoading,
  hasH5PContentError,
  getH5PContentError,
  getH5PStatements,
  getH5PStatementOperationState,
  isH5PStatementSaving,
  hasH5PStatementError,
  getH5PStatementError,
  getH5PValidationResults,
  getH5PValidationOperationState,
  isH5PValidating,
  hasH5PValidationError,
  getH5PValidationError,
  getH5PResults,
  getH5PResultsOperationState,
  isH5PResultsLoading,
  hasH5PResultsError,
  getH5PResultsError,
} = selectors;
