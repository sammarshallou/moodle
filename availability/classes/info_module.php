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
 * Class handles conditional availability information for an activity.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_availability;

defined('MOODLE_INTERNAL') || die();

/**
 * Class handles conditional availability information for an activity.
 */
class info_module extends info_base {
    /** @var \cm_info Activity. */
    private $cm;

    /**
     * Constructs with item details.
     *
     * @param \cm_info $cm Course-module object
     */
    public function __construct(\cm_info $cm) {
        parent::__construct($cm->get_course(), $cm->visible, $cm->availability);
        $this->cm = $cm;
    }

    protected function get_thing_name() {
        // Unfortunately we cannot get the cm name at this point. That is because
        // the error in question normally occurs within obtain_dynamic_data when
        // called from within the getter for the name property! PHP won't let
        // us access the property at this point.
        return 'cm ' . $this->cm->id;
    }

    protected function set_in_database($availability) {
        global $DB;
        $DB->set_field('course_modules', 'availability', $availability,
                array('id' => $this->cm->id));
    }

    /**
     * Checks if an activity is visible to the given user.
     *
     * Unlike other checks in the availability system, this check includes the
     * $cm->visible flag. It is equivalent to $cm->uservisible.
     *
     * If you have already checked (or do not care whether) the user has access
     * to the course, you can set $checkcourse to false to save it checking
     * course access.
     *
     * When checking for the current user, you should generally not call
     * this function. Instead, use get_fast_modinfo to get a cm_info object,
     * then simply check the $cm->uservisible flag. This function is intended
     * to obtain that information for a separate course-module object that
     * wasn't loaded with get_fast_modinfo, or for a different user.
     *
     * This function has a performance cost unless the availability system is
     * disabled, and you supply a $cm object with necessary fields, and you
     * don't check course access.
     *
     * @param int|stdClass|cm_info $cmorid Object or id representing activity
     * @param int $userid User id (0 = current user)
     * @param bool $checkcourse If true, checks whether the user has course access
     * @return bool True if the activity is visible to the specified user
     * @throws moodle_exception If the cmid doesn't exist
     */
    public static function is_user_visible($cmorid, $userid = 0, $checkcourse = true) {
        global $USER, $DB, $CFG;

        // Evaluate user id.
        if (!$userid) {
            $userid = $USER->id;
        }

        // If this happens to be already called with a cm_info for the right user
        // then just return uservisible.
        if (($cmorid instanceof cm_info) && $cmorid->get_modinfo()->userid == $userid) {
            return $cm->uservisible;
        }

        // If the $cmorid isn't an object or doesn't have required fields, load it.
        if (is_object($cmorid) && isset($cmorid->course) && isset($cmorid->visible)) {
            $cm = $cmorid;
        } else {
            if (is_object($cmorid)) {
                $cmorid = $cmorid->id;
            }
            $cm = $DB->get_record('course_modules', array('id' => $cmorid), '*', MUST_EXIST);
        }

        // If requested, check user can access the course.
        if ($checkcourse) {
            $coursecontext = \context_course::instance($cm->course);
            if (!is_enrolled($coursecontext, $userid, '', true) &&
                    !has_capability('moodle/course:view', $coursecontext, $userid)) {
                return false;
            }
        }

        // If availability is disabled, then all we need to do is check the visible flag.
        if (!$CFG->enableavailability && $cm->visible) {
            return true;
        }

        // When availability is enabled, access can depend on 3 things:
        // 1. $cm->visible
        // 2. $cm->availability
        // 3. $section->availability (for activity section and possibly for
        //    parent sections)
        // As a result we cannot take short cuts any longer and must get
        // standard modinfo.
        $modinfo = get_fast_modinfo($cm->course, $userid);
        return $modinfo->get_cm($cm->id)->uservisible;
    }
}
