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
import { useTopics, useCourseId, useDragDrop } from "../../hooks/curriculum";
import { useCurriculumError } from "../../hooks/curriculum/useCurriculumError";

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

  const { activeId, overId, handleDragStart, handleDragOver, handleDragEnd, handleDragCancel, handleReorderTopics } =
    useDragDrop({
      courseId,
      topics,
      setTopics,
      setEditState,
      setReorderState,
    });

  const { showError, handleDismissError, handleRetry, getErrorMessage } = useCurriculumError({
    reorderState,
    deletionState,
    duplicationState,
    topics,
    handleReorderTopics,
    handleTopicDelete,
    handleTopicDuplicate,
  });

  // Move useDispatch to component level
  const { createNotice } = useDispatch(noticesStore);

  // Configure pointer sensor for immediate drag
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 0 } }));

  // Memoize topic IDs array to prevent unnecessary recalculations
  const topicIds = useMemo(() => topics.map((t) => t.id), [topics]);

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
