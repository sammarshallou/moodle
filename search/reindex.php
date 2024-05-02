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
 * Manually request a reindex of a particular context for cases when the index is incorrect.
 *
 * @package core_search
 * @copyright 2024 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(__DIR__ . '/../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('searchreindex');

$PAGE->set_primary_active_tab('siteadminnode');

$mform = new core_search\form\reindex();

if ($mform->get_data()) {

}

echo $OUTPUT->header();

// Throw an error if search indexing is off - we don't show the page link in that case.
if (!\core_search\manager::is_indexing_enabled()) {
    throw new \moodle_exception('notavailable');
}

echo $mform->render();

echo $OUTPUT->footer();
