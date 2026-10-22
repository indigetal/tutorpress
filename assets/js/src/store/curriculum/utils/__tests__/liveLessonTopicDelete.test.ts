import { describe, expect, it } from "@jest/globals";
import { dispatch } from "@wordpress/data";
import { deleteTopicResolver } from "../../index";

const topicsFetch = {
  type: "API_FETCH",
  request: { path: "/tutorpress/v1/topics?course_id=11" },
};

const topicsBody = {
  success: true,
  message: "Topics retrieved successfully.",
  data: [{ id: 22, title: "Week", menu_order: 1, isCollapsed: true, contents: [] }],
};

const deleted = { success: true, message: "Topic deleted successfully.", data: null };

const partial = {
  code: "topic_content_deletion_partial",
  message: "Topic deleted, but some content-items could not be deleted.",
  data: { status: 207 },
};

type TopicError = {
  type: string;
  payload: { error: { code: string; message: string; data?: unknown } };
};

const openTopicDelete = (courseId: number) => {
  const generator = deleteTopicResolver(9, courseId);
  expect(generator.next().value).toMatchObject({
    type: "DELETE_TOPIC_START",
    payload: { topicId: 9 },
  });
  expect(generator.next().value).toEqual({
    type: "API_FETCH",
    request: { path: "/tutorpress/v1/topics/9", method: "DELETE" },
  });
  return generator;
};

type TopicRejection = Error & { refreshWarning: string | null };

const rememberTopics = (
  topics: Array<{ id: number; title: string; menu_order: number; isCollapsed: boolean; contents: [] }>
) => {
  const store = dispatch("tutorpress/curriculum") as {
    setTopics: (next: typeof topics) => void;
  };
  store.setTopics(topics);
};

const rejectTopicDelete = (generator: Generator): TopicRejection => {
  let rejected: unknown;
  try {
    generator.next();
  } catch (error) {
    rejected = error;
  }
  expect(rejected).toBeInstanceOf(Error);
  return rejected as TopicRejection;
};

const finishFailureRefresh = (generator: Generator, body: typeof topicsBody) => {
  const listed = generator.next(body);
  expect(listed.value).toMatchObject({ type: "SET_TOPICS" });
  const failed = generator.next();
  const payload = (failed.value as TopicError).payload;
  expect(payload).not.toHaveProperty("refreshWarning");
  expect(payload.error).not.toHaveProperty("refreshWarning");
  return { listed, failed };
};

describe("live lesson topic delete outcomes", () => {
  it("refreshes a successful delete and does not collapse every topic", () => {
    rememberTopics([{ id: 22, title: "Week", menu_order: 1, isCollapsed: false, contents: [] }]);
    const generator = openTopicDelete(11);
    expect(generator.next(deleted).value).toMatchObject({
      type: "DELETE_TOPIC_SUCCESS",
      payload: { topicId: 9 },
    });
    expect(generator.next().value).toEqual(topicsFetch);
    const listed = generator.next(topicsBody);
    expect(listed.value).toMatchObject({ type: "SET_TOPICS" });
    const topics = (listed.value as { payload: Array<{ id: number; isCollapsed: boolean }> }).payload;
    expect(Array.isArray(topics)).toBe(true);
    expect(topics.find((topic) => topic.id === 22)?.isCollapsed).toBe(false);
    expect(generator.next()).toEqual({ done: true, value: null });
  });

  it("does not refresh when courseId is not a positive number", () => {
    for (const courseId of [0, "11" as unknown as number]) {
      const generator = openTopicDelete(courseId);
      expect(generator.next(deleted).value).toMatchObject({ type: "DELETE_TOPIC_SUCCESS" });
      expect(generator.next()).toEqual({ done: true, value: null });
    }
  });

  it("refreshes a resolved 207 body and keeps its data on the error", () => {
    rememberTopics([{ id: 9, title: "Week", menu_order: 1, isCollapsed: false, contents: [] }]);
    const generator = openTopicDelete(11);
    expect(generator.next(partial).value).toEqual(topicsFetch);
    const { listed, failed } = finishFailureRefresh(generator, topicsBody);
    const topics = (listed.value as { payload: Array<{ id: number }> }).payload;
    expect(topics.find((topic) => topic.id === 9)).toBeUndefined();
    expect(failed.value).toMatchObject({
      type: "DELETE_TOPIC_ERROR",
      payload: { error: { code: "delete_failed", message: partial.message, data: { status: 207 } } },
    });
    const rejection = rejectTopicDelete(generator);
    expect(rejection.message).toBe(partial.message);
    expect(rejection.refreshWarning).toBeNull();
  });

  it("refreshes a thrown 409 and keeps the parent that the server still lists", () => {
    rememberTopics([{ id: 9, title: "Week", menu_order: 1, isCollapsed: false, contents: [] }]);
    const retained = {
      success: true,
      message: "Topics retrieved successfully.",
      data: [{ id: 9, title: "Week", menu_order: 1, isCollapsed: true, contents: [] }],
    };
    const generator = openTopicDelete(11);
    expect(generator.throw({ message: "Topic could not be deleted.", data: { status: 409 } }).value).toEqual(
      topicsFetch
    );
    const listed = generator.next(retained);
    expect(listed.value).toMatchObject({ type: "SET_TOPICS" });
    const topics = (listed.value as { payload: Array<{ id: number; isCollapsed: boolean }> }).payload;
    expect(topics.find((topic) => topic.id === 9)?.isCollapsed).toBe(false);
    const failed = generator.next();
    expect(failed.value).toMatchObject({
      type: "DELETE_TOPIC_ERROR",
      payload: {
        error: { code: "delete_failed", message: "Topic could not be deleted.", data: { status: 409 } },
      },
    });
    expect((failed.value as TopicError).payload.error).not.toHaveProperty("refreshWarning");
    const rejection = rejectTopicDelete(generator);
    expect(rejection.message).toBe("Topic could not be deleted.");
    expect(rejection.refreshWarning).toBeNull();
  });

  it("copies a non-settled error data object and does not refresh it", () => {
    for (const courseId of [0, "11" as unknown as number]) {
      const generator = openTopicDelete(courseId);
      const failed = generator.next(partial);
      expect(failed.value).toMatchObject({
        type: "DELETE_TOPIC_ERROR",
        payload: { error: { message: partial.message, data: { status: 207 } } },
      });
      const rejection = rejectTopicDelete(generator);
      expect(rejection.refreshWarning).toBeNull();
    }

    const generator = openTopicDelete(11);
    const failed = generator.throw({ message: "Missing topic.", data: { status: 404 } });
    expect(failed.value).toMatchObject({
      type: "DELETE_TOPIC_ERROR",
      payload: { error: { message: "Missing topic.", data: { status: 404 } } },
    });
    expect(rejectTopicDelete(generator).refreshWarning).toBeNull();
  });

  it("keeps a failure refresh warning on the rejection", () => {
    const generator = openTopicDelete(11);
    expect(generator.next(partial).value).toEqual(topicsFetch);
    const failed = generator.next({ success: false, message: "Network down", data: [] });
    expect(failed.value).toMatchObject({
      type: "DELETE_TOPIC_ERROR",
      payload: { error: { message: partial.message, data: partial.data } },
    });
    expect((failed.value as TopicError).payload).not.toHaveProperty("refreshWarning");
    const rejection = rejectTopicDelete(generator);
    expect(rejection.message).toBe(partial.message);
    expect(rejection.refreshWarning).toBe("Network down");
  });

  it("uses the topic failure text when the thrown message is empty or missing", () => {
    for (const thrown of [{ message: "" }, { data: { status: 404 } }, new Error("")]) {
      const generator = openTopicDelete(11);
      const failed = generator.throw(thrown);
      expect(failed.value).toMatchObject({
        type: "DELETE_TOPIC_ERROR",
        payload: { error: { code: "delete_failed", message: "Failed to delete topic" } },
      });
      expect(rejectTopicDelete(generator).message).toBe("Failed to delete topic");
    }
  });

  it("returns a refresh warning without failing the delete", () => {
    const generator = openTopicDelete(11);
    expect(generator.next(deleted).value).toMatchObject({ type: "DELETE_TOPIC_SUCCESS" });
    expect(generator.next().value).toEqual(topicsFetch);
    expect(generator.next({ success: false, message: "Network down", data: [] })).toEqual({
      done: true,
      value: "Network down",
    });
  });
});
