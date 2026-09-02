import { Topic } from "../../../types/curriculum";

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
