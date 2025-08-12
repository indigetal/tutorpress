/**
 * Prerequisites Store
 *
 * Dedicated feature store for fetching available courses used by the
 * prerequisites control in Course Access & Enrollment.
 */

import { createReduxStore, register, select } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";

// Types kept deliberately light; matches the reusable course search endpoint
export interface AvailableCourse {
  id: number;
  title: string;
  permalink?: string;
  featured_image?: string;
  author?: string;
  date_created?: string;
  price?: string;
  duration?: string;
  lesson_count?: number;
  quiz_count?: number;
  resource_count?: number;
}

interface PrerequisitesState {
  availableCourses: AvailableCourse[];
  isLoading: boolean;
  error: string | null;
  lastFetchedCourseId: number | null;
}

const initialState: PrerequisitesState = {
  availableCourses: [],
  isLoading: false,
  error: null,
  lastFetchedCourseId: null,
};

const ACTION_TYPES = {
  SET_LOADING: "SET_LOADING",
  SET_ERROR: "SET_ERROR",
  SET_AVAILABLE_COURSES: "SET_AVAILABLE_COURSES",
  SET_LAST_FETCHED: "SET_LAST_FETCHED",
} as const;

type PrerequisitesAction =
  | { type: "SET_LOADING"; payload: boolean }
  | { type: "SET_ERROR"; payload: string | null }
  | { type: "SET_AVAILABLE_COURSES"; payload: AvailableCourse[] }
  | { type: "SET_LAST_FETCHED"; payload: number | null };

const actions = {
  *fetchAvailableCourses(): Generator<any, any, any> {
    // Guard: need a current course ID; also avoid refetch if already up-to-date
    const courseId: number | null = (yield select("core/editor").getCurrentPostId()) || null;
    if (!courseId) {
      return;
    }

    const lastFetched: number | null = yield select("tutorpress/prerequisites").getLastFetchedCourseId();
    const existing: AvailableCourse[] = yield select("tutorpress/prerequisites").getAvailableCourses();
    if (lastFetched === courseId && existing && existing.length > 0) {
      return existing;
    }

    yield { type: ACTION_TYPES.SET_LOADING, payload: true };
    yield { type: ACTION_TYPES.SET_ERROR, payload: null };

    try {
      // Keep per_page modest; UX for long lists can be improved later
      const path = `/tutorpress/v1/courses/search?exclude=${courseId}&per_page=25&status=publish`;
      const response: { success?: boolean; data?: AvailableCourse[] } = (yield {
        type: "API_FETCH",
        request: { path, method: "GET" },
      }) as any;

      const list = (response && (response as any).data) || [];
      yield { type: ACTION_TYPES.SET_AVAILABLE_COURSES, payload: list };
      yield { type: ACTION_TYPES.SET_LAST_FETCHED, payload: courseId };
      return list;
    } catch (error: any) {
      const msg = error?.message || "Failed to fetch available courses";
      yield { type: ACTION_TYPES.SET_ERROR, payload: msg };
      throw error;
    } finally {
      yield { type: ACTION_TYPES.SET_LOADING, payload: false };
    }
  },
};

const selectors = {
  getAvailableCourses(state: PrerequisitesState): AvailableCourse[] {
    return state.availableCourses;
  },
  getCourseSelectionLoading(state: PrerequisitesState): boolean {
    return state.isLoading;
  },
  getCourseSelectionError(state: PrerequisitesState): string | null {
    return state.error;
  },
  getLastFetchedCourseId(state: PrerequisitesState): number | null {
    return state.lastFetchedCourseId;
  },
};

const store = createReduxStore("tutorpress/prerequisites", {
  reducer(
    state: PrerequisitesState = initialState,
    action: PrerequisitesAction | { type: string }
  ): PrerequisitesState {
    switch (action.type) {
      case ACTION_TYPES.SET_LOADING:
        return { ...state, isLoading: (action as any).payload };
      case ACTION_TYPES.SET_ERROR:
        return { ...state, error: (action as any).payload };
      case ACTION_TYPES.SET_AVAILABLE_COURSES:
        return { ...state, availableCourses: (action as any).payload };
      case ACTION_TYPES.SET_LAST_FETCHED:
        return { ...state, lastFetchedCourseId: (action as any).payload };
      default:
        return state;
    }
  },
  actions: { ...actions, fetchAvailableCourses: actions.fetchAvailableCourses },
  selectors,
  controls,
});

register(store);

export default store;
export const { fetchAvailableCourses } = actions as any;
export const { getAvailableCourses, getCourseSelectionLoading, getCourseSelectionError, getLastFetchedCourseId } =
  selectors as any;
