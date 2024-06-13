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
 * Form to reindex a course or activity.
 *
 * @package core_search
 * @copyright 2024 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core_search\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to reindex a course or activity.
 *
 * @package core_search
 */
class reindex extends \moodleform {
    /**
     * Defines form contents.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('static', 'info', '', get_string('reindexcourse_info', 'search'));

        $mform->addElement('course', 'courseid', get_string('course'));
        $mform->addRule('courseid', null, 'required', null, 'client');

        $options = [
            'ajax' => 'core_search/activityselector'
        ];
        $mform->addElement('autocomplete', 'cmid', get_string('activity'), [], $options);
        $mform->disabledIf('cmid', 'courseid');

        $mform->addElement('submit', 'reindex', get_string('reindexcourse', 'search'));
    }
}
