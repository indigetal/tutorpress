import { describe, expect, it } from "@jest/globals";
import { normalizeZoomUsersBody, normalizeZoomUsersError, selectedZoomHost, zoomUsersRequest } from "../liveLessonZoomUsers";

const usersPath = "/tutorpress/v1/live-lessons/zoom/users";

describe("zoom users request", () => {
  it("requires a positive course id only when the flag is strictly true", () => {
    expect(zoomUsersRequest(11, true)).toEqual({ path: `${usersPath}?course_id=11` });
    expect(zoomUsersRequest(0, true)).toBeNull();
    expect(zoomUsersRequest(null, true)).toBeNull();
    expect(zoomUsersRequest(undefined, true)).toBeNull();
  });

  it("keeps the legacy optional course_id for every other flag", () => {
    for (const enabled of [false, undefined, "true"] as const) {
      expect(zoomUsersRequest(11, enabled)).toEqual({ path: `${usersPath}?course_id=11` });
      expect(zoomUsersRequest(undefined, enabled)).toEqual({ path: usersPath });
    }
  });
});

const envelopeMessage = "Zoom users retrieved successfully.";

describe("zoom users body", () => {
  it("keeps a safe users row and drops an empty id or email", () => {
    expect(
      normalizeZoomUsersBody({
        message: envelopeMessage,
        data: {
          users: [
            {
              id: "h1",
              email: "a@example.com",
              first_name: "Ada",
              last_name: "Lovelace",
              instructor_name: "Ada Lovelace",
            },
            { id: "", email: "skip@example.com", first_name: "Skip", last_name: "Me" },
            { id: "h2", email: "", first_name: "No", last_name: "Mail" },
          ],
        },
      })
    ).toEqual({ hosts: [{ id: "h1", email: "a@example.com", label: "Ada Lovelace" }], message: null });
  });

  it("treats hosts and empty lists as rows with no failure", () => {
    expect(
      normalizeZoomUsersBody({
        message: envelopeMessage,
        data: { outcome: "hosts", hosts: [{ id: "h1", email: "a@example.com", name: "Ada" }] },
      })
    ).toEqual({ hosts: [{ id: "h1", email: "a@example.com", label: "Ada" }], message: null });
    expect(normalizeZoomUsersBody({ message: envelopeMessage, data: { outcome: "empty", hosts: [] } })).toEqual({
      hosts: [],
      message: null,
    });
    expect(normalizeZoomUsersBody({ message: envelopeMessage, data: { users: [] } })).toEqual({
      hosts: [],
      message: null,
    });
    expect(normalizeZoomUsersBody({})).toEqual({ hosts: [], message: null });
  });

  it("uses one failure string and does not merge users with hosts", () => {
    for (const outcome of ["pre_dispatch_failure", "definite_rejection", "uncertain"] as const) {
      expect(
        normalizeZoomUsersBody({
          message: envelopeMessage,
          data: { outcome, hosts: [{ id: "h1", email: "a@example.com", name: "Ada" }] },
        })
      ).toEqual({ hosts: [], message: "Failed to load Zoom users" });
    }
    expect(
      normalizeZoomUsersBody({
        message: envelopeMessage,
        data: {
          outcome: "hosts",
          users: [
            {
              id: "u1",
              email: "u@example.com",
              first_name: "User",
              last_name: "One",
              instructor_name: "User One",
            },
          ],
          hosts: [{ id: "h1", email: "h@example.com", name: "Host" }],
        },
      })
    ).toEqual({ hosts: [{ id: "u1", email: "u@example.com", label: "User One" }], message: null });
  });
});

describe("zoom users error", () => {
  it("keeps code and a non-empty message and leaves data off", () => {
    expect(
      normalizeZoomUsersError({ code: "zoom_api_error", message: "Network down", data: { token: "secret" } })
    ).toEqual({ code: "zoom_api_error", message: "Network down" });
    expect(normalizeZoomUsersError(new Error(""))).toEqual({ code: null, message: "Failed to load Zoom users" });
  });
});

describe("selected zoom host", () => {
  const hosts = [{ id: "h1", email: "a@example.com", label: "Ada" }];

  it("keeps a listed id, clears a stale id, and fills only an empty field", () => {
    expect(selectedZoomHost("h1", hosts)).toBe("h1");
    expect(selectedZoomHost("abc", hosts)).toBe("");
    expect(selectedZoomHost("", hosts)).toBe("h1");
    expect(selectedZoomHost("", [])).toBe("");
  });
});
