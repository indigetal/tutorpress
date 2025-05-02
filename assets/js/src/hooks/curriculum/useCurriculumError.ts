import { useCallback } from "react";
import {
  Topic,
  CurriculumError,
  ReorderOperationState,
  TopicDeletionState,
  TopicDuplicationState,
  OperationResult,
  CurriculumErrorCode,
} from "../../types/curriculum";
import { useError } from "../useError";
import { getErrorMessage } from "../../utils/errors";
import { __ } from "@wordpress/i18n";

export interface UseCurriculumErrorOptions {
  reorderState: ReorderOperationState;
  deletionState: TopicDeletionState;
  duplicationState: TopicDuplicationState;
  topics: Topic[];
  handleReorderTopics: (topics: Topic[]) => Promise<OperationResult<void>>;
  handleTopicDelete: (topicId: number) => Promise<void>;
  handleTopicDuplicate: (topicId: number) => Promise<void>;
}

export interface UseCurriculumErrorReturn {
  showError: boolean;
  handleDismissError: () => void;
  handleRetry: () => Promise<void>;
  getErrorMessage: (error: CurriculumError) => string;
  validateApiResponse: (response: unknown) => Topic[];
  createCurriculumError: (error: unknown, context: { action: string }) => CurriculumError;
}

export function useCurriculumError({
  reorderState,
  deletionState,
  duplicationState,
  topics,
  handleReorderTopics,
  handleTopicDelete,
  handleTopicDuplicate,
}: UseCurriculumErrorOptions): UseCurriculumErrorReturn {
  const { showError, handleDismissError } = useError({
    states: [reorderState, deletionState, duplicationState],
    isError: (state) => state.status === "error",
  });

  /** Validate API response format */
  const validateApiResponse = useCallback((response: unknown): Topic[] => {
    // Check if response is an object with the expected structure
    if (!response || typeof response !== "object") {
      throw new Error("Invalid response format: expected an object");
    }

    const apiResponse = response as { success: boolean; message: string; data: unknown };

    // Check if response has the expected properties
    if (!("success" in apiResponse) || !("data" in apiResponse)) {
      throw new Error("Invalid response format: missing success or data property");
    }

    // Check if data is an array
    if (!Array.isArray(apiResponse.data)) {
      throw new Error("Invalid response format: data property should be an array");
    }

    // Transform each topic in the array
    return apiResponse.data.map((topic: unknown) => {
      if (!topic || typeof topic !== "object") {
        throw new Error("Invalid topic format in response");
      }
      return {
        ...topic,
        isCollapsed: true,
      } as Topic;
    });
  }, []);

  /** Create a standardized curriculum error */
  const createCurriculumError = useCallback((error: unknown, context: { action: string }): CurriculumError => {
    return {
      code: CurriculumErrorCode.FETCH_FAILED,
      message: error instanceof Error ? error.message : __("Failed to load topics", "tutorpress"),
      context,
    };
  }, []);

  /** Handle retry for failed operations */
  const handleRetry = useCallback(async () => {
    if (reorderState.status === "error") {
      await handleReorderTopics(topics);
    } else if (deletionState.status === "error" && deletionState.topicId) {
      await handleTopicDelete(deletionState.topicId);
    } else if (duplicationState.status === "error" && duplicationState.sourceTopicId) {
      await handleTopicDuplicate(duplicationState.sourceTopicId);
    }
  }, [
    reorderState.status,
    deletionState,
    duplicationState,
    topics,
    handleReorderTopics,
    handleTopicDelete,
    handleTopicDuplicate,
  ]);

  return {
    showError,
    handleDismissError,
    handleRetry,
    getErrorMessage,
    validateApiResponse,
    createCurriculumError,
  };
}
