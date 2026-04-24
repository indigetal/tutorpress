import type { CourseSettings } from "../../types/courses";
import { useEntitySettings } from "./useEntitySettings";

type CourseSettingsUpdater =
  | CourseSettings
  | ((prev: CourseSettings | undefined) => CourseSettings);

type CourseSettingsPartialUpdater =
  | Partial<CourseSettings>
  | ((prev: CourseSettings | undefined) => Partial<CourseSettings>);

export type UseCourseSettingsReturn = {
  courseSettings: CourseSettings | undefined;
  setCourseSettings: (next: CourseSettingsUpdater) => void;
  ready: boolean;
  safeSet: (partial: CourseSettingsPartialUpdater) => void;
};

export function useCourseSettings(): UseCourseSettingsReturn {
  const { value, setValue, ready, safeSet } = useEntitySettings<CourseSettings>("courses", "course_settings");

  const setCourseSettings = (next: CourseSettingsUpdater) => {
    const resolved = typeof next === "function" ? next(value) : next;
    setValue(resolved);
  };

  const safeSetCourseSettings = (partial: CourseSettingsPartialUpdater) => {
    const resolved = typeof partial === "function" ? partial(value) : partial;
    safeSet(resolved);
  };

  return { courseSettings: value, setCourseSettings, ready, safeSet: safeSetCourseSettings };
}
