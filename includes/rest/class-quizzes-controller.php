<?php
/**
 * Quizzes REST Controller Class
 *
 * Handles REST API functionality for quizzes.
 *
 * @package TutorPress
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

class TutorPress_REST_Quizzes_Controller extends TutorPress_REST_Controller {

    /**
     * Initialize the controller.
     *
     * @since 1.0.0
     * @return void
     */
    public static function init() {
        // Hook into post save for quiz synchronization if needed
        add_action('save_post_tutor_quiz', [__CLASS__, 'handle_quiz_save'], 999, 3);
    }

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->rest_base = 'quizzes';
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        try {
            // Get quizzes for a topic
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base,
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_items'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'topic_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the topic to get quizzes for.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

            // Single quiz operations
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<id>[\d]+)',
                [
                    [
                        'methods'             => WP_REST_Server::READABLE,
                        'callback'            => [$this, 'get_item'],
                        'permission_callback' => [$this, 'check_permission'],
                    ],
                    [
                        'methods'             => WP_REST_Server::DELETABLE,
                        'callback'            => [$this, 'delete_item'],
                        'permission_callback' => [$this, 'check_permission'],
                    ],
                ]
            );

            // Duplicate quiz
            register_rest_route(
                $this->namespace,
                '/' . $this->rest_base . '/(?P<id>[\d]+)/duplicate',
                [
                    [
                        'methods'             => WP_REST_Server::CREATABLE,
                        'callback'            => [$this, 'duplicate_item'],
                        'permission_callback' => [$this, 'check_permission'],
                        'args'               => [
                            'topic_id' => [
                                'required'          => true,
                                'type'             => 'integer',
                                'sanitize_callback' => 'absint',
                                'description'       => __('The ID of the topic to duplicate the quiz to.', 'tutorpress'),
                            ],
                        ],
                    ],
                ]
            );

        } catch (Exception $e) {
            error_log('TutorPress Quiz Controller: Failed to register routes - ' . $e->getMessage());
        }
    }

    /**
     * Get quizzes for a topic.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_items($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $topic_id = $request->get_param('topic_id');

            // Validate topic
            $topic = get_post($topic_id);
            if (!$topic || $topic->post_type !== 'topics') {
                return new WP_Error(
                    'invalid_topic',
                    __('Invalid topic ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Get quizzes for the topic
            $quizzes = get_posts([
                'post_type'      => 'tutor_quiz',
                'post_parent'    => $topic_id,
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
                'post_status'    => ['publish', 'draft', 'private'],
            ]);

            // Format quizzes for response
            $formatted_quizzes = array_map(function($quiz) {
                $quiz_option = get_post_meta($quiz->ID, 'tutor_quiz_option', true);
                
                return [
                    'id'         => $quiz->ID,
                    'title'      => $quiz->post_title,
                    'content'    => $quiz->post_content,
                    'menu_order' => (int) $quiz->menu_order,
                    'status'     => $quiz->post_status,
                    'quiz_option' => $quiz_option ?: [],
                    'question_count' => $this->get_question_count($quiz->ID),
                ];
            }, $quizzes);

            return rest_ensure_response(
                $this->format_response(
                    $formatted_quizzes,
                    __('Quizzes retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'quizzes_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get a single quiz.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function get_item($request) {
        try {
            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $quiz_id = (int) $request->get_param('id');
            
            // Validate quiz
            $quiz = get_post($quiz_id);
            if (!$quiz || $quiz->post_type !== 'tutor_quiz') {
                return new WP_Error(
                    'invalid_quiz',
                    __('Invalid quiz ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Check if user can read this quiz
            if (!current_user_can('read_post', $quiz_id)) {
                return new WP_Error(
                    'cannot_read_quiz',
                    __('You do not have permission to read this quiz.', 'tutorpress'),
                    ['status' => 403]
                );
            }

            // Get quiz details
            $quiz_option = get_post_meta($quiz_id, 'tutor_quiz_option', true);
            $questions = $this->get_quiz_questions($quiz_id);

            $quiz_details = [
                'ID' => $quiz->ID,
                'post_title' => $quiz->post_title,
                'post_content' => $quiz->post_content,
                'post_status' => $quiz->post_status,
                'post_author' => $quiz->post_author,
                'post_parent' => $quiz->post_parent,
                'menu_order' => (int) $quiz->menu_order,
                'quiz_option' => $quiz_option ?: [],
                'questions' => $questions,
            ];

            return rest_ensure_response(
                $this->format_response(
                    $quiz_details,
                    __('Quiz retrieved successfully.', 'tutorpress')
                )
            );

        } catch (Exception $e) {
            return new WP_Error(
                'quiz_fetch_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Delete a quiz.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function delete_item($request) {
        try {
            global $wpdb;

            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $quiz_id = (int) $request->get_param('id');
            
            // Validate quiz
            $quiz = get_post($quiz_id);
            if (!$quiz || $quiz->post_type !== 'tutor_quiz') {
                return new WP_Error(
                    'invalid_quiz',
                    __('Invalid quiz ID.', 'tutorpress'),
                    ['status' => 404]
                );
            }

            // Check if user can delete this quiz
            if (!current_user_can('delete_post', $quiz_id)) {
                return new WP_Error(
                    'cannot_delete_quiz',
                    __('You do not have permission to delete this quiz.', 'tutorpress'),
                    ['status' => 403]
                );
            }

            // Fire before deletion hook
            do_action('tutorpress_quiz_before_delete', $quiz_id, $quiz);

            // Start transaction for cleanup
            $wpdb->query('START TRANSACTION');

            try {
                // COMPREHENSIVE DELETION - Following Tutor LMS patterns exactly
                
                // Step 1: Delete quiz attempts
                $wpdb->delete(
                    $wpdb->prefix . 'tutor_quiz_attempts',
                    ['quiz_id' => $quiz_id],
                    ['%d']
                );

                // Step 2: Delete quiz attempt answers
                $wpdb->delete(
                    $wpdb->prefix . 'tutor_quiz_attempt_answers',
                    ['quiz_id' => $quiz_id],
                    ['%d']
                );

                // Step 3: Get all question IDs for this quiz (CRITICAL STEP)
                $questions_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT question_id FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d",
                    $quiz_id
                ));

                // Step 4: Delete question answers using question IDs
                if (is_array($questions_ids) && count($questions_ids)) {
                    // Use WordPress prepare for safe IN clause
                    $placeholders = implode(',', array_fill(0, count($questions_ids), '%d'));
                    $wpdb->query(
                        $wpdb->prepare(
                            "DELETE FROM {$wpdb->prefix}tutor_quiz_question_answers WHERE belongs_question_id IN ($placeholders)",
                            $questions_ids
                        )
                    );
                }

                // Step 5: Delete quiz questions
                $wpdb->delete(
                    $wpdb->prefix . 'tutor_quiz_questions',
                    ['quiz_id' => $quiz_id],
                    ['%d']
                );

                // Step 6: Delete the quiz post
                $result = wp_delete_post($quiz_id, true);

                if (!$result) {
                    throw new Exception(__('Failed to delete quiz post.', 'tutorpress'));
                }

                // Commit transaction
                $wpdb->query('COMMIT');

                // Fire after deletion hooks
                do_action('tutorpress_quiz_deleted', $quiz_id, $quiz);
                do_action('tutor_delete_quiz_after', $quiz_id); // Compatible with Tutor LMS

                // Return success response (don't use 204 as it causes null responses with apiFetch)
                return rest_ensure_response(
                    $this->format_response(
                        null,
                        __('Quiz deleted successfully.', 'tutorpress')
                    )
                );

            } catch (Exception $e) {
                // Rollback transaction on error
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            return new WP_Error(
                'quiz_deletion_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Duplicate a quiz.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function duplicate_item($request) {
        try {
            global $wpdb;

            // Check Tutor LMS availability
            $tutor_check = $this->ensure_tutor_lms();
            if (is_wp_error($tutor_check)) {
                return $tutor_check;
            }

            $quiz_id = $request['id'];
            $topic_id = $request->get_param('topic_id');

            // Start transaction
            $wpdb->query('START TRANSACTION');

            try {
                // Get the source quiz
                $quiz = get_post($quiz_id);
                if (!$quiz || $quiz->post_type !== 'tutor_quiz') {
                    throw new Exception(__('Invalid quiz ID.', 'tutorpress'));
                }

                // Validate topic
                $topic = get_post($topic_id);
                if (!$topic || $topic->post_type !== 'topics') {
                    throw new Exception(__('Invalid topic ID.', 'tutorpress'));
                }

                // Get next menu order
                $next_order = $this->get_next_menu_order($topic_id, 'tutor_quiz');

                // Create new quiz
                $new_quiz_data = [
                    'post_type'    => 'tutor_quiz',
                    'post_title'   => $quiz->post_title . ' (Copy)',
                    'post_content' => $quiz->post_content,
                    'post_status'  => 'draft',
                    'post_author'  => get_current_user_id(),
                    'post_parent'  => $topic_id,
                    'menu_order'   => $next_order,
                ];

                $new_quiz_id = wp_insert_post($new_quiz_data);
                if (is_wp_error($new_quiz_id)) {
                    throw new Exception($new_quiz_id->get_error_message());
                }

                // Copy quiz meta data
                $quiz_option = get_post_meta($quiz_id, 'tutor_quiz_option', true);
                if ($quiz_option) {
                    update_post_meta($new_quiz_id, 'tutor_quiz_option', $quiz_option);
                }

                // Copy quiz questions and answers
                $this->duplicate_quiz_questions($quiz_id, $new_quiz_id);

                // Commit transaction
                $wpdb->query('COMMIT');

                // Get the new quiz details
                $new_quiz = get_post($new_quiz_id);
                $formatted_quiz = [
                    'id'         => $new_quiz->ID,
                    'title'      => $new_quiz->post_title,
                    'content'    => $new_quiz->post_content,
                    'menu_order' => (int) $new_quiz->menu_order,
                    'status'     => $new_quiz->post_status,
                    'quiz_option' => $quiz_option ?: [],
                    'question_count' => $this->get_question_count($new_quiz_id),
                ];

                return rest_ensure_response(
                    $this->format_response(
                        $formatted_quiz,
                        __('Quiz duplicated successfully.', 'tutorpress')
                    )
                );

            } catch (Exception $e) {
                // Rollback transaction on error
                $wpdb->query('ROLLBACK');
                throw $e;
            }

        } catch (Exception $e) {
            return new WP_Error(
                'quiz_duplication_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Get question count for a quiz.
     *
     * @since 1.0.0
     * @param int $quiz_id Quiz ID.
     * @return int Question count.
     */
    private function get_question_count($quiz_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d",
            $quiz_id
        ));
        
        return (int) $count;
    }

    /**
     * Get next menu order for a quiz in a topic.
     *
     * @since 1.0.0
     * @param int $topic_id Topic ID.
     * @param string $post_type Post type.
     * @return int Next menu order.
     */
    private function get_next_menu_order($topic_id, $post_type) {
        global $wpdb;
        
        // Get the highest menu_order for the topic and post type
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(menu_order) FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = %s",
            $topic_id,
            $post_type
        ));
        
        return (int) $max_order + 1;
    }

    /**
     * Get quiz questions with answers.
     *
     * @since 1.0.0
     * @param int $quiz_id Quiz ID.
     * @return array Questions with answers.
     */
    private function get_quiz_questions($quiz_id) {
        global $wpdb;
        
        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d ORDER BY question_order ASC",
            $quiz_id
        ));
        
        if (!$questions) {
            return [];
        }
        
        // Get answers for each question and convert data types for React
        foreach ($questions as &$question) {
            $answers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tutor_quiz_question_answers WHERE belongs_question_id = %d ORDER BY answer_order ASC",
                $question->question_id
            ));
            
            // Convert question properties to proper types for React
            $question->question_id = (int) $question->question_id;
            $question->quiz_id = (int) $question->quiz_id;
            $question->question_mark = (int) $question->question_mark;
            $question->question_order = (int) $question->question_order;
            
            // Fix multiple escaping issues by unslashing content fields
            $question->question_description = wp_unslash($question->question_description);
            $question->answer_explanation = wp_unslash($question->answer_explanation);
            $question->question_title = wp_unslash($question->question_title);
            
            // Parse and convert question_settings from serialized PHP data to structured object
            $question_settings = [];
            if (!empty($question->question_settings)) {
                $parsed_settings = unserialize($question->question_settings);
                if (is_array($parsed_settings)) {
                    $question_settings = $parsed_settings;
                }
            }
            
            // Create properly structured question_settings with boolean conversion
            $question->question_settings = [
                'question_type' => $question->question_type,
                'answer_required' => isset($question_settings['answer_required']) ? (bool) $question_settings['answer_required'] : true,
                'randomize_question' => isset($question_settings['randomize_question']) ? (bool) $question_settings['randomize_question'] : false,
                'question_mark' => (int) $question->question_mark,
                'show_question_mark' => isset($question_settings['show_question_mark']) ? (bool) $question_settings['show_question_mark'] : true,
                'has_multiple_correct_answer' => isset($question_settings['has_multiple_correct_answer']) ? (bool) $question_settings['has_multiple_correct_answer'] : false,
                'is_image_matching' => isset($question_settings['is_image_matching']) ? (bool) $question_settings['is_image_matching'] : false,
            ];
            
            // Convert answers to proper types
            foreach ($answers as &$answer) {
                $answer->answer_id = (int) $answer->answer_id;
                $answer->belongs_question_id = (int) $answer->belongs_question_id;
                $answer->image_id = (int) $answer->image_id;
                $answer->answer_order = (int) $answer->answer_order;
                
                // Fix multiple escaping issues by unslashing answer content
                $answer->answer_title = wp_unslash($answer->answer_title);
                
                // Populate image_url from WordPress media library if image_id exists
                if (!empty($answer->image_id) && $answer->image_id > 0) {
                    $image_url = wp_get_attachment_image_url($answer->image_id, 'full');
                    $answer->image_url = $image_url ? $image_url : '';
                } else {
                    $answer->image_url = '';
                }
            }
            
            $question->question_answers = $answers ?: [];
        }
        
        return $questions;
    }

    /**
     * Duplicate quiz questions and answers.
     *
     * @since 1.0.0
     * @param int $source_quiz_id Source quiz ID.
     * @param int $new_quiz_id New quiz ID.
     * @return void
     */
    private function duplicate_quiz_questions($source_quiz_id, $new_quiz_id) {
        global $wpdb;
        
        // Get source questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}tutor_quiz_questions WHERE quiz_id = %d ORDER BY question_order ASC",
            $source_quiz_id
        ));
        
        foreach ($questions as $question) {
            // Insert new question
            $new_question_data = [
                'quiz_id' => $new_quiz_id,
                'question_title' => $question->question_title,
                'question_description' => $question->question_description,
                'answer_explanation' => $question->answer_explanation,
                'question_type' => $question->question_type,
                'question_mark' => $question->question_mark,
                'question_settings' => $question->question_settings,
                'question_order' => $question->question_order,
            ];
            
            $wpdb->insert(
                $wpdb->prefix . 'tutor_quiz_questions',
                $new_question_data
            );
            
            $new_question_id = $wpdb->insert_id;
            
            // Get and duplicate answers
            $answers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}tutor_quiz_question_answers WHERE belongs_question_id = %d ORDER BY answer_order ASC",
                $question->question_id
            ));
            
            foreach ($answers as $answer) {
                $new_answer_data = [
                    'belongs_question_id' => $new_question_id,
                    'belongs_question_type' => $answer->belongs_question_type,
                    'answer_title' => $answer->answer_title,
                    'is_correct' => $answer->is_correct,
                    'image_id' => $answer->image_id,
                    'answer_two_gap_match' => $answer->answer_two_gap_match,
                    'answer_view_format' => $answer->answer_view_format,
                    'answer_settings' => $answer->answer_settings,
                    'answer_order' => $answer->answer_order,
                ];
                
                $wpdb->insert(
                    $wpdb->prefix . 'tutor_quiz_question_answers',
                    $new_answer_data
                );
            }
        }
    }

    /**
     * Handle quiz save hook.
     *
     * @since 1.0.0
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     * @param bool    $update  Whether this is an existing post being updated.
     * @return void
     */
    public static function handle_quiz_save($post_id, $post, $update) {
        // Add any quiz-specific save handling here if needed
        // This follows the same pattern as lessons and assignments
    }
} 