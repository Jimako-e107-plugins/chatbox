<?php
// $Id$   
if (!defined('e107_INIT')) { exit; }

if(vartrue($eMenuActive['chatbox_menu']) && (e107::getPlugConfig('chatbox')->get('cb_layer') == 2))
{
	$eplug_js[] = e_JS."e_ajax.php";
}


