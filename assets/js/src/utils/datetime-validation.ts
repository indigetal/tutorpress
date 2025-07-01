/**
 * DateTime Validation Utilities
 *
 * Focused validation utilities for TutorPress date/time handling.
 * Provides validation logic for Course Access Panel and Google Meet Live Lessons
 * without over-engineering.
 *
 * @package TutorPress
 * @subpackage Utils
 * @since 1.6.0
 */

// ============================================================================
// TYPESCRIPT INTERFACES
// ============================================================================

/**
 * Time option for dropdowns
 */
export interface DateTimeOption {
  label: string;
  value: string;
}

/**
 * Result of date/time validation
 */
export interface DateTimeValidationResult {
  isValid: boolean;
  errors: string[];
  correctedDateTime?: string;
}

// ============================================================================
// CORE GMT UTILITIES (for Course Access Panel)
// ============================================================================

/**
 * Convert local date to GMT format for storage
 *
 * Used by Course Access Panel for GMT storage compatibility with Tutor LMS
 */
export const convertToGMT = (localDate: Date): string => {
  return localDate.toISOString().slice(0, 19).replace("T", " ");
};

/**
 * Parse GMT string to Date object
 *
 * Handles both "2024-12-20 14:30:00" and ISO formats
 */
export const parseGMTString = (gmtString: string | null | undefined): Date | null => {
  if (!gmtString || gmtString.trim() === "") {
    return null;
  }

  try {
    // Handle both formats: "2024-12-20 14:30:00" and ISO format
    const isoString = gmtString.includes("T") ? gmtString : gmtString.replace(" ", "T") + "Z";
    const date = new Date(isoString);

    if (isNaN(date.getTime())) {
      return null;
    }

    return date;
  } catch (error) {
    return null;
  }
};

/**
 * Display date in user-friendly format
 *
 * Converts GMT storage back to user-friendly display
 */
export const displayDate = (gmtString: string | null | undefined): string => {
  const date = parseGMTString(gmtString);
  return date
    ? date.toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      })
    : new Date().toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric",
      });
};

/**
 * Display time in user-friendly format
 *
 * Converts GMT storage back to user-friendly 12-hour time
 */
export const displayTime = (gmtString: string | null | undefined): string => {
  const date = parseGMTString(gmtString);
  return date
    ? date.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      })
    : new Date().toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      });
};

/**
 * Combine date and time strings into GMT datetime
 *
 * Essential for converting separate date/time inputs to GMT storage
 */
export const combineDateTime = (localDate: Date, localTimeStr: string): string => {
  // Parse the time string to get hours and minutes
  const timeParts = localTimeStr.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
  if (!timeParts) {
    // Fallback to current time if parsing fails
    return convertToGMT(localDate);
  }

  let hours = parseInt(timeParts[1], 10);
  const minutes = parseInt(timeParts[2], 10);
  const ampm = timeParts[3].toUpperCase();

  // Convert to 24-hour format
  if (ampm === "PM" && hours !== 12) {
    hours += 12;
  } else if (ampm === "AM" && hours === 12) {
    hours = 0;
  }

  // Create new date with the specified time
  const combinedLocalDate = new Date(localDate);
  combinedLocalDate.setHours(hours, minutes, 0, 0);

  return convertToGMT(combinedLocalDate);
};

// ============================================================================
// SHARED VALIDATION UTILITIES (for both Course Access Panel and Google Meet)
// ============================================================================

/**
 * Generate time options with configurable intervals
 *
 * Replaces inline generateTimeOptions functions in both components
 * Standardizes on 30-minute intervals across TutorPress
 */
export const generateTimeOptions = (interval: number = 30): DateTimeOption[] => {
  const options: DateTimeOption[] = [];
  for (let hour = 0; hour < 24; hour++) {
    for (let minute = 0; minute < 60; minute += interval) {
      const date = new Date();
      date.setHours(hour, minute);
      const timeStr = date.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      });

      options.push({
        label: timeStr,
        value: timeStr,
      });
    }
  }
  return options;
};

/**
 * Filter end time options based on start time
 *
 * Prevents selecting end times before start time on same date
 * Used by both Course Access Panel and Google Meet for validation
 */
export const filterEndTimeOptions = (
  allTimeOptions: Array<{ label: string; value: string }>,
  startDate: Date,
  startTime: string,
  endDate: Date
) => {
  // If different dates, no filtering needed
  if (startDate.toDateString() !== endDate.toDateString()) {
    return allTimeOptions;
  }

  // Find start time index and return options after it
  const startTimeIndex = allTimeOptions.findIndex((option) => option.value === startTime);
  if (startTimeIndex >= 0) {
    return allTimeOptions.slice(startTimeIndex + 1);
  }

  return allTimeOptions;
};

/**
 * Auto-correct end date when start date is later
 *
 * Simple utility: if start date > end date, auto-correct end date to match start date
 * No blocking, no complex strategies - just helpful auto-correction
 */
export const autoCorrectEndDate = (
  startDate: Date,
  endDate: Date
): { shouldCorrect: boolean; correctedEndDate?: Date } => {
  // Compare just the date part (not time)
  const startDateOnly = new Date(startDate.getFullYear(), startDate.getMonth(), startDate.getDate());
  const endDateOnly = new Date(endDate.getFullYear(), endDate.getMonth(), endDate.getDate());

  if (startDateOnly > endDateOnly) {
    return {
      shouldCorrect: true,
      correctedEndDate: new Date(startDate),
    };
  }

  return { shouldCorrect: false };
};

/**
 * Auto-correct end time when it's too close to start time
 *
 * Simple utility: if end time <= start time, auto-correct to minimum duration after start
 * Used by both Course Access Panel and Google Meet for auto-correction
 */
export const validateAndCorrectMeetingTime = (
  startDate: Date,
  startTime: string,
  endDate: Date,
  endTime: string,
  minDurationMinutes: number = 30
): { correctedEndTime?: string } => {
  // Convert to Date objects for comparison
  const startDateTime = new Date(startDate);
  const endDateTime = new Date(endDate);

  // Parse and set times
  const startTimeParts = startTime.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);
  const endTimeParts = endTime.match(/(\d{1,2}):(\d{2})\s*(AM|PM)/i);

  if (!startTimeParts || !endTimeParts) {
    return {};
  }

  // Set start time
  let startHour = parseInt(startTimeParts[1], 10);
  if (startTimeParts[3].toUpperCase() === "PM" && startHour !== 12) startHour += 12;
  if (startTimeParts[3].toUpperCase() === "AM" && startHour === 12) startHour = 0;
  startDateTime.setHours(startHour, parseInt(startTimeParts[2], 10), 0, 0);

  // Set end time
  let endHour = parseInt(endTimeParts[1], 10);
  if (endTimeParts[3].toUpperCase() === "PM" && endHour !== 12) endHour += 12;
  if (endTimeParts[3].toUpperCase() === "AM" && endHour === 12) endHour = 0;
  endDateTime.setHours(endHour, parseInt(endTimeParts[2], 10), 0, 0);

  // Check if end is before/equal to start - auto-correct if needed
  if (endDateTime <= startDateTime) {
    const correctedEnd = new Date(startDateTime.getTime() + minDurationMinutes * 60 * 1000);
    return {
      correctedEndTime: correctedEnd.toLocaleTimeString("en-US", {
        hour: "numeric",
        minute: "2-digit",
        hour12: true,
      }),
    };
  }

  return {};
};

/**
 * Simple datetime validation with auto-correction
 *
 * Auto-corrects end date when start date is later, then validates times
 * Simple and consistent behavior for all datetime components
 */
export const validateAndCorrectDateTime = (
  startDate: Date,
  startTime: string,
  endDate: Date,
  endTime: string,
  minDurationMinutes: number = 30
): {
  correctedEndDate?: Date;
  correctedEndTime?: string;
} => {
  // Auto-correct end date if start date is later
  const dateCorrection = autoCorrectEndDate(startDate, endDate);
  const finalEndDate = dateCorrection.correctedEndDate || endDate;

  // Validate times with final dates
  const timeValidation = validateAndCorrectMeetingTime(startDate, startTime, finalEndDate, endTime, minDurationMinutes);

  return {
    correctedEndDate: dateCorrection.correctedEndDate,
    correctedEndTime: timeValidation.correctedEndTime,
  };
};
