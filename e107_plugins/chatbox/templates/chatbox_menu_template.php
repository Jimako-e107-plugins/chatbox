<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * e107 chatbox Plugin - menu (sidebar widget) template
 *
*/
if ( ! defined('e107_INIT')) {
	exit;
}

//---------------------------------MENU-----------------------------------------

$CHATBOX_MENU_TEMPLATE['menu']['start'] =
	'<ul class="chatbox-message-list list-unstyled mt-3">' . PHP_EOL;

$CHATBOX_MENU_TEMPLATE['menu']['item'] 	= '
<li class="chatbox-message d-flex mb-2">
<div class="chatbox-message-avatar flex-shrink-0 me-3">
	<span>{CB_AVATAR: size=48&shape=circle}</span>
</div>
<div class="chatbox-message-body flex-grow-1">
	<b>{CB_USERNAME}</b>&nbsp;
	<small class="chatbox-timestamp text-muted">{CB_TIMEDATE}</small><br />
	<p>{CB_MESSAGE}</p>
</div>
</li>' . PHP_EOL;

$CHATBOX_MENU_TEMPLATE['menu']['end'] 	= '</ul>'. PHP_EOL;
