/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI for managing course topics. Uses WordPress Data
 * store for global state management and @dnd-kit for drag-and-drop functionality.
 *
 * State Management:
 * - Topics and related states are managed through the curriculum store
 * - Drag and drop state is managed locally through useDragDrop hook
 * - Error handling is managed through useCurriculumError hook
 */
import React, { useMemo, useEffect } from "react";
import { Button, Flex, FlexBlock, Spinner } from "@wordpress/components";
import { plus, update, close } from "@wordpress/icons";
import { CurriculumErrorCode } from "../../types/curriculum";
import type { Topic, OperationResult } from "../../types/curriculum";
import { __ } from "@wordpress/i18n";
import { DndContext } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import { store as noticesStore } from "@wordpress/notices";
import { useDispatch, useSelect } from "@wordpress/data";
import { curriculumStore } from "../../store/curriculum";
import { TopicSection } from "./curriculum/TopicSection";
import TopicForm from "./curriculum/TopicForm";
import { useTopics, useCourseId, useDragDrop } from "../../hooks/curriculum";
import { useCurriculumError } from "../../hooks/curriculum/useCurriculumError";

// ============================================================================
// Components
// ============================================================================

/**
 * Wraps a TopicSection component with drag-and-drop functionality using generic CSS classes
 */
const SortableTopic: React.FC<{
  topic: Topic;
  courseId?: number;
  onEdit: () => void;
  onEditCancel: () => void;
  onEditSave: (topicId: number, data: any) => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
  onToggle?: () => void;
  isEditing: boolean;
  getItemClasses: (item: any, isDragging?: boolean) => string;
}> = ({
  topic,
  courseId,
  onEdit,
  onEditCancel,
  onEditSave,
  onDuplicate,
  onDelete,
  onToggle,
  isEditing,
  getItemClasses,
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

  const classNames = getItemClasses(topic, isDragging);

  return (
    <div ref={setNodeRef} className={classNames} style={style}>
      <TopicSection
        topic={topic}
        courseId={courseId}
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
 * Main Curriculum component for managing course topics.
 *
 * Features:
 * - Topic list with drag-and-drop reordering
 * - Topic creation, editing, deletion, and duplication
 * - Error handling and retry functionality
 * - Loading and empty states
 *
 * State Management:
 * - Uses curriculum store for global state (topics, operations, etc.)
 * - Local state for drag-and-drop operations
 * - Error handling through dedicated hook
 */
const Curriculum: React.FC = (): JSX.Element => {
  // Get root element data attributes
  const rootElement = document.getElementById("tutorpress-curriculum-builder");
  const postId = rootElement?.dataset.postId ? parseInt(rootElement.dataset.postId, 10) : 0;
  const postType = rootElement?.dataset.postType || "";
  const isLesson = postType === "lesson";
  const isAssignment = postType === "tutor_assignments";

  // Get course ID from hook (will handle both direct course ID and parent course ID)
  const courseId = useCourseId();

  // Get store state
  const { operationState } = useSelect(
    (select) => ({
      operationState: select(curriculumStore).getOperationState(),
    }),
    []
  );

  const {
    topics,
    topicCreationState,
    editState,
    reorderState,
    deletionState,
    duplicationState,
    isAddingTopic,
    handleTopicToggle,
    handleAddTopicClick,
    handleTopicEdit,
    handleTopicEditCancel,
    handleTopicEditSave,
    handleTopicFormSave,
    handleTopicFormCancel,
    handleTopicDelete,
    handleTopicDuplicate,
    isLoading,
    error,
  } = useTopics({ courseId: courseId ?? 0, isLesson, isAssignment });

  // Get store actions
  const { setTopics, setEditState, setReorderState } = useDispatch(curriculumStore);

  const {
    activeId,
    overId,
    handleDragStart,
    handleDragOver,
    handleDragEnd,
    handleDragCancel,
    handleReorderTopics,
    sensors,
    itemIds,
    getItemClasses,
    getWrapperClasses,
    dragState,
  } = useDragDrop({
    courseId: courseId ?? 0,
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
    handleTopicDelete,
    handleTopicDuplicate,
    handleReorderTopics: async (topics: Topic[]): Promise<OperationResult<void>> => {
      setReorderState({ status: "reordering" });
      try {
        setTopics(topics);
        setReorderState({ status: "success" });
        return { success: true };
      } catch (err) {
        const error = {
          code: CurriculumErrorCode.REORDER_FAILED,
          message: err instanceof Error ? err.message : __("Failed to reorder topics", "tutorpress"),
        };
        setReorderState({
          status: "error",
          error,
        });
        return { success: false, error };
      }
    },
  });

  const { createNotice } = useDispatch(noticesStore);

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
  if (isLoading || operationState.status === "loading" || !courseId) {
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
          <SortableContext items={itemIds} strategy={verticalListSortingStrategy}>
            <div className="tutorpress-topics-list">
              {topics.length === 0 ? (
                <div style={{ textAlign: "center", padding: "32px 0", color: "#757575" }}>
                  {__("Start building your course", "tutorpress")}
                </div>
              ) : (
                topics.map((topic) => (
                  <div key={topic.id} className={getWrapperClasses(topic)}>
                    <SortableTopic
                      topic={topic}
                      courseId={courseId}
                      onEdit={() => handleTopicEdit(topic.id)}
                      onEditCancel={handleTopicEditCancel}
                      onEditSave={handleTopicEditSave}
                      onDuplicate={() => handleTopicDuplicate(topic.id)}
                      onDelete={() => handleTopicDelete(topic.id)}
                      onToggle={() => handleTopicToggle(topic.id)}
                      isEditing={editState.isEditing && editState.topicId === topic.id}
                      getItemClasses={getItemClasses}
                    />
                  </div>
                ))
              )}
            </div>
          </SortableContext>
        </DndContext>

        {/* Add Topic Button */}
        {!isAddingTopic && (
          <Button
            variant="secondary"
            icon={plus}
            onClick={handleAddTopicClick}
            style={{ marginTop: "16px" }}
            disabled={isLoading}
          >
            {__("Add Topic", "tutorpress")}
          </Button>
        )}

        {/* Topic Creation Form */}
        {isAddingTopic && (
          <TopicForm
            onSave={handleTopicFormSave}
            onCancel={handleTopicFormCancel}
            isCreating={topicCreationState.status === "creating"}
          />
        )}
      </div>
    </div>
  );
};

export default Curriculum;
