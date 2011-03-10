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
 * Unlocks a discussion.
 * @package forumngfeature
 * @subpackage lock
 * @copyright 2011 The Open University
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');
require_once($CFG->dirroot . '/mod/forumng/mod_forumng.php');

$d = required_param('d', PARAM_INT);
$cloneid = optional_param('clone', 0, PARAM_INT);

$discussion = mod_forumng_discussion::get_from_id($d, $cloneid);
$forum = $discussion->get_forum();
$cm = $forum->get_course_module();
$course = $forum->get_course();

// Check permission for change
$discussion->require_edit();

// Is this the actual unlock?
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $discussion->unlock();
    redirect('../../discuss.php?' . $discussion->get_link_params(mod_forumng::PARAM_PLAIN));
}

// Confirm page. Work out navigation for header
$pagename = get_string('unlockdiscussion', 'forumngfeature_lock',
    $discussion->get_subject(false));

$navigation = array();
$navigation[] = array(
    'name'=>shorten_text(htmlspecialchars(
        $discussion->get_subject())),
    'link'=>$discussion->get_url(), 'type'=>'forumng');
$navigation[] = array(
    'name'=>$pagename, 'type'=>'forumng');

$PAGEWILLCALLSKIPMAINDESTINATION = true;
print_header_simple(format_string($forum->get_name()) . ': ' . $pagename,
    "", build_navigation($navigation, $cm), "", "", true,
    '', navmenu($course, $cm));

print skip_main_destination();

// Show confirm option
$confirmstring = get_string('confirmunlock', 'forumngfeature_lock');
notice_yesno($confirmstring, 'unlock.php', '../../discuss.php',
    array('d'=>$discussion->get_id(), 'clone'=>$cloneid),
    array('d'=>$discussion->get_id(), 'clone'=>$cloneid),
    'post', 'get');

// Display footer
print_footer($course);
