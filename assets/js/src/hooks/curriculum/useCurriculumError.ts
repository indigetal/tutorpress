import { useCallback } from "react";
import {
  Topic,
  CurriculumError,
  ReorderOperationState,
  TopicDeletionState,
  TopicDuplicationState,
  OperationResult,
} from "../../types/curriculum";
import { useError } from "../useError";
import { getErrorMessage } from "../../utils/errors";

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
  };
}
