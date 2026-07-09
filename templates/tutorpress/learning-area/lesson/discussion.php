<?php
/**
 * TutorPress learning-area lesson Discussion template.
 *
 * Renders WordPress-native lesson comments inside Tutor LMS 4.0 modern/kids tabs.
 */

defined('ABSPATH') || exit;

$lesson_post = null;

if (isset($post) && $post instanceof WP_Post) {
    $lesson_post = $post;
} elseif (!empty($lesson_id)) {
    $lesson_post = get_post(absint($lesson_id));
} else {
    $lesson_post = get_post();
}

$is_active   = isset($is_active) ? (bool) $is_active : false;
$panel_style = 'padding: 24px;';

if (!$is_active) {
    $panel_style .= ' display: none;';
}
?>
<div
    class="tutorpress-learning-area-discussion tutorpress-discussion-panel"
    data-tutorpress-template="learning-area-lesson-discussion"
    x-show="activeTab === 'comments'"
    aria-labelledby="tutorpress-discussion-heading"
    style="<?php echo esc_attr($panel_style); ?>"
>
    <h2 id="tutorpress-discussion-heading" class="screen-reader-text">
        <?php echo esc_html__('Discussion', 'tutorpress'); ?>
    </h2>

    <?php
    if ($lesson_post instanceof WP_Post) {
        global $post;

        $previous_post = $post;
        $post          = $lesson_post;

        setup_postdata($post);
        comments_template();

        if ($previous_post instanceof WP_Post) {
            $post = $previous_post;
            setup_postdata($post);
        } else {
            wp_reset_postdata();
        }
    } else {
        ?>
        <p class="tutorpress-discussion-unavailable">
            <?php echo esc_html__('Discussion is unavailable for this lesson.', 'tutorpress'); ?>
        </p>
        <?php
    }
    ?>
</div>
