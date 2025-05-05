import { useEffect } from "react";
import { Topic } from "../../types/curriculum";

const STORAGE_KEY = "tutorpress_curriculum_state";

interface StoredState {
  topics: Topic[];
  courseId: number;
  timestamp: number;
}

export function useStatePersistence(courseId: number, topics: Topic[], setTopics: (topics: Topic[]) => void) {
  // Save state to localStorage when topics change
  useEffect(() => {
    if (topics.length > 0) {
      const state: StoredState = {
        topics,
        courseId,
        timestamp: Date.now(),
      };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    }
  }, [topics, courseId]);

  // Restore state from localStorage on mount
  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        const state: StoredState = JSON.parse(stored);
        const age = Date.now() - state.timestamp;

        // Only restore if it's for the same course and less than 1 hour old
        if (state.courseId === courseId && age < 3600000) {
          setTopics(state.topics);
        } else {
          localStorage.removeItem(STORAGE_KEY);
        }
      }
    } catch (error) {
      console.error("Error restoring curriculum state:", error);
      localStorage.removeItem(STORAGE_KEY);
    }
  }, [courseId, setTopics]);
}
