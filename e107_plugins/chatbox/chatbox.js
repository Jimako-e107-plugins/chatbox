/*
 * e107 chatbox plugin
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Plugin behavior — extracted from inline event handlers.
 *
 * Replaces the following inline attributes from chatbox_menu.php:
 *   - <textarea> onselect/onclick/onkeyup="storeCaret(this);"
 *   - <input chat_submit> onclick="javascript:sendInfo(...)"
 *   - <input chatbox-emotes-toggle> onclick="expandit('emote')"
 *   - <form id='chatbox'> onsubmit='return(false);' (AJAX mode)
 *
 * The legacy global helpers in e107 core (storeCaret, addtext, sendInfo,
 * expandit, and the .addEmote click handler injected by r_emote()) are
 * still used. Replacing them with modern textarea APIs (selectionStart,
 * setRangeText) is tracked separately — it would require also replacing
 * r_emote() output, which is out of scope for this PR.
 *
 * Why every listener is delegated from `document`:
 * In AJAX mode (cb_layer === 2), submitting the form makes the legacy
 * core sendInfo() helper replace the entire contents of #chatbox_posts
 * with the server response — and the response includes a fresh copy of
 * the whole <form id='chatbox'> (textarea, submit button, emote toggle,
 * emote panel). Any listeners bound directly to those elements die with
 * them, and sendInfo() does not call e107.attachBehaviors() on the new
 * content, so a re-attach won't run. Delegating from document means the
 * listeners survive every AJAX swap without a re-bind.
 *
 * Scope: menu surface only. chat.php (standalone page) keeps its inline
 * handlers for now and is handled in a follow-up surface-by-surface PR.
 */
var e107 = e107 || {'settings': {}, 'behaviors': {}};

(function ($)
{
	'use strict';

	e107.behaviors.chatboxMenu = {

		attach: function (context, settings)
		{
			// Bind delegated listeners exactly once, regardless of how
			// many times attach() runs. The marker lives on document so
			// AJAX replacements of #chatbox_posts cannot wipe it.
			$(document).once('chatbox-menu').each(function ()
			{
				var $doc = $(this);

				/*
				 * Caret tracking — replaces inline
				 * onselect/onclick/onkeyup on <textarea>. Also bound to
				 * focus, which is the load-bearing piece for issue #7:
				 * focusing the textarea (programmatically from the emote
				 * toggle below, or via tab-keyboard) primes the stored
				 * caret position before the user can click an emote.
				 */
				$doc.on(
					'focus.chatbox click.chatbox keyup.chatbox select.chatbox',
					'#chatbox #cmessage',
					function ()
					{
						if (typeof window.storeCaret === 'function')
						{
							window.storeCaret(this);
						}
					}
				);

				/*
				 * AJAX submit — replaces inline
				 * onclick="javascript:sendInfo(...)" and
				 * onsubmit='return(false);' on the form.
				 *
				 * Activated by data-chatbox-ajax='1' on the submit
				 * button, which the PHP side sets only when
				 * cb_layer === 2.
				 */
				$doc.on('click.chatbox', '#chatbox #chat_submit[data-chatbox-ajax="1"]', function (event)
				{
					event.preventDefault();
					var form = this.form;

					// Respect HTML5 validation (e.g. required textarea).
					// preventDefault above bypasses the native submit
					// flow, so we run the check explicitly. reportValidity
					// shows the browser's standard validation tooltip.
					if (form && typeof form.checkValidity === 'function' && !form.checkValidity())
					{
						if (typeof form.reportValidity === 'function')
						{
							form.reportValidity();
						}
						return;
					}

					var $btn = $(this);
					var ajaxUrl = $btn.data('chatbox-ajax-url');
					var ajaxTarget = $btn.data('chatbox-ajax-target');
					if (typeof window.sendInfo === 'function')
					{
						window.sendInfo(ajaxUrl, ajaxTarget, form);
					}
				});

				$doc.on('submit.chatbox', '#chatbox', function (event)
				{
					// Only block the native submit when the form is in
					// AJAX mode — detected by the marker on the submit
					// button. In non-AJAX mode the form must submit
					// normally for a full page reload.
					if ($(this).find('#chat_submit[data-chatbox-ajax="1"]').length)
					{
						event.preventDefault();
					}
				});

				/*
				 * Emote panel toggle — replaces inline
				 * onclick="expandit('emote')" on the toggle button.
				 *
				 * The panel ID comes from data-chatbox-emote-toggle, so
				 * the behavior is not coupled to the literal 'emote'
				 * string — that stays a PHP/template concern.
				 *
				 * After opening the panel, focus the textarea. This
				 * triggers the focus handler above, which calls
				 * storeCaret(), which is the issue #7 fix.
				 */
				$doc.on('click.chatbox', '#chatbox .chatbox-emotes-toggle', function ()
				{
					var $btn = $(this);
					var panelId = $btn.data('chatbox-emote-toggle');
					if (panelId && typeof window.expandit === 'function')
					{
						window.expandit(panelId);
					}
					var textarea = document.getElementById('cmessage');
					if (textarea)
					{
						textarea.focus();
					}
				});
			});
		}

	};

})(jQuery);
