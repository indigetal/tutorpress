/**
 * Quiz API Service for TutorPress
 *
 * This service wraps Tutor LMS quiz functionality with WordPress-native API patterns.
 * It handles the quiz builder AJAX endpoint and provides a clean interface for quiz operations.
 */

import apiFetch from "@wordpress/api-fetch";
import { __ } from "@wordpress/i18n";
import {
  QuizForm,
  QuizDetails,
  QuizOperationResult,
  QuizError,
  QuizErrorCode,
  createQuizError,
  isValidQuizDetails,
} from "../types/quiz";

/**
 * Quiz service class that handles all quiz-related API operations
 */
class QuizService {
  /**
   * Get the Tutor LMS nonce for AJAX requests
   */
  private getTutorNonce(): string {
    // Check for Tutor LMS nonce in various possible locations
    const tutorData = (window as any).tutor_data || (window as any)._tutor_nonce || (window as any).tutorConfig;

    if (tutorData && tutorData._tutor_nonce) {
      return tutorData._tutor_nonce;
    }

    // Check _tutorobject (most common location)
    const tutorObject = (window as any)._tutorobject;
    if (tutorObject && tutorObject._tutor_nonce) {
      return tutorObject._tutor_nonce;
    }

    // Fallback: try to get from meta tag or other sources
    const nonceElement = document.querySelector('meta[name="tutor-nonce"]');
    if (nonceElement) {
      return nonceElement.getAttribute("content") || "";
    }

    // Last resort: try to get from any tutor-related global
    const globals = Object.keys(window).filter((key) => key.toLowerCase().includes("tutor"));
    for (const globalKey of globals) {
      const globalValue = (window as any)[globalKey];
      if (globalValue && typeof globalValue === "object" && globalValue._tutor_nonce) {
        return globalValue._tutor_nonce;
      }
    }

    console.warn("Tutor LMS nonce not found. Quiz operations may fail.");
    return "";
  }

  /**
   * Save a quiz using the Tutor LMS quiz builder AJAX endpoint
   */
  async saveQuiz(quizData: QuizForm, courseId: number, topicId: number): Promise<QuizOperationResult<QuizDetails>> {
    try {
      // Prepare the form data as Tutor LMS expects it
      const formData = new FormData();
      formData.append("action", "tutor_quiz_builder_save");
      formData.append("_tutor_nonce", this.getTutorNonce());
      formData.append("payload", JSON.stringify(quizData));
      formData.append("course_id", courseId.toString());
      formData.append("topic_id", topicId.toString());

      // Add deleted IDs if they exist
      if (quizData.deleted_question_ids && quizData.deleted_question_ids.length > 0) {
        quizData.deleted_question_ids.forEach((id, index) => {
          formData.append(`deleted_question_ids[${index}]`, id.toString());
        });
      }

      if (quizData.deleted_answer_ids && quizData.deleted_answer_ids.length > 0) {
        quizData.deleted_answer_ids.forEach((id, index) => {
          formData.append(`deleted_answer_ids[${index}]`, id.toString());
        });
      }

      // Get the AJAX URL from Tutor LMS globals (handles multisite)
      const tutorObject = (window as any)._tutorobject;
      const ajaxUrl = tutorObject?.ajaxurl || "/wp-admin/admin-ajax.php";

      // Use @wordpress/api-fetch to call the Tutor LMS AJAX endpoint
      const response = await apiFetch({
        url: ajaxUrl,
        method: "POST",
        body: formData,
      });

      // Handle the response based on Tutor LMS patterns
      if (response && typeof response === "object") {
        const tutorResponse = response as any;

        // Check for success in various possible response formats
        const isSuccess =
          tutorResponse.success === true ||
          tutorResponse.status_code === 200 ||
          (tutorResponse.message && tutorResponse.message.includes("successfully"));

        if (isSuccess && tutorResponse.data) {
          // Validate the quiz details response
          if (isValidQuizDetails(tutorResponse.data)) {
            return {
              success: true,
              data: tutorResponse.data as QuizDetails,
            };
          } else {
            // If data exists but isn't valid, try to construct a valid response
            const quizId = tutorResponse.data.ID || tutorResponse.data;
            if (typeof quizId === "number") {
              // Fetch the complete quiz details
              const detailsResult = await this.getQuizDetails(quizId);
              if (detailsResult.success && detailsResult.data) {
                return {
                  success: true,
                  data: detailsResult.data,
                };
              }
            }
          }
        }

        // Handle error response
        const errorMessage =
          tutorResponse.message || tutorResponse.data?.message || __("Failed to save quiz", "tutorpress");

        return {
          success: false,
          error: createQuizError(
            QuizErrorCode.SAVE_FAILED,
            errorMessage,
            { type: "save", topicId, quizId: quizData.ID },
            { details: JSON.stringify(tutorResponse) }
          ),
        };
      }

      // Unexpected response format
      return {
        success: false,
        error: createQuizError(QuizErrorCode.INVALID_RESPONSE, __("Invalid response from server", "tutorpress"), {
          type: "save",
          topicId,
          quizId: quizData.ID,
        }),
      };
    } catch (error) {
      console.error("Quiz save error:", error);

      return {
        success: false,
        error: createQuizError(
          QuizErrorCode.NETWORK_ERROR,
          error instanceof Error ? error.message : __("Network error occurred", "tutorpress"),
          { type: "save", topicId, quizId: quizData.ID },
          { details: error instanceof Error ? error.stack : String(error) }
        ),
      };
    }
  }

  /**
   * Get quiz details by quiz ID
   * Since there's no dedicated AJAX endpoint, we'll use a REST API approach
   */
  async getQuizDetails(quizId: number): Promise<QuizOperationResult<QuizDetails>> {
    try {
      // Try to get quiz details via WordPress REST API first
      const response = await apiFetch({
        path: `/wp/v2/tutor_quiz/${quizId}?_embed=true`,
        method: "GET",
      });

      if (response && typeof response === "object") {
        const post = response as any;

        // Get quiz meta data
        const quizOption = post.meta?.tutor_quiz_option || {};

        // Construct quiz details object
        const quizDetails: QuizDetails = {
          ID: post.id,
          post_title: post.title?.rendered || post.title || "",
          post_content: post.content?.rendered || post.content || "",
          post_status: post.status || "publish",
          post_author: post.author?.toString() || "0",
          post_parent: post.parent || 0,
          menu_order: post.menu_order || 0,
          quiz_option: quizOption,
          questions: [], // Questions would need to be fetched separately
        };

        return {
          success: true,
          data: quizDetails,
        };
      }

      return {
        success: false,
        error: createQuizError(QuizErrorCode.FETCH_FAILED, __("Quiz not found", "tutorpress"), {
          type: "edit",
          quizId,
          topicId: 0,
        }),
      };
    } catch (error) {
      console.error("Quiz fetch error:", error);

      return {
        success: false,
        error: createQuizError(
          QuizErrorCode.FETCH_FAILED,
          error instanceof Error ? error.message : __("Failed to fetch quiz details", "tutorpress"),
          { type: "edit", quizId, topicId: 0 },
          { details: error instanceof Error ? error.stack : String(error) }
        ),
      };
    }
  }

  /**
   * Delete a quiz
   * Uses WordPress REST API to delete the quiz post
   */
  async deleteQuiz(quizId: number): Promise<QuizOperationResult<void>> {
    try {
      const response = await apiFetch({
        path: `/wp/v2/tutor_quiz/${quizId}?force=true`,
        method: "DELETE",
      });

      if (response && (response as any).deleted === true) {
        return {
          success: true,
        };
      }

      return {
        success: false,
        error: createQuizError(QuizErrorCode.DELETE_FAILED, __("Failed to delete quiz", "tutorpress"), {
          type: "delete",
          quizId,
        }),
      };
    } catch (error) {
      console.error("Quiz delete error:", error);

      return {
        success: false,
        error: createQuizError(
          QuizErrorCode.DELETE_FAILED,
          error instanceof Error ? error.message : __("Failed to delete quiz", "tutorpress"),
          { type: "delete", quizId },
          { details: error instanceof Error ? error.stack : String(error) }
        ),
      };
    }
  }

  /**
   * Duplicate a quiz
   * This will create a copy of an existing quiz
   */
  async duplicateQuiz(
    sourceQuizId: number,
    topicId: number,
    courseId: number
  ): Promise<QuizOperationResult<QuizDetails>> {
    try {
      // First, get the source quiz details
      const sourceResult = await this.getQuizDetails(sourceQuizId);
      if (!sourceResult.success || !sourceResult.data) {
        return {
          success: false,
          error: createQuizError(QuizErrorCode.DUPLICATE_FAILED, __("Source quiz not found", "tutorpress"), {
            type: "duplicate",
            quizId: sourceQuizId,
            topicId,
          }),
        };
      }

      // Create a new quiz based on the source
      const sourceQuiz = sourceResult.data;
      const duplicatedQuizData: QuizForm = {
        post_title: `${sourceQuiz.post_title} (Copy)`,
        post_content: sourceQuiz.post_content,
        quiz_option: sourceQuiz.quiz_option,
        questions: sourceQuiz.questions.map((question) => ({
          ...question,
          question_id: 0, // Reset ID for new question
          _data_status: "new" as const,
          question_answers: question.question_answers.map((answer) => ({
            ...answer,
            answer_id: 0, // Reset ID for new answer
            belongs_question_id: 0, // Will be set when question is created
            _data_status: "new" as const,
          })),
        })),
        deleted_question_ids: [],
        deleted_answer_ids: [],
      };

      // Save the duplicated quiz
      return await this.saveQuiz(duplicatedQuizData, courseId, topicId);
    } catch (error) {
      console.error("Quiz duplicate error:", error);

      return {
        success: false,
        error: createQuizError(
          QuizErrorCode.DUPLICATE_FAILED,
          error instanceof Error ? error.message : __("Failed to duplicate quiz", "tutorpress"),
          { type: "duplicate", quizId: sourceQuizId, topicId },
          { details: error instanceof Error ? error.stack : String(error) }
        ),
      };
    }
  }
}

// Create and export a singleton instance
export const quizService = new QuizService();

// Export individual methods for convenience - properly bound to the instance
export const saveQuiz = quizService.saveQuiz.bind(quizService);
export const getQuizDetails = quizService.getQuizDetails.bind(quizService);
export const deleteQuiz = quizService.deleteQuiz.bind(quizService);
export const duplicateQuiz = quizService.duplicateQuiz.bind(quizService);
