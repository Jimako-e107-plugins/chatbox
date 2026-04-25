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
	'<ul class="media-list list-group">' . PHP_EOL;

$CHATBOX_TEMPLATE['list']['item']  = '
<li class="media list-group-item" >
<div class="media-left">
	<span class="media-object">{CB_AVATAR:size=60}</span>
</div>
<div class="media-body">
	<h4 class="media-heading" style="display: inline !important;">{CB_USERNAME}</h4>
	<small class="label label-default pull-right float-right">{CB_TIMEDATE}</small><br>
	<p>{CB_MESSAGE}</p>
	<div>
		<div class="pull-left float-left">{CB_BLOCKED}</div>
		<div class="pull-right float-right">{CB_MOD}</div>
	</div>
</div>
</li>'. PHP_EOL;

$CHATBOX_TEMPLATE['list']['end']   = '</ul>'. PHP_EOL;
