import type { ContentDripSettings } from "../../types/additional-content";
import { useSelect } from "@wordpress/data";

export type UseContentDripSettingsReturn = {
  contentDripSettings: ContentDripSettings | undefined;
  setContentDripSettings: (next: ContentDripSettings) => void;
  ready: boolean;
  safeSet: (partial: Partial<ContentDripSettings>) => void;
};

export function useContentDripSettings(): UseContentDripSettingsReturn {
  const contentDripSettings = useSelect((select: any) => {
    return select("core/editor").getEditedPostAttribute("content_drip");
  }, []);

  const ready = contentDripSettings !== undefined && contentDripSettings !== null;

  const setContentDripSettings = (next: ContentDripSettings) => {
    const { editPost } = (window as any).wp.data.dispatch("core/editor");
    editPost({ content_drip: next });
  };

  const safeSet = (partial: Partial<ContentDripSettings>) => {
    const { editPost } = (window as any).wp.data.dispatch("core/editor");
    editPost({
      content_drip: {
        ...contentDripSettings,
        ...partial,
      },
    });
  };

  return { contentDripSettings, setContentDripSettings, ready, safeSet };
}
