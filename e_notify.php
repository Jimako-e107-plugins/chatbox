<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2014 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 */

if (!defined('e107_INIT')) { exit; }


class chatbox_notify extends notify // plugin-folder + '_notify'
{
	function config()
	{

		$config = array();

		$config[] = array(
			'name'			=> NT_LAN_CB_2, //  "Message posted"
			'function'		=> "user_chatbox_post_created",
			'category'		=> ''
		);

		return $config;
	}

	function user_chatbox_post_created($data)
	{

		$message = NT_LAN_CB_3.': '.USERNAME.' ('.LAN_IP.': '.e107::getIPHandler()->ipDecode($data['ip']).' )<br />';
		$message .= NT_LAN_CB_5.':<br />'.$data['cmessage'].'<br /><br />';

		$this->send('user_chatbox_post_created', NT_LAN_CB_6, $message);
	}

}
