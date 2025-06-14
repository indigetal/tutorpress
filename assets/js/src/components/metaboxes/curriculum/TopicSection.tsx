import React, { type MouseEvent, useState } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock } from "@wordpress/components";
import { moreVertical, plus, dragHandle, chevronDown, chevronRight } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";
import type { ContentItem, DragHandleProps, TopicSectionProps } from "../../../types/curriculum";
import ActionButtons from "./ActionButtons";
import TopicForm from "./TopicForm";
import { useLessons } from "../../../hooks/curriculum/useLessons";
import { useAssignments } from "../../../hooks/curriculum/useAssignments";
import { useQuizzes } from "../../../hooks/curriculum/useQuizzes";
import { QuizModal } from "../../modals/QuizModal";
import { InteractiveQuizModal } from "../../modals/InteractiveQuizModal";

/**
 * Props for content item row
 */
interface ContentItemRowProps {
  item: ContentItem;
  onEdit?: () => void;
  onDuplicate?: () => void;
  onDelete?: () => void;
}

/**
 * Content item icon mapping
 */
const contentTypeIcons = {
  lesson: "text-page",
  tutor_quiz: "star-filled",
  interactive_quiz: "chart-bar",
  assignment: "clipboard",
  tutor_assignments: "clipboard",
  meet_lesson: "video-alt2",
  zoom_lesson: "video-alt3",
} as const;

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
export const TopicSection: React.FC<TopicSectionProps> = ({
  topic,
  courseId,
  dragHandleProps,
  onEdit,
  onEditCancel,
  onEditSave,
  onDuplicate,
  onDelete,
  onToggle,
  isEditing,
}): JSX.Element => {
  // Quiz modal state
  const [isQuizModalOpen, setIsQuizModalOpen] = useState(false);
  const [editingQuizId, setEditingQuizId] = useState<number | undefined>(undefined);

  // Interactive Quiz modal state
  const [isInteractiveQuizModalOpen, setIsInteractiveQuizModalOpen] = useState(false);
  const [editingInteractiveQuizId, setEditingInteractiveQuizId] = useState<number | undefined>(undefined);

  // Initialize lesson operations hook
  const { handleLessonDuplicate, handleLessonDelete, isLessonDuplicating } = useLessons({
    courseId,
    topicId: topic.id,
  });

  // Initialize assignment operations hook
  const { handleAssignmentEdit, handleAssignmentDuplicate, handleAssignmentDelete } = useAssignments({
    courseId,
    topicId: topic.id,
  });

  // Initialize quiz operations hook
  const { handleQuizEdit, handleQuizDuplicate, handleQuizDelete, isQuizDuplicating } = useQuizzes({
    courseId,
    topicId: topic.id,
  });

  // Handle lesson edit - redirect to lesson editor
  const handleLessonEdit = (lessonId: number) => {
    const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
    const url = new URL("post.php", adminUrl);
    url.searchParams.append("post", lessonId.toString());
    url.searchParams.append("action", "edit");
    window.location.href = url.toString();
  };

  // Handle quiz edit - open quiz modal
  const handleQuizEditModal = (quizId: number) => {
    setEditingQuizId(quizId);
    setIsQuizModalOpen(true);
  };

  // Handle quiz modal open for new quiz
  const handleQuizModalOpen = () => {
    setEditingQuizId(undefined);
    setIsQuizModalOpen(true);
  };

  // Handle quiz modal close
  const handleQuizModalClose = () => {
    setIsQuizModalOpen(false);
    setEditingQuizId(undefined);
    // TODO: Refresh curriculum if quiz was created/updated
  };

  // Handle Interactive Quiz modal open
  const handleInteractiveQuizModalOpen = () => {
    setEditingInteractiveQuizId(undefined);
    setIsInteractiveQuizModalOpen(true);
  };

  // Handle Interactive Quiz modal close
  const handleInteractiveQuizModalClose = () => {
    setIsInteractiveQuizModalOpen(false);
    setEditingInteractiveQuizId(undefined);
    // TODO: Refresh curriculum if Interactive Quiz was created/updated
  };

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
          isCreating={false}
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
                onEdit={
                  item.type === "lesson"
                    ? () => handleLessonEdit(item.id)
                    : item.type === "tutor_assignments"
                    ? () => handleAssignmentEdit(item.id)
                    : item.type === "tutor_quiz"
                    ? () => handleQuizEditModal(item.id)
                    : item.type === "interactive_quiz"
                    ? () => {
                        setEditingInteractiveQuizId(item.id);
                        setIsInteractiveQuizModalOpen(true);
                      }
                    : undefined
                }
                onDuplicate={
                  item.type === "lesson"
                    ? () => handleLessonDuplicate(item.id, topic.id)
                    : item.type === "tutor_assignments"
                    ? () => handleAssignmentDuplicate(item.id, topic.id)
                    : item.type === "tutor_quiz"
                    ? () => handleQuizDuplicate(item.id, topic.id)
                    : item.type === "interactive_quiz"
                    ? () => handleQuizDuplicate(item.id, topic.id)
                    : undefined
                }
                onDelete={
                  item.type === "lesson"
                    ? () => handleLessonDelete(item.id)
                    : item.type === "tutor_assignments"
                    ? () => handleAssignmentDelete(item.id)
                    : item.type === "tutor_quiz"
                    ? () => handleQuizDelete(item.id, topic.id)
                    : item.type === "interactive_quiz"
                    ? () => handleQuizDelete(item.id, topic.id)
                    : undefined
                }
              />
            ))}
          </div>
          <Flex className="tutorpress-content-actions" justify="space-between" gap={2}>
            <Flex gap={2} style={{ width: "auto" }}>
              <Button
                variant="secondary"
                isSmall
                icon={plus}
                onClick={() => {
                  // Redirect to new lesson page with topic_id
                  const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
                  const url = new URL("post-new.php", adminUrl);
                  url.searchParams.append("post_type", "lesson");
                  url.searchParams.append("topic_id", topic.id.toString());
                  window.location.href = url.toString();
                }}
              >
                {__("Lesson", "tutorpress")}
              </Button>
              <Button variant="secondary" isSmall icon={plus} onClick={handleQuizModalOpen}>
                {__("Quiz", "tutorpress")}
              </Button>
              <Button variant="secondary" isSmall icon={plus} onClick={handleInteractiveQuizModalOpen}>
                {__("Interactive Quiz", "tutorpress")}
              </Button>
              <Button
                variant="secondary"
                isSmall
                icon={plus}
                onClick={() => {
                  // Redirect to new assignment page with topic_id
                  const adminUrl = window.tutorPressCurriculum?.adminUrl || "";
                  const url = new URL("post-new.php", adminUrl);
                  url.searchParams.append("post_type", "tutor_assignments");
                  url.searchParams.append("topic_id", topic.id.toString());
                  window.location.href = url.toString();
                }}
              >
                {__("Assignment", "tutorpress")}
              </Button>
            </Flex>
            <Button icon={moreVertical} label={__("More options", "tutorpress")} isSmall />
          </Flex>
        </CardBody>
      ) : null}

      {/* Quiz Modal */}
      <QuizModal
        isOpen={isQuizModalOpen}
        onClose={handleQuizModalClose}
        topicId={topic.id}
        courseId={courseId}
        quizId={editingQuizId}
      />

      {/* Interactive Quiz Modal */}
      <InteractiveQuizModal
        isOpen={isInteractiveQuizModalOpen}
        onClose={handleInteractiveQuizModalClose}
        topicId={topic.id}
        courseId={courseId}
        quizId={editingInteractiveQuizId}
      />
    </Card>
  );
};

export default TopicSection;
