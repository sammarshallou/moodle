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

namespace core_search\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

/**
 * External function for listing activities on a course.
 *
 * @package core_search
 * @copyright 2024 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_course_activities extends external_api {

    /**
     * Webservice parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters(
            [
                'courseid' => new external_value(PARAM_INT, 'Course id'),
                'query' => new external_value(PARAM_NOTAGS, 'Optional query (blank = all activities)'),
            ]
        );
    }

    /**
     * Webservice returns.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(new external_single_structure([
            'cmid' => new external_value(PARAM_INT, 'Course-module id'),
            'name' => new external_value(PARAM_INT, 'Activity name e.g. "General discussion forum"'),
            'modname' => new external_value(PARAM_ALPHANUMEXT, 'Module name e.g. "forum"'),
        ]));
    }

    /**
     * Gets activities on a course.
     *
     * @param int $courseid Course id
     * @param string $query Optional search query ('' = all)
     * @return array List of activities
     */
    public static function execute(int $courseid, string $query): array {
        global $PAGE;

        ['courseid' => $courseid, 'query' => $query] = self::validate_parameters(
            self::execute_parameters(),
            [
                'courseid' => $courseid,
                'query' => $query,
            ]
        );

        // Get the context. This should ensure that user is allowed to access the course.
        $context = \context_course::instance($courseid);
        external_api::validate_context($context);

        // Get details for all activities in course.
        $modinfo = get_fast_modinfo($courseid);
        $results = [];
        $lowerquery = \core_text::strtolower($query);
        foreach ($modinfo->get_cms() as $cm) {
            // When there is a query, skip activities that don't match it.
            if ($lowerquery !== '') {
                $lowername = \core_text::strtolower($cm->name);
                if (strpos($lowername, $lowerquery) === false) {
                    continue;
                }
            }
            $results[] = (object)[
                'cmid' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
            ];
        }

        return $results;
    }
}
