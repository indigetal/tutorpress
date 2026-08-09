<?php
/**
 * Frontend H5P listing overrides for Interactive Quizzes.
 *
 * Restores course contents and attempt answers when Tutor Pro's H5P addon filters
 * strip them, using WordPress H5P plugin availability (not edit_posts). Detectors
 * remain on TutorPress_Addon_Checker; this class owns filter registration only.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_H5P_Listing_Overrides {

	/**
	 * Per-request course-content snapshot taken before Pro H5P strip (priority 9).
	 * Overwritten on each tutor_filter_course_content invocation; not a cross-request cache.
	 *
	 * @var array|null
	 */
	private static $content_snapshot = null;

	/**
	 * Per-request attempt-answers snapshot taken before Pro H5P strip (priority 9).
	 * Overwritten on each tutor_filter_attempt_answers invocation; not a cross-request cache.
	 *
	 * @var array|null
	 */
	private static $answers_snapshot = null;

	/**
	 * Register WordPress init hook that attaches listing filters at priority 100.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'override_h5p_addon_filtering' ), 100 );
	}

	/**
	 * Override Tutor Pro H5P addon filtering to allow interactive quizzes
	 * to display in Tutor LMS frontend even when Tutor Pro H5P addon is disabled.
	 *
	 * Course-content restoration uses a priority-9 snapshot plus priority-15 policy
	 * keyed to WordPress H5P plugin availability (not edit_posts / feature access).
	 *
	 * @since 1.4.0
	 */
	public static function override_h5p_addon_filtering() {
		// Always register filters regardless of H5P plugin status
		// This allows us to both show content when H5P plugin is active AND hide content when H5P plugin is inactive

		// Priority 9: snapshot + annotate before Pro's priority-10 strip.
		// Priority 15: restore/exclude from snapshot based on WP H5P plugin only.
		add_filter( 'tutor_filter_course_content', array( __CLASS__, 'snapshot_h5p_quiz_content' ), 9, 1 );
		add_filter( 'tutor_filter_course_content', array( __CLASS__, 'allow_h5p_quiz_content' ), 15, 1 );
		add_filter( 'tutor_filter_lesson_sidebar', array( __CLASS__, 'allow_h5p_sidebar_contents' ), 15, 2 );
		add_filter( 'tutor_filter_attempt_answers', array( __CLASS__, 'snapshot_h5p_attempt_answers' ), 9, 1 );
		add_filter( 'tutor_filter_attempt_answers', array( __CLASS__, 'allow_h5p_attempt_answers' ), 15, 1 );

		// Add debug logging when WP_DEBUG is enabled
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			add_action( 'wp_footer', array( __CLASS__, 'debug_h5p_filtering' ) );
		}
	}

	/**
	 * Snapshot topic contents and annotate H5P quiz identity before Pro strips them.
	 *
	 * Reads tutor_quiz_option once per content post. Availability is independent of
	 * edit_posts; this hook only records identity for the later plugin-active policy.
	 *
	 * @since 2.2.0
	 * @param array $current_topic The topic array from Core.
	 * @return array Unchanged topic (Pro may strip after this runs).
	 */
	public static function snapshot_h5p_quiz_content( $current_topic ) {
		$contents = isset( $current_topic['contents'] ) && is_array( $current_topic['contents'] )
			? $current_topic['contents']
			: array();

		foreach ( $contents as $post ) {
			if ( ! is_object( $post ) || empty( $post->ID ) ) {
				continue;
			}
			$quiz_option = get_post_meta( $post->ID, 'tutor_quiz_option', true );
			if ( is_array( $quiz_option ) && isset( $quiz_option['quiz_type'] ) && 'tutor_h5p_quiz' === $quiz_option['quiz_type'] ) {
				$post->quiz_type = 'tutor_h5p_quiz';
			}
		}

		self::$content_snapshot = array(
			'id'       => isset( $current_topic['id'] ) ? $current_topic['id'] : null,
			'title'    => isset( $current_topic['title'] ) ? $current_topic['title'] : null,
			'summary'  => isset( $current_topic['summary'] ) ? $current_topic['summary'] : null,
			'contents' => $contents,
		);

		return $current_topic;
	}

	/**
	 * Pure course-content H5P listing policy (testable without live plugin state).
	 *
	 * When the WordPress H5P plugin is active, restore snapshot contents wholesale
	 * (including H5P quizzes). When inactive, exclude annotated H5P quizzes.
	 * Never merges with Pro-stripped input. Independent of edit_posts.
	 *
	 * @since 2.2.0
	 * @param array      $current_topic     Topic array after earlier filters.
	 * @param array|null $snapshot_topic    Priority-9 snapshot (or null).
	 * @param bool       $h5p_plugin_active Whether the WordPress H5P plugin is active.
	 * @return array
	 */
	public static function apply_h5p_quiz_content_policy( $current_topic, $snapshot_topic, $h5p_plugin_active ) {
		if ( ! is_array( $snapshot_topic ) || ! isset( $snapshot_topic['contents'] ) || ! is_array( $snapshot_topic['contents'] ) ) {
			return $current_topic;
		}

		if ( $h5p_plugin_active ) {
			$current_topic['contents'] = $snapshot_topic['contents'];
			return $current_topic;
		}

		$topic_contents = array();
		foreach ( $snapshot_topic['contents'] as $post ) {
			if ( is_object( $post ) && isset( $post->quiz_type ) && 'tutor_h5p_quiz' === $post->quiz_type ) {
				continue;
			}
			$topic_contents[] = $post;
		}
		$current_topic['contents'] = $topic_contents;

		return $current_topic;
	}

	/**
	 * Allow H5P quiz content in course display when the WordPress H5P plugin is active.
	 *
	 * Uses the priority-9 snapshot + plugin-active predicate. Does not use
	 * can_user_access_feature / edit_posts (learners must see listed Interactive Quizzes).
	 *
	 * @since 1.4.0
	 * @param array $current_topic The topic array.
	 * @return array
	 */
	public static function allow_h5p_quiz_content( $current_topic ) {
		return self::apply_h5p_quiz_content_policy(
			$current_topic,
			self::$content_snapshot,
			TutorPress_Addon_Checker::is_h5p_plugin_active()
		);
	}

	/**
	 * Allow H5P content in sidebar when the WordPress H5P plugin is active.
	 *
	 * Predicate is plugin-active only (not edit_posts / feature access). Rebuild and
	 * exclusion-query branches are otherwise unchanged.
	 *
	 * @since 1.4.0
	 * @param object $query The content query object.
	 * @param int    $topic_id The topic id.
	 * @return \WP_Query
	 */
	public static function allow_h5p_sidebar_contents( $query, $topic_id ) {
		$h5p_plugin_active = TutorPress_Addon_Checker::is_h5p_plugin_active();

		// If WP H5P plugin is active, recreate the original query (no H5P exclusion).
		if ( $h5p_plugin_active ) {
			$topics_id        = tutor_utils()->get_post_id( $topic_id );
			$lesson_post_type = tutor()->lesson_post_type;
			$post_type        = array_unique( apply_filters( 'tutor_course_contents_post_types', array( $lesson_post_type, 'tutor_quiz' ) ) );

			$args = array(
				'post_type'      => $post_type,
				'post_parent'    => $topics_id,
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				// No meta_query to exclude H5P quizzes - include everything
			);

			return new \WP_Query( $args );
		}

		// If H5P plugin is not active, filter out H5P quizzes
		$topics_id        = tutor_utils()->get_post_id( $topic_id );
		$lesson_post_type = tutor()->lesson_post_type;
		$post_type        = array_unique( apply_filters( 'tutor_course_contents_post_types', array( $lesson_post_type, 'tutor_quiz' ) ) );

		$args = array(
			'post_type'      => $post_type,
			'post_parent'    => $topics_id,
			'posts_per_page' => -1,
			'orderby'        => 'menu_order',
			'order'          => 'ASC',
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => 'tutor_quiz_option',
					'value'   => 's:9:"quiz_type";s:14:"tutor_h5p_quiz";',
					'compare' => 'NOT LIKE',
				),
				array(
					'key'     => 'tutor_quiz_option',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		return new \WP_Query( $args );
	}

	/**
	 * Snapshot attempt answers before Pro strips H5P question_type rows.
	 *
	 * Does not re-query or change access boundaries; caller already authorized the attempt.
	 *
	 * @since 2.2.0
	 * @param array $answers Attempt answers from Core.
	 * @return array Unchanged answers (Pro may strip after this runs).
	 */
	public static function snapshot_h5p_attempt_answers( $answers ) {
		self::$answers_snapshot = is_array( $answers ) ? $answers : array();
		return $answers;
	}

	/**
	 * Pure attempt-answers H5P listing policy (testable without live plugin state).
	 *
	 * Active: return the snapshot as stored (includes question_type=h5p). Inactive:
	 * key-preserving array_filter excluding h5p. Never uses array_values(). Never
	 * merges with Pro-stripped input. Independent of edit_posts.
	 *
	 * @since 2.2.0
	 * @param array      $answers           Answers after earlier filters.
	 * @param array|null $snapshot_answers  Priority-9 snapshot (or null).
	 * @param bool       $h5p_plugin_active Whether the WordPress H5P plugin is active.
	 * @return array
	 */
	public static function apply_h5p_attempt_answers_policy( $answers, $snapshot_answers, $h5p_plugin_active ) {
		if ( ! is_array( $snapshot_answers ) ) {
			return $answers;
		}

		if ( $h5p_plugin_active ) {
			return $snapshot_answers;
		}

		return array_filter(
			$snapshot_answers,
			function ( $answer ) {
				return ! ( is_object( $answer ) && isset( $answer->question_type ) && 'h5p' === $answer->question_type );
			}
		);
	}

	/**
	 * Allow H5P attempt answers when the WordPress H5P plugin is active.
	 *
	 * Uses the priority-9 snapshot + plugin-active predicate. Does not pass through
	 * Pro-stripped input and does not use can_user_access_feature / edit_posts.
	 *
	 * @since 1.4.0
	 * @param array $answers The attempt answers to filter.
	 * @return array
	 */
	public static function allow_h5p_attempt_answers( $answers ) {
		return self::apply_h5p_attempt_answers_policy(
			$answers,
			self::$answers_snapshot,
			TutorPress_Addon_Checker::is_h5p_plugin_active()
		);
	}

	/**
	 * Debug method to log H5P filtering status.
	 */
	public static function debug_h5p_filtering() {
		if ( ! is_user_logged_in() || ! current_user_can( 'administrator' ) ) {
			return;
		}

		// Course-content listing override is gated by WP H5P plugin only (not feature-access).
		$h5p_plugin_active = TutorPress_Addon_Checker::is_h5p_plugin_active();

		echo '<script>
            console.log("TutorPress H5P Filtering Debug:");
            console.log("- Listing override (WP H5P plugin):", ' . ( $h5p_plugin_active ? 'true' : 'false' ) . ');
            console.log("- Tutor Pro H5P Addon Enabled:", ' . ( TutorPress_Addon_Checker::is_h5p_enabled() ? 'true' : 'false' ) . ');
            console.log("- Current Filters:", {
                "tutor_filter_course_content": ' . ( has_filter( 'tutor_filter_course_content' ) ? 'true' : 'false' ) . ',
                "tutor_filter_lesson_sidebar": ' . ( has_filter( 'tutor_filter_lesson_sidebar' ) ? 'true' : 'false' ) . ',
                "tutor_filter_attempt_answers": ' . ( has_filter( 'tutor_filter_attempt_answers' ) ? 'true' : 'false' ) . '
            });
        </script>';
	}
}
