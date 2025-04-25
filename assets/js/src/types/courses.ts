/**
 * Course-related type definitions
 *
 * Contains base types for course functionality, including the
 * Course Curriculum metabox implementation.
 */

/**
 * Base Types
 */

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

/**
 * Base course interface
 */
export interface BaseCourse {
  id: number;
  title: string;
  content: string;
  status: string;
}

/**
 * UI-specific Types
 */

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
  order: number;
  contents: ContentItem[];
}

/**
 * Course with UI-specific properties for the curriculum editor
 */
export interface Course extends BaseCourse {
  topics: Topic[];
}

/**
 * Order-related Types
 */

export interface TopicOrder {
  topic_id: number;
  order: number;
}

export interface ContentOrder {
  content_id: number;
  topic_id: number;
  order: number;
}

/**
 * Drag and Drop Types
 */

/**
 * Props for drag handle elements
 */
export interface DragHandleProps {
  ref: (element: HTMLElement | null) => void;
  style?: React.CSSProperties;
  [key: string]: any;
}

/**
 * Props for sortable topic components
 */
export interface SortableTopicProps {
  topic: Topic;
}

/**
 * Props for topic section components
 */
export interface TopicSectionProps {
  topic: Topic;
  dragHandleProps?: DragHandleProps;
}
