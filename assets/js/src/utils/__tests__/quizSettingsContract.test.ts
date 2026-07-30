import { describe, expect, it } from "@jest/globals";
import type {
  QuizCapabilities,
  QuizSettingsLoadInput,
  QuizSettings,
  QuizSettingsContract,
  RawQuizSettings,
} from "../../types/quiz";
import { createInitialQuizFormState } from "../../hooks/quiz/useQuizForm";
import {
  convertRawQuizSettingsToFormModel,
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

const loadSettings = (
  rawSettings: RawQuizSettings,
  options: Partial<Omit<QuizSettingsLoadInput, "rawSettings">> = {}
) =>
  convertRawQuizSettingsToFormModel({
    contract: "v4",
    contentType: "tutor_quiz",
    contentDripAvailable: false,
    hasProContentDripSettings: false,
    ...options,
    rawSettings,
  });

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

describe("Quiz Settings raw-to-effective loading", () => {
  it("loads Tutor 4 values and derives UI-only toggles", () => {
    const result = loadSettings({
      time_limit: { time_value: "25", time_type: "hours" },
      hide_quiz_time_display: "1",
      limit_attempts_allowed: 1,
      attempts_allowed: "3",
      pass_is_required: true,
      passing_grade: "75",
      max_questions_for_answer: "4",
      quiz_auto_start: "1",
      auto_start_delay: "7",
      question_layout_view: "single_question",
      enable_pagination: true,
      pagination_type: "number",
      enable_answer_reveal: 1,
      answers_reveal_duration: "10",
      hide_previous_button: "1",
      questions_order: "asc",
      hide_question_number_overview: 1,
      short_answer_characters_limit: "",
      open_ended_answer_characters_limit: "350",
    });

    expect(result.effectiveSettings).toMatchObject({
      enable_time_limit: true,
      time_limit: { time_value: 25, time_type: "hours" },
      hide_quiz_time_display: true,
      limit_attempts_allowed: true,
      attempts_allowed: 3,
      pass_is_required: true,
      passing_grade: 75,
      limit_questions_to_answer: true,
      max_questions_for_answer: 4,
      quiz_auto_start: true,
      auto_start_delay: 7,
      question_layout_view: "single_question",
      enable_pagination: true,
      pagination_type: "number",
      enable_answer_reveal: true,
      answers_reveal_duration: 10,
      hide_previous_button: true,
      questions_order: "asc",
      hide_question_number_overview: true,
      short_answer_characters_limit: "",
      open_ended_answer_characters_limit: 350,
    });
  });

  it("uses legacy feedback and pagination when Tutor 4 keys are absent", () => {
    const result = loadSettings({
      feedback_mode: "retry",
      question_layout_view: "question_pagination",
      question_pagination_style: "radio",
    });

    expect(result.effectiveSettings).toMatchObject({
      feedback_mode: "retry",
      limit_attempts_allowed: true,
      enable_answer_reveal: false,
      question_layout_view: "single_question",
      enable_pagination: true,
      pagination_type: "radio",
    });
  });

  it("uses key presence for conflicting Tutor 4 feedback and pagination flags", () => {
    const result = loadSettings({
      feedback_mode: "retry",
      limit_attempts_allowed: "0",
      enable_answer_reveal: "0",
      question_layout_view: "single_question",
      enable_pagination: "0",
      pagination_type: "number",
    });

    expect(result.effectiveSettings).toMatchObject({
      limit_attempts_allowed: false,
      enable_answer_reveal: false,
      enable_pagination: false,
      pagination_type: "number",
    });
  });

  it("always presents legacy question pagination as enabled Single Question pagination", () => {
    const result = loadSettings({
      question_layout_view: "question_pagination",
      enable_pagination: "0",
    });

    expect(result.effectiveSettings).toMatchObject({
      question_layout_view: "single_question",
      enable_pagination: true,
    });
  });

  it("keeps Tutor 4-only keys opaque under the legacy contract", () => {
    const result = loadSettings(
      {
        feedback_mode: "reveal",
        limit_attempts_allowed: "1",
        enable_answer_reveal: "0",
        question_layout_view: "single_question",
        enable_pagination: "1",
        pagination_type: "number",
        question_pagination_style: "radio",
        auto_start_delay: 10,
        answers_reveal_duration: 10,
        hide_previous_button: "1",
      },
      { contract: "legacy" }
    );

    expect(result.effectiveSettings).toMatchObject({
      feedback_mode: "reveal",
      limit_attempts_allowed: false,
      enable_answer_reveal: true,
      enable_pagination: false,
      pagination_type: "radio",
      auto_start_delay: 5,
      answers_reveal_duration: 5,
      hide_previous_button: false,
    });
  });

  it("produces defensive values for sparse and malformed storage", () => {
    const result = loadSettings({
      time_limit: { time_value: "not-a-number", time_type: "fortnights" },
      attempts_allowed: null,
      passing_grade: "",
      max_questions_for_answer: null,
      questions_order: "future-order",
      pagination_type: "future-style",
    });

    expect(result.rawSettings).toEqual({
      time_limit: { time_value: "not-a-number", time_type: "fortnights" },
      attempts_allowed: null,
      passing_grade: "",
      max_questions_for_answer: null,
      questions_order: "future-order",
      pagination_type: "future-style",
    });
    expect(result.effectiveSettings).toMatchObject({
      enable_time_limit: false,
      time_limit: { time_value: 0, time_type: "minutes" },
      attempts_allowed: 10,
      passing_grade: 80,
      limit_questions_to_answer: false,
      max_questions_for_answer: 10,
      question_layout_view: "single_question",
      questions_order: "rand",
      pagination_type: "shape",
    });
  });

  it.each([
    ["absent", {}, false, 10],
    ["zero", { max_questions_for_answer: 0 }, false, 10],
    ["negative", { max_questions_for_answer: -2 }, false, 10],
    ["positive", { max_questions_for_answer: "6" }, true, 6],
  ])(
    "reloads an H5P maximum that is %s without changing raw storage",
    (_label, rawSettings, enabled, companion) => {
      const result = loadSettings(rawSettings, { contentType: "tutor_h5p_quiz" });

      expect(result.effectiveSettings?.limit_questions_to_answer).toBe(enabled);
      expect(result.effectiveSettings?.max_questions_for_answer).toBe(companion);
      expect(result.rawSettings).toEqual(rawSettings);
    }
  );

  it("creates a defensive drip object when nested storage is absent", () => {
    const result = loadSettings({}, { contentDripAvailable: true });

    expect(result.rawSettings).toEqual({});
    expect(result.effectiveSettings).toMatchObject({
      enable_time_limit: false,
      limit_attempts_allowed: false,
      limit_questions_to_answer: false,
      max_questions_for_answer: 10,
      question_layout_view: "single_question",
      enable_pagination: false,
    });
    expect(result.effectiveSettings?.content_drip_settings).toEqual({
      unlock_date: "",
      after_xdays_of_enroll: 0,
      prerequisites: [],
    });
    expect(result.rawSettings).not.toHaveProperty("content_drip_settings");
  });

  it("uses nested drip storage only when available and Pro meta is absent", () => {
    const rawSettings: RawQuizSettings = {
      content_drip_settings: {
        unlock_date: "2026-08-01",
        after_xdays_of_enroll: "4",
        prerequisites: ["12", 14, 0, "invalid"],
        future_nested: "keep",
      },
    };
    const result = loadSettings(rawSettings, {
      contentDripAvailable: true,
      hasProContentDripSettings: false,
    });

    expect(result.effectiveSettings?.content_drip_settings).toEqual({
      unlock_date: "2026-08-01",
      after_xdays_of_enroll: 4,
      prerequisites: [12, 14],
    });
    expect(result.rawSettings).toEqual(rawSettings);
  });

  it("treats present empty Pro meta as the complete drip authority", () => {
    const result = loadSettings(
      {
        content_drip_settings: {
          unlock_date: "nested-date",
          after_xdays_of_enroll: 9,
          prerequisites: [22],
        },
      },
      {
        contentDripAvailable: true,
        hasProContentDripSettings: true,
        proContentDripSettings: [],
      }
    );

    expect(result.effectiveSettings?.content_drip_settings).toEqual({
      unlock_date: "",
      after_xdays_of_enroll: 0,
      prerequisites: [],
    });
  });

  it("does not fill missing fields in partial Pro meta from nested storage", () => {
    const result = loadSettings(
      {
        content_drip_settings: {
          unlock_date: "nested-date",
          after_xdays_of_enroll: 9,
          prerequisites: [22],
        },
      },
      {
        contentDripAvailable: true,
        hasProContentDripSettings: true,
        proContentDripSettings: { unlock_date: "pro-date" },
      }
    );

    expect(result.effectiveSettings?.content_drip_settings).toEqual({
      unlock_date: "pro-date",
      after_xdays_of_enroll: 0,
      prerequisites: [],
    });
  });

  it("keeps drip surfaces preservation-only when Content Drip is unavailable", () => {
    const rawSettings: RawQuizSettings = {
      content_drip_settings: { after_xdays_of_enroll: 9, future_nested: "keep" },
    };
    const result = loadSettings(rawSettings, {
      contentDripAvailable: false,
      hasProContentDripSettings: true,
      proContentDripSettings: { after_xdays_of_enroll: 4, future_pro: "keep" },
    });

    expect(result.effectiveSettings?.content_drip_settings).toEqual({
      unlock_date: "",
      after_xdays_of_enroll: 0,
      prerequisites: [],
    });
    expect(result.rawSettings).toEqual(rawSettings);
  });

  it("preserves raw quiz_type without using it to choose editor context", () => {
    const staleH5p = loadSettings({ quiz_type: "tutor_h5p_quiz" });
    const unknownIdentity = loadSettings({ quiz_type: "future_identity" });
    const explicitInteractive = loadSettings(
      { quiz_type: "future_identity" },
      { contentType: "tutor_h5p_quiz" }
    );

    expect(staleH5p.effectiveSettings).toEqual(unknownIdentity.effectiveSettings);
    expect(staleH5p.contentType).toBe("tutor_quiz");
    expect(explicitInteractive.contentType).toBe("tutor_h5p_quiz");
    expect(staleH5p.rawSettings.quiz_type).toBe("tutor_h5p_quiz");
    expect(explicitInteractive.rawSettings.quiz_type).toBe("future_identity");
  });

  it("never mutates the loaded raw object or its known nested objects", () => {
    const rawSettings: RawQuizSettings = {
      time_limit: { time_value: "12", time_type: "minutes", future_time: "keep" },
      content_drip_settings: {
        after_xdays_of_enroll: "3",
        future_nested: ["keep"],
      },
      future_top_level: { keep: true },
    };
    const before = JSON.parse(JSON.stringify(rawSettings));
    const result = loadSettings(rawSettings, { contentDripAvailable: true });

    expect(rawSettings).toEqual(before);
    expect(result.rawSettings).toEqual(before);
    expect(result.rawSettings).not.toBe(rawSettings);
    expect(result.rawSettings.time_limit).not.toBe(rawSettings.time_limit);
    expect(result.rawSettings.content_drip_settings).not.toBe(rawSettings.content_drip_settings);
  });

  it("fails closed while retaining raw storage for an unavailable contract", () => {
    const rawSettings: RawQuizSettings = {
      quiz_type: "tutor_h5p_quiz",
      future_top_level: "keep",
    };
    const result = loadSettings(rawSettings, { contract: "unavailable" });

    expect(result.effectiveSettings).toBeNull();
    expect(result.rawSettings).toEqual(rawSettings);
    expect(result.dirtyGroups.size).toBe(0);
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
    expect(state.effectiveSettings).toMatchObject({
      enable_time_limit: false,
      limit_attempts_allowed: false,
      limit_questions_to_answer: false,
      max_questions_for_answer: 10,
      question_layout_view: "single_question",
    });
    expect(state.dirtySettingsGroups.size).toBe(0);
  });

  it("keeps loaded settings and raw storage separate from effective precedence", () => {
    const loadedSettings = {
      feedback_mode: "retry",
      limit_attempts_allowed: "0",
      question_layout_view: "question_pagination",
      enable_pagination: "0",
      time_limit: { time_value: "8", time_type: "minutes", future_time: "keep" },
      content_drip_settings: { after_xdays_of_enroll: "3", future_nested: "keep" },
      quiz_type: "tutor_h5p_quiz",
      future_top_level: { keep: true },
    } as unknown as QuizSettings;
    const before = JSON.parse(JSON.stringify(loadedSettings));
    const state = createInitialQuizFormState({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      initialData: { quiz_option: loadedSettings },
      contentDripAvailable: true,
    });

    expect(loadedSettings).toEqual(before);
    expect(state.settings).toBe(loadedSettings);
    expect(state.rawSettings).toEqual(before);
    expect(state.rawSettings).not.toBe(loadedSettings);
    expect(state.rawSettings.time_limit).not.toBe(loadedSettings.time_limit);
    expect(state.effectiveSettings).toMatchObject({
      enable_time_limit: true,
      time_limit: { time_value: 8, time_type: "minutes" },
      feedback_mode: "retry",
      limit_attempts_allowed: false,
      question_layout_view: "single_question",
      enable_pagination: true,
      content_drip_settings: { after_xdays_of_enroll: 3 },
    });
    expect(state.contentType).toBe("tutor_quiz");
    expect(state.rawSettings.quiz_type).toBe("tutor_h5p_quiz");
  });

  it("forwards strict Pro drip precedence into existing form initialization", () => {
    const loadedSettings = {
      content_drip_settings: {
        unlock_date: "nested-date",
        after_xdays_of_enroll: 9,
        prerequisites: [22],
      },
    } as QuizSettings;
    const state = createInitialQuizFormState({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      initialData: { quiz_option: loadedSettings },
      contentDripAvailable: true,
      hasProContentDripSettings: true,
      proContentDripSettings: { unlock_date: "pro-date" },
    });

    expect(state.effectiveSettings?.content_drip_settings).toEqual({
      unlock_date: "pro-date",
      after_xdays_of_enroll: 0,
      prerequisites: [],
    });
    expect(state.rawSettings.content_drip_settings).toEqual(loadedSettings.content_drip_settings);
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
