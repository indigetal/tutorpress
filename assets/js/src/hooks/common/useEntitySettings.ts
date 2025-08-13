import { useEntityProp } from "@wordpress/core-data";

export type UseEntitySettingsReturn<T extends object> = {
  value: T | undefined;
  setValue: (next: T) => void;
  ready: boolean;
  safeSet: (partial: Partial<T>) => void;
};

export function useEntitySettings<T extends object>(postType: string, field: string): UseEntitySettingsReturn<T> {
  const tuple = useEntityProp("postType", postType, field) as unknown as [T | undefined, (v: T) => void, any?];
  const value = tuple?.[0] as T | undefined;
  const setValue = tuple?.[1] as (v: T) => void;
  const ready = value != null;
  const safeSet = (partial: Partial<T>) => {
    const base = (value || ({} as T)) as T;
    setValue({ ...base, ...partial } as T);
  };

  return { value, setValue, ready, safeSet };
}
