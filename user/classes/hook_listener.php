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

namespace core_user;

class hook_listener {
    public static function grant_fast_file(\core_files\hook\grant_fast_file $hook) {
        global $CFG;

        // Allow all user icons to be downloaded via fastfile.php.
        if ($hook->component === 'core_user' && $hook->area === 'icon') {
            // USer icons are available to public only if both these config settings are true
            // (seems weird to me but let's not change it).
            $public = empty($CFG->forcelogin) && empty($CFG->forceloginforprofileimage);
            if ($public || isloggedin()) {
                $hook->grant($public);
            }
        }
    }

}
