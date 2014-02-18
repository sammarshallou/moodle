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
 * Unit tests for the user profile condition.
 *
 * @package availability_profile
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use availability_profile\condition;

/**
 * Unit tests for the date condition.
 */
class availability_profile_condition_testcase extends advanced_testcase {
    /** @var profile_define_text Profile field for testing */
    protected $profilefield;

    /** @var array Array of user IDs for whome we already set the profile field */
    protected $setusers = array();

    public function setUp() {
        global $DB;

        $this->resetAfterTest();

        // Add a custom profile field type. The API for doing this is indescribably
        // horrid and tightly intertwined with the form UI, so it's best to add
        // it directly in database.
        $DB->insert_record('user_info_field', array(
                'shortname' => 'frogtype', 'name' => 'Type of frog', 'categoryid' => 1,
                'datatype' => 'text'));
        $this->profilefield = $DB->get_record('user_info_field',
                array('shortname' => 'frogtype'));

        // Clear static cache.
        \availability_profile\condition::wipe_static_cache();
    }

    /**
     * Tests constructing and using date condition as part of tree.
     */
    public function test_in_tree() {
        global $USER, $SITE;

        $this->setAdminUser();

        $modinfo = get_fast_modinfo($SITE);

        $structure = (object)array('op' => '|', 'show' => true, 'c' => array(
                (object)array('type' => 'profile',
                        'op' => condition::OP_IS_EQUAL_TO,
                        'cf' => 'frogtype', 'v' => 'tree')));
        $tree = new \core_availability\tree($structure);

        // Initial check (user does not have custom field).
        $result = $tree->check_available(false, $SITE, true, $USER->id, $modinfo);
        $this->assertFalse($result->is_available());

        // Set field.
        $this->set_field($USER->id, 'tree');

        // Now it's true!
        $result = $tree->check_available(false, $SITE, true, $USER->id, $modinfo);
        $this->assertTrue($result->is_available());
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
        } catch (coding_exception $e) {
            $this->assertContains('Missing or invalid ->op', $e->getMessage());
        }

        // Invalid op.
        $structure->op = 'isklingonfor';
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Missing or invalid ->op', $e->getMessage());
        }

        // Missing value.
        $structure->op = condition::OP_IS_EQUAL_TO;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Missing or invalid ->v', $e->getMessage());
        }

        // Invalid value (not string).
        $structure->v = false;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Missing or invalid ->v', $e->getMessage());
        }

        // Unexpected value.
        $structure->op = condition::OP_IS_EMPTY;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Unexpected ->v', $e->getMessage());
        }

        // Missing field.
        $structure->op = condition::OP_IS_EQUAL_TO;
        $structure->v = 'flying';
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Missing ->sf or ->cf', $e->getMessage());
        }

        // Invalid field (not string).
        $structure->sf = 42;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Invalid ->sf', $e->getMessage());
        }

        // Both fields.
        $structure->sf = 'department';
        $structure->cf = 'frogtype';
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Both ->sf and ->cf', $e->getMessage());
        }

        // Invalid ->cf field (not string).
        unset($structure->sf);
        $structure->cf = false;
        try {
            $cond = new condition($structure);
            $this->fail();
        } catch (coding_exception $e) {
            $this->assertContains('Invalid ->cf', $e->getMessage());
        }

        // Valid examples (checks values are correctly included).
        $structure->cf = 'frogtype';
        $cond = new condition($structure);
        $this->assertEquals('{profile:*frogtype isequalto flying}', (string)$cond);

        unset($structure->v);
        $structure->op = condition::OP_IS_EMPTY;
        $cond = new condition($structure);
        $this->assertEquals('{profile:*frogtype isempty}', (string)$cond);

        unset($structure->cf);
        $structure->sf = 'department';
        $cond = new condition($structure);
        $this->assertEquals('{profile:department isempty}', (string)$cond);
    }

    /**
     * Tests the save() function.
     */
    public function test_save() {
        $structure = (object)array('cf' => 'frogtype', 'op' => condition::OP_IS_EMPTY);
        $cond = new condition($structure);
        $structure->type = 'profile';
        $this->assertEquals($structure, $cond->save());

        $structure = (object)array('cf' => 'frogtype', 'op' => condition::OP_ENDS_WITH,
                'v' => 'bouncy');
        $cond = new condition($structure);
        $structure->type = 'profile';
        $this->assertEquals($structure, $cond->save());
    }

    /**
     * Tests the is_available function. There is no separate test for
     * get_full_information because that function is called from is_available
     * and we test its values here.
     */
    public function test_is_available() {
        global $USER, $SITE, $DB;
        $this->setAdminUser();
        $modinfo = get_fast_modinfo($SITE);

        // Prepare to test with all operators against custom field using all
        // combinations of NOT and true/false states..
        $information = 'x';
        $structure = (object)array('cf' => 'frogtype');

        $structure->op = condition::OP_IS_NOT_EMPTY;
        $cond = new condition($structure);
        $this->assert_is_available_result(false, '~Type of frog.*is not empty~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, 'poison dart');
        $this->assert_is_available_result(true, '~Type of frog.*is empty~',
                $cond, $SITE, $USER->id, $modinfo);

        $structure->op = condition::OP_IS_EMPTY;
        $cond = new condition($structure);
        $this->assert_is_available_result(false, '~.*Type of frog.*is empty~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, null);
        $this->assert_is_available_result(true, '~.*Type of frog.*is not empty~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, '');
        $this->assert_is_available_result(true, '~.*Type of frog.*is not empty~',
                $cond, $SITE, $USER->id, $modinfo);

        $structure->op = condition::OP_CONTAINS;
        $structure->v = 'llf';
        $cond = new condition($structure);
        $this->assert_is_available_result(false, '~Type of frog.*contains.*llf~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, 'bullfrog');
        $this->assert_is_available_result(true, '~Type of frog.*does not contain.*llf~',
                $cond, $SITE, $USER->id, $modinfo);

        $structure->op = condition::OP_DOES_NOT_CONTAIN;
        $cond = new condition($structure);
        $this->assert_is_available_result(false, '~Type of frog.*does not contain.*llf~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, 'goliath');
        $this->assert_is_available_result(true, '~Type of frog.*contains.*llf~',
                $cond, $SITE, $USER->id, $modinfo);

        $structure->op = condition::OP_IS_EQUAL_TO;
        $structure->v = 'Kermit';
        $cond = new condition($structure);
        $this->assert_is_available_result(false, '~Type of frog.*is <.*Kermit~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, 'Kermit');
        $this->assert_is_available_result(true, '~Type of frog.*is not.*Kermit~',
                $cond, $SITE, $USER->id, $modinfo);

        $structure->op = condition::OP_STARTS_WITH;
        $structure->v = 'Kerm';
        $cond = new condition($structure);
        $this->assert_is_available_result(true, '~Type of frog.*does not start.*Kerm~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, 'Keroppi');
        $this->assert_is_available_result(false, '~Type of frog.*starts.*Kerm~',
                $cond, $SITE, $USER->id, $modinfo);

        $structure->op = condition::OP_ENDS_WITH;
        $structure->v = 'ppi';
        $cond = new condition($structure);
        $this->assert_is_available_result(true, '~Type of frog.*does not end.*ppi~',
                $cond, $SITE, $USER->id, $modinfo);
        $this->set_field($USER->id, 'Kermit');
        $this->assert_is_available_result(false, '~Type of frog.*ends.*ppi~',
                $cond, $SITE, $USER->id, $modinfo);

        // Also test is_available for a different (not current) user.
        $generator = $this->getDataGenerator();
        $user = $generator->create_user();
        $structure->op = condition::OP_CONTAINS;
        $structure->v = 'rne';
        $cond = new condition($structure);
        $this->assertFalse($cond->is_available(false, $SITE, true, $user->id, $modinfo));
        $this->set_field($user->id, 'horned');
        $this->assertTrue($cond->is_available(false, $SITE, true, $user->id, $modinfo));

        // Now check with a standard field (department).
        $structure = (object)array('op' => condition::OP_IS_EQUAL_TO,
                'sf' => 'department', 'v' => 'Cheese Studies');
        $cond = new condition($structure);
        $this->assertFalse($cond->is_available(false, $SITE, true, $USER->id, $modinfo));
        $this->assertFalse($cond->is_available(false, $SITE, true, $user->id, $modinfo));

        // Check the message (should be using lang string with capital, which
        // is evidence that it called the right function to get the name).
        $information = $cond->get_description(false, false, $SITE, $modinfo);
        $this->assertRegExp('~Department~', $information);

        // Set the field to true for both users and retry.
        $DB->set_field('user', 'department', 'Cheese Studies', array('id' => $user->id));
        $USER->department = 'Cheese Studies';
        $this->assertTrue($cond->is_available(false, $SITE,
                true, $USER->id, $modinfo));
        $this->assertTrue($cond->is_available(false, $SITE,
                true, $user->id, $modinfo));
    }

    /**
     * Sets the custom profile field used for testing.
     *
     * @param int $userid User id
     * @param string|null $value Field value or null to clear
     */
    protected function set_field($userid, $value) {
        global $DB, $USER;

        $alreadyset = array_key_exists($userid, $this->setusers);
        if (is_null($value)) {
            $DB->delete_records('user_info_data',
                    array('userid' => $userid, 'fieldid' => $this->profilefield->id));
            unset($this->setusers[$userid]);
        } else if ($alreadyset) {
            $DB->set_field('user_info_data', 'data', $value,
                    array('userid' => $userid, 'fieldid' => $this->profilefield->id));
        } else {
            $DB->insert_record('user_info_data', array('userid' => $userid,
                    'fieldid' => $this->profilefield->id, 'data' => $value));
            $this->setusers[$userid] = true;
        }
    }

    /**
     * Checks the result of is_available. This function is to save duplicated
     * code; it does two checks (the normal is_available with $not set to true
     * and set to false). Whichever result is expected to be true, it checks
     * $information ends up as empty string for that one, and as a regex match
     * for another one.
     *
     * @param bool $yes If the positive test is expected to return true
     * @param string $failpattern Regex pattern to match text when it returns false
     * @param condition $cond Condition
     * @param stdClass $course Course object
     * @param int $userid User id
     * @param course_modinfo $modinfo Modinfo
     */
    protected function assert_is_available_result($yes, $failpattern, condition $cond,
            $course, $userid, $modinfo) {
        // Positive (normal) test.
        $this->assertEquals($yes, $cond->is_available(false, $course, true, $userid, $modinfo),
                'Failed checking normal (positive) result');
        if (!$yes) {
            $information = $cond->get_description(false, false, $course, $modinfo);
            $this->assertRegExp($failpattern, $information);
        }

        // Negative (NOT) test.
        $this->assertEquals(!$yes, $cond->is_available(true, $course, true, $userid, $modinfo),
                'Failed checking NOT (negative) result');
        if ($yes) {
            $information = $cond->get_description(false, true, $course, $modinfo);
            $this->assertRegExp($failpattern, $information);
        }
    }
}
