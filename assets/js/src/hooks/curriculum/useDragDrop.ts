import { useCallback } from "react";
import type { DragEndEvent, DragStartEvent, DragOverEvent } from "@dnd-kit/core";
import {
  Topic,
  CurriculumError,
  CurriculumErrorCode,
  OperationResult,
  ReorderOperationState,
} from "../../types/curriculum";
import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import { useSortableList, type SortableItem } from "../common/useSortableList";

// Type guard for WP REST API response
const isWpRestResponse = (response: unknown): response is { success: boolean; message: string; data: unknown } => {
  return typeof response === "object" && response !== null && "success" in response && "data" in response;
};

export interface UseDragDropOptions {
  courseId: number;
  topics: Topic[];
  setTopics: React.Dispatch<React.SetStateAction<Topic[]>>;
  setEditState: (state: { isEditing: boolean; topicId: null }) => void;
  setReorderState: (state: ReorderOperationState) => void;
}

export interface UseDragDropReturn {
  activeId: number | null;
  overId: number | null;
  handleDragStart: (event: DragStartEvent) => void;
  handleDragOver: (event: DragOverEvent) => void;
  handleDragEnd: (event: DragEndEvent) => Promise<void>;
  handleDragCancel: () => void;
  handleReorderTopics: (newOrder: Topic[]) => Promise<OperationResult<void>>;
  // Additional properties for SortableList integration
  sensors: ReturnType<typeof useSortableList>["sensors"];
  itemIds: number[];
  getItemClasses: (item: SortableItem, isDragging?: boolean) => string;
  getWrapperClasses: (item: SortableItem, showIndicator?: boolean) => string;
  dragState: ReturnType<typeof useSortableList>["dragState"];
}

export function useDragDrop({
  courseId,
  topics,
  setTopics,
  setEditState,
  setReorderState,
}: UseDragDropOptions): UseDragDropReturn {
  /** Handle topic reordering with API persistence */
  const handleReorderTopics = useCallback(
    async (newOrder: Topic[]): Promise<OperationResult<void>> => {
      setReorderState({ status: "reordering" });

      try {
        const res = await apiFetch<unknown>({
          path: `/tutorpress/v1/topics/reorder`,
          method: "POST",
          data: {
            course_id: courseId,
            topic_orders: newOrder.map((t, idx) => ({ id: t.id, order: idx })),
          },
        });

        if (!isWpRestResponse(res)) {
          throw {
            code: CurriculumErrorCode.INVALID_RESPONSE,
            message: __("Invalid response format from server", "tutorpress"),
          };
        }

        if (!res.success) {
          throw {
            code: CurriculumErrorCode.SERVER_ERROR,
            message: res.message || __("Server returned an error", "tutorpress"),
          };
        }

        setReorderState({ status: "success" });
        return { success: true };
      } catch (err) {
        console.error("Error reordering topics:", err);

        const isNetworkError =
          err instanceof Error &&
          (err.message.includes("offline") || err.message.includes("network") || err.message.includes("fetch"));

        const error: CurriculumError = {
          code: isNetworkError ? CurriculumErrorCode.NETWORK_ERROR : CurriculumErrorCode.REORDER_FAILED,
          message: err instanceof Error ? err.message : __("Failed to reorder topics", "tutorpress"),
          context: {
            action: "reorder_topics",
          },
        };

        setReorderState({ status: "error", error });
        return { success: false, error };
      }
    },
    [courseId, setReorderState]
  );

  /** Handle drag start: cancel edit mode and close all topics */
  const handleTopicDragStart = useCallback(
    (activeId: number): void => {
      // Cancel edit mode if active
      setEditState({ isEditing: false, topicId: null });
      // Close all topics when dragging starts
      setTopics((currentTopics) =>
        currentTopics.map((topic) => ({
          ...topic,
          isCollapsed: true,
        }))
      );
    },
    [setEditState, setTopics]
  );

  /** Handle reorder with optimistic updates and rollback on failure */
  const handleTopicReorder = useCallback(
    async (newOrder: Topic[]): Promise<OperationResult<void>> => {
      // Optimistic update
      setTopics(newOrder);

      const result = await handleReorderTopics(newOrder);
      if (!result.success) {
        // Revert to original order on failure
        setTopics(topics);
      }
      return result;
    },
    [topics, setTopics, handleReorderTopics]
  );

  // Use the generic sortable list hook
  const { dragHandlers, dragState, sensors, itemIds, getItemClasses, getWrapperClasses } = useSortableList<Topic>({
    items: topics,
    onReorder: handleTopicReorder,
    persistenceMode: "api",
    context: "topics",
    onDragStart: handleTopicDragStart,
    activationDistance: 0, // Match original topics behavior
  });

  return {
    activeId: dragState.activeId,
    overId: dragState.overId,
    handleDragStart: dragHandlers.handleDragStart,
    handleDragOver: dragHandlers.handleDragOver,
    handleDragEnd: dragHandlers.handleDragEnd,
    handleDragCancel: dragHandlers.handleDragCancel,
    handleReorderTopics,
    // Additional properties for SortableList integration
    sensors,
    itemIds,
    getItemClasses,
    getWrapperClasses,
    dragState,
  };
}
