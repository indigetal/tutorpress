import { describe, expect, it, jest } from "@jest/globals";
import { act, createElement } from "react";
import { createRoot } from "react-dom/client";
import {
  useTopicDeleteConfirmation,
  type UseTopicDeleteConfirmationReturn,
} from "../useTopicDeleteConfirmation";

const renderConfirmation = (
  continueTopicDelete: (topicId: number) => Promise<void>,
) => {
  (
    globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }
  ).IS_REACT_ACT_ENVIRONMENT = true;
  let current: UseTopicDeleteConfirmationReturn | undefined;
  const root = createRoot(document.createElement("div"));

  const Harness = () => {
    current = useTopicDeleteConfirmation(continueTopicDelete);
    return null;
  };

  act(() => {
    root.render(createElement(Harness));
  });

  return {
    current: () => {
      if (!current) {
        throw new Error("Topic delete confirmation hook did not render");
      }
      return current;
    },
    unmount: () => {
      act(() => root.unmount());
    },
  };
};

const createDeferred = () => {
  let resolve: () => void = () => {};
  let reject: (reason?: unknown) => void = () => {};
  const promise = new Promise<void>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
};

describe("useTopicDeleteConfirmation", () => {
  it("records the requested topic ID and opens confirmation", () => {
    const continueTopicDelete = jest.fn(async () => {});
    const { current, unmount } = renderConfirmation(continueTopicDelete);

    act(() => {
      current().requestTopicDelete(101);
    });

    expect(current().requestedTopicId).toBe(101);
    expect(current().isOpen).toBe(true);
    expect(current().isBusy).toBe(false);
    expect(continueTopicDelete).not.toHaveBeenCalled();
    unmount();
  });

  it("cancels an idle request without invoking the continuation", () => {
    const continueTopicDelete = jest.fn(async () => {});
    const { current, unmount } = renderConfirmation(continueTopicDelete);

    act(() => {
      current().requestTopicDelete(101);
    });
    act(() => {
      current().cancelTopicDelete();
    });

    expect(current().requestedTopicId).toBeNull();
    expect(current().isOpen).toBe(false);
    expect(continueTopicDelete).not.toHaveBeenCalled();
    unmount();
  });

  it("invokes the continuation once, blocks duplicates while busy, then clears", async () => {
    const deferred = createDeferred();
    const continueTopicDelete = jest.fn(() => deferred.promise);
    const { current, unmount } = renderConfirmation(continueTopicDelete);

    act(() => {
      current().requestTopicDelete(101);
    });

    let confirmPromise: Promise<void> = Promise.resolve();
    act(() => {
      confirmPromise = current().confirmTopicDelete();
      void current().confirmTopicDelete();
    });

    expect(continueTopicDelete).toHaveBeenCalledTimes(1);
    expect(continueTopicDelete).toHaveBeenCalledWith(101);
    expect(current().isBusy).toBe(true);
    expect(current().isOpen).toBe(true);

    await act(async () => {
      deferred.resolve();
      await confirmPromise;
    });

    expect(current().isOpen).toBe(false);
    expect(current().isBusy).toBe(false);
    unmount();
  });

  it("clears after rejection and allows a fresh confirmation request", async () => {
    const deferred = createDeferred();
    const continueTopicDelete = jest.fn(() => deferred.promise);
    const { current, unmount } = renderConfirmation(continueTopicDelete);

    act(() => {
      current().requestTopicDelete(101);
    });

    let confirmPromise: Promise<void> = Promise.resolve();
    act(() => {
      confirmPromise = current().confirmTopicDelete();
    });

    await act(async () => {
      deferred.reject(new Error("delete failed"));
      await confirmPromise.catch(() => {});
    });

    expect(current().isOpen).toBe(false);
    expect(current().isBusy).toBe(false);

    act(() => {
      current().requestTopicDelete(101);
    });

    expect(current().requestedTopicId).toBe(101);
    expect(current().isOpen).toBe(true);
    expect(current().isBusy).toBe(false);
    unmount();
  });
});
