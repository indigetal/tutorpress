<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * TutorPress Collaborative Content Editing Service
 *
 * Enables co-instructors to edit course content (lessons, assignments, quizzes)
 * by hooking into WordPress's `user_has_cap` filter and granting the minimal
 * capabilities required when the user is an instructor of the related course.
 *
 * This is intentionally non-invasive: it does not modify Tutor LMS core or its
 * post type registration and only augments WordPress capability checks.
 */
class TutorPress_Collaborative_Editing {

    /**
     * Singleton instance
     *
     * @var TutorPress_Collaborative_Editing|null
     */
    private static $instance;

    /**
     * Get singleton instance
     *
     * @return TutorPress_Collaborative_Editing
     */
    public static function get_instance() {
        if (! isset( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register hooks
     */
    private function __construct() {
        // Priority 10, 4 args: $allcaps, $caps, $args, $user
        add_filter( 'user_has_cap', array( $this, 'grant_collaborative_editing_capabilities' ), 10, 4 );
        
        // Also hook map_meta_cap to handle primitive capability mapping
        add_filter( 'map_meta_cap', array( $this, 'map_collaborative_meta_caps' ), 10, 4 );
    }

    /**
     * Grant collaborative editing capabilities for course content when appropriate.
     *
     * @param array   $allcaps Existing capabilities for the user.
     * @param array   $caps    Capability names being checked.
     * @param array   $args    Extra args - [0] => capability, [1] => user_id, [2] => post_id.
     * @param WP_User $user    WP_User object for the user.
     * @return array Modified $allcaps
     */
    public function grant_collaborative_editing_capabilities( $allcaps, $caps, $args, $user ) {
        // Handle global capabilities (like 'edit_posts') that don't have a post context
        // These are checked by REST API controllers before post-specific checks
        if ( empty( $args[2] ) ) {
            return $allcaps;
        }

        $capability = isset( $args[0] ) ? $args[0] : '';
        $user_id    = isset( $args[1] ) ? (int) $args[1] : get_current_user_id();
        
        // Handle WP_Block_Editor_Context object (WordPress 6.0+)
        if ( is_object( $args[2] ) && isset( $args[2]->post ) && isset( $args[2]->post->ID ) ) {
            $post_id = (int) $args[2]->post->ID;
        } else {
            $post_id = (int) $args[2];
        }

        // Target capabilities relevant to Tutor LMS course content editing.
        $collaborative_caps = array(
            // WordPress primitives used by Gutenberg and editors.
            'edit_post',
            'delete_post',
            'edit_posts',
            'edit_others_posts',
            'edit_published_posts',
            'delete_posts',
            'delete_others_posts',
            'delete_published_posts',

            // Course capabilities
            'edit_tutor_course',
            'read_tutor_course',
            'delete_tutor_course',
            'edit_tutor_courses',
            'edit_others_tutor_courses',
            'edit_published_tutor_courses',
            'delete_tutor_courses',
            'delete_others_tutor_courses',
            'delete_published_tutor_courses',

            // Lesson capabilities
            'edit_tutor_lesson',
            'read_tutor_lesson',
            'delete_tutor_lesson',
            'edit_others_tutor_lessons',
            'delete_others_tutor_lessons',

            // Assignment capabilities
            'edit_tutor_assignment',
            'read_tutor_assignment',
            'delete_tutor_assignment',
            'edit_others_tutor_assignments',
            'delete_others_tutor_assignments',

            // Quiz capabilities (included for completeness)
            'edit_tutor_quiz',
            'read_tutor_quiz',
            'delete_tutor_quiz',
            'edit_others_tutor_quizzes',
            'delete_others_tutor_quizzes',
        );

        if ( ! in_array( $capability, $collaborative_caps, true ) ) {
            return $allcaps;
        }

        // Confirm this post is a Tutor LMS content type we care about.
        $post_type = get_post_type( $post_id );
        $allowed_types = array( 'lesson', 'tutor_assignments', 'tutor_quiz', 'courses' );
        
        // Use Tutor LMS post type properties if available for accuracy
        if ( function_exists( 'tutor' ) && is_object( tutor() ) ) {
            if ( property_exists( tutor(), 'course_post_type' ) ) {
                $allowed_types[] = tutor()->course_post_type;
            }
            if ( property_exists( tutor(), 'lesson_post_type' ) ) {
                $allowed_types[] = tutor()->lesson_post_type;
            }
            if ( property_exists( tutor(), 'assignment_post_type' ) ) {
                $allowed_types[] = tutor()->assignment_post_type;
            }
            if ( property_exists( tutor(), 'quiz_post_type' ) ) {
                $allowed_types[] = tutor()->quiz_post_type;
            }
        }
        
        // Remove duplicates
        $allowed_types = array_unique( $allowed_types );

        if ( ! in_array( $post_type, $allowed_types, true ) ) {
            return $allcaps;
        }

        // Use TutorPress_Permissions helper to determine instructor relationship.
        $permissions = new TutorPress_Permissions();
        
        // For course content (lessons, assignments, quizzes), check via course relationship
        if ( in_array( $post_type, array( 'lesson', 'tutor_assignments', 'tutor_quiz' ), true ) ) {
            if ( $permissions->can_user_edit_course_content( $post_id, $user_id ) ) {
                $allcaps[ $capability ] = true;
            }
        }
        // For courses themselves, check if user is an instructor of that course
        elseif ( in_array( $post_type, array( 'courses', tutor()->course_post_type ?? 'courses' ), true ) ) {
            if ( $permissions->can_user_access_course( $post_id, $user_id ) ) {
                $allcaps[ $capability ] = true;
            }
        }

        return $allcaps;
    }

    /**
     * Map meta capabilities for collaborative editing
     * 
     * This runs BEFORE user_has_cap and maps meta capabilities like 'edit_post'
     * to primitive capabilities like 'edit_published_posts'.
     *
     * @param array  $caps    Primitive capabilities required.
     * @param string $cap     Capability being checked.
     * @param int    $user_id User ID.
     * @param array  $args    Additional args (usually contains post ID).
     * @return array Modified primitive capabilities.
     */
    public function map_collaborative_meta_caps( $caps, $cap, $user_id, $args ) {
        // WordPress rewrites edit_post/delete_post to the CPT primitive when map_meta_cap is false.
        $child_edit_caps   = array( 'edit_post', 'edit_tutor_lesson', 'edit_tutor_assignment' );
        $child_delete_caps = array( 'delete_post', 'delete_tutor_lesson', 'delete_tutor_assignment' );

        if ( ! in_array( $cap, array_merge( array( 'read_post' ), $child_edit_caps, $child_delete_caps ), true ) ) {
            return $caps;
        }

        // Need a post ID
        if ( empty( $args[0] ) ) {
            return $caps;
        }

        $post_id = (int) $args[0];
        $post = get_post( $post_id );

        if ( ! $post ) {
            return $caps;
        }

        // Only handle Tutor LMS post types
        $tutor_post_types = array( 'courses', 'course-bundle', 'lesson', 'tutor_assignments', 'tutor_quiz' );
        if ( function_exists( 'tutor' ) && is_object( tutor() ) ) {
            if ( property_exists( tutor(), 'course_post_type' ) ) {
                $tutor_post_types[] = tutor()->course_post_type;
            }
            if ( property_exists( tutor(), 'lesson_post_type' ) ) {
                $tutor_post_types[] = tutor()->lesson_post_type;
            }
        }
        $tutor_post_types = array_unique( $tutor_post_types );

        if ( ! in_array( $post->post_type, $tutor_post_types, true ) ) {
            return $caps;
        }

        $permissions = new TutorPress_Permissions();
        $is_course   = ( 'courses' === $post->post_type )
            || ( function_exists( 'tutor' ) && is_object( tutor() ) && $post->post_type === tutor()->course_post_type );

        if ( $is_course ) {
            if ( $permissions->can_user_access_course( $post_id, $user_id ) ) {
                return array( 'exist' );
            }

            return $caps;
        }

        if ( 'course-bundle' === $post->post_type ) {
            if ( $permissions->can_user_edit_bundle( $post_id, $user_id ) ) {
                return array( 'exist' );
            }

            if ( in_array( $cap, array( 'edit_post', 'delete_post' ), true ) ) {
                return array( 'do_not_allow' );
            }

            return $caps;
        }

        $can_edit_content = $permissions->can_user_edit_course_content( $post_id, $user_id );
        if ( $can_edit_content ) {
            return array( 'exist' );
        }

        $child_types = array( 'lesson', 'tutor_assignments' );
        if ( function_exists( 'tutor' ) && is_object( tutor() ) ) {
            if ( property_exists( tutor(), 'lesson_post_type' ) ) {
                $child_types[] = tutor()->lesson_post_type;
            }
            if ( property_exists( tutor(), 'assignment_post_type' ) ) {
                $child_types[] = tutor()->assignment_post_type;
            }
        }

        if ( in_array( $post->post_type, array_unique( $child_types ), true )
            && in_array( $cap, array_merge( $child_edit_caps, $child_delete_caps ), true ) ) {
            return array( 'do_not_allow' );
        }

        return $caps;
    }
}

// Initialize the service so the filter is registered when this file is loaded.
TutorPress_Collaborative_Editing::get_instance();


