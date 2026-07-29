/**
 * Shared Quiz Question Type Metadata
 *
 * @description Single local source of truth for the question types TutorPress knows
 *              about: fallback label, picker position, Pro flag, and whether Tutor 4.0
 *              restricts the type to Modern/Kids learning modes. Also owns the shared
 *              question and answer-row factories so each type step can be implemented
 *              without adding a `QuizModal` switch branch.
 *
 *              This metadata can only ever *subtract* capability. Authoring requires the
 *              server capability contract to permit it AND a registered local editor;
 *              nothing here can grant a capability the server withheld.
 *
 *              Labels here are a fallback only. When
 *              `tutorPressCurriculum.quizCapabilities` is present, Tutor's own label wins
 *              so TutorPress and Tutor's frontend builder never show two names for one
 *              feature and upstream renames/translations track automatically.
 *
 *              This module is a leaf: it must not import from the question component
 *              registry, so component files can import it freely. The
 *              "is there a local editor" question is answered by `isLocallyAuthorable()`
 *              in `components/modals/quiz/questions/index.ts`, which owns the registry.
 *
 * @package TutorPress
 * @subpackage Utils
 * @since 1.0.0
 */

import { __ } from "@wordpress/i18n";
import {
  getDefaultQuestionSettings,
  type QuizCapabilities,
  type QuizQuestion,
  type QuizQuestionOption,
  type QuizQuestionType,
} from "../types/quiz";

/**
 * Local metadata for one known question type.
 */
export interface QuizQuestionTypeMeta {
  /**
   * Fallback display label.
   *
   * Used only when the server capability contract is unavailable, and for `h5p`, which
   * Tutor never registers in its question-type registry.
   */
  label: string;
  /**
   * Position in TutorPress's picker, or `null` to keep the type out of the picker.
   *
   * `null` covers Tutor's alias-only registry entries and `h5p`.
   */
  pickerOrder: number | null;
  /** Whether Tutor gates the type behind Tutor LMS Pro. */
  isPro: boolean;
  /**
   * Tutor 4.0 type restricted to the Modern and Kids learning modes.
   *
   * Mirrors `QuizModel::get_modern_mode_quiz_types()`. Used to keep these types out of
   * the contract-absent fallback list; live gating always comes from the server.
   */
  modernModeOnly: boolean;
}

/**
 * Metadata for every question type TutorPress knows by name.
 *
 * Labels and Pro flags match Tutor's registry (`QuizModel::get_question_types()`).
 * Picker order preserves TutorPress's established pre-4.0 sequence, then appends the
 * five Tutor 4.0 native types in Tutor's own registry order.
 */
export const QUIZ_QUESTION_TYPE_META: Record<QuizQuestionType, QuizQuestionTypeMeta> = {
  // Pre-4.0 types, in TutorPress's established picker order.
  true_false: { label: __("True/False", "tutorpress"), pickerOrder: 0, isPro: false, modernModeOnly: false },
  multiple_choice: { label: __("Multiple Choice", "tutorpress"), pickerOrder: 1, isPro: false, modernModeOnly: false },
  open_ended: { label: __("Open Ended", "tutorpress"), pickerOrder: 2, isPro: false, modernModeOnly: false },
  fill_in_the_blank: {
    label: __("Fill In The Blanks", "tutorpress"),
    pickerOrder: 3,
    isPro: false,
    modernModeOnly: false,
  },
  short_answer: { label: __("Short Answer", "tutorpress"), pickerOrder: 4, isPro: true, modernModeOnly: false },
  matching: { label: __("Matching", "tutorpress"), pickerOrder: 5, isPro: true, modernModeOnly: false },
  image_answering: { label: __("Image Answering", "tutorpress"), pickerOrder: 6, isPro: true, modernModeOnly: false },
  ordering: { label: __("Ordering", "tutorpress"), pickerOrder: 7, isPro: true, modernModeOnly: false },

  // Tutor 4.0 native types, appended in Tutor's registry order.
  draw_image: { label: __("Image Marking", "tutorpress"), pickerOrder: 8, isPro: true, modernModeOnly: true },
  scale: { label: __("Range", "tutorpress"), pickerOrder: 9, isPro: true, modernModeOnly: true },
  pin_image: { label: __("Pin", "tutorpress"), pickerOrder: 10, isPro: true, modernModeOnly: true },
  coordinates: { label: __("Graph", "tutorpress"), pickerOrder: 11, isPro: true, modernModeOnly: true },
  puzzle: { label: __("Puzzle", "tutorpress"), pickerOrder: 12, isPro: true, modernModeOnly: true },

  // Alias-only registry entries. Tutor registers them, but they are variants of the
  // types above and TutorPress has never offered them in its picker.
  single_choice: { label: __("Single Choice", "tutorpress"), pickerOrder: null, isPro: false, modernModeOnly: false },
  image_matching: { label: __("Image Matching", "tutorpress"), pickerOrder: null, isPro: true, modernModeOnly: false },

  // Authored through the separate Interactive Quiz modal and absent from Tutor's
  // registry, so it is never offered here and `isPro` is inert. The label exists
  // because Interactive Quiz question badges still need one.
  h5p: { label: __("H5P", "tutorpress"), pickerOrder: null, isPro: false, modernModeOnly: false },
};

/**
 * Tutor 4.0 question types that Tutor's Legacy save path cannot safely accept.
 *
 * Tutor core currently throws for four of these before checking `_data_status`.
 * Graph is included deliberately so TutorPress exposes one deterministic failure
 * boundary for the complete modern-only set.
 */
export const TUTOR_4_NATIVE_QUESTION_TYPES = [
  "draw_image",
  "scale",
  "pin_image",
  "coordinates",
  "puzzle",
] as const;

/**
 * Whether a stored slug is one of Tutor 4.0's five modern-only native types.
 */
export const isTutor4NativeQuestionType = (questionType: string): boolean =>
  (TUTOR_4_NATIVE_QUESTION_TYPES as readonly string[]).includes(questionType);

/**
 * Decide whether a loaded row may use its local editor under the active contract.
 *
 * The server must positively identify the row as registered and editable, and the
 * caller must positively identify a complete local editor. Missing or malformed
 * capability data therefore fails closed without changing the stored row.
 */
export const canEditLoadedQuestion = (
  questionType: string,
  capabilities: QuizCapabilities | undefined,
  hasLocalEditor: (questionType: string) => boolean
): boolean => {
  if (!capabilities || !Array.isArray(capabilities.questionTypes) || !hasLocalEditor(questionType)) {
    return false;
  }

  const capability = capabilities.questionTypes.find(
    (entry) => Boolean(entry) && entry.slug === questionType
  );

  return capability?.registered === true && capability.can_edit_existing === true;
};

/**
 * Why TutorPress must stop a quiz save before Tutor's AJAX endpoint.
 */
export type QuizQuestionSaveBlockReason = "legacy_mode" | "unavailable_contract";

/**
 * One loaded row that makes the current save unsafe.
 */
export interface QuizQuestionSaveBlock {
  questionType: string;
  reason: QuizQuestionSaveBlockReason;
}

/**
 * Find the first row TutorPress must refuse to submit.
 *
 * Tutor 4.0 Legacy inspects modern-only slugs before `_data_status`, so even an
 * untouched row must be blocked. Tutor 3.9.x explicitly reports
 * `hasNativeQuizTypes: false`; downgraded native rows may pass only while they remain
 * `no_change`, which is the generic preservation path verified by Step 12.
 *
 * Every other row without a positively available editor is likewise allowed only as
 * `no_change`. This keeps unknown, malformed-contract, and unavailable rows opaque
 * while preventing reorder or other accidental mutations from reaching Tutor.
 */
export const getQuestionSaveBlock = (
  questions: QuizQuestion[],
  capabilities: QuizCapabilities | undefined,
  hasLocalEditor: (questionType: string) => boolean
): QuizQuestionSaveBlock | null => {
  const legacyRejectsNativeRows =
    capabilities?.learningMode === "legacy" && capabilities.hasNativeQuizTypes !== false;

  if (legacyRejectsNativeRows) {
    const nativeQuestion = questions.find((question) => isTutor4NativeQuestionType(question.question_type));
    if (nativeQuestion) {
      return {
        questionType: nativeQuestion.question_type,
        reason: "legacy_mode",
      };
    }
  }

  for (const question of questions) {
    if (canEditLoadedQuestion(question.question_type, capabilities, hasLocalEditor)) {
      continue;
    }

    if (question._data_status === "no_change") {
      continue;
    }

    return {
      questionType: question.question_type,
      reason: "unavailable_contract",
    };
  }

  return null;
};

/**
 * Sort key placing slugs TutorPress does not know after every known picker entry.
 */
const UNKNOWN_TYPE_PICKER_ORDER = Number.MAX_SAFE_INTEGER;

/**
 * Type guard for a slug TutorPress has local metadata for.
 *
 * A slug that fails this check is an opaque unknown: Tutor may have registered it
 * through `tutor_get_question_types`, and its stored data must be preserved rather
 * than interpreted.
 */
export const isKnownQuizQuestionType = (slug: string): slug is QuizQuestionType =>
  Object.prototype.hasOwnProperty.call(QUIZ_QUESTION_TYPE_META, slug);

/**
 * Get local metadata for a slug, or `undefined` for an unknown slug.
 */
export const getQuizQuestionTypeMeta = (slug: string): QuizQuestionTypeMeta | undefined =>
  isKnownQuizQuestionType(slug) ? QUIZ_QUESTION_TYPE_META[slug] : undefined;

/**
 * Get the fallback display label for a slug.
 *
 * Callers with access to the server capability contract must prefer its label; this is
 * for slugs the contract does not carry, such as `h5p`, and for unknown slugs, which
 * degrade to a humanized form of the stored slug.
 */
export const getQuizQuestionTypeLabel = (slug: string): string =>
  getQuizQuestionTypeMeta(slug)?.label ?? slug.replace(/_/g, " ");

/**
 * Whether a slug is deliberately hidden from TutorPress's picker.
 *
 * Covers Tutor's alias-only entries and `h5p`. Unknown slugs are not hidden: Tutor
 * registered them, so they remain visible and are gated by the capability contract.
 */
export const isHiddenFromPicker = (slug: string): boolean => getQuizQuestionTypeMeta(slug)?.pickerOrder === null;

/**
 * Picker sort key for a slug. Unknown slugs sort last.
 */
export const getQuizQuestionTypePickerOrder = (slug: string): number =>
  getQuizQuestionTypeMeta(slug)?.pickerOrder ?? UNKNOWN_TYPE_PICKER_ORDER;

/**
 * Question types offered when the server capability contract is unavailable.
 *
 * Picker entries that Tutor 4.0 does not restrict to Modern/Kids, in picker order.
 * Callers must present these as creation-disabled display metadata: an absent contract
 * must never silently enable authoring.
 */
export const getFallbackPickerTypes = (): Array<{ slug: QuizQuestionType; meta: QuizQuestionTypeMeta }> =>
  (Object.keys(QUIZ_QUESTION_TYPE_META) as QuizQuestionType[])
    .map((slug) => ({ slug, meta: QUIZ_QUESTION_TYPE_META[slug] }))
    .filter(({ meta }) => meta.pickerOrder !== null && !meta.modernModeOnly)
    .sort((a, b) => getQuizQuestionTypePickerOrder(a.slug) - getQuizQuestionTypePickerOrder(b.slug));

/**
 * Range (`scale`) configuration stored inside the answer row's JSON.
 *
 * Every key is consumed by Tutor Pro's frontend scale renderer. Only `min`, `max`, and
 * `labelEvery` are editable in Tutor's own builder; the rest are written on creation and
 * carried forward untouched.
 */
export interface ScaleConfig {
  min: number;
  max: number;
  /**
   * Always `1`.
   *
   * Tutor's native editor forces this on creation, on load, and on every config change,
   * so authoring any other value would be silently rewritten the next time the question
   * is opened in Tutor's builder.
   */
  step: number;
  defaultValue: number;
  pxPerUnit: number;
  labelEvery: number;
  minorTickEvery: number;
  precision: number;
}

/**
 * Range answer contract stored in `answer_two_gap_match` with `answer_view_format: "scale"`.
 *
 * `value` is the instructor's correct value. Tutor Pro's grader reads `value`,
 * `config.step`, and `config.precision`.
 */
export interface ScaleAnswerData {
  value: number;
  config: ScaleConfig;
}

/**
 * Tutor's defaults for a newly created Range row.
 */
export const NATIVE_SCALE_DEFAULTS: ScaleAnswerData = {
  value: 50,
  config: {
    min: 0,
    max: 100,
    step: 1,
    defaultValue: 50,
    pxPerUnit: 10,
    labelEvery: 10,
    minorTickEvery: 5,
    precision: 0,
  },
};

/**
 * Result of reading a stored Range answer value.
 *
 * `empty` means nothing is stored yet and defaults may be seeded. `malformed` means Tutor
 * holds a value TutorPress cannot interpret; that value must be preserved, never replaced.
 */
export type ScaleAnswerParseResult =
  | { status: "empty" }
  | { status: "valid"; data: ScaleAnswerData }
  | { status: "malformed" };

/**
 * Coerce an unknown stored value to a finite number, falling back when it is not one.
 */
const toFiniteNumber = (raw: unknown, fallback: number): number => {
  const parsed = typeof raw === "number" ? raw : parseFloat(String(raw));
  return Number.isFinite(parsed) ? parsed : fallback;
};

/**
 * Apply Tutor's config normalization.
 *
 * Mirrors the native editor's `normalizeScaleConfig()`, which pins `step` to `1`.
 */
export const normalizeScaleConfig = (config: ScaleConfig): ScaleConfig => ({ ...config, step: 1 });

/**
 * Parse a stored Range answer value defensively.
 *
 * Accepts a value only when it carries a finite numeric `value` and a `config` object,
 * which is the same acceptance test Tutor's native editor applies. Missing config keys
 * fall back to Tutor's defaults per key rather than rejecting the whole value.
 */
export const parseScaleAnswer = (raw: string | null | undefined): ScaleAnswerParseResult => {
  if (!raw || !raw.trim()) {
    return { status: "empty" };
  }

  let decoded: unknown;
  try {
    decoded = JSON.parse(raw);
  } catch {
    return { status: "malformed" };
  }

  if (!decoded || typeof decoded !== "object" || Array.isArray(decoded)) {
    return { status: "malformed" };
  }

  const candidate = decoded as { value?: unknown; config?: unknown };
  if (typeof candidate.value !== "number" || !Number.isFinite(candidate.value)) {
    return { status: "malformed" };
  }
  if (!candidate.config || typeof candidate.config !== "object" || Array.isArray(candidate.config)) {
    return { status: "malformed" };
  }

  const config = candidate.config as Record<string, unknown>;
  const defaults = NATIVE_SCALE_DEFAULTS.config;

  return {
    status: "valid",
    data: {
      value: candidate.value,
      config: normalizeScaleConfig({
        min: toFiniteNumber(config.min, defaults.min),
        max: toFiniteNumber(config.max, defaults.max),
        step: defaults.step,
        defaultValue: toFiniteNumber(config.defaultValue, defaults.defaultValue),
        pxPerUnit: toFiniteNumber(config.pxPerUnit, defaults.pxPerUnit),
        labelEvery: toFiniteNumber(config.labelEvery, defaults.labelEvery),
        minorTickEvery: toFiniteNumber(config.minorTickEvery, defaults.minorTickEvery),
        precision: toFiniteNumber(config.precision, defaults.precision),
      }),
    },
  };
};

/**
 * Serialize a Range answer value.
 *
 * Key order matches Tutor's native editor so an untouched default row serializes
 * identically on both builders.
 */
export const serializeScaleAnswer = (data: ScaleAnswerData): string => {
  const config = normalizeScaleConfig(data.config);

  return JSON.stringify({
    value: data.value,
    config: {
      min: config.min,
      max: config.max,
      step: config.step,
      defaultValue: config.defaultValue,
      pxPerUnit: config.pxPerUnit,
      labelEvery: config.labelEvery,
      minorTickEvery: config.minorTickEvery,
      precision: config.precision,
    },
  });
};

/**
 * One Graph (`coordinates`) grid point. Both axes are integers.
 */
export interface CoordinatePoint {
  x: number;
  y: number;
}

/**
 * The only axis ranges Tutor offers. An axis range of `n` spans `-n` through `n`.
 */
export type CoordinatesAxisRange = 10 | 20;

/**
 * Tutor's default axis range for a newly created Graph question.
 *
 * Also the fallback everywhere the stored setting is absent or unreadable, matching
 * Tutor Pro's grader and both of its Graph templates.
 */
export const NATIVE_COORDINATES_AXIS_RANGE: CoordinatesAxisRange = 10;

/** The axis ranges offered in the editor's selector. */
export const COORDINATES_AXIS_RANGE_OPTIONS: CoordinatesAxisRange[] = [10, 20];

/** Tutor's upper bound on correct answer points. */
export const MAX_COORDINATE_POINTS = 5;

/** The point Tutor shows for a Graph question with nothing stored yet. */
export const NATIVE_COORDINATES_DEFAULT_POINT: CoordinatePoint = { x: 0, y: 0 };

/**
 * Normalize a stored or selected axis range to one of Tutor's two supported values.
 *
 * Mirrors the native editor's `resolveAxisRange()` and Tutor's `Number()` coercion of
 * `coordinates_axis_range` on save: anything that is not 20 resolves to 10.
 */
export const resolveCoordinatesAxisRange = (raw: unknown): CoordinatesAxisRange =>
  Number(raw) === 20 ? 20 : NATIVE_COORDINATES_AXIS_RANGE;

/**
 * Round to the nearest integer and clamp both axes into the current grid extent.
 *
 * Mirrors the native editor's `sanitizePoint()`, which it applies to the whole point set
 * on every commit.
 */
export const sanitizeCoordinatePoint = (point: CoordinatePoint, axisRange: CoordinatesAxisRange): CoordinatePoint => {
  const clamp = (value: number): number => Math.max(-axisRange, Math.min(axisRange, Math.round(value)));

  return { x: clamp(point.x), y: clamp(point.y) };
};

/**
 * Result of reading a stored Graph answer value.
 *
 * `empty` means nothing usable is stored yet, which includes a stored `[]`. `malformed`
 * means Tutor holds a value TutorPress must preserve rather than interpret.
 */
export type CoordinatesAnswerParseResult =
  | { status: "empty" }
  | { status: "valid"; points: CoordinatePoint[] }
  | { status: "malformed" };

/**
 * Read one point from an unknown stored element, or `null` when it is not one.
 *
 * Coordinates are rounded, matching the native editor's own parse. Tutor's validator
 * requires integers, so a fractional stored value could not have come from either builder.
 */
const parseCoordinatePoint = (raw: unknown): CoordinatePoint | null => {
  if (!raw || typeof raw !== "object" || Array.isArray(raw)) {
    return null;
  }

  const candidate = raw as { x?: unknown; y?: unknown };
  if (typeof candidate.x !== "number" || typeof candidate.y !== "number") {
    return null;
  }
  if (!Number.isFinite(candidate.x) || !Number.isFinite(candidate.y)) {
    return null;
  }

  return { x: Math.round(candidate.x), y: Math.round(candidate.y) };
};

/**
 * Parse a stored Graph answer value defensively.
 *
 * Accepts only the two shapes Tutor itself accepts: a bare array of points, and a legacy
 * single `{x,y}` object, which Tutor's core validator wraps into a one-element list.
 * Every other shape is reported malformed so the stored value is preserved untouched —
 * that is what keeps an optional grader-honored `config` from ever being stripped, since
 * such a row is never rewritten.
 *
 * The point count is deliberately not capped here. A row holding more than Tutor's
 * five remains editable so the author can delete down to the limit; validation blocks
 * the save until then.
 */
export const parseCoordinatesAnswer = (raw: string | null | undefined): CoordinatesAnswerParseResult => {
  if (!raw || !raw.trim()) {
    return { status: "empty" };
  }

  let decoded: unknown;
  try {
    decoded = JSON.parse(raw);
  } catch {
    return { status: "malformed" };
  }

  if (Array.isArray(decoded)) {
    if (decoded.length === 0) {
      return { status: "empty" };
    }

    const points: CoordinatePoint[] = [];
    for (const element of decoded) {
      const point = parseCoordinatePoint(element);
      if (!point) {
        return { status: "malformed" };
      }
      points.push(point);
    }

    return { status: "valid", points };
  }

  const legacySinglePoint = parseCoordinatePoint(decoded);
  return legacySinglePoint ? { status: "valid", points: [legacySinglePoint] } : { status: "malformed" };
};

/**
 * Serialize a Graph answer value.
 *
 * Produces the bare array Tutor's native editor writes, with the same per-point key
 * order. Author order is preserved: Tutor never sorts, and Tutor Pro's grader sorts
 * normalized point strings itself, so ordering cannot affect grading.
 *
 * Returns `null` for an empty set. Tutor can never store `[]` — its editor coerces an
 * empty set back to the origin and refuses to delete the last point — and its validator
 * rejects a zero-length array, so writing one would produce a row Tutor will not save.
 */
export const serializeCoordinatesAnswer = (points: CoordinatePoint[]): string | null => {
  if (points.length === 0) {
    return null;
  }

  return JSON.stringify(points.map((point) => ({ x: point.x, y: point.y })));
};

// ============================================================================
// Tutor 4.0 temporary mask deletion contract
// ============================================================================

/**
 * Question types whose answer values can reference a Tutor Pro-owned quiz image file.
 *
 * Matches the three slugs Tutor's own builder checks before registering abandoned
 * values (`QuestionList.tsx:344-347`) and the three its server accepts for file-backed
 * deletion (`QuizBuilder.php:467`). Tutor's PHP `is_mask_image_question_type()` is a
 * deliberately different, narrower list — draw and pin only — used for a different
 * purpose, so do not reconcile the two.
 */
export const MASK_QUESTION_TYPES = ["draw_image", "pin_image", "puzzle"] as const;

/**
 * Whether a question type can reference a Tutor Pro-owned quiz image file.
 */
export const isMaskQuestionType = (questionType: string): boolean =>
  (MASK_QUESTION_TYPES as readonly string[]).includes(questionType);

/**
 * Collect the temporary mask/image values abandoned by deleting an unsaved question.
 *
 * Tutor registers these in exactly one place — deleting a question that has never been
 * persisted (`QuestionList.tsx:342-364`) — and harvests both `answer_two_gap_match` and
 * `image_url` from every answer row. There is deliberately no replace or clear trigger:
 * Tutor's client has none, and adding one would put TutorPress in the business of
 * deleting files Tutor leaves alone. Tutor's server cleans a *replaced* mask only for
 * `draw_image`/`pin_image` (`QuizBuilder.php:190-192`), only from
 * `answer_two_gap_match`, and only on an update to an already-persisted row
 * (`:151-164`). A replaced Puzzle asset and a replaced background image therefore leak
 * a file in native Tutor; those are native leaks and stay native leaks. An abandoned
 * unsaved mask is still a data URL that never became a file, so there is nothing for a
 * client-side replace trigger to clean up in the first place.
 *
 * The caller hands the result to Tutor's `deleted_temp_mask_values` field and Tutor
 * decides what to delete: it resolves each value to a path, requires the path to be
 * readable, and requires it to sit inside its own `tutor/quiz-images` directory
 * (`QuizBuilder.php:639-680`). TutorPress never inspects the filesystem and never
 * deletes anything.
 *
 * Three gates guard registration, and they can only ever register less than Tutor does:
 *
 * 1. `_data_status === "new"` is Tutor's own gate.
 * 2. `question_id < 0` requires a TutorPress temporary ID. **Keep this when question
 *    duplication is implemented.** Tutor's `handleDuplicateQuestion()` spreads the
 *    source row wholesale and stamps it `NEW`, so duplicating a persisted Draw question
 *    and deleting the copy registers the *original's* stored file for deletion. A
 *    duplicate that carries a non-numeric or persisted ID fails this gate and registers
 *    nothing, which is the safe direction.
 * 3. No `content_id`, so a Content Bank-linked row can never contribute a value. Tutor
 *    shares those files between linked rows.
 */
export const collectAbandonedTempMaskValues = (question: QuizQuestion): string[] => {
  if (question._data_status !== "new" || !(question.question_id < 0)) {
    return [];
  }

  if (question.content_id !== undefined && question.content_id !== null && question.content_id !== "") {
    return [];
  }

  if (!isMaskQuestionType(question.question_type)) {
    return [];
  }

  const values = (question.question_answers || [])
    .flatMap((answer) => [answer.answer_two_gap_match, answer.image_url])
    .map((value) => (typeof value === "string" ? value.trim() : ""))
    .filter((value) => value.length > 0);

  return Array.from(new Set(values));
};

/**
 * Generate a temporary ID for an unsaved question or answer row.
 *
 * Negative so Tutor's save path and TutorPress's deletion tracking can distinguish
 * unsaved rows from persisted ones.
 */
const createTemporaryId = (): number => -(Date.now() + Math.floor(Math.random() * 1000));

/**
 * Create a new, unsaved question of the given type.
 *
 * Settings come from `getDefaultQuestionSettings()`, the single definition of question
 * defaults, so no caller can drift from it. Puzzle is the one native type whose factory
 * creates its required answer immediately; the editor must never repair or seed that row
 * on mount.
 */
export const createDefaultQuestion = (questionType: QuizQuestionType, questionOrder: number): QuizQuestion => {
  const questionId = createTemporaryId();
  const questionAnswers: QuizQuestionOption[] =
    questionType === "puzzle"
      ? [
          {
            answer_id: createTemporaryId(),
            belongs_question_id: questionId,
            belongs_question_type: "puzzle",
            answer_title: "",
            is_correct: "1",
            answer_two_gap_match: "",
            answer_view_format: "puzzle",
            answer_order: 0,
            _data_status: "new",
          },
        ]
      : [];

  return {
    question_id: questionId,
    question_title: "",
    question_description: "",
    question_mark: 1,
    answer_explanation: "",
    question_order: questionOrder,
    question_type: questionType,
    question_settings: getDefaultQuestionSettings(questionType),
    question_answers: questionAnswers,
    _data_status: "new",
  };
};

/**
 * Create a new, unsaved answer row for a question.
 *
 * Retains Tutor's generic answer-row fields so any type can use one row without a
 * type-specific schema. Types that carry their configuration in
 * `answer_two_gap_match` or `answer_view_format` override those after creation.
 */
export const createDefaultAnswerRow = (question: QuizQuestion, answerOrder: number): QuizQuestionOption => ({
  answer_id: createTemporaryId(),
  belongs_question_id: question.question_id,
  belongs_question_type: question.question_type,
  answer_title: "",
  is_correct: "0",
  image_id: 0,
  image_url: "",
  answer_two_gap_match: "",
  answer_view_format: "",
  answer_order: answerOrder,
  _data_status: "new",
});
