/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState, useMemo, useCallback } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock, ButtonGroup, Spinner } from "@wordpress/components";
import {
  moreVertical,
  plus,
  edit,
  copy,
  trash,
  dragHandle,
  chevronDown,
  chevronRight,
  update,
  close,
} from "@wordpress/icons";
import type { Topic, ContentItem, DragHandleProps, SortableTopicProps, TopicSectionProps } from "../../types/courses";
import type { TutorResponse } from "../../types/api";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";
import type { DragEndEvent, DragStartEvent, DragOverEvent } from "@dnd-kit/core";
import { DndContext, useSensor, useSensors, PointerSensor } from "@dnd-kit/core";
import { SortableContext, useSortable, verticalListSortingStrategy, arrayMove } from "@dnd-kit/sortable";
import { CSS } from "@dnd-kit/utilities";

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

/** Props for action buttons */
interface ActionButtonsProps {
  onEdit?: () => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
}

/** Props for content item row */
interface ContentItemRowProps {
  item: ContentItem;
  onEdit?: () => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
}

/** Props for sortable topic wrapper */
interface SortableTopicWrapperProps {
  topic: Topic;
  isActive: boolean;
  isOver: boolean;
}

/** Error codes for curriculum operations */
enum CurriculumErrorCode {
  FETCH_FAILED = "fetch_failed",
  REORDER_FAILED = "reorder_failed",
  INVALID_RESPONSE = "invalid_response",
  SERVER_ERROR = "server_error",
  NETWORK_ERROR = "network_error",
}

/** Structured error type for curriculum operations */
interface CurriculumError {
  code: CurriculumErrorCode;
  message: string;
  // Include additional context that might help users or support
  context?: {
    action?: string;
    topicId?: number;
  };
}

/** API response status */
type ApiStatus = "idle" | "loading" | "success" | "error";

/** Topic operation state with structured error */
type TopicOperationState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "success"; data: Topic[] }
  | { status: "error"; error: CurriculumError };

/** Reorder operation state with structured error */
type ReorderOperationState =
  | { status: "idle" }
  | { status: "reordering" }
  | { status: "success" }
  | { status: "error"; error: CurriculumError };

/** Get user-friendly error message based on error code */
const getErrorMessage = (error: CurriculumError): string => {
  switch (error.code) {
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

/**
 * Action buttons for items and topics
 */
const ActionButtons: React.FC<ActionButtonsProps> = ({ onEdit, onDuplicate, onDelete }): JSX.Element => (
  <Flex gap={1} justify="flex-end" style={{ width: "auto" }}>
    <Button icon={edit} label="Edit" isSmall onClick={onEdit} />
    <Button icon={copy} label="Duplicate" isSmall onClick={onDuplicate} />
    <Button icon={trash} label="Delete" isSmall onClick={onDelete} />
  </Flex>
);

/**
 * Renders a single content item
 */
const ContentItemRow: React.FC<ContentItemRowProps> = ({ item, onEdit, onDuplicate, onDelete }): JSX.Element => (
  <Flex className="tutorpress-content-item" align="center" gap={2}>
    <Button icon={dragHandle} label="Drag to reorder" isSmall />
    <Icon icon={contentTypeIcons[item.type]} />
    <FlexBlock style={{ textAlign: "left" }}>{item.title}</FlexBlock>
    <ActionButtons onEdit={onEdit} onDuplicate={onDuplicate} onDelete={onDelete} />
  </Flex>
);

/**
 * Renders a topic section with its content items and accepts drag handle props
 */
const TopicSection: React.FC<TopicSectionProps & ActionButtonsProps> = ({
  topic,
  dragHandleProps,
  onEdit,
  onDuplicate,
  onDelete,
}): JSX.Element => (
  <Card className="tutorpress-topic">
    <CardHeader>
      <Flex align="center" gap={2}>
        <Button icon={dragHandle} label="Drag to reorder" isSmall {...dragHandleProps} />
        <FlexBlock style={{ textAlign: "left" }}>{topic.title}</FlexBlock>
        <ActionButtons onEdit={onEdit} onDuplicate={onDuplicate} onDelete={onDelete} />
        <Button
          icon={topic.isCollapsed ? chevronRight : chevronDown}
          label={topic.isCollapsed ? "Expand" : "Collapse"}
          isSmall
        />
      </Flex>
    </CardHeader>
    <CardBody>
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
  </Card>
);

/**
 * Wraps a TopicSection for DnD; uses internal isOver/isDragging flags
 * to apply pure-CSS placeholder and dragging styles.
 */
const SortableTopic: React.FC<SortableTopicProps & ActionButtonsProps> = ({
  topic,
  onEdit,
  onDuplicate,
  onDelete,
}): JSX.Element => {
  const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging, isOver } =
    useSortable({ id: topic.id });

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
        onDuplicate={onDuplicate}
        onDelete={onDelete}
      />
    </div>
  );
};

/** Snapshot of curriculum state */
interface CurriculumSnapshot {
  topics: Topic[];
  timestamp: number;
  operation: "reorder" | "edit" | "delete";
}

/** Operation result type */
type OperationResult<T> = {
  success: boolean;
  data?: T;
  error?: CurriculumError;
};

/**
 * Main Curriculum component
 */
const Curriculum: React.FC = (): JSX.Element => {
  const [operationState, setOperationState] = useState<TopicOperationState>({ status: "idle" });
  const [reorderState, setReorderState] = useState<ReorderOperationState>({ status: "idle" });
  const [topics, setTopics] = useState<Topic[]>([]);
  const [activeId, setActiveId] = useState<number | null>(null);
  const [overId, setOverId] = useState<number | null>(null);
  const [snapshot, setSnapshot] = useState<CurriculumSnapshot | null>(null);
  const [showError, setShowError] = useState(false);

  // Get course ID from URL - simplified as we trust WordPress context
  const courseId = Number(new URLSearchParams(window.location.search).get("post"));

  if (!courseId) {
    return <div>{__("Unable to load curriculum - missing course ID", "tutorpress")}</div>;
  }

  // Configure pointer sensor for immediate drag
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 0 } }));

  // Memoize topic IDs array to prevent unnecessary recalculations
  const topicIds = useMemo(() => topics.map((t) => t.id), [topics]);

  // Fetch topics on mount
  React.useEffect(() => {
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
          const validTopics = response.data.filter(isValidTopic);
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

  /** Retry last failed operation */
  const retryOperation = useCallback(async () => {
    if (!snapshot) return;

    clearErrors();

    switch (snapshot.operation) {
      case "reorder":
        // Retry reordering with current topic order
        await handleReorderTopics(topics);
        break;
      // Add cases for other operations as they're implemented
    }
  }, [snapshot, topics, clearErrors]);

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

      // Handle network errors specifically
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

  /** Handle drag start: track active item */
  const handleDragStart = useCallback((event: DragStartEvent): void => {
    setActiveId(Number(event.active.id));
  }, []);

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

  /** Handle error dismissal */
  const handleDismissError = useCallback(() => {
    setShowError(false);
    // Don't clear the error state itself, in case we need it for retry
  }, []);

  /** Show error notification when error state changes */
  React.useEffect(() => {
    if (reorderState.status === "error") {
      setShowError(true);
    } else {
      setShowError(false);
    }
  }, [reorderState]);

  /** Clear error on successful retry */
  const handleRetry = useCallback(async () => {
    await retryOperation();
    setShowError(false);
  }, [retryOperation]);

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

  return (
    <div className="tutorpress-curriculum">
      {reorderState.status === "error" && (
        <Flex
          direction="column"
          align="center"
          gap={2}
          style={{
            marginBottom: "16px",
            padding: "12px",
            background: "#f8d7da",
            border: "1px solid #f5c2c7",
            borderRadius: "4px",
          }}
        >
          <div className="tutorpress-error" style={{ color: "#842029" }}>
            {getErrorMessage(reorderState.error)}
          </div>
          <Button variant="secondary" icon={update} onClick={retryOperation}>
            {__("Retry", "tutorpress")}
          </Button>
        </Flex>
      )}

      <div style={{ textAlign: "left" }}>
        <Button
          variant="secondary"
          className="tutorpress-add-topic"
          icon={plus}
          disabled={["loading", "reordering"].includes(operationState.status) || reorderState.status === "reordering"}
        >
          Add Topic
        </Button>

        <DndContext
          sensors={sensors}
          onDragStart={handleDragStart}
          onDragOver={handleDragOver}
          onDragEnd={handleDragEnd}
          onDragCancel={handleDragCancel}
        >
          <SortableContext items={topicIds} strategy={verticalListSortingStrategy}>
            <div className="tutorpress-topics-list">
              {topics.map((topic) => (
                <div
                  key={topic.id}
                  className={`tutorpress-topic-wrapper ${activeId && overId === topic.id ? "show-indicator" : ""}`}
                >
                  <SortableTopic
                    topic={topic}
                    onEdit={() => console.log("Edit topic:", topic.id)}
                    onDuplicate={() => console.log("Duplicate topic:", topic.id)}
                    onDelete={() => console.log("Delete topic:", topic.id)}
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
              ))}
            </div>
          </SortableContext>
        </DndContext>
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
