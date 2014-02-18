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
 * @package availability_group
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use availability_group\condition;

/**
 * Unit tests for the condition.
 */
class availability_group_condition_testcase extends advanced_testcase {
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

        // Make 2 test groups, one in a grouping and one not.
        $grouping = $generator->create_grouping(array('courseid' => $course->id));
        $group1 = $generator->create_group(array('courseid' => $course->id, 'name' => 'G1!'));
        groups_assign_grouping($grouping->id, $group1->id);
        $group2 = $generator->create_group(array('courseid' => $course->id, 'name' => 'G2!'));

        // Do test (not in group).
        $cond = new condition((object)array('id' => (int)$group1->id));

        // Check if available (when not available).
        $modinfo = get_fast_modinfo($course->id, $user->id);
        $this->assertFalse($cond->is_available(false, $course, true, $user->id, $modinfo));
        $information = $cond->get_description(false, false, $course, $modinfo);
        $this->assertRegExp('~You belong to.*G1!~', $information);
        $this->assertTrue($cond->is_available(true, $course, true, $user->id, $modinfo));

        // Add user to groups and refresh cache.
        groups_add_member($group1, $user);
        groups_add_member($group2, $user);
        get_fast_modinfo($course->id, $user->id, true);
        $modinfo = get_fast_modinfo($course->id, $user->id);

        // Recheck.
        $this->assertTrue($cond->is_available(false, $course, true, $user->id, $modinfo));
        $this->assertFalse($cond->is_available(true, $course, true, $user->id, $modinfo));
        $information = $cond->get_description(false, true, $course, $modinfo);
        $this->assertRegExp('~do not belong to.*G1!~', $information);

        // Check group 2 works also.
        $cond = new condition((object)array('id' => (int)$group2->id));
        $this->assertTrue($cond->is_available(false, $course, true, $user->id, $modinfo));

        // What about an 'any group' condition?
        $cond = new condition((object)array());
        $this->assertTrue($cond->is_available(false, $course, true, $user->id, $modinfo));
        $this->assertFalse($cond->is_available(true, $course, true, $user->id, $modinfo));
        $information = $cond->get_description(false, true, $course, $modinfo);
        $this->assertRegExp('~do not belong to any~', $information);

        // Admin user doesn't belong to a group, but they can access it
        // either way (positive or NOT).
        $this->setAdminUser();
        $this->assertTrue($cond->is_available(false, $course, true, $USER->id, $modinfo));
        $this->assertTrue($cond->is_available(true, $course, true, $USER->id, $modinfo));

        // Group that doesn't exist uses 'missing' text.
        $cond = new condition((object)array('id' => $group2->id + 1000));
        $this->assertFalse($cond->is_available(false, $course, true, $user->id, $modinfo));
        $information = $cond->get_description(false, false, $course, $modinfo);
        $this->assertRegExp('~You belong to.*\(Missing group\)~', $information);
    }

    /**
     * Tests the constructor including error conditions. Also tests the
     * string conversion feature (intended for debugging only).
     */
    public function test_constructor() {
        // Invalid id (not int).
        $structure = (object)array('id' => 'bourne');
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('Invalid ->id', $e->getMessage());
        }

        // Valid (with id).
        $structure->id = 123;
        $cond = new condition($structure);
        $this->assertEquals('{group:#123}', (string)$cond);

        // Valid (no id).
        unset($structure->id);
        $cond = new condition($structure);
        $this->assertEquals('{group:any}', (string)$cond);
    }

    /**
     * Tests the save() function.
     */
    public function test_save() {
        $structure = (object)array('id' => 123);
        $cond = new condition($structure);
        $structure->type = 'group';
        $this->assertEquals($structure, $cond->save());

        $structure = (object)array();
        $cond = new condition($structure);
        $structure->type = 'group';
        $this->assertEquals($structure, $cond->save());
    }

    /**
     * Tests the update_dependency_id() function.
     */
    public function test_update_dependency_id() {
        $cond = new condition((object)array('id' => 123));
        $this->assertFalse($cond->update_dependency_id('frogs', 123, 456));
        $this->assertFalse($cond->update_dependency_id('groups', 12, 34));
        $this->assertTrue($cond->update_dependency_id('groups', 123, 456));
        $after = $cond->save();
        $this->assertEquals(456, $after->id);
    }
}
