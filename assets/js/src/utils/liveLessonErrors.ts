import type {
  LiveLessonAdvertisedAction,
  LiveLessonFullProjection,
  LiveLessonProjectedOperation,
  LiveLessonSyncNotice,
  LiveLessonSyncState,
} from "../types/liveLessons";

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null && !Array.isArray(value);

const syncState = (value: unknown): LiveLessonSyncState | undefined =>
  value === "pending" || value === "error" || value === "unlinked" || value === "synced" ? value : undefined;

/** A notice is a string, a list, an object, or null. */
const notice = (value: unknown): LiveLessonSyncNotice | null | undefined =>
  value === null || typeof value === "string" || Array.isArray(value) || isRecord(value) ? value : undefined;

/** Copy the three projected operation fields and leave every extra key off. */
const operation = (value: unknown): LiveLessonProjectedOperation | null | undefined => {
  if (value === null) return null;
  if (!isRecord(value) || typeof value.operation_id !== "string") return undefined;
  const kind = value.kind === "create" || value.kind === "update" || value.kind === "delete" ? value.kind : undefined;
  const dispatch =
    value.dispatch_state === "not_dispatched" ||
    value.dispatch_state === "dispatch_possible" ||
    value.dispatch_state === "confirmed"
      ? value.dispatch_state
      : undefined;
  return kind && dispatch ? { operation_id: value.operation_id, kind, dispatch_state: dispatch } : undefined;
};

/**
 * status is "scheduled". The numeric HTTP status stays on the error data.
 */
const projection = (value: unknown): LiveLessonFullProjection | undefined => {
  if (!isRecord(value) || value.status !== "scheduled" || typeof value.sync_version !== "number") return undefined;
  const sync = syncState(value.sync_state);
  const projectedOperation = operation(value.operation);
  const warning = notice(value.warning);
  const error = notice(value.error);
  const record = value.record === null || isRecord(value.record) ? value.record : undefined;
  if (!sync || projectedOperation === undefined || warning === undefined || error === undefined || record === undefined)
    return undefined;
  return {
    status: "scheduled",
    sync_state: sync,
    sync_version: value.sync_version,
    operation: projectedOperation,
    record,
    warning,
    error,
  };
};

const sameKeys = (value: Record<string, unknown>, names: string[]): boolean =>
  Object.keys(value).length === names.length &&
  names.every((name) => Object.prototype.hasOwnProperty.call(value, name));

/**
 * Remove provider_id, then keep the entry only when the remainder is one advertised descriptor.
 * provider_id inside projected_record.record is not handled here.
 */
const advertisedAction = (value: unknown): LiveLessonAdvertisedAction | undefined => {
  if (!isRecord(value)) return undefined;
  const entry = { ...value };
  delete entry.provider_id;
  const version = entry.version;
  const operationId = typeof entry.operation_id === "string" ? entry.operation_id : undefined;
  if (entry.action === "resolve_owner" && version === 0 && sameKeys(entry, ["action", "version"]))
    return { action: "resolve_owner", version: 0 };
  if (
    entry.action === "attach" &&
    entry.kind === "legacy_identity" &&
    version === 0 &&
    sameKeys(entry, ["action", "kind", "version"])
  )
    return { action: "attach", kind: "legacy_identity", version: 0 };
  if (
    entry.action === "inspect" &&
    typeof version === "number" &&
    operationId &&
    sameKeys(entry, ["action", "operation_id", "version"])
  )
    return { action: "inspect", version, operation_id: operationId };
  if (entry.action === "inspect" && typeof version === "number" && sameKeys(entry, ["action", "version"]))
    return { action: "inspect", version };
  if (
    entry.action === "attach" &&
    entry.kind === "uncertain_create" &&
    typeof version === "number" &&
    operationId &&
    sameKeys(entry, ["action", "kind", "operation_id", "version"])
  )
    return { action: "attach", kind: "uncertain_create", version, operation_id: operationId };
  if (
    entry.action === "abandon" &&
    typeof version === "number" &&
    operationId &&
    sameKeys(entry, ["action", "operation_id", "version"])
  )
    return { action: "abandon", version, operation_id: operationId };
  if (
    entry.action === "retry" &&
    typeof version === "number" &&
    operationId &&
    sameKeys(entry, ["action", "operation_id", "version"])
  )
    return { action: "retry", version, operation_id: operationId };
  return undefined;
};

/** data.status is the numeric HTTP status. persisted is copied as emitted. */
export interface LiveLessonNormalizedErrorData {
  status?: number;
  record_id?: number;
  course_id?: number;
  topic_id?: number;
  operation_id?: string | null;
  sync_state?: LiveLessonSyncState;
  sync_version?: number;
  persisted?: boolean;
  refetch_required?: boolean;
  warning?: LiveLessonSyncNotice | null;
  projected_record?: LiveLessonFullProjection;
  actions?: LiveLessonAdvertisedAction[];
}

export interface LiveLessonNormalizedError {
  code?: string;
  message: string;
  data?: LiveLessonNormalizedErrorData;
}

const copyNumber = (
  data: LiveLessonNormalizedErrorData,
  source: Record<string, unknown>,
  key: "status" | "record_id" | "course_id" | "topic_id" | "sync_version",
) => {
  if (typeof source[key] === "number") data[key] = source[key];
};

/**
 * Normalize parsed apiFetch JSON or a native Error.
 * An Error contributes message only. A missing data object stays absent.
 */
export function normalizeLiveLessonError(thrown: unknown): LiveLessonNormalizedError {
  if (thrown instanceof Error) return { message: thrown.message };
  if (!isRecord(thrown)) return { message: "" };

  const normalized: LiveLessonNormalizedError = { message: typeof thrown.message === "string" ? thrown.message : "" };
  if (typeof thrown.code === "string") normalized.code = thrown.code;
  if (!Object.prototype.hasOwnProperty.call(thrown, "data") || !isRecord(thrown.data)) return normalized;

  const source = thrown.data;
  const data: LiveLessonNormalizedErrorData = {};
  copyNumber(data, source, "status");
  copyNumber(data, source, "record_id");
  copyNumber(data, source, "course_id");
  copyNumber(data, source, "topic_id");
  copyNumber(data, source, "sync_version");
  if (source.operation_id === null || typeof source.operation_id === "string") data.operation_id = source.operation_id;
  const sync = syncState(source.sync_state);
  if (sync) data.sync_state = sync;
  if (typeof source.persisted === "boolean") data.persisted = source.persisted;
  if (typeof source.refetch_required === "boolean") data.refetch_required = source.refetch_required;
  // Copy warning when the key is present, including null.
  if (Object.prototype.hasOwnProperty.call(source, "warning")) {
    const warning = notice(source.warning);
    if (warning !== undefined) data.warning = warning;
  }
  if (Object.prototype.hasOwnProperty.call(source, "projected_record")) {
    const projected = projection(source.projected_record);
    if (projected) data.projected_record = projected;
  }
  if (Array.isArray(source.actions)) {
    const actions: LiveLessonAdvertisedAction[] = [];
    source.actions.forEach((entry) => {
      const kept = advertisedAction(entry);
      if (kept) actions.push(kept);
    });
    data.actions = actions;
  }
  normalized.data = data;
  return normalized;
}

/**
 * Refresh-failure message.
 * A native Error or an object message is kept. Empty and missing messages are null.
 * code and data are ignored.
 */
export function readLiveLessonRefreshMessage(failure: unknown): string | null {
  if (failure == null) {
    return null;
  }
  const message = failure instanceof Error ? failure.message : isRecord(failure) ? failure.message : undefined;
  return typeof message === "string" && message !== "" ? message : null;
}

const refreshSecret = /token|secret|credential|password|api_key/i;

/**
 * persisted_result() refresh string.
 * A secret-shaped message becomes refresh_failed. Null and empty text stay null.
 */
export function sanitizeLiveLessonRefreshWarning(message: string | null): string | null {
  if (message === null || message === "") {
    return null;
  }
  return refreshSecret.test(message) ? "refresh_failed" : message;
}

/**
 * Keep the mutation outcome and attach a sanitized refresh warning beside it.
 * A refresh failure does not rewrite persisted, code, message, data, or projected_record.
 */
export function preserveLiveLessonMutation<T>(
  outcome: T,
  refreshFailure?: unknown
): { outcome: T; refreshWarning: string | null } {
  return {
    outcome,
    refreshWarning: sanitizeLiveLessonRefreshWarning(readLiveLessonRefreshMessage(refreshFailure)),
  };
}
