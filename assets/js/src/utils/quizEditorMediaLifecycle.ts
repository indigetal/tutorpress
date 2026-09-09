/**
 * Idempotent finalizer for one editor-ID-scoped classic media workflow.
 * Operates only on the supplied frame, editor ID, listener cleanups, and media object.
 */

export interface QuizEditorMediaFrame {
  close: () => void;
  detach: () => void;
}

export interface QuizEditorMediaNamespace {
  frame: QuizEditorMediaFrame | null;
  editor: {
    remove: (editorId: string) => void;
    activeEditor: string | null;
  };
}

export interface QuizEditorMediaFinalizerOptions {
  frame: QuizEditorMediaFrame;
  editorId: string;
  media: QuizEditorMediaNamespace;
  cleanupListeners: Array<() => void>;
}

export const createQuizEditorMediaFinalizer = ({
  frame,
  editorId,
  media,
  cleanupListeners,
}: QuizEditorMediaFinalizerOptions): (() => void) => {
  const listeners = cleanupListeners.slice();
  let finalized = false;

  return () => {
    if (finalized) {
      return;
    }

    finalized = true;

    listeners.forEach((cleanup) => {
      cleanup();
    });

    frame.close();
    frame.detach();
    media.editor.remove(editorId);

    if (media.frame === frame) {
      media.frame = null;
    }

    if (media.editor.activeEditor === editorId) {
      media.editor.activeEditor = null;
    }
  };
};

export const createQuizEditorMediaRequestSession = ({
  attachmentIds,
  postId,
}: {
  attachmentIds: Array<number | string>;
  postId: number | string;
}) => {
  const ids = new Set(attachmentIds.map(String));
  const post = String(postId);
  const seen = new WeakSet<object>();
  let settledCount = 0;
  let failed = false;
  let receivedSetContent = false;
  const fields = (data: unknown): [string, string, string] => {
    if (typeof data === "string") {
      const params = new URLSearchParams(data.replace(/^\?/, ""));
      return [
        params.get("action") ?? "",
        params.get("post_id") ?? "",
        params.get("attachment[id]") ?? "",
      ];
    }
    const row = (data && typeof data === "object" ? data : {}) as Record<
      string,
      unknown
    >;
    const nested = row.attachment as { id?: unknown } | undefined;
    return [
      String(row.action ?? ""),
      String(row.post_id ?? ""),
      String(nested?.id ?? row["attachment[id]"] ?? ""),
    ];
  };
  const observe = (request: object, data: unknown, asFailed: boolean) => {
    const [action, requestPost, attachmentId] = fields(data);
    if (
      action !== "send-attachment-to-editor" ||
      requestPost !== post ||
      !ids.has(attachmentId) ||
      seen.has(request)
    ) {
      return;
    }
    seen.add(request);
    settledCount += 1;
    failed = failed || asFailed;
  };
  return {
    observeAjaxError: (request: object, data: unknown) =>
      observe(request, data, true),
    observeAjaxComplete: (request: object, data: unknown) =>
      observe(request, data, false),
    markSetContent: () => {
      receivedSetContent = true;
    },
    getSettledCount: () => settledCount,
    hasFailed: () => failed,
    hasSetContent: () => receivedSetContent,
  };
};
