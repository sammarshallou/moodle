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
 * Front-end class.
 *
 * @package availability_date
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace availability_date;

defined('MOODLE_INTERNAL') || die();

/**
 * Front-end class.
 *
 * @package availability_date
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    protected function get_javascript_strings() {
        return array('ajaxerror', 'direction_before', 'direction_from', 'direction_until');
    }

    /**
     * Given field values, obtains the corresponding timestamp.
     *
     * @param int $year Year
     * @param int $month Month
     * @param int $day Day
     * @param int $hour Hour
     * @param int $minute Minute
     * @return int Timestamp
     */
    public static function get_time_from_fields($year, $month, $day, $hour, $minute) {
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $gregoriandate = $calendartype->convert_to_gregorian(
                $year, $month, $day, $hour, $minute);
        return make_timestamp($gregoriandate['year'], $gregoriandate['month'],
                $gregoriandate['day'], $gregoriandate['hour'], $gregoriandate['minute'], 0);
    }

    /**
     * Given a timestamp, obtains corresponding field values.
     *
     * @param int $time Timestamp
     * @return stdClass Object with fields for year, month, day, hour, minute
     */
    public static function get_fields_from_time($time) {
        $calendartype = \core_calendar\type_factory::get_calendar_instance();
        $wrongfields = $calendartype->timestamp_to_date_array($time);
        return array('day' => $wrongfields['mday'],
                'month' => $wrongfields['mon'], 'year' => $wrongfields['year'],
                'hour' => $wrongfields['hours'], 'minute' => $wrongfields['minutes']);
    }

    protected function get_javascript_init_params($course, \cm_info $cm = null,
            \section_info $section = null) {
        // Support internationalised calendars.
        $calendartype = \core_calendar\type_factory::get_calendar_instance();

        // Get current date, but set time to 00:00 (to make it easier to
        // specify whole days) and change name of mday field to match below.
        $wrongfields = $calendartype->timestamp_to_date_array(time());
        $current = array('day' => $wrongfields['mday'],
                'month' => $wrongfields['mon'], 'year' => $wrongfields['year'],
                'hour' => 0, 'minute' => 0);

        // Time part is handled the same everywhere.
        $hours = array();
        for ($i = 0; $i <= 23; $i++) {
            $hours[$i] = sprintf("%02d", $i);
        }
        $minutes = array();
        for ($i = 0; $i < 60; $i += 5) {
            $minutes[$i] = sprintf("%02d", $i);
        }

        // List date fields.
        $fields = $calendartype->get_date_order(
                $calendartype->get_min_year(), $calendartype->get_max_year());
        
        // Add time fields - in RTL mode these are switched.
        $fields['split'] = '/';
        if (right_to_left()) {
            $fields['minute'] = $minutes;
            $fields['colon'] = ':';
            $fields['hour'] = $hours;
        } else {
            $fields['hour'] = $hours;
            $fields['colon'] = ':';
            $fields['minute'] = $minutes;
        }

        // Output all date fields.
        $html = '<span class="availability-group">';
        foreach ($fields as $field => $options) {
            if ($options === '/') {
                $html = rtrim($html) . '</span> <span class="availability-group">';
                continue;
            }
            if ($options === ':') {
                $html .= ': ';
                continue;
            }
            $html .= \html_writer::start_tag('select', array('name' => $field));
            foreach ($options as $key => $value) {
                $params = array('value' => $key);
                if ($current[$field] == $key) {
                    $params['selected'] = 'selected';
                }
                $html .= \html_writer::tag('option', s($value), $params);
            }
            $html .= \html_writer::end_tag('select');
            $html .= ' ';
        }
        $html = rtrim($html) . '</span>';

        // Also get the time that corresponds to this default date.
        $time = self::get_time_from_fields($current['year'], $current['month'],
                $current['day'], $current['hour'], $current['minute']);

        return array($html, $time);
    }
}
