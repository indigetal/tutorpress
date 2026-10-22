import { select } from "@wordpress/data";
import { Topic } from "../../../types/curriculum";
import { readLiveLessonRefreshMessage, sanitizeLiveLessonRefreshWarning } from "../../../utils/liveLessonErrors";

/**
 * Copy isCollapsed from current topics onto fetched topics with the same ID.
 * Newly discovered topics default to collapsed. Fetched content, order, and
 * status remain authoritative; presentation state is never copied across IDs.
 */
export function reconcileFetchedTopics(currentTopics: Topic[], fetchedTopics: Topic[]): Topic[] {
  const collapseById = new Map(currentTopics.map((topic) => [topic.id, topic.isCollapsed]));

  return fetchedTopics.map((topic) => ({
    ...topic,
    isCollapsed: collapseById.get(topic.id) ?? true,
  }));
}

/**
 * Refresh topics after a settled mutation.
 * Yields the topics fetch, then SET_TOPICS from reconcileFetchedTopics().
 * Returns a refresh warning separately. Failure leaves the current list in place.
 */
export function* refreshTopicsAfterSettledMutation(courseId: number): Generator<unknown, string | null, unknown> {
  try {
    const response = (yield {
      type: "API_FETCH",
      request: {
        path: `/tutorpress/v1/topics?course_id=${courseId}`,
      },
    }) as { success: boolean; message: string; data: Topic[] };

    if (!response.success) {
      throw new Error(response.message || "Failed to fetch topics");
    }

    const topics = response.data.map((topic) => ({
      ...topic,
      isCollapsed: true,
      contents:
        topic.contents?.map((content, index) => ({
          ...content,
          topic_id: topic.id,
          order: index,
        })) || [],
    }));

    const currentTopics = select("tutorpress/curriculum").getTopics() as Topic[];
    yield {
      type: "SET_TOPICS",
      payload: reconcileFetchedTopics(currentTopics, topics),
    };
    return null;
  } catch (error) {
    return sanitizeLiveLessonRefreshWarning(readLiveLessonRefreshMessage(error));
  }
}
