<?php

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');

class editsection_form extends moodleform {

    function definition() {

        $mform  = $this->_form;
        $course = $this->_customdata['course'];
        $mform->addElement('checkbox', 'usedefaultname', get_string('sectionusedefaultname'));
        $mform->setDefault('usedefaultname', true);

        $mform->addElement('text', 'name', get_string('sectionname'), array('size'=>'30'));
        $mform->setType('name', PARAM_TEXT);
        $mform->disabledIf('name','usedefaultname','checked');

        /// Prepare course and the editor

        $mform->addElement('editor', 'summary_editor', get_string('summary'), null, $this->_customdata['editoroptions']);
        $mform->addHelpButton('summary_editor', 'summary');
        $mform->setType('summary_editor', PARAM_RAW);

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->_registerCancelButton('cancel');

    }

    function definition_after_data() {
        global $CFG, $DB;

        $mform  = $this->_form;
        $course = $this->_customdata['course'];

        if (!empty($CFG->enableavailability)) {
            // Conditional availability

            $options = array();
            $options[0] = get_string('none');
            if ($groupings = $DB->get_records('groupings', array('courseid' => $course->id))) {
                foreach ($groupings as $grouping) {
                    $options[$grouping->id] = format_string($grouping->name);
                }
            }
            $mform->addElement('header', '', get_string('availabilityconditions', 'condition'));
            $mform->addElement('select', 'groupingid', get_string('groupingsection', 'group'), $options);
            $mform->addHelpButton('groupingid', 'groupingsection', 'group');
            $mform->addElement('date_time_selector', 'availablefrom', get_string('availablefrom', 'condition'), array('optional' => true));
            $mform->addElement('date_time_selector', 'availableuntil', get_string('availableuntil', 'condition'), array('optional' => true));

            // Conditions based on grades
            $gradeoptions = array();
            $items = grade_item::fetch_all(array('courseid' => $course->id));
            $items = $items ? $items : array();
            foreach($items as $id => $item) {
                // Do not include grades for current item - TO DO
                $gradeoptions[$id] = $item->get_name();
            }
            asort($gradeoptions);
            $gradeoptions = array(0 => get_string('none', 'condition')) + $gradeoptions;

            $grouparray = array();
            $grouparray[] = $mform->createElement('select', 'conditiongradeitemid', '', $gradeoptions);
            $grouparray[] = $mform->createElement('static', '', '', ' '.get_string('grade_atleast','condition').' ');
            $grouparray[] = $mform->createElement('text', 'conditiongrademin', '', array('size' => 3));
            $grouparray[] = $mform->createElement('static', '', '', '% '.get_string('grade_upto','condition').' ');
            $grouparray[] = $mform->createElement('text', 'conditiongrademax', '', array('size' => 3));
            $grouparray[] = $mform->createElement('static', '', '', '%');
            $mform->setType('conditiongrademin', PARAM_FLOAT);
            $mform->setType('conditiongrademax', PARAM_FLOAT);
            $group = $mform->createElement('group', 'conditiongradegroup',
                get_string('gradecondition', 'condition'), $grouparray);

            // Get version with condition info and store it so we don't ask
            // twice
            if(!empty($this->_customdata['cs'])) {
                $this->_customdata['cs']->objtype = CONDITION_OBJECT_SECTION;
                $ci = new condition_info($this->_customdata['cs']);
                $fullcs = $ci->get_full_course_mors();
                $count = count($fullcs->conditionsgrade)+1;
            } else {
                $count = 1;
            }

            $this->repeat_elements(array($group), $count, array(), 'conditiongraderepeats', 'conditiongradeadds', 2,
                                   get_string('addgrades', 'condition'), true);
            $mform->addHelpButton('conditiongradegroup[0]', 'gradecondition', 'condition');

            // Conditions based on completion
            $completion = new completion_info($course);
            if ($completion->is_enabled()) {
                $completionoptions = array();
                $modinfo = get_fast_modinfo($course);
                foreach($modinfo->cms as $id => $cm) {
                    // Add each course-module if it:
                    // (a) has completion turned on
                    // (b) does not belong to current course-section
                    if ($cm->completion && (empty($course) || $this->_customdata['cs']->id != $cm->section)) {
                        $completionoptions[$id] = $cm->name;
                    }
                }
                asort($completionoptions);
                $completionoptions = array(0 => get_string('none', 'condition')) + $completionoptions;

                $completionvalues = array(
                    COMPLETION_COMPLETE => get_string('completion_complete', 'condition'),
                    COMPLETION_INCOMPLETE => get_string('completion_incomplete', 'condition'),
                    COMPLETION_COMPLETE_PASS => get_string('completion_pass', 'condition'),
                    COMPLETION_COMPLETE_FAIL => get_string('completion_fail', 'condition'));

                $grouparray = array();
                $grouparray[] = $mform->createElement('select', 'conditionsourcecmid', '', $completionoptions);
                $grouparray[] = $mform->createElement('select', 'conditionrequiredcompletion', '', $completionvalues);
                $group = $mform->createElement('group', 'conditioncompletiongroup',
                    get_string('completioncondition', 'condition'), $grouparray);

                $count = empty($fullcs) ? 1 : count($fullcs->conditionscompletion)+1;
                $this->repeat_elements(array($group), $count,array(),
                    'conditioncompletionrepeats', 'conditioncompletionadds', 2,
                    get_string('addcompletions', 'condition'), true);
                $mform->addHelpButton('conditioncompletiongroup[0]', 'completionconditionsection', 'condition');
            }

            // Availability conditions - set up form values
            if (!empty($CFG->enableavailability) && $this->_customdata['cs']) {
                $num = 0;
                foreach($fullcs->conditionsgrade as $gradeitemid => $minmax) {
                    $groupelements = $mform->getElement('conditiongradegroup['.$num.']')->getElements();
                    $groupelements[0]->setValue($gradeitemid);
                    // These numbers are always in the format 0.00000 - the rtrims remove any final zeros and,
                    // if it is a whole number, the decimal place.
                    $groupelements[2]->setValue(is_null($minmax->min) ? '' : rtrim(rtrim($minmax->min,'0'), '.'));
                    $groupelements[4]->setValue(is_null($minmax->max) ? '' : rtrim(rtrim($minmax->max,'0'), '.'));
                    $num++;
                }

                if ($completion->is_enabled()) {
                    $num = 0;
                    foreach($fullcs->conditionscompletion as $othercmid => $state) {
                        $groupelements = $mform->getElement('conditioncompletiongroup['.$num.']')->getElements();
                        $groupelements[0]->setValue($othercmid);
                        $groupelements[1]->setValue($state);
                        $num++;
                    }
                }
            }

            // Do we display availability info to students?
            $mform->addElement('select', 'showavailability', get_string('showavailabilitysection', 'condition'),
                array(CONDITION_STUDENTVIEW_SHOW => get_string('showavailabilitysection_show', 'condition'),
                CONDITION_STUDENTVIEW_HIDE => get_string('showavailabilitysection_hide', 'condition')));

            if (isset($this->_customdata['showavailability'])) {
                $mform->setDefault('showavailability', $this->_customdata['showavailability']);
            } else {
                $mform->setDefault('showavailability', CONDITION_STUDENTVIEW_SHOW);
            }
        }

//--------------------------------------------------------------------------------
        $this->add_action_buttons();

    }

    // form verification
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        // Conditions: Don't let them set dates which make no sense 
        if (array_key_exists('availablefrom', $data) &&
            $data['availablefrom'] && $data['availableuntil'] &&
            $data['availablefrom'] > $data['availableuntil']) {
            $errors['availablefrom'] = get_string('badavailabledates', 'condition');
        }

        return $errors;
    }

}
