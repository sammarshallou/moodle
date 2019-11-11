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
 * JavaScript for scheduled tasks page. At the moment this just lets you refresh the current
 * running tasks.
 *
 * @module tool_task/scheduledtasks
 * @package tool_task
 * @copyright 2019 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since 3.9
 */

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';

/**
 * Button used to refresh running tasks.
 */
let refreshRunningTasksButton;

/**
 * Handles a button click.
 */
function buttonHandler() {
    refreshRunningTasksButton.disabled = true;
    M.util.js_pending('tool_task_refresh');

    const showError = () => {
        refreshRunningTasksButton.disabled = false;
        M.util.js_complete('tool_task_refresh');
        Notification.alert(M.util.get_string('error', 'moodle'),
            M.util.get_string('error_loading', 'tool_task'), M.util.get_string('ok', 'moodle'));
    };

    Ajax.call([{
        methodname: 'tool_task_get_running_tasks',
        args: {},
        done: (data) => {
            Templates.render('tool_task/running', data).then((result) => {
                document.querySelector('.tool_task_running').outerHTML = result;
                init();
                refreshRunningTasksButton.disabled = false;
                M.util.js_complete('tool_task_refresh');
                return null;
            }).catch(showError);
        },
        fail: showError
    }]);
}

/**
 * Initialises the button listener.
 */
export const init = () => {
    refreshRunningTasksButton = document.getElementById('tool_task_refresh');
    refreshRunningTasksButton.addEventListener('click', () => {
        buttonHandler();
    });
};
