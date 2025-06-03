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
   * Sanitize HTML content from TinyMCE editors
   */
  private sanitizeQuizData(quizData: QuizForm): any {
    // Create a deep copy to avoid mutating the original data
    const sanitized = {
      ...quizData,
      questions: quizData.questions.map((question) => {
        // Flatten question_settings into individual properties as expected by Tutor LMS
        const { question_settings, ...questionBase } = question;

        // Create the serialized question_settings as Tutor LMS expects
        const serializedSettings = {
          question_type: question.question_type,
          answer_required: question_settings.answer_required ? 1 : 0,
          randomize_question: question_settings.randomize_question ? 1 : 0,
          question_mark: question.question_mark,
          show_question_mark: question_settings.show_question_mark ? 1 : 0,
        };

        return {
          ...questionBase,
          // Sanitize HTML content in question fields
          question_description: this.sanitizeHtml(question.question_description),
          answer_explanation: this.sanitizeHtml(question.answer_explanation),
          // Provide both formats for Tutor LMS compatibility
          question_settings: serializedSettings, // Keep the nested object for compatibility
          // Also provide flattened properties (convert booleans to integers)
          answer_required: question_settings.answer_required ? 1 : 0,
          randomize_question: question_settings.randomize_question ? 1 : 0,
          show_question_mark: question_settings.show_question_mark ? 1 : 0,
          // Note: question_mark and question_type are already at the top level
          question_answers: question.question_answers.map((answer) => ({
            ...answer,
            // Sanitize HTML content in answer fields
            answer_title: this.sanitizeHtml(answer.answer_title),
          })),
        };
      }),
    };

    return sanitized;
  }

  /**
   * Basic HTML sanitization for Tutor LMS compatibility
   */
  private sanitizeHtml(html: string): string {
    if (!html || typeof html !== "string") {
      return "";
    }

    // For now, just return the HTML as-is since Tutor LMS should handle HTML content
    // If we get specific errors, we can add more sanitization here
    return html.trim();
  }

  /**
   * Save a quiz using the Tutor LMS quiz builder AJAX endpoint
   */
  async saveQuiz(quizData: QuizForm, courseId: number, topicId: number): Promise<QuizOperationResult<QuizDetails>> {
    try {
      // Sanitize HTML content from TinyMCE editors
      const sanitizedQuizData = this.sanitizeQuizData(quizData);

      console.log("Original quiz data:", quizData);
      console.log("Sanitized quiz data:", sanitizedQuizData);

      // Prepare the form data as Tutor LMS expects it
      const formData = new FormData();
      formData.append("action", "tutor_quiz_builder_save");
      formData.append("_tutor_nonce", this.getTutorNonce());
      formData.append("payload", JSON.stringify(sanitizedQuizData));
      formData.append("course_id", courseId.toString());
      formData.append("topic_id", topicId.toString());

      // Add deleted IDs if they exist
      if (sanitizedQuizData.deleted_question_ids && sanitizedQuizData.deleted_question_ids.length > 0) {
        sanitizedQuizData.deleted_question_ids.forEach((id: number, index: number) => {
          formData.append(`deleted_question_ids[${index}]`, id.toString());
        });
      }

      if (sanitizedQuizData.deleted_answer_ids && sanitizedQuizData.deleted_answer_ids.length > 0) {
        sanitizedQuizData.deleted_answer_ids.forEach((id: number, index: number) => {
          formData.append(`deleted_answer_ids[${index}]`, id.toString());
        });
      }

      // Get the AJAX URL from Tutor LMS globals (handles multisite)
      const tutorObject = (window as any)._tutorobject;
      const ajaxUrl = tutorObject?.ajaxurl || "/wp-admin/admin-ajax.php";

      // Log what we're sending to the API
      console.log("Sending to Tutor LMS API:");
      console.log("- URL:", ajaxUrl);
      console.log("- Action:", "tutor_quiz_builder_save");
      console.log("- Course ID:", courseId);
      console.log("- Topic ID:", topicId);
      console.log("- Nonce:", this.getTutorNonce());
      console.log("- Payload (stringified):", JSON.stringify(sanitizedQuizData));

      // Use @wordpress/api-fetch to call the Tutor LMS AJAX endpoint
      const response = await apiFetch({
        url: ajaxUrl,
        method: "POST",
        body: formData,
      });

      console.log("Tutor LMS API response:", response);

      // Handle the response based on Tutor LMS patterns
      if (response && typeof response === "object") {
        const tutorResponse = response as any;

        // Check for success in various possible response formats
        const isSuccess =
          tutorResponse.success === true ||
          tutorResponse.status_code === 200 ||
          (tutorResponse.message && tutorResponse.message.includes("successfully"));

        if (isSuccess && tutorResponse.data) {
          let quizDetails: QuizDetails | null = null;

          // Validate the quiz details response
          if (isValidQuizDetails(tutorResponse.data)) {
            quizDetails = tutorResponse.data as QuizDetails;
          } else {
            // If data exists but isn't valid, try to construct a valid response
            const quizId = tutorResponse.data.ID || tutorResponse.data;
            if (typeof quizId === "number") {
              // Fetch the complete quiz details
              const detailsResult = await this.getQuizDetails(quizId);
              if (detailsResult.success && detailsResult.data) {
                quizDetails = detailsResult.data;
              }
            }
          }

          // If we have quiz details, ensure the parent is set correctly
          if (quizDetails) {
            // Check if the quiz parent is set to the topic (it should be)
            if (quizDetails.post_parent !== topicId) {
              console.log(`Quiz parent mismatch. Expected: ${topicId}, Got: ${quizDetails.post_parent}. Fixing...`);

              try {
                // Update the quiz parent to be the topic
                const updateResponse = await apiFetch({
                  path: `/wp/v2/tutor_quiz/${quizDetails.ID}`,
                  method: "POST",
                  data: {
                    parent: topicId,
                  },
                });

                console.log("Quiz parent updated successfully:", updateResponse);

                // Update our local quiz details
                quizDetails.post_parent = topicId;
              } catch (updateError) {
                console.warn("Failed to update quiz parent, but quiz was created:", updateError);
                // Don't fail the entire operation for this
              }
            }

            return {
              success: true,
              data: quizDetails,
            };
          }
        }

        // Handle error response
        const errorMessage =
          tutorResponse.message || tutorResponse.data?.message || __("Failed to save quiz", "tutorpress");

        console.error("Tutor LMS API error response:", {
          status_code: tutorResponse.status_code,
          message: tutorResponse.message,
          data: tutorResponse.data,
          full_response: tutorResponse,
        });

        return {
          success: false,
          error: createQuizError(
            QuizErrorCode.SAVE_FAILED,
            errorMessage,
            { type: "save", topicId, quizId: sanitizedQuizData.ID },
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
          quizId: sanitizedQuizData.ID,
        }),
      };
    } catch (error) {
      console.error("Quiz save error:", error);

      // Check if this is a 400 error with detailed response data
      if (error && typeof error === "object" && (error as any).status_code === 400) {
        const errorResponse = error as any;

        console.error("Detailed 400 error response:", {
          status_code: errorResponse.status_code,
          message: errorResponse.message,
          data: errorResponse.data,
          full_error: errorResponse,
        });

        // Log the data object specifically to see its contents
        if (errorResponse.data) {
          console.error("Error data details:", errorResponse.data);
          console.error("Error data type:", typeof errorResponse.data);
          console.error("Error data keys:", Object.keys(errorResponse.data || {}));
        }

        // Extract more specific error information if available
        let detailedMessage = errorResponse.message || "Bad Request";
        if (errorResponse.data && typeof errorResponse.data === "object") {
          if (errorResponse.data.message) {
            detailedMessage = errorResponse.data.message;
          }
          if (errorResponse.data.errors) {
            detailedMessage += ` - ${JSON.stringify(errorResponse.data.errors)}`;
          }
        }

        return {
          success: false,
          error: createQuizError(
            QuizErrorCode.SAVE_FAILED,
            detailedMessage,
            { type: "save", topicId, quizId: quizData.ID },
            { details: JSON.stringify(errorResponse) }
          ),
        };
      }

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
   * Uses TutorPress REST API endpoint
   */
  async getQuizDetails(quizId: number): Promise<QuizOperationResult<QuizDetails>> {
    try {
      // Use @wordpress/api-fetch to call our TutorPress REST API endpoint
      const response = await apiFetch({
        path: `/tutorpress/v1/quizzes/${quizId}`,
        method: "GET",
      });

      if (response && typeof response === "object") {
        const apiResponse = response as any;

        if (apiResponse.success && apiResponse.data) {
          // Validate the quiz details response
          if (isValidQuizDetails(apiResponse.data)) {
            return {
              success: true,
              data: apiResponse.data as QuizDetails,
            };
          } else {
            // Try to construct a valid response from the data
            const quizData = apiResponse.data;
            const quizDetails: QuizDetails = {
              ID: quizData.ID || quizData.id || 0,
              post_title: quizData.post_title || quizData.title || "",
              post_content: quizData.post_content || quizData.content || "",
              post_status: quizData.post_status || "publish",
              post_author: quizData.post_author?.toString() || "0",
              post_parent: quizData.post_parent || 0,
              menu_order: quizData.menu_order || 0,
              quiz_option: quizData.quiz_option || quizData.quiz_settings || {},
              questions: quizData.questions || quizData.quiz_questions || [],
            };

            return {
              success: true,
              data: quizDetails,
            };
          }
        }

        // Handle error response
        const errorMessage = apiResponse.message || __("Quiz not found", "tutorpress");

        return {
          success: false,
          error: createQuizError(QuizErrorCode.FETCH_FAILED, errorMessage, {
            type: "edit",
            quizId,
            topicId: 0,
          }),
        };
      }

      return {
        success: false,
        error: createQuizError(QuizErrorCode.FETCH_FAILED, __("Invalid response from server", "tutorpress"), {
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
   * Uses TutorPress REST API endpoint
   */
  async deleteQuiz(quizId: number): Promise<QuizOperationResult<void>> {
    try {
      // Use @wordpress/api-fetch to call our TutorPress REST API endpoint
      const response = await apiFetch({
        path: `/tutorpress/v1/quizzes/${quizId}`,
        method: "DELETE",
      });

      if (response && typeof response === "object") {
        const apiResponse = response as any;

        if (apiResponse.success) {
          return {
            success: true,
          };
        }

        // Handle error response
        const errorMessage = apiResponse.message || __("Failed to delete quiz", "tutorpress");

        return {
          success: false,
          error: createQuizError(QuizErrorCode.DELETE_FAILED, errorMessage, {
            type: "delete",
            quizId,
          }),
        };
      }

      // For successful deletion, the response might be empty (204 status)
      return {
        success: true,
      };
    } catch (error) {
      console.error("Quiz delete error:", error);

      // Check if this is a 204 No Content response (successful deletion)
      if (error instanceof Error && error.message.includes("204")) {
        return {
          success: true,
        };
      }

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
