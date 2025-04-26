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
    deletionState,
    duplicationState,
    isAddingTopic,
    setIsAddingTopic,
    handleTopicToggle,
    handleAddTopicClick,
    handleTopicEdit,
    handleTopicEditCancel,
    handleTopicEditSave,
    handleTopicFormSave,
    handleTopicFormCancel,
    handleTopicDelete,
    handleTopicDuplicate,
    createSnapshot,
    restoreFromSnapshot,
    isLoading,
    error,
  } = useTopics({ courseId });

  // Drag and drop state
  const [activeId, setActiveId] = useState<number | null>(null);
  const [overId, setOverId] = useState<number | null>(null);

  // Error notification state
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

  /** Handle error dismissal */
  const handleDismissError = useCallback(() => {
    setShowError(false);
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

  /** Handle retry for failed operations */
  const handleRetry = useCallback(async () => {
    if (reorderState.status === "error") {
      await handleReorderTopics(topics);
    } else if (deletionState.status === "error" && deletionState.topicId) {
      await handleTopicDelete(deletionState.topicId);
    } else if (duplicationState.status === "error" && duplicationState.sourceTopicId) {
      await handleTopicDuplicate(duplicationState.sourceTopicId);
    }
    setShowError(false);
  }, [
    reorderState.status,
    deletionState,
    duplicationState,
    topics,
    handleReorderTopics,
    handleTopicDelete,
    handleTopicDuplicate,
  ]);

  // =============================
  // Effects
  // =============================

  // Show error notification when error states change
  useEffect(() => {
    if (reorderState.status === "error" || deletionState.status === "error" || duplicationState.status === "error") {
      setShowError(true);
    } else {
      setShowError(false);
    }
  }, [reorderState, deletionState, duplicationState]);

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
          <Button variant="secondary" icon={update} onClick={handleRetry}>
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
      {showError &&
        (reorderState.status === "error" ||
          deletionState.status === "error" ||
          duplicationState.status === "error") && (
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
              <div style={{ color: "#842029" }}>
                {reorderState.status === "error"
                  ? getErrorMessage(reorderState.error)
                  : deletionState.status === "error"
                  ? getErrorMessage(deletionState.error!)
                  : duplicationState.status === "error"
                  ? getErrorMessage(duplicationState.error!)
                  : ""}
              </div>
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
