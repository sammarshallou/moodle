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
 * This file contains unit tests for the 'task running' data.
 *
 * @package core
 * @category test
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\task;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/task_fixtures.php');

/**
 * This file contains unit tests for the 'task running' data.
 *
 * @copyright 2019 The Open University
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task_running_testcase extends \advanced_testcase {

    /**
     * Test for ad-hoc tasks.
     */
    public function test_adhoc_task_running() {
        $this->resetAfterTest();

        // Specify lock factory. The reason is that Postgres locks don't work within a single
        // process (i.e. if you try to get a lock that you already locked, it will just let you)
        // which is usually OK but not here where we are simulating running two tasks at once in
        // the same process.
        set_config('lock_factory', '\core\lock\db_record_lock_factory');

        // Create and queue 2 new ad-hoc tasks.
        manager::queue_adhoc_task(new adhoc_test_task());
        manager::queue_adhoc_task(new adhoc_test2_task());

        // Check no tasks are marked running.
        $running = manager::get_running_tasks();
        $this->assertEmpty($running->adhoc);

        // Mark the first task running and check results. Because adhoc tasks are pseudo-randomly
        // shuffled, it is safer if we can cope with either of them being first.
        $before = time();
        $task = manager::get_next_adhoc_task(time());
        $task2first = get_class($task) === 'core\task\adhoc_test2_task';
        manager::adhoc_task_starting($task);
        $after = time();
        $running = manager::get_running_tasks();
        $this->assertCount(1, $running->adhoc);
        $this->assertInstanceOf($task2first ? 'core\task\adhoc_test2_task' : 'core\task\adhoc_test_task',
                $running->adhoc[0]->task);
        $this->assertLessThanOrEqual($after, $running->adhoc[0]->started);
        $this->assertGreaterThanOrEqual($before, $running->adhoc[0]->started);

        // Mark the second task running and check results.
        $task2 = manager::get_next_adhoc_task(time());
        manager::adhoc_task_starting($task2);
        $running = manager::get_running_tasks();
        $this->assertCount(2, $running->adhoc);
        if ($task2first) {
            $this->assertInstanceOf('core\task\adhoc_test2_task', $running->adhoc[0]->task);
            $this->assertInstanceOf('core\task\adhoc_test_task', $running->adhoc[1]->task);
        } else {
            $this->assertInstanceOf('core\task\adhoc_test_task', $running->adhoc[0]->task);
            $this->assertInstanceOf('core\task\adhoc_test2_task', $running->adhoc[1]->task);
        }

        // Second task completes successfully.
        manager::adhoc_task_complete($task2);
        $running = manager::get_running_tasks();
        $this->assertCount(1, $running->adhoc);
        $this->assertInstanceOf($task2first ? 'core\task\adhoc_test2_task' : 'core\task\adhoc_test_task',
                $running->adhoc[0]->task);

        // First task fails.
        manager::adhoc_task_failed($task);
        $running = manager::get_running_tasks();
        $this->assertCount(0, $running->adhoc);
    }

    /**
     * Test for scheduled tasks.
     */
    public function test_scheduled_task_running() {
        global $DB;
        $this->resetAfterTest();

        // Check no tasks are marked running.
        $running = manager::get_running_tasks();
        $this->assertEmpty($running->scheduled);

        // Disable all the tasks, except two, and set those two due to run.
        $DB->set_field_select('task_scheduled', 'disabled', 1, 'classname != ? AND classname != ?',
                ['\core\task\session_cleanup_task', '\core\task\file_trash_cleanup_task']);
        $DB->set_field('task_scheduled', 'nextruntime', 1,
                ['classname' => '\core\task\session_cleanup_task']);
        $DB->set_field('task_scheduled', 'nextruntime', 1,
                ['classname' => '\core\task\file_trash_cleanup_task']);
        $DB->set_field('task_scheduled', 'lastruntime', time() - 1000,
                ['classname' => '\core\task\session_cleanup_task']);
        $DB->set_field('task_scheduled', 'lastruntime', time() - 500,
                ['classname' => '\core\task\file_trash_cleanup_task']);

        // Get the first task and start it off.
        $task1 = manager::get_next_scheduled_task(time());
        $before = time();
        manager::scheduled_task_starting($task1);
        $after = time();
        $running = manager::get_running_tasks();
        $this->assertCount(1, $running->scheduled);
        $this->assertLessThanOrEqual($after, $running->scheduled[0]->started);
        $this->assertGreaterThanOrEqual($before, $running->scheduled[0]->started);
        $this->assertInstanceOf('core\task\session_cleanup_task', $running->scheduled[0]->task);

        // Mark the second task running and check results. We have to change the times so the other
        // one comes up first, otherwise it repeats the same one.
        $DB->set_field('task_scheduled', 'lastruntime', time() - 1500,
                ['classname' => '\core\task\file_trash_cleanup_task']);
        $task2 = manager::get_next_scheduled_task(time());
        manager::scheduled_task_starting($task2);
        $running = manager::get_running_tasks();
        $this->assertCount(2, $running->scheduled);
        $this->assertInstanceOf('core\task\file_trash_cleanup_task', $running->scheduled[0]->task);
        $this->assertInstanceOf('core\task\session_cleanup_task', $running->scheduled[1]->task);

        // Complete the file trash one.
        manager::scheduled_task_complete($task2);
        $running = manager::get_running_tasks();
        $this->assertCount(1, $running->scheduled);

        // Other task fails.
        manager::scheduled_task_failed($task1);
        $running = manager::get_running_tasks();
        $this->assertCount(0, $running->scheduled);
    }
}
