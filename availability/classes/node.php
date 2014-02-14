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
 * Node (base class) used to construct a tree of availability conditions.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_availability;

defined('MOODLE_INTERNAL') || die();

/**
 * Node (base class) used to construct a tree of availability conditions.
 */
abstract class node {
    /**
     * Determines whether this particular item is currently available
     * according to the availability criteria.
     *
     * - This does not include the 'visible' setting (i.e. this might return
     *   true even if visible is false); visible is handled independently.
     * - This does not take account of the viewhiddenactivities capability.
     *   That should apply later.
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
     * @return result Availability check result
     */
    public abstract function check_available($not,
            $course, $grabthelot, $userid, \course_modinfo $modinfo);

    /**
     * Checks whether this condition is actually going to be available for
     * all users under normal circumstances.
     *
     * Normally, if there are any conditions, then it may be hidden. However
     * in the case of date conditions there are some conditions which will
     * definitely not result in it being hidden for anyone.
     *
     * @param bool $not Set true if we are inverting the condition
     * @return bool True if condition will return available for everyone
     */
    public abstract function is_available_for_all($not = false);

    /**
     * Saves tree data back to a structure object.
     *
     * @return stdClass Structure object (ready to be made into JSON format)
     */
    public abstract function save();

    /**
     * Updates this node if it contains any references (dependencies) to the
     * given table and id.
     *
     * @param string $table Table name e.g. 'course_modules'
     * @param int $oldid Previous ID
     * @param int $newid New ID
     * @return bool True if it changed, otherwise false
     */
    public abstract function update_dependency_id($table, $oldid, $newid);
}
