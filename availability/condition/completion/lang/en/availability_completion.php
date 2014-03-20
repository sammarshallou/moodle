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
 * Language strings.
 *
AMOS BEGIN
 CPY [completion_complete,core_condition],[option_complete,availability_completion]
 CPY [completion_fail,core_condition],[option_fail,availability_completion]
 CPY [completion_incomplete,core_condition],[option_incomplete,availability_completion]
 CPY [completion_pass,core_condition],[option_pass,availability_completion]
AMOS END
 * @package availability_completion
 * @copyright 2014 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['description'] = 'Require students to complete (or not complete) an activity.';
$string['missing'] = '(Missing activity)';
$string['option_complete'] = 'must be marked complete';
$string['option_fail'] = 'must be complete with fail grade';
$string['option_incomplete'] = 'must not be marked complete';
$string['option_pass'] = 'must be complete with pass grade';
$string['requires_0'] = 'The activity <strong>{$a}</strong> is incomplete';
$string['requires_1'] = 'The activity <strong>{$a}</strong> is marked complete';
$string['requires_2'] = 'The activity <strong>{$a}</strong> is complete and passed';
$string['requires_3'] = 'The activity <strong>{$a}</strong> is complete and failed';
$string['requires_not2'] = 'The activity <strong>{$a}</strong> is not complete and passed';
$string['requires_not3'] = 'The activity <strong>{$a}</strong> is not complete and failed';
$string['title'] = 'Activity completion';
