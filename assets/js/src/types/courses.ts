/**
 * Course-related type definitions
 *
 * Contains base types for course functionality.
 */

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
 * Course with UI-specific properties for the curriculum editor
 */
export interface Course extends BaseCourse {
  topics: import("./curriculum").Topic[];
}
