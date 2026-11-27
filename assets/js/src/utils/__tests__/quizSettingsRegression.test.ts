import { describe, expect, it } from "@jest/globals";
import { convertQuizSettingsFormModelToPayload, convertRawQuizSettingsToFormModel } from "../quizSettingsContract";
import type { QuizEffectiveSettings, QuizSettingsContract, QuizSettingsDirtyGroup, RawQuizSettings } from "../../types/quiz";

const savedSettings = (
  contract: QuizSettingsContract,
  rawSettings: RawQuizSettings,
  dirty: QuizSettingsDirtyGroup,
  patch: Partial<QuizEffectiveSettings> = {}
) => {
  const loaded = convertRawQuizSettingsToFormModel({
    contract, contentType: "tutor_quiz", rawSettings,
    contentDripAvailable: false, hasProContentDripSettings: false,
  });
  const saved = convertQuizSettingsFormModelToPayload({
    ...loaded,
    effectiveSettings: loaded.effectiveSettings ? { ...loaded.effectiveSettings, ...patch } : null,
    dirtyGroups: new Set([dirty]), isNewQuiz: false, h5pPluginAvailable: false,
  });
  if (saved.status !== "ready") throw new Error(saved.reason);
  return saved.settings;
};

describe("quiz settings data-loss boundaries", () => {
  it("keeps an edited passing grade and untouched question_settings", () => {
    const settings = savedSettings("v4", { passing_grade: 70, question_settings: { keep: true } }, "passing_grade", { passing_grade: 90 });
    expect(settings).toMatchObject({ passing_grade: 90, question_settings: { keep: true } });
  });

  it("writes V4 retry feedback flags and drops feedback_mode", () => {
    const settings = savedSettings("v4", { feedback_mode: "retry" }, "legacy_feedback");
    expect(settings).toMatchObject({ limit_attempts_allowed: "1", enable_answer_reveal: "0" });
    expect(settings).not.toHaveProperty("feedback_mode");
  });

  it("stores turned-on legacy pagination on legacy wire keys", () => {
    const settings = savedSettings("legacy", { question_layout_view: "single_question", question_pagination_style: "number" }, "pagination", { question_layout_view: "single_question", enable_pagination: true });
    expect(settings).toMatchObject({ question_layout_view: "question_pagination", question_pagination_style: "number" });
    expect(settings).not.toHaveProperty("enable_pagination");
    expect(settings).not.toHaveProperty("pagination_type");
  });
});
