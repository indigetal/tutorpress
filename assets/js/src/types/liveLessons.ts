/**
 * Live Lessons Types
 *
 * @description TypeScript interfaces for Live Lessons functionality supporting
 *              Google Meet and Zoom integration within TutorPress curriculum.
 *
 * @package TutorPress
 * @subpackage Types/LiveLessons
 */

/**
 * Supported Live Lesson Types
 */
export type LiveLessonType = "google_meet" | "zoom";

/**
 * Live Lesson Settings Interface
 */
export interface LiveLessonSettings {
  timezone: string;
  duration: number; // in minutes
  allowEarlyJoin: boolean;
  autoRecord?: boolean;
  requirePassword?: boolean;
  waitingRoom?: boolean;
  add_enrolled_students?: string; // "Yes" or "No" for Google Meet compatibility
}

/**
 * Core Live Lesson Data Interface
 */
export interface LiveLesson {
  id: number;
  title: string;
  description: string;
  type: LiveLessonType;
  topicId: number;
  courseId: number;
  startDateTime: string; // ISO 8601 format
  endDateTime: string; // ISO 8601 format
  meetingUrl?: string;
  meetingId?: string;
  password?: string;
  settings: LiveLessonSettings;
  status: "scheduled" | "live" | "ended" | "cancelled";
  createdAt: string;
  updatedAt: string;
}

/**
 * Live Lesson Form Data Interface
 * Used for creating and editing live lessons
 */
export interface LiveLessonFormData {
  title: string;
  description: string;
  type: LiveLessonType;
  startDateTime: string;
  endDateTime: string;
  settings: Partial<LiveLessonSettings>;
  providerConfig?: Record<string, any>; // Provider-specific configuration
}

/**
 * Live Lesson Form Validation Errors
 */
export interface LiveLessonFormErrors {
  title?: string;
  description?: string;
  startDateTime?: string;
  endDateTime?: string;
  timezone?: string;
  duration?: string;
  general?: string;
}

/**
 * Google Meet Specific Configuration
 */
export interface GoogleMeetConfig {
  type: "google_meet";
  calendarId?: string;
  sendInvitations: boolean;
  guestsCanInviteOthers: boolean;
  guestsCanModify: boolean;
  guestsCanSeeOtherGuests: boolean;
}

/**
 * Zoom Specific Configuration
 */
export interface ZoomConfig {
  type: "zoom";
  hostId?: string;
  alternativeHosts?: string[];
  registrationType: "none" | "once" | "recurring";
  approvalType: "automatic" | "manual";
  joinBeforeHost: boolean;
  muteUponEntry: boolean;
}

/**
 * Live Lesson Provider Configuration Union Type
 */
export type LiveLessonProviderConfig = GoogleMeetConfig | ZoomConfig;

/**
 * Extended Live Lesson with Provider Configuration
 */
export interface LiveLessonWithConfig extends LiveLesson {
  providerConfig?: LiveLessonProviderConfig;
}

/**
 * Live Lesson API Response Interface
 */
export interface LiveLessonApiResponse {
  success: boolean;
  message: string;
  data: LiveLesson | LiveLesson[];
}

/**
 * Live Lesson Creation Response
 */
export interface LiveLessonCreateResponse extends LiveLessonApiResponse {
  data: LiveLesson;
}

/**
 * Live Lesson List Response
 */
export interface LiveLessonListResponse extends LiveLessonApiResponse {
  data: LiveLesson[];
  pagination?: {
    total: number;
    page: number;
    perPage: number;
    totalPages: number;
  };
}

/**
 * Live Lesson Modal Props Interface
 */
export interface LiveLessonModalProps {
  isOpen: boolean;
  onClose: () => void;
  topicId: number;
  courseId?: number; // Make optional to match TopicSectionProps
  lessonId?: number; // For editing existing lessons
  lessonType: LiveLessonType;
}

/**
 * Live Lesson Button Props Interface
 */
export interface LiveLessonButtonProps {
  topicId: number;
  courseId: number;
  type: LiveLessonType;
  variant?: "primary" | "secondary";
  size?: "small" | "medium" | "large";
  disabled?: boolean;
  onClick?: () => void;
}

/**
 * Live Lesson Form Component Props
 */
export interface LiveLessonFormProps {
  initialData?: Partial<LiveLessonFormData>;
  lessonType: LiveLessonType;
  onSave: (data: LiveLessonFormData) => Promise<void>;
  onCancel: () => void;
  isCreating?: boolean;
  errors?: LiveLessonFormErrors;
}

/**
 * Timezone Option Interface
 */
export interface TimezoneOption {
  label: string;
  value: string;
  offset: string;
}

/**
 * Live Lesson Content Item Interface
 * Extends the base ContentItem for curriculum integration
 */
export interface LiveLessonContentItem {
  id: number;
  title: string;
  type: "google_meet_lesson" | "zoom_lesson";
  lessonType: LiveLessonType;
  startDateTime: string;
  status: LiveLesson["status"];
  meetingUrl?: string;
}

/**
 * Google Meet Form Data Interface
 * Used for Google Meet form state management
 */
export interface GoogleMeetFormData {
  title: string;
  summary: string;
  startDate: Date;
  startTime: string; // 12-hour format like "09:00 AM"
  endDate: Date;
  endTime: string; // 12-hour format like "10:00 AM"
  timezone: string;
  addEnrolledStudents: boolean;
}

/**
 * Zoom Form Data Interface
 * Used for Zoom form state management
 */
export interface ZoomFormData {
  title: string;
  summary: string;
  date: Date;
  time: string; // 12-hour format like "09:00 AM"
  duration: number;
  durationUnit: "minutes" | "hours";
  timezone: string;
  autoRecording: "none" | "local" | "cloud";
  password: string;
  host: string; // Zoom user ID from Zoom API
}
