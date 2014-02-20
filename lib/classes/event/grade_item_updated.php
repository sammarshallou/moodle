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
 * Event fired when a course grade item is changed in the database by any means.
 *
 * @package core
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\event;
defined('MOODLE_INTERNAL') || die();

/**
 * Event fired when a course grade item is changed in the database by any means.
 *
 * @package core_grading
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_item_updated extends base {
    protected function init() {
        $this->data['crud'] = 'u';
        // This event is performed by teachers or related.
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        // It affects the grade_items table.
        $this->data['objecttable'] = 'grade_items';
    }

    /**
     * Gets localised event name.
     *
     * @return string Event name
     */
    public static function get_name() {
        return get_string('eventgradeitemupdated', 'moodle');
    }

    public function get_description() {
        return "Grade item {$this->objectid} updated.";
    }
}
