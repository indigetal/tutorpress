import { describe, expect, it } from "@jest/globals";
import {
  normalizeLiveLessonError,
  preserveLiveLessonMutation,
  readLiveLessonRefreshMessage,
  sanitizeLiveLessonRefreshWarning,
} from "../liveLessonErrors";

const projectedRecord = {
  id: 9,
  title: "Meet",
  persisted_envelope: true,
  resolution: ["legacy_resolution_required"],
  status: "scheduled",
  sync_state: "error",
  sync_version: 1,
  operation: { operation_id: "op", kind: "create", dispatch_state: "not_dispatched", provider_id: null },
  record: { provider_id: "meet-1", summary: "Keep" },
  warning: null,
  error: "failed",
};

describe("live lesson error normalizer", () => {
  it("keeps code, message, numeric status, and the scheduled projection", () => {
    const normalized = normalizeLiveLessonError({
      code: "live_lesson_persisted_transition",
      message: "Live lesson transition failed.",
      stack: "omit",
      data: {
        status: 500,
        record_id: 9,
        course_id: 3,
        topic_id: 4,
        operation_id: "op",
        sync_state: "error",
        sync_version: 1,
        persisted: true,
        refetch_required: true,
        token: "secret",
        projected_record: projectedRecord,
        actions: [
          { action: "resolve_owner", version: 0, provider_id: "owner" },
          { action: "attach", kind: "legacy_identity", version: 0, provider_id: "legacy" },
          { action: "inspect", version: 1, operation_id: "op", provider_id: "inspect" },
          { action: "inspect", version: 2 },
          { action: "attach", kind: "uncertain_create", version: 1, operation_id: "op", provider_id: "create" },
          { action: "abandon", version: 1, operation_id: "op", provider_id: "abandon" },
          { action: "retry", version: 1, operation_id: "op", provider_id: "zoom-host" },
          { action: "discard_failed", version: 1, provider_id: "drop" },
          { action: "adopt_unlinked" },
          { action: "abandon_unknown", operation_id: "op" },
        ],
      },
    });
    expect(normalized).toEqual({
      code: "live_lesson_persisted_transition",
      message: "Live lesson transition failed.",
      data: {
        status: 500,
        record_id: 9,
        course_id: 3,
        topic_id: 4,
        operation_id: "op",
        sync_state: "error",
        sync_version: 1,
        persisted: true,
        refetch_required: true,
        projected_record: {
          status: "scheduled",
          sync_state: "error",
          sync_version: 1,
          operation: { operation_id: "op", kind: "create", dispatch_state: "not_dispatched" },
          record: { provider_id: "meet-1", summary: "Keep" },
          warning: null,
          error: "failed",
        },
        actions: [
          { action: "resolve_owner", version: 0 },
          { action: "attach", kind: "legacy_identity", version: 0 },
          { action: "inspect", version: 1, operation_id: "op" },
          { action: "inspect", version: 2 },
          { action: "attach", kind: "uncertain_create", version: 1, operation_id: "op" },
          { action: "abandon", version: 1, operation_id: "op" },
          { action: "retry", version: 1, operation_id: "op" },
        ],
      },
    });
    expect(normalized).not.toHaveProperty("stack");
    expect(normalized.data).not.toHaveProperty("token");
    expect(normalized.data).not.toHaveProperty("warning");
    expect(normalized.data?.projected_record).not.toHaveProperty("persisted_envelope");
    expect(normalized.data?.projected_record).not.toHaveProperty("resolution");
  });

  it("copies a null warning and false persisted from a persistence failure", () => {
    expect(normalizeLiveLessonError({
      code: "live_lesson_persisted_error",
      message: "Live lesson persistence failed.",
      data: { status: 409, persisted: false, warning: null, refetch_required: true },
    })).toEqual({
      code: "live_lesson_persisted_error",
      message: "Live lesson persistence failed.",
      data: { status: 409, persisted: false, warning: null, refetch_required: true },
    });
  });

  it("drops every untyped action and keeps an empty actions array", () => {
    expect(normalizeLiveLessonError({
      code: "live_lesson_persisted_error",
      message: "Live lesson persistence failed.",
      data: { actions: [{ action: "discard_failed" }, { action: "adopt_unlinked" }, { action: "abandon_unknown" }] },
    })).toEqual({
      code: "live_lesson_persisted_error",
      message: "Live lesson persistence failed.",
      data: { actions: [] },
    });
  });

  it("normalizes a code and message object that has no data", () => {
    const normalized = normalizeLiveLessonError({ code: "fetch_error", message: "Network down" });
    expect(normalized).toEqual({ code: "fetch_error", message: "Network down" });
    expect(normalized).not.toHaveProperty("data");
  });

  it("reduces a native Error to its message", () => {
    const thrown = new Error("boom");
    thrown.stack = "secret stack";
    const normalized = normalizeLiveLessonError(thrown);
    expect(normalized).toEqual({ message: "boom" });
    expect(normalized).not.toHaveProperty("stack");
    expect(normalized).not.toHaveProperty("code");
    expect(normalized).not.toHaveProperty("data");
  });
});

describe("live lesson refresh message", () => {
  it("reads a native Error message", () => {
    const thrown = new Error("boom");
    thrown.stack = "secret stack";
    expect(readLiveLessonRefreshMessage(thrown)).toBe("boom");
  });

  it("reads a plain object message and ignores code and data", () => {
    expect(readLiveLessonRefreshMessage({ code: "fetch_error", message: "Network down", data: { token: "secret" } })).toBe("Network down");
  });

  it("returns null for an empty message or a failure with no string message", () => {
    expect(readLiveLessonRefreshMessage({ code: "fetch_error", message: "", data: { status: 500 } })).toBeNull();
    expect(readLiveLessonRefreshMessage({ code: "fetch_error", data: { status: 500 } })).toBeNull();
    expect(readLiveLessonRefreshMessage(new Error(""))).toBeNull();
  });
});

describe("live lesson refresh warning", () => {
  it("keeps a non-matching message verbatim", () => {
    expect(sanitizeLiveLessonRefreshWarning("Network down")).toBe("Network down");
  });

  it("replaces a secret-shaped message with refresh_failed", () => {
    expect(sanitizeLiveLessonRefreshWarning("bad token value")).toBe("refresh_failed");
    expect(sanitizeLiveLessonRefreshWarning("API_KEY leaked")).toBe("refresh_failed");
  });

  it("keeps null and empty text null", () => {
    expect(sanitizeLiveLessonRefreshWarning(null)).toBeNull();
    expect(sanitizeLiveLessonRefreshWarning("")).toBeNull();
  });
});

describe("live lesson mutation precedence", () => {
  const projectedRecord = {
    status: "scheduled" as const,
    sync_state: "synced" as const,
    sync_version: 1,
    operation: null,
    record: { provider_id: "meet-1" },
    warning: null,
    error: null,
  };

  it("keeps a persisted success and attaches the refresh warning separately", () => {
    const outcome = { persisted: true, projected_record: projectedRecord };
    const preserved = preserveLiveLessonMutation(outcome, { code: "fetch_error", message: "Network down", data: { token: "secret" } });
    expect(preserved.outcome).toBe(outcome);
    expect(preserved.outcome.projected_record).toBe(projectedRecord);
    expect(preserved.outcome.persisted).toBe(true);
    expect(preserved.refreshWarning).toBe("Network down");
  });

  it("keeps a persisted failure when the refresh message is secret-shaped", () => {
    const outcome = {
      code: "live_lesson_persisted_error",
      message: "Live lesson persistence failed.",
      data: { status: 500, persisted: false, projected_record: projectedRecord },
    };
    const preserved = preserveLiveLessonMutation(outcome, { code: "fetch_error", message: "bad token value", data: { status: 500 } });
    expect(preserved.outcome).toBe(outcome);
    expect(preserved.outcome.code).toBe("live_lesson_persisted_error");
    expect(preserved.outcome.message).toBe("Live lesson persistence failed.");
    expect(preserved.outcome.data).toBe(outcome.data);
    expect(preserved.refreshWarning).toBe("refresh_failed");
  });

  it("leaves refreshWarning null when the refresh has no string message", () => {
    const outcome = { persisted: true, projected_record: projectedRecord };
    const preserved = preserveLiveLessonMutation(outcome, { code: "fetch_error", message: "", data: { status: 500 } });
    expect(preserved).toEqual({ outcome, refreshWarning: null });
    expect(preserved.outcome).toBe(outcome);
    expect(preserveLiveLessonMutation(outcome).refreshWarning).toBeNull();
  });
});
