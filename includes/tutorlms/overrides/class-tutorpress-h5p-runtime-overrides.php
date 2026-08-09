<?php
/**
 * Frontend H5P runtime overrides for Interactive Quiz take-quiz rendering.
 *
 * Registers learner render hooks when the WordPress H5P plugin is active and the
 * Tutor Pro H5P runtime is absent (Pro does not construct Quiz/Assets). Detectors
 * remain on TutorPress_Addon_Checker; this class owns runtime hook registration.
 *
 * Phase 1: medium render bridge only (template, description, desc_render, hidden
 * inputs). Does not claim quiz.js, xAPI, required-answer AJAX, or marks.
 *
 * @package TutorPress
 * @since 2.2.0
 */

defined( 'ABSPATH' ) || exit;

class TutorPress_H5P_Runtime_Overrides {

	/**
	 * Armed flag for the current modern question-render window (D4 A′).
	 *
	 * When armed, front-end `post`-context `wp_kses_allowed_html` may merge the
	 * H5P iframe allowlist until after-answers cleanup or a hard-exclude hit.
	 *
	 * @var bool
	 */
	private static $h5p_iframe_kses_armed = false;

	/**
	 * Register WordPress init hook that attaches runtime filters at priority 100.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_runtime_hooks' ), 100 );
	}

	/**
	 * Arm the H5P iframe kses flag (fixtures and live R1-B wrapper only).
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function arm_h5p_iframe_kses() {
		self::$h5p_iframe_kses_armed = true;
	}

	/**
	 * Disarm the H5P iframe kses flag.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function disarm_h5p_iframe_kses() {
		self::$h5p_iframe_kses_armed = false;
	}

	/**
	 * Whether the H5P iframe kses flag is currently armed.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	public static function is_h5p_iframe_kses_armed() {
		return (bool) self::$h5p_iframe_kses_armed;
	}

	/**
	 * Merge Decision D2 H5P iframe attrs into a kses tags array (merge-not-replace).
	 *
	 * Attribute keys are lowercase (kses canonical). Existing iframe attrs from
	 * other plugins are preserved.
	 *
	 * @since 2.2.0
	 * @param array $tags Allowed HTML tags map from wp_kses_allowed_html.
	 * @return array
	 */
	public static function merge_h5p_iframe_allowed_html( $tags ) {
		if ( ! is_array( $tags ) ) {
			$tags = array();
		}

		$h5p_iframe_attrs = array(
			'id'              => true,
			'class'           => true,
			'data-content-id' => true,
			'style'           => true,
			'src'             => true,
			'frameborder'     => true,
			'scrolling'       => true,
			'title'           => true,
		);

		if ( isset( $tags['iframe'] ) && is_array( $tags['iframe'] ) ) {
			$tags['iframe'] = array_merge( $tags['iframe'], $h5p_iframe_attrs );
		} else {
			$tags['iframe'] = $h5p_iframe_attrs;
		}

		return $tags;
	}

	/**
	 * Whether the current request must never receive the H5P iframe allowlist merge.
	 *
	 * Hard excludes (D4): admin, REST_REQUEST, AJAX, and content-save filters.
	 *
	 * @since 2.2.0
	 * @return bool
	 */
	private static function is_h5p_iframe_kses_hard_excluded() {
		if ( is_admin() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( wp_doing_ajax() ) {
			return true;
		}

		if (
			doing_filter( 'content_save_pre' )
			|| doing_filter( 'excerpt_save_pre' )
			|| doing_filter( 'title_save_pre' )
			|| doing_filter( 'wp_insert_post_data' )
		) {
			return true;
		}

		return false;
	}

	/**
	 * Merge minimal H5P iframe attrs into wp_kses_allowed_html for the armed window.
	 *
	 * Decision D4 A′: merge when context is `post`, the flag is armed, and hard
	 * excludes are clear (not is_admin(), not REST_REQUEST, not wp_doing_ajax(),
	 * not content-save filters). Ordinary armed `post` merges remain armed so
	 * Core's per-tag allowlist resolve can keep sibling tags (wrapper div +
	 * iframe) in the same wp_kses_post. Armed + hard-exclude hits return $tags
	 * unchanged and disarm immediately. Non-`post` contexts return $tags
	 * unchanged and do not disarm. Live cleanup is
	 * clear_h5p_iframe_kses_after_question on tutor_quiz_question_after_answers
	 * @999 (modern learning-area/quiz/question.php only).
	 *
	 * Decision D5 residual surface: while armed, any wp_kses_post / post-context
	 * kses during the current modern question-render window can retain D2 iframe
	 * attrs — not provenance-scoped and not request-wide forever with no cleanup.
	 * No script allow; no event-handler attrs; no allow/allowfullscreen/sandbox
	 * unless replanned.
	 *
	 * @since 2.2.0
	 * @param array  $tags    Allowed HTML tags.
	 * @param string $context Kses context (e.g. 'post').
	 * @return array
	 */
	public static function allow_h5p_iframe_html( $tags, $context ) {
		if ( 'post' !== $context ) {
			return $tags;
		}

		if ( ! self::is_h5p_iframe_kses_armed() ) {
			return $tags;
		}

		if ( self::is_h5p_iframe_kses_hard_excluded() ) {
			self::disarm_h5p_iframe_kses();
			return $tags;
		}

		// Remain armed so sibling tags in the same wp_kses_post still see the allowlist.
		return self::merge_h5p_iframe_allowed_html( $tags );
	}

	/**
	 * Disarm the H5P iframe kses flag after modern question answers render (D4 A′).
	 *
	 * Bound to tutor_quiz_question_after_answers on the modern learning-area
	 * question template path. Body only clears the armed flag.
	 *
	 * @since 2.2.0
	 * @param mixed $quiz          Quiz post object from Core (unused).
	 * @param mixed $quiz_settings Quiz settings array (unused).
	 * @param mixed $question      Question object (unused).
	 * @return void
	 */
	public static function clear_h5p_iframe_kses_after_question( $quiz = null, $quiz_settings = null, $question = null ) {
		self::disarm_h5p_iframe_kses();
	}

	/**
	 * Whether TutorPress should register Phase 1 learner runtime hooks.
	 *
	 * True only when WP H5P is on and Pro H5P runtime is absent. Pure helper for
	 * fixtures; live registration passes Addon Checker detectors.
	 *
	 * @since 2.2.0
	 * @param bool $h5p_plugin_active     WordPress H5P plugin active.
	 * @param bool $pro_h5p_runtime_active Pro H5P runtime present (addon ∧ WP H5P).
	 * @return bool
	 */
	public static function should_register_runtime_hooks( $h5p_plugin_active, $pro_h5p_runtime_active ) {
		return (bool) $h5p_plugin_active && ! (bool) $pro_h5p_runtime_active;
	}

	/**
	 * Attach Phase 1 runtime hooks when the registration predicate passes.
	 *
	 * Kses allowlist + after-question cleanup register only when the predicate
	 * passes and tutor()->has_pro is false (R1-B / D4 A′). Other Phase 1 hooks
	 * remain predicate-only so R1-A can still run when has_pro is true.
	 *
	 * @since 2.2.0
	 * @return void
	 */
	public static function register_runtime_hooks() {
		$h5p_plugin_active = TutorPress_Addon_Checker::is_h5p_plugin_active();
		// Mirrors TutorPro\H5P\H5P::is_enabled(): Pro addon enabled AND WP H5P active.
		$pro_h5p_runtime_active = TutorPress_Addon_Checker::is_h5p_enabled() && $h5p_plugin_active;

		if ( ! self::should_register_runtime_hooks( $h5p_plugin_active, $pro_h5p_runtime_active ) ) {
			return;
		}

		add_filter( 'tutor_filter_quiz_question_template', array( __CLASS__, 'filter_question_template' ), 10, 2 );
		add_filter( 'tutor_filter_quiz_question_description', array( __CLASS__, 'allow_h5p_question_description' ), 12, 1 );
		add_action( 'tutor_quiz_question_desc_render', array( __CLASS__, 'render_question_description' ), 10, 2 );
		add_action( 'tutor_quiz_question_after_answers', array( __CLASS__, 'register_h5p_question_input_field' ), 10, 3 );
		add_action( 'tutor_require_question_answer_file', array( __CLASS__, 'require_h5p_answer_file' ), 10, 3 );

		// Use boolean property access — Config exposes has_pro via __get without __isset,
		// so empty()/isset() are unreliable (Core uses tutor()->has_pro directly).
		$has_pro = function_exists( 'tutor' ) && tutor() && (bool) tutor()->has_pro;
		if ( ! $has_pro ) {
			add_filter( 'wp_kses_allowed_html', array( __CLASS__, 'allow_h5p_iframe_html' ), 10, 2 );
			add_action( 'tutor_quiz_question_after_answers', array( __CLASS__, 'clear_h5p_iframe_kses_after_question' ), 999, 3 );
		}
	}

	/**
	 * Blank the modern H5P question template path so Core skips missing h5p.php.
	 *
	 * Mirrors Tutor Pro Quiz::filter_quiz_question_template(): empty string for h5p
	 * only; all other question types passthrough unchanged.
	 *
	 * @since 2.2.0
	 * @param string $template      Template path from Core.
	 * @param string $question_type Question type slug.
	 * @return string
	 */
	public static function filter_question_template( $template, $question_type ) {
		if ( 'h5p' === $question_type ) {
			return '';
		}

		return $template;
	}

	/**
	 * Live wrapper for tutor_filter_quiz_question_description.
	 *
	 * Passes Core's one-arg description through the R1/S1 helper with tutor()->has_pro.
	 * Arms the H5P iframe kses flag only for R1-B expanded HTML (!has_pro and
	 * returned string differs from input). Does not arm for R1-A or unchanged
	 * descriptions (D4 A′ arm ownership).
	 *
	 * @since 2.2.0
	 * @param string $description Question description from Core.
	 * @return string
	 */
	public static function allow_h5p_question_description( $description ) {
		$description = (string) $description;
		// Boolean property access — see register_runtime_hooks (empty()/isset() unreliable).
		$has_pro     = function_exists( 'tutor' ) && tutor() && (bool) tutor()->has_pro;
		$result      = self::filter_question_description( $description, (bool) $has_pro );

		if ( ! $has_pro && $result !== $description ) {
			self::arm_h5p_iframe_kses();
		}

		return $result;
	}

	/**
	 * Transform H5P question descriptions per Decision R1 + S1.
	 *
	 * Uses the WordPress H5P plugin get_content() gate (same as Pro Quiz.php:312-318).
	 * Core passes only the description string — no question_type — so a numeric
	 * description that is a valid H5P content ID on a non-H5P question will also
	 * transform. That is accepted Phase 1 Pro-parity behavior, not a TutorPress
	 * regression vs Pro.
	 *
	 * Contracts:
	 * - get_content() not an array → description unchanged (S1).
	 * - has_pro true → shortcode string only `[h5p id=<id>]` (R1-A; no HTML expand).
	 * - has_pro false → do_shortcode() expanded HTML (R1-B).
	 *
	 * @since 2.2.0
	 * @param string $description Question description (often an H5P content ID).
	 * @param bool   $has_pro     Whether tutor()->has_pro is true (injected for tests).
	 * @return string
	 */
	public static function filter_question_description( $description, $has_pro ) {
		$description = (string) $description;

		$content = null;
		if ( class_exists( 'H5P_Plugin' ) ) {
			$plugin = \H5P_Plugin::get_instance();
			if ( $plugin && is_callable( array( $plugin, 'get_content' ) ) ) {
				$content = $plugin->get_content( $description );
			}
		}

		if ( ! is_array( $content ) ) {
			return $description;
		}

		// Validated H5P content IDs are numeric; sanitize before shortcode emission.
		$content_id     = absint( $description );
		$h5p_short_code = '[h5p id=' . $content_id . ']';

		if ( $has_pro ) {
			return $h5p_short_code;
		}

		return do_shortcode( $h5p_short_code );
	}

	/**
	 * Emit H5P HTML once for Core's has_pro description branch (Decision R1-A).
	 *
	 * Runs do_shortcode on the Core-built markup and echoes exactly once. The
	 * description filter must not also expand/echo on this branch.
	 *
	 * @since 2.2.0
	 * @param string $markup   Core wrapper markup containing the shortcode.
	 * @param mixed  $question Question object from Core (unused; signature match).
	 * @return void
	 */
	public static function render_question_description( $markup, $question = null ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_shortcode expands H5P plugin HTML inside Core's wrapper; escaping would destroy the embed.
		echo do_shortcode( (string) $markup );
	}

	/**
	 * Register modern hidden H5P question input (Pro Quiz.php:238-264).
	 *
	 * Returns early in legacy learning mode (Decision C1). Requires
	 * quiz_type=tutor_h5p_quiz.
	 *
	 * @since 2.2.0
	 * @param mixed $quiz          Quiz post object from Core.
	 * @param mixed $quiz_settings Quiz settings array.
	 * @param mixed $question      Question object.
	 * @return void
	 */
	public static function register_h5p_question_input_field( $quiz, $quiz_settings, $question ) {
		if ( function_exists( 'tutor_utils' ) && tutor_utils() && tutor_utils()->is_legacy_learning_mode() ) {
			return;
		}

		if ( ! is_array( $quiz_settings ) || ! isset( $quiz_settings['quiz_type'] ) || 'tutor_h5p_quiz' !== $quiz_settings['quiz_type'] ) {
			return;
		}

		if ( ! is_object( $question ) || empty( $question->question_id ) ) {
			return;
		}

		global $tutor_is_started_quiz;

		$attempt_id  = (int) ( is_object( $tutor_is_started_quiz ) ? ( $tutor_is_started_quiz->attempt_id ?? 0 ) : 0 );
		$question_id = absint( $question->question_id );
		$field_name  = sprintf( 'attempt[%d][quiz_question][%d]', $attempt_id, $question_id );
		$register_attr = "register('{$field_name}')";
		?>
		<input
			class="tutor-hidden"
			type="radio"
			name="<?php echo esc_attr( $field_name ); ?>"
			value="<?php echo esc_attr( '' ); ?>"
			x-bind="<?php echo esc_attr( $register_attr ); ?>"
		>
		<?php
	}

	/**
	 * Emit hidden H5P answer area for question_type=h5p (Pro Quiz.php:405-412).
	 *
	 * @since 2.2.0
	 * @param string $question_type    Question type slug.
	 * @param mixed  $is_started_quiz  Started attempt object.
	 * @param mixed  $question         Question object.
	 * @return void
	 */
	public static function require_h5p_answer_file( $question_type, $is_started_quiz, $question ) {
		if ( 'h5p' !== $question_type ) {
			return;
		}

		if ( ! is_object( $is_started_quiz ) || ! is_object( $question ) ) {
			return;
		}

		$attempt_id  = absint( $is_started_quiz->attempt_id ?? 0 );
		$question_id = absint( $question->question_id ?? 0 );
		$field_name  = 'attempt[' . $attempt_id . '][quiz_question][' . $question_id . '][]';
		?>
		<div id="quiz-matching-ans-area" hidden class="quiz-question-ans-choice-area tutor-mt-40 question-type-<?php echo esc_attr( $question_type ); ?>">
			<input class="" name="<?php echo esc_attr( $field_name ); ?>" />
		</div>
		<?php
	}
}
