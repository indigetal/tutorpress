import { describe, expect, it } from "@jest/globals";
import type { QuizQuestion } from "../../types/quiz";
import { prepareInteractiveQuizUpdateQuestionsForTutor } from "../quizForm";

describe("prepareInteractiveQuizUpdateQuestionsForTutor", () => {
  it("rewrites new H5P ids without mutating input, persisted H5P, or non-H5P questions", () => {
    const persisted = {
      question_id: 888434,
      question_type: "h5p",
      _data_status: "no_change",
    } as QuizQuestion;
    const created = {
      question_id: -123,
      question_type: "h5p",
      _data_status: "new",
    } as QuizQuestion;
    const nonH5p = {
      question_id: -5,
      question_type: "true_false",
      _data_status: "new",
    } as QuizQuestion;
    const input = [persisted, created, nonH5p];
    const result = prepareInteractiveQuizUpdateQuestionsForTutor(input);

    expect(input).toEqual([persisted, created, nonH5p]);
    expect(created.question_id).toBe(-123);
    expect(result.map((row) => row.question_id)).toEqual([
      888434,
      "tutorpress-h5p-123",
      -5,
    ]);
  });
});
