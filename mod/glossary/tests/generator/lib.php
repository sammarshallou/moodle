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

defined('MOODLE_INTERNAL') || die();

/**
 * Data generator class.
 *
 * @package mod_glossary
 * @copyright 2013 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_glossary_generator extends testing_module_generator {

    /**
     * @var int keep track of how many entries have been created.
     */
    protected $entrycount = 0;

    /**
     * To be called from data reset code only,
     * do not use in tests.
     */
    public function reset() {
        $this->entrycount = 0;
        parent::reset();
    }

    /**
     * Create new module instance.
     *
     * @param array|stdClass $record
     * @param array $options
     * @return stdClass activity record with extra cmid field
     */
    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/glossary/lib.php');

        $this->instancecount++;
        $i = $this->instancecount;

        $record = (object)(array)$record;
        $options = (array)$options;

        if (empty($record->course)) {
            throw new coding_exception('Module generator requires $record->course.');
        }
        if (!isset($record->name)) {
            $record->name = get_string('pluginname', 'glossary') . ' ' . $i;
        }
        if (!isset($record->intro)) {
            $record->intro = 'Test glossary ' . $i;
        }
        if (!isset($record->introformat)) {
            $record->introformat = FORMAT_MOODLE;
        }

        // Defaults from form defaults.
        if (!isset($record->displayformat)) {
            $record->displayformat = 'dictionary';
        }
        if (!isset($record->approvaldisplayformat)) {
            $record->approvaldisplayformat = 'default';
        }
        if (!isset($record->entbypage)) {
            $record->entbypage = !empty($CFG->glossary_entbypage) ? $CFG->glossary_entbypage : 10;
        }
        if (!isset($record->cmidnumber)) {
            $record->cmidnumber = '';
        }
        if (!isset($record->assessed)) {
            $record->assessed = 0;
        }
        if (!isset($record->showalphabet)) {
            $record->showalphabet = 1;
        }
        if (!isset($record->showall)) {
            $record->showall = 1;
        }
        if (!isset($record->showspecial)) {
            $record->showspecial = 1;
        }
        if (!isset($record->allowprintview)) {
            $record->allowprintview = 1;
        }
        if (!isset($record->defaultapproval)) {
            $record->defaultapproval = $CFG->glossary_defaultapproval;
        }
        if (!isset($record->allowduplicatedentries)) {
            $record->allowduplicatedentries = $CFG->glossary_dupentries;
        }
        if (!isset($record->allowcomments)) {
            $record->allowcomments = $CFG->glossary_allowcomments;
        }
        if (!isset($record->usedynalink)) {
            $record->usedynalink = $CFG->glossary_linkbydefault;
        }

        $record->coursemodule = $this->precreate_course_module($record->course, $options);
        $id = glossary_add_instance($record, null);
        return $this->post_add_instance($id, $record->coursemodule);
    }

    /**
     * Creates a glossary entry.
     *
     * @param array|stdClass $record Settings for record
     * @param array $options Options (currently ignored)
     * @return stdClass Entry record from database
     */
    public function create_entry($record = null, array $options = null) {
        global $DB, $USER;

        $record = (object) (array) $record;
        $options = (array) $options;
        $this->entrycount++;

        if (empty($record->glossaryid)) {
            throw new coding_exception('Entry generator requires $record->glossaryid');
        }

        if (empty($record->concept)) {
            $record->title = 'Entry ' . $this->entrycount;
        }
        if (!isset($record->timecreated)) {
            $record->timecreated = time();
        }
        if (!isset($record->timemodified)) {
            $record->timemodified = time();
        }
        if (!isset($record->userid)) {
            $record->userid = $USER->id;
        }
        if (!isset($record->definition)) {
            $record->definition = 'Definition for entry ' . $this->entrycount;
        }
        if (!isset($record->definitionformat)) {
            $record->definitionformat = FORMAT_HTML;
        }
        if (!isset($record->approved)) {
            // Entries are approved by default (this is not the logic it uses
            // when creating them in UI, it does a capability check or uses
            // glossary setting).
            $record->approved = 1;
        }

        // Insert record and return full record (inc. database defaults).
        $recordid = $DB->insert_record('glossary_entries', $record);
        return $DB->get_record('glossary_entries', array('id' => $recordid));
    }

}
