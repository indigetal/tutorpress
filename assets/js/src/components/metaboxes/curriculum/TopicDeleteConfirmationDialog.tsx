/**
 * Topic deletion confirmation dialog.
 *
 * Composes stable WordPress Core Modal and Button APIs (WP 5.8–7.1).
 */
import React from "react";
import { Button, Modal } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

export interface TopicDeleteConfirmationDialogProps {
  isOpen: boolean;
  isBusy: boolean;
  onCancel: () => void;
  onConfirm: () => void;
}

export const TopicDeleteConfirmationDialog: React.FC<
  TopicDeleteConfirmationDialogProps
> = ({ isOpen, isBusy, onCancel, onConfirm }) => {
  if (!isOpen) {
    return null;
  }

  const handleRequestClose = () => {
    if (!isBusy) {
      onCancel();
    }
  };

  return (
    <Modal
      title={__("Delete topic?", "tutorpress")}
      onRequestClose={handleRequestClose}
      isDismissible={!isBusy}
    >
      <p>
        {__(
          "Are you sure you want to delete this topic? Any lessons, quizzes, assignments, or live lessons in it will also be permanently deleted. This action cannot be undone.",
          "tutorpress",
        )}
      </p>
      <Button onClick={onCancel} disabled={isBusy}>
        {__("Cancel", "tutorpress")}
      </Button>
      <Button
        isDestructive
        isBusy={isBusy}
        disabled={isBusy}
        onClick={onConfirm}
      >
        {__("Delete", "tutorpress")}
      </Button>
    </Modal>
  );
};

export default TopicDeleteConfirmationDialog;
