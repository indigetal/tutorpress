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

/**
 * Action buttons for items and topics
 */
const ActionButtons: React.FC = (): JSX.Element => (
  <Flex gap={1} justify="flex-end" style={{ width: "auto" }}>
    <Button icon={edit} label="Edit" isSmall />
    <Button icon={copy} label="Duplicate" isSmall />
    <Button icon={trash} label="Delete" isSmall />
  </Flex>
);

/**
 * Renders a single content item
 */
const ContentItemRow: React.FC<{ item: ContentItem }> = ({ item }): JSX.Element => (
  <Flex className="tutorpress-content-item" align="center" gap={2}>
    <Button icon={dragHandle} label="Drag to reorder" isSmall />
    <Icon icon={contentTypeIcons[item.type]} />
    <FlexBlock style={{ textAlign: "left" }}>{item.title}</FlexBlock>
    <ActionButtons />
  </Flex>
);

/**
 * Renders a topic section with its content items and accepts drag handle props
 */
const TopicSection: React.FC<TopicSectionProps> = ({ topic, dragHandleProps }): JSX.Element => (
  <Card className="tutorpress-topic">
    <CardHeader>
      <Flex align="center" gap={2}>
        <Button icon={dragHandle} label="Drag to reorder" isSmall {...dragHandleProps} />
        <FlexBlock style={{ textAlign: "left" }}>{topic.title}</FlexBlock>
        <ActionButtons />
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
          <ContentItemRow key={item.id} item={item} />
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
const SortableTopic: React.FC<SortableTopicProps> = ({ topic }): JSX.Element => {
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
      <TopicSection topic={topic} dragHandleProps={{ ...attributes, ...listeners, ref: setActivatorNodeRef }} />
    </div>
  );
};

/**
 * Main Curriculum component
 */
const Curriculum: React.FC = (): JSX.Element => {
  const [isLoading, setIsLoading] = useState(false);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [error, setError] = useState<string | null>(null);
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

      setIsLoading(true);
      setError(null);
      try {
        const response = await apiFetch<{ success: boolean; message: string; data: Topic[] }>({
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
          method: "GET",
        });

        if (response.success) {
          setTopics(response.data);
        } else {
          throw new Error(response.message);
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : "Failed to fetch topics");
        console.error("Error fetching topics:", err);
      } finally {
        setIsLoading(false);
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

    try {
      const res = await apiFetch<{ success: boolean; message: string; data: any }>({
        path: `/tutorpress/v1/topics/reorder`,
        method: "POST",
        data: {
          course_id: Number(courseId),
          topic_orders: newOrder.map((t, idx) => ({ id: t.id, order: idx })),
        },
      });

      if (!res.success) throw new Error(res.message);
    } catch (err) {
      console.error("Error reordering topics:", err);
      setError(err instanceof Error ? err.message : "Failed to reorder topics");
      setTopics(snapshot);
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

  return (
    <div className="tutorpress-curriculum">
      {error && (
        <div className="tutorpress-error" style={{ color: "red", marginBottom: "10px" }}>
          {error}
        </div>
      )}

      <div style={{ textAlign: "left" }}>
        <Button variant="secondary" className="tutorpress-add-topic" icon={plus} disabled={isLoading}>
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
                  className={`tutorpress-topic-wrapper ${activeId && overId === topic.id ? "show-indicator" : ""} ${
                    topic.id === topics[topics.length - 1].id ? "last-topic" : ""
                  }`}
                >
                  <SortableTopic topic={topic} />
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
