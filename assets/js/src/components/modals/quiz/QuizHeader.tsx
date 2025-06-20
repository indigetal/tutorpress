/**
 * Quiz Modal Header Component
 *
 * @description Header section for the quiz modal containing the title and primary action buttons.
 *              Displays context-aware labels for creating vs editing quizzes and manages button
 *              states including loading, validation, and success states. Extracted from QuizModal
 *              during Phase 1 refactoring for better separation of concerns.
 *
 * @features
 * - Context-aware title (Create vs Edit Quiz)
 * - Save/Update button with loading states
 * - Cancel button functionality
 * - Validation state handling
 * - Success state indication
 *
 * @usage
 * <QuizHeader
 *   quizId={quizId}
 *   isValid={isValid}
 *   isSaving={isSaving}
 *   onSave={handleSave}
 *   onClose={handleClose}
 * />
 *
 * @package TutorPress
 * @subpackage Quiz/Components
 * @since 1.0.0
 */

import React from "react";
import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

interface QuizHeaderProps {
  quizId?: number;
  isValid: boolean;
  isSaving: boolean;
  saveSuccess: boolean;
  onSave: () => void;
  onClose: () => void;
}

export const QuizHeader: React.FC<QuizHeaderProps> = ({ quizId, isValid, isSaving, saveSuccess, onSave, onClose }) => {
  return (
    <div className="quiz-modal-header">
      <h1 className="quiz-modal-title">{quizId ? __("Edit Quiz", "tutorpress") : __("Create Quiz", "tutorpress")}</h1>
      <div className="quiz-modal-header-actions tpress-header-actions-group">
        <Button variant="secondary" onClick={onClose} disabled={isSaving}>
          {__("Cancel", "tutorpress")}
        </Button>
        <Button variant="primary" onClick={onSave} disabled={!isValid || isSaving || saveSuccess} isBusy={isSaving}>
          {isSaving
            ? quizId
              ? __("Updating...", "tutorpress")
              : __("Saving...", "tutorpress")
            : saveSuccess
              ? __("Saved!", "tutorpress")
              : quizId
                ? __("Update Quiz", "tutorpress")
                : __("Save Quiz", "tutorpress")}
        </Button>
      </div>
    </div>
  );
};
