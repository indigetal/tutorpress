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

const CURRICULUM_STORE = "tutorpress/curriculum";

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
  const [loadError, setLoadError] = useState<string | null>(null);

  // Get WordPress date settings for proper timezone handling
  const dateSettings = (window as any).wp?.date?.getSettings?.() || {};
  const defaultTimezone = dateSettings.timezone?.string || Intl.DateTimeFormat().resolvedOptions().timeZone;

  // Google Meet form state
  const [googleMeetForm, setGoogleMeetForm] = useState<GoogleMeetFormData>({
    title: "",
    summary: "",
    startDate: new Date(),
    startTime: "12:00 AM",
    endDate: new Date(),
    endTime: "12:30 AM",
    timezone: defaultTimezone,
    addEnrolledStudents: false,
  });

  // Zoom form state
  const [zoomForm, setZoomForm] = useState<ZoomFormData>({
    title: "",
    summary: "",
    date: new Date(),
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
        setGoogleMeetForm({
          title: data.title || "",
          summary: data.description || "",
          startDate: new Date(data.startDateTime),
          startTime: formatTimeFromDateTime(data.startDateTime),
          endDate: new Date(data.endDateTime),
          endTime: formatTimeFromDateTime(data.endDateTime),
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

        setZoomForm({
          title: data.title || "",
          summary: data.description || "",
          date: new Date(data.startDateTime),
          time: formatTimeFromDateTime(data.startDateTime),
          duration: data.settings?.duration || 40,
          durationUnit: "minutes",
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
    const now = new Date();
    const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000);

    setGoogleMeetForm({
      title: "",
      summary: "",
      startDate: now,
      startTime: "12:00 AM",
      endDate: oneHourLater,
      endTime: "12:30 AM",
      timezone: defaultTimezone,
      addEnrolledStudents: false,
    });

    setZoomForm({
      title: "",
      summary: "",
      date: now,
      time: "12:00 AM",
      duration: 40,
      durationUnit: "minutes",
      timezone: defaultTimezone,
      autoRecording: "none",
      password: "",
      host: "", // Will be populated by ZoomForm when Zoom users load
    });

    setSaveError(null);
    setLoadError(null);
  };

  /**
   * Format time from ISO datetime string to 12-hour format
   */
  const formatTimeFromDateTime = (dateTimeString: string): string => {
    try {
      const date = new Date(dateTimeString);
      return date.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      });
    } catch {
      return "09:00 AM";
    }
  };

  /**
   * Combine date and time string into a Date object
   */
  const combineDateTime = (date: Date, timeString: string): Date => {
    const [time, period] = timeString.split(" ");
    const [hours, minutes] = time.split(":").map(Number);

    let adjustedHours = hours;
    if (period === "PM" && hours !== 12) {
      adjustedHours += 12;
    } else if (period === "AM" && hours === 12) {
      adjustedHours = 0;
    }

    const combined = new Date(date);
    combined.setHours(adjustedHours, minutes, 0, 0);
    return combined;
  };

  /**
   * Format datetime for storage exactly as user selected it (no timezone conversion)
   * This matches how Tutor LMS handles datetime - store exactly what user entered
   */
  const formatDateTimeForStorage = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hours = String(date.getHours()).padStart(2, "0");
    const minutes = String(date.getMinutes()).padStart(2, "0");
    const seconds = String(date.getSeconds()).padStart(2, "0");
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
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

      // Validate date/time logic
      const startDateTime = combineDateTime(googleMeetForm.startDate, googleMeetForm.startTime);
      const endDateTime = combineDateTime(googleMeetForm.endDate, googleMeetForm.endTime);

      if (isNaN(startDateTime.getTime())) {
        errors.push(__("Invalid start date/time", "tutorpress"));
      }

      if (isNaN(endDateTime.getTime())) {
        errors.push(__("Invalid end date/time", "tutorpress"));
      }

      if (endDateTime <= startDateTime) {
        errors.push(__("End time must be after start time", "tutorpress"));
      }

      // Check if start time is in the past (optional, but good UX)
      if (startDateTime < new Date()) {
        errors.push(__("Start time cannot be in the past", "tutorpress"));
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

      // Validate date/time logic
      const startDateTime = combineDateTime(zoomForm.date, zoomForm.time);

      if (isNaN(startDateTime.getTime())) {
        errors.push(__("Invalid start date/time", "tutorpress"));
      }

      // Check if start time is in the past (optional, but good UX)
      if (startDateTime < new Date()) {
        errors.push(__("Start time cannot be in the past", "tutorpress"));
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

    try {
      let liveLessonData: LiveLessonFormData;

      if (lessonType === "google_meet") {
        // Combine date and time fields
        const startDateTime = combineDateTime(googleMeetForm.startDate, googleMeetForm.startTime);
        const endDateTime = combineDateTime(googleMeetForm.endDate, googleMeetForm.endTime);

        // Convert form data to API format (matches previous implementation)
        liveLessonData = {
          title: googleMeetForm.title,
          description: googleMeetForm.summary,
          type: "google_meet",
          startDateTime: formatDateTimeForStorage(startDateTime),
          endDateTime: formatDateTimeForStorage(endDateTime),
          settings: {
            timezone: googleMeetForm.timezone,
            duration: Math.ceil((endDateTime.getTime() - startDateTime.getTime()) / (1000 * 60)),
            allowEarlyJoin: true,
            autoRecord: false,
            requirePassword: false,
            waitingRoom: false,
            add_enrolled_students: googleMeetForm.addEnrolledStudents ? "Yes" : "No",
          },
        };
      } else {
        // Combine date and time fields
        const startDateTime = combineDateTime(zoomForm.date, zoomForm.time);

        // Calculate end date based on duration
        const durationMs =
          zoomForm.durationUnit === "hours" ? zoomForm.duration * 60 * 60 * 1000 : zoomForm.duration * 60 * 1000;
        const endDate = new Date(startDateTime.getTime() + durationMs);

        // Convert form data to API format (matches previous implementation)
        liveLessonData = {
          title: zoomForm.title,
          description: zoomForm.summary,
          type: "zoom",
          startDateTime: formatDateTimeForStorage(startDateTime),
          endDateTime: formatDateTimeForStorage(endDate),
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

      // Use WordPress Data Store dispatch - different action for create vs update
      if (lessonId) {
        // Update existing lesson
        await updateLiveLesson(lessonId, liveLessonData);

        createNotice(
          "success",
          lessonType === "google_meet"
            ? __("Google Meet lesson updated successfully", "tutorpress")
            : __("Zoom lesson updated successfully", "tutorpress"),
          {
            type: "snackbar",
            isDismissible: true,
          }
        );
      } else {
        // Create new lesson
        await saveLiveLesson(liveLessonData, courseId || 0, topicId);

        createNotice(
          "success",
          lessonType === "google_meet"
            ? __("Google Meet lesson created successfully", "tutorpress")
            : __("Zoom lesson created successfully", "tutorpress"),
          {
            type: "snackbar",
            isDismissible: true,
          }
        );
      }

      // Close modal
      handleClose();
    } catch (error) {
      console.error("TutorPress: Failed to save Live Lesson:", error);
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
