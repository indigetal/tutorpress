/**
 * Attachments Metadata Store
 *
 * Caches attachment metadata by id for panels that render attachment lists.
 * Ids remain in course_settings via useEntityProp; this store only fetches metadata.
 */

import { createReduxStore, register, select } from "@wordpress/data";
import { controls } from "@wordpress/data-controls";

export interface AttachmentMeta {
  id: number;
  filename: string;
  url?: string;
  mime_type?: string;
}

interface AttachmentsMetaState {
  byId: Record<number, AttachmentMeta>;
  isLoading: boolean;
  error: string | null;
}

const initialState: AttachmentsMetaState = {
  byId: {},
  isLoading: false,
  error: null,
};

const ACTION_TYPES = {
  SET_LOADING: "SET_LOADING",
  SET_ERROR: "SET_ERROR",
  MERGE_METADATA: "MERGE_METADATA",
} as const;

type AttachmentsMetaAction =
  | { type: "SET_LOADING"; payload: boolean }
  | { type: "SET_ERROR"; payload: string | null }
  | { type: "MERGE_METADATA"; payload: AttachmentMeta[] };

const actions = {
  *fetchAttachmentsMetadata(ids: number[]): Generator<any, any, any> {
    const distinctIds = Array.from(new Set((ids || []).filter((n) => Number.isFinite(n)))) as number[];
    if (distinctIds.length === 0) return [];

    // Only fetch missing ids (cache-aware)
    const existingById: Record<number, AttachmentMeta> = yield select("tutorpress/attachments-meta").getById();
    const missingIds = distinctIds.filter((id) => !existingById[id]);
    if (missingIds.length === 0) return distinctIds.map((id) => existingById[id]);

    yield { type: ACTION_TYPES.SET_LOADING, payload: true };
    yield { type: ACTION_TYPES.SET_ERROR, payload: null };

    try {
      const courseId: number | null = (yield select("core/editor").getCurrentPostId()) || null;
      // API requires courseId context; gracefully handle absence
      const query = new URLSearchParams();
      missingIds.forEach((id) => query.append("attachment_ids[]", String(id)));
      const path = courseId
        ? `/tutorpress/v1/courses/${courseId}/attachments?${query.toString()}`
        : `/tutorpress/v1/courses/0/attachments?${query.toString()}`; // fallback; server may ignore

      const response: { success?: boolean; data?: AttachmentMeta[] } = (yield {
        type: "API_FETCH",
        request: { path, method: "GET" },
      }) as any;

      const list: AttachmentMeta[] = (response && (response as any).data) || [];
      yield { type: ACTION_TYPES.MERGE_METADATA, payload: list };
      return list;
    } catch (error: any) {
      yield { type: ACTION_TYPES.SET_ERROR, payload: error?.message || "Failed to fetch attachments metadata" };
      throw error;
    } finally {
      yield { type: ACTION_TYPES.SET_LOADING, payload: false };
    }
  },
};

const selectors = {
  getById(state: AttachmentsMetaState): Record<number, AttachmentMeta> {
    return state.byId;
  },
  getAttachmentsMetadata(state: AttachmentsMetaState, ids?: number[]): AttachmentMeta[] {
    if (!ids || ids.length === 0) return Object.values(state.byId);
    return ids.map((id) => state.byId[id]).filter(Boolean) as AttachmentMeta[];
  },
  getAttachmentsLoading(state: AttachmentsMetaState): boolean {
    return state.isLoading;
  },
  getAttachmentsError(state: AttachmentsMetaState): string | null {
    return state.error;
  },
};

const store = createReduxStore("tutorpress/attachments-meta", {
  reducer(state: AttachmentsMetaState = initialState, action: AttachmentsMetaAction | { type: string }) {
    switch (action.type) {
      case ACTION_TYPES.SET_LOADING:
        return { ...state, isLoading: (action as any).payload };
      case ACTION_TYPES.SET_ERROR:
        return { ...state, error: (action as any).payload };
      case ACTION_TYPES.MERGE_METADATA: {
        const next = { ...state.byId } as Record<number, AttachmentMeta>;
        for (const meta of (action as any).payload as AttachmentMeta[]) {
          if (meta && typeof meta.id === "number") next[meta.id] = meta;
        }
        return { ...state, byId: next };
      }
      default:
        return state;
    }
  },
  actions: { ...actions, fetchAttachmentsMetadata: actions.fetchAttachmentsMetadata },
  selectors,
  controls,
});

register(store);

export default store;
export const { fetchAttachmentsMetadata } = actions as any;
export const { getById, getAttachmentsMetadata, getAttachmentsLoading, getAttachmentsError } = selectors as any;
