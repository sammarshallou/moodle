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
 * Mock condition.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_mock;

defined('MOODLE_INTERNAL') || die();

class condition extends \core_availability\condition {
    /** @var bool True if available */
    protected $available;
    /** @var string Message if not available */
    protected $message;
    /** @var bool True if available for all (normal state) */
    protected $forall;
    /** @var bool True if available for all (NOT state) */
    protected $forallnot;
    /** @var string Dependency table (empty if none) */
    protected $dependtable;
    /** @var id Dependency id (0 if none) */
    protected $dependid;

    public function __construct($structure) {
        $this->available = isset($structure->a) ? $structure->a : false;
        $this->message = isset($structure->m) ? $structure->m : '';
        $this->forall = isset($structure->all) ? $structure->all : false;
        $this->forallnot = isset($structure->allnot) ? $structure->allnot : false;
        $this->dependtable = isset($structure->table) ? $structure->table : '';
        $this->dependid = isset($structure->id) ? $structure->id : 0;
    }

    public function save() {
        return (object)array('a' => $this->available, 'm' => $this->message,
                'all' => $this->forall, 'allnot' => $this->forallnot,
                'table' => $this->dependtable, 'id' => $this->dependid);
    }

    public function is_available($not, $course, $grabthelot, $userid, \course_modinfo $modinfo) {
        return $not ? !$this->available : $this->available;
    }

    public function is_available_for_all($not = false) {
        return $not ? $this->forallnot : $this->forall;
    }

    public function get_description($full, $not, $course, \course_modinfo $modinfo) {
        $fulltext = $full ? '[FULL]' : '';
        $nottext = $not ? '!' : '';
        return $nottext . $fulltext . $this->message;
    }

    public function get_standalone_description(
            $full, $not, $course, \course_modinfo $modinfo) {
        // Override so that we can spot that this function is used.
        return 'SA: ' . $this->get_description($full, $not, $course, $modinfo);
    }

    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === $this->dependtable && (int)$oldid === (int)$this->dependid) {
            $this->dependid = $newid;
            return true;
        } else {
            return false;
        }
    }

    protected function get_debug_string() {
        return ($this->available ? 'y' : 'n') . ',' . $this->message;
    }
}
