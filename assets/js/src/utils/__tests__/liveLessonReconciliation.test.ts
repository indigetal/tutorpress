import { describe, expect, it } from "@jest/globals";
import {
  buildLiveLessonReconcileRequest,
  deriveLiveLessonRecoveryActions,
  liveLessonRowPresentation,
} from "../liveLessonReconciliation";

describe("deriveLiveLessonRecoveryActions", () => {
  it("keeps each advertised descriptor and does not collapse the two inspect shapes", () => {
    const advertised = [
      { version: 0, action: "resolve_owner" },
      { action: "attach", kind: "legacy_identity", version: 0 },
      { action: "inspect", version: 3, operation_id: "op-1" },
      { action: "attach", kind: "uncertain_create", version: 3, operation_id: "op-1" },
      { action: "abandon", version: 3, operation_id: "op-1" },
      { action: "retry", version: 3, operation_id: "op-1" },
      { action: "inspect", version: 4 },
    ];

    expect(deriveLiveLessonRecoveryActions(advertised)).toEqual([
      { action: "resolve_owner", version: 0 },
      { action: "attach", kind: "legacy_identity", version: 0 },
      { action: "inspect", version: 3, operation_id: "op-1" },
      { action: "attach", kind: "uncertain_create", version: 3, operation_id: "op-1" },
      { action: "abandon", version: 3, operation_id: "op-1", label: "possible orphan" },
      { action: "retry", version: 3, operation_id: "op-1" },
      { action: "inspect", version: 4 },
    ]);
  });

  it("drops an extra key and the three locator names", () => {
    expect(
      deriveLiveLessonRecoveryActions([
        { action: "retry", version: 2, operation_id: "op-2" },
        { action: "inspect", version: 3, provider_id: "evt" },
        { action: "abandon", version: 3, operation_id: "op-1", label: "possible orphan" },
        { action: "discard_failed", version: 1 },
        { action: "adopt_unlinked", version: 1 },
        { action: "abandon_unknown", version: 1, operation_id: "op-1" },
      ])
    ).toEqual([{ action: "retry", version: 2, operation_id: "op-2" }]);
  });

  it("returns an empty list for a non-array", () => {
    expect(deriveLiveLessonRecoveryActions(null)).toEqual([]);
    expect(deriveLiveLessonRecoveryActions(undefined)).toEqual([]);
    expect(deriveLiveLessonRecoveryActions({ action: "retry", version: 1, operation_id: "op-1" })).toEqual([]);
    expect(deriveLiveLessonRecoveryActions("retry")).toEqual([]);
  });
});

describe("buildLiveLessonReconcileRequest", () => {
  const uncertain = { action: "attach" as const, kind: "uncertain_create" as const, version: 3, operation_id: "op-1" };
  const legacy = { action: "attach" as const, kind: "legacy_identity" as const, version: 0 as const };

  it("builds an uncertain attach from the supplied provider id and the advertised operation", () => {
    expect(buildLiveLessonReconcileRequest(uncertain, { provider_id: "evt-1" })).toEqual({
      provider_id: "evt-1",
      version: 3,
      operation_id: "op-1",
    });
    expect(buildLiveLessonReconcileRequest(uncertain, { provider_id: "evt-1", operation_id: "other" })).toBeNull();
    expect(buildLiveLessonReconcileRequest(uncertain, { provider_id: "evt-1", selected_owner_id: 7 })).toBeNull();
    expect(buildLiveLessonReconcileRequest({ ...uncertain, operation_id: "" }, { provider_id: "evt-1" })).toBeNull();
  });

  it("includes a legacy owner only when that owner is advertised as ambiguous", () => {
    expect(buildLiveLessonReconcileRequest(legacy, { provider_id: "evt-1" })).toEqual({
      provider_id: "evt-1",
      version: 0,
    });
    expect(buildLiveLessonReconcileRequest(legacy, { provider_id: "evt-1", selected_owner_id: 7 }, true)).toEqual({
      provider_id: "evt-1",
      version: 0,
      selected_owner_id: 7,
    });
    expect(buildLiveLessonReconcileRequest(legacy, { provider_id: "evt-1", selected_owner_id: 7 })).toBeNull();
    expect(buildLiveLessonReconcileRequest(legacy, { provider_id: "evt-1", operation_id: "op-1" })).toBeNull();
  });

  it("leaves provider identity off ordinary inspect and retry requests", () => {
    expect(buildLiveLessonReconcileRequest({ action: "inspect", version: 4 }, {})).toEqual({
      action: "inspect",
      version: 4,
    });
    expect(buildLiveLessonReconcileRequest({ action: "inspect", version: 3, operation_id: "op-1" }, {})).toEqual({
      action: "inspect",
      version: 3,
      operation_id: "op-1",
    });
    expect(buildLiveLessonReconcileRequest({ action: "retry", version: 3, operation_id: "op-1" }, {})).toEqual({
      action: "retry",
      version: 3,
      operation_id: "op-1",
    });
    expect(buildLiveLessonReconcileRequest({ action: "inspect", version: 4 }, { provider_id: "evt-1" })).toBeNull();
    expect(buildLiveLessonReconcileRequest({ action: "retry", version: 3, operation_id: "op-1" }, { provider_id: "evt-1" })).toBeNull();
  });

  it("copies abandon and resolve-owner without a supplied id or the orphan label", () => {
    expect(
      buildLiveLessonReconcileRequest({ action: "abandon", version: 3, operation_id: "op-1", label: "possible orphan" }, {})
    ).toEqual({ action: "abandon", version: 3, operation_id: "op-1" });
    expect(buildLiveLessonReconcileRequest({ action: "resolve_owner", version: 0 }, {})).toEqual({
      action: "resolve_owner",
      version: 0,
    });
    expect(buildLiveLessonReconcileRequest({ action: "resolve_owner", version: 0 }, { provider_id: "evt-1" })).toBeNull();
    expect(buildLiveLessonReconcileRequest(uncertain, null)).toBeNull();
  });
});

describe("liveLessonRowPresentation", () => {
  const retry = { action: "retry" as const, version: 3, operation_id: "op-1" };
  const knownInspect = { action: "inspect" as const, version: 4 };
  const liveError = { type: "meet_lesson", sync_state: "error", actions: [retry] };

  it("returns no badge and no actions unless capability is strictly true", () => {
    expect(liveLessonRowPresentation(liveError, false)).toEqual({ badge: null, actions: [] });
    expect(liveLessonRowPresentation(liveError, undefined)).toEqual({ badge: null, actions: [] });
    expect(liveLessonRowPresentation(liveError, null)).toEqual({ badge: null, actions: [] });
    expect(liveLessonRowPresentation(liveError, "true")).toEqual({ badge: null, actions: [] });
  });

  it("returns no badge and no actions for a non-live row", () => {
    expect(liveLessonRowPresentation({ ...liveError, type: "lesson" }, true)).toEqual({ badge: null, actions: [] });
  });

  it("badges pending, error, and unlinked from sync state when capability is true", () => {
    expect(liveLessonRowPresentation({ type: "zoom_lesson", sync_state: "pending", actions: [retry] }, true)).toEqual({
      badge: "pending",
      actions: [retry],
    });
    expect(liveLessonRowPresentation(liveError, true)).toEqual({ badge: "error", actions: [retry] });
    expect(liveLessonRowPresentation({ type: "meet_lesson", sync_state: "unlinked", actions: [retry] }, true)).toEqual({
      badge: "unlinked",
      actions: [retry],
    });
  });

  it("leaves a synced row without a badge and still returns known-provider inspect", () => {
    expect(
      liveLessonRowPresentation({ type: "meet_lesson", sync_state: "synced", actions: [knownInspect] }, true)
    ).toEqual({
      badge: null,
      actions: [knownInspect],
    });
  });

  it("leaves the badge empty for a missing or other sync state and still returns the action", () => {
    const missing = liveLessonRowPresentation({ type: "zoom_lesson", actions: [retry] }, true);
    const other = liveLessonRowPresentation({ type: "zoom_lesson", sync_state: "scheduled", actions: [retry] }, true);

    expect(missing.badge).toBeNull();
    expect(missing.actions).toEqual([retry]);
    expect(other.badge).toBeNull();
    expect(other.actions).toEqual([retry]);
  });
});
