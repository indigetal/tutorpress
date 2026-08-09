/**
 * TutorPress H5P iframe identity helper (injected into H5P iframes).
 *
 * XHR → parent postMessage + set_iframe xAPI extensions (Pro parity).
 * setObject no-op until set_iframe (D31). No Pro URLs. No parent DOM identity parsing.
 */
(function () {
	'use strict';

	var nativeOpen = XMLHttpRequest.prototype.open;
	var nativeSend = XMLHttpRequest.prototype.send;
	var requestUrl = '';

	XMLHttpRequest.prototype.open = function (method, url) {
		requestUrl = url;
		return nativeOpen.apply(this, arguments);
	};

	XMLHttpRequest.prototype.send = function (body) {
		var action = new URLSearchParams(requestUrl).get('action');
		if (body) {
			var params = new URLSearchParams(body);
			window.parent.postMessage(
				{ action: action, contentId: params.get('contentId') },
				'*'
			);
		}
		return nativeSend.apply(this, arguments);
	};

	window.addEventListener(
		'message',
		function (event) {
			if (!event.data || event.data.action !== 'set_iframe') {
				return;
			}

			var el = document.querySelector(event.data.selector);
			if (!el || typeof H5P === 'undefined') {
				return;
			}

			var questionId = event.data.question_id;

			H5P.XAPIEvent.prototype.setObject = function (instance) {
				if (instance.contentId) {
					this.data.statement.object = {
						id: this.getContentXAPIId(instance),
						objectType: 'Activity',
						definition: {
							extensions: {
								'http://h5p.org/x-api/h5p-local-content-id': instance.contentId,
								'http://h5p.org/x-api/h5p-local-question-id': questionId,
							},
						},
					};

					if (instance.subContentId) {
						this.data.statement.object.definition.extensions[
							'http://h5p.org/x-api/h5p-subContentId'
						] = instance.subContentId;
						if (typeof instance.getTitle === 'function') {
							this.data.statement.object.definition.name = {
								'en-US': instance.getTitle(),
							};
						}
					} else {
						var content = H5P.getContentForInstance(instance.contentId);
						if (content && content.metadata && content.metadata.title) {
							this.data.statement.object.definition.name = {
								'en-US': H5P.createTitle(content.metadata.title),
							};
						}
					}
				} else {
					this.data.statement.object = { definition: {} };
				}
			};
		},
		false
	);
})();
