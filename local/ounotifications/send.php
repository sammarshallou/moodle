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

namespace local_ounotifications;

require('../../config.php');
require_once($CFG->libdir . '/formslib.php');

$PAGE->set_context(\context_system::instance());
$url = new \moodle_url('/local/ounotifications/send.php');
$PAGE->set_url($url);

$title = get_string('send', 'local_ounotifications');
$PAGE->set_title($title);

// We'll let just anyone trigger this since it is for testing.
require_admin();

// Define a form
class test_form extends \moodleform {

    function definition() {
        $mform =& $this->_form;

        $options = [
                'ajax' => 'tool_dataprivacy/form-user-selector',
                'valuehtmlcallback' => function($value) {
                    global $OUTPUT;

                    $allusernames = get_all_user_name_fields(true);
                    $fields = 'id, email, ' . $allusernames;
                    $user = \core_user::get_user($value, $fields);
                    $useroptiondata = [
                            'fullname' => fullname($user),
                            'email' => $user->email
                    ];
                    return $OUTPUT->render_from_template('tool_dataprivacy/form-user-selector-suggestion', $useroptiondata);
                }
        ];
        $mform->addElement('autocomplete', 'userid', get_string('user', 'local_ounotifications'), [], $options);
        $mform->addRule('userid', null, 'required', null, 'client');
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('text', 'tma', get_string('tma', 'local_ounotifications'));
        $mform->addRule('tma', null, 'required', null, 'client');
        $mform->setType('tma', PARAM_INT);
        $mform->setDefault('tma', '42');

        $mform->addElement('text', 'date', get_string('date', 'local_ounotifications'));
        $mform->addRule('date', null, 'required', null, 'client');
        $mform->setType('date', PARAM_TEXT);
        $mform->setDefault('date', 'Frogsday, 34th October');

        $mform->addElement('text', 'url', get_string('url', 'local_ounotifications'));
        $mform->addRule('url', null, 'required', null, 'client');
        $mform->setType('url', PARAM_RAW);
        $mform->setDefault('url', 'course/view.php?id=2');

        $this->add_action_buttons();
    }
}

echo $OUTPUT->header();

echo $OUTPUT->heading($title);

$mform = new test_form();
if ($data = $mform->get_data()) {
    $user = $DB->get_record('user', ['id' => $data->userid], '*', MUST_EXIST);

    $message = new \core\message\message();
    $message->component = 'local_ounotifications';
    $message->name = 'tmadue';
    $message->userfrom = \core_user::get_noreply_user();
    $message->userto = $user;
    $message->subject = 'TMA ' . $data->tma . ' due soon (B747-21K)';
    $message->fullmessage = 'Unused I think? (1)'; // Not used by the mobile notifications.
    $message->fullmessageformat = FORMAT_PLAIN;
    $message->fullmessagehtml = 'Unused I think? (2)'; // Not used by the mobile notifications.
    $message->smallmessage = 'Remember to submit your TMA by 12:00 midday on ' . $data->date;
    $message->notification = 1;
    $message->contexturl = $CFG->wwwroot . '/' . $data->url;
    $message->contexturlname = 'Unused I think? (3)';

    $message->customdata = ['appurl' => $message->contexturl];

    $messageid = message_send($message);

    echo $OUTPUT->notification(get_string('sent', 'local_ounotifications', $messageid), 'notifysuccess');
}

$mform->display();

echo $OUTPUT->footer();
