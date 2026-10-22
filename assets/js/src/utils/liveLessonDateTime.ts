const wallDate = /^([0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01]))$/;
const wallTime = /^((?:[1-9]|1[0-2])):([0-5][0-9]) (AM|PM)$/;

/** Serialize a YYYY-MM-DD date and 12-hour time to YYYY-MM-DD HH:mm:00. */
export function serializeWallClock(date: string, time: string): string | null {
  const dateMatch = wallDate.exec(date);
  const timeMatch = wallTime.exec(time);
  if (!dateMatch || !timeMatch) {
    return null;
  }
  const hour = Number(timeMatch[1]);
  const hour24 = timeMatch[3] === "PM" ? (hour % 12) + 12 : hour % 12;
  return `${dateMatch[1]} ${String(hour24).padStart(2, "0")}:${timeMatch[2]}:00`;
}
const wallClock = /^([0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01])) ([01][0-9]|2[0-3]):([0-5][0-9]):00$/;

/** Hydrate YYYY-MM-DD HH:mm:00 text to a date string and 12-hour time. */
export function hydrateWallClock(wall: string): { date: string; time: string } | null {
  const match = wallClock.exec(wall);
  if (!match) {
    return null;
  }
  const hour24 = Number(match[2]);
  return { date: match[1], time: `${hour24 % 12 || 12}:${match[3]} ${hour24 < 12 ? "AM" : "PM"}` };
}
/** Copy localized timezone options. A missing or non-array list is empty. */
export function localizedTimezoneOptions(
  localized: readonly { value: string; label: string }[] | undefined
): { value: string; label: string }[] {
  if (!Array.isArray(localized)) {
    return [];
  }
  return localized.map((option) => ({ value: option.value, label: option.label }));
}
/** Keep options whose values are present in the caller-supplied server list. */
export function filterApprovedTimezones<T extends { value: string }>(options: readonly T[], approved: readonly string[]): T[] {
  const allowed = new Set(approved);
  return options.filter((option) => allowed.has(option.value));
}
/** Typed Zoom duration. minutes is 1..480 and hours is 1..8. */
export function zoomDuration(value: number, unit: string): { value: number; unit: "minutes" | "hours" } | null {
  if (Number.isInteger(value) && ((unit === "minutes" && value >= 1 && value <= 480) || (unit === "hours" && value >= 1 && value <= 8))) {
    return { value, unit };
  }
  return null;
}

export function wallClockMinutes(wall: string): number {
  const match = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):00$/.exec(wall);
  if (!match) {
    return 0;
  }
  const year = Number(match[1]);
  let month = Number(match[2]);
  const day = Number(match[3]);
  const hour = Number(match[4]);
  const minute = Number(match[5]);
  const shiftedYear = year - (month <= 2 ? 1 : 0);
  month += month > 2 ? -3 : 9;
  const era = Math.floor((shiftedYear >= 0 ? shiftedYear : shiftedYear - 399) / 400);
  const yearOfEra = shiftedYear - era * 400;
  const dayOfYear = Math.floor((153 * month + 2) / 5) + day - 1;
  const dayOfEra = yearOfEra * 365 + Math.floor(yearOfEra / 4) - Math.floor(yearOfEra / 100) + dayOfYear;
  return (era * 146097 + dayOfEra - 719468) * 1440 + hour * 60 + minute;
}

function wallClockFromMinutes(total: number): string {
  const dayMinutes = ((total % 1440) + 1440) % 1440;
  const z = Math.floor(total / 1440) + 719468;
  const era = Math.floor((z >= 0 ? z : z - 146096) / 146097);
  const doe = z - era * 146097;
  const yoe = Math.floor((doe - Math.floor(doe / 1460) + Math.floor(doe / 36524) - Math.floor(doe / 146096)) / 365);
  const y = yoe + era * 400;
  const doy = doe - (365 * yoe + Math.floor(yoe / 4) - Math.floor(yoe / 100));
  const mp = Math.floor((5 * doy + 2) / 153);
  const day = doy - Math.floor((153 * mp + 2) / 5) + 1;
  const month = mp + (mp < 10 ? 3 : -9);
  const year = y + (month <= 2 ? 1 : 0);
  const hour = Math.floor(dayMinutes / 60);
  const minute = dayMinutes % 60;
  return `${String(year).padStart(4, "0")}-${String(month).padStart(2, "0")}-${String(day).padStart(2, "0")} ${String(hour).padStart(2, "0")}:${String(minute).padStart(2, "0")}:00`;
}

export function addZoomDuration(wall: string, duration: number, unit: string): string {
  if (!wallClock.exec(wall)) {
    return "";
  }
  const typed = zoomDuration(duration, unit);
  if (!typed) {
    return "";
  }
  const minutes = typed.unit === "hours" ? typed.value * 60 : typed.value;
  return wallClockFromMinutes(wallClockMinutes(wall) + minutes);
}

export function hydrateZoomDuration(start: string, end: string, duration: number): { value: number; unit: "minutes" | "hours" } {
  if (!Number.isInteger(duration) || duration < 1) {
    return { value: 40, unit: "minutes" };
  }
  if (!wallClock.exec(start) || !wallClock.exec(end)) {
    return { value: duration, unit: "minutes" };
  }
  const span = wallClockMinutes(end) - wallClockMinutes(start);
  if (span === duration * 60 && zoomDuration(duration, "hours")) {
    return { value: duration, unit: "hours" };
  }
  return { value: duration, unit: "minutes" };
}
