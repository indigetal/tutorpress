/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState, useMemo, useCallback } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock, ButtonGroup } from "@wordpress/components";
import { moreVertical, plus, edit, copy, trash, dragHandle, chevronDown, chevronRight } from "@wordpress/icons";
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

/**
 * Main Curriculum component
 */
const Curriculum: React.FC = (): JSX.Element => {
  const [operationState, setOperationState] = useState<TopicOperationState>({ status: "idle" });
  const [reorderState, setReorderState] = useState<ReorderOperationState>({ status: "idle" });
  const [topics, setTopics] = useState<Topic[]>([]);
  const [activeId, setActiveId] = useState<number | null>(null);
  const [overId, setOverId] = useState<number | null>(null);

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
      const snapshot = topics;
      setTopics(newOrder);
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
      } catch (err) {
        console.error("Error reordering topics:", err);
        setTopics(snapshot);
        setReorderState({
          status: "error",
          error: {
            code: CurriculumErrorCode.REORDER_FAILED,
            message: err instanceof Error ? err.message : __("Failed to reorder topics", "tutorpress"),
            context: {
              action: "reorder_topics",
              topicId: activeId,
            },
          },
        });
      }
    },
    [courseId, topics]
  );

  /** Handle drag cancel: clean up states */
  const handleDragCancel = useCallback((): void => {
    setActiveId(null);
    setOverId(null);
  }, []);

  // Render error state
  if (operationState.status === "error") {
    return (
      <div className="tutorpress-curriculum">
        <div className="tutorpress-error" style={{ color: "red", marginBottom: "10px" }}>
          {getErrorMessage(operationState.error)}
        </div>
      </div>
    );
  }

  // Render loading state
  if (operationState.status === "loading") {
    return (
      <div className="tutorpress-curriculum">
        <div>Loading topics...</div>
      </div>
    );
  }

  return (
    <div className="tutorpress-curriculum">
      {reorderState.status === "error" && (
        <div className="tutorpress-error" style={{ color: "red", marginBottom: "10px" }}>
          {getErrorMessage(reorderState.error)}
        </div>
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
                </div>
              ))}
            </div>
          </SortableContext>
        </DndContext>
      </div>
    </div>
  );
};

export default Curriculum;
