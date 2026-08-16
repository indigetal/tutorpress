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
   * D6: wrapper hidden when computed display is none (Alpine x-show + jQuery .hide()).
   *
   * @param {Element} el Question wrapper element.
   * @return {boolean}
   */
  function isWrapperHidden(el) {
    if (!el || el.nodeType !== 1) {
      return true;
    }
    return window.getComputedStyle(el).display === "none";
  }

  /** Prior hidden state per wrapper (D6). WeakMap avoids leaking detached nodes. */
  var wrapperWasHidden = new WeakMap();

  /**
   * Invoke cb only on hidden→visible. First observation seeds state without firing.
   *
   * @param {Element} el Question wrapper element.
   * @param {Function} cb Called with el when it became visible.
   */
  function onWrapperBecameVisible(el, cb) {
    if (!el || el.nodeType !== 1 || typeof cb !== "function") {
      return;
    }
    var nowHidden = isWrapperHidden(el);
    if (!wrapperWasHidden.has(el)) {
      wrapperWasHidden.set(el, nowHidden);
      return;
    }
    var wasHidden = wrapperWasHidden.get(el);
    wrapperWasHidden.set(el, nowHidden);
    if (wasHidden && !nowHidden) {
      cb(el);
    }
  }

  /** Modern + legacy linear question wrappers (D1). */
  var QUESTION_WRAPPER_SELECTOR =
    ".tutor-quiz-question-wrapper, .quiz-attempt-single-question";

  /**
   * Observe wrapper style/class mutations; invoke onVisible only on hidden→visible (D6).
   * Does not start at boot — Step 5 wires this. Callback may be a no-op until resize lands.
   *
   * @param {Function} [onVisible] Called with wrapper el on hidden→visible.
   * @return {MutationObserver|null}
   */
  function startWrapperVisibilityObserver(onVisible) {
    var cb = typeof onVisible === "function" ? onVisible : function () {};
    var nodes = document.querySelectorAll(QUESTION_WRAPPER_SELECTOR);
    if (!nodes.length) {
      return null;
    }

    var i;
    for (i = 0; i < nodes.length; i++) {
      onWrapperBecameVisible(nodes[i], cb);
    }

    var observer = new MutationObserver(function (mutations) {
      var m;
      var el;
      for (m = 0; m < mutations.length; m++) {
        el = mutations[m].target;
        if (!el || el.nodeType !== 1 || typeof el.matches !== "function") {
          continue;
        }
        if (!el.matches(QUESTION_WRAPPER_SELECTOR)) {
          continue;
        }
        onWrapperBecameVisible(el, cb);
      }
    });

    for (i = 0; i < nodes.length; i++) {
      observer.observe(nodes[i], {
        attributes: true,
        attributeFilter: ["style", "class"],
      });
    }

    return observer;
  }

  /**
   * Collect H5P.instances arrays reachable for an iframe (D5).
   * Quiz shortcode iframes keep instances on contentWindow; parent may be empty.
   *
   * @param {HTMLIFrameElement} iframeEl
   * @return {Array}
   */
  function getH5pInstanceListsForIframe(iframeEl) {
    var lists = [];
    if (typeof H5P !== "undefined" && Array.isArray(H5P.instances)) {
      lists.push(H5P.instances);
    }
    try {
      var win = iframeEl.contentWindow;
      if (
        win &&
        win.H5P &&
        Array.isArray(win.H5P.instances) &&
        win.H5P.instances !== H5P.instances
      ) {
        lists.push(win.H5P.instances);
      }
    } catch (e) {
      // Cross-origin: fail-closed for this iframe.
    }
    return lists;
  }

  /**
   * Prefer H5P.trigger(instance, 'resize') for iframe(s) under wrapper (D1/D5).
   * Fail-soft: same-origin scrollHeight → style.height when no instance matched.
   * One-shot rAF/timeout for layout settle. No-op while wrapper still hidden.
   *
   * @param {Element} wrapperEl Question wrapper element.
   */
  function resizeH5pUnderWrapper(wrapperEl) {
    if (!wrapperEl || wrapperEl.nodeType !== 1) {
      return;
    }
    if (isWrapperHidden(wrapperEl)) {
      return;
    }

    function run() {
      if (isWrapperHidden(wrapperEl)) {
        return;
      }

      var iframes = wrapperEl.querySelectorAll("iframe.h5p-iframe");
      var i;
      for (i = 0; i < iframes.length; i++) {
        var iframe = iframes[i];
        var contentId = iframe.getAttribute("data-content-id");
        var triggered = false;

        if (
          contentId &&
          typeof H5P !== "undefined" &&
          typeof H5P.trigger === "function"
        ) {
          var lists = getH5pInstanceListsForIframe(iframe);
          var li;
          var ji;
          for (li = 0; li < lists.length; li++) {
            var instances = lists[li];
            for (ji = 0; ji < instances.length; ji++) {
              var instance = instances[ji];
              if (!instance || String(instance.contentId) !== String(contentId)) {
                continue;
              }
              H5P.trigger(instance, "resize");
              triggered = true;
            }
          }
        }

        if (!triggered) {
          applyFailSoftIframeHeight(iframe);
        }
      }
    }

    if (typeof window.requestAnimationFrame === "function") {
      window.requestAnimationFrame(run);
    } else {
      window.setTimeout(run, 0);
    }
  }

  /**
   * Fail-soft height when instance resize unavailable (D1/D5).
   *
   * @param {HTMLIFrameElement} iframe
   */
  function applyFailSoftIframeHeight(iframe) {
    try {
      var doc = iframe.contentDocument;
      if (!doc || !doc.body) {
        return;
      }
      var height = doc.body.scrollHeight;
      if (height > 0) {
        iframe.style.height = height + "px";
      }
    } catch (e) {
      // Cross-origin or not ready: fail-closed.
    }
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
    startWrapperVisibilityObserver(resizeH5pUnderWrapper);
  });

  window.tutorpressH5PQuizBridge = {
    isLegacyShell: isLegacyShell,
    getAttemptAndQuizIds: getAttemptAndQuizIds,
    getQuestionIdFromEl: getQuestionIdFromEl,
    getContentIdForIframe: getContentIdForIframe,
    isWrapperHidden: isWrapperHidden,
    resizeH5pUnderWrapper: resizeH5pUnderWrapper,
    startWrapperVisibilityObserver: startWrapperVisibilityObserver,
  };
})(window.jQuery);
