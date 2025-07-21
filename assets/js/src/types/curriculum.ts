/**
 * Type definitions for the Course Curriculum functionality
 */

import type { Course } from "./courses";
import type { CSSProperties } from "react";

// ============================================================================
// Base Types
// ============================================================================

/**
 * Base content item interface that represents any type of course content
 */
export interface BaseContentItem {
  id: number;
  title: string;
  type:
    | "lesson"
    | "tutor_quiz"
    | "interactive_quiz"
    | "assignment"
    | "tutor_assignments"
    | "meet_lesson"
    | "zoom_lesson";
  status: string; // WordPress post status: 'publish', 'draft', 'private', 'pending', 'future'
}

/**
 * Base topic interface that represents a curriculum section
 */
export interface BaseTopic {
  id: number;
  title: string;
  content?: string;
}

// ============================================================================
// Type Guards
// ============================================================================

/**
 * Type guard for validating Topic objects
 */
export const isValidTopic = (topic: unknown): topic is Topic => {
  return (
    typeof topic === "object" &&
    topic !== null &&
    "id" in topic &&
    "title" in topic &&
    "content" in topic &&
    "contents" in topic &&
    Array.isArray((topic as Topic).contents)
  );
};

// ============================================================================
// UI Types
// ============================================================================

/**
 * Content item with UI-specific properties for the curriculum editor
 */
export interface ContentItem extends BaseContentItem {
  topic_id: number;
  order: number;
}

/**
 * Topic with UI-specific properties for the curriculum editor
 */
export interface Topic extends BaseTopic {
  isCollapsed: boolean;
  menu_order: number;
  contents: ContentItem[];
  summary?: string;
}

/**
 * Props for drag handle elements
 */
export interface DragHandleProps {
  ref: (element: HTMLElement | null) => void;
  style?: CSSProperties;
  [key: string]: any;
}

/**
 * Props for sortable topic components
 */
export interface SortableTopicProps {
  topic: Topic;
  courseId?: number;
  onEdit: () => void;
  onEditCancel: () => void;
  onEditSave: (topicId: number, data: TopicFormData) => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
  onToggle?: () => void;
  isEditing: boolean;
}

/**
 * Props for topic section components
 */
export interface TopicSectionProps {
  topic: Topic;
  courseId?: number;
  dragHandleProps: DragHandleProps;
  onEdit: () => void;
  onEditCancel: () => void;
  onEditSave: (topicId: number, data: TopicFormData) => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
  onToggle?: () => void;
  isEditing: boolean;
}

// ============================================================================
// State Types
// ============================================================================

/**
 * Error codes for curriculum operations
 */
export enum CurriculumErrorCode {
  FETCH_FAILED = "fetch_failed",
  REORDER_FAILED = "reorder_failed",
  INVALID_RESPONSE = "invalid_response",
  SERVER_ERROR = "server_error",
  NETWORK_ERROR = "network_error",
  CREATION_FAILED = "creation_failed",
  VALIDATION_ERROR = "validation_error",
  OPERATION_IN_PROGRESS = "operation_in_progress",
  EDIT_FAILED = "edit_failed",
  DELETE_FAILED = "delete_failed",
  DUPLICATE_FAILED = "duplicate_failed",
  CREATE_FAILED = "create_failed",
  SAVE_FAILED = "SAVE_FAILED",
  UPDATE_FAILED = "UPDATE_FAILED",
}

/**
 * Structured error type for curriculum operations
 */
export interface CurriculumError {
  code: CurriculumErrorCode;
  message: string;
  context?: {
    action?: string;
    topicId?: number;
    details?: string;
    operationType?: TopicActiveOperation["type"];
    operationData?: {
      sourceTopicId?: number;
      targetTopicId?: number;
    };
  };
}

/**
 * Topic form data
 */
export interface TopicFormData {
  title: string;
  summary: string;
}

/**
 * Topic edit state
 */
export interface TopicEditState {
  isEditing: boolean;
  topicId: number | null;
}

/**
 * Topic creation state with structured error
 */
export type TopicCreationState =
  | { status: "idle" }
  | { status: "creating" }
  | { status: "success"; data: Topic }
  | { status: "error"; error: CurriculumError };

/**
 * Topic operation state with structured error
 */
export type TopicOperationState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "deleting" }
  | { status: "success"; data: Topic[] }
  | { status: "error"; error: CurriculumError };

/**
 * Reorder operation state with structured error
 */
export type ReorderOperationState =
  | { status: "idle" }
  | { status: "reordering" }
  | { status: "success" }
  | { status: "error"; error: CurriculumError };

/**
 * Snapshot of curriculum state
 */
export interface CurriculumSnapshot {
  topics: Topic[];
  timestamp: number;
  operation: "reorder" | "edit" | "delete" | "duplicate";
}

/**
 * Operation result type
 */
export type OperationResult<T> = {
  success: boolean;
  data?: T;
  error?: CurriculumError;
};

// ============================================================================
// Order Types
// ============================================================================

export interface TopicOrder {
  topic_id: number;
  menu_order: number;
}

export interface ContentOrder {
  content_id: number;
  topic_id: number;
  order: number;
}

export interface TopicDeletionState {
  status: "idle" | "deleting" | "error" | "success";
  error?: CurriculumError;
  topicId?: number;
}

export interface TopicDuplicationState {
  status: "idle" | "duplicating" | "error" | "success";
  error?: CurriculumError;
  sourceTopicId?: number;
  duplicatedTopicId?: number;
}

export interface LessonDuplicationState {
  status: "idle" | "duplicating" | "error" | "success";
  error?: CurriculumError;
  sourceLessonId?: number;
  duplicatedLessonId?: number;
}

export interface AssignmentDuplicationState {
  status: "idle" | "duplicating" | "error" | "success";
  error?: CurriculumError;
  sourceAssignmentId?: number;
  duplicatedAssignmentId?: number;
}

export interface QuizDuplicationState {
  status: "idle" | "duplicating" | "error" | "success";
  error?: CurriculumError;
  sourceQuizId?: number;
  duplicatedQuizId?: number;
}

export type TopicActiveOperation =
  | { type: "none" }
  | { type: "edit"; topicId: number }
  | { type: "delete"; topicId: number }
  | { type: "duplicate"; topicId: number }
  | { type: "reorder" }
  | { type: "create" };

export type LessonActiveOperation =
  | { type: "none" }
  | { type: "edit"; lessonId: number }
  | { type: "delete"; lessonId: number }
  | { type: "duplicate"; lessonId: number }
  | { type: "create" };

export type AssignmentActiveOperation =
  | { type: "none" }
  | { type: "edit"; assignmentId: number }
  | { type: "delete"; assignmentId: number }
  | { type: "duplicate"; assignmentId: number }
  | { type: "create" };

export type QuizActiveOperation =
  | { type: "none" }
  | { type: "edit"; quizId: number }
  | { type: "delete"; quizId: number }
  | { type: "duplicate"; quizId: number }
  | { type: "create" };

export interface CurriculumState {
  topics: Topic[];
  operationState: TopicOperationState;
  topicCreationState: TopicCreationState;
  editState: TopicEditState;
  deletionState: TopicDeletionState;
  duplicationState: TopicDuplicationState;
  lessonDuplicationState: LessonDuplicationState;
  assignmentDuplicationState: AssignmentDuplicationState;
  quizDuplicationState: QuizDuplicationState;
  reorderState: ReorderOperationState;
  isAddingTopic: boolean;
  activeOperation: TopicActiveOperation;
  fetchState: {
    isLoading: boolean;
    error: CurriculumError | null;
    lastFetchedCourseId: number | null;
  };
}

/**
 * Helper function to create operation-specific errors
 */
export const createOperationError = (
  code: CurriculumErrorCode,
  message: string,
  operation: TopicActiveOperation,
  context?: Omit<CurriculumError["context"], "operationType" | "operationData">
): CurriculumError => {
  return {
    code,
    message,
    context: {
      ...context,
      operationType: operation.type,
      operationData:
        operation.type !== "none" && "topicId" in operation ? { sourceTopicId: operation.topicId } : undefined,
    },
  };
};

/**
 * Live Lesson types
 */
// Note: Live Lessons types moved to assets/js/src/types/liveLessons.ts
// to avoid duplication and follow proper separation of concerns
