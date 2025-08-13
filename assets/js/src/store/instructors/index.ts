/**
 * Instructors Store (tutorpress/instructors)
 *
 * Dedicated feature store for course instructors domain:
 * - Fetch current author and co-instructors for a course
 * - Search for instructors to add
 * - Update author and co-instructors (kept for transition; panel will move IDs to entity)
 *
 * This store exposes display/search data only; co-instructor IDs will live
 * in the entity prop `course_settings.instructors`.
 */

import { createReduxStore, register, select } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";
import type { InstructorUser, InstructorSearchResult } from "../../types/courses";

interface InstructorsState {
  author: InstructorUser | null;
  coInstructors: InstructorUser[];
  searchResults: InstructorSearchResult[];
  isLoading: boolean;
  error: string | null;
  isSearching: boolean;
  searchError: string | null;
  lastFetchedCourseId: number | null;
}

const initialState: InstructorsState = {
  author: null,
  coInstructors: [],
  searchResults: [],
  isLoading: false,
  error: null,
  isSearching: false,
  searchError: null,
  lastFetchedCourseId: null,
};

type InstructorsAction =
  | { type: "SET_LOADING"; payload: boolean }
  | { type: "SET_ERROR"; payload: string | null }
  | { type: "SET_SEARCHING"; payload: boolean }
  | { type: "SET_SEARCH_ERROR"; payload: string | null }
  | { type: "SET_AUTHOR"; payload: InstructorUser | null }
  | { type: "SET_CO_INSTRUCTORS"; payload: InstructorUser[] }
  | { type: "SET_SEARCH_RESULTS"; payload: InstructorSearchResult[] }
  | { type: "SET_LAST_FETCHED"; payload: number | null };

const actions = {
  setLoading(isLoading: boolean) {
    return { type: "SET_LOADING" as const, payload: isLoading };
  },
  setError(error: string | null) {
    return { type: "SET_ERROR" as const, payload: error };
  },
  setSearching(isSearching: boolean) {
    return { type: "SET_SEARCHING" as const, payload: isSearching };
  },
  setSearchError(error: string | null) {
    return { type: "SET_SEARCH_ERROR" as const, payload: error };
  },
  setAuthor(author: InstructorUser | null) {
    return { type: "SET_AUTHOR" as const, payload: author };
  },
  setCoInstructors(list: InstructorUser[]) {
    return { type: "SET_CO_INSTRUCTORS" as const, payload: list };
  },
  setSearchResults(list: InstructorSearchResult[]) {
    return { type: "SET_SEARCH_RESULTS" as const, payload: list };
  },
  setLastFetchedCourseId(courseId: number | null) {
    return { type: "SET_LAST_FETCHED" as const, payload: courseId };
  },

  /**
   * Fetch course instructors (author + co-instructors) for current course.
   * Cache-aware: if already fetched for this course and we have data, skip.
   */
  *fetchCourseInstructors(force?: boolean): Generator<any, any, any> {
    const courseId: number | null = (yield select("core/editor").getCurrentPostId()) || null;
    if (!courseId) {
      return;
    }

    if (!force) {
      const lastFetched: number | null = yield select("tutorpress/instructors").getLastFetchedCourseId();
      const existingAuthor: InstructorUser | null = yield select("tutorpress/instructors").getAuthor();
      const existingCo: InstructorUser[] = yield select("tutorpress/instructors").getCoInstructors();
      if (lastFetched === courseId && (existingAuthor || (existingCo && existingCo.length > 0))) {
        return { author: existingAuthor, co_instructors: existingCo };
      }
    }

    try {
      yield actions.setLoading(true);
      yield actions.setError(null);

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings/instructors`,
          method: "GET",
        },
      };

      if (!response || response.success === false) {
        const msg = (response && response.message) || "Failed to fetch instructors";
        throw new Error(msg);
      }

      const data = (response && response.data) || {};
      const author: InstructorUser | null = data.author || null;
      const co: InstructorUser[] = data.co_instructors || [];

      yield actions.setAuthor(author);
      yield actions.setCoInstructors(co);
      yield actions.setLastFetchedCourseId(courseId);
    } catch (error: any) {
      yield actions.setError(error?.message || "Failed to fetch instructors");
      throw error;
    } finally {
      yield actions.setLoading(false);
    }
  },

  /**
   * Search for instructors to add (excludes current author and co-instructors on the server).
   */
  *searchInstructors(search: string): Generator<any, any, any> {
    const courseId: number | null = (yield select("core/editor").getCurrentPostId()) || null;
    if (!courseId) {
      return;
    }
    try {
      yield actions.setSearching(true);
      yield actions.setSearchError(null);

      const query = new URLSearchParams();
      if (search && search.trim()) {
        query.append("search", search.trim());
      }

      const response = yield {
        type: "API_FETCH",
        request: {
          path: `/tutorpress/v1/courses/${courseId}/settings/instructors/search?${query.toString()}`,
          method: "GET",
        },
      };

      if (!response || response.success === false) {
        const msg = (response && response.message) || "Failed to search instructors";
        throw new Error(msg);
      }

      yield actions.setSearchResults((response && response.data) || []);
    } catch (error: any) {
      yield actions.setSearchError(error?.message || "Failed to search instructors");
      throw error;
    } finally {
      yield actions.setSearching(false);
    }
  },
};

const selectors = {
  getAuthor(state: InstructorsState): InstructorUser | null {
    return state.author;
  },
  getCoInstructors(state: InstructorsState): InstructorUser[] {
    return state.coInstructors;
  },
  getSearchResults(state: InstructorsState): InstructorSearchResult[] {
    return state.searchResults;
  },
  getIsLoading(state: InstructorsState): boolean {
    return state.isLoading;
  },
  getError(state: InstructorsState): string | null {
    return state.error;
  },
  getIsSearching(state: InstructorsState): boolean {
    return state.isSearching;
  },
  getSearchError(state: InstructorsState): string | null {
    return state.searchError;
  },
  getLastFetchedCourseId(state: InstructorsState): number | null {
    return state.lastFetchedCourseId;
  },
};

const store = createReduxStore("tutorpress/instructors", {
  reducer(
    state: InstructorsState = initialState,
    action: InstructorsAction | { type: string; payload?: any }
  ): InstructorsState {
    switch (action.type) {
      case "SET_LOADING":
        return { ...state, isLoading: (action as any).payload };
      case "SET_ERROR":
        return { ...state, error: (action as any).payload };
      case "SET_SEARCHING":
        return { ...state, isSearching: (action as any).payload };
      case "SET_SEARCH_ERROR":
        return { ...state, searchError: (action as any).payload };
      case "SET_AUTHOR":
        return { ...state, author: (action as any).payload };
      case "SET_CO_INSTRUCTORS":
        return { ...state, coInstructors: (action as any).payload };
      case "SET_SEARCH_RESULTS":
        return { ...state, searchResults: (action as any).payload };
      case "SET_LAST_FETCHED":
        return { ...state, lastFetchedCourseId: (action as any).payload };
      default:
        return state;
    }
  },
  actions: { ...actions },
  selectors,
  controls,
});

register(store);

export default store;
export const instructorsStore = store;
export const { fetchCourseInstructors, searchInstructors } = actions as any;
export const {
  getAuthor,
  getCoInstructors,
  getSearchResults,
  getIsLoading,
  getError,
  getIsSearching,
  getSearchError,
  getLastFetchedCourseId,
} = selectors as any;
