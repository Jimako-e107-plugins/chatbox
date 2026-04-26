<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * e107 chatbox_menu Plugin
 *
*/

if(isset($_POST['chatbox_ajax']))
{
	define('e_MINIMAL', true);
	if(!defined('e107_INIT'))
	{
		require_once(__DIR__.'/../../class2.php');
	}
}

global $e107cache, $e107;

$tp = e107::getParser();
$pref = e107::getPref();

if(!e107::isInstalled('chatbox'))
{
	return '';
}

e107::lan('chatbox', e_LANGUAGE);


$emessage = '';


if((isset($_POST['chat_submit']) || e_AJAX_REQUEST) && $_POST['cmessage'] !== '')
{

	if(!USER && !$pref['anon_post'])
	{
		$cmessage = ''; // disallow post
	}
	else
	{
		$nick = trim(preg_replace("#\[.*\]#s", '', $tp->toDB($_POST['nick'])));

		$cmessage = $_POST['cmessage'];
		$cmessage = preg_replace("#\[.*?\](.*?)\[/.*?\]#s", "\\1", $cmessage);


		$fp = new floodprotect;

		if($fp->flood('chatbox', 'cb_datestamp'))
		{
			if(trim($cmessage) !== '' && (strlen(trim($cmessage)) < 1000))
			{

				$cmessage = $tp->toDB($cmessage);

				if($sql->select('chatbox', '*',
					"cb_message='{$cmessage}' AND cb_datestamp+84600>" . time()))
				{

					$emessage = CHATBOX_L17;

				}
				else
				{

					$datestamp = time();
					$ip = e107::getIPHandler()->getIP(false);

					if(USER)
					{

						$nick = USERID . '.' . USERNAME;

						$postTime = time();
						$sql->update('user', "user_chats = user_chats + 1, user_lastpost = {$postTime} WHERE user_id = " . USERID);

					}
					elseif(!$nick)
					{

						$nick = '0.Anonymous';

					}
					else
					{

						if($sql->select('user', '*', "user_name='$nick' "))
						{

							$emessage = CHATBOX_L1;

						}
						else
						{

							$nick = "0." . $nick;

						}

					}
					if(!$emessage)
					{
						$insertId = $sql->insert('chatbox',
							"0, '{$nick}', '{$cmessage}', '{$datestamp}', 0, '{$ip}' ");

						if($insertId)
						{

							$edata_cb = [
								'id'        => $insertId,
								'nick'      => $nick,
								'cmessage'  => $cmessage,
								'datestamp' => $datestamp,
								'ip'        => $ip,
							];

							e107::getEvent()->trigger('user_chatbox_post_created', $edata_cb);
							$e107cache->clear('nq_chatbox');
						}

					}
				}
			}
			else
			{
				$emessage = CHATBOX_L15;
			}
		}
		else
		{
			$emessage = $tp->lanVars(CHATBOX_L19, FLOODTIMEOUT ?: 'n/a');
		}
	}
}


if(!USER && !$pref['anon_post'])
{

	if($pref['user_reg'])
	{

		$text1 = str_replace(['[', ']'], ["<a href='" . e_LOGIN . "'>", '</a>'],
			CHATBOX_L3);

		if($pref['user_reg'] === 1)
		{
			$text1 .= str_replace(['[', ']'],
				["<a href='" . e_SIGNUP . "'>", '</a>'], CHATBOX_L3b);
		}

		$texta =
			"<div class='chatbox-login-hint' style='text-align:center'>" . $text1 . '</div><br /><br />';
	}

}
else
{
	$cb_width = (defined('CBWIDTH') ? CBWIDTH : '');

	if(varset($pref['cb_layer']) === 2)
	{

		$texta = "<form id='chatbox' action='" . e_REQUEST_SELF . "' method='post' onsubmit='return(false);'>
		<div>
			<input type='hidden' name='chatbox_ajax' id='chatbox_ajax' value='1' />
		</div>";
	}
	else
	{

		$texta = "<form id='chatbox' method='post' action='" . e_REQUEST_SELF . "'>";
	 
	}

	$texta .= "<div class='chatbox-input-block' id='chatbox-input-block'>";

	if(($pref['anon_post'] == '1' && USER === false))
	{
		$texta .= "\n<input class='chatbox-nick' type='text' id='nick' name='nick' value='' maxlength='50' " . ($cb_width
				? "style='width: " . $cb_width . ";'" : '') . ' /><br />';
	}

	if($pref['cb_layer'] === 2)
	{

		$oc =
			"onclick=\"javascript:sendInfo('" . SITEURLBASE . e_PLUGIN_ABS . "chatbox/chatbox_menu.php', 'chatbox_posts', this.form);\"";

	}
	else
	{

		$oc = '';

	}

	$texta .= '
	<textarea placeholder="' . LAN_CHATBOX_100 . "\" required class='chatbox-message-input form-control' id='cmessage' name='cmessage' cols='20' rows='5' style='max-width:97%; " . ($cb_width
			? 'width:' . $cb_width . ';' : '') . " overflow: auto' onselect='storeCaret(this);' onclick='storeCaret(this);' onkeyup='storeCaret(this);'></textarea>
	<br />
	<input class='button btn btn-sm btn-primary chatbox-submit' type='submit' id='chat_submit' name='chat_submit' value='" . CHATBOX_L4 . "' {$oc}/>";


	// $texta .= "<input type='reset' name='reset' value='".CHATBOX_L5."' />"; // How often do we see these lately? ;-)


	if(!empty($pref['cb_emote']) && !empty($pref['smiley_activate']))
	{
		$texta .= "
		<input class='button btn btn-sm btn-secondary chatbox-emotes-toggle' type='button' style='cursor:pointer' size='30' value='" . CHATBOX_L14 . "' onclick=\"expandit('emote')\" />
		<div class='chatbox-emotes-panel' style='display:none' id='emote'>" . r_emote() . "</div>\n";
	}

	$texta .= "</div>\n</form>\n";
}


if($emessage !== '')
{
	$texta .= "<div class='chatbox-error' style='text-align:center'><b>" . $emessage . '</b></div>';
}


if(!$text = e107::getCache()->retrieve('nq_chatbox'))
{

	global $pref, $tp;

	$pref['chatbox_posts'] = (!empty($pref['chatbox_posts']) ? (int) $pref['chatbox_posts'] : 10);

	$chatbox_posts = $pref['chatbox_posts'];

	if(!isset($pref['cb_mod']))
	{
		$pref['cb_mod'] = e_UC_ADMIN;
	}

	if(!defined('CB_MOD'))
	{
		define('CB_MOD', check_class($pref['cb_mod']));
	}

	$qry = "SELECT c.*, u.user_name, u.user_image FROM #chatbox AS c
	LEFT JOIN #user AS u ON SUBSTRING_INDEX(c.cb_nick, '.', 1) = u.user_id
	ORDER BY c.cb_datestamp DESC LIMIT 0, " . (int) $chatbox_posts;
 
	$CHATBOX_TEMPLATE = e107::getTemplate('chatbox', 'chatbox_menu', 'menu');

	// FIX - don't call getScBatch() if don't need to globally register the methods
	// $sc = e107::getScBatch('chatbox');

	// the good way in this case - it works with any object having sc_*, models too
	//$sc = new chatbox_shortcodes();

	$sc = e107::getScBatch('chatbox', true);

	if($sql->gen($qry))
	{

		$cbpost = $sql->rows();

		$text .= "<div class='chatbox-posts-block' id='chatbox-posts-block'>\n";

		$text .= $tp->parseTemplate($CHATBOX_TEMPLATE['start'], false, $sc);

		foreach($cbpost as $cb)
		{
			$sc->setVars($cb);
			$text .= $tp->parseTemplate($CHATBOX_TEMPLATE['item'], false, $sc);
		}

		$text .= $tp->parseTemplate($CHATBOX_TEMPLATE['end'], false, $sc);

		$text .= '</div>';

	}
	else
	{
		$text .= "<span class='chatbox-empty'>" . CHATBOX_L11 . '</span>';
	}

	$total_chats = $sql->count('chatbox');

	if($total_chats > $chatbox_posts || CB_MOD)
	{
		$text .= "<br /><div class='chatbox-more-link' style='text-align:center'><a href='" . e_PLUGIN_ABS . "chatbox/chat.php'>" . (CB_MOD
				? CHATBOX_L13
				: CHATBOX_L12) . '</a> (' . $total_chats . ')</div>';
	}

	e107::getCache()->set('nq_chatbox', $text);
}


$caption = (file_exists(THEME . 'images/chatbox_menu.png')
	? "<img src='" . THEME_ABS . "images/chatbox_menu.png' alt='' /> " . LAN_PLUGIN_CHATBOX_MENU_NAME
	: LAN_PLUGIN_CHATBOX_MENU_NAME);


if(varset($pref['cb_layer']) === 1)
{

	$text =
		$texta . "<div class='chatbox-scroll-layer' style='border : 0; padding : 4px; width : auto; height : " . $pref['cb_layer_height'] . "px; overflow : auto; '>" . $text . '</div>';

	$ns->tablerender($caption, $text, 'chatbox');

}
elseif(varset($pref['cb_layer']) === 2 && e_AJAX_REQUEST)
{

	$text = $texta . $text;
	$text = str_replace(e_IMAGE, e_IMAGE_ABS, $text);
	echo $text;

}
else
{

	$text = $texta . $text;

	if($pref['cb_layer'] === 2)
	{
		$text = "<div id='chatbox_posts'>" . $text . '</div>';
	}

	$ns->tablerender($caption, $text, 'chatbox');
}

