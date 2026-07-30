import { describe, expect, it } from "@jest/globals";
import type {
  QuizCapabilities,
  QuizSettings,
  QuizSettingsContract,
  RawQuizSettings,
} from "../../types/quiz";
import { createInitialQuizFormState } from "../../hooks/quiz/useQuizForm";
import {
  createNewQuizSettingsFormModel,
  QUIZ_SETTINGS_GROUP_FIELDS,
} from "../quizSettingsContract";

const createCapabilities = (contract: QuizSettingsContract): QuizCapabilities => {
  const isV4 = contract === "v4";
  const isLegacy = contract === "legacy";

  return {
    tutorActive: true,
    tutorVersion: isV4 ? "4.0.0" : isLegacy ? "3.9.15" : "",
    meetsSupportedFloor: contract !== "unavailable",
    hasNativeQuizTypes: true,
    learningMode: "modern",
    proActive: false,
    proNativeQuizSupport: false,
    supportsTempMaskDeletion: isV4,
    quizSettingsContract: contract,
    quizSettingsUnavailableReason: contract === "unavailable" ? "legacy_contract_unavailable" : "",
    supportsOrthogonalFeedback: isV4,
    supportsSeparatePagination: isV4,
    supportsV4TimingNavigation: isV4,
    supportsLegacyFeedbackLayout: isLegacy,
    supportsV4QuizContentDrip: isV4,
    questionTypes: [],
  };
};

describe("Quiz Settings defaults contract", () => {
  it("creates Tutor 4 defaults for a standard quiz", () => {
    const model = createNewQuizSettingsFormModel(createCapabilities("v4"), "tutor_quiz");

    expect(model).not.toBeNull();
    expect(model?.contract).toBe("v4");
    expect(model?.contentType).toBe("tutor_quiz");
    expect(model?.effectiveSettings).toMatchObject({
      enable_time_limit: false,
      limit_attempts_allowed: false,
      attempts_allowed: 10,
      limit_questions_to_answer: false,
      max_questions_for_answer: 10,
      auto_start_delay: 5,
      question_layout_view: "single_question",
      enable_pagination: false,
      pagination_type: "shape",
      enable_answer_reveal: false,
      answers_reveal_duration: 5,
    });
    expect(model?.rawSettings).toMatchObject({
      max_questions_for_answer: 0,
      limit_attempts_allowed: "0",
      enable_pagination: "0",
      enable_answer_reveal: "0",
    });
    expect(model?.rawSettings).not.toHaveProperty("enable_time_limit");
    expect(model?.rawSettings).not.toHaveProperty("limit_questions_to_answer");
  });

  it("uses Tutor 4 H5P layout and question maximum defaults", () => {
    const model = createNewQuizSettingsFormModel(createCapabilities("v4"), "tutor_h5p_quiz");

    expect(model?.effectiveSettings.question_layout_view).toBe("question_below_each_other");
    expect(model?.effectiveSettings.limit_questions_to_answer).toBe(false);
    expect(model?.effectiveSettings.max_questions_for_answer).toBe(10);
    expect(model?.rawSettings).toMatchObject({
      question_layout_view: "question_below_each_other",
      max_questions_for_answer: 0,
    });
    expect(model?.rawSettings).not.toHaveProperty("quiz_type");
  });

  it("creates legacy defaults without generated Tutor 4-only keys", () => {
    const model = createNewQuizSettingsFormModel(createCapabilities("legacy"), "tutor_quiz");
    const v4OnlyKeys = [
      "limit_attempts_allowed",
      "auto_start_delay",
      "enable_pagination",
      "pagination_type",
      "enable_answer_reveal",
      "answers_reveal_duration",
      "hide_previous_button",
    ];

    expect(model?.contract).toBe("legacy");
    expect(model?.rawSettings).toMatchObject({
      feedback_mode: "default",
      attempts_allowed: 0,
      question_layout_view: "",
    });
    v4OnlyKeys.forEach((key) => {
      expect(model?.rawSettings).not.toHaveProperty(key);
    });
  });

  it("does not choose defaults for an unavailable or missing contract", () => {
    expect(createNewQuizSettingsFormModel(createCapabilities("unavailable"), "tutor_quiz")).toBeNull();
    expect(createNewQuizSettingsFormModel(undefined, "tutor_quiz")).toBeNull();
  });

  it("keeps empty existing raw storage distinct from new defaults", () => {
    const existingRaw: RawQuizSettings = {};
    const model = createNewQuizSettingsFormModel(createCapabilities("v4"), "tutor_quiz");

    expect(existingRaw).toEqual({});
    expect(model?.rawSettings).not.toBe(existingRaw);
    expect(model?.rawSettings).not.toEqual(existingRaw);
  });

  it("accepts and retains unknown top-level and nested raw keys", () => {
    const raw: RawQuizSettings = {
      future_top_level: { enabled: true },
      content_drip_settings: {
        unlock_date: "",
        future_nested: ["preserve", 42],
      },
    };

    expect(raw.future_top_level).toEqual({ enabled: true });
    expect(raw.content_drip_settings?.future_nested).toEqual(["preserve", 42]);
  });

  it("defines inseparable and independent dirty groups explicitly", () => {
    expect(QUIZ_SETTINGS_GROUP_FIELDS.attempts).toEqual([
      "limit_attempts_allowed",
      "attempts_allowed",
    ]);
    expect(QUIZ_SETTINGS_GROUP_FIELDS.answer_reveal).toEqual([
      "enable_answer_reveal",
      "answers_reveal_duration",
    ]);
    expect(QUIZ_SETTINGS_GROUP_FIELDS.layout).toEqual(["question_layout_view"]);
    expect(QUIZ_SETTINGS_GROUP_FIELDS.pagination).toEqual([
      "enable_pagination",
      "pagination_type",
    ]);
    expect(QUIZ_SETTINGS_GROUP_FIELDS.drip_unlock_date).toEqual([
      "content_drip_settings.unlock_date",
    ]);
  });
});

describe("Quiz form contract context", () => {
  it("initializes standard and H5P forms from explicit caller context", () => {
    const standard = createInitialQuizFormState({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
    });
    const h5p = createInitialQuizFormState({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_h5p_quiz",
    });

    expect(standard.settingsContract).toBe("v4");
    expect(standard.contentType).toBe("tutor_quiz");
    expect(standard.effectiveSettings?.question_layout_view).toBe("single_question");
    expect(standard.effectiveSettings?.max_questions_for_answer).toBe(10);
    expect(h5p.contentType).toBe("tutor_h5p_quiz");
    expect(h5p.effectiveSettings?.question_layout_view).toBe("question_below_each_other");
    expect(h5p.effectiveSettings?.max_questions_for_answer).toBe(10);
  });

  it("preserves a sparse existing raw snapshot without adding defaults", () => {
    const sparseSettings = {} as QuizSettings;
    const state = createInitialQuizFormState({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      initialData: {
        post_title: "Sparse quiz",
        quiz_option: sparseSettings,
      },
    });

    expect(state.settings).toBe(sparseSettings);
    expect(state.rawSettings).toEqual({});
    expect(state.effectiveSettings).toBeNull();
    expect(state.dirtySettingsGroups.size).toBe(0);
  });

  it("retains unavailable as a distinct state without raw payload defaults", () => {
    const state = createInitialQuizFormState({
      capabilities: createCapabilities("unavailable"),
      contentType: "tutor_quiz",
    });

    expect(state.settingsContract).toBe("unavailable");
    expect(state.rawSettings).toEqual({});
    expect(state.effectiveSettings).toBeNull();
  });
});
