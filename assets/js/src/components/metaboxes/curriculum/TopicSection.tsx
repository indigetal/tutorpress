/**
 * TopicSection.tsx
 *
 * Main component for displaying and managing curriculum topics in the TutorPress Course Builder.
 * This component handles the entire topic section including:
 * - Topic header with drag/drop functionality and action buttons
 * - Content items (lessons, quizzes, assignments, interactive quizzes)
 * - Live Lessons integration (Google Meet and Zoom) when addons are enabled
 * - Modal forms for creating new content with WordPress components
 * - Responsive button layout with overflow menu for smaller screens
 *
 * Key Features:
 * - Drag and drop reordering via @dnd-kit
 * - Integration with WordPress Data Store for all operations
 * - Conditional rendering based on addon availability (H5P, Google Meet, Zoom)
 * - Responsive design with CSS Grid and Flexbox
 * - WordPress admin styling consistency
 * - Form validation and user feedback via WordPress notices
 *
 * Known Linter Issues (Non-breaking):
 * - JSDoc parameter documentation warnings - these are documentation-only issues
 * - TypeScript type strictness on SelectControl values - runtime behavior is correct
 * - Experimental API usage (__experimentalNumberControl) - WordPress component is stable in practice
 * - Variable shadowing in nested scopes - isolated and intentional for clarity
 *
 * These linter warnings do not affect functionality and are safe to ignore.
 *
 * @package TutorPress
 * @subpackage Curriculum/Components
 * @since 1.5.2
 */

import React, { type MouseEvent, useState, useEffect } from "react";
import {
  Card,
  CardHeader,
  CardBody,
  Button,
  Icon,
  Flex,
  FlexBlock,
  Dropdown,
  MenuGroup,
  MenuItem,
} from "@wordpress/components";
import { moreVertical, plus, dragHandle, chevronDown, chevronRight } from "@wordpress/icons";
import { __ } from "@wordpress/i18n";
import type { ContentItem, DragHandleProps, TopicSectionProps } from "../../../types/curriculum";
import ActionButtons from "./ActionButtons";
import TopicForm from "./TopicForm";
import { useLessons } from "../../../hooks/curriculum/useLessons";
import { useAssignments } from "../../../hooks/curriculum/useAssignments";
import { useQuizzes } from "../../../hooks/curriculum/useQuizzes";
import { QuizModal } from "../../modals/QuizModal";
import { LiveLessonModal } from "../../modals/live-lessons";
import { isH5pEnabled, isGoogleMeetEnabled, isZoomEnabled } from "../../../utils/addonChecker";
import { store as noticesStore } from "@wordpress/notices";
import { useDispatch } from "@wordpress/data";
const CURRICULUM_STORE = "tutorpress/curriculum";

// Conditionally import Interactive Quiz components only when H5P is enabled
let InteractiveQuizModal: React.ComponentType<any> | null = null;
if (isH5pEnabled()) {
  // Use dynamic import to prevent loading when H5P is not available
  const { InteractiveQuizModal: ImportedInteractiveQuizModal } = require("../../modals/InteractiveQuizModal");
  InteractiveQuizModal = ImportedInteractiveQuizModal;
}

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
 * @param {ContentItemRowProps} props - Component props
 * @param {ContentItem} props.item - The content item to display
 * @param {Function} [props.onEdit] - Optional edit handler
 * @param {Function} [props.onDuplicate] - Optional duplicate handler
 * @param {Function} [props.onDelete] - Optional delete handler
 * @return {JSX.Element} Content item row component
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

  // Interactive Quiz modal state - only when H5P is enabled
  const [isInteractiveQuizModalOpen, setIsInteractiveQuizModalOpen] = useState(false);
  const [editingInteractiveQuizId, setEditingInteractiveQuizId] = useState<number | undefined>(undefined);

  // Live Lessons modal state
  const [isGoogleMeetModalOpen, setIsGoogleMeetModalOpen] = useState(false);
  const [isZoomModalOpen, setIsZoomModalOpen] = useState(false);
  const [editingGoogleMeetId, setEditingGoogleMeetId] = useState<number | undefined>(undefined);
  const [editingZoomId, setEditingZoomId] = useState<number | undefined>(undefined);

  // Get notice actions
  const { createNotice } = useDispatch(noticesStore);

  // Get curriculum store actions for live lessons
  const { deleteLiveLesson, duplicateLiveLesson } = useDispatch(CURRICULUM_STORE);

  // Helper function to show H5p disabled notice
  const showH5pDisabledNotice = () => {
    createNotice("warning", __("H5P integration is currently disabled. Contact the site admin.", "tutorpress"), {
      isDismissible: true,
      type: "snackbar",
    });
  };

  // Helper function to show Google Meet disabled notice
  const showGoogleMeetDisabledNotice = () => {
    createNotice(
      "warning",
      __("Google Meet integration is currently disabled. Contact the site admin.", "tutorpress"),
      {
        isDismissible: true,
        type: "snackbar",
      }
    );
  };

  // Helper function to show Zoom disabled notice
  const showZoomDisabledNotice = () => {
    createNotice("warning", __("Zoom integration is currently disabled. Contact the site admin.", "tutorpress"), {
      isDismissible: true,
      type: "snackbar",
    });
  };

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

  // Handle Interactive Quiz modal open - only when H5P is enabled
  const handleInteractiveQuizModalOpen = () => {
    if (!isH5pEnabled()) return;
    setEditingInteractiveQuizId(undefined);
    setIsInteractiveQuizModalOpen(true);
  };

  // Handle Interactive Quiz modal close - only when H5P is enabled
  const handleInteractiveQuizModalClose = () => {
    if (!isH5pEnabled()) return;
    setIsInteractiveQuizModalOpen(false);
    setEditingInteractiveQuizId(undefined);
    // TODO: Refresh curriculum if Interactive Quiz was created/updated
  };

  // Live Lessons modal handlers
  const handleGoogleMeetModalOpen = () => {
    if (!isGoogleMeetEnabled()) {
      showGoogleMeetDisabledNotice();
      return;
    }
    setIsGoogleMeetModalOpen(true);
  };

  const handleGoogleMeetModalClose = () => {
    setIsGoogleMeetModalOpen(false);
    setEditingGoogleMeetId(undefined);
  };

  const handleZoomModalOpen = () => {
    if (!isZoomEnabled()) {
      showZoomDisabledNotice();
      return;
    }
    setIsZoomModalOpen(true);
  };

  const handleZoomModalClose = () => {
    setIsZoomModalOpen(false);
    setEditingZoomId(undefined);
  };

  // Live lesson delete handlers
  const handleLiveLessonDelete = (liveLessonId: number) => {
    if (window.confirm(__("Are you sure you want to delete this live lesson?", "tutorpress"))) {
      deleteLiveLesson(liveLessonId);
    }
  };

  // Live lesson duplicate handler (Google Meet only)
  const handleGoogleMeetDuplicate = (liveLessonId: number, topicId: number) => {
    if (!isGoogleMeetEnabled()) {
      showGoogleMeetDisabledNotice();
      return;
    }
    duplicateLiveLesson(liveLessonId, topicId, courseId || 0);
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
          initialData={{
            title: topic.title,
            summary: topic.content || "",
          }}
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
                              if (!isH5pEnabled()) {
                                showH5pDisabledNotice();
                                return;
                              }
                              setEditingInteractiveQuizId(item.id);
                              setIsInteractiveQuizModalOpen(true);
                            }
                          : item.type === "meet_lesson"
                            ? () => {
                                if (!isGoogleMeetEnabled()) {
                                  showGoogleMeetDisabledNotice();
                                  return;
                                }
                                setEditingGoogleMeetId(item.id);
                                setIsGoogleMeetModalOpen(true);
                              }
                            : item.type === "zoom_lesson"
                              ? () => {
                                  if (!isZoomEnabled()) {
                                    showZoomDisabledNotice();
                                    return;
                                  }
                                  setEditingZoomId(item.id);
                                  setIsZoomModalOpen(true);
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
                          ? () => {
                              if (!isH5pEnabled()) {
                                showH5pDisabledNotice();
                                return;
                              }
                              handleQuizDuplicate(item.id, topic.id);
                            }
                          : item.type === "meet_lesson"
                            ? () => handleGoogleMeetDuplicate(item.id, topic.id)
                            : undefined // Zoom lessons don't support duplication
                }
                onDelete={
                  item.type === "lesson"
                    ? () => handleLessonDelete(item.id)
                    : item.type === "tutor_assignments"
                      ? () => handleAssignmentDelete(item.id)
                      : item.type === "tutor_quiz"
                        ? () => handleQuizDelete(item.id, topic.id)
                        : item.type === "interactive_quiz"
                          ? () => {
                              if (!isH5pEnabled()) {
                                showH5pDisabledNotice();
                                return;
                              }
                              handleQuizDelete(item.id, topic.id);
                            }
                          : item.type === "meet_lesson"
                            ? () => {
                                if (!isGoogleMeetEnabled()) {
                                  showGoogleMeetDisabledNotice();
                                  return;
                                }
                                handleLiveLessonDelete(item.id);
                              }
                            : item.type === "zoom_lesson"
                              ? () => {
                                  if (!isZoomEnabled()) {
                                    showZoomDisabledNotice();
                                    return;
                                  }
                                  handleLiveLessonDelete(item.id);
                                }
                              : undefined
                }
              />
            ))}
          </div>
          <Flex className="tutorpress-content-actions" justify="space-between" gap={2}>
            <Flex gap={2} style={{ width: "auto" }} className="tutorpress-content-buttons">
              {/* Core content buttons - always visible */}
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
                className="tutorpress-btn-core"
              >
                {__("Lesson", "tutorpress")}
              </Button>
              <Button
                variant="secondary"
                isSmall
                icon={plus}
                onClick={handleQuizModalOpen}
                className="tutorpress-btn-core"
              >
                {__("Quiz", "tutorpress")}
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
                className="tutorpress-btn-core"
              >
                {__("Assignment", "tutorpress")}
              </Button>

              {/* Extended content buttons - responsive visibility */}
              {isH5pEnabled() && (
                <Button
                  variant="secondary"
                  isSmall
                  icon={plus}
                  onClick={handleInteractiveQuizModalOpen}
                  className="tutorpress-btn-extended"
                >
                  {__("Interactive Quiz", "tutorpress")}
                </Button>
              )}
              {isGoogleMeetEnabled() && (
                <Button
                  variant="secondary"
                  isSmall
                  icon={plus}
                  onClick={handleGoogleMeetModalOpen}
                  className="tutorpress-btn-extended"
                >
                  {__("Google Meet", "tutorpress")}
                </Button>
              )}
              {isZoomEnabled() && (
                <Button
                  variant="secondary"
                  isSmall
                  icon={plus}
                  onClick={handleZoomModalOpen}
                  className="tutorpress-btn-extended"
                >
                  {__("Zoom", "tutorpress")}
                </Button>
              )}
            </Flex>

            {/* Overflow menu for smaller screens */}
            <Dropdown
              className="tutorpress-content-overflow"
              contentClassName="tutorpress-content-overflow-content"
              popoverProps={{
                placement: "bottom-end",
                offset: 4,
              }}
              renderToggle={({ isOpen, onToggle }) => (
                <Button
                  icon={moreVertical}
                  label={__("More content options", "tutorpress")}
                  isSmall
                  onClick={onToggle}
                  aria-expanded={isOpen}
                  className="tutorpress-content-overflow-toggle"
                />
              )}
              renderContent={({ onClose }) => (
                <MenuGroup label={__("Add Content", "tutorpress")}>
                  {/* H5P option in overflow */}
                  {isH5pEnabled() && (
                    <MenuItem
                      icon={plus}
                      onClick={() => {
                        handleInteractiveQuizModalOpen();
                        onClose();
                      }}
                      className="tutorpress-overflow-h5p"
                    >
                      {__("Interactive Quiz", "tutorpress")}
                    </MenuItem>
                  )}
                  {/* Live Lessons options in overflow */}
                  {isGoogleMeetEnabled() && (
                    <MenuItem
                      icon={plus}
                      onClick={() => {
                        handleGoogleMeetModalOpen();
                        onClose();
                      }}
                      className="tutorpress-overflow-google-meet"
                    >
                      {__("Google Meet", "tutorpress")}
                    </MenuItem>
                  )}
                  {isZoomEnabled() && (
                    <MenuItem
                      icon={plus}
                      onClick={() => {
                        handleZoomModalOpen();
                        onClose();
                      }}
                      className="tutorpress-overflow-zoom"
                    >
                      {__("Zoom", "tutorpress")}
                    </MenuItem>
                  )}
                </MenuGroup>
              )}
            />
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
      {InteractiveQuizModal && (
        <InteractiveQuizModal
          isOpen={isInteractiveQuizModalOpen}
          onClose={handleInteractiveQuizModalClose}
          topicId={topic.id}
          courseId={courseId}
          quizId={editingInteractiveQuizId}
        />
      )}

      {/* Google Meet Live Lesson Modal */}
      <LiveLessonModal
        isOpen={isGoogleMeetModalOpen}
        onClose={handleGoogleMeetModalClose}
        topicId={topic.id}
        courseId={courseId}
        lessonType="google_meet"
        lessonId={editingGoogleMeetId}
      />

      {/* Zoom Live Lesson Modal */}
      <LiveLessonModal
        isOpen={isZoomModalOpen}
        onClose={handleZoomModalClose}
        topicId={topic.id}
        courseId={courseId}
        lessonType="zoom"
        lessonId={editingZoomId}
      />
    </Card>
  );
};

export default TopicSection;
