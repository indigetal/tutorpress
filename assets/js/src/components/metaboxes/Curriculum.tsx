/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock, ButtonGroup } from "@wordpress/components";
import { moreVertical, plus, edit, copy, trash, dragHandle, chevronDown, chevronRight } from "@wordpress/icons";
import type { Topic, ContentItem, ApiResponse } from "../../types/api";
import { __ } from "@wordpress/i18n";
import { getTopics } from "../../api/topics";
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
  // Get the current course ID from the URL
  const courseId = new URLSearchParams(window.location.search).get("post");

  // We'll use this structure for the reorder endpoint testing
  const testReorderTopics = async () => {
    // TODO: Implement reorder testing when endpoint is ready
  };

  return (
    <div className="tutorpress-curriculum">
      <div style={{ textAlign: "left" }}>
        <Button variant="secondary" className="tutorpress-add-topic" icon={plus}>
          Add Topic
        </Button>
        {process.env.NODE_ENV === "development" && (
          <div style={{ marginTop: "10px" }}>
            <ButtonGroup>
              <Button variant="secondary" onClick={testReorderTopics} disabled={isLoading}>
                {__("Test: Reorder Topics", "tutorpress")}
              </Button>
            </ButtonGroup>
          </div>
        )}
      </div>
    </div>
  );
};

export default Curriculum;
