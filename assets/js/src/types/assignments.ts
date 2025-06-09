/**
 * Type definitions for Assignment functionality
 */

/**
 * Assignment interface representing a Tutor LMS assignment
 */
export interface Assignment {
  id: number;
  title: string;
  content: string;
  topic_id: number;
  course_id: number;
  order: number;
  status: "publish" | "draft" | "private";
  created_at: string;
  updated_at: string;
  meta?: {
    assignment_time_duration?: {
      time_value: number;
      time_type: "minutes" | "hours" | "days" | "weeks";
    };
    assignment_total_marks?: number;
    assignment_pass_marks?: number;
    assignment_upload_files_limit?: number;
    assignment_upload_file_size_limit?: number;
    [key: string]: any;
  };
}

/**
 * Assignment creation data
 */
export interface AssignmentCreateData {
  title: string;
  content: string;
  topic_id: number;
}

/**
 * Assignment update data
 */
export interface AssignmentUpdateData {
  title?: string;
  content?: string;
  topic_id?: number;
  meta?: Assignment["meta"];
}

/**
 * Assignment duplication data
 */
export interface AssignmentDuplicateData {
  topic_id: number;
}
