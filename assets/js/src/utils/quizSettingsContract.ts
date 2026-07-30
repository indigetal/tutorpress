/**
 * Fail-closed client helpers for the PHP-selected Quiz Settings contract.
 *
 * Components must use these helpers instead of comparing Tutor version strings.
 */

import type {
  QuizCapabilities,
  QuizSettingsContract,
  QuizSettingsUnavailableReason,
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
