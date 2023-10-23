<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Authentication Plugin: SAML IdP
 *
 * Allows Moodle to act as an IdP. Reqiures standalone SimpleSAMLphp properly configured
 *
 * @package    auth_samlidp
 * @copyright  2017 Enovation Solutions (http://enovation.ie)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/authlib.php');

class auth_plugin_samlidp extends auth_plugin_base {

    const COMPONENT_NAME = 'auth_samlidp';
    const AUTH_COOKIE_DEFAULT = 'MoodleSAMLIDPSessionID';

    private $simplesamlAutoloadPhp;

    /**
     * Constructor.
     */
    public function __construct() {
        global $CFG;
        $this->authtype = 'samlidp';
        $this->config = get_config(self::COMPONENT_NAME);
        $this->simplesamlAutoloadPhp = $this->config->simplesaml_coderoot.'/lib/_autoload.php';
    }

    /**
     * Saves $_GET{'ReturnTo'}
     *
     * @param void
     * @return void
     */
    public function loginpage_hook () {
        $returnto = optional_param('ReturnTo', '', PARAM_URL);
        if ($returnto) {
            if (isloggedin() and !isguestuser()) {
                # this is a consequent return to Moodle via SAML
                # it needs to be handled a usual SAML way - create a cookie and redirect to the return address
                global $USER;
                $this->set_cookie($USER);
                header('Location: '. $returnto);
                exit();
            } else {
                global $SESSION;
                $SESSION->samlurl = $returnto;  # record $returnto as it will not be available later
            }
        }
    }

    /**
     * Sets a module-specific cookie to send a user ID to SimpleSAMLphp
     * called from loginpage_hook() and user_authenticated_hook()
     *
     * @param stdClass $user
     * @return void
     */
    private function set_cookie ($user) {
        if (file_exists($this->simplesamlAutoloadPhp)) {
            require_once($this->simplesamlAutoloadPhp);
            $sspConfig = SimpleSAML_Configuration::getInstance();
            $sspAuthsources = SimpleSAML_Configuration::getConfig('authsources.php');
            $cookieName = $sspAuthsources->getValue($this->config->simplesaml_authsource);
            $uid = $user->id;
            if ($cookieName && isset($cookieName{'cookie_name'}) && $cookieName{'cookie_name'}) {
                $salt = $sspConfig->getValue('secretsalt');
                setcookie($cookieName{'cookie_name'}, hash_hmac('sha1', $salt.$uid, $salt).':'.$uid, 0, $sspConfig->getValue('session.cookie.path'));
            } else {
                $this->report_misconfigured_authsouces();
            }
        } else {
            $this->report_missing_autoload();
        }
    }

    /**
     * If configured properly, sets an authentication cookie, encrypts a user ID into the cookie value
     *
     * @param object $user user object, later used for $USER
     * @param string $username (with system magic quotes)
     * @param string $password plain text password (with system magic quotes)
     * @return void
     */
    public function user_authenticated_hook (&$user, $username, $password) {
        global $SESSION, $USER;

        $this->set_cookie($user);

        if (isset($SESSION->samlurl) && $SESSION->samlurl) {
            $samlurl = $SESSION->samlurl;
            unset($SESSION->samlurl);
            complete_user_login($user);     # need to run it here otherwise the moodle user is not really logged in
            header('Location: '. $samlurl);
            exit();                         # need to exit as otherwise moodle takes control and redirects to own /
        }
    }

    /**
     * If configured properly, destroys own auth cookie, optionally redirects to the ReturnTo URL
     *
     * @param stdClass $user clone of USER object before the user session was terminated
     * @return void
     */
    public function postlogout_hook($user) {
        global $OUTPUT;
        if (file_exists($this->simplesamlAutoloadPhp)) {
            $returnto = optional_param('ReturnTo', '', PARAM_URL);
            require_once($this->simplesamlAutoloadPhp);
            $sspConfig = SimpleSAML_Configuration::getInstance();
            $sspAuthsources = SimpleSAML_Configuration::getConfig('authsources.php');
            $cookieName = $sspAuthsources->getValue($this->config->simplesaml_authsource);
            if ($cookieName && isset($cookieName{'cookie_name'}) && $cookieName{'cookie_name'}) {

                setcookie($cookieName{'cookie_name'}, '',  time() - 3600, $sspConfig->getValue('session.cookie.path'));

                if (ini_get('session.use_cookies')) {
                    $params = session_get_cookie_params();
                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $params['path'], $params['domain'],
                        $params['secure'], $params['httponly']
                    );
                }
                if ($returnto) {
                    header('Location: '.$returnto);
                    exit();
                }
            } else {
                $this->report_misconfigured_authsouces();
            }
        } else {
            $this->report_missing_autoload();
        }
    }

    /**
     * Reports a configuration error into http server error.log
     *
     * @return void
     */
    private function report_misconfigured_authsouces () {
        $msg = sprintf("Misconfigured SimpleSAMLphp IdP (missing configuration block for '%s' in authsources.php, or 'cookie_name' entry in the block)
            or incorrect SAML IdP Moodle module configuration (wrong simplesaml_authsource)", $this->config->simplesaml_authsource);
        trigger_error($msg, E_USER_WARNING);
    }

    /**
     * Reports a configuration error into http server error.log
     *
     * @return void
     */
    private function report_missing_autoload () {
        trigger_error("Misconfigured SAML IDP plugin: cannot find a path to SimpleSAMLphp _autoload.php. The current path is incorrect: ".
                $this->simplesamlAutoloadPhp, E_USER_WARNING);
    }


    /**
     * Returns true if the username and password work and false if they are
     * wrong or don't exist.
     *
     * @param string $username The username
     * @param string $password The password
     * @return bool Authentication success or failure.
     */
    public function user_login($username, $password) {
        return false;
    }

    /**
     * Called when the user record is updated.
     * Modifies user in external database. It takes olduser (before changes) and newuser (after changes)
     * compares information saved modified information to external db.
     *
     * @param stdClass $olduser     Userobject before modifications
     * @param stdClass $newuser     Userobject new modified userobject
     * @return boolean result
     *
     */
    public function user_update($olduser, $newuser) {
        return false;
    }

    /**
     * A chance to validate form data, and last chance to
     * do stuff before it is inserted in config_plugin
     *
     * @param stfdClass $form
     * @param array $err errors
     * @return void
     */
    public function validate_form($form, &$err) {
    }

    /**
     * Returns true if this authentication plugin is "internal".
     *
     * Internal plugins use password hashes from Moodle user table for authentication.
     *
     * @return bool
     */
    public function is_internal() {
        return false;
    }

    /**
     * Returns false if this plugin is enabled but not configured.
     *
     * @return bool
     */
    public function is_configured() {
        if (!empty($this->config->simplesaml_authsource) && !empty($this->config->simplesaml_coderoot)) {
            return true;
        }
        return false;
    }

    /**
     * Indicates if moodle should automatically update internal user
     * records with data from external sources using the information
     * from auth_plugin_base::get_userinfo().
     *
     * @return bool true means automatically copy data from ext to user table
     */
    public function is_synchronised_with_external() {
        return false;
    }

    /**
     * Returns true if this authentication plugin can change the user's
     * password.
     *
     * @return bool
     */
    public function can_change_password() {
        return false;
    }

    /**
     * Returns the URL for changing the user's pw, or empty if the default can
     * be used.
     *
     * @return moodle_url
     */
    public function change_password_url() {
        return null;
    }

    /**
     * Returns true if plugin allows resetting of internal password.
     *
     * @return bool
     */
    public function can_reset_password() {
        return false;
    }
}
