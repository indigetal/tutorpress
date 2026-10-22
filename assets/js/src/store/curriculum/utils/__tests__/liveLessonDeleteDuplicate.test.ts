import { describe, expect, it, jest } from "@jest/globals";
import { select } from "@wordpress/data";
import { deleteResolvers } from "../contentDelete";
import { duplicateResolvers } from "../contentDuplicate";

jest.mock("@wordpress/data", () => ({
  select: jest.fn(),
}));

const mockedSelect = select as unknown as {
  mockReturnValue: (value: { getTopics: () => Array<{ id: number; isCollapsed: boolean }> }) => void;
};

const topicsFetch = {
  type: "API_FETCH",
  request: { path: "/tutorpress/v1/topics?course_id=11" },
};

const topicsBody = {
  success: true,
  message: "Topics retrieved successfully.",
  data: [{ id: 22, title: "Week", menu_order: 1, isCollapsed: true, contents: [] }],
};

const projection = {
  status: "scheduled" as const,
  sync_state: "error" as const,
  sync_version: 0,
  operation: null,
  record: { id: 9 },
  warning: null,
  error: "cleanup_failed",
};

const legacyDeleted = { id: 9, deleted: true, type: "zoom", title: "Standup" };

const confirmedProvider = {
  outcome: "confirmed",
  provenance: "delete",
  item: null,
  provider_id: "zm-1",
  diagnostics: [],
};

const deleteEnvelope = {
  error: "provider_uncertain",
  operation: { operation_id: "op-1", kind: "delete" },
  owner_id: 4,
  provenance: "delete",
  provider: "zoom",
  provider_id: "zm-1",
  reconciliation_required: true,
  schema_version: 1,
  state: "error",
  version: 3,
  warning: null,
};

type DeleteRejection = Error & {
  outcome: { message: string; data?: Record<string, unknown> };
  refreshWarning: string | null;
};

const openDelete = (courseId: number) => {
  const generator = deleteResolvers.liveLesson(9);
  expect(generator.next().value).toMatchObject({
    type: "DELETE_LIVE_LESSON_START",
    payload: { liveLessonId: 9 },
  });
  expect(generator.next().value).toMatchObject({
    type: "API_FETCH",
    request: { path: "/tutorpress/v1/live-lessons/9", method: "GET" },
  });
  expect(generator.next({ data: { courseId } }).value).toMatchObject({
    type: "API_FETCH",
    request: { path: "/tutorpress/v1/live-lessons/9", method: "DELETE" },
  });
  return generator;
};

const rejectDelete = (generator: Generator): DeleteRejection => {
  let rejected: unknown;
  try {
    generator.next();
  } catch (error) {
    rejected = error;
  }
  expect(rejected).toBeInstanceOf(Error);
  return rejected as DeleteRejection;
};

const finishRefresh = (generator: Generator) => {
  expect(generator.next(topicsBody).value).toMatchObject({ type: "SET_TOPICS" });
  const failed = generator.next();
  expect((failed.value as { payload: Record<string, unknown> }).payload).not.toHaveProperty("refreshWarning");
  return failed;
};

describe("live lesson delete outcomes", () => {
  it("refreshes a legacy deleted body and does not remove the row in place", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    const generator = openDelete(11);
    expect(generator.next({ data: legacyDeleted }).value).toMatchObject({
      type: "DELETE_LIVE_LESSON_SUCCESS",
      payload: { liveLessonId: 9, courseId: 11 },
    });
    expect(generator.next().value).toEqual(topicsFetch);
    const listed = generator.next(topicsBody);
    expect(listed.value).toMatchObject({ type: "SET_TOPICS" });
    expect(Array.isArray((listed.value as { payload: unknown }).payload)).toBe(true);
    expect(generator.next()).toEqual({
      done: true,
      value: { outcome: legacyDeleted, refreshWarning: null },
    });
  });

  it("refreshes a confirmed provider delete", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    const generator = openDelete(11);
    expect(
      generator.next({ message: "Live lesson deleted successfully.", data: confirmedProvider }).value
    ).toMatchObject({
      type: "DELETE_LIVE_LESSON_SUCCESS",
      payload: { liveLessonId: 9, courseId: 11 },
    });
    expect(generator.next().value).toEqual(topicsFetch);
    expect(generator.next(topicsBody).value).toMatchObject({ type: "SET_TOPICS" });
    expect(generator.next()).toEqual({
      done: true,
      value: { outcome: confirmedProvider, refreshWarning: null },
    });
  });

  it("reports success without refresh when the prefetched course id is not positive", () => {
    const generator = openDelete(0);
    expect(generator.next({ message: "Live lesson deleted successfully.", data: legacyDeleted }).value).toMatchObject({
      type: "DELETE_LIVE_LESSON_SUCCESS",
      payload: { liveLessonId: 9 },
    });
    expect(generator.next()).toEqual({
      done: true,
      value: { outcome: legacyDeleted, refreshWarning: null },
    });
  });

  it("refreshes a resolved sync envelope without reporting deletion success", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    const generator = openDelete(11);
    expect(generator.next({ message: "Request successful.", data: deleteEnvelope }).value).toEqual(topicsFetch);
    const failed = finishRefresh(generator);
    expect(failed.value).toMatchObject({
      type: "DELETE_LIVE_LESSON_ERROR",
      payload: {
        error: {
          code: "delete_failed",
          message: "Failed to delete live_lesson",
          data: { warning: null },
        },
      },
    });
    const error = (failed.value as { payload: { error: Record<string, unknown> } }).payload.error;
    expect(error.data).toEqual({ warning: null });
    expect(error.data).not.toHaveProperty("provider_id");
    expect(error.data).not.toHaveProperty("state");
    expect(error).not.toHaveProperty("provider_id");
    expect(error).not.toHaveProperty("state");
    const rejection = rejectDelete(generator);
    expect(rejection.message).toBe("Failed to delete live_lesson");
    expect(rejection.outcome.data).toEqual({ warning: null });
    expect(rejection.refreshWarning).toBeNull();
  });

  it("refreshes when persisted is false and refetch_required or projected_record is present", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    for (const data of [
      { persisted: false, refetch_required: true },
      { persisted: false, projected_record: projection },
      { persisted: true },
    ]) {
      const generator = openDelete(11);
      expect(generator.throw({ message: "Saved locally.", data }).value).toEqual(topicsFetch);
      const failed = finishRefresh(generator);
      expect(failed.value).toMatchObject({
        type: "DELETE_LIVE_LESSON_ERROR",
        payload: { error: { code: "delete_failed", message: "Saved locally.", data } },
      });
      const rejection = rejectDelete(generator);
      expect(rejection.message).toBe("Saved locally.");
      expect(rejection.outcome.data).toEqual(data);
      expect(rejection.refreshWarning).toBeNull();
    }
  });

  it("does not refresh a thrown body that only has persisted false", () => {
    const generator = openDelete(11);
    const data = { persisted: false };
    const failed = generator.throw({ message: "Not deleted.", data });
    expect(failed.value).toMatchObject({
      type: "DELETE_LIVE_LESSON_ERROR",
      payload: { error: { code: "delete_failed", message: "Not deleted.", data } },
    });
    const rejection = rejectDelete(generator);
    expect(rejection.message).toBe("Not deleted.");
    expect(rejection.outcome.data).toEqual(data);
    expect(rejection.refreshWarning).toBeNull();
  });

  it("keeps a refresh warning on the rejection when the topics fetch fails", () => {
    const generator = openDelete(11);
    expect(generator.next({ data: legacyDeleted }).value).toMatchObject({ type: "DELETE_LIVE_LESSON_SUCCESS" });
    expect(generator.next().value).toEqual(topicsFetch);
    expect(generator.next({ success: false, message: "Network down", data: [] })).toEqual({
      done: true,
      value: { outcome: legacyDeleted, refreshWarning: "Network down" },
    });
  });
});

const duplicated = {
  id: 21,
  title: "Standup (Copy)",
  status: "publish",
  sync_state: "unlinked",
  sync_version: 1,
};

const unlinkedProjection = {
  status: "scheduled" as const,
  sync_state: "unlinked" as const,
  sync_version: 1,
  operation: null,
  record: { id: 21 },
  warning: null,
  error: null,
};

const openDuplicate = (courseId: number) => {
  const generator = duplicateResolvers.liveLesson(9, 22, courseId);
  expect(generator.next().value).toMatchObject({
    type: "DUPLICATE_LIVE_LESSON_START",
    payload: { liveLessonId: 9, topicId: 22, courseId },
  });
  expect(generator.next().value).toEqual({
    type: "API_FETCH",
    request: {
      path: "/tutorpress/v1/live-lessons/9/duplicate",
      method: "POST",
      data: { topic_id: 22, course_id: courseId },
    },
  });
  return generator;
};

const rejectDuplicate = (generator: Generator): DeleteRejection => {
  let rejected: unknown;
  try {
    generator.next();
  } catch (error) {
    rejected = error;
  }
  expect(rejected).toBeInstanceOf(Error);
  return rejected as DeleteRejection;
};

describe("live lesson duplicate outcomes", () => {
  it("refreshes a settled duplicate and keeps the server record", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    for (const data of [duplicated, { id: 21, title: "Standup (Copy)" }]) {
      const generator = openDuplicate(11);
      expect(generator.next({ data }).value).toMatchObject({
        type: "DUPLICATE_LIVE_LESSON_SUCCESS",
        payload: {
          liveLesson: { id: 21, title: "Standup (Copy)" },
          sourceLiveLessonId: 9,
          courseId: 11,
        },
      });
      expect(generator.next().value).toEqual(topicsFetch);
      const listed = generator.next(topicsBody);
      expect(listed.value).toMatchObject({ type: "SET_TOPICS" });
      expect(Array.isArray((listed.value as { payload: unknown }).payload)).toBe(true);
      expect(generator.next()).toEqual({
        done: true,
        value: { outcome: data, refreshWarning: null },
      });
    }
  });

  it("does not refresh when courseId is not positive", () => {
    const generator = openDuplicate(0);
    expect(generator.next({ data: duplicated }).value).toMatchObject({
      type: "DUPLICATE_LIVE_LESSON_SUCCESS",
      payload: {
        liveLesson: { id: 21, title: "Standup (Copy)" },
        sourceLiveLessonId: 9,
        courseId: 0,
      },
    });
    expect(generator.next()).toEqual({
      done: true,
      value: { outcome: duplicated, refreshWarning: null },
    });
  });

  it("refreshes when persisted is false and refetch_required or projected_record is present", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    for (const data of [
      { persisted: false, refetch_required: true },
      { persisted: false, projected_record: unlinkedProjection },
      { persisted: true },
    ]) {
      const generator = openDuplicate(11);
      expect(generator.throw({ message: "Saved locally.", data }).value).toEqual(topicsFetch);
      const failed = finishRefresh(generator);
      expect(failed.value).toMatchObject({
        type: "DUPLICATE_LIVE_LESSON_ERROR",
        payload: {
          liveLessonId: 9,
          error: { code: "duplicate_failed", message: "Saved locally.", data },
        },
      });
      const payload = (failed.value as { payload: { error: Record<string, unknown> } }).payload;
      expect(payload).not.toHaveProperty("refreshWarning");
      expect(payload.error).not.toHaveProperty("refreshWarning");
      const rejection = rejectDuplicate(generator);
      expect(rejection.message).toBe("Saved locally.");
      expect(rejection.outcome.data).toEqual(data);
      expect(rejection.refreshWarning).toBeNull();
    }
  });

  it("does not refresh a thrown body that only has persisted false", () => {
    const generator = openDuplicate(11);
    const data = { persisted: false };
    const failed = generator.throw({ message: "Not duplicated.", data });
    expect(failed.value).toMatchObject({
      type: "DUPLICATE_LIVE_LESSON_ERROR",
      payload: {
        liveLessonId: 9,
        error: { code: "duplicate_failed", message: "Not duplicated.", data },
      },
    });
    const payload = (failed.value as { payload: { error: Record<string, unknown> } }).payload;
    expect(payload).not.toHaveProperty("refreshWarning");
    expect(payload.error).not.toHaveProperty("refreshWarning");
    const rejection = rejectDuplicate(generator);
    expect(rejection.message).toBe("Not duplicated.");
    expect(rejection.outcome.data).toEqual(data);
    expect(rejection.refreshWarning).toBeNull();
  });

  it("uses the duplicate failure text when the thrown message is empty", () => {
    const generator = openDuplicate(11);
    const failed = generator.throw({ message: "", data: { persisted: false } });
    expect(failed.value).toMatchObject({
      type: "DUPLICATE_LIVE_LESSON_ERROR",
      payload: {
        liveLessonId: 9,
        error: {
          code: "duplicate_failed",
          message: "Failed to duplicate live_lesson",
          data: { persisted: false },
        },
      },
    });
    const rejection = rejectDuplicate(generator);
    expect(rejection.message).toBe("Failed to duplicate live_lesson");
    expect(rejection.refreshWarning).toBeNull();
  });

  it("keeps a refresh warning on the rejection when the topics fetch fails", () => {
    const generator = openDuplicate(11);
    const data = { persisted: false, refetch_required: true };
    expect(generator.throw({ message: "Saved locally.", data }).value).toEqual(topicsFetch);
    const failed = generator.next({ success: false, message: "Network down", data: [] });
    expect(failed.value).toMatchObject({
      type: "DUPLICATE_LIVE_LESSON_ERROR",
      payload: { liveLessonId: 9, error: { message: "Saved locally.", data } },
    });
    expect((failed.value as { payload: Record<string, unknown> }).payload).not.toHaveProperty("refreshWarning");
    const rejection = rejectDuplicate(generator);
    expect(rejection.message).toBe("Saved locally.");
    expect(rejection.outcome.data).toEqual(data);
    expect(rejection.refreshWarning).toBe("Network down");
  });

  it("still appends a lesson or quiz duplicate and does not throw", () => {
    const lessonBody = { data: { id: 8, title: "Copied lesson" } };
    const lesson = duplicateResolvers.lesson(4, 22);
    expect(lesson.next().value).toMatchObject({ type: "DUPLICATE_LESSON_START" });
    expect(lesson.next().value).toMatchObject({
      type: "API_FETCH",
      request: { path: "/tutorpress/v1/lessons/4/duplicate", method: "POST", data: { topic_id: 22 } },
    });
    expect(lesson.next(lessonBody).value).toMatchObject({ type: "DUPLICATE_LESSON_SUCCESS" });
    const lessonTopics = lesson.next().value as { type: string; payload: unknown };
    expect(lessonTopics.type).toBe("SET_TOPICS");
    expect(typeof lessonTopics.payload).toBe("function");
    expect(lesson.next()).toEqual({ done: true, value: lessonBody.data });

    const quizBody = { data: { id: 6, title: "Copied quiz" } };
    const quiz = duplicateResolvers.quiz(5, 22, 11);
    expect(quiz.next().value).toMatchObject({
      type: "DUPLICATE_QUIZ_START",
      payload: { quizId: 5, topicId: 22, courseId: 11 },
    });
    expect(quiz.next().value).toMatchObject({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/quizzes/5/duplicate",
        method: "POST",
        data: { topic_id: 22, course_id: 11 },
      },
    });
    expect(quiz.next(quizBody).value).toMatchObject({ type: "DUPLICATE_QUIZ_SUCCESS" });
    const quizTopics = quiz.next().value as { type: string; payload: unknown };
    expect(quizTopics.type).toBe("SET_TOPICS");
    expect(typeof quizTopics.payload).toBe("function");
    expect(quiz.next()).toEqual({ done: true, value: quizBody.data });
  });
});
