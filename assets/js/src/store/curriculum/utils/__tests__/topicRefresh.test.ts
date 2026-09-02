import { describe, expect, it } from "@jest/globals";
import { Topic } from "../../../../types/curriculum";
import { reconcileFetchedTopics } from "../topicRefresh";

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
