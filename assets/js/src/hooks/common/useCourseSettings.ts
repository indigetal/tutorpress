import type { CourseSettings } from "../../types/courses";
import { useEntitySettings } from "./useEntitySettings";

export type UseCourseSettingsReturn = {
  courseSettings: CourseSettings | undefined;
  setCourseSettings: (next: CourseSettings) => void;
  ready: boolean;
  safeSet: (partial: Partial<CourseSettings>) => void;
};

export function useCourseSettings(): UseCourseSettingsReturn {
  const { value, setValue, ready, safeSet } = useEntitySettings<CourseSettings>("courses", "course_settings");
  return { courseSettings: value, setCourseSettings: setValue, ready, safeSet };
}
