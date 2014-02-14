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
 * Unit tests for the condition tree class and related logic.
 *
 * @package core_availability
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Unit tests for the condition tree class and related logic.
 */
class tree_testcase extends \advanced_testcase {
    public function setUp() {
        // Load the mock condition so that it can be used.
        require_once(__DIR__ . '/mock_condition.php');
    }

    /**
     * Tests constructing a tree with errors.
     */
    public function test_construct_errors() {
        try {
            new \core_availability\tree('frog');
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('not object', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array());
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('missing ->op', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '*'));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('unknown ->op', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '|'));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('missing ->show', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '|', 'show' => 0));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('->show not bool', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '&'));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('missing ->showc', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '&', 'showc' => 0));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('->showc not array', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '&', 'showc' => array(0)));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('->showc value not bool', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '|', 'show' => true));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('missing ->c', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '|', 'show' => true,
                    'c' => 'side'));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('->c not array', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '|', 'show' => true,
                    'c' => array(3)));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('child not object', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '|', 'show' => true,
                    'c' => array((object)array('type' => 'doesnotexist'))));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('Unknown condition type: doesnotexist', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '|', 'show' => true,
                    'c' => array((object)array())));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('missing ->op', $e->getMessage());
        }
        try {
            new \core_availability\tree((object)array('op' => '&',
                    'c' => array((object)array('op' => '&', 'c' => array())),
                    'showc' => array(true, true)
                    ));
            $this->fail();
        } catch(coding_exception $e) {
            $this->assertContains('->c, ->showc mismatch', $e->getMessage());
        }
    }

    /**
     * Tests constructing a tree with plugin that does not exist (ignored).
     */
    public function test_construct_ignore_missing_plugin() {
        // Construct a tree with & combination of one condition that doesn't exist.
        $tree = new \core_availability\tree((object)array('op' => '|', 'c' => array(
                (object)array('type' => 'doesnotexist')), 'show' => true), true);
        // Expected result is an empty tree with | condition, shown.
        $this->assertEquals('+|()', (string)$tree);
    }

    /**
     * Tests constructing a tree with subtrees using all available operators.
     */
    public function test_construct_just_trees() {
        $structure = (object)array('op' => '&', 'c' => array(
                (object)array('op' => '|', 'c' => array()),
                (object)array('op' => '!&', 'c' => array(
                    (object)array('op' => '!|', 'c' => array())))
                ), 'showc' => array(true, true));
        $tree = new \core_availability\tree($structure);
        $this->assertEquals('&(+|(),+!&(!|()))', (string)$tree);
    }

    /**
     * Tests constructing tree using the mock plugin.
     */
    public function test_construct_with_mock_plugin() {
        $structure = (object)array('op' => '|', 'show' => true, 'c' => array(
                (object)array('type' => 'mock', 'a' => true, 'm' => '')));
        $tree = new \core_availability\tree($structure);
        $this->assertEquals('+|({mock:y,})', (string)$tree);
    }

    /**
     * Tests the check_available and get_result_information functions.
     */
    public function test_check_available() {
        global $USER;

        // Setup.
        $this->resetAfterTest();
        $modinfo = get_fast_modinfo(SITEID);
        $course = $modinfo->get_course();
        $this->setAdminUser();
        $information = '';

        // No conditions.
        $structure = (object)array('op' => '|', 'show' => true, 'c' => array());
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertTrue($available);

        // One condition set to yes.
        $structure->c = array(
                (object)array('type' => 'mock', 'a' => true));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertTrue($available);

        // One condition set to no.
        $structure->c = array(
                (object)array('type' => 'mock', 'a' => false, 'm' => 'no'));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertEquals('SA: no', $information);

        // Two conditions, OR, resolving as true.
        $structure->c = array(
                (object)array('type' => 'mock', 'a' => false, 'm' => 'no'),
                (object)array('type' => 'mock', 'a' => true));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertTrue($available);
        $this->assertEquals('', $information);

        // Two conditions, OR, resolving as false.
        $structure->c = array(
                (object)array('type' => 'mock', 'a' => false, 'm' => 'no'),
                (object)array('type' => 'mock', 'a' => false, 'm' => 'way'));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertRegExp('~any of.*no.*way~', $information);

        // Two conditions, OR, resolving as false, no display.
        $structure->show = false;
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertEquals('', $information);

        // Two conditions, AND, resolving as true.
        $structure->op = '&';
        unset($structure->show);
        $structure->showc = array(true, true);
        $structure->c = array(
                (object)array('type' => 'mock', 'a' => true),
                (object)array('type' => 'mock', 'a' => true));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertTrue($available);

        // Two conditions, AND, one false.
        $structure->c = array(
                (object)array('type' => 'mock', 'a' => false, 'm' => 'wom'),
                (object)array('type' => 'mock', 'a' => true, 'm' => ''));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertEquals('SA: wom', $information);

        // Two conditions, AND, both false.
        $structure->c = array(
                (object)array('type' => 'mock', 'a' => false, 'm' => 'wom'),
                (object)array('type' => 'mock', 'a' => false, 'm' => 'bat'));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertRegExp('~wom.*bat~', $information);

        // Two conditions, AND, both false, show turned off for one. When
        // show is turned off, that means if you don't have that condition
        // you don't get to see anything at all.
        $structure->showc[0] = false;
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertEquals('', $information);
        $structure->showc[0] = true;

        // Two conditions, NOT OR, both false.
        $structure->op = '!|';
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertTrue($available);

        // Two conditions, NOT OR, one true.
        $structure->c[0]->a = true;
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertEquals('SA: !wom', $information);

        // Two conditions, NOT OR, both true.
        $structure->c[1]->a = true;
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertRegExp('~!wom.*!bat~', $information);

        // Two conditions, NOT AND, both true.
        $structure->op = '!&';
        unset($structure->showc);
        $structure->show = true;
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertRegExp('~any of.*!wom.*!bat~', $information);

        // Two conditions, NOT AND, one true.
        $structure->c[1]->a = false;
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertTrue($available);

        // Nested NOT conditions; true.
        $structure->c = array(
                (object)array('op' => '!&', 'show' => true, 'c' => array(
                    (object)array('type' => 'mock', 'a' => true, 'm' => 'no'))));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertTrue($available);

        // Nested NOT conditions; false (note no ! in message).
        $structure->c[0]->c[0]->a = false;
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertEquals('SA: no', $information);

        // Nested condition groups, message test.
        $structure->op = '|';
        $structure->c = array(
                (object)array('op' => '&', 'showc' => array(true, true),
                    'c' => array(
                        (object)array('type' => 'mock', 'a' => false, 'm' => '1'),
                        (object)array('type' => 'mock', 'a' => false, 'm' => '2')
                    )),
                (object)array('type' => 'mock', 'a' => false, 'm' => 3));
        list ($available, $information) = $this->get_available_results(
                $structure, $course, $USER->id, $modinfo);
        $this->assertFalse($available);
        $this->assertRegExp('~<ul.*<ul.*<li.*1.*<li.*2.*</ul>.*<li.*3~', $information);
    }

    /**
     * Shortcut function to check availability and also get information.
     *
     * @param stdClass $structure Tree structure
     * @param stdClass $course Moodle course object
     * @param int $userid User id
     * @param \course_modinfo $modinfo Modinfo
     */
    protected function get_available_results($structure, $course, $userid, $modinfo) {
        $tree = new \core_availability\tree($structure);
        $result = $tree->check_available(false, $course, true, $userid, $modinfo);
        return array($result->is_available(),
                $tree->get_result_information($course, $modinfo, $result));
    }

    /**
     * Tests the is_available_for_all() function.
     */
    public function test_is_available_for_all() {
        // Empty tree is always available.
        $structure = (object)array('op' => '|', 'show' => true, 'c' => array());
        $tree = new \core_availability\tree($structure);
        $this->assertTrue($tree->is_available_for_all());

        // Tree with normal item in it, not always available.
        $structure->c[0] = (object)array('type' => 'mock');
        $tree = new \core_availability\tree($structure);
        $this->assertFalse($tree->is_available_for_all());

        // OR tree with one always-available item.
        $structure->c[1] = (object)array('type' => 'mock', 'all' => true);
        $tree = new \core_availability\tree($structure);
        $this->assertTrue($tree->is_available_for_all());

        // AND tree with one always-available and one not.
        $structure->op = '&';
        $structure->showc = array(true, true);
        unset($structure->show);
        $tree = new \core_availability\tree($structure);
        $this->assertFalse($tree->is_available_for_all());

        // Test NOT conditions (items not always-available).
        $structure->op = '!&';
        $structure->show = true;
        unset($structure->showc);
        $tree = new \core_availability\tree($structure);
        $this->assertFalse($tree->is_available_for_all());

        // Test again with one item always-available for NOT mode.
        $structure->c[1]->allnot = true;
        $tree = new \core_availability\tree($structure);
        $this->assertTrue($tree->is_available_for_all());
    }

    /**
     * Tests the get_full_information() function.
     */
    public function test_get_full_information() {
        // Setup.
        $modinfo = get_fast_modinfo(SITEID);
        $course = $modinfo->get_course();

        // No conditions.
        $structure = (object)array('op' => '|', 'show' => true, 'c' => array());
        $tree = new \core_availability\tree($structure);
        $this->assertEquals('', $tree->get_full_information($course, $modinfo));

        // Condition (normal and NOT).
        $structure->c = array(
                (object)array('type' => 'mock', 'm' => 'thing'));
        $tree = new \core_availability\tree($structure);
        $this->assertEquals('SA: [FULL]thing',
                $tree->get_full_information($course, $modinfo));
        $structure->op = '!&';
        $tree = new \core_availability\tree($structure);
        $this->assertEquals('SA: ![FULL]thing',
                $tree->get_full_information($course, $modinfo));

        // Complex structure.
        $structure->op = '|';
        $structure->c = array(
                (object)array('op' => '&', 'showc' => array(true, true),
                    'c' => array(
                        (object)array('type' => 'mock', 'm' => '1'),
                        (object)array('type' => 'mock', 'm' => '2')
                    )),
                (object)array('type' => 'mock', 'm' => 3));
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~<ul.*<ul.*<li.*1.*<li.*2.*</ul>.*<li.*3~',
                $tree->get_full_information($course, $modinfo));

        // Test intro messages before list. First, OR message.
        $structure->c = array(
                (object)array('type' => 'mock', 'm' => '1'),
                (object)array('type' => 'mock', 'm' => '2')
        );
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~Not available unless any of:.*<ul>~',
                $tree->get_full_information($course, $modinfo));

        // Now, OR message when not shown.
        $structure->show = false;
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~hidden.*<ul>~',
                $tree->get_full_information($course, $modinfo));

        // AND message.
        $structure->op = '&';
        unset($structure->show);
        $structure->showc = array(false, false);
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~Not available unless:.*<ul>~',
                $tree->get_full_information($course, $modinfo));

        // Hidden markers on items.
        $this->assertRegExp('~1.*hidden.*2.*hidden~',
                $tree->get_full_information($course, $modinfo));

        // Hidden markers on child tree and items.
        $structure->c[1] = (object)array('op' => '&', 'c' => array(
                (object)array('type' => 'mock', 'm' => '2'),
                (object)array('type' => 'mock', 'm' => '3')
        ));
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~1.*hidden.*All of \(hidden.*2.*3~',
                $tree->get_full_information($course, $modinfo));
        $structure->c[1]->op = '|';
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~1.*hidden.*Any of \(hidden.*2.*3~',
                $tree->get_full_information($course, $modinfo));

        // Hidden markers on single-item display, AND and OR.
        $structure->showc = array(false);
        $structure->c = array(
                (object)array('type' => 'mock', 'm' => '1')
        );
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~1.*hidden~',
                $tree->get_full_information($course, $modinfo));

        unset($structure->showc);
        $structure->show = false;
        $structure->op = '|';
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~1.*hidden~',
                $tree->get_full_information($course, $modinfo));

        // Hidden marker if single item is tree.
        $structure->c[0] = (object)array('op' => '&', 'c' => array(
                (object)array('type' => 'mock', 'm' => '1'),
                (object)array('type' => 'mock', 'm' => '2')
        ));
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~Not available \(hidden.*1.*2~',
                $tree->get_full_information($course, $modinfo));

        // Single item tree containing single item.
        unset($structure->c[0]->c[1]);
        $tree = new \core_availability\tree($structure);
        $this->assertRegExp('~SA.*1.*hidden~',
                $tree->get_full_information($course, $modinfo));
    }

    /**
     * Tests the get_all_children() function.
     */
    public function test_get_all_children() {
        // Create a tree with nothing in.
        $structure = (object)array('op' => '|', 'show' => true, 'c' => array());
        $tree1 = new \core_availability\tree($structure);

        // Create second tree with complex structure.
        $structure->c = array(
                (object)array('op' => '|', 'show' => true, 'c' => array(
                    (object)array('type' => 'mock', 'm' => '1'),
                    (object)array('type' => 'mock', 'm' => '2')
                )),
                (object)array('type' => 'mock', 'm' => 3));
        $tree2 = new \core_availability\tree($structure);

        // Check list of conditions from both trees.
        $this->assertEquals(array(), $tree1->get_all_children('core_availability\condition'));
        $result = $tree2->get_all_children('core_availability\condition');
        $this->assertEquals(3, count($result));
        $this->assertEquals('{mock:n,1}', (string)$result[0]);
        $this->assertEquals('{mock:n,2}', (string)$result[1]);
        $this->assertEquals('{mock:n,3}', (string)$result[2]);

        // Check specific type, should give same results.
        $result2 = $tree2->get_all_children('availability_mock\condition');
        $this->assertEquals($result, $result2);
    }

    /**
     * Tests the update_dependency_id() function.
     */
    public function test_update_dependency_id() {
        // Create tree with structure of 3 mocks.
        $structure = (object)array('op' => '|', 'show' => true, 'c' => array(
                (object)array('op' => '|', 'show' => true, 'c' => array(
                    (object)array('type' => 'mock', 'table' => 'frogs', 'id' => 9),
                    (object)array('type' => 'mock', 'table' => 'zombies', 'id' => 9)
                )),
                (object)array('type' => 'mock', 'table' => 'frogs', 'id' => 9)));

        // Get 'before' value.
        $tree = new \core_availability\tree($structure);
        $before = $tree->save();

        // Try replacing a table or id that isn't used.
        $this->assertFalse($tree->update_dependency_id('toads', 9, 13));
        $this->assertFalse($tree->update_dependency_id('frogs', 7, 8));
        $this->assertEquals($before, $tree->save());

        // Replace the zombies one.
        $this->assertTrue($tree->update_dependency_id('zombies', 9, 666));
        $after = $tree->save();
        $this->assertEquals(666, $after->c[0]->c[1]->id);

        // And the frogs one.
        $this->assertTrue($tree->update_dependency_id('frogs', 9, 3));
        $after = $tree->save();
        $this->assertEquals(3, $after->c[0]->c[0]->id);
        $this->assertEquals(3, $after->c[1]->id);
    }
}
