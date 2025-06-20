/**
 * Generic Sortable List Component
 *
 * @description Reusable wrapper component that adds drag-and-drop functionality to any list.
 *              Integrates with the useSortableList hook and provides consistent drag-and-drop
 *              experience across topics, question options, and question lists.
 *
 * @features
 * - Generic item rendering with custom render functions
 * - Consistent drag-and-drop behavior across all contexts
 * - Superior visual feedback with blue overlay and drop indicators
 * - Flexible persistence modes (API vs local state)
 * - Comprehensive error handling and accessibility
 * - Context-aware styling and behavior
 * - Keyboard navigation support
 *
 * @usage
 * // Topics Example
 * <SortableList
 *   items={topics}
 *   onReorder={handleTopicReorder}
 *   persistenceMode="api"
 *   context="topics"
 *   renderItem={(topic, { dragHandleProps, isDragging }) => (
 *     <TopicItem topic={topic} dragHandleProps={dragHandleProps} isDragging={isDragging} />
 *   )}
 * />
 *
 * // Question Options Example
 * <SortableList
 *   items={options}
 *   onReorder={handleOptionReorder}
 *   persistenceMode="local"
 *   context="options"
 *   renderItem={(option, { dragHandleProps, isDragging }) => (
 *     <OptionCard option={option} dragHandleProps={dragHandleProps} isDragging={isDragging} />
 *   )}
 * />
 *
 * @package TutorPress
 * @subpackage Components/Common
 * @since 1.0.0
 */

import React from "react";
import { DndContext, DragOverlay, closestCenter } from "@dnd-kit/core";
import { SortableContext, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { useSortable } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { useSortableList } from "../../hooks/common/useSortableList";
import type {
  SortableItem,
  OperationResult,
  PersistenceMode,
  SortableContext as SortableContextType,
  UseSortableListOptions,
} from "../../hooks/common/useSortableList";

// ============================================================================
// Types and Interfaces
// ============================================================================

/**
 * Drag handle props interface for render functions
 */
export interface DragHandleProps {
  /** Attributes for the drag handle element */
  attributes: Record<string, any>;
  /** Listeners for drag events */
  listeners: Record<string, any> | undefined;
  /** Ref for the drag handle element */
  ref: (element: HTMLElement | null) => void;
  /** Whether the item is currently being dragged */
  isDragging: boolean;
}

/**
 * Render context passed to item render functions
 */
export interface SortableItemRenderContext {
  /** Props for the drag handle */
  dragHandleProps: DragHandleProps;
  /** Whether this item is currently being dragged */
  isDragging: boolean;
  /** Whether any item is currently being dragged */
  isAnyDragging: boolean;
  /** CSS transform for the item */
  transform: string | undefined;
  /** CSS transition for the item */
  transition: string | undefined;
  /** CSS classes for the item */
  itemClasses: string;
  /** CSS classes for the wrapper */
  wrapperClasses: string;
}

/**
 * Item render function type
 */
export type SortableItemRenderFunction<T extends SortableItem> = (
  item: T,
  context: SortableItemRenderContext
) => React.ReactNode;

/**
 * Drag overlay render function type
 */
export type DragOverlayRenderFunction<T extends SortableItem> = (item: T | null) => React.ReactNode;

/**
 * SortableList component props
 */
export interface SortableListProps<T extends SortableItem> {
  /** Array of items to make sortable */
  items: T[];
  /** Function to handle reordering */
  onReorder: (newOrder: T[]) => Promise<OperationResult<void>> | OperationResult<void>;
  /** Persistence mode - determines error handling behavior */
  persistenceMode: PersistenceMode;
  /** Context for styling and behavior customization */
  context: SortableContextType;
  /** Function to render each item */
  renderItem: SortableItemRenderFunction<T>;
  /** Optional function to render drag overlay */
  renderDragOverlay?: DragOverlayRenderFunction<T>;
  /** Optional callback for drag start side effects */
  onDragStart?: (activeId: number) => void;
  /** Optional callback for drag end side effects */
  onDragEnd?: () => void;
  /** Custom activation constraint distance */
  activationDistance?: number;
  /** Whether to disable drag and drop */
  disabled?: boolean;
  /** Additional CSS classes for the container */
  className?: string;
  /** Additional CSS classes for the list */
  listClassName?: string;
  /** ARIA label for the sortable list */
  ariaLabel?: string;
  /** Whether to show loading state */
  isLoading?: boolean;
  /** Loading message */
  loadingMessage?: string;
  /** Whether to show error state */
  hasError?: boolean;
  /** Error message */
  errorMessage?: string;
  /** Error retry callback */
  onRetry?: () => void;
}

// ============================================================================
// Sortable Item Component
// ============================================================================

/**
 * Individual sortable item wrapper component
 */
interface SortableItemWrapperProps<T extends SortableItem> {
  item: T;
  renderItem: SortableItemRenderFunction<T>;
  context: SortableContextType;
  isAnyDragging: boolean;
  getItemClasses: (item: SortableItem, isDragging?: boolean) => string;
  getWrapperClasses: (item: SortableItem, showIndicator?: boolean) => string;
}

function SortableItemWrapper<T extends SortableItem>({
  item,
  renderItem,
  context,
  isAnyDragging,
  getItemClasses,
  getWrapperClasses,
}: SortableItemWrapperProps<T>) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: item.id,
  });

  // Generate CSS classes
  const itemClasses = getItemClasses(item, isDragging);
  const wrapperClasses = getWrapperClasses(item);

  // Create drag handle props
  const dragHandleProps: DragHandleProps = {
    attributes,
    listeners,
    ref: setActivatorNodeRef,
    isDragging,
  };

  // Create render context
  const renderContext: SortableItemRenderContext = {
    dragHandleProps,
    isDragging,
    isAnyDragging,
    transform: transform ? CSS.Transform.toString(transform) : undefined,
    transition,
    itemClasses,
    wrapperClasses,
  };

  return (
    <div
      ref={setNodeRef}
      className={wrapperClasses}
      style={{
        transform: CSS.Transform.toString(transform),
        transition,
      }}
    >
      {renderItem(item, renderContext)}
    </div>
  );
}

// ============================================================================
// Main SortableList Component
// ============================================================================

/**
 * Generic sortable list component
 */
export function SortableList<T extends SortableItem>({
  items,
  onReorder,
  persistenceMode,
  context,
  renderItem,
  renderDragOverlay,
  onDragStart,
  onDragEnd,
  activationDistance = 8,
  disabled = false,
  className = "",
  listClassName = "",
  ariaLabel,
  isLoading = false,
  loadingMessage = "Loading...",
  hasError = false,
  errorMessage = "An error occurred",
  onRetry,
}: SortableListProps<T>) {
  // =============================
  // Hook Integration
  // =============================

  const { dragHandlers, dragState, sensors, itemIds, getItemClasses, getWrapperClasses } = useSortableList<T>({
    items,
    onReorder,
    persistenceMode,
    context,
    onDragStart,
    onDragEnd,
    activationDistance,
    disabled: disabled || isLoading,
  });

  // =============================
  // Helper Functions
  // =============================

  /**
   * Get the currently dragged item for overlay
   */
  const getDraggedItem = (): T | null => {
    if (!dragState.activeId) return null;
    return items.find((item) => item.id === dragState.activeId) || null;
  };

  /**
   * Default drag overlay renderer
   */
  const defaultDragOverlayRenderer = (item: T | null): React.ReactNode => {
    if (!item) return null;

    // Create a simplified render context for the overlay
    const overlayContext: SortableItemRenderContext = {
      dragHandleProps: {
        attributes: {},
        listeners: undefined,
        ref: () => {}, // No-op ref for overlay
        isDragging: true,
      },
      isDragging: true,
      isAnyDragging: true,
      transform: undefined,
      transition: undefined,
      itemClasses: getItemClasses(item, true),
      wrapperClasses: getWrapperClasses(item),
    };

    return <div className={overlayContext.wrapperClasses}>{renderItem(item, overlayContext)}</div>;
  };

  // =============================
  // Error and Loading States
  // =============================

  if (hasError) {
    return (
      <div className={`sortable-list-error tpress-error-state-section ${className}`}>
        <p>{errorMessage}</p>
        {onRetry && (
          <button onClick={onRetry} className="sortable-list-retry-btn tpress-error-retry-btn">
            Retry
          </button>
        )}
      </div>
    );
  }

  if (isLoading) {
    return (
      <div className={`sortable-list-loading tpress-loading-state-inline ${className}`}>
        <p>{loadingMessage}</p>
      </div>
    );
  }

  // =============================
  // Main Render
  // =============================

  return (
    <div className={`sortable-list-container ${className}`}>
      <DndContext
        sensors={sensors}
        collisionDetection={closestCenter}
        onDragStart={dragHandlers.handleDragStart}
        onDragOver={dragHandlers.handleDragOver}
        onDragEnd={dragHandlers.handleDragEnd}
        onDragCancel={dragHandlers.handleDragCancel}
      >
        <SortableContext items={itemIds} strategy={verticalListSortingStrategy}>
          <div
            className={`sortable-list ${listClassName}`}
            role="list"
            aria-label={ariaLabel || `Sortable list of ${items.length} items`}
          >
            {items.map((item) => (
              <SortableItemWrapper
                key={item.id}
                item={item}
                renderItem={renderItem}
                context={context}
                isAnyDragging={dragState.isDragging}
                getItemClasses={getItemClasses}
                getWrapperClasses={getWrapperClasses}
              />
            ))}
          </div>
        </SortableContext>

        <DragOverlay>
          {renderDragOverlay ? renderDragOverlay(getDraggedItem()) : defaultDragOverlayRenderer(getDraggedItem())}
        </DragOverlay>
      </DndContext>

      {/* Operation Status Display */}
      {dragState.operationState.status === "reordering" && (
        <div className="sortable-list-status">
          <p>Reordering items...</p>
        </div>
      )}

      {dragState.operationState.status === "error" && dragState.operationState.error && (
        <div className="sortable-list-error tpress-error-state-section">
          <p>Error: {dragState.operationState.error.message}</p>
          {onRetry && (
            <button onClick={onRetry} className="sortable-list-retry-btn tpress-error-retry-btn">
              Retry
            </button>
          )}
        </div>
      )}
    </div>
  );
}

// ============================================================================
// Utility Components and Hooks
// ============================================================================

/**
 * Simple drag handle component for common use cases
 */
export interface SimpleDragHandleProps {
  dragHandleProps: DragHandleProps;
  className?: string;
  ariaLabel?: string;
}

export function SimpleDragHandle({
  dragHandleProps,
  className = "tutorpress-drag-handle",
  ariaLabel = "Drag to reorder",
}: SimpleDragHandleProps) {
  return (
    <button
      {...dragHandleProps.attributes}
      {...(dragHandleProps.listeners || {})}
      ref={dragHandleProps.ref as React.LegacyRef<HTMLButtonElement>}
      className={className}
      aria-label={ariaLabel}
      type="button"
    >
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path
          d="M7 2C7.55228 2 8 2.44772 8 3V17C8 17.5523 7.55228 18 7 18C6.44772 18 6 17.5523 6 17V3C6 2.44772 6.44772 2 7 2Z"
          fill="currentColor"
        />
        <path
          d="M13 2C13.5523 2 14 2.44772 14 3V17C14 17.5523 13.5523 18 13 18C12.4477 18 12 17.5523 12 17V3C12 2.44772 12.4477 2 13 2Z"
          fill="currentColor"
        />
      </svg>
    </button>
  );
}

/**
 * Hook for creating simple reorder handlers
 */
export function useSimpleReorder<T extends SortableItem>(setItems: React.Dispatch<React.SetStateAction<T[]>>) {
  return React.useCallback(
    (newOrder: T[]): OperationResult<void> => {
      try {
        setItems(newOrder);
        return { success: true };
      } catch (error) {
        return {
          success: false,
          error: {
            code: "LOCAL_UPDATE_FAILED",
            message: error instanceof Error ? error.message : "Failed to update items",
          },
        };
      }
    },
    [setItems]
  );
}

// ============================================================================
// Default Export
// ============================================================================

export default SortableList;
