import React, { useState } from "react";
import { BaseModalLayout } from "../../common/BaseModalLayout";
import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import SubscriptionPlanForm from "./SubscriptionPlanForm";
import type { SubscriptionPlan } from "../../../types/subscriptions";

interface SubscriptionModalProps {
  isOpen: boolean;
  onClose: () => void;
  isDirty?: boolean;
}

export const SubscriptionModal: React.FC<SubscriptionModalProps> = ({ isOpen, onClose, isDirty }) => {
  const [isFormVisible, setIsFormVisible] = useState(true); // Show form by default
  const [editingPlan, setEditingPlan] = useState<Partial<SubscriptionPlan> | null>(null);
  const [formMode, setFormMode] = useState<"add" | "edit" | "duplicate">("add");

  // Handle form save
  const handleFormSave = (planData: Partial<SubscriptionPlan>) => {
    console.log("Saving subscription plan:", planData);
    // TODO: Integrate with subscription store
    setIsFormVisible(false);
    setEditingPlan(null);
  };

  // Handle form cancel
  const handleFormCancel = () => {
    setIsFormVisible(false);
    setEditingPlan(null);
    setFormMode("add");
  };

  // Handle add new plan
  const handleAddPlan = () => {
    setEditingPlan(null);
    setFormMode("add");
    setIsFormVisible(true);
  };

  // Header with dynamic actions
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
      <div className="tutorpress-modal-content">
        {isFormVisible && (
          <SubscriptionPlanForm
            initialData={editingPlan || undefined}
            onSave={handleFormSave}
            onCancel={handleFormCancel}
            mode={formMode}
          />
        )}

        {/* Add New Plan Button - Bottom Left */}
        <div className="tutorpress-modal-footer">
          <Button variant="secondary" onClick={handleAddPlan} disabled={isFormVisible}>
            {__("Add New Plan", "tutorpress")}
          </Button>
        </div>
      </div>
    </BaseModalLayout>
  );
};
