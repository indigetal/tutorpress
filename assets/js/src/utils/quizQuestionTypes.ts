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
 * defaults, so no caller can drift from it.
 */
export const createDefaultQuestion = (questionType: QuizQuestionType, questionOrder: number): QuizQuestion => ({
  question_id: createTemporaryId(),
  question_title: "",
  question_description: "",
  question_mark: 1,
  answer_explanation: "",
  question_order: questionOrder,
  question_type: questionType,
  question_settings: getDefaultQuestionSettings(questionType),
  question_answers: [],
  _data_status: "new",
});

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
