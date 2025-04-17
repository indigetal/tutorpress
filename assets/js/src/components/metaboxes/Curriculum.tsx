/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock, ButtonGroup } from "@wordpress/components";
import { moreVertical, plus, edit, copy, trash, dragHandle, chevronDown, chevronRight } from "@wordpress/icons";
import type { Topic, ContentItem } from "../../types/courses";
import type { TutorResponse } from "../../types/api";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

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
const ActionButtons: React.FC = () => (
  <Flex gap={1} justify="flex-end" style={{ width: "auto" }}>
    <Button icon={edit} label="Edit" isSmall />
    <Button icon={copy} label="Duplicate" isSmall />
    <Button icon={trash} label="Delete" isSmall />
  </Flex>
);

/**
 * Renders a single content item
 */
const ContentItemRow: React.FC<{ item: ContentItem }> = ({ item }) => (
  <Flex className="tutorpress-content-item" align="center" gap={2}>
    <Button icon={dragHandle} label="Drag to reorder" isSmall />
    <Icon icon={contentTypeIcons[item.type]} />
    <FlexBlock style={{ textAlign: "left" }}>{item.title}</FlexBlock>
    <ActionButtons />
  </Flex>
);

/**
 * Renders a topic section with its content items
 */
const TopicSection: React.FC<{ topic: Topic }> = ({ topic }) => (
  <Card className="tutorpress-topic">
    <CardHeader>
      <Flex align="center" gap={2}>
        <Button icon={dragHandle} label="Drag to reorder" isSmall />
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
 * Main Curriculum component
 */
const Curriculum: React.FC = () => {
  const [isLoading, setIsLoading] = useState(false);
  const [topics, setTopics] = useState<Topic[]>([]);
  const [error, setError] = useState<string | null>(null);

  // Get the current course ID from the URL
  const courseId = new URLSearchParams(window.location.search).get("post");

  // Fetch topics when component mounts
  React.useEffect(() => {
    const fetchTopics = async () => {
      if (!courseId) return;

      setIsLoading(true);
      setError(null);
      try {
        const response = await apiFetch<TutorResponse<Topic[]>>({
          path: `/tutorpress/v1/topics?course_id=${courseId}`,
          method: "GET",
        });

        if (response.status_code === 200) {
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

        {topics.map((topic) => (
          <TopicSection key={topic.id} topic={topic} />
        ))}
      </div>
    </div>
  );
};

export default Curriculum;
