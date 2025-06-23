/**
 * Content Drip Actions
 * Action creators for content drip functionality
 */

import type {
  ContentDripItemSettings,
  ContentDripInfo,
  PrerequisitesByTopic,
  ContentDripSaveResponse,
  PrerequisitesResponse,
} from "../../../types/content-drip";

// Action Types
export const CONTENT_DRIP_ACTION_TYPES = {
  SET_CONTENT_DRIP_ITEM_SETTINGS: "SET_CONTENT_DRIP_ITEM_SETTINGS", // Changed: for individual items (lessons/assignments)
  SET_CONTENT_DRIP_LOADING: "SET_CONTENT_DRIP_LOADING",
  SET_CONTENT_DRIP_ERROR: "SET_CONTENT_DRIP_ERROR",
  SET_CONTENT_DRIP_SAVING: "SET_CONTENT_DRIP_SAVING",
  SET_CONTENT_DRIP_SAVE_ERROR: "SET_CONTENT_DRIP_SAVE_ERROR",
  SET_PREREQUISITES: "SET_PREREQUISITES",
  SET_PREREQUISITES_LOADING: "SET_PREREQUISITES_LOADING",
  SET_PREREQUISITES_ERROR: "SET_PREREQUISITES_ERROR",
} as const;

// Action Creators
export const setContentDripSettings = (postId: number, settings: ContentDripItemSettings) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ITEM_SETTINGS,
  payload: { postId, settings },
});

export const setContentDripLoading = (postId: number, loading: boolean) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_LOADING,
  payload: { postId, loading },
});

export const setContentDripError = (postId: number, error: string | null) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_ERROR,
  payload: { postId, error },
});

export const setContentDripSaving = (postId: number, saving: boolean) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVING,
  payload: { postId, saving },
});

export const setContentDripSaveError = (postId: number, error: string | null) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_CONTENT_DRIP_SAVE_ERROR,
  payload: { postId, error },
});

export const setPrerequisites = (courseId: number, prerequisites: PrerequisitesByTopic[]) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES,
  payload: { courseId, prerequisites },
});

export const setPrerequisitesLoading = (courseId: number, loading: boolean) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_LOADING,
  payload: { courseId, loading },
});

export const setPrerequisitesError = (courseId: number, error: string | null) => ({
  type: CONTENT_DRIP_ACTION_TYPES.SET_PREREQUISITES_ERROR,
  payload: { courseId, error },
});

// Async Action Creators
export const updateContentDripSettings = (postId: number, settings: ContentDripItemSettings) => ({
  type: "UPDATE_CONTENT_DRIP_SETTINGS",
  payload: { postId, settings },
});

export const duplicateContentDripSettings = (sourcePostId: number, targetPostId: number) => ({
  type: "DUPLICATE_CONTENT_DRIP_SETTINGS",
  payload: { sourcePostId, targetPostId },
});

// Action type definitions for TypeScript
export type ContentDripAction =
  | ReturnType<typeof setContentDripSettings>
  | ReturnType<typeof setContentDripLoading>
  | ReturnType<typeof setContentDripError>
  | ReturnType<typeof setContentDripSaving>
  | ReturnType<typeof setContentDripSaveError>
  | ReturnType<typeof setPrerequisites>
  | ReturnType<typeof setPrerequisitesLoading>
  | ReturnType<typeof setPrerequisitesError>
  | ReturnType<typeof updateContentDripSettings>
  | ReturnType<typeof duplicateContentDripSettings>;
