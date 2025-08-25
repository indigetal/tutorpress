/**
 * Common Hooks Exports
 *
 * @description Centralized exports for reusable hooks that can be used across different modal types.
 *              These hooks provide generic functionality for form state management, validation,
 *              and other common patterns extracted from specific implementations.
 *
 * @package TutorPress
 * @subpackage Common/Hooks
 * @since 1.0.0
 */

// Form State Management
export {
  useModalFormState,
  createDefaultValidationRules,
  mergeValidationErrors,
  createNumericValidationRule,
  createRequiredFieldValidationRule,
  type ModalFormErrors,
  type ModalFormState,
  type ValidationRule,
  type ModalFormInitialData,
  type UseModalFormStateOptions,
  type UseModalFormStateReturn,
} from "./useModalFormState";

export {
  useSortableList,
  createLocalReorderHandler,
  isWpRestResponse,
  type SortableItem,
  type SortableError,
  type OperationResult,
  type PersistenceMode,
  type SortableContext,
  type OperationState,
  type ReorderOperationState,
  type DragState,
  type DragHandlers,
  type UseSortableListOptions,
  type UseSortableListReturn,
} from "./useSortableList";

export { useEntitySettings, type UseEntitySettingsReturn } from "./useEntitySettings";
export { useCourseSettings, type UseCourseSettingsReturn } from "./useCourseSettings";
export { useLessonSettings } from "./useLessonSettings";
export { useAssignmentSettings } from "./useAssignmentSettings";
export { default as useBundleMeta, type UseBundleMetaReturn } from "./useBundleMeta";
