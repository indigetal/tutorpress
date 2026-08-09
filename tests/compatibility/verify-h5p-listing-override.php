<?php
/**
 * Verify pure H5P listing-override policy helpers (both plugin-active branches).
 *
 * Uses synthetic annotated posts/answers only — no temporary posts, no live
 * is_h5p_plugin_active() / get_post_meta() branch coverage. Documents the
 * priority-9 snapshot → priority-10 Pro strip → priority-15 policy story.
 *
 * Usage (from WordPress root):
 *   wp eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-listing-override.php
 */

$fail = static function ($message) {
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
};

$assert = static function ($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

if (!class_exists('TutorPress_H5P_Listing_Overrides')) {
    $fail('TutorPress_H5P_Listing_Overrides is unavailable.');
}

try {
    // Synthetic posts already annotated as priority-9 would leave them.
    $lesson = (object) array('ID' => 101, 'post_title' => 'Lesson');
    $h5p_quiz = (object) array(
        'ID'         => 102,
        'post_title' => 'Interactive Quiz',
        'quiz_type'  => 'tutor_h5p_quiz',
    );
    $standard_quiz = (object) array('ID' => 103, 'post_title' => 'Standard Quiz');

    $snapshot_topic = array(
        'id'       => 10,
        'title'    => 'Topic',
        'summary'  => '',
        'contents' => array($lesson, $h5p_quiz, $standard_quiz),
    );

    // Optional documentation of Pro strip: H5P row removed from filter input.
    $pro_stripped_topic = array(
        'id'       => 10,
        'title'    => 'Topic',
        'summary'  => '',
        'contents' => array($lesson, $standard_quiz),
    );

    $active_topic = TutorPress_H5P_Listing_Overrides::apply_h5p_quiz_content_policy(
        $pro_stripped_topic,
        $snapshot_topic,
        true
    );
    $assert(3 === count($active_topic['contents']), 'Active content policy should restore all snapshot posts.');
    $assert(
        isset($active_topic['contents'][1]->quiz_type) && 'tutor_h5p_quiz' === $active_topic['contents'][1]->quiz_type,
        'Active content policy should keep annotated H5P identity.'
    );

    $inactive_topic = TutorPress_H5P_Listing_Overrides::apply_h5p_quiz_content_policy(
        $pro_stripped_topic,
        $snapshot_topic,
        false
    );
    $assert(2 === count($inactive_topic['contents']), 'Inactive content policy should exclude H5P quizzes.');
    foreach ($inactive_topic['contents'] as $post) {
        $assert(
            !(isset($post->quiz_type) && 'tutor_h5p_quiz' === $post->quiz_type),
            'Inactive content policy must not retain tutor_h5p_quiz posts.'
        );
    }

    $answer_mc = (object) array('question_type' => 'multiple_choice', 'given_answer' => 'a');
    $answer_h5p = (object) array('question_type' => 'h5p', 'given_answer' => 'x');
    $answer_tf = (object) array('question_type' => 'true_false', 'given_answer' => 'true');
    // Non-sequential keys mirror Core/Pro key-preserving array_filter behavior.
    $snapshot_answers = array(
        2 => $answer_mc,
        5 => $answer_h5p,
        7 => $answer_tf,
    );
    $pro_stripped_answers = array(); // All-H5P strip edge case documented; policy uses snapshot.

    $active_answers = TutorPress_H5P_Listing_Overrides::apply_h5p_attempt_answers_policy(
        $pro_stripped_answers,
        $snapshot_answers,
        true
    );
    $assert($snapshot_answers === $active_answers, 'Active answers policy should return the snapshot as stored.');
    $assert(isset($active_answers[5]) && 'h5p' === $active_answers[5]->question_type, 'Active answers keep H5P row.');

    $inactive_answers = TutorPress_H5P_Listing_Overrides::apply_h5p_attempt_answers_policy(
        $pro_stripped_answers,
        $snapshot_answers,
        false
    );
    $assert(!isset($inactive_answers[5]), 'Inactive answers policy should exclude question_type=h5p.');
    $assert(isset($inactive_answers[2]) && isset($inactive_answers[7]), 'Inactive answers must preserve non-H5P keys.');
    $assert(array(2, 7) === array_keys($inactive_answers), 'Inactive answers must not reindex with array_values().');

    $null_content = TutorPress_H5P_Listing_Overrides::apply_h5p_quiz_content_policy($pro_stripped_topic, null, true);
    $assert($null_content === $pro_stripped_topic, 'Null content snapshot should leave current topic unchanged.');
    $null_answers = TutorPress_H5P_Listing_Overrides::apply_h5p_attempt_answers_policy($pro_stripped_answers, null, true);
    $assert($null_answers === $pro_stripped_answers, 'Null answers snapshot should leave current answers unchanged.');

    fwrite(STDOUT, "PASS: H5P listing-override policy helpers (content + answers, both branches).\n");
} catch (Throwable $e) {
    $fail($e->getMessage());
}
