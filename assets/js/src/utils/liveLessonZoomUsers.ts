export interface ZoomHostRow {
  id: string;
  email: string;
  label: string;
}

const zoomUsersPath = "/tutorpress/v1/live-lessons/zoom/users";
export const zoomHostLoadFailed = "Failed to load Zoom users";

const record = (value: unknown): Record<string, unknown> | null =>
  typeof value === "object" && value !== null && !Array.isArray(value) ? (value as Record<string, unknown>) : null;
const text = (value: unknown): string => (typeof value === "string" ? value : "");
const failedOutcome = (value: unknown): boolean =>
  value === "pre_dispatch_failure" || value === "definite_rejection" || value === "uncertain";

/** Strict true requires a positive course id. Every other flag keeps the optional legacy query. */
export function zoomUsersRequest(courseId: number | null | undefined, enabled: unknown): { path: string } | null {
  const id = typeof courseId === "number" && Number.isInteger(courseId) && courseId > 0 ? courseId : 0;
  if (enabled === true && id < 1) return null;
  return { path: id > 0 ? `${zoomUsersPath}?course_id=${id}` : zoomUsersPath };
}

function hostRows(list: unknown, labelOf: (row: Record<string, unknown>) => string): ZoomHostRow[] {
  const hosts: ZoomHostRow[] = [];
  if (!Array.isArray(list)) return hosts;
  list.forEach((entry) => {
    const row = record(entry);
    const id = text(row?.id);
    const email = text(row?.email);
    if (row && id !== "" && email !== "") hosts.push({ id, email, label: labelOf(row) });
  });
  return hosts;
}

/** Read data.users or data.hosts. The envelope message is unused. */
export function normalizeZoomUsersBody(body: unknown): { hosts: ZoomHostRow[]; message: string | null } {
  const data = record(record(body)?.data);
  if (!data) return { hosts: [], message: null };
  if (Array.isArray(data.users)) {
    return { hosts: hostRows(data.users, (row) => `${text(row.first_name)} ${text(row.last_name)}`.trim()), message: null };
  }
  if (Array.isArray(data.hosts) && (data.outcome === "hosts" || data.outcome === "empty")) {
    return { hosts: hostRows(data.hosts, (row) => text(row.name)), message: null };
  }
  return { hosts: [], message: failedOutcome(data.outcome) ? zoomHostLoadFailed : null };
}

/** Keep code and a non-empty message. data stays off. */
export function normalizeZoomUsersError(error: unknown): { code: string | null; message: string } {
  const row = error instanceof Error ? { message: error.message } : record(error);
  const message = text(row?.message);
  const code = row && "code" in row ? text(row.code) : "";
  return { code: code !== "" ? code : null, message: message !== "" ? message : zoomHostLoadFailed };
}

/** Keep a listed id. A stale id clears. The first row fills only an empty field. */
export function selectedZoomHost(host: string, hosts: ZoomHostRow[]): string {
  if (hosts.some((row) => row.id === host)) return host;
  if (host !== "") return "";
  return hosts[0]?.id ?? "";
}
