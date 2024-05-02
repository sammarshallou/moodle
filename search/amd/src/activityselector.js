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
 * Activity selector for the reindex form.
 *
 * @module core_search/activityselector
 * @copyright 2024 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Obtains list of activities for Ajax autocomplete element.
 *
 * @param {String} selector Selector of the element
 * @param {String} query The query string
 * @param {Function} success Callback function to be called with an array of results
 * @param {Function} failure Callback to be called in case of failure, with error message
 */
export async function transport(selector, query, success, failure) {
    // Get course id.
    const element = document.querySelector(selector);
    const courseSelection = element.closest('form').querySelector(
        'select[name=courseid] ~ .form-autocomplete-selection');
    const courseId = parseInt(courseSelection.dataset.activeValue);

    // Do AJAX request to list activities on course matching query.
    try {
        const response = await Ajax.call([{methodname: 'core_search_get_course_activities', args: {
            courseid: courseId,
            query: query
        }}]);
        success(response);
    } catch (e) {
        failure(e);
    }
}

/**
 * Processes results for Ajax autocomplete element.
 *
 * @param {String} selector Selector of the element
 * @param {Array} results Array of results
 * @return {Object[]} Array of results with 'value' and 'label' fields
 */
export function processResults(selector, results) {
    const output = [];
    for (let result of results) {
        output.push({value: result.cmid, label: result.name});
    }
    return output;
}
