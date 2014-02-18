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
 * Condition main class.
 *
 * @package availability_group
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_group;

defined('MOODLE_INTERNAL') || die();

/**
 * Condition main class.
 *
 * @package availability_group
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var array Array from group id => name */
    protected static $groupnames = array();

    /** @var int ID of group that this condition requires, or 0 = any group */
    protected $groupid;

    /**
     * Constructor.
     *
     * @param stdClass $structure Data structure from JSON decode
     * @throws coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        // Get group id.
        if (!property_exists($structure, 'id')) {
            $this->groupid = 0;
        } else if (is_int($structure->id)) {
            $this->groupid = $structure->id;
        } else {
            throw new \coding_exception('Invalid ->id for group condition');
        }
    }

    public function save() {
        $result = (object)array('type' => 'group');
        if ($this->groupid) {
            $result->id = $this->groupid;
        }
        return $result;
    }

    public function is_available($not, $course, $grabthelot, $userid, \course_modinfo $modinfo) {
        $context = \context_course::instance($course->id);
        $allow = true;
        if (!has_capability('moodle/site:accessallgroups', $context, $userid)) {
            // Get all groups the user belongs to.
            $groups = $modinfo->get_groups();
            if ($this->groupid) {
                $allow = in_array($this->groupid, $groups);
            } else {
                // No specific group. Allow if they belong to any group at all.
                $allow = $groups ? true : false;
            }

            // The NOT condition applies before accessallgroups (i.e. if you
            // set something to be available to those NOT in group X,
            // people with accessallgroups can still access it even if
            // they are in group X).
            if ($not) {
                $allow = !$allow;
            }
        }
        return $allow;
    }

    public function get_description($full, $not, $course, \course_modinfo $modinfo) {
        global $DB;

        if ($this->groupid) {
            // Need to get the name for the group. Unfortunately this requires
            // a database query. To save queries, get all groups for course at
            // once in a static cache.
            if (!array_key_exists($this->groupid, self::$groupnames)) {
                $coursegroups = $DB->get_records(
                        'groups', array('courseid' => $course->id), '', 'id, name');
                foreach ($coursegroups as $rec) {
                    self::$groupnames[$rec->id] = $rec->name;
                }
            }
    
            // If it still doesn't exist, it must have been misplaced.
            if (!array_key_exists($this->groupid, self::$groupnames)) {
                $name = get_string('missing', 'availability_group');
            } else {
                $context = \context_course::instance($course->id);
                $name = format_string(self::$groupnames[$this->groupid], true,
                        array('context' => $context));
            }
        } else {
            return get_string($not ? 'requires_notanygroup' : 'requires_anygroup',
                    'availability_group');
        }

        return get_string($not ? 'requires_notgroup' : 'requires_group',
                'availability_group', $name);
    }

    protected function get_debug_string() {
        return $this->groupid ? '#' . $this->groupid : 'any';
    }

    public function update_after_restore($restoreid, \base_logger $logger, $name) {
        if (!$this->groupid) {
            return false;
        }
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'group', $this->groupid);
        if (!$rec || !$rec->newitemid) {
            $this->groupid = -1;
            $logger->process('Restored item (' . $name .
                    ') has availability condition on group that was not restored',
                    \backup::LOG_WARNING);
        } else {
            $this->groupid = (int)$rec->newitemid;
        }
        return true;
    }

    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'groups' && (int)$this->groupid === (int)$oldid) {
            $this->groupid = $newid;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Wipes the static cache used to store grouping names.
     */
    public static function wipe_static_cache() {
        self::$groupnames = array();
    }
}
