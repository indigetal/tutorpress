import { describe, expect, it } from "@jest/globals";
import {
  addZoomDuration,
  filterApprovedTimezones,
  hydrateWallClock,
  hydrateZoomDuration,
  localizedTimezoneOptions,
  serializeWallClock,
  zoomDuration,
} from "../liveLessonDateTime";

describe("live lesson wall-clock serialization", () => {
  it("copies the date and maps 12-hour time to minute-precision text", () => {
    expect(serializeWallClock("2026-09-21", "3:04 PM")).toBe("2026-09-21 15:04:00");
    expect(serializeWallClock("2026-09-21", "12:00 AM")).toBe("2026-09-21 00:00:00");
    expect(serializeWallClock("2026-09-21", "12:00 PM")).toBe("2026-09-21 12:00:00");
    expect(serializeWallClock("2026-09-21", "11:05 AM")).toBe("2026-09-21 11:05:00");
    expect(serializeWallClock("2026-11-01", "1:30 AM")).toBe("2026-11-01 01:30:00");
    expect(serializeWallClock("2026-03-08", "2:30 AM")).toBe("2026-03-08 02:30:00");
    expect(serializeWallClock("2026-13-01", "3:04 PM")).toBeNull();
    expect(serializeWallClock("2026-09-21", "15:04")).toBeNull();
    expect(serializeWallClock("2026-09-21", "0:30 AM")).toBeNull();
    expect(serializeWallClock("2026-09-21", "3:60 PM")).toBeNull();
  });
  it("hydrates wall-clock text, filters the server list, and retains zoom units", () => {
    expect(hydrateWallClock("2026-09-21 15:04:00")).toEqual({ date: "2026-09-21", time: "3:04 PM" });
    expect(hydrateWallClock("2026-09-21 00:00:00")).toEqual({ date: "2026-09-21", time: "12:00 AM" });
    expect(hydrateWallClock("2026-09-21 12:00:00")).toEqual({ date: "2026-09-21", time: "12:00 PM" });
    expect(hydrateWallClock("2026-11-01 01:30:00")).toEqual({ date: "2026-11-01", time: "1:30 AM" });
    expect(hydrateWallClock("2026-03-08 02:30:00")).toEqual({ date: "2026-03-08", time: "2:30 AM" });
    expect(hydrateWallClock("2026-09-21 15:04:01")).toBeNull();
    expect(filterApprovedTimezones([{ value: "UTC" }, { value: "CET" }, { value: "Canada/Atlantic" }, { value: "Etc/Greenwich" }], ["UTC", "Canada/Atlantic", "Etc/Greenwich"])).toEqual([{ value: "UTC" }, { value: "Canada/Atlantic" }, { value: "Etc/Greenwich" }]);
    expect(filterApprovedTimezones([{ value: "CET" }, { value: "SST" }], ["CET", "UTC"])).toEqual([{ value: "CET" }]);
    expect(zoomDuration(8, "hours")).toEqual({ value: 8, unit: "hours" });
    expect(zoomDuration(480, "minutes")).toEqual({ value: 480, unit: "minutes" });
    expect(zoomDuration(9, "hours")).toBeNull();
    expect(zoomDuration(8, "hr")).toBeNull();
    expect(zoomDuration(40, "min")).toBeNull();
  });
  it("adds typed Zoom duration in calendar minutes", () => {
    expect(addZoomDuration("2026-10-13 15:04:00", 2, "hours")).toBe("2026-10-13 17:04:00");
    expect(addZoomDuration("2024-02-29 23:30:00", 2, "hours")).toBe("2024-03-01 01:30:00");
    expect(addZoomDuration("not-a-wall-clock", 2, "hours")).toBe("");
  });
  it("hydrates a Zoom duration from the emitted span", () => {
    expect(hydrateZoomDuration("2026-10-13 15:04:00", "2026-10-13 17:04:00", 2)).toEqual({ value: 2, unit: "hours" });
    expect(hydrateZoomDuration("2026-10-13 15:04:00", "2026-10-13 15:44:00", 40)).toEqual({ value: 40, unit: "minutes" });
    expect(hydrateZoomDuration("2026-10-13 15:04:00", "2026-10-13 15:04:00", Number.NaN)).toEqual({ value: 40, unit: "minutes" });
    expect(hydrateZoomDuration("2026-10-13 15:04:00", "2026-10-13 15:04:00", 0)).toEqual({ value: 40, unit: "minutes" });
    expect(hydrateZoomDuration("not-a-wall-clock", "2026-10-13 17:04:00", 2)).toEqual({ value: 2, unit: "minutes" });
  });
  it("round-trips wall-clock text and a typed zoom unit", () => {
    expect(hydrateWallClock(serializeWallClock("2026-09-21", "3:04 PM") ?? "")).toEqual({ date: "2026-09-21", time: "3:04 PM" });
    expect(hydrateWallClock(serializeWallClock("2026-09-21", "12:00 AM") ?? "")).toEqual({ date: "2026-09-21", time: "12:00 AM" });
    expect(hydrateWallClock(serializeWallClock("2026-09-21", "12:00 PM") ?? "")).toEqual({ date: "2026-09-21", time: "12:00 PM" });
    expect(hydrateWallClock(serializeWallClock("2026-11-01", "1:30 AM") ?? "")).toEqual({ date: "2026-11-01", time: "1:30 AM" });
    const hours = zoomDuration(2, "hours");
    const minutes = zoomDuration(40, "minutes");
    expect(hours).toEqual({ value: 2, unit: "hours" });
    expect(minutes).toEqual({ value: 40, unit: "minutes" });
    expect(hydrateZoomDuration("2026-10-13 15:04:00", addZoomDuration("2026-10-13 15:04:00", hours ? hours.value : 0, hours ? hours.unit : ""), hours ? hours.value : 0)).toEqual({ value: 2, unit: "hours" });
    expect(hydrateZoomDuration("2026-10-13 15:04:00", addZoomDuration("2026-10-13 15:04:00", minutes ? minutes.value : 0, minutes ? minutes.unit : ""), minutes ? minutes.value : 0)).toEqual({ value: 40, unit: "minutes" });
  });
});

describe("localizedTimezoneOptions", () => {
  it("returns an empty list when the localized list is missing or not an array", () => {
    expect(localizedTimezoneOptions(undefined)).toEqual([]);
    expect(localizedTimezoneOptions(null as unknown as undefined)).toEqual([]);
  });

  it("copies a supplied label and keeps CET or SST only when that pair is present", () => {
    expect(localizedTimezoneOptions([{ value: "America/New_York", label: "America/New_York" }])).toEqual([
      { value: "America/New_York", label: "America/New_York" },
    ]);
    expect(localizedTimezoneOptions([{ value: "UTC", label: "UTC" }])).toEqual([{ value: "UTC", label: "UTC" }]);
    expect(
      localizedTimezoneOptions([
        { value: "UTC", label: "UTC" },
        { value: "CET", label: "CET" },
      ])
    ).toEqual([
      { value: "UTC", label: "UTC" },
      { value: "CET", label: "CET" },
    ]);
    expect(localizedTimezoneOptions([{ value: "SST", label: "SST" }])).toEqual([{ value: "SST", label: "SST" }]);
  });
});
