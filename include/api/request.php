<?php
////////////////////////////////////////////////////////////////////////////////
//                                                                            //
//   Copyright (C) 2009  Phorum Development Team                              //
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
 * This script implements functions for processing page requests.
 *
 * @package    PhorumAPI
 * @subpackage Request
 * @copyright  2009, Phorum Development Team
 * @license    Phorum License, http://www.phorum.org/license.txt
 */

if (!defined('PHORUM')) return;

// {{{ Function: phorum_api_request_parse()
/**
 * Parse a Phorum page request.
 *
 * This will handle a couple of tasks for parsing Phorum requests: 
 *
 * - When PHP magic quotes are enabled, the automatically added
 *   slashes are stripped from the request data.
 * - The "parse_request" hook is called.
 * - The $_SERVER["QUERY_STRING"] or $PHORUM_CUSTOM_QUERY_STRING 
 *   (a global variable that can be set to override the standard
 *   QUERY_STRING) is parsed. The request variables are stored
 *   in $PHORUM["args"].
 * - For the file download script, $_SERVER['PATH_INFO'] might be
 *   used to set the file to download. If this is the case, then
 *   this path info is parsed into standard Phorum arguments.
 * - If a forum_id is available in the request, then it is stored
 *   in $PHORUM['forum_id'].
 */
function phorum_api_request_parse()
{
    global $PHORUM;
    $phorum = Phorum::API();

    // Thanks a lot for magic quotes :-/
    // In PHP6, magic quotes are (finally) removed, so we have to check for
    // the get_magic_quotes_gpc() function here. The "@" is for suppressing
    // deprecation warnings that are spawned by PHP 5.3 and higher when
    // using the get_magic_quotes_gpc() function.
    if (function_exists('get_magic_quotes_gpc') && @get_magic_quotes_gpc() &&
        (count($_POST) || count($_GET))) {

        foreach ($_POST as $key => $value) {
            if (!is_array($value)) {
                $_POST[$key] = stripslashes($value);
            } else {
                $_POST[$key] = phorum_api_request_stripslashes($value);
            }
        }
        foreach ($_GET as $key => $value) {
            if (!is_array($value)) {
                $_GET[$key] = stripslashes($value);
            } else {
                $_GET[$key] = phorum_api_request_stripslashes($value);
            }
        }
    }

    /*
     * [hook]
     *     parse_request
     *
     * [description]
     *     This hook gives modules a chance to tweak the request environment,
     *     before Phorum parses and handles the request data. For tweaking the
     *     request environment, some of the options are:
     *     <ul>
     *       <li>
     *         Changing the value of <literal>$_REQUEST["forum_id"]</literal>
     *         to override the used forum_id.
     *       </li>
     *       <li>
     *         Changing the value of <literal>$_SERVER["QUERY_STRING"]</literal>
     *         or setting the global override variable
     *         <literal>$PHORUM_CUSTOM_QUERY_STRING</literal> to feed Phorum a
     *         different query string than the one provided by the webserver.
     *       </li>
     *     </ul>
     *     Tweaking the request data should result in data that Phorum can handle.
     *
     * [category]
     *     Request initialization
     *
     * [when]
     *     Right before Phorum runs the request parsing code in
     *     <filename>common.php</filename>.
     *
     * [input]
     *     No input.
     *
     * [output]
     *     No output.
     *
     * [example]
     *     <hookcode>
     *     function phorum_mod_foo_parse_request()
     *     {
     *         // Override the query string.
     *         global $PHORUM_CUSTOM_QUERY_STRING
     *         $PHORUM_CUSTOM_QUERY_STRING = "1,some,phorum,query=string";
     *
     *         // Override the forum_id.
     *         $_REQUEST['forum_id'] = "1234";
     *     }
     *     </hookcode>
     */
    if (isset($PHORUM["hooks"]["parse_request"])) {
        $phorum->modules->hook("parse_request");
    }

    // Get the forum_id if set using a POST or GET parameter.
    if (isset($_POST['forum_id']) && is_numeric($_POST['forum_id'])) {
        $PHORUM['forum_id'] = (int) $_POST['forum_id'];
    } elseif (isset($_GET['forum_id']) && is_numeric($_GET['forum_id'])) {
        $PHORUM['forum_id'] = (int) $_GET['forum_id'];
    }

    // Look for and parse the QUERY_STRING or custom query string in
    // $PHORUM_CUSTOM_QUERY_STRING..
    // For the admin environment, we don't handle this request handling step.
    // The admin scripts use $_POST and $_GET instead of $PHORUM['args'].
    if (!defined("PHORUM_ADMIN") &&
        (isset($_SERVER["QUERY_STRING"]) ||
         isset($GLOBALS["PHORUM_CUSTOM_QUERY_STRING"]))) {

        // Standard GET request parameters.
        if (strpos($_SERVER["QUERY_STRING"], "&") !== FALSE)
        {
            $PHORUM["args"] = $_GET;
        }
        // Phorum formatted parameters. Phorum formatted URLs do not
        // use the standard GET parameter schema. Instead, parameters
        // are added comma separated to the URL.
        else
        {
            $Q_STR = empty($GLOBALS["PHORUM_CUSTOM_QUERY_STRING"])
                   ? $_SERVER["QUERY_STRING"]
                   : $GLOBALS["PHORUM_CUSTOM_QUERY_STRING"];

            // Ignore stuff past a # (HTML anchors).
            if (strstr($Q_STR, '#')) {
                list($Q_STR, $other) = explode('#', $Q_STR, 2);
            }

            // Explode the query string on commas.
            $PHORUM['args'] = $Q_STR == '' ? array() : explode(',', $Q_STR);

            // Check for any named parameters. These are parameters that
            // are added to the URL in the "name=value" form.
            if (strstr($Q_STR, "=" ))
            {
                foreach($PHORUM['args'] as $key => $arg)
                {
                    // If an arg has an =, then create an element in the
                    // argument array with the left part as the key and
                    // the right part as the value.
                    if (strstr($arg, '='))
                    {
                        list($var, $value) = explode('=', $arg, 2);

                        // Get rid of the original numbered arg.
                        unset($PHORUM["args"][$key]);

                        // Add the named arg
                        $PHORUM['args'][$var] = $value;
                    }
                }
            }
        }

        // Handle path info based URLs for the file downloading script.
        if (phorum_page == 'file' &&
            !empty($_SERVER['PATH_INFO']) &&
            preg_match('!^/(download/)?(\d+)/(\d+)/!', $_SERVER['PATH_INFO'], $m)) {
            $PHORUM['args']['file'] = $m[3];
            $PHORUM['args'][0] = $PHORUM['forum_id'] = $m[2];
            $PHORUM['args']['download'] = empty($m[1]) ? 0 : 1;
        }

        // Set the active forum_id if not already set by a forum_id
        // request parameter, when the forum_id is passed as the first
        // Phorum request parameter.
        if (empty($PHORUM['forum_id']) && isset($PHORUM['args'][0])) {
            $PHORUM['forum_id'] = (int) $PHORUM['args'][0];
        }
    }
}
// }}}

// {{{ Function: phorum_api_request_stripslashes()
/**
 * Recursively remove slashes from array elements.  
 *
 * @param array $array
 *     The data array to modify.
 *
 * @return array
 *     The modified data array.
 */
function phorum_api_request_stripslashes($array)
{
    if (!is_array($array)) {
        return $array;
    } else {
        foreach($array as $key => $value) {
            if (!is_array($value)) {
                $array[$key] = stripslashes($value);
            } else {
                $array[$key] = phorum_api_request_stripslashes($value);
            }
        }
    }
    return $array;
}
// }}}

// {{{ Function: phorum_api_request_check_token()
/**
 * Setup and check posting tokens for form POST requests.
 *
 * For protecting forms against CSRF attacks, a signed posting token
 * is utilized. This posting token must be included in the POST request.
 * Without the token, Phorum will not accept the POST data. 
 *
 * This function will check whether we are handling a POST request.
 * If yes, then check if an anti-CSRF token is provided in the POST data.
 * If no token is available or if the token does not match the expected
 * token, then the POST request is rejected.
 *
 * As a side effect, the required token is added to the {POST_VARS}
 * template variable. This facilitates protecting scripts. As
 * long as the template variable is added to the <form> for the
 * script, it will be automatically protected.
 *
 * @param string $target_page
 *     The page for which to check a posting token. When no target
 *     page is provided, then the constant "phorum_page" is used instead.
 *
 * @return string
 *     The expected posting token.
 */
function phorum_api_request_check_token($target_page = NULL)
{
    global $PHORUM;
    $phorum = Phorum::API();

    if ($target_page === NULL) $target_page = phorum_page;

    // Generate the posting token.
    $posting_token = md5(
        ($target_page !== NULL ? $target_page : phorum_page) . '/' .
        (
          $PHORUM['user']['user_id']
          ? $PHORUM['user']['password'].'/'.$PHORUM['user']['sessid_lt']
          : (
              isset($_SERVER['HTTP_USER_AGENT'])
              ? $_SERVER['HTTP_USER_AGENT']
              : 'unknown'
            )
        ) . '/' .
        $PHORUM['private_key']
    );

    // Add the posting token to the {POST_VARS}.
    $PHORUM['DATA']['POST_VARS'] .=
        "<input type=\"hidden\" name=\"posting_token:$target_page\" " .
        "value=\"$posting_token\"/>\n";

    // Check the posting token if a form post is done.
    if (!empty($_POST))
    {
        if (!isset($_POST["posting_token:$target_page"]) ||
            $_POST["posting_token:$target_page"] != $posting_token) {
            $PHORUM['DATA']['ERROR'] =
                'Possible hack attempt detected. ' .
                'The posted form data was rejected.';
            phorum_build_common_urls();
            $phorum->output("message");
            exit();
        }
    }

    return $posting_token;
}
// }}}

?>
