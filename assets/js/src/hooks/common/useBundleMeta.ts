import { useEntityProp } from "@wordpress/core-data";

/**
 * Return type for useBundleMeta hook
 */
export interface UseBundleMetaReturn {
  meta: Record<string, any> | undefined;
  setMeta: (newMeta: Record<string, any>) => void;
  ready: boolean;
  safeSet: (partialMeta: Record<string, any>) => void;
  setKey: (key: string, value: any) => void;
}

/**
 * Custom hook for bundle meta management using WordPress entity prop
 *
 * Provides a thin wrapper around useEntityProp for bundle meta access
 * following the useEntityMeta pattern documented in frontend-arch-improvements.md
 *
 * @returns {UseBundleMetaReturn} Bundle meta management interface
 */
const useBundleMeta = (): UseBundleMetaReturn => {
  const tuple = useEntityProp("postType", "course-bundle", "meta") as unknown as [
    Record<string, any> | undefined,
    (newMeta: Record<string, any>) => void,
    any?,
  ];

  const meta = tuple?.[0] as Record<string, any> | undefined;
  const setMeta = tuple?.[1] as (newMeta: Record<string, any>) => void;
  const ready = meta != null;

  const safeSet = (partialMeta: Record<string, any>) => {
    const base = (meta || {}) as Record<string, any>;
    setMeta({ ...base, ...partialMeta });
  };

  const setKey = (key: string, value: any) => {
    safeSet({ [key]: value });
  };

  return { meta, setMeta, ready, safeSet, setKey };
};

export default useBundleMeta;
