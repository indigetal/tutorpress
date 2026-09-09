import { describe, expect, it, jest } from "@jest/globals";
import {
  createQuizEditorMediaFinalizer,
  createQuizEditorMediaRequestSession,
} from "../quizEditorMediaLifecycle";

describe("createQuizEditorMediaFinalizer", () => {
  it("finalizes matching pointers once and is a no-op on the second call", () => {
    const frame = { close: jest.fn(), detach: jest.fn() };
    const cleanup = jest.fn();
    const media = {
      frame,
      editor: { remove: jest.fn(), activeEditor: "question_description" },
    };
    const finalize = createQuizEditorMediaFinalizer({
      frame,
      editorId: "question_description",
      media,
      cleanupListeners: [cleanup],
    });

    finalize();
    finalize();

    expect(cleanup).toHaveBeenCalledTimes(1);
    expect(frame.close).toHaveBeenCalledTimes(1);
    expect(frame.detach).toHaveBeenCalledTimes(1);
    expect(media.editor.remove).toHaveBeenCalledTimes(1);
    expect(media.editor.remove).toHaveBeenCalledWith("question_description");
    expect(media.frame).toBeNull();
    expect(media.editor.activeEditor).toBeNull();
  });

  it("leaves a different frame object and activeEditor string unchanged", () => {
    const frame = { close: jest.fn(), detach: jest.fn() };
    const otherFrame = { close: jest.fn(), detach: jest.fn() };
    const media = {
      frame: otherFrame,
      editor: { remove: jest.fn(), activeEditor: "answer_explanation" },
    };

    createQuizEditorMediaFinalizer({
      frame,
      editorId: "question_description",
      media,
      cleanupListeners: [],
    })();

    expect(frame.close).toHaveBeenCalledTimes(1);
    expect(frame.detach).toHaveBeenCalledTimes(1);
    expect(otherFrame.close).not.toHaveBeenCalled();
    expect(otherFrame.detach).not.toHaveBeenCalled();
    expect(media.frame).toBe(otherFrame);
    expect(media.editor.activeEditor).toBe("answer_explanation");
    expect(media.editor.remove).toHaveBeenCalledWith("question_description");
  });
});

describe("createQuizEditorMediaRequestSession", () => {
  const objectData = {
    action: "send-attachment-to-editor",
    post_id: 42,
    attachment: { id: 9203 },
  };
  const queryData =
    "action=send-attachment-to-editor&post_id=42&attachment[id]=9204";

  it("matches object and query-string Core requests and ignores unrelated traffic", () => {
    const session = createQuizEditorMediaRequestSession({
      attachmentIds: [9203, 9204],
      postId: 42,
    });
    const objectRequest = {};
    const queryRequest = {};

    session.observeAjaxComplete(objectRequest, objectData);
    session.observeAjaxComplete(queryRequest, queryData);
    session.observeAjaxComplete(
      {},
      { action: "heartbeat", post_id: 42, attachment: { id: 9203 } },
    );
    session.observeAjaxComplete(
      {},
      "action=send-attachment-to-editor&post_id=99&attachment[id]=9203",
    );
    session.observeAjaxComplete(
      {},
      {
        action: "send-attachment-to-editor",
        post_id: 42,
        attachment: { id: 1 },
      },
    );
    session.markSetContent();

    expect(session.getSettledCount()).toBe(2);
    expect(session.hasFailed()).toBe(false);
    expect(session.hasSetContent()).toBe(true);
  });

  it("settles an ajaxError then ajaxComplete pair once with sticky failure", () => {
    const session = createQuizEditorMediaRequestSession({
      attachmentIds: [9203, 9204],
      postId: 42,
    });
    const failedRequest = {};

    session.observeAjaxError(failedRequest, objectData);
    session.observeAjaxComplete(failedRequest, objectData);

    expect(session.getSettledCount()).toBe(1);
    expect(session.hasFailed()).toBe(true);

    session.observeAjaxComplete({}, queryData);

    expect(session.getSettledCount()).toBe(2);
    expect(session.hasFailed()).toBe(true);
  });
});
