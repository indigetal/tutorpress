/**
 * Question Validation Hook
 *
 * @description Centralized validation logic for all quiz question types. Provides consistent
 *              validation rules and error messaging across the entire quiz system. Extracted
 *              from QuizModal and question components during Phase 2.5 refactoring to eliminate
 *              validation logic duplication and create extensible validation system.
 *
 * @features
 * - Validation for all supported question types
 * - Extensible validation registry for new question types
 * - Consistent error messaging with i18n support
 * - Question-level and quiz-level validation
 * - Configurable validation rules
 * - Type-safe validation interfaces
 *
 * @usage
 * const {
 *   validateQuestion,
 *   validateAllQuestions,
 *   getQuestionErrors,
 *   hasQuestionErrors,
 *   getQuizValidationSummary
 * } = useQuestionValidation();
 *
 * @package TutorPress
 * @subpackage Quiz/Hooks
 * @since 1.0.0
 */

import { useMemo } from "react";
import { __ } from "@wordpress/i18n";
import type { QuizQuestion, QuizQuestionType } from "../../types/quiz";

/**
 * Validation result for a single question
 */
export interface QuestionValidationResult {
  /** Whether the question is valid */
  isValid: boolean;
  /** Array of validation error messages */
  errors: string[];
  /** Question type that was validated */
  questionType: QuizQuestionType;
  /** Question index in the quiz */
  questionIndex?: number;
}

/**
 * Validation result for an entire quiz
 */
export interface QuizValidationResult {
  /** Whether the entire quiz is valid */
  isValid: boolean;
  /** Array of all validation errors across all questions */
  errors: string[];
  /** Map of question index to validation results */
  questionResults: Map<number, QuestionValidationResult>;
  /** Total number of questions validated */
  totalQuestions: number;
  /** Number of questions with errors */
  questionsWithErrors: number;
}

/**
 * Validation rule function type
 */
export type ValidationRule = (question: QuizQuestion) => string[];

/**
 * Question validation registry
 */
interface QuestionValidationRegistry {
  [key: string]: ValidationRule[];
}

/**
 * Question Validation Hook
 */
export const useQuestionValidation = () => {
  /**
   * Validation rules registry by question type
   */
  const validationRegistry: QuestionValidationRegistry = useMemo(
    () => ({
      // Multiple Choice validation rules
      multiple_choice: [
        // Minimum options rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          return options.length < 2
            ? [__("Multiple choice questions must have at least 2 options.", "tutorpress")]
            : [];
        },

        // Empty option text rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          const emptyOptions = options.filter((option) => !option.answer_title?.trim());
          return emptyOptions.length > 0 ? [__("All options must have text content.", "tutorpress")] : [];
        },

        // Duplicate option content rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          const optionTexts = options.map((option) => option.answer_title?.trim().toLowerCase()).filter(Boolean);
          const uniqueTexts = new Set(optionTexts);
          return optionTexts.length !== uniqueTexts.size
            ? [__("Options cannot have duplicate content.", "tutorpress")]
            : [];
        },

        // Correct answer required rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          const correctAnswers = options.filter((answer) => answer.is_correct === "1");
          const isAnswerRequired = question.question_settings.answer_required;
          return isAnswerRequired && correctAnswers.length === 0
            ? [__("At least one option must be marked as correct.", "tutorpress")]
            : [];
        },
      ],

      // True/False validation rules
      true_false: [
        // Correct answer required rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          const correctAnswers = options.filter((answer) => answer.is_correct === "1");
          const isAnswerRequired = question.question_settings.answer_required;
          return isAnswerRequired && correctAnswers.length === 0
            ? [__("Please select the correct answer (True or False).", "tutorpress")]
            : [];
        },
      ],

      // Single Choice validation rules (similar to multiple choice)
      single_choice: [
        // Minimum options rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          return options.length < 2 ? [__("Single choice questions must have at least 2 options.", "tutorpress")] : [];
        },

        // Empty option text rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          const emptyOptions = options.filter((option) => !option.answer_title?.trim());
          return emptyOptions.length > 0 ? [__("All options must have text content.", "tutorpress")] : [];
        },

        // Exactly one correct answer rule
        (question: QuizQuestion) => {
          const options = question.question_answers || [];
          const correctAnswers = options.filter((answer) => answer.is_correct === "1");
          const isAnswerRequired = question.question_settings.answer_required;
          if (isAnswerRequired) {
            if (correctAnswers.length === 0) {
              return [__("Please select the correct answer.", "tutorpress")];
            }
            if (correctAnswers.length > 1) {
              return [__("Single choice questions can only have one correct answer.", "tutorpress")];
            }
          }
          return [];
        },
      ],

      // Open Ended validation rules
      open_ended: [
        // Answer requirement check (open ended questions are typically always valid)
        (question: QuizQuestion) => {
          // Open ended questions don't require predefined answers
          return [];
        },
      ],

      // Fill in the Blank validation rules
      fill_in_the_blank: [
        // Will be implemented when fill-in-the-blank is added
        (question: QuizQuestion) => {
          // Placeholder for future implementation
          return [];
        },
      ],

      // Short Answer validation rules
      short_answer: [
        // Will be implemented when short answer is added
        (question: QuizQuestion) => {
          // Placeholder for future implementation
          return [];
        },
      ],

      // Matching validation rules
      matching: [
        // Will be implemented when matching is added
        (question: QuizQuestion) => {
          // Placeholder for future implementation
          return [];
        },
      ],

      // Image Matching validation rules
      image_matching: [
        // Will be implemented when image matching is added
        (question: QuizQuestion) => {
          // Placeholder for future implementation
          return [];
        },
      ],

      // Image Answering validation rules
      image_answering: [
        // Will be implemented when image answering is added
        (question: QuizQuestion) => {
          // Placeholder for future implementation
          return [];
        },
      ],

      // Ordering validation rules
      ordering: [
        // Minimum options requirement
        (question: QuizQuestion) => {
          const answers = question.question_answers || [];
          if (answers.length < 2) {
            return [__("Ordering questions must have at least 2 options.", "tutorpress")];
          }
          return [];
        },
        // Answer text requirement
        (question: QuizQuestion) => {
          const answers = question.question_answers || [];
          const emptyAnswers = answers.filter((answer) => !answer.answer_title?.trim());
          if (emptyAnswers.length > 0) {
            return [__("All ordering options must have text.", "tutorpress")];
          }
          return [];
        },
        // Duplicate answers check
        (question: QuizQuestion) => {
          const answers = question.question_answers || [];
          const answerTexts = answers.map((answer) => answer.answer_title?.trim().toLowerCase()).filter(Boolean);
          const uniqueTexts = new Set(answerTexts);
          if (answerTexts.length !== uniqueTexts.size) {
            return [__("Ordering options must be unique.", "tutorpress")];
          }
          return [];
        },
      ],

      // H5P validation rules
      h5p: [
        // Will be implemented when H5P is added
        (question: QuizQuestion) => {
          // Placeholder for future implementation
          return [];
        },
      ],
    }),
    []
  );

  /**
   * Common validation rules that apply to all question types
   */
  const commonValidationRules: ValidationRule[] = useMemo(
    () => [
      // Question title required
      (question: QuizQuestion) => {
        return !question.question_title?.trim() ? [__("Question title is required.", "tutorpress")] : [];
      },
    ],
    []
  );

  /**
   * Validate a single question
   */
  const validateQuestion = (question: QuizQuestion, questionIndex?: number): QuestionValidationResult => {
    const errors: string[] = [];

    // Apply common validation rules
    commonValidationRules.forEach((rule) => {
      errors.push(...rule(question));
    });

    // Apply question type specific validation rules
    const typeRules = validationRegistry[question.question_type] || [];
    typeRules.forEach((rule) => {
      errors.push(...rule(question));
    });

    return {
      isValid: errors.length === 0,
      errors,
      questionType: question.question_type,
      questionIndex,
    };
  };

  /**
   * Validate all questions in a quiz
   */
  const validateAllQuestions = (questions: QuizQuestion[]): QuizValidationResult => {
    const allErrors: string[] = [];
    const questionResults = new Map<number, QuestionValidationResult>();
    let questionsWithErrors = 0;

    questions.forEach((question, index) => {
      const result = validateQuestion(question, index);
      questionResults.set(index, result);

      if (!result.isValid) {
        questionsWithErrors++;
        // Prefix errors with question number for quiz-level reporting
        result.errors.forEach((error) => {
          allErrors.push(__(`Question ${index + 1}: ${error}`, "tutorpress"));
        });
      }
    });

    return {
      isValid: allErrors.length === 0,
      errors: allErrors,
      questionResults,
      totalQuestions: questions.length,
      questionsWithErrors,
    };
  };

  /**
   * Get validation errors for a specific question
   */
  const getQuestionErrors = (question: QuizQuestion): string[] => {
    const result = validateQuestion(question);
    return result.errors;
  };

  /**
   * Check if a question has validation errors
   */
  const hasQuestionErrors = (question: QuizQuestion): boolean => {
    const result = validateQuestion(question);
    return !result.isValid;
  };

  /**
   * Get a summary of quiz validation
   */
  const getQuizValidationSummary = (questions: QuizQuestion[]) => {
    const result = validateAllQuestions(questions);
    return {
      isValid: result.isValid,
      totalQuestions: result.totalQuestions,
      questionsWithErrors: result.questionsWithErrors,
      errorCount: result.errors.length,
      validQuestions: result.totalQuestions - result.questionsWithErrors,
    };
  };

  /**
   * Register a new validation rule for a question type
   */
  const registerValidationRule = (questionType: QuizQuestionType, rule: ValidationRule) => {
    if (!validationRegistry[questionType]) {
      validationRegistry[questionType] = [];
    }
    validationRegistry[questionType].push(rule);
  };

  return {
    validateQuestion,
    validateAllQuestions,
    getQuestionErrors,
    hasQuestionErrors,
    getQuizValidationSummary,
    registerValidationRule,
  };
};
