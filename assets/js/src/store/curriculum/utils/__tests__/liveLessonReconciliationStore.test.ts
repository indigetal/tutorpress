import { afterEach, describe, expect, it } from "@jest/globals";
import { dispatch } from "@wordpress/data";
import { reconcileLiveLessonResolver } from "../../index";
import { buildLiveLessonReconcileRequest } from "../../../../utils/liveLessonReconciliation";

const inspect = { action: "inspect" as const, version: 4 };
const uncertain = { action: "attach" as const, kind: "uncertain_create" as const, version: 3, operation_id: "op-1" };
const legacy = { action: "attach" as const, kind: "legacy_identity" as const, version: 0 as const };
const abandon = { action: "abandon" as const, version: 3, operation_id: "op-1", label: "possible orphan" };
const lessonId = 9;
const courseId = 11;
const topicsFetch = {
  type: "API_FETCH",
  request: { path: "/tutorpress/v1/topics?course_id=11" },
};
const topicsBody = {
  success: true,
  message: "Topics retrieved successfully.",
  data: [{ id: 22, title: "Week", menu_order: 1, isCollapsed: true, contents: [] }],
};
const resolved = { id: lessonId, sync_state: "synced" };
const projectedRecord = {
  status: "scheduled" as const,
  sync_state: "error" as const,
  sync_version: 1,
  operation: null,
  record: null,
  warning: null,
  error: null,
};

type ReconciliationRejection = Error & {
  outcome: {
    code?: string;
    message: string;
    data?: {
      status?: number;
      operation_id?: string;
      sync_version?: number;
      sync_state?: "pending" | "error" | "unlinked" | "synced";
      persisted?: boolean;
      warning?: string | null;
      projected_record?: typeof projectedRecord;
    };
  };
  refreshWarning: string | null;
};

const setFlag = (enabled?: boolean) => {
  window.tutorPressCurriculum = {
    adminUrl: "https://example.test/wp-admin/",
    ...(enabled === undefined ? {} : { liveLessonSyncEnabled: enabled }),
  };
};

const rememberTopics = () => {
  const store = dispatch("tutorpress/curriculum") as { setTopics: (topics: typeof topicsBody.data) => void };
  store.setTopics(topicsBody.data);
};

const openInspect = (id = courseId, supplied: unknown = {}, ambiguousOwner = false) =>
  reconcileLiveLessonResolver(lessonId, inspect, supplied, id, ambiguousOwner);

const caughtReconcile = (run: () => void): ReconciliationRejection => {
  let rejected: unknown;
  try {
    run();
  } catch (error) {
    rejected = error;
  }
  expect(rejected).toBeInstanceOf(Error);
  return rejected as ReconciliationRejection;
};

afterEach(() => {
  delete window.tutorPressCurriculum;
});

describe("reconcileLiveLesson", () => {
  it("returns null with no request when the capability flag is not strictly true", () => {
    delete window.tutorPressCurriculum;
    expect(openInspect().next()).toEqual({ done: true, value: null });

    setFlag();
    expect(openInspect().next()).toEqual({ done: true, value: null });

    setFlag(false);
    expect(openInspect().next()).toEqual({ done: true, value: null });

    window.tutorPressCurriculum = {
      adminUrl: "https://example.test/wp-admin/",
      liveLessonSyncEnabled: "true" as unknown as boolean,
    };
    expect(openInspect().next()).toEqual({ done: true, value: null });
  });

  it("returns null with no request when the builder rejects the supplied record", () => {
    setFlag(true);
    expect(openInspect(courseId, { provider_id: "evt" }).next()).toEqual({ done: true, value: null });
  });

  it("posts the typed reconcile body and refreshes with the caller course id", () => {
    setFlag(true);
    rememberTopics();
    const supplied = {};
    const ambiguousOwner = true;
    const body = buildLiveLessonReconcileRequest(inspect, supplied, ambiguousOwner);
    const generator = openInspect(courseId, supplied, ambiguousOwner);
    expect(generator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/live-lessons/9/reconcile",
        method: "POST",
        data: body,
      },
    });
    expect(body).toEqual({ action: "inspect", version: 4 });
    expect(body).not.toHaveProperty("provider_id");
    expect(generator.next(resolved).value).toEqual(topicsFetch);
    expect(generator.next(topicsBody).value).toMatchObject({ type: "SET_TOPICS" });
    expect(generator.next()).toEqual({ done: true, value: { outcome: resolved, refreshWarning: null } });
  });

  it("keeps a refresh warning beside the resolved outcome", () => {
    setFlag(true);
    const generator = openInspect();
    generator.next();
    expect(generator.next(resolved).value).toEqual(topicsFetch);
    expect(generator.next({ success: false, message: "Network down", data: [] })).toEqual({
      done: true,
      value: { outcome: resolved, refreshWarning: "Network down" },
    });
  });

  it("does not refresh when the caller course id is zero", () => {
    setFlag(true);
    const generator = openInspect(0);
    generator.next();
    expect(generator.next(resolved)).toEqual({ done: true, value: { outcome: resolved, refreshWarning: null } });
  });

  it("refreshes a settled failure with the caller course id and preserves the normalized outcome", () => {
    setFlag(true);
    rememberTopics();
    const generator = openInspect();
    generator.next();
    const thrown = {
      code: "live_lesson_persisted_error",
      message: "Saved locally.",
      data: { persisted: true, projected_record: projectedRecord },
    };
    expect(generator.throw(thrown).value).toEqual(topicsFetch);
    expect(generator.next(topicsBody).value).toMatchObject({ type: "SET_TOPICS" });
    const rejection = caughtReconcile(() => {
      generator.next();
    });
    expect(rejection.message).toBe("Saved locally.");
    expect(rejection.outcome).toMatchObject({
      code: "live_lesson_persisted_error",
      message: "Saved locally.",
      data: { persisted: true, projected_record: projectedRecord },
    });
    expect(rejection.outcome).not.toHaveProperty("refreshWarning");
    expect(rejection.refreshWarning).toBeNull();
  });

  it("does not refresh a settled failure when the caller course id is zero", () => {
    setFlag(true);
    const generator = openInspect(0);
    generator.next();
    const rejection = caughtReconcile(() => {
      generator.throw({ code: "live_lesson_persisted_error", message: "Saved locally.", data: { persisted: true } });
    });
    expect(rejection.message).toBe("Saved locally.");
    expect(rejection.outcome.data).toEqual({ persisted: true });
    expect(rejection.refreshWarning).toBeNull();
  });

  it("keeps an empty normalized message empty", () => {
    setFlag(true);
    const generator = openInspect();
    generator.next();
    const rejection = caughtReconcile(() => {
      generator.throw({ message: "" });
    });
    expect(rejection.message).toBe("");
    expect(rejection.outcome).toEqual({ message: "" });
    expect(rejection.refreshWarning).toBeNull();
  });

  it("posts both attach request shapes", () => {
    setFlag(true);
    const uncertainSupplied = { provider_id: "evt-1" };
    const uncertainBody = buildLiveLessonReconcileRequest(uncertain, uncertainSupplied);
    const uncertainGenerator = reconcileLiveLessonResolver(lessonId, uncertain, uncertainSupplied, courseId);
    expect(uncertainGenerator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/live-lessons/9/attach/uncertain-create",
        method: "POST",
        data: uncertainBody,
      },
    });
    expect(uncertainBody).toEqual({ provider_id: "evt-1", version: 3, operation_id: "op-1" });
    expect(uncertainBody).not.toHaveProperty("action");
    expect(uncertainBody).not.toHaveProperty("label");

    const legacySupplied = { provider_id: "evt-1" };
    const legacyBody = buildLiveLessonReconcileRequest(legacy, legacySupplied);
    const legacyGenerator = reconcileLiveLessonResolver(lessonId, legacy, legacySupplied, courseId);
    expect(legacyGenerator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/live-lessons/9/attach/legacy",
        method: "POST",
        data: legacyBody,
      },
    });
    expect(legacyBody).toEqual({ provider_id: "evt-1", version: 0 });
    expect(legacyBody).not.toHaveProperty("selected_owner_id");
    expect(legacyBody).not.toHaveProperty("operation_id");
    expect(legacyBody).not.toHaveProperty("label");
  });

  it("posts a legacy owner only when that owner is ambiguous", () => {
    setFlag(true);
    const supplied = { provider_id: "evt-1", selected_owner_id: 7 };
    const body = buildLiveLessonReconcileRequest(legacy, supplied, true);
    const generator = reconcileLiveLessonResolver(lessonId, legacy, supplied, courseId, true);
    expect(generator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/live-lessons/9/attach/legacy",
        method: "POST",
        data: body,
      },
    });
    expect(body).toEqual({ provider_id: "evt-1", version: 0, selected_owner_id: 7 });

    setFlag(true);
    expect(reconcileLiveLessonResolver(lessonId, legacy, supplied, courseId, false).next()).toEqual({
      done: true,
      value: null,
    });
  });

  it("preserves a stale operation or version without refreshing", () => {
    setFlag(true);
    const generator = openInspect();
    generator.next();
    const rejection = caughtReconcile(() => {
      generator.throw({
        code: "stale_operation",
        message: "Stale operation.",
        data: { status: 409, operation_id: "other-op", sync_version: 4, persisted: false },
      });
    });
    expect(rejection.message).toBe("Stale operation.");
    expect(rejection.outcome).toEqual({
      code: "stale_operation",
      message: "Stale operation.",
      data: { status: 409, operation_id: "other-op", sync_version: 4, persisted: false },
    });
    expect(rejection.outcome).not.toHaveProperty("refreshWarning");
    expect(rejection.refreshWarning).toBeNull();
  });

  it("preserves an unresolved mismatch or uncertain state without refreshing", () => {
    for (const syncState of ["error", "unlinked"] as const) {
      setFlag(true);
      const generator = openInspect();
      generator.next();
      const rejection = caughtReconcile(() => {
        generator.throw({
          message: "Unresolved.",
          data: { sync_state: syncState, warning: "provider mismatch", persisted: false },
        });
      });
      expect(rejection.outcome).toEqual({
        message: "Unresolved.",
        data: { sync_state: syncState, warning: "provider mismatch", persisted: false },
      });
      expect(rejection.refreshWarning).toBeNull();
    }
  });

  it("posts abandon without the orphan label and keeps its warning beside a refresh failure", () => {
    setFlag(true);
    const supplied = {};
    const body = buildLiveLessonReconcileRequest(abandon, supplied);
    const generator = reconcileLiveLessonResolver(lessonId, abandon, supplied, courseId);
    expect(generator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/live-lessons/9/reconcile",
        method: "POST",
        data: body,
      },
    });
    expect(body).toEqual({ action: "abandon", version: 3, operation_id: "op-1" });
    expect(body).not.toHaveProperty("label");
    const abandoned = { id: lessonId, sync_state: "error", warning: "orphan acknowledgment" };
    expect(generator.next(abandoned).value).toEqual(topicsFetch);
    expect(generator.next({ success: false, message: "Network down", data: [] })).toEqual({
      done: true,
      value: { outcome: abandoned, refreshWarning: "Network down" },
    });
  });

  it("returns a synced uncertain-create payload after refresh", () => {
    setFlag(true);
    rememberTopics();
    const supplied = { provider_id: "evt-1" };
    const body = buildLiveLessonReconcileRequest(uncertain, supplied);
    const generator = reconcileLiveLessonResolver(lessonId, uncertain, supplied, courseId);
    expect(generator.next().value).toEqual({
      type: "API_FETCH",
      request: {
        path: "/tutorpress/v1/live-lessons/9/attach/uncertain-create",
        method: "POST",
        data: body,
      },
    });
    const finalized = { id: lessonId, sync_state: "synced" };
    expect(generator.next(finalized).value).toEqual(topicsFetch);
    expect(generator.next(topicsBody).value).toMatchObject({ type: "SET_TOPICS" });
    expect(generator.next()).toEqual({ done: true, value: { outcome: finalized, refreshWarning: null } });
  });

  it("keeps a settled failure's refresh warning off the outcome", () => {
    setFlag(true);
    const generator = openInspect();
    generator.next();
    expect(
      generator.throw({
        code: "live_lesson_persisted_error",
        message: "Saved locally.",
        data: { persisted: true, warning: "provider mismatch" },
      }).value
    ).toEqual(topicsFetch);
    const rejection = caughtReconcile(() => {
      generator.next({ success: false, message: "Network down", data: [] });
    });
    expect(rejection.message).toBe("Saved locally.");
    expect(rejection.outcome).toEqual({
      code: "live_lesson_persisted_error",
      message: "Saved locally.",
      data: { persisted: true, warning: "provider mismatch" },
    });
    expect(rejection.outcome).not.toHaveProperty("refreshWarning");
    expect(rejection.refreshWarning).toBe("Network down");
  });
});
