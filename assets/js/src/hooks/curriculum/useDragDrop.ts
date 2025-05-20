import { useState, useCallback } from "react";
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
import { arrayMove } from "@dnd-kit/sortable";

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
}

export function useDragDrop({
  courseId,
  topics,
  setTopics,
  setEditState,
  setReorderState,
}: UseDragDropOptions): UseDragDropReturn {
  // Drag and drop state
  const [activeId, setActiveId] = useState<number | null>(null);
  const [overId, setOverId] = useState<number | null>(null);

  /** Handle drag start: cancel edit mode and close all topics */
  const handleDragStart = useCallback((event: DragStartEvent): void => {
    setActiveId(Number(event.active.id));
    // Cancel edit mode if active
    setEditState({ isEditing: false, topicId: null });
    // Close all topics when dragging starts
    setTopics((currentTopics) =>
      currentTopics.map((topic) => ({
        ...topic,
        isCollapsed: true,
      }))
    );
  }, []);

  /** Handle drag over: track item being dragged over */
  const handleDragOver = useCallback((event: DragOverEvent): void => {
    setOverId(event.over?.id ? Number(event.over.id) : null);
  }, []);

  /** Handle topic reordering */
  const handleReorderTopics = async (newOrder: Topic[]): Promise<OperationResult<void>> => {
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
  };

  /** Handle drag end: update topic order */
  const handleDragEnd = useCallback(
    async (event: DragEndEvent): Promise<void> => {
      const { active, over } = event;

      if (over && active.id !== over.id) {
        const oldIndex = topics.findIndex((t) => t.id === Number(active.id));
        const newIndex = topics.findIndex((t) => t.id === Number(over.id));

        const newOrder = arrayMove(topics, oldIndex, newIndex);
        setTopics(newOrder);

        const result = await handleReorderTopics(newOrder);
        if (!result.success) {
          // Revert to original order on failure
          setTopics(topics);
        }
      }

      setActiveId(null);
      setOverId(null);
    },
    [topics, handleReorderTopics]
  );

  /** Handle drag cancel: reset state */
  const handleDragCancel = useCallback((): void => {
    setActiveId(null);
    setOverId(null);
  }, []);

  return {
    activeId,
    overId,
    handleDragStart,
    handleDragOver,
    handleDragEnd,
    handleDragCancel,
    handleReorderTopics,
  };
}
