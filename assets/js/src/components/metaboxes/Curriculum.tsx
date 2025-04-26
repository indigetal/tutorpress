/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState, useMemo, useCallback, useEffect } from "react";
import { Button, Flex, FlexBlock, Spinner } from "@wordpress/components";
import { plus, update, close } from "@wordpress/icons";
import { CurriculumErrorCode } from "../../types/curriculum";
import type {
  Topic,
  CurriculumError,
  TopicFormData,
  TopicEditState,
  TopicCreationState,
  TopicOperationState,
  ReorderOperationState,
  CurriculumSnapshot,
  OperationResult,
  SortableTopicProps,
} from "../../types/curriculum";
import { isValidTopic } from "../../types/curriculum";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import type { DragEndEvent, DragStartEvent, DragOverEvent } from "@dnd-kit/core";
import { DndContext, useSensor, useSensors, PointerSensor } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy, arrayMove } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { getTopics, reorderTopics, duplicateTopic } from "../../api/topics";
import { store as noticesStore } from "@wordpress/notices";
import { useDispatch } from "@wordpress/data";
import { TopicSection } from "./curriculum/TopicSection";
import TopicForm from "./curriculum/TopicForm";
import { useTopics, useCourseId } from "../../hooks/curriculum";

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Get user-friendly error message based on error code
 */
const getErrorMessage = (error: CurriculumError): string => {
  switch (error.code) {
    case CurriculumErrorCode.CREATION_FAILED:
      return __("Unable to create topic. Please try again.", "tutorpress");
    case CurriculumErrorCode.VALIDATION_ERROR:
      return __("Please fill in all required fields.", "tutorpress");
    case CurriculumErrorCode.NETWORK_ERROR:
      return __(
        "Unable to save changes - you appear to be offline. Please check your connection and try again.",
        "tutorpress"
      );
    case CurriculumErrorCode.FETCH_FAILED:
      return __("Unable to load topics. Please refresh the page to try again.", "tutorpress");
    case CurriculumErrorCode.REORDER_FAILED:
      return __("Unable to save topic order. Your changes will be restored.", "tutorpress");
    case CurriculumErrorCode.INVALID_RESPONSE:
      return __("Received invalid response from server. Please try again.", "tutorpress");
    case CurriculumErrorCode.SERVER_ERROR:
      return __("The server encountered an error. Please try again.", "tutorpress");
    default:
      return __("An unexpected error occurred. Please try again.", "tutorpress");
  }
};

// Type guard for WP REST API response
const isWpRestResponse = (response: unknown): response is { success: boolean; message: string; data: unknown } => {
  return typeof response === "object" && response !== null && "success" in response && "data" in response;
};

/**
 * Wraps a TopicSection for DnD; uses internal isOver/isDragging flags
 * to apply pure-CSS placeholder and dragging styles.
 */
const SortableTopic: React.FC<SortableTopicProps> = ({
  topic,
  onEdit,
  onEditCancel,
  onEditSave,
  onDuplicate,
  onDelete,
  onToggle,
  isEditing,
}): JSX.Element => {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({
    id: topic.id,
  });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    position: "relative" as const,
    height: isDragging ? "auto" : undefined,
  };

  const classNames = ["tutorpress-sortable-topic", isDragging && "tutorpress-sortable-topic--dragging"]
    .filter(Boolean)
    .join(" ");

  return (
    <div ref={setNodeRef} className={classNames} style={style}>
      <TopicSection
        topic={topic}
        dragHandleProps={{ ...attributes, ...listeners, ref: setActivatorNodeRef }}
        onEdit={onEdit}
        onEditCancel={onEditCancel}
        onEditSave={onEditSave}
        onDuplicate={onDuplicate}
        onDelete={onDelete}
        onToggle={onToggle}
        isEditing={isEditing}
      />
    </div>
  );
};

// ============================================================================
// Main Component
// ============================================================================

/**
 * Main Curriculum component
 */
const Curriculum: React.FC = (): JSX.Element => {
  const courseId = useCourseId();
  const {
    topics,
    setTopics,
    operationState,
    setOperationState,
    topicCreationState,
    setTopicCreationState,
    editState,
    setEditState,
    reorderState,
    setReorderState,
    isAddingTopic,
    setIsAddingTopic,
    handleTopicToggle,
    handleAddTopicClick,
    handleTopicEdit,
    handleTopicEditCancel,
    handleTopicEditSave,
    isLoading,
    error,
  } = useTopics({ courseId });

  // Drag and drop state
  const [activeId, setActiveId] = useState<number | null>(null);
  const [overId, setOverId] = useState<number | null>(null);

  // Snapshot state for undo operations
  const [snapshot, setSnapshot] = useState<CurriculumSnapshot | null>(null);
  const [showError, setShowError] = useState(false);

  // Move useDispatch to component level
  const { createNotice } = useDispatch(noticesStore);

  // Configure pointer sensor for immediate drag
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 0 } }));

  // Memoize topic IDs array to prevent unnecessary recalculations
  const topicIds = useMemo(() => topics.map((t) => t.id), [topics]);

  // =============================
  // Callbacks
  // =============================

  /** Create a snapshot of current state */
  const createSnapshot = useCallback(
    (operation: CurriculumSnapshot["operation"]) => {
      setSnapshot({
        topics: [...topics],
        timestamp: Date.now(),
        operation,
      });
    },
    [topics]
  );

  /** Restore from snapshot */
  const restoreFromSnapshot = useCallback(() => {
    if (snapshot) {
      setTopics(snapshot.topics);
      setSnapshot(null);
      return true;
    }
    return false;
  }, [snapshot]);

  /** Clear error states */
  const clearErrors = useCallback(() => {
    if (operationState.status === "error") {
      setOperationState({ status: "idle" });
    }
    if (reorderState.status === "error") {
      setReorderState({ status: "idle" });
    }
  }, []);

  /** Handle error dismissal */
  const handleDismissError = useCallback(() => {
    setShowError(false);
  }, []);

  /** Retry last failed operation */
  const retryOperation = useCallback(async () => {
    if (!snapshot) return;

    clearErrors();

    switch (snapshot.operation) {
      case "reorder":
        await handleReorderTopics(topics);
        break;
    }
  }, [snapshot, topics, clearErrors]);

  /** Clear error on successful retry */
  const handleRetry = useCallback(async () => {
    await retryOperation();
    setShowError(false);
  }, [retryOperation]);

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
      setSnapshot(null); // Clear snapshot on success
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

  /** Handle drag start: cancel edit mode and close all topics */
  const handleDragStart = useCallback(
    (event: DragStartEvent): void => {
      setActiveId(Number(event.active.id));
      // Cancel edit mode if active
      if (editState.isEditing) {
        setEditState({ isEditing: false, topicId: null });
      }
      // Close all topics when dragging starts
      setTopics((currentTopics) =>
        currentTopics.map((topic) => ({
          ...topic,
          isCollapsed: true,
        }))
      );
    },
    [editState.isEditing]
  );

  /** Handle drag over: track item being dragged over */
  const handleDragOver = useCallback((event: DragOverEvent): void => {
    setOverId(event.over ? Number(event.over.id) : null);
  }, []);

  /** Handle drag end: reorder topics via API */
  const handleDragEnd = useCallback(
    async (event: DragEndEvent): Promise<void> => {
      // Clear drag states immediately
      setActiveId(null);
      setOverId(null);

      if (!event.over) return;

      const activeId = Number(event.active.id);
      const dropId = Number(event.over.id);

      if (activeId === dropId) return;

      const oldIndex = topics.findIndex((t) => t.id === activeId);
      const newIndex = topics.findIndex((t) => t.id === dropId);

      if (oldIndex === -1 || newIndex === -1) return;

      const newOrder = arrayMove(topics, oldIndex, newIndex);

      // Create snapshot before updating state
      createSnapshot("reorder");
      setTopics(newOrder);

      const result = await handleReorderTopics(newOrder);

      if (!result.success) {
        restoreFromSnapshot();
      }
    },
    [courseId, topics, createSnapshot, restoreFromSnapshot]
  );

  /** Handle drag cancel: clean up states */
  const handleDragCancel = useCallback((): void => {
    setActiveId(null);
    setOverId(null);
  }, []);

  /** Create a new topic */
  const createTopic = async (data: TopicFormData): Promise<OperationResult<Topic>> => {
    try {
      // Validate required fields
      if (!data.title.trim()) {
        return {
          success: false,
          error: {
            code: CurriculumErrorCode.VALIDATION_ERROR,
            message: __("Topic title is required", "tutorpress"),
            context: { action: "create_topic" },
          },
        };
      }

      // Calculate the new topic's menu_order (always at the end)
      const lastTopic =
        topics.length > 0
          ? topics.reduce((max, topic) => (topic.menu_order > max.menu_order ? topic : max), topics[0])
          : null;
      const newMenuOrder = lastTopic ? lastTopic.menu_order + 1 : 0;

      const response = await apiFetch<{ success: boolean; data: Topic; message?: string }>({
        path: `/tutorpress/v1/topics`,
        method: "POST",
        data: {
          course_id: courseId,
          title: data.title.trim(),
          content: data.summary.trim() || " ", // Ensure content is never null
          menu_order: newMenuOrder,
        },
      });

      if (!isWpRestResponse(response)) {
        return {
          success: false,
          error: {
            code: CurriculumErrorCode.INVALID_RESPONSE,
            message: __("Invalid response format from server", "tutorpress"),
            context: { action: "create_topic" },
          },
        };
      }

      // Type guard for database error response
      const isDbError = (data: unknown): data is { code: string; message: string } => {
        return (
          typeof data === "object" &&
          data !== null &&
          "code" in data &&
          typeof data.code === "string" &&
          "message" in data &&
          typeof data.message === "string"
        );
      };

      if (!response.success || !isValidTopic(response.data)) {
        // Handle specific database errors
        if (isDbError(response.data) && response.data.code === "db_insert_error") {
          return {
            success: false,
            error: {
              code: CurriculumErrorCode.SERVER_ERROR,
              message: __("Database error while creating topic. Please try again.", "tutorpress"),
              context: {
                action: "create_topic",
                details: response.data.message,
              },
            },
          };
        }

        return {
          success: false,
          error: {
            code: CurriculumErrorCode.CREATION_FAILED,
            message: response.message || __("Failed to create topic", "tutorpress"),
            context: { action: "create_topic" },
          },
        };
      }

      const newTopic = response.data as Topic;
      return { success: true, data: newTopic };
    } catch (error: unknown) {
      console.error("Error creating topic:", error);

      // Handle known error types
      if (error && typeof error === "object" && "code" in error && "message" in error) {
        return {
          success: false,
          error: {
            code: (error as { code: CurriculumErrorCode }).code,
            message: (error as { message: string }).message,
            context: { action: "create_topic" },
          },
        };
      }

      // Default error for unknown types
      return {
        success: false,
        error: {
          code: CurriculumErrorCode.CREATION_FAILED,
          message: error instanceof Error ? error.message : __("Failed to create topic", "tutorpress"),
          context: { action: "create_topic" },
        },
      };
    }
  };

  /** Handle topic form save */
  const handleTopicFormSave = useCallback(
    async (data: TopicFormData) => {
      setTopicCreationState({ status: "creating" });

      const result = await createTopic(data);

      if (result.success && result.data) {
        // Ensure we're working with a valid Topic
        const newTopic: Topic = result.data;
        setTopics((currentTopics) => [...currentTopics, newTopic]);
        setTopicCreationState({ status: "success", data: newTopic });
        setIsAddingTopic(false); // Close the form after successful creation
      } else {
        setTopicCreationState({ status: "error", error: result.error! });
        // Keep form open to allow retry
      }
    },
    [courseId, topics]
  );

  /** Handle topic form cancel */
  const handleTopicFormCancel = useCallback(() => {
    setTopicCreationState({ status: "idle" });
    setIsAddingTopic(false); // Close the form when cancelled
  }, []);

  /** Handle topic deletion */
  const handleTopicDelete = useCallback(
    async (topicId: number) => {
      if (!window.confirm(__("Are you sure you want to delete this topic?", "tutorpress"))) {
        return;
      }

      try {
        // Create snapshot before updating state
        createSnapshot("delete");

        // Optimistically update UI
        setTopics((currentTopics) => currentTopics.filter((t) => t.id !== topicId));

        const response = await apiFetch<{ success: boolean; message?: string }>({
          path: `/tutorpress/v1/topics/${topicId}`,
          method: "DELETE",
        });

        if (!response.success) {
          throw {
            code: CurriculumErrorCode.SERVER_ERROR,
            message: response.message || __("Failed to delete topic", "tutorpress"),
          };
        }

        // Clear snapshot on success
        setSnapshot(null);
      } catch (err) {
        console.error("Error deleting topic:", err);
        // Restore previous state
        restoreFromSnapshot();
        // Show error notification
        setShowError(true);
      }
    },
    [createSnapshot, restoreFromSnapshot]
  );

  /**
   * Handle duplicating a topic
   */
  const handleTopicDuplicate = useCallback(
    async (topicId: number) => {
      try {
        const duplicatedTopic = await duplicateTopic(courseId, topicId);

        // Only proceed if we got a valid topic back
        if (duplicatedTopic && duplicatedTopic.id) {
          // Create snapshot before updating state
          createSnapshot("duplicate");

          // Add the duplicated topic to the end of the list with isCollapsed set to true
          setTopics((prevTopics) => [...prevTopics, { ...duplicatedTopic, isCollapsed: true }]);

          // Clear snapshot on success
          setSnapshot(null);
        }
      } catch (error) {
        console.error("Error duplicating topic:", error);

        // Show error message
        createNotice("error", error instanceof Error ? error.message : __("Failed to duplicate topic", "tutorpress"));

        // Restore previous state if needed
        if (snapshot?.operation === "duplicate") {
          restoreFromSnapshot();
        }
      }
    },
    [courseId, createNotice, createSnapshot, restoreFromSnapshot, snapshot]
  );

  // =============================
  // Effects
  // =============================

  // Show error notification when error state changes
  useEffect(() => {
    if (reorderState.status === "error") {
      setShowError(true);
    } else {
      setShowError(false);
    }
  }, [reorderState]);

  // =============================
  // Render Methods
  // =============================

  // Render error state with retry button
  if (error) {
    return (
      <div className="tutorpress-curriculum">
        <Flex direction="column" align="center" gap={2} style={{ padding: "20px" }}>
          <div className="tutorpress-error" style={{ color: "red", marginBottom: "10px" }}>
            {getErrorMessage(error)}
          </div>
          <Button variant="secondary" icon={update} onClick={retryOperation}>
            {__("Retry", "tutorpress")}
          </Button>
        </Flex>
      </div>
    );
  }

  // Render loading state
  if (isLoading) {
    return (
      <div className="tutorpress-curriculum">
        <Flex direction="column" align="center" justify="center" style={{ padding: "20px", gap: "8px" }}>
          <Spinner />
          <span>{__("Loading curriculum...", "tutorpress")}</span>
        </Flex>
      </div>
    );
  }

  // Main render
  return (
    <div className="tutorpress-curriculum">
      <div style={{ textAlign: "left" }}>
        <DndContext
          sensors={sensors}
          onDragStart={handleDragStart}
          onDragOver={handleDragOver}
          onDragEnd={handleDragEnd}
          onDragCancel={handleDragCancel}
        >
          <SortableContext items={topicIds} strategy={verticalListSortingStrategy}>
            <div className="tutorpress-topics-list">
              {topics.length === 0 ? (
                <div style={{ textAlign: "center", padding: "32px 0", color: "#757575" }}>
                  {__("Start building your course", "tutorpress")}
                </div>
              ) : (
                topics.map((topic) => (
                  <div
                    key={topic.id}
                    className={`tutorpress-topic-wrapper ${activeId && overId === topic.id ? "show-indicator" : ""}`}
                  >
                    <SortableTopic
                      topic={topic}
                      onEdit={() => handleTopicEdit(topic.id)}
                      onEditCancel={handleTopicEditCancel}
                      onEditSave={handleTopicEditSave}
                      onDuplicate={() => handleTopicDuplicate(topic.id)}
                      onDelete={() => handleTopicDelete(topic.id)}
                      onToggle={() => handleTopicToggle(topic.id)}
                      isEditing={editState.isEditing && editState.topicId === topic.id}
                    />
                    {reorderState.status === "reordering" && activeId === topic.id && (
                      <div className="tutorpress-saving-indicator">
                        <Flex align="center" gap={2}>
                          <Spinner />
                          <div>{__("Saving...", "tutorpress")}</div>
                        </Flex>
                      </div>
                    )}
                  </div>
                ))
              )}
              {isAddingTopic && (
                <TopicForm
                  onSave={handleTopicFormSave}
                  onCancel={handleTopicFormCancel}
                  error={topicCreationState.status === "error" ? topicCreationState.error : undefined}
                  isCreating={topicCreationState.status === "creating"}
                />
              )}
            </div>
          </SortableContext>
        </DndContext>

        <div style={{ marginTop: "16px" }}>
          <Button
            variant="secondary"
            className="tutorpress-add-topic"
            icon={plus}
            onClick={handleAddTopicClick}
            disabled={
              ["loading", "reordering"].includes(operationState.status) ||
              reorderState.status === "reordering" ||
              isAddingTopic
            }
          >
            {__("Add Topic", "tutorpress")}
          </Button>
        </div>
      </div>

      {/* Error notification */}
      {showError && reorderState.status === "error" && (
        <div className="tutorpress-error-notification">
          <Flex direction="column" gap={2} style={{ padding: "12px" }}>
            <Flex justify="space-between" align="center">
              <div style={{ color: "#842029", fontWeight: "500" }}>{__("Error Saving Changes", "tutorpress")}</div>
              <Button
                icon={close}
                label={__("Dismiss", "tutorpress")}
                onClick={handleDismissError}
                style={{ padding: 0, height: "auto" }}
              />
            </Flex>
            <div style={{ color: "#842029" }}>{getErrorMessage(reorderState.error)}</div>
            <Flex justify="flex-end">
              <Button variant="secondary" icon={update} onClick={handleRetry} style={{ marginTop: "8px" }}>
                {__("Retry", "tutorpress")}
              </Button>
            </Flex>
          </Flex>
        </div>
      )}
    </div>
  );
};

export default Curriculum;
