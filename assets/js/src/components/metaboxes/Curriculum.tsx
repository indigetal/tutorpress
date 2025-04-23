/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState } from "react";
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

/** API response type for topic operations */
interface TopicApiResponse {
  success: boolean;
  message: string;
  data: Topic[];
}

/** API response type for reordering */
interface ReorderApiResponse {
  success: boolean;
  message: string;
  data: {
    orders: Array<{
      topic_id: number;
      order: number;
    }>;
  };
}

/** API response status */
type ApiStatus = "idle" | "loading" | "success" | "error";

/** API error types */
type ApiErrorType =
  | { type: "network"; message: string }
  | { type: "validation"; message: string; field?: string }
  | { type: "auth"; message: string }
  | { type: "unknown"; message: string };

/** Topic operation state */
type TopicOperationState =
  | { status: "idle" }
  | { status: "loading" }
  | { status: "success"; data: Topic[] }
  | { status: "error"; error: ApiErrorType };

/** Reorder operation state */
type ReorderOperationState =
  | { status: "idle" }
  | { status: "reordering" }
  | { status: "success" }
  | { status: "error"; error: ApiErrorType };

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

  // Get course ID from URL
  const courseId = new URLSearchParams(window.location.search).get("post");

  // Configure pointer sensor for immediate drag
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 0 } }));

  // Fetch topics on mount
  React.useEffect(() => {
    const fetchTopics = async (): Promise<void> => {
      if (!courseId) return;

      setOperationState({ status: "loading" });

      try {
        const response = await apiFetch<TopicApiResponse>({
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
          method: "GET",
        });

        if (response.success) {
          setTopics(response.data);
          setOperationState({ status: "success", data: response.data });
        } else {
          setOperationState({
            status: "error",
            error: { type: "validation", message: response.message },
          });
        }
      } catch (err) {
        console.error("Error fetching topics:", err);
        setOperationState({
          status: "error",
          error: {
            type: "network",
            message: err instanceof Error ? err.message : "Failed to fetch topics",
          },
        });
      }
    };

    fetchTopics();
  }, [courseId]);

  /** Handle drag start: track active item */
  const handleDragStart = (event: DragStartEvent): void => {
    setActiveId(Number(event.active.id));
  };

  /** Handle drag over: track item being dragged over */
  const handleDragOver = (event: DragOverEvent): void => {
    setOverId(event.over ? Number(event.over.id) : null);
  };

  /** Handle drag end: reorder topics via API */
  const handleDragEnd = async (event: DragEndEvent): Promise<void> => {
    // Clear drag states immediately
    setActiveId(null);
    setOverId(null);

    if (!event.over || !courseId) return;

    const activeId = Number(event.active.id);
    const dropId = Number(event.over.id);

    if (activeId === dropId || isNaN(activeId) || isNaN(dropId)) return;

    const oldIndex = topics.findIndex((t) => t.id === activeId);
    const newIndex = topics.findIndex((t) => t.id === dropId);

    if (oldIndex === -1 || newIndex === -1) return;

    const newOrder = arrayMove(topics, oldIndex, newIndex);
    const snapshot = topics;
    setTopics(newOrder);
    setReorderState({ status: "reordering" });

    try {
      const res = await apiFetch<ReorderApiResponse>({
        path: `/tutorpress/v1/topics/reorder`,
        method: "POST",
        data: {
          course_id: Number(courseId),
          topic_orders: newOrder.map((t, idx) => ({ id: t.id, order: idx })),
        },
      });

      if (!res.success) {
        throw new Error(res.message);
      }

      setReorderState({ status: "success" });
    } catch (err) {
      console.error("Error reordering topics:", err);
      setTopics(snapshot);
      setReorderState({
        status: "error",
        error: {
          type: "network",
          message: err instanceof Error ? err.message : "Failed to reorder topics",
        },
      });
    }
  };

  /** Handle drag cancel: clean up states */
  const handleDragCancel = (): void => {
    setActiveId(null);
    setOverId(null);
  };

  if (!courseId) {
    return <div>No course ID found</div>;
  }

  // Render error state
  if (operationState.status === "error") {
    return (
      <div className="tutorpress-curriculum">
        <div className="tutorpress-error" style={{ color: "red", marginBottom: "10px" }}>
          {operationState.error.message}
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
          {reorderState.error.message}
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
          <SortableContext items={topics.map((t) => t.id)} strategy={verticalListSortingStrategy}>
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
