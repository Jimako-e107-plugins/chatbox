<?php
/*
 * e107 website system
 *
 * Copyright (C) 2008-2013 e107 Inc (e107.org)
 * Released under the terms and conditions of the
 * GNU General Public License (http://www.gnu.org/licenses/gpl.txt)
 *
 * Plugin - Chatbox
 *
 * Plugin install / upgrade hooks. Picked up by core via the
 * <plugin>_setup.php convention (see e107_admin/update_routines.php).
 */

if(!defined('e107_INIT'))
{
	exit;
}


class chatbox_setup
{

	/**
	 * Plugin-owned preferences that historically lived in the e107 core
	 * config namespace and now belong in e107::getPlugConfig('chatbox').
	 *
	 * Keys are intentionally unchanged during this migration: the move is
	 * a namespace change, not a rename. Renaming (e.g. dropping the cb_
	 * prefix) is tracked separately in DEV_NOTES.
	 *
	 * @var array<string,string> old core key => new plugin key
	 */
	protected $legacyPrefs = array(
		'chatbox_posts'   => 'chatbox_posts',
		'cb_mod'          => 'cb_mod',
		'cb_layer'        => 'cb_layer',
		'cb_layer_height' => 'cb_layer_height',
		'cb_emote'        => 'cb_emote',
		'cb_user_addon'   => 'cb_user_addon',
	);


	/**
	 * Tells core whether the plugin still has work to do.
	 *
	 * Core calls this from the admin database-update screen. Returning
	 * true triggers a "needs upgrade" notice; the actual work happens
	 * in upgrade_post() once the admin clicks through.
	 *
	 * @return bool
	 */
	public function upgrade_required()
	{
		$corePref = e107::getConfig('core')->getPref();

		foreach(array_keys($this->legacyPrefs) as $oldKey)
		{
			if(isset($corePref[$oldKey]))
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Move plugin-owned prefs from the core namespace into the plugin's
	 * own namespace.
	 *
	 * Implementation follows the documented e107 idiom verbatim
	 * (devguide.e107.org → "Migrating plugin preferences"):
	 *
	 *   migrateData($map, true) reads the legacy keys from core,
	 *   removes them from core (saving core internally), and returns
	 *   the values as an array. We then write that array straight to
	 *   the plugin namespace. No merge, no second core save — both
	 *   would be wrong:
	 *
	 *   - merging $newPrefs against $plugConfig->getPref() lets the
	 *     defaults seeded by <pluginPrefs> in plugin.xml win over the
	 *     admin's actual saved values, silently reverting them to
	 *     defaults.
	 *   - re-saving core after migrateData() already saved it would
	 *     write back a stale in-memory snapshot.
	 *
	 * Idempotent: migrateData() returns falsy when none of the legacy
	 * keys exist, so re-running this is a no-op.
	 *
	 * @return bool true on success, false if save failed
	 */
	public function upgrade_post()
	{
		$newPrefs = e107::getConfig('core')->migrateData($this->legacyPrefs, true);

		if(empty($newPrefs))
		{
			return true; // nothing to migrate; treat as success
		}

		$result = e107::getPlugConfig('chatbox')
			->setPref($newPrefs)
			->save(false, true, false);

		e107::getCache()->clear('nq_chatbox');

		return $result !== false;
	}

}
