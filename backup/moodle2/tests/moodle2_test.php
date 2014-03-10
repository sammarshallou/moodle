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
 * Tests for Moodle 2 format backup operation.
 *
 * @package core_backup
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Tests for Moodle 2 format backup operation.
 *
 * @package core_backup
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_backup_moodle2_testcase extends advanced_testcase {

    /**
     * Tests the availability field on modules and sections is correctly
     * backed up and restored.
     */
    public function test_backup_availability() {
        global $DB, $CFG;

        $this->resetAfterTest(true);
        $this->setAdminUser();
        $CFG->enableavailability = true;
        $CFG->enablecompletion = true;

        // Create a course with some availability data set.
        $generator = $this->getDataGenerator();
        $course = $generator->create_course(
                array('format' => 'topics', 'numsections' => 3,
                    'enablecompletion' => COMPLETION_ENABLED),
                array('createsections' => true));
        $forum = $generator->create_module('forum', array(
                'course' => $course->id));
        $forum2 = $generator->create_module('forum', array(
                'course' => $course->id, 'completion' => COMPLETION_TRACKING_MANUAL));

        // We need a grade, easiest is to add an assignment.
        $assignrow = $generator->create_module('assign', array(
                'course' => $course->id));
        $assign = new assign(context_module::instance($assignrow->cmid), false, false);
        $item = $assign->get_grade_item();

        // Make a test grouping as well.
        $grouping = $generator->create_grouping(array('courseid' => $course->id,
                'name' => 'Grouping!'));

        $availability = '{"op":"|","show":false,"c":[' .
                '{"type":"completion","cm":' . $forum2->cmid .',"e":1},' .
                '{"type":"grade","id":' . $item->id . ',"min":4,"max":94},' .
                '{"type":"grouping","id":' . $grouping->id . '}' .
                ']}';
        $DB->set_field('course_modules', 'availability', $availability, array(
                'id' => $forum->cmid));
        $DB->set_field('course_sections', 'availability', $availability, array(
                'course' => $course->id, 'section' => 1));

        // Backup and restore it.
        $newcourseid = $this->backup_and_restore($course);

        // Check settings in new course.
        $modinfo = get_fast_modinfo($newcourseid);
        $forums = array_values($modinfo->get_instances_of('forum'));
        $assigns = array_values($modinfo->get_instances_of('assign'));
        $newassign = new assign(context_module::instance($assigns[0]->id), false, false);
        $newitem = $newassign->get_grade_item();
        $newgroupingid = $DB->get_field('groupings', 'id', array('courseid' => $newcourseid));

        // Expected availability should have new ID for the forum, grade, and grouping.
        $newavailability = str_replace(
                '"grouping","id":' . $grouping->id,
                '"grouping","id":' . $newgroupingid,
                str_replace(
                    '"grade","id":' . $item->id,
                    '"grade","id":' . $newitem->id,
                    str_replace(
                        '"cm":' . $forum2->cmid,
                        '"cm":' . $forums[1]->id,
                        $availability)));

        $this->assertEquals($newavailability, $forums[0]->availability);
        $this->assertNull($forums[1]->availability);
        $this->assertEquals($newavailability, $modinfo->get_section_info(1, MUST_EXIST)->availability);
        $this->assertNull($modinfo->get_section_info(2, MUST_EXIST)->availability);
    }

    /**
     * Backs a course up and restores it.
     *
     * @param stdClass $course Course object to backup
     * @return int ID of newly restored course
     */
    protected function backup_and_restore($course) {
        global $USER, $CFG;

        // Turn off file logging, otherwise it can't delete the file (Windows).
        $CFG->backup_file_logger_level = backup::LOG_NONE;

        // Do backup with default settings. MODE_IMPORT means it will just
        // create the directory and not zip it.
        $bc = new backup_controller(backup::TYPE_1COURSE, $course->id,
                backup::FORMAT_MOODLE, backup::INTERACTIVE_NO, backup::MODE_IMPORT,
                $USER->id);
        $backupid = $bc->get_backupid();
        $bc->execute_plan();
        $bc->destroy();

        // Do restore to new course with default settings.
        $newcourseid = restore_dbops::create_new_course(
                $course->fullname, $course->shortname . '_2', $course->category);
        $rc = new restore_controller($backupid, $newcourseid,
                backup::INTERACTIVE_NO, backup::MODE_GENERAL, $USER->id,
                backup::TARGET_NEW_COURSE);
        $this->assertTrue($rc->execute_precheck());
        $rc->execute_plan();
        $rc->destroy();

        return $newcourseid;
    }
}
