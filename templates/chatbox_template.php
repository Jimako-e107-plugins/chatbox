<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * e107 chatbox Plugin - page template (chat.php)
 *
*/
if ( ! defined('e107_INIT')) {
	exit;
}

//---------------------------------LIST-----------------------------------------

$CHATBOX_TEMPLATE['list']['start'] =
	'<ul class="chatbox-message-list list-group">' . PHP_EOL;

$CHATBOX_TEMPLATE['list']['item']  = '
<li class="chatbox-message list-group-item d-flex" >
<div class="chatbox-message-avatar flex-shrink-0 me-3">
	<span>{CB_AVATAR:size=60}</span>
</div>
<div class="chatbox-message-body flex-grow-1">
	<div class="h4 d-inline chatbox-username">{CB_USERNAME}</div>
	<small class="chatbox-timestamp text-muted float-end">{CB_TIMEDATE}</small><br>
	<p class="chatbox-message-text">{CB_MESSAGE}</p>
	<div class="chatbox-message-footer">
		<div class="chatbox-blocked-wrap float-start">{CB_BLOCKED}</div>
		<div class="chatbox-mod-wrap float-end">{CB_MOD}</div>
	</div>
</div>
</li>'. PHP_EOL;

$CHATBOX_TEMPLATE['list']['end']   = '</ul>'. PHP_EOL;
