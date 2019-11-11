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
 * Behat step definitions for scheduled task administration.
 *
 * @package tool_task
 * @copyright 2017 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

use \Behat\Gherkin\Node\TableNode;

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Behat step definitions for scheduled task administration.
 *
 * @package tool_task
 * @copyright 2017 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_tool_task extends behat_base {

    /**
     * Set a fake fail delay for a scheduled task.
     *
     * @Given /^the scheduled task "(?P<task_name>[^"]+)" has a fail delay of "(?P<seconds_number>\d+)" seconds$/
     * @param string $task Task classname
     * @param int $seconds Fail delay time in seconds
     */
    public function scheduled_task_has_fail_delay_seconds($task, $seconds) {
        global $DB;
        $id = $DB->get_field('task_scheduled', 'id', ['classname' => $task], IGNORE_MISSING);
        if (!$id) {
            throw new Exception('Unknown scheduled task: ' . $task);
        }
        $DB->set_field('task_scheduled', 'faildelay', $seconds, ['id' => $id]);
    }

    /**
     * Set up some tasks to be 'running' (not really).
     *
     * @param TableNode $data List of tasks
     * @Given /^I pretend that the following tasks are running:$/
     */
    public function i_pretend_that_the_following_tasks_are_running(TableNode $data) {
        global $DB;

        foreach ($data->getHash() as $rowdata) {
            // Check and get the data from the user-entered row.
            $fields = array_flip(['type', 'classname', 'seconds']);
            foreach ($rowdata as $key => $value) {
                if (!array_key_exists($key, $fields)) {
                    throw new Exception('Field "' . $key . '" does not exist');
                }
                if ($value === '' && in_array($key, ['classname', 'type'])) {
                    // You can set the seconds field blank to 'stop' it running.
                    throw new Exception('Field "' . $key . '" is blank');
                }
            }
            foreach (['classname', 'type'] as $requiredfield) {
                if (!array_key_exists($requiredfield, $rowdata) ||
                        $rowdata[$requiredfield] === '') {
                    throw new Exception('Field "' . $key . '" must be set');
                }
            }

            // Default values.
            $adhoc = $rowdata['type'] === 'adhoc';
            $scheduled = $rowdata['type'] === 'scheduled';
            if (!$adhoc && !$scheduled) {
                throw new Exception('Type must be adhoc or scheduled');
            }
            if (!array_key_exists('seconds', $rowdata)) {
                $rowdata['seconds'] = '1';
            }

            // Get start time (or null if stopping).
            if ($rowdata['seconds']) {
                $starttime = time() - $rowdata['seconds'];
            } else {
                $starttime = null;
            }

            if ($scheduled) {
                // For scheduled tasks, find the matching row and set it.
                $DB->set_field('task_scheduled', 'running', $starttime,
                        ['classname' => $rowdata['classname']]);
            } else {
                $task = new $rowdata['classname'];
                // For ad-hoc tasks, add or delete a row.
                if ($starttime) {
                    $faketask = [
                        'component' => $task->get_component(),
                        'classname' => $rowdata['classname'],
                        'nextruntime' => 0,
                        'blocking' => 0,
                        'running' => $starttime
                    ];
                    $DB->insert_record('task_adhoc', $faketask);
                } else {
                    $DB->delete_records('task_adhoc', ['classname' => $rowdata['classname'],
                            'nextruntime' => 0]);
                }
            }
        }
    }
}
