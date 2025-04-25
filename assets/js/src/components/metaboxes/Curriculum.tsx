/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState, useMemo, useCallback, useEffect } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock, ButtonGroup, Spinner } from "@wordpress/components";
import { moreVertical, plus, dragHandle, chevronDown, chevronRight, update, close } from "@wordpress/icons";
import { CurriculumErrorCode } from "../../types/curriculum";
import type {
  Topic,
  ContentItem,
  DragHandleProps,
  CurriculumError,
  TopicFormData,
  TopicEditState,
  TopicCreationState,
  TopicOperationState,
  ReorderOperationState,
  CurriculumSnapshot,
  OperationResult,
  SortableTopicProps,
  TopicSectionProps,
} from "../../types/curriculum";
import type { TutorResponse } from "../../types/api";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import type { DragEndEvent, DragStartEvent, DragOverEvent } from "@dnd-kit/core";
import { DndContext, useSensor, useSensors, PointerSensor } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy, arrayMove } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";
import type { MouseEvent } from "react";
import { getTopics, reorderTopics, duplicateTopic } from "../../api/topics";
import { store as noticesStore } from "@wordpress/notices";
import { useDispatch } from "@wordpress/data";
import ActionButtons from "./curriculum/ActionButtons";
import TopicForm from "./curriculum/TopicForm";

// ============================================================================
// Type Definitions
// ============================================================================

/**
 * Props for content item row
 */
interface ContentItemRowProps {
  item: ContentItem;
  onEdit?: () => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
}

// ============================================================================
// Constants
// ============================================================================

/**
 * Content item icon mapping
 */
const contentTypeIcons = {
  lesson: "text-page",
  quiz: "star-filled",
  interactive_quiz: "chart-bar",
  assignment: "clipboard",
  meet_lesson: "video-alt2",
  zoom_lesson: "video-alt3",
} as const;

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

// Topic validation focusing on essential fields
const isValidTopic = (topic: unknown): topic is Topic => {
  return (
    typeof topic === "object" &&
    topic !== null &&
    "id" in topic &&
    "title" in topic &&
    "contents" in topic &&
    Array.isArray((topic as Topic).contents)
  );
};

// ============================================================================
// Sub-Components
// ============================================================================

/**
 * Renders a single content item
 */
const ContentItemRow: React.FC<ContentItemRowProps> = ({ item, onEdit, onDuplicate, onDelete }): JSX.Element => (
  <div className="tutorpress-content-item">
    <Flex align="center" gap={2}>
      <div className="tutorpress-content-item-icon">
        <Icon icon={contentTypeIcons[item.type]} className="item-icon" />
        <Icon icon={dragHandle} className="drag-icon" />
      </div>
      <FlexBlock style={{ textAlign: "left" }}>{item.title}</FlexBlock>
      <div className="tutorpress-content-item-actions">
        <ActionButtons onEdit={onEdit} onDuplicate={onDuplicate} onDelete={onDelete} />
      </div>
    </Flex>
  </div>
);

/**
 * Renders a topic section with its content items and accepts drag handle props
 */
const TopicSection: React.FC<TopicSectionProps> = ({
  topic,
  dragHandleProps,
  onEdit,
  onEditCancel,
  onEditSave,
  onDuplicate,
  onDelete,
  onToggle,
  isEditing,
}): JSX.Element => {
  // Handle double-click on title or summary
  const handleDoubleClick = (e: MouseEvent<HTMLDivElement>) => {
    e.preventDefault();
    onEdit();
  };

  // Handle header click for toggle
  const handleHeaderClick = (e: MouseEvent<HTMLDivElement>) => {
    // Don't toggle if clicking on a button or dragging
    if ((e.target as HTMLElement).closest("button")) {
      return;
    }
    onToggle?.();
  };

  const headerClassName = `tutorpress-topic-header ${!topic.isCollapsed ? "is-open" : ""}`;
  const cardClassName = `tutorpress-topic ${!topic.isCollapsed ? "is-open" : ""}`;

  return (
    <Card className={cardClassName}>
      <CardHeader className={headerClassName} onClick={handleHeaderClick}>
        <Flex align="center" gap={2}>
          <Button icon={dragHandle} label="Drag to reorder" isSmall {...dragHandleProps} />
          <FlexBlock style={{ textAlign: "left" }}>
            {!isEditing && (
              <div className="tutorpress-topic-title" onDoubleClick={handleDoubleClick}>
                {topic.title}
              </div>
            )}
          </FlexBlock>
          <div className="tutorpress-topic-actions">
            <ActionButtons onEdit={onEdit} onDuplicate={onDuplicate} onDelete={onDelete} />
            <Button
              icon={topic.isCollapsed ? chevronRight : chevronDown}
              label={topic.isCollapsed ? __("Expand", "tutorpress") : __("Collapse", "tutorpress")}
              onClick={(e: MouseEvent<HTMLButtonElement>) => {
                e.stopPropagation();
                onToggle?.();
              }}
              isSmall
            />
          </div>
        </Flex>
      </CardHeader>
      {isEditing ? (
        <TopicForm
          initialData={{ title: topic.title, summary: topic.content || "" }}
          onSave={(data) => onEditSave(topic.id, data)}
          onCancel={onEditCancel}
        />
      ) : !topic.isCollapsed ? (
        <CardBody>
          {topic.content && (
            <div
              className="tutorpress-topic-summary"
              onDoubleClick={handleDoubleClick}
              style={{ marginBottom: "16px" }}
            >
              {topic.content}
            </div>
          )}
          <div className="tutorpress-content-items">
            {topic.contents.map((item) => (
              <ContentItemRow
                key={item.id}
                item={item}
                onEdit={() => console.log("Edit content:", item.id)}
                onDuplicate={() => console.log("Duplicate content:", item.id)}
                onDelete={() => console.log("Delete content:", item.id)}
              />
            ))}
          </div>
          <Flex className="tutorpress-content-actions" justify="space-between" gap={2}>
            <Flex gap={2} style={{ width: "auto" }}>
              <Button variant="secondary" isSmall icon={plus}>
                Lesson
              </Button>
              <Button variant="secondary" isSmall icon={plus}>
                Quiz
              </Button>
              <Button variant="secondary" isSmall icon={plus}>
                Interactive Quiz
              </Button>
              <Button variant="secondary" isSmall icon={plus}>
                Assignment
              </Button>
            </Flex>
            <Button icon={moreVertical} label="More options" isSmall />
          </Flex>
        </CardBody>
      ) : null}
    </Card>
  );
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
  // =============================
  // State
  // =============================
  const [operationState, setOperationState] = useState<TopicOperationState>({ status: "idle" });
  const [reorderState, setReorderState] = useState<ReorderOperationState>({ status: "idle" });
  const [topics, setTopics] = useState<Topic[]>([]);
  const [activeId, setActiveId] = useState<number | null>(null);
  const [overId, setOverId] = useState<number | null>(null);
  const [snapshot, setSnapshot] = useState<CurriculumSnapshot | null>(null);
  const [showError, setShowError] = useState(false);
  const [isAddingTopic, setIsAddingTopic] = useState(false);
  const [topicCreationState, setTopicCreationState] = useState<TopicCreationState>({ status: "idle" });
  const [editState, setEditState] = useState<TopicEditState>({ isEditing: false, topicId: null });

  // Move useDispatch to component level
  const { createNotice } = useDispatch(noticesStore);

  // Get course ID from URL - simplified as we trust WordPress context
  const courseId = Number(new URLSearchParams(window.location.search).get("post"));

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

  /** Handle topic edit start */
  const handleTopicEdit = useCallback((topicId: number) => {
    setEditState({ isEditing: true, topicId });
  }, []);

  /** Handle topic edit cancel */
  const handleTopicEditCancel = useCallback(() => {
    setEditState({ isEditing: false, topicId: null });
  }, []);

  /** Handle topic edit save */
  const handleTopicEditSave = useCallback(async (topicId: number, data: TopicFormData) => {
    try {
      const response = await apiFetch<{ success: boolean; data: Topic; message?: string }>({
        path: `/tutorpress/v1/topics/${topicId}`,
        method: "PATCH",
        data: {
          title: data.title.trim(),
          content: data.summary.trim() || " ", // Ensure content is never null
        },
      });

      if (!response.success || !isValidTopic(response.data)) {
        throw {
          code: CurriculumErrorCode.CREATION_FAILED,
          message: response.message || __("Failed to update topic", "tutorpress"),
        };
      }

      // Update topic in list with response data and ensure it's collapsed
      setTopics((currentTopics) =>
        currentTopics.map((topic) =>
          topic.id === topicId ? { ...topic, title: response.data.title, content: response.data.content } : topic
        )
      );
      setEditState({ isEditing: false, topicId: null });
    } catch (err) {
      console.error("Error updating topic:", err);
      setShowError(true);
    }
  }, []);

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

  /** Handle topic toggle */
  const handleTopicToggle = useCallback((topicId: number) => {
    setTopics((currentTopics) =>
      currentTopics.map((topic) => (topic.id === topicId ? { ...topic, isCollapsed: !topic.isCollapsed } : topic))
    );
  }, []);

  /** Handle starting topic addition */
  const handleAddTopicClick = useCallback(() => {
    // Close all topics when adding a new one
    setTopics((currentTopics) =>
      currentTopics.map((topic) => ({
        ...topic,
        isCollapsed: true,
      }))
    );
    setIsAddingTopic(true);
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
        setIsAddingTopic(false);
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
    setIsAddingTopic(false);
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

  // Fetch topics on mount
  useEffect(() => {
    const fetchTopics = async (): Promise<void> => {
      setOperationState({ status: "loading" });

      try {
        const response = await apiFetch<unknown>({
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
          method: "GET",
        });

        if (!isWpRestResponse(response)) {
          throw {
            code: CurriculumErrorCode.INVALID_RESPONSE,
            message: __("Invalid response format from server", "tutorpress"),
          };
        }

        if (response.success && Array.isArray(response.data)) {
          const validTopics = response.data.filter(isValidTopic).map((topic) => ({ ...topic, isCollapsed: true }));
          setTopics(validTopics);
          setOperationState({ status: "success", data: validTopics });
        } else {
          throw {
            code: CurriculumErrorCode.SERVER_ERROR,
            message: response.message || __("Server returned an error", "tutorpress"),
          };
        }
      } catch (err) {
        console.error("Error fetching topics:", err);
        setOperationState({
          status: "error",
          error: {
            code: CurriculumErrorCode.FETCH_FAILED,
            message: err instanceof Error ? err.message : __("Failed to load topics", "tutorpress"),
            context: { action: "fetch_topics" },
          },
        });
      }
    };

    fetchTopics();
  }, [courseId]);

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
  if (operationState.status === "error") {
    return (
      <div className="tutorpress-curriculum">
        <Flex direction="column" align="center" gap={2} style={{ padding: "20px" }}>
          <div className="tutorpress-error" style={{ color: "red", marginBottom: "10px" }}>
            {getErrorMessage(operationState.error)}
          </div>
          <Button variant="secondary" icon={update} onClick={retryOperation}>
            {__("Retry", "tutorpress")}
          </Button>
        </Flex>
      </div>
    );
  }

  // Render loading state
  if (operationState.status === "loading" || operationState.status === "idle") {
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

      {/* Floating error notification */}
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
