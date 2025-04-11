/**
 * Course-related type definitions
 *
 * Contains types specific to course functionality, including the
 * Course Curriculum metabox implementation.
 */

/**
 * Course Curriculum Types
 */

/**
 * Represents a content item within a topic. Content items can be:
 * - lesson: Standard lesson
 * - quiz: Standard quiz
 * - interactive_quiz: Interactive quiz with enhanced features
 * - assignment: Student assignment
 * - meet_lesson: Google Meet live lesson
 * - zoom_lesson: Zoom live lesson
 */
export interface ContentItem {
  id: number;
  title: string;
  type: "lesson" | "quiz" | "interactive_quiz" | "assignment" | "meet_lesson" | "zoom_lesson";
  topic_id: number;
  order: number;
}

/**
 * Represents a topic in the course curriculum.
 * Topics are containers that hold ordered content items and can be collapsed/expanded.
 */
export interface Topic {
  id: number;
  title: string;
  isCollapsed: boolean;
  order: number;
  contents: ContentItem[];
}

/**
 * WordPress REST API Types for Courses
 */
export interface Course {
  id: number;
  title: string;
  content: string;
  status: string;
  topics: Topic[];
}
