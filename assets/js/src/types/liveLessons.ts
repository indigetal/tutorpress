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
  startDateTime: string; // Wall clock YYYY-MM-DD HH:mm:00
  endDateTime: string; // Wall clock YYYY-MM-DD HH:mm:00
  meetingUrl?: string;
  meetingId?: string;
  password?: string;
  settings: LiveLessonSettings;
  status: "scheduled" | "live" | "ended" | "cancelled";
  createdAt: string;
  updatedAt: string;
  sync_state?: LiveLessonSyncState;
  sync_version?: number;
  operation?: LiveLessonProjectedOperation | null;
  record?: Record<string, unknown> | null;
  warning?: LiveLessonSyncNotice | null;
  error?: LiveLessonSyncNotice | null;
}

/**
 * Envelope sync state. Separate from LiveLesson.status and from WordPress post status.
 */
export type LiveLessonSyncState = "pending" | "error" | "unlinked" | "synced";

/**
 * Sanitized envelope notice: a string, a JSON list, or a string-keyed object.
 */
export type LiveLessonSyncNotice = string | unknown[] | Record<string, unknown>;

/**
 * Item-projection operation. The three fields project_item() emits, not the stored snapshot.
 */
export interface LiveLessonProjectedOperation {
  operation_id: string;
  kind: "create" | "update" | "delete";
  dispatch_state: "not_dispatched" | "dispatch_possible" | "confirmed";
}

/**
 * Item projection. status is the compatibility literal scheduled.
 */
export interface LiveLessonFullProjection {
  status: "scheduled";
  sync_state: LiveLessonSyncState;
  sync_version: number;
  operation: LiveLessonProjectedOperation | null;
  record: Record<string, unknown> | null;
  warning: LiveLessonSyncNotice | null;
  error: LiveLessonSyncNotice | null;
}

/**
 * Descriptors from actions(). None include provider_id.
 * Known-provider inspect omits operation_id. Abandon is advertised as abandon.
 */
export type LiveLessonAdvertisedAction =
  | { action: "resolve_owner"; version: 0 }
  | { action: "attach"; kind: "legacy_identity"; version: 0 }
  | { action: "inspect"; version: number; operation_id: string }
  | { action: "inspect"; version: number }
  | { action: "attach"; kind: "uncertain_create"; version: number; operation_id: string }
  | { action: "abandon"; version: number; operation_id: string }
  | { action: "retry"; version: number; operation_id: string };

/**
 * Uncertain-create attach body. version is an integer >= 1. provider_id and operation_id are non-empty.
 */
export interface LiveLessonUncertainCreateAttachRequest {
  provider_id: string;
  version: number;
  operation_id: string;
}

/**
 * Legacy attach body. version is 0. provider_id is non-empty.
 * A present selected_owner_id is an integer greater than 0. operation_id is absent.
 */
export interface LiveLessonLegacyAttachRequest {
  provider_id: string;
  version: 0;
  selected_owner_id?: number;
}

/**
 * Wall clock YYYY-MM-DD HH:mm:00. Not an ISO instant.
 */
export type LiveLessonWallClock = string;

/**
 * Zoom duration accepted by zoom_duration(). minutes is an integer 1..480. hours is an integer 1..8.
 */
export interface LiveLessonZoomDuration {
  value: number;
  unit: "minutes" | "hours";
}

/**
 * True-gate schedule request. timezone is required.
 * Zoom sends duration. Google omits it and derives the span from the wall clocks.
 */
export interface LiveLessonScheduleRequest {
  start_date_time: LiveLessonWallClock;
  end_date_time: LiveLessonWallClock;
  timezone: string;
  duration?: LiveLessonZoomDuration;
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
  sync_state?: LiveLessonSyncState;
  sync_version?: number;
  menuOrder?: number;
}

/**
 * Google Meet Form Data Interface
 * Used for Google Meet form state management
 */
export interface GoogleMeetFormData {
  title: string;
  summary: string;
  startDate: string; // YYYY-MM-DD
  startTime: string; // 12-hour format like "09:00 AM"
  endDate: string; // YYYY-MM-DD
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
  date: string; // YYYY-MM-DD
  time: string; // 12-hour format like "09:00 AM"
  duration: number;
  durationUnit: "minutes" | "hours";
  timezone: string;
  autoRecording: "none" | "local" | "cloud";
  password: string;
  host: string; // Zoom user ID from Zoom API
}
