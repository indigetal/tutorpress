import {
  Topic,
  TopicOperationState,
  TopicEditState,
  TopicCreationState,
  ReorderOperationState,
  TopicDeletionState,
  TopicDuplicationState,
  CurriculumError,
  OperationResult,
  TopicActiveOperation,
} from "../../types/curriculum";

/**
 * Action types for the curriculum store
 */
export type CurriculumAction =
  | { type: "SET_TOPICS"; payload: Topic[] | ((topics: Topic[]) => Topic[]) }
  | { type: "SET_OPERATION_STATE"; payload: TopicOperationState }
  | { type: "SET_EDIT_STATE"; payload: TopicEditState }
  | { type: "SET_TOPIC_CREATION_STATE"; payload: TopicCreationState }
  | { type: "SET_REORDER_STATE"; payload: ReorderOperationState }
  | { type: "SET_DELETION_STATE"; payload: TopicDeletionState }
  | { type: "SET_DUPLICATION_STATE"; payload: TopicDuplicationState }
  | { type: "SET_IS_ADDING_TOPIC"; payload: boolean }
  | { type: "SET_ACTIVE_OPERATION"; payload: TopicActiveOperation }
  | { type: "FETCH_TOPICS_START"; payload: { courseId: number } }
  | { type: "FETCH_TOPICS_SUCCESS"; payload: { topics: Topic[] } }
  | { type: "FETCH_TOPICS_ERROR"; payload: { error: CurriculumError } };

/**
 * State interface for the curriculum store
 */
export interface CurriculumState {
  topics: Topic[];
  operationState: TopicOperationState;
  topicCreationState: TopicCreationState;
  editState: TopicEditState;
  deletionState: TopicDeletionState;
  duplicationState: TopicDuplicationState;
  reorderState: ReorderOperationState;
  isAddingTopic: boolean;
  fetchState: {
    isLoading: boolean;
    error: CurriculumError | null;
    lastFetchedCourseId: number | null;
  };
}

/**
 * Selector types for the curriculum store
 */
export interface CurriculumSelectors {
  getTopics: (state: CurriculumState) => Topic[];
  getOperationState: (state: CurriculumState) => TopicOperationState;
  getEditState: (state: CurriculumState) => TopicEditState;
  getTopicCreationState: (state: CurriculumState) => TopicCreationState;
  getReorderState: (state: CurriculumState) => ReorderOperationState;
  getDeletionState: (state: CurriculumState) => TopicDeletionState;
  getDuplicationState: (state: CurriculumState) => TopicDuplicationState;
  getIsAddingTopic: (state: CurriculumState) => boolean;
  getFetchState: (state: CurriculumState) => {
    isLoading: boolean;
    error: CurriculumError | null;
    lastFetchedCourseId: number | null;
  };
}

/**
 * Action creator types for the curriculum store
 */
export interface CurriculumActions {
  setTopics: (topics: Topic[] | ((topics: Topic[]) => Topic[])) => CurriculumAction;
  setOperationState: (state: TopicOperationState) => CurriculumAction;
  setEditState: (state: TopicEditState) => CurriculumAction;
  setTopicCreationState: (state: TopicCreationState) => CurriculumAction;
  setReorderState: (state: ReorderOperationState) => CurriculumAction;
  setDeletionState: (state: TopicDeletionState) => CurriculumAction;
  setDuplicationState: (state: TopicDuplicationState) => CurriculumAction;
  setIsAddingTopic: (isAdding: boolean) => CurriculumAction;
  setActiveOperation: (operation: TopicActiveOperation) => CurriculumAction;
  fetchTopicsStart: (courseId: number) => CurriculumAction;
  fetchTopicsSuccess: (topics: Topic[]) => CurriculumAction;
  fetchTopicsError: (error: CurriculumError) => CurriculumAction;
}

/**
 * Async action creator types for the curriculum store
 */
export interface CurriculumAsyncActions {
  fetchTopics: (courseId: number) => Promise<OperationResult<Topic[]>>;
  createTopic: (courseId: number, data: { title: string; summary: string }) => Promise<OperationResult<Topic>>;
  updateTopic: (topicId: number, data: { title: string; summary: string }) => Promise<OperationResult<Topic>>;
  deleteTopic: (topicId: number) => Promise<OperationResult<void>>;
  duplicateTopic: (topicId: number, courseId: number) => Promise<OperationResult<Topic>>;
}

/**
 * Helper type for state updates that can be either a value or a function
 */
export type StateUpdate<T> = T | ((current: T) => T);

/**
 * Helper type for handling state updates
 */
export type StateUpdateHandler<T> = (current: T, update: StateUpdate<T>) => T;
