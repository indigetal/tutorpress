/**
 * Content Drip Selectors
 * Selectors for accessing content drip state
 */

import type { ContentDripItemSettings, PrerequisitesByTopic, ContentDripStoreState } from "../../../types/content-drip";

// State interface extension
export interface AdditionalContentStoreStateWithContentDrip {
  contentDripItems: {
    [postId: number]: {
      settings: ContentDripItemSettings;
      loading: boolean;
      error: string | null;
      saving: boolean;
      saveError: string | null;
    };
  };
  prerequisites: {
    [courseId: number]: {
      data: PrerequisitesByTopic[];
      loading: boolean;
      error: string | null;
    };
  };
}

// Selectors
export const getContentDripSettings = (state: any, postId: number): ContentDripItemSettings | null => {
  return state.contentDripItems?.[postId]?.settings || null;
};

export const isContentDripLoading = (state: any, postId: number): boolean => {
  return state.contentDripItems?.[postId]?.loading || false;
};

export const getContentDripError = (state: any, postId: number): string | null => {
  return state.contentDripItems?.[postId]?.error || null;
};

export const isContentDripSaving = (state: any, postId: number): boolean => {
  return state.contentDripItems?.[postId]?.saving || false;
};

export const getContentDripSaveError = (state: any, postId: number): string | null => {
  return state.contentDripItems?.[postId]?.saveError || null;
};

export const getPrerequisites = (state: any, courseId: number): PrerequisitesByTopic[] | null => {
  return state.prerequisites?.[courseId]?.data || null;
};

export const isPrerequisitesLoading = (state: any, courseId: number): boolean => {
  return state.prerequisites?.[courseId]?.loading || false;
};

export const getPrerequisitesError = (state: any, courseId: number): string | null => {
  return state.prerequisites?.[courseId]?.error || null;
};

// Computed selectors
export const hasContentDripSettings = (state: any, postId: number): boolean => {
  const settings = getContentDripSettings(state, postId);
  return settings !== null;
};

export const isContentDripEnabled = (state: any, postId: number): boolean => {
  const dripInfo = state.dripInfoByPostId?.[postId];
  return dripInfo?.enabled === true;
};

export const getContentDripType = (state: any, postId: number): string | null => {
  const dripInfo = state.dripInfoByPostId?.[postId];
  return dripInfo?.type || null;
};

export const hasPrerequisites = (state: any, courseId: number): boolean => {
  const prerequisites = getPrerequisites(state, courseId);
  return prerequisites !== null && prerequisites.length > 0;
};

// Utility selectors for component usage
export const getContentDripInfo = (state: any, postId: number) => ({
  settings: getContentDripSettings(state, postId),
  loading: isContentDripLoading(state, postId),
  error: getContentDripError(state, postId),
  saving: isContentDripSaving(state, postId),
  saveError: getContentDripSaveError(state, postId),
  hasSettings: hasContentDripSettings(state, postId),
  isEnabled: isContentDripEnabled(state, postId),
  dripType: getContentDripType(state, postId),
});

export const getPrerequisitesInfo = (state: any, courseId: number) => ({
  prerequisites: getPrerequisites(state, courseId),
  loading: isPrerequisitesLoading(state, courseId),
  error: getPrerequisitesError(state, courseId),
  hasPrerequisites: hasPrerequisites(state, courseId),
});
