import {
  Topic,
  TopicOperationState,
  TopicEditState,
  TopicCreationState,
  ReorderOperationState,
  TopicDeletionState,
  TopicDuplicationState,
  CurriculumError,
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
  | { type: "SET_IS_ADDING_TOPIC"; payload: boolean };

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
}

/**
 * Async action creator types for the curriculum store
 */
export interface CurriculumAsyncActions {
  fetchTopics: (courseId: number) => Generator<any, void, any>;
  createTopic: (courseId: number, data: { title: string; summary: string }) => Generator<any, void, any>;
  updateTopic: (topicId: number, data: { title: string; summary: string }) => Generator<any, void, any>;
  deleteTopic: (topicId: number) => Generator<any, void, any>;
  duplicateTopic: (topicId: number, courseId: number) => Generator<any, void, any>;
}

/**
 * Helper type for state updates that can be either a value or a function
 */
export type StateUpdate<T> = T | ((current: T) => T);

/**
 * Helper type for handling state updates
 */
export type StateUpdateHandler<T> = (current: T, update: StateUpdate<T>) => T;
