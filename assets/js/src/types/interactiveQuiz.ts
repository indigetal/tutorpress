/**
 * Interactive Quiz Type Definitions for TutorPress
 *
 * @description Type definitions for H5P Interactive Quiz Modal settings.
 *              Defines a subset of quiz settings specifically needed for Interactive Quiz Modal,
 *              which has fewer settings than the regular Quiz Modal but uses the same SettingsTab component.
 *
 * @package TutorPress
 * @subpackage Types/InteractiveQuiz
 * @since 1.0.0
 */

import type { QuestionOrder } from "./quiz";

/**
 * Interactive Quiz Settings Interface
 *
 * Subset of quiz settings needed for H5P Interactive Quiz Modal.
 * Based on user specification: only 4 settings are needed.
 */
export interface InteractiveQuizSettings {
  /** Number of attempts allowed (0 = unlimited) */
  attemptsAllowed: number;

  /** Minimum percentage required to pass (0-100) */
  passingGrade: number;

  /** Whether quiz starts automatically when page loads */
  quizAutoStart: boolean;

  /** Order in which questions are presented */
  questionsOrder: QuestionOrder;
}

/**
 * Props interface for SettingsTab when used with Interactive Quiz Modal
 *
 * This interface defines the minimal props needed to render SettingsTab
 * for Interactive Quiz, making most Quiz-specific props optional.
 */
export interface InteractiveQuizSettingsTabProps {
  // Required Interactive Quiz settings
  attemptsAllowed: number;
  passingGrade: number;
  quizAutoStart: boolean;
  questionsOrder: QuestionOrder;

  // Required UI state
  isSaving: boolean;
  saveSuccess: boolean;
  saveError: string | null;

  // Required handlers
  onSettingChange: (settings: Record<string, any>) => void;
  onSaveErrorDismiss: () => void;

  // Required validation errors (subset)
  errors: {
    passingGrade?: string;
    attemptsAllowed?: string;
  };
}

/**
 * Default Interactive Quiz Settings
 */
export const getDefaultInteractiveQuizSettings = (): InteractiveQuizSettings => ({
  attemptsAllowed: 0, // Unlimited attempts by default
  passingGrade: 80, // 80% passing grade
  quizAutoStart: false, // Manual start by default
  questionsOrder: "sorting", // Default sorting order
});
