<?php
/**
 * Manage Course Related Logic
 *
 * @package Tutor
 * @author indigetal <support@indigetal.com>
 * @link https://indigetal.com
 * @since 2.7.9
 */

namespace TUTOR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use TUTOR\Input;
use Tutor\Models\CourseModel;

/**
 * Course Class
 *
 * @since 2.7.9
 */
class Course extends Tutor_Base {

	/**
	 * Additional course meta info
	 *
	 * @var array
	 */
	private $additional_meta = array(
		'_tutor_enable_qa',
		'_tutor_is_public_course',
	);

	/**
	 * Constructor
	 *
	 * @since 2.7.9
	 * @return void
	 */
	public function __construct() {
		parent::__construct();

		// Load Assets for Gutenberg Editor Sidebar
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_assets' ) );

        // Add course level to course settings
		add_filter( 'tutor_course_settings_tabs', array( $this, 'add_course_level_to_settings' ) );

        // Ensure Gutenberg Supports Author Assignment
		add_filter( 'wp_insert_post_data', array( $this, 'tutor_add_gutenberg_author' ), '99', 2 );

		// Ensure course metadata saves correctly
		add_action( 'save_post_' . $this->course_post_type, array( $this, 'save_course_meta' ), 10, 2 );

        // Check if the course is starting and set metadata if applicable
        add_action( 'tutor_lesson_load_before', array( $this, 'tutor_lesson_load_before' ) );

		// Enable Disable Course Details Page Feature
		$this->course_elements_enable_disable();

		// Manage Enrollment & Completion (Frontend Action)
		add_action( 'template_redirect', array( $this, 'enroll_now' ) );
		add_action( 'init', array( $this, 'mark_course_complete' ) );

        // Enroll the user after login if they attempted to enroll as a guest.
        add_action( 'tutor_do_enroll_after_login_if_attempt', array( $this, 'enroll_after_login_if_attempt' ), 10, 2 );

        add_filter( 'tutor_enroll_required_login_class', array( $this, 'add_enroll_required_login_class' ) );

		// Remove the course price if enrolled
		add_filter( 'tutor_course_price', array( $this, 'remove_price_if_enrolled' ) );
		
		// Restrict new enroll/purchase button if course member limit reached
		add_filter( 'tutor_course_restrict_new_entry', array( $this, 'restrict_new_student_entry' ) );

		// Restrict media library access to only show the instructor's own uploads (replaces restrict_media)
        add_action( 'pre_get_posts', array( $this, 'filter_media_library_query' ) );

        // Remove course complete button if course completion is strict mode
		add_filter( 'tutor_course/single/complete_form', array( $this, 'tutor_lms_hide_course_complete_btn' ) );
		add_filter( 'get_gradebook_generate_form_html', array( $this, 'get_generate_greadbook' ) );

        /*
        * Course Builder Metabox(?)
        */
		add_action( 'wp_ajax_tutor_save_topic', array( $this, 'tutor_save_topic' ) );
		add_action( 'wp_ajax_tutor_delete_topic', array( $this, 'tutor_delete_topic' ) );
		add_action( 'wp_ajax_tutor_update_course_content_order', array( $this, 'tutor_update_course_content_order' ) );

        add_action( 'wp_ajax_tutor_course_enrollment', array( $this, 'course_enrollment' ) );

        // Backend Columns (Keep for List View)
		add_filter( "manage_{$this->course_post_type}_posts_columns", array( $this, 'add_column' ), 10, 1 );
		add_action( "manage_{$this->course_post_type}_posts_custom_column", array( $this, 'custom_lesson_column' ), 10, 2 );
		
        /**
		 * Frontend Dashboard
         * (To be moved to REST API later)
		 */
        add_action( 'wp_ajax_tutor_delete_dashboard_course', array( $this, 'tutor_delete_dashboard_course' ) );
        // Frontend metabox supports for course builder
		add_action( 'tutor/dashboard_course_builder_form_field_after', array( $this, 'register_meta_box_in_frontend' ) );

        // Do Stuff for the course save from frontend
		add_action( 'save_tutor_course', array( $this, 'attach_product_with_course' ), 10, 2 );
		
        // Reset course progress on retake
        add_action( 'wp_ajax_tutor_reset_course_progress', array( $this, 'tutor_reset_course_progress' ) );

        // Delete course data when a course is deleted
		add_action( 'before_delete_post', array( $this, 'delete_associated_enrollment' ) );
		add_action( 'deleted_post', array( new CourseModel(), 'delete_course_data' ) );

        // After trash a course redirect to course list page
        add_action( 'trashed_post', __CLASS__ . '::redirect_to_course_list_page' );

        // Remove wp trash button if instructor settings is disabled
        add_action( 'tutor_option_save_after', array( $this, 'disable_course_trash_instructor' ) );

        add_action( 'wp_ajax_tutor_get_wc_product', array( $this, 'tutor_get_wc_product' ) );
		
        // Filter product in shop page
        $this->filter_product_in_shop_page();

		//Popup for review
		add_action( 'wp_footer', array( $this, 'popup_review_form' ) );
		add_action( 'wp_ajax_tutor_clear_review_popup_data', array( $this, 'clear_review_popup_data' ) );

        // Add social share content in header
        add_action( 'wp_head', array( $this, 'social_share_content' ) );

	}

    /**
     * Enqueue assets for Gutenberg editor and frontend course builder.
     */
    public function enqueue_assets() {
        $post = get_post();
        
        if ($post && get_post_type($post) === 'courses') {
            $asset_path = plugin_dir_path(__FILE__) . '../assets/core-wp/';
            $asset_url  = plugin_dir_url(__FILE__) . '../assets/core-wp/';

            $scripts = [
                'course-settings'     => 'course-settings.js',
                'course-builder'      => 'course-builder.js',
                'course-enrollments'  => 'course-enrollments.js',
                'course-instructors'  => 'course-instructors.js',
                'course-monetization' => 'course-monetization.js',
                'frontend-builder' => 'frontend-builder.js',
            ];

            foreach ($scripts as $handle => $file) {
                wp_enqueue_script(
                    $handle,
                    $asset_url . $file,
                    ['wp-edit-post', 'wp-components', 'wp-data', 'wp-hooks', 'wp-i18n'],
                    filemtime($asset_path . $file),
                    true
                );
            }

            // Enqueue Editor-Specific Styles
            wp_enqueue_style(
                'gutenberg-ui',
                plugin_dir_url(__FILE__) . '../assets/css/gutenberg-ui.css',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/css/gutenberg-ui.css')
            );
        }

        /**
         * save_course_meta moved to REST API: See Course_REST_Controller.
         */

        // Enqueue frontend course builder assets when viewing a course on the frontend
        if (!is_admin() && is_singular('courses')) {
            wp_enqueue_script(
                'tutor-frontend-course-builder',
                $asset_url . 'frontend-course-builder.js',
                ['wp-api-fetch'],
                filemtime($asset_path . 'frontend-course-builder.js'),
                true
            );

            wp_enqueue_style(
                'tutor-frontend-builder-style',
                plugin_dir_url(__FILE__) . '../assets/css/frontend-course-builder.css',
                [],
                filemtime(plugin_dir_path(__FILE__) . '../assets/css/frontend-course-builder.css')
            );
        }
    }

    /**
     * Add "enroll required login" CSS class if native login is enabled.
     *
     * @param string $class_name Existing CSS class name.
     *
     * @return string Filtered class name.
     */
    public function add_enroll_required_login_class( $class_name ) {
        $enabled_tutor_login = get_option( 'enable_tutor_native_login', false );
    
        if ( ! $enabled_tutor_login ) {
            return '';
        }
    
        return $class_name;
    }

    /**
     * Restrict New Student Entry
     */
    public function restrict_new_student_entry( $content ) {
        // Get Course ID
        $course_id = get_the_ID();

        // Fetch Fully Booked Status from REST API
        $response = wp_remote_get(rest_url("tutorpress/v1/course-availability/$course_id"));
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // If the course is fully booked, display the message
        if (!empty($data['fully_booked'])) {
            return '<div class="list-item-booking booking-full tutor-d-flex tutor-align-center">
                        <div class="booking-progress tutor-d-flex">
                            <span class="tutor-mr-8 tutor-color-warning tutor-icon-circle-info"></span>
                        </div>
                        <div class="tutor-fs-7 tutor-fw-medium">' .
                        __('Fully Booked', 'tutor') .
                        '</div>
                    </div>';
        }

        return $content;
    }

    /**
     * Restrict media library access to only show an instructor's own uploads (replaces restrict_media)
     *
     * @param WP_Query $query The query object.
     */
    public function filter_media_library_query( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() || $query->get('post_type') !== 'attachment' ) {
            return;
        }

        // Check if the request is for the media library
        if ( wp_doing_ajax() && isset($_POST['action']) && $_POST['action'] === 'query-attachments' ) {
            if ( current_user_can( 'tutor_instructor' ) && ! current_user_can( 'manage_options' ) ) {
                $query->set( 'author', get_current_user_id() ); // Restrict to current instructor
            }
        }
    }

    /**
     * Remove move to trash button on WordPress editor for instructors.
     *
     * Prevents instructors from deleting courses if the option is disabled.
     *
     * @return void
     * TODO: Ensure `disable_course_trash_instructor()` runs when the setting is updated via API.
     * - Changes to `tutor_instructor_can_delete_course` should always be reflected in the API.
     */
    public function disable_course_trash_instructor() {
        $can_trash_post = get_option( 'tutor_instructor_can_delete_course', false );
        $instructor_role = apply_filters( 'tutor_instructor_role', tutor()->instructor_role ?? null );

        if ( ! $instructor_role ) {
            return; // Role not defined, exit early.
        }

        $role = get_role( $instructor_role );

        if ( ! $role ) {
            return; // Role does not exist, exit early.
        }

        $current_cap = $role->has_cap( 'delete_tutor_courses' );

        // Only update capabilities if needed
        if ( $can_trash_post && ! $current_cap ) {
            $role->add_cap( 'delete_tutor_courses' );
            $role->add_cap( 'delete_tutor_course' );
        } elseif ( ! $can_trash_post && $current_cap ) {
            $role->remove_cap( 'delete_tutor_courses' );
            $role->remove_cap( 'delete_tutor_course' );
        }
    }

    /**
     * Register Additional Data Metabox Below the Editor Canvas
     *
     * @since 2.7.9
     * @return void
     */
    public function register_additional_data() {
        $course_post_type = tutor()->course_post_type;
    
        tutor_meta_box_wrapper(
            'tutor-course-additional-data',
            __( 'Additional Data', 'tutor' ),
            array( $this, 'render_additional_data_metabox' ),
            $course_post_type,
            'advanced',
            'default',
            'tutor-admin-post-meta'
        );
    }

    /**
     * Render Additional Data Metabox (Replaces course-additional-data.php)
     *
     * @since 2.7.9
     * @return void
     */
    public function render_additional_data_metabox() {
        $course_id = get_the_ID();

        if ( get_post_type( $course_id ) !== tutor()->course_post_type ) {
            die( __( 'Invalid post type', 'tutor' ) );
        }

        // Fetch all course meta fields
        $course_meta = array(
            'benefits'          => get_post_meta( $course_id, '_tutor_course_benefits', true ),
            'target_audience'   => get_post_meta( $course_id, '_tutor_course_target_audience', true ),
            'material_includes' => get_post_meta( $course_id, '_tutor_course_material_includes', true ),
            'requirements'      => get_post_meta( $course_id, '_tutor_course_requirements', true ),
        );

        // Fetch Course Duration Meta (Hours & Minutes)
        // Determine how tutor_utils()->get_course_duration() actually retrieves the duration (likely from post meta)
        $duration_meta = get_post_meta( $course_id, '_tutor_course_duration', true );
        $duration_parts = explode( ':', $duration_meta ); // Assuming HH:MM format

        $course_meta['duration'] = array(
            'hours'   => isset( $duration_parts[0] ) ? intval( $duration_parts[0] ) : 0,
            'minutes' => isset( $duration_parts[1] ) ? intval( $duration_parts[1] ) : 0,
        );
        // Replace course-additional-data.php with inline rendering
        ?>

        <div class="tutor-mb-32">
            <label class="tutor-fs-6 tutor-fw-medium tutor-color-black">
                <?php esc_html_e( 'What Will I Learn?', 'tutor' ); ?>
            </label>
            <textarea class="tutor-form-control tutor-form-control-auto-height tutor-mt-12" name="course_benefits" rows="2"><?php 
                echo esc_textarea( $course_meta['benefits'] ); 
            ?></textarea>
        </div>

        <div class="tutor-mb-32">
            <label class="tutor-fs-6 tutor-fw-medium tutor-color-black">
                <?php esc_html_e( 'Targeted Audience', 'tutor' ); ?>
            </label>
            <textarea class="tutor-form-control tutor-form-control-auto-height tutor-mt-12" name="course_target_audience" rows="2"><?php 
                echo esc_textarea( $course_meta['target_audience'] ); 
            ?></textarea>
        </div>

        <div class="tutor-row tutor-mb-32">
            <div class="tutor-col-12 tutor-mb-12">
                <label class="tutor-fs-6 tutor-fw-medium tutor-color-black"><?php esc_html_e( 'Total Course Duration', 'tutor' ); ?></label>
            </div>
            <div class="tutor-col-6 tutor-col-sm-4 tutor-col-md-3">
                <input class="tutor-form-control tutor-mb-5" type="number" min="0" value="<?php echo esc_attr( $course_meta['duration']['hours'] ); ?>" name="course_duration[hours]">
                <span class="tutor-fs-7 tutor-color-muted"><?php _e( 'Hours', 'tutor' ); ?></span>
            </div>
            <div class="tutor-col-6 tutor-col-sm-4 tutor-col-md-3">
                <input class="tutor-form-control tutor-mb-4 tutor-number-validation" type="number" min="0" max="59" value="<?php echo esc_attr( $course_meta['duration']['minutes'] ); ?>" name="course_duration[minutes]">
                <span class="tutor-fs-7 tutor-color-muted"><?php esc_html_e( 'Minutes', 'tutor' ); ?></span>
            </div>
        </div>

        <div class="tutor-mb-32">
            <label class="tutor-fs-6 tutor-fw-medium tutor-color-black">
                <?php esc_html_e( 'Materials Included', 'tutor' ); ?>
            </label>
            <textarea class="tutor-form-control tutor-form-control-auto-height tutor-mt-12" name="course_material_includes" rows="5"><?php 
                echo esc_textarea( $course_meta['material_includes'] ); 
            ?></textarea>
        </div>

        <div class="tutor-mb-32">
            <label class="tutor-fs-6 tutor-fw-medium tutor-color-black">
                <?php esc_html_e( 'Requirements/Instructions', 'tutor' ); ?>
            </label>
            <textarea class="tutor-form-control tutor-form-control-auto-height tutor-mt-12" name="course_requirements" rows="2"><?php 
                echo esc_textarea( $course_meta['requirements'] ); 
            ?></textarea>
        </div>

        <?php
    }

  /**
   * Handles updating the order of course content (topics, lessons, quizzes).
   * Called via AJAX from tutor-course-builder.js.
   */
	public function tutor_update_course_content_order() {
		tutor_utils()->checking_nonce();

		if ( Input::has( 'content_parent' ) ) {
			$content_parent = Input::post( 'content_parent', array(), Input::TYPE_ARRAY );
			$topic_id       = tutor_utils()->array_get( 'parent_topic_id', $content_parent );
			$content_id     = tutor_utils()->array_get( 'content_id', $content_parent );

			if ( ! tutor_utils()->can_user_manage( 'topic', $topic_id ) ) {
				wp_send_json_success( array( 'message' => __( 'Access Denied!', 'tutor' ) ) );
				exit;
			}

			// Update the parent topic id of the content.
			global $wpdb;
			$wpdb->update( $wpdb->posts, array( 'post_parent' => $topic_id ), array( 'ID' => $content_id ) );
		}

		// Save course content order.
		$this->save_course_content_order();

		wp_send_json_success();
	}

    /**
     * Course meta box (Topics)
     *
     * Loads the course builder metabox template. This will remain
     * until the course builder is fully migrated to a dedicated
     * Gutenberg panel.
     *
     * @since 1.0.0
     * @param boolean $echo display or not.
     * @return string
     */
    public function course_meta_box( $echo = true ) {
        // TODO: Review and refactor when course builder is moved to Gutenberg.
        $file_path = tutor()->path . 'views/metabox/course-topics.php';

        if ( $echo ) {
            include $file_path;
        } else {
            ob_start();
            include $file_path;
            return ob_get_clean();
        }
    }

   /**
     * Register the course topics section in the frontend course builder.
     *
     * @since 1.3.4
     * 
     * This function is responsible for loading the course topics management interface 
     * in the frontend course builder. It includes the `course-topics.php` template, 
     * which handles:
     * 
     * - Displaying the course content builder (topics, lessons, quizzes).
     * - Rendering a button to add new topics.
     * - Providing modal dialogs for adding/editing topics, lessons, quizzes, and assignments.
     *
     * Unlike `register_frontend_course_builder()`, which initializes the modern 
     * frontend course builder UI, this function maintains compatibility with the 
     * existing Tutor LMS course topics interface.
     *
     * @return void
     */ 
    public function register_meta_box_in_frontend() {
        global $post;

        do_action('tutor_course_builder_metabox_before', get_the_ID());

        include tutor()->path . 'views/metabox/course-topics.php';

        do_action('tutor_course_builder_metabox_after', get_the_ID());
    }

        /**
	 * Save course content order
     * 
	 * TODO: Future Refactor - Move to REST API
	 * -----------------------------------------
	 * - This function is currently used for sorting and saving the course content order
	 *   via AJAX, primarily within the interactive Course Builder metabox.
	 * - As we plan to migrate the Course Builder into a dedicated tab in Gutenberg, this
	 *   function should eventually be converted into a REST API endpoint.
	 * - Before refactoring, we need to:
	 *   1. Review tutor-course-builder.js to determine how this function is currently called.
	 *   2. Check if a similar function exists in Course_REST_Controller.php.
	 *   3. Ensure that migrating this logic does not break existing AJAX interactions.
	 * - This will be revisited when we perform a full audit of tutor-course-builder.js.
	 */
	private function save_course_content_order() {
		global $wpdb;

		$new_order = Input::post( 'tutor_topics_lessons_sorting' );
		if ( ! empty( $new_order ) ) {
			$order = json_decode( $new_order, true );

			if ( is_array( $order ) && count( $order ) ) {
				$i = 0;
				foreach ( $order as $topic ) {
					$i++;
					$wpdb->update(
						$wpdb->posts,
						array( 'menu_order' => $i ),
						array( 'ID' => $topic['topic_id'] )
					);

					/**
					 * Removing All lesson with topic
					 */

					$wpdb->update(
						$wpdb->posts,
						array( 'post_parent' => 0 ),
						array( 'post_parent' => $topic['topic_id'] )
					);

					/**
					 * Lesson Attaching with topic ID
					 * Sorting lesson
					 */
					if ( isset( $topic['lesson_ids'] ) ) {
						$lesson_ids = $topic['lesson_ids'];
					} else {
						$lesson_ids = array();
					}
					if ( count( $lesson_ids ) ) {
						foreach ( $lesson_ids as $lesson_key => $lesson_id ) {
							$wpdb->update(
								$wpdb->posts,
								array(
									'post_parent' => $topic['topic_id'],
									'menu_order'  => $lesson_key,
								),
								array( 'ID' => $lesson_id )
							);
						}
					}
				}
			}
		}
	}

    /**
	 * Save course topic (AJAX)
	 * 
	 * NOTE: Keeping this as-is for now. Future migration to REST API possible.
	 */
	public function tutor_save_topic() {
		tutor_utils()->checking_nonce();

		// Check required fields.
		if (empty(Input::post('topic_title'))) {
			wp_send_json_error(['message' => __('Topic title is required!', 'tutor')]);
		}

		// Gather parameters.
		$course_id = Input::post('topic_course_id', 0, Input::TYPE_INT);
		$topic_id = Input::post('topic_id', 0, Input::TYPE_INT);
		$topic_title = Input::post('topic_title');
		$topic_summary = Input::post('topic_summery', '', Input::TYPE_KSES_POST);
		$next_topic_order_id = tutor_utils()->get_next_topic_order_id($course_id, $topic_id);

		// Validate if user can manage the topic.
		if (!tutor_utils()->can_user_manage('course', $course_id) || 
		    ($topic_id && !tutor_utils()->can_user_manage('topic', $topic_id))) {
			wp_send_json_error(['message' => __('Access Denied', 'tutor')]);
		}

		// Create or update topic.
		$post_arr = [
			'post_type'    => 'topics',
			'post_title'   => $topic_title,
			'post_content' => $topic_summary,
			'post_status'  => 'publish',
			'post_author'  => get_current_user_id(),
			'post_parent'  => $course_id,
			'menu_order'   => $next_topic_order_id,
		];
		if ($topic_id) {
			$post_arr['ID'] = $topic_id;
		}
		$current_topic_id = wp_insert_post($post_arr);

		ob_start();
		include tutor()->path . 'views/metabox/course-contents.php';

		wp_send_json_success([
			'topic_title'     => $topic_title,
			'course_contents' => ob_get_clean(),
		]);
	}

    /**
     * ===================================================================================
     * Functions Used in frontend course builder
     * -----------------------------------------------------------------------------------
     * These functions are tied to the Frontend Course Builder's UI
     * - Replace old PHP-based metaboxes with dynamic JavaScript UI components.
     * - Loads the same data as Gutenberg via REST API.
     * ===================================================================================
     */

    /**
     * Register frontend course builder settings panel.
     *
     * @since 2.7.9
     * @return void
     */
    public function register_frontend_course_builder() {
        global $post;

        do_action('tutor_course_builder_metabox_before', get_the_ID());

        // Enqueue frontend course builder assets
        wp_enqueue_script('tutor-frontend-course-builder');
        wp_enqueue_style('tutor-frontend-builder-style');

        // Render a container that JavaScript will populate with course data
        echo '<div id="tutor-frontend-course-builder"></div>';

        do_action('tutor_course_builder_metabox_after', get_the_ID());
    }

    /**
     * tutor_get_wc_product() has been migrated to the REST API.
     * - Use the `/tutorpress/v1/wc-product/{product_id}` REST endpoint instead.
     * - Ensure JavaScript components fetch product data via the REST API.
     *
     * @since 3.0.0
     */

     /**
     * Add custom columns to the WP Admin Course List Table.
     *
     * This function modifies the default WordPress list table for courses,
     * adding additional columns for Lessons, Students, and Price while preserving 
     * existing columns. The newly added columns provide useful at-a-glance information 
     * for administrators managing courses.
     *
     * - 'Lessons'   → Displays the number of lessons in the course.
     * - 'Students'  → Displays the number of enrolled students.
     * - 'Price'     → Displays the course price.
     *
     * The function ensures that the default 'Date' column remains intact and 
     * positions the new columns before it.
     *
     * Developers can modify this list using the 'tutor_course_admin_columns' filter.
     *
     * @since 1.0.0
     *
     * @param array $columns Existing columns in the WP Admin course list.
     * @return array Modified column list including Lessons, Students, and Price.
     */

    public function add_column($columns) {
        $new_columns = array();
    
        // Preserve WordPress default columns before 'date'.
        foreach ($columns as $key => $value) {
            if ($key === 'date') {
                $new_columns['lessons']  = __('Lessons', 'tutor');
                $new_columns['students'] = __('Students', 'tutor');
                $new_columns['price']    = __('Price', 'tutor');
            }
            $new_columns[$key] = $value;
        }
    
        /**
         * Allow developers to modify the course admin columns.
         *
         * @since 2.7.9
         */
        return apply_filters('tutor_course_admin_columns', $new_columns);
    }

}
