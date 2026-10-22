import type { LiveLessonAdvertisedAction, LiveLessonLegacyAttachRequest, LiveLessonUncertainCreateAttachRequest } from "../types/liveLessons";

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}

function operationId(value: Record<string, unknown>): string | null {
  return typeof value.operation_id === "string" ? value.operation_id : null;
}

function advertisedAction(value: Record<string, unknown>): LiveLessonAdvertisedAction | null {
  const keys = Object.keys(value).sort().join(",");
  const version = value.version;
  const operation = operationId(value);

  if (keys === "action,version" && value.action === "resolve_owner" && version === 0) {
    return { action: "resolve_owner", version: 0 };
  }
  if (keys === "action,kind,version" && value.action === "attach" && value.kind === "legacy_identity" && version === 0) {
    return { action: "attach", kind: "legacy_identity", version: 0 };
  }
  if (keys === "action,version" && value.action === "inspect" && typeof version === "number") {
    return { action: "inspect", version };
  }
  if (keys === "action,kind,operation_id,version" && value.action === "attach" && value.kind === "uncertain_create" && typeof version === "number" && operation !== null) {
    return { action: "attach", kind: "uncertain_create", version, operation_id: operation };
  }
  if (keys === "action,operation_id,version" && (value.action === "inspect" || value.action === "abandon" || value.action === "retry") && typeof version === "number" && operation !== null) {
    return { action: value.action, version, operation_id: operation };
  }
  return null;
}

type LabeledLiveLessonRecoveryAction = LiveLessonAdvertisedAction & { label?: string };

function labelAcceptedAbandon(descriptor: LiveLessonAdvertisedAction): LabeledLiveLessonRecoveryAction {
  if (descriptor.action !== "abandon") {
    return descriptor;
  }
  return { ...descriptor, label: "possible orphan" };
}

export function deriveLiveLessonRecoveryActions(actions: unknown): LabeledLiveLessonRecoveryAction[] {
  if (!Array.isArray(actions)) {
    return [];
  }
  const derived: LabeledLiveLessonRecoveryAction[] = [];
  for (const entry of actions) {
    if (!isRecord(entry)) {
      continue;
    }
    const descriptor = advertisedAction(entry);
    if (descriptor) {
      derived.push(labelAcceptedAbandon(descriptor));
    }
  }
  return derived;
}

type LiveLessonStoredIdRequest =
  | { action: "resolve_owner"; version: 0 }
  | { action: "inspect"; version: number }
  | { action: "inspect" | "retry" | "abandon"; version: number; operation_id: string };

function requestKeys(value: Record<string, unknown>): string {
  return Object.keys(value).sort().join(",");
}

export function buildLiveLessonReconcileRequest(
  descriptor: LabeledLiveLessonRecoveryAction,
  supplied: unknown,
  ambiguousOwner = false
): LiveLessonUncertainCreateAttachRequest | LiveLessonLegacyAttachRequest | LiveLessonStoredIdRequest | null {
  if (!isRecord(supplied)) {
    return null;
  }
  const keys = requestKeys(supplied);
  const provider = typeof supplied.provider_id === "string" && supplied.provider_id !== "" ? supplied.provider_id : null;
  const owner =
    typeof supplied.selected_owner_id === "number" && Number.isInteger(supplied.selected_owner_id) && supplied.selected_owner_id > 0
      ? supplied.selected_owner_id
      : null;

  if (descriptor.action === "attach" && descriptor.kind === "uncertain_create") {
    if (keys !== "provider_id" || provider === null || !Number.isInteger(descriptor.version) || descriptor.version < 1 || descriptor.operation_id === "") {
      return null;
    }
    return { provider_id: provider, version: descriptor.version, operation_id: descriptor.operation_id };
  }
  if (descriptor.action === "attach" && descriptor.kind === "legacy_identity") {
    if (keys === "provider_id" && provider !== null) {
      return { provider_id: provider, version: 0 };
    }
    if (ambiguousOwner && keys === "provider_id,selected_owner_id" && provider !== null && owner !== null) {
      return { provider_id: provider, version: 0, selected_owner_id: owner };
    }
    return null;
  }
  if (keys !== "") {
    return null;
  }
  if (descriptor.action === "resolve_owner") {
    return { action: "resolve_owner", version: 0 };
  }
  if (descriptor.action === "inspect" && !("operation_id" in descriptor)) {
    return { action: "inspect", version: descriptor.version };
  }
  if ("operation_id" in descriptor && (descriptor.action === "inspect" || descriptor.action === "retry" || descriptor.action === "abandon")) {
    return { action: descriptor.action, version: descriptor.version, operation_id: descriptor.operation_id };
  }
  return null;
}

export function liveLessonRowPresentation(
  row: { type: string; sync_state?: unknown; actions?: unknown },
  enabled: unknown
): { badge: "pending" | "error" | "unlinked" | null; actions: ReturnType<typeof deriveLiveLessonRecoveryActions> } {
  if (enabled !== true || (row.type !== "meet_lesson" && row.type !== "zoom_lesson")) {
    return { badge: null, actions: [] };
  }
  const state = row.sync_state;
  return {
    badge: state === "pending" || state === "error" || state === "unlinked" ? state : null,
    actions: deriveLiveLessonRecoveryActions(row.actions),
  };
}
