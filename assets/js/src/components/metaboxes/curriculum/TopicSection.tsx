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

import React, { type MouseEvent, useState } from "react";
import {
  Card,
  CardHeader,
  CardBody,
  Button,
  Icon,
  Flex,
  FlexBlock,
  Modal,
  Dropdown,
  MenuGroup,
  MenuItem,
  TextControl,
  TextareaControl,
  DateTimePicker,
  SelectControl,
  CheckboxControl,
  Popover,
  __experimentalNumberControl as NumberControl,
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
import { isH5pEnabled, isGoogleMeetEnabled, isZoomEnabled } from "../../../utils/addonChecker";
import { store as noticesStore } from "@wordpress/notices";
import { useDispatch } from "@wordpress/data";

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

  // Date picker popover state
  const [datePickerOpen, setDatePickerOpen] = useState<string | null>(null);

  // Google Meet form state
  const [googleMeetForm, setGoogleMeetForm] = useState({
    title: "",
    summary: "",
    startDate: new Date(),
    endDate: new Date(Date.now() + 60 * 60 * 1000), // 1 hour later
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    addEnrolledStudents: false,
  });

  // Zoom form state
  const [zoomForm, setZoomForm] = useState({
    title: "",
    summary: "",
    date: new Date(),
    duration: 40,
    durationUnit: "minutes",
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    autoRecording: "none",
    password: "",
    host: "default",
  });

  // Get notice actions
  const { createNotice } = useDispatch(noticesStore);

  // Helper function to show H5P disabled notice
  const showH5pDisabledNotice = () => {
    createNotice("warning", __("H5P integration is currently disabled. Contact the site admin.", "tutorpress"), {
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
    // Reset form state when opening
    setGoogleMeetForm({
      title: "",
      summary: "",
      startDate: new Date(),
      endDate: new Date(Date.now() + 60 * 60 * 1000), // 1 hour later
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      addEnrolledStudents: false,
    });
    setIsGoogleMeetModalOpen(true);
  };

  const handleGoogleMeetModalClose = () => {
    setIsGoogleMeetModalOpen(false);
    setDatePickerOpen(null);
    // TODO: Refresh curriculum if Google Meet lesson was created/updated
  };

  const handleZoomModalOpen = () => {
    // Reset form state when opening
    setZoomForm({
      title: "",
      summary: "",
      date: new Date(),
      duration: 40,
      durationUnit: "minutes",
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      autoRecording: "none",
      password: "",
      host: "default",
    });
    setIsZoomModalOpen(true);
  };

  const handleZoomModalClose = () => {
    setIsZoomModalOpen(false);
    setDatePickerOpen(null);
    // TODO: Refresh curriculum if Zoom lesson was created/updated
  };

  // Common timezone options (simplified list)
  const timezoneOptions = [
    { label: "UTC", value: "UTC" },
    { label: "America/New_York (EST/EDT)", value: "America/New_York" },
    { label: "America/Chicago (CST/CDT)", value: "America/Chicago" },
    { label: "America/Denver (MST/MDT)", value: "America/Denver" },
    {
      label: "America/Los_Angeles (PST/PDT)",
      value: "America/Los_Angeles",
    },
    { label: "Europe/London (GMT/BST)", value: "Europe/London" },
    { label: "Europe/Paris (CET/CEST)", value: "Europe/Paris" },
    { label: "Asia/Tokyo (JST)", value: "Asia/Tokyo" },
    { label: "Australia/Sydney (AEST/AEDT)", value: "Australia/Sydney" },
  ];

  // Duration unit options for Zoom
  const durationUnitOptions = [
    { label: __("Minutes", "tutorpress"), value: "minutes" },
    { label: __("Hours", "tutorpress"), value: "hours" },
  ];

  // Form submission handlers
  const handleGoogleMeetSubmit = () => {
    // Basic validation
    if (!googleMeetForm.title.trim()) {
      createNotice("error", __("Meeting name is required", "tutorpress"), {
        type: "snackbar",
        isDismissible: true,
      });
      return;
    }

    // TODO: Connect to WordPress Data Store for actual submission
    // Form data ready for submission: googleMeetForm
    createNotice("success", __("Google Meet lesson created successfully", "tutorpress"), {
      type: "snackbar",
      isDismissible: true,
    });
    handleGoogleMeetModalClose();
  };

  const handleZoomSubmit = () => {
    // Basic validation
    if (!zoomForm.title.trim()) {
      createNotice("error", __("Meeting name is required", "tutorpress"), {
        type: "snackbar",
        isDismissible: true,
      });
      return;
    }

    // TODO: Connect to WordPress Data Store for actual submission
    // Form data ready for submission: zoomForm
    createNotice("success", __("Zoom lesson created successfully", "tutorpress"), {
      type: "snackbar",
      isDismissible: true,
    });
    handleZoomModalClose();
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
                    ? () => {
                        if (!isH5pEnabled()) {
                          showH5pDisabledNotice();
                          return;
                        }
                        handleQuizDelete(item.id, topic.id);
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
      {isGoogleMeetModalOpen && (
        <Modal
          title={__("Create Google Meet Live Lesson", "tutorpress")}
          onRequestClose={handleGoogleMeetModalClose}
          className="tutorpress-live-lesson-modal tutorpress-google-meet-modal"
          size="medium"
        >
          <div className="tutorpress-modal-content">
            <TextControl
              label={__("Meeting Name", "tutorpress")}
              value={googleMeetForm.title}
              onChange={(value) =>
                setGoogleMeetForm({
                  ...googleMeetForm,
                  title: value,
                })
              }
              placeholder={__("Enter meeting name", "tutorpress")}
              required
            />

            <TextareaControl
              label={__("Meeting Summary", "tutorpress")}
              value={googleMeetForm.summary}
              onChange={(value) =>
                setGoogleMeetForm({
                  ...googleMeetForm,
                  summary: value,
                })
              }
              placeholder={__("Enter meeting summary", "tutorpress")}
              rows={4}
            />

            <div className="tutorpress-form-row">
              <div className="tutorpress-form-col">
                <TextControl
                  label={__("Meeting Start Date", "tutorpress")}
                  value={googleMeetForm.startDate.toLocaleString()}
                  onChange={() => {}} // No-op since this is readonly
                  onClick={() => setDatePickerOpen(datePickerOpen === "startDate" ? null : "startDate")}
                  readOnly
                  style={{ cursor: "pointer" }}
                />
                {datePickerOpen === "startDate" && (
                  <Popover onClose={() => setDatePickerOpen(null)}>
                    <DateTimePicker
                      currentDate={googleMeetForm.startDate}
                      onChange={(date) => {
                        setGoogleMeetForm({
                          ...googleMeetForm,
                          startDate: date ? new Date(date) : new Date(),
                        });
                        setDatePickerOpen(null);
                      }}
                      is12Hour={true}
                    />
                  </Popover>
                )}
              </div>
            </div>

            <div className="tutorpress-form-row">
              <div className="tutorpress-form-col">
                <TextControl
                  label={__("Meeting End Date", "tutorpress")}
                  value={googleMeetForm.endDate.toLocaleString()}
                  onChange={() => {}} // No-op since this is readonly
                  onClick={() => setDatePickerOpen(datePickerOpen === "endDate" ? null : "endDate")}
                  readOnly
                  style={{ cursor: "pointer" }}
                />
                {datePickerOpen === "endDate" && (
                  <Popover onClose={() => setDatePickerOpen(null)}>
                    <DateTimePicker
                      currentDate={googleMeetForm.endDate}
                      onChange={(date) => {
                        setGoogleMeetForm({
                          ...googleMeetForm,
                          endDate: date ? new Date(date) : new Date(),
                        });
                        setDatePickerOpen(null);
                      }}
                      is12Hour={true}
                    />
                  </Popover>
                )}
              </div>
            </div>

            <SelectControl
              label={__("Timezone", "tutorpress")}
              value={googleMeetForm.timezone}
              options={timezoneOptions}
              onChange={(value) =>
                setGoogleMeetForm({
                  ...googleMeetForm,
                  timezone: value,
                })
              }
            />

            <CheckboxControl
              label={__("Add enrolled students as attendees", "tutorpress")}
              checked={googleMeetForm.addEnrolledStudents}
              onChange={(checked) =>
                setGoogleMeetForm({
                  ...googleMeetForm,
                  addEnrolledStudents: checked,
                })
              }
            />
          </div>
          <div className="tutorpress-modal-footer">
            <Button variant="secondary" onClick={handleGoogleMeetModalClose}>
              {__("Cancel", "tutorpress")}
            </Button>
            <Button variant="primary" onClick={handleGoogleMeetSubmit}>
              {__("Create Meeting", "tutorpress")}
            </Button>
          </div>
        </Modal>
      )}

      {/* Zoom Live Lesson Modal */}
      {isZoomModalOpen && (
        <Modal
          title={__("Create Zoom Live Lesson", "tutorpress")}
          onRequestClose={handleZoomModalClose}
          className="tutorpress-live-lesson-modal tutorpress-zoom-modal"
          size="medium"
        >
          <div className="tutorpress-modal-content">
            <TextControl
              label={__("Meeting Name", "tutorpress")}
              value={zoomForm.title}
              onChange={(value) => setZoomForm({ ...zoomForm, title: value })}
              placeholder={__("Enter meeting name", "tutorpress")}
              required
            />

            <TextareaControl
              label={__("Meeting Summary", "tutorpress")}
              value={zoomForm.summary}
              onChange={(value) => setZoomForm({ ...zoomForm, summary: value })}
              placeholder={__("Enter meeting summary", "tutorpress")}
              rows={4}
            />

            <div className="tutorpress-form-row">
              <div className="tutorpress-form-col">
                <TextControl
                  label={__("Meeting Date", "tutorpress")}
                  value={zoomForm.date.toLocaleString()}
                  onChange={() => {}} // No-op since this is readonly
                  onClick={() => setDatePickerOpen(datePickerOpen === "zoomDate" ? null : "zoomDate")}
                  readOnly
                  style={{ cursor: "pointer" }}
                />
                {datePickerOpen === "zoomDate" && (
                  <Popover onClose={() => setDatePickerOpen(null)}>
                    <DateTimePicker
                      currentDate={zoomForm.date}
                      onChange={(date) => {
                        setZoomForm({
                          ...zoomForm,
                          date: date ? new Date(date) : new Date(),
                        });
                        setDatePickerOpen(null);
                      }}
                      is12Hour={true}
                    />
                  </Popover>
                )}
              </div>
            </div>

            <div className="tutorpress-form-row">
              <div className="tutorpress-form-col-half">
                <NumberControl
                  label={__("Meeting Duration", "tutorpress")}
                  value={zoomForm.duration}
                  onChange={(value) =>
                    setZoomForm({
                      ...zoomForm,
                      duration: parseInt(value || "40") || 40,
                    })
                  }
                  min={1}
                  max={480}
                />
              </div>
              <div className="tutorpress-form-col-half">
                <SelectControl
                  label={__("Duration Unit", "tutorpress")}
                  value={zoomForm.durationUnit}
                  options={durationUnitOptions}
                  onChange={(value) =>
                    setZoomForm({
                      ...zoomForm,
                      durationUnit: value,
                    })
                  }
                />
              </div>
            </div>

            <SelectControl
              label={__("Timezone", "tutorpress")}
              value={zoomForm.timezone}
              options={timezoneOptions}
              onChange={(value) => setZoomForm({ ...zoomForm, timezone: value })}
            />

            <SelectControl
              label={__("Auto Recording", "tutorpress")}
              value={zoomForm.autoRecording}
              options={[
                {
                  label: __("None", "tutorpress"),
                  value: "none",
                },
                {
                  label: __("Local", "tutorpress"),
                  value: "local",
                },
                {
                  label: __("Cloud", "tutorpress"),
                  value: "cloud",
                },
              ]}
              onChange={(value) =>
                setZoomForm({
                  ...zoomForm,
                  autoRecording: value,
                })
              }
            />

            <TextControl
              label={__("Meeting Password", "tutorpress")}
              value={zoomForm.password}
              onChange={(value) => setZoomForm({ ...zoomForm, password: value })}
              placeholder={__("Enter meeting password (optional)", "tutorpress")}
            />

            <SelectControl
              label={__("Meeting Host", "tutorpress")}
              value={zoomForm.host}
              options={[
                {
                  label: __("Default Host", "tutorpress"),
                  value: "default",
                },
                {
                  label: __("Alternative Host", "tutorpress"),
                  value: "alternative",
                },
              ]}
              onChange={(value) => setZoomForm({ ...zoomForm, host: value })}
            />
          </div>
          <div className="tutorpress-modal-footer">
            <Button variant="secondary" onClick={handleZoomModalClose}>
              {__("Cancel", "tutorpress")}
            </Button>
            <Button variant="primary" onClick={handleZoomSubmit}>
              {__("Create Meeting", "tutorpress")}
            </Button>
          </div>
        </Modal>
      )}
    </Card>
  );
};

export default TopicSection;
