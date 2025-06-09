/**
 * Lesson-related type definitions
 *
 * Contains types specific to lesson functionality within the WordPress REST API.
 */

/**
 * WordPress REST API Types for Lessons
 */
export interface Lesson {
  id: number;
  title: string;
  content: string;
  status: string;
}
