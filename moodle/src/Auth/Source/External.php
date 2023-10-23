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
 * SimplSAMLphp module file
 *
 * @package    auth_samlidp
 * @copyright  2017 Enovation Solutions (http://enovation.ie)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

################################################################################
/*
 * In SimpleSAMLphp, in config/authsources.php, SIMILAR MUST BE ADDED:
 *
 *   'moodle-userpass' => array(
 *       'moodle:External',
 *       'moodle_coderoot' => '/var/www/ticket/moodle314/www',
 *       'logout_url' => 'https://10.0.0.28/moodle314/auth/samlidp/logout.php',  // plugin's logout page
 *       'login_url' => 'https://10.0.0.28/moodle314/login/index.php',           // standard Moodle login page
 *       'cookie_name' => 'MoodleSAMLIDPSessionID',
 *   ),
 */
################################################################################

class sspmod_moodle_Auth_Source_External extends SimpleSAML_Auth_Source {
    const STATE_IDENT = 'moodle:External';
    private $config;

    public function __construct($info, $config) {
        assert(is_array($info));
        assert(is_array($config));

        parent::__construct($info, $config);
        if (!isset($config{'cookie_name'})) {
            throw new SimpleSAML_Error_Exception('Misconfiguration in authsources');    # in the moodle part in config/authsources.php there must be 'cookie_name' setting
        }
        $ssp_config = SimpleSAML_Configuration::getInstance();
        $config{'cookie_path'} = $ssp_config->getValue('session.cookie.path');
        $config{'cookie_salt'} = $ssp_config->getValue('secretsalt');
        $this->config = $config;
    }

    public function authenticate (&$state) {
        $user = $this->getUser();
        if ($user) {
            # user authenticated, nothing to do
            $state['Attributes'] = $user;
            return;
        } else {
            # redirect to a login page
            $state['moodle:AuthID'] = $this->authId;
            $state_id = SimpleSAML_Auth_State::saveState($state, self::STATE_IDENT);
            $return_to = SimpleSAML_Module::getModuleURL('moodle/resume.php', array('State' => $state_id));
            $auth_page = $this->config{'login_url'} . '?ReturnTo=' . $return_to;
            SimpleSAML_Utilities::redirect($auth_page, array('ReturnTo' => $return_to));
        }
    }

    public static function resume() {
        if (!isset($_REQUEST['State'])) {
            throw new SimpleSAML_Error_BadRequest('Missing "State" parameter.');
        }
        $state_id = (string)$_REQUEST['State'];

        $state = SimpleSAML_Auth_State::loadState($state_id, self::STATE_IDENT);
        $source = SimpleSAML_Auth_Source::getById($state['moodle:AuthID']);
        if ($source === NULL) {
            throw new SimpleSAML_Error_Exception('Could not find authentication source with id ' . $state[self::AUTHID]);
        }

        if (! ($source instanceof self)) {
            throw new SimpleSAML_Error_Exception('Authentication source type changed.');
        }

        $user = $source->getUser($state);
        if ($user === NULL) {
            throw new SimpleSAML_Error_Exception('User not authenticated after login page.');
        }
        $state['Attributes'] = $user;

        SimpleSAML_Auth_Source::completeAuth($state);
        exit(); # should never reach here
    }

    private function getUser() {
        $uid = 0;
        if (isset($_COOKIE{$this->config{'cookie_name'}}) && $_COOKIE{$this->config{'cookie_name'}}) {
            $str_cookie = $_COOKIE{$this->config{'cookie_name'}};
            # cookie created by: "setcookie($cookieName{'cookie_name'}, hash_hmac('sha1', $salt.$account->uid, $salt).':'.$uid, 0, $sspConfig->getValue('session.cookie.path'));"
            # in auth/samlidp/auth.php in Moodle
            $arr_cookie = explode(':', $str_cookie);

            if ((isset($arr_cookie[0]) && $arr_cookie[0])
                && (isset($arr_cookie[1]) && $arr_cookie[1])
            ) {
                # make sure no one manipulated the hash or the uid in the cookie before we trust the uid
                if (hash_hmac('sha1', $this->config{'cookie_salt'}.$arr_cookie[1], $this->config{'cookie_salt'}) == $arr_cookie[0]) {
                    $uid = (int)$arr_cookie[1];
                } else {
                    throw new SimpleSAML_Error_Exception('Cookie hash invalid.');
                }
            }
        }

        # our cookie must be removed here
        if (isset($_COOKIE{$this->config{'cookie_name'}})) {
            setcookie($this->config{'cookie_name'}, "", time() - 3600, $this->config{'cookie_path'});
        }

        if ($uid) {
            # bootstrap moodle
            global $CFG, $DB;
            define('CLI_SCRIPT', true);
            define('WEB_CRON_EMULATED_CLI', 'defined');
            $configphp = $this->config{'moodle_coderoot'}."/config.php";
            $userlib = $this->config{'moodle_coderoot'}."/user/profile/lib.php";
            if (file_exists($configphp)) {
                require_once($configphp);
            } else {
                throw new SimpleSAML_Error_Exception('Moodle app instantiation failure: cannot require()' . $configphp);
            }

            if (file_exists($userlib)) {
                require_once($userlib);
            } else {
                throw new SimpleSAML_Error_Exception('Moodle app instantiation failure: cannot require()' . $userlib);
            }

            # query for a user
            $user = $DB->get_record('user', array('id' => $uid));
            $profile_fields = profile_user_record($user->id, false);

            # simplesaml is somehow expecting a very weird structure in attributes ($user)
            # we also don't reveal user password, auth method, secret
            if ($user) {
                unset($user->auth);
                unset($user->password);
                unset($user->secret);
                $user->uid = $user->id;     # i dont believe this is strictly necessary. just nice-to-have
                foreach ((array)$user as $param => $value) {
                    $userattr{$param} = array($value);
                }
                foreach ($profile_fields as $param => $value) {
                    $userattr{'profile_field_'.$param} = array($value);
                }
            }
            return $userattr;
        } else {
            return NULL;
        }
        return NULL;
    }

    public function logout (&$state) {
        if (!session_id()) {
            session_start();
        }

        if (isset($_COOKIE{$this->config{'cookie_name'}})) {
            setcookie($this->config{'cookie_name'}, "", time() - 3600, $this->config{'cookie_path'});
        }
        session_destroy();

        $logout_url = $this->config{'logout_url'};
        if (!empty($state['ReturnTo'])) {
            $logout_url .= '?ReturnTo=' . $state['ReturnTo'];
        }

        SimpleSAML_Utilities::redirect($logout_url);
        die();
    }
}
