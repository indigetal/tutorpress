import React from "react";
import { BaseModalLayout } from "../../common/BaseModalLayout";
import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";

interface SubscriptionModalProps {
  isOpen: boolean;
  onClose: () => void;
  isDirty?: boolean;
}

export const SubscriptionModal: React.FC<SubscriptionModalProps> = ({ isOpen, onClose, isDirty }) => {
  // Placeholder header with Cancel/Save buttons
  const header = (
    <div className="subscription-modal-header">
      <h1 className="subscription-modal-title">{__("Subscription Plans", "tutorpress")}</h1>
      <div className="subscription-modal-header-actions tpress-header-actions-group">
        <Button variant="secondary" onClick={onClose}>
          {__("Cancel", "tutorpress")}
        </Button>
        <Button variant="primary" disabled>
          {__("Save", "tutorpress")}
        </Button>
      </div>
    </div>
  );

  return (
    <BaseModalLayout isOpen={isOpen} onClose={onClose} isDirty={isDirty} className="subscription-modal" header={header}>
      {/* Placeholder for plan list and form */}
      <div style={{ padding: 32, textAlign: "center" }}>
        <p>{__("Subscription modal content coming soon...", "tutorpress")}</p>
      </div>
    </BaseModalLayout>
  );
};
