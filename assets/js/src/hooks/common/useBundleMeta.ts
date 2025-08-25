import { useEntityProp } from "@wordpress/core-data";

export interface UseBundleMetaReturn {
  meta: Record<string, any> | undefined;
  setMeta: (v: Record<string, any> | ((prev: Record<string, any> | undefined) => Record<string, any>)) => void;
  ready: boolean;
  safeSet: (partial: Record<string, any>) => void;
  setKey: (key: string, value: any) => void;
}

/**
 * Thin wrapper around useEntityProp for course-bundle meta.
 */
export default function useBundleMeta(): UseBundleMetaReturn {
  const [meta, setMeta] = useEntityProp("postType", "course-bundle", "meta") as any;

  const ready = typeof meta !== "undefined";

  const safeSet = (partial: Record<string, any>) => {
    setMeta((prev: Record<string, any> | undefined) => ({ ...(prev || {}), ...partial }));
  };

  const setKey = (key: string, value: any) => {
    setMeta((prev: Record<string, any> | undefined) => ({ ...(prev || {}), [key]: value }));
  };

  return { meta, setMeta, ready, safeSet, setKey };
}


