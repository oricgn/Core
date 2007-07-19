<?php
////////////////////////////////////////////////////////////////////////////////

//                                                                            //
//   Copyright (C) 2007  Phorum Development Team                              //
//   http://www.phorum.org                                                    //
//                                                                            //
//   This program is free software. You can redistribute it and/or modify     //
//   it under the terms of either the current Phorum License (viewable at     //
//   phorum.org) or the Phorum License that was distributed with this file    //
//                                                                            //
//   This program is distributed in the hope that it will be useful,          //
//   but WITHOUT ANY WARRANTY, without even the implied warranty of           //
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     //
//                                                                            //
//   You should have received a copy of the Phorum License                    //
//   along with this program.                                                 //
//                                                                            //
////////////////////////////////////////////////////////////////////////////////

/**
 * This script implements the Phorum user API.
 *
 * The user API is used for managing users and user related data. The API
 * does also implement the Phorum session system, which is used for
 * remembering authenticated users. See the documentation for the function
 * {@link phorum_api_user_session_create()} for more information on
 * Phorum user sessions.
 *
 * The Phorum user API supports modules which can override Phorum's
 * authentication and session handling. And example module is provided
 * with the user API documentation.
 *
 * @package    PhorumAPI
 * @subpackage UserAPI
 * @copyright  2007, Phorum Development Team
 * @license    Phorum License, http://www.phorum.org/license.txt
 *
 * @example    user_auth_module.php Authentication override module example
 *
 * @todo
 *     Make sure that PHORUM_ORIGINAL_USER_CODE is handled somehow
 *     in the new API layer (unless it's not needed to handle that
 *     due to superb design of course ;-).
 *
 * @todo Document what fields are in a user record.
 *
 */

if (!defined('PHORUM')) return;

// {{{ Constant and variable definitions
/**
 * Used for identifying long term sessions. The value is used as
 * the name for the session cookie for long term sessions.
 */
define( 'PHORUM_SESSION_LONG_TERM' ,   'phorum_session_v5' );

/**
 * Used for identifying short term sessions. The value is used as
 * the name for the session cookie for short term sessions
 * (this is used by the tighter authentication scheme).
 */
define( 'PHORUM_SESSION_SHORT_TERM',   'phorum_session_st' );

/**
 * Used for identifying admin sessions. The value is used as
 * the name for the session cookie for admin sessions.
 */
define( 'PHORUM_SESSION_ADMIN',        'phorum_admin_session' );

/**
 * Function call parameter, which tells various functions that
 * a front end forum session has to be handled.
 */
define('PHORUM_FORUM_SESSION',         1);

/**
 * Function call parameter, which tells various functions that
 * an admin back end session has to be handled.
 */
define('PHORUM_ADMIN_SESSION',         2);


/**
 * Function call flag, which tells {@link phorum_api_user_set_active_user()}
 * that the short term forum session has to be activated.
 */
define('PHORUM_FLAG_SESSION_ST',       1);

/**
 * Function call flag, which tells {@link phorum_api_user_save()} that the
 * password field should be stored as is.
 * This can be used to feed Phorum MD5 encrypted passwords. Normally,
 * the password field would be MD5 encrypted by the function. This will
 * keep the phorum_api_user_save() function from double encrypting the password.
 */
define('PHORUM_FLAG_RAW_PASSWORD',     1);

/**
 * Function call flag, which tells {@link phorum_api_user_get_display_name()}
 * that the returned display names have to be HTML formatted, so they can be
 * used for showing the name in HTML pages.
 */
define('PHORUM_FLAG_HTML',             1);

/**
 * Function call flag, which tells {@link phorum_api_user_get_display_name()}
 * that the returned display names should be stripped down to plain text
 * format, so they can be used for showing the name in things like mail
 * messages and message quoting.
 */
define('PHORUM_FLAG_PLAINTEXT',        2);

/**
 * Function call parameter, which tells {@link phorum_api_user_session_create()}
 * that session ids have to be reset to new values as far as that is sensible
 * for a newly logged in user.
 */
define('PHORUM_SESSID_RESET_LOGIN',    1);

/**
 * Function call parameter, which tells {@link phorum_api_user_session_create()}
 * that all session ids have to be reset to new values. This is for example
 * appropriate after a user changed the password (so active sessions on
 * other computers or browsers will be ended).
 */
define('PHORUM_SESSID_RESET_ALL',      2);

/**
 * Function call parameter, which tells {@link phorum_api_user_get_list()}
 * that all users have to be returned.
 */
define('PHORUM_GET_ALL',               0);

/**
 * Function call parameter, which tells {@link phorum_api_user_get_list()}
 * that all active users have to be returned.
 */
define('PHORUM_GET_ACTIVE',            1);

/**
 * Function call parameter, which tells {@link phorum_api_user_get_list()}
 * that all inactive users have to be returned.
 */
define('PHORUM_GET_INACTIVE',          2);

/**
 * Function call parameter, which tells {@link phorum_api_user_check_access()}
 * and {@link phorum_api_user_check_group_access()} to return an array
 * of respectively forums or groups for which a user is granted access.
 */
define('PHORUM_ACCESS_LIST', -1);

/**
 * Function call parameter, which tells {@link phorum_api_user_check_access()}
 * and {@link phorum_api_user_check_group_access()} to check if the user
 * is granted access for respectively any forum or group.
 */
define('PHORUM_ACCESS_ANY', -2);

/**
 * Permission flag which allows users to read forum messages.
 */
define('PHORUM_USER_ALLOW_READ', 1);

/**
 * Permission flag which allows users to reply to forum messages.
 */
define('PHORUM_USER_ALLOW_REPLY', 2);

/**
 * Permission flag which allows users to edit their own forum messages.
 */
define('PHORUM_USER_ALLOW_EDIT', 4);

/**
 * Permission flag which allows users to start new forum topics.
 */
define('PHORUM_USER_ALLOW_NEW_TOPIC', 8);

/**
 * Permission flag which allows users to attach files to their forum messages.
 */
define('PHORUM_USER_ALLOW_ATTACH', 32);

/**
 * Permission flag which allows users to edit other users' messages.
 */
define('PHORUM_USER_ALLOW_MODERATE_MESSAGES', 64);

/**
 * Permission flag which allows users to moderate user signup
 * requests within the vroot.
 */
define('PHORUM_USER_ALLOW_MODERATE_USERS', 128);

/**
 * Group permission flag for users which are suspended by a group moderator.
 */
define('PHORUM_USER_GROUP_SUSPENDED', -1);

/**
 * Group permission flag for users which are not yet approved by
 * a group moderator.
 */
define('PHORUM_USER_GROUP_UNAPPROVED', 0);

/**
 * Group permission flag for users which are active approved group members.
 */
define('PHORUM_USER_GROUP_APPROVED', 1);

/**
 * Group permission flag for users which are group moderator.
 */
define('PHORUM_USER_GROUP_MODERATOR', 2);

/**
 * This array describes user data fields. It is mainly used internally
 * for configuring how to handle the fields and for doing checks on them.
 */
$GLOBALS['PHORUM']['API']['user_fields'] = array
(
  // Fields that are really in the Phorum users table.
  'user_id'                 => 'int',
  'username'                => 'string',
  'real_name'               => 'string',
  'display_name'            => 'string',
  'password'                => 'string',
  'password_temp'           => 'string',
  'sessid_lt'               => 'string',
  'sessid_st'               => 'string',
  'sessid_st_timeout'       => 'string',
  'email'                   => 'string',
  'email_temp'              => 'string',
  'hide_email'              => 'bool',
  'active'                  => 'int',
  'admin'                   => 'bool',
  'signature'               => 'string',
  'posts'                   => 'int',
  'date_added'              => 'int',
  'date_last_active'        => 'int',
  'last_active_forum'       => 'int',
  'threaded_list'           => 'int',
  'threaded_read'           => 'int',
  'hide_activity'           => 'bool',
  'show_signature'          => 'bool',
  'email_notify'            => 'int',
  'pm_email_notify'         => 'bool',
  'tz_offset'               => 'int',
  'is_dst'                  => 'bool',
  'user_language'           => 'string',
  'user_template'           => 'string',
  'moderation_email'        => 'bool',
  'moderator_data'          => 'array',
  'settings_data'           => 'array',

   // Fields that are used for passing on information about user related,
   // data, which is not stored in a standard user table field.
   'forum_permissions'      => 'array',

   // Fields that we do not use for saving data (yet?), but which might
   // be in the user data (e.g. if we store a user data array like it was
   // returned by phorum_api_user_get()).
   'groups'                 => NULL,
   'group_permissions'      => NULL,
   'permissions'            => NULL,
);

// }}}

// ----------------------------------------------------------------------
// Storing and retrieving user data.
// ----------------------------------------------------------------------

// {{{ Function: phorum_api_user_save()
/**
 * Create or update Phorum users.
 *
 * This function can be used for both creating and updating Phorum users.
 * If no user_id is provided in the user data, a new user will be created.
 * If a user_id is provided, then the existing user will be updated or a
 * new user with that user_id is created.
 *
 * @param array $user
 *     An array containing user data. This array should at least contain
 *     a field "user_id". This field can be NULL to create a new user
 *     with an automatically assigned user_id. It can also be set to a
 *     user_id to either update an existing user or to create a new user
 *     with the provided user_id.
 *
 * @param int $flags
 *     If the flag {@link PHORUM_FLAG_RAW_PASSWORD} is set, then the
 *     password fields ("password" and "password_temp") are considered to be
 *     MD5 encrypted already. So this can be used to feed Phorum existing MD5
 *     encrypted passwords.
 *
 * @return int
 *     The user_id of the user. For new users, the newly assigned user_id
 *     will be returned.
 */
function phorum_api_user_save($user, $flags = 0)
{
    global $PHORUM;

    include_once('./include/api/custom_profile_fields.php');

    // $user must be an array.
    if (!is_array($user)) trigger_error(
        'phorum_api_user_save(): $user argument is not an array',
        E_USER_ERROR
    );

    // We need at least the user_id field.
    if (!array_key_exists('user_id', $user)) trigger_error(
        'phorum_api_user_save(): missing field "user_id" in user data array',
        E_USER_ERROR
    );
    if ($user['user_id'] !== NULL && !is_numeric($user['user_id'])) {
        trigger_error(
            'phorum_api_user_save(): field "user_id" not NULL or numerical',
            E_USER_ERROR
        );
    }

    // Check if we are handling an existing or new user.
    $existing = NULL;
    if ($user['user_id'] !== NULL) {
        $existing = phorum_api_user_get($user['user_id'], TRUE);
    }

    // Create a user data array that is understood by the database layer.
    // We start out with the existing record, if we have one.
    $dbuser = $existing === NULL ? array() : $existing;

    // Merge in the fields from the $user argument.
    foreach ($user as $fld => $val) {
        $dbuser[$fld] = $val;
    }

    // Initialize storage for custom profile field data.
    $user_data = array();

    // Check and format fields.
    foreach ($dbuser as $fld => $val)
    {
        // Make sure that a valid field name is used. We do a strict check
        // on this (in the spirit of defensive programming).
        $fldtype = NULL;
        $custom  = NULL;
        if (!array_key_exists($fld, $PHORUM['API']['user_fields'])) {
            $custom = phorum_api_custom_profile_field_byname($fld);
            if ($custom === NULL) {
                trigger_error(
                    'phorum_api_user_save(): Illegal field name used in ' .
                    'user data: ' . htmlspecialchars($fld),
                    E_USER_ERROR
                );
            } else {
                $fldtype = 'custom_profile_field';
            }
        } else {
            $fldtype = $PHORUM['API']['user_fields'][$fld];
        }

        switch ($fldtype)
        {
            // A field that has to be fully ignored.
            case NULL:
                break;

            case 'int':
                $dbuser[$fld] = $val === NULL ? NULL : (int) $val;
                break;

            case 'string':
                $dbuser[$fld] = $val === NULL ? NULL : trim($val);
                break;

            case 'bool':
                $dbuser[$fld] = $val ? 1 : 0;
                break;

            case 'array':
                // TODO: maybe check for real arrays here?
                $dbuser[$fld] = $val;
                break;

            case 'custom_profile_field':
                // Arrays and NULL values are left untouched.
                // Other values are truncated to their configured field length.
                if ($val !== NULL && !is_array($val)) {
                    $val = substr($val, 0, $custom['length']);
                }
                $user_data[$custom['id']] = $val;
                unset($dbuser[$fld]);
                break;

            default:
                trigger_error(
                    'phorum_api_user_save(): Illegal field type used: ' .
                    htmlspecialchars($fldtype),
                    E_USER_ERROR
                );
                break;
        }
    }

    // Add the custom profile field data to the user data.
    $dbuser['user_data'] = $user_data;

    // At this point, we should have a couple of mandatory fields available
    // in our data. Without these fields, the user record is not sane
    // enough to continue with.
    // We really need a username, so we can always generate a display name.
    if (!isset($dbuser['username']) || $dbuser['username'] == '') {
        trigger_error(
            'phorum_api_user_save(): the username field for a user record ' .
            'cannot be empty',
            E_USER_ERROR
        );
    }
    // Phorum sends out mail messages on several occasions. So we need a
    // mail address for the user.
    if (!isset($dbuser['email']) || $dbuser['email'] == '') {
        trigger_error(
            'phorum_api_user_save(): the email field for a user record ' .
            'cannot be empty',
            E_USER_ERROR
        );
    }

    // For new accounts only.
    if (!$existing)
    {
        if (empty($dbuser['date_added']))
            $dbuser['date_added'] = time();

        if (empty($dbuser['date_last_active']))
            $dbuser['date_last_active'] = time();
    }

    // Handle password encryption.
    foreach (array('password', 'password_temp') as $fld)
    {
        // Sometimes, this function is (accidentally) called with existing
        // passwords in the data. Prevent duplicate encryption.
        if ($existing  && strlen($existing[$fld]) == 32 &&
            $existing[$fld] == $dbuser[$fld]) {
            continue;
        }

        // If the password field is empty, we should never store the MD5 sum
        // of an empty string as a safety precaution. Instead we store a
        // string which will never work as a password. This could happen in
        // case of bugs in the code or in case external user auth is used
        // (in which case Phorum can have empty passwords, since the Phorum
        // passwords are not used at all).
        if (!isset($dbuser[$fld]) || $dbuser[$fld] === NULL || $dbuser[$fld] == '') {
            $dbuser[$fld] = '*NO PASSWORD SET*';
            continue;
        }

        // Only crypt the password using MD5, if the PHORUM_FLAG_RAW_PASSWORD
        // flag is not set.
        if (!($flags & PHORUM_FLAG_RAW_PASSWORD)) {
            $dbuser[$fld] = md5($dbuser[$fld]);
        }
    }

    // Determine the display name to use for the user. If the setting
    // $PHORUM["custom_display_name"] is enabled (a "secret" setting which
    // cannot be changed through the admin settings, but only through
    // modules that consciously set it), then Phorum expects that the display
    // name is a HTML formatted display_name field, which is provided by
    // 3rd party software. Otherwise, the username or real_name is used
    // (depending on the $PHORUM["display_name_source"] Phorum setting).
    if (empty($PHORUM['custom_display_name'])) {
        $display_name = $dbuser['username'];
        if ($PHORUM['display_name_source'] == 'real_name' &&
            isset($dbuser['real_name']) &&
            trim($dbuser['real_name']) != '') {
            $display_name = $dbuser['real_name'];
        }
        $dbuser['display_name'] = $display_name;
    }
    // If the 3rd party software provided no or an empty display_name,
    // then we save the day by using the username (just so users won't show up
    // empty on screen, this should not happen at all in the first place).
    // We HTML encode the username, because custom display names are supposed
    // to be provided in escaped HTML format.
    elseif (!isset($dbuser['display_name']) ||
            trim($dbuser['display_name']) == '') {
        $dbuser['display_name'] = htmlspecialchars($dbuser['username'], ENT_COMPAT, $PHORUM['DATA']['HCHARSET']);
    }

    /**
     * [hook]
     *     user_save
     *
     * [description]
     *     This hook can be used to handle the data that is going to be
     *     stored in the database for a user. Modules can do some last
     *     minute change on the data or keep some external system in sync
     *     with the Phorum user data. In combination with the [user_get]
     *     hook, this hook can also be used to store and retrieve some of
     *     the Phorum user fields using some external system.
     *
     * [category]
     *     User data handling
     *
     * [when]
     *     Just before user data is stored in the database.
     *
     * [input]
     *     An array containing user data that will be sent to the database.
     *
     * [output]
     *     The same array as was used for the hook call argument,
     *     possibly with some updated fields in it.
     */
    if (isset($PHORUM['hooks']['user_save'])) {
        $dbuser = phorum_hook('user_save', $dbuser);
    }

    // Add or update the user in the database.
    if ($existing) {
        phorum_db_user_save($dbuser);
    } else {
        $dbuser['user_id'] = phorum_db_user_add($dbuser);
    }

    // If the display name changed for the user, then we do need to run
    // updates throughout the Phorum database to make references to this
    // user to show up correctly.
    if ($existing && $existing['display_name'] != $dbuser['display_name']) {
       phorum_db_user_display_name_updates($dbuser);
    }

    // If user caching is enabled, we invalidate the cache for this user.
    if (!empty($PHORUM['cache_users'])) {
        phorum_cache_remove('user', $dbuser['user_id']);
    }

    // Are we handling the active Phorum user? Then refresh the user data.
    if (isset($PHORUM['user']) &&
        $PHORUM['user']['user_id'] == $dbuser['user_id']) {
        $PHORUM['user'] = phorum_api_user_get($user['user_id'], TRUE);
    }

    return $dbuser['user_id'];
}
// }}}

// {{{ Function: phorum_api_user_save_raw()
/**
 * This function quickly updates the Phorum users table, using all fields in
 * the user data as real user table fields.
 *
 * This is the quickest way to update the user table. Care has to be taken
 * by the calling function though, to provide the information exactly as the
 * Phorum users table expects it. Only use this function if speed is really
 * an issue.
 *
 * @param array $user
 *     An array containing user data. This array should at least contain
 *     a field "user_id", pointing to the user_id of an existing user to
 *     update. Besides "user_id", this array can contain other fields,
 *     which should all be valid fields from the users table.
 */
function phorum_api_user_save_raw($user)
{
    if (empty($user['user_id'])) trigger_error(
        'phorum_api_user_save_raw(): the user_id field cannot be empty',
        E_USER_ERROR
    );

    // This hook is documented in phorum_api_user_save().
    if (isset($PHORUM['hooks']['user_save'])) {
        $user = phorum_hook('user_save', $user);
    }

    // Store the data in the database.
    phorum_db_user_save($user);

    // Invalidate the cache for the user, unless we are only updating
    // user activity tracking fields.
    if (!empty($GLOBALS['PHORUM']['cache_users']))
    {
        // Count the number of activity tracking fields in the data.
        $count = 1; // count user_id as an activity tracking field
        if (array_key_exists('date_last_active',  $user)) $count ++;
        if (array_key_exists('last_active_forum', $user)) $count ++;

        // Invalidate the cache, if there are non-activity tracking fields.
        if ($count != count($user)) {
            phorum_cache_remove('user', $user['user_id']);
        }
    }
}
// }}}

// {{{ Function: phorum_api_user_save_settings()
/**
 * Create or update user settings for the active Phorum user.
 *
 * This function can be used to store arbitrairy settings for the active
 * Phorum user in the database. The main goal for this function is to store
 * user settings which are not available as a Phorum user table field in
 * the database. These are settings which do not really belong to the Phorum
 * core, but which are for example used for remembering some kind of state
 * in a user interface (templates). Since each user interface might require
 * different settings, a dynamic settings storage like this is required.
 *
 * If you are writing modules that need to store data for a user, then please
 * do not use this function. Instead, use custom profile fields. The data
 * that is stored using this function can be best looked at as if it were
 * session data.
 *
 * @param array $settings
 *     An array of setting name => value pairs to store as user
 *     settings in the database.
 */
function phorum_api_user_save_settings($settings)
{
    global $PHORUM;

    // Get the active user's user_id.
    if (empty($PHORUM['user']['user_id'])) return;
    $user_id = $PHORUM['user']['user_id'];

    // The settings data must always be an array.
    if (empty($PHORUM['user']['settings_data'])) {
        $PHORUM['user']['settings_data'] = array();
    }

    // Merge the setting with the existing settings.
    foreach ($settings as $name => $value) {
        if ($value === NULL) {
            unset($PHORUM['user']['settings_data'][$name]);
        } else {
            $PHORUM['user']['settings_data'][$name] = $value;
        }
    }

    // Save the settings in the database.
    phorum_db_user_save(array(
        'user_id'       => $user_id,
        'settings_data' => $PHORUM['user']['settings_data']
    ));
}
// }}}

// {{{ Function: phorum_api_user_get()
/**
 * Retrieve data for Phorum users.
 *
 * @param mixed $user_id
 *     Either a single user_id or an array of user_ids.
 *
 * @param boolean $detailed
 *     If this parameter is TRUE (default is FALSE), then the user's
 *     groups and permissions are included in the user data.
 *
 * @return mixed
 *     If the $user_id parameter is a single user_id, then either an array
 *     containing user data is returned or NULL if the user was not found.
 *     If the $user_id parameter is an array of user_ids, then an array
 *     of user data arrays is returned, indexed by the user_id.
 *     Users for user_ids that are not found are not included in the
 *     returned array.
 */
function phorum_api_user_get($user_id, $detailed = FALSE)
{
    $PHORUM = $GLOBALS['PHORUM'];

    if (!is_array($user_id)) {
        $user_ids = array($user_id);
    } else {
        $user_ids = $user_id;
    }

    // Prepare the return data array. For each requested user_id,
    // a slot is prepared in this array. Also, turn the user id array
    // into an array which has the user_id as both the key and value.
    $users = array();
    $new_user_ids = array();
    foreach ($user_ids as $id) {
        $users[$id] = NULL;
        $new_user_ids[$id] = $id;
    }
    $user_ids = $new_user_ids;

    // First, try to retrieve user data from the user cache,
    // if user caching is enabled.
    if (!empty($PHORUM['cache_users']))
    {
        $cached_users = phorum_cache_get('user', $user_ids);
        if (is_array($cached_users))
        {
            foreach ($cached_users as $id => $user) {
                $users[$id] = $user;
                unset($user_ids[$id]);
            }

            // We need to retrieve the data for some dynamic fields
            // from the database.
            $dynamic_data = phorum_db_user_get_fields(
                array_keys($cached_users),
                array('date_last_active','last_active_forum','posts')
            );

            // Store the results in the users array.
            foreach ($dynamic_data as $id => $data) {
                $users[$id] = array_merge($users[$id],$data);
            }
        }
    }

    // Retrieve user data for the users for which no data was
    // retrieved from the cache.
    if (count($user_ids))
    {
        $db_users = phorum_db_user_get($user_ids, $detailed);

        foreach ($db_users as $id => $user)
        {
            // Merge the group and forum permissions into a final
            // permission value per forum. Forum permissions that are
            // assigned to a user directly override any group based
            // permission.
            if (!$user['admin']) {
                if (!empty($user['group_permissions'])) {
                    foreach ($user['group_permissions'] as $fid => $perm) {
                        if (!isset($user['permissions'][$fid])) {
                            $user['permissions'][$fid] = $perm;
                        } else {
                            $user['permissions'][$fid] |= $perm;
                        }
                    }
                }
                if (!empty($user['forum_permissions'])) {
                    foreach ($user['forum_permissions'] as $fid => $perm) {
                        $user['permissions'][$fid] = $perm;
                    }
                }
            }

            // If detailed information was requested, we store the data in
            // the cache. For non-detailed information, we do not cache the
            // data, because there is not much to gain there by caching.
            if ($detailed && !empty($PHORUM['cache_users'])) {
                phorum_cache_put('user', $id, $user);
            }

            // Store the results in the users array.
            $users[$id] = $user;
        }
    }

    // Remove the users for which we did not find data from the array.
    foreach ($users as $id => $user) {
        if ($user === NULL) {
            unset($users[$id]);
        }
    }

    /**
     * [hook]
     *     user_get
     *
     * [description]
     *     This hook can be used to handle the data that was retrieved
     *     from the database for a user. Modules can add and modify the
     *     user data. In combination with the [user_save]
     *     hook, this hook can also be used to store and retrieve some of
     *     the Phorum user fields in some external system
     *
     * [category]
     *     User data handling
     *
     * [when]
     *     Just after user data has been retrieved from the database.
     *
     * [input]
     *     An array of users. Each item in this array is an array
     *     containing data for a single user.
     *
     * [output]
     *     The same array as was used for the hook call argument,
     *     possibly with some updated fields in it.
     */
    if (isset($PHORUM['hooks']['user_get'])) {
        $users = phorum_hook('user_get', $users, $detailed);
    }

    // Return the results.
    if (is_array($user_id)) {
        return $users;
    } else {
        return isset($users[$user_id]) ? $users[$user_id] : NULL;
    }
}
// }}}

// {{{ Function: phorum_api_user_get_setting()
/**
 * This function can be used to retrieve the value for a user setting
 * that was stored by the {@link phorum_api_user_save_settings()} function
 * for the active Phorum user.
 *
 * @param string $name
 *     The name of the setting for which to retrieve the setting value.
 *
 * @return mixed
 *     The value of the setting or NULL if it is not available.
 */
function phorum_api_user_get_setting($name)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // No settings available at all?
    if (empty($PHORUM['user']['settings_data'])) return NULL;

    // The setting is available.
    if (array_key_exists($name, $PHORUM['user']['settings_data'])) {
        return $PHORUM['user']['settings_data'][$name];
    } else {
        return NULL;
    }
}
// }}}

// {{{ Function: phorum_api_user_get_display_name()
/**
 * Retrieve the display name to use for one or more users.
 *
 * The name to use depends on the "display_name_source" setting. This
 * one points to either the username or the real_name field of the
 * user. If the display_name is requested for an unknown user, then
 * a fallback name will be used.
 *
 * @param mixed $user_id
 *     Either a single user_id, an array of user_ids or NULL to use the
 *     user_id of the active Phorum user.
 *
 * @param mixed $fallback
 *     The fallback display name to use in case the user is unknown or NULL
 *     to use the "AnonymousUser" language string.
 *
 * @param mixed $flags
 *     One of {@link PHORUM_FLAG_HTML} (the default) or
 *     {@link PHORUM_FLAG_PLAINTEXT}. These determine what output format
 *     is used for the display names.
 *
 * @return mixed
 *     If the $user_id parameter was NULL or a single user_id, then this
 *     function will return a single display name. If it was an array,
 *     then this function will return an array of display names, indexed
 *     by user_id.
 */
function phorum_api_user_get_display_name($user_id = NULL, $fallback = NULL, $flags = PHORUM_FLAG_HTML)
{
    $PHORUM = $GLOBALS['PHORUM'];

    if ($fallback === NULL) {
        $fallback = $PHORUM['DATA']['LANG']['AnonymousUser'];
    }

    // Use the user_id for the active user.
    if ($user_id === NULL) {
        $user_id = $PHORUM['user']['user_id'];
    }

    // From here on, we need an array of user_ids to lookup.
    $user_ids = is_array($user_id) ? $user_id : array((int)$user_id);

    // Lookup the users.
    $users = phorum_api_user_get($user_ids, FALSE);

    // Determine the display names.
    $display_names = array();
    foreach ($user_ids as $id)
    {
        $display_name = empty($users[$id])
                      ? $fallback
                      : $users[$id]['display_name'];

        // Generate HTML based display names.
        if ($flags == PHORUM_FLAG_HTML)
        {
            // If the setting $PHORUM["custom_display_name"] is enabled,
            // then Phorum expects that the display name is a HTML
            // formatted display_name field, which is provided by
            // 3rd party software. So those do not have to be HTML escaped.
            // Other names do have to be escaped.
            if (empty($users[$id]) || empty($PHORUM['custom_display_name']))
            {
                $display_name = htmlspecialchars($display_name, ENT_COMPAT, $PHORUM['DATA']['HCHARSET']);
            }
        }
        // Generate a plain text version of the display name. This is the
        // display name as it can be found in the database. Only for
        // custom_display_name cases, we need to strip HTML code.
        elseif ($flags == PHORUM_FLAG_PLAINTEXT)
        {
            // Strip tags from the name. These might be in the
            // name if the custom_display_name feature is enabled.
            // So for custom display names we strip the HTML from the
            // display name that we found above.
            if (!empty($PHORUM['custom_display_name']))
            {
                $display_name = trim(strip_tags($display_name));

                // If the name was 100% HTML code (so empty after stripping),
                // then fallback to the default display_name that Phorum
                // would use without the custom display name feature.
                if ($display_name == '') {
                    if (empty($users[$id])) {
                        $display_name = $fallback;
                    } else {
                        $display_name = $users[$id]['username'];
                        if ($PHORUM['display_name_source'] == 'real_name' &&
                            trim($users[$id]['real_name']) != '') {
                            $display_name = $users[$id]['real_name'];
                        }
                    }
                }
            }
        }

        $display_names[$id] = $display_name;
    }

    if (is_array($user_id)) {
        return $display_names;
    } else {
        return $display_names[$user_id];
    }
}
// }}}

// {{{ Function: phorum_api_user_search()
/**
 * Search for users, based on simple search conditions, which act on
 * fields in the user table.
 *
 * The parameters $field, $value and $operator (which are used for defining
 * the search condition) can be arrays or single values. If arrays are used,
 * then all three parameter arrays must contain the same number of elements
 * and the key values in the arrays must be the same.
 *
 * @param mixed $field
 *     The user table field / fields to search on.
 *
 * @param mixed $value
 *     The value / values to search for.
 *
 * @param mixed $operator
 *     The operator / operators to use. Valid operators are
 *     "=", "!=", "<>", "<", ">", ">=" and "<=", "*". The
 *     "*" operator is for executing a "LIKE" match query.
 *
 * @param boolean $return_array
 *     If this parameter has a true value, then an array of all matching
 *     user_ids will be returned. Else, a single user_id will be returned.
 *
 * @param string $type
 *     The type of search to perform. This can be one of:
 *     - AND  match against all fields
 *     - OR   match against any of the fields
 *
 * @return mixed
 *     An array of matching user_ids or a single user_id (based on the
 *     $return_array parameter). If no user_ids can be found at all,
 *     then 0 (zero) will be returned.
 */
function phorum_api_user_search($field, $value, $operator = '=', $return_array = FALSE, $type = 'AND')
{
    return phorum_db_user_check_field($field, $value, $operator, $return_array, $type);
}
// }}}

// {{{ Function: phorum_api_user_search_display_name()
/**
 * Search user(s) for a given display name.
 *
 * @param string $name
 *     The display name to search for.
 *
 * @param boolean $return_array
 *     If TRUE, then the function will return all users that match
 *     the display name. If FALSE, the function will try to find an
 *     exact match for a single user.
 *
 * @return mixed
 *     If the $return_array parameter has a true value, then an array
 *     will be returned, containing the user_ids of all matching users.
 *     Otherwise, either a user_id (exact user match found) or NULL
 *     (no user found) is returned.
 */
function phorum_api_user_search_display_name($name, $return_array = FALSE)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // Exact or partial match?
    $oper = $return_array ? '*' : '=';

    // Find users by the display name field.
    $user_ids = phorum_api_user_search('display_name', $name, $oper, TRUE);

    if ($return_array) {
        return empty($user_ids) ? array() : $user_ids;
    } elseif (!empty($user_ids) && count($user_ids) == 1) {
        $user_id = array_shift($user_ids);
        return $user_id;
    } else {
        return NULL;
    }
}
// }}}

// {{{ Function: phorum_api_user_subscribe()
/**
 * Subscribe a user to a thread.
 *
 * Remark: Currently, there is no active support for subscribing to forums
 * of for subscription type PHORUM_SUBSCRIPTION_DIGEST in the Phorum core.
 *
 * @param integer $user_id
 *     The id of the user to create the subscription for.
 *
 * @param integer $thread
 *     The id of the thread to describe to.
 *
 * @param integer $forum_id
 *     The id of the forum in which the thread to subscribe to resides.
 *
 * @param integer $type
 *     The type of subscription. Available types are:
 *     - {@link PHORUM_SUBSCRIPTION_MESSAGE}
 *       Send a mail message for every new message.
 *     - {@link PHORUM_SUBSCRIPTION_BOOKMARK}
 *       Make new messages visible from the followed threads interface.
 *     - {@link PHORUM_SUBSCRIPTION_DIGEST}
 *       Periodically, send a mail message containing a list of new messages.
 *
 * @todo Do we really need the forum_id for subscribing to a thread in
 *       phorum_api_user_subscribe()? We could as well only use the thread
 *       and load the data for it from the db to find the forum_id. We can
 *       keep forum_id in there, but IMO only for subscribing to a full forum
 *       (not a core option).
 */
function phorum_api_user_subscribe($user_id, $thread, $forum_id, $type)
{
    // Check if the user is allowed to read the forum.
    if (! phorum_api_user_check_access(PHORUM_USER_ALLOW_READ, $forum_id)) {
        return;
    }

    // Setup the subscription.
    phorum_db_user_subscribe($user_id, $thread, $forum_id, $type);
}
// }}}

// {{{ Function: phorum_api_user_unsubscribe()
/**
 * Unsubscribe a user from a thread.
 *
 * @param integer $user_id
 *     The id of the user to remove the subscription for.
 *
 * @param integer $thread
 *     The id of the thread to describe from.
 *
 * @param integer $forum_id
 *     The id of the forum in which the thread to unsubscribe from resides.
 *     This parameter can be 0 (zero) to simply unsubscribe by thread id alone.
 */
function phorum_api_user_unsubscribe($user_id, $thread, $forum_id = 0)
{
    // Remove the subscription.
    phorum_db_user_unsubscribe($user_id, $thread, $forum_id);
}
// }}}

// {{{ Function: phorum_api_user_list()
/**
 * Retrieve a list of Phorum users.
 *
 * @param int $flags
 *     One of:
 *     - {@link PHORUM_GET_ALL}: retrieve a list of all users (the default)
 *     - {@link PHORUM_GET_ACTIVE}: retrieve a list of all active users
 *     - {@link PHORUM_GET_INACTIVE}: retrieve a list of all inactive users
 *
 * @return array
 *     An array of users, indexed by user_id. Each element in the array
 *     is an array, containing the fields "user_id", "username" and
 *     "display_name".
 */
function phorum_api_user_list($type = PHORUM_GET_ALL)
{
    // Retrieve a list of users from the database.
    $list = phorum_db_user_get_list($type);

    /**
     * [hook]
     *     user_list
     *
     * [description]
     *
     *     This hook can be used for reformatting the list of users that
     *     is returned by the phorum_api_user_list() function. Reformatting
     *     could mean things like changing the sort order or modifying the
     *     fields in the user arrays.
     *
     * [category]
     *     User data handling
     *
     * [when]
     *     Each time the phorum_api_user_list() function is called. The core
     *     Phorum code calls the function for creating user drop down lists
     *     (if those are enabled in the Phorum general settings) for the
     *     group moderation interface in the control center and for sending
     *     private messages.
     *
     * [input]
     *     An array of user info arrays. Each user info array contains the
     *     fields "user_id", "username" and "display_name". The hook function
     *     is allowed to update the "username" and "display_name" fields.
     *
     * [output]
     *     The same array as was used for the hook call argument,
     *     possibly with some updated fields in it.
     */
    if (isset($GLOBALS['PHORUM']['hooks']['user_list'])) {
        $list = phorum_hook('user_list', $list);
    }

    return $list;
}
// }}}

// ----------------------------------------------------------------------
// Authentication and session management.
// ----------------------------------------------------------------------

// {{{ Function: phorum_api_user_authenticate()
/**
 * Check the authentication credentials for a user.
 *
 * @example user_login.php Handle a user forum login
 *
 * @param string $type
 *     The type of session for which authentication is run. This must be
 *     one of {@link PHORUM_FORUM_SESSION} or {@link PHORUM_ADMIN_SESSION}.
 *
 *     This parameter is mostly used for logging purposes and for giving
 *     mods a chance to handle user authentication for only a certain type
 *     of session. It is not used for denying authentication if for example
 *     a standard user tries to authenticate for the admin interface. Those
 *     restrictions are handled in a different part of the user API.
 *
 *     See the documentation for {@link phorum_api_user_session_create()}
 *     for more information on Phorum user sessions.
 *
 * @param string $username
 *     The username for the user.
 *
 * @param string $password
 *     The password for the user.
 *
 * @return mixed
 *     If the authentication credentials are correct, this function returns
 *     the user_id of the authenticated user. Otherwise, FALSE is returned.
 */
function phorum_api_user_authenticate($type, $username, $password)
{
    $PHORUM = $GLOBALS['PHORUM'];

    $user_id = NULL;

    /**
     * [hook]
     *     user_authenticate
     *
     * [description]
     *     This hooks gives modules a chance to handle the user
     *     authentication (for example to authenticate against an
     *     external source like an LDAP server).
     *
     * [category]
     *     User authentication and session handling
     *
     * [when]
     *     Just before Phorum runs its own user authentication.
     *
     * [input]
     *     An array containing the following fields:
     *     <ul>
     *     <li>type:
     *         either PHORUM_FORUM_SESSION or PHORUM_ADMIN_SESSION;</li>
     *     <li>username:
     *         the username of the user to authenticate;</li>
     *     <li>password:
     *         the password of the user to authenticate;</li>
     *     <li>user_id:
     *         Always NULL on input. This field implements the
     *         authentication state.</li>
     *     </ul>
     *
     * [output]
     *     The same array as was used for the hook call argument,
     *     possibly with the user_id field updated. This field can
     *     be set to one of the following values by a module:
     *
     *     <ul>
     *     <li>NULL: let Phorum handle the authentication</li>
     *     <li>FALSE: the authentication credentials are rejected</li>
     *     <li>1234: the numerical user_id of the authenticated user</li>
     *     </ul>
     */
    if (isset($PHORUM['hooks']['user_authenticate']))
    {
        // Run the hook.
        $authinfo = phorum_hook('user_authenticate', array(
            'type'     => $type,
            'username' => $username,
            'password' => $password,
            'user_id'  => NULL
        ));

        // Authentication rejected by module.
        if ($authinfo['user_id'] === FALSE) {
            return FALSE;
        }

        // Check if the returned user_id is numerical, if the the module
        // did return a user_id.
        if ($authinfo['user_id']!==NULL && !is_numeric($authinfo['user_id'])) {
            trigger_error(
                'Hook user_check_login returned a non-numerical user_id "' .
                htmlspecialchars($authinfo['user_id']) .
                '" for the authenticated user. Phorum only supports numerical ' .
                'user_id values.',
                E_USER_ERROR
            );
        }

        $user_id = $authinfo['user_id'];
    }

    // No module handled the authentication?
    // Then we have to run the Phorum authentication.
    if ($user_id === NULL)
    {
        // Check the password.
        $user_id = phorum_db_user_check_login($username, md5($password));

        // Password check failed? Then try the temporary password (used for
        // the password reminder feature).
        $temporary_matched = FALSE;
        if ($user_id == 0) {
            $user_id = phorum_db_user_check_login($username, md5($password), TRUE);
            if ($user_id != 0) {
                $temporary_matched = TRUE;
            }
        }

        // If the temporary password matched, then synchronize the main
        // password with the temporary password. The temporary password
        // is kept the same.
        if ($temporary_matched) {
            phorum_api_user_save(array(
                'user_id'  => $user_id,
                'password' => $password
            ));
        }
    }

    return $user_id ? $user_id : FALSE;
}
// }}}

// {{{ Function: phorum_api_user_set_active_user()
/**
 * Set the active Phorum user.
 *
 * This function can be used to setup the Phorum data to indicate which
 * user is logged in or to setup the anonymous user. Calling this function
 * is all that is needed to tell Phorum which user is logged in (or to
 * tell that no user is logged in by setting up the anonymous user).
 *
 * Next to setting up the user data, the function will handle user activity
 * tracking (based on the "track_user_activity" setting) and setup some
 * special (template) variables:
 *
 * The variabe $PHORUM["DATA"]["ADMINISTRATOR"] will be set to TRUE if
 * the active user is an administrator, FALSE otherwise.
 *
 * For type {@link PHORUM_FORUM_SESSION}, the following extra variables
 * will be filled:
 *
 * - $PHORUM["DATA"]["LOGGEDIN"]:
 *   TRUE if the user is logged in, FALSE otherwise.
 *
 * - $PHORUM["DATA"]["FULLY_LOGGEDIN"]:
 *   TRUE if a short term session is active (by setting the
 *   {@link PHORUM_FLAG_SESSION_ST} flag for the $flags parameter),
 *   FALSE otherwise.
 *
 * @example user_login.php Handle a user forum login
 *
 * @param string $type
 *     The type of session for which to set the active user. This must be
 *     one of {@link PHORUM_FORUM_SESSION} or {@link PHORUM_ADMIN_SESSION}.
 *     See the documentation for {@link phorum_api_user_session_create()}
 *     for more information on Phorum user sessions.
 *
 * @param mixed $user
 *     The user_id or the full user data array for the user that has to be
 *     the active user or NULL if the active user has to be set to the
 *     anonymous user (the default).
 *
 * @param integer $flags
 *     If the flag {@link PHORUM_FLAG_SESSION_ST} is set, then the short
 *     term session will be enabled for {@link PHORUM_FORUM_SESSION}
 *     based sessions.
 *
 * @return boolean
 *     TRUE if a real user was set as the active user successfully
 *     or FALSE if the anonymous user was set (either because that was
 *     requested or because setting the real user failed). If setting a
 *     real user as the active user failed, the functions
 *     {@link phorum_api_strerror()} and {@link phorum_api_errno()} can be
 *     used to retrieve information about the error which occurred.
 */
function phorum_api_user_set_active_user($type, $user = NULL, $flags = 0)
{
    global $PHORUM;

    // Reset error storage.
    $GLOBALS['PHORUM']['API']['errno'] = NULL;
    $GLOBALS['PHORUM']['API']['error'] = NULL;

    // Determine what user to use.
    if ($user !== NULL)
    {
        // Use a full user array.
        if (is_array($user)) {
            // Some really basic checks on the user data. If something's
            // missing, then we fall back to the anonymous user.
            if (!isset($user['user_id']) ||
                !isset($user['active'])) {
                phorum_api_error_set(
                    PHORUM_ERRNO_ERROR,
                    'phorum_api_user_set_active_user(): ' .
                    'user record seems incomplete'
                );
                $user = NULL;
            }
        }
        // Retrieve the user by its user_id.
        elseif (is_numeric($user)) {
            $user = phorum_api_user_get($user, TRUE);
        }
        // Bogus $user parameter.
        else trigger_error(
            'phorum_api_user_set_active_user(): $user argument should be ' .
            'one of NULL, array or integer',
            E_USER_ERROR
        );

        // Fall back to the anonymous user if the user is not activated.
        if ($user && $user['active'] != PHORUM_USER_ACTIVE) {
            phorum_api_error_set(
                PHORUM_ERRNO_ERROR,
                'phorum_api_user_set_active_user(): ' .
                'the user is not active'
            );
            $user = NULL;
        }

        // Fall back to the anonymous user if the user does not have
        // admin rights, while an admin setup was requested.
        if ($type == PHORUM_ADMIN_SESSION && $user && empty($user['admin'])) {
            phorum_api_error_set(
                PHORUM_ERRNO_ERROR,
                'phorum_api_user_set_active_user(): ' .
                'the user is not an administrator'
            );
            $user = NULL;
        }
    }

    // Clear the special variables.
    $PHORUM['DATA']['LOGGEDIN']       = FALSE;
    $PHORUM['DATA']['FULLY_LOGGEDIN'] = FALSE;
    $PHORUM['DATA']['ADMINISTRATOR']  = FALSE;

    // ----------------------------------------------------------------------
    // Set the anonymous user.
    // ----------------------------------------------------------------------

    if (! $user)
    {
        // Fill the Phorum user with anonymous user data.
        $PHORUM['user'] = array(
            'user_id'   => 0,
            'username'  => '',
            'real_name' => '',
            'admin'     => false,
            'newinfo'   => array()
        );

        return FALSE;
    }

    // ----------------------------------------------------------------------
    // Set the active Phorum user and handle activity tracking.
    // ----------------------------------------------------------------------

    $PHORUM['user'] = $user;

    if (!empty($user['admin'])) {
        $PHORUM['DATA']['ADMINISTRATOR'] = TRUE;
    }

    if ($type == PHORUM_FORUM_SESSION) {
        $PHORUM['DATA']['LOGGEDIN'] = TRUE;
        if ($flags & PHORUM_FLAG_SESSION_ST) {
            $PHORUM['DATA']['FULLY_LOGGEDIN'] = TRUE;
        }
    }

    // Handle tracking user activity. The "track_user_activity" setting
    // specifies the user activity update interval in seconds (the lower
    // this setting is, the more often the database will be updated).
    if ($PHORUM['track_user_activity'] &&
        (empty($user['date_last_active']) ||
         $user['date_last_active'] < time() - $PHORUM['track_user_activity']))
    {
        $date_last_active  = time();
        $last_active_forum = empty($PHORUM['forum_id'])
                           ? 0 : $PHORUM['forum_id'];

        // Update the user data in the database.
        phorum_api_user_save_raw(array(
            'user_id'           => $user['user_id'],
            'date_last_active'  => $date_last_active,
            'last_active_forum' => $last_active_forum
        ));

        // Update the live user data.
        $PHORUM['user']['date_last_active']  = $date_last_active;
        $PHORUM['user']['last_active_forum'] = $last_active_forum;
    }

    return TRUE;
}
// }}}

// {{{ Function: phorum_api_user_session_create()
/**
 * Create a Phorum user session.
 *
 * Phorum does not use PHP sessions. Instead, it uses its own session
 * management system for remembering logged in users. There are
 * multiple reasons for that, amongst which are:
 *
 * - the lack of session support (on some PHP installs);
 * - missing out of the box load balancing support (sessions are normally
 *   written to local session state files, so multiple machines would not
 *   work well together);
 * - file I/O problems (both performance and file system permissions can
 *   be a problem);
 * - the amount of unneeded overhead that is caused by the PHP session system;
 * - the fact that Phorum also supports URI based sessions (without cookie).
 *
 * This function can be used to create or maintain a login session for a
 * Phorum user. A prerequisite is that an active Phorum user is set through
 * the {@link phorum_api_user_set_active_user()} function, before calling
 * this function.
 *
 * There are two session types available: {@link PHORUM_FORUM_SESSION}
 * (used for the front end application) and {@link PHORUM_ADMIN_SESSION}
 * (used for the administrative back end).
 *
 * Admin sessions are used for the administrative back end system. For
 * security reasons, the back end does not share the front end session,
 * but uses a fully separate session instead. This session does not
 * have a timeout restriction, but it does not survive closing the
 * browser. It is always tracked using a cookie, never using URI
 * authentication (for security reasons).
 *
 * The forum sessions can be split up into long term and short term sessions:
 *
 * - Long term session:
 *   The standard Phorum user session. This session is long lasting and will
 *   survive after closing the browser (unless the long term session timeout
 *   is set to zero). If tighter security is not enabled, then this session
 *   is all a user needs to fully use all forum options. This session is
 *   tracked using either a cookie or URI authentication.
 *
 * - Short term session:
 *   This session has a limited life time and will not survive closing the
 *   browser. If tighter security is enabled, then the user will not be able
 *   to use all forum functions, unless there is a short term session active
 *   (e.g. posting forum messages and reading/writing private messages are
 *   restricted). This session is tracked using a cookie. If URI authentication
 *   is in use (because of admin config or cookie-less browsers) Phorum will
 *   only look at the long term session (even in tighter security mode), since
 *   URI authentication can be considered to be short term by nature.
 *
 * @example user_login.php Handle a user forum login
 *
 * @param string $type
 *     The type of session to initialize. This must be one of
 *     {@link PHORUM_FORUM_SESSION} or {@link PHORUM_ADMIN_SESSION}.
 *
 * @param integer $reset
 *     If it is set to 0 (zero, the default), then existing session_ids
 *     will be reused if possible.
 *
 *     If this parameter is set to PHORUM_SESSID_RESET_LOGIN, then a new
 *     session id will be generated for short term forum sessions and if
 *     cookies are disabled for some reason, for long term forum sessions
 *     as well (to prevent accidental distribution of URLs with auth info
 *     in them). This is the type of session id reset that is appropriate
 *     after handling a login action.
 *
 *     If this parameter is set to PHORUM_SESSID_RESET_ALL, then all session
 *     ids will be reset to new values. This is for example
 *     appropriate after a user changed the password (so active sessions on
 *     other computers or browsers will be ended).
 *
 * @return boolean
 *     TRUE in case the session was initialized successfully.
 *     Otherwise, FALSE will be returned. The functions
 *     {@link phorum_api_strerror()} and {@link phorum_api_errno()} can be
 *     used to retrieve information about the error which occurred.
 */
function phorum_api_user_session_create($type, $reset = 0)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // Allow modules to handle creating a session or to simply fully
    // ignore creating sessions (for example useful if the hook
    // "user_session_restore" is used to inherit an external session from
    // some 3rd party application). The hook function gets the session
    // type as its argument and can return NULL if the Phorum session
    // create function does not have to be run.
    if (isset($PHORUM['hooks']['user_session_create'])) {
        if (phorum_hook('user_session_create', $type) === NULL) {
            return TRUE;
        }
    }

    // Reset error storage.
    $GLOBALS['PHORUM']['API']['errno'] = NULL;
    $GLOBALS['PHORUM']['API']['error'] = NULL;

    // Check if we have a valid session type.
    if ($type != PHORUM_FORUM_SESSION &&
        $type != PHORUM_ADMIN_SESSION) trigger_error(
        'phorum_api_user_session_create(): Illegal session type: ' .
        htmlspecialchars($type),
        E_USER_ERROR
    );

    // Check if the active Phorum user was set.
    if (empty($PHORUM['user']) ||
        empty($PHORUM['user']['user_id'])) trigger_error(
        'phorum_api_user_session_create(): Missing user in environment',
        E_USER_ERROR
    );

    // Check if the user is activated.
    if ($GLOBALS['PHORUM']['user']['active'] != PHORUM_USER_ACTIVE) {
        return phorum_api_error_set(
            PHORUM_ERRNO_NOACCESS,
            'The user is not (yet) activated (user id '.$user['user_id'].')'
        );
    }

    // For admin sessions, check if the user has administrator rights.
    // This is also checked from phorum_api_user_set_active_user(), but
    // one can never be too sure about this.
    if ($type == PHORUM_ADMIN_SESSION &&
        empty($GLOBALS['PHORUM']['user']['admin'])) {
        return phorum_api_error_set(
            PHORUM_ERRNO_NOACCESS,
            'The user is not an administrator (user id '.$user['user_id'].')'
        );
    }

    // Shortcut for checking if session ids are stored in cookies.
    // Note that the software that uses this function is responsible for
    // setting $PHORUM["use_cookies"] to PHORUM_NO_COOKIES if the client
    // does not support cookies.
    $use_cookies = isset($PHORUM['use_cookies']) &&
                   $PHORUM['use_cookies'] > PHORUM_NO_COOKIES;

    // ----------------------------------------------------------------------
    // Retrieve or generate required session id(s).
    // ----------------------------------------------------------------------

    $user = $GLOBALS['PHORUM']['user'];

    // Generate a long term session id. This one is used by all session types.
    // Create a new long term session id if no session id is available yet or
    // if a refresh was requested and cookies are disabled (with cookies
    // enabled, we always reuse the existing long term session, so the session
    // can be remembered and shared between multiple browsers / computers).
    $refresh_sessid_lt =
        empty($user['sessid_lt']) ||
        (!$use_cookies && $reset == PHORUM_SESSID_RESET_LOGIN) ||
        $reset == PHORUM_SESSID_RESET_ALL;
    if ($refresh_sessid_lt) {
        $sessid_lt = md5($user['username'].microtime().$user['password']);
        phorum_api_user_save_raw(array(
            'user_id'   => $user['user_id'],
            'sessid_lt' => $sessid_lt,
        ));
        $GLOBALS['PHORUM']['user']['sessid_lt'] = $sessid_lt;
    } else {
        $sessid_lt = $user['sessid_lt'];
    }

    // For forum sessions, generate a short term session id if tight
    // security is enabled in the configuration and cookies are enabled
    // (with URI authentication, the tight security system is bypassed
    // since the user will have to login on every visit already).
    $refresh_sessid_st = FALSE;
    if ($type == PHORUM_FORUM_SESSION &&
        !empty($PHORUM['tight_security']) &&
        $use_cookies)
    {
        // How much longer is the existing short term session id valid?
        $timeleft = empty($user['sessid_st_timeout'])
                  ? 0 : $user['sessid_st_timeout'] - time();

        // Create a new short term session id if ..
        if (empty($user['sessid_st']) || // .. no session id is available yet
            $reset) {                    // .. any type of reset was requested
            $sessid_st = md5($user['username'].microtime().$user['password']);
            $refresh_sessid_st = TRUE;
        } else {
            // Reuse the existing short term session id
            $sessid_st = $user['sessid_st'];

            // Have the session timeout reset if more than one third of the
            // session's life time has passed and if the session has not
            // yet expired.
            if ($timeleft > 0 &&
                $timeleft < $PHORUM['short_session_timeout']*60/2) {
                $refresh_sessid_st = TRUE;
            }
        }

        // The session data needs updating.
        if ($refresh_sessid_st) {
            $timeout = time() + $PHORUM['short_session_timeout']*60;
            phorum_api_user_save_raw(array(
                'user_id'           => $user['user_id'],
                'sessid_st'         => $sessid_st,
                'sessid_st_timeout' => $timeout
            ));
            $GLOBALS['PHORUM']['user']['sessid_st'] = $sessid_st;
            $GLOBALS['PHORUM']['user']['sessid_st_timeout'] = $timeout;
        }
    }

    // For admin sessions, the session id is computed using the long term
    // session id and some random data that was generated at install time.
    if ($type == PHORUM_ADMIN_SESSION) {
        $sessid_admin = md5($sessid_lt . $PHORUM['admin_session_salt']);
    }

    // ----------------------------------------------------------------------
    // Route the required session id(s) to the user.
    // ----------------------------------------------------------------------

    $user = $GLOBALS['PHORUM']['user'];

    if ($type == PHORUM_FORUM_SESSION)
    {
        // The long term session can be stored in either a cookie or
        // URL / form posting data.
        if ($use_cookies) {
            $timeout = empty($PHORUM['session_timeout'])
                     ? 0 : time() + 86400 * $PHORUM['session_timeout'];
            setcookie(
                PHORUM_SESSION_LONG_TERM,
                $user['user_id'].':'.$sessid_lt,
                $timeout,
                $PHORUM['session_path'], $PHORUM['session_domain']
            );
        } else {
            // Add the session id to the URL building GET variables.
            $GLOBALS['PHORUM']['DATA']['GET_VARS'][PHORUM_SESSION_LONG_TERM] =
                PHORUM_SESSION_LONG_TERM . '=' .
                urlencode($user['user_id'].':'.$sessid_lt);

            // Add the session id to the form POST variables.
            $GLOBALS['PHORUM']['DATA']['POST_VARS'] .=
                '<input type="hidden" name="'.PHORUM_SESSION_LONG_TERM.'" ' .
                'value="'.$user['user_id'].':'.$sessid_lt.'" />';
        }

        // The short term session id is always put in a cookie.
        if ($refresh_sessid_st) {
            setcookie(
                PHORUM_SESSION_SHORT_TERM,
                $user['user_id'].':'.$user['sessid_st'],
                $user['sessid_st_timeout'],
                $PHORUM['session_path'], $PHORUM['session_domain']
            );
        }
    }

    // The admin session id is always put in a cookie.
    elseif ($type == PHORUM_ADMIN_SESSION) {
        setcookie(
            PHORUM_SESSION_ADMIN,
            $user['user_id'].':'.$sessid_admin,
            0, // admin sessions are destroyed as soon as the browser closes
            $PHORUM['session_path'], $PHORUM['session_domain']
        );
    }

    return TRUE;
}
// }}}

// {{{ Function: phorum_api_user_session_restore()
/**
 * Restore a Phorum user session.
 *
 * This function will check for a valid user session for either the
 * forum or the admin interface (based on the $type parameter). If a valid
 * session is found, then the user session will be restored.
 *
 * @param string $type
 *     The type of session to check for. This must be one of
 *     {@link PHORUM_FORUM_SESSION} or {@link PHORUM_ADMIN_SESSION}.
 *     See the documentation for {@link phorum_api_user_session_create()}
 *     for more information on Phorum user sessions.
 *
 * @return boolean
 *     TRUE in case a valid session is detected, otherwise FALSE.
 *     Note that a {@link PHORUM_FORUM_SESSION} will return TRUE if
 *     a long term session is detected and that the sort term session
 *     might be missing. Code which depends on short term sessions should
 *     investigate the $PHORUM["DATA"]["FULLY_LOGGEDIN"] variable.
 */
function phorum_api_user_session_restore($type)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // ----------------------------------------------------------------------
    // Determine which session cookie(s) to check.
    // ----------------------------------------------------------------------

    // A list of session cookies to lookup.
    // The possible values for the items in this array are:
    //
    // 0: this cookie does not have to be checked
    // 1: a check has to be done or failed for this cookie
    // 2: the check for this cookie was successful
    //
    $check_session = array(
        PHORUM_SESSION_LONG_TERM  => 0,
        PHORUM_SESSION_SHORT_TERM => 0,
        PHORUM_SESSION_ADMIN      => 0
    );

    if ($type == PHORUM_FORUM_SESSION)
    {
        // Lookup the long term cookie.
        $check_session[PHORUM_SESSION_LONG_TERM] = 1;

        // Lookup the short term cookie if tight security is enabled.
        if (!empty($PHORUM['tight_security'])) {
            $check_session[PHORUM_SESSION_SHORT_TERM] = 1;
        }
    }
    elseif ($type == PHORUM_ADMIN_SESSION)
    {
        // Lookup the admin cookie.
        $check_session[PHORUM_SESSION_ADMIN] = 1;
    }
    else trigger_error(
        'phorum_api_user_session_restore(): Illegal session type: ' .
        htmlspecialchars($type),
        E_USER_ERROR
    );

    // ----------------------------------------------------------------------
    // Check the session cookie(s).
    // ----------------------------------------------------------------------

    // Now we decided what session cookie(s) we want to check, we allow
    // modules to hook into the session system to do the check for us.
    // This can for example be used to let Phorum inherit an already
    // running authenticated session in some external system.
    //
    // What the module has to do, is fill the fields from the passed
    // array with the user_id of the user for which a session is active
    // or FALSE if there is no session active. One or more of the fields
    // can be kept at NULL to have them handled by Phorum. This way,
    // the module could let the front end forum sesson inherit the
    // session from a different system, but let Phorum fully handle the
    // admin sessions on it own.
    $hook_sessions = array(
        PHORUM_SESSION_LONG_TERM  => NULL,
        PHORUM_SESSION_SHORT_TERM => NULL,
        PHORUM_SESSION_ADMIN      => NULL
    );
    if (isset($PHORUM['hooks']['user_session_restore'])) {
        $hook_sessions = phorum_hook('user_session_restore', $hook_sessions);
    }

    $real_cookie = FALSE;
    $session_user = NULL;
    foreach ($check_session as $cookie => $do_check)
    {
        if (!$do_check) continue;

        // Check if a module did provide a user_id for the checked session.
        $user_id_from_hook_session = FALSE;
        if ($hook_sessions[$cookie] !== NULL) {

            // Continue with the next cookie, if a module specified the
            // session cookie as invalid.
            if ($hook_sessions[$cookie] === FALSE) continue;

            // Pass on the user_id that was set by the module.
            // We add a fake a session id to the user_id here,
            // to make the split from below work.
            $value = $hook_sessions[$cookie] . ':dummy';
            $user_id_from_hook_session = TRUE;

            // To not let Phorum fall back to URI authentication.
            $real_cookie = TRUE;
        }

        // Check for a real cookie, which can always be expected for
        // short term and admin sessions and for long term sessions if
        // cookies are enabled.
        elseif (($cookie != PHORUM_SESSION_LONG_TERM ||
             (isset($PHORUM['use_cookies']) &&
             $PHORUM['use_cookies'] > PHORUM_NO_COOKIES)) &&
              isset($_COOKIE[$cookie])) {

            $value = $_COOKIE[$cookie];
            $real_cookie = TRUE;
        }

        // Check for URI based authentication.
        elseif ($PHORUM['use_cookies'] < PHORUM_REQUIRE_COOKIES &&
                  isset($PHORUM['args'][$cookie])) {
            $value = urldecode($PHORUM['args'][$cookie]);
        }

        // Check for session id in form POST data.
        elseif ($PHORUM['use_cookies'] < PHORUM_REQUIRE_COOKIES &&
                  isset($_POST[$cookie])) {
            $value = $_POST[$cookie];
        }

        // Check for session id in form GET data (should rarely happen, but
        // it helps sometimes).
        elseif ($PHORUM['use_cookies'] < PHORUM_REQUIRE_COOKIES &&
                  isset($_GET[$cookie])) {
            $value = $_GET[$cookie];
        }

        // Cookie not found. Continue with the next one.
        else {
            continue;
        }

        // The cookie value is formatted as <user id>:<session id>.
        // Split these into separate parts.
        list($user_id, $sessid) = explode(':', $value, 2);

        // The user_id should be numerical at all times.
        if (!is_numeric($user_id)) continue;

       // Find the data for the session user by its user_id. If the user
       // cannot be found, then the session is destroyed and the
       // anonymous user is setup.
        if ($session_user === NULL) {
            $session_user = phorum_api_user_get($user_id, TRUE);
            if (empty($session_user) ||
                $session_user['active'] != PHORUM_USER_ACTIVE) {
                phorum_api_user_session_destroy($type);
                return FALSE;
            }
        } else {
            // The user_id should be the same for all cookies.
            // If a different user_id is found, then the cookie
            // that we're currently looking at is ignored. It could
            // be an old cookie for a different user.
            if ($session_user['user_id'] != $user_id) {
                continue;
            }
        }

        // Check if the session id from the cookie is valid for the user.
        $valid_session =
            $user_id_from_hook_session ||

            ($cookie == PHORUM_SESSION_LONG_TERM  &&
             !empty($session_user['sessid_lt']) &&
             $session_user['sessid_lt'] == $sessid) ||

            ($cookie == PHORUM_SESSION_SHORT_TERM &&
             !empty($session_user['sessid_st']) &&
             $session_user['sessid_st'] == $sessid) ||

            ($cookie == PHORUM_SESSION_ADMIN &&
             !empty($session_user['sessid_lt']) &&
             md5($session_user['sessid_lt'].$PHORUM['admin_session_salt']) == $sessid);

        // Keep track of valid session cookies.
        if ($valid_session) {
            $check_session[$cookie] = 2;
        }
    }

    // No real cookie found for a long term session? Then we will ignore
    // short term sessions (short term sessions are not implemented for URI
    // authentication) and update the "use_cookies" setting accordingly.
    if ($check_session[PHORUM_SESSION_LONG_TERM] == 2 && ! $real_cookie) {
        $check_session[PHORUM_SESSION_SHORT_TERM] = 0;
        $GLOBALS['PHORUM']['use_cookies'] = PHORUM_NO_COOKIES;
    }

    // ----------------------------------------------------------------------
    // Check if a user session needs to be restored.
    // ----------------------------------------------------------------------

    $do_restore_session = FALSE;
    $do_restore_short_term_session = FALSE;

    if ($type == PHORUM_FORUM_SESSION)
    {
        // Valid long term forum session found.
        if ($check_session[PHORUM_SESSION_LONG_TERM] == 2)
        {
            $do_restore_session = TRUE;

            if ($check_session[PHORUM_SESSION_SHORT_TERM] == 1) {
                // Checked short term session, but no valid session found.
                $do_restore_short_term_session = FALSE;
            } else {
                // Short term session not checked (0) or valid (2).
                $do_restore_short_term_session = TRUE;
            }
        }
    }
    elseif ($type == PHORUM_ADMIN_SESSION)
    {
        // Valid admin session found. Note that the function
        // phorum_api_user_set_active_user() might still reject the user
        // if it's not an admin user (anymore).
        if ($check_session[PHORUM_SESSION_ADMIN] == 2) {
            $do_restore_session = TRUE;
        }
    }

    // ----------------------------------------------------------------------
    // Restore the user session.
    // ----------------------------------------------------------------------

    // No session to restore? Then destroy the session
    // and setup the anonymous user.
    if (! $do_restore_session)
    {
        phorum_api_user_session_destroy($type);
        return FALSE;
    }
    // Restore a user's session.
    else
    {
        // Setup the Phorum user.
        $flags = 0;
        if ($do_restore_short_term_session) $flags |= PHORUM_FLAG_SESSION_ST;
        phorum_api_user_set_active_user($type, $session_user, $flags);

        // Refresh and keep the session alive for the user.
        phorum_api_user_session_create($type);

        return TRUE;
    }
}
// }}}

// {{{ Function: phorum_api_user_session_destroy()
/**
 * Destroy a Phorum user session.
 *
 * This will destroy a Phorum user session and set the active
 * Phorum user to the anonymous user.
 *
 * @param string $type
 *     The type of session to destroy. This must be one of
 *     {@link PHORUM_FORUM_SESSION} or {@link PHORUM_ADMIN_SESSION}.
 *     See the documentation for {@link phorum_api_user_session_create()}
 *     for more information on Phorum user sessions.
 */
function phorum_api_user_session_destroy($type)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // Allow modules to handle destroying a session or to simply fully
    // ignore destroying sessions (for example useful if the hook
    // "user_session_restore" is used to inherit an external session from
    // some 3rd party application). The hook function gets the session
    // type as its argument and can return NULL if the Phorum session
    // destroy function does not have to be run.
    $do_phorum_destroy_session = TRUE;
    if (isset($PHORUM['hooks']['user_session_destroy'])) {
        if (phorum_hook('user_session_destroy', $type) === NULL) {
            $do_phorum_destroy_session = FALSE;
        }
    }

    if ($do_phorum_destroy_session)
    {
        // Destroy session cookie(s). We do not care here if use_cookies is
        // enabled or not. We just want to clean out all that we have here.
        if ($type == PHORUM_FORUM_SESSION) {
            setcookie(
                PHORUM_SESSION_SHORT_TERM, '', time()-86400,
                $PHORUM['session_path'], $PHORUM['session_domain']
            );
            setcookie(
                PHORUM_SESSION_LONG_TERM, '', time()-86400,
                $PHORUM['session_path'], $PHORUM['session_domain']
            );
        } elseif ($type == PHORUM_ADMIN_SESSION) {
            setcookie(
                PHORUM_SESSION_ADMIN, '', time()-86400,
                $PHORUM['session_path'], $PHORUM['session_domain']
            );
        } else trigger_error(
            'phorum_api_user_session_destroy(): Illegal session type: ' .
            htmlspecialchars($type),
            E_USER_ERROR
        );

        // If cookies are not in use, then the long term session is reset
        // to a new value. That way we fully invalidate URI authentication
        // data, so that old URL's won't work anymore. We can only do this
        // if we have an active Phorum user.
        if ($PHORUM['use_cookies'] == PHORUM_NO_COOKIES &&
            $type == PHORUM_FORUM_SESSION &&
            !empty($PHORUM['user']) && !empty($PHORUM['user']['user_id'])) {

            $user = $PHORUM['user'];

            $sessid_lt = md5($user['username'].microtime().$user['password']);
            phorum_api_user_save_raw(array(
                'user_id'   => $user['user_id'],
                'sessid_lt' => $sessid_lt,
            ));
        }
    }

    // Force Phorum to see the anonymous user from here on.
    phorum_api_user_set_active_user(PHORUM_FORUM_SESSION, NULL);
}
// }}}

// ----------------------------------------------------------------------
// Authorization management.
// ----------------------------------------------------------------------

// {{{ Function: phorum_api_user_check_access()
/**
 * Check if a user has certain access right for a single forum.
 *
 * @param integer $permission
 *     The permission to check for. Multiple permissions can be OR-ed
 *     together. The available permissions are:
 *     - {@link PHORUM_USER_ALLOW_READ}
 *     - {@link PHORUM_USER_ALLOW_REPLY}
 *     - {@link PHORUM_USER_ALLOW_EDIT}
 *     - {@link PHORUM_USER_ALLOW_NEW_TOPIC}
 *     - {@link PHORUM_USER_ALLOW_ATTACH}
 *     - {@link PHORUM_USER_ALLOW_MODERATE_MESSAGES}
 *     - {@link PHORUM_USER_ALLOW_MODERATE_USERS}
 *
 * @param mixed $forum_id
 *     Specifies the forum(s) to look at. Available options are:
 *     - The id of the forum for which to check the access
 *     - 0 (zero, the default) to check access for the active forum
 *     - An array of forum_ids to check
 *     - {@link PHORUM_ACCESS_ANY} to check if the user has access rights
 *       for any of the available forums
 *     - {@link PHORUM_ACCESS_LIST} to return a list of forum_ids for which the
 *       user has access rights
 *
 * @param mixed $user
 *     Specifies the user to look at. Available options are:
 *     - 0 (zero, the default) to look at the active Phorum user.
 *     - A full user data array.
 *     - A single user_id.
 *
 * @return mixed
 *     The return value depends on the $forum_id argument that was used:
 *
 *     - Single forum_id , 0 (zero) or {@link PHORUM_ACCESS_ANY}:
 *       return either TRUE (access granted) or FALSE (access denied).
 *
 *     - An array of forum_ids or {@link PHORUM_ACCESS_LIST}:
 *       return an array, containing all forum_ids for which
 *       permission was granted (both keys and values are forum_ids
 *       in this array).
 */
function phorum_api_user_check_access($permission, $forum_id = 0, $user = 0)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // Prepare the array of forum ids to check.
    $forum_access = array();
    $forums = NULL;
    $single_forum_id = NULL;
    // An array of forum ids.
    if (is_array($forum_id)) {
        foreach ($forum_id as $id) $forum_access[$id] = FALSE;
    // Forum id 0 (zero).
    } elseif (empty($forum_id)) {
        $single_forum_id = $PHORUM['forum_id'];
        $forum_access[$PHORUM['forum_id']] = FALSE;
        $forums = array(
            $PHORUM['forum_id'] => array(
                'reg_perms' => $PHORUM['reg_perms'],
                'pub_perms' => $PHORUM['pub_perms']
            )
        );
    // Retrieve a forum access list or access-rights-in-any-forum.
    } elseif ($forum_id == PHORUM_ACCESS_LIST ||
              $forum_id == PHORUM_ACCESS_ANY) {
        $forums = phorum_db_get_forums(0, NULL, $PHORUM['vroot']);
        foreach ($forums as $id => $data) $forum_access[$id] = FALSE;
    // A single forum id.
    } else {
        $single_forum_id = $forum_id;
        $forum_access[$forum_id] = FALSE;
    }

    // Prepare the user to check the access for.
    if (empty($user)) {
        $user = $PHORUM['user'];
    } elseif (!is_array($user)) {
        $user = phorum_api_user_get($user);
    }

    // Inactive users have no permissions at all.
    if (!empty($user['user_id']) && empty($user['active']))
    {
        if ($forum_id == PHORUM_ACCESS_ANY) return FALSE;

        // No further code required. We'll just keep all forum
        // permissions set to FALSE here.
    }
    // Administrators always have permission.
    elseif (!empty($user['user_id']) && !empty($user['admin']))
    {
        if ($forum_id == PHORUM_ACCESS_ANY) return TRUE;

        foreach ($forum_access as $id => $data) {
            $forum_access[$id] = TRUE;
        }
    }
    // For other users, we have to do a full permission lookup.
    else
    {
        // Check the access rights for each forum.
        foreach ($forum_access as $id => $data)
        {
            $perm = NULL;

            // Authenticated user with specific access rights.
            if (!empty($user['user_id']) &&
                isset($user['permissions'][$id])) {
                $perm = $user['permissions'][$id];
            }
            // User for which to use the forum permissions.
            else {
                // Fetch data for the forums, unless we already have that
                // data available.
                if ($forums === NULL) {
                    $forums = phorum_db_get_forums(array_keys($forum_access));
                }

                $key = empty($user['user_id']) ? 'pub_perms' : 'reg_perms';
                if (isset($forums[$id][$key])) {
                    $perm = $forums[$id][$key];
                }
            }

            // Check if the user has the requested permission for the forum.
            if (!empty($perm) && ($perm & $permission) == $permission)
            {
                if ($forum_id == PHORUM_ACCESS_ANY) {
                    return TRUE;
                } else {
                    $forum_access[$id] = TRUE;
                }
            }
        }
    }

    // If we reach this code, then we did not find any forum for the user.
    if ($forum_id == PHORUM_ACCESS_ANY) return FALSE;

    // Return the results.
    if ($single_forum_id !== NULL) {
        // Return either TRUE or FALSE.
        return empty($forum_access[$single_forum_id]) ? FALSE : TRUE;
    } else {
        // Return an array of forums for which permission is granted.
        // Both the keys and values are the forum ids.
        $return = array();
        foreach ($forum_access as $id => $has_permission) {
            if ($has_permission) $return[$id] = $id;
        }
        return $return;
    }
}
// }}}

// {{{ Function: phorum_api_user_check_group_access()
/**
 * @param integer $permission
 *     The permission level to check for. The function will check if the
 *     user has equal or higher permissions for the group(s). The available
 *     permission levels in low-to-high level order are:
 *     - {@link PHORUM_USER_GROUP_SUSPENDED}
 *     - {@link PHORUM_USER_GROUP_UNAPPROVED}
 *     - {@link PHORUM_USER_GROUP_APPROVED}
 *     - {@link PHORUM_USER_GROUP_MODERATOR}
 *
 * @param mixed $group_id
 *     Specifies the group(s) to look at. Available options are:
 *     - The id of the group for which to check the access.
 *     - An array of group_ids to check.
 *     - {@link PHORUM_ACCESS_ANY} to check if the user has access rights
 *       for any of the available groups.
 *     - {@link PHORUM_ACCESS_LIST} to return a list of group_ids for which the
 *       user has access rights.
 *
 * @param mixed $user
 *     Specifies the user to look at. Available options are:
 *     - 0 (zero, the default) to look at the active Phorum user.
 *     - A full user data array.
 *     - A single user_id.
 *
 * @return mixed
 *     The return value depends on the $group_id argument that was used:
 *
 *     - Single group_id or {@link PHORUM_ACCESS_ANY}:
 *       return either TRUE (access granted) or FALSE (access denied).
 *
 *     - An array of group_ids or {@link PHORUM_ACCESS_LIST}:
 *       return an array, containing all groups for which permission was
 *       granted. The keys in this array are group_ids and the values are
 *       group info arrays. These arrays contain the fields "group_id",
 *       "name", "open", "permissions" (which contains an array of
 *       forum permissions, indexed by forum_id), "user_status" (which contains
 *       the group status for the user, i.e. one of the PHORUM_USER_GROUP_*
 *       constants).
 */
function phorum_api_user_check_group_access($permission, $group_id, $user = 0)
{
    $PHORUM = $GLOBALS['PHORUM'];

    // Prepare the user to check the access for.
    if (empty($user)) {
        $user = $PHORUM['user'];
    } elseif (!is_array($user)) {
        $user = phorum_api_user_get($user);
    }

    // Retrieve all the groups for the current user. Admins get all groups.
    if (!empty($user['user_id']) && !empty($user['admin'])) {
        $groups = phorum_db_get_groups();
    } else {
        $usergroups = phorum_db_user_get_groups($user['user_id']);
        $groups = empty($usergroups)
                ? array()
                : phorum_db_get_groups(array_keys($usergroups));
    }

    // Prepare the array of group_ids to check.
    $group_access = array();
    $single_group_id = NULL;
    // An array of group ids.
    if (is_array($group_id)) {
        foreach ($group_id as $id) $group_access[$id] = FALSE;
    // Retrieve a group access list or access-rights-in-any-group.
    } elseif ($group_id == PHORUM_ACCESS_LIST ||
              $group_id == PHORUM_ACCESS_ANY) {
        foreach ($groups as $id => $data) $group_access[$id] = FALSE;
    // A single group id.
    } else {
        $single_group_id = $group_id;
        $group_access[$group_id] = FALSE;
    }

    // Inactive users have no group permissions at all.
    if (!empty($user['user_id']) && empty($user['active']))
    {
        if ($group_id == PHORUM_ACCESS_ANY) return FALSE;

        // No further code required. We'll just keep all group
        // permissions set to FALSE here.
    }
    // Administrators always have full group permission. This will include
    // restrictive access groups for the administrator as well. That is no
    // problem, because the admin status for the user will override any
    // group permission restriction.
    elseif (!empty($user['user_id']) && !empty($user['admin']))
    {
        if ($group_id == PHORUM_ACCESS_ANY) return TRUE;

        foreach ($group_access as $id => $data) {
            $group_access[$id] = $groups[$id];
            $group_access[$id]['user_status'] = PHORUM_USER_GROUP_MODERATOR;
        }
    }
    // For other users, we have to do a full permission lookup.
    else
    {
        foreach ($group_access as $id => $data)
        {
            if (!isset($groups[$id])) continue;

            if ($usergroups[$id] >= $permission)
            {
                if ($group_id == PHORUM_ACCESS_ANY) return TRUE;

                $group_access[$id] = $groups[$id];
                $group_access[$id]['user_status'] = $usergroups[$id];
                continue;
            }
        }
    }

    // If we reach this code, then we did not find any group for the user.
    if ($group_id == PHORUM_ACCESS_ANY) return FALSE;

    // Return the results.
    if ($single_group_id !== NULL) {
        // Return either TRUE or FALSE.
        return empty($group_access[$single_group_id]) ? FALSE : TRUE;
    } else {
        // Return an array of groups for which permission is granted.
        // The keys are group_ids and the values the user's permissions
        // for the groups.
        $return = array();
        foreach ($group_access as $id => $group) {
            if ($group !== FALSE) $return[$id] = $group;
        }
        return $return;
    }
}
// }}}

// {{{ Function: phorum_api_user_list_moderators()
/**
 * Retrieve a list of moderators.
 *
 * @param integer $forum_id
 *     The id of the forum for which to retrieve a list of moderators or
 *     0 (zero, the default) to use the active forum.
 *
 * @param boolean $exclude_admin
 *     If TRUE, then the admin users are kept out of the list. The default
 *     is to include admin users.
 *
 * @param boolean $for_mail
 *     If TRUE, then a list of moderators is created for sending moderator
 *     mail messages. Moderators which have disabled the moderation_email
 *     option will be excluded from the list in this case. The default
 *     is to include all moderators.
 *
 * @return array
 *     An array of moderator users, indexed by user_id.
 */
function phorum_api_user_list_moderators($forum_id = 0, $exclude_admin = FALSE, $for_mail = FALSE)
{
    $PHORUM = $GLOBALS['PHORUM'];

    if (empty($forum_id)) $forum_id = $PHORUM['forum_id'];

    return phorum_db_user_get_moderators($forum_id, $exclude_admin, $for_email);
}
// }}}

?>
