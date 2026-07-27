/**
 * Quiz Image Canvas Hook
 *
 * @description Shared freehand mask authoring over a background image, for Tutor LMS 4.0's
 *              `draw_image` and `pin_image` question types. Both store a raster PNG mask in
 *              the answer row's `answer_two_gap_match`; Tutor Pro converts it to a file and
 *              owns all grading. This hook owns canvas sizing, pointer and keyboard input,
 *              and mask export. It never writes an answer row: it reports a committed mask
 *              through `onMaskCommit` and the calling editor decides what to persist.
 *
 * @coordinates The mask is a raster proportional to the image's displayed box, which is what
 *              Tutor stores and what Tutor Pro's grader expects: it resamples the student
 *              mask to the instructor mask's dimensions and compares intersection over union.
 *              There is no source-image pixel space and no stored coordinate. Drawing happens
 *              in CSS pixels, the visible canvas is scaled up by `devicePixelRatio` so strokes
 *              stay crisp, and the exported PNG is written back down to the CSS box so the
 *              persisted artifact matches Tutor's resolution. That export size is load-bearing:
 *              the instructor mask sets the comparison grid for every future attempt, so a
 *              larger raster silently multiplies Pro's per-pixel grading cost.
 *
 * @lifecycle   Only a completed stroke or an explicit clear commits. Mounting, loading a
 *              stored mask, and resizing redraw without committing, so opening a saved
 *              question can never mark its row `update`. This deliberately diverges from
 *              Tutor's own builder, which re-encodes the mask on mount at the current window
 *              width and thereby changes the grading basis of an untouched question.
 *
 * @usage
 * const canvas = useQuizImageCanvas({
 *   imageRef,
 *   canvasRef,
 *   containerRef,
 *   imageUrl: option.image_url,
 *   initialMaskValue: option.answer_two_gap_match,
 *   onMaskCommit: (maskDataUrl) => writeAnswerRow(maskDataUrl),
 * });
 *
 * @package TutorPress
 * @subpackage Quiz/Hooks
 * @since 1.0.0
 */

import { useCallback, useEffect, useRef, useState } from "react";
import { __ } from "@wordpress/i18n";

/** Tutor's instructor mask stroke and fill colour, matched so authored masks look native. */
export const QUIZ_MASK_STROKE_STYLE = "rgba(220, 53, 69, 0.95)";

/** Tutor's brush size, in CSS pixels. */
export const QUIZ_MASK_BRUSH_SIZE = 1;

/** Keyboard cursor movement per arrow key press, and with Shift held, in CSS pixels. */
const KEYBOARD_STEP = 5;
const KEYBOARD_LARGE_STEP = 20;

/** Radius of the keyboard cursor preview dot, in CSS pixels. */
const KEYBOARD_CURSOR_RADIUS = 5;
const KEYBOARD_CURSOR_STYLE = "rgba(0, 0, 255, 0.65)";

/** A cleared mask is the empty string, which is how Tutor represents "no mask". */
export const EMPTY_MASK_VALUE = "";

/** Plain rectangle, so pointer mapping stays testable without a DOM. */
export interface CanvasRect {
  left: number;
  top: number;
  width: number;
  height: number;
}

export interface CanvasPoint {
  x: number;
  y: number;
}

export interface CanvasPixelSize {
  width: number;
  height: number;
}

/**
 * Backing-store size for a canvas covering a CSS box at a given pixel ratio.
 *
 * The CSS box stays the drawing coordinate space; the ratio only sharpens rendering.
 */
export const computeCanvasPixelSize = (cssWidth: number, cssHeight: number, pixelRatio: number): CanvasPixelSize => {
  const safeRatio = Number.isFinite(pixelRatio) && pixelRatio > 0 ? pixelRatio : 1;
  const safeWidth = Number.isFinite(cssWidth) && cssWidth > 0 ? cssWidth : 0;
  const safeHeight = Number.isFinite(cssHeight) && cssHeight > 0 ? cssHeight : 0;

  return {
    width: Math.max(0, Math.round(safeWidth * safeRatio)),
    height: Math.max(0, Math.round(safeHeight * safeRatio)),
  };
};

/**
 * Map a pointer's client position into the canvas drawing space, in CSS pixels.
 *
 * The rect is measured rather than assumed, so a CSS-scaled or zoomed canvas still maps
 * correctly, and the result is clamped to the box so a stroke cannot leave the mask.
 */
export const pointerToCanvasPoint = (
  clientX: number,
  clientY: number,
  rect: CanvasRect,
  cssWidth: number,
  cssHeight: number
): CanvasPoint => {
  const scaleX = rect.width > 0 ? cssWidth / rect.width : 1;
  const scaleY = rect.height > 0 ? cssHeight / rect.height : 1;

  return {
    x: Math.max(0, Math.min(cssWidth, (clientX - rect.left) * scaleX)),
    y: Math.max(0, Math.min(cssHeight, (clientY - rect.top) * scaleY)),
  };
};

/** Move the keyboard cursor within the drawing box. Returns `null` for an unhandled key. */
export const moveKeyboardCursor = (
  cursor: CanvasPoint,
  key: string,
  cssWidth: number,
  cssHeight: number,
  shiftKey: boolean
): CanvasPoint | null => {
  const step = shiftKey ? KEYBOARD_LARGE_STEP : KEYBOARD_STEP;
  let { x, y } = cursor;

  if (key === "ArrowLeft") {
    x -= step;
  } else if (key === "ArrowRight") {
    x += step;
  } else if (key === "ArrowUp") {
    y -= step;
  } else if (key === "ArrowDown") {
    y += step;
  } else {
    return null;
  }

  return {
    x: Math.max(0, Math.min(cssWidth, x)),
    y: Math.max(0, Math.min(cssHeight, y)),
  };
};

/**
 * Whether a stored image or mask reference is safe to assign as an image source.
 *
 * Stored values arrive from Tutor's tables, so a hostile row must not be able to put a
 * `javascript:` or other active scheme into an attribute. Only same-document http(s)
 * URLs, protocol-relative and root-relative paths, and image data URLs are accepted.
 */
export const isSafeImageSource = (value: unknown): boolean => {
  if (typeof value !== "string") {
    return false;
  }

  const trimmed = value.trim();
  if (trimmed === "") {
    return false;
  }
  if (/^data:image\/[a-z0-9.+-]+;base64,/i.test(trimmed)) {
    return true;
  }
  if (/^https?:\/\//i.test(trimmed) || trimmed.startsWith("//") || trimmed.startsWith("/")) {
    return !/[\s"'<>]/.test(trimmed);
  }

  return false;
};

export interface UseQuizImageCanvasOptions {
  /** Background image element the canvas is sized and positioned against. */
  imageRef: React.RefObject<HTMLImageElement | null>;
  /** Overlay canvas the mask is drawn on. */
  canvasRef: React.RefObject<HTMLCanvasElement | null>;
  /** Wrapper observed for size changes. */
  containerRef: React.RefObject<HTMLElement | null>;
  /** Background image URL, or empty when none is selected yet. */
  imageUrl?: string;
  /** Stored mask value to display on mount. Never re-committed. */
  initialMaskValue?: string;
  /** Called with an exported PNG data URL, or the empty string when the mask is cleared. */
  onMaskCommit: (maskDataUrl: string) => void;
  /** Optional screen-reader announcement sink. */
  onAnnounce?: (message: string) => void;
}

export interface UseQuizImageCanvasReturn {
  /** Re-measure and redraw. Safe to call at any time; never commits. */
  syncCanvas: () => void;
  /** Clear the mask and commit the empty value. */
  clearMask: () => void;
  /** Whether a stroke is in progress. */
  isDrawing: boolean;
  /** Whether the canvas currently holds a mask. */
  hasMask: boolean;
  /** Whether the background image or the stored mask failed to load. */
  hasLoadError: boolean;
  /** Whether the mask could not be exported, which blocks committing rather than clearing. */
  hasExportError: boolean;
  /** Attach to the background image's `load` event. */
  handleImageLoad: () => void;
  /** Attach to the background image's `error` event. */
  handleImageError: () => void;
}

/**
 * Freehand mask authoring over a background image.
 */
export const useQuizImageCanvas = ({
  imageRef,
  canvasRef,
  containerRef,
  imageUrl,
  initialMaskValue,
  onMaskCommit,
  onAnnounce,
}: UseQuizImageCanvasOptions): UseQuizImageCanvasReturn => {
  const [isDrawing, setIsDrawing] = useState(false);
  const [hasMask, setHasMask] = useState(false);
  const [hasLoadError, setHasLoadError] = useState(false);
  const [hasExportError, setHasExportError] = useState(false);

  const cssSizeRef = useRef<CanvasPixelSize>({ width: 0, height: 0 });
  const strokeRef = useRef({ active: false, pointerId: null as number | null, hasMoved: false, lastX: 0, lastY: 0 });
  const maskImageRef = useRef<HTMLImageElement | null>(null);
  const keyboardCursorRef = useRef<CanvasPoint>({ x: 0, y: 0 });
  const keyboardPreviewRef = useRef(false);

  const onMaskCommitRef = useRef(onMaskCommit);
  const onAnnounceRef = useRef(onAnnounce);
  onMaskCommitRef.current = onMaskCommit;
  onAnnounceRef.current = onAnnounce;

  const announce = useCallback((message: string) => {
    onAnnounceRef.current?.(message);
  }, []);

  /** Reset stroke styling after any resize, which discards canvas context state. */
  const applyStrokeStyle = useCallback((context: CanvasRenderingContext2D) => {
    context.strokeStyle = QUIZ_MASK_STROKE_STYLE;
    context.fillStyle = QUIZ_MASK_STROKE_STYLE;
    context.lineCap = "round";
    context.lineJoin = "round";
    context.lineWidth = QUIZ_MASK_BRUSH_SIZE;
  }, []);

  /**
   * Redraw the cached mask, optionally with a transient overlay, without committing.
   */
  const redraw = useCallback(
    (overlay?: (context: CanvasRenderingContext2D) => void) => {
      const canvas = canvasRef.current;
      const context = canvas?.getContext("2d");
      const { width, height } = cssSizeRef.current;
      if (!canvas || !context || width <= 0 || height <= 0) {
        return;
      }

      context.clearRect(0, 0, width, height);
      const maskImage = maskImageRef.current;
      if (maskImage) {
        // Stretch to the current box: the mask is proportional, so geometry survives a resize.
        context.drawImage(maskImage, 0, 0, width, height);
      }
      applyStrokeStyle(context);
      overlay?.(context);
    },
    [applyStrokeStyle, canvasRef]
  );

  /**
   * Measure the displayed image, resize the canvas for the current pixel ratio, and redraw.
   */
  const syncCanvas = useCallback(() => {
    const image = imageRef.current;
    const canvas = canvasRef.current;
    if (!image || !canvas) {
      return;
    }

    const rect = image.getBoundingClientRect();
    const cssWidth = rect.width || image.naturalWidth;
    const cssHeight = rect.height || image.naturalHeight;
    if (!cssWidth || !cssHeight) {
      return;
    }

    const ratio = typeof window !== "undefined" && window.devicePixelRatio ? window.devicePixelRatio : 1;
    const backing = computeCanvasPixelSize(cssWidth, cssHeight, ratio);

    cssSizeRef.current = { width: cssWidth, height: cssHeight };
    canvas.width = backing.width;
    canvas.height = backing.height;
    canvas.style.width = `${cssWidth}px`;
    canvas.style.height = `${cssHeight}px`;

    const context = canvas.getContext("2d");
    if (context) {
      // Draw in CSS pixels; the ratio only sharpens what is rendered.
      context.setTransform(backing.width / cssWidth, 0, 0, backing.height / cssHeight, 0, 0);
    }
    redraw();
  }, [canvasRef, imageRef, redraw]);

  /**
   * Export the mask at the CSS box size, independent of the rendered pixel ratio.
   *
   * Returns the empty string for a blank canvas, matching Tutor, or `null` when the canvas
   * cannot be read at all — a tainted canvas must not be mistaken for a cleared mask.
   */
  const exportMask = useCallback((): string | null => {
    const canvas = canvasRef.current;
    const { width, height } = cssSizeRef.current;
    if (!canvas || width <= 0 || height <= 0) {
      return null;
    }

    const exportCanvas = document.createElement("canvas");
    exportCanvas.width = Math.round(width);
    exportCanvas.height = Math.round(height);
    const exportContext = exportCanvas.getContext("2d");
    if (!exportContext) {
      return null;
    }

    try {
      exportContext.drawImage(canvas, 0, 0, exportCanvas.width, exportCanvas.height);
      const blank = document.createElement("canvas");
      blank.width = exportCanvas.width;
      blank.height = exportCanvas.height;
      const exported = exportCanvas.toDataURL("image/png");

      return exported === blank.toDataURL("image/png") ? EMPTY_MASK_VALUE : exported;
    } catch (error) {
      return null;
    }
  }, [canvasRef]);

  /**
   * Export, cache for later redraws, and report the committed value.
   */
  const commitMask = useCallback(() => {
    const exported = exportMask();
    if (exported === null) {
      setHasExportError(true);
      return;
    }

    setHasExportError(false);
    setHasMask(exported !== EMPTY_MASK_VALUE);

    if (exported === EMPTY_MASK_VALUE) {
      maskImageRef.current = null;
    } else {
      const cached = new Image();
      cached.onload = () => {
        maskImageRef.current = cached;
      };
      cached.src = exported;
    }

    onMaskCommitRef.current(exported);
  }, [exportMask]);

  const beginStroke = useCallback(
    (x: number, y: number): boolean => {
      const canvas = canvasRef.current;
      const context = canvas?.getContext("2d");
      const { width, height } = cssSizeRef.current;
      if (!context || strokeRef.current.active || width <= 0 || height <= 0) {
        return false;
      }

      // Each new stroke replaces the previous mask, as Tutor's builder does.
      context.clearRect(0, 0, width, height);
      maskImageRef.current = null;
      applyStrokeStyle(context);
      context.beginPath();
      context.moveTo(x, y);

      strokeRef.current = { active: true, pointerId: null, hasMoved: false, lastX: x, lastY: y };
      setIsDrawing(true);
      keyboardPreviewRef.current = false;

      return true;
    },
    [applyStrokeStyle, canvasRef]
  );

  const extendStroke = useCallback(
    (x: number, y: number) => {
      const context = canvasRef.current?.getContext("2d");
      if (!context || !strokeRef.current.active) {
        return;
      }

      context.lineTo(x, y);
      context.stroke();
      strokeRef.current.hasMoved = true;
      strokeRef.current.lastX = x;
      strokeRef.current.lastY = y;
    },
    [canvasRef]
  );

  const finishStroke = useCallback(() => {
    const context = canvasRef.current?.getContext("2d");
    if (!context || !strokeRef.current.active) {
      return;
    }

    if (strokeRef.current.hasMoved) {
      context.closePath();
      context.fill();
    } else {
      // A single click or tap paints a dot, matching Tutor.
      context.beginPath();
      context.arc(strokeRef.current.lastX, strokeRef.current.lastY, QUIZ_MASK_BRUSH_SIZE / 2, 0, Math.PI * 2);
      context.fill();
    }

    strokeRef.current.active = false;
    strokeRef.current.pointerId = null;
    setIsDrawing(false);
    commitMask();
  }, [canvasRef, commitMask]);

  /** Abandon an in-progress stroke, restoring the previously committed mask. */
  const cancelStroke = useCallback(() => {
    if (!strokeRef.current.active) {
      return;
    }

    strokeRef.current = { active: false, pointerId: null, hasMoved: false, lastX: 0, lastY: 0 };
    setIsDrawing(false);
    syncCanvas();
  }, [syncCanvas]);

  const clearMask = useCallback(() => {
    if (strokeRef.current.active) {
      cancelStroke();
    }

    const context = canvasRef.current?.getContext("2d");
    const { width, height } = cssSizeRef.current;
    if (context && width > 0 && height > 0) {
      context.clearRect(0, 0, width, height);
      applyStrokeStyle(context);
    }

    maskImageRef.current = null;
    setHasMask(false);
    setHasExportError(false);
    onMaskCommitRef.current(EMPTY_MASK_VALUE);
  }, [applyStrokeStyle, cancelStroke, canvasRef]);

  const handleImageLoad = useCallback(() => {
    setHasLoadError(false);
    syncCanvas();
  }, [syncCanvas]);

  /** A failed background image must leave the stored answer untouched. */
  const handleImageError = useCallback(() => {
    setHasLoadError(true);
  }, []);

  /**
   * Display the stored mask. Deliberately does not commit: an untouched question stays
   * `no_change`, so its grading basis cannot shift with the window width.
   */
  useEffect(() => {
    maskImageRef.current = null;
    setHasMask(false);
    setHasExportError(false);

    if (!initialMaskValue || !isSafeImageSource(initialMaskValue)) {
      setHasLoadError(Boolean(initialMaskValue));
      syncCanvas();
      return;
    }

    const storedMask = new Image();
    storedMask.crossOrigin = "anonymous";
    storedMask.onload = () => {
      maskImageRef.current = storedMask;
      setHasMask(true);
      setHasLoadError(false);
      redraw();
    };
    storedMask.onerror = () => {
      setHasLoadError(true);
    };
    storedMask.src = initialMaskValue;
  }, [initialMaskValue, redraw, syncCanvas]);

  /** Keep the canvas aligned with the displayed image, without committing. */
  useEffect(() => {
    const container = containerRef.current;
    const image = imageRef.current;
    if (!imageUrl || !image) {
      return;
    }

    syncCanvas();
    window.addEventListener("resize", syncCanvas);
    const observer = typeof ResizeObserver !== "undefined" ? new ResizeObserver(() => syncCanvas()) : null;
    observer?.observe(image);
    if (container) {
      observer?.observe(container);
    }

    return () => {
      window.removeEventListener("resize", syncCanvas);
      observer?.disconnect();
    };
  }, [containerRef, imageRef, imageUrl, syncCanvas]);

  /** Pointer and keyboard input. */
  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !imageUrl) {
      return;
    }

    canvas.style.touchAction = "none";

    const toCanvasPoint = (event: PointerEvent): CanvasPoint =>
      pointerToCanvasPoint(
        event.clientX,
        event.clientY,
        canvas.getBoundingClientRect(),
        cssSizeRef.current.width,
        cssSizeRef.current.height
      );

    const handlePointerDown = (event: PointerEvent) => {
      if (event.pointerType === "mouse" && event.button !== 0) {
        return;
      }
      event.preventDefault();

      const point = toCanvasPoint(event);
      if (!beginStroke(point.x, point.y)) {
        return;
      }

      strokeRef.current.pointerId = typeof event.pointerId === "number" ? event.pointerId : null;
      if (strokeRef.current.pointerId !== null) {
        try {
          canvas.setPointerCapture(strokeRef.current.pointerId);
        } catch (error) {
          // Capture is an optimisation; drawing still works without it.
        }
      }
    };

    const isActivePointer = (event: PointerEvent) =>
      strokeRef.current.active && (strokeRef.current.pointerId === null || event.pointerId === strokeRef.current.pointerId);

    const handlePointerMove = (event: PointerEvent) => {
      if (!isActivePointer(event)) {
        return;
      }
      event.preventDefault();
      const point = toCanvasPoint(event);
      extendStroke(point.x, point.y);
    };

    const handlePointerUp = (event: PointerEvent) => {
      if (!isActivePointer(event)) {
        return;
      }
      event.preventDefault();
      finishStroke();
    };

    const drawKeyboardCursor = () => {
      if (!keyboardPreviewRef.current || strokeRef.current.active) {
        return;
      }
      redraw((context) => {
        context.fillStyle = KEYBOARD_CURSOR_STYLE;
        context.beginPath();
        context.arc(keyboardCursorRef.current.x, keyboardCursorRef.current.y, KEYBOARD_CURSOR_RADIUS, 0, Math.PI * 2);
        context.fill();
      });
    };

    const handleFocus = () => {
      keyboardCursorRef.current = {
        x: cssSizeRef.current.width / 2,
        y: cssSizeRef.current.height / 2,
      };
      keyboardPreviewRef.current = true;
      drawKeyboardCursor();
    };

    const handleBlur = () => {
      keyboardPreviewRef.current = false;
      if (strokeRef.current.active) {
        finishStroke();
      }
    };

    const handleKeyDown = (event: KeyboardEvent) => {
      const { key } = event;

      if (key === " " || key === "Enter") {
        event.preventDefault();
        if (strokeRef.current.active) {
          finishStroke();
          keyboardPreviewRef.current = true;
          drawKeyboardCursor();
          announce(__("Selection completed.", "tutorpress"));
          return;
        }
        if (beginStroke(keyboardCursorRef.current.x, keyboardCursorRef.current.y)) {
          announce(__("Drawing started. Use arrow keys to trace, then Enter to finish.", "tutorpress"));
        }
        return;
      }

      if (key === "Escape" || key === "Backspace" || key === "Delete") {
        if (!strokeRef.current.active) {
          return;
        }
        event.preventDefault();
        cancelStroke();
        keyboardPreviewRef.current = true;
        drawKeyboardCursor();
        announce(__("Drawing cancelled.", "tutorpress"));
        return;
      }

      if (key === "c" || key === "C") {
        event.preventDefault();
        clearMask();
        keyboardPreviewRef.current = true;
        drawKeyboardCursor();
        announce(__("Selection cleared.", "tutorpress"));
        return;
      }

      const nextCursor = moveKeyboardCursor(
        keyboardCursorRef.current,
        key,
        cssSizeRef.current.width,
        cssSizeRef.current.height,
        event.shiftKey
      );
      if (!nextCursor) {
        return;
      }

      event.preventDefault();
      keyboardCursorRef.current = nextCursor;
      if (strokeRef.current.active) {
        extendStroke(nextCursor.x, nextCursor.y);
        return;
      }
      keyboardPreviewRef.current = true;
      drawKeyboardCursor();
    };

    canvas.addEventListener("pointerdown", handlePointerDown);
    canvas.addEventListener("pointermove", handlePointerMove);
    canvas.addEventListener("pointerup", handlePointerUp);
    canvas.addEventListener("pointercancel", handlePointerUp);
    canvas.addEventListener("focus", handleFocus);
    canvas.addEventListener("blur", handleBlur);
    canvas.addEventListener("keydown", handleKeyDown);

    return () => {
      canvas.removeEventListener("pointerdown", handlePointerDown);
      canvas.removeEventListener("pointermove", handlePointerMove);
      canvas.removeEventListener("pointerup", handlePointerUp);
      canvas.removeEventListener("pointercancel", handlePointerUp);
      canvas.removeEventListener("focus", handleFocus);
      canvas.removeEventListener("blur", handleBlur);
      canvas.removeEventListener("keydown", handleKeyDown);
    };
  }, [announce, beginStroke, cancelStroke, canvasRef, clearMask, extendStroke, finishStroke, imageUrl, redraw]);

  return {
    syncCanvas,
    clearMask,
    isDrawing,
    hasMask,
    hasLoadError,
    hasExportError,
    handleImageLoad,
    handleImageError,
  };
};
