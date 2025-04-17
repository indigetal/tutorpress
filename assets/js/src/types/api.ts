/**
 * API Response Types
 */

/**
 * Generic API Response interface
 */
export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

/**
 * Topic Response interface
 */
export interface Topic {
  id: number;
  title: string;
  content: string;
  menu_order: number;
  status: string;
  contents: ContentItem[];
  isCollapsed?: boolean; // Optional UI state property
}

/**
 * Content Item Response interface
 */
export interface ContentItem {
  id: number;
  title: string;
  type: "lesson" | "quiz" | "interactive_quiz" | "assignment" | "meet_lesson" | "zoom_lesson";
  menu_order: number;
  status: string;
}
