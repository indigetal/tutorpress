/**
 * Presentational Tutor 4 quiz Content Drip mode fields.
 */

import React from "react";
import {
  FormTokenField,
  Notice,
  Spinner,
  TextControl,
  __experimentalNumberControl as NumberControl,
} from "@wordpress/components";
import { __ } from "@wordpress/i18n";
import type { PrerequisitesByTopic } from "../../../types/content-drip";
import {
  getQuizPrerequisiteSuggestions,
  quizPrerequisiteIdsToTokens,
  quizPrerequisiteTokensToIds,
} from "../../../utils/quizSettingsContract";

export type QuizContentDripFieldsMode = "unlock_date" | "available_after_days" | "prerequisites";

export interface QuizContentDripFieldsProps {
  mode: QuizContentDripFieldsMode;
  unlockDate?: string;
  afterXDaysOfEnroll?: number;
  prerequisites?: number[];
  prerequisiteOptions?: PrerequisitesByTopic[];
  currentQuizId?: number;
  onUnlockDateChange?: (unlockDate: string) => void;
  onAfterXDaysChange?: (days: number) => void;
  onPrerequisitesChange?: (prerequisiteIds: number[]) => void;
  disabled?: boolean;
  contentDripLoading?: boolean;
  contentDripError?: string | null;
  availableAfterDaysError?: string;
}

const toDateInputValue = (unlockDate: string): string =>
  unlockDate.includes("T") ? unlockDate.split("T")[0] : unlockDate;

export const QuizContentDripFields: React.FC<QuizContentDripFieldsProps> = ({
  mode,
  unlockDate = "",
  afterXDaysOfEnroll = 0,
  prerequisites = [],
  prerequisiteOptions,
  currentQuizId,
  onUnlockDateChange,
  onAfterXDaysChange,
  onPrerequisitesChange,
  disabled = false,
  contentDripLoading = false,
  contentDripError = null,
  availableAfterDaysError,
}) => {
  if (mode === "unlock_date") {
    return (
      <div className="quiz-modal-settings-frame">
        <h4>{__("Unlock Date", "tutorpress")}</h4>
        <div className="quiz-modal-setting-group">
          <TextControl
            label={__("Unlock Date", "tutorpress")}
            type="date"
            value={toDateInputValue(unlockDate)}
            placeholder={__("Select Unlock Date", "tutorpress")}
            onChange={(value) => onUnlockDateChange?.(value ? `${value}T00:00:00` : "")}
            disabled={disabled}
            help={__("Set the date when the quiz will be available.", "tutorpress")}
          />
        </div>
      </div>
    );
  }

  if (mode === "prerequisites") {
    return (
      <div className="quiz-modal-settings-frame">
        <h4>{__("Prerequisites", "tutorpress")}</h4>
        <div className="quiz-modal-setting-group">
          {contentDripLoading ? (
            <Spinner />
          ) : (
            <FormTokenField
              label={__("Prerequisites", "tutorpress")}
              value={quizPrerequisiteIdsToTokens(prerequisites, prerequisiteOptions, currentQuizId)}
              suggestions={getQuizPrerequisiteSuggestions(prerequisiteOptions, currentQuizId)}
              onChange={(tokens) =>
                onPrerequisitesChange?.(
                  quizPrerequisiteTokensToIds(tokens, prerequisiteOptions, currentQuizId)
                )
              }
              placeholder={__("Select Prerequisite", "tutorpress")}
              disabled={disabled}
              __experimentalExpandOnFocus
              __experimentalShowHowTo={false}
            />
          )}
          <p className="quiz-modal-setting-help">
            {__("Select items that should be complete before this item", "tutorpress")}
          </p>
          {contentDripError && (
            <Notice status="error" isDismissible={false}>
              {contentDripError}
            </Notice>
          )}
        </div>
      </div>
    );
  }

  return (
    <div className="quiz-modal-settings-frame">
      <h4>{__("Available after days", "tutorpress")}</h4>
      <div className="quiz-modal-setting-group">
        <NumberControl
          label={__("Available after days", "tutorpress")}
          value={afterXDaysOfEnroll}
          onChange={(value) => onAfterXDaysChange?.(parseInt(value as string, 10) || 0)}
          min={0}
          step={1}
          placeholder="0"
          disabled={disabled}
        />
        <p className="quiz-modal-setting-help">
          {__("This quiz will be available after the given number of days.", "tutorpress")}
        </p>
        {availableAfterDaysError && (
          <Notice status="error" isDismissible={false}>
            {availableAfterDaysError}
          </Notice>
        )}
      </div>
    </div>
  );
};
