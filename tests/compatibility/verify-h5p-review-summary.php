<?php
/**
 * Verify H5P Review Summary restore (A1/C1/D17) and slash template-path attach.
 *
 * Path filter + synthetics + live attempt 43. No learning_mode write. No row writes.
 *
 * Usage (from WordPress root, DevKinsta-loaded plugin tree):
 *   wp82 eval-file wp-content/plugins/tutorpress/tests/compatibility/verify-h5p-review-summary.php --allow-root
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

if (!class_exists('TutorPress_H5P_Review_Overrides')) {
    $fail('TutorPress_H5P_Review_Overrides is unavailable.');
}

try {
    $core = '/tmp/fake-core-summary.php';
    $wrapper = TutorPress_H5P_Review_Overrides::get_summary_wrapper_path();
    $slash = apply_filters(
        'tutor_get_template_path',
        $core,
        'shared/components/quiz/attempt-details/summary'
    );
    $assert($slash === $wrapper, 'Slash Summary name must return the TutorPress wrapper.');
    $assert(
        $core === TutorPress_H5P_Review_Overrides::get_stored_core_summary_path(),
        'Slash match must store the incoming Core path after apply_filters.'
    );

    $other = '/tmp/other-template.php';
    $pass = apply_filters(
        'tutor_get_template_path',
        $other,
        'shared/components/quiz/attempt-details/questions-sidebar'
    );
    $assert($pass === $other, 'Unrelated template path must pass through.');

    $h5p = (object) array(
        'question_type' => 'h5p',
        'given_answer'  => '',
        'is_correct'    => '1',
    );
    $skipped_tf = (object) array(
        'question_type' => 'true_false',
        'given_answer'  => '',
    );
    $answered_mcq = (object) array(
        'question_type' => 'multiple_choice',
        'given_answer'  => 'choice-a',
    );

    $instructor_in = array($skipped_tf);
    $instructor_out = TutorPress_H5P_Review_Overrides::answers_for_student_summary(
        $instructor_in,
        array($h5p, $skipped_tf),
        true
    );
    $assert($instructor_out === $instructor_in, 'Instructor flag must return $answers unchanged.');

    $mixed = TutorPress_H5P_Review_Overrides::answers_for_student_summary(
        array($answered_mcq),
        array($skipped_tf, $h5p, $answered_mcq),
        false
    );
    $assert(is_array($mixed) && 2 === count($mixed), 'Student mixed must keep H5P + non-skipped.');
    $assert($mixed[0] === $h5p, 'C1: skipped H5P sibling must be kept.');
    $assert($mixed[1] === $answered_mcq, 'Answered non-H5P must be kept.');
    foreach ($mixed as $row) {
        $assert($row !== $skipped_tf, 'C1: skipped non-H5P must be dropped.');
    }

    $attempt_id = 43;
    $unfiltered = \Tutor\Models\QuizModel::get_quiz_answers_by_attempt_id($attempt_id);
    $assert(is_array($unfiltered) && 2 === count($unfiltered), 'Attempt 43 must have two unfiltered rows.');
    foreach ($unfiltered as $row) {
        $assert(is_object($row) && 'h5p' === (string) $row->question_type, 'Attempt 43 rows must be h5p.');
        $given = isset($row->given_answer) ? (string) $row->given_answer : '';
        $assert('' === $given, 'Attempt 43 JOIN given_answer must be empty.');
        $assert(!empty($row->is_correct), 'Attempt 43 is_correct must be truthy.');
    }

    $student = \Tutor\Models\QuizModel::filter_attempt_answers_for_details($unfiltered, false);
    $assert(is_array($student) && 0 === count($student), 'Core student details-filter must empty attempt 43.');

    $restored = TutorPress_H5P_Review_Overrides::answers_for_student_summary(array(), $unfiltered, false);
    $assert(is_array($restored) && 2 === count($restored), 'Helper must restore two H5P rows for attempt 43.');
    $assert(!empty($restored), 'Restored list must be truthy (banner off).');
    foreach ($restored as $row) {
        $given = isset($row->given_answer) ? (string) $row->given_answer : '';
        $assert('' === $given, 'Restored given_answer must stay empty.');
        $assert(!empty($row->is_correct), 'Restored is_correct must be truthy.');
    }

    global $wpdb;
    $db_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT given_answer FROM {$wpdb->prefix}tutor_quiz_attempt_answers WHERE quiz_attempt_id = %d",
            $attempt_id
        )
    );
    $assert(is_array($db_rows) && 2 === count($db_rows), 'D17 re-query must find two attempt-answer rows.');
    foreach ($db_rows as $db_row) {
        $given = null === $db_row->given_answer ? '' : (string) $db_row->given_answer;
        $assert('' === $given || '""' === $given, 'D17: DB given_answer must stay empty.');
    }

    $correct = 0;
    $incorrect = 0;
    foreach ($restored as $answer) {
        if (!empty($answer->is_correct)) {
            ++$correct;
        } elseif (!in_array($answer->question_type, array('open_ended', 'short_answer'), true)) {
            ++$incorrect;
        }
    }
    $assert(2 === $correct && 0 === $incorrect, 'Simulated Summary counts must be 2 correct / 0 incorrect.');

    fwrite(STDOUT, "PASS: H5P review-summary (slash path + synthetics + attempt 43).\n");
} catch (Throwable $e) {
    $fail($e->getMessage());
}
