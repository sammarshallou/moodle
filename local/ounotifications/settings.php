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

defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ounotifications',
            new lang_string('pluginname', 'local_ounotifications'));
    $ADMIN->add('mobileapp', $settings);

    // Mobile app feature to restrict courses from web service by shortname.
    $settings->add(new admin_setting_configtextarea('local_ounotifications/upcomingevents',
            get_string('upcomingevents', 'local_ounotifications'),
            get_string('upcomingevents_desc', 'local_ounotifications'), '.*', PARAM_RAW));
}
