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
 * External/AJAX functions.
 *
 * @package tool_task
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_task;

defined('MOODLE_INTERNAL') || die();

/**
 * External/AJAX functions.
 *
 * @package tool_task
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {
    /**
     * Returns description of get_running_tasks() parameters (there aren't any).
     *
     * @return \external_function_parameters
     */
    public static function get_running_tasks_parameters(): \external_function_parameters {
        return new \external_function_parameters([]);
    }

    /**
     * Gets the current running tasks.
     *
     * @return array Data
     */
    public static function get_running_tasks(): array {
        global $PAGE;

        self::validate_context(\context_system::instance());

        // Make sure the renderer is loaded, but it's a static function so no need for the object.
        $PAGE->get_renderer('tool_task');
        return \tool_task_renderer::get_running_tasks_data(\core\task\manager::get_running_tasks());
    }

    /**
     * Returns description of get_running_tasks() result value.
     *
     * @return \external_description
     */
    public static function get_running_tasks_returns(): \external_description {
        return new \external_single_structure([
                'scheduled' => new \external_multiple_structure(
                    new \external_single_structure([
                        'taskname' => new \external_value(PARAM_RAW, 'Display name of task'),
                        'taskclass' => new \external_value(PARAM_RAW, 'Class name of task'),
                        'component' => new \external_value(PARAM_RAW, 'Display name of component'),
                        'time' => new \external_value(PARAM_RAW, 'Remaining time in display format'),
                        'started' => new \external_value(PARAM_INT, 'Time task started (seconds since epoch)'),
                    ])
                ),
                'adhoc' => new \external_multiple_structure(
                    new \external_single_structure([
                        'taskclass' => new \external_value(PARAM_RAW, 'Class name of task'),
                        'component' => new \external_value(PARAM_RAW, 'Display name of component'),
                        'time' => new \external_value(PARAM_RAW, 'Remaining time in display format'),
                        'started' => new \external_value(PARAM_INT, 'Time task started (seconds since epoch)'),
                    ])
                ),
                'anytasks' => new \external_value(PARAM_BOOL, 'True if there are any running tasks'),
                'lastupdated' => new \external_value(PARAM_INT, 'Time of update (now)')
        ]);
    }
}
