import { useEntitySettings } from "./useEntitySettings";

type LessonSettings = Record<string, any>;

export function useLessonSettings() {
  const { value, setValue, ready, safeSet } = useEntitySettings<LessonSettings>("lesson", "lesson_settings");
  return {
    lessonSettings: value,
    setLessonSettings: setValue,
    ready,
    safeSet,
  };
}
