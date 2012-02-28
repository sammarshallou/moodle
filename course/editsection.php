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
 * Edit the introduction of a section
 *
 * @copyright 1999 Martin Dougiamas  http://dougiamas.com
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @package course
 */

require_once("../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/conditionlib.php');

require_once('editsection_form.php');

$id = required_param('id',PARAM_INT);    // Week/topic ID

$PAGE->set_url('/course/editsection.php', array('id'=>$id));

$section = $DB->get_record('course_sections', array('id' => $id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $section->course), '*', MUST_EXIST);

require_login($course);
$context = get_context_instance(CONTEXT_COURSE, $course->id);
require_capability('moodle/course:update', $context);

$editoroptions = array('context'=>$context ,'maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes'=>$CFG->maxbytes, 'trusttext'=>false, 'noclean'=>true);
$section = file_prepare_standard_editor($section, 'summary', $editoroptions, $context, 'course', 'section', $section->id);
$section->usedefaultname = (is_null($section->name));

// let's preload availability conditions
if (!empty($CFG->enableavailability)) {
    // get section availability conditions from secinfo
    $sections = get_all_sections_secinfo($course->id);
    $sectionno = $section->section;
    $section->conditionsgrade = $sections[$sectionno]->conditionsgrade;
    $section->conditionscompletion = $sections[$sectionno]->conditionscompletion;
}

$mform = new editsection_form(null, array('course'=>$course, 'editoroptions'=>$editoroptions, 'cs'=>$section, 'showavailability'=>isset($section->showavailability) ? $section->showavailability  : null));
$mform->set_data($section); // set current value

/// If data submitted, then process and store.
if ($mform->is_cancelled()){
    redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);

} else if ($data = $mform->get_data()) {
    if (empty($data->usedefaultname)) {
        $section->name = $data->name;
    } else {
        $section->name = null;
    }
    $data = file_postupdate_standard_editor($data, 'summary', $editoroptions, $context, 'course', 'section', $section->id);
    $section->summary = $data->summary;
    $section->summaryformat = $data->summaryformat;
    if (!empty($CFG->enableavailability)) {
        $section->availablefrom      = $data->availablefrom;
        $section->availableuntil     = $data->availableuntil;
        $section->groupingid         = $data->groupingid;
        $section->showavailability   = $data->showavailability;
    }
    $DB->update_record('course_sections', $section);
    if (!empty($CFG->enableavailability)) {
        //updating grade & completion conditions table
        //first let's delete existing conditions for this section from db
        $DB->delete_records('course_sections_availability', array('coursesectionid' => $section->id));
        //now insert new conditions received from user
        if (!empty($data->conditiongradegroup)) {
            foreach ($data->conditiongradegroup as $groupvalue) {
                if ($groupvalue['conditiongradeitemid'] > 0) {
                    $datacg = new stdClass;
                    $datacg->coursesectionid = $section->id;
                    $datacg->gradeitemid = $groupvalue['conditiongradeitemid'];
                    $datacg->grademin = $groupvalue['conditiongrademin'];
                    $datacg->grademax = $groupvalue['conditiongrademax'];
                    $DB->insert_record('course_sections_availability', $datacg);
                }
            }
        }
        if (!empty($data->conditioncompletiongroup)) {
            foreach ($data->conditioncompletiongroup as $groupvalue) {
                if ($groupvalue['conditionsourcecmid'] > 0) {
                    $datacg = new stdClass;
                    $datacg->coursesectionid = $section->id;
                    $datacg->sourcecmid = $groupvalue['conditionsourcecmid'];
                    $datacg->requiredcompletion = $groupvalue['conditionrequiredcompletion'];
                    $DB->insert_record('course_sections_availability', $datacg);
                }
            }
        }
    }
    rebuild_course_secinfo($course->id);

    add_to_log($course->id, "course", "editsection", "editsection.php?id=$section->id", "$section->section");
    $PAGE->navigation->clear_cache();
    redirect("view.php?id=$course->id");
}

$sectionname  = get_section_name($course, $section);
$stredit      = get_string('edita', '', " $sectionname");
$strsummaryof = get_string('summaryof', '', " $sectionname");

$PAGE->set_title($stredit);
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add($stredit);
echo $OUTPUT->header();

echo $OUTPUT->heading($strsummaryof);

$mform->display();
echo $OUTPUT->footer();
