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
 * Base class for a single availability condition. All condition types must
 * extend this class.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_availability;

defined('MOODLE_INTERNAL') || die();

/**
 * Base class for a single availability condition. All condition types must
 * extend this class.
 *
 * The structure of a condition in JSON input data is:
 *
 * { type:'date', ... }
 *
 * where 'date' is the name of the plugin (availability_date in this case) and
 * ... is arbitrary extra data to be used by the plugin.
 *
 * Conditions require a constructor with one parameter: $structure. This will
 * contain all the JSON data for the condition. If the structure of the data
 * is incorrect (e.g. missing fields) then the constructor may throw a
 * coding_exception. However, the constructor should cope with all data that
 * was previously valid (e.g. if the format changes, old data may still be
 * present in a restore, so there should be a default value for any new fields
 * and old ones should be handled correctly).
 */
abstract class condition extends node {

    /**
     * Determines whether a particular item is currently available
     * according to this availability condition.
     *
     * The $not option is potentially confusing. This option always indicates
     * the 'real' value of NOT. For example, a condition inside a 'NOT AND'
     * group will get this called with $not = true, but if you put another
     * 'NOT OR' group inside the first group, then a condition inside that will
     * be called with $not = false. We need to use the real values, rather than
     * the more natural use of the current value at this point inside the tree,
     * so that the information displayed to users makes sense.
     *
     * @param bool $not Set true if we are inverting the condition
     * @param stdClass $course Moodle course object
     * @param bool $grabthelot Performance hint: if true, caches information
     *   required for all course-modules, to make the front page and similar
     *   pages work more quickly (works only for current user)
     * @param int $userid User ID to check availability for
     * @param course_modinfo $modinfo Modinfo for course
     * @return bool True if available
     */
    public abstract function is_available(
            $not, $course, $grabthelot, $userid, \course_modinfo $modinfo);

    public function check_available($not,
            $course, $grabthelot, $userid, \course_modinfo $modinfo) {
        // Use is_available, and we always display (at this stage).
        $allow = $this->is_available($not, $course, $grabthelot, $userid, $modinfo);
        return new result($allow, $this);
    }

    public function is_available_for_all($not = false) {
        // Default is that all conditions may make something unavailable.
        return false;
    }

    /**
     * Display a representation of this condition (used for debugging).
     *
     * @return string Text representation of condition
     */
    public function __toString() {
        return '{' . $this->get_type() . ':' . $this->get_debug_string() . '}';
    }

    /**
     * @return string The type name for this plugin
     */
    protected function get_type() {
        return preg_replace('~^availability_(.*?)\\\\condition$~', '$1', get_class($this));
    }

    /**
     * Obtains a string describing this restriction (whether or not
     * it actually applies). Used to obtain information that is displayed to
     * students if the activity is not available to them, and for staff to see
     * what conditions are.
     *
     * The $full parameter can be used to distinguish between 'staff' cases
     * (when displaying all information about the activity) and 'student' cases
     * (when displaying only conditions they don't meet).
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param stdClass $course Moodle course object
     * @param course_modinfo $modinfo Modinfo for course
     * @return string Information string (for admin) about all restrictions on
     *   this item
     */
    public abstract function get_description(
            $full, $not, $course, \course_modinfo $modinfo);

    /**
     * Obtains a string describing this restriction, used when there is only
     * a single restriction to display. (I.e. this provides a 'short form'
     * rather than showing in a list.)
     *
     * Default behaviour sticks the prefix text, normally displayed above
     * the list, in front of the standard get_description call.
     *
     * @param bool $full Set true if this is the 'full information' view
     * @param bool $not Set true if we are inverting the condition
     * @param stdClass $course Moodle course object
     * @param course_modinfo $modinfo Modinfo for course
     * @return string Information string (for admin) about all restrictions on
     *   this item
     */
    public function get_standalone_description(
            $full, $not, $course, \course_modinfo $modinfo) {
        return get_string('list_root_and', 'availability') . ' ' .
                $this->get_description($full, $not, $course, $modinfo);
    }

    /**
     * Obtains a representation of the options of this condition as a string,
     * for debugging.
     *
     * @return string Text representation of parameters
     */
    protected abstract function get_debug_string();

    public function update_dependency_id($table, $oldid, $newid) {
        // By default, assumes there are no dependent ids.
        return false;
    }
}
