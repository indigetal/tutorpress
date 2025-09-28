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
import { Card, CardBody, Button, Icon, Flex, FlexBlock, Spinner, Notice, CardHeader } from "@wordpress/components";
import { dragHandle, plus, chevronDown, chevronRight } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";
import { DndContext } from "@dnd-kit/core";
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
 * Sortable Card wrapper for subscription plans
 */
const SortableSubscriptionPlanCard: React.FC<{
  plan: SubscriptionPlan;
  isEditing: boolean;
  onEditToggle: () => void;
  onDuplicate: () => void;
  onDelete: () => void;
  onSave: (data: Partial<SubscriptionPlan>) => void;
  onCancel: () => void;
  className?: string;
}> = ({ plan, isEditing, onEditToggle, onDuplicate, onDelete, onSave, onCancel, className }) => {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: plan.id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  const cardClassName = `tutorpress-subscription-plan ${isEditing ? "is-editing" : ""} ${className || ""}`;

  return (
    <div ref={setNodeRef} style={style}>
      <Card className={cardClassName}>
        <CardHeader>
          <Flex align="center" gap={2}>
            <div className="tutorpress-subscription-plan-icon tpress-flex-shrink-0">
              <Button
                icon={dragHandle}
                label="Drag to reorder"
                isSmall
                {...attributes}
                {...listeners}
                ref={setActivatorNodeRef}
              />
            </div>
            <FlexBlock style={{ textAlign: "left" }}>
              {!isEditing && (
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
              )}
            </FlexBlock>
            <div className="tpress-item-actions-right">
              <ActionButtons onEdit={onEditToggle} onDuplicate={onDuplicate} onDelete={onDelete} />
            </div>
          </Flex>
        </CardHeader>
        {isEditing ? <SubscriptionPlanForm initialData={plan} onSave={onSave} onCancel={onCancel} mode="edit" /> : null}
      </Card>
    </div>
  );
};

/**
 * Props for subscription plan section
 */
interface SubscriptionPlanSectionProps {
  courseId: number;
  postType?: string;
  onFormSave: (planData: Partial<SubscriptionPlan>) => void;
  onFormCancel: () => void;
  editingPlanId?: number | null;
  onPlanEditToggle?: (planId: number) => void;
  isNewPlanFormVisible?: boolean;
  onAddNewPlan?: () => void;
}

/**
 * Main subscription plan section component
 */
export const SubscriptionPlanSection: React.FC<SubscriptionPlanSectionProps> = ({
  courseId,
  postType = "courses",
  onFormSave,
  onFormCancel,
  editingPlanId = null,
  onPlanEditToggle,
  isNewPlanFormVisible = false,
  onAddNewPlan,
}): JSX.Element => {
  // Get store state and actions
  const {
    plans: storePlans,
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

  // Local state for optimistic updates (following TopicSection pattern)
  const [localPlansOrder, setLocalPlansOrder] = useState<SubscriptionPlan[]>([]);

  // Update local state when store plans change
  useEffect(() => {
    if (storePlans.length > 0) {
      setLocalPlansOrder(storePlans);
    }
  }, [storePlans]);

  // Use local state for display (following TopicSection pattern)
  const plans = localPlansOrder.length > 0 ? localPlansOrder : storePlans;

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
      getSubscriptionPlans();
    }
  }, [courseId, getSubscriptionPlans]);

  // Handle plan reordering (following TopicSection pattern)
  const handlePlanReorder = async (newOrder: SubscriptionPlan[]) => {
    // Immediately update local state for smooth UI (following TopicSection pattern)
    setLocalPlansOrder(newOrder);

    try {
      const planOrder = newOrder.map((plan) => plan.id);
      await sortSubscriptionPlans(planOrder);
      createNotice("success", __("Subscription plans reordered successfully.", "tutorpress"), {
        type: "snackbar",
      });
      return { success: true };
    } catch (error) {
      // Revert local state on API failure (following TopicSection pattern)
      setLocalPlansOrder(storePlans);
      console.error("Error reordering subscription plans:", error);
      return { success: false, error: { code: "reorder_failed", message: String(error) } };
    }
  };

  // Drag and drop configuration
  const { dragHandlers, dragState, sensors, itemIds, getItemClasses, getWrapperClasses } = useSortableList({
    items: plans,
    onReorder: handlePlanReorder,
    persistenceMode: "api",
    context: "subscription_plans",
    onDragStart: () => {
      // Close all open forms when dragging starts
      if (onPlanEditToggle) {
        // Close any editing plan
        if (editingPlanId !== null) {
          onPlanEditToggle(editingPlanId);
        }
        // Close new plan form if open
        if (isNewPlanFormVisible && onAddNewPlan) {
          // We need to trigger the close by calling the cancel handler
          onFormCancel();
        }
      }
    },
  });

  // Handle plan duplicate (optimistic: add temp copy, revert on failure)
  const handlePlanDuplicate = async (plan: SubscriptionPlan) => {
    const prev = storePlans;
    const tempId = `temp-${Date.now()}`;
    const tempPlan: SubscriptionPlan = {
      ...plan,
      id: tempId as any,
      plan_name: `${plan.plan_name} (Copy)`,
    } as SubscriptionPlan;
    // Optimistically add
    setLocalPlansOrder([...prev, tempPlan]);
    try {
      const resp = await duplicateSubscriptionPlan(plan.id);
      // On success the store will be updated by resolver; sync will occur via effect
      createNotice("success", __("Subscription plan duplicated successfully.", "tutorpress"), {
        type: "snackbar",
      });
    } catch (error) {
      // Revert
      setLocalPlansOrder(prev);
      createNotice("error", String(error || __("Failed to duplicate subscription plan.", "tutorpress")), {
        type: "snackbar",
      });
      console.error("Error duplicating subscription plan:", error);
    }
  };

  // Handle plan delete (optimistic: remove locally, revert on failure)
  const handlePlanDelete = async (plan: SubscriptionPlan) => {
    if (!window.confirm(__("Are you sure you want to delete this subscription plan?", "tutorpress"))) {
      return;
    }

    const prev = storePlans;
    // Optimistically remove
    setLocalPlansOrder(prev.filter((p: SubscriptionPlan) => p.id !== plan.id));

    try {
      await deleteSubscriptionPlan(plan.id);
      createNotice("success", __("Subscription plan deleted successfully.", "tutorpress"), {
        type: "snackbar",
      });
    } catch (error) {
      // Revert
      setLocalPlansOrder(prev);
      createNotice("error", String(error || __("Failed to delete subscription plan.", "tutorpress")), {
        type: "snackbar",
      });
      console.error("Error deleting subscription plan:", error);
    }
  };

  // Handle plan edit toggle
  const handlePlanEditToggle = (planId: number) => {
    if (onPlanEditToggle) {
      onPlanEditToggle(planId);
    }
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

      {/* Plan List - Always Visible */}
      {!isLoading && (
        <div className="tutorpress-subscription-plan-list">
          {plans.length === 0 ? (
            <div className="tutorpress-subscription-plan-empty">
              <p>{__("No subscription plans found.", "tutorpress")}</p>
              <Button variant="primary" onClick={onAddNewPlan}>
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
                  {plans.map((plan: SubscriptionPlan) => {
                    const isEditing = editingPlanId === plan.id;

                    const handleSaveWrapper = async (data: Partial<SubscriptionPlan>) => {
                      const prev = storePlans;
                      // If creating (no id), add optimistic temp plan
                      if (!data.id) {
                        const tempId = `temp-${Date.now()}`;
                        const tempPlan = { ...(data as SubscriptionPlan), id: tempId as any } as SubscriptionPlan;
                        setLocalPlansOrder([...prev, tempPlan]);
                      } else {
                        // Optimistically update existing
                        setLocalPlansOrder(
                          prev.map((p: SubscriptionPlan) => (p.id === data.id ? { ...p, ...data } : p))
                        );
                      }

                      try {
                        await onFormSave(data);
                        // Success: store will sync and effect will update localPlansOrder
                        createNotice("success", __("Subscription plan saved.", "tutorpress"), { type: "snackbar" });
                      } catch (err) {
                        // Revert
                        setLocalPlansOrder(prev);
                        createNotice("error", String(err || __("Failed to save subscription plan.", "tutorpress")), {
                          type: "snackbar",
                        });
                        console.error("Error saving subscription plan:", err);
                      }
                    };

                    return (
                      <SortableSubscriptionPlanCard
                        key={plan.id}
                        plan={plan}
                        isEditing={isEditing}
                        onEditToggle={() => handlePlanEditToggle(plan.id)}
                        onDuplicate={() => handlePlanDuplicate(plan)}
                        onDelete={() => handlePlanDelete(plan)}
                        onSave={handleSaveWrapper}
                        onCancel={onFormCancel}
                        className={getItemClasses(plan, dragState.isDragging)}
                      />
                    );
                  })}
                </div>
              </SortableContext>
            </DndContext>
          )}

          {/* New Plan Form - Always at bottom when visible */}
          {isNewPlanFormVisible && (
            <Card className="tutorpress-subscription-plan-new-form">
              <CardHeader>
                <Flex align="center" gap={2}>
                  <FlexBlock style={{ textAlign: "left" }}>
                    <div className="tutorpress-subscription-plan-title">{__("Add New Plan", "tutorpress")}</div>
                  </FlexBlock>
                </Flex>
              </CardHeader>
              <SubscriptionPlanForm initialData={undefined} onSave={onFormSave} onCancel={onFormCancel} mode="add" />
            </Card>
          )}

          {/* Add New Plan Button - Always visible unless new form is open */}
          {plans.length > 0 && !isNewPlanFormVisible && (
            <div className="tutorpress-subscription-plan-actions">
              <Button variant="secondary" onClick={onAddNewPlan} disabled={isNewPlanFormVisible}>
                <Icon icon={plus} />
                {__("Add New Plan", "tutorpress")}
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  );
};

export default SubscriptionPlanSection;
