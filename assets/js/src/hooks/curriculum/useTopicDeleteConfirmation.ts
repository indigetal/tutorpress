/**
 * Bounded confirmation controller for topic deletion.
 *
 * Owns request, confirmation, cancellation, and immediate duplicate-submit
 * protection. Does not inspect topic metadata or perform the deletion mutation.
 */
import { useCallback, useRef, useState } from "react";

export interface UseTopicDeleteConfirmationReturn {
  requestedTopicId: number | null;
  isOpen: boolean;
  isBusy: boolean;
  requestTopicDelete: (topicId: number) => void;
  cancelTopicDelete: () => void;
  confirmTopicDelete: () => Promise<void>;
}

export function useTopicDeleteConfirmation(
  continueTopicDelete: (topicId: number) => Promise<void>,
): UseTopicDeleteConfirmationReturn {
  const [requestedTopicId, setRequestedTopicId] = useState<number | null>(null);
  const [isBusy, setIsBusy] = useState(false);
  const submissionLatch = useRef(false);

  const requestTopicDelete = useCallback((topicId: number) => {
    if (submissionLatch.current) {
      return;
    }
    setRequestedTopicId(topicId);
  }, []);

  const cancelTopicDelete = useCallback(() => {
    if (submissionLatch.current) {
      return;
    }
    setRequestedTopicId(null);
  }, []);

  const confirmTopicDelete = useCallback(async () => {
    if (submissionLatch.current || requestedTopicId === null) {
      return;
    }

    const topicId = requestedTopicId;
    submissionLatch.current = true;
    setIsBusy(true);

    try {
      await continueTopicDelete(topicId);
    } finally {
      setRequestedTopicId(null);
      setIsBusy(false);
      submissionLatch.current = false;
    }
  }, [continueTopicDelete, requestedTopicId]);

  return {
    requestedTopicId,
    isOpen: requestedTopicId !== null,
    isBusy,
    requestTopicDelete,
    cancelTopicDelete,
    confirmTopicDelete,
  };
}
