/**
 * Base Modal Header Component
 *
 * @description Reusable modal header component extracted from Quiz Modal to support DRY principles.
 *              Provides consistent header layout with title and action buttons that can be shared
 *              across different modal types (Quiz, Interactive Quiz, etc.). Maintains compatibility
 *              with existing quiz-modal CSS classes and supports customizable button text and states.
 *              Includes built-in support for unsaved changes confirmation when closing.
 *
 * @features
 * - Customizable modal title
 * - Primary save/update button with loading states
 * - Secondary cancel button with unsaved changes confirmation
 * - Validation state handling (disabled when invalid)
 * - Success state indication
 * - Flexible button text for different contexts
 * - Built-in dirty state checking with confirmation dialog
 *
 * @usage
 * <BaseModalHeader
 *   title="Create Quiz"
 *   isValid={isValid}
 *   isDirty={isDirty}
 *   isSaving={isSaving}
 *   saveSuccess={saveSuccess}
 *   primaryButtonText="Save Quiz"
 *   savingButtonText="Saving..."
 *   successButtonText="Saved!"
 *   onSave={handleSave}
 *   onClose={handleClose}
 * />
 *
 * @package TutorPress
 * @subpackage Components/Common
 * @since 1.0.0
 */

import React from "react";
import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export interface BaseModalHeaderProps {
  /** Modal title to display */
  title: string;
  /** Whether the form is currently valid */
  isValid: boolean;
  /** Whether a save operation is in progress */
  isSaving: boolean;
  /** Whether the save operation was successful */
  saveSuccess?: boolean;
  /** Whether the form has unsaved changes */
  isDirty?: boolean;
  /** Text for the primary save button */
  primaryButtonText?: string;
  /** Text shown during saving */
  savingButtonText?: string;
  /** Text shown after successful save */
  successButtonText?: string;
  /** Text for the cancel button */
  cancelButtonText?: string;
  /** Custom confirmation message for unsaved changes */
  unsavedChangesMessage?: string;
  /** Function to call when save is clicked */
  onSave: () => void;
  /** Function to call when cancel/close is clicked */
  onClose: () => void;
  /** Additional CSS class name for the header */
  className?: string;
}

export const BaseModalHeader: React.FC<BaseModalHeaderProps> = ({
  title,
  isValid,
  isSaving,
  saveSuccess = false,
  isDirty,
  primaryButtonText = __("Save", "tutorpress"),
  savingButtonText = __("Saving...", "tutorpress"),
  successButtonText = __("Saved!", "tutorpress"),
  cancelButtonText = __("Cancel", "tutorpress"),
  unsavedChangesMessage,
  onSave,
  onClose,
  className = "quiz-modal",
}) => {
  // Handle close with dirty check
  const handleClose = () => {
    if (isDirty) {
      const message =
        unsavedChangesMessage || __("You have unsaved changes. Are you sure you want to close?", "tutorpress");
      if (confirm(message)) {
        onClose();
      }
    } else {
      onClose();
    }
  };

  // Determine button text based on current state
  const getButtonText = () => {
    if (isSaving) {
      return savingButtonText;
    }
    if (saveSuccess) {
      return successButtonText;
    }
    return primaryButtonText;
  };

  return (
    <div className={`${className}-header`}>
      <h1 className={`${className}-title`}>{title}</h1>
      <div className={`${className}-header-actions`}>
        <Button variant="secondary" onClick={handleClose} disabled={isSaving}>
          {cancelButtonText}
        </Button>
        <Button variant="primary" onClick={onSave} disabled={!isValid || isSaving || saveSuccess} isBusy={isSaving}>
          {getButtonText()}
        </Button>
      </div>
    </div>
  );
};
