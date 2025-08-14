import type { AdditionalContentData } from "../../types/additional-content";
import { useSelect } from "@wordpress/data";

export type UseAdditionalContentDataReturn = {
  additionalContentData: AdditionalContentData | undefined;
  setAdditionalContentData: (next: AdditionalContentData) => void;
  ready: boolean;
  safeSet: (partial: Partial<AdditionalContentData>) => void;
};

export function useAdditionalContentData(): UseAdditionalContentDataReturn {
  const additionalContentData = useSelect((select: any) => {
    return select("core/editor").getEditedPostAttribute("additional_content");
  }, []);

  const ready = additionalContentData !== undefined && additionalContentData !== null;

  const setAdditionalContentData = (next: AdditionalContentData) => {
    const { editPost } = (window as any).wp.data.dispatch("core/editor");
    editPost({ additional_content: next });
  };

  const safeSet = (partial: Partial<AdditionalContentData>) => {
    const { editPost } = (window as any).wp.data.dispatch("core/editor");
    editPost({
      additional_content: {
        ...additionalContentData,
        ...partial,
      },
    });
  };

  return { additionalContentData, setAdditionalContentData, ready, safeSet };
}
