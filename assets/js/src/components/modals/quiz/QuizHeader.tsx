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
      <div className="quiz-modal-header-actions">
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
