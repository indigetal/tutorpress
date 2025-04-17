/**
 * Course Curriculum Metabox Component
 *
 * Implements the curriculum builder UI using WordPress components.
 */
import React, { useState } from "react";
import { Card, CardHeader, CardBody, Button, Icon, Flex, FlexBlock, ButtonGroup } from "@wordpress/components";
import { moreVertical, plus, edit, copy, trash, dragHandle, chevronDown, chevronRight } from "@wordpress/icons";
import type { Topic, ContentItem } from "../../types";
import { __ } from "@wordpress/i18n";
import { getTopics } from "../../api/topics";

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

  const handleDebugClick = async (testCourseId?: number) => {
    setIsLoading(true);
    try {
      // Use provided test ID or current course ID
      const idToTest = testCourseId || (courseId ? parseInt(courseId, 10) : 0);

      if (!idToTest) {
        console.error("No course ID found");
        return;
      }

      console.log(`Testing topics endpoint for course ID: ${idToTest}`);
      const topics = await getTopics(idToTest);
      console.log("Success! Topics retrieved:", topics);

      // Log some helpful statistics
      console.log("\nSummary:");
      console.log(`Total topics: ${topics.length}`);
      console.log("Topics with content:", topics.filter((t) => t.contents.length > 0).length);
      console.log(
        "Content items by type:",
        topics.reduce(
          (acc, topic) => {
            topic.contents.forEach((item) => {
              acc[item.type] = (acc[item.type] || 0) + 1;
            });
            return acc;
          },
          {} as Record<string, number>
        )
      );
    } catch (error) {
      console.error("Test failed:", error);
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="tutorpress-curriculum">
      <div style={{ textAlign: "left" }}>
        <Button variant="secondary" className="tutorpress-add-topic" icon={plus}>
          Add Topic
        </Button>
        {/* Development Tools: These buttons help test the REST API during development */}
        {process.env.NODE_ENV === "development" && (
          <div style={{ marginTop: "10px" }}>
            <ButtonGroup>
              <Button variant="secondary" onClick={() => handleDebugClick()} disabled={isLoading}>
                {__("Debug: Test Current Course", "tutorpress")}
              </Button>
              <Button variant="secondary" onClick={() => handleDebugClick(999999)} disabled={isLoading}>
                {__("Test: Invalid Course ID", "tutorpress")}
              </Button>
              <Button variant="secondary" onClick={() => handleDebugClick(30)} disabled={isLoading}>
                {__("Test: Regular Post Type", "tutorpress")}
              </Button>
            </ButtonGroup>
          </div>
        )}
      </div>
    </div>
  );
};

export default Curriculum;
