import { getTopics, reorderTopics, duplicateTopic } from "./topics";
import { quizService, saveQuiz, getQuizDetails, deleteQuiz, duplicateQuiz } from "./quiz";
import {
  wcService,
  getWcProducts,
  getWcProductDetails,
  eddService,
  getEddProducts,
  getEddProductDetails,
} from "./product-service";
import {
  getDefaultQuizSettings,
  getDefaultQuestionSettings,
  isValidQuizQuestion,
  isValidQuizDetails,
  createQuizError,
} from "../types/quiz";
// Import quiz form utilities
import * as quizFormUtils from "../utils/quizForm";
import type { TutorPressApi } from "../types/wordpress";

// Create the quiz utilities object
const quizUtils = {
  getDefaultQuizSettings,
  getDefaultQuestionSettings,
  isValidQuizQuestion,
  isValidQuizDetails,
  createQuizError,
  // Add quiz service methods
  saveQuiz,
  getQuizDetails,
  deleteQuiz,
  duplicateQuiz,
  // Add the service instance for advanced usage
  service: quizService,
};

// Create the API object
const api: TutorPressApi = {
  getTopics,
  reorderTopics,
  duplicateTopic,
  getWcProducts,
  getWcProductDetails,
  getEddProducts,
  getEddProductDetails,
};

// Export the API object
export default api;

// Initialize window.tutorpress if it doesn't exist
if (typeof window.tutorpress === "undefined") {
  window.tutorpress = {
    api,
    quiz: quizUtils,
    utils: quizFormUtils,
    wc: wcService,
    edd: eddService,
  };
} else {
  window.tutorpress.api = api;
  window.tutorpress.quiz = quizUtils;
  window.tutorpress.utils = quizFormUtils;
  window.tutorpress.wc = wcService;
  window.tutorpress.edd = eddService;
}
