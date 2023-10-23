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
 * Privacy Subsystem implementation for auth_samlidp.
 *
 * @package    auth_samlidp
 * @copyright  2018 Enovation Solutions
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace auth_samlidp\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\contextlist;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy Subsystem implementation for auth_samlidp.
 *
 */
class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $items a reference to the collection to use to store the metadata.
     * @return collection the updated collection of metadata items.
     */
    public static function get_metadata(collection $items) : collection {
        global $DB;

        // Standard profile fields are more-less fixed, so here we hardcode them
        // auth, password, secret are not sent, as specified in moodle/lib/Auth/Source/External.php in getUser()
        $exportedfields = array (
            'id' => 'privacy:metadata:id',
#            'auth' => 'privacy:metadata:auth',
            'confirmed' => 'privacy:metadata:confirmed',
            'policyagreed' => 'privacy:metadata:policyagreed',
            'deleted' => 'privacy:metadata:deleted',
            'suspended' => 'privacy:metadata:suspended',
            'mnethostid' => 'privacy:metadata:mnethostid',
            'username' => 'privacy:metadata:username',
#            'password' => 'privacy:metadata:password',
            'idnumber' => 'privacy:metadata:idnumber',
            'firstname' => 'privacy:metadata:firstname',
            'lastname' => 'privacy:metadata:lastname',
            'email' => 'privacy:metadata:email',
            'emailstop' => 'privacy:metadata:emailstop',
            'icq' => 'privacy:metadata:icq',
            'skype' => 'privacy:metadata:skype',
            'yahoo' => 'privacy:metadata:yahoo',
            'aim' => 'privacy:metadata:aim',
            'msn' => 'privacy:metadata:msn',
            'phone1' => 'privacy:metadata:phone1',
            'phone2' => 'privacy:metadata:phone2',
            'institution' => 'privacy:metadata:institution',
            'department' => 'privacy:metadata:department',
            'address' => 'privacy:metadata:address',
            'city' => 'privacy:metadata:city',
            'country' => 'privacy:metadata:country',
            'lang' => 'privacy:metadata:lang',
            'calendartype' => 'privacy:metadata:calendartype',
            'theme' => 'privacy:metadata:theme',
            'timezone' => 'privacy:metadata:timezone',
            'firstaccess' => 'privacy:metadata:firstaccess',
            'lastaccess' => 'privacy:metadata:lastaccess',
            'lastlogin' => 'privacy:metadata:lastlogin',
            'currentlogin' => 'privacy:metadata:currentlogin',
            'lastip' => 'privacy:metadata:lastip',
#            'secret' => 'privacy:metadata:secret',
            'picture' => 'privacy:metadata:picture',
            'url' => 'privacy:metadata:url',
            'description' => 'privacy:metadata:description',
            'descriptionformat' => 'privacy:metadata:descriptionformat',
            'mailformat' => 'privacy:metadata:mailformat',
            'maildigest' => 'privacy:metadata:maildigest',
            'maildisplay' => 'privacy:metadata:maildisplay',
            'autosubscribe' => 'privacy:metadata:autosubscribe',
            'trackforums' => 'privacy:metadata:trackforums',
            'timecreated' => 'privacy:metadata:timecreated',
            'timemodified' => 'privacy:metadata:timemodified',
            'trustbitmask' => 'privacy:metadata:trustbitmask',
            'imagealt' => 'privacy:metadata:imagealt',
            'lastnamephonetic' => 'privacy:metadata:lastnamephonetic',
            'firstnamephonetic' => 'privacy:metadata:firstnamephonetic',
            'middlename' => 'privacy:metadata:middlename',
            'alternatename' => 'privacy:metadata:alternatename',
        );
        $profilefields = $DB->get_records('user_info_field', array());
        foreach ($profilefields as $profilefield) {
            $exportedfields{'profile_field_'.$profilefield->shortname} = 'privacy:metadata:profilefield';
        }
        $items->add_external_location_link(
            'samlidp_provider',
            $exportedfields,
            'privacy:metadata:externalpurpose'
        );
        return $items;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid the userid.
     * @return contextlist - empty contextlist, the plugin does not store user data
     */
    public static function get_contexts_for_userid(int $userid) : contextlist {
        return new contextlist();
    }

    /**
     * Export nothing as the plugin does not store user data
     *
     * @param approved_contextlist $contextlist a list of contexts approved for export.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * Delete nothing as the plugin does not store user data
     *
     * @param \context $context the context to delete in.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Delete nothing as the plugin does not store user data
     *
     * @param approved_contextlist $contextlist a list of contexts approved for deletion.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }
}
