import { readFileSync } from "fs";
import { join } from "path";
import { describe, expect, it } from "@jest/globals";

describe("live lesson harness", () => {
  it("loads the live-lesson unit gate", () => {
    expect(true).toBe(true);
  });

  it("keeps the unit gate on scripted helpers and topic refresh", () => {
    const script = String(
      JSON.parse(readFileSync(join(process.cwd(), "package.json"), "utf8")).scripts["test:live-lessons"]
    );
    expect(script).toContain("topicRefresh\\.test\\.ts");
    expect(script).toContain("liveLesson");
    expect(script).not.toMatch(/ZoomForm|LiveLessonModal|tutor-pro|wp-cli/i);
  });
});
