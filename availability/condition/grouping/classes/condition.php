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
 * @package availability_grouping
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_grouping;

defined('MOODLE_INTERNAL') || die();

/**
 * Condition main class.
 *
 * @package availability_grouping
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var array Array from grouping id => name */
    protected static $groupingnames = array();

    /** @var int ID of grouping that this condition requires */
    protected $groupingid;

    /**
     * Constructor.
     *
     * @param stdClass $structure Data structure from JSON decode
     * @throws coding_exception If invalid data structure.
     */
    public function __construct($structure) {
        // Get grouping id.
        if (isset($structure->id) && is_int($structure->id)) {
            $this->groupingid = $structure->id;
        } else {
            throw new \coding_exception('Missing or invalid ->id for grouping condition');
        }
    }

    public function save() {
        return (object)array('type' => 'grouping', 'id' => $this->groupingid);
    }

    public function is_available($not, $course, $grabthelot, $userid, \course_modinfo $modinfo) {
        $context = \context_course::instance($course->id);
        $allow = true;
        if (!has_capability('moodle/site:accessallgroups', $context, $userid)) {
            // If the activity has 'group members only' and you don't have accessallgroups...
            $groups = $modinfo->get_groups($this->groupingid);
            if (!$groups) {
                // ...and you don't belong to a group, then set it so you can't see/access it
                $allow = false;
            }

            // The NOT condition applies before accessallgroups (i.e. if you
            // set something to be available to those NOT in grouping X,
            // people with accessallgroups can still access it even if
            // they are in grouping X).
            if ($not) {
                $allow = !$allow;
            }
        }
        return $allow;
    }

    public function get_description($full, $not, $course, \course_modinfo $modinfo) {
        global $DB;

        // Need to get the name for the grouping. Unfortunately this requires
        // a database query. To save queries, get all groupings for course at
        // once in a static cache.
        if (!array_key_exists($this->groupingid, self::$groupingnames)) {
            $coursegroupings = $DB->get_records(
                    'groupings', array('courseid' => $course->id), '', 'id, name');
            foreach ($coursegroupings as $rec) {
                self::$groupingnames[$rec->id] = $rec->name;
            }
        }

        // If it still doesn't exist, it must have been misplaced.
        if (!array_key_exists($this->groupingid, self::$groupingnames)) {
            $name = get_string('missing', 'availability_grouping');
        } else {
            $context = \context_course::instance($course->id);
            $name = format_string(self::$groupingnames[$this->groupingid], true,
                    array('context' => $context));
        }

        return get_string($not ? 'requires_notgrouping' : 'requires_grouping',
                'availability_grouping', $name);
    }

    protected function get_debug_string() {
        return '#' . $this->groupingid;
    }

    public function update_after_restore($restoreid, \base_logger $logger, $name) {
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'grouping', $this->groupingid);
        if (!$rec || !$rec->newitemid) {
            $this->groupingid = 0;
            $logger->process('Restored item (' . $name .
                    ') has availability condition on grouping that was not restored',
                    \backup::LOG_WARNING);
        } else {
            $this->groupingid = (int)$rec->newitemid;
        }
        return true;
    }

    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'groupings' && (int)$this->groupingid === (int)$oldid) {
            $this->groupingid = $newid;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Wipes the static cache used to store grouping names.
     */
    public static function wipe_static_cache() {
        self::$groupingnames = array();
    }
}
