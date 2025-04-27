/**
 * Hook for managing state snapshots in the curriculum builder
 *
 * Provides a generic mechanism for creating, restoring, and managing snapshots
 * of curriculum state, without knowledge of specific operations.
 */
import { useState, useCallback } from "react";
import { Topic } from "../../types/curriculum";

// ============================================================================
// Types
// ============================================================================

/**
 * Snapshot of curriculum state
 *
 * @property topics - Array of topics to backup
 * @property timestamp - When the snapshot was created
 * @property operation - Type of operation that triggered the snapshot
 */
export interface CurriculumSnapshot {
  topics: Topic[];
  timestamp: number;
  operation: "reorder" | "edit" | "delete" | "duplicate";
}

/**
 * Configuration options for the useSnapshot hook
 */
export interface UseSnapshotOptions {
  /** Current topics array to snapshot */
  topics: Topic[];
  /** Function to update topics when restoring */
  setTopics: React.Dispatch<React.SetStateAction<Topic[]>>;
}

/**
 * Return type for the useSnapshot hook
 */
export interface UseSnapshotReturn {
  /** Current snapshot if one exists */
  snapshot: CurriculumSnapshot | null;
  /** Create a new snapshot */
  createSnapshot: (operation: CurriculumSnapshot["operation"]) => void;
  /** Restore from current snapshot */
  restoreFromSnapshot: () => boolean;
  /** Clear current snapshot */
  clearSnapshot: () => void;
}

/**
 * Hook for managing curriculum state snapshots
 *
 * @param options Hook configuration options
 * @returns Snapshot state and operations
 */
export function useSnapshot({ topics, setTopics }: UseSnapshotOptions): UseSnapshotReturn {
  // =============================
  // State
  // =============================
  const [snapshot, setSnapshot] = useState<CurriculumSnapshot | null>(null);

  // =============================
  // Operations
  // =============================

  /** Create a new snapshot of the current state */
  const createSnapshot = useCallback(
    (operation: CurriculumSnapshot["operation"]) => {
      setSnapshot({
        topics: [...topics],
        timestamp: Date.now(),
        operation,
      });
    },
    [topics]
  );

  /** Restore state from the current snapshot */
  const restoreFromSnapshot = useCallback(() => {
    if (snapshot) {
      setTopics(snapshot.topics);
      setSnapshot(null);
      return true;
    }
    return false;
  }, [snapshot, setTopics]);

  /** Clear the current snapshot */
  const clearSnapshot = useCallback(() => {
    setSnapshot(null);
  }, []);

  return {
    snapshot,
    createSnapshot,
    restoreFromSnapshot,
    clearSnapshot,
  };
}
