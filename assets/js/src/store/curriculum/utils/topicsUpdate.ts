import { Topic, ContentItem } from "../../../types/curriculum";

/**
 * Content type mapping for consistent type handling
 */
export const CONTENT_TYPE_MAP = {
  lesson: "lesson",
  assignment: "tutor_assignments", // Server uses this, not "assignment"
  quiz: "tutor_quiz",
  interactive_quiz: "interactive_quiz",
  meet_lesson: "meet_lesson",
  zoom_lesson: "zoom_lesson",
} as const;

/**
 * Extended ContentItem with additional properties used in practice
 */
export interface ExtendedContentItem extends ContentItem {
  menu_order?: number;
}

/**
 * Creates a ContentItem from response data with required properties
 */
export const createContentItem = (
  id: number,
  title: string,
  type: ContentItem["type"],
  topicId: number,
  order?: number,
  status: string = "publish"
): ExtendedContentItem => ({
  id,
  title,
  type,
  topic_id: topicId,
  order: order ?? 1,
  menu_order: order ?? 1,
  status,
});

/**
 * Removes content from topics by ID and type
 */
export const removeContentFromTopics = (
  currentTopics: Topic[],
  contentId: number,
  contentType: ContentItem["type"]
): Topic[] => {
  return currentTopics.map((topic) => ({
    ...topic,
    contents: topic.contents?.filter((content) => !(content.id === contentId && content.type === contentType)) || [],
  }));
};

/**
 * Adds content to a specific topic
 */
export const addContentToTopic = (
  currentTopics: Topic[],
  topicId: number,
  contentItem: ExtendedContentItem
): Topic[] => {
  return currentTopics.map((topic) => {
    if (topic.id === topicId) {
      return {
        ...topic,
        contents: [...(topic.contents || []), contentItem],
      };
    }
    return topic;
  });
};

/**
 * Updates existing content in topics by finding and replacing it
 */
export const updateContentInTopics = (
  currentTopics: Topic[],
  contentId: number,
  updatedProperties: Partial<ExtendedContentItem>
): Topic[] => {
  return currentTopics.map((topic) => {
    const contentIndex = topic.contents?.findIndex((item) => item.id === contentId) ?? -1;

    if (contentIndex >= 0) {
      const updatedContents = [...(topic.contents || [])];
      updatedContents[contentIndex] = {
        ...updatedContents[contentIndex],
        ...updatedProperties,
      };

      return {
        ...topic,
        contents: updatedContents,
      };
    }
    return topic;
  });
};

/**
 * Creates a SET_TOPICS payload function for removing content
 */
export const createRemoveContentPayload = (contentId: number, contentType: ContentItem["type"]) => {
  return (currentTopics: Topic[]) => removeContentFromTopics(currentTopics, contentId, contentType);
};

/**
 * Creates a SET_TOPICS payload function for removing content with multiple possible types
 */
export const createRemoveMultiTypeContentPayload = (contentId: number, contentTypes: ContentItem["type"][]) => {
  return (currentTopics: Topic[]) => {
    return currentTopics.map((topic) => ({
      ...topic,
      contents:
        topic.contents?.filter((content) => !(content.id === contentId && contentTypes.includes(content.type))) || [],
    }));
  };
};

/**
 * Creates a SET_TOPICS payload function for adding content
 */
export const createAddContentPayload = (topicId: number, contentItem: ExtendedContentItem) => {
  return (currentTopics: Topic[]) => addContentToTopic(currentTopics, topicId, contentItem);
};

/**
 * Creates a SET_TOPICS payload function for updating content
 */
export const createUpdateContentPayload = (contentId: number, updatedProperties: Partial<ExtendedContentItem>) => {
  return (currentTopics: Topic[]) => updateContentInTopics(currentTopics, contentId, updatedProperties);
};

/**
 * Helper to create a duplicated content item with proper ordering
 */
export const createDuplicatedContentItem = (
  id: number,
  title: string,
  type: ContentItem["type"],
  topicId: number,
  currentTopics: Topic[]
): ExtendedContentItem => {
  const targetTopic = currentTopics.find((topic) => topic.id === topicId);
  const nextOrder = (targetTopic?.contents?.length || 0) + 1;

  return createContentItem(id, title, type, topicId, nextOrder);
};

/**
 * Creates a SET_TOPICS payload function for duplicating content
 */
export const createDuplicateContentPayload = (
  topicId: number,
  id: number,
  title: string,
  type: ContentItem["type"]
) => {
  return (currentTopics: Topic[]) => {
    const contentItem = createDuplicatedContentItem(id, title, type, topicId, currentTopics);
    return addContentToTopic(currentTopics, topicId, contentItem);
  };
};

/**
 * Creates a SET_TOPICS payload function for saving new content
 */
export const createSaveContentPayload = (topicId: number, id: number, title: string, type: ContentItem["type"]) => {
  return (currentTopics: Topic[]) => {
    const contentItem = createDuplicatedContentItem(id, title, type, topicId, currentTopics);
    return addContentToTopic(currentTopics, topicId, contentItem);
  };
};

/**
 * Type-safe content type resolver
 */
export const resolveContentType = (type: string): ContentItem["type"] => {
  // Handle known mappings
  if (type === "assignment") return "tutor_assignments";
  if (type === "google_meet") return "meet_lesson";
  if (type === "zoom") return "zoom_lesson";

  // Return as-is if it's already a valid ContentItem type
  return type as ContentItem["type"];
};

/**
 * Creates a generic SET_TOPICS action for direct topic updates
 */
export const createSetTopicsAction = (payload: Topic[] | ((topics: Topic[]) => Topic[])) => ({
  type: "SET_TOPICS" as const,
  payload,
});
