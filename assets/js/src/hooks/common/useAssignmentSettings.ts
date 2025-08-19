import { useEntitySettings } from "./useEntitySettings";

type AssignmentSettings = Record<string, any>;

export function useAssignmentSettings() {
  const { value, setValue, ready, safeSet } = useEntitySettings<AssignmentSettings>(
    "tutor_assignments",
    "assignment_settings"
  );
  return {
    assignmentSettings: value,
    setAssignmentSettings: setValue,
    ready,
    safeSet,
  };
}
