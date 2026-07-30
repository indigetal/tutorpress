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
  QuizSettingsDirtyGroup,
  QuizSettingsFormModel,
  QuizSettingsContract,
  QuizSettingsUnavailableReason,
  RawQuizSettings,
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
