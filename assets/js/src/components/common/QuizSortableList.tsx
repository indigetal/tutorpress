/**
 * Quiz Sortable List Component
 *
 * @description Quiz-specific drag-and-drop wrapper optimized for question options and quiz contexts.
 *              Reuses the shared useSortableList hook but provides quiz-specific optimizations
 *              for DOM structure, transform handling, and visual feedback patterns.
 *
 * @features
 * - Optimized for quiz option DOM structure (solves cursor offset issues)
 * - Local state management pattern (no immediate API persistence)
 * - Quiz-specific visual feedback and styling
 * - Reuses shared utilities from useSortableList hook
 * - Supports question options, question lists, and future quiz drag contexts
 * - Smooth cursor-following behavior for quiz options
 * - Context-aware styling for different quiz components
 *
 * @usage
 * // Question Options Example
 * <QuizSortableList
 *   items={options}
 *   onReorder={handleOptionReorder}
 *   context="options"
 *   renderItem={(option, { dragHandleProps, isDragging }) => (
 *     <SortableOption option={option} dragHandleProps={dragHandleProps} isDragging={isDragging} />
 *   )}
 * />
 *
 * // Question List Example
 * <QuizSortableList
 *   items={questions}
 *   onReorder={handleQuestionReorder}
 *   context="questions"
 *   renderItem={(question, { dragHandleProps, isDragging }) => (
 *     <QuestionItem question={question} dragHandleProps={dragHandleProps} isDragging={isDragging} />
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
import { useSortableList, createLocalReorderHandler } from "../../hooks/common/useSortableList";
import type {
  SortableItem,
  OperationResult,
  SortableContext as SortableContextType,
} from "../../hooks/common/useSortableList";
import { arrayMove } from "@dnd-kit/sortable";

// ============================================================================
// Types and Interfaces
// ============================================================================

/**
 * Quiz-specific drag handle props interface
 */
export interface QuizDragHandleProps {
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
 * Quiz-specific render context passed to item render functions
 */
export interface QuizSortableItemRenderContext {
  /** Props for the drag handle */
  dragHandleProps: QuizDragHandleProps;
  /** Whether this item is currently being dragged */
  isDragging: boolean;
  /** Whether any item is currently being dragged */
  isAnyDragging: boolean;
  /** CSS transform for the item (optimized for quiz contexts) */
  transform: string | undefined;
  /** CSS transition for the item */
  transition: string | undefined;
  /** CSS classes for the item */
  itemClasses: string;
  /** CSS classes for the wrapper */
  wrapperClasses: string;
}

/**
 * Quiz item render function type
 */
export type QuizSortableItemRenderFunction<T extends SortableItem> = (
  item: T,
  context: QuizSortableItemRenderContext
) => React.ReactNode;

/**
 * Quiz drag overlay render function type
 */
export type QuizDragOverlayRenderFunction<T extends SortableItem> = (item: T | null) => React.ReactNode;

/**
 * Quiz-specific context types
 */
export type QuizSortableContext = "options" | "questions";

/**
 * QuizSortableList component props
 */
export interface QuizSortableListProps<T extends SortableItem> {
  /** Array of items to make sortable */
  items: T[];
  /** Function to handle reordering (local state management) */
  onReorder: (newOrder: T[]) => void;
  /** Quiz context for styling and behavior customization */
  context: QuizSortableContext;
  /** Function to render each item */
  renderItem: QuizSortableItemRenderFunction<T>;
  /** Optional function to render drag overlay */
  renderDragOverlay?: QuizDragOverlayRenderFunction<T>;
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
}

// ============================================================================
// Quiz Sortable Item Component
// ============================================================================

/**
 * Individual quiz sortable item wrapper component
 */
interface QuizSortableItemWrapperProps<T extends SortableItem> {
  item: T;
  renderItem: QuizSortableItemRenderFunction<T>;
  context: QuizSortableContext;
  isAnyDragging: boolean;
  getItemClasses: (item: SortableItem, isDragging?: boolean) => string;
  getWrapperClasses: (item: SortableItem, showIndicator?: boolean) => string;
}

function QuizSortableItemWrapper<T extends SortableItem>({
  item,
  renderItem,
  context,
  isAnyDragging,
  getItemClasses,
  getWrapperClasses,
}: QuizSortableItemWrapperProps<T>) {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: item.id,
  });

  // Generate CSS classes
  const itemClasses = getItemClasses(item, isDragging);
  const wrapperClasses = getWrapperClasses(item);

  // Quiz-optimized transform handling - addresses cursor offset issues
  const optimizedTransform = transform
    ? {
        ...transform,
        // Reduce scale factor for smoother quiz option dragging
        scaleX: 1,
        scaleY: 1,
      }
    : null;

  // Create quiz drag handle props
  const dragHandleProps: QuizDragHandleProps = {
    attributes,
    listeners,
    ref: setActivatorNodeRef,
    isDragging,
  };

  // Create quiz render context
  const renderContext: QuizSortableItemRenderContext = {
    dragHandleProps,
    isDragging,
    isAnyDragging,
    transform: optimizedTransform ? CSS.Transform.toString(optimizedTransform) : undefined,
    transition,
    itemClasses,
    wrapperClasses,
  };

  return (
    <div ref={setNodeRef} className={wrapperClasses}>
      {renderItem(item, renderContext)}
    </div>
  );
}

// ============================================================================
// Main QuizSortableList Component
// ============================================================================

/**
 * QuizSortableList Component
 *
 * Quiz-specific drag-and-drop wrapper that optimizes for quiz contexts while
 * reusing shared utilities from useSortableList hook.
 */
export function QuizSortableList<T extends SortableItem>({
  items,
  onReorder,
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
}: QuizSortableListProps<T>) {
  // Create local reorder handler that wraps the provided onReorder callback
  const handleReorder = React.useCallback(
    (newOrder: T[]): OperationResult<void> => {
      try {
        onReorder(newOrder);
        return { success: true };
      } catch (error) {
        return {
          success: false,
          error: {
            code: "REORDER_FAILED",
            message: "Failed to reorder items",
            context: {
              action: "reorder",
              details: error instanceof Error ? error.message : "Unknown error",
            },
          },
        };
      }
    },
    [onReorder]
  );

  // Use shared sortable list hook with local persistence mode
  const { dragHandlers, dragState, sensors, itemIds, getItemClasses, getWrapperClasses } = useSortableList({
    items,
    onReorder: handleReorder,
    persistenceMode: "local", // Quiz contexts use local state management
    context: context === "options" ? "options" : "questions", // Map to shared context types
    onDragStart,
    onDragEnd,
    activationDistance,
    disabled,
  });

  // Get currently dragged item for overlay
  const getDraggedItem = (): T | null => {
    if (!dragState.activeId) return null;
    return items.find((item) => item.id === dragState.activeId) || null;
  };

  // Default drag overlay renderer optimized for quiz contexts
  const defaultDragOverlayRenderer = (item: T | null): React.ReactNode => {
    if (!item) return null;

    // Create a simplified drag overlay context
    const overlayContext: QuizSortableItemRenderContext = {
      dragHandleProps: {
        attributes: {},
        listeners: undefined,
        ref: () => {},
        isDragging: true,
      },
      isDragging: true,
      isAnyDragging: true,
      transform: undefined,
      transition: undefined,
      itemClasses: getItemClasses(item, true),
      wrapperClasses: getWrapperClasses(item),
    };

    return <div className="quiz-sortable-drag-overlay">{renderItem(item, overlayContext)}</div>;
  };

  // Handle loading state
  if (isLoading) {
    return (
      <div className={`quiz-sortable-list-container ${className}`}>
        <div className="quiz-sortable-list-loading tpress-loading-state-inline">{loadingMessage}</div>
      </div>
    );
  }

  // Handle empty state
  if (items.length === 0) {
    return (
      <div className={`quiz-sortable-list-container ${className}`}>
        <div className={`quiz-sortable-list ${listClassName}`} />
      </div>
    );
  }

  return (
    <div className={`quiz-sortable-list-container ${className}`}>
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
            className={`quiz-sortable-list ${listClassName}`}
            role="list"
            aria-label={ariaLabel || `Sortable ${context} list`}
          >
            {items.map((item) => (
              <QuizSortableItemWrapper
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
    </div>
  );
}

// ============================================================================
// Quiz-Specific Utility Components
// ============================================================================

/**
 * Simple quiz drag handle component props
 */
export interface QuizDragHandleComponentProps {
  dragHandleProps: QuizDragHandleProps;
  className?: string;
  ariaLabel?: string;
}

export function QuizDragHandle({
  dragHandleProps,
  className = "tutorpress-drag-handle",
  ariaLabel = "Drag to reorder",
}: QuizDragHandleComponentProps) {
  return (
    <button
      type="button"
      className={className}
      aria-label={ariaLabel}
      {...dragHandleProps.attributes}
      {...dragHandleProps.listeners}
      ref={dragHandleProps.ref}
    >
      <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <path
          d="M7 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 10a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM7 18a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM13 2a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM13 6a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM13 10a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM13 14a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM13 18a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"
          fill="currentColor"
        />
      </svg>
    </button>
  );
}

/**
 * Quiz-specific reorder hook for local state management
 */
export function useQuizReorder<T extends SortableItem>(setItems: React.Dispatch<React.SetStateAction<T[]>>) {
  return React.useCallback(
    (newOrder: T[]) => {
      setItems(newOrder);
    },
    [setItems]
  );
}

// ============================================================================
// Quiz Drag Handler Utilities - for preserving original architecture
// ============================================================================

/**
 * Shared drag handler logic for quiz options with original architecture preservation
 */
export interface QuizDragHandlerOptions<T extends SortableItem> {
  items: T[];
  onReorder: (newItems: T[]) => void;
  onDragStart?: (activeId: number) => void;
  onDragEnd?: () => void;
}

/**
 * Create reusable drag handlers that work with original DndContext
 */
export function createQuizDragHandlers<T extends SortableItem>({
  items,
  onReorder,
  onDragStart,
  onDragEnd,
}: QuizDragHandlerOptions<T>) {
  const handleDragStart = React.useCallback(
    (event: any) => {
      const activeId = Number(event.active.id);
      onDragStart?.(activeId);
    },
    [onDragStart]
  );

  const handleDragEnd = React.useCallback(
    (event: any) => {
      const { active, over } = event;

      if (over && active.id !== over.id) {
        const oldIndex = items.findIndex((item) => item.id === Number(active.id));
        const newIndex = items.findIndex((item) => item.id === Number(over.id));

        if (oldIndex !== -1 && newIndex !== -1) {
          // Reorder the items using arrayMove
          const reorderedItems = arrayMove(items, oldIndex, newIndex);

          // Update order fields and data status if it's a quiz question option
          const updatedItems = reorderedItems.map((item, index) => ({
            ...item,
            // Update answer_order if it exists (for quiz options)
            ...("answer_order" in item && { answer_order: index + 1 }),
            // Update data status if it exists
            ...("_data_status" in item && {
              _data_status: (item as any)._data_status === "new" ? "new" : "update",
            }),
          }));

          onReorder(updatedItems as T[]);
        }
      }

      onDragEnd?.();
    },
    [items, onReorder, onDragEnd]
  );

  const handleDragCancel = React.useCallback(() => {
    onDragEnd?.();
  }, [onDragEnd]);

  return {
    handleDragStart,
    handleDragEnd,
    handleDragCancel,
  };
}

/**
 * Get CSS classes for quiz options that match the centralized styling
 */
export function getQuizOptionClasses(
  option: { is_correct?: string | "0" | "1"; _data_status?: string },
  isDragging: boolean = false,
  isEditing: boolean = false,
  showCorrectIndicator: boolean = true
): string {
  const classes = [
    "quiz-modal-option-card",
    showCorrectIndicator && option.is_correct === "1" && "is-correct",
    isEditing && "quiz-modal-option-card-editing",
    isDragging && "is-dragging",
  ]
    .filter(Boolean)
    .join(" ");

  return classes;
}

/**
 * Quiz option reorder utility specifically for question options
 */
export function createQuizOptionReorder(
  onQuestionUpdate: (questionIndex: number, field: any, value: any) => void,
  questionIndex: number
) {
  return React.useCallback(
    (newOrder: any[]) => {
      // Update answer_order for all options
      const updatedAnswers = newOrder.map((answer: any, index: number) => ({
        ...answer,
        answer_order: index + 1,
        _data_status: answer._data_status === "new" ? "new" : "update",
      }));

      onQuestionUpdate(questionIndex, "question_answers", updatedAnswers);
    },
    [onQuestionUpdate, questionIndex]
  );
}
