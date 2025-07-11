/**
 * SubscriptionPlanSection.tsx
 *
 * Main component for displaying and managing subscription plans in the Subscription Modal.
 * This component handles the entire subscription plan section including:
 * - Plan list display with drag/drop functionality and action buttons
 * - Form integration for adding/editing subscription plans
 * - Modal management for plan operations
 * - Responsive button layout with proper state management
 *
 * Key Features:
 * - Drag and drop reordering via @dnd-kit using useSortableList hook
 * - Integration with WordPress Data Store for all operations
 * - Action buttons (edit, duplicate, delete) using ActionButtons component
 * - Form integration with SubscriptionPlanForm
 * - Responsive design with WordPress admin styling consistency
 * - Form validation and user feedback via WordPress notices
 *
 * @package TutorPress
 * @subpackage Subscription/Components
 * @since 1.0.0
 */

import React, { type MouseEvent, useState, useEffect } from "react";
import { Card, CardBody, Button, Icon, Flex, FlexBlock, Spinner, Notice } from "@wordpress/components";
import { dragHandle, plus, chevronDown, chevronRight } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";
import { DndContext, DragOverlay } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { useSelect, useDispatch } from "@wordpress/data";
import { store as noticesStore } from "@wordpress/notices";
import type { SubscriptionPlan } from "../../../types/subscriptions";
import ActionButtons from "../../metaboxes/curriculum/ActionButtons";
import SubscriptionPlanForm from "./SubscriptionPlanForm";
import { useSortableList } from "../../../hooks/common/useSortableList";
import { useError } from "../../../hooks/useError";

const SUBSCRIPTION_STORE = "tutorpress/subscriptions";

/**
 * Props for subscription plan row
 */
interface SubscriptionPlanRowProps {
  plan: SubscriptionPlan;
  onEdit?: () => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
  dragHandleProps?: any;
  className?: string;
  style?: React.CSSProperties;
}

/**
 * Subscription plan icon mapping
 */
const planTypeIcons = {
  course: "list-view",
} as const;

/**
 * Renders a single subscription plan
 * @param {SubscriptionPlanRowProps} props - Component props
 * @param {SubscriptionPlan} props.plan - The subscription plan to display
 * @param {Function} [props.onEdit] - Optional edit handler
 * @param {Function} [props.onDuplicate] - Optional duplicate handler
 * @param {Function} [props.onDelete] - Optional delete handler
 * @param {Object} [props.dragHandleProps] - Props for drag handle
 * @param {string} [props.className] - Additional CSS classes
 * @param {Object} [props.style] - Additional inline styles
 * @return {JSX.Element} Subscription plan row component
 */
const SubscriptionPlanRow: React.FC<SubscriptionPlanRowProps> = ({
  plan,
  onEdit,
  onDuplicate,
  onDelete,
  dragHandleProps,
  className,
  style,
}): JSX.Element => (
  <div className={`tutorpress-subscription-plan ${className || ""}`} style={style}>
    <Flex align="center" gap={2}>
      <div className="tutorpress-subscription-plan-icon tpress-flex-shrink-0">
        <Button icon={dragHandle} label="Drag to reorder" isSmall {...dragHandleProps} />
      </div>
      <FlexBlock style={{ textAlign: "left" }}>
        <div className="tutorpress-subscription-plan-title">
          {plan.plan_name}{" "}
          <span className="plan-cost-interval">
            • ${plan.regular_price} / {plan.recurring_value} {plan.recurring_interval}
          </span>
          {plan.sale_price && plan.sale_price > 0 && (
            <span className="tutorpress-subscription-plan-sale"> (Sale: ${plan.sale_price})</span>
          )}
          {plan.is_featured && <span className="tutorpress-subscription-plan-featured"> • Featured</span>}
        </div>
      </FlexBlock>
      <div className="tpress-item-actions-right">
        <ActionButtons onEdit={onEdit} onDuplicate={onDuplicate} onDelete={onDelete} />
      </div>
    </Flex>
  </div>
);

/**
 * Sortable wrapper for subscription plans
 */
const SortableSubscriptionPlan: React.FC<SubscriptionPlanRowProps> = (props): JSX.Element => {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: props.plan.id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
    ...props.style,
  };

  return (
    <div ref={setNodeRef} style={style}>
      <SubscriptionPlanRow
        {...props}
        dragHandleProps={{
          ...attributes,
          ...listeners,
          ref: setActivatorNodeRef,
        }}
        className={isDragging ? "tutorpress-subscription-plan--dragging" : ""}
      />
    </div>
  );
};

/**
 * Props for subscription plan section
 */
interface SubscriptionPlanSectionProps {
  courseId: number;
  onFormSave: (planData: Partial<SubscriptionPlan>) => void;
  onFormCancel: () => void;
}

/**
 * Main subscription plan section component
 */
export const SubscriptionPlanSection: React.FC<SubscriptionPlanSectionProps> = ({
  courseId,
  onFormSave,
  onFormCancel,
}): JSX.Element => {
  // Form state
  const [isFormVisible, setIsFormVisible] = useState(false);
  const [editingPlan, setEditingPlan] = useState<Partial<SubscriptionPlan> | null>(null);
  const [formMode, setFormMode] = useState<"add" | "edit" | "duplicate">("add");

  // Get store state and actions
  const {
    plans,
    isLoading,
    error: storeError,
    sortingLoading,
    sortingError,
  } = useSelect(
    (select: any) => ({
      plans: select(SUBSCRIPTION_STORE).getSubscriptionPlans(),
      isLoading: select(SUBSCRIPTION_STORE).getSubscriptionPlansLoading(),
      error: select(SUBSCRIPTION_STORE).getSubscriptionPlansError(),
      sortingLoading: select(SUBSCRIPTION_STORE).getSortingLoading(),
      sortingError: select(SUBSCRIPTION_STORE).getSortingError(),
    }),
    []
  );

  const { deleteSubscriptionPlan, duplicateSubscriptionPlan, sortSubscriptionPlans, setSelectedPlan, resetForm } =
    useDispatch(SUBSCRIPTION_STORE);

  // Get the resolver directly for fetching plans
  const { getSubscriptionPlans } = useDispatch(SUBSCRIPTION_STORE);

  // Get notice actions
  const { createNotice } = useDispatch(noticesStore);

  // Error handling for sorting operations
  const { showError: showSortingError, handleDismissError: handleDismissSortingError } = useError({
    states: [{ status: sortingLoading ? "reordering" : "idle", error: sortingError }],
    isError: (state) => state.status === "error",
  });

  // Show sorting errors as notices
  useEffect(() => {
    if (showSortingError && sortingError) {
      createNotice("error", sortingError, {
        isDismissible: true,
        type: "snackbar",
        onDismiss: handleDismissSortingError,
      });
    }
  }, [showSortingError, sortingError, createNotice, handleDismissSortingError]);

  // Fetch plans on mount
  useEffect(() => {
    if (courseId && getSubscriptionPlans) {
      // Call the resolver directly to fetch plans
      getSubscriptionPlans();
    }
  }, [courseId, getSubscriptionPlans]);

  // Handle plan reordering
  const handlePlanReorder = async (newOrder: SubscriptionPlan[]) => {
    try {
      const planOrder = newOrder.map((plan) => plan.id);
      await sortSubscriptionPlans(planOrder);
      createNotice("success", __("Subscription plans reordered successfully.", "tutorpress"), {
        type: "snackbar",
      });
      return { success: true };
    } catch (error) {
      console.error("Error reordering subscription plans:", error);
      return { success: false, error: { code: "reorder_failed", message: String(error) } };
    }
  };

  // Drag and drop configuration
  const { dragHandlers, dragState, sensors, itemIds, getItemClasses, getWrapperClasses } = useSortableList({
    items: plans,
    onReorder: handlePlanReorder,
    persistenceMode: "api",
    context: "topics", // Reuse topics context for similar styling
  });

  // Handle plan edit
  const handlePlanEdit = (plan: SubscriptionPlan) => {
    setEditingPlan(plan);
    setFormMode("edit");
    setSelectedPlan(plan);
    setIsFormVisible(true);
  };

  // Handle plan duplicate
  const handlePlanDuplicate = async (plan: SubscriptionPlan) => {
    try {
      await duplicateSubscriptionPlan(plan.id);
      createNotice("success", __("Subscription plan duplicated successfully.", "tutorpress"), {
        type: "snackbar",
      });
    } catch (error) {
      console.error("Error duplicating subscription plan:", error);
    }
  };

  // Handle plan delete
  const handlePlanDelete = async (plan: SubscriptionPlan) => {
    if (window.confirm(__("Are you sure you want to delete this subscription plan?", "tutorpress"))) {
      try {
        await deleteSubscriptionPlan(plan.id);
        createNotice("success", __("Subscription plan deleted successfully.", "tutorpress"), {
          type: "snackbar",
        });
      } catch (error) {
        console.error("Error deleting subscription plan:", error);
      }
    }
  };

  // Handle form save
  const handleFormSave = (planData: Partial<SubscriptionPlan>) => {
    onFormSave(planData);
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

  // Show store errors as notices
  useEffect(() => {
    if (storeError) {
      createNotice("error", storeError, {
        isDismissible: true,
        type: "snackbar",
      });
    }
  }, [storeError, createNotice]);

  return (
    <div className="tutorpress-subscription-plan-section">
      {/* Error Display */}
      {storeError && (
        <Notice status="error" isDismissible={false}>
          {storeError}
        </Notice>
      )}

      {/* Loading State */}
      {isLoading && (
        <div className="tutorpress-subscription-plan-loading">
          <Spinner />
          <span>{__("Loading subscription plans...", "tutorpress")}</span>
        </div>
      )}

      {/* Plan List */}
      {!isLoading && !isFormVisible && (
        <div className="tutorpress-subscription-plan-list">
          {plans.length === 0 ? (
            <div className="tutorpress-subscription-plan-empty">
              <p>{__("No subscription plans found.", "tutorpress")}</p>
              <Button variant="primary" onClick={handleAddPlan}>
                {__("Add First Plan", "tutorpress")}
              </Button>
            </div>
          ) : (
            <DndContext
              sensors={sensors}
              onDragStart={dragHandlers.handleDragStart}
              onDragOver={dragHandlers.handleDragOver}
              onDragEnd={dragHandlers.handleDragEnd}
              onDragCancel={dragHandlers.handleDragCancel}
            >
              <SortableContext items={itemIds} strategy={verticalListSortingStrategy}>
                <div className="tutorpress-subscription-plan-items">
                  {plans.map((plan: SubscriptionPlan) => (
                    <SortableSubscriptionPlan
                      key={plan.id}
                      plan={plan}
                      onEdit={() => handlePlanEdit(plan)}
                      onDuplicate={() => handlePlanDuplicate(plan)}
                      onDelete={() => handlePlanDelete(plan)}
                      className={getItemClasses(plan, dragState.isDragging)}
                    />
                  ))}
                </div>
              </SortableContext>

              <DragOverlay>
                {dragState.activeId ? (
                  <SubscriptionPlanRow
                    plan={plans.find((plan: SubscriptionPlan) => plan.id === dragState.activeId)!}
                    className="tutorpress-subscription-plan--dragging"
                  />
                ) : null}
              </DragOverlay>
            </DndContext>
          )}

          {/* Add New Plan Button */}
          {plans.length > 0 && (
            <div className="tutorpress-subscription-plan-actions">
              <Button variant="secondary" onClick={handleAddPlan}>
                <Icon icon={plus} />
                {__("Add New Plan", "tutorpress")}
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Form Display */}
      {isFormVisible && (
        <SubscriptionPlanForm
          initialData={editingPlan || undefined}
          onSave={handleFormSave}
          onCancel={handleFormCancel}
          mode={formMode}
        />
      )}
    </div>
  );
};

export default SubscriptionPlanSection;
