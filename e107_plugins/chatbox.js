/*
 * e107 chatbox plugin
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Plugin behavior — extracted from inline event handlers.
 *
 * Scope: this file currently addresses only issue #7 (emote panel
 * insertion failing on first interaction). The rest of the inline
 * handlers in chatbox_menu.php and chat.php (storeCaret, sendInfo,
 * expandit) are intentionally left in place; their extraction is
 * tracked separately under the "JS extraction" theme.
 */
(function () {
	'use strict';

	/**
	 * Issue #7: emote insertion silently fails on a fresh page load if
	 * the user clicks an emote before ever clicking, typing, or
	 * selecting text in the textarea.
	 *
	 * Root cause: the emote panel's click handler (registered by
	 * r_emote() in e107 core) calls addtext(value, true), which inserts
	 * at a caret position previously stored by storeCaret(). If
	 * storeCaret() has never run, no position is stored and addtext()
	 * has nowhere to insert — so it silently no-ops.
	 *
	 * Fix: when the user opens the emote panel, prime the stored caret
	 * position by calling storeCaret() on the message textarea. The
	 * handler is delegated from document so it works regardless of
	 * load order or whether the chatbox is rendered inside an AJAX
	 * response.
	 *
	 * This does not replace the inline onclick="expandit('emote')" on
	 * the toggle button; it runs alongside it.
	 */
	document.addEventListener('click', function (event) {
		var toggle = event.target.closest('.chatbox-emotes-toggle');
		if (!toggle) {
			return;
		}

		// The toggle and its textarea live inside the same #chatbox form.
		var form = toggle.closest('#chatbox');
		var textarea = form ? form.querySelector('#cmessage') : document.getElementById('cmessage');
		if (!textarea) {
			return;
		}

		if (typeof window.storeCaret === 'function') {
			window.storeCaret(textarea);
		}
	}, false);
})();
