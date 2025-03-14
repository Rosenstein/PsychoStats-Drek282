<?php
/**
 *	This file is part of PsychoStats.
 *
 *	Written by Jason Morriss
 *	Copyright 2008 Jason Morriss
 *
 *	PsychoStats is free software: you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation, either version 3 of the License, or
 *	(at your option) any later version.
 *
 *	PsychoStats is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with PsychoStats.  If not, see <http://www.gnu.org/licenses/>.
 *
 *	Version: $Id: players_edit.php 389 2008-04-18 15:04:10Z lifo $
 */
define("PSYCHOSTATS_PAGE", true);
define("PSYCHOSTATS_ADMIN_PAGE", true);
$basename = basename(__FILE__, '.php');
include("../includes/common.php");
include("./common.php");
$cms->theme->assign('page', 'users');

$validfields = array('ref','id','del','submit','cancel');
$cms->theme->assign_request_vars($validfields, true);

$message = '';
$cms->theme->assign_by_ref('message', $message);

if ($cancel) {
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'players.php' )));
}

// load the matching player if an ID was given
$plr = array();
$plr_user =& $cms->new_user();

if ($id) {
	// load the player based on their plrid
	$plr = $ps->get_player_profile($id);
	if ($plr and $plr['uniqueid'] == null) { // no matching profile; lets create one (all plrs should have one, regardless)
		$_id = $ps->db->escape($id, true);
		list($uid) = $ps->db->fetch_list("SELECT uniqueid FROM $ps->t_plr WHERE plrid=$_id");
		list($name) = $ps->db->fetch_list("SELECT name FROM $ps->t_plr_ids_name WHERE plrid=$_id ORDER BY totaluses DESC LIMIT 1");
		$ps->db->insert($ps->t_plr_profile, array( 'uniqueid' => $uid, 'name' => $name ));
		$plr['uniqueid'] = $uid;
		$plr['name'] = $name;
	}

	if (!$plr) {
		$data = array( 'message' => $cms->trans("Invalid player ID Specified") );
		$cms->full_page_err($basename, $data);
		exit();		
	}
	if ($plr['userid']) {
		$plr_user->load($plr['userid']);
		if (!$plr_user->userid()) {	// the user doesn't actually exist
			// remove userid from plr profile
			$ps->db->update($ps->t_plr_profile, array( 'userid' => null ), 'plrid', $plr['plrid']);
			$plr_user->userid(0);
		} else {
			$plr['username'] = $plr_user->username();
		}
	}
} else {
	$data = array( 'message' => $cms->trans("Invalid player ID Specified") );
	$cms->full_page_err($basename, $data);
	exit();		
}

// delete it, if asked to
if ($del and $id and $plr['plrid'] == $id) {
	if (!$ps->delete_player($id)) {
		$data = array( 'message' => $cms->trans("Error deleting player: " . $ps->db->errstr) );
		$cms->full_page_err($basename, $data);
		exit();
	}
	previouspage(ps_url_wrapper(array( '_amp' => '&', '_base' => 'players.php' )));
}

// create the form variables
$form = $cms->new_form();
$form->default_modifier('trim');
$form->field('plrname');	// 'plrname' is used instead of 'name' to avoid conflicts with some software (nuke)
$form->field('email', 'email');
$form->field('discord');
$form->field('twitch');
$form->field('youtube');
$form->field('socialclub');
$form->field('website');
$form->field('icon');
$form->field('cc');
$form->field('logo');
$form->field('namelocked');
$form->field('username');
$form->field('password');
$form->field('password2');
$form->field('accesslevel');

// process the form if submitted
$valid = true;
if (isset($submit)) {
	$form->validate();
	$input = $form->values();
	$valid = !$form->has_errors();
	// protect against CSRF attacks
	if ($ps->conf['main']['security']['csrf_protection']) $valid = ($valid and $form->key_is_valid($cms->session));

	$input['name'] = $input['plrname'];
	unset($input['plrname']);

	// force a protocol prefix on the website url (http://)
	if (!empty($input['website']) and !preg_match('|^\w+://|', $input['website'])) {
		$input['website'] = "http://" . $input['website'];
	}

	// return error if website address does not exist or is unreachable
	if (!empty($input['website']) and !url_exists($input['website'])) {
        $form->error('website', $cms->trans("The web address is unreachable.") . " " .
            $cms->trans("Resubmit to try again.") 
			);
        $form->set('website', $website);
	}
	// return error if discord id is not an 18 digit number
	if (!empty($input['discord']) and !preg_match('|^[\d+]{17}$|', $input['discord'])) {
        $form->error('discord', $cms->trans("The Discord ID is not in the correct format.") . " " .
            $cms->trans("Resubmit to try again.") 
			);
        $form->set('discord', $discord);
	}

	// return error if twitch user name is not in correct format
	if (!empty($input['twitch']) and !preg_match('|^[a-zA-Z0-9][\w]{3,24}$|', $input['twitch'])) {
        $form->error('twitch', $cms->trans("Twitch user name not in correct format.") . " " .
            $cms->trans("Resubmit to try again.") 
			);
        $form->set('twitch', $twitch);
	}

	// return error if youtube user name is not in correct format
	if (!empty($input['youtube']) and !preg_match('|^[a-zA-Z0-9_-]{1,}$|', $input['youtube'])) {
        $form->error('youtube', $cms->trans("YouTube user name not in correct format.") . " " .
            $cms->trans("Resubmit to try again.") 
			);
        $form->set('youtube', $twitch);
	}

	// return error if socialclub user name is not in correct format
	if (!empty($input['socialclub']) and !preg_match('|^[a-zA-Z0-9_-]{5,15}$|', $input['socialclub'])) {
        $form->error('socialclub', $cms->trans("SocialClub nick name not in correct format.") . " " .
            $cms->trans("Resubmit to try again.") 
			);
        $form->set('socialclub', $twitch);
	}

	// strip out any bad tags from the logo.
	if (!empty($input['logo'])) {
		$logo = ps_strip_tags($input['logo']);
		$c1 = md5($logo);
		$c2 = md5($input['logo']);
		if ($c1 != $c2) {
			$form->error('logo', $cms->trans("Invalid tags were removed.") . " " .
				$cms->trans("Resubmit to try again.") 
			);
			$form->set('logo', $logo);
		}
		$input['logo'] = $logo;
	}

	if (!array_key_exists($input['accesslevel'], $cms->user->accesslevels())) {
		$form->error('accesslevel', $cms->trans("Invalid access level specified"));
	}
	if (!$form->error('username') and $input['username'] != '') {
		// load the user matching the username
		$_u = $plr_user->load_user($input['username'], 'username');

		// do not allow a duplicate username if another user has it already
		if ($_u and $_u['userid']) {
			$form->error('username', $cms->trans("Username already exists; please try another name"));
		} else {
			unset($form->errors['username']);
		}
		unset($_u);
	}

	// if a username is given we need to make sure a password was provided too (if there wasn't one already)
	if ($input['username'] != '') {
		// verify the passwords match if one was specified
		if (!$plr_user->userid() and $input['password'] == '') {
			$form->error('password', $cms->trans("A password must be entered for new users"));
		} elseif ($input['password'] != '') {
			if ($input['password'] != $input['password2']) {
				$form->error('password', $cms->trans("Passwords do not match; please try again"));
				$form->error('password2', ' ');
			}
		} else {
			unset($input['password']);
		}
		unset($input['password2']);
	}
	
	$valid = ($valid and !$form->has_errors());
	if ($valid) {
		// setup user record
		$u['username'] = $input['username'];
		if ($input['password'] != '') $u['password'] = $plr_user->hash($input['password']);
		$u['accesslevel'] = $input['accesslevel'];
		$u['confirmed'] = 1;
		unset($input['username']);
		unset($input['password']);
		unset($input['password2']);
		unset($input['accesslevel']);

		$input['cc'] = strtoupper($input['cc']);
        if (!$input['namelocked']) $input['namelocked'] = 0;

		// save a NEW user record if this player didn't have one
		$inserted = false;
		if (!$plr_user->userid() and $u['username'] != '') {
			$inserted = true;
			$u['userid'] = $plr_user->next_userid();	// assign an ID
			$input['userid'] = $u['userid'];		// point the plr_profile to this userid
			$ok = $plr_user->insert_user($u);
			if (!$ok) {
				$form->error('fatal', $cms->trans("Error saving user: " . $plr_user->db->errstr));
			} else {
				$plr_user->load($u['userid']);
			}
		}

		$ok = false;
		// update player record (even if the user failed to insert above)
		if ($id) {
			$ok = $ps->db->update($ps->t_plr_profile, $input, 'uniqueid', $plr['uniqueid']);
		} else {
			$ok = $ps->db->insert($ps->t_plr_profile, $input);
		}

		// update user record if something was changed
		if (!$inserted and $ok) {
			$changed = false;
			foreach (array('username', 'password', 'accesslevel', 'confirmed') as $k) {
				if (!array_key_exists($k, $u)) continue;
				if ($plr_user->$k() != $u[$k]) {
					$changed = true;
					break;
				}
			}
			if ($changed) {
				$ok = $plr_user->update_user($u, $plr_user->userid());
			}
		}

		if (!$ok) {
			$form->error('fatal', "Error updating database: " . $ps->db->errstr);
		} else {
			previouspage(ps_url_wrapper('players.php'));
		}

	}

} else {
	// fill in defaults
	if ($id) {
		$plr['plrname'] = $plr['name'];
		$in = $plr;
		if ($plr_user->userid()) {
			$in = array_merge($in, $plr_user->to_form_input());
		} else {
			$in['accesslevel'] = $plr_user->acl_user();
		}
		$form->input($in);
	} else {
//		$form->set('accesslevel', $plr_user->acl_user());
//		$form->set('confirmed', 1);
	}
}

$cms->crumb('Manage', ps_url_wrapper('manage.php'));
$cms->crumb('Players', ps_url_wrapper('players.php'));
$cms->crumb('Edit');

// save a new form key in the players session cookie
// this will also be put into a 'hidden' field in the form
if ($ps->conf['main']['security']['csrf_protection']) $cms->session->key($form->key());

$allowed_html_tags = str_replace(',', ', ', $ps->conf['theme']['format']['allowed_html_tags']);
if ($allowed_html_tags == '') $allowed_html_tags = '<em>' . $cms->translate("none") . '</em>';
$cms->theme->assign(array(
	'errors'	=> $form->errors(),
	'plr'		=> $plr,
	'plr_user'	=> $plr_user->to_form_input(),
	'allowed_html_tags' => $allowed_html_tags,
	'accesslevels'	=> $plr_user->accesslevels(),
	'form'		=> $form->values(),
	'form_key'	=> $ps->conf['main']['security']['csrf_protection'] ? $cms->session->key() : '',
));

// display the output
$cms->theme->add_css('css/forms.css');
//$cms->theme->add_js('js/jquery.interface.js');
$cms->theme->add_js('js/forms.js');
$cms->theme->add_js('js/players.js');
$cms->full_page($basename, $basename, $basename.'_header', $basename.'_footer', '');

?>
