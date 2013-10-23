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
 * Generator tests.
 *
 * @package mod_glossary
 * @copyright 2013 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_glossary_generator_testcase extends advanced_testcase {

    /**
     * Test creating an instance of module.
     */
    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('glossary', array('course' => $course->id)));
        $glossary = $this->getDataGenerator()->create_module('glossary', array('course' => $course->id));
        $this->assertTrue($DB->record_exists('glossary', array('course' => $course->id, 'id' => $glossary->id)));
    }

    /**
     * Test creating a glossary entry.
     */
    public function test_create_entry() {
        global $DB, $USER;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $glossary = $this->getDataGenerator()->create_module('glossary', array('course' => $course->id));
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_glossary');

        $entry = $generator->create_entry(array('glossaryid' => $glossary->id));
        $entries = glossary_get_user_entries($glossary->id, $USER->id);
        $this->assertEquals(1, count($entries));
    }

}
