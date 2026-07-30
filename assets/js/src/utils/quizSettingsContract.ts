/**
 * Fail-closed client helpers for the PHP-selected Quiz Settings contract.
 *
 * Components must use these helpers instead of comparing Tutor version strings.
 */

import type {
  QuizCapabilities,
  QuizContentDripSettings,
  QuizContentType,
  QuizEffectiveSettings,
  QuizSettingsLoadInput,
  QuizSettingsLoadResult,
  QuizSettingsDirtyGroup,
  QuizSettingsFormModel,
  QuizSettingsContract,
  QuizSettingsUnavailableReason,
  RawQuizContentDripSettings,
  RawQuizSettings,
  RawQuizScalar,
  TimeUnit,
} from "../types/quiz";

export const getQuizSettingsContract = (capabilities?: QuizCapabilities): QuizSettingsContract =>
  capabilities?.quizSettingsContract ?? "unavailable";

export const getQuizSettingsUnavailableReason = (
  capabilities?: QuizCapabilities
): QuizSettingsUnavailableReason => {
  if (getQuizSettingsContract(capabilities) !== "unavailable") {
    return "";
  }

  return capabilities?.quizSettingsUnavailableReason || "legacy_contract_unavailable";
};

export const canEditQuizSettings = (capabilities?: QuizCapabilities): boolean =>
  getQuizSettingsContract(capabilities) !== "unavailable";

export const supportsOrthogonalQuizFeedback = (capabilities?: QuizCapabilities): boolean =>
  getQuizSettingsContract(capabilities) === "v4" && capabilities?.supportsOrthogonalFeedback === true;

export const supportsSeparateQuizPagination = (capabilities?: QuizCapabilities): boolean =>
  getQuizSettingsContract(capabilities) === "v4" && capabilities?.supportsSeparatePagination === true;

export const supportsV4QuizTimingNavigation = (capabilities?: QuizCapabilities): boolean =>
  getQuizSettingsContract(capabilities) === "v4" && capabilities?.supportsV4TimingNavigation === true;

export const supportsLegacyQuizFeedbackLayout = (capabilities?: QuizCapabilities): boolean =>
  getQuizSettingsContract(capabilities) === "legacy" && capabilities?.supportsLegacyFeedbackLayout === true;

export const supportsV4QuizContentDrip = (capabilities?: QuizCapabilities): boolean =>
  getQuizSettingsContract(capabilities) === "v4" && capabilities?.supportsV4QuizContentDrip === true;

type QuizEffectiveFieldPath =
  | keyof QuizEffectiveSettings
  | `content_drip_settings.${keyof QuizContentDripSettings}`;

/**
 * Persisted fields owned by each independently dirty setting group.
 */
export const QUIZ_SETTINGS_GROUP_FIELDS = {
  passing_grade: ["passing_grade"],
  question_order: ["questions_order"],
  attempts: ["limit_attempts_allowed", "attempts_allowed"],
  answer_reveal: ["enable_answer_reveal", "answers_reveal_duration"],
  legacy_feedback: [
    "feedback_mode",
    "limit_attempts_allowed",
    "attempts_allowed",
    "enable_answer_reveal",
    "answers_reveal_duration",
  ],
  question_limit: ["limit_questions_to_answer", "max_questions_for_answer"],
  time_limit: ["enable_time_limit", "time_limit"],
  hide_countdown: ["hide_quiz_time_display"],
  auto_start: ["quiz_auto_start", "auto_start_delay"],
  layout: ["question_layout_view"],
  pagination: ["enable_pagination", "pagination_type"],
  hide_previous: ["hide_previous_button"],
  hide_question_number: ["hide_question_number_overview"],
  short_answer_character_limit: ["short_answer_characters_limit"],
  open_ended_character_limit: ["open_ended_answer_characters_limit"],
  pass_required: ["pass_is_required"],
  drip_unlock_date: ["content_drip_settings.unlock_date"],
  drip_available_after_days: ["content_drip_settings.after_xdays_of_enroll"],
  drip_prerequisites: ["content_drip_settings.prerequisites"],
} as const satisfies Record<QuizSettingsDirtyGroup, readonly QuizEffectiveFieldPath[]>;

const createEffectiveDefaults = (
  contract: Exclude<QuizSettingsContract, "unavailable">,
  contentType: QuizContentType
): QuizEffectiveSettings => {
  const isH5P = contentType === "tutor_h5p_quiz";

  return {
    enable_time_limit: false,
    time_limit: {
      time_value: 0,
      time_type: "minutes",
    },
    hide_quiz_time_display: false,
    feedback_mode: "default",
    limit_attempts_allowed: false,
    attempts_allowed: contract === "v4" ? 10 : 0,
    pass_is_required: false,
    passing_grade: 80,
    limit_questions_to_answer: false,
    max_questions_for_answer: 10,
    quiz_auto_start: false,
    auto_start_delay: 5,
    question_layout_view: isH5P ? "question_below_each_other" : "single_question",
    enable_pagination: false,
    pagination_type: "shape",
    enable_answer_reveal: false,
    answers_reveal_duration: 5,
    hide_previous_button: false,
    questions_order: "rand",
    hide_question_number_overview: false,
    short_answer_characters_limit: 200,
    open_ended_answer_characters_limit: 500,
    content_drip_settings: {
      unlock_date: "",
      after_xdays_of_enroll: 0,
      prerequisites: [],
    },
  };
};

const hasOwn = (value: object, key: PropertyKey): boolean =>
  Object.prototype.hasOwnProperty.call(value, key);

const toBoolean = (value: unknown): boolean => value === true || value === 1 || value === "1";

const toFiniteNumber = (value: unknown, fallback: number): number => {
  if (typeof value === "number") {
    return Number.isFinite(value) ? value : fallback;
  }

  if (typeof value === "string" && value.trim() !== "") {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  }

  return fallback;
};

const oneOf = <T extends string>(value: unknown, allowed: readonly T[], fallback: T): T =>
  typeof value === "string" && allowed.includes(value as T) ? (value as T) : fallback;

const cloneRawSettings = (settings: RawQuizSettings): RawQuizSettings => {
  const cloned = { ...settings };

  if (settings.time_limit && typeof settings.time_limit === "object") {
    cloned.time_limit = { ...settings.time_limit };
  }
  if (settings.content_drip_settings && typeof settings.content_drip_settings === "object") {
    cloned.content_drip_settings = { ...settings.content_drip_settings };
  }

  return cloned;
};

const toCharacterLimit = (value: RawQuizScalar | undefined, fallback: number): number | "" =>
  value === "" ? "" : toFiniteNumber(value, fallback);

const toPrerequisites = (value: unknown): number[] =>
  Array.isArray(value)
    ? value
        .map((id) => toFiniteNumber(id, 0))
        .filter((id) => Number.isInteger(id) && id > 0)
    : [];

const toEffectiveDripSettings = (
  source: RawQuizContentDripSettings | unknown[] | undefined
): QuizContentDripSettings => {
  const keyedSource = Array.isArray(source) ? undefined : source;

  return {
    unlock_date: typeof keyedSource?.unlock_date === "string" ? keyedSource.unlock_date : "",
    after_xdays_of_enroll: toFiniteNumber(keyedSource?.after_xdays_of_enroll, 0),
    prerequisites: toPrerequisites(keyedSource?.prerequisites),
  };
};

/**
 * Converts raw builder storage to editor values without changing or normalizing storage.
 */
export const convertRawQuizSettingsToFormModel = ({
  contract,
  contentType,
  rawSettings,
  contentDripAvailable,
  hasProContentDripSettings,
  proContentDripSettings,
}: QuizSettingsLoadInput): QuizSettingsLoadResult => {
  const preservedRawSettings = cloneRawSettings(rawSettings);

  if (contract === "unavailable") {
    return {
      contract,
      contentType,
      rawSettings: preservedRawSettings,
      effectiveSettings: null,
      dirtyGroups: new Set<QuizSettingsDirtyGroup>(),
    };
  }

  const defaults = createEffectiveDefaults(contract, contentType);
  const feedbackMode = oneOf(rawSettings.feedback_mode, ["default", "reveal", "retry"], "default");
  const legacyPagination = rawSettings.question_layout_view === "question_pagination";
  const rawLayout = rawSettings.question_layout_view;
  const questionLayout =
    rawLayout === "question_below_each_other" || rawLayout === "single_question"
      ? rawLayout
      : "single_question";
  const isV4 = contract === "v4";
  const enablePagination = isV4
    ? legacyPagination ||
      (hasOwn(rawSettings, "enable_pagination")
        ? toBoolean(rawSettings.enable_pagination)
        : legacyPagination)
    : legacyPagination;
  const paginationType = isV4
    ? rawSettings.pagination_type !== null &&
      rawSettings.pagination_type !== undefined &&
      hasOwn(rawSettings, "pagination_type")
      ? oneOf(rawSettings.pagination_type, ["shape", "number", "radio"], "shape")
      : oneOf(rawSettings.question_pagination_style, ["shape", "number", "radio"], "shape")
    : oneOf(rawSettings.question_pagination_style, ["shape", "number", "radio"], "shape");
  const maxQuestions = toFiniteNumber(rawSettings.max_questions_for_answer, 0);
  const timeValue = toFiniteNumber(rawSettings.time_limit?.time_value, 0);
  const dripSource = contentDripAvailable
    ? hasProContentDripSettings
      ? proContentDripSettings
      : rawSettings.content_drip_settings
    : undefined;

  return {
    contract,
    contentType,
    rawSettings: preservedRawSettings,
    effectiveSettings: {
      enable_time_limit: timeValue > 0,
      time_limit: {
        time_value: timeValue,
        time_type: oneOf<TimeUnit>(
          rawSettings.time_limit?.time_type,
          ["seconds", "minutes", "hours", "days", "weeks"],
          "minutes"
        ),
      },
      hide_quiz_time_display: toBoolean(rawSettings.hide_quiz_time_display),
      feedback_mode: feedbackMode,
      limit_attempts_allowed: isV4
        ? hasOwn(rawSettings, "limit_attempts_allowed")
          ? toBoolean(rawSettings.limit_attempts_allowed)
          : feedbackMode === "retry"
        : feedbackMode === "retry",
      attempts_allowed: toFiniteNumber(rawSettings.attempts_allowed, defaults.attempts_allowed),
      pass_is_required: toBoolean(rawSettings.pass_is_required),
      passing_grade: toFiniteNumber(rawSettings.passing_grade, defaults.passing_grade),
      limit_questions_to_answer: maxQuestions > 0,
      max_questions_for_answer: maxQuestions > 0 ? maxQuestions : 10,
      quiz_auto_start: toBoolean(rawSettings.quiz_auto_start),
      auto_start_delay: isV4
        ? toFiniteNumber(rawSettings.auto_start_delay, defaults.auto_start_delay)
        : defaults.auto_start_delay,
      question_layout_view: questionLayout,
      enable_pagination: enablePagination,
      pagination_type: paginationType,
      enable_answer_reveal: isV4
        ? hasOwn(rawSettings, "enable_answer_reveal")
          ? toBoolean(rawSettings.enable_answer_reveal)
          : feedbackMode === "reveal"
        : feedbackMode === "reveal",
      answers_reveal_duration: isV4
        ? toFiniteNumber(rawSettings.answers_reveal_duration, defaults.answers_reveal_duration)
        : defaults.answers_reveal_duration,
      hide_previous_button: isV4 ? toBoolean(rawSettings.hide_previous_button) : false,
      questions_order: oneOf(rawSettings.questions_order, ["rand", "sorting", "asc", "desc"], "rand"),
      hide_question_number_overview: toBoolean(rawSettings.hide_question_number_overview),
      short_answer_characters_limit: toCharacterLimit(
        rawSettings.short_answer_characters_limit,
        defaults.short_answer_characters_limit || 200
      ),
      open_ended_answer_characters_limit: toCharacterLimit(
        rawSettings.open_ended_answer_characters_limit,
        defaults.open_ended_answer_characters_limit || 500
      ),
      content_drip_settings: contentDripAvailable
        ? toEffectiveDripSettings(dripSource)
        : { ...defaults.content_drip_settings },
    },
    dirtyGroups: new Set<QuizSettingsDirtyGroup>(),
  };
};

const createV4RawDefaults = (effectiveSettings: QuizEffectiveSettings): RawQuizSettings => ({
  time_limit: { ...effectiveSettings.time_limit },
  hide_quiz_time_display: "0",
  limit_attempts_allowed: "0",
  attempts_allowed: effectiveSettings.attempts_allowed,
  pass_is_required: "0",
  passing_grade: effectiveSettings.passing_grade,
  max_questions_for_answer: 0,
  quiz_auto_start: "0",
  auto_start_delay: effectiveSettings.auto_start_delay,
  question_layout_view: effectiveSettings.question_layout_view,
  enable_pagination: "0",
  pagination_type: effectiveSettings.pagination_type,
  enable_answer_reveal: "0",
  answers_reveal_duration: effectiveSettings.answers_reveal_duration,
  hide_previous_button: "0",
  questions_order: effectiveSettings.questions_order,
  hide_question_number_overview: "0",
  short_answer_characters_limit: effectiveSettings.short_answer_characters_limit,
  open_ended_answer_characters_limit: effectiveSettings.open_ended_answer_characters_limit,
  content_drip_settings: { ...effectiveSettings.content_drip_settings },
});

const createLegacyRawDefaults = (): RawQuizSettings => ({
  time_limit: {
    time_value: 0,
    time_type: "minutes",
  },
  hide_quiz_time_display: false,
  feedback_mode: "default",
  attempts_allowed: 0,
  pass_is_required: false,
  passing_grade: 80,
  max_questions_for_answer: 10,
  quiz_auto_start: false,
  question_layout_view: "",
  questions_order: "rand",
  hide_question_number_overview: false,
  short_answer_characters_limit: 200,
  open_ended_answer_characters_limit: 500,
  content_drip_settings: {
    unlock_date: "",
    after_xdays_of_enroll: 0,
    prerequisites: [],
  },
});

/**
 * Builds new-quiz defaults only when PHP selected an executable wire contract.
 */
export const createNewQuizSettingsFormModel = (
  capabilities: QuizCapabilities | undefined,
  contentType: QuizContentType
): QuizSettingsFormModel | null => {
  const contract = getQuizSettingsContract(capabilities);

  if (contract === "unavailable") {
    return null;
  }

  const effectiveSettings = createEffectiveDefaults(contract, contentType);

  return {
    contract,
    contentType,
    rawSettings: contract === "v4" ? createV4RawDefaults(effectiveSettings) : createLegacyRawDefaults(),
    effectiveSettings,
    dirtyGroups: new Set<QuizSettingsDirtyGroup>(),
  };
};
