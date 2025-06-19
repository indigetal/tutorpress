/**
 * Selector factory utilities for curriculum store
 * Reduces repetitive selector boilerplate by generating common selector patterns
 */

import { CurriculumState } from "../index";

/**
 * Configuration for creating content selectors
 */
export interface ContentSelectorConfig {
  /** The state property name (e.g., 'quizState', 'lessonState') */
  stateKey: keyof CurriculumState;
  /** The prefix for selector names (e.g., 'Quiz', 'Lesson') */
  prefix: string;
  /** Whether this content type supports saving operations */
  supportsSaving?: boolean;
  /** Whether this content type supports deleting operations */
  supportsDeleting?: boolean;
  /** Whether this content type supports duplicating operations */
  supportsDuplicating?: boolean;
  /** Custom active ID field name (defaults to 'active{Prefix}Id') */
  activeIdField?: string;
  /** Custom last saved ID field name (defaults to 'lastSaved{Prefix}Id') */
  lastSavedIdField?: string;
}

/**
 * Generated selector functions for a content type
 */
export interface ContentSelectors {
  /** Get the full state object */
  getState: (state: CurriculumState) => any;
  /** Get the active content ID */
  getActiveId?: (state: CurriculumState) => number | undefined;
  /** Get the last saved content ID */
  getLastSavedId?: (state: CurriculumState) => number | undefined;
  /** Check if content is loading */
  isLoading: (state: CurriculumState) => boolean;
  /** Check if content is saving (if supported) */
  isSaving?: (state: CurriculumState) => boolean;
  /** Check if content is deleting (if supported) */
  isDeleting?: (state: CurriculumState) => boolean;
  /** Check if content is duplicating (if supported) */
  isDuplicating?: (state: CurriculumState) => boolean;
  /** Check if content has an error */
  hasError: (state: CurriculumState) => boolean;
  /** Get the error object */
  getError: (state: CurriculumState) => any;
}

/**
 * Create a set of standard selectors for a content type
 * @param config Configuration for the content type
 * @returns Object containing all generated selectors
 */
export function createContentSelectors(config: ContentSelectorConfig): ContentSelectors {
  const { stateKey, prefix, supportsSaving = false, supportsDeleting = false, supportsDuplicating = false } = config;

  // Determine field names
  const activeIdField = config.activeIdField || `active${prefix}Id`;
  const lastSavedIdField = config.lastSavedIdField || `lastSaved${prefix}Id`;

  const selectors: ContentSelectors = {
    // Get full state
    getState: (state: CurriculumState) => state[stateKey],

    // Loading state
    isLoading: (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.status === "loading";
    },

    // Error states
    hasError: (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.status === "error" || Boolean(contentState?.error);
    },

    getError: (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.error;
    },
  };

  // Add active ID selector if the field exists
  const sampleState = {} as any;
  if (activeIdField) {
    selectors.getActiveId = (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.[activeIdField];
    };
  }

  // Add last saved ID selector if the field exists and is not explicitly undefined
  if (lastSavedIdField !== undefined) {
    selectors.getLastSavedId = (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.[lastSavedIdField];
    };
  }

  // Add operation-specific selectors
  if (supportsSaving) {
    selectors.isSaving = (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.status === "saving";
    };
  }

  if (supportsDeleting) {
    selectors.isDeleting = (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.status === "deleting";
    };
  }

  if (supportsDuplicating) {
    selectors.isDuplicating = (state: CurriculumState) => {
      const contentState = state[stateKey] as any;
      return contentState?.status === "duplicating";
    };
  }

  return selectors;
}

/**
 * Pre-configured selector sets for common content types
 */

// Quiz selectors
export const quizSelectors = createContentSelectors({
  stateKey: "quizState",
  prefix: "Quiz",
  supportsSaving: true,
  supportsDeleting: true,
  supportsDuplicating: true,
  activeIdField: "activeQuizId",
  lastSavedIdField: "lastSavedQuizId",
});

// Lesson selectors
export const lessonSelectors = createContentSelectors({
  stateKey: "lessonState",
  prefix: "Lesson",
  supportsSaving: false,
  supportsDeleting: false,
  supportsDuplicating: false,
  activeIdField: "activeLessonId",
  lastSavedIdField: undefined, // Lesson state doesn't have lastSavedId
});

// Live Lesson selectors
export const liveLessonSelectors = createContentSelectors({
  stateKey: "liveLessonState",
  prefix: "LiveLesson",
  supportsSaving: true,
  supportsDeleting: true,
  supportsDuplicating: true,
  activeIdField: "activeLiveLessonId",
  lastSavedIdField: "lastSavedLiveLessonId",
});

/**
 * Helper function to create named selectors from a selector set
 * @param selectors The selector set from createContentSelectors
 * @param prefix The prefix for naming (e.g., 'quiz', 'lesson')
 * @returns Object with properly named selector functions
 */
export function createNamedSelectors<T extends ContentSelectors>(
  selectors: T,
  prefix: string
): Record<string, (state: CurriculumState) => any> {
  const named: Record<string, (state: CurriculumState) => any> = {};

  // Basic selectors
  named[`get${prefix}State`] = selectors.getState;
  named[`is${prefix}Loading`] = selectors.isLoading;
  named[`has${prefix}Error`] = selectors.hasError;
  named[`get${prefix}Error`] = selectors.getError;

  // Optional selectors
  if (selectors.getActiveId) {
    named[`getActive${prefix}Id`] = selectors.getActiveId;
  }

  if (selectors.getLastSavedId) {
    named[`getLastSaved${prefix}Id`] = selectors.getLastSavedId;
  }

  if (selectors.isSaving) {
    named[`is${prefix}Saving`] = selectors.isSaving;
  }

  if (selectors.isDeleting) {
    named[`is${prefix}Deleting`] = selectors.isDeleting;
  }

  if (selectors.isDuplicating) {
    named[`is${prefix}Duplicating`] = selectors.isDuplicating;
  }

  return named;
}

// Pre-generated named selectors
export const namedQuizSelectors = createNamedSelectors(quizSelectors, "Quiz");
export const namedLessonSelectors = createNamedSelectors(lessonSelectors, "Lesson");
export const namedLiveLessonSelectors = createNamedSelectors(liveLessonSelectors, "LiveLesson");
