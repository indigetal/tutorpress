import { describe, expect, it, jest } from "@jest/globals";
import { select } from "@wordpress/data";
import { Topic } from "../../../../types/curriculum";
import { reconcileFetchedTopics, refreshTopicsAfterSettledMutation } from "../topicRefresh";

jest.mock("@wordpress/data", () => ({
  select: jest.fn(),
}));

const mockedSelect = select as unknown as {
  mockReturnValue: (value: { getTopics: () => Topic[] }) => void;
};

const courseId = 9;

const topicsFetch = {
  type: "API_FETCH",
  request: { path: `/tutorpress/v1/topics?course_id=${courseId}` },
};

const openRefresh = () => {
  const generator = refreshTopicsAfterSettledMutation(courseId);
  expect(generator.next().value).toEqual(topicsFetch);
  return generator;
};

const topic = (id: number, overrides: Partial<Topic> = {}): Topic => ({
  id,
  title: `Topic ${id}`,
  menu_order: id,
  isCollapsed: true,
  contents: [],
  ...overrides,
});

describe("reconcileFetchedTopics", () => {
  it("preserves expanded state for the same topic ID", () => {
    const current = [topic(1, { isCollapsed: false })];
    const fetched = [topic(1, { isCollapsed: true, title: "Fetched" })];

    expect(reconcileFetchedTopics(current, fetched)[0].isCollapsed).toBe(false);
  });

  it("preserves collapsed state for the same topic ID", () => {
    const current = [topic(1, { isCollapsed: true })];
    const fetched = [topic(1, { isCollapsed: false })];

    expect(reconcileFetchedTopics(current, fetched)[0].isCollapsed).toBe(true);
  });

  it("defaults newly discovered topics to collapsed", () => {
    const current = [topic(1, { isCollapsed: false })];
    const fetched = [topic(1, { isCollapsed: false }), topic(2, { isCollapsed: false })];

    const result = reconcileFetchedTopics(current, fetched);
    expect(result[0].isCollapsed).toBe(false);
    expect(result[1].isCollapsed).toBe(true);
  });

  it("drops removed topics instead of carrying them forward", () => {
    const current = [topic(1, { isCollapsed: false }), topic(2)];
    const fetched = [topic(2)];

    const result = reconcileFetchedTopics(current, fetched);
    expect(result.map((item) => item.id)).toEqual([2]);
  });

  it("follows fetched order while preserving collapse by ID", () => {
    const current = [topic(1, { isCollapsed: false, menu_order: 1 }), topic(2, { isCollapsed: true, menu_order: 2 })];
    const fetched = [topic(2, { menu_order: 1 }), topic(1, { menu_order: 2 })];

    const result = reconcileFetchedTopics(current, fetched);
    expect(result.map((item) => item.id)).toEqual([2, 1]);
    expect(result[0].isCollapsed).toBe(true);
    expect(result[1].isCollapsed).toBe(false);
  });

  it("keeps fetched content, title, and status authoritative", () => {
    const current = [
      topic(1, {
        isCollapsed: false,
        title: "Stale",
        contents: [{ id: 10, title: "Old", type: "lesson", status: "draft", topic_id: 1, order: 0 }],
      }),
    ];
    const fetched = [
      topic(1, {
        isCollapsed: true,
        title: "Fresh",
        contents: [{ id: 20, title: "New", type: "lesson", status: "publish", topic_id: 1, order: 0 }],
      }),
    ];

    const result = reconcileFetchedTopics(current, fetched)[0];
    expect(result.isCollapsed).toBe(false);
    expect(result.title).toBe("Fresh");
    expect(result.contents).toEqual(fetched[0].contents);
  });
});

describe("refreshTopicsAfterSettledMutation", () => {
  it("reconciles a successful fetch and returns null", () => {
    mockedSelect.mockReturnValue({
      getTopics: () => [topic(1, { isCollapsed: false, title: "Stale" })],
    });
    const generator = openRefresh();
    const listed = generator.next({
      success: true,
      message: "Topics retrieved successfully.",
      data: [
        {
          id: 1,
          title: "Fresh",
          content: "body",
          menu_order: 1,
          status: "publish",
          isCollapsed: true,
          contents: [
            {
              id: 20,
              title: "Meet",
              type: "meet_lesson",
              status: "publish",
              menu_order: 4,
              order: 7,
              topic_id: 99,
              sync_state: "pending",
            },
          ],
        },
        {
          id: 2,
          title: "Added",
          content: "",
          menu_order: 2,
          status: "draft",
          isCollapsed: false,
          contents: [],
        },
      ],
    });

    expect(listed.value).toEqual({
      type: "SET_TOPICS",
      payload: [
        {
          id: 1,
          title: "Fresh",
          content: "body",
          menu_order: 1,
          status: "publish",
          isCollapsed: false,
          contents: [
            {
              id: 20,
              title: "Meet",
              type: "meet_lesson",
              status: "publish",
              menu_order: 4,
              order: 0,
              topic_id: 1,
              sync_state: "pending",
            },
          ],
        },
        {
          id: 2,
          title: "Added",
          content: "",
          menu_order: 2,
          status: "draft",
          isCollapsed: true,
          contents: [],
        },
      ],
    });
    expect(generator.next()).toEqual({ value: null, done: true });
    expect(select).toHaveBeenCalledWith("tutorpress/curriculum");
  });

  it("returns the unsuccessful message and yields no SET_TOPICS", () => {
    const settled = openRefresh().next({ success: false, message: "Could not load", data: [] });
    expect(settled).toEqual({ value: "Could not load", done: true });
  });

  it("keeps a thrown object message and yields no SET_TOPICS", () => {
    const settled = openRefresh().throw({ code: "fetch_error", message: "Network down", data: { token: "secret" } });
    expect(settled).toEqual({ value: "Network down", done: true });
  });

  it("replaces a secret-shaped thrown message and yields no SET_TOPICS", () => {
    const settled = openRefresh().throw({ message: "bad token value" });
    expect(settled).toEqual({ value: "refresh_failed", done: true });
  });

  it("returns null for an empty or missing thrown message and yields no SET_TOPICS", () => {
    expect(openRefresh().throw({ message: "" })).toEqual({ value: null, done: true });
    expect(openRefresh().throw({ code: "fetch_error", data: { status: 500 } })).toEqual({ value: null, done: true });
  });
});
