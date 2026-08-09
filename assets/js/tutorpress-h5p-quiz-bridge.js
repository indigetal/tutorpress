/**
 * TutorPress H5P quiz parent bridge (take-quiz xAPI / gates).
 * Localized: window.tutorpressH5PQuiz { ajaxurl, _tutor_nonce }. No Pro URLs.
 */
(function ($) {
  "use strict";

  /** D16: #tutor-answering-quiz ⇒ legacy; else modern form shell. */
  function isLegacyShell() {
    return !!document.getElementById("tutor-answering-quiz");
  }

  /**
   * D16 attempt + quiz. Fail-closed null if missing.
   * Modern: only quiz-attempt-form-{attempt}-{quiz}.
   * Legacy: only input[name=attempt_id] + #tutor_quiz_id.
   */
  function getAttemptAndQuizIds() {
    if (isLegacyShell()) {
      var attemptId = parseInt($("input[name='attempt_id']").val(), 10);
      var quizId = parseInt($("#tutor_quiz_id").val(), 10);
      if (!attemptId || !quizId) {
        return null;
      }
      return { attempt_id: attemptId, quiz_id: quizId };
    }

    var form = document.querySelector("form[id^='quiz-attempt-form-']");
    if (!form || typeof form.id !== "string") {
      return null;
    }
    var match = form.id.match(/^quiz-attempt-form-(\d+)-(\d+)$/);
    if (!match) {
      return null;
    }
    var attempt = parseInt(match[1], 10);
    var quiz = parseInt(match[2], 10);
    if (!attempt || !quiz) {
      return null;
    }
    return { attempt_id: attempt, quiz_id: quiz };
  }

  /** D16 question_id from closest shell wrapper. Fail-closed null. */
  function getQuestionIdFromEl(el) {
    var $wrap = isLegacyShell()
      ? $(el).closest(".quiz-attempt-single-question")
      : $(el).closest(".tutor-quiz-question");
    if (!$wrap.length) {
      return null;
    }
    var idMatch = String($wrap.attr("id") || "").match(/\d+/);
    if (!idMatch) {
      return null;
    }
    var questionId = parseInt(idMatch[0], 10);
    return questionId || null;
  }

  /**
   * D16 content_id for set_iframe (shell-specific; no cross-shell chain).
   * Legacy: data-h5p-quiz-content-id, else iframe data-content-id.
   * Modern: iframe[data-content-id] only.
   */
  function getContentIdForIframe(iframeEl) {
    if (isLegacyShell()) {
      var $q = $(iframeEl).closest(".quiz-attempt-single-question");
      if ($q.length) {
        var legacy = parseInt($q.attr("data-h5p-quiz-content-id"), 10);
        if (legacy) {
          return legacy;
        }
      }
      var legacyIframe = parseInt($(iframeEl).attr("data-content-id"), 10);
      return legacyIframe || null;
    }

    var modern = parseInt($(iframeEl).attr("data-content-id"), 10);
    return modern || null;
  }

  /**
   * Pro-parity parent setObject patch for one question_id (D16 extensions).
   *
   * @param {number} questionId Tutor question id.
   */
  function applyParentSetObject(questionId) {
    if (typeof H5P === "undefined") {
      return;
    }
    H5P.XAPIEvent.prototype.setObject = function (instance) {
      if (instance.contentId) {
        this.data.statement.object = {
          id: this.getContentXAPIId(instance),
          objectType: "Activity",
          definition: {
            extensions: {
              "http://h5p.org/x-api/h5p-local-content-id": instance.contentId,
              "http://h5p.org/x-api/h5p-local-question-id": questionId,
            },
          },
        };
        if (instance.subContentId) {
          this.data.statement.object.definition.extensions["http://h5p.org/x-api/h5p-subContentId"] =
            instance.subContentId;
          if (typeof instance.getTitle === "function") {
            this.data.statement.object.definition.name = {
              "en-US": instance.getTitle(),
            };
          }
        } else {
          var content = H5P.getContentForInstance(instance.contentId);
          if (content && content.metadata && content.metadata.title) {
            this.data.statement.object.definition.name = {
              "en-US": H5P.createTitle(content.metadata.title),
            };
          }
        }
      } else {
        this.data.statement.object = { definition: {} };
      }
    };
  }

  function setupH5pIdentity() {
    document.querySelectorAll(".h5p-content").forEach(function (el) {
      var questionId = getQuestionIdFromEl(el);
      if (!questionId) {
        return;
      }
      applyParentSetObject(questionId);
    });

    document.querySelectorAll(".h5p-iframe").forEach(function (iframe) {
      var questionId = getQuestionIdFromEl(iframe);
      var contentId = getContentIdForIframe(iframe);
      if (!questionId || !contentId) {
        return;
      }
      var timer = setInterval(function () {
        var doc = iframe.contentDocument || (iframe.contentWindow && iframe.contentWindow.document);
        if (!doc) {
          return;
        }
        if (doc.readyState === "complete" || doc.readyState === "interactive") {
          iframe.contentWindow.postMessage(
            {
              action: "set_iframe",
              selector: ".h5p-content",
              question_id: questionId,
              content_id: contentId,
            },
            "*",
          );
          clearInterval(timer);
        }
      }, 500);
    });
  }

  var EXT_CONTENT = "http://h5p.org/x-api/h5p-local-content-id";
  var EXT_QUESTION = "http://h5p.org/x-api/h5p-local-question-id";

  /**
   * D1: modern Alpine radio fill after scored save. No legacy; no [] shell; no Next bind.
   *
   * @param {number|string} questionId Tutor question id.
   * @param {number|string} contentId H5P content id.
   */
  function unlockModernNext(questionId, contentId) {
    if (isLegacyShell()) {
      return;
    }
    if (contentId === undefined || contentId === null || contentId === "") {
      return;
    }
    var ids = getAttemptAndQuizIds();
    if (!ids || !questionId) {
      return;
    }
    var name = "attempt[" + ids.attempt_id + "][quiz_question][" + questionId + "]";
    var radio = document.querySelector('input[type="radio"][name="' + name + '"]');
    if (!radio) {
      return;
    }
		radio.value = String(contentId);
		radio.dispatchEvent(new Event("input", { bubbles: true }));
	}

	/** D14 modern: iframe[data-content-id] + .tutor-quiz-question. */
	function collectModernPairs() {
		var pairs = [];
		document.querySelectorAll("iframe[data-content-id]").forEach(function (iframe) {
			var $q = $(iframe).closest(".tutor-quiz-question");
			if (!$q.length) {
				return;
			}
			var idMatch = String($q.attr("id") || "").match(/\d+/);
			var questionId = idMatch ? parseInt(idMatch[0], 10) : 0;
			var contentId = parseInt($(iframe).attr("data-content-id"), 10);
			if (questionId && contentId) {
				pairs.push({ question_id: questionId, content_id: contentId });
			}
		});
		return pairs;
	}

	/** check_h5p_question_answered via tutorpressH5PQuiz. Fail-closed → null. */
	function checkRequiredAnswers(pairs, attemptId, quizId) {
		return new Promise(function (resolve) {
			var cfg = window.tutorpressH5PQuiz;
			if (!cfg || !cfg.ajaxurl || !cfg._tutor_nonce || !attemptId || !quizId) {
				resolve(null);
				return;
			}
			$.ajax({
				url: cfg.ajaxurl,
				type: "POST",
				data: {
					action: "check_h5p_question_answered",
					question_ids: JSON.stringify(pairs || []),
					attempt_id: attemptId,
					quiz_id: quizId,
					_tutor_nonce: cfg._tutor_nonce,
				},
				success: function (response) {
					if (!response || !response.success || !response.data) {
						resolve(null);
						return;
					}
					try {
						var required = JSON.parse(response.data.required_answers);
						resolve(Array.isArray(required) ? required : null);
					} catch (err) {
						resolve(null);
					}
				},
				error: function () {
					resolve(null);
				},
			});
		});
	}

	function clearModernRequiredErrors() {
		document.querySelectorAll(".tutor-quiz-questions-error").forEach(function (el) {
			if (el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});
	}

	function showModernRequiredErrors(required) {
		clearModernRequiredErrors();
		if (!required || !required.length) {
			return;
		}
		required.forEach(function (pair) {
			var q = document.getElementById(String(pair.question_id));
			if (!q || q.querySelector(".tutor-quiz-questions-error")) {
				return;
			}
			var err = document.createElement("div");
			err.className = "tutor-quiz-questions-error";
			err.textContent = "Answer for this question is required.";
			q.appendChild(err);
		});
	}

	/** D14 legacy: data-h5p-quiz-content-id on .quiz-attempt-single-question. */
	function collectLegacyPairs() {
		var pairs = [];
		document
			.querySelectorAll(".quiz-attempt-single-question[data-h5p-quiz-content-id]")
			.forEach(function (el) {
				var idMatch = String(el.id || "").match(/\d+/);
				var questionId = idMatch ? parseInt(idMatch[0], 10) : 0;
				var contentId = parseInt(el.getAttribute("data-h5p-quiz-content-id"), 10);
				if (questionId && contentId) {
					pairs.push({ question_id: questionId, content_id: contentId });
				}
			});
		return pairs;
	}

	function clearLegacyRequiredErrors() {
		document.querySelectorAll(".answer-help-block .answer-required").forEach(function (el) {
			if (el.parentNode) {
				el.parentNode.removeChild(el);
			}
		});
	}

	function showLegacyRequiredErrors(required) {
		clearLegacyRequiredErrors();
		if (!required || !required.length) {
			return;
		}
		required.forEach(function (pair) {
			var wrap = document.getElementById(
				"quiz-attempt-single-question-" + pair.question_id
			);
			if (!wrap) {
				return;
			}
			var block = wrap.querySelector(".answer-help-block");
			if (!block) {
				return;
			}
			var p = document.createElement("p");
			p.className = "answer-required";
			p.style.color = "#dc3545";
			p.textContent = "The answer for this question is required";
			block.appendChild(p);
		});
	}

	/** D14 allow-path: requestSubmit() only; guard against re-entry loops. */
	var submitAllowing = false;
	function allowSubmitOnce(form) {
		if (submitAllowing || !form || typeof form.requestSubmit !== "function") {
			return;
		}
		submitAllowing = true;
		try {
			form.requestSubmit();
		} finally {
			setTimeout(function () {
				submitAllowing = false;
			}, 0);
		}
	}

	/** D14 modern linear Submit (.tutor-quiz-submit-btn). Not Next; not form^=. */
	function bindModernLinearSubmit() {
		if (isLegacyShell()) {
			return;
		}
		document.querySelectorAll("button.tutor-quiz-submit-btn").forEach(function (btn) {
			btn.addEventListener("click", function (e) {
				e.preventDefault();
				var ids = getAttemptAndQuizIds();
				if (!ids) {
					return;
				}
				var form = document.querySelector("form[id^='quiz-attempt-form-']");
				checkRequiredAnswers(
					collectModernPairs(),
					ids.attempt_id,
					ids.quiz_id
				).then(function (required) {
					if (required === null) {
						return;
					}
					if (required.length === 0) {
						allowSubmitOnce(form);
						return;
					}
					showModernRequiredErrors(required);
				});
			});
		});
	}

	/** D14 modern non-linear Submit (button[form^=quiz-attempt-form-]). Allow on button.form. */
	function bindModernNonLinearSubmit() {
		if (isLegacyShell()) {
			return;
		}
		document
			.querySelectorAll("button[form^='quiz-attempt-form-']")
			.forEach(function (btn) {
				btn.addEventListener("click", function (e) {
					e.preventDefault();
					var form = btn.form;
					var formId = form && form.id ? String(form.id) : "";
					var match = formId.match(/^quiz-attempt-form-(\d+)-(\d+)$/);
					if (!match) {
						return;
					}
					var attemptId = parseInt(match[1], 10);
					var quizId = parseInt(match[2], 10);
					if (!attemptId || !quizId) {
						return;
					}
					checkRequiredAnswers(
						collectModernPairs(),
						attemptId,
						quizId
					).then(function (required) {
						if (required === null) {
							return;
						}
						if (required.length === 0) {
							allowSubmitOnce(form);
							return;
						}
						showModernRequiredErrors(required);
					});
				});
			});
	}

	/** D14 legacy non-linear Submit (name=quiz_answer_submit_btn). No legacy-linear bind. */
	function bindLegacyNonLinearSubmit() {
		if (!isLegacyShell()) {
			return;
		}
		document
			.querySelectorAll("button[name='quiz_answer_submit_btn']")
			.forEach(function (btn) {
				btn.addEventListener("click", function (e) {
					e.preventDefault();
					var ids = getAttemptAndQuizIds();
					if (!ids) {
						return;
					}
					var form = document.getElementById("tutor-answering-quiz");
					checkRequiredAnswers(
						collectLegacyPairs(),
						ids.attempt_id,
						ids.quiz_id
					).then(function (required) {
						if (required === null) {
							return;
						}
						if (required.length === 0) {
							allowSubmitOnce(form);
							return;
						}
						showLegacyRequiredErrors(required);
					});
				});
			});
	}

	/**
	 * Shared xAPI → save AJAX. Skip if question extension or attempt/quiz IDs missing.
	 * D1 on written|exists; Submit gates via bind helpers (Step 9).
	 *
	 * @param {Object} event H5P xAPI event.
	 */
	function onXapiEvent(event) {
    var cfg = window.tutorpressH5PQuiz;
    if (!cfg || !cfg.ajaxurl || !cfg._tutor_nonce) {
      return;
    }
    var statement = event.data && event.data.statement;
    if (!statement || !statement.object || !statement.object.definition) {
      return;
    }
    var extensions = statement.object.definition.extensions || {};
    if (extensions[EXT_QUESTION] === undefined) {
      return;
    }
    var ids = getAttemptAndQuizIds();
    if (!ids) {
      return;
    }
    var questionId = extensions[EXT_QUESTION];
    var contentId = extensions[EXT_CONTENT];
    $.ajax({
      url: cfg.ajaxurl,
      type: "POST",
      data: {
        action: "save_h5p_question_xAPI_statement",
        quiz_id: ids.quiz_id,
        attempt_id: ids.attempt_id,
        question_id: questionId,
        content_id: contentId,
        statement: JSON.stringify(statement),
        _tutor_nonce: cfg._tutor_nonce,
      },
      success: function (response) {
        if (!response || !response.success || !response.data) {
          return;
        }
        var status = response.data.result_status;
        if (status !== "written" && status !== "exists") {
          return;
        }
        unlockModernNext(questionId, contentId);
      },
    });
  }

  function bindXapiListener() {
    if (typeof H5P === "undefined" || !H5P.externalDispatcher) {
      return;
    }
    H5P.externalDispatcher.on("xAPI", onXapiEvent);
  }

  $(function () {
    setupH5pIdentity();
    bindXapiListener();
    bindModernLinearSubmit();
    bindModernNonLinearSubmit();
    bindLegacyNonLinearSubmit();
  });

  window.tutorpressH5PQuizBridge = {
    isLegacyShell: isLegacyShell,
    getAttemptAndQuizIds: getAttemptAndQuizIds,
    getQuestionIdFromEl: getQuestionIdFromEl,
    getContentIdForIframe: getContentIdForIframe,
  };
})(window.jQuery);
