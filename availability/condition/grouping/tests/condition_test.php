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
 * Unit tests for the condition.
 *
 * @package availability_grouping
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use availability_grouping\condition;

/**
 * Unit tests for the condition.
 */
class availability_grouping_condition_testcase extends advanced_testcase {
    /**
     * Tests constructing and using condition.
     */
    public function test_usage() {
        global $CFG, $USER;
        $this->resetAfterTest();
        $CFG->enableavailability = true;

        // Erase static cache before test.
        condition::wipe_static_cache();

        // Make a test course and user.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $generator->enrol_user($user->id, $course->id);

        // Make a test grouping and group.
        $grouping = $generator->create_grouping(array('courseid' => $course->id,
                'name' => 'Grouping!'));
        $group = $generator->create_group(array('courseid' => $course->id));
        groups_assign_grouping($grouping->id, $group->id);

        // Do test (not in grouping).
        $structure = (object)array('type' => 'grouping', 'id' => (int)$grouping->id);
        $cond = new condition($structure);

        // Check if available (when not available).
        $modinfo = get_fast_modinfo($course->id, $user->id);
        $this->assertFalse($cond->is_available(false, $course, true, $user->id, $modinfo));
        $information = $cond->get_description(false, false, $course, $modinfo);
        $this->assertRegExp('~belong to a group in.*Grouping!~', $information);
        $this->assertTrue($cond->is_available(true, $course, true, $user->id, $modinfo));

        // Add user to grouping and refresh cache.
        groups_add_member($group, $user);
        get_fast_modinfo($course->id, $user->id, true);
        $modinfo = get_fast_modinfo($course->id, $user->id);

        // Recheck.
        $this->assertTrue($cond->is_available(false, $course, true, $user->id, $modinfo));
        $this->assertFalse($cond->is_available(true, $course, true, $user->id, $modinfo));
        $information = $cond->get_description(false, true, $course, $modinfo);
        $this->assertRegExp('~do not belong to a group in.*Grouping!~', $information);

        // Admin user doesn't belong to the grouping, but they can access it
        // either way (positive or NOT) because of accessallgroups.
        $this->setAdminUser();
        $this->assertTrue($cond->is_available(false, $course, true, $USER->id, $modinfo));
        $this->assertTrue($cond->is_available(true, $course, true, $USER->id, $modinfo));

        // Grouping that doesn't exist uses 'missing' text.
        $cond = new condition((object)array('id' => $grouping->id + 1000));
        $this->assertFalse($cond->is_available(false, $course, true, $user->id, $modinfo));
        $information = $cond->get_description(false, false, $course, $modinfo);
        $this->assertRegExp('~belong to a group in.*(Missing grouping)~', $information);
    }

    /**
     * Tests the constructor including error conditions. Also tests the
     * string conversion feature (intended for debugging only).
     */
    public function test_constructor() {
        // No parameters.
        $structure = new stdClass();
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('Missing or invalid ->id', $e->getMessage());
        }

        // Invalid id (not int).
        $structure->id = 'bourne';
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('Missing or invalid ->id', $e->getMessage());
        }

        $structure->id = 123;
        $cond = new condition($structure);
        $this->assertEquals('{grouping:#123}', (string)$cond);
    }

    /**
     * Tests the save() function.
     */
    public function test_save() {
        $structure = (object)array('id' => 123);
        $cond = new condition($structure);
        $structure->type = 'grouping';
        $this->assertEquals($structure, $cond->save());
    }

    /**
     * Tests the update_dependency_id() function.
     */
    public function test_update_dependency_id() {
        $cond = new condition((object)array('id' => 123));
        $this->assertFalse($cond->update_dependency_id('frogs', 123, 456));
        $this->assertFalse($cond->update_dependency_id('groupings', 12, 34));
        $this->assertTrue($cond->update_dependency_id('groupings', 123, 456));
        $after = $cond->save();
        $this->assertEquals(456, $after->id);
    }
}
