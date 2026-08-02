import { describe, expect, it } from "@jest/globals";
import { act, createElement, useState } from "react";
import { createRoot } from "react-dom/client";
import type {
  QuizCapabilities,
  QuizContentType,
  QuizEffectiveSettings,
  QuizForm,
  QuizSettingsLoadInput,
  QuizSettings,
  QuizSettingsContract,
  QuizSettingsDirtyGroup,
  QuizSettingsSaveResult,
  RawQuizSettings,
} from "../../types/quiz";
import {
  createInitialQuizFormState,
  useQuizForm,
  type UseQuizFormOptions,
  type UseQuizFormReturn,
} from "../../hooks/quiz/useQuizForm";
import {
  convertQuizSettingsFormModelToPayload,
  convertRawQuizSettingsToFormModel,
  createNewQuizSettingsFormModel,
  getQuizContentDripActiveControl,
  getQuizPrerequisiteSuggestions,
  isInteractiveQuizEditingAvailable,
  isRetryCapableQuizAttempts,
  QUIZ_SETTINGS_GROUP_FIELDS,
  quizPrerequisiteIdsToTokens,
  quizPrerequisiteTokensToIds,
  sanitizeQuizPrerequisiteIds,
  shouldBlockQuizSettingsEditing,
  shouldShowAnswerReveal,
  shouldShowAnswerRevealDuration,
  shouldShowAutoStartDelay,
  shouldShowCharacterLimitsFrame,
  shouldShowContentDripSettingsFrame,
  shouldShowHideCountdown,
  shouldShowHidePreviousButton,
  shouldShowHideQuestionNumber,
  shouldShowNavigationControls,
  shouldShowOpenEndedCharacterLimit,
  shouldShowPaginationControls,
  shouldShowPassIsRequired,
  shouldShowQuizContentDripAvailableAfterDays,
  shouldShowQuizContentDripEditor,
  shouldShowQuizContentDripModeFrame,
  shouldShowQuizContentDripPrerequisites,
  shouldShowQuizContentDripUnlockDate,
  shouldShowQuizScopeMaximumQuestions,
  shouldShowShortAnswerCharacterLimit,
  shouldShowTimingTimeLimit,
  quizHasOpenEndedQuestions,
  quizHasShortAnswerQuestions,
} from "../quizSettingsContract";
import { appendContentDripPostFieldsToFormData, buildTopLevelContentDripFormFields } from "../quizForm";
import { saveQuizResolver } from "../../store/curriculum";

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

interface SaveSettingsOptions {
  contract?: QuizSettingsContract;
  contentType?: QuizSettingsLoadInput["contentType"];
  dirtyGroups?: QuizSettingsDirtyGroup[];
  isNewQuiz?: boolean;
  h5pRuntimeAvailable?: boolean;
  updateEffective?: (settings: QuizEffectiveSettings) => void;
}

const saveSettings = (
  rawSettings: RawQuizSettings,
  {
    contract = "v4",
    contentType = "tutor_quiz",
    dirtyGroups = [],
    isNewQuiz = false,
    h5pRuntimeAvailable = true,
    updateEffective,
  }: SaveSettingsOptions = {}
): QuizSettingsSaveResult => {
  const loaded = loadSettings(rawSettings, { contract, contentType });
  const effectiveSettings = loaded.effectiveSettings
    ? {
        ...loaded.effectiveSettings,
        time_limit: { ...loaded.effectiveSettings.time_limit },
        content_drip_settings: { ...loaded.effectiveSettings.content_drip_settings },
      }
    : null;

  if (effectiveSettings && updateEffective) {
    updateEffective(effectiveSettings);
  }

  return convertQuizSettingsFormModelToPayload({
    contract,
    contentType,
    rawSettings,
    effectiveSettings,
    dirtyGroups: new Set(dirtyGroups),
    isNewQuiz,
    h5pRuntimeAvailable,
  });
};

const getReadySettings = (result: QuizSettingsSaveResult): RawQuizSettings => {
  expect(result.status).toBe("ready");
  if (result.status !== "ready") {
    throw new Error(`Expected a ready save result, received ${result.reason}`);
  }
  return result.settings;
};

const containsUndefined = (value: unknown): boolean => {
  if (Array.isArray(value)) {
    return value.some(containsUndefined);
  }
  if (value && typeof value === "object") {
    return Object.values(value).some((item) => item === undefined || containsUndefined(item));
  }
  return false;
};

const renderQuizFormHook = (options: UseQuizFormOptions) => {
  (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
  let current: UseQuizFormReturn | undefined;
  const container = document.createElement("div");
  const root = createRoot(container);

  const Harness = () => {
    current = useQuizForm(options);
    return null;
  };

  act(() => {
    root.render(createElement(Harness));
  });

  return {
    current: (): UseQuizFormReturn => {
      if (!current) {
        throw new Error("Quiz form hook did not render");
      }
      return current;
    },
    unmount: () => {
      act(() => root.unmount());
    },
  };
};

/** Same as renderQuizFormHook, but options can flip without remounting the hook instance. */
const renderQuizFormHookWithMutableOptions = (initialOptions: UseQuizFormOptions) => {
  (globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;
  let current: UseQuizFormReturn | undefined;
  let setOptions: ((next: UseQuizFormOptions) => void) | undefined;
  const container = document.createElement("div");
  const root = createRoot(container);

  const Harness = () => {
    const [options, updateOptions] = useState(initialOptions);
    setOptions = updateOptions;
    current = useQuizForm(options);
    return null;
  };

  act(() => {
    root.render(createElement(Harness));
  });

  return {
    current: (): UseQuizFormReturn => {
      if (!current) {
        throw new Error("Quiz form hook did not render");
      }
      return current;
    },
    updateOptions: (next: UseQuizFormOptions) => {
      if (!setOptions) {
        throw new Error("Quiz form options updater is unavailable");
      }
      act(() => {
        setOptions?.(next);
      });
    },
    unmount: () => {
      act(() => root.unmount());
    },
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

describe("Quiz Settings dirty-aware payload conversion", () => {
  it("preserves an existing raw snapshot exactly on a no-op save", () => {
    const rawSettings: RawQuizSettings = {
      passing_grade: "64",
      time_limit: { time_value: "0", time_type: "minutes", future_time: "keep" },
      content_drip_settings: {
        unlock_date: "2026-08-01",
        future_nested: ["keep", 42],
      },
      future_top_level: { enabled: true },
    };
    const before = JSON.parse(JSON.stringify(rawSettings));
    const payload = getReadySettings(saveSettings(rawSettings));

    expect(payload).toEqual(before);
    expect(rawSettings).toEqual(before);
    expect(payload).not.toBe(rawSettings);
    expect(payload.time_limit).not.toBe(rawSettings.time_limit);
    expect(payload.content_drip_settings).not.toBe(rawSettings.content_drip_settings);
  });

  it.each([
    ["passing grade", "passing_grade", (settings: QuizEffectiveSettings) => (settings.passing_grade = 72), {
      passing_grade: 72,
    }],
    [
      "question order",
      "question_order",
      (settings: QuizEffectiveSettings) => (settings.questions_order = "desc"),
      { questions_order: "desc" },
    ],
    [
      "question limit",
      "question_limit",
      (settings: QuizEffectiveSettings) => {
        settings.limit_questions_to_answer = true;
        settings.max_questions_for_answer = 6;
      },
      { max_questions_for_answer: 6 },
    ],
    [
      "time limit",
      "time_limit",
      (settings: QuizEffectiveSettings) => {
        settings.enable_time_limit = true;
        settings.time_limit = { time_value: 25, time_type: "hours" };
      },
      { time_limit: { time_value: 25, time_type: "hours", future_time: "keep" } },
    ],
    [
      "countdown visibility",
      "hide_countdown",
      (settings: QuizEffectiveSettings) => (settings.hide_quiz_time_display = true),
      { hide_quiz_time_display: "1" },
    ],
    [
      "auto start",
      "auto_start",
      (settings: QuizEffectiveSettings) => {
        settings.quiz_auto_start = true;
        settings.auto_start_delay = 7;
      },
      { quiz_auto_start: "1", auto_start_delay: 7 },
    ],
    [
      "question number visibility",
      "hide_question_number",
      (settings: QuizEffectiveSettings) => (settings.hide_question_number_overview = true),
      { hide_question_number_overview: "1" },
    ],
    [
      "short-answer limit",
      "short_answer_character_limit",
      (settings: QuizEffectiveSettings) => (settings.short_answer_characters_limit = ""),
      { short_answer_characters_limit: "" },
    ],
    [
      "open-ended limit",
      "open_ended_character_limit",
      (settings: QuizEffectiveSettings) => (settings.open_ended_answer_characters_limit = 320),
      { open_ended_answer_characters_limit: 320 },
    ],
    [
      "pass requirement",
      "pass_required",
      (settings: QuizEffectiveSettings) => (settings.pass_is_required = true),
      { pass_is_required: "1" },
    ],
    [
      "previous-button visibility",
      "hide_previous",
      (settings: QuizEffectiveSettings) => (settings.hide_previous_button = true),
      { hide_previous_button: "1" },
    ],
    [
      "unlock date",
      "drip_unlock_date",
      (settings: QuizEffectiveSettings) =>
        (settings.content_drip_settings.unlock_date = "2026-09-01T00:00:00"),
      { content_drip_settings: { unlock_date: "2026-09-01", future_nested: "keep" } },
    ],
    [
      "available-after days",
      "drip_available_after_days",
      (settings: QuizEffectiveSettings) =>
        (settings.content_drip_settings.after_xdays_of_enroll = 4),
      { content_drip_settings: { after_xdays_of_enroll: 4, future_nested: "keep" } },
    ],
    [
      "prerequisites",
      "drip_prerequisites",
      (settings: QuizEffectiveSettings) => (settings.content_drip_settings.prerequisites = [12, 14]),
      { content_drip_settings: { prerequisites: [12, 14], future_nested: "keep" } },
    ],
  ] as Array<
    [
      string,
      QuizSettingsDirtyGroup,
      (settings: QuizEffectiveSettings) => void,
      Partial<RawQuizSettings>,
    ]
  >)("writes only the dirty %s group", (_label, dirtyGroup, updateEffective, expected) => {
    const rawSettings: RawQuizSettings = {
      untouched: { value: "keep" },
      time_limit: { time_value: 0, time_type: "minutes", future_time: "keep" },
      content_drip_settings: { future_nested: "keep" },
    };
    const payload = getReadySettings(
      saveSettings(rawSettings, {
        dirtyGroups: [dirtyGroup],
        updateEffective,
      })
    );

    expect(payload).toMatchObject(expected);
    expect(payload.untouched).toEqual({ value: "keep" });
  });

  it.each(["attempts", "answer_reveal", "legacy_feedback"] as QuizSettingsDirtyGroup[])(
    "atomically materializes V4 feedback flags when %s is edited",
    (dirtyGroup) => {
      const payload = getReadySettings(
        saveSettings(
          {
            feedback_mode: "retry",
            attempts_allowed: "4",
            answers_reveal_duration: "5",
            future_top_level: "keep",
          },
          {
            dirtyGroups: [dirtyGroup],
            updateEffective: (settings) => {
              settings.limit_attempts_allowed = true;
              settings.attempts_allowed = 3;
              settings.enable_answer_reveal = true;
              settings.answers_reveal_duration = 7;
            },
          }
        )
      );

      expect(payload).toMatchObject({
        limit_attempts_allowed: "1",
        enable_answer_reveal: "1",
        future_top_level: "keep",
      });
      expect(payload).not.toHaveProperty("feedback_mode");
      if (dirtyGroup !== "answer_reveal") {
        expect(payload.attempts_allowed).toBe(3);
      }
      if (dirtyGroup !== "attempts") {
        expect(payload.answers_reveal_duration).toBe(7);
      }
    }
  );

  it.each(["layout", "pagination"] as QuizSettingsDirtyGroup[])(
    "atomically canonicalizes V4 layout and pagination when %s is edited",
    (dirtyGroup) => {
      const payload = getReadySettings(
        saveSettings(
          {
            question_layout_view: "question_pagination",
            question_pagination_style: "radio",
            future_top_level: "keep",
          },
          {
            dirtyGroups: [dirtyGroup],
            updateEffective: (settings) => {
              settings.question_layout_view = "single_question";
              settings.enable_pagination = false;
              settings.pagination_type = "number";
            },
          }
        )
      );

      expect(payload).toMatchObject({
        question_layout_view: "single_question",
        enable_pagination: "0",
        pagination_type: "number",
        future_top_level: "keep",
      });
      expect(payload).not.toHaveProperty("question_pagination_style");
    }
  );

  it("does not clear hidden dependent values during an unrelated edit", () => {
    const payload = getReadySettings(
      saveSettings(
        {
          question_layout_view: "question_below_each_other",
          enable_answer_reveal: "1",
          hide_question_number_overview: "1",
          enable_pagination: "1",
          hide_previous_button: "1",
        },
        {
          dirtyGroups: ["passing_grade"],
          updateEffective: (settings) => (settings.passing_grade = 90),
        }
      )
    );

    expect(payload).toMatchObject({
      passing_grade: 90,
      enable_answer_reveal: "1",
      hide_question_number_overview: "1",
      enable_pagination: "1",
      hide_previous_button: "1",
    });
  });

  it("writes legacy feedback without changing opaque V4 keys", () => {
    const payload = getReadySettings(
      saveSettings(
        {
          feedback_mode: "default",
          attempts_allowed: 10,
          limit_attempts_allowed: "0",
          enable_answer_reveal: "1",
          answers_reveal_duration: 10,
        },
        {
          contract: "legacy",
          dirtyGroups: ["legacy_feedback"],
          updateEffective: (settings) => {
            settings.feedback_mode = "retry";
            settings.attempts_allowed = 2;
          },
        }
      )
    );

    expect(payload).toEqual({
      feedback_mode: "retry",
      attempts_allowed: 2,
      limit_attempts_allowed: "0",
      enable_answer_reveal: "1",
      answers_reveal_duration: 10,
    });
  });

  it.each([
    ["Single Question without pagination", "single_question", false, "single_question"],
    ["Single Question with pagination", "single_question", true, "question_pagination"],
    ["Full Page", "question_below_each_other", true, "question_below_each_other"],
  ] as Array<
    [
      string,
      "single_question" | "question_below_each_other",
      boolean,
      "single_question" | "question_pagination" | "question_below_each_other",
    ]
  >)(
    "translates legacy layout for %s",
    (_label, layout, paginationEnabled, expectedLayout) => {
      const payload = getReadySettings(
        saveSettings(
          {
            enable_pagination: "0",
            pagination_type: "number",
            hide_previous_button: "1",
          },
          {
            contract: "legacy",
            dirtyGroups: ["layout"],
            updateEffective: (settings) => {
              settings.question_layout_view = layout;
              settings.enable_pagination = paginationEnabled;
              settings.pagination_type = "radio";
            },
          }
        )
      );

      expect(payload).toMatchObject({
        question_layout_view: expectedLayout,
        question_pagination_style: "radio",
        enable_pagination: "0",
        pagination_type: "number",
        hide_previous_button: "1",
      });
    }
  );

  it("does not generate Tutor 4-only keys for a new legacy quiz", () => {
    const payload = getReadySettings(
      saveSettings({}, { contract: "legacy", isNewQuiz: true })
    );
    const v4OnlyKeys = [
      "limit_attempts_allowed",
      "auto_start_delay",
      "enable_pagination",
      "pagination_type",
      "enable_answer_reveal",
      "answers_reveal_duration",
      "hide_previous_button",
    ];

    v4OnlyKeys.forEach((key) => expect(payload).not.toHaveProperty(key));
  });

  it("ignores V4-only dirty groups under the legacy contract", () => {
    const payload = getReadySettings(
      saveSettings(
        { future_top_level: "keep" },
        {
          contract: "legacy",
          dirtyGroups: ["attempts", "answer_reveal", "hide_previous"],
          updateEffective: (settings) => {
            settings.limit_attempts_allowed = true;
            settings.enable_answer_reveal = true;
            settings.hide_previous_button = true;
          },
        }
      )
    );

    expect(payload).toEqual({ future_top_level: "keep" });
  });

  it.each([
    ["unavailable contract", "unavailable", true, "settings_contract_unavailable"],
    ["legacy contract", "legacy", true, "interactive_v4_required"],
    ["missing H5P runtime", "v4", false, "h5p_runtime_unavailable"],
  ] as Array<[string, QuizSettingsContract, boolean, string]>)(
    "blocks Interactive conversion for %s without changing raw identity",
    (_label, contract, h5pRuntimeAvailable, reason) => {
      const rawSettings: RawQuizSettings = {
        quiz_type: "future_identity",
        future_top_level: "keep",
      };
      const result = saveSettings(rawSettings, {
        contract,
        contentType: "tutor_h5p_quiz",
        h5pRuntimeAvailable,
      });

      expect(result).toEqual({
        status: "blocked",
        reason,
        rawSettings,
      });
    }
  );

  it("enforces H5P identity for a valid Interactive conversion", () => {
    const payload = getReadySettings(
      saveSettings(
        { quiz_type: "future_identity", future_top_level: "keep" },
        { contentType: "tutor_h5p_quiz" }
      )
    );

    expect(payload).toEqual({
      quiz_type: "tutor_h5p_quiz",
      future_top_level: "keep",
    });
  });

  it.each([
    ["exact stale H5P identity", "tutor_h5p_quiz", undefined],
    ["unknown identity", "future_identity", "future_identity"],
    ["null identity", null, null],
  ] as Array<[string, string | null, string | null | undefined]>)(
    "applies standard identity policy for %s",
    (_label, rawIdentity, expectedIdentity) => {
      const payload = getReadySettings(
        saveSettings({ quiz_type: rawIdentity, future_top_level: "keep" })
      );

      if (expectedIdentity === undefined) {
        expect(payload).not.toHaveProperty("quiz_type");
      } else {
        expect(payload.quiz_type).toBe(expectedIdentity);
      }
      expect(payload.future_top_level).toBe("keep");
    }
  );

  it("serializes a new untouched Interactive maximum as zero", () => {
    const payload = getReadySettings(
      saveSettings({}, { contentType: "tutor_h5p_quiz", isNewQuiz: true })
    );

    expect(payload.max_questions_for_answer).toBe(0);
    expect(payload.quiz_type).toBe("tutor_h5p_quiz");
  });

  it.each([
    ["absent", {}, undefined],
    ["zero", { max_questions_for_answer: 0 }, 0],
    ["positive", { max_questions_for_answer: 7 }, 7],
  ] as Array<[string, RawQuizSettings, number | undefined]>)(
    "preserves an untouched existing Interactive maximum that is %s",
    (_label, rawMaximum, expectedMaximum) => {
      const payload = getReadySettings(
        saveSettings(rawMaximum, { contentType: "tutor_h5p_quiz" })
      );

      if (expectedMaximum === undefined) {
        expect(payload).not.toHaveProperty("max_questions_for_answer");
      } else {
        expect(payload.max_questions_for_answer).toBe(expectedMaximum);
      }
    }
  );

  it("writes an enabled Interactive maximum exactly", () => {
    const payload = getReadySettings(
      saveSettings(
        { max_questions_for_answer: 0 },
        {
          contentType: "tutor_h5p_quiz",
          dirtyGroups: ["question_limit"],
          updateEffective: (settings) => {
            settings.limit_questions_to_answer = true;
            settings.max_questions_for_answer = 6;
          },
        }
      )
    );

    expect(payload.max_questions_for_answer).toBe(6);
  });

  it("writes zero when the Interactive maximum is disabled", () => {
    const payload = getReadySettings(
      saveSettings(
        { max_questions_for_answer: 8 },
        {
          contentType: "tutor_h5p_quiz",
          dirtyGroups: ["question_limit"],
          updateEffective: (settings) => {
            settings.limit_questions_to_answer = false;
            settings.max_questions_for_answer = 8;
          },
        }
      )
    );

    expect(payload.max_questions_for_answer).toBe(0);
  });

  it("starts new payloads from contract defaults rather than an existing raw snapshot", () => {
    const payload = getReadySettings(
      saveSettings(
        {
          passing_grade: 12,
          future_top_level: "do-not-merge",
        },
        { isNewQuiz: true }
      )
    );

    expect(payload.passing_grade).toBe(80);
    expect(payload).not.toHaveProperty("future_top_level");
  });

  it("removes undefined values without dropping defined unknown data", () => {
    const rawSettings = {
      undefined_top_level: undefined,
      future_top_level: {
        keep: true,
        remove: undefined,
        values: [1, undefined, 2],
      },
      content_drip_settings: {
        future_nested: "keep",
        remove: undefined,
      },
    } as RawQuizSettings;
    const payload = getReadySettings(saveSettings(rawSettings));

    expect(containsUndefined(payload)).toBe(false);
    expect(payload).toEqual({
      future_top_level: {
        keep: true,
        values: [1, 2],
      },
      content_drip_settings: {
        future_nested: "keep",
      },
    });
  });
});

describe("Quiz Settings runtime save integration", () => {
  it("returns a ready standard form after dirty updates and preserves unrelated raw data", () => {
    window.tutorpressAddons = {
      h5p: true,
      h5p_plugin_active: false,
    } as typeof window.tutorpressAddons;
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      initialData: {
        ID: 41,
        post_title: " Existing quiz ",
        quiz_option: {
          feedback_mode: "retry",
          attempts_allowed: 4,
          passing_grade: 80,
          quiz_type: "tutor_h5p_quiz",
          future_top_level: { keep: true },
        } as unknown as QuizSettings,
      },
    });

    try {
      act(() => {
        hook.current().updateSettings({
          feedback_mode: "reveal",
          passing_grade: 75,
        });
      });

      const result = hook.current().getFormData([], false);
      expect(result.status).toBe("ready");
      if (result.status !== "ready") {
        throw new Error(`Expected ready result, received ${result.reason}`);
      }

      expect(result.formData.post_title).toBe("Existing quiz");
      expect(result.formData.quiz_option).toMatchObject({
        passing_grade: 75,
        limit_attempts_allowed: "0",
        enable_answer_reveal: "1",
        attempts_allowed: 4,
        future_top_level: { keep: true },
      });
      expect(result.formData.quiz_option).not.toHaveProperty("feedback_mode");
      expect(result.formData.quiz_option).not.toHaveProperty("quiz_type");
    } finally {
      hook.unmount();
    }
  });

  it("returns blocked then ready Interactive results from localized H5P runtime state", () => {
    window.tutorpressAddons = {
      h5p: true,
      h5p_plugin_active: false,
    } as typeof window.tutorpressAddons;
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_h5p_quiz",
      initialData: {
        ID: 42,
        post_title: "Interactive quiz",
        quiz_option: {
          quiz_type: "future_identity",
          future_top_level: "keep",
        } as unknown as QuizSettings,
      },
    });

    try {
      const blocked = hook.current().getFormData([], false);
      expect(blocked).toMatchObject({
        status: "blocked",
        reason: "h5p_runtime_unavailable",
        rawSettings: {
          quiz_type: "future_identity",
          future_top_level: "keep",
        },
      });
      expect(blocked).not.toHaveProperty("formData");

      window.tutorpressAddons = {
        h5p: true,
        h5p_plugin_active: true,
      } as typeof window.tutorpressAddons;
      const ready = hook.current().getFormData([], false);
      expect(ready.status).toBe("ready");
      if (ready.status !== "ready") {
        throw new Error(`Expected ready result, received ${ready.reason}`);
      }

      expect(ready.formData.quiz_option).toEqual({
        quiz_type: "tutor_h5p_quiz",
        future_top_level: "keep",
      });
      expect(ready.formData.quiz_option).not.toHaveProperty("max_questions_for_answer");
    } finally {
      hook.unmount();
    }
  });

  it("returns a blocked caller result for an unavailable settings contract", () => {
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("unavailable"),
      contentType: "tutor_quiz",
      initialData: {
        ID: 43,
        quiz_option: {
          quiz_type: "tutor_h5p_quiz",
          future_top_level: "keep",
        } as unknown as QuizSettings,
      },
    });

    try {
      expect(hook.current().getFormData([], false)).toEqual({
        status: "blocked",
        reason: "settings_contract_unavailable",
        rawSettings: {
          quiz_type: "tutor_h5p_quiz",
          future_top_level: "keep",
        },
      });
    } finally {
      hook.unmount();
    }
  });

  it("returns contract defaults plus dirty values for a new caller", () => {
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
    });

    try {
      act(() => {
        hook.current().updateSettings({ passing_grade: 65 });
      });
      const result = hook.current().getFormData([], true);

      expect(result.status).toBe("ready");
      if (result.status !== "ready") {
        throw new Error(`Expected ready result, received ${result.reason}`);
      }
      expect(result.formData.quiz_option).toMatchObject({
        passing_grade: 65,
        max_questions_for_answer: 0,
        question_layout_view: "single_question",
      });
    } finally {
      hook.unmount();
    }
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

  it("re-applies Pro drip when course drip becomes available after empty quiz_option load", () => {
    const capabilities = createCapabilities("v4");
    const hook = renderQuizFormHookWithMutableOptions({
      capabilities,
      contentType: "tutor_quiz",
      contentDripAvailable: false,
    });

    try {
      act(() => {
        hook.current().initializeWithData({
          post_title: "Empty option quiz",
          quiz_option: {} as QuizSettings,
          has_pro_content_drip_settings: true,
          pro_content_drip_settings: { unlock_date: "pro-date", future_pro: "keep" },
        });
      });

      expect(hook.current().formState.dripAuthorityApplied).toBe(false);
      expect(hook.current().formState.hasProContentDripSettings).toBe(true);
      expect(hook.current().formState.rawSettings).toEqual({});
      expect(hook.current().formState.settings.content_drip_settings?.unlock_date).not.toBe("pro-date");

      hook.updateOptions({
        capabilities,
        contentType: "tutor_quiz",
        contentDripAvailable: true,
      });

      expect(hook.current().formState.dripAuthorityApplied).toBe(true);
      expect(hook.current().formState.isDirty).toBe(false);
      expect(hook.current().formState.settings.content_drip_settings).toEqual({
        unlock_date: "pro-date",
        after_xdays_of_enroll: 0,
        prerequisites: [],
      });
      expect(hook.current().formState.effectiveSettings?.content_drip_settings).toEqual({
        unlock_date: "pro-date",
        after_xdays_of_enroll: 0,
        prerequisites: [],
      });
      expect(hook.current().formState.rawSettings).toEqual({});
    } finally {
      hook.unmount();
    }
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

describe("Quiz scope visibility gates", () => {
  it("treats unlimited or multi-attempt limits as retry-capable", () => {
    expect(isRetryCapableQuizAttempts(false, 0)).toBe(false);
    expect(isRetryCapableQuizAttempts(true, 1)).toBe(false);
    expect(isRetryCapableQuizAttempts(true, 0)).toBe(true);
    expect(isRetryCapableQuizAttempts(true, 2)).toBe(true);
  });

  it("shows Pass is required only for V4 sequential drip with retry-capable attempts", () => {
    const base = {
      contract: "v4" as const,
      contentDripAvailable: true,
      contentDripType: "unlock_sequentially",
      limitAttemptsAllowed: true,
      attemptsAllowed: 0,
      contentType: "tutor_quiz" as const,
    };

    expect(shouldShowPassIsRequired(base)).toBe(true);
    expect(shouldShowPassIsRequired({ ...base, contract: "legacy" })).toBe(false);
    expect(shouldShowPassIsRequired({ ...base, contentDripAvailable: false })).toBe(false);
    expect(shouldShowPassIsRequired({ ...base, contentDripType: "unlock_by_date" })).toBe(false);
    expect(shouldShowPassIsRequired({ ...base, attemptsAllowed: 1 })).toBe(false);
    expect(
      shouldShowPassIsRequired({
        ...base,
        contentType: "tutor_h5p_quiz",
        showAllSettings: false,
      })
    ).toBe(false);
    expect(
      shouldShowPassIsRequired({
        ...base,
        contentType: "tutor_h5p_quiz",
        showAllSettings: true,
      })
    ).toBe(true);
  });

  it("discloses Maximum Questions for Interactive only when showAllSettings is on", () => {
    expect(
      shouldShowQuizScopeMaximumQuestions({ contentType: "tutor_quiz", showAllSettings: false })
    ).toBe(true);
    expect(
      shouldShowQuizScopeMaximumQuestions({
        contentType: "tutor_h5p_quiz",
        showAllSettings: false,
      })
    ).toBe(false);
    expect(
      shouldShowQuizScopeMaximumQuestions({
        contentType: "tutor_h5p_quiz",
        showAllSettings: true,
      })
    ).toBe(true);
  });

  it("writes V4 attempts without emitting legacy feedback_mode", () => {
    const payload = getReadySettings(
      saveSettings(
        { feedback_mode: "retry", attempts_allowed: 4 },
        {
          dirtyGroups: ["attempts"],
          updateEffective: (settings) => {
            settings.limit_attempts_allowed = true;
            settings.attempts_allowed = 3;
          },
        }
      )
    );

    expect(payload).toMatchObject({
      limit_attempts_allowed: "1",
      attempts_allowed: 3,
    });
    expect(payload).not.toHaveProperty("feedback_mode");
  });

  it("does not emit V4 attempts keys when legacy Feedback is edited", () => {
    const payload = getReadySettings(
      saveSettings(
        { feedback_mode: "default", attempts_allowed: 0 },
        {
          contract: "legacy",
          dirtyGroups: ["legacy_feedback"],
          updateEffective: (settings) => {
            settings.feedback_mode = "retry";
            settings.attempts_allowed = 2;
            settings.limit_attempts_allowed = true;
          },
        }
      )
    );

    expect(payload).toEqual({
      feedback_mode: "retry",
      attempts_allowed: 2,
    });
    expect(payload).not.toHaveProperty("limit_attempts_allowed");
    expect(payload).not.toHaveProperty("enable_answer_reveal");
  });
});

describe("Timing visibility gates and serialization", () => {
  it("gates Time Limit, Hide countdown, and Auto Start delay by disclosure and contract", () => {
    expect(shouldShowTimingTimeLimit({ contentType: "tutor_quiz" })).toBe(true);
    expect(
      shouldShowTimingTimeLimit({ contentType: "tutor_h5p_quiz", showAllSettings: false })
    ).toBe(false);
    expect(
      shouldShowTimingTimeLimit({ contentType: "tutor_h5p_quiz", showAllSettings: true })
    ).toBe(true);

    expect(
      shouldShowHideCountdown({ enableTimeLimit: true, contentType: "tutor_quiz" })
    ).toBe(true);
    expect(
      shouldShowHideCountdown({ enableTimeLimit: false, contentType: "tutor_quiz" })
    ).toBe(false);
    expect(
      shouldShowHideCountdown({
        enableTimeLimit: true,
        contentType: "tutor_h5p_quiz",
        showAllSettings: false,
      })
    ).toBe(false);

    expect(shouldShowAutoStartDelay({ contract: "v4", quizAutoStart: true })).toBe(true);
    expect(shouldShowAutoStartDelay({ contract: "v4", quizAutoStart: false })).toBe(false);
    expect(shouldShowAutoStartDelay({ contract: "legacy", quizAutoStart: true })).toBe(false);
  });

  it("folds a disabled Time Limit into time_value 0 without emitting enable_time_limit", () => {
    const payload = getReadySettings(
      saveSettings(
        { time_limit: { time_value: 25, time_type: "hours", future_time: "keep" } },
        {
          dirtyGroups: ["time_limit"],
          updateEffective: (settings) => {
            settings.enable_time_limit = false;
            settings.time_limit = { time_value: 25, time_type: "hours" };
          },
        }
      )
    );

    expect(payload.time_limit).toEqual({
      time_value: 0,
      time_type: "hours",
      future_time: "keep",
    });
    expect(payload).not.toHaveProperty("enable_time_limit");
  });

  it("emits V4 auto_start_delay when dirty and preserves opaque legacy delay otherwise", () => {
    const v4Payload = getReadySettings(
      saveSettings(
        {},
        {
          dirtyGroups: ["auto_start"],
          updateEffective: (settings) => {
            settings.quiz_auto_start = true;
            settings.auto_start_delay = 7;
          },
        }
      )
    );
    expect(v4Payload).toMatchObject({ quiz_auto_start: "1", auto_start_delay: 7 });

    const zeroDelayPayload = getReadySettings(
      saveSettings(
        {},
        {
          dirtyGroups: ["auto_start"],
          updateEffective: (settings) => {
            settings.quiz_auto_start = true;
            settings.auto_start_delay = 0;
          },
        }
      )
    );
    expect(zeroDelayPayload).toMatchObject({ quiz_auto_start: "1", auto_start_delay: 0 });

    const legacyDirty = getReadySettings(
      saveSettings(
        { auto_start_delay: 10 },
        {
          contract: "legacy",
          dirtyGroups: ["auto_start"],
          updateEffective: (settings) => {
            settings.quiz_auto_start = true;
            settings.auto_start_delay = 7;
          },
        }
      )
    );
    expect(legacyDirty).toMatchObject({ quiz_auto_start: "1", auto_start_delay: 10 });

    const legacyOpaque = getReadySettings(
      saveSettings(
        { auto_start_delay: 10, quiz_auto_start: "0" },
        {
          contract: "legacy",
          dirtyGroups: ["passing_grade"],
          updateEffective: (settings) => {
            settings.passing_grade = 70;
          },
        }
      )
    );
    expect(legacyOpaque).toMatchObject({
      passing_grade: 70,
      auto_start_delay: 10,
      quiz_auto_start: "0",
    });
  });

  it("blocks save when Time Limit is enabled with a non-positive companion", () => {
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      initialData: { post_title: "Timing quiz" },
    });

    try {
      act(() => {
        hook.current().updateSettings({
          enable_time_limit: true,
          time_limit: { time_value: 0, time_type: "minutes" },
        });
      });
      expect(hook.current().formState.isValid).toBe(false);
      expect(hook.current().formState.errors.timeLimit).toBe(
        "Time limit must be greater than 0"
      );

      act(() => {
        hook.current().updateSettings({ enable_time_limit: false });
      });
      expect(hook.current().formState.errors.timeLimit).toBeUndefined();
      expect(hook.current().formState.isValid).toBe(true);
    } finally {
      hook.unmount();
    }
  });
});

describe("Navigation visibility gates and layout form wiring", () => {
  it("gates Navigation disclosure, Single Question dependents, and Reveal suppression", () => {
    expect(shouldShowNavigationControls({ contentType: "tutor_quiz" })).toBe(true);
    expect(
      shouldShowNavigationControls({ contentType: "tutor_h5p_quiz", showAllSettings: false })
    ).toBe(false);
    expect(
      shouldShowNavigationControls({ contentType: "tutor_h5p_quiz", showAllSettings: true })
    ).toBe(true);

    expect(
      shouldShowPaginationControls({
        questionLayoutView: "single_question",
        contentType: "tutor_quiz",
      })
    ).toBe(true);
    expect(
      shouldShowPaginationControls({
        questionLayoutView: "question_below_each_other",
        contentType: "tutor_quiz",
      })
    ).toBe(false);
    expect(
      shouldShowHideQuestionNumber({
        questionLayoutView: "single_question",
        contentType: "tutor_h5p_quiz",
        showAllSettings: false,
      })
    ).toBe(false);

    expect(
      shouldShowAnswerReveal({
        contract: "v4",
        questionLayoutView: "single_question",
        contentType: "tutor_quiz",
      })
    ).toBe(true);
    expect(
      shouldShowAnswerReveal({
        contract: "v4",
        questionLayoutView: "single_question",
        contentType: "tutor_h5p_quiz",
      })
    ).toBe(false);
    expect(
      shouldShowAnswerReveal({
        contract: "legacy",
        questionLayoutView: "single_question",
        contentType: "tutor_quiz",
      })
    ).toBe(false);
    expect(
      shouldShowAnswerRevealDuration({
        contract: "v4",
        questionLayoutView: "single_question",
        contentType: "tutor_quiz",
        enableAnswerReveal: true,
      })
    ).toBe(true);
    expect(
      shouldShowAnswerRevealDuration({
        contract: "v4",
        questionLayoutView: "single_question",
        contentType: "tutor_quiz",
        enableAnswerReveal: false,
      })
    ).toBe(false);

    expect(
      shouldShowHidePreviousButton({
        contract: "v4",
        questionLayoutView: "single_question",
        enablePagination: false,
        contentType: "tutor_quiz",
      })
    ).toBe(true);
    expect(
      shouldShowHidePreviousButton({
        contract: "v4",
        questionLayoutView: "single_question",
        enablePagination: true,
        contentType: "tutor_quiz",
      })
    ).toBe(false);
    expect(
      shouldShowHidePreviousButton({
        contract: "legacy",
        questionLayoutView: "single_question",
        enablePagination: false,
        contentType: "tutor_quiz",
      })
    ).toBe(false);
  });

  it("preserves V4 pagination when layout is edited to Single Question", () => {
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      initialData: {
        post_title: "Navigation quiz",
        quiz_option: {
          question_layout_view: "question_below_each_other",
          enable_pagination: true,
          pagination_type: "radio",
          hide_previous_button: true,
          hide_question_number_overview: true,
        } as unknown as QuizSettings,
      },
    });

    try {
      act(() => {
        hook.current().initializeWithData({
          post_title: "Navigation quiz",
          quiz_option: {
            question_layout_view: "question_below_each_other",
            enable_pagination: true,
            pagination_type: "radio",
            hide_previous_button: true,
            hide_question_number_overview: true,
          } as unknown as QuizSettings,
        });
      });

      act(() => {
        hook.current().updateSettings({ question_layout_view: "single_question" });
      });

      expect(hook.current().formState.settings.enable_pagination).toBe(true);
      expect(hook.current().formState.settings.pagination_type).toBe("radio");
      expect(hook.current().formState.settings.hide_previous_button).toBe(true);
      expect(hook.current().formState.settings.hide_question_number_overview).toBe(true);
      expect(hook.current().formState.dirtySettingsGroups.has("layout")).toBe(true);
      expect(hook.current().formState.dirtySettingsGroups.has("pagination")).toBe(false);
    } finally {
      hook.unmount();
    }
  });
});

describe("Character Limits visibility gates and empty/zero preservation", () => {
  const shortOnly = [{ question_type: "short_answer" as const }];
  const openOnly = [{ question_type: "open_ended" as const }];
  const both = [
    { question_type: "short_answer" as const },
    { question_type: "open_ended" as const },
  ];

  it("gates by question type and withholds Interactive entirely", () => {
    expect(quizHasShortAnswerQuestions(shortOnly)).toBe(true);
    expect(quizHasOpenEndedQuestions(openOnly)).toBe(true);
    expect(shouldShowCharacterLimitsFrame({ contentType: "tutor_quiz", questions: shortOnly })).toBe(
      true
    );
    expect(
      shouldShowCharacterLimitsFrame({
        contentType: "tutor_quiz",
        questions: [{ question_type: "true_false" }],
      })
    ).toBe(false);
    expect(
      shouldShowShortAnswerCharacterLimit({ contentType: "tutor_quiz", questions: openOnly })
    ).toBe(false);
    expect(
      shouldShowOpenEndedCharacterLimit({ contentType: "tutor_quiz", questions: shortOnly })
    ).toBe(false);
    [false, true].forEach((showAllSettings) => {
      expect(
        shouldShowCharacterLimitsFrame({
          contentType: "tutor_h5p_quiz",
          questions: both,
          showAllSettings,
        })
      ).toBe(false);
      expect(
        shouldShowShortAnswerCharacterLimit({
          contentType: "tutor_h5p_quiz",
          questions: both,
          showAllSettings,
        })
      ).toBe(false);
      expect(
        shouldShowOpenEndedCharacterLimit({
          contentType: "tutor_h5p_quiz",
          questions: both,
          showAllSettings,
        })
      ).toBe(false);
    });
  });

  it("preserves empty/zero on load, form overlay, dirty payload, and unrelated saves", () => {
    const loaded = loadSettings({
      short_answer_characters_limit: "",
      open_ended_answer_characters_limit: 0,
    });
    expect(loaded.effectiveSettings?.short_answer_characters_limit).toBe("");
    expect(loaded.effectiveSettings?.open_ended_answer_characters_limit).toBe(0);

    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
    });
    try {
      act(() => {
        hook.current().initializeWithData({
          post_title: "Character limits quiz",
          quiz_option: {
            short_answer_characters_limit: "",
            open_ended_answer_characters_limit: 0,
          } as unknown as QuizSettings,
        });
      });
      expect(hook.current().formState.settings.short_answer_characters_limit).toBe("");
      expect(hook.current().formState.settings.open_ended_answer_characters_limit).toBe(0);

      act(() => {
        hook.current().updateSettings({
          short_answer_characters_limit: "",
          open_ended_answer_characters_limit: 0,
        });
      });
      expect(hook.current().formState.dirtySettingsGroups.has("short_answer_character_limit")).toBe(
        true
      );
      expect(hook.current().formState.dirtySettingsGroups.has("open_ended_character_limit")).toBe(
        true
      );
    } finally {
      hook.unmount();
    }

    const emptyPayload = getReadySettings(
      saveSettings(
        { short_answer_characters_limit: 200, open_ended_answer_characters_limit: 500 },
        {
          dirtyGroups: ["short_answer_character_limit", "open_ended_character_limit"],
          updateEffective: (settings) => {
            settings.short_answer_characters_limit = "";
            settings.open_ended_answer_characters_limit = 0;
          },
        }
      )
    );
    expect(emptyPayload.short_answer_characters_limit).toBe("");
    expect(emptyPayload.open_ended_answer_characters_limit).toBe(0);

    const untouched = getReadySettings(
      saveSettings(
        {
          short_answer_characters_limit: "",
          open_ended_answer_characters_limit: 350,
          passing_grade: "80",
        },
        {
          dirtyGroups: ["passing_grade"],
          updateEffective: (settings) => {
            settings.passing_grade = 90;
          },
        }
      )
    );
    expect(untouched.short_answer_characters_limit).toBe("");
    expect(untouched.open_ended_answer_characters_limit).toBe(350);
    expect(untouched.passing_grade).toBe(90);
  });
});

describe("Interactive disclosure and Auto Start delay", () => {
  const sequentialPassRequired = {
    contract: "v4" as const,
    contentDripAvailable: true,
    contentDripType: "unlock_sequentially",
    limitAttemptsAllowed: true,
    attemptsAllowed: 0,
  };

  const disclosureGates = (showAllSettings: boolean) => [
    shouldShowQuizScopeMaximumQuestions({ contentType: "tutor_h5p_quiz", showAllSettings }),
    shouldShowTimingTimeLimit({ contentType: "tutor_h5p_quiz", showAllSettings }),
    shouldShowHideCountdown({
      enableTimeLimit: true,
      contentType: "tutor_h5p_quiz",
      showAllSettings,
    }),
    shouldShowNavigationControls({ contentType: "tutor_h5p_quiz", showAllSettings }),
    shouldShowPaginationControls({
      questionLayoutView: "single_question",
      contentType: "tutor_h5p_quiz",
      showAllSettings,
    }),
    shouldShowHideQuestionNumber({
      questionLayoutView: "single_question",
      contentType: "tutor_h5p_quiz",
      showAllSettings,
    }),
    shouldShowHidePreviousButton({
      contract: "v4",
      questionLayoutView: "single_question",
      enablePagination: false,
      contentType: "tutor_h5p_quiz",
      showAllSettings,
    }),
    shouldShowPassIsRequired({
      ...sequentialPassRequired,
      contentType: "tutor_h5p_quiz",
      showAllSettings,
    }),
    shouldShowContentDripSettingsFrame({
      contentType: "tutor_h5p_quiz",
      showAllSettings,
      contentDripUiAvailable: true,
    }),
  ];

  it("gates Interactive disclosure-controlled groups with showAllSettings", () => {
    expect(disclosureGates(false).every((visible) => visible === false)).toBe(true);
    expect(disclosureGates(true).every((visible) => visible === true)).toBe(true);

    // Standard ignores Interactive disclosure for supported groups.
    expect(shouldShowTimingTimeLimit({ contentType: "tutor_quiz", showAllSettings: false })).toBe(true);
    expect(
      shouldShowContentDripSettingsFrame({
        contentType: "tutor_quiz",
        showAllSettings: false,
        contentDripUiAvailable: true,
      })
    ).toBe(true);
  });

  it("keeps Auto Start delay independent of Interactive disclosure", () => {
    // Always-visible companion when Auto Start is on — not gated by showAllSettings.
    expect(shouldShowAutoStartDelay({ contract: "v4", quizAutoStart: true })).toBe(true);
    expect(shouldShowAutoStartDelay({ contract: "v4", quizAutoStart: false })).toBe(false);
    expect(shouldShowAutoStartDelay({ contract: "legacy", quizAutoStart: true })).toBe(false);
  });
});

describe("Interactive never-display and drip-unavailable", () => {
  const mixedLimitQuestions = [
    { question_type: "short_answer" as const },
    { question_type: "open_ended" as const },
  ];

  it("never displays Reveal or Character Limits for Interactive under either disclosure state", () => {
    [false, true].forEach((showAllSettings) => {
      expect(
        shouldShowAnswerReveal({
          contract: "v4",
          questionLayoutView: "single_question",
          contentType: "tutor_h5p_quiz",
        })
      ).toBe(false);
      expect(
        shouldShowAnswerRevealDuration({
          contract: "v4",
          questionLayoutView: "single_question",
          contentType: "tutor_h5p_quiz",
          enableAnswerReveal: true,
        })
      ).toBe(false);
      expect(
        shouldShowCharacterLimitsFrame({
          contentType: "tutor_h5p_quiz",
          questions: mixedLimitQuestions,
          showAllSettings,
        })
      ).toBe(false);
      expect(
        shouldShowShortAnswerCharacterLimit({
          contentType: "tutor_h5p_quiz",
          questions: mixedLimitQuestions,
          showAllSettings,
        })
      ).toBe(false);
      expect(
        shouldShowOpenEndedCharacterLimit({
          contentType: "tutor_h5p_quiz",
          questions: mixedLimitQuestions,
          showAllSettings,
        })
      ).toBe(false);
    });
  });

  it("hides the Content Drip frame when drip UI is unavailable", () => {
    expect(
      shouldShowContentDripSettingsFrame({
        contentType: "tutor_quiz",
        showAllSettings: true,
        contentDripUiAvailable: false,
      })
    ).toBe(false);
    expect(
      shouldShowContentDripSettingsFrame({
        contentType: "tutor_h5p_quiz",
        showAllSettings: true,
        contentDripUiAvailable: false,
      })
    ).toBe(false);
  });
});

describe("Interactive editing availability and fail-closed gates", () => {
  it.each([
    ["valid V4 Interactive", "tutor_h5p_quiz", "v4", true, true],
    ["Interactive missing runtime", "tutor_h5p_quiz", "v4", false, false],
    ["Interactive legacy contract", "tutor_h5p_quiz", "legacy", true, false],
    ["Interactive unavailable contract", "tutor_h5p_quiz", "unavailable", true, false],
    ["standard V4 with runtime", "tutor_quiz", "v4", true, false],
  ] as Array<[string, "tutor_quiz" | "tutor_h5p_quiz", QuizSettingsContract, boolean, boolean]>)(
    "isInteractiveQuizEditingAvailable for %s",
    (_label, contentType, contract, h5pRuntimeAvailable, expected) => {
      expect(
        isInteractiveQuizEditingAvailable({ contentType, contract, h5pRuntimeAvailable })
      ).toBe(expected);
    }
  );

  it.each([
    ["blocks Interactive without runtime", "tutor_h5p_quiz", "v4", false, true],
    ["blocks Interactive on legacy", "tutor_h5p_quiz", "legacy", true, true],
    ["allows valid Interactive", "tutor_h5p_quiz", "v4", true, false],
    ["blocks standard unavailable", "tutor_quiz", "unavailable", true, true],
    ["allows standard V4 without H5P runtime", "tutor_quiz", "v4", false, false],
    ["allows standard legacy", "tutor_quiz", "legacy", true, false],
  ] as Array<[string, "tutor_quiz" | "tutor_h5p_quiz", QuizSettingsContract, boolean, boolean]>)(
    "shouldBlockQuizSettingsEditing %s",
    (_label, contentType, contract, h5pRuntimeAvailable, expected) => {
      expect(
        shouldBlockQuizSettingsEditing({ contentType, contract, h5pRuntimeAvailable })
      ).toBe(expected);
    }
  );
});

describe("Course drip addon and mode gates", () => {
  const COURSE_DRIP_MODES = [
    "unlock_by_date",
    "specific_days",
    "unlock_sequentially",
    "after_finishing_prerequisites",
  ] as const;

  const frame = (
    contentType: "tutor_quiz" | "tutor_h5p_quiz",
    contentDripUiAvailable: boolean,
    showAllSettings = false
  ) =>
    shouldShowContentDripSettingsFrame({ contentType, showAllSettings, contentDripUiAvailable });

  const passRequired = (
    contentType: "tutor_quiz" | "tutor_h5p_quiz",
    contentDripAvailable: boolean,
    contentDripType: string,
    showAllSettings = false
  ) =>
    shouldShowPassIsRequired({
      contract: "v4",
      limitAttemptsAllowed: true,
      attemptsAllowed: 0,
      contentType,
      contentDripAvailable,
      contentDripType,
      showAllSettings,
    });

  it("gates the drip frame by addon availability and Interactive disclosure", () => {
    expect(frame("tutor_quiz", false, true)).toBe(false);
    expect(frame("tutor_h5p_quiz", false, true)).toBe(false);
    // Mode does not gate the shared frame; mode-specific controls are covered separately.
    expect(frame("tutor_quiz", true, false)).toBe(true);
    expect(frame("tutor_h5p_quiz", true, false)).toBe(false);
    expect(frame("tutor_h5p_quiz", true, true)).toBe(true);
  });

  it("shows Pass is required only for sequential mode when drip is available", () => {
    COURSE_DRIP_MODES.forEach((contentDripType) => {
      const expected = contentDripType === "unlock_sequentially";
      expect(passRequired("tutor_quiz", true, contentDripType)).toBe(expected);
      expect(passRequired("tutor_h5p_quiz", true, contentDripType, true)).toBe(expected);
      expect(passRequired("tutor_quiz", false, contentDripType)).toBe(false);
    });

    expect(passRequired("tutor_h5p_quiz", true, "unlock_sequentially", false)).toBe(false);
  });
});

describe("Content Drip mode controls and prerequisite helpers", () => {
  const dripBase = {
    contract: "v4" as const,
    contentDripAvailable: true,
    contentType: "tutor_quiz" as const,
    h5pRuntimeAvailable: true,
    showAllSettings: false,
  };

  const prerequisiteOptions = [
    {
      topic_id: 1,
      topic_title: "Topic",
      items: [
        { id: 10, title: "Lesson A", type: "lesson", topic_id: 1, topic_title: "Topic", type_label: "Lesson" },
        { id: 20, title: "This Quiz", type: "tutor_quiz", topic_id: 1, topic_title: "Topic", type_label: "Quiz" },
      ],
    },
  ];

  it("maps course drip modes to active controls", () => {
    expect(getQuizContentDripActiveControl("unlock_by_date")).toBe("unlock_date");
    expect(getQuizContentDripActiveControl("specific_days")).toBe("available_after_days");
    expect(getQuizContentDripActiveControl("after_finishing_prerequisites")).toBe("prerequisites");
    expect(getQuizContentDripActiveControl("unlock_sequentially")).toBe("none");
    expect(getQuizContentDripActiveControl("")).toBe("none");
  });

  it("gates the mode editor by V4, drip availability, and Interactive runtime/disclosure", () => {
    expect(shouldShowQuizContentDripEditor(dripBase)).toBe(true);
    expect(shouldShowQuizContentDripEditor({ ...dripBase, contract: "legacy" })).toBe(false);
    expect(shouldShowQuizContentDripEditor({ ...dripBase, contentDripAvailable: false })).toBe(false);

    const interactive = { ...dripBase, contentType: "tutor_h5p_quiz" as const, showAllSettings: true };
    expect(shouldShowQuizContentDripEditor(interactive)).toBe(true);
    expect(shouldShowQuizContentDripEditor({ ...interactive, showAllSettings: false })).toBe(false);
    expect(shouldShowQuizContentDripEditor({ ...interactive, h5pRuntimeAvailable: false })).toBe(false);
  });

  it("shows only the matching mode control and never a sequential drip frame", () => {
    const dateInput = { ...dripBase, contentDripType: "unlock_by_date" };
    expect(shouldShowQuizContentDripModeFrame(dateInput)).toBe(true);
    expect(shouldShowQuizContentDripUnlockDate(dateInput)).toBe(true);
    expect(shouldShowQuizContentDripAvailableAfterDays(dateInput)).toBe(false);
    expect(shouldShowQuizContentDripPrerequisites(dateInput)).toBe(false);

    expect(shouldShowQuizContentDripAvailableAfterDays({ ...dripBase, contentDripType: "specific_days" })).toBe(true);
    expect(
      shouldShowQuizContentDripPrerequisites({ ...dripBase, contentDripType: "after_finishing_prerequisites" })
    ).toBe(true);

    const sequential = { ...dripBase, contentDripType: "unlock_sequentially" };
    expect(shouldShowQuizContentDripModeFrame(sequential)).toBe(false);
    expect(shouldShowQuizContentDripUnlockDate(sequential)).toBe(false);
  });

  it("covers Interactive per-mode controls across runtime and disclosure", () => {
    const modes = [
      ["unlock_by_date", shouldShowQuizContentDripUnlockDate],
      ["specific_days", shouldShowQuizContentDripAvailableAfterDays],
      ["after_finishing_prerequisites", shouldShowQuizContentDripPrerequisites],
    ] as const;

    modes.forEach(([contentDripType, showControl]) => {
      const open = {
        ...dripBase,
        contentType: "tutor_h5p_quiz" as const,
        contentDripType,
        showAllSettings: true,
        h5pRuntimeAvailable: true,
      };
      expect(showControl(open)).toBe(true);
      expect(showControl({ ...open, showAllSettings: false })).toBe(false);
      expect(showControl({ ...open, h5pRuntimeAvailable: false })).toBe(false);
      expect(showControl({ ...open, contentDripAvailable: false })).toBe(false);
    });

    expect(
      shouldShowQuizContentDripModeFrame({
        ...dripBase,
        contentType: "tutor_h5p_quiz",
        contentDripType: "unlock_sequentially",
        showAllSettings: true,
        h5pRuntimeAvailable: true,
      })
    ).toBe(false);
  });

  it("excludes the current quiz and sanitizes prerequisite ID/token round-trips", () => {
    expect(getQuizPrerequisiteSuggestions(prerequisiteOptions, 20)).toEqual(["Lesson A (Lesson)"]);
    expect(getQuizPrerequisiteSuggestions(prerequisiteOptions)).toEqual([
      "Lesson A (Lesson)",
      "This Quiz (Quiz)",
    ]);
    expect(sanitizeQuizPrerequisiteIds([10, "20", 0, -3, 1.5, "x"])).toEqual([10, 20]);
    expect(quizPrerequisiteIdsToTokens([10, 20], prerequisiteOptions)).toEqual([
      "Lesson A (Lesson)",
      "This Quiz (Quiz)",
    ]);
    expect(quizPrerequisiteIdsToTokens([10, 20], prerequisiteOptions, 20)).toEqual(["Lesson A (Lesson)"]);
    expect(
      quizPrerequisiteTokensToIds(["Lesson A (Lesson)", { value: "This Quiz (Quiz)" }], prerequisiteOptions)
    ).toEqual([10, 20]);
    expect(
      quizPrerequisiteTokensToIds(["Lesson A (Lesson)", "This Quiz (Quiz)"], prerequisiteOptions, 20)
    ).toEqual([10]);
    expect(quizPrerequisiteTokensToIds(["Unknown"], prerequisiteOptions)).toEqual([]);
  });
});

describe("Content Drip form updates and validation", () => {
  it("preserves inactive drip fields and dirties only the patched group", () => {
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      contentDripAvailable: true,
      initialData: {
        post_title: "Drip quiz",
        quiz_option: {
          content_drip_settings: {
            unlock_date: "2026-01-15T00:00:00",
            after_xdays_of_enroll: 7,
            prerequisites: [10, 20],
          },
        } as QuizSettings,
      },
    });

    try {
      act(() => {
        hook.current().updateContentDripSettings({ unlock_date: "2026-02-01T00:00:00" });
      });

      expect(hook.current().formState.settings.content_drip_settings).toEqual({
        unlock_date: "2026-02-01T00:00:00",
        after_xdays_of_enroll: 7,
        prerequisites: [10, 20],
      });
      expect(hook.current().formState.dirtySettingsGroups.has("drip_unlock_date")).toBe(true);
      expect(hook.current().formState.dirtySettingsGroups.has("drip_available_after_days")).toBe(false);
      expect(hook.current().formState.dirtySettingsGroups.has("drip_prerequisites")).toBe(false);

      act(() => {
        hook.current().updateContentDripSettings({
          prerequisites: [10, 0, -1, 30] as unknown as number[],
        });
      });

      expect(hook.current().formState.settings.content_drip_settings).toEqual({
        unlock_date: "2026-02-01T00:00:00",
        after_xdays_of_enroll: 7,
        prerequisites: [10, 30],
      });
      expect(hook.current().formState.dirtySettingsGroups.has("drip_prerequisites")).toBe(true);
    } finally {
      hook.unmount();
    }
  });

  it("rejects negative available-after-days values", () => {
    const hook = renderQuizFormHook({
      capabilities: createCapabilities("v4"),
      contentType: "tutor_quiz",
      contentDripAvailable: true,
      initialData: { post_title: "Days quiz" },
    });

    try {
      act(() => {
        hook.current().updateContentDripSettings({ after_xdays_of_enroll: -2 });
      });
      expect(hook.current().formState.isValid).toBe(false);
      expect(hook.current().formState.errors.availableAfterDays).toBe(
        "Available after days cannot be negative"
      );

      act(() => {
        hook.current().updateContentDrip(0);
      });
      expect(hook.current().formState.errors.availableAfterDays).toBeUndefined();
      expect(hook.current().formState.isValid).toBe(true);
    } finally {
      hook.unmount();
    }
  });
});

describe("Top-level Content Drip FormData builder", () => {
  const dripSettings = {
    unlock_date: " 2026-03-15T12:00:00 ",
    after_xdays_of_enroll: 3.7,
    prerequisites: [10, 0, -2, 20] as number[],
  };

  it.each([
    ["unlock_by_date", "drip_unlock_date", { "content_drip_settings[unlock_date]": "2026-03-15" }],
    [
      "specific_days",
      "drip_available_after_days",
      { "content_drip_settings[after_xdays_of_enroll]": 3 },
    ],
    [
      "after_finishing_prerequisites",
      "drip_prerequisites",
      { "content_drip_settings[prerequisites]": [10, 20] },
    ],
  ] as Array<[string, QuizSettingsDirtyGroup, Record<string, string | number | number[]>]>)(
    "emits one native field for %s when matching dirty",
    (contentDripType, dirtyGroup, expected) => {
      expect(
        buildTopLevelContentDripFormFields({
          contentDripAvailable: true,
          settingsContract: "v4",
          contentDripType,
          dirtyGroups: new Set<QuizSettingsDirtyGroup>([dirtyGroup]),
          dripSettings,
        })
      ).toEqual(expected);
    }
  );

  it("clears prerequisites with an empty string", () => {
    expect(
      buildTopLevelContentDripFormFields({
        contentDripAvailable: true,
        settingsContract: "v4",
        contentDripType: "after_finishing_prerequisites",
        dirtyGroups: new Set<QuizSettingsDirtyGroup>(["drip_prerequisites"]),
        dripSettings: { prerequisites: [] },
      })
    ).toEqual({ "content_drip_settings[prerequisites]": "" });
  });

  it.each([
    ["addon unavailable", false, "v4" as QuizSettingsContract, "unlock_by_date", "drip_unlock_date"],
    ["legacy contract", true, "legacy", "unlock_by_date", "drip_unlock_date"],
    ["sequential", true, "v4", "unlock_sequentially", "drip_unlock_date"],
    ["unknown mode", true, "v4", "", "drip_unlock_date"],
    ["no dirty group", true, "v4", "unlock_by_date", null],
    ["mismatched dirty", true, "v4", "unlock_by_date", "drip_prerequisites"],
  ] as Array<[string, boolean, QuizSettingsContract, string, QuizSettingsDirtyGroup | null]>)(
    "returns {} for %s",
    (_label, contentDripAvailable, settingsContract, contentDripType, dirty) => {
      expect(
        buildTopLevelContentDripFormFields({
          contentDripAvailable,
          settingsContract,
          contentDripType,
          dirtyGroups: new Set<QuizSettingsDirtyGroup>(dirty ? [dirty] : []),
          dripSettings,
        })
      ).toEqual({});
    }
  );
});

describe("Content Drip FormData append separation", () => {
  it("appends companions outside JSON payload and leaves payload drip nested alone", () => {
    const formData = new FormData();
    const payload = {
      quiz_option: {
        content_drip_settings: { unlock_date: "2026-04-01", after_xdays_of_enroll: 7, prerequisites: [10] },
      },
    };
    formData.append("payload", JSON.stringify(payload));

    appendContentDripPostFieldsToFormData(formData, {
      "content_drip_settings[unlock_date]": "2026-04-01",
    });

    expect(formData.get("content_drip_settings[unlock_date]")).toBe("2026-04-01");
    const payloadRaw = String(formData.get("payload"));
    expect(payloadRaw).not.toContain("content_drip_settings[unlock_date]");
    expect(JSON.parse(payloadRaw)).toEqual(payload);
  });

  it("appends empty-string and indexed prerequisite clears without mutating payload", () => {
    const formData = new FormData();
    formData.append("payload", JSON.stringify({ quiz_option: { content_drip_settings: { prerequisites: "" } } }));

    appendContentDripPostFieldsToFormData(formData, {
      "content_drip_settings[prerequisites]": "",
    });
    expect(formData.get("content_drip_settings[prerequisites]")).toBe("");

    const withIds = new FormData();
    appendContentDripPostFieldsToFormData(withIds, {
      "content_drip_settings[prerequisites]": [10, 20],
    });
    expect(withIds.get("content_drip_settings[prerequisites][0]")).toBe("10");
    expect(withIds.get("content_drip_settings[prerequisites][1]")).toBe("20");

    const noop = new FormData();
    noop.append("payload", "{}");
    appendContentDripPostFieldsToFormData(noop, undefined);
    appendContentDripPostFieldsToFormData(noop, {});
    expect([...noop.keys()]).toEqual(["payload"]);
  });
});

describe("saveQuiz FormData separation", () => {
  it("appends Pro companions outside the resolver's JSON payload", () => {
    const quizData = {
      post_title: "Resolver drip quiz",
      post_content: "",
      quiz_option: {
        content_drip_settings: {
          unlock_date: "2026-04-01",
          after_xdays_of_enroll: 7,
          prerequisites: [10],
        },
      },
      questions: [],
    } as unknown as QuizForm;
    const windowWithTutor = window as Window & {
      _tutorobject?: { ajaxurl?: string; _tutor_nonce?: string };
    };
    const previousTutorObject = windowWithTutor._tutorobject;
    windowWithTutor._tutorobject = { ajaxurl: "/admin-ajax.php", _tutor_nonce: "nonce" };

    try {
      const resolver = saveQuizResolver(quizData, 3, 4, {
        "content_drip_settings[unlock_date]": "2026-04-01",
      });
      resolver.next();
      const yielded = resolver.next().value as { request: { body: FormData } };
      const body = yielded.request.body;
      const payload = JSON.parse(String(body.get("payload")));

      expect(body.get("content_drip_settings[unlock_date]")).toBe("2026-04-01");
      expect(payload.quiz_option.content_drip_settings.unlock_date).toBe("2026-04-01");
      expect(String(body.get("payload"))).not.toContain("content_drip_settings[unlock_date]");
    } finally {
      if (previousTutorObject === undefined) {
        delete windowWithTutor._tutorobject;
      } else {
        windowWithTutor._tutorobject = previousTutorObject;
      }
    }
  });
});

describe("Content Drip getFormData envelope active modes", () => {
  const nestedBase = {
    unlock_date: "2026-01-15T00:00:00",
    after_xdays_of_enroll: 7,
    prerequisites: [10, 20],
    future_nested: "keep",
  };

  const modeCases = [
    [
      "unlock_by_date",
      { unlock_date: "2026-04-01T00:00:00" },
      { "content_drip_settings[unlock_date]": "2026-04-01" },
      { ...nestedBase, unlock_date: "2026-04-01" },
    ],
    [
      "specific_days",
      { after_xdays_of_enroll: 9 },
      { "content_drip_settings[after_xdays_of_enroll]": 9 },
      { ...nestedBase, after_xdays_of_enroll: 9 },
    ],
    [
      "after_finishing_prerequisites",
      { prerequisites: [30] },
      { "content_drip_settings[prerequisites]": [30] },
      { ...nestedBase, prerequisites: [30] },
    ],
  ] as Array<
    [
      string,
      Partial<{ unlock_date: string; after_xdays_of_enroll: number; prerequisites: number[] }>,
      Record<string, string | number | number[]>,
      Record<string, unknown>,
    ]
  >;

  (["tutor_quiz", "tutor_h5p_quiz"] as QuizContentType[]).forEach((contentType) => {
    it.each(modeCases)(
      `${contentType} × %s posts envelope outside quiz_option and preserves inactive nested`,
      (contentDripType, patch, expectedEnvelope, expectedNested) => {
        const previousAddons = window.tutorpressAddons;
        window.tutorpressAddons = {
          h5p: true,
          h5p_plugin_active: true,
        } as typeof window.tutorpressAddons;

        const hook = renderQuizFormHook({
          capabilities: createCapabilities("v4"),
          contentType,
          contentDripAvailable: true,
          contentDripType,
          initialData: {
            post_title: "Drip envelope quiz",
            quiz_option: {
              ...(contentType === "tutor_h5p_quiz" ? { quiz_type: "tutor_h5p_quiz" } : {}),
              content_drip_settings: { ...nestedBase },
            } as unknown as QuizSettings,
          },
        });

        try {
          act(() => {
            hook.current().updateContentDripSettings(patch);
          });

          const result = hook.current().getFormData([], false);
          expect(result.status).toBe("ready");
          if (result.status !== "ready") {
            throw new Error(`Expected ready result, received ${result.reason}`);
          }

          expect(result.contentDripPostFields).toEqual(expectedEnvelope);
          expect(result.formData).not.toHaveProperty("contentDripPostFields");
          expect("content_drip_settings[unlock_date]" in result.formData.quiz_option).toBe(false);
          expect("content_drip_settings[after_xdays_of_enroll]" in result.formData.quiz_option).toBe(false);
          expect("content_drip_settings[prerequisites]" in result.formData.quiz_option).toBe(false);
          expect(result.formData.quiz_option.content_drip_settings).toEqual(expectedNested);
        } finally {
          hook.unmount();
          window.tutorpressAddons = previousAddons;
        }
      }
    );
  });
});

describe("Content Drip getFormData omission and identity", () => {
  const enableH5p = () => {
    window.tutorpressAddons = { h5p: true, h5p_plugin_active: true } as typeof window.tutorpressAddons;
  };

  const readyAfter = (options: UseQuizFormOptions, edit: (hook: UseQuizFormReturn) => void) => {
    enableH5p();
    const hook = renderQuizFormHook(options);
    act(() => edit(hook.current()));
    const result = hook.current().getFormData([], false);
    expect(result.status).toBe("ready");
    if (result.status !== "ready") {
      hook.unmount();
      throw new Error(`Expected ready result, received ${result.reason}`);
    }
    return { result, unmount: hook.unmount };
  };

  it("clears prerequisites as nested '' and top-level ''", () => {
    const { result, unmount } = readyAfter(
      {
        capabilities: createCapabilities("v4"),
        contentType: "tutor_quiz",
        contentDripAvailable: true,
        contentDripType: "after_finishing_prerequisites",
        initialData: {
          post_title: "Clear prereqs",
          quiz_option: {
            content_drip_settings: {
              unlock_date: "2026-01-15",
              after_xdays_of_enroll: 7,
              prerequisites: [10, 20],
              future_nested: "keep",
            },
          } as unknown as QuizSettings,
        },
      },
      (form) => form.updateContentDripSettings({ prerequisites: [] })
    );
    try {
      expect(result.contentDripPostFields).toEqual({ "content_drip_settings[prerequisites]": "" });
      expect(result.formData.quiz_option.content_drip_settings).toEqual({
        unlock_date: "2026-01-15",
        after_xdays_of_enroll: 7,
        prerequisites: "",
        future_nested: "keep",
      });
    } finally {
      unmount();
    }
  });

  it.each([
    [
      "sequential mode",
      "v4" as QuizSettingsContract,
      true,
      "unlock_sequentially",
      (form: UseQuizFormReturn) => form.updateContentDripSettings({ unlock_date: "2026-05-01" }),
      {},
      undefined,
    ],
    [
      "drip unavailable",
      "v4" as QuizSettingsContract,
      false,
      "unlock_by_date",
      (form: UseQuizFormReturn) => form.updateContentDripSettings({ unlock_date: "2026-05-01" }),
      {},
      undefined,
    ],
    [
      "legacy contract",
      "legacy" as QuizSettingsContract,
      true,
      "unlock_by_date",
      (form: UseQuizFormReturn) => form.updateContentDripSettings({ unlock_date: "2026-05-01" }),
      {},
      undefined,
    ],
    [
      "unrelated passing-grade edit",
      "v4" as QuizSettingsContract,
      true,
      "unlock_by_date",
      (form: UseQuizFormReturn) => form.updateSettings({ passing_grade: 88 }),
      {},
      { unlock_date: "2026-01-15", after_xdays_of_enroll: 7, prerequisites: [] },
    ],
  ] as Array<
    [
      string,
      QuizSettingsContract,
      boolean,
      string,
      (form: UseQuizFormReturn) => void,
      Record<string, unknown>,
      Record<string, unknown> | undefined,
    ]
  >)(
    "omits envelope for %s",
    (_label, contract, contentDripAvailable, contentDripType, edit, envelope, nested) => {
      const drip = { unlock_date: "2026-01-15", after_xdays_of_enroll: 7, prerequisites: [] as number[] };
      const { result, unmount } = readyAfter(
        {
          capabilities: createCapabilities(contract),
          contentType: "tutor_quiz",
          contentDripAvailable,
          contentDripType,
          initialData: {
            post_title: "Omission quiz",
            quiz_option: { content_drip_settings: drip } as unknown as QuizSettings,
          },
        },
        edit
      );
      try {
        expect(result.contentDripPostFields).toEqual(envelope);
        if (nested) {
          expect(result.formData.quiz_option.passing_grade).toBe(88);
          expect(result.formData.quiz_option.content_drip_settings).toEqual(nested);
        }
      } finally {
        unmount();
      }
    }
  );
  it.each([
    [
      "Interactive enforces identity with drip envelope",
      "tutor_h5p_quiz" as QuizContentType,
      "specific_days",
      { quiz_type: "future_identity", content_drip_settings: { unlock_date: "", after_xdays_of_enroll: 2, prerequisites: [] } },
      (form: UseQuizFormReturn) => form.updateContentDripSettings({ after_xdays_of_enroll: 5 }),
      "tutor_h5p_quiz",
      { "content_drip_settings[after_xdays_of_enroll]": 5 },
    ],
    [
      "standard removes exact stale H5P identity",
      "tutor_quiz" as QuizContentType,
      "unlock_by_date",
      {
        quiz_type: "tutor_h5p_quiz",
        content_drip_settings: { unlock_date: "2026-01-15", after_xdays_of_enroll: 0, prerequisites: [] },
      },
      (form: UseQuizFormReturn) => form.updateContentDripSettings({ unlock_date: "2026-06-01" }),
      undefined,
      { "content_drip_settings[unlock_date]": "2026-06-01" },
    ],
  ])("%s", (_label, contentType, contentDripType, quizOption, edit, identity, envelope) => {
    const { result, unmount } = readyAfter(
      {
        capabilities: createCapabilities("v4"),
        contentType,
        contentDripAvailable: true,
        contentDripType,
        initialData: {
          post_title: "Identity drip",
          quiz_option: quizOption as unknown as QuizSettings,
        },
      },
      edit
    );
    try {
      if (identity === undefined) {
        expect(result.formData.quiz_option).not.toHaveProperty("quiz_type");
      } else {
        expect(result.formData.quiz_option.quiz_type).toBe(identity);
      }
      expect(result.contentDripPostFields).toEqual(envelope);
    } finally {
      unmount();
    }
  });
});
