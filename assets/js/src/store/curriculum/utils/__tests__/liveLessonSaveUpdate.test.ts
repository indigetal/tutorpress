import { afterEach, describe, expect, it, jest } from "@jest/globals";
import { select } from "@wordpress/data";
import { LiveLessonFormData } from "../../../../types/liveLessons";
import { createStandardUpdateResolver, liveLessonCreateBody, liveLessonPatchBody, saveUpdateResolvers } from "../contentSaveUpdate";

jest.mock("@wordpress/data", () => ({
  select: jest.fn(),
}));

const mockedSelect = select as unknown as {
  mockReturnValue: (value: { getTopics: () => Array<{ id: number; isCollapsed: boolean }> }) => void;
};

const identityKeys = ["provider_id", "event_id", "meeting_id", "eventId", "meetingId"] as const;

const lesson = (type: LiveLessonFormData["type"], duration = 40): LiveLessonFormData => ({
  title: "Standup",
  description: "Notes",
  type,
  startDateTime: "2026-10-13 15:04:00",
  endDateTime: "2026-10-13 16:04:00",
  settings: {
    timezone: "America/New_York",
    duration,
    allowEarlyJoin: true,
    autoRecord: false,
    requirePassword: true,
    waitingRoom: false,
    add_enrolled_students: "Yes",
  },
  providerConfig: {
    host: "host-1",
    password: "keep",
    provider_id: "prov",
    event_id: "event",
    meeting_id: "meeting",
    eventId: "event-camel",
    meetingId: "meeting-camel",
  },
});

const meetingFlags = {
  allow_early_join: true,
  auto_record: false,
  require_password: true,
  waiting_room: false,
  add_enrolled_students: "Yes",
};

const zoomFlags = {
  ...meetingFlags,
  password: "keep",
};

const shared = {
  title: "Standup",
  description: "Notes",
  start_date_time: "2026-10-13 15:04:00",
  end_date_time: "2026-10-13 16:04:00",
  provider_config: { host: "host-1", password: "keep" },
  topic_id: 22,
  course_id: 11,
};

const falseGateZoom = {
  ...shared,
  type: "zoom",
  settings: {
    timezone: "America/New_York",
    duration: 40,
    ...zoomFlags,
  },
};

const setFlag = (enabled?: boolean) => {
  window.tutorPressCurriculum = {
    adminUrl: "https://example.test/wp-admin/",
    ...(enabled === undefined ? {} : { liveLessonSyncEnabled: enabled }),
  };
};

afterEach(() => {
  delete window.tutorPressCurriculum;
});

describe("live lesson create body", () => {
  it("keeps the false-gate body unless the flag is strictly true", () => {
    for (const enabled of [undefined, false] as const) {
      setFlag(enabled);
      expect(liveLessonCreateBody(lesson("zoom"), 11, 22)).toEqual(falseGateZoom);
    }
    window.tutorPressCurriculum = {
      adminUrl: "https://example.test/wp-admin/",
      liveLessonSyncEnabled: "true" as unknown as boolean,
    };
    expect(liveLessonCreateBody(lesson("zoom"), 11, 22)).toEqual(falseGateZoom);
  });

  it("serializes a true-gate Zoom body from the minute count", () => {
    setFlag(true);
    expect(liveLessonCreateBody(lesson("zoom"), 11, 22)).toEqual({
      ...shared,
      type: "zoom",
      timezone: "America/New_York",
      duration: { value: 40, unit: "minutes" },
      settings: {
        timezone: "America/New_York",
        ...zoomFlags,
      },
    });
  });

  it("copies a Zoom password into settings on both gates", () => {
    for (const enabled of [undefined, true] as const) {
      setFlag(enabled);
      const zoom = lesson("zoom");
      expect(liveLessonCreateBody(zoom, 11, 22).settings).toMatchObject({ password: "keep" });
      const cleared = { ...zoom, providerConfig: { ...zoom.providerConfig, password: "" } };
      expect(liveLessonCreateBody(cleared, 11, 22).settings).toMatchObject({ password: "" });
      const { password: _removed, ...config } = zoom.providerConfig ?? {};
      expect(liveLessonCreateBody({ ...zoom, providerConfig: config }, 11, 22).settings).not.toHaveProperty("password");
      expect(liveLessonCreateBody(lesson("google_meet"), 11, 22).settings).not.toHaveProperty("password");
    }
  });

  it("omits top-level duration for Google and for a rejected minute count", () => {
    setFlag(true);
    expect(liveLessonCreateBody(lesson("google_meet"), 11, 22)).toEqual({
      ...shared,
      type: "google_meet",
      timezone: "America/New_York",
      settings: {
        timezone: "America/New_York",
        ...meetingFlags,
      },
    });
    const rejected = liveLessonCreateBody(lesson("zoom", 481), 11, 22);
    expect(rejected).not.toHaveProperty("duration");
    expect(rejected.settings).not.toHaveProperty("duration");
    expect(rejected).toMatchObject({
      timezone: "America/New_York",
      topic_id: 22,
      course_id: 11,
      settings: { timezone: "America/New_York" },
    });
  });

  it("drops the five identity keys and keeps host", () => {
    const polluted = {
      ...lesson("zoom"),
      provider_id: "top",
      status: "scheduled",
      durationUnit: "minutes",
      duration_unit: "min",
    } as LiveLessonFormData;
    for (const enabled of [undefined, true] as const) {
      setFlag(enabled);
      const body = liveLessonCreateBody(polluted, 11, 22);
      for (const key of [...identityKeys, "status", "durationUnit", "duration_unit"]) {
        expect(body).not.toHaveProperty(key);
        expect(body.provider_config).not.toHaveProperty(key);
      }
      expect(body.provider_config).toEqual({ host: "host-1", password: "keep" });
      expect(body.topic_id).toBe(22);
      expect(body.course_id).toBe(11);
    }
  });
});

const scheduleOf = (body: Record<string, unknown>) => {
  const schedule = { ...body };
  delete schedule.topic_id;
  delete schedule.course_id;
  return schedule;
};

describe("live lesson patch body", () => {
  it("matches the create schedule unless the flag is strictly true", () => {
    for (const enabled of [undefined, false] as const) {
      setFlag(enabled);
      const created = liveLessonCreateBody(lesson("zoom"), 11, 22);
      const patched = liveLessonPatchBody(lesson("zoom"));
      expect(created).toMatchObject({ topic_id: 22, course_id: 11 });
      expect(patched).toEqual(scheduleOf(created));
      expect(patched).not.toHaveProperty("topic_id");
      expect(patched).not.toHaveProperty("course_id");
      expect(patched).not.toHaveProperty("startDateTime");
      expect(patched).not.toHaveProperty("endDateTime");
      expect(patched).not.toHaveProperty("providerConfig");
    }
  });

  it("matches a strictly true Zoom create schedule", () => {
    setFlag(true);
    const created = liveLessonCreateBody(lesson("zoom"), 11, 22);
    expect(liveLessonPatchBody(lesson("zoom"))).toEqual(scheduleOf(created));
    expect(created).toMatchObject({ topic_id: 22, course_id: 11, duration: { value: 40, unit: "minutes" } });
  });

  it("matches a strictly true Google create schedule", () => {
    setFlag(true);
    const created = liveLessonCreateBody(lesson("google_meet"), 11, 22);
    const patched = liveLessonPatchBody(lesson("google_meet"));
    expect(patched).toEqual(scheduleOf(created));
    expect(patched).not.toHaveProperty("duration");
    expect(patched).not.toHaveProperty("topic_id");
    expect(patched).not.toHaveProperty("course_id");
  });

  it("puts the patch body on the API_FETCH yield", () => {
    setFlag(true);
    const form = lesson("zoom");
    const generator = saveUpdateResolvers.updateLiveLesson(9, form);
    expect(generator.next().value).toMatchObject({
      type: "UPDATE_LIVE_LESSON_START",
      payload: { liveLessonId: 9, data: form },
    });
    expect(generator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/live-lessons/9",
        method: "PATCH",
        data: liveLessonPatchBody(form),
      },
    });
  });

  it("sends the original data when no transformer is configured", () => {
    const form = { startDateTime: "keep" };
    const generator = createStandardUpdateResolver({
      type: "update",
      apiPath: "/tutorpress/v1/example",
      entityName: "example",
      idField: "exampleId",
      responseDataExtractor: () => ({ id: 1, title: "Example" }),
      successPayloadCreator: (entity) => entity,
      topicUpdatePayloadCreator: () => ({ type: "noop" }),
    })(4, form);
    expect(generator.next().value).toMatchObject({
      type: "UPDATE_EXAMPLE_START",
      payload: { exampleId: 4, data: form },
    });
    expect(generator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/example/4",
        method: "PATCH",
        data: form,
      },
    });
  });
});

const pastMessage = "Start time cannot be in the past";

const topicsFetch = {
  type: "API_FETCH",
  request: { path: "/tutorpress/v1/topics?course_id=11" },
};

const createdLesson = {
  id: 9,
  title: "Standup",
  description: "Notes",
  type: "zoom" as const,
  topicId: 22,
  courseId: 11,
  status: "scheduled" as const,
  startDateTime: "2026-10-13 15:04:00",
  endDateTime: "2026-10-13 16:04:00",
  duration: { value: 40, unit: "minutes" as const },
  settings: {
    timezone: "America/New_York",
    autoRecording: "none",
    waitingRoom: false,
    addEnrolledStudents: "",
    status: "",
    transparency: "",
    visibility: "",
    reminder_minutes: null,
  },
  providerConfig: { host: "host-1" },
  password: "keep",
  sync_state: "synced" as const,
  sync_version: 3,
  persisted_envelope: true,
  operation: null,
  warning: null,
  error: null,
  resolution: null,
  record: null,
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

const cleanupData = {
  status: 500,
  record_id: 9,
  sync_state: "error" as const,
  sync_version: 0,
  persisted: true,
  refetch_required: true,
  projected_record: projection,
};

const topicsBody = {
  success: true,
  message: "Topics retrieved successfully.",
  data: [{ id: 22, title: "Week", menu_order: 1, isCollapsed: true, contents: [] }],
};

type CreateRejection = Error & {
  outcome: { message: string; data?: Record<string, unknown> };
  refreshWarning: string | null;
};

const openCreate = () => {
  const generator = saveUpdateResolvers.saveLiveLesson(lesson("zoom"), 11, 22);
  generator.next();
  expect(generator.next().value).toMatchObject({
    type: "API_FETCH",
    request: { path: "/tutorpress/v1/live-lessons", method: "POST" },
  });
  return generator;
};

const rejectCreate = (generator: Generator): CreateRejection => {
  let rejected: unknown;
  try {
    generator.next();
  } catch (error) {
    rejected = error;
  }
  expect(rejected).toBeInstanceOf(Error);
  return rejected as CreateRejection;
};

describe("live lesson create outcomes", () => {
  it("returns the created entity after a successful refresh", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    const generator = openCreate();
    expect(generator.next({ data: createdLesson }).value).toMatchObject({
      type: "SAVE_LIVE_LESSON_SUCCESS",
      payload: { liveLesson: createdLesson, courseId: 11 },
    });
    expect(generator.next().value).toEqual(topicsFetch);
    const listed = generator.next(topicsBody);
    expect(listed.value).toMatchObject({ type: "SET_TOPICS" });
    expect(Array.isArray((listed.value as { payload: unknown }).payload)).toBe(true);
    expect(generator.next()).toEqual({
      done: true,
      value: { outcome: createdLesson, refreshWarning: null },
    });
  });

  it("returns a refresh warning and leaves the topic list in place", () => {
    const generator = openCreate();
    generator.next({ data: createdLesson });
    expect(generator.next().value).toEqual(topicsFetch);
    const finished = generator.next({ success: false, message: "Network down", data: [] });
    expect(finished.done).toBe(true);
    expect(finished.value).toMatchObject({
      outcome: createdLesson,
      refreshWarning: "Network down",
    });
  });

  it("refreshes when persisted, refetch_required, or projected_record is present", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    for (const data of [{ persisted: true }, { refetch_required: true }, { projected_record: projection }]) {
      const generator = openCreate();
      expect(
        generator.throw({ code: "live_lesson_persisted_error", message: "Saved locally.", data }).value
      ).toEqual(topicsFetch);
      expect(generator.next(topicsBody).value).toMatchObject({ type: "SET_TOPICS" });
      const failed = generator.next();
      expect(failed).toMatchObject({
        done: false,
        value: {
          type: "SAVE_LIVE_LESSON_ERROR",
          payload: { error: { code: "SAVE_FAILED", message: "Saved locally.", data } },
        },
      });
      expect((failed.value as { payload: Record<string, unknown> }).payload).not.toHaveProperty("refreshWarning");
      const rejection = rejectCreate(generator);
      expect(rejection.message).toBe("Saved locally.");
      expect(rejection.outcome.data).toEqual(data);
      expect(rejection.refreshWarning).toBeNull();
    }
  });

  it("does not refresh a thrown body without a settled record", () => {
    const generator = openCreate();
    const data = { status: 400, persisted: false };
    const failed = generator.throw({
      code: "rest_invalid_param",
      message: pastMessage,
      data,
    });
    expect(failed.value).toMatchObject({
      type: "SAVE_LIVE_LESSON_ERROR",
      payload: { error: { code: "SAVE_FAILED", message: pastMessage, data } },
    });
    expect((failed.value as { payload: Record<string, unknown> }).payload).not.toHaveProperty("refreshWarning");
    const rejection = rejectCreate(generator);
    expect(rejection.message).toBe(pastMessage);
    expect(rejection.outcome.data).toEqual(data);
    expect(rejection.refreshWarning).toBeNull();
  });

  it("keeps the failed-cleanup fields beside a refresh warning", () => {
    const generator = openCreate();
    expect(
      generator.throw({
        code: "live_lesson_initialization_failed",
        message: "Failed to initialize the live lesson.",
        data: cleanupData,
      }).value
    ).toEqual(topicsFetch);
    const failed = generator.next({ success: false, message: "Network down", data: [] });
    const payload = (failed.value as { payload: { error: { data: unknown } } }).payload;
    expect(failed.value).toMatchObject({ type: "SAVE_LIVE_LESSON_ERROR" });
    expect(payload).not.toHaveProperty("refreshWarning");
    expect(payload.error.data).toEqual(cleanupData);
    const rejection = rejectCreate(generator);
    expect(rejection.message).toBe("Failed to initialize the live lesson.");
    expect(rejection.outcome.data).toEqual(cleanupData);
    expect(rejection.outcome.data).toMatchObject({
      record_id: 9,
      sync_state: "error",
      sync_version: 0,
      refetch_required: true,
    });
    expect(rejection.refreshWarning).toBe("Network down");
  });

  it("uses the save fallback when the thrown message is empty", () => {
    const generator = openCreate();
    const failed = generator.throw({ message: "" });
    expect(failed.value).toMatchObject({
      type: "SAVE_LIVE_LESSON_ERROR",
      payload: { error: { code: "SAVE_FAILED", message: "Failed to save live_lesson" } },
    });
    const rejection = rejectCreate(generator);
    expect(rejection.message).toBe("Failed to save live_lesson");
    expect(rejection.outcome).not.toHaveProperty("data");
    expect(rejection.refreshWarning).toBeNull();
  });
});

const openUpdate = () => {
  const generator = saveUpdateResolvers.updateLiveLesson(9, lesson("zoom"));
  generator.next();
  const posted = generator.next().value as { request: { data: Record<string, unknown> } };
  expect(posted).toMatchObject({
    type: "API_FETCH",
    request: { path: "/tutorpress/v1/live-lessons/9", method: "PATCH", data: liveLessonPatchBody(lesson("zoom")) },
  });
  for (const key of ["provider_id", "event_id", "meeting_id", "eventId", "meetingId"]) {
    expect(posted.request.data).not.toHaveProperty(key);
    expect(posted.request.data.provider_config).not.toHaveProperty(key);
  }
  return generator;
};

const unlinkedData = {
  status: 500,
  record_id: 9,
  course_id: 11,
  sync_state: "error" as const,
  sync_version: 0,
  persisted: false,
  refetch_required: true,
  projected_record: projection,
};

describe("live lesson update outcomes", () => {
  it("returns a legacy envelope after a successful refresh", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    const generator = openUpdate();
    expect(generator.next({ data: createdLesson }).value).toMatchObject({
      type: "UPDATE_LIVE_LESSON_SUCCESS",
      payload: { liveLesson: createdLesson, courseId: 11 },
    });
    expect(generator.next().value).toEqual(topicsFetch);
    const listed = generator.next(topicsBody);
    expect(listed.value).toMatchObject({ type: "SET_TOPICS" });
    expect(Array.isArray((listed.value as { payload: unknown }).payload)).toBe(true);
    expect(generator.next()).toEqual({
      done: true,
      value: { outcome: createdLesson, refreshWarning: null },
    });
  });

  it("returns a refresh warning and leaves the topic list in place", () => {
    const generator = openUpdate();
    generator.next({ data: createdLesson });
    expect(generator.next().value).toEqual(topicsFetch);
    const finished = generator.next({ success: false, message: "Network down", data: [] });
    expect(finished.done).toBe(true);
    expect(finished.value).toMatchObject({
      outcome: createdLesson,
      refreshWarning: "Network down",
    });
  });

  it("returns the entity when the legacy envelope has no course id", () => {
    const bare = { id: 9, title: "Standup", type: "zoom" as const };
    const generator = openUpdate();
    expect(generator.next({ data: bare }).value).toMatchObject({ type: "UPDATE_LIVE_LESSON_SUCCESS" });
    const finished = generator.next();
    expect(finished.done).toBe(true);
    expect(finished.value).toMatchObject({
      outcome: bare,
      refreshWarning: null,
    });
    expect((finished.value as { outcome: { courseId?: number } }).outcome.courseId).toBeUndefined();
  });

  it("refreshes when a positive course_id has persisted, refetch_required, or projected_record", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [{ id: 22, isCollapsed: false }],
    });
    for (const data of [
      { course_id: 11, persisted: true },
      { course_id: 11, refetch_required: true },
      { course_id: 11, projected_record: projection },
    ]) {
      const generator = openUpdate();
      expect(
        generator.throw({ code: "live_lesson_persisted_error", message: "Saved locally.", data }).value
      ).toEqual(topicsFetch);
      expect(generator.next(topicsBody).value).toMatchObject({ type: "SET_TOPICS" });
      const failed = generator.next();
      expect(failed).toMatchObject({
        done: false,
        value: {
          type: "UPDATE_LIVE_LESSON_ERROR",
          payload: { error: { code: "UPDATE_FAILED", message: "Saved locally.", data } },
        },
      });
      expect((failed.value as { payload: Record<string, unknown> }).payload).not.toHaveProperty("refreshWarning");
      const rejection = rejectCreate(generator);
      expect(rejection.message).toBe("Saved locally.");
      expect(rejection.outcome.data).toEqual(data);
      expect(rejection.refreshWarning).toBeNull();
    }
  });

  it("does not refresh a thrown body without a settled record", () => {
    const generator = openUpdate();
    const data = { status: 400, persisted: false, course_id: 11 };
    const failed = generator.throw({
      code: "rest_invalid_param",
      message: pastMessage,
      data,
    });
    expect(failed.value).toMatchObject({
      type: "UPDATE_LIVE_LESSON_ERROR",
      payload: { error: { code: "UPDATE_FAILED", message: pastMessage, data } },
    });
    expect((failed.value as { payload: Record<string, unknown> }).payload).not.toHaveProperty("refreshWarning");
    const rejection = rejectCreate(generator);
    expect(rejection.message).toBe(pastMessage);
    expect(rejection.outcome.data).toEqual(data);
    expect(rejection.refreshWarning).toBeNull();
  });

  it("does not refresh a settled body without a positive course_id", () => {
    const generator = openUpdate();
    const data = { persisted: true, refetch_required: true, projected_record: projection, course_id: 0 };
    const failed = generator.throw({
      code: "live_lesson_persisted_error",
      message: "Saved locally.",
      data,
    });
    expect(failed.value).toMatchObject({
      type: "UPDATE_LIVE_LESSON_ERROR",
      payload: { error: { code: "UPDATE_FAILED", message: "Saved locally.", data } },
    });
    const rejection = rejectCreate(generator);
    expect(rejection.outcome.data).toEqual(data);
    expect(rejection.refreshWarning).toBeNull();
  });

  it("keeps an unlinked finalization projection beside a refresh warning", () => {
    const generator = openUpdate();
    expect(
      generator.throw({
        code: "live_lesson_persisted_transition",
        message: "Live lesson transition failed.",
        data: unlinkedData,
      }).value
    ).toEqual(topicsFetch);
    const failed = generator.next({ success: false, message: "Network down", data: [] });
    const payload = (failed.value as { payload: { error: { data: unknown } } }).payload;
    expect(failed.value).toMatchObject({ type: "UPDATE_LIVE_LESSON_ERROR" });
    expect(payload).not.toHaveProperty("refreshWarning");
    expect(payload.error.data).toEqual(unlinkedData);
    const rejection = rejectCreate(generator);
    expect(rejection.message).toBe("Live lesson transition failed.");
    expect(rejection.outcome.data).toEqual(unlinkedData);
    expect(rejection.outcome.data).toMatchObject({
      record_id: 9,
      sync_state: "error",
      sync_version: 0,
      refetch_required: true,
      persisted: false,
    });
    expect(rejection.refreshWarning).toBe("Network down");
  });

  it("uses the update fallback when the thrown message is empty", () => {
    const generator = openUpdate();
    const failed = generator.throw({ message: "" });
    expect(failed.value).toMatchObject({
      type: "UPDATE_LIVE_LESSON_ERROR",
      payload: { error: { code: "UPDATE_FAILED", message: "Failed to update live_lesson" } },
    });
    const rejection = rejectCreate(generator);
    expect(rejection.message).toBe("Failed to update live_lesson");
    expect(rejection.outcome).not.toHaveProperty("data");
    expect(rejection.refreshWarning).toBeNull();
  });
});
