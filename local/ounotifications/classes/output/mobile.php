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

namespace local_ounotifications\output;

/**
 * Mobile output functions.
 */
class mobile {

    /**
     * Returns the unused menu page.
     *
     * @param array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     */
    public static function main_view(array $args): array {
        require_login();
        return [
            'templates' => [
                [
                    'id' => 'main',
                    'html' => '<p>Frog</p>',
                ],
            ],
            'javascript' => '',
            'otherdata' => [],
            'files' => []
        ];
    }

    /**
     * Returns shared (global) templates and information for the feature.
     *
     * @param array $args Arguments (empty)
     * @return array Array with information required by app
     */
    public static function main_init(array $args): array {
        global $CFG;
        $lines = preg_split('~\s*\n+\s*~', trim(get_config('local_ounotifications', 'upcomingevents')));
        $events = [];
        foreach ($lines as $line) {
            // Skip comments.
            if (preg_match('~^\#~', $line)) {
                continue;
            }
            //* 42 2020-11-26 14:42:00 course/view.php?id=123 Title which may not contain commas, second line of text
            if (preg_match('~^([0-9]+)\s+([0-9]{4}-[0-9]{2}-[0-9]{2}\s+[0-9]{2}:[0-9]{2}:[0-9]{2})\s+([^ ]+)\s+([^,]+),\s*(.*)$~', $line, $matches)) {
                [, $id, $datetime, $url, $title, $text] = $matches;

                $tz = \core_date::get_server_timezone_object();
                $timestamp = (new \DateTime($datetime, $tz))->getTimestamp();
                $events[] = (object)['id' => $id, 'timestamp' => $timestamp,
                        'url' => $CFG->wwwroot . '/' . $url, 'title' => $title, 'text' => $text];
            }
        }

        return [
            'templates' => [],
            'javascript' => file_get_contents($CFG->dirroot .'/local/ounotifications/appjs/init.js'),
            'otherdata' => ['events' => json_encode($events)],
            'files' => []
        ];
    }
}
