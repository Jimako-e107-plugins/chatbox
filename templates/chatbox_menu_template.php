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
	'<ul class="media-list unstyled list-unstyled mt-3">' . PHP_EOL;

$CHATBOX_MENU_TEMPLATE['menu']['item'] 	= '
<li class="media d-flex mb-2">
<div class="media-left me-3">
	<span class="media-object mr-3">{CB_AVATAR: size=48&shape=circle}</span>
</div> 
<div class="media-body">
	<b>{CB_USERNAME}</b>&nbsp;
	<small class="muted smalltext">{CB_TIMEDATE}</small><br />
	<p>{CB_MESSAGE}</p>
</div>
</li>' . PHP_EOL;

$CHATBOX_MENU_TEMPLATE['menu']['end'] 	= '</ul>'. PHP_EOL;
