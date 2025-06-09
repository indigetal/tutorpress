import { useState, useCallback, useEffect } from "react";

export interface UseErrorOptions<T extends { status: string }> {
  states: T[];
  isError: (state: T) => boolean;
}

export interface UseErrorReturn {
  showError: boolean;
  handleDismissError: () => void;
}

/**
 * Generic error handling hook that manages error visibility state
 * based on provided error states and error detection logic
 */
export function useError<T extends { status: string }>({ states, isError }: UseErrorOptions<T>): UseErrorReturn {
  const [showError, setShowError] = useState(false);

  const handleDismissError = useCallback(() => {
    setShowError(false);
  }, []);

  useEffect(() => {
    const hasError = states.some(isError);
    setShowError(hasError);
  }, [states, isError]);

  return {
    showError,
    handleDismissError,
  };
}
