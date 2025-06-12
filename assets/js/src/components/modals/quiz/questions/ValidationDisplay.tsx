/**
 * Validation Display Component
 *
 * @description Standardized validation error display component for quiz questions.
 *              Provides consistent error messaging, styling, and presentation across
 *              all question types. Created during Phase 2.5 refactoring to eliminate
 *              validation display code duplication and enhance error user experience.
 *
 * @features
 * - Consistent error display styling
 * - Support for different error severity levels
 * - Optional error icons and visual indicators
 * - Customizable container styling
 * - Accessibility features (ARIA labels)
 * - Animation support for error transitions
 * - Empty state handling
 * - Internationalization support
 *
 * @usage
 * // Basic usage with validation errors
 * <ValidationDisplay
 *   errors={validationErrors}
 *   show={showValidationErrors}
 * />
 *
 * // Advanced usage with custom styling
 * <ValidationDisplay
 *   errors={validationErrors}
 *   show={showValidationErrors}
 *   severity="warning"
 *   showIcons={true}
 *   className="custom-validation-style"
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Questions
 * @since 1.0.0
 */

import React from "react";
import { Icon } from "@wordpress/components";
import { warning, info } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";

/**
 * Error severity levels
 */
export type ValidationSeverity = "error" | "warning" | "info";

/**
 * Individual validation error interface
 */
export interface ValidationError {
  /** Error message text */
  message: string;
  /** Error code for identification */
  code?: string;
  /** Field that triggered the error */
  field?: string;
  /** Error severity level */
  severity?: ValidationSeverity;
}

/**
 * Validation Display component props
 */
export interface ValidationDisplayProps {
  /** Array of validation errors to display */
  errors: string[] | ValidationError[];
  /** Whether to show the validation errors */
  show: boolean;
  /** Default severity level for string errors */
  severity?: ValidationSeverity;
  /** Whether to show icons next to errors */
  showIcons?: boolean;
  /** Custom CSS class for the container */
  className?: string;
  /** Custom CSS class for individual error items */
  errorClassName?: string;
  /** Whether to animate error transitions */
  animated?: boolean;
  /** Maximum number of errors to display */
  maxErrors?: number;
  /** Custom aria label for accessibility */
  ariaLabel?: string;
}

/**
 * Get icon component for severity level
 */
const getSeverityIcon = (severity: ValidationSeverity) => {
  switch (severity) {
    case "error":
      return warning;
    case "warning":
      return warning;
    case "info":
      return info;
    default:
      return warning;
  }
};

/**
 * Get CSS class for severity level
 */
const getSeverityClass = (severity: ValidationSeverity): string => {
  switch (severity) {
    case "error":
      return "quiz-modal-validation-error";
    case "warning":
      return "quiz-modal-validation-warning";
    case "info":
      return "quiz-modal-validation-info";
    default:
      return "quiz-modal-validation-error";
  }
};

/**
 * Normalize errors to consistent format
 */
const normalizeErrors = (
  errors: string[] | ValidationError[],
  defaultSeverity: ValidationSeverity = "error"
): ValidationError[] => {
  return errors.map((error, index) => {
    if (typeof error === "string") {
      return {
        message: error,
        code: `error_${index}`,
        severity: defaultSeverity,
      };
    }
    return {
      ...error,
      severity: error.severity || defaultSeverity,
      code: error.code || `error_${index}`,
    };
  });
};

/**
 * Validation Display Component
 */
export const ValidationDisplay: React.FC<ValidationDisplayProps> = ({
  errors,
  show,
  severity = "error",
  showIcons = false,
  className = "",
  errorClassName = "",
  animated = false,
  maxErrors,
  ariaLabel,
}) => {
  // Don't render if not showing or no errors
  if (!show || !errors.length) {
    return null;
  }

  // Normalize errors to consistent format
  const normalizedErrors = normalizeErrors(errors, severity);

  // Limit errors if maxErrors is specified
  const displayErrors = maxErrors ? normalizedErrors.slice(0, maxErrors) : normalizedErrors;
  const hasMoreErrors = maxErrors && normalizedErrors.length > maxErrors;

  // Determine primary severity (highest severity in the list)
  const primarySeverity = normalizedErrors.reduce((highest, error) => {
    const severityOrder = { error: 3, warning: 2, info: 1 };
    const currentLevel = severityOrder[error.severity || "error"];
    const highestLevel = severityOrder[highest];
    return currentLevel > highestLevel ? error.severity || "error" : highest;
  }, "info" as ValidationSeverity);

  // Build container classes
  const containerClasses = [
    getSeverityClass(primarySeverity),
    animated ? "quiz-modal-validation-animated" : "",
    className,
  ]
    .filter(Boolean)
    .join(" ");

  // Build aria label
  const finalAriaLabel =
    ariaLabel ||
    (primarySeverity === "error"
      ? __("Validation errors", "tutorpress")
      : primarySeverity === "warning"
      ? __("Validation warnings", "tutorpress")
      : __("Validation information", "tutorpress"));

  return (
    <div className={containerClasses} role="alert" aria-live="polite" aria-label={finalAriaLabel}>
      {displayErrors.map((error) => {
        const errorClasses = ["quiz-modal-validation-item", `quiz-modal-validation-${error.severity}`, errorClassName]
          .filter(Boolean)
          .join(" ");

        return (
          <div key={error.code} className={errorClasses}>
            {showIcons && (
              <Icon icon={getSeverityIcon(error.severity || "error")} className="quiz-modal-validation-icon" />
            )}
            <span className="quiz-modal-validation-message">{error.message}</span>
          </div>
        );
      })}

      {hasMoreErrors && (
        <div className="quiz-modal-validation-more">
          {__(`… and ${normalizedErrors.length - (maxErrors || 0)} more errors`, "tutorpress")}
        </div>
      )}
    </div>
  );
};
