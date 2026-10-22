/**
 * Live Lesson Modal Component
 *
 * @description Modal for creating and editing Live Lessons (Google Meet and Zoom) content within the course curriculum.
 *              Follows the established modal patterns from QuizModal and InteractiveQuizModal:
 *              1. Uses WordPress Modal component directly (not BaseModalLayout)
 *              2. Simple form-based approach with provider-specific forms
 *              3. Integrates with WordPress Data Store following API_FETCH pattern
 *              4. Maintains consistent UI/UX with other TutorPress modals
 *
 * @package TutorPress
 * @subpackage Components/Modals/LiveLessons
 * @since 1.5.2
 */

import React, { useState, useEffect } from "react";
import { Modal, Button, Notice, Spinner } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useDispatch } from "@wordpress/data";
import { store as noticesStore } from "@wordpress/notices";
import type {
  LiveLessonModalProps,
  LiveLessonFormData,
  GoogleMeetFormData,
  ZoomFormData,
} from "../../../types/liveLessons";
import { GoogleMeetForm } from "./GoogleMeetForm";
import { ZoomForm } from "./ZoomForm";
import { LiveLessonRecoveryControls } from "./LiveLessonRecoveryControls";
import { addZoomDuration, hydrateWallClock, hydrateZoomDuration, serializeWallClock, wallClockMinutes } from "../../../utils/liveLessonDateTime";
import { deriveLiveLessonRecoveryActions } from "../../../utils/liveLessonReconciliation";

const CURRICULUM_STORE = "tutorpress/curriculum";

type LiveLessonRecoveryAction = ReturnType<typeof deriveLiveLessonRecoveryActions>[number];

function canonicalLiveLessonSave(value: unknown): { refreshWarning: string | null } | null {
  if (typeof value !== "object" || value === null) return null;
  const outcome = (value as { outcome?: unknown }).outcome;
  const refreshWarning = (value as { refreshWarning?: unknown }).refreshWarning;
  if (typeof outcome !== "object" || outcome === null || typeof (outcome as { id?: unknown }).id !== "number") return null;
  if (refreshWarning !== null && typeof refreshWarning !== "string") return null;
  return { refreshWarning };
}

function liveLessonSaveFailure(error: unknown): {
  refreshWarning: string | null;
  actions: LiveLessonRecoveryAction[];
  projectedRecord: object | null;
} {
  const empty = { refreshWarning: null, actions: [] as LiveLessonRecoveryAction[], projectedRecord: null };
  if (typeof error !== "object" || error === null) return empty;
  const source = error as { refreshWarning?: unknown; outcome?: unknown };
  const refreshWarning = typeof source.refreshWarning === "string" && source.refreshWarning !== "" ? source.refreshWarning : null;
  const outcome = source.outcome;
  if (typeof outcome !== "object" || outcome === null) return { ...empty, refreshWarning };
  const data = (outcome as { data?: unknown }).data;
  if (typeof data !== "object" || data === null) return { ...empty, refreshWarning };
  const failure = data as { persisted?: unknown; projected_record?: unknown; actions?: unknown };
  const projectedRecord =
    failure.persisted === true && typeof failure.projected_record === "object" && failure.projected_record !== null
      ? failure.projected_record
      : null;
  const actions =
    window.tutorPressCurriculum?.liveLessonSyncEnabled === true ? deriveLiveLessonRecoveryActions(failure.actions) : [];
  return { refreshWarning, actions, projectedRecord };
}

export const LiveLessonModal: React.FC<LiveLessonModalProps> = ({
  isOpen,
  onClose,
  topicId,
  courseId,
  lessonId,
  lessonType,
}) => {
  // Form state management
  const [isLoading, setIsLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [refreshWarning, setRefreshWarning] = useState<string | null>(null);
  const [recoveryActions, setRecoveryActions] = useState<LiveLessonRecoveryAction[]>([]);
  const [loadError, setLoadError] = useState<string | null>(null);

  // Get WordPress date settings for proper timezone handling
  const dateSettings = (window as any).wp?.date?.getSettings?.() || {};
  const defaultTimezone = dateSettings.timezone?.string || Intl.DateTimeFormat().resolvedOptions().timeZone;

  // Google Meet form state
  const [googleMeetForm, setGoogleMeetForm] = useState<GoogleMeetFormData>({
    title: "",
    summary: "",
    startDate: "",
    startTime: "12:00 AM",
    endDate: "",
    endTime: "12:30 AM",
    timezone: defaultTimezone,
    addEnrolledStudents: false,
  });

  // Zoom form state
  const [zoomForm, setZoomForm] = useState<ZoomFormData>({
    title: "",
    summary: "",
    date: "",
    time: "12:00 AM",
    duration: 40,
    durationUnit: "minutes",
    timezone: defaultTimezone,
    autoRecording: "none",
    password: "",
    host: "", // Will be populated by ZoomForm when Zoom users load
  });

  // Store dispatch and selectors
  const { createNotice } = useDispatch(noticesStore);
  const { saveLiveLesson, updateLiveLesson } = useDispatch(CURRICULUM_STORE);

  // Load existing lesson data if editing
  useEffect(() => {
    if (lessonId && isOpen) {
      loadExistingLessonData(lessonId);
    }
  }, [lessonId, isOpen]);

  // Reset form when modal opens for new lesson
  useEffect(() => {
    if (isOpen && !lessonId) {
      resetForm();
    }
  }, [isOpen, lessonId]);

  /**
   * Load existing lesson data for editing
   */
  const loadExistingLessonData = async (id: number) => {
    setIsLoading(true);
    setLoadError(null);

    try {
      console.log(`TutorPress: Loading Live Lesson data for ID ${id}`);

      // Use WordPress apiFetch to get lesson data
      const response = await (window as any).wp.apiFetch({
        path: `/tutorpress/v1/live-lessons/${id}`,
        method: "GET",
      });

      if (!response.success || !response.data) {
        throw new Error(response.message || __("Failed to load Live Lesson data", "tutorpress"));
      }

      const data = response.data;
      console.log("TutorPress: Loaded Live Lesson data:", data);

      // Initialize form with loaded data based on lesson type
      if (data.type === "google_meet") {
        const start = hydrateWallClock(data.startDateTime || "");
        const end = hydrateWallClock(data.endDateTime || "");
        setGoogleMeetForm({
          title: data.title || "",
          summary: data.description || "",
          startDate: start?.date ?? "",
          startTime: start?.time ?? "12:00 AM",
          endDate: end?.date ?? "",
          endTime: end?.time ?? "12:30 AM",
          timezone: data.settings?.timezone || defaultTimezone,
          addEnrolledStudents: data.settings?.add_enrolled_students === "Yes",
        });
      } else if (data.type === "zoom") {
        // Use autoRecording value from providerConfig
        let autoRecording: "none" | "local" | "cloud" = "none";
        if (
          data.providerConfig?.autoRecording &&
          ["none", "local", "cloud"].includes(data.providerConfig.autoRecording)
        ) {
          autoRecording = data.providerConfig.autoRecording as "none" | "local" | "cloud";
        }

        const start = hydrateWallClock(data.startDateTime || "");
        const typed = hydrateZoomDuration(data.startDateTime || "", data.endDateTime || "", Number(data.settings?.duration));
        setZoomForm({
          title: data.title || "",
          summary: data.description || "",
          date: start?.date ?? "",
          time: start?.time ?? "12:00 AM",
          duration: typed.value,
          durationUnit: typed.unit,
          timezone: data.settings?.timezone || defaultTimezone,
          autoRecording: autoRecording,
          password: data.providerConfig?.password || "",
          host: data.providerConfig?.host || "", // Will be populated by ZoomForm when Zoom users load
        });
      }
    } catch (error) {
      console.error("TutorPress: Failed to load Live Lesson data:", error);
      setLoadError(error instanceof Error ? error.message : __("Failed to load lesson data", "tutorpress"));
    } finally {
      setIsLoading(false);
    }
  };

  /**
   * Reset form to default values
   */
  const resetForm = () => {
    setGoogleMeetForm({
      title: "",
      summary: "",
      startDate: "",
      startTime: "12:00 AM",
      endDate: "",
      endTime: "12:30 AM",
      timezone: defaultTimezone,
      addEnrolledStudents: false,
    });

    setZoomForm({
      title: "",
      summary: "",
      date: "",
      time: "12:00 AM",
      duration: 40,
      durationUnit: "minutes",
      timezone: defaultTimezone,
      autoRecording: "none",
      password: "",
      host: "", // Will be populated by ZoomForm when Zoom users load
    });

    setSaveError(null);
    setRefreshWarning(null);
    setRecoveryActions([]);
    setLoadError(null);
  };

  /**
   * Validate form data - Enhanced validation to match Tutor LMS requirements
   */
  const validateForm = (): { isValid: boolean; errors: string[] } => {
    const errors: string[] = [];

    if (lessonType === "google_meet") {
      // Required fields for Google Meet
      if (!googleMeetForm.title.trim()) {
        errors.push(__("Meeting name is required", "tutorpress"));
      }

      if (!googleMeetForm.summary.trim()) {
        errors.push(__("Meeting summary is required", "tutorpress"));
      }

      if (!googleMeetForm.timezone) {
        errors.push(__("Timezone is required", "tutorpress"));
      }

      if (!googleMeetForm.startTime) {
        errors.push(__("Start time is required", "tutorpress"));
      }

      if (!googleMeetForm.endTime) {
        errors.push(__("End time is required", "tutorpress"));
      }

      const startWall = serializeWallClock(googleMeetForm.startDate, googleMeetForm.startTime);
      const endWall = serializeWallClock(googleMeetForm.endDate, googleMeetForm.endTime);

      if (!startWall) {
        errors.push(__("Invalid start date/time", "tutorpress"));
      }

      if (!endWall) {
        errors.push(__("Invalid end date/time", "tutorpress"));
      }

      if (startWall && endWall && endWall <= startWall) {
        errors.push(__("End time must be after start time", "tutorpress"));
      }
    } else if (lessonType === "zoom") {
      // Required fields for Zoom
      if (!zoomForm.title.trim()) {
        errors.push(__("Meeting name is required", "tutorpress"));
      }

      if (!zoomForm.summary.trim()) {
        errors.push(__("Meeting summary is required", "tutorpress"));
      }

      if (!zoomForm.timezone) {
        errors.push(__("Timezone is required", "tutorpress"));
      }

      if (!zoomForm.time) {
        errors.push(__("Start time is required", "tutorpress"));
      }

      if (zoomForm.duration <= 0) {
        errors.push(__("Duration must be greater than 0", "tutorpress"));
      }

      if (!zoomForm.host.trim()) {
        errors.push(__("Meeting host is required", "tutorpress"));
      }

      const startWall = serializeWallClock(zoomForm.date, zoomForm.time);

      if (!startWall) {
        errors.push(__("Invalid start date/time", "tutorpress"));
      }

      const endWall = startWall ? addZoomDuration(startWall, zoomForm.duration, zoomForm.durationUnit) : "";
      if (startWall && endWall && endWall <= startWall) {
        errors.push(__("End time must be after start time", "tutorpress"));
      }

      // Validate duration based on unit
      const maxDuration = zoomForm.durationUnit === "hours" ? 8 : 480; // 8 hours max
      if (zoomForm.duration > maxDuration) {
        const unitLabel = zoomForm.durationUnit === "hours" ? __("hours", "tutorpress") : __("minutes", "tutorpress");
        errors.push(__(`Duration cannot exceed ${maxDuration} ${unitLabel}`, "tutorpress"));
      }
    }

    return { isValid: errors.length === 0, errors };
  };

  /**
   * Handle form submission using WordPress Data Store (matches previous implementation)
   */
  const handleSave = async () => {
    const validation = validateForm();
    if (!validation.isValid) {
      setSaveError(validation.errors.join(", "));
      return;
    }

    setIsSaving(true);
    setSaveError(null);
    setRefreshWarning(null);
    setRecoveryActions([]);

    try {
      let liveLessonData: LiveLessonFormData;

      if (lessonType === "google_meet") {
        const startDateTime = serializeWallClock(googleMeetForm.startDate, googleMeetForm.startTime) ?? "";
        const endDateTime = serializeWallClock(googleMeetForm.endDate, googleMeetForm.endTime) ?? "";

        liveLessonData = {
          title: googleMeetForm.title,
          description: googleMeetForm.summary,
          type: "google_meet",
          startDateTime,
          endDateTime,
          settings: {
            timezone: googleMeetForm.timezone,
            duration: wallClockMinutes(endDateTime) - wallClockMinutes(startDateTime),
            allowEarlyJoin: true,
            autoRecord: false,
            requirePassword: false,
            waitingRoom: false,
            add_enrolled_students: googleMeetForm.addEnrolledStudents ? "Yes" : "No",
          },
        };
      } else {
        const startDateTime = serializeWallClock(zoomForm.date, zoomForm.time) ?? "";
        const endDateTime = addZoomDuration(startDateTime, zoomForm.duration, zoomForm.durationUnit);

        // Convert form data to API format (matches previous implementation)
        liveLessonData = {
          title: zoomForm.title,
          description: zoomForm.summary,
          type: "zoom",
          startDateTime,
          endDateTime,
          settings: {
            timezone: zoomForm.timezone,
            duration: zoomForm.durationUnit === "hours" ? zoomForm.duration * 60 : zoomForm.duration,
            allowEarlyJoin: true,
            autoRecord: zoomForm.autoRecording !== "none",
            requirePassword: !!zoomForm.password,
            waitingRoom: true,
          },
          providerConfig: {
            password: zoomForm.password,
            host: zoomForm.host,
            autoRecording: zoomForm.autoRecording,
          },
        };
      }

      console.log("TutorPress: Saving Live Lesson with data:", liveLessonData);

      const result: unknown = lessonId
        ? await updateLiveLesson(lessonId, liveLessonData)
        : await saveLiveLesson(liveLessonData, courseId || 0, topicId);
      const saved = canonicalLiveLessonSave(result);
      if (saved === null) {
        setSaveError(__("Failed to save lesson", "tutorpress"));
        setRefreshWarning(null);
        setRecoveryActions([]);
        return;
      }

      createNotice(
        "success",
        lessonId
          ? lessonType === "google_meet"
            ? __("Google Meet lesson updated successfully", "tutorpress")
            : __("Zoom lesson updated successfully", "tutorpress")
          : lessonType === "google_meet"
            ? __("Google Meet lesson created successfully", "tutorpress")
            : __("Zoom lesson created successfully", "tutorpress"),
        {
          type: "snackbar",
          isDismissible: true,
        }
      );
      if (saved.refreshWarning) {
        createNotice("error", saved.refreshWarning, {
          type: "snackbar",
          isDismissible: true,
        });
      }
      handleClose();
    } catch (error) {
      console.error("TutorPress: Failed to save Live Lesson:", error);
      const failure = liveLessonSaveFailure(error);
      setRefreshWarning(failure.refreshWarning);
      setRecoveryActions(failure.actions);
      const isUpdating = !!lessonId;
      const providerName = lessonType === "google_meet" ? "Google Meet" : "Zoom";

      createNotice(
        "error",
        isUpdating
          ? __(`Failed to update ${providerName} lesson`, "tutorpress")
          : __(`Failed to create ${providerName} lesson`, "tutorpress"),
        {
          type: "snackbar",
          isDismissible: true,
        }
      );
      setSaveError(error instanceof Error ? error.message : __("Failed to save lesson", "tutorpress"));
      if (failure.projectedRecord !== null) {
        return;
      }
    } finally {
      setIsSaving(false);
    }
  };

  /**
   * Handle modal close
   */
  const handleClose = () => {
    if (!isSaving) {
      resetForm();
      onClose();
    }
  };

  /**
   * Get modal title based on lesson type and mode
   */
  const getModalTitle = (): string => {
    const isEditing = !!lessonId;
    const providerName = lessonType === "google_meet" ? "Google Meet" : "Zoom";

    return isEditing
      ? __(`Edit ${providerName} Live Lesson`, "tutorpress")
      : __(`Create ${providerName} Live Lesson`, "tutorpress");
  };

  if (!isOpen) return null;

  return (
    <Modal title={getModalTitle()} onRequestClose={handleClose} className="tutorpress-live-lesson-modal" size="medium">
      <div className="tutorpress-modal-content">
        {/* Loading State */}
        {isLoading && (
          <div className="tutorpress-modal-loading tpress-loading-state-centered">
            <Spinner />
            <p>{__("Loading lesson data...", "tutorpress")}</p>
          </div>
        )}

        {/* Load Error */}
        {loadError && (
          <Notice status="error" isDismissible={false}>
            {loadError}
          </Notice>
        )}

        {/* Save Error */}
        {saveError && (
          <Notice status="error" isDismissible={false}>
            {saveError}
          </Notice>
        )}

        {refreshWarning !== null && (
          <Notice status="warning" isDismissible={false}>
            {refreshWarning}
          </Notice>
        )}

        <LiveLessonRecoveryControls actions={recoveryActions} />

        {/* Form Content */}
        {!isLoading && !loadError && (
          <>
            {lessonType === "google_meet" && (
              <GoogleMeetForm formData={googleMeetForm} onChange={setGoogleMeetForm} disabled={isSaving} />
            )}

            {lessonType === "zoom" && <ZoomForm formData={zoomForm} onChange={setZoomForm} disabled={isSaving} />}
          </>
        )}

        {/* Modal Actions */}
        <div className="tutorpress-modal-actions tpress-button-group tpress-button-group-end">
          <Button variant="tertiary" onClick={handleClose} disabled={isSaving}>
            {__("Cancel", "tutorpress")}
          </Button>

          <Button
            variant="primary"
            onClick={handleSave}
            disabled={isSaving || isLoading || !!loadError}
            isBusy={isSaving}
          >
            {isSaving
              ? __("Saving...", "tutorpress")
              : lessonId
                ? __("Update Lesson", "tutorpress")
                : __("Create Lesson", "tutorpress")}
          </Button>
        </div>
      </div>
    </Modal>
  );
};
