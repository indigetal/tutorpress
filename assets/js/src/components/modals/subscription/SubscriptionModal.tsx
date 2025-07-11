import React, { useState, useEffect } from "react";
import { Modal } from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import { useSelect, useDispatch } from "@wordpress/data";
import SubscriptionPlanSection from "./SubscriptionPlanSection";
import type { SubscriptionPlan } from "../../../types/subscriptions";

interface SubscriptionModalProps {
  isOpen: boolean;
  onClose: () => void;
  isDirty?: boolean;
  courseId: number;
  initialPlan?: SubscriptionPlan | null;
  shouldShowForm?: boolean;
}

export const SubscriptionModal: React.FC<SubscriptionModalProps> = ({
  isOpen,
  onClose,
  isDirty,
  courseId,
  initialPlan,
  shouldShowForm = false,
}) => {
  // State for managing which plan is being edited (if any)
  const [editingPlanId, setEditingPlanId] = useState<number | null>(null);
  const [isNewPlanFormVisible, setIsNewPlanFormVisible] = useState(shouldShowForm);

  // Handle initial plan for editing when modal opens
  useEffect(() => {
    if (initialPlan && isOpen) {
      setEditingPlanId(initialPlan.id);
      setIsNewPlanFormVisible(false);
    } else if (!initialPlan && isOpen) {
      setEditingPlanId(null);
      setIsNewPlanFormVisible(shouldShowForm);
    }
  }, [initialPlan, isOpen, shouldShowForm]);

  // Get store state
  const { isLoading } = useSelect(
    (select: any) => ({
      isLoading: select("tutorpress/subscriptions").getSubscriptionPlansLoading(),
    }),
    []
  );

  const { resetForm } = useDispatch("tutorpress/subscriptions");

  // Handle form save
  const handleFormSave = (planData: Partial<SubscriptionPlan>) => {
    console.log("Saving subscription plan:", planData);
    setEditingPlanId(null);
    setIsNewPlanFormVisible(false);
    resetForm();
  };

  // Handle form cancel
  const handleFormCancel = () => {
    setEditingPlanId(null);
    setIsNewPlanFormVisible(false);
    resetForm();
  };

  // Handle plan edit toggle
  const handlePlanEditToggle = (planId: number) => {
    setEditingPlanId(editingPlanId === planId ? null : planId);
    setIsNewPlanFormVisible(false);
  };

  // Handle add new plan
  const handleAddNewPlan = () => {
    setEditingPlanId(null);
    setIsNewPlanFormVisible(true);
    resetForm();
  };

  if (!isOpen) return null;

  return (
    <Modal
      title={__("Subscription Plans", "tutorpress")}
      onRequestClose={onClose}
      className="subscription-modal"
      size="large"
    >
      <div className="tutorpress-modal-content">
        <SubscriptionPlanSection
          courseId={courseId}
          onFormSave={handleFormSave}
          onFormCancel={handleFormCancel}
          editingPlanId={editingPlanId}
          onPlanEditToggle={handlePlanEditToggle}
          isNewPlanFormVisible={isNewPlanFormVisible}
          onAddNewPlan={handleAddNewPlan}
        />
      </div>
    </Modal>
  );
};
