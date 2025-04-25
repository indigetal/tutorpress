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
  type: "lesson" | "quiz" | "interactive_quiz" | "assignment" | "meet_lesson" | "zoom_lesson";
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
export const enum CurriculumErrorCode {
  FETCH_FAILED = "fetch_failed",
  REORDER_FAILED = "reorder_failed",
  INVALID_RESPONSE = "invalid_response",
  SERVER_ERROR = "server_error",
  NETWORK_ERROR = "network_error",
  CREATION_FAILED = "creation_failed",
  VALIDATION_ERROR = "validation_error",
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
