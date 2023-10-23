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
 * Admin settings and defaults.
 *
 * @package    auth_samlidp
 * @copyright  2018 Enovation Solutions (http://enovation.ie)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {

    // Path to SimpleSAMLphp code  root
    $settings->add(new admin_setting_configtext('auth_samlidp/simplesaml_coderoot',
            get_string('coderoot_key', 'auth_samlidp'),
            get_string('coderoot_help', 'auth_samlidp'), '', PARAM_PATH, 48));

    // authsource
    $settings->add(new admin_setting_configtext('auth_samlidp/simplesaml_authsource',
            get_string('authsource_key', 'auth_samlidp'),
            get_string('authsource_help', 'auth_samlidp'), 'moodle-userpass', PARAM_ALPHANUMEXT));

}
