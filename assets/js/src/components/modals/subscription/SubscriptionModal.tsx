import React, { useState } from "react";
import { BaseModalLayout } from "../../common/BaseModalLayout";
import { Button } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import SubscriptionPlanForm from "./SubscriptionPlanForm";
import SubscriptionPlanSection from "./SubscriptionPlanSection";
import type { SubscriptionPlan } from "../../../types/subscriptions";

interface SubscriptionModalProps {
  isOpen: boolean;
  onClose: () => void;
  isDirty?: boolean;
  courseId: number;
}

export const SubscriptionModal: React.FC<SubscriptionModalProps> = ({ isOpen, onClose, isDirty, courseId }) => {
  const [isFormVisible, setIsFormVisible] = useState(false); // Show plan list by default
  const [editingPlan, setEditingPlan] = useState<Partial<SubscriptionPlan> | null>(null);
  const [formMode, setFormMode] = useState<"add" | "edit" | "duplicate">("add");

  // Get store state
  const { formDirty, isLoading } = useSelect(
    (select: any) => ({
      formDirty: select("tutorpress/subscriptions").getFormDirty(),
      isLoading: select("tutorpress/subscriptions").getSubscriptionPlansLoading(),
    }),
    []
  );

  const { resetForm } = useDispatch("tutorpress/subscriptions");

  // Handle form save
  const handleFormSave = (planData: Partial<SubscriptionPlan>) => {
    console.log("Saving subscription plan:", planData);
    setIsFormVisible(false);
    setEditingPlan(null);
    resetForm();
  };

  // Handle form cancel
  const handleFormCancel = () => {
    setIsFormVisible(false);
    setEditingPlan(null);
    setFormMode("add");
    resetForm();
  };

  // Handle add new plan
  const handleAddPlan = () => {
    setEditingPlan(null);
    setFormMode("add");
    setIsFormVisible(true);
    resetForm();
  };

  // Header with dynamic actions
  const header = (
    <div className="subscription-modal-header">
      <h1 className="subscription-modal-title">{__("Subscription Plans", "tutorpress")}</h1>
      <div className="subscription-modal-header-actions tpress-header-actions-group">
        <Button variant="secondary" onClick={onClose} disabled={isLoading}>
          {__("Cancel", "tutorpress")}
        </Button>
        <Button variant="primary" disabled={!formDirty || isLoading}>
          {__("Save", "tutorpress")}
        </Button>
      </div>
    </div>
  );

  return (
    <BaseModalLayout isOpen={isOpen} onClose={onClose} isDirty={isDirty} className="subscription-modal" header={header}>
      <div className="tutorpress-modal-content">
        {/* Plan List Section - Above Form */}
        {!isFormVisible && (
          <SubscriptionPlanSection courseId={courseId} onFormSave={handleFormSave} onFormCancel={handleFormCancel} />
        )}

        {/* Form Section - Below Plan List */}
        {isFormVisible && (
          <SubscriptionPlanForm
            initialData={editingPlan || undefined}
            onSave={handleFormSave}
            onCancel={handleFormCancel}
            mode={formMode}
          />
        )}
      </div>
    </BaseModalLayout>
  );
};
