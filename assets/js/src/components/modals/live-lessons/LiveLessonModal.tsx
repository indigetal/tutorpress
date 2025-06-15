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
    startTime: "09:00 AM",
    endDate: new Date(),
    endTime: "10:00 AM",
    timezone: defaultTimezone,
    addEnrolledStudents: false,
  });

  // Zoom form state
  const [zoomForm, setZoomForm] = useState<ZoomFormData>({
    title: "",
    summary: "",
    date: new Date(),
    time: "09:00 AM",
    duration: 40,
    durationUnit: "minutes",
    timezone: defaultTimezone,
    autoRecording: "none",
    password: "",
    host: "default",
  });

  // Store dispatch and selectors
  const { createNotice } = useDispatch(noticesStore);
  const { saveLiveLesson } = useDispatch(CURRICULUM_STORE);

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
        setZoomForm({
          title: data.title || "",
          summary: data.description || "",
          date: new Date(data.startDateTime),
          time: formatTimeFromDateTime(data.startDateTime),
          duration: data.settings?.duration || 40,
          durationUnit: "minutes",
          timezone: data.settings?.timezone || defaultTimezone,
          autoRecording: data.settings?.autoRecord ? "cloud" : "none",
          password: data.password || "",
          host: "default",
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
      startTime: "09:00 AM",
      endDate: oneHourLater,
      endTime: "10:00 AM",
      timezone: defaultTimezone,
      addEnrolledStudents: false,
    });

    setZoomForm({
      title: "",
      summary: "",
      date: now,
      time: "09:00 AM",
      duration: 40,
      durationUnit: "minutes",
      timezone: defaultTimezone,
      autoRecording: "none",
      password: "",
      host: "default",
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
   * Validate form data
   */
  const validateForm = (): { isValid: boolean; errors: string[] } => {
    const errors: string[] = [];

    if (lessonType === "google_meet") {
      if (!googleMeetForm.title.trim()) {
        errors.push(__("Meeting name is required", "tutorpress"));
      }

      const startDateTime = combineDateTime(googleMeetForm.startDate, googleMeetForm.startTime);
      const endDateTime = combineDateTime(googleMeetForm.endDate, googleMeetForm.endTime);

      if (endDateTime <= startDateTime) {
        errors.push(__("End time must be after start time", "tutorpress"));
      }
    } else if (lessonType === "zoom") {
      if (!zoomForm.title.trim()) {
        errors.push(__("Meeting name is required", "tutorpress"));
      }
      if (zoomForm.duration <= 0) {
        errors.push(__("Duration must be greater than 0", "tutorpress"));
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

      // Use WordPress Data Store dispatch (matches previous implementation)
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

      // Close modal
      handleClose();
    } catch (error) {
      console.error("TutorPress: Failed to save Live Lesson:", error);
      createNotice(
        "error",
        lessonType === "google_meet"
          ? __("Failed to create Google Meet lesson", "tutorpress")
          : __("Failed to create Zoom lesson", "tutorpress"),
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
    <Modal title={getModalTitle()} onRequestClose={handleClose} className="tutorpress-live-lesson-modal" size="large">
      <div className="tutorpress-modal-content">
        {/* Loading State */}
        {isLoading && (
          <div className="tutorpress-modal-loading">
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
        <div className="tutorpress-modal-actions">
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
