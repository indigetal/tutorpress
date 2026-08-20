/**
 * TutorPress H5P review modal (legacy View).
 * Localized: window.tutorpressH5PReview { ajaxurl, _tutor_nonce }. No Pro URLs.
 */
jQuery(function ($) {
	$(document).on("click", ".open-tutor-h5p-quiz-result-modal-btn", function (e) {
		e.preventDefault();
		var cfg = window.tutorpressH5PReview;
		if (!cfg || !cfg.ajaxurl || !cfg._tutor_nonce) {
			return;
		}
		var $btn = $(this);
		$.ajax({
			url: cfg.ajaxurl,
			type: "POST",
			data: {
				action: "view_h5p_quiz_result",
				quiz_id: parseInt($btn.data("quiz-id"), 10),
				user_id: parseInt($btn.data("user-id"), 10),
				question_id: parseInt($btn.data("question-id"), 10),
				attempt_id: parseInt($btn.data("attempt-id"), 10),
				content_id: parseInt($btn.data("content-id"), 10),
				_tutor_nonce: cfg._tutor_nonce,
			},
			success: function (response) {
				if (!response || !response.success || !response.data) {
					return;
				}
				$(".h5p-quiz-result-modal .tutor-modal-container").html(response.data.output);
				$(".h5p-quiz-result-modal").addClass("tutor-is-active");
			},
		});
	});
});
